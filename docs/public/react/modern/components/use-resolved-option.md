---
title: useResolvedOption
---

# useResolvedOption

**Source:** `frontend/src/shared/hooks/useResolvedOption.js`

Custom hook that resolves a single saved post or WooCommerce product ID into a `{ value, title }` option object on mount. Use it alongside `ModalPostSelector` so the trigger button shows the real post/product name immediately on page load — before the user has opened the modal and loaded the full option list.

## What it does

Makes a single targeted API call to fetch the name for the saved ID. For posts/pages it calls the WP REST API (`/wp/v2/{restSlug}/{id}?_fields=id,title`) and HTML-decodes the title via `he`. For products it calls the WooCommerce REST API (`/wc/v3/products/{id}`) and reads `product.name`.

The hook skips the fetch when `id` is falsy, `"-1"`, or an empty string, and sets `option` to `null` instead.

If the fetch fails for any reason the error is silently swallowed and `option` remains `null`. `ModalPostSelector` will show its placeholder text in that case.

A cleanup flag (`cancelled`) prevents state updates if the component unmounts before the fetch completes.

## Parameters

| Name | Type | Required | Description |
|---|---|---|---|
| `id` | `string \| number` | Yes | The saved post or product ID to resolve. Pass `null`, `""`, or `"-1"` to skip the fetch. |
| `type` | `"post" \| "product"` | Yes | Determines which API endpoint is called. `"post"` uses the WP REST API; `"product"` uses the WooCommerce REST API. |
| `restSlug` | `string` | No | WP REST API slug used when `type === "post"` (e.g. `"pages"`, `"posts"`, `"wicket_mship_tier"`). Defaults to `"pages"`. Ignored when `type === "product"`. |

## Return value

| Field | Type | Description |
|---|---|---|
| `option` | `{ value: number, title: string } \| null` | The resolved option object, or `null` while loading or if the fetch failed/was skipped. |
| `isLoading` | `boolean` | `true` while the fetch is in progress. Pass this as `isLoadingValue` on `ModalPostSelector`. |

:::details Example — resolving a saved page ID
```jsx
import ModalPostSelector from 'shared/components/ModalPostSelector';
import useResolvedOption from 'shared/hooks/useResolvedOption';

const { option: resolvedPage, isLoading: isLoadingPage } = useResolvedOption(
  savedPageId,   // e.g. 42
  'post',
  'pages'
);

<ModalPostSelector
  id="form_page_id"
  label={__('Form Page', 'wicket-memberships')}
  value={resolvedPage}
  isLoadingValue={isLoadingPage}
  onChange={(selected) => setSavedPageId(selected?.value ?? null)}
  loadOptions={loadPageOptions}
  columns={[
    { key: 'title', label: __('Title', 'wicket-memberships'), flex: 1, searchable: true },
  ]}
/>
```
:::

:::details Example — resolving a saved product ID
```jsx
const { option: resolvedProduct, isLoading: isLoadingProduct } = useResolvedOption(
  savedProductId,
  'product'
  // restSlug is ignored for products
);
```
:::

::: tip
The `option` object returned by this hook has `{ value, title }` — the same shape expected by `ModalPostSelector`. Pass it directly as the `value` prop; no transformation is needed.
:::

::: warning
This hook is intentionally minimal: it resolves one ID at a time and does not cache across re-mounts. If `id` changes after mount (e.g. the user navigates between records without unmounting), the hook re-runs the fetch for the new ID. If `id` is derived from unsaved state that changes frequently, consider debouncing it before passing it to this hook.
:::
