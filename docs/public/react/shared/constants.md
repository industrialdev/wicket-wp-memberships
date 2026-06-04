---
title: Constants & Utilities
---

# Constants & Utilities

This page covers three shared modules:

- `shared/constants.js` — exported constants, plugin settings, and formatting helpers
- `shared/cycleUtils.js` — membership cycle data helpers
- `shared/utils/pagination.js` — URL-based pagination state helpers

---

## Constants

### `TIER_CPT_SLUG`

```js
export const TIER_CPT_SLUG = "wicket_mship_tier";
```

The WordPress custom post type slug for membership tier posts. Used when constructing REST API paths against the WP core endpoint.

---

### `DEFAULT_DATE_FORMAT`

```js
export const DEFAULT_DATE_FORMAT = "yyyy-MM-dd";
```

The default date format string used with `react-datepicker`. Note the lowercase `yyyy` — this is the `date-fns` / `react-datepicker` token format, not `moment` format.

---

### `WC_PRODUCT_TYPES`

```js
export const WC_PRODUCT_TYPES = ["subscription", "variable-subscription"];
```

Array of WooCommerce product type slugs that are valid for use with memberships. Use this when filtering WC product queries to only show membership-compatible products.

---

### `PLUGIN_SETTINGS`

```js
export const PLUGIN_SETTINGS = wicketMembershipsSettings;
```

A reference to the global `wicketMembershipsSettings` object injected by WordPress via `wp_localize_script`. Available on every admin page that loads the plugin frontend.

::: tip
`PLUGIN_SETTINGS` is the canonical way to read server-provided configuration inside React components. Do not access `window.wicketMembershipsSettings` directly — always import from `shared/constants`.
:::

Known keys on `wicketMembershipsSettings`:

| Key | Type | Description |
|---|---|---|
| `adminUrl` | `string` | WordPress admin base URL (`/wp-admin/`) |
| `currency` | `string` | ISO 4217 currency code used by the site (e.g. `"USD"`) |
| `WICKET_MSHIP_MDP_TIMEZONE` | `string` | IANA timezone string from the MDP (e.g. `"America/Toronto"`). Falls back to `"UTC"` when absent. |

Additional keys may be present depending on context. `WP_ADMIN_URL` is also exported directly as a convenience alias: `export const WP_ADMIN_URL = wicketMembershipsSettings.adminUrl`.

---

## Formatters

### `formatCurrency(price)`

Formats a numeric price value as a localized currency string using the site's configured currency code.

| Name | Type | Required | Description |
|---|---|---|---|
| `price` | `string\|number\|null` | Yes | The price value to format |

**Returns:** A localized currency string (e.g. `"$12.00"`). Returns `"—"` when `price` is `undefined`, `null`, or an empty string. Returns the raw value unchanged when it cannot be parsed as a number.

Uses `Intl.NumberFormat` with the currency code from `PLUGIN_SETTINGS.currency`, falling back to `"USD"`.

:::details Example

```js
import { formatCurrency } from 'shared/constants';

formatCurrency(12)       // "$12.00"
formatCurrency("99.50")  // "$99.50"
formatCurrency(null)     // "—"
formatCurrency("")       // "—"
```

:::

---

### `formatDateWithTooltip(isoString)`

Formats an ISO date string as `YYYY-MM-DD` rendered inside a `<span>` element, with the full ISO 8601 string in the MDP timezone shown as a hover tooltip via the `title` attribute.

| Name | Type | Required | Description |
|---|---|---|---|
| `isoString` | `string` | Yes | An ISO 8601 date/datetime string |

**Returns:** A `<span title="<full ISO string>"><YYYY-MM-DD></span>` JSX element. Returns an empty string when `isoString` is falsy.

The display date is rendered in the timezone configured in `PLUGIN_SETTINGS.WICKET_MSHIP_MDP_TIMEZONE` (falls back to `"UTC"`). The `title` attribute contains the full ISO 8601 string including the UTC offset as formatted by `moment.tz`.

::: warning Required for all date display in the admin UI
**Always use `formatDateWithTooltip` when rendering any date field in the admin UI.** Never render a raw date string directly. Pass the original ISO string from the PHP REST response — do not pre-format dates to `Y-m-d` on the server, as that strips the timezone offset needed to correctly localise the display and populate the tooltip.
:::

:::details Example

```js
import { formatDateWithTooltip } from 'shared/constants';

// Renders: <span title="2026-10-05T00:00:00-04:00">2026-10-05</span>
formatDateWithTooltip("2026-10-05T04:00:00Z");
```

In a component:

```jsx
<td>{formatDateWithTooltip(membership.start_date)}</td>
```

:::

---

## Cycle Utilities

Exported from `shared/cycleUtils.js`. These helpers produce and normalise the cycle data object that controls membership period calculation.

### `getDefaultCycleData()`

Returns a fresh default cycle data object. Takes no parameters.

**Returns:**

```js
{
  cycle_type: "calendar",
  anniversary_data: {
    period_count: "1",
    period_type: "year",
    align_end_dates_enabled: false,
    align_end_dates_type: "first-day-of-month",
  },
  calendar_items: [],
}
```

- `cycle_type`: either `"calendar"` or `"anniversary"`.
- `anniversary_data`: controls anniversary-cycle period length and optional end-date alignment.
- `calendar_items`: array of calendar window objects; populated only when `cycle_type` is `"calendar"`.

---

### `normalizeCycleData(cycleData)`

Merges a partial or potentially incomplete cycle data object from a server response with the defaults produced by `getDefaultCycleData`, ensuring no keys are missing.

| Name | Type | Required | Description |
|---|---|---|---|
| `cycleData` | `object\|null\|undefined` | No | Cycle data from the server; safe to pass `null` or `undefined` |

**Returns:** A fully populated cycle data object. Merging strategy:

- Top-level keys from `cycleData` override the defaults.
- `anniversary_data` is merged shallowly — the defaults are spread first, then the incoming `anniversary_data` is spread on top, so partial objects are safe.
- `calendar_items` is taken from `cycleData.calendar_items` if it is an array; otherwise falls back to `[]`.

:::details Example

```js
import { normalizeCycleData } from 'shared/cycleUtils';

const raw = { cycle_type: "anniversary", anniversary_data: { period_count: "2" } };
const normalized = normalizeCycleData(raw);
// {
//   cycle_type: "anniversary",
//   anniversary_data: {
//     period_count: "2",
//     period_type: "year",          // filled in from defaults
//     align_end_dates_enabled: false,
//     align_end_dates_type: "first-day-of-month",
//   },
//   calendar_items: [],
// }
```

:::

---

## Pagination Utilities

Exported from `shared/utils/pagination.js`. These helpers read and write the current page number from the browser URL's `paged` query parameter, preserving all other existing query parameters.

### `getPagedFromUrl()`

Reads the current page number from `window.location.search`. Takes no parameters.

**Returns:** An integer `>= 1`. Returns `1` when the `paged` parameter is absent or cannot be parsed as a positive integer.

---

### `updatePageInUrl(page)`

Updates the `paged` query parameter in the browser URL using `history.replaceState` (no page reload).

| Name | Type | Required | Description |
|---|---|---|---|
| `page` | `number` | Yes | The page number to store |

All other existing query parameters are preserved. The URL is updated in place without triggering a navigation event.

:::details Example

```js
import { getPagedFromUrl, updatePageInUrl } from 'shared/utils/pagination';

// Read current page on mount
const currentPage = getPagedFromUrl(); // 1 (default)

// Update URL when user clicks a page
updatePageInUrl(3);
// → URL becomes: /wp-admin/...?paged=3
```

:::
