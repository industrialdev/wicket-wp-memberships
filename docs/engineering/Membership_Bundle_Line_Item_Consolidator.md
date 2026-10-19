---
title: "Membership_Bundle_Line_Item_Consolidator"
audience: [developer]
php_class: Membership_Bundle_Line_Item_Consolidator
source_files: ["includes/Membership_Bundle_Line_Item_Consolidator.php"]
---

# Membership_Bundle_Line_Item_Consolidator

Combines identical line items added to a bundle renewal order into one line with a quantity, and records the consolidation in an order note.

**Architecture position:** `Membership_Bundle_Renewal_Order_Controller::apply_bundle_renewal_line_item_price_filter()` decides whether to run it (Line Item Consolidation setting, then the `wicket_mship_bundle_renewal_consolidate_line_items` filter) and which lines are eligible (those added by `wicket_mship_bundle_renewal_line_item_price` callbacks, tracked per member). This class only does the matching and merging.

## Methods

### `consolidate( \WC_Order $renewal_order, array $added_line_members, bool $forced_by_filter ): void` _(static)_

`$added_line_members` maps each added line item ID to the membership post IDs whose callback added it. `$forced_by_filter` is true when the filter enabled consolidation while the setting was off.

Combines identical product lines that `wicket_mship_bundle_renewal_line_item_price` callbacks added — e.g. the same late fee for several members — into one line with a quantity.

- **Scope:** only line items added during the per-member loop. Member lines (`_membership_post_id`) and lines copied from the subscription are never touched. Fee lines (`add_fee()`) are not combined — WooCommerce fee lines have no quantity. Lines added with `add_item()` but not yet saved are skipped: they have no ID, so they can't be removed or attributed.
- **Match rule** (`line_item_signature()`): product ID, variation ID, name, tax class, unit subtotal, unit total, and every item meta key/value. Any difference keeps lines separate. A line whose meta can't be JSON-encoded is never merged.
- **Merge:** the first line keeps the summed quantity, subtotal, and total; the others are queued for removal. `_wicket_bundle_renewal_member_post_ids` on the kept line lists every member whose callback added one of the merged lines (a callback that raises another line's quantity instead of adding its own isn't attributed). Per-unit meta on the kept line, such as WooCommerce's backorder note, is carried over unchanged.
- **Caller contract:** run `calculate_totals()` afterwards. It recalculates taxes and its save deletes the removed lines. With per-line tax rounding, tax on one merged line can differ by a cent from the sum of the separate lines' tax.
- **Order note** (private, only when something combined): a bold "Membership Bundles: line item consolidation" heading, then "Combined N identical lines into M line(s):" and a bulleted list of `name × quantity`. Ends with "(enabled by code filter)" when the filter turned it on while the setting was off.

### `line_item_signature( \WC_Order_Item_Product $item ): ?string` _(private, static)_

Returns `null` (never merge) if any part fails to JSON-encode; otherwise an MD5 of product ID, variation ID, name, tax class, unit subtotal, unit total, and the sorted list of every item meta key/value.
