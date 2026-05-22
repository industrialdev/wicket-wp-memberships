# MDP Membership Bundle Integration Plan

## Context

Individual and organization memberships are fully synced to the MDP via
`Membership_Controller::create_mdp_record()` and `update_mdp_record()`. Those
wrappers call the base-plugin functions `wicket_assign_individual_membership()`,
`wicket_assign_organization_membership()`, `wicket_update_individual_membership_dates()`,
`wicket_update_organization_membership_dates()`, and `wicket_delete_person_membership()` /
`wicket_delete_organization_membership()`.

**Membership bundles currently have no equivalent.** The MDP does not yet expose
a bundle membership API. This plan documents every sync gap so that when the MDP
bundle API is available, each integration point is already identified, stubbed
with a `// TODO` comment, and tracked here.

No MDP calls should be implemented until the MDP API shape is confirmed.

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

## Bundle Sync Gaps — TODO Stubs Required

### 1. `Membership_Bundle::create()` — after local post creation

**File:** `includes/Membership_Bundle.php` (~line 2608, end of `create()`)
**Individual equivalent:** `Admin_Controller.php:153` — `create_mdp_record()`

After the WP post, meta, dates, subscription, and owner are all written and the
method is about to `return $bundle`, sync the new bundle to an MDP bundle membership
record. The MDP UUID returned should be stored as post meta (likely
`membership_bundle_mdp_uuid` — confirm field name with MDP team).

**MDP data needed (unknown until API is defined):**
- Bundle name
- Org UUID (`org_uuid` post meta)
- Tier UUID (from `Membership_Bundle_Config`)
- `starts_at` / `ends_at` / `expires_at`
- Owner person UUID

**`// TODO` comment:** Added at `Membership_Bundle.php` — see code stub below.

---

### 2. `Membership_Bundle::transition_to()` — on status change

**File:** `includes/Membership_Bundle.php` (~line 2628, after status cascade to members)
**Individual equivalent:** `Admin_Controller.php:316` — `update_mdp_record()` after status transition

When a bundle transitions to any new status (`active`, `cancelled`, `expired`,
`grace-period`), push the updated status to the MDP bundle membership record.

**MDP data needed:**
- Bundle MDP UUID (`membership_bundle_mdp_uuid`)
- New status (map local vocabulary to MDP enum — TBD)
- Updated dates (if status change collapses/adjusts them)

**Note:** Cancellation via `cancel_bundle()` → `transition_to('cancelled')` flows
through this same method — no separate cancel stub needed at the
`Bundle_Admin_Controller::cancel_bundle()` level unless the MDP exposes a distinct
cancel endpoint vs. a status-update endpoint.

**`// TODO` comment:** Added at `Membership_Bundle.php` — see code stub below.

---

### 3. `Bundle_Admin_Controller::update_bundle_entity_record()` — on date/renewal edit

**File:** `includes/Bundle_Admin_Controller.php` (~line 514, after `apply_edit_fields()` succeeds)
**Individual equivalent:** `Admin_Controller.php:748` — `update_mdp_record()` after date edit

Admin edits bundle dates (starts_at, ends_at, expires_at, renewal_type). After
local meta is written, sync the new dates to the MDP bundle membership record.

**MDP data needed:**
- Bundle MDP UUID
- `starts_at`, `ends_at`, `expires_at` (already available as `$dates` on line 512)
- Seat count / max_assignments if applicable

**`// TODO` comment:** Added at `Bundle_Admin_Controller.php` — see code stub below.

---

### 4. `Bundle_Admin_Controller::cancel_bundle()` — on cancellation

**File:** `includes/Bundle_Admin_Controller.php` (~line 1097 path A, ~line 1083 path C)
**Individual equivalent:** `Admin_Controller.php` cancel flows + `Utilities.php:224`

If MDP exposes a discrete cancel endpoint (separate from status update), call
it here. If MDP only has a status field, this is covered by stub #2
(`transition_to` cascade). Leave a stub at both cancel paths until the API
shape is known.

**Paths:**
- Path A (cancel_all + immediately): after `transition_to('cancelled')` — line ~1097
- Path C (keep_as_individual): after `cancel_keep_as_individual()` — line ~1082
- Path B (cancel_all + at_end_date): sets subscription to pending-cancel; bundle
  itself transitions later via a scheduled job — stub goes at the point the
  deferred cancellation is queued (~line 1140).

