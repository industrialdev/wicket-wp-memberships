---
title: WicketButton
---

# WicketButton

**Source:** `frontend/src/shared/components/WicketButton.js`

Thin wrapper around `@wordpress/components` Button that sets `type="button"` by default and adds optional dashicon rendering.

## What it does

Two additions over the raw `Button`:

1. `type` defaults to `"button"` instead of `"submit"`. This prevents accidental form submission when a button is placed inside a `<form>` tag without an explicit type.
2. `dashicon` prop renders a `<span className="dashicons dashicons-{slug}">` before the label, with a non-breaking space separator when both icon and children are present.

All other `@wordpress/components` Button props (`variant`, `disabled`, `isBusy`, `isDestructive`, `href`, `onClick`, etc.) pass through unchanged.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `children` | `ReactNode` | No | Button label or content. |
| `dashicon` | `string` | No | Dashicons icon slug (without the `dashicons-` prefix). E.g. `"edit"` renders `dashicons-edit`. |
| `type` | `string` | No | HTML button type. Defaults to `"button"`. Pass `"submit"` explicitly on submit buttons. |
| `...props` | `any` | No | All other `@wordpress/components` Button props pass through. |

:::details Example
```jsx
// Icon-only button
<WicketButton dashicon="edit" onClick={handleEdit} />

// Button with icon + label
<WicketButton dashicon="archive" variant="secondary" isDestructive>
  Archive
</WicketButton>

// Explicit submit button inside a form
<WicketButton type="submit" variant="primary" isBusy={isSaving}>
  Save
</WicketButton>
```
:::
