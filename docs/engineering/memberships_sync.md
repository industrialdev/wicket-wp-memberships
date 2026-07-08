---
title: "Subscription Sync Tool — Engineering Reference"
audience: [developer, agent]
php_class: null
source_files: ["custom/memberships-sync.php", "wicket.php", "includes/Membership_Tier.php", "includes/Helper.php", "includes/Membership_Controller.php"]
---

# Subscription Sync Tool — Engineering Reference

A procedural (no-class) tool in `custom/memberships-sync.php` that reconciles existing WooCommerce subscriptions with imported `wicket_membership` records. It links each subscription/order/product back to its membership post and, for per-seat organization tiers, pushes the seat count to the MDP.

## Loading & Entry Point

The file is **conditionally included** from `wicket.php` only when the `allow_local_imports` option key exists (the **MDP Import** / `ALLOW_LOCAL_IMPORTS` feature). If MDP Import is off, none of these functions are defined.

| | |
|---|---|
| Hook | `add_action( 'template_redirect', 'wicket_action_woocommerce_loaded', 10, 1 )` |
| Trigger | `?mship_subscription_sync=1` query var (any front-end URL) |
| Capability | `current_user_can( 'manage_options' )` — else `wp_die()` |
| Output | Full standalone HTML page; request ends via `wicket_sync_page_footer()` (`exit`) |

`template_redirect` is used (not `init`) so the current user, capability check, and admin bar are all available, and exiting replaces the theme output.

## Function Map

| Function | Responsibility |
|---|---|
| `wicket_action_woocommerce_loaded()` | Hook callback; calls the worker when the trigger var is present |
| `wicket_sync_subscriptions()` | Orchestrator: sanitises input, counts, renders the page, loops subscriptions, performs the linking/seat sync |
| `wicket_sync_page_header()` | Opens the admin-styled document (title, admin bar, admin UI styles, colour scheme) |
| `wicket_sync_page_footer()` | Renders admin bar, prints `admin-bar` script, closes document, `exit` |
| `wicket_sync_enqueue_admin_color_scheme()` | Registers + enqueues the current user's admin colour scheme stylesheet |
| `wicket_sync_render_control_bar( array $state )` | Emits the GET form + client-side run-mode/pagination logic |
| `wicket_get_mapped_product_id_for_tier( $product_id )` | Remaps a legacy product id to the tier's current product id (in-file `$product_map`, empty by default) |
| `wicket_update_membership_json_data( $post_id, $debug, $membership_incoming )` | Rebuilds the membership JSON blob and writes it to order/subscription/user meta |
| `memberships_update_seat_count( $client, $uuid, $seat_count )` | PATCHes MDP `organization_memberships/{uuid}` with `max_assignments` |
| `wicket_wc_log_mship_sync( $data, $append_file_name, $level )` | WC_Logger helper (currently only called from commented-out code — see Caveats) |

## Request Parameters

All read from `$_REQUEST` and sanitised at the top of `wicket_sync_subscriptions()`.

| Param | Type / sanitisation | Effect |
|---|---|---|
| `mship_subscription_sync` | presence | Triggers the tool |
| `wmsync_run` | presence | Marks an explicit action; **absent on a bare load → form only, nothing runs** |
| `no_debug` | presence | LIVE mode (writes). Absent → dry run (default) |
| `count_only` | presence | Count branch only; reports totals then exits |
| `subscription_to_update` | `(int)`, `>0` else `''` | Single-subscription scope; overrides pagination + date |
| `page_number` | `max(1, (int))`, clamped to `total_pages` | Pagination offset |
| `page_length` | `(int)`, must be in `[25,50,100,200]` else `100` | Per-page size |
| `created_after` | must match `/^\d{4}-\d{2}-\d{2}$/` else `''` | `date_created >=` filter |

`wmsync_mode` and `wmsync_confirm` are form-only fields; the client-side JS translates them into `no_debug` and never reach server logic directly.

## Execution Flow

1. Capability check → sanitise params → derive `$debug`, `$count_only`, `$action_requested`.
2. **Pagination count** (only if `$action_requested && batch && !count_only`): IDs-only `wcs_get_subscriptions()` to compute `$pagination_total` and `$total_pages`; clamp `$page_number`.
3. `wicket_sync_page_header()` → `wicket_sync_render_control_bar()`.
4. **Bare load guard**: if `!$action_requested`, call `wicket_sync_page_footer()` and stop (form only).
5. Open the `<pre>` card; print the run summary (env, mode, scope/position).
6. **Branch:**
   - `count_only`: query active/on-hold IDs, load each and test `has_term('membership','product_cat', …)`, print two totals, exit.
   - `subscription_to_update`: build a single-element `$subscriptions` array.
   - else: `wcs_get_subscriptions()` with `subscriptions_per_page = page_length`, `offset = (page-1)*page_length`, optional `date_created`.
7. If LIVE, initialise `$wicket_api_client = wicket_api_client()`.
8. **Per subscription → per line item** (see Matching, below).
9. Close `<pre>`, `wicket_sync_page_footer()`.

