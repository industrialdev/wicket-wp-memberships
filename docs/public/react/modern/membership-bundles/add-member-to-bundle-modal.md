---
title: AddMemberToBundleModal
---

# AddMemberToBundleModal

Modal dialog that allows an admin to add a new MDP person to a membership bundle. Opened from the Membership Actions dropdown on the bundle detail page. Implements a sequential three-step selection workflow before the API call is made.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `isOpen` | `boolean` | Yes | Controls modal visibility. |
| `bundlePostId` | `number` | Yes | WP post ID of the membership bundle to add the member to. |
| `onRequestClose` | `Function` | Yes | Called when the modal should close (Cancel button or close icon). Resets all internal state. |
| `onSuccess` | `Function` | Yes | Called after a successful add. The parent should refresh data (e.g. increment `memberRefreshKey`) and surface a success notice. |

## Three-Step Workflow

The three selectors are rendered sequentially in the modal. Each step becomes available only after the preceding step is complete.

### Step 1 — Select User

An async search field queries `fetchMdpPersons` with a minimum of 3 characters. Each option is labelled `Full Name (uuid) — email`. Changing the user selection resets the tier and product selections.

### Step 2 — Select Tier

A `ModalPostSelector` loads all published `wicket_mship_tier` CPT posts filtered to `type === "individual"`. After the tier list is loaded, a single follow-up call to `fetchMembershipProducts` resolves product and variation names for all tier product entries in one request. The tier selector is disabled until a user is selected.

### Step 3 — Select Product (conditional)

The product selector appears only when the selected tier has **more than one** product in its `tier_data.product_data`. When exactly one product exists, it is automatically pre-selected and the selector is hidden. Product options are derived from the already-enriched tier data — no additional network request is made.

## API Call

On submit, calls `addMemberToBundle(bundlePostId, payload)` where the payload is:

```js
{
  mode: "new",
  person_uuid: selectedUser.value,   // MDP person UUID
  tier_post_id: selectedTier.value,  // WP tier post ID
  product_id: selectedProduct.productId,
  variation_id: selectedProduct.variationId, // omitted when null
}
```

The submit button is disabled until all three steps are complete and the request is not in flight.

:::details Example

```jsx
<AddMemberToBundleModal
  isOpen={isAddMemberOpen}
  bundlePostId={bundlePostId}
  onRequestClose={() => setIsAddMemberOpen(false)}
  onSuccess={() => {
    setIsAddMemberOpen(false);
    setMemberRefreshKey((k) => k + 1);
    if (onMemberAdded) onMemberAdded();
  }}
/>
```

:::
