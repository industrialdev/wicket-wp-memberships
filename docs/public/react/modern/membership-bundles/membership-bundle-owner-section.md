---
title: MembershipBundleOwnerSection
---

# MembershipBundleOwnerSection

Adapter component for the shared `MembershipOwnerSection` UI component. Maps bundle `pageData` to flat props and injects the bundle-specific API call and label copy. Returns `null` while data is loading or unavailable.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `pageData` | `object\|null` | Yes | Data returned by `fetchBundleEditPageInfo`. The component returns `null` when this is `null`. |
| `isLoading` | `boolean` | Yes | Returns `null` when `true`. Prevents the owner section from rendering before data is ready. |
| `onOwnerUpdated` | `Function` | Yes | Called with `{ name, uuid }` after a successful ownership change. Bubbles up to `MembershipBundlePage` to patch local state without a full reload. |

## What It Renders

Delegates entirely to `MembershipOwnerSection` with the following mapped values:

- **Title**: "Membership Bundle Owner"
- **Tooltip**: "Represents the person responsible for managing and renewing this Membership Bundle."
- **Current owner option**: `{ label: owner.name, value: owner.uuid }` derived from `pageData.owner`, or `null` if no owner is set.
- **MDP link**: `pageData.owner.mdp_link` — displayed as a link to the owner's MDP profile.
- **Switch-to URL**: `pageData.owner.switch_to_url` — allows switching the admin session to the owner's account.

## API Call on Change

The owner search field queries `fetchMdpPersons({ term: inputValue })` with a minimum of 3 characters. Each option is labelled `Full Name (uuid) — email`.

On save, if the selected person's UUID differs from the current owner's UUID, the component calls `updateBundleChangeOwnership(bundlePostId, selectedOption.value)`. On a successful response (`response.success === true`), `onOwnerUpdated` is called with the new owner's `{ name, uuid }`.

:::details Example

```jsx
<MembershipBundleOwnerSection
  pageData={pageData}
  isLoading={isLoading}
  onOwnerUpdated={handleOwnerUpdated}
/>
```

:::
