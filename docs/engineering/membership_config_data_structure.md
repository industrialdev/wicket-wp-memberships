
# Membership_Config Data Structure, Types, and Renewal Logic

This document summarizes the different types of config values, data structures, and renewal logic used in the `Membership_Config` class, as found in `includes/Membership_Config.php`. It also documents the key methods and how/why to use them for implementing membership renewal.

## Config Types

There are two types of configs that support memberships:

### 1. Anniversary-Based Configs
- Memberships renew based on the anniversary of the member’s join/start date.
- The config stores rules for how long a period lasts (e.g., 1 year, 6 months).
- Methods in the `Membership_Config` class handle how start/end/renewal dates are calculated for each member based on their unique start date.

### 2. Calendar-Based Configs
- Memberships renew on fixed calendar dates, defined by “seasons” (e.g., Jan 1–Dec 31, or custom periods).
- The config defines one or more calendar “seasons” with start and end dates.
- Methods in the `Membership_Config` class determine which season applies and how renewal/expiration is handled for all members in that config.

Both config types define how memberships renew, and the `Membership_Config` class contains specific methods to calculate and use these dates to support membership logic.

---

## Main Config Meta Fields

### 1. `renewal_window_data` (array)
- **days_count**: int — Number of days before end date when renewal is allowed.
- **locales**: array (by language code, e.g. 'en')
  - **callout_header**: string — Header for renewal callout.
  - **callout_content**: string — Content for renewal callout.
  - **callout_button_label**: string — Label for renewal button.

### 2. `late_fee_window_data` (array)
- **days_count**: int — Number of days after end date when late fee applies.
- **product_id**: int — Product ID for late fee.
- **locales**: array (by language code, e.g. 'en')
  - **callout_header**: string — Header for late fee callout.
  - **callout_content**: string — Content for late fee callout.
  - **callout_button_label**: string — Label for late fee button.

### 3. `cycle_data` (array)
- **cycle_type**: string — 'calendar' or 'anniversary'.
- **calendar_items**: array (if cycle_type is 'calendar')
  - **season_name**: string
  - **active**: bool
  - **start_date**: string (Y-m-d)
  - **end_date**: string (Y-m-d)
- **anniversary_data**: array (if cycle_type is 'anniversary')
  - **period_count**: int
  - **period_type**: string ('year', 'month', 'day')
  - **align_end_dates_enabled**: bool
  - **align_end_dates_type**: string ('first-day-of-month', '15th-of-month', 'last-day-of-month')

### 4. `multi_tier_renewal` (int|bool)
- 1 or true if multi-tier renewal is enabled, 0 or false otherwise.

## Value Access Patterns
- All main config fields are stored as post meta (serialized arrays for complex fields).
- Accessed via `get_post_meta($post_id, $key, true)`.
- The class provides getter methods for each logical value, e.g. `get_renewal_window_days()`, `get_late_fee_window_product_id()`, `get_cycle_type()`, etc.

## Example Structure

```
renewal_window_data = [
  'days_count' => 30,
  'locales' => [
    'en' => [
      'callout_header' => 'Renew Now!',
      'callout_content' => 'Renew your membership before it expires.',
      'callout_button_label' => 'Renew',
    ],
  ],
]
late_fee_window_data = [
  'days_count' => 10,
  'product_id' => 123,
  'locales' => [
    'en' => [
      'callout_header' => 'Late Fee Applies',
      'callout_content' => 'A late fee will be charged.',
      'callout_button_label' => 'Pay Late Fee',
    ],
  ],
]
cycle_data = [
  'cycle_type' => 'anniversary',
  'anniversary_data' => [
    'period_count' => 1,
    'period_type' => 'year',
    'align_end_dates_enabled' => true,
    'align_end_dates_type' => 'last-day-of-month',
  ],
]
multi_tier_renewal = 1
```

---

## Callout Display Logic

- The dates from the config control when callouts appear in the user's account center.
- The early renewal callout appears when the current date is within the `early_renewal_days` value before the `membership_end_date`.
- The grace period callout appears when the current date is within the `grace_period` days value after the `membership_end_date`.
- The early renewal date is always calculated in real time from the config value.
- The expired date of the membership is calculated using the grace period days and is set when the membership is created.


