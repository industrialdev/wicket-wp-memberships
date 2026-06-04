---
title: AdminNoticeStack
---

# AdminNoticeStack

**Source:** `frontend/src/shared/components/AdminNoticeStack.js`

Renders an ordered list of WordPress-style notices from an array of notice objects. Returns `null` when the array is empty.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `notices` | `Notice[]` | No | Array of notice objects to render. Defaults to `[]`. |

## Notice object shape

Each element of the `notices` array must conform to this shape:

| Field | Type | Required | Description |
|---|---|---|---|
| `id` | `string` | Yes | Unique key used by React. |
| `status` | `string` | No | `"warning"` (default), `"success"`, `"error"`, or `"info"`. Maps directly to the `@wordpress/components` Notice `status` prop. |
| `message` | `ReactNode` | Yes | Notice body content. Accepts strings or JSX. |
| `onDismiss` | `Function` | No | When provided the notice renders a dismiss button and calls this function when clicked. When omitted the notice is not dismissible. |
| `action` | `object` | No | Optional inline action: `{ label: string, onClick: Function }`. Renders a `WicketButton` with `variant="link"` below the message. |

:::details Example
```js
const notices = [
  {
    id: "load-error",
    status: "warning",
    message: "Data could not be loaded.",
    action: {
      label: "Retry",
      onClick: retryLoad,
    },
  },
  {
    id: "saved",
    status: "success",
    message: "Bundle saved successfully.",
    onDismiss: () => setSavedNotice(null),
  },
];

<AdminNoticeStack notices={notices} />
```
:::

## When to use

Place `AdminNoticeStack` at the top of a page content area, above the main form. Accumulate notices in a state array in the page component and pass them down. This keeps notice logic centralized and avoids per-component inline error banners.
