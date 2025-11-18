
# Helper Class Index

**File:** includes/Helper.php

## Methods

- `__construct()`
- `adjust_next_payment_date_after_renewal($subscription, $renewal_order)`
- `add_slug_on_mship_tier_create($post_ID, $post, $update)`
- `wps_select_checkout_field_display_admin_order_meta($post)`
- `action_buttons_add_meta_boxes()`
- `display_action_buttons()`
- `extra_info_add_meta_boxes()`
- `extra_info_data_contents()`
- `get_wp_languages_iso()` (static)
- `get_membership_config_cpt_slug()` (static)
- `get_membership_cpt_slug()` (static)
- `get_membership_tier_cpt_slug()` (static)
- `is_valid_membership_post($membership_post_id)` (static)
- `get_all_status_names()` (static)
- `get_allowed_transition_status($status)` (static)
- `get_membership_post_data_from_membership_json($membership_json, $json_encoded = true, $dir = 'post')` (static)
- `get_membership_json_from_membership_post_data($membership_array, $json_encode = true)` (static)
- `get_org_data($org_uuid, $bypass_lookup = false, $force_lookup = false)` (static)
- `store_an_organizations_data_in_options_table($org_uuid, $force_update = false)` (static)
- `get_post_meta($post_id)` (static)
- `wicket_memberships_alter_query($query)`
- `wicket_memberships_admin_search_box()`
- `has_next_payment_date($membership)` (static)

## Method Descriptions

**__construct()**
Sets up hooks and actions for admin UI, debug features, and subscription renewal date adjustment.

**adjust_next_payment_date_after_renewal($subscription, $renewal_order)**
Adjusts the next payment date for monthly subscriptions after a manual renewal to match the membership cycle.

**add_slug_on_mship_tier_create($post_ID, $post, $update)**
Adds a slug to a membership tier post when it is created, based on MDP data.

**wps_select_checkout_field_display_admin_order_meta($post)**
Displays membership meta data in the admin order and subscription details pages.

**action_buttons_add_meta_boxes()**
Adds a meta box with action buttons to the membership post edit screen (for debug/admin use).

**display_action_buttons()**
Renders the action buttons meta box UI for membership posts.

**extra_info_add_meta_boxes()**
Adds a meta box to display extra info on the membership post edit screen.

**extra_info_data_contents()**
Renders the extra info meta box, showing post, customer, and order data for the membership.

**get_wp_languages_iso()** (static)
Returns the ISO language codes for the current WPML languages or the default WP language.

**get_membership_config_cpt_slug()** (static)
Returns the custom post type slug for membership configs.

**get_membership_cpt_slug()** (static)
Returns the custom post type slug for memberships.

**get_membership_tier_cpt_slug()** (static)
Returns the custom post type slug for membership tiers.

**is_valid_membership_post($membership_post_id)** (static)
Checks if a given post ID is a published membership post.

**get_all_status_names()** (static)
Returns an array of all possible membership status names and slugs.

**get_allowed_transition_status($status)** (static)
Returns the allowed status transitions for a given membership status, considering lockout settings.

**get_membership_post_data_from_membership_json($membership_json, $json_encoded = true, $dir = 'post')** (static)
Converts membership JSON data to post meta data, mapping keys as needed.

**get_membership_json_from_membership_post_data($membership_array, $json_encode = true)** (static)
Converts post meta data to membership JSON, mapping keys as needed.

**get_org_data($org_uuid, $bypass_lookup = false, $force_lookup = false)** (static)
Retrieves organization data from options or external API, with optional force lookup.

**store_an_organizations_data_in_options_table($org_uuid, $force_update = false)** (static)
Stores organization data in the WP options table, fetching from the API if needed.

**get_post_meta($post_id)** (static)
Returns filtered post meta for a given post, formatting date fields and skipping private keys.

**wicket_memberships_alter_query($query)**
Alters the admin membership posts query to support custom search fields.

**wicket_memberships_admin_search_box()**
Renders a custom search box in the admin membership posts list.

**has_next_payment_date($membership)** (static)
Determines if a membership/subscription should have a next payment date set, based on renewal logic and config.
