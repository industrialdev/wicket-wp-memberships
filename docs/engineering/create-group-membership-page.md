---
title: "Create Group Membership Page — Frontend Implementation Plan"
audience: [developer, agent]
source_files:
  - "frontend/src/members/group_list.js"
  - "frontend/src/membership_groups/pages/edit.js"
  - "frontend/src/membership_groups/components/GroupMembershipPage.js"
  - "frontend/src/shared/components/MembershipOwnerSection.js"
  - "frontend/src/shared/components/ModalPostSelector.js"
  - "frontend/src/shared/components/MembershipDatesSection.js"
  - "frontend/src/membership_group_configs/hooks/useGroupConfigBootstrap.js"
  - "frontend/src/shared/services/api.js"
---

# Create Group Membership Page — Frontend Implementation Plan

## Overview

This document tracks the design and implementation of the **Create Group Membership** page. This is a net-new feature — no existing "create" flow exists for group memberships. Individual and organisational memberships are always created via WooCommerce subscriptions; this is the inverse path for groups.

**Entry point:** A "Create New Group Membership" button added to the group member index page (`group_list.js`).

**Target:** A new single-page React form (not a multi-step wizard) that collects the five required inputs and submits to the REST API to create a new `Membership_Group` post.

---

## Status

### Commit 1 — `set_owner()` UUID refactor
| Step | Status |
|------|--------|
| PHP: `Membership_Group::set_owner()` accepts UUID string; update `is_owner()` | Done |
| PHP: Update `Group_Admin_Controller::update_group_change_ownership()` to call `set_owner($uuid)` directly | Done |
| PHP: Update `memberships_create_full_group()` factory — `owner_user_id` → `owner_uuid` | Done |
| Tests: Update `set_owner` / `is_owner` tests; add UUID-based cases | Done |
| Docs: Update `Membership_Group.md` and `Group_Admin_Controller.md` method signatures | Done |

### Commit 2 — `get_owner()` structured return
| Step | Status |
|------|--------|
| PHP: Add `Membership_Group::get_owner()` returning structured array | Not started |
| PHP: Simplify `Group_Admin_Controller::build_owner_field()` to use `get_owner()` | Not started |
| Tests: Add `get_owner()` tests | Not started |

### Commit 3 — `POST /group` accepts `owner_uuid`
| Step | Status |
|------|--------|
| PHP: `Membership_Group::create()` signature `owner_user_id` → `owner_uuid` | Not started |
| PHP: `POST /wicket_member/v1/group` param `owner_user_id` → `owner_uuid` | Not started |
| PHP: Disable native WP REST routes for group membership CPT | Not started |
| Tests: Update all `owner_user_id` REST test params → `owner_uuid` | Not started |

### Commit 4 — Extract `MembershipDatePicker`
| Step | Status |
|------|--------|
| React: Extract `MembershipDatePicker` single-field component from `MembershipDatesSection` | Not started |
| React: Refactor `MembershipDatesSection` to compose from `MembershipDatePicker` | Not started |

### Commit 5 — Extract `MembershipOwnerAsyncSelect`
| Step | Status |
|------|--------|
| React: Extract `MembershipOwnerAsyncSelect` component from `MembershipOwnerSection` | Not started |
| React: Refactor `MembershipOwnerSection` to compose from `MembershipOwnerAsyncSelect` | Not started |

### Commit 6 — Create Group Membership page
| Step | Status |
|------|--------|
| React: `CreateGroupMembershipPage` component + entry point | Not started |
| React: Org UUID placeholder `<TextControl>` | Not started |
| React: Form validation & submission | Not started |
| React: Redirect to group edit page on success | Not started |
| PHP: Register admin page + enqueue bundle | Not started |
| PHP: "Create Group Membership" button in `render_group_members_page()` | Not started |
| Build & integration test | Not started |

---

## Form Fields

| Field | UI Component | Source | Notes |
|-------|-------------|--------|-------|
| Name | Plain `<TextControl>` | `@wordpress/components` | Simple text input |
| Group Config | `ModalPostSelector` | `shared/components/ModalPostSelector.js` | `loadOptions` calls `apiFetch` against the group config CPT REST slug — no new API function needed (see below) |
| Organisation UUID | `<TextControl>` placeholder | TODO | Placeholder only for now; proper async org selector deferred |
| Membership Owner | `MembershipOwnerAsyncSelect` (new extracted component) | Extracted from `MembershipOwnerSection.js` | Just the `AsyncSelectWpStyled` + debounce logic — no Save button, no MDP link, no Switch To (see below) |
| Start Date | `MembershipDatePicker` (new single-field component) | Extracted from `MembershipDatesSection.js` | See refactor note below |

