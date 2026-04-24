---
title: "OrgUuidAsyncSelect — Implementation Plan"
audience: [developer, agent]
source_files:
  - "frontend/src/shared/constants.js"
  - "frontend/src/shared/services/api.js"
  - "frontend/src/shared/components/OrgUuidAsyncSelect.js"
  - "frontend/src/shared/components/MembershipOwnerAsyncSelect.js"
related_endpoints:
  - "POST /wicket-base/v1/search-orgs"
  - "GET /wicket_member/v1/org_data"
---

# OrgUuidAsyncSelect — Implementation Plan

## Overview

A shared React component that lets a user search for and select an MDP organisation by name. On selection it surfaces the org's UUID to the parent — the org is **never imported into WordPress**. On initial load, if a UUID is already stored, the component hydrates itself by fetching the org name so the field shows a human-readable label rather than a raw UUID.

The component follows the same pattern as `MembershipOwnerAsyncSelect`: fully controlled, debounced internally, 3-character minimum, parent supplies `value` and `onChange`.

---

## No New PHP Required

Both REST endpoints this component needs already exist:

| Purpose | Endpoint | Notes |
|---------|----------|-------|
| Search orgs by name | `POST /wicket-base/v1/search-orgs` | Pass `{ searchTerm, autocomplete: true, includeMembershipSummary: false }`. Returns `wp_send_json_success($array)` so the actual data is in `response.data`. |
| Hydrate UUID → name on load | `GET /wicket_member/v1/org_data?org_uuid=<uuid>` | Returns `{ name, location }`. Backed by `Helper::get_org_data()` which caches in the WP options table. |

---

## Implementation Steps

### Step 1 — Add `BASE_PLUGIN_API_URL` constant

**File:** `frontend/src/shared/constants.js`

**Status:** Done

```js
export const BASE_PLUGIN_API_URL = "/wicket-base/v1";
```

---

### Step 2 — Add two API functions to `api.js`

**File:** `frontend/src/shared/services/api.js`

Add these two exports after the existing `fetchMdpPersons` function:

```js
import {
  API_URL,
  BASE_PLUGIN_API_URL,
  PLUGIN_API_URL,
  // ...existing imports
} from "../constants";

/**
 * Search MDP organisations by name (autocomplete, no membership summary).
 *
 * POST /wicket-base/v1/search-orgs
 * Returns wp_send_json_success shape: { success: true, data: [...] }
 *
 * @param {string} searchTerm
 * @returns {Promise<Array>} Array of org objects: { id, name, type, type_name }
 */
export const fetchSearchOrgs = (searchTerm) => {
  return apiFetch({
    path: `${BASE_PLUGIN_API_URL}/search-orgs`,
    method: "POST",
    data: {
      searchTerm,
      autocomplete: true,
      includeMembershipSummary: false,
    },
  }).then((response) => response?.data ?? []);
};

/**
 * Resolve an org UUID to its name and location.
 *
 * GET /wicket_member/v1/org_data?org_uuid=<uuid>
 * Returns { name, location }
 *
 * @param {string} orgUuid
 * @returns {Promise<{ name: string, location: string }>}
 */
export const fetchOrgByUuid = (orgUuid) => {
  return apiFetch({
    path: addQueryArgs(`${PLUGIN_API_URL}/org_data`, { org_uuid: orgUuid }),
  });
};
```

**Notes:**
- `search-orgs` uses `wp_send_json_success()` on the PHP side, so `apiFetch` receives `{ success: true, data: [...] }`. The `.then((r) => r?.data ?? [])` unwraps this.
- `org_data` returns a plain object `{ name, location }` — no unwrapping needed.

---

### Step 3 — Create `OrgUuidAsyncSelect` component

**File:** `frontend/src/shared/components/OrgUuidAsyncSelect.js`

