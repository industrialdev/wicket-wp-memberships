---
title: Create Bundle UI
---

# Create Bundle UI

The Create Bundle UI provides a single-page form for creating a new **Membership Bundle** record in WP admin. It collects the bundle name, bundle config, owning organization, membership owner (person), and start date, then calls the `createMembershipBundle` API function and redirects to the new bundle's edit page on success.

## Entry Point

`frontend/src/create_membership_bundle/pages/create.js`

The entry script mounts `CreateMembershipBundlePage` onto the `#create_membership_bundle` DOM element and reads three `data-*` attributes:

```js
createRoot(app).render(
  <CreateMembershipBundlePage
    bundleConfigCptSlug={app.dataset.bundleConfigCptSlug}
    listUrl={app.dataset.listUrl}
    editBundleBaseUrl={app.dataset.editBundleBaseUrl}
  />
);
```

## Component

- [CreateMembershipBundlePage](./create-membership-bundle-page) — Form shell with error boundary, validation, and submit handling.
