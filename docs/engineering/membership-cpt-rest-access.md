---
title: "Membership CPT REST Access — Engineering Reference"
audience: [developer, agent]
php_class: Membership_Post_Types
source_files:
  - "includes/Membership_Post_Types.php"
  - "includes/Api_Key_Access.php"
  - "includes/Settings.php"
---

# Membership CPT REST Access

How the three membership post types are exposed over `wp/v2`, what guards them, and how approved WooCommerce API keys are admitted.

## Endpoints

| CPT | Route | `show_in_rest` |
|---|---|---|
| `wicket_membership` | `/wp/v2/wicket_membership` | `true` |
| `wicket_mship_config` | `/wp/v2/wicket_mship_config` | `true` |
| `wicket_mship_tier` | `/wp/v2/wicket_mship_tier` | `true` |

Collection routes and single-record sub-routes (`/<base>/{id}`) are both covered.

## The capability gate

`Membership_Post_Types::restrict_membership_rest_access()`, hooked to `rest_pre_dispatch` at priority 10.

```php
if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) { /* 401 / 403 */ }
```

**The gate is auth-method agnostic.** It asks only whether a user is resolved and holds `manage_options` — never *how* they authenticated. There is no allowlist of authentication mechanisms anywhere in the plugin. Cookie + nonce, Application Password and WooCommerce consumer keys all arrive here identically.

Historically these endpoints were reachable only by Application Password for server-to-server callers. That was emergent, not enforced: a cookie needs a browser session, and WooCommerce refused to authenticate its keys on non-WooCommerce routes.

Returns `401` when no user was resolved and `403` when one was but lacks the capability (`rest_authorization_required_code()`).

## Admitting WooCommerce API keys

`WC_REST_Authentication::authenticate()` returns before inspecting credentials unless `is_request_to_rest_api()` is true, and that only matches URIs containing `wp-json/wc/` or `wp-json/wc-` (`class-wc-rest-authentication.php:69-84`). The `woocommerce_rest_is_request_to_rest_api` filter is the only seam for widening it.

`Membership_Post_Types::allow_woocommerce_key_auth()` returns `true` only when **all five** hold, evaluated cheapest-first:

| # | Condition | Implementation |
|---|---|---|
| 1 | Request is over TLS | `is_ssl()` |
| 2 | A `ck_`-prefixed credential is present | `Api_Key_Access::get_request_consumer_key()` |
| 3 | URI targets a membership route | `is_protected_rest_request_uri()` |
| 4 | No OAuth 1.0a parameters | `Api_Key_Access::request_has_oauth_params()` |
| 5 | Key resolves to an allowlisted `key_id` | `Api_Key_Access::get_key_id_for_request()` |

Only condition 5 queries the database. Any failure returns the incoming value unchanged — the callback **never returns a hard `false`**, which would disable consumer-key auth for WooCommerce's own routes.

Returning `true` grants nothing by itself. It only lets WooCommerce *attempt* authentication; the secret, the key's scope and `manage_options` are all still enforced downstream.

### Request lifecycle

```
determine_current_user
  ├─ 10  cookie
  ├─ 15  WC authenticate()                  ← allow_woocommerce_key_auth() decides whether this runs
  └─ 20  wp_validate_application_password
         ↓
rest_pre_dispatch
  ├─ WC check_user_permissions()            ← read / read_write scope vs HTTP method
  └─ restrict_membership_rest_access()      ← manage_options
         ↓
WP_REST_Posts_Controller capability checks
```

## Why `manage_options` cannot be dropped

The CPT controllers' own capabilities are weaker than they look:

| CPT | Registration | Effective REST capability |
|---|---|---|
| `wicket_membership` | `capability_type => 'post'`, `map_meta_cap => true` | editor-level qualifies |
| `wicket_mship_config` | `capability_type => 'page'`, no `map_meta_cap` | primitive `edit_pages` — editor-level |
| `wicket_mship_tier` | `capability_type => 'page'`, no `map_meta_cap` | primitive `edit_pages` — editor-level |

Without the gate, an editor-owned key could read and write membership records.

## Api_Key_Access

`includes/Api_Key_Access.php` — all static, no hooks, side-effect free.