---

## Component Reuse Notes

### `ModalPostSelector` for Group Config — no new API function needed

`ModalPostSelector.loadOptions` accepts any async function returning `[{ value, title, ...extras }]`. The existing pattern (from `useGroupConfigBootstrap.js:68`) is to call `apiFetch` directly against a WP REST slug:

```js
loadOptions={async () => {
  const posts = await apiFetch({
    path: addQueryArgs(`${API_URL}/${groupConfigCptSlug}`, {
      _fields: "id,title,date,modified",
      status: "publish",
      per_page: -1,
    }),
  });
  return posts.map((p) => ({
    title: he.decode(p.title.rendered),
    value: p.id,
    modified: p.modified,
    published: p.date,
  }));
}}
```

**`groupConfigCptSlug` is confirmed available** — `Membership_Group_Config_CPT_Hooks.php:108` already passes it as `data-group-config-cpt-slug` on its own mount node. The new create page PHP must do the same.

No changes to `api.js` are required for this field.

### `MembershipOwnerAsyncSelect` — extract from `MembershipOwnerSection`

`MembershipOwnerSection` bundles the async select, debounce logic, a Save button, an MDP link, and a Switch To button. On a create form none of the action buttons make sense — there's no existing record to save to or switch to. The full component would render awkwardly.

**Planned refactor:** Extract a `MembershipOwnerAsyncSelect` component containing only the `AsyncSelectWpStyled` + debounce logic. `MembershipOwnerSection` is then rebuilt as `MembershipOwnerAsyncSelect` + its action buttons + alert. The create page uses `MembershipOwnerAsyncSelect` directly and stores the selected value in local form state.

**New component signature:**

```js
// shared/components/MembershipOwnerAsyncSelect.js
const MembershipOwnerAsyncSelect = ({
  value,         // { label, value } | null — controlled
  onLoadOptions, // (inputValue, callback) => void
  onChange,      // (selectedOption) => void
}) => { ... }
```

`MembershipOwnerSection` becomes `MembershipOwnerAsyncSelect` + the existing Save/MDP/SwitchTo button row. All existing callers are unaffected.

---

## `MembershipDatesSection` Refactor — Extract Single-Date Component

`MembershipDatesSection` currently renders all three date pickers (start, end, expiry) as a single composite component. We only need start date here, and forcing the other two fields to render as hidden/disabled is awkward.

**Planned refactor:** Extract a `MembershipDatePicker` single-field component from the internals of `MembershipDatesSection`, then rebuild `MembershipDatesSection` as three instances of `MembershipDatePicker`. The create page uses one instance directly.

`isoToPickerDate` and `pickerDateToIso` are already exported from `MembershipDatesSection.js` and can be reused by the new component without moving them.

**New component signature:**

```js
// shared/components/MembershipDatePicker.js
const MembershipDatePicker = ({
  name,        // e.g. "membership_starts_at" — drives the end-of-day vs start-of-day ISO conversion
  label,       // translated label string
  value,       // Date | null (controlled)
  disabled,
  onChange,    // (Date | null) => void
}) => { ... }
```

`MembershipDatesSection` becomes:

```js
const MembershipDatesSection = ({ startsAt, endsAt, expiresAt, disabled, onStartsAtChange, onEndsAtChange, onExpiresAtChange }) => (
  <Flex align="end" justify="start" gap={6} direction={["column", "row"]}>
    <FlexBlock><MembershipDatePicker name="membership_starts_at" label={__("Start Date")} value={startsAt} disabled={disabled} onChange={onStartsAtChange} /></FlexBlock>
    <FlexBlock><MembershipDatePicker name="membership_ends_at"   label={__("End Date")}   value={endsAt}   disabled={disabled} onChange={onEndsAtChange}   /></FlexBlock>
    <FlexBlock><MembershipDatePicker name="membership_expires_at" label={__("Expiration Date")} value={expiresAt} disabled={disabled} onChange={onExpiresAtChange} /></FlexBlock>
  </Flex>
);
```

