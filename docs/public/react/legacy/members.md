---
title: Members (Legacy)
---

# Members (Legacy)

::: warning Pending rework
These files are monolithic legacy components scheduled for refactoring into the component-based architecture used by the Membership Bundles feature. Do not use these as a pattern for new development — refer to the [Modern Component Architecture](../modern/) section instead.
:::

::: tip Modern equivalent
The component-based pattern to follow when refactoring these files is documented in [Modern Component Architecture](../modern/). The shared components that should replace inline rendering are in [Shared Components](../modern/components/).
:::

## PHP Integration

### members/index.js — Individual Members list

The admin controller (`Membership_CPT_Hooks`) enqueues `wicket-memberships_member_list` (`frontend/build/member_list.js`) and renders:

```html
<!-- Individual members page -->
<div
  id="member_list"
  data-edit-member-url="https://example.com/wp-admin/admin.php?page=wicket_individual_member_edit"
  data-member-type="individual"
  data-filter-bundle-id="42"
  data-filter-tier-uuid="abc-123-..."
  data-bundles-enabled="1"
></div>

<!-- Organisation members page -->
<div
  id="member_list"
  data-edit-member-url="https://example.com/wp-admin/admin.php?page=wicket_org_member_edit"
  data-member-type="organization"
></div>
```

| Attribute | Source | Description |
|---|---|---|
| `data-edit-member-url` | `admin_url('admin.php?page=wicket_individual_member_edit')` or `wicket_org_member_edit` | Base URL for the member edit page; `id` query arg is appended per row |
| `data-member-type` | Hard-coded per render function | `individual` or `organization` |
| `data-filter-bundle-id` | `intval($_GET['filter_bundle_id'])` | Pre-selects bundle filter; only present on the individual page |
| `data-filter-tier-uuid` | `sanitize_text_field($_GET['filter_tier_uuid'])` | Pre-selects tier filter; only present on the individual page |
| `data-bundles-enabled` | `$_ENV['WICKET_MSHIP_ENABLE_BUNDLES']` | `"1"` when bundles feature flag is on; only present on the individual page |

### members/edit.js — Member edit page

The admin controller enqueues `wicket-memberships_edit_member` (`frontend/build/member_edit.js`) and renders:

```html
<!-- Individual member edit -->
<div
  id="edit_member"
  data-record-id="123"
  data-membership-uuid="abc-def-..."
  data-member-type="individual"
></div>

<!-- Organisation member edit -->
<div
  id="edit_member"
  data-record-id="org-uuid-..."
  data-membership-uuid="abc-def-..."
  data-member-type="organization"
></div>
```

| Attribute | Source | Description |
|---|---|---|
| `data-record-id` | Individual: `$user->ID` looked up from `$_GET['id']` (UUID → WP user ID). Org: raw `$_GET['id']` (org UUID) | Identifier passed to all API fetch calls |
| `data-membership-uuid` | `$_GET['membership_uuid']` | If set, the matching membership row is expanded on mount; optional |
| `data-member-type` | Hard-coded per render function | `individual` or `organization` |

### Global JS object (`wicketMembershipsSettings`)

Both pages also have access to the `wicketMembershipsSettings` global injected by `Admin_Controller::admin_footer_scripts()` into the admin footer. React code accesses it as `PLUGIN_SETTINGS` (re-exported from `shared/constants.js`):

| Key | Source | Description |
|---|---|---|
| `WICKET_MSHIP_MERGE_TOOLS` | `$_ENV['WICKET_MSHIP_MERGE_TOOLS']` | Feature flag for the merge-tools UI |
| `WICKET_MSHIP_MULTI_TIER_RENEWALS` | `$_ENV['WICKET_MSHIP_MULTI_TIER_RENEWALS']` | Enables multi-tier renewal option |
| `adminUrl` | `admin_url()` | WordPress admin base URL |
| `WICKET_MSHIP_MDP_TIMEZONE` | `$_ENV['WICKET_MSHIP_MDP_TIMEZONE']` (falls back to `UTC`) | Timezone used for date display throughout the admin UI |