```jsx
import { useCallback, useRef } from "react";
import { __ } from "@wordpress/i18n";
import { AsyncSelectWpStyled } from "../styled_elements";

/**
 * OrgUuidAsyncSelect — debounced async select for choosing an MDP organisation.
 *
 * Fully controlled: the parent owns the selected value and receives changes via
 * onChange. The selected option's `value` is the org UUID; `label` is the org name.
 * The org is never imported into WordPress — this component is a pure selector.
 *
 * Debounce (300 ms) and the 3-character minimum are handled internally.
 * The parent supplies a plain `(inputValue, callback) => void` load function.
 *
 * Hydration on load: if a UUID is already stored, the parent should resolve it
 * to a `{ label: orgName, value: orgUuid }` option via `fetchOrgByUuid` before
 * passing it as `value`. See usage notes below.
 *
 * @param {object|null} props.value          - Controlled selected option: { label, value }.
 * @param {object[]}    [props.defaultOptions] - Options shown before the user types.
 * @param {Function}    props.onLoadOptions  - `(inputValue, callback) => void`.
 * @param {Function}    props.onChange       - Called with the selected option on change.
 */
const OrgUuidAsyncSelect = ({
  value = null,
  defaultOptions = [],
  onLoadOptions,
  onChange,
}) => {
  const debounceTimer = useRef(null);
  const debouncedLoadOptions = useCallback(
    (inputValue, callback) => {
      if (debounceTimer.current) { clearTimeout(debounceTimer.current); }
      debounceTimer.current = setTimeout(() => {
        onLoadOptions(inputValue, callback);
      }, 300);
    },
    [onLoadOptions],
  );

  return (
    <AsyncSelectWpStyled
      id="org_uuid_selector"
      classNamePrefix="select"
      name="org_uuid"
      value={value}
      defaultOptions={defaultOptions}
      loadOptions={debouncedLoadOptions}
      isClearable={true}
      isSearchable={true}
      noOptionsMessage={({ inputValue }) =>
        inputValue.length < 3
          ? __("Type at least 3 characters to search…", "wicket-memberships")
          : __("No results found.", "wicket-memberships")
      }
      onChange={onChange}
    />
  );
};

export default OrgUuidAsyncSelect;
```

**Differences from `MembershipOwnerAsyncSelect`:**
- `id` / `name` are `org_uuid_selector` / `org_uuid`
- `isClearable` is `true` (org association can be cleared)

---

### Step 4 — Usage pattern in a parent component

The parent is responsible for:

1. **Hydration on mount** — if the form field already has an `org_uuid` value, call `fetchOrgByUuid(uuid)` and set initial `orgOption` state to `{ label: data.name, value: uuid }`.
2. **Providing `onLoadOptions`** — fetch orgs by search term and map to `{ label, value }` options.
3. **Handling `onChange`** — receive the selected option and update form state with `option.value` (the UUID).

```jsx
import { useState, useEffect, useCallback } from "react";
import OrgUuidAsyncSelect from "../../shared/components/OrgUuidAsyncSelect";
import { fetchSearchOrgs, fetchOrgByUuid } from "../../shared/services/api";

// --- Inside the parent component ---

const [orgOption, setOrgOption] = useState(null); // { label: orgName, value: orgUuid }

// Hydrate existing UUID → label on mount
useEffect(() => {
  if (!existingOrgUuid) return;
  fetchOrgByUuid(existingOrgUuid)
    .then((data) => {
      if (data?.name) {
        setOrgOption({ label: data.name, value: existingOrgUuid });
      }
    })
    .catch((err) => console.error("[OrgUuidAsyncSelect] hydration error", err));
}, [existingOrgUuid]);

// Load options as the user types
const loadOrgOptions = useCallback((inputValue, callback) => {
  if (inputValue.length < 3) return;
  fetchSearchOrgs(inputValue)
    .then((orgs) =>
      callback(orgs.map((org) => ({ label: org.name, value: org.id })))
    )
    .catch((err) => {
      console.error("[OrgUuidAsyncSelect] search error", err);
      callback([]);
    });
}, []);

// On form submit, read orgOption.value to get the UUID
const orgUuid = orgOption?.value ?? null;

// JSX
<OrgUuidAsyncSelect
  value={orgOption}
  onLoadOptions={loadOrgOptions}
  onChange={(option) => setOrgOption(option)}
/>
```

---

## API Response Shapes

### `POST /wicket-base/v1/search-orgs` (autocomplete, no membership summary)

```json
{
  "success": true,
  "data": [
    { "id": "uuid-1234", "name": "Acme Corp", "type": "business", "type_name": "Business" },
    { "id": "uuid-5678", "name": "Acme Foundation", "type": "nonprofit", "type_name": "Non-Profit" }
  ]
}
```

### `GET /wicket_member/v1/org_data?org_uuid=uuid-1234`

```json
{ "name": "Acme Corp", "location": "Toronto, Ontario, CA" }
```

---

## Checklist

| Step | Status |
|------|--------|
| Add `BASE_PLUGIN_API_URL` constant to `constants.js` | Done |
| Add `fetchSearchOrgs` to `api.js` | Pending |
| Add `fetchOrgByUuid` to `api.js` | Pending |
| Create `OrgUuidAsyncSelect.js` component | Pending |
| Wire into target parent component(s) | Pending |
