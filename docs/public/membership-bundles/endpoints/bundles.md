---
title: Bundles
---

# Bundles Endpoints

These endpoints handle creating bundles, retrieving bundle records, updating bundle fields, and fetching filter and breakdown data.

---

## Create a bundle

**`POST /wp-json/wicket_member/v1/bundle`**

Creates a new membership bundle post, calculates dates from the linked config, creates a WooCommerce subscription, and schedules date-trigger jobs.

### Request body

| Name | Type | Required | Description |
|---|---|---|---|
| `name` | `string` | Yes | Display name for the bundle (post title). |
| `membership_bundle_config_id` | `integer` | Yes | Post ID of the `wicket_mship_bcfg` config record. |
| `org_uuid` | `string` | Yes | MDP organisation UUID. |
| `owner_uuid` | `string` | Yes | MDP person UUID of the bundle owner. |
| `start_date` | `string` | Yes | ISO 8601 start date (e.g. `2025-01-01`). |

### Response

`200 OK`

```json
{
    "success": "Bundle created successfully.",
    "bundle_post_id": 123
}
```

### Errors

:::details Error codes
| Status | Code | Cause |
|---|---|---|
| `400` | — | Validation error (missing/invalid parameters) |
| `500` | — | Post creation or meta write failed |
:::

### Example

:::details Example
```bash
curl -X POST https://example.com/wp-json/wicket_member/v1/bundle \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: {nonce}" \
  -d '{
    "name": "Acme Corp 2025 Membership",
    "membership_bundle_config_id": 42,
    "org_uuid": "org-uuid-here",
    "owner_uuid": "person-uuid-here",
    "start_date": "2025-01-01"
  }'
```
:::

---

## Get a bundle record

**`GET /wp-json/wicket_member/v1/membership_bundle_entity`**

Returns post meta and child membership post IDs for a single bundle.

### Query parameters

| Name | Type | Required | Description |
|---|---|---|---|
| `bundle_post_id` | `integer` | Yes | Post ID of the `wicket_mship_bundle`. |

### Response

`200 OK`

```json
{
    "ID": 123,
    "bundle_group_uuid": "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
    "title": "Acme Corp 2025 Membership",
    "data": {
        "membership_status": "Active",
        "membership_status_slug": "active",
        "membership_starts_at": "01/01/2025",
        "membership_ends_at": "12/31/2025",
        "membership_expires_at": "01/30/2026",
        "membership_subscription_id": 456,
        "org_uuid": "org-uuid-here",
        "user_id": 7
    },
    "individual_members": [789, 790, 791]
}
```

`individual_members` is an array of `wicket_membership` post IDs belonging to this bundle.

### Example

:::details Example
```bash
curl "https://example.com/wp-json/wicket_member/v1/membership_bundle_entity?bundle_post_id=123" \
  -H "X-WP-Nonce: {nonce}"
```
:::

---

## Update a bundle record

**`POST /wp-json/wicket_member/v1/membership_bundle_entity/{bundle_post_id}/update`**

Updates editable fields on a bundle. Validates date ordering before writing. After a successful date update, Action Scheduler date-trigger jobs are rescheduled automatically.

### URL parameters

| Name | Type | Required | Description |
|---|---|---|---|
| `bundle_post_id` | `integer` | Yes | Post ID of the bundle to update. |

### Request body

| Name | Type | Required | Description |
|---|---|---|---|
| `membership_starts_at` | `string` | No | ISO 8601. Must be before `membership_ends_at`. |
| `membership_ends_at` | `string` | No | ISO 8601. Must not be after `membership_expires_at`. |
| `membership_expires_at` | `string` | No | ISO 8601. |
| `membership_renewal_type` | `string` | No | `"subscription"` or `"form_page"`. Changing this updates the WC subscription's `next_payment` date accordingly. |

### Response

`200 OK`

```json
{
    "success": "Bundle updated successfully."
}
```

### Errors

| Status | Cause |
|---|---|
| `400` | Date ordering is invalid (start after end, end after expires) |
| `404` | Bundle post not found |

---

## Get the bundle edit page data

**`GET /wp-json/wicket_member/v1/bundle/admin/get_edit_page_info`**

Returns the full data set for the bundle edit form: org, owner, config, subscription, order history, dates, and renewal series history.

### Query parameters

| Name | Type | Required | Description |
|---|---|---|---|
| `bundle_group_uuid` | `string` | Yes | The `membership_bundle_group_uuid` shared by all renewal-term posts in a series. This is not the bundle post ID. |

### Response

`200 OK`

