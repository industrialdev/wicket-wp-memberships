---
title: "Order_Touchpoint_Hold"
audience: [developer]
php_class: Order_Touchpoint_Hold
source_files: ["includes/Order_Touchpoint_Hold.php"]
---

# Order_Touchpoint_Hold

Holds `wicket-wp-base-plugin`'s WooCommerce order touchpoint until a bundle renewal order is fully built.

**Why:** `Membership_Bundle_Renewal_Order_Controller::create_renewal_order_job()` builds the bundle renewal order in an Action Scheduler job. The base plugin's `woocommerce_order_touchpoint()` (hooked on `woocommerce_new_order` and `woocommerce_order_status_changed`, priority 9999, when its "WooCommerce order touchpoints" setting is on) fires on that order's first save, before its line items, org, or repriced totals exist. The MDP keeps only the first touchpoint per order + status (`external_event_id` = `{order_id}_{status}`), so it would record an empty "Order Pending" that can never be corrected.

**Coupling:** depends on the base plugin's function name, priority, and argument counts. If the base plugin changes how it registers the order touchpoint, update this class.

## Methods

### `create_renewal_order( \WC_Subscription $subscription ): \WC_Order|\WP_Error` _(static)_

Unhooks `woocommerce_order_touchpoint()`, calls `wcs_create_renewal_order( $subscription )`, re-hooks it in a `finally`, then calls it once for the returned order (re-read with `wc_get_order()`). The touchpoint's content, action, and `external_event_id` are the base plugin's own; only the timing changes. Status changes during creation (e.g. repricing failure → `on-hold`) are held too, so the single touchpoint reflects the order's final status.

- Writes nothing if creation returns a `WP_Error` or throws.
- When the base plugin's order touchpoints are disabled, just creates the order and never re-hooks anything.

## Callers

- `Membership_Bundle_Renewal_Order_Controller::create_renewal_order_job()` — creates the bundle renewal order through this class, so the touchpoint includes the repriced line items, totals, and org.
