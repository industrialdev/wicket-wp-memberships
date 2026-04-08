# Membership_Controller Class Index

**File:** includes/Membership_Controller.php

## Methods

- `__construct()`
- `product_add_on()`
- `add_cart_item_data($cart_item_meta, $product_id)`
- `get_item_data($other_data, $cart_item)`
- `add_order_item_meta($item_id, $values)`
- `update_membership_status($id, $status)`
- `validate_renewal_order_items($item, $cart_item_key, $values, $order)` (static)
- `get_memberships_data_from_subscription_products($order)` (private)
- `get_membership_array_from_user_meta_by_post_id($membership_post_id, $user_id = null)` (static)
- `get_membership_array_from_post($membership_post_id)` (static)
- `get_membership_array_from_post_id($membership_post_id)` (static)
- `get_membership_array_from_order_and_product_id($mship_order_id, $mship_product_id)` (static)
- `amend_membership_json($membership_post_id, $meta_array)`
- `catch_order_completed($order_id)` (static)
- `schedule_wicket_wipe_next_payment_date($subscription_id)`
- `catch_wicket_wipe_next_payment_date($sub_id)` (static)
- `scheduler_dates_for_expiry($membership)`
- `catch_membership_early_renew_at($membership_parent_order_id, $membership_product_id)` (static)
- `catch_membership_ends_at($membership_parent_order_id, $membership_product_id)` (static)
- `catch_membership_expires_at($membership_parent_order_id, $membership_product_id)` (static)
- `membership_early_renew_at_date_reached($membership)`
- `membership_ends_at_date_reached($membership)`
- `membership_expires_at_date_reached($membership)`
- `create_membership_record($membership, $processing_renewal = false, $status_cycled = false)` (static)
- `update_subscription_status($membership_subscription_id, $status, $note = '')`
- `guidv4($data = null)` (private)
- `update_membership_subscription($membership, $fields = [ 'start_date', 'end_date', 'next_payment_date' ], $subcription_created = false)`
- `update_mdp_record($membership, $meta_data)`
- `create_mdp_record($membership)`
- `check_mdp_membership_record_exists($membership)` (private)
- `update_local_membership_record($membership_post_id, $meta_data)`
- `get_person_uuid($user_id)`
- `get_user_id_from_membership_post($membership_post_id)` (static)
- `create_local_membership_record($membership, $membership_wicket_uuid, $skip_approval = false)`
- `wicket_update_subscription_meta_membership_post_id($membership_post_id, $membership, $new_order_processed = false)`
- `catch_expire_current_membership($previous_membership_post_id, $new_membership_post_id = 0)` (static)
- `check_local_membership_record_exists($membership)` (private)
- `surface_error()` (private)
- `error_notice()`
- `get_my_memberships($flag = 'all', $tier_uuid = '', $user_id = null)`
- `get_membership_callouts($user_id = null, $status = "")`
- `add_late_fee_product_to_subscription_renewal_order($subscription_id)`
- `get_members_list_group_by_filter($groupby)`
- `get_members_list($type, $page, $posts_per_page, $status, $search = '', $filter = [], $order_col = null, $order_dir = null)`
- `get_tier_info($tier_uuids, $properties = [])` (static)
- `get_org_info($org_uuids, $properties = [])` (static)
- `get_members_filters($type)`
- `daily_membership_expiry_hook()` (static)
- `daily_membership_grace_period_hook()` (static)

---

## Method Descriptions

**__construct()**
Initializes the controller, sets up CPT slugs, and registers WooCommerce hooks for cart and order item data.

**product_add_on()**
Outputs additional fields for org UUID and renewal membership post ID on the WooCommerce product page.

**add_cart_item_data($cart_item_meta, $product_id)**
Adds org UUID and renewal membership post ID to cart item meta if present in the request.

**get_item_data($other_data, $cart_item)**
Returns org UUID and renewal membership post ID for display in the cart and checkout.

**add_order_item_meta($item_id, $values)**
Adds org UUID and renewal membership post ID to order item meta if not already present.

**update_membership_status($id, $status)**
Updates the membership status for a given post ID or Wicket UUID.

**validate_renewal_order_items($item, $cart_item_key, $values, $order)** (static)
Checks if a renewal order is within the valid renewal period and throws an exception if not.

**get_memberships_data_from_subscription_products($order)** (private)
Retrieves membership data from the products in a WooCommerce order's subscriptions, handling renewal logic and meta updates.

**get_membership_array_from_user_meta_by_post_id($membership_post_id, $user_id = null)** (static)
Gets the membership JSON data from user meta for a given membership post ID.

**get_membership_array_from_post($membership_post_id)** (static)
Gets the membership meta data from a membership post.

**get_membership_array_from_post_id($membership_post_id)** (static)
Gets the membership JSON data from order and product ID, using post meta.