**`// TODO` comment:** Added at `Bundle_Admin_Controller.php` — see code stubs below.

---

### 5. `Membership_Bundle::set_owner()` — on owner change

**File:** `includes/Membership_Bundle.php` (~line 1202, after `reassign_subscription_customer()`)
**Individual equivalent:** `Admin_Controller.php:1392` — `update_mdp_record()` after ownership transfer

When the bundle owner changes, update the MDP bundle membership record to reflect
the new owner's person UUID.

**MDP data needed:**
- Bundle MDP UUID
- New owner's person UUID (`$uuid` parameter — already in scope)

**`// TODO` comment:** Added at `Membership_Bundle.php` — see code stub below.

---

### 6. Bundle post trashed / deleted — no MDP bundle UUID yet

**File:** `includes/Utilities.php` — `delete_wicket_membership_in_mdp()` (line ~246)
**Individual equivalent:** `wicket_delete_person_membership()` / `wicket_delete_organization_membership()`

When a `wicket_mship_bundle` post is trashed, call the MDP bundle delete endpoint
using the stored `membership_bundle_mdp_uuid`. Currently `Utilities.php`
handles individual/org deletes via CPT-type checks. Add a third branch for
bundle CPT.

**`// TODO` comment:** Added at `Utilities.php` — see code stub below.

---

### 7. Frontend: "View in MDP" links (wired to bundle MDP URL)

**Files:**
- `frontend/src/members/MembershipBundleDetails.js` — "View in MDP" link (currently disabled)
- `frontend/src/members/bundle_list.js` — "Link to MDP" column (currently disabled)
- `frontend/src/membership_bundles/components/IntroBlockSection.js` — "View in MDP" action

These are already noted in `TODO.md`. They are blocked on a bundle MDP UUID
existing in post meta. Once stubs 1–6 land and the MDP API is live, replace the
org MDP link with a membership bundle MDP link using `membership_bundle_mdp_uuid`.

No `// TODO` code comment needed — already tracked in `TODO.md`.

---

### 8. Bundle series UUID stability (design decision) — SUPERSEDED

~~Documented in `TODO.md` under "Bundle List & Detail — UUID-Based Navigation and
Series Grouping". If MDP assigns a UUID that is **stable across annual renewals**
of the same bundle, store it as `membership_bundle_series_uuid` and use it as the
dedup key. If MDP assigns a fresh UUID per renewal period, fall back to the
composite `org_uuid + bundle_name` key.~~

**Resolved — see `PLAN-grouping-membership-bundles.md`.**

The MDP has no bundle API and will not assign bundle UUIDs. Bundle series grouping
is handled locally via `membership_bundle_group_uuid`, a WordPress-generated
composite key (`{org_uuid}-{random_suffix}`) stored as post meta. This key is
written on first create and copied forward on renewal. It is independent of any
MDP UUID. The full design is in `plans/PLAN-grouping-membership-bundles.md`.

---

## Code Stubs to Add

### `Membership_Bundle.php` — `create()` (after local post/meta/subscription written, ~line 2608)

```php
// TODO: Sync new bundle to MDP bundle membership record once MDP bundle API is available.
//       Store the returned MDP UUID as 'membership_bundle_mdp_uuid' post meta.
//       Data to send: bundle name, org_uuid, tier UUID (from config), starts_at,
//       ends_at, expires_at, owner person UUID.
//       See TODO_MDP_INTEGRATION.md §1 for full requirements.
```

### `Membership_Bundle.php` — `transition_to()` (after status cascade to members, ~line 2628)

```php
// TODO: Push updated bundle status to MDP bundle membership record.
//       Map local status vocabulary to MDP enum (TBD with MDP team).
//       If the transition also changes dates (e.g. cancelled collapses ends_at),
//       include the updated dates in the same call.
//       See TODO_MDP_INTEGRATION.md §2.
```

### `Membership_Bundle.php` — `set_owner()` (after `reassign_subscription_customer()`, ~line 1202)

