---
title: Renewal Types
---

# Renewal Types

A bundle config defines how the bundle is renewed when its membership period ends. There are three renewal types, plus two time windows that govern when renewal activity is permitted or promoted.

## Renewal type: `subscription`

The bundle renews automatically via WooCommerce Subscriptions. When the subscription's `next_payment` date is reached, WooCommerce processes the renewal payment and `Membership_Controller::handle_bundle_renewal()` is triggered to create a new bundle term and re-provision all member seats.

Check the renewal type on a config:

```php
$config = $bundle->get_config();

if ( $config->is_renewal_subscription() ) {
    // WooCommerce Subscriptions drives renewal
}
```

When a bundle config's renewal type is `subscription`, the bundle's WooCommerce subscription will have a `next_payment` date set to `ends_at`. When the bundle is activated (`pending → active`), this date is set on the subscription automatically.

If the renewal type is later changed to something other than `subscription` on an active bundle (via the edit endpoint), the `next_payment` date is removed from the subscription so WooCommerce does not trigger an unwanted renewal payment.

## Renewal type: `form_page`

Renewal is handled manually through a WordPress page containing a renewal form (typically a Gravity Forms integration). No automatic WooCommerce renewal payment occurs. The bundle's WooCommerce subscription will have no `next_payment` date.

**How the flow works:** When a bundle's `early_renew_at` date is reached, the `wicket_memberships_bundle_renewal_period_open` action fires. You use this hook (or AutomateWoo) to notify the bundle owner and direct them to the renewal form page. The form page itself is responsible for collecting payment and creating the new bundle term — the plugin does not handle form submission. The form page ID is stored on the config and exposed via the admin edit page (`membership_next_tier_form_page_id` post meta on the bundle) so frontend code can build the correct renewal URL.

```php
if ( $config->is_renewal_form_page() ) {
    $page_id     = $config->get_renewal_form_page_id();
    $renewal_url = get_permalink( $page_id );
}
```

```php
// Listen for the renewal window opening to send the owner to the form
add_action( 'wicket_memberships_bundle_renewal_period_open', function( int $bundle_post_id ) {
    $bundle      = new \Wicket_Memberships\Membership_Bundle( $bundle_post_id );
    $config      = $bundle->get_config();
    $renewal_url = $config->is_renewal_form_page()
        ? get_permalink( $config->get_renewal_form_page_id() )
        : null;

    // send notification to $bundle->get_owner()['email'] with $renewal_url
} );
```

## Renewal type: `confirmation_renewal`

Renewal order creation is suppressed automatically, just like `form_page`. Instead of directing the owner to an external form, the bundle owner confirms renewal directly through the Account Center's renewal-window callout — the same callout UI `subscription` and `form_page` bundles already show, reusing its existing copy (header/content/button label) with no new fields on the config. The renewal order is only created when the owner explicitly confirms via `POST /bundle/{bundle_post_id}/confirm_renewal` (see [Confirm a renewal](../endpoints/bundle-status.md#confirm-a-renewal-confirmation-renewal-bundles)).

```php
if ( $config->is_renewal_confirmation() ) {
    // Bundle owner must explicitly confirm via the confirm_renewal endpoint
}
```

**Mechanics:** like `form_page`, the bundle's `next_payment` date is suppressed — set on activation (`activate_subscription_for_dates()`), each subsequent renewal term (`renew_bundle()`), and any admin date edit (`sync_subscription_dates()`) — so WooCommerce Subscriptions never auto-fires the renewal payment on its own schedule. The confirm endpoint calls the exact same `wcs_create_renewal_order()` the admin's manual "create renewal order" tool uses; from there, everything downstream (`catch_order_completed()` → `handle_bundle_renewal()`) is identical to any other bundle renewal.

**Confirm window:** the confirm action is only accepted between `early_renew_at` and `ends_at` — the same window that governs the renewal-window callout itself. A confirm attempt outside that window, by anyone other than the bundle's owner, or on a bundle not configured with `confirmation_renewal`, is rejected. A second confirm once a renewal order already exists for the current cycle is rejected as already-renewed rather than creating a duplicate order.

## Renewal window

The renewal window is the period before `ends_at` during which renewal is permitted. It is defined in days on the bundle config. The `early_renew_at` date is calculated as `ends_at - renewal_window_days`, snapped to the end of that day in the MDP timezone.

```php
$days = $config->get_renewal_window_days();
// e.g. 30 — renewal is open for the 30 days before membership ends
```

When `early_renew_at` is reached, the Action Scheduler job fires `wicket_memberships_bundle_renewal_period_open`. You can listen to this hook to trigger notifications or unlock renewal UI.

