# Membership_Bundle_Block_Controller

**File:** `includes/Membership_Bundle_Block_Controller.php`
**Namespace:** `Wicket_Memberships`

Registers the `wicket-memberships/membership-bundles-list` Gutenberg block — a plain (non-ACF) dynamic block with no configurable attributes. The front end is rendered entirely by `includes/blocks/membership-bundles-list/render.php`, which switches between two PHP + Alpine.js templates on the SAME rendered page based on the current URL: `templates/account-membership-bundles/list.php` (the "Membership Bundle - List" screen, default) when there is no `bundle_post_id` query param, or `templates/account-membership-bundles/detail.php` (the "Membership Bundle - Detail/Manage" screen — status/dates, tier breakdown, members table) when `bundle_post_id` is a positive integer. This lets `list.php`'s "Manage Bundle" links stay simple (just add `bundle_post_id` to the current URL) without needing a second page or any mechanism to locate one. The editor preview uses a small no-build `index.js` (registered via block.json's `editorScript`, with dependencies declared in the sibling `index.asset.php` since there's no webpack/`@wordpress/scripts` step) that wraps `<ServerSideRender>` — this is what makes the block reliably show up and preview in the inserter; block.json alone (render-only, no editor script) is not sufficient on all WP/Gutenberg versions.

## Why a separate controller

Block registration is an `init`-time, editor-facing concern, distinct from the REST routing in `Membership_Bundle_WP_REST_Controller` and the admin CRUD in `Membership_Bundle_Admin_Controller`. Kept as its own class rather than folded into an existing controller.

## Methods

### `register_block(): void`

Hooked to `init`. Calls `register_block_type( __DIR__ . '/blocks/membership-bundles-list' )`, which reads the block's `block.json`.

### `is_alpine_available(): bool` (static)

Determines whether Alpine.js is present on the current page before the block template renders any `x-data` markup. Mirrors `wicket-wp-base-plugin`'s `Assets::enqueue_plugin_scripts()` logic:

- Returns `true` if the `wicket-plugin-alpine-script` handle is registered or enqueued (the path base-plugin takes on non-Wicket themes).
- Returns `true` if `is_wicket_theme_active()` exists and reports the Wicket theme is active (which is expected to ship its own Alpine instance).
- Returns `false` otherwise.

Callers (currently `includes/blocks/membership-bundles-list/render.php`) must treat `false` as "do not render Alpine-dependent markup" — the render callback shows a generic unavailable-feature notice to all users, plus an admin-only diagnostic detail, instead of shipping broken interactive markup.

### `get_bundles_requiring_attention_count(): int` (static)

Counts the current WP user's owned `wicket_mship_bundle` posts that are currently showing a renewal callout: `early_renewal` (inside the renewal window) or `grace_period`. Returns `0` when nobody is logged in.

Loads the owner's bundles in status `active`, `delayed` or `grace_period` (the only statuses `Membership_Bundle::get_renewal_state()` can match) and counts those where `Membership_Bundle::get_renewal_callout()` is not `null`. The account-menu badge therefore matches the detail view exactly. Already-renewed terms, autopay early renewals, bundles with no usable renewal flow, and the global "Disable Renewal Callouts" setting are all excluded. Expired bundles are not counted.

### `get_nav_badge_href_fragments(): array` (static, private)

Resolves the href substring(s) the badge's CSS selector should match against, from the real slug of the configured "Manage Membership Bundles" page (`Helper::get_membership_bundles_manage_page_id()`, set in Settings > Wicket Memberships > Membership Bundles). Returns a single-item array with that page's slug when the page exists and is published, or an empty array when no page is configured yet or the configured page ID no longer resolves — callers treat an empty result as "nothing to match" and skip rendering the badge.

### `render_nav_badge_style(): void`

Hooked to `wp_head`. Calls `get_bundles_requiring_attention_count()` and, only when the count is greater than zero AND `get_nav_badge_href_fragments()` returns at least one fragment, echoes a `<style>` block with a `::after` badge (count, red pill background) on the "Manage Membership Bundles" page link inside each account nav menu ID variant — not on the menu container itself, so the badge only appears on that one link. For example, if the configured page's slug is `membership-bundles`:

