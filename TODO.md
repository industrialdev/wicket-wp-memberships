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
| `qa/tests/WordPress/Memberships/` | `Membership_Group_Cron_Controller` — all three daily handlers | **Blocked on QA suite being fixed.** Once suite is stable, add tests for: (1) `daily_group_grace_period_hook` transitions active group with elapsed `ends_at` to grace-period; (2) `daily_group_expiry_hook` transitions active/grace-period group with elapsed `expires_at` to expired; (3) `daily_group_activation_hook` transitions delayed group with elapsed `starts_at` to active. Each test must assert: correct group status after hook, child individual membership statuses cascaded, dates unchanged on the group post. | — |
| `qa/tests/WordPress/Memberships/` | `Membership_Group::schedule_date_trigger_jobs()` — date trigger AS jobs | **Blocked on QA suite being fixed.** Once suite is stable, add tests for: (1) three AS jobs scheduled on `create()` with correct timestamps; (2) jobs rescheduled (old cancelled, new created) on `update_group_entity_record()` date change; (3) no job scheduled when a date field is empty; (4) `catch_group_early_renew_at`, `catch_group_ends_at`, `catch_group_expires_at` each fire the correct `do_action` hook with the group post ID. | — |

---



## Membership_Group

| File | Method | Note | Asana |
|---|---|---|---|
| `includes/Membership_Group.php` | `create()` | Implement full group approval workflow: send approval email (link to org edit page), handle `pending→active` admin transition, show member-portal callout while pending. Mirror `Membership_Controller::create_membership_record()` lines 764–781 and `Admin_Controller::admin_manage_status()`. Also decide whether approval blocks adding individual memberships to the group until approved. | — |
| `includes/Membership_Group.php` | `apply_edit_fields()` | Review and consider replacing with typed getters/setters per field — current `array<string,mixed>` signature allows any meta key to be written without validation | — |
| `includes/Membership_Group.php` | `add_subscription_line_item()` | Bulk CSV import: `calculate_totals()` fires per `add_member()` call. For large imports, investigate batching totals recalculation. | — |

## Pagination — List Pages

| File | Component | Note | Asana |
|---|---|---|---|
| `frontend/src/members/index.js` | Individual & org member list pagination | Replace the static current-page display (`searchParams.page` rendered as text) with a number `<input>` that accepts manual page entry and triggers navigation on blur/Enter. Also sync the current page into the URL query string (e.g. `?page=3`) so the value persists on refresh and can be shared/bookmarked. Applies to individual_member_list and org_member_list pages. | — |
| `frontend/src/members/group_list.js` | Group member list pagination | Same as above — replace static page display with a typeable number input and sync page to URL. Applies to group_member_list page. | — |

## Frontend Tests

| File | Method | Note | Asana |
|---|---|---|---|
| `frontend/src/` | — | Build frontend tests for shared components and membership group config UI (GroupConfigForm, SeasonConfigModal, GracePeriodSection, RenewalWindowSection, etc.) | — |

## QA Tests — Group Owner Callouts

| File | Test | Note | Asana |
|---|---|---|---|
| `qa/tests/WordPress/Memberships/` | Group owner early renewal callout | Verify `Membership_Group::get_owner_callouts()` returns an `early_renewal` entry (with correct `membership` shape) when `current_time >= early_renew_at && current_time < ends_at`. | — |
| `qa/tests/WordPress/Memberships/` | Group owner grace period callout | Verify `get_owner_callouts()` returns a `grace_period` entry when `current_time > ends_at && current_time <= expires_at`. Requires `expires_at` to be set on the group post. | — |
| `qa/tests/WordPress/Memberships/` | Group owner pending callout | Verify `get_owner_callouts()` returns a `pending_approval` entry when group `membership_status = pending`. | — |
| `qa/tests/WordPress/Memberships/` | Group-linked individual suppressed | Verify `Membership_Controller::get_membership_callouts()` does not include a callout for an individual `wicket_membership` post that has `membership_group_id` set. | — |
| `qa/tests/WordPress/Memberships/` | Group owner with no individual post | Verify `get_membership_callouts()` returns group owner callouts even when the owner has no `wicket_membership` post at all. | — |
| `qa/tests/WordPress/Memberships/` | Entry shape matches ACC contract | Verify each entry in `early_renewal`/`grace_period`/`pending_approval` from `get_owner_callouts()` has keys: `membership.ID`, `membership.ends_in_days`, `membership.meta.membership_status`, `membership.next_tier`, `membership.subscription_renewal`, `membership.multi_tier_renewal`, `callout.header`, `callout.content`. | — |


## Membership_Group_Config — Product ID concerns

| File | Method | Note | Asana |
|---|---|---|---|
| `includes/Membership_Group_Config.php` | `get_late_fee_window_product_id()` | **Temporary implementation.** Reads `product_id` from `late_fee_window_data` meta (same as `Membership_Config`). The late-fee product concept for group configs has not been defined — may need to be derived from the group's tier products instead of stored directly. Review and replace before production use. Currently included in `Membership_Group::get_owner_callouts()` grace period entries — fix here will flow through automatically. | — |

## MDP Group Sync — Implementation (blocked on MDP API + base plugin)

Call sites are stubbed. `Membership_Group::sync_mdp_create/update/delete()` are no-ops
until the MDP exposes a group membership API and the base plugin adds the helper functions below.
See `TODO_MDP_INTEGRATION.md` for full context and open questions for the MDP team.

### Step 1 — Base plugin: add three helper functions in `helper-unsorted.php`

