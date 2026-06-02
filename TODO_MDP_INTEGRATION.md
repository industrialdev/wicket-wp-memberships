# MDP Membership Bundle Integration

## Context

Individual and organization memberships are fully synced to the MDP via
`Membership_Controller::create_mdp_record()` and `update_mdp_record()`. Those
wrappers call the base-plugin functions `wicket_assign_individual_membership()`,
`wicket_assign_organization_membership()`, `wicket_update_individual_membership_dates()`,
`wicket_update_organization_membership_dates()`, and `wicket_delete_person_membership()` /
`wicket_delete_organization_membership()`.

Bundle membership MDP sync is now fully implemented. See the sync summary below.

---

## What Individual/Org Sync Does (Reference)

| Event | Code location | MDP call |
|---|---|---|
| Membership created | `Admin_Controller.php:153` | `create_mdp_record()` → `wicket_assign_*_membership()` |
| Dates/status updated (admin edit) | `Admin_Controller.php:155, 316, 748` | `update_mdp_record()` → `wicket_update_*_membership_dates()` |
| Owner changed | `Admin_Controller.php:1392` | `update_mdp_record()` |
| Direct individual assign (re-assign flows) | `Admin_Controller.php:1015, 1214, 1319` | `wicket_assign_individual_membership()` |
| Post trashed / bulk deleted | `Utilities.php:224, 232, 251, 253` | `wicket_delete_person_membership()` / `wicket_delete_organization_membership()` |
| Subscription seat count sync | `custom/memberships-sync.php:219` | `memberships_update_seat_count()` |

---

## Bundle Sync — Implemented

All sync methods are live in `includes/Membership_Bundle.php`. Base plugin helpers
are in `helper-membership-bundles.php`.

| Event | Code location | MDP call |
|---|---|---|
| Bundle created | `Membership_Bundle.php:208` | `sync_mdp_create()` → `wicket_create_bundle_membership()` |
| Bundle renewed | `Membership_Bundle.php:325` | `sync_mdp_create()` → `wicket_create_bundle_membership()` |
| Status changed | `Membership_Bundle.php:1455` | `sync_mdp_update()` → `wicket_update_bundle_membership()` |
| Dates edited (admin) | `Membership_Bundle.php:1938` | `sync_mdp_update()` → `wicket_update_bundle_membership()` |
| Owner changed | `Membership_Bundle.php:2093` | `sync_mdp_update()` → `wicket_update_bundle_membership_owner()` |
| Post hard-deleted | `Utilities.php` (bundle CPT branch) | `sync_mdp_delete()` → `wicket_delete_bundle_membership()` |

`sync_mdp_create()` stores the returned MDP UUID as `membership_bundle_mdp_uuid` post meta.
`sync_mdp_update()` silently no-ops when `membership_bundle_mdp_uuid` is empty (safe degradation).

## Bundle List & Detail Page Rearchitecture — Implemented

The target UUID-based architecture is fully implemented.

| Feature | Target | Status |
|---|---|---|
| List dedup key | `membership_bundle_group_uuid` | ✓ Done — `Bundle_Admin_Controller::get_membership_bundles_list()` |
| Row `id` field | UUID string | ✓ Done — `build_membership_bundles_row()` returns `get_bundle_group_uuid()` |
| Navigation URL param | `id=UUID` | ✓ Done — `Membership_CPT_Hooks::render_edit_bundle_member_page()` reads UUID |
| Detail page loads | All posts matching UUID | ✓ Done — `get_bundle_edit_page_info()` queries by `membership_bundle_group_uuid` |
| Renewal linkage | Shared UUID (no explicit pointer) | ✓ Done — UUID copied forward in `renew_bundle()` |

Frontend MDP links (bundle list "View in MDP", detail page "View in MDP", member panel link)
are wired — `bundle_mdp_link` built from `membership_bundle_mdp_uuid` post meta and passed
through all three PHP endpoints. Links render only when `bundle_mdp_uuid` is populated (i.e.
the bundle has been synced to MDP).

---

## Open Questions for MDP Team

1. What is the endpoint/method signature for creating a bundle membership record?
2. What fields does the bundle membership record accept (seats, tier UUID, status enum values)?
3. Is there a discrete cancel endpoint, or does cancellation go through a status-update PATCH?

**Resolved:**

4. ~~Is the bundle membership UUID stable across annual renewals?~~ Bundle series grouping is handled locally via `membership_bundle_group_uuid` — independent of any MDP UUID. See `plans/PLAN-grouping-membership-bundles.md`.
5. ~~What MDP timezone conventions apply to bundle date fields?~~ Same `WICKET_MSHIP_MDP_TIMEZONE` env var + `Utilities::get_mdp_day_end()` used everywhere.
6. ~~Does the MDP bundle record carry a seat count concept?~~ No seat count on bundle records.

---

## Related Files

- `includes/Membership_Bundle.php` — `sync_mdp_create/update/delete()` implementations
- `includes/Bundle_Admin_Controller.php` — `get_membership_bundles_list()`, `get_bundle_edit_page_info()`, `build_membership_bundles_row()`
- `includes/Membership_CPT_Hooks.php` — `render_edit_bundle_member_page()`
- `includes/Utilities.php` — `delete_wicket_membership_in_mdp()` bundle CPT branch
- `../wicket-wp-base-plugin/includes/helpers/helper-membership-bundles.php` — base plugin bundle API helpers
- `TODO.md` — outstanding items including MDP-gated frontend work
- `CURRENT_SCOPE.md` — active bundle feature scope
- `plans/PLAN-grouping-membership-bundles.md` — bundle series UUID design
