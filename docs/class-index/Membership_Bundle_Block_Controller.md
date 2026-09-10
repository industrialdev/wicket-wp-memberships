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

## Related

- REST data source: `Membership_Bundle_WP_REST_Controller::get_my_membership_bundles()` — `GET /wicket_member/v1/membership_bundles/mine`.
- Row/query logic: `Membership_Bundle_Admin_Controller::get_membership_bundles_list()` (`$owner_user_id` param).
- Public docs: `docs/public/membership-bundles/endpoints/bundles.md` (endpoint), `docs/public/membership-bundles/classes/membership-bundle-admin-controller.md` (query method).