| Method | Purpose |
|---|---|
| `get_available_keys()` | Key list for the settings UI. Never selects `consumer_key` / `consumer_secret`. |
| `get_allowed_key_ids()` | Saved allowlist ∩ live keys. Memoised per request. |
| `get_request_consumer_key()` | Credential from the request, or null. |
| `get_key_id_for_request()` | Above, hashed via `wc_api_hash()`, resolved to `key_id`. |
| `request_has_oauth_params()` | Read-only OAuth detection. |

Degrades to empty/null when WooCommerce is absent (guarded on `function_exists( 'wc_api_hash' )`).

## Implementation constraints

These are not stylistic; each one is load-bearing.

**Never call `WC_REST_Authentication::get_oauth_parameters()`.** It looks like the right helper and is public, but it calls `set_error()` when OAuth parameters are partially present (`:334`), nulling WooCommerce's resolved user and planting a 401. The filter runs 3+ times per request, so this would corrupt authentication for unrelated requests, including WooCommerce's own routes. Detect OAuth by reading `$_GET`, `$_POST` and the `Authorization` header directly; `get_authorization_header()` (`:260`) *is* public and pure.

**Credential precedence mirrors WooCommerce exactly** (`:187-197`): query-string pair wins, Basic header is the fallback, and each source requires key *and* secret together. Reading the fields independently would let a request be authorised on one key while WooCommerce authenticates a different one.

**The filter receives no arguments and matches on `$_SERVER['REQUEST_URI']`.** `$request->get_route()` is unavailable. `get_requested_rest_route()` handles both the prefixed-path form and the `?rest_route=` form, using `rest_get_url_prefix()` rather than a hardcoded `wp-json`.

**Route slugs have one source.** `get_protected_rest_bases()` feeds both the route matcher and the URI matcher.

**SSL is enforced by us, not inherited.** WooCommerce is not SSL-locked: `is_ssl()` gates only its Basic path (`:98`), and when false it falls through to OAuth 1.0a over plain HTTP. Condition 1 sits at `is_request_to_rest_api()`, which WooCommerce evaluates at `:94` — *before* that structure — so a plain-HTTP request exits at `:95` and never reaches the OAuth branch at `:106`.

WordPress core reads no proxy headers: `is_ssl()` checks `$_SERVER['HTTPS']`, then `SERVER_PORT` only if `HTTPS` is unset. On this stack three things supply it — nginx `fastcgi_param HTTPS $https if_not_empty`, Bedrock's `HTTP_X_FORWARDED_PROTO` mapping in `config/application.php`, and `SERVER_PORT 443`. The `if_not_empty` matters: without it a plain-HTTP request sets `HTTPS=''`, which is *set but empty*, causing core to skip the `SERVER_PORT` branch entirely.

## Settings

`Settings::wicket_mship_api_allowed_keys()` renders the multi-select; `Api_Key_Access::OPTION_KEY` is the shared option key name.

`wicket_membership_plugin_options_validate()` rebuilds its output array from scratch — **any option key it does not explicitly name is dropped on every save.** Adding a new field to this options page means adding it there too.

## Known gaps

Neither is a security weakness; both are diagnosability costs.

- The settings list shows only key name and truncated key. The **owner is not shown**, so a key owned by a non-admin looks fine and returns `403` at runtime. Closing this means `user_can( $key['user_id'], 'manage_options' )` — role names are unreliable here because User Role Editor is active. `get_available_keys()` already returns `user_id`.
- **Permissions are not shown**, so a `read`-only key cannot be distinguished before selection; it will fail its first write with a `401` from WooCommerce.

## Testing notes

- Deactivating WooCommerce puts this site into a fatal error unrelated to this feature. Recover with `wp --skip-plugins --skip-themes plugin activate woocommerce`. Test WooCommerce-absent behaviour by loading `Api_Key_Access` under `wp --skip-plugins --skip-themes` instead.
- Rate limiting returns 429 on rapid repeated REST calls; a 429 is not a result.
- To baseline "before this feature", empty the allowlist. Removing the filter on `init` does not work — `wp_get_current_user()` runs earlier, so authentication has already happened.
- A REST-created `wicket_membership` does not persist a title, so write probes leave an *untitled draft*. Clean up by the ID in the 201 response.

## Related

- [WooCommerce API Keys with Membership API Access](../product/settings-membership-api-access.md) — the setting
- [Grant an Integration Access to Membership Data](../guides/grant-api-access-to-memberships.md) — admin guide
