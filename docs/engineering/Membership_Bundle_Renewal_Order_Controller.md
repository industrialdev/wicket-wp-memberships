---
title: "Membership_Bundle_Renewal_Order_Controller"
audience: [developer]
php_class: Membership_Bundle_Renewal_Order_Controller
source_files: ["includes/Membership_Bundle_Renewal_Order_Controller.php"]
---

# Membership_Bundle_Renewal_Order_Controller

Bundle renewal-order creation: claiming, queuing, and per-member repricing. Split out of `Membership_Bundle_Cron_Controller`, which still owns daily bundle status cron and the post-payment member-provisioning batch.

**Architecture position:** Everything from "a renewal order needs to be created" (admin action, member confirm, or WCS's own native admin action) through the order actually being built and priced. `Membership_Bundle_Cron_Controller::process_bundle_renewal_members()` reads this class's output (`_wicket_bundle_renewal_resolved_tier_post_id` item meta) but does not call into it directly.

## `apply_filters` Hooks Fired (client extensibility)

| Filter | Fired by | Args | Default / no-op behavior |
|---|---|---|---|
| `wicket_mship_bundle_renewal_charge_tier_product` | `reprice_bundle_renewal_line_item()` | `mixed $override, int $old_membership_post_id, int $user_id, int $old_bundle_post_id, array $core_default` | Default `null` — resolved default stands. A non-null override is validated against the target tier's own products and fails closed to the default on mismatch |
| `wicket_mship_bundle_renewal_line_item_price` | `apply_bundle_renewal_line_item_price_filter()` | `mixed $override, \WC_Order_Item $item, int $item_id, int $membership_post_id, int $user_id, \WC_Order $renewal_order` | Default `null`, single-channel — return value ignored either way; no callback means no price/fee mutation occurs |
| `wicket_mship_bundle_line_item_extra_meta` | `refresh_bundle_renewal_line_item_meta()` | `array $extra_meta, int $item_id, \WP_User\|false $user, int $membership_post_id, int $product_id, bool $is_renewal` | Default `[]` — no-op; re-fires the same filter `Membership_Bundle::add_subscription_line_item()` fires at add-time, with `$is_renewal = true` on this call site |

## Registered Actions

| Hook | Handler | Notes |
|---|---|---|
| `wicket_bundle_create_renewal_order` | `create_renewal_order_job()` | dispatched by the create/confirm REST endpoints |
| `woocommerce_order_action_wcs_create_pending_renewal` (WCS native admin order action) | `intercept_wcs_create_pending_renewal_for_bundle()` | fired by WooCommerce core's order-actions meta box save handler when an admin selects "Create pending renewal order" on a subscription edit screen; this plugin hooks at priority 5, before WCS's own handler at priority 10 |
| `admin_notices` | `render_queued_bundle_renewal_order_notice()` | renders the one-time notice `intercept_wcs_create_pending_renewal_for_bundle()` sets via transient |
| `add_meta_boxes` | `maybe_render_large_bundle_subscription_notice()` | priority 40; fires on WCS's own subscription edit screen render |
| `wcs_renewal_order_created` (WCS native filter) | `apply_bundle_renewal_line_item_price_filter()` | fired by WCS's own `wcs_create_renewal_order()`, once per renewal order |
| `wcs_renewal_order_items` (WCS native filter) | `refresh_bundle_renewal_line_item_meta()` | fired by `wcs_create_order_from_subscription()` (dynamic filter name `wcs_{$type}_items`, `$type = 'renewal_order'` for real renewals) — fires on the subscription's own items, before they're copied to the fresh renewal order; never fires for a resubscribe order |

## In-Request Caches

`create_renewal_order_job()` resets three static caches (`Membership_Tier` objects, `WC_Product` objects, membership-post `user_id`) at the start of each run via `reset_caches()`. They exist only for the duration of that one job — never shared with `Membership_Bundle_Cron_Controller::process_bundle_renewal_members()`, a separate Action Scheduler job.

Primed up front in `create_renewal_order_job()`:
- `update_meta_cache( 'post', $membership_post_ids )` — one query loads `membership_tier_post_id`/`membership_product_id`/`user_id` for every member on the subscription, instead of one `get_post_meta()` call per member per hook callback.
- `cache_users( $user_ids )` — same idea for `get_user_by( 'id', ... )`, called once per member in both `apply_bundle_renewal_line_item_price_filter()` and `refresh_bundle_renewal_line_item_meta()`.

`get_cached_tier()`, `get_cached_product()`, and `get_cached_membership_user_id()` are the read-through accessors every method below uses instead of constructing a fresh `Membership_Tier`/`WC_Product` or re-reading `user_id` meta per line item. For a bundle with hundreds of members sharing a handful of distinct tiers/products, this collapses what would be thousands of queries/object constructions into a small, fixed number.

`create_renewal_order_job()` also calls `wc_set_time_limit( 300 )` (when available) before `wcs_create_renewal_order()` — Action Scheduler never interrupts a running job; the only real ceiling is PHP's own `max_execution_time`, which Action Scheduler's own `raise_time_limit()` only ever raises to its own ~30s budget, not to unlimited. A host that blocks `set_time_limit()` (some locked-down shared hosting) leaves this as a silent no-op.

## Methods

### `claim_renewal_order_creation( int $bundle_post_id ): true|array{status: int, order_id: ?int}`

Claim a bundle's renewal-order-creation slot, or report it's already claimed/complete. Must happen at enqueue time — once creation is deferred, no order exists yet for a second request to detect via an order-existence check.

Uses `add_post_meta(unique: true)` rather than `update_post_meta`'s `$prev_value`: the latter skips its compare-and-swap when `$prev_value` is empty, which is exactly a bundle's first claim.

---

### `clear_completed_renewal_order_claim( int $bundle_post_id ): void`

Deletes `membership_renewal_order_creation` post meta, but only when it holds a completed claim (`order_id` set). Leaves an in-flight claim (queued or still creating, no `order_id` yet) untouched, so it does not weaken `claim_renewal_order_creation()`'s concurrency guard against a genuine second request racing an in-progress job.

Called by `Membership_Bundle_WP_REST_Controller::create_bundle_renewal_order()` (the admin manual action) and by `intercept_wcs_create_pending_renewal_for_bundle()` (below) before each claims — both may legitimately be triggered again on the same bundle post, unlike `confirm_bundle_renewal()` (member-facing, one confirm per cycle), which does not call this and keeps blocking on a completed claim indefinitely.

---

### `intercept_wcs_create_pending_renewal_for_bundle( \WC_Subscription $subscription ): void`

Hooked to WooCommerce Subscriptions' native `woocommerce_order_action_wcs_create_pending_renewal` admin order action, at priority 5 — before WCS's own handler (`WCS_Admin_Meta_Boxes::create_pending_renewal_action_request()`, priority 10). Fired when an admin selects "Create pending renewal order" from a subscription's Order Actions dropdown in wp-admin.

WCS's own handler calls `wcs_create_renewal_order()` synchronously on that admin request, which fires `wcs_renewal_order_created` — the same filter `apply_bundle_renewal_line_item_price_filter()` hooks — running the full per-member repricing loop inline, with no Action Scheduler involved anywhere in that call chain. For a large bundle this is a real timeout risk on a live HTTP request, unlike this plugin's own two REST endpoints (`create_bundle_renewal_order`, `confirm_bundle_renewal`), which already defer the equivalent work to a background job.

**Behavior:**
- Resolves `membership_bundle_id` post meta on the subscription. If it's empty or doesn't resolve to a `wicket_mship_bundle` post, returns immediately — WCS's own priority-10 handler runs normally for non-bundle subscriptions, completely unaffected.
- For a bundle subscription: `remove_action()`s WCS's own handler off this same hook (so it never runs, and `wcs_create_renewal_order()` is never called synchronously here) — the callback array must exactly match WCS's own registration (`['WCS_Admin_Meta_Boxes', 'create_pending_renewal_action_request']`, no leading backslash on the class name; `class_exists()` normalizes one, WordPress's own callback-identity check does not) — then calls `clear_completed_renewal_order_claim()` + `claim_renewal_order_creation()` and, on a successful claim, `as_schedule_single_action( 'wicket_bundle_create_renewal_order', ... )` — the identical job `create_bundle_renewal_order()` dispatches.
- On a claim conflict (order already exists, or creation already in flight), sets a transient notice instead of proceeding — no order is created and nothing is queued twice.
- Sets a one-time transient notice (`wicket_mship_bundle_renewal_order_notice_{user_id}`) either way, since removing WCS's own handler also removes WCS's own success/failure admin notice for this action.

---

### `render_queued_bundle_renewal_order_notice(): void`

Hooked to `admin_notices`. Reads and immediately deletes the transient `intercept_wcs_create_pending_renewal_for_bundle()` sets, rendering it as a standard WordPress admin notice. A transient (rather than a `redirect_post_location` query arg) is used because it survives independently of whatever redirect WCS's or WooCommerce's own order-actions save flow performs, and is scoped per-user so it does not leak to a different admin viewing the same screen.

---

### `maybe_render_large_bundle_subscription_notice(): void`

Hooked to `add_meta_boxes` (priority 40, after WCS's own meta boxes are registered). Registers a meta box (`wicket_mship_bundle_large_subscription_notice`, `side`/`high` context) warning on WCS's native subscription edit screen when the subscription belongs to a bundle with 250+ line items — before the admin submits that screen's own "Save"/order-actions form. `side`/`high` places it directly under WCS's own "Order actions" box in the sidebar, rather than at the top of the page as a generic `admin_notices` print would.

**Why this can't be handled inside `intercept_wcs_create_pending_renewal_for_bundle()`:** that screen's own line-items meta box renders roughly a dozen named form fields per member. A subscription with hundreds of members can exceed PHP's `max_input_vars` limit (default `1000`–`4000` depending on host) on that screen's *own* form submission — a PHP-FPM request-startup check that silently truncates `$_POST` **before any WordPress or plugin code runs**, including this plugin's own hook. By the time `intercept_wcs_create_pending_renewal_for_bundle()` executes, the truncation (if any) has already happened and cannot be detected or reversed from inside a `do_action`/`add_action` callback. The only actionable point is warning the admin beforehand, on page render.

**Flow:**
1. Resolves the current screen via `wcs_get_page_screen_id( 'shop_subscription' )` (handles both classic and HPOS admin screens) — no-ops on any other screen.
2. Resolves the subscription ID from `$_GET['post']` (classic) or `$_GET['id']` (HPOS).
3. Checks `membership_bundle_id` post meta on the subscription — no-ops for a non-bundle subscription.
4. Counts `$subscription->get_items()` — no-ops below 250.
5. Calls `add_meta_box()` with `render_large_bundle_subscription_notice_box()` as the render callback, passing `bundle_post_id` through `$args`.

### `render_large_bundle_subscription_notice_box( $post_or_order, array $metabox ): void`

The meta box render callback `maybe_render_large_bundle_subscription_notice()` registers. Reads `bundle_post_id` back out of `$metabox['args']` and prints a link to this bundle's own edit page (`admin.php?page=wicket_bundle_member_edit&id={bundle_group_uuid}`), whose own "Create Renewal Order" action only ever sends `bundle_post_id` — a single field, unaffected by member count.

Not a transient/one-time notice like `render_queued_bundle_renewal_order_notice()` — the meta box (and its warning) is registered fresh on every load of that screen for that subscription, since the risk (a large member count) is a property of the subscription, not a one-off event.

---

### `create_renewal_order_job( int $bundle_post_id, int $subscription_id ): void`

Create a bundle's renewal order in the background — dispatched by the REST endpoints instead of calling `wcs_create_renewal_order()` inline in the request. Writes the result (`order_id` or failure) back onto the claim's own meta; that's the terminal state the UI polls for and a later claim attempt checks against.

Resets the in-request caches (above), primes them from the subscription's own line items, sets the subscription `on-hold` (WCS's pay-for-order page only accepts payment for an `on-hold`/`pending` subscription), raises the execution time limit, then calls `wcs_create_renewal_order()`.

---

### `apply_bundle_renewal_line_item_price_filter( \WC_Order $renewal_order, \WC_Subscription $subscription ): \WC_Order`

Hooked to WooCommerce Subscriptions' own `wcs_renewal_order_created` filter. This is the actual order WCS bills the customer on.

**Flow:**

1. Scopes to bundle subscriptions only — a subscription is linked to a bundle when some `wicket_mship_bundle` post's `membership_subscription_id` meta points to it. Non-bundle renewal orders pass through untouched.
2. Loops the renewal order's line items, resolving each to its member via `_membership_post_id` order-item meta, then to `user_id` post meta (cached — see above).
3. Calls `reprice_bundle_renewal_line_item()` (below) to reprice the item to the term the member is renewing into, before the price/fee filter runs. A failure is collected, not thrown.
4. Fires `wicket_mship_bundle_renewal_line_item_price` once per member/line-item, inside its own try/catch (log-and-continue on failure — a single bad member's callback must not abort the rest of the order or skip `calculate_totals()`). If a callback directly overrides the item's total (not a product swap, not a coupon/discount — both already visible elsewhere), `_wicket_bundle_renewal_original_product_price` (the pre-filter price) and `_wicket_bundle_renewal_price_decision_source` (always `filter_override`) are stamped on the item.
5. Calls `$renewal_order->calculate_totals()` once after the full loop, regardless of any item's failure.
6. If any member's repricing failed, puts the order `on-hold` with a note naming every failed member and error code, and logs the batch.

**Return contract:** the filter's own return value is discarded. A callback communicates any change — price adjustment, added fee/product line, whole-order effect — by mutating the passed `$item`/`$renewal_order` directly via the normal WC API. Default behavior with no callback attached: the loop runs but no mutation occurs.

**Return value:** always returns `$renewal_order` (or whatever non-`WC_Order` value was passed in, unchanged) — required because `wcs_renewal_order_created` is a WCS filter, not an action. Holds true even when repricing failed for some members — the order is put on hold, not withheld.

---

### `resolve_sequential_logic_succession( int $tier_post_id, ?int $product_id, int $old_membership_post_id ): array|\WP_Error`

Resolve the tier/product a renewing member's `sequential_logic` tier succeeds to. Non-`sequential_logic` tiers pass through unchanged. Private to this class — the only caller is `reprice_bundle_renewal_line_item()`.

Returns `array{tier_post_id, product_id, variation_id, decision_source: 'unchanged'|'sequential_logic'}` or a `\WP_Error` (`no_next_tier`, `no_next_tier_product`).

---

### `reprice_bundle_renewal_line_item( \WC_Order_Item_Product $item, int $item_id, int $membership_post_id, int $user_id, int $old_bundle_post_id ): true|string`

Resolves the member's term-ahead tier/product via `resolve_sequential_logic_succession()` and sets `product_id`/`variation_id`/`name`/`tax_class`/`subtotal`/`total` on the line item from it. Fires `wicket_mship_bundle_renewal_charge_tier_product`; a non-null override is validated via `validate_charge_tier_product_override()` (below) and falls back to the resolved default on any mismatch. Uses `WC_Product::get_price()`, so sale prices are honored.

Writes the resolved tier as item meta — `_wicket_bundle_renewal_resolved_tier_post_id` — unconditionally on every successful call, whether or not the tier actually changed. `Membership_Bundle_Cron_Controller::process_bundle_renewal_members()` treats its presence as proof repricing completed; its absence signals a genuine failure or a pre-Milestone-9 renewal order.

When the decision isn't `unchanged`, also writes:
- `_wicket_bundle_renewal_decision_source`: `sequential_logic` or `filter_override`.
- `_wicket_bundle_renewal_previous_tier_post_id` / `_wicket_bundle_renewal_previous_product_id`: the old membership's tier/product before resolution.
- `_wicket_bundle_renewal_decided_at`: `current_time('c')`, ISO 8601 with offset.

Returns `true` on success, or a short error code string on failure (`missing_tier_post_id`, a `resolve_sequential_logic_succession()` error code, or `product_not_found`) — never throws.

---

### `validate_charge_tier_product_override( array $override ): array|null`

Validates a `wicket_mship_bundle_renewal_charge_tier_product` override against its claimed tier's own `get_product_ids()`/`get_product_variation_ids()`. A `product_id` that only matches the tier's variation list is normalised into the `variation_id` slot. Returns `null` (fail closed) if the tier/product pair doesn't actually belong together.

---

### `refresh_bundle_renewal_line_item_meta( array $items, \WC_Order $new_order, \WC_Subscription $subscription ): array`

Hooked to WCS's renewal-order-specific dynamic filter (`wcs_{$type}_items` with `$type = 'renewal_order'`, fired from `wcs_create_order_from_subscription()`), not the generic `wcs_new_order_items` — so this never fires for a resubscribe order. Fixes the staleness gap `Membership_Bundle::add_subscription_line_item()`'s filter accepted at add-time: without this, a member's line-item meta (client-extensibility meta and `_member_name`) never refreshes after first add, for the life of the bundle.

**Flow:**

1. Gated on `$_ENV['WICKET_MSHIP_ENABLE_BUNDLES']` and scoped to bundle subscriptions only (same `membership_subscription_id` lookup pattern as `apply_bundle_renewal_line_item_price_filter()`). Non-bundle renewals pass through untouched.
2. `$items` are the **subscription's own** `WC_Order_Item` objects at this point — WCS has not yet created the renewal order's items. Loops them, resolving each to `_membership_post_id` and `user_id` (cached).
3. Refreshes `_member_name` from the current `WP_User::$display_name`.
4. Fires `wicket_mship_bundle_line_item_extra_meta` — the same filter `add_subscription_line_item()` fires at add-time — with a trailing `$is_renewal = true` argument. Writes whatever keys the callback returns via `update_meta_data()`.
5. Saves the item. Both steps run inside a per-item try/catch (log-and-continue).

**Why writing to the subscription's item is sufficient:** `wcs_copy_order_item()` copies every meta key (except `_reduced_stock`) from the subscription's item onto the fresh renewal-order item immediately after this filter runs. Refreshing the subscription's item here therefore reaches both objects from one write.

**Defensive verification:** `apply_bundle_renewal_line_item_price_filter()` (on `wcs_renewal_order_created`, which fires after this method) checks that `_member_name` actually landed on the real renewal-order item and writes it directly if absent, so a future WCS change to `wcs_copy_order_item()`'s meta-copy behavior can't silently reintroduce the staleness gap.

## Related

- `Membership_Bundle_Cron_Controller::process_bundle_renewal_members()` — reads `_wicket_bundle_renewal_resolved_tier_post_id` this class writes; separate Action Scheduler job, no shared in-request cache
- `Membership_Bundle_WP_REST_Controller::create_bundle_renewal_order()` / `confirm_bundle_renewal()` — the two REST callers of `claim_renewal_order_creation()`
- `Membership_Bundle::add_subscription_line_item()` — fires `wicket_mship_bundle_line_item_extra_meta` at add-time; `refresh_bundle_renewal_line_item_meta()` re-fires it on renewal
