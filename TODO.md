# TODO

Tracked outstanding work items for the wicket-wp-memberships plugin.
Add an entry here whenever a `// TODO` comment is left in code.
Remove the entry when the work is completed.

---

## Membership_Group

| File | Method | Note | Asana |
|---|---|---|---|
| `includes/Membership_Group.php` | `create()` | Create a WooCommerce subscription for this group. Required by CURRENT_SCOPE.md — without this, groups created via the new Create Group page will be missing their subscription. | — |
| `includes/Membership_Group.php` | `add_individual_membership()` | Review implementation | [task](https://app.asana.com/1/1138832104141584/project/1213403241762018/task/1213781837058525) |
| `includes/Membership_Group.php` | `apply_edit_fields()` | Review and consider replacing with typed getters/setters per field — current `array<string,mixed>` signature allows any meta key to be written without validation | — |
| `includes/Membership_Group.php` | `cascade_dates_to_members()` | Implement date cascading from group to all child individual memberships. Should propagate starts_at, ends_at, expires_at, early_renew_at to active members; skip cancelled members. Add QA tests in `qa/tests/WordPress/Memberships/membership-group.pest.php` once implemented. | — |

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
| `includes/Group_Admin_Controller.php` | `update_group_entity_record()` | Apply real WooCommerce subscription date/status updates for group edits. Current implementation only persists local parity fields and cascades local member meta. | — |
| `includes/Group_Admin_Controller.php` | `get_group_edit_page_info()` | Replace mocked `order` payload with real linked WooCommerce order enrichment when group order implementation exists. | — |
| `includes/Group_Admin_Controller.php` | `get_group_edit_page_info()` | Replace mocked `subscription` payload with real linked WooCommerce subscription enrichment when group subscription implementation exists. | — |
| `includes/Group_Admin_Controller.php` | `get_group_entity_records()` | Enrich the group entity response with WooCommerce subscription and order data. | — |

## Individual Member Edit — Membership Group Details Panel (Frontend)

| File | Method | Note | Asana |
|---|---|---|---|
| `frontend/src/members/MembershipGroupDetails.js` | `MembershipGroupDetails` | Wire "View in MDP" link to the real membership group MDP URL once group MDP sync is implemented — currently disabled (red, pointer-events: none). | — |
| `frontend/src/members/MembershipGroupDetails.js` | `MembershipGroupDetails` | Implement "Move to Another Group" action — button is currently disabled (red, no-op). | — |
| `frontend/src/members/MembershipGroupDetails.js` | `MembershipGroupDetails` | Implement "Remove from Group" action — button is currently disabled (red, no-op). | — |

## Group List (Frontend)

| File | Method | Note | Asana |
|---|---|---|---|
| `frontend/src/members/group_list.js` | `GroupMemberList` | Enable the "Link to MDP" column once membership group MDP sync is implemented — link is currently rendered disabled (red, pointer-events: none). | — |

## Membership Group Detail Page (Frontend)

| File | Method | Note | Asana |
|---|---|---|---|
| `frontend/src/membership_groups/components/IntroBlockSection.js` | `IntroBlockSection` | Replace the org MDP link used in the "View in MDP" action with the correct membership group MDP link once membership group MDP sync is implemented. | — |
| `frontend/src/shared/components/MembershipBillingInfoSection.js` | `MembershipBillingInfoSection` | Replace mock subscription link and next payment date with real values once `Group_Admin_Controller::get_group_edit_page_info()` is enriched with full subscription data (link, next_payment_date). | — |
| `frontend/src/shared/components/MembershipOrderDetailsSection.js` | `MembershipOrderDetailsSection` | Replace mock orders array with real order data once `Group_Admin_Controller::get_group_edit_page_info()` is enriched with order information from the linked WooCommerce subscription. | — |
| `frontend/src/membership_groups/components/MembershipGroupRecordDetails.js` | `MembershipGroupRecordDetails` | Replace mock `subscriptionLink` and `nextPaymentDate` (read from `groupPageData.subscription`) with real values once `Group_Admin_Controller::get_group_edit_page_info()` is enriched with live WooCommerce subscription data. | — |
| `frontend/src/membership_groups/components/MembershipGroupRecordDetails.js` | `MembershipGroupRecordDetails` | Switch `subscriptionId`, `subscriptionLink`, `nextPaymentDate`, and `orders` props to read from the individual `record` object rather than `groupPageData` once per-record subscription and order enrichment is implemented in `Group_Admin_Controller`. | — |

## Membership_Group_WP_REST_Controller

| File | Method | Note | Asana |
|---|---|---|---|
| `includes/Membership_Group_WP_REST_Controller.php` | `register_routes()` | Register `POST /group/{id}/create_renewal_order` once the group subscription line item structure is finalised. Current group edit flow intentionally mocks commerce gaps instead of attempting partial implementation. | — |
| `includes/Membership_Group_WP_REST_Controller.php` | `register_routes()` | Register remaining group routes once backing business logic exists: `GET /get_membership_group_callouts`, `POST /group/{id}/add_member`, `POST /group/{id}/remove_member`, `POST /group/{id}/move_member`, `POST /group/{id}/cancel`, `GET /group/{id}/members`, and `POST /group/{id}/import_members`. | — |
