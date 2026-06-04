---
title: ModalPostSelector
---

# ModalPostSelector

**Source:** `frontend/src/shared/components/ModalPostSelector.js`

Modal-based picker for WordPress posts, pages, or WooCommerce products. The most complex shared input component. Supports client-side search, column-level sorting, and paginated display. Data is loaded lazily on first modal open and cached for the lifetime of the component instance.

## What it does

Renders a trigger button showing the current selection (or placeholder). Clicking the button opens a `WicketModal` containing:

- A search input for client-side filtering
- A sortable, paginated data table built from the `columns` prop
- A footer with Cancel and Select buttons

The selection flow is two-phase: clicking a row sets a pending selection (highlighted in the table); clicking **Select** commits it by calling `onChange`. Cancelling or closing the modal discards the pending selection.

When a selection exists and the field is not disabled, a clear button (✕) is shown in the trigger button; clicking it calls `onChange(null)` directly without opening the modal.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `id` | `string` | Yes | `id` attribute on the trigger button. |
| `label` | `string` | Yes | Field label rendered above the trigger button. |
| `placeholder` | `string` | No | Placeholder text shown in the trigger button when nothing is selected. Defaults to `"Select…"`. |
| `value` | `{ value: number \| string, title: string } \| null` | No | Currently selected option. Defaults to `null`. |
| `onChange` | `Function` | Yes | Called with the confirmed option `{ value, title }` or `null` (on clear). |
| `disabled` | `boolean` | No | Disables the trigger button and hides the clear indicator. Defaults to `false`. |
| `modalTitle` | `string` | No | Title shown in the modal header. Falls back to `label`, then `"Select an option"`. |
| `loadOptions` | `Function` | Yes | `async () => Array<OptionObject>`. Called once on first modal open. Result is cached. |
| `isLoadingValue` | `boolean` | No | When `true`, trigger button shows "Loading…" and is disabled. Use while resolving the initial saved value. Defaults to `false`. |
| `idLabel` | `string` | No | Override for the "ID" column header. Defaults to `"ID"`. |
| `columns` | `Array<ColumnDescriptor>` | No | Column definitions for the data columns between the implicit ID column and the implicit view icon column. Defaults to Title + Created + Last Modified. |

### OptionObject shape (returned by `loadOptions`)

| Field | Type | Description |
|---|---|---|
| `value` | `number \| string` | The post/product ID. Used as the React key and displayed in the implicit ID column. |
| `title` | `string` | Display title. Used in the trigger button and by any column with `key: "title"`. |
| _(any extra fields)_ | `any` | Additional fields referenced by custom column `key` values (e.g. `price`, `published`, `modified`). |

### ColumnDescriptor shape

| Field | Type | Description |
|---|---|---|
| `key` | `string` | Property name on the option object to display. Use `"title"` for the post title. |
| `label` | `string` | Column header text. |
| `width` | `number` | Fixed column width in pixels. Omit to use `flex`. |
| `flex` | `number` | CSS flex value (e.g. `1` for fill remaining space). |
| `searchable` | `boolean` | When `true`, this column's values are included in client-side search filtering. |
| `sortable` | `boolean` | When `false`, the column header is not clickable. Defaults to `true`. |
| `format` | `"text" \| "currency" \| "date"` | Controls cell rendering. `"currency"` formats via `Intl.NumberFormat` using `wicketMembershipsSettings.currency`. `"date"` formats ISO strings with month/day/year + time. Defaults to `"text"`. |

## Implicit columns

Two columns are always present regardless of the `columns` prop:

| Column | Position | Width | Description |
|---|---|---|---|
| ID | First | 80 px | Displays `opt.value`. Sortable. Header label from `idLabel` prop. |
| View icon | Last | 44 px | Links to the WP admin edit screen for the post. Not sortable, click is isolated from row selection. |

## Search and filter behaviour

- Search is client-side, applied to the loaded options array.
- Filtering matches against the ID (`opt.value`) and all columns marked `searchable: true`.
- Search resets to page 1 on each keystroke.
- The table shows an empty state message distinct from "no results" vs "no options".

## Sort behaviour

- Default sort is by ID ascending.
- Clicking any sortable column header sorts ascending on first click; a second click on the same column reverses to descending.
- Sorting resets to page 1.
- `"currency"` columns sort numerically; all others sort as strings (case-insensitive).

## Pagination

- Page size is 20 rows.
- The pagination bar (Prev / Next + "Page X of Y (N total)") appears only when the filtered result set exceeds 20 rows.
- Pagination state resets whenever the search query changes.

## Data loading lifecycle

`loadOptions` is called at most once per component mount. Subsequent modal opens reuse the cached result. If loading fails, an error notice with a **Retry** button is shown; clicking Retry triggers another `loadOptions` call.

## Display label resolution

The trigger button displays `"<title> (<id>)"`. When the modal has been opened at least once and the full option list is loaded, the title is resolved from the loaded options array rather than the saved `value.title`. This fixes stale labels if only an ID was saved (e.g. from a previous save before the user interacted with the modal).

:::details Example — single searchable title column
```jsx
import ModalPostSelector from 'shared/components/ModalPostSelector';
import { useResolvedOption } from 'shared/hooks/useResolvedOption';

const { option: resolvedPage, isLoading: isLoadingPage } = useResolvedOption(
  savedPageId,
  'post',
  'pages'
);

const loadPageOptions = useCallback(async () => {
  const posts = await apiFetch({
    path: addQueryArgs('/wp/v2/pages', { _fields: 'id,title', per_page: -1, status: 'publish' }),
  });
  return posts.map((p) => ({ value: p.id, title: he.decode(p.title.rendered) }));
}, []);

<ModalPostSelector
  id="form_page_id"
  label={__('Form Page', 'wicket-memberships')}
  placeholder={__('Select a page…', 'wicket-memberships')}
  modalTitle={__('Select Form Page', 'wicket-memberships')}
  value={resolvedPage}
  isLoadingValue={isLoadingPage}
  onChange={(selected) => setSavedPageId(selected ? selected.value : null)}
  loadOptions={loadPageOptions}
  columns={[
    { key: 'title', label: __('Title', 'wicket-memberships'), flex: 1, searchable: true },
  ]}
/>
```
:::

:::details Example — product picker with currency column
```jsx
<ModalPostSelector
  id="product_id"
  label={__('Product', 'wicket-memberships')}
  modalTitle={__('Select Product', 'wicket-memberships')}
  value={productOption}
  onChange={setProductOption}
  loadOptions={loadProductOptions}
  idLabel={__('SKU', 'wicket-memberships')}
  columns={[
    { key: 'title',  label: __('Name', 'wicket-memberships'),  flex: 1,   searchable: true },
    { key: 'price',  label: __('Price', 'wicket-memberships'), width: 100, format: 'currency', sortable: true },
  ]}
/>
```
:::

::: tip
Use `useResolvedOption` alongside this component. It resolves the saved ID to a `{ value, title }` option on mount so the trigger button shows the real name before the user opens the modal.
:::

::: warning
`loadOptions` must return a stable reference (wrap with `useCallback`). The component calls it only once, but if the reference changes between renders a fresh load will be triggered on the next modal open.
:::
