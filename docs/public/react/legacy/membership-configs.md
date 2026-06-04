---
title: Membership Configs (Legacy)
---

# Membership Configs (Legacy)

::: warning Pending rework
These files are monolithic legacy components scheduled for refactoring into the component-based architecture used by the Membership Bundles feature. Do not use these as a pattern for new development — refer to the [Modern Component Architecture](../modern/) section instead.
:::

::: tip Modern equivalent
The component-based pattern to follow when refactoring these files is documented in [Modern Component Architecture](../modern/). The shared components that should replace inline rendering are in [Shared Components](../modern/components/).
:::

## PHP Integration

The admin controller (`Membership_Config_CPT_Hooks`) enqueues `wicket-memberships_membership_config_create` (`frontend/build/membership_config_create.js`) on the page `admin_page_wicket_membership_config_edit` and renders:

```html
<div
  id="create_membership_config"
  data-config-cpt-slug="wicket_mship_cfg"
  data-tier-cpt-slug="wicket_mship_tier"
  data-config-list-url="https://example.com/wp-admin/edit.php?post_type=wicket_mship_cfg"
  data-tier-list-url="https://example.com/wp-admin/edit.php?post_type=wicket_mship_tier"
  data-tier-mdp-uuids="uuid-1,uuid-2"
  data-language-codes="en,fr"
  data-post-id="456"
></div>
```

| Attribute | Source | Description |
|---|---|---|
| `data-config-cpt-slug` | `Helper::get_membership_config_cpt_slug()` | WP REST API post-type slug for membership configs |
| `data-tier-cpt-slug` | `Helper::get_membership_tier_cpt_slug()` | WP REST API post-type slug for membership tiers |
| `data-config-list-url` | `admin_url('edit.php?post_type=<config_slug>')` | Redirect target after a successful save |
| `data-tier-list-url` | `admin_url('edit.php?post_type=<tier_slug>')` | URL passed to the connected-tiers sub-component's "View All" link |
| `data-tier-mdp-uuids` | `Membership_Tier::get_tier_uuids_by_config_id($post_id)` imploded as CSV | Comma-separated MDP tier UUIDs already linked to this config; empty string in create mode |
| `data-language-codes` | `Helper::get_wp_languages_iso()` imploded as CSV | Active WP site languages; drives locale tabs in callout modals |
| `data-post-id` | `$_GET['post_id']` | WP post ID of the config being edited; absent (empty string) in create mode |

The admin URL for editing an existing config is `admin.php?page=wicket_membership_config_edit&post_id=<ID>`. WordPress `post.php?action=edit` and `post-new.php` for this CPT are intercepted by `create_edit_page_redirects()` and 302-redirected to this page automatically.

### Global JS object (`wicketMembershipsSettings`)

The page also has access to the `wicketMembershipsSettings` global injected by `Admin_Controller::admin_footer_scripts()`. React code accesses it as `PLUGIN_SETTINGS` (re-exported from `shared/constants.js`):

| Key | Source | Description |
|---|---|---|
| `WICKET_MSHIP_MERGE_TOOLS` | `$_ENV['WICKET_MSHIP_MERGE_TOOLS']` | Feature flag for the merge-tools UI |
| `WICKET_MSHIP_MULTI_TIER_RENEWALS` | `$_ENV['WICKET_MSHIP_MULTI_TIER_RENEWALS']` | When truthy, shows the "Multi-Tier Renewal" checkbox in the config form |
| `adminUrl` | `admin_url()` | WordPress admin base URL |
| `WICKET_MSHIP_MDP_TIMEZONE` | `$_ENV['WICKET_MSHIP_MDP_TIMEZONE']` (falls back to `UTC`) | Timezone used for date display |

---

## membership_configs/edit.js — Config Editor (1330 lines)

Mounts on `#create_membership_config`. Handles both create and edit modes for a `wicket_mship_cfg` post. The component is named `CreateMembershipConfig` internally but serves as the edit page too — the `postId` dataset prop distinguishes the two modes.

### What it renders

- Page heading ("Add New Membership Configuration" or "Edit Membership Configuration")
- Validation error notices
- **General settings** — config name text field; optional "Multi-Tier Renewal" checkbox (visible only when `PLUGIN_SETTINGS.WICKET_MSHIP_MULTI_TIER_RENEWALS` is truthy)
- **Renewal Window** — day count input and a "Callout Configuration" button that opens a locale-aware modal for the renewal callout copy (header, content, button label per language code)
- **Grace Period Window** — day count input, optional late-fee WooCommerce product selector, and a "Callout Configuration" button for the grace period callout copy
- **Cycle** — a dropdown to choose `calendar` or `anniversary`
  - Anniversary mode: period count + period type (year/month/week) and an optional "Align End Dates" checkbox with an align-by selector
  - Calendar mode: a seasons table with add/edit/archive season capability via a modal
