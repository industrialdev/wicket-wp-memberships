---
title: Legacy Frontend
---

# Legacy Frontend

::: warning Pending rework
These files are monolithic legacy components scheduled for refactoring into the component-based architecture used by the Membership Bundles feature. Do not use these as a pattern for new development — refer to the [Modern Component Architecture](../modern/) section instead.
:::

## What "legacy" means here

Each legacy page is a single, large `.js` file that mounts a React root directly onto a DOM element inserted by PHP. The characteristics shared by all files in this section are:

- **No shared section components.** Every section of the admin page (header, filters, table, forms, modals) is defined inline in the same file rather than composed from separately-maintained components.
- **State management mixed with rendering.** `useState` and the JSX tree live together. There is no clear separation between data layer and view layer.
- **Direct API calls inside `useEffect`.** Network requests (via `apiFetch` or the shared `services/api` wrappers) are issued directly from effect hooks at the component level, not from a centralized store or context provider.
- **Dataset props passed from PHP.** WordPress PHP enqueues the script and sets `data-*` attributes on a container `<div>`. React reads these via the element's `dataset` and spreads them as props on mount. There is no routing or URL-driven prop hydration — the dataset is set once on page load.

The contrast with the modern approach is that the Membership Bundles feature (see `frontend/src/bundles/`) uses smaller, independently testable section components that receive props and callbacks from a thin page-level coordinator.

## Legacy files

| File | Lines | Mounts on |
|---|---|---|
| `members/index.js` | 642 | `#member_list` |
| `members/edit.js` | 1114 | `#edit_member` |
| `members/bundle_list.js` | 263 | `#bundle_member_list` |
| `members/create_renewal_order.js` | 439 | (modal export, no direct mount) |
| `membership_configs/edit.js` | 1330 | `#create_membership_config` |
| `membership_configs/tiers.js` | 181 | (sub-component, no direct mount) |
| `membership_tiers/edit.js` | 727 | `#create_membership_tier` |
| `membership_tiers/manage_products.js` | 551 | (sub-component, no direct mount) |

## Partially modernised: extracted modal components

The following components have been split out of `members/edit.js` into their own files. They follow a controlled-modal pattern (the parent holds `isOpen` and passes `onRequestClose`/`onSuccess` callbacks) and are considered closer to the modern style, even though the parent that consumes them is still legacy.

| Component file | Purpose |
|---|---|
| `members/ManageStatusModal.js` | Modal that updates the status of a single membership record |
| `members/AddToMembershipBundleModal.js` | Modal that adds an individual membership to a bundle |
| `members/MoveToMembershipBundleModal.js` | Modal that moves a member from one bundle to another |
| `members/RemoveFromMembershipBundleModal.js` | Modal that removes a member from a bundle |
| `members/MembershipBundleDetails.js` | Expanded detail panel for a bundle membership row in the edit page |
| `members/MembershipBundleBadge.js` | Inline badge that labels a membership row as belonging to a bundle |

These files should be used as the reference pattern when extracting further sections from the remaining monolithic components.

## Sub-pages

- [Members (Legacy)](./members)
- [Membership Configs (Legacy)](./membership-configs)
- [Membership Tiers (Legacy)](./membership-tiers)