---

## members/index.js — Member List (642 lines)

Mounts on `#member_list`. Renders the WordPress-style admin list table for either individual or organisation members, depending on the `memberType` dataset prop.

### What it renders

- A page heading (`Individual Members` or `Organisation Members`)
- Status tab bar: All / Pending / Grace Period, each with a live count
- A search box (submits on Enter)
- A filter bar with React Select dropdowns for status, tier, and (for individual members) bundle
- A sortable `wp-list-table` with pagination via the shared `Pagination` component

### Dataset props (read from the PHP-inserted DOM element)

| Name | Type | Required | Description |
|---|---|---|---|
| `memberType` | `string` | Yes | `individual` or `organization` — controls columns, labels, and filter visibility |
| `editMemberUrl` | `string` | Yes | Base URL for the edit-member page; query arg `id` is appended per row |
| `filterBundleId` | `string` | No | Pre-selects the bundle filter dropdown on mount |
| `filterTierUuid` | `string` | No | Pre-selects the tier filter dropdown on mount |
| `bundlesEnabled` | `string` | No | `"1"` to show the Bundles column (individual members only) |

### Fetch / filter / sort / pagination pattern

1. On mount, three requests fire in parallel: `fetchMembers`, `fetchMembershipFilters`, and three tab-count fetches (`all`, `pending`, `grace_period`).
2. `fetchMembers` returns `{ results, count }`. The component derives `totalPages` from `count / posts_per_page` and auto-corrects to page 1 if the stored page exceeds the new total.
3. After the first members response, unique tier UUIDs are collected from `all_membership_tiers` on each member and passed to `fetchTiersInfo` to resolve display names.
4. Filter, sort, and tab clicks update `searchParams` and immediately call `fetchMembers` with the new params — there is no debounce.
5. The URL `paged` query arg is kept in sync via `updatePageInUrl` from `shared/utils/pagination`.

### Key state variables

| Variable | Purpose |
|---|---|
| `members` | Current page of member objects from the API |
| `tiersInfo` | Tier metadata keyed by UUID, used to resolve tier names in the table |
| `membershipFilters` | Available filter options (statuses, tiers, bundles) |
| `searchParams` | The current API request parameters (page, sort, filters) |
| `tempSearchParams` | Staging area for the search text field (applied on submit) |
| `tempStatus`, `tempTier`, `tempGroup` | Staging area for the three filter dropdowns (applied on filter submit) |
| `activeTab` | Which status tab is highlighted (`all`, `pending`, `grace_period`) |
| `tabCounts` | Counts displayed in the tab bar |

:::details Example dataset attributes set by PHP
```html
<div
  id="member_list"
  data-member-type="individual"
  data-edit-member-url="https://example.com/wp-admin/admin.php?page=wicket-memberships-member-edit"
  data-bundles-enabled="1"
></div>
```
:::

---

## members/edit.js — Member Editor (1114 lines)

Mounts on `#edit_member`. Renders the full edit page for a single member (individual or organisation), showing all their membership records in a collapsible table.

### What it renders

- Member name heading with "Switch to" and "View in MDP" buttons
- A `RecordTopInfo` bar showing email/location and identifying number
- A collapsible `wp-list-table` of membership records, one row per `wicket_mship` post
- Per-record expanded panel containing:
  - **Billing info** — linked subscription and order table (if present)
  - **Manage Status** action (opens `ManageStatusModal`)
  - **Actions dropdown** — "Create Renewal Order" and "Add to Membership Bundle"
  - **`ManageMembership`** — "Manage Membership" button opening a modal with Transfer and Switch actions (see below)
  - **Edit form** — start/end/expiry date pickers, renewal type selector, conditional fields (sequential tier, form page), seats info and membership-owner selector (organisation only)
  - **Update Membership** submit button
- For bundle memberships: `MembershipBundleDetails` replaces the edit form
- `ManageStatusModal` rendered outside the table at the page level

### Fetches on mount

All four fetches run in parallel from a single `useEffect`:

