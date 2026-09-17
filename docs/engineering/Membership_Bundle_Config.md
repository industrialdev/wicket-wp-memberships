---
title: "Membership_Bundle_Config"
audience: [developer]
php_class: Membership_Bundle_Config
source_files: ["includes/Membership_Bundle_Config.php"]
---

# Membership_Bundle_Config

**File:** `includes/Membership_Bundle_Config.php`

Represents a Membership Bundle Config CPT record (`wicket_mship_bcfg`). Combines the date/cycle/renewal-window logic of `Membership_Config` with the approval and renewal-type logic of `Membership_Tier`, scoped specifically for Membership Bundles. Renewal type is limited to `subscription` and `form_page`.

**CPT slug:** `wicket_mship_bcfg` — via `Helper::get_membership_bundle_config_cpt_slug()`

**Relationship to `Membership_Bundle`:** Each bundle holds a reference to its config via `membership_bundle_config_id` post meta. The config is the authoritative source for all date calculations — `get_membership_dates()` is called during `Membership_Bundle::create()` and `renew_bundle()` to derive `starts_at`, `ends_at`, `expires_at`, and `early_renew_at`. Retrieve via `Membership_Bundle::get_config()`.

**Admin UI:** The create/edit form for bundle config records is managed by `Membership_Bundle_Config_CPT_Hooks` — see that doc for the React mount point, enqueued scripts, and list-table columns.

**Architecture position:** Read-only model. No status transitions or WooCommerce side effects. Consumed by `Membership_Bundle` (date derivation), `Membership_Bundle_Admin_Controller` (edit page data), and `Membership_Bundle_Config_WP_REST_Controller` (date calculation endpoint).

## Meta Layout

| Meta key | Type | Source pattern |
|---|---|---|
| `renewal_window_data` | array | Membership_Config |
| `late_fee_window_data` | array | Membership_Config |
| `cycle_data` | array | Membership_Config |
| `bundle_config_data` | array | Membership_Tier (`tier_data`) |

`bundle_config_data` keys: `renewal_type`, `renewal_form_page_id`, `approval_required`, `grant_owner_assignment`, `approval_email_recipient`, `approval_callout_data`.

## Methods

- `__construct($post_id)`
- `get_post_id()`
- `get_title()`
- `get_meta_bundle_config_data_field_name()` (static)
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
- `get_renewal_type()`
- `is_renewal_subscription()`
- `is_renewal_form_page()`
- `get_renewal_form_page_id()`
- `is_approval_required()`
- `is_grant_owner_assignment()`
- `get_approval_email()`
- `get_approval_callout_header($lang = 'en')`
- `get_approval_callout_content($lang = 'en')`
- `get_approval_callout_button_label($lang = 'en')`
- `update_bundle_config_data(array $new_bundle_config_data)`

### Private Methods

- `get_renewal_window_data()`
- `get_late_fee_window_data()`
- `get_bundle_config_data()`
- `get_approval_callout_data()`
- `get_anniversary_start_date($membership = [])`
- `get_seasonal_start_date($membership = [])`
- `get_seasonal_end_date($membership = [])`

---

## Method Descriptions

**__construct($post_id)**
Validates the post exists and is the `wicket_mship_bcfg` CPT type. On failure, sets `$post_id = 0` and empties all data properties (logs via `Wicket()->log()`). On success, loads all four meta arrays.

**get_post_id()**
Returns the post ID for this group config.

**get_title()**
Returns the title of the group config post.

**get_meta_bundle_config_data_field_name()** (static)
Returns the meta key used to store bundle config data (`'bundle_config_data'`).

**get_renewal_window_days()**
Returns the number of days in the renewal window, or false if not set.

**get_renewal_window_callout_header($lang = 'en')**
Returns the renewal callout header for the given language, or false.

**get_renewal_window_callout_content($lang = 'en')**
Returns the renewal callout content for the given language, or false.

**get_renewal_window_callout_button_label($lang = 'en')**
Returns the renewal callout button label for the given language, or false.

