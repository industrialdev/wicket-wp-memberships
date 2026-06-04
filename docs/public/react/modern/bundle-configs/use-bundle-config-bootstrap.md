---
title: useBundleConfigBootstrap
---

# useBundleConfigBootstrap

`frontend/src/membership_bundle_configs/hooks/useBundleConfigBootstrap.js`

Initializes and manages all async data needed to render the bundle config form. Handles three independent async operations: loading the existing record (edit mode only), lazy-loading WP post options, and lazy-loading WooCommerce product options.

## Params

| Name | Type | Required | Description |
|---|---|---|---|
| `postId` | `string \| undefined` | No | WP post ID of the record to load. When provided, the hook fetches the record on mount. |
| `bundleConfigCptSlug` | `string` | Yes | REST base for the CPT; used to build the API path `${API_URL}/${bundleConfigCptSlug}/${postId}`. |
| `languageCodes` | `string[]` | Yes | Active language codes; passed to `normalizeBundleConfigPostToForm` when hydrating the form from the API response. |
| `defaultForm` | `object` | Yes | Initial form state (built by `createDefaultForm`). Re-applied whenever `defaultForm` reference changes. |

:::details Returns

```ts
{
  // Form state
  form: object;              // Current form values
  setForm: Dispatch;         // Form state setter

  // Request states
  recordRequest: {
    status: "idle" | "loading" | "success" | "error";
    error: unknown | null;
  };
  postsRequest: {
    status: "idle" | "loading" | "success" | "error";
    error: unknown | null;
  };
  productsRequest: {
    status: "idle" | "loading" | "success" | "error";
    error: unknown | null;
  };

  // Loaded option lists
  wpPostsOptions: Array<{
    title: string;
    value: number;
    modified: string;
    published: string;
  }>;
  wcProductOptions: Array<{
    title: string;
    value: number;
    sku: string;
    price: string;
  }>;

  // Actions
  retryRecord: () => Promise<void>;     // Re-fetches the bundle config record
  retryPosts: (restSlug?: string) => Promise<Array>;   // Re-fetches WP posts
  retryProducts: () => Promise<Array>;  // Re-fetches WC products

  // Lazy loaders (same functions, exposed under a different name for clarity)
  loadPostOptions: (restSlug?: string) => Promise<Array>;
  loadProductOptions: () => Promise<Array>;

  // Derived
  isRecordReady: boolean;   // true when postId is absent OR recordRequest.status === "success"
}
```
:::

## Loading states

The three parallel requests are independent. Only `recordRequest` starts in `"loading"` when `postId` is present; the post and product requests start as `"idle"` and are triggered on demand by their lazy loaders.

| Request | Initial state (create mode) | Initial state (edit mode) | Triggered by |
|---|---|---|---|
| `recordRequest` | `success` | `loading` | Auto-runs on mount when `postId` is set; `retryRecord` on error |
| `postsRequest` | `idle` | `idle` | `loadPostOptions` called by `RenewalTypeSection` |
| `productsRequest` | `idle` | `idle` | `loadProductOptions` called by `GracePeriodSection` |

## Lazy loaders

`loadPostOptions(restSlug = "pages")` fetches `${API_URL}/${restSlug}` with `_fields=id,title,date,modified&status=publish&per_page=-1` and maps results to `{ title, value, modified, published }`.

`loadProductOptions()` calls `fetchWcProducts({ status: "publish", per_page: -1 })` from the shared API service and maps results to `{ title, value, sku, price }`.

Both loaders update their respective request state and option list, and return the mapped option array so callers can use it inline if needed.

## Reset behavior

When `defaultForm` or `hasPostId` changes (derived from `postId`), the hook resets all state to initial values and re-runs the record fetch if `postId` is present. This ensures stale data from a previous navigation is never shown.

::: tip
`isRecordReady` is the correct gate for enabling form interaction. Use it rather than checking `recordRequest.status` directly.
:::

::: warning
If `recordRequest.status === "error"`, expose a retry UI using the `retryRecord` function. `BundleConfigPage` already does this via `AdminNoticeStack`.
:::