## Matching Logic (per line item)

1. Resolve product id: `$item->get_variation_id()` ?: `$item->get_product_id()`.
2. `wicket_get_mapped_product_id_for_tier()` remaps legacy → current product id.
3. `Membership_Tier::get_tier_by_product_id( $mapped_product_id )` → tier; skip item if none, or if `get_membership_tier_post_id()` is empty.
4. `get_posts()` on `wicket_membership` where `user_id = $sub->get_user_id()` **AND** `membership_tier_post_id = $tier_id`. The subscriber is the WooCommerce subscription user; the tier is derived from the subscribed product. If multiple match, **the first is used** (`$user_memberships[0]`).
5. If the item meta `_membership_post_id_renew` is already set, the item is skipped (already linked).

## Writes Performed (LIVE only)

For a matched, not-yet-linked item:

- **Membership post meta** (`update_post_meta` on `$user_memberships[0]->ID`): `membership_product_id`, `membership_subscription_id`, `membership_parent_order_id`.
- **Subscription item meta**: `wc_add_order_item_meta( $item_id, '_membership_post_id_renew', $membership_id, true )` — the link that drives the renewal flow.
- **JSON blob** via `wicket_update_membership_json_data()`:
  - `add_post_meta( $order_id, '_wicket_membership_{product_id}', $json )`
  - `add_post_meta( $subscription_id, '_wicket_membership_{product_id}', $json )`
  - `add_user_meta( $user_id, '_wicket_membership_{post_id}', $user_json )`
  - JSON is produced by `Helper::get_membership_json_from_membership_post_data()`, which maps the `org_seats` post meta to the `membership_seats` key in the blob. In `$debug` it buffers `var_dump()` into a collapsible toggle and writes nothing.

## Per-seat Seat Sync

Guard: `$Tier->is_organization_tier() && $Tier->is_per_seat() && (live client present || debug)`.

- Seat count = `$item->get_quantity()` (the subscription line-item quantity).
- LIVE: `update_post_meta( $membership_id, 'org_seats', $quantity )`, then `memberships_update_seat_count( $client, $membership_wicket_uuid, $quantity )`.
- `memberships_update_seat_count()` normalises `(int) $seat_count < 1 → null` (unlimited), matching `Membership_Controller::update_mdp_record()`, and PATCHes `organization_memberships/{uuid}` with `attributes.max_assignments`.
- Individual tiers and `is_per_range_of_seats()` tiers are **not** touched here.

## Page Rendering

`wicket_sync_page_header()` deliberately avoids `wp_head()` so the theme's `wp_enqueue_scripts` never fires — only these handles are enqueued and printed via `wp_print_styles()`: `admin-bar`, `common`, `buttons`, `forms`, `dashicons`, plus the colour scheme. The admin bar is forced on (`add_filter('show_admin_bar','__return_true')`) and `_wp_admin_bar_init()` is called directly if the global is unset. `wicket_sync_page_footer()` calls `wp_admin_bar_render()` and `wp_print_scripts(['admin-bar'])`.

`wicket_sync_enqueue_admin_color_scheme()` loads `wp-admin/includes/misc.php` and calls `register_admin_color_schemes()` (normally admin-only) to populate `$_wp_admin_css_colors`, then enqueues the current user's scheme URL when one exists ("fresh" has none).

## Control Bar Safety Model (client-side)

`wicket_sync_render_control_bar()` emits hidden fields (`no_debug`, `count_only`, `page_number`, `wmsync_run`) set by JS:

- `wmsyncSubmit('run')` — honours the dry/live radio; LIVE requires the confirm checkbox or it `alert()`s and aborts.
- `wmsyncSubmit('count')` / `wmsyncPage(±1)` — always force `no_debug=''` (dry). Navigation/counting never write.
- Every button sets `wmsync_run=1`; the dry/live radio always defaults to **dry** on load (LIVE is never persisted from the request).

## External Dependencies

`wcs_get_subscriptions()`, `wcs_get_subscription()` (WC Subscriptions); `wicket_api_client()` (base plugin); `Membership_Tier::get_tier_by_product_id()`, `Membership_Tier::is_organization_tier()/is_per_seat()/get_membership_tier_post_id()`; `Helper::get_membership_json_from_membership_post_data()`; WP `get_posts`, `*_post_meta`, `*_user_meta`, `wc_get_order_item_meta`, `wc_add_order_item_meta`, `has_term`.

## Caveats

- **`wicket_wc_log_mship_sync()`** is only invoked from commented-out code (the `$log` array is accumulated but not persisted). Its `source` includes `time()`, which would spawn a new WC log file per second if re-enabled — fix the source before relying on it.
- **First-matched membership wins** when multiple memberships share a user + tier; there is no disambiguation by date or status.
- **Admin bar is forced visible** regardless of the user's profile preference, since the page is built around it.
- Hard-coded admin edit link uses a `/wp/wp-admin/...` path prefix in the matched-membership log line.
