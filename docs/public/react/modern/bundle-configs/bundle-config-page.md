---
title: BundleConfigPage
---

# BundleConfigPage

`frontend/src/membership_bundle_configs/components/BundleConfigPage.js`

Top-level page component for the bundle config create/edit screen. Renders the WordPress admin heading, wraps the inner content in an `AdminPageErrorBoundary`, and increments a reset key whenever the boundary catches an error so the inner tree is fully remounted on recovery.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `bundleConfigCptSlug` | `string` | Yes | REST base for the bundle config CPT (e.g. `wicket_mship_bcfg`). Passed through to the inner content and used to build API paths. |
| `bundleConfigListUrl` | `string` | Yes | URL of the bundle config list admin page. Used for the Cancel button and post-save redirect. |
| `postId` | `string` | No | WP post ID of the record being edited. Omit or leave empty for create mode. |
| `languageCodes` | `string` | No | Comma-separated language codes (e.g. `"en,fr"`). Defaults to `["en"]` when blank. |

All props arrive from the mount element's `dataset` attributes — see `pages/edit.js`.

## Error Boundary

`BundleConfigPage` wraps `BundleConfigPageContent` in `AdminPageErrorBoundary`. When the boundary catches an error:

1. The boundary renders its fallback UI with a "Try again" button.
2. Clicking "Try again" calls `onReset`, which increments `errorBoundaryResetKey`.
3. The updated key is passed both as the `resetKey` prop on the boundary and as the `key` prop on `BundleConfigPageContent`, forcing a full remount and clearing all stale state.

## Bootstrap Hook

The inner `BundleConfigPageContent` component calls `useBundleConfigBootstrap` to:

- Parse `languageCodes` into an array (falling back to `["en"]`).
- Build a `defaultForm` with `createDefaultForm`.
- Load the existing record when `postId` is present.
- Lazily load WP posts and WooCommerce product options on demand.

## Create vs. Edit mode

| Condition | Heading | Submit label | API method |
|---|---|---|---|
| `postId` absent / empty | "Add New Membership Bundle Configuration" | "Save Bundle Configuration" | `POST /bundle-config-cpt-slug` |
| `postId` present | "Edit Membership Bundle Configuration" | "Update Bundle Configuration" | `POST /bundle-config-cpt-slug/{postId}` |

::: tip
On a successful save (`response.id` is truthy), the page redirects to `bundleConfigListUrl`. No success toast is shown — the list page is the confirmation.
:::

:::details Example
```html
<div
  id="create_membership_bundle_config"
  data-bundle-config-cpt-slug="wicket_mship_bcfg"
  data-bundle-config-list-url="/wp-admin/edit.php?post_type=wicket_mship_bcfg"
  data-post-id="42"
  data-language-codes="en,fr"
></div>
```
:::
