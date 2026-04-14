# TODO

Tracked outstanding work items for the wicket-wp-memberships plugin.
Add an entry here whenever a `// TODO` comment is left in code.
Remove the entry when the work is completed.

---

## Membership_Group

| File | Method | Note | Asana |
|---|---|---|---|
| `includes/Membership_Group.php` | `create()` | Review bare-bones implementation before use in production | [task](https://app.asana.com/1/1138832104141584/project/1213403241762018/task/1213775558142167) |
| `includes/Membership_Group.php` | `add_individual_membership()` | Review implementation | [task](https://app.asana.com/1/1138832104141584/project/1213403241762018/task/1213781837058525) |

## Frontend Tests

| File | Method | Note | Asana |
|---|---|---|---|
| `frontend/src/` | — | Build frontend tests for shared components and membership group config UI (GroupConfigForm, SeasonConfigModal, GracePeriodSection, RenewalWindowSection, etc.) | — |

## Membership_Group_Config — Product ID concerns

| File | Method | Note | Asana |
|---|---|---|---|
| `includes/Membership_Group_Config.php` | `get_late_fee_window_product_id()` | **Temporary implementation.** Reads `product_id` from `late_fee_window_data` meta (same as `Membership_Config`). The late-fee product concept for group configs has not been defined — may need to be derived from the group's tier products instead of stored directly. Review and replace before production use. | — |

## Group_Admin_Controller

| File | Method | Note | Asana |
|---|---|---|---|
| `includes/Group_Admin_Controller.php` | `update_group_manage_status()` | Cancel the linked group WooCommerce subscription when a group is cancelled or expired, once group subscription management is implemented. | — |
| `includes/Group_Admin_Controller.php` | `update_group_manage_status()` | Cascade date changes to child individual memberships once `cascade_dates_to_members()` is implemented. | — |
| `includes/Group_Admin_Controller.php` | `get_group_entity_records()` | Enrich the group entity response with WooCommerce subscription and order data. | — |
| `includes/Group_Admin_Controller.php` | `update_group_entity_record()` | Wire subscription date updates when the group renewal type changes. | — |

## Membership_Group_WP_REST_Controller

| File | Method | Note | Asana |
|---|---|---|---|
| `includes/Membership_Group_WP_REST_Controller.php` | `register_routes()` | Register `POST /group/{id}/create_renewal_order` once the group subscription line item structure is finalised. | — |
| `includes/Membership_Group_WP_REST_Controller.php` | `register_routes()` | Register remaining group routes once backing business logic exists: `GET /group_memberships`, `GET /group_membership_filters`, `GET /get_group_membership_callouts`, `POST /group`, `POST /group/{id}/add_member`, `POST /group/{id}/remove_member`, `POST /group/{id}/move_member`, `POST /group/{id}/cancel`, `GET /group/{id}/members`, and `POST /group/{id}/import_members`. | — |