```php
// TODO: Update MDP bundle membership record with new owner person UUID ($uuid).
//       Read bundle MDP UUID from 'membership_bundle_mdp_uuid' post meta.
//       See TODO_MDP_INTEGRATION.md §5.
```

### `Bundle_Admin_Controller.php` — `update_bundle_entity_record()` (after `apply_edit_fields()`, ~line 514)

```php
// TODO: Sync updated bundle dates to MDP bundle membership record.
//       Equivalent to Membership_Controller::update_mdp_record() for individuals (Admin_Controller.php:748).
//       Data: bundle MDP UUID, starts_at, ends_at, expires_at (available in $dates at line 512).
//       See TODO_MDP_INTEGRATION.md §3.
```

### `Bundle_Admin_Controller.php` — `cancel_bundle()` path A (~line 1097)

```php
// TODO: If MDP exposes a discrete cancel endpoint, call it here after transition_to('cancelled').
//       If MDP uses status-update only, this is covered by the transition_to() stub.
//       Confirm API shape with MDP team. See TODO_MDP_INTEGRATION.md §4.
```

### `Bundle_Admin_Controller.php` — `cancel_bundle()` path B (~line 1140)

```php
// TODO: Notify MDP of pending-cancel state if MDP supports deferred cancellation.
//       Otherwise, defer the MDP call to when the scheduled transition fires.
//       See TODO_MDP_INTEGRATION.md §4.
```

### `Bundle_Admin_Controller.php` — `cancel_bundle()` path C (~line 1082)

```php
// TODO: If MDP needs to be notified when a bundle is dissolved into individual memberships,
//       call the MDP bundle delete/cancel endpoint here.
//       See TODO_MDP_INTEGRATION.md §4.
```

### `Utilities.php` — `delete_wicket_membership_in_mdp()` (~line 246)

```php
// TODO: Add CPT branch for wicket_mship_bundle: read 'membership_bundle_mdp_uuid'
//       and call MDP bundle delete endpoint (function name TBD).
//       Mirrors wicket_delete_person_membership() / wicket_delete_organization_membership().
//       See TODO_MDP_INTEGRATION.md §6.
```

---

## Open Questions for MDP Team

1. What is the endpoint/method signature for creating a bundle membership record?
2. What fields does the bundle membership record accept (seats, tier UUID, status enum values)?
3. Is there a discrete cancel endpoint, or does cancellation go through a status-update PATCH?
4. ~~Is the bundle membership UUID stable across annual renewals of the same bundle?~~ **Resolved: bundle series grouping is handled locally via `membership_bundle_group_uuid` — `membership_bundle_mdp_uuid` not involved. See `plans/PLAN-grouping-membership-bundles.md`.**
5. ~~What MDP timezone conventions apply to bundle date fields?~~ **Resolved: same `WICKET_MSHIP_MDP_TIMEZONE` env var + `Utilities::get_mdp_day_end()` used everywhere already.**
6. ~~Does the MDP bundle record carry a `max_assignments` / seat count concept?~~ **Resolved: no seat count on bundle records. Seat management does not apply to bundles.**

---

## Bundle List & Detail Page Rearchitecture

### Design Decisions (resolved)

- `membership_bundle_group_uuid` is **stable across annual renewals** — the same UUID is stored on every `wicket_mship_bundle` post belonging to the same bundle series (e.g. "ACME IT 2024" and "ACME IT 2025" share one UUID).
- The list page deduplicates by `membership_bundle_group_uuid`, showing one row per bundle with the **latest instance's dates and status**.
- The detail page shows **all yearly instances stacked** — mirrors how individual membership detail stacks all tiers.
- **No `previous_bundle_post_id` needed** — the shared UUID is sufficient linkage. No explicit renewal chain pointer.
- No separate series UUID field needed — `membership_bundle_group_uuid` serves both roles.

### Current state (post-ID based, deviates from individual/org pattern)

| | Individual/Org | Bundle (current) |
|---|---|---|
| List dedup key | `user_id` / `org_uuid` | WP post ID — no dedup |
| Navigation URL param | `id=person_uuid` / `id=org_uuid` | `id=post_id` |
| Detail page loads | All posts matching UUID | Single post by post ID |
| Renewal linkage | `previous_membership_post_id` | None |

### Target state (UUID based, mirrors individual/org)

