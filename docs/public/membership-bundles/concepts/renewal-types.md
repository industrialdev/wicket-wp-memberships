---
title: Renewal Types
---

# Renewal Types

A bundle config defines how the bundle is renewed when its membership period ends. There are two renewal types, plus two time windows that govern when renewal activity is permitted or promoted.

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