All existing callers of `MembershipDatesSection` are unaffected. The create page uses `MembershipDatePicker` directly with `name="membership_starts_at"`.

---

## Organisation UUID — Placeholder Approach (v1)

No reusable org search/select component exists in the plugin or (to be confirmed) in `wicket-wp-base-plugin`. For v1, render a plain `<TextControl>` so the form is functional:

```js
<TextControl
  label={__("Organisation UUID", "wicket-memberships")}
  value={form.orgUuid}
  onChange={(val) => setForm({ ...form, orgUuid: val })}
/>
```

A proper async org selector (`MdpOrgSelector`) is a follow-up task. Before building it, check `wicket-wp-base-plugin` for an existing org search REST endpoint to avoid duplicating the MDP proxy.

---

## Architecture Approach

Follow the **modern era** pattern from `FRONTEND.md` (same as `membership_group_configs/`):

- New directory: `frontend/src/create_group_membership/`
- Entry point: `pages/create.js` — mounts `<CreateGroupMembershipPage />` to `#create_group_membership`
- Main component: `components/CreateGroupMembershipPage.js`
- Form state managed locally in the page component (no external state management needed)
- Validation inline before submit, same pattern as `GroupConfigPage.validateForm()`
- On success: redirect to the group edit page (so admin is immediately in context to add members)

### New build entry

Add `create_group_membership` as a new entry in `frontend/webpack.config.js` (or equivalent). Built output: `frontend/build/create_group_membership.js`.

---

## PHP Work Required

1. **Admin menu page** — register a new WP admin page in `Membership_CPT_Hooks.php` alongside the existing group list/edit pages, with slug `wicket_create_group_membership`. Enqueue the new JS bundle. Render:
   ```html
   <div id="create_group_membership"
        data-group-config-cpt-slug="..."
        data-list-url="...">
   </div>
   ```
   Pass `data-group-config-cpt-slug` the same way `Membership_Group_Config_CPT_Hooks.php:108` does.

2. **"Create Group Membership" button** — rendered in PHP inside `render_group_members_page()` as a standard WP admin page-title action link, above the React mount div. Pattern: `<a href="{$create_url}" class="page-title-action">...</a>`. The `$create_url` is `admin_url('admin.php?page=' . self::CREATE_GROUP_MEMBER_PAGE_SLUG)`. Add `CREATE_GROUP_MEMBER_PAGE_SLUG` as a new constant on the class alongside the existing `LIST_GROUP_MEMBER_PAGE_SLUG` and `EDIT_GROUP_MEMBER_PAGE_SLUG`. Do not add the button inside `group_list.js`.

3. **REST endpoint for creation** — `POST /wicket_member/v1/group` **already exists** in `Membership_Group_WP_REST_Controller.php:229`. The handler at line 369 calls `Membership_Group::create()` with these params:
   - `name` (string)
   - `membership_group_config_id` (int)
   - `org_uuid` (string)
   - `owner_user_id` (int) — **note: WP user ID, not UUID**
   - `start_date` (ISO 8601 string)

   No new endpoint needed.

4. **Disable native WP REST create for the group membership CPT** — the group membership CPT has `show_in_rest => true` (via `Membership_Post_Types.php`), which exposes a native `POST /wp/v2/{cptSlug}` create route. This should be disabled so all creation goes through the dedicated endpoint. Use the `rest_endpoints` filter to remove it:
   ```php
   add_filter( 'rest_endpoints', function( $endpoints ) {
     $slug = Helper::get_membership_group_cpt_slug();
     unset( $endpoints[ '/wp/v2/' . $slug ] );     // removes create (and list/batch on that route)
     return $endpoints;
   });
   ```
   Add this in `Membership_Group_WP_REST_Controller.php` or `Membership_Post_Types.php`. Confirm the individual GET/edit routes (`/wp/v2/{slug}/{id}`) are not needed before removing the entire base route.

---

## React Component Breakdown

