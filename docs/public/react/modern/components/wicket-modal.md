---
title: WicketModal
---

# WicketModal

**Source:** `frontend/src/shared/components/WicketModal.js`

Standard modal wrapper for the Wicket Memberships admin UI. Wraps `@wordpress/components` Modal via the `ModalStyled` styled element and enforces a max-width of `840px`.

## What it does

When `isOpen` is `false`, returns `null` — no DOM node is rendered. When `isOpen` is `true`, renders the modal with `maxWidth: 840px` and `width: 100%` injected via the `style` prop.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `isOpen` | `boolean` | Yes | Controls whether the modal is visible. When `false` the component renders nothing. |
| `title` | `string` | Yes | Modal header title. Required by the underlying `@wordpress/components` Modal. |
| `onRequestClose` | `Function` | Yes | Called when the modal requests to close (Escape key, click outside, or close button). |
| `children` | `ReactNode` | Yes | Modal body content. |
| `...rest` | `any` | No | Any additional props are forwarded to the underlying `ModalStyled` / `Modal`. Use this to pass `shouldCloseOnClickOutside={false}` when needed. |

:::details Example
```jsx
<WicketModal
  isOpen={isModalOpen}
  title="Change Status"
  onRequestClose={() => setIsModalOpen(false)}
>
  <p>Modal content here.</p>
</WicketModal>
```
:::

::: tip
Pass `shouldCloseOnClickOutside={false}` via `...rest` on destructive action modals (cancel, delete) to prevent accidental dismissal.
:::
