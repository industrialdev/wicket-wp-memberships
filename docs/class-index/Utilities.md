
# Utilities Class Index

**File:** includes/Utilities.php

## Methods

- `__construct()`
- `display_autopay_status_row_admin($order)`
- `wicket_membership_clear_the_cart()`
- `wicket_logger($message, $data = [], $format = 'json', $logFile = "mship_error.log")` (static)
- `wc_log_mship_error($data, $level = 'error')` (static)
- `delete_all_person_memberships_from_mdp($person_uuid)` (static)
- `delete_wicket_membership_in_mdp($post_id)`
- `show_membership_delete_error()`
- `show_membership_product_delete_error()`
- `prevent_delete_linked_product($post_id, $post_status)`
- `hide_cart_item_remove_link($product_remove, $cart_item_key)`
- `disable_cart_item_quantity($product_quantity, $cart_item_key, $cart_item)`
- `handle_wp_delete_user($user_id, $reassign = false, $user = false)`
- `is_wicket_show_mship_order_org_search()`
- `set_subscription_postmeta_suborg_uuid()`
- `wicket_sub_org_select_metabox($post_type, $post)`
- `wicket_sub_org_select_callback($subscription)`
- `enqueue_suborg_scripts()`
- `handle_suborg_search()`
- `handle_wicket_tier_uuid_update()`
- `wicket_display_membership_id_input_on_order($subscription)`
- `wicket_assign_subscription_to_membership($membership_subscription_id, $membership_id = null)`
- `autorenew_checkbox_toggle_switch()` (static)
- `wc_autorenew_toggle_filters()` (static)
- `wc_autorenew_toggle_shortcode($atts)`
- `wicket_wc_enqueue_scripts_autorenew_toggle()` (static)
- `sync_autorenew_field()`
- `handle_user_auto_renew_toggle()` (static)
- `enqueue_mship_ajax_script()` (static)

## Method Descriptions

**__construct()**
Initializes hooks, filters, and actions for membership and subscription management, including metaboxes, AJAX, and admin notices.

**display_autopay_status_row_admin($order)**
Displays the autopay status (on/off) for a WooCommerce subscription order in the admin panel.

**wicket_membership_clear_the_cart()**
Empties the WooCommerce cart if the `empty-cart` query param is set.

**wicket_logger($message, $data = [], $format = 'json', $logFile = "mship_error.log")** (static)
Logs messages and data to a file for debugging in development environments.

**wc_log_mship_error($data, $level = 'error')** (static)
Logs errors to the WooCommerce logger for plugin-related issues.

**delete_all_person_memberships_from_mdp($person_uuid)** (static)
Deletes all memberships for a person in the MDP using external API functions.

**delete_wicket_membership_in_mdp($post_id)**
Deletes a membership in the MDP when a post is trashed, handling both individual and organization types.

**show_membership_delete_error()**
Displays an admin error notice if a membership could not be deleted in the MDP.

**show_membership_product_delete_error()**
Displays an admin error notice if a product assigned to a membership is deleted.

**prevent_delete_linked_product($post_id, $post_status)**
Prevents deletion of products linked to memberships or late fee products, redirecting with an error if attempted.

**hide_cart_item_remove_link($product_remove, $cart_item_key)**
Disables the remove link for cart items in membership categories, replacing it with an empty-cart link.

**disable_cart_item_quantity($product_quantity, $cart_item_key, $cart_item)**
Disables the quantity input for cart items in membership categories, making it read-only.

**handle_wp_delete_user($user_id, $reassign = false, $user = false)**
Handles cleanup of user and order meta when a user is deleted, and deletes related membership posts.

**is_wicket_show_mship_order_org_search()**
Checks if the organization search option is enabled for subscription orders.

**set_subscription_postmeta_suborg_uuid()**
Sets the organization UUID meta on a subscription order item when selected.

**wicket_sub_org_select_metabox($post_type, $post)**
Adds a metabox to the subscription admin page for selecting an organization if required.

**wicket_sub_org_select_callback($subscription)**
Renders the organization search UI in the metabox for a subscription.

**enqueue_suborg_scripts()**
Enqueues JavaScript for the organization search metabox and AJAX functionality.

**handle_suborg_search()**
Handles AJAX requests for organization search in the admin UI.

**handle_wicket_tier_uuid_update()**
Handles AJAX requests to update the tier UUID for migrating tier data between environments.

**wicket_display_membership_id_input_on_order($subscription)**
Adds a UI input to assign a membership ID to a subscription in the admin order page.

**wicket_assign_subscription_to_membership($membership_subscription_id, $membership_id = null)**
Assigns a WooCommerce subscription to a membership, syncing meta and user data for renewal flows.

**autorenew_checkbox_toggle_switch()** (static)
Adds hooks and AJAX for the front-end autorenew toggle if enabled in the environment.

**wc_autorenew_toggle_filters()** (static)
Registers the autorenew toggle shortcode and Gravity Forms filter for use in forms.

**wc_autorenew_toggle_shortcode($atts)**
Renders the autorenew toggle UI for use in shortcodes and forms.

**wicket_wc_enqueue_scripts_autorenew_toggle()** (static)
Enqueues styles and JavaScript for the autorenew toggle UI in the front-end footer.

**sync_autorenew_field()**
JavaScript helper for syncing the autorenew toggle state with Gravity Forms fields (see inline script).

**handle_user_auto_renew_toggle()** (static)
Handles AJAX requests to update a user's autorenew status and updates subscription/order meta.

**enqueue_mship_ajax_script()** (static)
Enqueues a custom AJAX script for membership actions if present in the theme, or registers a dummy script for localization.
