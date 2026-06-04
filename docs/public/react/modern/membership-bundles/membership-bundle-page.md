---
title: MembershipBundlePage
---

# MembershipBundlePage

Top-level container for the membership bundle detail page. Renders the WordPress admin page heading, an optional back link to the bundle list, and wraps all content inside an `AdminPageErrorBoundary`. Mirrors the role of `BundleConfigPage` in `membership_bundle_configs/`.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `bundleGroupUuid` | `string` | Yes | The `membership_bundle_group_uuid` for the series. Read from `app.dataset.bundleGroupUuid` in `pages/edit.js`. |
| `listUrl` | `string` | Yes | URL of the membership bundle list admin page. Rendered as a "Back to Membership Bundles" link when truthy. |
| `individualMembersUrl` | `string` | Yes | URL of the individual members list page. Passed through to child sections for filtered member links. |

## Error Boundary

`MembershipBundlePage` wraps its content in `AdminPageErrorBoundary`. The boundary holds a numeric `errorBoundaryResetKey` state value. Incrementing the key both resets the boundary and remounts `MembershipBundlePageContent` (via the `key` prop), giving the user a clean retry without a full page reload.

## Notice Stack

Notices are managed inside `MembershipBundlePageContent` and rendered by `AdminNoticeStack` above the content area. Four notice slots exist:

| Slot | Trigger |
|---|---|
| `load-error` | `requestState.status === "error"` from the bootstrap hook. Includes a "Retry loading" action that calls `retryLoad`. |
| `new-bundle` | URL contains `?new=1` at mount time. Displays a success message prompting the admin to add members. |
| `member-added` | `handleMemberAdded` callback fired by `MembershipBundleForm` after a successful member add. |
| `bundle-cancelled` | `handleGroupCancelled` callback fired after a successful bundle cancellation. |

## Bootstrap Hook

`MembershipBundlePageContent` calls `useMembershipBundleBootstrap({ bundleGroupUuid })` and destructures:

- `pageData` — the loaded bundle data or `null` while loading.
- `setPageData` — used by `handleOwnerUpdated` to patch the owner in local state without a full reload.
- `requestState` — `{ status: "loading" | "success" | "error", error }`.
- `retryLoad` — re-fetches page data; wired to the error notice action.
- `renewalProcessingMeta` — passed directly to `RenewalProcessingOverlay`.

## Layout

The form content is wrapped in a styled `ContentArea` (`position: relative; overflow: hidden`) so that `RenewalProcessingOverlay` (which uses `position: absolute; inset: 0`) is scoped to the bundle content area rather than the full viewport.

:::details Example

```jsx
// pages/edit.js
const app = document.getElementById("bundle_member_edit");
if (app) {
  createRoot(app).render(
    <MembershipBundlePage
      bundleGroupUuid={app.dataset.bundleGroupUuid}
      listUrl={app.dataset.listUrl}
      individualMembersUrl={app.dataset.individualMembersUrl}
    />
  );
}
```

:::
