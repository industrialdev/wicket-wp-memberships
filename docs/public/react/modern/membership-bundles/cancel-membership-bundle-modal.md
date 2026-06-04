---
title: CancelMembershipBundleModal
---

# CancelMembershipBundleModal

Modal dialog displayed when an admin sets a membership bundle's status to "cancelled". Requires the admin to choose how individual memberships should be handled before the cancellation is submitted. Calls `cancelMembershipBundle` on confirm.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `isOpen` | `boolean` | Yes | Controls modal visibility. |
| `bundlePostId` | `number` | Yes | WP post ID of the membership bundle to cancel. |
| `onRequestClose` | `Function` | Yes | Called when the modal should close without cancelling. Resets all internal state. |
| `onSuccess` | `Function` | Yes | Called with the success message string after a successful cancellation. The parent should refresh record state and surface a notice. |

## Cancel Paths

The modal exposes three mutually exclusive outcomes depending on the combination of `member_handling` and (when applicable) `timing` selections.

### Path 1 — cancel_all / immediately

`member_handling: "cancel_all"`, `timing: "immediately"`

All individual memberships linked to the bundle are cancelled immediately when the request is processed.

### Path 2 — cancel_all / at_end_date

`member_handling: "cancel_all"`, `timing: "at_end_date"`

All individual memberships are scheduled to cancel at the membership bundle's end date rather than immediately.

### Path 3 — keep_as_individual

`member_handling: "keep_as_individual"`

Individual memberships are detached from the bundle and continue as standalone individual memberships. No timing selection is required for this path.

::: warning
The `keep_as_individual` option is irreversible. Once individual memberships are detached from the bundle they cannot be re-associated with it.
:::

The **Confirm Cancellation** button remains disabled until a valid combination is selected. For `cancel_all`, both `member_handling` and `timing` must be chosen. For `keep_as_individual`, only `member_handling` is required.

:::details Example

```jsx
<CancelMembershipBundleModal
  isOpen={isCancelGroupOpen}
  bundlePostId={bundlePostId}
  onRequestClose={() => setIsCancelGroupOpen(false)}
  onSuccess={(message) => {
    setIsCancelGroupOpen(false);
    onRecordUpdated({ ...record, status: "cancelled" });
    if (onBundleCancelled) onBundleCancelled(message);
  }}
/>
```

:::