**get_late_fee_window_product_id()**
Reads `product_id` directly from `late_fee_window_data` meta (same as `Membership_Config`). The late-fee UI section is implemented but visually hidden in the admin — the field exists in the data structure and is readable via this method, but is not currently surfaced for configuration.

Returns the late fee product ID, or false.

**get_late_fee_window_days()**
Returns the number of days in the late fee (grace period) window, or false.

**get_late_fee_window_callout_header($lang = 'en')**
Returns the late fee callout header for the given language, or false.

**get_late_fee_window_callout_content($lang = 'en')**
Returns the late fee callout content for the given language, or false.

**get_late_fee_window_callout_button_label($lang = 'en')**
Returns the late fee callout button label for the given language, or false.

**get_cycle_data()**
Reads and returns the `cycle_data` array from post meta, or false.

**get_cycle_type()**
Returns `'calendar'` or `'anniversary'`, or false if not set.

**get_calendar_seasons()**
Returns the formatted seasons array from `cycle_data['calendar_items']`, with dates converted to ISO 8601. Returns false if not set.

**get_current_calendar_season()**
Returns the active season matching today's date, or false if none matches.

**get_period_data()**
Returns `{ period_count, period_type }` based on cycle type. Defaults to `{ 1, 'year' }` for calendar configs.

**is_valid_renewal_date($membership, $date = null)**
Checks whether the given date falls within the valid renewal window for the membership.

**get_membership_dates($membership = [])**
Calculates and returns membership start, end, grace-period expiry, and early-renewal dates. Pass an empty array for new memberships that should start today.

Supported `$membership` keys:
- `membership_ends_at` (string) — ISO 8601 end date of the current period; used for renewals. The next period starts the day after this date.
- `start_date` (string) — ISO 8601 date override for a new membership start. When provided (with no `membership_ends_at`), all date calculations are anchored to this date instead of `'now'`.

**get_renewal_type()**
Returns `'subscription'` or `'form_page'` from `bundle_config_data`, or false.

**is_renewal_subscription()**
Returns true if `renewal_type` is `'subscription'`.

**is_renewal_form_page()**
Returns true if a `renewal_form_page_id` is set.

**get_renewal_form_page_id()**
Returns the renewal form page post ID from `bundle_config_data`, or false.

**is_approval_required()**
Returns `1` if `approval_required` is set in `bundle_config_data`, false otherwise.

**is_grant_owner_assignment()**
Returns `1` if `grant_owner_assignment` is set in `bundle_config_data`, false otherwise.

**get_approval_email()**
Returns the approval email recipient from `bundle_config_data`, or false.

**get_approval_callout_header($lang = 'en')**
Returns the approval callout header for the given language, or false.

**get_approval_callout_content($lang = 'en')**
Returns the approval callout content for the given language, or false.

**get_approval_callout_button_label($lang = 'en')**
Returns the approval callout button label for the given language, or false.

**update_bundle_config_data(array $new_bundle_config_data)**
Writes the replacement `bundle_config_data` array to post meta and refreshes the in-memory cache so subsequent reads on the same instance reflect the change.

### Private Methods

**get_renewal_window_data()**
Loads `renewal_window_data` from post meta.

**get_late_fee_window_data()**
Loads `late_fee_window_data` from post meta.

**get_bundle_config_data()**
Loads `bundle_config_data` from post meta (contains renewal type and approval settings).

**get_approval_callout_data()**
Returns `bundle_config_data['approval_callout_data']` if set, false otherwise.

**get_anniversary_start_date($membership = [])**
Calculates the start date for an anniversary cycle. Renewal = day after existing `membership_ends_at`; new with `start_date` key = that date; otherwise today. Returns ISO 8601.

**get_seasonal_start_date($membership = [])**
Calculates the start date for a calendar cycle. Same fallback order as `get_anniversary_start_date()`.

**get_seasonal_end_date($membership = [])**
Calculates the end date for a calendar cycle by matching the membership start against configured seasons. Start date is derived with the same fallback order as `get_seasonal_start_date()`. Falls back to start + 1 year. Returns ISO 8601.
