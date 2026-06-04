---
title: MembershipDetailsForm
---

# MembershipDetailsForm

**Source:** `frontend/src/shared/components/MembershipDetailsForm.js`

Composite form that owns local state for membership dates, renewal type, and an optional bundle owner selector. Submits everything in a single "Update Membership" button click and fires parallel save promises when the owner has changed.

## Sub-sections rendered

In order:

1. `Alert` — inline save result feedback
2. `MembershipDatesSection` — Start Date, End Date, Expiration Date pickers
3. `MembershipRenewalTypeSection` — renewal type selector with conditional sub-fields
4. Optional owner field — rendered when `onLoadOwnerOptions` is provided; shows the "Membership Bundle Owner" async-select with MDP link and impersonation button
5. `renderExtra` slot — rendered between the owner field and the submit button
6. Submit button — labelled by `submitLabel` (default: "Update Membership")

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `dates` | `object \| null` | No | Initial date values: `{ starts_at, ends_at, expires_at }`. ISO strings. Synced to state via `useEffect`. |
| `renewalType` | `string \| null` | No | Initial renewal type value. Synced to state via `useEffect`. |
| `tierRenewalType` | `string \| null` | No | Renewal type from the tier/config. Shown as a hint when `renewalType === "inherited"`. |
| `nextTierFormPageId` | `number \| null` | No | Current form page ID for `form_flow` renewal type. Synced via `useEffect`. |
| `nextTierId` | `number \| null` | No | Current next tier ID for `sequential_logic` renewal type. Synced via `useEffect`. |
| `disabled` | `boolean` | No | Disables all inputs and the save button. Defaults to `false`. |
| `allowedRenewalTypes` | `string[] \| null` | No | When provided, only these option values are shown in the renewal type selector. Pass `BUNDLE_RENEWAL_TYPE_OPTIONS` for bundle membership records. |
| `onSave` | `Function` | Yes | Called with the merged payload on submit. Must return `Promise<{ success?, error? }>`. See payload shape below. |
| `onSaved` | `Function` | No | Called after a successful save with the updated values. |
| `ownerOption` | `object \| null` | No | Current owner as `{ label, value }`. When provided, enables the owner field. Synced via `useEffect`. |
| `ownerMdpLink` | `string \| null` | No | URL to view the current owner in MDP. Renders a "View in MDP" link when set. |
| `ownerSwitchToUrl` | `string \| null` | No | Impersonation URL for the current owner. |
| `onLoadOwnerOptions` | `Function \| null` | No | `(inputValue, callback) => void` for the async owner select. When provided, the owner field is rendered. |
| `onOwnerSave` | `Function \| null` | No | Called with the selected option when saving the owner. Must return `Promise<{ success?, error? }>`. |
| `onOwnerUpdated` | `Function \| null` | No | Called with `{ name, uuid }` after a successful owner save. |
| `renderExtra` | `Function \| null` | No | Optional render prop called with no args, returns `ReactNode`. Rendered between the owner field and the submit button. |
| `submitLabel` | `string \| null` | No | Label for the submit button. Defaults to `"Update Membership"`. |

## `onSave` payload shape

```js
{
  membership_starts_at: string | undefined,  // ISO string, omitted when picker is empty
  membership_ends_at:   string | undefined,
  membership_expires_at: string | undefined,
  renewalType: string | null,
  nextTierFormPageId: number | null,
  nextTierId: number | null,
}
```

## Prop interaction notes

- The owner field is only rendered when `onLoadOwnerOptions` is provided. The other owner props (`ownerOption`, `ownerMdpLink`, `ownerSwitchToUrl`, `onOwnerSave`, `onOwnerUpdated`) have no effect without it.
- Owner save and membership save run in parallel via `Promise.all`. If either fails, the combined error message is shown in the `Alert`. If both succeed, `onSaved` and `onOwnerUpdated` are called.
- The submit button is disabled when `renewalType === "form_flow"` and `nextTierFormPageId` is not set, preventing submission of an incomplete form-flow configuration.
- Date values are converted from ISO strings to `Date` objects via `isoToPickerDate` (from `MembershipDatesSection`) on initial load, and back to ISO via `pickerDateToIso` on submit.

:::details Example
```jsx
<MembershipDetailsForm
  dates={{ starts_at: record.starts_at, ends_at: record.ends_at, expires_at: record.expires_at }}
  renewalType={record.renewal_type}
  tierRenewalType={configRenewalType}
  allowedRenewalTypes={BUNDLE_RENEWAL_TYPE_OPTIONS}
  onSave={(payload) => updateMembershipBundle(record.ID, payload)}
  onSaved={(updated) => updateRecord(updated)}
  submitLabel="Update Membership Bundle"
/>
```
:::
