# Membership_Bundle_Block_Controller

**File:** `includes/Membership_Bundle_Block_Controller.php`
**Namespace:** `Wicket_Memberships`

Registers the `wicket-memberships/membership-bundles-list` Gutenberg block — a plain (non-ACF) dynamic block with no configurable attributes. The front end is rendered entirely by `includes/blocks/membership-bundles-list/render.php`, which in turn includes `templates/account-membership-bundles/list.php` (the "Membership Bundle - List" PHP + Alpine.js screen). The editor preview uses a small no-build `index.js` (registered via block.json's `editorScript`, with dependencies declared in the sibling `index.asset.php` since there's no webpack/`@wordpress/scripts` step) that wraps `<ServerSideRender>` — this is what makes the block reliably show up and preview in the inserter; block.json alone (render-only, no editor script) is not sufficient on all WP/Gutenberg versions.

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

Counts the current WP user's owned `wicket_mship_bundle` posts whose `membership_status` meta is `Wicket_Memberships::STATUS_GRACE` ("Renew Memberships") or `Wicket_Memberships::STATUS_EXPIRED` ("Lapsed - Renew Memberships") — the two statuses that mean the member needs to take a renewal action. Returns `0` when nobody is logged in.

Reuses `Membership_Bundle_Admin_Controller::get_membership_bundles_list()` (owner-scoped via its `$owner_user_id` param, the same query/dedup path the member-facing "mine" REST endpoint relies on) rather than a bespoke `WP_Query`, so it stays in sync with that method's definition of "owned by" and "matches this status". Calls it once per status (`posts_per_page` of 1, since only the returned `count` — the pre-pagination total — is used) and sums the two counts, since that method only supports a single equality status filter, not an `IN` comparison across both at once.

### `render_nav_badge_style(): void`

Hooked to `wp_head`. Calls `get_bundles_requiring_attention_count()` and, only when the count is greater than zero, echoes a `<style>` block with a `::after` badge (count, red pill background) on the membership-bundles / membership-groups link inside each account nav menu ID variant — not on the menu container itself, so the badge only appears on that one link:

```
#wicket-acc-menu > .menu-item > a[href*=membership-bundles]::after,
#wicket-acc-menu > .menu-item > a[href*=membership-groups]::after,
#wicket-acc-menu-mobile > .menu-item > a[href*=membership-bundles]::after,
#wicket-acc-menu-mobile > .menu-item > a[href*=membership-groups]::after,
#wicket-acc-menu-two > .menu-item > a[href*=membership-bundles]::after,
#wicket-acc-menu-two > .menu-item > a[href*=membership-groups]::after,
#wicket-acc-menu-mobile-two > .menu-item > a[href*=membership-bundles]::after,
#wicket-acc-menu-mobile-two > .menu-item > a[href*=membership-groups]::after
```

Renders nothing at zero, so no empty/zero badge ever appears. The menu IDs and href fragments are the `NAV_BADGE_MENU_IDS` and `NAV_BADGE_HREF_FRAGMENTS` class constants, respectively — the selector list is their cross product.

Badge styling uses theme v2 CSS custom properties (`--spacing-200`, `--spacing-75`, `--spacing-50`, `--border-radius-600`, `--state-error`, `--text-content-reversed`, `--label-sm-font-size`), each with a hardcoded fallback value so the badge still renders correctly on a page/theme where those tokens aren't defined.

## Related

- REST data source: `Membership_Bundle_WP_REST_Controller::get_my_membership_bundles()` — `GET /wicket_member/v1/membership_bundles/mine`.
- Row/query logic: `Membership_Bundle_Admin_Controller::get_membership_bundles_list()` (`$owner_user_id` param).
- Public docs: `docs/public/membership-bundles/endpoints/bundles.md` (endpoint), `docs/public/membership-bundles/classes/membership-bundle-admin-controller.md` (query method).
