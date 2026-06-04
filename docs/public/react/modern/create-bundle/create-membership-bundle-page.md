---
title: CreateMembershipBundlePage
---

# CreateMembershipBundlePage

`frontend/src/create_membership_bundle/components/CreateMembershipBundlePage.js`

Page component that renders the "Create Membership Bundle" form. Wraps the inner form in `AdminPageErrorBoundary` with the same reset-key pattern used by other modern pages. All props come from `data-*` attributes on the `#create_membership_bundle` mount element.

## Props (from dataset)

| Name | Type | Required | Description |
|---|---|---|---|
| `bundleConfigCptSlug` | `string` | Yes | REST base for the bundle config CPT (e.g. `wicket_mship_bcfg`). Used when loading bundle config options in the `ModalPostSelector`. |
| `listUrl` | `string` | Yes | URL of the membership bundle list page. Shown as a "Back to Membership Bundles" link above the form. |
| `editBundleBaseUrl` | `string` | Yes | Base URL for the bundle edit page. After a successful create, the page redirects to `{editBundleBaseUrl}&id={bundle_group_uuid}&new=1`. |

## Form fields

| Field | Type | Required | Description |
|---|---|---|---|
| `name` | `TextControl` (string) | Yes | Human-readable name for the new bundle. |
| `bundleConfig` | `ModalPostSelector` (object) | Yes | Selected bundle config. Loads records from `${API_URL}/${bundleConfigCptSlug}` with columns for Config Name, Cycle, and Renewal Type. |
| `orgUuid` | `OrgUuidAsyncSelect` (string) | Yes | UUID of the owning MDP organization. Async search — requires at least 3 characters to trigger. |
| `owner` | `MembershipOwnerAsyncSelect` (object) | Yes | MDP person who owns the membership. Async search via `fetchMdpPersons` — requires at least 3 characters. |
| `startDate` | `MembershipDatePicker` (date) | Yes | Membership start date. Converted to ISO 8601 via `pickerDateToIso`. |

## API call on submit

On valid submission the component calls:

```js
createMembershipBundle({
  name,
  membership_bundle_config_id,
  org_uuid,
  owner_uuid,
  start_date,           // ISO 8601, derived from startDate via pickerDateToIso
})
```

`createMembershipBundle` is imported from `shared/services/api`.

## Success handling

If `response.success` is `true` and `response.response.bundle_group_uuid` is present, the page performs a hard redirect:

```
{editBundleBaseUrl}&id={bundle_group_uuid}&new=1
```

The `new=1` parameter signals the edit page that the bundle was just created (e.g. to show a welcome notice).

## Error handling

| Scenario | Behavior |
|---|---|
| Client-side validation fails | `errors` state is populated per-field; an `Alert` component renders the list of required-field messages at the top of the form. |
| API returns `response.error` | `submitError` is set to `response.error` and displayed in the `Alert`. |
| `apiFetch` throws | `submitError` is set from `err.error ?? err.message` or a generic fallback. |

The `Alert` component renders both validation errors and API errors. A dismiss button clears all errors from state.

::: tip
The error boundary reset key follows the same pattern as `BundleConfigPage`: incrementing the key fully remounts the inner form, clearing all stale state after an unhandled render error.
:::

:::details Example mount element
```html
<div
  id="create_membership_bundle"
  data-bundle-config-cpt-slug="wicket_mship_bcfg"
  data-list-url="/wp-admin/edit.php?post_type=wicket_mship_bundle"
  data-edit-bundle-base-url="/wp-admin/admin.php?page=wicket_mship_bundle_edit"
></div>
```
:::