```
CreateGroupMembershipPage
├── Page heading + back-to-list link
├── Error boundary (same pattern as GroupMembershipPage.js)
├── <form>
│   ├── Name — <TextControl>
│   ├── Group Config — <ModalPostSelector> (loadOptions → apiFetch group config CPT)
│   ├── Org UUID — <TextControl> placeholder (TODO: replace with MdpOrgSelector)
│   ├── Membership Owner — <MembershipOwnerAsyncSelect> (onLoadOptions → fetchMdpPersons)
│   └── Start Date — <MembershipDatePicker name="membership_starts_at">
├── Inline validation errors
└── Submit button + loading state
```

---

## Comparison to CURRENT_SCOPE.md

`CURRENT_SCOPE.md` defines the "Admin > Create Membership Group" feature at lines 80–128. Comparing against our plan:

| Scope item | Our plan | Gap |
|------------|----------|-----|
| Name field | `<TextControl>` ✓ | None |
| Membership Group Config dropdown | `ModalPostSelector` ✓ | None |
| Organisation — searchable lookup (name or ID), shows org name, city, state, postal | `<TextControl>` placeholder | **Gap** — scope requires a rich MDP org search; placeholder deferred by decision |
| Membership Owner — searchable lookup (name, email, ID), shows full name + email | `MembershipOwnerAsyncSelect` via `fetchMdpPersons` ✓ | Minor: confirm `fetchMdpPersons` response surfaces email for display in the dropdown |
| Start date | `MembershipDatePicker` ✓ | None |
| On save: post status = `Pending` | Handled by `Membership_Group::create()` ✓ | None |
| On save: end date + expiry calculated from config | Handled by `Membership_Group::create()` ✓ | None |
| On save: **WooCommerce subscription created** | **Not in plan** | **Gap** — scope requires a subscription to be created at group creation time. Needs to be part of the `POST /group` endpoint's server-side logic or a follow-up immediately after |
| On save: admin prompted to add members | **Not in plan** | **Gap** — scope says admin is prompted after creation. Could be as simple as a redirect to the group edit page, which already has the member management UI |

**Action items from scope comparison:**

1. **Subscription creation on save** — scope requires a WooCommerce subscription to be created when the group is saved. Not yet confirmed whether `Membership_Group::create()` handles this. Deferred — tracked in TODO.md.

2. **Post-creation redirect** — redirect to the group edit page (not the list page) so the admin is immediately in context to add members. In scope for this feature.

3. **Org selector** — the placeholder `<TextControl>` is a known deferral and intentional. The full spec (search by name/ID, display city/state/postal) requires a new MDP org search component and REST proxy endpoint. Tracked as a follow-up.

---

## Resolved Questions

1. **Native WP REST routes** — confirmed safe to remove. No frontend code calls `/wp/v2/{groupMembershipCptSlug}`. Disable via `rest_endpoints` filter in `Membership_Group_WP_REST_Controller::__construct()`, removing both the collection and single-item routes. Add a QA test confirming neither route is registered.

2. **Post-submit redirect** — on success, redirect to the group edit page: `admin_url('admin.php?page=' . EDIT_GROUP_MEMBER_PAGE_SLUG) . '?id=' . $newGroupPostId`. The new group `post_id` is returned in the REST response. Pass `data-edit-group-url` on the mount node, same pattern as the list page already uses for `data-edit-group-url`.

3. **Permissions** — use `edit_posts`, matching all existing group membership submenu pages in `Membership_CPT_Hooks.php`. (`manage_options` via `WICKET_MEMBERSHIPS_CAPABILITY` is reserved for the top-level menu and group config pages.)

---

## Prerequisite: Owner UUID Improvements

Before the create page owner field can be wired up, the owner API surface on `Membership_Group` and the REST layer needs to be made UUID-first. This is broken into discrete improvements below. Each is a self-contained change that can be reviewed and merged independently.

---

### Improvement 1 — `Membership_Group::set_owner()` accepts a UUID string

**Current:** `set_owner(int $user_id)` — callers must pre-resolve a UUID to a WP user ID.

**Change:** Replace the signature with `set_owner(string $uuid): int|false`. Add an `isValidUuid($uuid)` guard first (same pattern as `set_organization()`), then call `wicket_create_wp_user_if_not_exist($uuid)` to get or create the WP user, then proceed with the existing internal logic (validate user, store `user_id` meta, sync `post_author`, reassign order/subscription customer).

