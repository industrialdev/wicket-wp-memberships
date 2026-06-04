---
title: Bundle Config Dates
---

# Bundle Config Dates Endpoint

This endpoint calculates membership dates from a bundle config record. Use it to preview what dates a bundle will receive before creating it, or to calculate renewal dates without instantiating the config class directly.

---

## Calculate membership dates

**`GET /wp-json/wicket_member/v1/bundle_config/{id}/membership_dates`**

Returns the calculated `starts_at`, `ends_at`, `expires_at`, and `early_renew_at` dates for a given config, optionally anchored to a specific start date or existing membership for renewal calculations.

### URL parameters

| Name | Type | Required | Description |
|---|---|---|---|
| `id` | `integer` | Yes | Post ID of the `wicket_mship_bcfg` config record. |

### Query parameters

| Name | Type | Required | Description |
|---|---|---|---|
| `membership` | `array` | No | Optional membership data to inform the date calculation. See below. |

#### The `membership` parameter

Pass this to change how dates are anchored:

| Name | Type | Required | Description |
|---|---|---|---|
| `membership_ends_at` | `string` | No | ISO 8601 end date of the current membership term. When provided, date calculations treat this as a renewal — `starts_at` will be the day after this date. |
| `start_date` | `string` | No | ISO 8601 date to use as the membership start. When provided (without `membership_ends_at`), all calculations are anchored to this date instead of today. |

When `membership` is omitted or empty, dates are calculated anchored to today.

### Response

`200 OK`

```json
{
    "starts_at":      "2025-01-01T00:00:00+00:00",
    "ends_at":        "2025-12-31T23:59:59+00:00",
    "expires_at":     "2026-01-30T23:59:59+00:00",
    "early_renew_at": "2025-12-01T23:59:59+00:00"
}
```

All dates are ISO 8601 in UTC. `expires_at` and `early_renew_at` are empty strings when the config has no grace period or renewal window configured.

### Errors

| Status | Cause |
|---|---|
| `404` | Config post not found or wrong CPT |

### Examples

**Dates for a new membership starting today:**

:::details Example
```bash
curl "https://example.com/wp-json/wicket_member/v1/bundle_config/42/membership_dates" \
  -H "X-WP-Nonce: {nonce}"
```
:::

**Dates for a new membership with a specific start date:**

:::details Example
```bash
curl "https://example.com/wp-json/wicket_member/v1/bundle_config/42/membership_dates?membership[start_date]=2025-06-01" \
  -H "X-WP-Nonce: {nonce}"
```
:::

**Renewal dates (starting the day after an existing term ends):**

:::details Example
```bash
curl "https://example.com/wp-json/wicket_member/v1/bundle_config/42/membership_dates?membership[membership_ends_at]=2025-12-31" \
  -H "X-WP-Nonce: {nonce}"
```
:::

### PHP equivalent

The same calculation is available directly in PHP without an HTTP round-trip:

```php
$config = new \Wicket_Memberships\Membership_Bundle_Config( 42 );

// New membership starting today
$dates = $config->get_membership_dates();

// Renewal dates
$dates = $config->get_membership_dates([
    'membership_ends_at' => '2025-12-31',
]);

// New membership with specific start date
$dates = $config->get_membership_dates([
    'start_date' => '2025-06-01',
]);
```

See [`Membership_Bundle_Config::get_membership_dates()`](../classes/membership-bundle-config.md#get_membership_dates) for full documentation.