## Callout Rendering

- Callouts are displayed to users using a custom block in the `wicket-wp-account-centre` plugin.
- The block uses the response from the real method `get_membership_callouts` in the `Membership_Controller` class to determine which callouts to show and when.

> **Note:** When this documentation or any future reference describes a method by its purpose (e.g. "the callout method"), always look up and use the actual method name from the codebase. For callout display data, the real method is `get_membership_callouts`.

---

## Key Methods for Config Renewal and Callout Logic

These methods define how each config type (anniversary or calendar) controls membership renewal, start, end, expiration, and callout logic. Use these methods to implement or test membership renewal features.

### Config Type and Cycle Methods

- `get_cycle_type()` (public): Returns `'anniversary'` or `'calendar'` based on the config's `cycle_data`.
- `get_cycle_data()` (public): Returns the full cycle data array.

### Calendar-Based Methods

- `get_calendar_seasons()` (public): Returns an array of all defined calendar “seasons” (periods with start/end dates and active status), with dates in ISO 8601 format.
- `get_current_calendar_season()` (public): Returns the current active season (if any) based on today’s date.
- `get_seasonal_start_date($membership = [])` (private): Returns the start date for a membership in a calendar-based config.
- `get_seasonal_end_date($membership = [])` (private): Returns the end date for a membership in a calendar-based config.

### Anniversary-Based Methods

- `get_anniversary_start_date($membership = [])` (private): Returns the start date for a membership in an anniversary-based config.
- `get_period_data()` (public): Returns the period count and type (e.g., 1 year) for anniversary configs.

### General Membership Date Methods

- `get_membership_dates($membership = [])` (public): Main method to determine membership start and end dates. Uses `get_cycle_type()` to choose between anniversary and calendar logic. Also calculates early renewal and expiration dates based on config.
- `is_valid_renewal_date($membership, $date = null)` (public): Checks if a given date is within the valid renewal window for a membership, using the config’s rules.

---

**How to Use:**
- When implementing or testing membership renewal, always check the config’s type with `get_cycle_type()`.
- Use the appropriate methods above to calculate start, end, renewal, and expiration dates for memberships.
- Refer to this doc and the method summaries to understand the logic and data flow for each config type.

---

This doc can be referenced by developers and testers to understand the structure, types, and renewal logic of config values handled by the `Membership_Config` class.

# Membership_Config Data Structure, Types, and Renewal Logic

This document summarizes the different types of config values, data structures, and renewal logic used in the `Membership_Config` class, as found in `includes/Membership_Config.php`. It also documents the key methods and how/why to use them for implementing membership renewal.

## Config Types

There are two types of configs that support memberships:

### 1. Anniversary-Based Configs
- Memberships renew based on the anniversary of the member’s join/start date.
- The config stores rules for how long a period lasts (e.g., 1 year, 6 months).
- Methods in the `Membership_Config` class handle how start/end/renewal dates are calculated for each member based on their unique start date.

### 2. Calendar-Based Configs
- Memberships renew on fixed calendar dates, defined by “seasons” (e.g., Jan 1–Dec 31, or custom periods).
- The config defines one or more calendar “seasons” with start and end dates.
- Methods in the `Membership_Config` class determine which season applies and how renewal/expiration is handled for all members in that config.

Both config types define how memberships renew, and the `Membership_Config` class contains specific methods to calculate and use these dates to support membership logic.

---

## Main Config Meta Fields

### 1. `renewal_window_data` (array)
- **days_count**: int — Number of days before end date when renewal is allowed.
- **locales**: array (by language code, e.g. 'en')
  - **callout_header**: string — Header for renewal callout.
  - **callout_content**: string — Content for renewal callout.
  - **callout_button_label**: string — Label for renewal button.

### 2. `late_fee_window_data` (array)
- **days_count**: int — Number of days after end date when late fee applies.
- **product_id**: int — Product ID for late fee.
- **locales**: array (by language code, e.g. 'en')
  - **callout_header**: string — Header for late fee callout.
  - **callout_content**: string — Content for late fee callout.
  - **callout_button_label**: string — Label for late fee button.