**get_membership_array_from_order_and_product_id($mship_order_id, $mship_product_id)** (static)
Gets the membership JSON data from order and product ID, using post meta.

**amend_membership_json($membership_post_id, $meta_array)**
Updates the membership JSON data stored on user meta, order, and subscription with new values.

**catch_order_completed($order_id)** (static)
Processes a completed WooCommerce order, creating membership records as needed.

**schedule_wicket_wipe_next_payment_date($subscription_id)**
Schedules a background action to clear the next payment date for a subscription.

**catch_wicket_wipe_next_payment_date($sub_id)** (static)
Clears the next payment date and scheduled actions for a subscription.

**scheduler_dates_for_expiry($membership)**
Schedules actions for early renewal, end, and expiry dates for a membership, and handles expiring previous memberships.

**catch_membership_early_renew_at($membership_parent_order_id, $membership_product_id)** (static)
Handles the early renewal date event for a membership.

**catch_membership_ends_at($membership_parent_order_id, $membership_product_id)** (static)
Handles the end date event for a membership.

**catch_membership_expires_at($membership_parent_order_id, $membership_product_id)** (static)
Handles the expiry date event for a membership.

**membership_early_renew_at_date_reached($membership)**
Triggers the renewal period open action for a membership.

**membership_ends_at_date_reached($membership)**
Triggers the end date reached action for a membership.

**membership_expires_at_date_reached($membership)**
Triggers the grace period expired action for a membership.

**create_membership_record($membership, $processing_renewal = false, $status_cycled = false)** (static)
Creates a membership record in both the local database and the external MDP system, handling approval, status, and scheduled tasks.

**update_subscription_status($membership_subscription_id, $status, $note = '')**
Updates the status of a WooCommerce subscription, with error handling.

**guidv4($data = null)** (private)
Generates a version 4 UUID.

**update_membership_subscription($membership, $fields, $subcription_created)**
Updates the subscription dates for a membership, handling WooCommerce Subscriptions quirks and adding order notes.

**update_mdp_record($membership, $meta_data)**
Updates the membership record in the external MDP system, handling both individual and organization memberships.

**create_mdp_record($membership)**
Creates a new membership record in the external MDP system, handling both individual and organization memberships, and supporting version-specific features.

**check_mdp_membership_record_exists($membership)** (private)
Checks if a membership record already exists in the MDP system for the given person, tier, and dates.

**update_local_membership_record($membership_post_id, $meta_data)**
Updates the local WordPress membership post and user meta with new data.

**get_person_uuid($user_id)**
Returns the Wicket person UUID (user login) for a given WordPress user ID.

**get_user_id_from_membership_post($membership_post_id)** (static)
Returns the user ID associated with a membership post.

**create_local_membership_record($membership, $membership_wicket_uuid, $skip_approval = false)**
Creates or updates a local WordPress membership post and associated user meta, handling status, org data, and meta fields.

**wicket_update_subscription_meta_membership_post_id($membership_post_id, $membership, $new_order_processed = false)**
Ensures the subscription item meta for renewal post ID is kept in sync with the current membership post ID.

**catch_expire_current_membership($previous_membership_post_id, $new_membership_post_id = 0)** (static)
Expires the previous membership and activates the new membership if provided.

**check_local_membership_record_exists($membership)** (private)
Checks if a local membership post already exists for the given membership data.

**surface_error()** (private)
Displays an error notice in the WooCommerce admin if an error occurs.

**error_notice()**
Outputs an error notice in the WordPress admin UI.

**get_my_memberships($flag = 'all', $tier_uuid = '', $user_id = null)**
Retrieves all memberships for a user, optionally filtered by status and tier UUID.

**get_membership_callouts($user_id = null, $status = "")**
Returns callout data for memberships in early renewal, grace period, or pending approval, for use in the account center.

**add_late_fee_product_to_subscription_renewal_order($subscription_id)**
Adds a late fee product to a subscription renewal order if not already present.

**get_members_list_group_by_filter($groupby)**
Returns the group by clause for member list queries (used for custom filters).

**get_members_list($type, $page, $posts_per_page, $status, $search = '', $filter = [], $order_col = null, $order_dir = null)**
Retrieves a paginated list of memberships, with filtering and user/tier data enrichment.

**get_tier_info($tier_uuids, $properties = [])** (static)
Returns tier information and counts for the given UUIDs and properties.

**get_org_info($org_uuids, $properties = [])** (static)
Returns organization information and counts for the given UUIDs and properties.

**get_members_filters($type)**
Returns available filters for memberships by type (individual or organization).

**daily_membership_expiry_hook()** (static)
Daily cron hook to expire memberships whose expiration date was yesterday.

**daily_membership_grace_period_hook()** (static)
Daily cron hook to move memberships to grace period whose end date was yesterday.
