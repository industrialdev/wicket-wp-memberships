
# Membership_Tier Class Index

**File:** includes/Membership_Tier.php

## Methods

- `__construct($post_id)`
- `get_all_tier_product_ids()` (static)
- `get_all_tier_product_variation_ids()` (static)
- `get_tier_by_product_id($product_id)` (static)
- `get_tier_id_by_wicket_uuid($uuid)` (static)
- `get_tier_uuids_by_config_id($config_id)` (static)
- `get_tier_ids_by_config_id($config_id)` (static)
- `get_mdp_tier_name()`
- `get_mdp_tier_uuid()`
- `get_tier_renewal_type()`
- `is_renewal_subscription()`
- `is_renewal_form_page()`
- `is_renewal_tier()`
- `get_next_tier_id()`
- `get_next_tier_form_page_id()`
- `get_product_ids()`
- `get_product_variation_ids()`
- `get_next_tier()`
- `get_config_id()`
- `get_config()`
- `is_organization_tier()`
- `is_individual_tier()`
- `get_seat_type()`
- `is_per_seat()`
- `is_per_range_of_seats()`
- `get_tier_type()`
- `get_products_data()`
- `is_approval_required()`
- `is_grant_owner_assignment()`
- `get_approval_email()`
- `get_approval_callout_header($lang = 'en')`
- `get_approval_callout_content($lang = 'en')`
- `get_approval_callout_button_label($lang = 'en')`
- `update_tier_data($new_tier_data)`
- `get_meta_tier_data_field_name()` (static)
- `get_membership_tier_post_id()`
- `get_seat_count()`
- `get_membership_posts()`
- `get_membership_tier_slug()`
- `get_tier_post_id()`

### Private Methods
- `get_tier_data()`
- `get_approval_callout_data()`

---

## Method Descriptions

**__construct($post_id)**
Initializes the Membership_Tier object for a given post ID, loads tier data from post meta, and validates the post type.

**get_all_tier_product_ids()** (static)
Returns an array of all WooCommerce product post IDs attached to any membership tier.

**get_all_tier_product_variation_ids()** (static)
Returns an array of all WooCommerce product variation post IDs attached to any membership tier.

**get_tier_by_product_id($product_id)** (static)
Finds and returns a Membership_Tier object for the tier associated with a given product or variation ID, or false if not found.

**get_tier_id_by_wicket_uuid($uuid)** (static)
Returns the post ID of the tier with the given Wicket UUID, or false if not found.

**get_tier_uuids_by_config_id($config_id)** (static)
Returns an array of tier UUIDs for all tiers associated with a given config ID.

**get_tier_ids_by_config_id($config_id)** (static)
Returns an array of tier post IDs for all tiers associated with a given config ID.

**get_mdp_tier_name()**
Returns the MDP (Membership Data Platform) tier name for this tier, or false if not set.

**get_mdp_tier_uuid()**
Returns the MDP tier UUID for this tier, or false if not set.

**get_tier_renewal_type()**
Returns the renewal type for this tier (e.g., 'subscription', 'form_page', etc.), or false if not set.

**is_renewal_subscription()**
Returns true if the renewal type is 'subscription', otherwise false.

**is_renewal_form_page()**
Returns true if the tier has a next tier form page ID, otherwise false.

**is_renewal_tier()**
Returns true if the tier has a next tier ID, otherwise false.

**get_next_tier_id()**
Returns the post ID of the next tier, or false if not set.

**get_next_tier_form_page_id()**
Returns the post ID of the next tier form page, or false if not set.

**get_product_ids()**
Returns an array of WooCommerce product post IDs attached to this tier.

**get_product_variation_ids()**
Returns an array of WooCommerce product variation post IDs attached to this tier.

**get_next_tier()**
Returns a Membership_Tier object for the next tier, or false if not set.

**get_config_id()**
Returns the config post ID associated with this tier, or false if not set.

**get_config()**
Returns a Membership_Config object for the config associated with this tier, or false if not set.

**is_organization_tier()**
Returns true if this tier is an organization tier, otherwise false.

**is_individual_tier()**
Returns true if this tier is an individual tier, otherwise false.

**get_seat_type()**
Returns the seat type for this tier (e.g., 'per_seat', 'per_range_of_seats'), or false if not set.

**is_per_seat()**
Returns true if the seat type is 'per_seat', otherwise false.

**is_per_range_of_seats()**
Returns true if the seat type is 'per_range_of_seats', otherwise false.

**get_tier_type()**
Returns the type of this tier ('organization', 'individual', etc.), or false if not set.

**get_products_data()**
Returns an array of product data arrays for this tier, or false if not set.

**is_approval_required()**
Returns true if approval is required for this tier, otherwise false.

**is_grant_owner_assignment()**
Returns true if grant owner assignment is required for this tier, otherwise false.

**get_approval_email()**
Returns the approval email recipient for this tier, or false if not set.

**get_approval_callout_header($lang = 'en')**
Returns the approval callout header for the specified language, or false if not set.

**get_approval_callout_content($lang = 'en')**
Returns the approval callout content for the specified language, or false if not set.

**get_approval_callout_button_label($lang = 'en')**
Returns the approval callout button label for the specified language, or false if not set.

**update_tier_data($new_tier_data)**
Updates the tier data in post meta for this tier.

**get_meta_tier_data_field_name()** (static)
Returns the meta field name used to store tier data ('tier_data').

**get_membership_tier_post_id()**
Returns the post ID for this membership tier.

**get_seat_count()**
Returns the seat count for this tier, based on product data.

**get_membership_posts()**
Returns an array of membership posts associated with this tier.

**get_membership_tier_slug()**
Returns the slug for this membership tier.

**get_tier_post_id()**
Returns the post ID for this tier.

### Private Methods

**get_tier_data()**
Retrieves the tier data array from post meta for this tier.

**get_approval_callout_data()**
Retrieves the approval callout data array from tier data, if set.
