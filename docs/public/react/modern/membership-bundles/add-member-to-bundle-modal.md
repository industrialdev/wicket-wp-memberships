---
title: AddMemberToBundleModal
---

# AddMemberToBundleModal

Modal dialog that allows an admin to add an MDP person to a membership bundle. Opened from the Membership Actions dropdown on the bundle detail page. After the person is selected, the admin either reuses one of that person's existing eligible memberships or creates a brand new one.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `isOpen` | `boolean` | Yes | Controls modal visibility. |
| `bundlePostId` | `number` | Yes | WP post ID of the membership bundle to add the member to. |
| `eligibleTierIds` | `number[]` | No (default `[]`) | The bundle config's `eligible_tier_ids`. Empty means all active individual tiers are eligible — the tier dropdown in the "new membership" path is unfiltered in that case, not empty. |
| `onRequestClose` | `Function` | Yes | Called when the modal should close (Cancel button or close icon). Resets all internal state. |
| `onSuccess` | `Function` | Yes | Called after a successful add. The parent should refresh data (e.g. increment `memberRefreshKey`) and surface a success notice. |

## Workflow

### Step 1 — Select User

An async search field queries `fetchMdpPersons` with a minimum of 3 characters. Each option is labelled `Full Name (uuid) — email`.

Selecting a person triggers `fetchBundleEligibleMemberships(bundlePostId, personUuid)` to discover any of that person's standalone individual memberships that are eligible for this bundle (see [`fetchBundleEligibleMemberships`](../../shared/api.md#fetchbundleeligiblemembershipsbundlepostid-personuuid)). A request-ID ref guards against an out-of-order response overwriting state if the admin picks a different person before the lookup finishes.

A person with no WordPress account yet returns an empty list, so the flow defaults to "new membership" with no error. If the lookup itself fails, the error is shown inline and the flow falls back to "new membership."

### Step 2 — Choose new vs. existing membership

Once the eligible-memberships lookup resolves, a segmented switch appears:

- **"Use an existing membership"** — enabled only when at least one eligible membership was found. Selected by default when eligible memberships exist.
- **"Create a new membership"** — always enabled. Default when no eligible memberships were found.

Switching modes resets the tier, product, and existing-membership selections.

### Step 3a — Existing membership path

A `ModalPostSelector` lists the discovered eligible memberships (columns: tier name, start date, end date, status). Selecting one is sufficient to submit — no tier or product selection is needed, since the existing membership already has both.

### Step 3b — New membership path

- **Tier**: a `ModalPostSelector` loads published `wicket_mship_tier` CPT posts filtered to `type === "individual"` and to `eligibleTierIds` (when non-empty). A single follow-up call to `fetchMembershipProducts` resolves product/variation names for all tier product entries in one request.
- **Product** (conditional): shown only when the selected tier has more than one product in `tier_data.product_data`. With exactly one product, it is auto-selected and the selector is hidden. Product options are derived from the already-enriched tier data — no extra request.

## API Call

On submit, calls `addMemberToBundle(bundlePostId, payload)`. The payload shape depends on the chosen mode:

```js
// Existing membership
{
  mode: "existing",
  existing_membership_post_id: selectedExistingMembership.value,
  tier_post_id: selectedExistingMembership.tierPostId,
}

// New membership
{
  mode: "new",
  person_uuid: selectedUser.value,
  tier_post_id: selectedTier.value,
  product_id: selectedProduct.productId,
  variation_id: selectedProduct.variationId, // omitted when null
}
```

The submit button is disabled until the required selections for the current mode are complete and the request is not in flight.

:::details Example

```jsx
<AddMemberToBundleModal
  isOpen={isAddMemberOpen}
  bundlePostId={bundlePostId}
  eligibleTierIds={bundleConfig.eligible_tier_ids}
  onRequestClose={() => setIsAddMemberOpen(false)}
  onSuccess={() => {
    setIsAddMemberOpen(false);
    setMemberRefreshKey((k) => k + 1);
    if (onMemberAdded) onMemberAdded();
  }}
/>
```

:::