```json
{
    "ID": 123,
    "title": "Acme Corp 2025 Membership",
    "bundle_group_uuid": "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
    "org": {
        "uuid": "org-uuid-here",
        "name": "Acme Corp",
        "location": "Toronto, ON",
        "mdp_link": "https://..."
    },
    "owner": {
        "user_id": 7,
        "uuid": "person-uuid-here",
        "name": "Jane Smith",
        "email": "jane@acme.com",
        "mdp_link": "https://...",
        "switch_to_url": "https://..."
    },
    "config": { ... },
    "subscription_id": 456,
    "subscription": {
        "id": 456,
        "link": "https://...",
        "status": "active",
        "next_payment_date": "2025-12-31T23:59:59+00:00"
    },
    "orders": [
        {
            "id": 100,
            "link": "https://...",
            "total": "1200.00",
            "status": "completed",
            "date_created": "2025-01-01T00:00:00+00:00",
            "type": "parent"
        }
    ],
    "dates": {
        "starts_at": "2025-01-01T00:00:00+00:00",
        "ends_at": "2025-12-31T23:59:59+00:00",
        "expires_at": "2026-01-30T23:59:59+00:00",
        "early_renew_at": "2025-12-01T23:59:59+00:00"
    },
    "statuses": { ... },
    "allowed_transitions": { ... },
    "membership_records": [
        {
            "ID": 123,
            "name": "Acme Corp 2025 Membership",
            "status": "Active",
            "starts_at": "2025-01-01T00:00:00+00:00",
            "ends_at": "2025-12-31T23:59:59+00:00",
            "expires_at": "2026-01-30T23:59:59+00:00",
            "renewal_type": "subscription",
            "next_tier_form_page_id": null
        }
    ]
}
```

`membership_records` lists one entry per bundle post in the renewal series (newest first). It does not list individual member seats. `owner.switch_to_url` is empty when the User Switching plugin is inactive.

### Errors

| Status | Cause |
|---|---|
| `404` | No bundle posts found for the given group UUID |

### Example

:::details Example
```bash
curl "https://example.com/wp-json/wicket_member/v1/bundle/admin/get_edit_page_info?bundle_group_uuid=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" \
  -H "X-WP-Nonce: {nonce}"
```
:::

---

## List bundles

**`GET /wp-json/wicket_member/v1/membership_bundles`**

Returns a paginated, filterable list of membership bundles.

### Query parameters

| Name | Type | Required | Description |
|---|---|---|---|
| `page` | `integer` | No | Page number. Default `1`. |
| `per_page` | `integer` | No | Results per page. Default `25`. |
| `status` | `string` | No | Filter by status slug, or `all`. |
| `search` | `string` | No | Search term matched against bundle title. |
| `order_col` | `string` | No | Column to sort by. |
| `order_dir` | `string` | No | `ASC` or `DESC`. |

### Response

`200 OK`

```json
{
    "posts": [
        {
            "ID": 123,
            "title": "Acme Corp 2025 Membership",
            "membership_status": "active",
            "membership_starts_at": "2025-01-01T00:00:00+00:00",
            "membership_ends_at": "2025-12-31T23:59:59+00:00",
            "org_name": "Acme Corp"
        }
    ],
    "total": 1,
    "total_pages": 1,
    "page": 1
}
```

### Example

:::details Example
```bash
curl "https://example.com/wp-json/wicket_member/v1/membership_bundles?status=active&search=Acme&page=1" \
  -H "X-WP-Nonce: {nonce}"
```
:::

---

## Get bundle filter options

**`GET /wp-json/wicket_member/v1/membership_bundle_filters`**

Returns available filter options for the bundle list view (e.g. status values, sortable columns). Used to populate filter dropdowns in the admin UI.

### Response

`200 OK`

```json
{
    "statuses": [
        { "slug": "active",   "name": "Active" },
        { "slug": "pending",  "name": "Pending" },
        { "slug": "expired",  "name": "Expired" },
        { "slug": "cancelled","name": "Cancelled" }
    ]
}
```

---

## Get member count by tier

**`GET /wp-json/wicket_member/v1/bundle/{bundle_post_id}/members_by_tier`**

Returns the total active member count for a bundle, broken down by tier.

### URL parameters

| Name | Type | Required | Description |
|---|---|---|---|
| `bundle_post_id` | `integer` | Yes | Post ID of the bundle. |

### Response

`200 OK`

```json
{
    "total_members": 12,
    "tiers": [
        {
            "tier_uuid": "abc-123",
            "tier_name": "Standard",
            "member_count": 8
        },
        {
            "tier_uuid": "def-456",
            "tier_name": "Premium",
            "member_count": 4
        }
    ]
}
```

Tiers are sorted alphabetically by `tier_name`. Members without a `tier_uuid` are excluded from the breakdown and do not count toward `total_members`.

### Errors

| Status | Cause |
|---|---|
| `404` | Bundle post not found |
