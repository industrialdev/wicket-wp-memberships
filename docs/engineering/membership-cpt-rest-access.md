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

The three membership post types are exposed over the core `wp/v2` REST API and gated to administrators. Two credential types can reach them:

| Path | Credential | Scope of grant | Plugin code required |
|---|---|---|---|
| **A** | WordPress Application Password | Per user | **None** — core handles it |
| **B** | WooCommerce REST API key (`ck_`/`cs_`) | Per key, administrator-approved | The filter in §3 |

Both paths converge on the same capability gate. Neither bypasses the other.

---

## 1. Shared foundation

Everything in this section is common to both paths. Path A consists of *only* this section — it needs no path-specific code at all.

### 1.1 Endpoints

Registered in `Membership_Post_Types` on `init`, slugs from `Helper::get_*_cpt_slug()`:

| CPT | Route | `show_in_rest` |
|---|---|---|
| `wicket_membership` | `/wp/v2/wicket_membership` | `true` |
| `wicket_mship_config` | `/wp/v2/wicket_mship_config` | `true` |
| `wicket_mship_tier` | `/wp/v2/wicket_mship_tier` | `true` |

Collection routes and single-record sub-routes (`/<base>/{id}`) are both in scope.

### 1.2 The capability gate

`Membership_Post_Types::restrict_membership_rest_access()` on `rest_pre_dispatch`, priority 10.

```php
if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) { /* 401 / 403 */ }
```

**This is the single authorisation decision for both paths, and it is auth-method agnostic.** It asks only whether a user is resolved and holds `manage_options` — never *how* they authenticated. There is no allowlist of authentication mechanisms anywhere in the plugin.

Returns `401` when no user was resolved, `403` when one was but lacks the capability (`rest_authorization_required_code()`).

Because the gate does not distinguish credentials, adding Path B required **no change to it** — only the removal of WooCommerce's own refusal to authenticate on these routes.

### 1.3 Shared route matching

One slug source, two matchers, so the protected set cannot drift between them:

| Method | Consumed by |
|---|---|
| `get_protected_rest_bases()` | Both matchers below |
| `is_protected_rest_route( $route )` | The gate (§1.2) — has a `WP_REST_Request` |
| `get_requested_rest_route()` | The URI matcher below |
| `is_protected_rest_request_uri()` | Path B's filter (§3) — has no request object |

All private to `Membership_Post_Types`. `get_requested_rest_route()` handles both the prefixed-path form and the `?rest_route=` form, using `rest_get_url_prefix()` rather than a hardcoded `wp-json`.

### 1.4 Why `manage_options` cannot be dropped

The CPT controllers' own capabilities are weaker than they look, so the gate is doing real work for both paths:

| CPT | Registration | Effective REST capability |
|---|---|---|
| `wicket_membership` | `capability_type => 'post'`, `map_meta_cap => true` | editor-level qualifies |
| `wicket_mship_config` | `capability_type => 'page'`, no `map_meta_cap` | primitive `edit_pages` — editor-level |
| `wicket_mship_tier` | `capability_type => 'page'`, no `map_meta_cap` | primitive `edit_pages` — editor-level |

Without it, an editor-owned credential of either type could read and write membership records.

---

## 2. Path A — Application Password (per user)

### 2.1 Mechanism

Entirely core. No plugin code participates.

```
determine_current_user
  └─ 20  wp_validate_application_password    (wp-includes/default-filters.php:509)
```

The credential arrives as HTTP Basic `username:application-password`. Core resolves the user, and the request then meets the shared gate (§1.2) like any other authenticated request.

`grep -rn 'application_password' includes/ custom/` returns **0 matches**. This is deliberate: the path works because the gate is agnostic, not because anything supports it.

### 2.2 Availability

Core gates the feature itself (`wp-includes/user.php:5086`):

```php
function wp_is_application_passwords_supported() {
    return is_ssl() || 'local' === wp_get_environment_type();
}
```

So Application Passwords require HTTPS unless `WP_ENVIRONMENT_TYPE` is `local`. Filterable via `wp_is_application_passwords_available`.

Managed per user at **Users → Profile → Application Passwords** (`wp-admin/user-edit.php`).

### 2.3 Shared code this path relies on

Only §1. Specifically the gate (§1.2) for authorisation and the route matcher (§1.3) for deciding the route is in scope. It does not touch `Api_Key_Access` or the filter in §3.

### 2.4 Properties

- Grant is **per user**, not per integration — the credential inherits everything that user can do, across the whole REST API.
- Revoked from the owning user's profile.
- Unaffected by the memberships settings page. Nothing there enables or disables this path.

---