`wicket_create_wp_user_if_not_exist` (base plugin `helper-unsorted.php:71`) is confirmed safe to rely on: it returns `false` on an empty UUID, returns the existing WP user ID if the `user_login` or email already exists, and calls `wicket_get_person_by_id($uuid)` to fetch MDP data before creating a new user. If the MDP call fails (e.g. invalid UUID that doesn't exist in MDP), `wicket_get_person_by_id` will return a falsy value and `wp_insert_user` will fail, returning `false`. The `isValidUuid` guard before the call catches malformed UUIDs cheaply without a network round-trip.

```php
public function set_owner( string $uuid ): int|false {
    if ( ! isValidUuid( $uuid ) ) {
        Wicket()->log()->error( 'Membership_Group: Invalid owner UUID', [...] );
        return false;
    }
    $user_id = wicket_create_wp_user_if_not_exist( $uuid );
    if ( ! $user_id ) {
        return false;
    }
    $user = get_user_by( 'id', $user_id );
    if ( ! $user ) {
        return false;
    }
    // ... existing meta/post_author/order/subscription logic unchanged ...
}
```

All callers of `set_owner` must be updated to pass a UUID string. Known callers:
- `Membership_Group::create()` — currently passes `$owner_user_id` (int); updated in Improvement 3 to pass `$owner_uuid` (string). The early guard at `create():83` (`get_user_by('id', $owner_user_id)`) is removed — that validation moves inside `set_owner` via `wicket_create_wp_user_if_not_exist`. The rollback-on-failure path (`wp_delete_post`) is unaffected since `set_owner` returning false still triggers it.
- `Group_Admin_Controller::update_group_change_ownership()` — already has the UUID at line 787; currently calls `wicket_create_wp_user_if_not_exist` itself then passes the user ID. Simplify: call `set_owner($uuid)` directly and remove the manual resolution.
- QA tests in `membership-group.pest.php` and `membership-group-rest-controller.pest.php` that call `$group->set_owner($user_id)` directly.

**`is_owner()` — update to UUID as well:** Currently `is_owner(int $user_id): bool`. Change to `is_owner(string $uuid): bool`, deriving the user ID internally via `get_user_by('login', $uuid)` for the comparison. All callers updated accordingly.

**`memberships_create_full_group()` factory — must be updated:** The factory in `qa/tests/WordPress/Memberships/support/factories.php` accepts `owner_user_id` (int), creates a WP user, and passes the ID to `Membership_Group::create()`. After this change, the factory should accept `owner_uuid` (string) instead, create the WP user with that string as `user_login`, and pass the UUID to `create()`. The user creation block becomes:
```php
if (empty($args['owner_uuid'])) {
    $uuid = 'test-owner-uuid-' . uniqid();
    wp_insert_user(['user_login' => $uuid, 'user_email' => ...]);
    $owner_uuid = $uuid;
} else {
    $owner_uuid = (string) $args['owner_uuid'];
}
```

**Tests to update (QA suite — `membership-group.pest.php`):**
- `set_owner` tests: update to pass a UUID string instead of int
- `is_owner` tests: update to pass a UUID string
- Add: `set_owner` with a UUID that has no WP user calls `wicket_create_wp_user_if_not_exist` and stores the result
- Add: `set_owner` with an invalid/empty UUID returns false

---

### Improvement 2 — `Membership_Group::get_owner()` returns a structured object

**Current:** `get_owner_id()` returns an int, `get_owner_uuid()` returns a string. Callers that need both must call both and assemble the data themselves (e.g. `Group_Admin_Controller::build_owner_field()` does this manually).

**Change:** Add `get_owner(): array|false` that returns a single structured array, eliminating the need for callers to assemble owner data manually:

```php
/**
 * @return array{user_id: int, uuid: string, display_name: string, email: string}|false
 */
public function get_owner(): array|false {
    $user_id = $this->get_owner_id();
    if ( ! $user_id ) {
        return false;
    }
    $user = get_user_by( 'id', $user_id );
    if ( ! $user ) {
        return false;
    }
    return [
        'user_id'      => $user->ID,
        'uuid'         => $user->user_login,
        'display_name' => $user->display_name,
        'email'        => $user->user_email,
    ];
}
```

`get_owner_id()` and `get_owner_uuid()` stay in place — they remain valid single-purpose accessors. `Group_Admin_Controller::build_owner_field()` (line 382) currently calls `get_owner_id()`, then `get_user_by`, then `get_owner_uuid()` manually. After this change it calls `get_owner()` and reads directly from the returned array, removing ~10 lines of assembly logic.

**Tests to add (QA suite — `membership-group.pest.php`):**
- `get_owner()` returns the correct structured array when an owner is set
- `get_owner()` returns false when no owner is set
- `get_owner()` returns false when the stored user ID no longer resolves to a WP user

---

### Improvement 3 — `POST /wicket_member/v1/group` accepts `owner_uuid` instead of `owner_user_id`

**Current:** The create endpoint takes `owner_user_id` (int). The frontend has no way to supply a WP user ID — `fetchMdpPersons` returns MDP UUIDs. The `change_owner` endpoint already takes `new_owner_uuid` (string) and calls `wicket_create_wp_user_if_not_exist`. The create endpoint is inconsistent with this.

**Change:** In `Membership_Group_WP_REST_Controller`, replace the `owner_user_id` arg with `owner_uuid` (string). In `create_group_membership()`, pass the UUID through to `Membership_Group::create()`, which passes it to `set_owner()` (now UUID-native from Improvement 1). Update `Membership_Group::create()` signature to take `string $owner_uuid` instead of `int $owner_user_id`.

Endpoint body becomes:
```
{ name, membership_group_config_id, org_uuid, owner_uuid, start_date }
```

**Tests to update (QA suite — `membership-group-rest-controller.pest.php`):**
- All `set_param('owner_user_id', ...)` calls → `set_param('owner_uuid', ...)`
- All test setup that creates a WP user and passes its ID: update to pass the user's `user_login` (UUID) instead
- Error case at line 378 (`'owner_user_id does not exist.'`) → update param and expected error message to match the new validation
- Add: passing a valid UUID string resolves or creates a WP user and creates the group successfully

**Frontend impact:** `CreateGroupMembershipPage` passes `selectedOption.value` (UUID) directly as `owner_uuid` — no additional lookup step needed. Matches the edit flow exactly.

---

## Files to Create / Modify

| Action | Path | Notes |
|--------|------|-------|
| Create | `frontend/src/create_group_membership/pages/create.js` | Entry point, mounts to `#create_group_membership` |
| Create | `frontend/src/create_group_membership/components/CreateGroupMembershipPage.js` | Main page component |
| Create | `frontend/src/shared/components/MembershipDatePicker.js` | Extracted from `MembershipDatesSection` |
| Create | `frontend/src/shared/components/MembershipOwnerAsyncSelect.js` | Extracted from `MembershipOwnerSection` |
| Modify | `frontend/src/shared/components/MembershipDatesSection.js` | Refactor to compose from `MembershipDatePicker` |
| Modify | `frontend/src/shared/components/MembershipOwnerSection.js` | Refactor to compose from `MembershipOwnerAsyncSelect` |
| Modify | `frontend/webpack.config.js` (or equivalent) | Add `create_group_membership` entry point |
| Modify | `includes/Membership_CPT_Hooks.php` | Register new admin page, enqueue bundle, add Create button to list page |
| Modify | `includes/Membership_Group_WP_REST_Controller.php` | Disable native WP REST routes for group membership CPT via `rest_endpoints` filter; add QA test confirming routes absent |
| Modify | `includes/Membership_Group.php` | Change `set_owner()` to accept UUID string; add `get_owner()` (Improvements 1 & 2) |
| Modify | `includes/Membership_Group_WP_REST_Controller.php` | Replace `owner_user_id` with `owner_uuid` in create endpoint (Improvement 3) |
| Modify | `includes/Group_Admin_Controller.php` | Update `build_owner_field()` to use `get_owner()` |
| Modify | `qa/tests/WordPress/Memberships/membership-group-rest-controller.pest.php` | Update `owner_user_id` → `owner_uuid` in all create tests; add new owner UUID tests |
| Modify | `qa/tests/WordPress/Memberships/membership-group.pest.php` | Update `set_owner`/`is_owner` tests to pass UUID; add `get_owner()` tests |
| Modify | `qa/tests/WordPress/Memberships/support/factories.php` | Update `memberships_create_full_group()`: `owner_user_id` → `owner_uuid` |
