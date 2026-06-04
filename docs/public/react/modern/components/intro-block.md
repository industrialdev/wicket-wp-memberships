---
title: IntroBlock
---

# IntroBlock

**Source:** `frontend/src/shared/components/IntroBlock.js`

Header block rendered at the top of membership entity detail pages. Displays the entity name, action buttons, and a row of metadata fields. Data-agnostic — adapter components in each page's `components/` folder handle the mapping from page-specific data shapes to these flat props.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `title` | `string` | No | Primary heading text (e.g. bundle name, org name). Defaults to `""`. |
| `infoFields` | `InfoField[]` | No | Metadata items rendered in the RecordTopInfo bar. Defaults to `[]`. |
| `actions` | `Action[]` | No | Action buttons rendered to the right of the title. Defaults to `[]`. |
| `isLoading` | `boolean` | No | When `true`, renders an `AdminLoadingSkeleton` with the `introBlock` variant instead of the real content. Defaults to `false`. |

## `infoFields` array shape

Each element:

| Field | Type | Description |
|---|---|---|
| `label` | `string` | Bold label shown before the value (e.g. `"Organization"`). |
| `value` | `string \| ReactNode` | Value displayed after the label. Falls back to `"-"` when falsy. |

## `actions` array shape

Each element renders as a secondary `@wordpress/components` Button:

| Field | Type | Description |
|---|---|---|
| `label` | `string` | Button text. |
| `href` | `string` | Link URL. Button is disabled when this is falsy. |
| `target` | `string` | Link target (e.g. `"_blank"`). |
| `icon` | `string` | Optional dashicons icon slug rendered before the label (uses `@wordpress/components` `Icon`). |

:::details Example
```jsx
<IntroBlock
  title="Acme Corp Membership Bundle"
  infoFields={[
    { label: "Organization", value: "Acme Corp" },
    { label: "Status", value: "active" },
  ]}
  actions={[
    {
      label: "View in MDP",
      href: "https://mdp.example.com/...",
      target: "_blank",
      icon: "external",
    },
  ]}
  isLoading={isLoading}
/>
```
:::
