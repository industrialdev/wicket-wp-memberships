# TODO

Tracked outstanding work items for the wicket-wp-memberships plugin.
Add an entry here whenever a `// TODO` comment is left in code.
Remove the entry when the work is completed.

---

## QA Tests

| File | Test | Note | Asana |
|---|---|---|---|
| `qa/tests/WordPress/Memberships/admin-controller.pest.php` | `activates a pending membership and its subscription via admin status change` | `memberships_create_fixture` creates subscription as `active` instead of expected `on-hold`. Fix the fixture then re-enable the `todo()`. | — |
| `qa/tests/WordPress/Memberships/individual-member-filters.pest.php` | All (19 tests) | **Disabled** — file renamed to `.pest.php.disabled` to prevent suite timeout. `memberships_teardown()` runs 8× full-table `get_posts()` scans + `wp_delete_post()` per test (19 afterEach cycles). Fix: replace global teardown with targeted ID-based deletes in this file, or switch to shared `beforeAll` fixtures where test isolation allows. Re-enable by removing the `.disabled` suffix. | — |

---


## Membership_Group

| File | Method | Note | Asana |
|---|---|---|---|
| `includes/Membership_Group.php` | `create()` | Implement full group approval workflow: send approval email (link to org edit page), handle `pending→active` admin transition, show member-portal callout while pending. Mirror `Membership_Controller::create_membership_record()` lines 764–781 and `Admin_Controller::admin_manage_status()`. Also decide whether approval blocks adding individual memberships to the group until approved. | — |
| `includes/Membership_Group.php` | `apply_edit_fields()` | Review and consider replacing with typed getters/setters per field — current `array<string,mixed>` signature allows any meta key to be written without validation | — |
| `includes/Membership_Group.php` | `cascade_dates_to_members()` | Implement date cascading from group to all child individual memberships. Should propagate starts_at, ends_at, expires_at, early_renew_at to active members; skip cancelled members. Add QA tests in `qa/tests/WordPress/Memberships/membership-group.pest.php` once implemented. | — |
| `includes/Membership_Group.php` | `add_member()` | Link `membership_subscription_id` and `membership_parent_order_id` to the group's WooCommerce subscription once group subscription management exists. | — |
| `includes/Membership_Group.php` | `add_subscription_line_item()` | Bulk CSV import: `calculate_totals()` fires per `add_member()` call. For large imports, investigate batching totals recalculation. | — |
| `includes/Membership_Group.php` | `create_group_subscription()` | Implement group subscription status transitions. Individual memberships go `pending → active` via WC order completion hook (`Membership_Subscription_Controller` lines 84–87). Group subscriptions have no parent order — need explicit activation path, likely triggered when group status transitions to `active`. | — |

## Pagination — List Pages

| File | Component | Note | Asana |
|---|---|---|---|
| `frontend/src/members/index.js` | Individual & org member list pagination | Replace the static current-page display (`searchParams.page` rendered as text) with a number `<input>` that accepts manual page entry and triggers navigation on blur/Enter. Also sync the current page into the URL query string (e.g. `?page=3`) so the value persists on refresh and can be shared/bookmarked. Applies to individual_member_list and org_member_list pages. | — |
| `frontend/src/members/group_list.js` | Group member list pagination | Same as above — replace static page display with a typeable number input and sync page to URL. Applies to group_member_list page. | — |

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

## Group List & Detail — UUID-Based Navigation and Series Grouping

| File | Component | Note | Asana |
|---|---|---|---|
| `includes/Group_Admin_Controller.php` | `get_membership_groups_list()` | Rearchitect group list to deduplicate by a stable group series identifier (mirroring how individual list deduplicates by `user_id` and org list by `org_uuid`). Each row should represent a series of annual group instances, not a single `wicket_mship_group` post. | — |
| `includes/Group_Admin_Controller.php` | `get_group_edit_page_info()` | Detail page should load all `wicket_mship_group` posts belonging to the same series (same org + group name, or same series UUID), stacking them like individual detail stacks tiers. | — |
| `frontend/src/members/group_list.js` | `GroupMemberList` | Update list row navigation to use a stable series key (series UUID or composite `org_uuid+group_name`) instead of WP post ID. | — |
| `frontend/src/membership_groups/` | Group detail header | Header should show org name + group series name. Body should list all yearly group instances in the series. | — |

**Design decision pending — two paths, in priority order:**

1. **MDP group UUID (preferred):** Confirm with MDP team whether their upcoming group UUID endpoint returns a UUID that is stable across annual renewals of the same group (i.e. the same UUID for "ACME Board 2024" and "ACME Board 2025"). If yes, store as `membership_group_series_uuid` on each `wicket_mship_group` post and use it as the dedup/navigation key — directly parallel to `membership_wicket_uuid` on individual posts.
2. **Composite key fallback:** If MDP assigns a fresh UUID per year, use `org_uuid + group_name` as the composite dedup key in PHP. Navigate via both values in the URL query string. Fragile if group name changes between years — document that constraint clearly.

Full architecture context in `/srv/wicket-wp-stack/GROUP_MEMBERSHIP_LIST_PLAN.md`.

## Membership_Group_WP_REST_Controller

| File | Method | Note | Asana |
|---|---|---|---|
| `includes/Membership_Group_WP_REST_Controller.php` | `register_routes()` | Register `POST /group/{id}/create_renewal_order` once the group subscription line item structure is finalised. Current group edit flow intentionally mocks commerce gaps instead of attempting partial implementation. | — |
| `includes/Membership_Group_WP_REST_Controller.php` | `register_routes()` | Register remaining group routes once backing business logic exists: `GET /get_membership_group_callouts`, `POST /group/{id}/move_member`, `GET /group/{id}/members`, and `POST /group/{id}/import_members`. | — |
