---
title: OrgUuidAsyncSelect
---

# OrgUuidAsyncSelect

**Source:** `frontend/src/shared/components/OrgUuidAsyncSelect.js`

Debounced async-select for choosing an MDP organisation by UUID. Structurally identical to `MembershipOwnerAsyncSelect` except that the selected value represents an org UUID, the field `name` is `org_uuid`, the `id` is `org_uuid_selector`, and the select is clearable.

## What it does

Renders an `AsyncSelectWpStyled` bound to `id="org_uuid_selector"` and `name="org_uuid"`. The select is searchable and clearable (the user can remove the selected org by clicking the clear indicator). Before the user has typed three characters the no-options message reads "Type at least 3 characters to search…"; after typing three or more characters with no results it reads "No results found."

The 300 ms debounce is managed internally via a `useRef` timer.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `value` | `{ label: string, value: string } \| null` | No | Controlled selected option. The `value` field must be the MDP organisation UUID. The `label` field is the org name. Defaults to `null`. |
| `defaultOptions` | `Array<{ label: string, value: string }>` | No | Options shown before the user types. Defaults to `[]`. |
| `onLoadOptions` | `Function` | Yes | `(inputValue: string, callback: (options) => void) => void`. Called after the debounce delay with the current search string. The callback must be invoked with the resolved options array. |
| `onChange` | `Function` | Yes | Called with the full selected option `{ label, value }` or `null` when the selection is cleared. |

## Differences from MembershipOwnerAsyncSelect

| Aspect | OrgUuidAsyncSelect | MembershipOwnerAsyncSelect |
|---|---|---|
| `id` attribute | `org_uuid_selector` | `membership_owner_id` |
| `name` attribute | `org_uuid` | `membership_owner_uuid` |
| Clearable | Yes | No |
| Semantic purpose | MDP organisation | MDP person |

:::details Example
```jsx
const loadOrgOptions = useCallback((inputValue, callback) => {
  if (inputValue.length < 3) {
    callback([]);
    return;
  }
  apiFetch({
    path: addQueryArgs('/wicket-memberships/v1/organizations', { search: inputValue }),
  }).then((results) => {
    callback(
      results.map((org) => ({ label: org.name, value: org.uuid }))
    );
  });
}, []);

<OrgUuidAsyncSelect
  value={orgOption}
  defaultOptions={orgOption ? [orgOption] : []}
  onLoadOptions={loadOrgOptions}
  onChange={(selected) => setOrgOption(selected)}
/>
```
:::

::: tip
Because this select is clearable, `onChange` may be called with `null` when the user clears the field. Guard against `null` in your handler if the parent state does not accept it.
:::
