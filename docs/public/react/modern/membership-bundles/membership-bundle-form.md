---
title: MembershipBundleForm
---

# MembershipBundleForm

Form orchestrator for the membership bundle detail page. Renders all section components in order. Each section component is a thin adapter that maps `pageData` to the flat props expected by a shared UI component. Mirrors the role of `BundleConfigForm` in `membership_bundle_configs/`.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `pageData` | `object\|null` | Yes | Data returned by `fetchBundleEditPageInfo`. `null` while the bootstrap hook is loading. |
| `isLoading` | `boolean` | Yes | `true` while page data is pending. Passed through to each section so sections can render skeletons. |
| `onOwnerUpdated` | `Function` | Yes | Called with new owner data after a successful ownership change. Bubbles up to `MembershipBundlePage` to patch local state. |
| `individualMembersUrl` | `string` | Yes | URL of the individual members list page. Forwarded to `MembershipRecordsSection` for per-tier member filter links. |
| `onMemberAdded` | `Function` | No | Called after a member is successfully added to the bundle. Used by the parent to surface a success notice. |
| `onBundleCancelled` | `Function` | No | Called with a success message string after the bundle is cancelled. Used by the parent to surface a success notice. |

## Sections Rendered (in order)

1. **IntroBlockSection** — displays the bundle title, organisation name, and an optional "View in MDP" action link.
2. **MembershipRecordsSection** — displays the bundle's membership records table. Expanded rows render `MembershipBundleRecordDetails` via the section's `renderExpandedContent` prop, matching the layout pattern used in `members/edit.js`.

:::details Example

```jsx
<MembershipBundleForm
  pageData={pageData}
  isLoading={isLoading}
  onOwnerUpdated={handleOwnerUpdated}
  individualMembersUrl={individualMembersUrl}
  onMemberAdded={handleMemberAdded}
  onBundleCancelled={handleGroupCancelled}
/>
```

:::
