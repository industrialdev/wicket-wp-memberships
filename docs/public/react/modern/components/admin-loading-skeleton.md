---
title: AdminLoadingSkeleton
---

# AdminLoadingSkeleton

**Source:** `frontend/src/shared/components/AdminLoadingSkeleton.js`

An animated shimmer placeholder that mirrors the layout of the real content while data is loading. Uses a CSS `@keyframes` shimmer animation on grey gradient blocks.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `label` | `string` | Yes | Screen-reader label applied to the skeleton label element (`aria-label`). Also used as a key prefix for child elements. |
| `boxed` | `boolean` | No | When `true` (default), wraps the skeleton in a `BorderedBox`. When `false`, wraps in a `FormFlex` instead. |
| `variant` | `string` | No | Preset name controlling the row/column layout. Defaults to `"singleField"`. See table below. |

## Available variants

| Variant | Layout |
|---|---|
| `singleField` | One full-width block |
| `fieldWithAction` | Two columns: `1fr` + `180px` |
| `multiField` | Three columns: `1fr 1fr 180px` |
| `cycle` | Four rows matching the bundle config cycle section |
| `introBlock` | Two rows matching the IntroBlock header layout |
| `membershipTable` | Four rows matching a membership records table (header + 3 data rows) |

## When to use

Render this component in place of a section while its data is still loading. Pass `isLoading` from the bootstrap hook as the condition:

:::details Example
```jsx
if (isLoading) {
  return (
    <AdminLoadingSkeleton
      label="Membership Records"
      variant="membershipTable"
    />
  );
}
```
:::

The skeleton is `aria-hidden="true"` so screen readers skip it. The `label` prop is attached to the decorative label element only as `aria-label` — it is not announced unless the element has a role that supports it.
