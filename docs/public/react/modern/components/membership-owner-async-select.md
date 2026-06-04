---
title: MembershipOwnerAsyncSelect
---

# MembershipOwnerAsyncSelect

**Source:** `frontend/src/shared/components/MembershipOwnerAsyncSelect.js`

Debounced async-select for choosing a membership owner by MDP person UUID. Fully controlled — the parent owns the selected value and all data fetching logic. The component contributes a 300 ms debounce so callers supply a plain callback-style load function.

## What it does

Renders an `AsyncSelectWpStyled` (React Select async variant) bound to `id="membership_owner_id"` and `name="membership_owner_uuid"`. The select is always searchable and not clearable. Before the user has typed three characters the no-options message reads "Type at least 3 characters to search…"; once three or more characters have been typed and no results are found it reads "No results found."

The 300 ms debounce is managed internally via a `useRef` timer. Each new keystroke clears the pending timer and starts a fresh one.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `value` | `{ label: string, value: string } \| null` | No | Controlled selected option. The `value` field must be the MDP person UUID. Defaults to `null`. |
| `defaultOptions` | `Array<{ label: string, value: string }>` | No | Options shown before the user types (e.g., the currently saved owner pre-loaded by the parent). Defaults to `[]`. |
| `onLoadOptions` | `Function` | Yes | `(inputValue: string, callback: (options) => void) => void`. Called after the debounce delay with the current search string. The callback must be invoked with the resolved options array. |
| `onChange` | `Function` | Yes | Called with the full selected option object `{ label, value }` when the user picks a result. |

## Debounce

The 300 ms debounce is applied internally. Do not add a secondary debounce in `onLoadOptions` — this will compound the delay.

:::details Example
```jsx
const loadOwnerOptions = useCallback((inputValue, callback) => {
  if (inputValue.length < 3) {
    callback([]);
    return;
  }
  apiFetch({
    path: addQueryArgs('/wicket-memberships/v1/people', { search: inputValue }),
  }).then((results) => {
    callback(
      results.map((p) => ({ label: p.full_name, value: p.uuid }))
    );
  });
}, []);

<MembershipOwnerAsyncSelect
  value={ownerOption}
  defaultOptions={ownerOption ? [ownerOption] : []}
  onLoadOptions={loadOwnerOptions}
  onChange={setOwnerOption}
/>
```
:::

::: tip
Populate `defaultOptions` with the currently saved owner as a single-element array so the select renders the saved name immediately on page load, before the user interacts with it.
:::
