---
title: Membership_Bundle_Config
---

# Membership_Bundle_Config

`Membership_Bundle_Config` represents a bundle configuration record (`wicket_mship_bcfg` CPT). It is the authoritative source for date calculations, renewal windows, grace periods, and renewal type for any bundle that references it.

**File:** `includes/Membership_Bundle_Config.php`  
**Namespace:** `Wicket_Memberships`  
**CPT slug:** `wicket_mship_bcfg`

This class is read-only from a business-logic standpoint — it performs no status transitions, creates no WooCommerce objects, and has no side effects. You load it to read configuration and calculate dates.

## Method summary

- **[Identity](#identity)**
  - [`get_post_id()`](#get_post_id) — Get this config record's post ID
  - [`get_title()`](#get_title) — Get the config's display name

- **[Date calculation](#date-calculation)**
  - [`get_membership_dates()`](#get_membership_dates) — Calculate the full set of lifecycle dates for a new or renewing membership — the primary reason to use this class
  - [`get_cycle_type()`](#get_cycle_type) — Determine whether this config uses anniversary or calendar dating
  - [`get_period_data()`](#get_period_data) — Get the WooCommerce billing period used when creating the bundle subscription
  - [`get_calendar_seasons()`](#get_calendar_seasons) — List all configured calendar seasons
  - [`get_current_calendar_season()`](#get_current_calendar_season) — Get the season matching today's date
  - [`is_valid_renewal_date()`](#is_valid_renewal_date) — Check whether a given date falls within the renewal window

- **[Renewal window](#renewal-window)**
  - [`get_renewal_window_days()`](#get_renewal_window_days) — Number of days before `ends_at` that renewal opens
  - [`get_renewal_window_callout_header()`](#get_renewal_window_callout_header) — Localized heading for the renewal prompt
  - [`get_renewal_window_callout_content()`](#get_renewal_window_callout_content) — Localized body copy for the renewal prompt
  - [`get_renewal_window_callout_button_label()`](#get_renewal_window_callout_button_label) — Localized button label for the renewal prompt

- **[Grace period](#grace-period)**
  - [`get_late_fee_window_days()`](#get_late_fee_window_days) — Number of days after `ends_at` before the bundle expires
  - [`get_late_fee_window_callout_header()`](#get_late_fee_window_callout_header) — Localized heading for the grace period prompt
  - [`get_late_fee_window_callout_content()`](#get_late_fee_window_callout_content) — Localized body copy for the grace period prompt
  - [`get_late_fee_window_callout_button_label()`](#get_late_fee_window_callout_button_label) — Localized button label for the grace period prompt
  - [`get_late_fee_window_product_id()`](#get_late_fee_window_product_id) — WC product ID for a late fee charge (field exists; UI currently hidden)

- **[Renewal type](#renewal-type)**
  - [`get_renewal_type()`](#get_renewal_type) — Returns `'subscription'` or `'form_page'`
  - [`is_renewal_subscription()`](#is_renewal_subscription) — Check whether this config uses WooCommerce Subscriptions for renewal
  - [`is_renewal_form_page()`](#is_renewal_form_page) — Check whether this config uses a form page for renewal
  - [`get_renewal_form_page_id()`](#get_renewal_form_page_id) — Get the WP page ID for the renewal form

- **[Approval settings](#approval-settings)**
  - [`is_approval_required()`](#is_approval_required) — Check whether new seats require admin approval before activating
  - [`is_grant_owner_assignment()`](#is_grant_owner_assignment) — Check whether the bundle owner can assign members without admin approval
  - [`get_approval_email()`](#get_approval_email) — Get the email address that receives approval notifications
  - [`get_approval_callout_header()`](#get_approval_callout_header) — Localized heading shown to members awaiting approval
  - [`get_approval_callout_content()`](#get_approval_callout_content) — Localized body copy shown to members awaiting approval
  - [`get_approval_callout_button_label()`](#get_approval_callout_button_label) — Localized button label shown to members awaiting approval

- **[Write](#updating-config-data)**
  - [`update_bundle_config_data()`](#update_bundle_config_data) — Replace the renewal type and approval settings in one call

## Basic usage

Access a bundle's config through the bundle object:

```php
$bundle = new \Wicket_Memberships\Membership_Bundle( $bundle_post_id );
$config = $bundle->get_config();
```

Or instantiate directly with a post ID:

```php
$config = new \Wicket_Memberships\Membership_Bundle_Config( $config_post_id );

if ( $config->get_post_id() === 0 ) {
    // Post not found or wrong CPT
}
```

## Available methods

### Identity

#### `get_post_id()`

```php
public function get_post_id(): int
```

Returns the post ID of this config record. Returns `0` if the config failed to load.

#### `get_title()`

```php
public function get_title(): string
```

Returns the post title of this config record.

---

### Date calculation

#### `get_membership_dates()`

```php
public function get_membership_dates( array $membership = [] ): array
```

Calculates and returns the full set of membership dates for a bundle using this config. Called during `Membership_Bundle::create()` and `renew_bundle()` — also call it directly to preview dates before creating a bundle.

**Parameters**

| Name | Type | Required | Description |
|---|---|---|---|
| `$membership` | `array` | No | Pass `['membership_ends_at' => 'YYYY-MM-DD']` for renewal calculations. Pass `['start_date' => 'YYYY-MM-DD']` to anchor to a specific start. Omit to anchor to today. |

:::details Returns
```php
[
    'starts_at'      => string,  // ISO 8601 UTC
    'ends_at'        => string,  // ISO 8601 UTC
    'expires_at'     => string,  // ISO 8601 UTC (empty if no grace period)
    'early_renew_at' => string,  // ISO 8601 UTC (empty if no renewal window)
]
```
:::

:::details Example
```php
// New membership starting today
$dates = $config->get_membership_dates();

// Renewal — starts the day after current term ends
$dates = $config->get_membership_dates([
    'membership_ends_at' => '2025-12-31',
]);

// New membership with a specific start date
$dates = $config->get_membership_dates([
    'start_date' => '2025-06-01',
]);
```
:::

#### `get_cycle_type()`

```php
public function get_cycle_type(): string|false
```

Returns `'anniversary'` or `'calendar'`, or `false` if not configured. Determines how `get_membership_dates()` calculates the end date.

#### `get_period_data()`

```php
public function get_period_data(): array
```

Returns the WooCommerce billing period for this config. Used when creating the bundle's WooCommerce subscription.

**Returns:** `['period_count' => int, 'period_type' => string]` — e.g. `['period_count' => 1, 'period_type' => 'year']`. Calendar configs default to `1 year`.

#### `get_calendar_seasons()`

```php
public function get_calendar_seasons(): array|false
```

Returns all configured calendar seasons, or `false` if not a calendar config. Dates are converted to full ISO 8601 with the MDP timezone offset.

:::details Returns
```php
[
    [
        'start_date' => string,  // ISO 8601 UTC — season start
        'end_date'   => string,  // ISO 8601 UTC — season end
        'active'     => bool,    // whether this season is currently active
    ],
    // ...one entry per configured season
]
```
:::

:::details Example
```php
$seasons = $config->get_calendar_seasons();

foreach ( $seasons as $season ) {
    echo $season['start_date']; // e.g. "2025-01-01T00:00:00+00:00"
    echo $season['end_date'];   // e.g. "2025-12-31T23:59:59+00:00"
}
```
:::

#### `get_current_calendar_season()`

```php
public function get_current_calendar_season(): array|false
```

Returns the season whose date range contains today and whose `active` flag is `true`, or `false` if none matches. Returns the same shape as a single entry from `get_calendar_seasons()`.

:::details Example
```php
$season = $config->get_current_calendar_season();

if ( $season ) {
    echo $season['end_date']; // when the current season ends
}
```
:::

#### `is_valid_renewal_date()`

```php
public function is_valid_renewal_date( array $membership, ?string $date = null ): mixed
```

Checks whether a given date (default: today) falls within the renewal window.

**Parameters**

| Name | Type | Required | Description |
|---|---|---|---|
| `$membership` | `array` | Yes | Membership data. Include `membership_ends_at` for the current term end. |
| `$date` | `string\|null` | No | ISO 8601 date to check. Defaults to today. |

:::details Example
```php
$can_renew = $config->is_valid_renewal_date(
    membership: [ 'membership_ends_at' => '2025-12-31' ],
    date:       '2025-11-15'
);
```
:::

---

### Renewal window

The period before `ends_at` during which renewal is permitted.

#### `get_renewal_window_days()`

```php
public function get_renewal_window_days(): int|false
```

Returns the number of days in the renewal window, or `false` if not configured.

#### `get_renewal_window_callout_header()`

```php
public function get_renewal_window_callout_header( string $lang = 'en' ): string|false
```

Returns the localized heading for the renewal callout, or `false` if not set for the given language.

#### `get_renewal_window_callout_content()`

```php
public function get_renewal_window_callout_content( string $lang = 'en' ): string|false
```

Returns the localized body copy for the renewal callout, or `false` if not set.

#### `get_renewal_window_callout_button_label()`

```php
public function get_renewal_window_callout_button_label( string $lang = 'en' ): string|false
```

Returns the localized button label for the renewal callout, or `false` if not set.

:::details Example
```php
$header  = $config->get_renewal_window_callout_header( 'fr' );
$content = $config->get_renewal_window_callout_content( 'fr' );
$button  = $config->get_renewal_window_callout_button_label( 'fr' );
```
:::

---

### Grace period

The window after `ends_at` during which the bundle is in `grace-period` status before expiring.

#### `get_late_fee_window_days()`

```php
public function get_late_fee_window_days(): int|false
```

Returns the number of days in the grace period, or `false` if not configured. When not configured, `expires_at` equals `ends_at` and the bundle expires immediately after the end date passes.

#### `get_late_fee_window_callout_header()`

```php
public function get_late_fee_window_callout_header( string $lang = 'en' ): string|false
```

Returns the localized heading for the grace period callout, or `false` if not set.

#### `get_late_fee_window_callout_content()`

```php
public function get_late_fee_window_callout_content( string $lang = 'en' ): string|false
```

Returns the localized body copy for the grace period callout, or `false` if not set.

#### `get_late_fee_window_callout_button_label()`

```php
public function get_late_fee_window_callout_button_label( string $lang = 'en' ): string|false
```

Returns the localized button label for the grace period callout, or `false` if not set.

#### `get_late_fee_window_product_id()`

```php
public function get_late_fee_window_product_id(): int|false
```

Returns the late fee WooCommerce product ID, or `false`. This field exists in the data structure but the admin UI is currently hidden — returns `false` in most configurations.

---

### Renewal type

#### `get_renewal_type()`

```php
public function get_renewal_type(): string|false
```

Returns `'subscription'` or `'form_page'`, or `false` if not set. See [Renewal Types](../concepts/renewal-types.md) for a full explanation.

#### `is_renewal_subscription()`

```php
public function is_renewal_subscription(): bool
```

Returns `true` if `renewal_type` is `'subscription'`.

#### `is_renewal_form_page()`

```php
public function is_renewal_form_page(): bool
```

Returns `true` if a `renewal_form_page_id` is set on the config.

#### `get_renewal_form_page_id()`

```php
public function get_renewal_form_page_id(): int|false
```

Returns the WordPress page post ID for the renewal form page, or `false`.

:::details Example
```php
if ( $config->is_renewal_form_page() ) {
    $renewal_url = get_permalink( $config->get_renewal_form_page_id() );
}
```
:::

---

### Approval settings

Some configs require admin approval before new member seats become active.

#### `is_approval_required()`

```php
public function is_approval_required(): int|false
```

Returns `1` if approval is required before a seat becomes active, `false` otherwise. When required, newly added seats start in `pending` status instead of inheriting the bundle's active status.

#### `is_grant_owner_assignment()`

```php
public function is_grant_owner_assignment(): int|false
```

Returns `1` if the bundle owner can assign members without admin approval, `false` otherwise.

#### `get_approval_email()`

```php
public function get_approval_email(): string|false
```

Returns the email address that receives approval notifications, or `false` if not set.

#### `get_approval_callout_header()`

```php
public function get_approval_callout_header( string $lang = 'en' ): string|false
```

Returns the localized heading shown to members awaiting approval, or `false` if not set.

#### `get_approval_callout_content()`

```php
public function get_approval_callout_content( string $lang = 'en' ): string|false
```

Returns the localized body copy shown to members awaiting approval, or `false` if not set.

#### `get_approval_callout_button_label()`

```php
public function get_approval_callout_button_label( string $lang = 'en' ): string|false
```

Returns the localized button label shown to members awaiting approval, or `false` if not set.

---

### Updating config data

#### `update_bundle_config_data()`

```php
public function update_bundle_config_data( array $new_bundle_config_data ): void
```

Replaces the `bundle_config_data` meta array (renewal type, form page ID, approval settings) and refreshes the in-memory cache so subsequent reads on the same instance reflect the change.

**Parameters**

| Name | Type | Required | Description |
|---|---|---|---|
| `$new_bundle_config_data` | `array` | Yes | Replacement array for `bundle_config_data` meta. |

:::details Example
```php
$config->update_bundle_config_data([
    'renewal_type'         => 'form_page',
    'renewal_form_page_id' => 99,
    'approval_required'    => 1,
]);
```
:::

## Advanced usage

### Checking all config settings at once

```php
$config = $bundle->get_config();

$summary = [
    'cycle'        => $config->get_cycle_type(),
    'renewal_type' => $config->get_renewal_type(),
    'renewal_days' => $config->get_renewal_window_days(),
    'grace_days'   => $config->get_late_fee_window_days(),
    'approval'     => $config->is_approval_required(),
];
```

### Calculating renewal dates

```php
$current_ends_at = $bundle->get_dates()['ends_at'];

$next_dates = $bundle->get_config()->get_membership_dates([
    'membership_ends_at' => $current_ends_at,
]);

// $next_dates['starts_at'] — day after current ends_at
// $next_dates['ends_at']   — end of next term
```