```
#wicket-acc-menu > .menu-item > a[href*=membership-bundles]::after,
#wicket-acc-menu-mobile > .menu-item > a[href*=membership-bundles]::after,
#wicket-acc-menu-two > .menu-item > a[href*=membership-bundles]::after,
#wicket-acc-menu-mobile-two > .menu-item > a[href*=membership-bundles]::after
```

Renders nothing at zero count, and also renders nothing when no "Manage Membership Bundles" page is configured (or it no longer resolves), so no empty/zero/unmatchable badge ever appears. The menu IDs come from the `NAV_BADGE_MENU_IDS` class constant; the href fragment(s) come from `get_nav_badge_href_fragments()` — the selector list is their cross product.

Badge styling uses theme v2 CSS custom properties (`--spacing-200`, `--spacing-75`, `--spacing-50`, `--border-radius-600`, `--state-error`, `--text-content-reversed`, `--label-sm-font-size`), each with a hardcoded fallback value so the badge still renders correctly on a page/theme where those tokens aren't defined.

## Renewal (detail view)

When the bundle is in its renewal window (`early_renewal`) or grace period (`grace_period`), `detail.php` renders a renewal callout (`templates/account-membership-bundles/renewal-callout.php`) above the bundle title, server-side, from `Membership_Bundle::get_renewal_callout()`. Owner-only: `detail.php` requires `Membership_Bundle::is_current_user_owner()` and a passing `Membership_Bundle::check_current_user_access()` first.

- **Form-page flow:** the callout button links straight to `get_renewal_form_url()`.
- **Subscription flow:** the button opens `templates/account-membership-bundles/renew-modal.php`, which shows the membership count and a per-tier summary from `Membership_Bundle::get_renewal_summary()`. Its "Generate Order" button is a plain form POST to `admin-post.php`, handled below.

Styles (`.wicket-mship-bundle-renewal-callout--early_renewal` / `--grace_period`, `.wicket-mship-renew-modal__*`) live in the block's `style.css`. Colors match ACC's `ac-callout` renewal callouts.

### Constants

- `RENEWAL_ORDER_ACTION` = `wicket_mship_bundle_renewal_order`: the admin-post action, and the nonce action prefix (`{action}_{bundle_post_id}`).
- `RENEWAL_ERROR_QUERY_ARG` = `bundle_renewal_error`: the query arg carrying a failure code back to the detail view.

### `handle_renewal_order_request(): void`

Hooked to `admin_post_wicket_mship_bundle_renewal_order` (logged-in only; there is no `nopriv` hook).

1. Reads `bundle_post_id` and `return_url` from the POST, and passes `return_url` through `wp_validate_redirect()` so only same-site URLs are used.
2. Verifies the bundle-scoped nonce.
3. Requires `is_current_user_owner()` and `check_current_user_access()`.
4. Calls `Membership_Bundle::get_or_create_renewal_order()`.
5. On success, `wp_safe_redirect()`s to the order's `get_checkout_payment_url()`.
6. On failure, logs the `WP_Error` and redirects back to `return_url` with `?bundle_renewal_error={code}`.

### `get_renewal_error_message( string $code ): string` (static)

Maps a renewal error code to plain-language copy for `detail.php`. `not_renewable`, `no_members` and `forbidden`/`not_found` each get a specific message; anything else gets a generic "try again" message. An empty code returns `''`.

## Related

- List view REST data source: `Membership_Bundle_WP_REST_Controller::get_my_membership_bundles()` — `GET /wicket_member/v1/membership_bundles/mine`.
- List view row/query logic: `Membership_Bundle_Admin_Controller::get_membership_bundles_list()` (`$owner_user_id` param).
- Detail view REST data sources (all `permissions_check_bundle_org_member`-gated, in `Membership_Bundle_WP_REST_Controller`): `get_bundle_entity()` (`GET .../membership_bundle_entity/mine`), `get_bundle_members_by_tier()` (`GET .../bundle/{id}/members_by_tier/mine`), `get_bundle_members()` (`GET .../bundle/{id}/members/mine`).
- Public docs: `docs/public/membership-bundles/endpoints/bundles.md` (all four `/mine` endpoints above), `docs/public/membership-bundles/classes/membership-bundle-admin-controller.md` (query methods).
