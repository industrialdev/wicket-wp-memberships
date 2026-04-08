# Admin_Controller Class Index

**File:** includes/Admin_Controller.php

## Methods

- `__construct()`
- `admin_footer_scripts()`
- `init_menu()`
- `enqueue_scripts()`
- `get_admin_status_options($membership_post_id = null)` (static)
- `admin_manage_status($membership_post_id, $new_post_status)` (static)
- `get_edit_page_info($id)` (static)
- `update_org_name_on_memberships($org_uuid, $org_name)` (private)
- `get_membership_entity_records($id)` (static)
- `update_membership_entity_record($data)` (static)
- `update_membership_change_ownership($request)` (static)
- `create_renewal_order($request)` (static)

## Method Descriptions

**__construct()**
Initializes the Admin_Controller. Sets the custom post type slug and hooks up admin menu, script enqueue, and footer script actions.

**admin_footer_scripts()**
Outputs JavaScript variables to the admin footer for use by the Wicket Memberships admin UI, including merge tools and multi-tier renewal settings.

**init_menu()**
Adds the main Wicket Memberships menu page to the WordPress admin sidebar.

**enqueue_scripts()**
Enqueues the main CSS stylesheet for the Wicket Memberships admin interface.

**get_admin_status_options($membership_post_id = null)** (static)
Returns all possible membership statuses, or only allowed status transitions for a given membership post. Uses meta and helper functions to determine valid transitions.

**admin_manage_status($membership_post_id, $new_post_status)** (static)
Handles API requests to update a membership's status. Applies business rules for status transitions, updates meta, manages related subscription/order status, and logs errors. Returns a WP REST response.

**get_edit_page_info($id)** (static)
Returns identifying information for a user or organization for the edit page, including identifying number, email/location, admin panel link, and organization name. Updates org name on memberships if changed.

**update_org_name_on_memberships($org_uuid, $org_name)** (private)
Updates the organization name in all membership posts and user meta records for a given organization UUID. Used when an organization's name changes.

**get_membership_entity_records($id)** (static)
Retrieves all membership records for a user or organization, including meta, order, and subscription details. Formats data for admin display, including links and status names.

**update_membership_entity_record($data)** (static)
Updates a membership record with new data, including ownership changes, date fields, and renewal logic. Handles validation, updates both local and remote (MDP) records, and manages related subscription/order updates. Returns a WP REST response.

**update_membership_change_ownership($request)** (static)
Changes the owner of a membership, updating user meta, post meta, and associated order/subscription records. Handles user creation if needed and logs errors. Returns a WP REST response.

**create_renewal_order($request)** (static)
Creates a new WooCommerce order and subscription for renewing a membership. Associates the new order/subscription with the membership, ensures user existence, and returns the order URL in a WP REST response.
