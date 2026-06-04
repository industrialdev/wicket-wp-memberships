---
title: IntroBlockSection
---

# IntroBlockSection

Adapter component that maps membership bundle `pageData` to the flat props expected by the shared `IntroBlock` UI component. Contains no JSX of its own beyond the `IntroBlock` invocation.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `pageData` | `object\|null` | Yes | Data returned by `fetchBundleEditPageInfo`. `null` while loading; `IntroBlock` receives empty strings in that case. |
| `isLoading` | `boolean` | Yes | Passed through directly to `IntroBlock` so it can render a loading skeleton. |

## Mapping

| `pageData` field | `IntroBlock` prop | Notes |
|---|---|---|
| `pageData.title` | `title` | Falls back to `""` when absent. |
| `pageData.org.name` | `infoFields[0].value` | Rendered with the label "Organization". |
| `pageData.bundle_mdp_link` | `actions[0].href` | Only added when truthy. Action is labelled "View in MDP" and opens in a new tab. |

::: tip
`IntroBlockSection` exists to enforce the adapter pattern: `MembershipBundleForm` and `MembershipBundlePage` work exclusively with `pageData` from the REST API, but the shared `IntroBlock` component accepts only flat, typed props. By isolating the mapping in this thin adapter, the shape of `pageData` can change without touching the shared component, and the shared component can evolve its prop API without touching the bundle feature's data layer.
:::

:::details Example

```jsx
<IntroBlockSection pageData={pageData} isLoading={isLoading} />
```

:::
