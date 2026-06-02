# TODO

Tracked outstanding work items for the wicket-wp-memberships plugin.
Add an entry here whenever a `// TODO` comment is left in code.
Remove the entry when the work is completed.

---

## QA Tests

| File | Test | Note | Asana |
|---|---|---|---|
| `qa/tests/WordPress/Memberships/admin-controller.pest.php` | `activates a pending membership and its subscription via admin status change` | `memberships_create_fixture` creates subscription as `active` instead of expected `on-hold`. Fix the fixture then re-enable the `todo()`. | — |

---

## Membership_Bundle — Approval Workflow

| File | Method | Note | Asana |
|---|---|---|---|
| `includes/Membership_Bundle.php` | `create()` | Implement full bundle approval workflow: send approval email (link to org edit page), handle `pending→active` admin transition, show member-portal callout while pending. Mirror `Membership_Controller::create_membership_record()` lines 764–781 and `Admin_Controller::admin_manage_status()`. Also decide whether approval blocks adding individual memberships to the bundle until approved. | — |
| `includes/Membership_Bundle.php` | `apply_edit_fields()` | Review and consider replacing with typed getters/setters per field — current `array<string,mixed>` signature allows any meta key to be written without validation. | — |
| `includes/Membership_Bundle.php` | `add_subscription_line_item()` | Bulk CSV import: `calculate_totals()` fires per `add_member()` call. For large imports, investigate batching totals recalculation. | — |

---

---

## MDP Bundle Sync — Implemented

`Membership_Bundle::sync_mdp_create/update/delete()` are live. Base plugin helpers
(`helper-membership-bundles.php`) and all three sync methods are fully implemented and
called at the correct sites (create, renew, transition, date edit, owner change, delete).

---

---

## Frontend — MDP Links

| File | Component | Note | Asana |
|---|---|---|---|
| `frontend/src/members/MembershipBundleDetails.js` | `MembershipBundleDetails` | Implement "Move to Another Bundle" action — button is currently disabled. | — |

## Membership_Bundle_Config — Product ID concerns

| File | Method | Note | Asana |
|---|---|---|---|
| `includes/Membership_Bundle_Config.php` | `get_late_fee_window_product_id()` | **Temporary implementation.** Reads `product_id` from `late_fee_window_data` meta (same as `Membership_Config`). The late-fee product concept for bundle configs has not been defined — may need to be derived from the bundle's tier products instead of stored directly. Review and replace before production use. Currently included in `Membership_Bundle::get_owner_callouts()` grace period entries — fix here will flow through automatically. | — |

---

## Membership_Bundle_WP_REST_Controller

| File | Method | Note | Asana |
|---|---|---|---|
| `includes/Membership_Bundle_WP_REST_Controller.php` | `register_routes()` | Register `POST /bundle/{id}/create_renewal_order` once the bundle subscription line item structure is finalised. | — |
| `includes/Membership_Bundle_WP_REST_Controller.php` | `register_routes()` | Register remaining routes once backing business logic exists: `GET /bundle/{id}/members`, `POST /bundle/{id}/import_members`. (`move_member` is implemented as `move_individual_membership`.) | — |

---

## Frontend Tests

| File | Method | Note | Asana |
|---|---|---|---|
| `frontend/src/` | — | Build frontend tests for shared components and membership bundle config UI (BundleConfigForm, SeasonConfigModal, GracePeriodSection, RenewalWindowSection, etc.). | — |

---

## QA Tests — Bundle Owner Callouts

| File | Test | Note | Asana |
|---|---|---|---|
| `qa/tests/WordPress/Memberships/` | Bundle owner early renewal callout | Verify `Membership_Bundle::get_owner_callouts()` returns an `early_renewal` entry (with correct `membership` shape) when `current_time >= early_renew_at && current_time < ends_at`. | — |
| `qa/tests/WordPress/Memberships/` | Bundle owner grace period callout | Verify `get_owner_callouts()` returns a `grace_period` entry when `current_time > ends_at && current_time <= expires_at`. Requires `expires_at` to be set on the bundle post. | — |
| `qa/tests/WordPress/Memberships/` | Bundle owner pending callout | Verify `get_owner_callouts()` returns a `pending_approval` entry when bundle `membership_status = pending`. | — |
| `qa/tests/WordPress/Memberships/` | Bundle-linked individual suppressed | Verify `Membership_Controller::get_membership_callouts()` does not include a callout for an individual `wicket_membership` post that has `membership_bundle_id` set. | — |
| `qa/tests/WordPress/Memberships/` | Bundle owner with no individual post | Verify `get_membership_callouts()` returns bundle owner callouts even when the owner has no `wicket_membership` post at all. | — |
| `qa/tests/WordPress/Memberships/` | Entry shape matches ACC contract | Verify each entry in `early_renewal`/`grace_period`/`pending_approval` from `get_owner_callouts()` has keys: `membership.ID`, `membership.ends_in_days`, `membership.meta.membership_status`, `membership.next_tier`, `membership.subscription_renewal`, `membership.multi_tier_renewal`, `callout.header`, `callout.content`. | — |

---

