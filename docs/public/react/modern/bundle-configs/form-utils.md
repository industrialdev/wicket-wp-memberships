---
title: Form Utilities
---

# Form Utilities

`frontend/src/membership_bundle_configs/utils/formUtils.js`

Provides form initialization, API normalization, payload building, and error handling for the bundle config UI. Also re-exports `getDefaultCycleData` and `normalizeCycleData` from `shared/cycleUtils`.

## normalizeBundleConfigPostToForm

Converts a raw REST API response for a bundle config post into the form state shape expected by `BundleConfigForm`.

### Params

| Name | Type | Required | Description |
|---|---|---|---|
| `post` | `object` | Yes | Raw post object returned by the bundle config REST endpoint. |
| `languageCodes` | `string[]` | No | Active language codes. Defaults to `[]`. Used to ensure all locale keys are present in the returned form. |

### Return shape

Returns an object matching the `BundleConfigForm` form shape:

```ts
{
  name: string;                  // HTML-decoded post title
  renewal_window_data: {
    days_count: string;
    locales: Record<string, LocaleFields>;
  };
  late_fee_window_data: {
    days_count: string;
    product_id: string;
    locales: Record<string, LocaleFields>;
  };
  cycle_data: object;            // Normalized via normalizeCycleData
  bundle_config_data: {
    renewal_type: "subscription" | "form_page";
    renewal_form_page_id: string;
    approval_required: boolean;
    grant_owner_assignment: boolean;
    approval_email_recipient: string;
    approval_callout_data: { locales: Record<string, LocaleFields> };
  };
}
```

Locale merging ensures that if the saved record is missing a locale key (e.g. a new language was added after the record was created), the default empty locale fields are used rather than leaving the key absent.

Boolean coercion: `approval_required` and `grant_owner_assignment` accept `true`, `1`, or `"1"` from the API and normalize to `boolean`.

## validateBundleConfigFormData

::: warning
There is no standalone `validateBundleConfigFormData` export in `formUtils.js`. Validation logic lives inline in `BundleConfigPage` (`validateForm`) and checks that `form.name` is non-empty. If a shared validator is needed, add it to this file.
:::

## buildBundleConfigPayload

Converts the form state into the payload sent to the REST API on save.

### Params

| Name | Type | Required | Description |
|---|---|---|---|
| `form` | `object` | Yes | Current form state as managed by `BundleConfigForm`. |

### Return shape

```ts
{
  title: string;
  status: "publish";
  renewal_window_data: object;
  late_fee_window_data: object;
  cycle_data: object;
  bundle_config_data: {
    ...form.bundle_config_data,
    renewal_form_page_id: number;   // 0 when renewal_type !== "form_page"
    approval_required: boolean;
    grant_owner_assignment: boolean;
  };
}
```

`renewal_form_page_id` is coerced to an integer and set to `0` when `renewal_type` is not `"form_page"`.

## normalizeApiErrors

Extracts a flat array of error message strings from a REST API error object.

### Params

| Name | Type | Required | Description |
|---|---|---|---|
| `error` | `object` | Yes | The caught error from `apiFetch`. |
| `fallbackMessage` | `string` | No | Message to return when no structured error is found. Defaults to a generic "Something went wrong" string. |

### Validation rules / priority

1. If `error.data.params` exists, each param value is split on sentence boundaries and collected into the array.
2. If `error.message` exists, it is returned as a single-element array.
3. Otherwise, the `fallbackMessage` is returned as a single-element array.

## getPrimaryErrorMessage

Returns only the first message from `normalizeApiErrors`. Used in `BundleConfigPage` to populate the record-load error notice.

### Params

| Name | Type | Required | Description |
|---|---|---|---|
| `error` | `object` | Yes | The caught error object. |
| `fallbackMessage` | `string` | Yes | Fallback string if no message is found. |

## Locale helpers

| Function | Description |
|---|---|
| `createDefaultLocales(languageCodes)` | Creates a locale map keyed by language code, each with empty `callout_header`, `callout_content`, `callout_button_label` fields. |
| `mergeLocales(defaultLocales, incomingLocales)` | Merges saved locale data over the defaults, ensuring no key is missing. |
| `mergeCalloutData(defaultLocales, calloutData)` | Merges locale data inside a callout data object (`{ locales: ... }`). |

## Other exports

| Export | Description |
|---|---|
| `createDefaultForm(languageCodes)` | Builds the initial empty form state with all locale keys populated. |
| `createEmptySeason()` | Returns a blank season object `{ season_name, active, start_date, end_date }` for the SeasonConfigModal. |
| `findOptionByValue(options, value)` | Finds an option in a `{ value, ... }[]` array by loose string equality. |
| `getDefaultCycleData` | Re-exported from `shared/cycleUtils`. |
| `normalizeCycleData` | Re-exported from `shared/cycleUtils`. |

:::details Example — building a payload

```js
import { buildBundleConfigPayload } from '../utils/formUtils';

const payload = buildBundleConfigPayload(form);
await apiFetch({ path: endpoint, method: 'POST', data: payload });
```
:::
