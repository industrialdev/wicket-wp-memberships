---
title: Pagination
---

# Pagination

**Source:** `frontend/src/shared/components/Pagination.js`

Previous/next page buttons with an editable page number input. Renders `null` when `totalPages <= 1`.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `currentPage` | `number` | Yes | The active page number (1-based). |
| `totalPages` | `number` | Yes | Total number of pages. Component hides itself when this is `<= 1`. |
| `onPageChange` | `Function` | Yes | Called with the target page number when the user navigates. |

## Behaviour

- Previous button is disabled when `currentPage === 1`.
- Next button is disabled when `currentPage === totalPages`.
- The page input is editable. Changes are committed when the user presses Enter. The input value is clamped to `[1, totalPages]` and non-numeric input defaults to page `1`.
- Syncs the input value whenever `currentPage` changes externally (e.g. after a successful API call resets the page).

:::details Example
```jsx
const [page, setPage] = useState(1);

<Pagination
  currentPage={page}
  totalPages={totalPages}
  onPageChange={setPage}
/>
```
:::