Mirror the existing individual/org pattern exactly (try/catch, `wicket_pre_*` filter, `WP_Error` on failure).
Endpoint slugs follow MDP naming convention — confirm with MDP team before implementing.

| Function to add | HTTP | Endpoint (assumed) | Mirrors |
|---|---|---|---|
| `wicket_assign_group_membership( $person_uuid, $org_uuid, $membership_uuid, $starts_at, $ends_at, $grace_period_days, $previous_membership_uuid )` | POST | `group_memberships` | `wicket_assign_organization_membership()` |
| `wicket_update_group_membership( $membership_uuid, $starts_at, $ends_at, $grace_period_days )` | PATCH | `group_memberships/{uuid}` | `wicket_update_organization_membership_dates()` |
| `wicket_delete_group_membership( $membership_uuid )` | DELETE | `group_memberships/{uuid}` | `wicket_delete_organization_membership()` |

Payload shape, status field name, and any group-specific attributes (seats, owner UUID on create) are TBD — confirm with MDP team.

### Step 2 — Memberships plugin: implement the three `sync_mdp_*` stubs

Once base plugin functions exist, implement in `includes/Membership_Group.php`:

| Method | Calls | Uses |
|---|---|---|
| `sync_mdp_create()` | `wicket_assign_group_membership()` | `get_org_uuid()`, `get_config()` tier UUID, `get_dates()`, `get_owner_uuid()`, store response UUID as `membership_group_wicket_uuid` post meta |
| `sync_mdp_update()` | `wicket_update_group_membership()` | `membership_group_wicket_uuid` post meta, `get_dates()`, `get_membership_status()`, `get_owner_uuid()` |
| `sync_mdp_delete()` | `wicket_delete_group_membership()` | `membership_group_wicket_uuid` post meta |

## Group Subscription Renewal — Membership Creation on Payment

| File | Method | Note | Asana |
|---|---|---|---|
| `includes/Membership_Controller.php` | `catch_order_completed()` | **Gap: group subscription payments are ignored.** When a group subscription renews, `catch_order_completed()` fires but has no group awareness — no transition, no child membership renewal. Need to detect when the completing order belongs to a group subscription (check `membership_group_id` meta on subscription), then call `$group->transition_to('active')` and renew all child individual membership records. Should be implemented as an async Action Scheduler job given group subscriptions can have any number of child memberships, each requiring MDP API calls. | — |

---

## Group_Admin_Controller

| File | Method | Note | Asana |
|---|---|---|---|
| `includes/Group_Admin_Controller.php` | `update_group_entity_record()` | Sync updated dates to MDP when group dates are edited. Individual memberships call `update_mdp_record()` on date edit — group equivalent not yet implemented pending MDP group date API availability. | — |
| `includes/Group_Admin_Controller.php` | `admin_manage_status()` | Cancel the linked group WooCommerce subscription when a group is cancelled or expired, once group subscription management is implemented. | — |
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
| `frontend/src/membership_groups/components/MembershipGroupRecordDetails.js` | `MembershipGroupRecordDetails` | Switch `subscriptionId`, `subscriptionLink`, `nextPaymentDate`, and `orders` props to read from the individual `record` object rather than `groupPageData` once per-record subscription and order enrichment is implemented in `Group_Admin_Controller`. | — |

## Group List & Detail — UUID-Based Navigation and Series Grouping

**Design decision resolved:** MDP group UUID is stable year-to-year. `membership_group_wicket_uuid` is the dedup/navigation key — same UUID on every annual post for a group. No composite fallback or separate series key needed. See `TODO_MDP_INTEGRATION.md` for full architecture.

**Blocked on MDP API availability** — `membership_group_wicket_uuid` post meta is not populated until `sync_mdp_create()` is implemented.

| File | Component | Note | Asana |
|---|---|---|---|
| `includes/Group_Admin_Controller.php` | `get_membership_groups_list()` | Deduplicate by `membership_group_wicket_uuid`. One row per group, showing latest instance dates/status. Mirrors `get_members_list()` dedup pattern in `Membership_Controller`. | — |
| `includes/Group_Admin_Controller.php` | `get_group_edit_page_info()` | Load all posts matching `membership_group_wicket_uuid`, stack yearly instances. Mirrors individual detail stacking tiers. | — |
| `includes/Group_Admin_Controller.php` | `build_membership_groups_row()` | Replace `id => $post->ID` with `id => membership_group_wicket_uuid` post meta. | — |
| `includes/Membership_CPT_Hooks.php` | `render_edit_group_member_page()` | Read `$_GET['id']` as UUID string, pass as `data-group-uuid` to React (mirrors `data-record-id` for individuals). | — |
| `frontend/src/members/group_list.js` | `GroupMemberList` | Row navigation `id` is now UUID string, not post ID integer. | — |
| `frontend/src/membership_groups/` | Group detail page | UUID-based post query, stacked yearly instances, org name + group name header. | — |

## Membership_Group_WP_REST_Controller

| File | Method | Note | Asana |
|---|---|---|---|
| `includes/Membership_Group_WP_REST_Controller.php` | `register_routes()` | Register `POST /group/{id}/create_renewal_order` once the group subscription line item structure is finalised. Current group edit flow intentionally mocks commerce gaps instead of attempting partial implementation. | — |
| `includes/Membership_Group_WP_REST_Controller.php` | `register_routes()` | Register remaining group routes once backing business logic exists: `POST /group/{id}/move_member`, `GET /group/{id}/members`, and `POST /group/{id}/import_members`. | — |