| Call | Purpose |
|---|---|
| `fetchMemberInfo(recordId)` | Member metadata (display name, org name, email/location, MDP link, `switch_to_url`) |
| `fetchMemberships(recordId)` | All `wicket_mship` posts for this member |
| `apiFetch` on `/wp/v2/pages` | WP page options for the "Form Page" renewal selector |
| `fetchTiers()` | Local WP tier post options for the "Sequential Tier" renewal selector |

### Key state variables

| Variable | Purpose |
|---|---|
| `memberInfo` | Top-level member metadata returned by `fetchMemberInfo` |
| `memberships` | Array of membership records; each item carries extra UI state (`showRow`, `updatingNow`, `updateResult`, `isRenewalModalOpen`, `isAddToBundleOpen`) |
| `wpPagesOptions` | Options for the form-page selector |
| `wpTierOptions` | Options for the sequential-tier selector |
| `membershipOwnerOptions` | Options for the org membership-owner async select |
| `isManageStatusModalOpen` / `manageStatusTarget` | Controls `ManageStatusModal` |
| `pageNotices` | Stack of dismissible admin notices (e.g. "member removed from bundle") |

### Dataset props

| Name | Type | Required | Description |
|---|---|---|---|
| `memberType` | `string` | Yes | `individual` or `organization` |
| `recordId` | `string` | Yes | WP user login (individual) or org UUID (organisation) passed to all fetch calls |
| `membershipUuid` | `string` | No | If set, the matching membership row is expanded on mount |

### Date handling

All date fields (`membership_starts_at`, `membership_ends_at`, `membership_expires_at`) store full UTC ISO 8601 strings in state. The `react-datepicker` component receives a local `Date` object (converted to MDP timezone via `moment-timezone`). On change, dates are converted back to UTC ISO strings, respecting start-of-day for start dates and end-of-day for end/expiry dates.

---

## members/bundle_list.js — Bundle Member List (263 lines)

Mounts on `#bundle_member_list`. Renders the admin list table of membership bundles. This is displayed on the Membership Bundles list page (only visible when `WICKET_MSHIP_ENABLE_BUNDLES` is on).

### What it renders

- Search box
- Status filter dropdown
- Sortable table: Bundle Name, Org Name, Owner (email), Bundle Status, Last Updated, Link to MDP
- Pagination

### Dataset props

| Name | Type | Required | Description |
|---|---|---|---|
| `editBundleUrl` | `string` | Yes | Base URL for the bundle edit page; query arg `id` is appended per row |

### Fetches on mount

- `fetchMembershipBundles(searchParams)` — paged list of bundles
- `fetchMembershipBundleFilters()` — status options for the filter dropdown

The sort/filter/paginate pattern is identical to `members/index.js`.

### Key state variables

| Variable | Purpose |
|---|---|
| `bundles` | Current page of bundle objects |
| `bundleFilters` | Available status options for the filter dropdown |
| `searchParams` | Active API request params |
| `tempSearch` / `tempStatus` | Staging values for search and status filter |

---

## members/create_renewal_order.js — Renewal Order Modal (439 lines)

Not mounted directly. Exports two named exports and one default export used by `members/edit.js`.

### Exports

**`CreateRenewalOrderModal` (named export)**

The modal component itself. Controlled by the parent via `isOpen` / `onClose`. Fetches WooCommerce products the first time it opens and conditionally shows a variation selector for `variable-subscription` products. Calls `createRenewalOrder(membershipId, productId, variationId)` on submit and shows a success notice with a link to the created order.

| Prop | Type | Required | Description |
|---|---|---|---|
| `membership` | `object` | Yes | The membership record object; uses `membership.ID` when calling the API |
| `isOpen` | `boolean` | Yes | Whether the modal is visible |
| `onClose` | `function` | Yes | Called when the modal should close |

**`getRenewalOrderAction` (named export)**

Returns an action descriptor `{ label, disabled, onClick }` for use with `MembershipActionsDropdown`. The `disabled` condition mirrors the button in the legacy standalone component: the action is disabled unless `membership_status_slug` is `active`, `grace_period`, or `delayed`.

