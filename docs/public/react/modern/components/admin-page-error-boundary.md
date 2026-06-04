---
title: AdminPageErrorBoundary
---

# AdminPageErrorBoundary

**Source:** `frontend/src/shared/components/AdminPageErrorBoundary.js`

A React class-based error boundary that catches any uncaught render error in its subtree and replaces the broken UI with a WordPress-styled error notice and a "Try again" button.

## What it does

When a child component throws during rendering, `AdminPageErrorBoundary` catches the error via `getDerivedStateFromError`, sets `hasError: true`, and renders an `@wordpress/components` Notice with `status="error"`. The notice contains a WicketButton labelled "Try again" that calls `onReset`.

The boundary also resets automatically: when `resetKey` changes while the boundary is in an error state, `componentDidUpdate` clears `hasError`. This lets a parent force a fresh render without unmounting the boundary.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `children` | `ReactNode` | Yes | The component tree to protect. |
| `onReset` | `Function` | No | Called when the user clicks "Try again". Typically increments a counter that also changes `resetKey`. |
| `resetKey` | `any` | No | When this value changes while the boundary is in error state the error is cleared and `children` re-renders. |

## Reset behaviour

The typical pattern used throughout this codebase:

:::details Example
```jsx
const [resetKey, setResetKey] = useState(0);

<AdminPageErrorBoundary
  onReset={() => setResetKey((k) => k + 1)}
  resetKey={resetKey}
>
  {/* wrap inner content in key={resetKey} to force full remount */}
  <PageContent key={resetKey} />
</AdminPageErrorBoundary>
```

`onReset` increments `resetKey`. The boundary detects the change in `componentDidUpdate` and clears `hasError`. Because `PageContent` also receives `key={resetKey}`, React unmounts and remounts it, giving a clean slate.
:::

## When to use

Wrap the outermost content area of every admin page with this component. Do not use it around individual form fields — the fallback UI is page-level, not field-level.