## 3. Path B — WooCommerce API key (per key)

### 3.1 The problem

`WC_REST_Authentication::authenticate()` returns before inspecting credentials unless `is_request_to_rest_api()` is true, and that only matches URIs containing `wp-json/wc/` or `wp-json/wc-` (`class-wc-rest-authentication.php:69-84`). A consumer key sent to `/wp/v2/wicket_membership` is never even looked at, so the request reaches the gate as anonymous and gets a `401`.

`woocommerce_rest_is_request_to_rest_api` (`:83`) is the only seam WooCommerce provides. WooCommerce's own comment at `:80-81` names it as the supported third-party path.

### 3.2 The filter

`Membership_Post_Types::allow_woocommerce_key_auth()`, hooked in the constructor beside the gate. Returns `true` only when **all five** conditions hold, evaluated cheapest-first:

| # | Condition | Implementation |
|---|---|---|
| 0 | Not already a WooCommerce route | early `return $is_wc_request` |
| 1 | Request is over TLS | `is_ssl()` |
| 2 | A `ck_`-prefixed credential is present | `Api_Key_Access::get_request_consumer_key()` |
| 3 | URI targets a membership route | `is_protected_rest_request_uri()` — §1.3 |
| 4 | No OAuth 1.0a parameters | `Api_Key_Access::request_has_oauth_params()` |
| 5 | Key resolves to an allowlisted `key_id` | `Api_Key_Access::get_key_id_for_request()` |

Only condition 5 queries the database. The filter runs on most requests (WooCommerce calls it from `determine_current_user`), so the common path must exit on a superglobal read.

Any failure returns the **incoming value unchanged**. The callback never returns a hard `false`, which would disable consumer-key auth for WooCommerce's own routes.

Returning `true` grants nothing by itself — it only lets WooCommerce *attempt* authentication. The secret, the key's scope and `manage_options` are all still enforced downstream.

### 3.3 Api_Key_Access

`includes/Api_Key_Access.php` — all static, registers no hooks, side-effect free.

| Method | Purpose |
|---|---|
| `get_available_keys()` | Key list for the settings UI. Never selects `consumer_key` / `consumer_secret`. |
| `get_allowed_key_ids()` | Saved allowlist ∩ live keys. Memoised per request. |
| `get_request_consumer_key()` | Credential from the request, or null. |
| `get_key_id_for_request()` | Above, hashed via `wc_api_hash()`, resolved to `key_id`. |
| `request_has_oauth_params()` | Read-only OAuth detection. |

Degrades to empty/null when WooCommerce is absent (guarded on `function_exists( 'wc_api_hash' )`).

Revoked keys are filtered out on every read of the allowlist, because WooCommerce fires **no action** when a key is revoked — deletion happens inline in its settings handler for the `revoke-key` query arg. A stale ID in the option therefore can never grant access.

### 3.4 The allowlist setting

`Settings::wicket_mship_api_allowed_keys()` renders a multi-select of `get_available_keys()`, valued by `key_id`. Labels are `{description} — ending in {truncated_key}` and nothing else, matching how WooCommerce labels keys on its own screen. Stored at `wicket_membership_plugin_options['wicket_mship_api_allowed_keys']` as an `int[]`; `Api_Key_Access::OPTION_KEY` is the shared constant.

**Empty allowlist is a complete no-op** — the filter never returns `true`, so behaviour across the whole site is identical to not having this feature. That is the default and the off switch.

> `wicket_membership_plugin_options_validate()` rebuilds its output array from scratch. **Any option key it does not explicitly name is dropped on every save.** Adding a field to this options page means adding it there too.

### 3.5 Shared code this path relies on

§1 in full — the same gate and the same route slugs as Path A. Specifically, `is_protected_rest_request_uri()` (§1.3) and `is_protected_rest_route()` both read `get_protected_rest_bases()`, so extending the protected set extends both paths at once.

### 3.6 Properties

- Grant is **per key**, chosen by an administrator, and independent of the key's WooCommerce access.
- Selecting a key **widens** it. The key keeps all its normal WooCommerce access and gains membership access; it does not become memberships-only.
- The key's owner must still hold `manage_options` (§1.2) — approval alone is not enough.
- WooCommerce's `check_user_permissions()` (`:681-691`) enforces the key's `read`/`read_write` scope against the HTTP method (`:572-598`), gated on `if ( $this->user )` so it only affects requests WooCommerce authenticated. This comes free; the plugin does not reimplement it.

---

## 4. Combined request lifecycle

