---
title: "Membership_Group_WP_REST_Controller"
audience: [developer]
php_class: Membership_Group_WP_REST_Controller
source_files: ["includes/Membership_Group_WP_REST_Controller.php"]
---

# Membership_Group_WP_REST_Controller

**File:** `includes/Membership_Group_WP_REST_Controller.php`
**Namespace:** `Wicket_Memberships`
**REST Namespace:** `wicket_member/v1`

REST API gateway for membership group operations. Extends `WP_REST_Controller` and registers all group-related endpoints on `rest_api_init`.

Tier management, individual-membership imports, MDP person merges, and org-browsing endpoints are intentionally absent — those remain in `Membership_WP_REST_Controller`. Group config date calculation is handled by `Membership_Group_Config_WP_REST_Controller`.

All business logic is delegated to `Group_Admin_Controller`.

---

## Registered Routes

| Method | Route | Handler | Backed |
|---|---|---|---|
| `GET` | `/group_membership_entity` | `get_group_entity` | Yes |
| `POST` | `/group_membership_entity/{group_post_id}/update` | `update_group_entity` | Yes |
| `GET` | `/group/admin/status_options` | `get_group_admin_status_options` | Yes |
| `POST` | `/group/admin/manage_status` | `group_admin_manage_status` | Yes |
| `GET` | `/group/admin/get_edit_page_info` | `get_group_edit_page_info` | Yes |
| `POST` | `/group/{group_post_id}/change_owner` | `update_group_change_ownership` | Yes |
| `POST` | `/group/{group_post_id}/create_renewal_order` | `create_group_renewal_order` | Yes |

Routes with no backing business logic yet are documented as TODO stubs in `register_routes()` source comments and tracked in `TODO.md`.

---

## Methods

### `__construct()`

Sets `$this->namespace = 'wicket_member/v1'` and hooks `register_routes` to `rest_api_init`. Instantiated from `wicket.php` during plugin bootstrap.

### `register_routes()`

Registers all group REST routes. TODO stub routes are listed as comments — they register no endpoints until business logic is implemented.

### `get_group_entity( \WP_REST_Request $request )`

**Route:** `GET /group_membership_entity?group_post_id={id}`

Delegates to `Group_Admin_Controller::get_group_entity_records()`. Returns group post meta, formatted dates, and child membership post IDs.

### `update_group_entity( \WP_REST_Request $request )`

**Route:** `POST /group_membership_entity/{group_post_id}/update`

Delegates to `Group_Admin_Controller::update_group_entity_record()`. Validates date ordering and cascades changes to child members.

### `get_group_admin_status_options( \WP_REST_Request $request )`

**Route:** `GET /group/admin/status_options`

Optional param `group_post_id`. Delegates to `Group_Admin_Controller::get_admin_status_options()`. Returns all statuses or valid transitions from the current group status.

### `group_admin_manage_status( \WP_REST_Request $request )`

**Route:** `POST /group/admin/manage_status`

Params: `group_post_id`, `status`. Delegates to `Group_Admin_Controller::admin_manage_status()`. Handles status transitions, subscription updates, and cascades to child memberships.

### `get_group_edit_page_info( \WP_REST_Request $request )`

**Route:** `GET /group/admin/get_edit_page_info?group_post_id={id}`

Delegates to `Group_Admin_Controller::get_group_edit_page_info()`. Returns all data for the group edit form: group meta, org, owner, config, dates, and allowed status transitions.

### `update_group_change_ownership( \WP_REST_Request $request )`

**Route:** `POST /group/{group_post_id}/change_owner`

Body: `new_owner_uuid`. Delegates to `Group_Admin_Controller::update_group_change_ownership()`. Updates group post meta and WC subscription customer.

### `create_group_renewal_order( \WP_REST_Request $request )`

**Route:** `POST /group/{group_post_id}/create_renewal_order`

Body: `product_id`, optional `variation_id`. Delegates to `Group_Admin_Controller::create_group_renewal_order()`. Creates a WC order and subscription for the group renewal.

### `permissions_check_read( $request ): bool|\WP_REST_Response`

Allows all requests when `ALLOW_LOCAL_IMPORTS` is set. Otherwise requires `Wicket_Memberships::WICKET_MEMBERSHIPS_CAPABILITY`. Returns `401` response on failure.

### `permissions_check_write( $request ): bool|\WP_REST_Response`

Same logic as `permissions_check_read`. Applied to all `CREATABLE` routes.

### `authorization_status_code(): int`

Returns `401` if not logged in, `403` if logged in but unauthorized.

---

## TODO — Unimplemented Routes

These routes have no backing business logic yet. They are tracked in `TODO.md`.

| Method | Route | Feature |
|---|---|---|
| `GET` | `/group_memberships` | List/search/filter group memberships |
| `GET` | `/group_membership_filters` | Filter options for group membership list UI |
| `GET` | `/get_group_membership_callouts` | Group-level renewal/grace period callouts |
| `POST` | `/group` | Create a new group membership |
| `POST` | `/group/{id}/add_member` | Add individual to group |
| `POST` | `/group/{id}/remove_member` | Remove individual from group (cancel or continue) |
| `POST` | `/group/{id}/move_member` | Move individual to another group |
| `POST` | `/group/{id}/cancel` | Cancel group with options |
| `GET` | `/group/{id}/members` | List individual memberships in a group |
| `POST` | `/group/{id}/import_members` | Bulk CSV import of members into a group |

---

## Notes

- Registered in `wicket.php` alongside `Membership_WP_REST_Controller` and `Membership_Group_Config_WP_REST_Controller`.
- Permission model is identical to the existing controllers: capability-gated, bypassed by `ALLOW_LOCAL_IMPORTS`.
- Does not register the merge webhook or any individual-membership import routes.
