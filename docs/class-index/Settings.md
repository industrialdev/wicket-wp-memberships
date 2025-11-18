
# Settings Class Index

**File:** includes/Settings.php

## Methods

- `wicket_membership_add_settings_page()` (static)
- `wicket_membership_render_plugin_settings_page()` (static)
- `get_next_scheduled_membership_grace_period()`
- `get_next_scheduled_membership_expiry()`
- `wicket_membership_register_settings()` (static)
- `wicket_mship_multi_tier_renewal()` (static)
- `wicket_mship_disable_renewal()` (static)
- `wicket_mship_assign_subscription()` (static)
- `wicket_show_mship_order_org_search()` (static)
- `bypass_wicket()` (static)
- `wicket_mship_autorenew_toggle()` (static)
- `wicket_mship_subscription_renew()` (static)
- `wicket_memberships_debug_acc()` (static)
- `wicket_memberships_debug_renew()` (static)
- `wicket_memberships_debug_cart_ids()` (static)
- `allow_local_imports()` (static)
- `wicket_show_order_debug_data()` (static)
- `bypass_status_change_lockout()` (static)
- `wicket_membership_debug_mode()` (static)
- `wicket_membership_plugin_options_validate($input)` (static)
- `wicket_plugin_section_functional_text()` (static)
- `wicket_plugin_section_debug_text()` (static)
- `wicket_plugin_status_change_reporting()` (static)
- `check_migrate_tier_slugs()` (static)

## Method Descriptions

**wicket_membership_add_settings_page()** (static)
Adds the Wicket Memberships settings page to the WordPress admin options menu.

**wicket_membership_render_plugin_settings_page()** (static)
Renders the HTML form for the plugin settings page, including all settings sections and fields.

**get_next_scheduled_membership_grace_period()**
Returns the next scheduled run time for the membership grace period Action Scheduler hook.

**get_next_scheduled_membership_expiry()**
Returns the next scheduled run time for the membership expiry Action Scheduler hook.

**wicket_membership_register_settings()** (static)
Registers all plugin settings, sections, and fields for the Wicket Memberships plugin.

**wicket_mship_multi_tier_renewal()** (static)
Outputs a checkbox to enable multi-tier renewal logic and related UI in the plugin.

**wicket_mship_disable_renewal()** (static)
Outputs a checkbox to disable renewal callouts in the Account Centre.

**wicket_mship_assign_subscription()** (static)
Outputs a checkbox to allow assigning memberships by ID on the WooCommerce Subscription page.

**wicket_show_mship_order_org_search()** (static)
Outputs a select field to enable organization search/selection for memberships on the WC Subscription Order admin page.

**bypass_wicket()** (static)
Outputs a checkbox to disable creation of memberships in the Wicket MDP (bypass external system).

**wicket_mship_autorenew_toggle()** (static)
Outputs a checkbox to enable the user-facing Autorenew toggle for subscriptions, including a shortcode for front-end use.

**wicket_mship_subscription_renew()** (static)
Outputs a checkbox to enable the [BETA] subscription renewal flow for tiers.

**wicket_memberships_debug_acc()** (static)
Outputs a checkbox to show debug info and renewal callouts in the account center (for debugging only).

**wicket_memberships_debug_renew()** (static)
Outputs a checkbox to allow renewals outside the normal period and adjust days for renewal callout debugging.

**wicket_memberships_debug_cart_ids()** (static)
Outputs a checkbox to show product meta/debug info on cart/checkout pages.

**allow_local_imports()** (static)
Outputs a checkbox and help text to enable importing MDP export CSVs via SSH command line (for advanced/admin use only).

**wicket_show_order_debug_data()** (static)
Outputs a checkbox to show membership JSON meta on order/subscription pages for debugging.

**bypass_status_change_lockout()** (static)
Outputs a checkbox to disable status change sequence rules, allowing any status change.

**wicket_membership_debug_mode()** (static)
Outputs a checkbox to enable debug mode throughout the plugin admin, showing extra data and menu items.

**wicket_membership_plugin_options_validate($input)** (static)
Validates and sanitizes all plugin settings input before saving.

**wicket_plugin_section_functional_text()** (static)
Outputs a description for the functional settings section on the settings page.

**wicket_plugin_section_debug_text()** (static)
Outputs a description for the debug settings section on the settings page.

**wicket_plugin_status_change_reporting()** (static)
Outputs information and controls for scheduled membership status change actions (grace period, expiry).

**check_migrate_tier_slugs()** (static)
Checks all membership tiers for missing slugs and adds them if needed (data migration utility).