The config also provides callout copy for the renewal window — header, body content, and button label — in a specified language:

```php
$header  = $config->get_renewal_window_callout_header( 'en' );
$content = $config->get_renewal_window_callout_content( 'en' );
$button  = $config->get_renewal_window_callout_button_label( 'en' );
```

Use these values to populate a frontend renewal prompt without hardcoding strings.

### Checking whether a date is a valid renewal date

```php
// Pass the existing membership data array and optionally a date to check
$is_valid = $config->is_valid_renewal_date(
    membership: [ 'membership_ends_at' => '2025-12-31' ],
    date:       '2025-12-01'
);
```

## Grace period (late fee window)

The grace period is the window after `ends_at` during which a bundle is still considered accessible, though the membership period has technically ended. The bundle moves to `grace-period` status when `ends_at` passes, and to `expired` when `expires_at` passes.

The number of days in the grace period comes from `late_fee_window_days` on the config:

```php
$grace_days = $config->get_late_fee_window_days();
// false if no grace period is configured
```

When no grace period is configured, `expires_at` equals `ends_at` and the bundle expires immediately after the end date passes.

Like the renewal window, the grace period callout copy is available per language:

```php
$header  = $config->get_late_fee_window_callout_header( 'en' );
$content = $config->get_late_fee_window_callout_content( 'en' );
$button  = $config->get_late_fee_window_callout_button_label( 'en' );
```

::: tip
The late fee product field (`get_late_fee_window_product_id()`) exists in the data structure and is readable, but the late fee product UI is currently not surfaced in the admin. The field will return `false` unless it was populated directly.
:::

## Calendar vs. anniversary cycles

The `cycle_type` on the config determines how membership dates are calculated.

**Anniversary** — dates are relative to the activation date. A one-year anniversary membership activated on March 15 ends on March 14 of the following year.

**Calendar** — dates snap to configured seasons. If the config defines a season from January 1 to December 31, all calendar memberships activated during that year share the same end date regardless of when they start.

```php
$cycle_type = $config->get_cycle_type();
// 'anniversary' or 'calendar'

if ( $cycle_type === 'calendar' ) {
    $seasons = $config->get_calendar_seasons();
    $current = $config->get_current_calendar_season();
}
```

## Calculating membership dates from a config

To preview what dates a bundle will receive before creating it, call `get_membership_dates()` on the config:

```php
$config = new \Wicket_Memberships\Membership_Bundle_Config( $config_post_id );

// Dates for a new membership starting today:
$dates = $config->get_membership_dates();

// Dates for a renewal (starting the day after ends_at):
$dates = $config->get_membership_dates([
    'membership_ends_at' => '2025-12-31',
]);

// Dates for a new membership with a specific start date:
$dates = $config->get_membership_dates([
    'start_date' => '2025-06-01',
]);

// $dates['starts_at']      — ISO 8601
// $dates['ends_at']        — ISO 8601
// $dates['expires_at']     — ISO 8601 (empty string if no grace period)
// $dates['early_renew_at'] — ISO 8601 (empty string if no renewal window)
```

All dates are stored and returned in UTC, with day boundaries snapped to the MDP timezone. Do not snap dates to UTC midnight directly.

You can also calculate dates via the REST API without instantiating the class — see [Bundle Config Dates](../endpoints/bundle-config-dates.md).

## Per-member tier succession on renewal

The renewal types above govern the **bundle container's** own renewal mechanics. Separately, each **member's** `Membership_Tier` has its own `renewal_type` field (`current_tier`, `sequential_logic`, `form_flow`, or `subscription`) that determines which tier/product a member renews into when the bundle renews.

`Membership_Bundle_Cron_Controller::process_bundle_renewal_members()` checks each member's old tier at renewal time and resolves their new tier/product accordingly:

- **`sequential_logic`** — the member auto-advances to the tier's configured `next_tier_id`, with no admin or member action required. The next tier's product is resolved as the tier's first configured product, preferring a variation over the parent product if one exists — the same deterministic pick `Import_Controller::create_bundle_member()` uses for CSV-imported members. If the next tier has more than one product, the member/bundle owner is **not** asked which one applies; this is expected, existing behavior, not new to this fix.
- **`current_tier`** — the member renews into the same tier and product, unchanged.
- **`form_flow`** — treated identically to `current_tier` for bundle renewal: the member renews into the same tier/product unchanged. This is a deliberate divergence from what `form_flow` means for a standalone individual membership (an external form gates the renewal). Bundle renewal is a batch, hands-off cron process with no per-member interruption point, so that gating is not enforced here. Avoid assigning `form_flow` tiers to bundle members if that gating behavior matters for the tier.

