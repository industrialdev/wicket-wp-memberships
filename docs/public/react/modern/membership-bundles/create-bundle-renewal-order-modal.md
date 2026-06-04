---
title: CreateBundleRenewalOrderModal
---

# CreateBundleRenewalOrderModal

Modal dialog that creates a WooCommerce renewal order off the bundle's existing subscription. Does not create a new subscription — line items are inherited from the existing bundle subscription so billing history stays intact.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `isOpen` | `boolean` | Yes | Controls modal visibility. |
| `bundlePostId` | `number` | Yes | WP post ID of the membership bundle for which to create the renewal order. |
| `onRequestClose` | `Function` | Yes | Called when the modal should close. Resets all internal state. |
| `onSuccess` | `Function` | No | Called with the new order URL string (or `null`) after a successful creation. |

## What It Does

On confirm, the modal calls `createBundleRenewalOrder(bundlePostId)`. The API returns an object containing `order_url` — the WooCommerce edit URL for the newly created order.

On success the confirmation and cancel buttons are replaced with a success alert and a **"View renewal order in WooCommerce"** link that opens `order_url` in a new tab.

On error, an inline `Alert` is displayed and the form remains open so the admin can retry.

The "Create Renewal Order" action is disabled (`disabled: isCancelled`) in `MembershipActionsSection` when the bundle record status is `"cancelled"`.

:::details Example

```jsx
<CreateBundleRenewalOrderModal
  isOpen={isCreateRenewalOrderOpen}
  bundlePostId={bundlePostId}
  onRequestClose={() => setIsCreateRenewalOrderOpen(false)}
  onSuccess={() => setIsCreateRenewalOrderOpen(false)}
/>
```

:::