### 3. `cycle_data` (array)
- **cycle_type**: string — 'calendar' or 'anniversary'.
- **calendar_items**: array (if cycle_type is 'calendar')
  - **season_name**: string
  - **active**: bool
  - **start_date**: string (Y-m-d)
  - **end_date**: string (Y-m-d)
- **anniversary_data**: array (if cycle_type is 'anniversary')
  - **period_count**: int
  - **period_type**: string ('year', 'month', 'day')
  - **align_end_dates_enabled**: bool
  - **align_end_dates_type**: string ('first-day-of-month', '15th-of-month', 'last-day-of-month')

### 4. `multi_tier_renewal` (int|bool)
- 1 or true if multi-tier renewal is enabled, 0 or false otherwise.

## Value Access Patterns
- All main config fields are stored as post meta (serialized arrays for complex fields).
- Accessed via `get_post_meta($post_id, $key, true)`.
- The class provides getter methods for each logical value, e.g. `get_renewal_window_days()`, `get_late_fee_window_product_id()`, `get_cycle_type()`, etc.

## Example Structure

```
renewal_window_data = [
  'days_count' => 30,
  'locales' => [
    'en' => [
      'callout_header' => 'Renew Now!',
      'callout_content' => 'Renew your membership before it expires.',
      'callout_button_label' => 'Renew',
    ],
  ],
]
late_fee_window_data = [
  'days_count' => 10,
  'product_id' => 123,
  'locales' => [
    'en' => [
      'callout_header' => 'Late Fee Applies',
      'callout_content' => 'A late fee will be charged.',
      'callout_button_label' => 'Pay Late Fee',
    ],
  ],
]
cycle_data = [
  'cycle_type' => 'anniversary',
  'anniversary_data' => [
    'period_count' => 1,
    'period_type' => 'year',
    'align_end_dates_enabled' => true,
    'align_end_dates_type' => 'last-day-of-month',
  ],
]
multi_tier_renewal = 1
```

---

## Key Methods for Config Renewal Logic

These methods define how each config type (anniversary or calendar) controls membership renewal, start, end, and expiration logic. Use these methods to implement or test membership renewal features.

### 1. `get_cycle_type()`
- Returns `'anniversary'` or `'calendar'` based on the config's `cycle_data`.
- Determines which renewal logic to use for a membership.

### 2. `get_calendar_seasons()`
- Returns an array of all defined calendar “seasons” (periods with start/end dates and active status).
- Converts season dates to ISO 8601 format.
- Used to determine which season applies to a membership.

### 3. `get_current_calendar_season()`
- Returns the current active season (if any) based on today’s date.
- Used to determine which calendar period a membership is in.

### 4. `get_seasonal_start_date($membership = [])` (private)
- Returns the start date for a membership in a calendar-based config.
- Uses today’s date or the day after the previous membership’s end.

### 5. `get_seasonal_end_date($membership = [])` (private)
- Returns the end date for a membership in a calendar-based config.
- Finds the season that matches the membership’s start time.

### 6. `get_anniversary_start_date($membership = [])` (private)
- Returns the start date for a membership in an anniversary-based config.
- Uses today’s date or the day after the previous membership’s end.

### 7. `get_period_data()`
- Returns the period count and type (e.g., 1 year) for anniversary configs.
- Defaults to 1 year if not set.

### 8. `get_membership_dates($membership = [])`
- Main method to determine membership start and end dates.
- Uses `get_cycle_type()` to choose between anniversary and calendar logic.
- For anniversary: calculates end date based on period and alignment settings.
- For calendar: uses seasonal start/end date methods.
- Also calculates early renewal and expiration dates based on config.

### 9. `is_valid_renewal_date($membership, $date = null)`
- Checks if a given date is within the valid renewal window for a membership, using the config’s rules.

---

**How to Use:**
- When implementing or testing membership renewal, always check the config’s type with `get_cycle_type()`.
- Use the appropriate methods above to calculate start, end, renewal, and expiration dates for memberships.
- Refer to this doc and the method summaries to understand the logic and data flow for each config type.

---

This doc can be referenced by developers and testers to understand the structure, types, and renewal logic of config values handled by the `Membership_Config` class.