```
determine_current_user
  ├─ 10  cookie                              ← admin UI
  ├─ 15  WC authenticate()                   ← PATH B: §3.2 filter decides whether this runs
  └─ 20  wp_validate_application_password    ← PATH A: core, no plugin involvement
         ↓
rest_authentication_errors
         ↓
rest_pre_dispatch
  ├─ WC check_user_permissions()             ← PATH B only: read / read_write scope
  └─ restrict_membership_rest_access()       ← SHARED: manage_options (§1.2)
         ↓
WP_REST_Posts_Controller capability checks   ← SHARED
```

Path A and Path B are independent up to `rest_authentication_errors` and identical after it.

---

## 5. Implementation constraints

Not stylistic — each is load-bearing.

**Never call `WC_REST_Authentication::get_oauth_parameters()`.** It looks like the right helper for condition 4 and is public, but it calls `set_error()` when OAuth parameters are only partially present (`:334`), nulling WooCommerce's resolved user and planting a 401. The filter runs 3+ times per request, so this would corrupt authentication for unrelated requests — including WooCommerce's own routes. Detect OAuth by reading `$_GET`, `$_POST` and the `Authorization` header directly. `get_authorization_header()` (`:260`) *is* public and pure, so it is safe to reuse.

**Credential precedence mirrors WooCommerce exactly** (`:187-197`): query-string pair wins, Basic header is the fallback, and each source requires key *and* secret together. Reading the fields independently would let a request be authorised on one key while WooCommerce authenticates a different one.

**The filter receives no arguments and matches on `$_SERVER['REQUEST_URI']`.** `$request->get_route()` is unavailable there — hence the separate URI matcher in §1.3.

**SSL is enforced by the plugin, not inherited.** WooCommerce is not SSL-locked: `is_ssl()` gates only its Basic path (`:98`), and when false it falls through to OAuth 1.0a over plain HTTP. Condition 1 sits at `is_request_to_rest_api()`, which WooCommerce evaluates at `:94` — *before* that structure — so a plain-HTTP request exits at `:95` and never reaches the OAuth branch at `:106`.

Note both paths depend on `is_ssl()` for different reasons: Path B via condition 1, Path A because core gates the whole feature on it (§2.2).

**`is_ssl()` reads no proxy headers.** Core checks `$_SERVER['HTTPS']`, then `SERVER_PORT` only if `HTTPS` is unset. On this stack three things supply it: nginx `fastcgi_param HTTPS $https if_not_empty`, Bedrock's `HTTP_X_FORWARDED_PROTO` mapping (`config/application.php:136-137`), and `SERVER_PORT 443`. The `if_not_empty` matters — without it a plain-HTTP request sets `HTTPS=''`, which is *set but empty*, causing core to skip the `SERVER_PORT` branch entirely. Trusting `X_FORWARDED_PROTO` assumes the edge is the only route to PHP.

---

## 6. Known gaps

Path B only. Neither is a security weakness; both are diagnosability costs of keeping the selector to two fields.

- **Key owner is not shown.** A key owned by a non-admin looks fine in the selector and returns `403` at runtime. Closing it means `user_can( $key['user_id'], 'manage_options' )` — role names are unreliable here because User Role Editor is active. `get_available_keys()` already returns `user_id`.
- **Key permissions are not shown.** A `read`-only key cannot be distinguished before selection; it will fail its first write with a `401` from WooCommerce.

---

## 7. Testing notes

- **Baseline for Path B** is an empty allowlist. Removing the filter on `init` does *not* work — `wp_get_current_user()` runs earlier, so WooCommerce has already authenticated and the route still returns 200. A valid override must be registered at mu-plugin load time.
- **Do not deactivate WooCommerce** to test degradation. It puts this site into a fatal error unrelated to this feature (500 on every route including WP-CLI bootstrap). Recover with `wp --skip-plugins --skip-themes plugin activate woocommerce`. Test WooCommerce-absent behaviour by loading `Api_Key_Access` under `wp --skip-plugins --skip-themes` instead.
- **Rate limiting** returns 429 on rapid repeated REST calls. A 429 is not a result.
- **A non-admin credential returns 403, not 401** — a user *was* resolved. Applies to both paths.
- **`/wp/v2/posts` returns 401 when any `ck_` Basic credential is attached**, where anonymous returns 200. That is core rejecting `ck_…` as a username (`invalid_username`), not this feature.
- **A REST-created `wicket_membership` does not persist a title**, so write probes leave an *untitled draft*. Clean up by the ID in the 201 response.

---

## Related

- [WooCommerce API Keys with Membership API Access](../product/settings-membership-api-access.md) — the setting
- [Connect an Integration to Membership Data](../guides/grant-api-access-to-memberships.md) — admin guide, both paths