**`CreateRenewalOrder` (default export)**

The original standalone component that renders a `BorderedBox` with a trigger button plus the modal. Kept for backward compatibility. New usage should prefer `MembershipActionsDropdown` + `CreateRenewalOrderModal`.

### API called

`POST /wicket-memberships/v1/create-renewal-order` via the `createRenewalOrder` service function, passing `membership_post_id`, `product_id`, and optionally `variation_id`.

---

## members/manage_membership.js — Manage Membership Modal

Not mounted directly. Default export used by `members/edit.js` inside each membership record's expanded panel.

### What it renders

A "Manage Membership" button inside a `BorderedBox`. Clicking it validates eligibility before opening the modal:

- Blocked (shows a warning modal instead) unless the membership's `membership_starts_at` is in the past **and** `membership_status` is `active` (case-insensitive check against `'active'`/`'Active'`).
- When eligible, opens a `Modal` with a "Select Action" dropdown: **Transfer Membership** or **Switch Membership**.
- **Transfer**: async-select to search an MDP person (`/mdp_person/search`, min 3 characters), a two-step confirm, then calls `transferMembership`. On success with a `redirect_url`, opens it in a new tab and reloads the page.
- **Switch**: renders `SwitchMembership` (see below) inline.

### Props

| Name | Type | Required | Description |
|---|---|---|---|
| `membership` | `object` | Yes | The membership record object; uses `membership.data.membership_post_id`, `membership.data.membership_starts_at`, `membership.data.membership_status` |

### API called

`transferMembership({ new_owner_uuid, membership_post_id })` from `shared/services/api.js`.

---

## members/switch_membership.js — Switch Membership Form

Not mounted directly. Default export rendered by `manage_membership.js` when the "Switch Membership" action is selected.

### What it renders

A "Switch Tier Action" dropdown with a single visible option, **Create Membership** (a "Create Order" option exists in code but is intentionally hidden — tier-only switch; see the `SWITCH_INTEGRATION_MIGRATE_IMPLEMENTATION.md` code comment before removing it). Selecting **Create Membership** shows a searchable tier select, filtered to tiers matching the current membership's type (individual/organization) so a membership cannot be switched cross-type. Submitting calls `switchMembership(membership.ID, tierPostId, 'tier')`; on a response with `redirect_url`, navigates the browser there.

### Props

| Name | Type | Required | Description |
|---|---|---|---|
| `membership` | `object` | Yes | The membership record object; uses `membership.ID` and `membership.data.membership_type` |

### API called

`fetchTiers`, `fetchWcProducts`, `fetchProductVariations`, and `switchMembership` from `shared/services/api.js`.

---

## Extracted modal components

The following files were split out of `members/edit.js`. Each follows a controlled pattern: the parent holds open state and passes a close/success callback.

**`ManageStatusModal`**

Opens from the "Manage Status" button on a membership record row. Allows the admin to change the membership status with optional date overrides. On success, calls `onStatusUpdated(postId, newStatus, responseData)` so the parent can patch the `memberships` array without re-fetching.

**`AddToMembershipBundleModal`**

Allows adding an existing individual membership to a bundle. Accepts `membershipPostId` and `tierPostId`. On success, calls `onSuccess()` which triggers a full re-fetch of memberships in the parent.

**`MoveToMembershipBundleModal`**

Allows moving a member from their current bundle to a different bundle. Accepts `membershipPostId`. On success calls `onSuccess()`.

**`RemoveFromMembershipBundleModal`**

Allows removing a member from their bundle. Accepts `membershipPostId`. On success calls `onSuccess()`.

**`MembershipBundleDetails`**

Renders the expanded row panel for a membership that belongs to a bundle. Shows bundle-specific details and bundle member management actions. Replaces the standard edit form when `membership.is_membership_bundle` is true. Accepts `membership` and an `onSuccess(message)` callback used to add a page-level notice and re-fetch memberships.

**`MembershipBundleBadge`**

A small inline label component that renders a badge indicating which bundle a membership belongs to. Accepts `bundleName`. Rendered next to the tier name in the membership records table.