| | Individual/Org | Bundle (target) |
|---|---|---|
| List dedup key | `user_id` / `org_uuid` | `membership_bundle_group_uuid` |
| Navigation URL param | `id=person_uuid` / `id=org_uuid` | `id=membership_bundle_group_uuid` |
| Detail page loads | All posts matching UUID | All posts matching UUID |
| Renewal linkage | `previous_membership_post_id` | Shared UUID (no explicit pointer) |

### Changes required

**Backend — `includes/Bundle_Admin_Controller.php`**

- `get_membership_bundles_list()`: deduplicate rows by `membership_bundle_group_uuid` (same
  PHP dedup pattern as `Membership_Controller::get_members_list()` — fetch all, sort,
  keep first per unique key). Row represents latest instance only.
- `get_bundle_edit_page_info()`: load all `wicket_mship_bundle` posts matching the UUID
  from URL param, not a single post by post ID. Stack instances by year.
- `build_membership_bundles_row()`: replace `id => $post->ID` with
  `id => get_post_meta( $post->ID, 'membership_bundle_group_uuid', true )`.

**Backend — `includes/Membership_CPT_Hooks.php`**

- `render_edit_bundle_member_page()` (~line 199): read `$_GET['id']` as
  `membership_bundle_group_uuid` (string) instead of post ID. Pass as
  `data-bundle-uuid` to the React component (mirroring `data-record-id` for individuals).

**Frontend — `frontend/src/members/bundle_list.js`**

- Row navigation: `addQueryArgs(editBundleUrl, { id: bundle.id })` where `bundle.id` is now
  the UUID string, not a post ID integer.

**Frontend — `frontend/src/membership_bundles/` (detail page)**

- Replace single-post load with UUID-based query: fetch all bundle posts matching
  `membership_bundle_group_uuid`.
- Stack yearly instances in the detail view (mirroring individual tier stacking).
- Header: org name + bundle name. Body: list of all yearly instances with their dates/status.

**Blocked on:** `membership_bundle_group_uuid` being populated — requires the bundle creation
flow to generate and store the UUID (see `plans/PLAN-grouping-membership-bundles.md`).
All list/detail rearchitecture work can be stubbed but not fully functional until UUIDs
exist in post meta.

---

## Implementation Order (when MDP API is ready)

1. Confirm API shape and answer open questions above.
2. Add base-plugin helper functions (`wicket_assign_bundle_membership()`, `wicket_update_bundle_membership()`, `wicket_delete_bundle_membership()`) — mirroring individual/org equivalents in `helper-unsorted.php`.
3. Implement `sync_mdp_create()` — foundational; populates `membership_bundle_mdp_uuid` post meta which all downstream work depends on.
4. Implement `sync_mdp_update()` — status, date, and owner sync.
5. Implement `sync_mdp_delete()` — hard-delete on post trash.
6. Rearchitect bundle list page: dedup by `membership_bundle_group_uuid`, update row `id` field, update navigation URL param.
7. Rearchitect bundle detail page: UUID-based post query, stacked yearly instances.
8. Wire frontend MDP links (currently disabled/red).

---

## Related Files

- `includes/Membership_Bundle.php` — `sync_mdp_create/update/delete()` stubs
- `includes/Bundle_Admin_Controller.php` — `get_membership_bundles_list()`, `get_bundle_edit_page_info()`, `build_membership_bundles_row()`
- `includes/Membership_CPT_Hooks.php` — `render_edit_bundle_member_page()` (~line 199)
- `includes/Utilities.php` — `delete_wicket_membership_in_mdp()`
- `includes/Membership_Controller.php` — reference: `get_members_list()` dedup pattern
- `includes/Admin_Controller.php` — reference: individual/org sync call sites
- `frontend/src/members/bundle_list.js` — row navigation
- `frontend/src/membership_bundles/` — bundle detail page components
- `../wicket-wp-base-plugin/includes/helpers/helper-unsorted.php` — where base plugin bundle API functions will be added
- `TODO.md` — tracks all outstanding items including MDP-gated frontend work
- `CURRENT_SCOPE.md` — active bundle feature scope
- `plans/PLAN-grouping-membership-bundles.md` — bundle series UUID design