`next_tier_id` is re-evaluated fresh on every renewal cycle from whichever tier the member currently holds — it is not a pre-resolved multi-hop chain. Editing a tier's `next_tier_id` between renewal cycles is picked up automatically on the member's next renewal.

## Overriding which tier/product a member renews into

A `wicket_mship_bundle_renewal_member_tier_product` filter lets a child theme fully override the tier/product decision above — for example, when a client's own succession logic (not the built-in `renewal_type`/`next_tier_id` config) should decide instead.

```php
add_filter( 'wicket_mship_bundle_renewal_member_tier_product', function ( $override, $old_membership_post_id, $user_id, $new_bundle_post_id, $old_bundle_post_id, $core_default ) {
    $my_result = my_client_succession_lookup( $user_id, $old_membership_post_id, $old_bundle_post_id );

    if ( ! empty( $my_result ) ) {
        return [
            'tier_post_id' => $my_result['tier_post_id'],
            'product_id'   => $my_result['product_id'],
        ];
    }

    return null; // not eligible / no answer: the default above stands
}, 10, 6 );
```

**Full override, not a merge — null-or-array contract.** The filter's default value is `null`, never the core default. A **non-null** return (`['tier_post_id' => ..., 'product_id' => ...]`) fully replaces the tier/product resolved above for that member. A **null** return means "no override — the resolution above stands." This is stricter than an always-populated array: core never has to guess whether an unchanged value means "confirmed" or "didn't answer" — only non-null counts as an answer.

**Fires unconditionally for every renewing member, regardless of `renewal_type`** — not gated to only `sequential_logic` members. The above resolution always runs first; this filter then runs on top for every member, every time, with no exceptions.

**No validation on the override's return value.** If a callback returns an invalid `tier_post_id`/`product_id` pair (tier doesn't exist, product not on that tier), it is passed straight into the same `add_member()` call every other renewal uses. Whatever error results (`invalid_tier`, `ambiguous_product`, `product_not_found`, etc.) surfaces as a normal renewal failure in the batch's error tracking — the same as any other renewal error. This is deliberate: a buggy override fails loud and visible rather than being silently caught or falling back to the default.

**Scope: bundle renewal only** — not added to the standalone individual-membership renewal path, which already derives its tier/product from an actual purchase event.

## Per-member price/fee adjustment on renewal

A `wicket_mship_bundle_renewal_line_item_price` filter fires once per member's line item as a bundle's renewal order is built, letting a child theme apply a per-member price adjustment, discount, or fee — for example, a late fee for a member who missed their individual renewal window, or a promo-code-style discount.

```php
add_filter( 'wicket_mship_bundle_renewal_line_item_price', function ( $unused, $item, $item_id, $membership_post_id, $user_id, $renewal_order ) {
    $adjustment = my_client_lookup_adjustment( $user_id, $membership_post_id );
    if ( empty( $adjustment ) ) {
        return null; // not eligible: leave the item untouched
    }

    // Adjust this line item's own price directly:
    $item->set_total( $item->get_total() + $adjustment['amount'] );
    $item->set_subtotal( $item->get_subtotal() + $adjustment['amount'] );

    // Or add a separate line instead of adjusting this item's own price:
    // $renewal_order->add_fee( [ 'name' => 'Late fee', 'total' => $adjustment['amount'] ] );

    return null; // return value is not read by core — see below
}, 10, 6 );
```

**Return value is never read or applied by core.** A callback communicates every change — price/subtotal adjustment, a separate fee or product line, or any other order-level effect — by mutating the passed `$item` and/or `$renewal_order` directly, using the normal WooCommerce API (`$item->set_total()`/`set_subtotal()`, `$renewal_order->add_fee()`, `add_product()`, etc.).

**Fires per member/line-item, not once for the whole order**, so one member's callback throwing is logged and skipped without aborting the rest of the renewal order. `calculate_totals()` is called once after every item's callback has run.

**Fires on the actual renewal order WCS bills the customer on** (WCS's own `wcs_renewal_order_created`), not on this plugin's own renewal batch cron (`process_bundle_renewal_members()`), which re-provisions membership records on a decoupled cadence and does not reliably correspond to the order actually being charged.

**Idempotency across renewal cycles is the callback's own responsibility.** Core does not detect or prevent a callback from adding the same fee/product line on every cycle — if a callback should only apply a fee once, it must check for an existing line itself (e.g. via order-item meta linking back to the membership post).

::: tip Not doing
There is no native rule-based pricing engine or promo-code support in core, and none is planned — this filter is the only mechanism, and any pricing/eligibility logic lives entirely in whatever answers it.
:::
