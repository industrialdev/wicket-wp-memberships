---
title: MembershipOwnerSection
---

# MembershipOwnerSection

**Source:** `frontend/src/shared/components/MembershipOwnerSection.js`

Displays and allows changing the owner of a membership record. Renders an async-select, a "Save Owner" button, an optional impersonation button, and an optional MDP link. Manages its own saving state and inline success/error feedback.

Data-agnostic — callers are responsible for mapping their page-specific data shapes to these props and for injecting the `onSave` function that calls the appropriate API endpoint.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `title` | `string` | Yes | Label shown above the select (e.g. `"Membership Bundle Owner"`). |
| `tooltipText` | `string` | Yes | Text shown in the info tooltip beside the label. |
| `ownerOption` | `object \| null` | No | Current owner as `{ label: string, value: string }`. Defaults to `null`. |
| `mdpLink` | `string \| null` | No | URL to view the current owner in MDP. Renders a "View in MDP" link when set. |
| `switchToUrl` | `string \| null` | No | Impersonation URL for the current owner. Passed to `SwitchToButton`. |
| `onLoadOptions` | `Function` | Yes | `(inputValue, callback) => void`. Async search function for the owner select. |
| `onSave` | `Function` | Yes | `(selectedOption) => Promise<{ success?, error? }>`. Called when the admin clicks "Save Owner". |

::: tip
This component is used standalone (not inside `MembershipDetailsForm`) when you need an independent owner save button with its own feedback. Use the `onLoadOwnerOptions` / `onOwnerSave` props on `MembershipDetailsForm` when the owner save should be bundled into a single submit action.
:::
