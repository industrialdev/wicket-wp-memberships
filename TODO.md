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
| `includes/Group_Admin_Controller.php` | `admin_manage_status()` | Cancel the linked group WooCommerce subscription when a group is cancelled or expired, once group subscription management is implemented. | — |
| `includes/Group_Admin_Controller.php` | `admin_manage_status()` | Move WC subscription activation and date-update logic into `Membership_Group::activate_subscription( array $dates )`. | — |
| `includes/Group_Admin_Controller.php` | `admin_manage_status()` | Move status cascade to child members into `Membership_Group::cascade_status_to_members( string $status )`. | — |
| `includes/Group_Admin_Controller.php` | `admin_manage_status()` | Move the meta persistence loop into `Membership_Group::apply_status_meta( array $meta_data )`. | — |
| `includes/Group_Admin_Controller.php` | `update_group_entity_record()` | Apply real WooCommerce subscription date/status updates for group edits. Current implementation only persists local parity fields and cascades local member meta. | — |
| `includes/Group_Admin_Controller.php` | `normalize_group_edit_payload()` | Extract into `Membership_Group::normalize_edit_payload( array $data )` — already instantiates the group and reads from `get_config()`; belongs on the model. As part of this, replace all `to_wp_timezone_iso()` calls with `Utilities::get_utc_datetime( date( 'Y-m-d', $timestamp ) )->format( 'c' )` and delete `to_wp_timezone_iso()`. Dates must be stored in UTC; render in MDP timezone at display time using `Utilities::get_utc_datetime()`. | — |
| `includes/Group_Admin_Controller.php` | `cascade_group_edit_to_members()` | Extract into `Membership_Group::cascade_dates_to_members( array $normalized )` — already referenced by name in the TODO comment in `admin_manage_status()`. | — |
| `includes/Group_Admin_Controller.php` | `get_group_edit_page_info()` | Replace mocked `order` payload with real linked WooCommerce order enrichment when group order implementation exists. | — |
| `includes/Group_Admin_Controller.php` | `get_group_edit_page_info()` | Replace mocked `subscription` payload with real linked WooCommerce subscription enrichment when group subscription implementation exists. | — |
| `includes/Group_Admin_Controller.php` | `get_group_entity_records()` | Enrich the group entity response with WooCommerce subscription and order data. | — |
| `includes/Group_Admin_Controller.php` | `update_group_change_ownership()` | Simplify user resolution — the upfront `get_user_by( 'login', ... )` check is redundant as `wicket_create_wp_user_if_not_exist()` already does it internally. Replace the two-step block with a single `wicket_create_wp_user_if_not_exist()` call followed by `get_user_by( 'id', ... )`. | — |
| `includes/Group_Admin_Controller.php` | `update_group_change_ownership()` | Move WC order customer reassignment into `Membership_Group::reassign_order_customer( int $user_id )`. | — |
| `includes/Group_Admin_Controller.php` | `update_group_change_ownership()` | Move WC subscription customer reassignment into `Membership_Group::reassign_subscription_customer( int $user_id )`. | — |
| `includes/Group_Admin_Controller.php` | `build_group_memberships_row()` | Owner `name` and `email` are read from `user_name`/`user_email` post meta which is no longer stored. Resolve from the WP user object via the stored `user_id` instead (or via a `Membership_Group` helper). | — |
| `includes/Group_Admin_Controller.php` | `get_group_memberships_list()` | The `user_email` filter key queries `user_email` post meta which is no longer stored. Update to filter via WP user lookup or remove until a replacement strategy is in place. | — |

## Group List (Frontend)

| File | Method | Note | Asana |
|---|---|---|---|
| `frontend/src/members/group_list.js` | `GroupMemberList` | Enable the "Link to MDP" column once group membership MDP sync is implemented — link is currently rendered disabled (red, pointer-events: none). | — |

## Group Membership Detail Page (Frontend)

| File | Method | Note | Asana |
|---|---|---|---|
| `frontend/src/membership_groups/components/IntroBlockSection.js` | `IntroBlockSection` | Replace the org MDP link used in the "View in MDP" action with the correct group membership MDP link once group membership MDP sync is implemented. | — |
| `frontend/src/shared/components/MembershipBillingInfoSection.js` | `MembershipBillingInfoSection` | Replace mock subscription link and next payment date with real values once `Group_Admin_Controller::get_group_edit_page_info()` is enriched with full subscription data (link, next_payment_date). | — |
| `frontend/src/shared/components/MembershipOrderDetailsSection.js` | `MembershipOrderDetailsSection` | Replace mock orders array with real order data once `Group_Admin_Controller::get_group_edit_page_info()` is enriched with order information from the linked WooCommerce subscription. | — |
| `frontend/src/membership_groups/components/GroupMembershipRecordDetails.js` | `GroupMembershipRecordDetails` | Replace mock `subscriptionLink` and `nextPaymentDate` (read from `groupPageData.subscription`) with real values once `Group_Admin_Controller::get_group_edit_page_info()` is enriched with live WooCommerce subscription data. | — |
| `frontend/src/membership_groups/components/GroupMembershipRecordDetails.js` | `GroupMembershipRecordDetails` | Switch `subscriptionId`, `subscriptionLink`, `nextPaymentDate`, and `orders` props to read from the individual `record` object rather than `groupPageData` once per-record subscription and order enrichment is implemented in `Group_Admin_Controller`. | — |

## Membership_Group_WP_REST_Controller

| File | Method | Note | Asana |
|---|---|---|---|
| `includes/Membership_Group_WP_REST_Controller.php` | `register_routes()` | Register `POST /group/{id}/create_renewal_order` once the group subscription line item structure is finalised. Current group edit flow intentionally mocks commerce gaps instead of attempting partial implementation. | — |
| `includes/Membership_Group_WP_REST_Controller.php` | `register_routes()` | Register remaining group routes once backing business logic exists: `GET /get_group_membership_callouts`, `POST /group`, `POST /group/{id}/add_member`, `POST /group/{id}/remove_member`, `POST /group/{id}/move_member`, `POST /group/{id}/cancel`, `GET /group/{id}/members`, and `POST /group/{id}/import_members`. | — |
