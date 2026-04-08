
# Membership_Config Class Index

**File:** includes/Membership_Config.php

## Methods

- `__construct($post_id)`
- `get_title()`
- `get_renewal_window_days()`
- `get_renewal_window_callout_header($lang = 'en')`
- `get_renewal_window_callout_content($lang = 'en')`
- `get_renewal_window_callout_button_label($lang = 'en')`
- `get_late_fee_window_product_id()`
- `get_late_fee_window_days()`
- `get_late_fee_window_callout_header($lang = 'en')`
- `get_late_fee_window_callout_content($lang = 'en')`
- `get_late_fee_window_callout_button_label($lang = 'en')`
- `get_cycle_data()`
- `get_cycle_type()`
- `get_calendar_seasons()`
- `get_current_calendar_season()`
- `get_period_data()`
- `is_valid_renewal_date($membership, $date = null)`
- `get_membership_dates($membership = [])`
- `is_multitier_renewal()`

### Private Methods
- `get_renewal_window_data()`
- `get_late_fee_window_data()`
- `get_anniversary_start_date($membership = [])`
- `get_seasonal_start_date($membership = [])`
- `get_seasonal_end_date($membership = [])`

---

## Method Descriptions

**__construct($post_id)**
Initializes the Membership_Config object for a given post ID, loads renewal window, late fee window, and cycle data from post meta.

**get_title()**
Returns the title of the membership config post.

**get_renewal_window_days()**
Returns the number of days in the renewal window, or false if not set.

**get_renewal_window_callout_header($lang = 'en')**
Returns the callout header for the renewal window in the specified language, or false if not set.

**get_renewal_window_callout_content($lang = 'en')**
Returns the callout content for the renewal window in the specified language, or false if not set.

**get_renewal_window_callout_button_label($lang = 'en')**
Returns the callout button label for the renewal window in the specified language, or false if not set.

**get_late_fee_window_product_id()**
Returns the product ID for the late fee window, or false if not set.

**get_late_fee_window_days()**
Returns the number of days in the late fee window, or false if not set.

**get_late_fee_window_callout_header($lang = 'en')**
Returns the callout header for the late fee window in the specified language, or false if not set.

**get_late_fee_window_callout_content($lang = 'en')**
Returns the callout content for the late fee window in the specified language, or false if not set.

**get_late_fee_window_callout_button_label($lang = 'en')**
Returns the callout button label for the late fee window in the specified language, or false if not set.

**get_cycle_data()**
Returns the cycle data array for this config, or false if not set.

**get_cycle_type()**
Returns the cycle type ('calendar' or 'anniversary'), or false if not set.

**get_calendar_seasons()**
Returns an array of formatted calendar seasons from the cycle data, or false if not set.

**get_current_calendar_season()**
Returns the current calendar season based on the current date, or false if not found.

**get_period_data()**
Returns the period count and type for the membership cycle, based on cycle type.

**is_valid_renewal_date($membership, $date = null)**
Checks if the given date is a valid renewal date for the membership, based on config settings.

**get_membership_dates($membership = [])**
Returns an array of membership start, end, early renewal, and expiration dates, based on config and membership data.

**is_multitier_renewal()**
Returns true if multi-tier renewal is enabled for this config, otherwise false.

### Private Methods

**get_renewal_window_data()**
Retrieves the renewal window data array from post meta for this config.

**get_late_fee_window_data()**
Retrieves the late fee window data array from post meta for this config.

**get_anniversary_start_date($membership = [])**
Calculates the start date for an anniversary-based membership cycle.

**get_seasonal_start_date($membership = [])**
Calculates the start date for a seasonal (calendar) membership cycle.

**get_seasonal_end_date($membership = [])**
Calculates the end date for a seasonal (calendar) membership cycle, considering active seasons.
