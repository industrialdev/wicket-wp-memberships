---
title: MembershipBundleRecordDetails
---

# MembershipBundleRecordDetails

Expanded panel content rendered inside the detail row of `MembershipRecordsSection` when the admin expands a bundle record. Wires all shared section components to bundle-specific API functions and mirrors the expanded row layout from `members/edit.js`.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `record` | `object` | Yes | Single membership record entry from `pageData.membership_records`. |
| `bundlePageData` | `object` | Yes | Full `pageData` for the bundle (provides subscription, orders, owner, config, and statuses). |
| `onRecordUpdated` | `Function` | Yes | Called with the updated record shape after a status or date/renewal-type save. |
| `onOwnerUpdated` | `Function` | Yes | Called with new owner data after a successful ownership change. Bubbles up to `MembershipBundlePage`. |
| `individualMembersUrl` | `string` | Yes | URL of the individual members list page. Forwarded to `BundleMembersSection` for per-tier filter links. |
| `onMemberAdded` | `Function` | No | Called after a member is successfully added. Used by the parent to surface a notice. |
| `onBundleCancelled` | `Function` | No | Called with a success message after the bundle is cancelled. |

## Expanded Row Content (in render order)

1. **MembershipBillingInfoSection** — subscription ID, subscription admin link, and next payment date. These values are read from `bundlePageData.subscription` and are shared across all record rows until the REST endpoint is enriched with per-record billing data.

2. **MembershipOrderDetailsSection** — order record table sourced from `bundlePageData.orders`.

3. **MembershipStatusSection + MembershipActionsSection** (side by side) — the status selector calls `updateMembershipBundleStatus`. Selecting "cancelled" intercepts the normal status change and opens `CancelMembershipBundleModal` instead. The actions dropdown exposes two items:
   - **Add Member to Bundle** — opens `AddMemberToBundleModal`.
   - **Create Renewal Order** — opens `CreateBundleRenewalOrderModal`. Disabled when the record status is `"cancelled"`.

4. **CancelMembershipBundleModal**, **AddMemberToBundleModal**, **CreateBundleRenewalOrderModal** — rendered inline (closed by default) and controlled by local boolean state.

5. **MembershipDetailsForm** — date pickers (start, end, expiry), renewal type selector, and owner section. Save calls `updateMembershipBundle(record.ID, payload)`. The form's `renderExtra` prop injects `BundleMembersSection` scoped to `record.ID` so each expanded row shows the member count for its own specific bundle post.

## Renewal Type Defaults

The component maps the config's `renewal_type` value to the form's select option value before passing it as `defaultRenewalType`:

| Config value | Form value |
|---|---|
| `form_page` | `form_flow` |
| `subscription` | `subscription` |

The allowed renewal type options are provided by `BUNDLE_RENEWAL_TYPE_OPTIONS` from `MembershipRenewalTypeSection`.

:::details Example

```jsx
// Inside MembershipRecordsSection's renderExpandedContent
<MembershipBundleRecordDetails
  record={record}
  bundlePageData={pageData}
  onRecordUpdated={handleRecordUpdated}
  onOwnerUpdated={onOwnerUpdated}
  individualMembersUrl={individualMembersUrl}
  onMemberAdded={onMemberAdded}
  onBundleCancelled={onBundleCancelled}
/>
```

:::