- **Connected Membership Tiers** — rendered by `MembershipConfigTiers` (see below), visible only when `tierMdpUuids` is non-empty
- Save button

### Modals

| Modal | Trigger | Purpose |
|---|---|---|
| Renewal Window Callout | "Callout Configuration" next to renewal window | Edit per-locale callout header/content/button label for the renewal window |
| Grace Period Window Callout | "Callout Configuration" next to grace period window | Edit per-locale callout copy for the grace period window |
| Season (Add/Edit) | "Add Season" button or edit icon on a season row | Create or update a calendar season (name, active flag, start date, end date) |

Callout modals use a separate `tempForm` staging state. Changes are only written back to `form` when the modal form is submitted, allowing the user to cancel without losing the committed data.

### Dataset props

| Name | Type | Required | Description |
|---|---|---|---|
| `configCptSlug` | `string` | Yes | WP REST API post-type slug for membership configs (e.g. `wicket_mship_cfg`) |
| `configListUrl` | `string` | Yes | URL to redirect to after a successful save |
| `tierListUrl` | `string` | Yes | URL passed to `MembershipConfigTiers` for the "View All" link |
| `tierCptSlug` | `string` | Yes | WP REST API post-type slug for membership tiers |
| `postId` | `string` | No | WP post ID of the config being edited; absent means create mode |
| `tierMdpUuids` | `string` | No | Comma-separated list of MDP tier UUIDs already linked to this config |
| `languageCodes` | `string` | Yes | Comma-separated list of language codes (e.g. `en,fr`); drives locale tabs in callout modals |

### Fetches on mount

| Request | Purpose |
|---|---|
| `apiFetch` on `/${configCptSlug}/${postId}` | Load existing config data in edit mode (skipped in create mode) |
| `fetchWcProducts` | Populate the late-fee product selector |

On save, a `POST` is made to `/${configCptSlug}` (create) or `/${configCptSlug}/${postId}` (update) via `apiFetch`. On success the browser is redirected to `configListUrl`.

### Key state variables

| Variable | Purpose |
|---|---|
| `form` | The committed form data: `name`, `renewal_window_data`, `late_fee_window_data`, `cycle_data`, `multi_tier_renewal` |
| `tempForm` | Staging copy of `form` used by the callout modals so edits can be cancelled |
| `tempSeason` | In-progress season being added or edited in the season modal |
| `currentSeasonIndex` | Index into `form.cycle_data.calendar_items` being edited; `null` means new season |
| `wcProductOptions` | WooCommerce product options for the late-fee product selector |
| `errors` | Validation/API errors shown at the top of the form |
| `seasonErrors` | Field-level errors for the season modal |
| `isSubmitting` | Busy state for the save button |
| `currentRenewalWindowDataLocale` / `currentLateFeeWindowDataLocale` | Active language tab inside each callout modal |

### Form data shape

```js
{
  name: '',
  multi_tier_renewal: false,
  renewal_window_data: {
    days_count: '1',
    locales: {
      en: { callout_header: '', callout_content: '', callout_button_label: '' }
    }
  },
  late_fee_window_data: {
    days_count: '0',
    product_id: '-1',
    locales: { /* same shape as renewal_window_data.locales */ }
  },
  cycle_data: {
    cycle_type: 'calendar', // or 'anniversary'
    anniversary_data: {
      period_count: '1',
      period_type: 'year', // 'year' | 'month' | 'week'
      align_end_dates_enabled: false,
      align_end_dates_type: 'first-day-of-month'
    },
    calendar_items: [
      { season_name: '', active: true, start_date: '', end_date: '' }
    ]
  }
}
```

---

## membership_configs/tiers.js — Connected Tiers Sub-component (181 lines)

Not mounted directly. Rendered inside `CreateMembershipConfig` when `tierMdpUuids` is non-empty. Exports `MembershipConfigTiers` as the default export.

### What it does

Fetches the MDP tier metadata for the UUIDs already linked to the config and displays a read-only summary table: tier name, active/inactive status, type (individual/organisation), category, and member count. A "View All" link opens `tierListUrl` in a new tab.

### Props

| Name | Type | Required | Description |
|---|---|---|---|
| `configPostId` | `string` | Yes | The WP post ID of the parent config; the component renders nothing when absent |
| `tierCptSlug` | `string` | Yes | WP REST API post-type slug, used when constructing edit links |
| `tierMdpUuids` | `string` | Yes | Comma-separated MDP tier UUIDs to display |
| `tierListUrl` | `string` | Yes | URL for the "View All" link |

### Fetch behaviour

Two requests run sequentially on mount:

1. `fetchMembershipTiers({ filters: { id: tierIdsArray } })` — fetches MDP tier metadata, then filters client-side to ensure only the requested UUIDs are shown (the API may return extras).
2. `apiFetch` on `/wicket-memberships/v1/membership_tier_info` with `properties[]=count` — fetches the active member count per tier.

Member counts are resolved separately because the MDP tiers endpoint does not include them.
