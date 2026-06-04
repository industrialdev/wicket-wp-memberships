---
title: MembershipRenewalTypeSection
---

# MembershipRenewalTypeSection

**Source:** `frontend/src/shared/components/MembershipRenewalTypeSection.js`

Renewal type selector with conditional sub-fields. Used on individual membership, org membership, and membership bundle record pages. The component manages its own async data for pages and tier lists; all field values are owned by the caller and received via `onChange`.

## What it does

Renders a labelled `react-select` dropdown for Renewal Type. When the selected type is `form_flow`, a `ModalPostSelector` for choosing a WordPress page appears below. When the type is `sequential_logic`, a `ModalPostSelector` for choosing a membership tier appears instead. No sub-field is shown for other renewal types.

When `renewalType` is `inherited` and `tierRenewalType` is provided, a hint line below the dropdown shows the inherited renewal type label resolved from the known option list.

## Available renewal types

| Value | Label | Notes |
|---|---|---|
| `inherited` | Inherited from Tier | Shows the inherited type as a hint. No sub-field. |
| `sequential_logic` | Sequential Logic | Shows the Sequential Tier selector. |
| `form_flow` | Renewal Form Flow | Shows the Form Page selector. |
| `subscription` | Subscription Renewal | No sub-field. |
| `current_tier` | Current Tier | No sub-field. |

### Restricting available options

Pass `BUNDLE_RENEWAL_TYPE_OPTIONS` (exported from this module) as `allowedRenewalTypes` when using the component inside bundle membership contexts. Bundles support only `subscription` and `form_flow`.

```js
import MembershipRenewalTypeSection, {
  BUNDLE_RENEWAL_TYPE_OPTIONS,
} from 'shared/components/MembershipRenewalTypeSection';
```

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `renewalType` | `string \| null` | No | Current renewal type value. Defaults to `null`. |
| `tierRenewalType` | `string \| null` | No | Renewal type from the parent tier/config, shown as a hint when `renewalType === 'inherited'`. Accepts both `RENEWAL_TYPE_OPTIONS` values and config-level values (`subscription`, `form_page`). Defaults to `null`. |
| `nextTierFormPageId` | `number \| null` | No | Currently selected form page ID. Used when `renewalType === 'form_flow'`. Defaults to `null`. |
| `nextTierId` | `number \| null` | No | Currently selected sequential tier ID. Used when `renewalType === 'sequential_logic'`. Defaults to `null`. |
| `disabled` | `boolean` | No | Disables all inputs. Defaults to `false`. |
| `allowedRenewalTypes` | `string[] \| null` | No | When provided, only option values in this array are shown in the dropdown. Use `BUNDLE_RENEWAL_TYPE_OPTIONS` for bundle memberships. Defaults to `null` (all options shown). |
| `onChange` | `Function` | Yes | Called with a patch object on any field change. See patch shape below. |

### `onChange` patch object shape

`onChange` is always called with a flat patch object. Merge it into your save payload:

| Change | Patch shape |
|---|---|
| Renewal type changed | `{ renewalType: string }` |
| Form page selected | `{ nextTierFormPageId: number \| null }` |
| Sequential tier selected | `{ nextTierId: number \| null }` |

## Data loading

- **Form pages** are fetched from the WP REST API (`/wp/v2/pages`) filtered to `status=publish`. Loaded lazily via `ModalPostSelector` on first open of the page picker modal.
- **Membership tiers** are fetched via `fetchTiers()` from `shared/services/api`. Also loaded lazily via `ModalPostSelector`.

Both `loadOptions` callbacks are memoised with `useCallback` and are stable across renders.

:::details Example
```jsx
const [renewalType, setRenewalType] = useState('inherited');
const [nextTierFormPageId, setNextTierFormPageId] = useState(null);
const [nextTierId, setNextTierId] = useState(null);

<MembershipRenewalTypeSection
  renewalType={renewalType}
  tierRenewalType={tierConfig.renewal_type}
  nextTierFormPageId={nextTierFormPageId}
  nextTierId={nextTierId}
  disabled={isSaving}
  onChange={(patch) => {
    if ('renewalType'        in patch) setRenewalType(patch.renewalType);
    if ('nextTierFormPageId' in patch) setNextTierFormPageId(patch.nextTierFormPageId);
    if ('nextTierId'         in patch) setNextTierId(patch.nextTierId);
  }}
/>
```
:::

::: tip
When `renewalType` changes away from `form_flow` or `sequential_logic`, the previously selected sub-field value is not automatically cleared by this component. Clear `nextTierFormPageId` or `nextTierId` in your `onChange` handler when `renewalType` changes to avoid stale values in the save payload.
:::
