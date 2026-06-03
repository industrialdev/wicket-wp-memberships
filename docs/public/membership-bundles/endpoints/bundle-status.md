# Bundle Status Endpoints

These endpoints manage bundle status transitions, cancellation, and renewal orders.

For a full explanation of the status model and what each transition does, see [Bundle Lifecycle](../concepts/bundle-lifecycle.md).

---

## [Get available status transitions](#get-available-status-transitions)

**`GET /wp-json/wicket_member/v1/bundle/admin/status_options`**

Returns status options. When `bundle_post_id` is provided, returns only the transitions valid from the bundle's current status. When omitted, returns all status names.

### [Query parameters](#query-parameters)

| Parameter | Type | Required | Description |
|---|---|---|---|
| `bundle_post_id` | `integer` | No | Post ID of the bundle. When provided, filters to valid transitions only. |

### [Response](#response)

`200 OK` — all statuses:

```json
{
    "pending":     "Pending",
    "active":      "Active",
    "delayed":     "Delayed",
    "grace-period":"Grace Period",
    "expired":     "Expired",
    "cancelled":   "Cancelled"
}
```

`200 OK` — with `bundle_post_id` for a `pending` bundle:

```json
{
    "active":    { "name": "Active",    "slug": "active" },
    "cancelled": { "name": "Cancelled", "slug": "cancelled" }
}
```

An empty object is returned when the bundle is in a terminal status (`expired` or `cancelled`).

---

## [Transition a bundle to a new status](#transition-a-bundle-to-a-new-status)

**`POST /wp-json/wicket_member/v1/bundle/admin/manage_status`**

Executes a status transition on a bundle. Applies lifecycle rules, recalculates dates where applicable, activates the WooCommerce subscription on `pending → active`, and cascades the new status to all child individual memberships.

### [Request body](#request-body)

| Parameter | Type | Required | Description |
|---|---|---|---|
| `bundle_post_id` | `integer` | Yes | Post ID of the bundle to transition. |
| `status` | `string` | Yes | Target status slug (e.g. `"active"`, `"cancelled"`). |

### [Response](#response)

`200 OK`

```json
{
    "success": "Bundle status updated to active.",
    "bypassed": false
}
```

`bypassed` is `true` only when the `BYPASS_STATUS_CHANGE_LOCKOUT` environment flag is set (development/testing only).

### [Errors](#errors)

| Status | Cause |
|---|---|
| `400` | Transition is not valid from the current status |
| `404` | Bundle post not found |

### [Example](#example)

Activate a pending bundle:

```bash
curl -X POST "https://example.com/wp-json/wicket_member/v1/bundle/admin/manage_status" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: {nonce}" \
  -d '{
    "bundle_post_id": 123,
    "status": "active"
  }'
```

---

## [Cancel a bundle](#cancel-a-bundle)

**`POST /wp-json/wicket_member/v1/bundle/{bundle_post_id}/cancel`**

Cancels a bundle with configurable member handling and timing. Three distinct paths are available.

### [URL parameters](#url-parameters)

| Parameter | Type | Required | Description |
|---|---|---|---|
| `bundle_post_id` | `integer` | Yes | Post ID of the bundle to cancel. |

### [Request body](#request-body)

| Parameter | Type | Required | Description |
|---|---|---|---|
| `member_handling` | `string` | Yes | `"cancel_all"` — cancel all member seats. `"keep_as_individual"` — convert all seats to standalone memberships. |
| `timing` | `string` | Conditional | Required when `member_handling` is `"cancel_all"`. `"immediately"` — hard cancel now. `"at_end_date"` — preserve member access until `ends_at`. |

### [Cancellation paths](#cancellation-paths)

**Path A — `cancel_all` + `immediately`**

All child memberships are cancelled immediately. Dates are collapsed to now. The WooCommerce subscription is hard-cancelled.

**Path B — `cancel_all` + `at_end_date`**

Bundle status becomes `cancelled` but existing `ends_at` is preserved. Child memberships retain their `active` status and members keep access until the original end date. The subscription is set to `pending-cancel` and a deferred job hard-cancels it at `ends_at`. No manual follow-up is required.

**Path C — `keep_as_individual`**

Each active bundle member is converted to a standalone individual membership. The released membership inherits the bundle's remaining `ends_at`, `expires_at`, and `early_renew_at`. Each member receives their own WooCommerce order and subscription. The bundle is then cancelled.

### [Response](#response)

`200 OK` — Path A or B:

```json
{
    "success": "Bundle cancelled successfully."
}
```

`200 OK` — Path C (may include warnings for individual members that could not be converted):

```json
{
    "success": "Bundle cancelled and members converted to individual memberships.",
    "warnings": [
        "Could not resolve user for membership 456."
    ]
}
```

### [Errors](#errors)

| Status | Cause |
|---|---|
| `400` | Invalid `member_handling` or `timing` value |
| `400` | Path B requested but bundle has no `ends_at` date |
| `400` | Transition not valid from current status |
| `404` | Bundle post not found |

### [Examples](#examples)

**Cancel immediately:**

```bash
curl -X POST "https://example.com/wp-json/wicket_member/v1/bundle/123/cancel" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: {nonce}" \
  -d '{
    "member_handling": "cancel_all",
    "timing": "immediately"
  }'
```

**Cancel at end of term:**

```bash
curl -X POST "https://example.com/wp-json/wicket_member/v1/bundle/123/cancel" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: {nonce}" \
  -d '{
    "member_handling": "cancel_all",
    "timing": "at_end_date"
  }'
```

**Convert members to standalone and cancel:**

```bash
curl -X POST "https://example.com/wp-json/wicket_member/v1/bundle/123/cancel" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: {nonce}" \
  -d '{
    "member_handling": "keep_as_individual"
  }'
```

---

## [Create a renewal order](#create-a-renewal-order)

**`POST /wp-json/wicket_member/v1/bundle/{bundle_post_id}/create_renewal_order`**

Creates a WooCommerce renewal order for the bundle's linked subscription. Use this to manually trigger a renewal payment when automatic subscription renewal is not configured or when a manual renewal order is needed.

### [URL parameters](#url-parameters)

| Parameter | Type | Required | Description |
|---|---|---|---|
| `bundle_post_id` | `integer` | Yes | Post ID of the bundle. |

### [Request body](#request-body)

| Parameter | Type | Required | Description |
|---|---|---|---|
| `product_id` | `integer` | Yes | WC product ID to include in the renewal order. |
| `variation_id` | `integer` | No | WC variation ID. Overrides `product_id` when provided. |

### [Response](#response)

`200 OK`

```json
{
    "order_id": 500,
    "order_url": "https://example.com/wp-admin/post.php?post=500&action=edit"
}
```

### [Errors](#errors)

| Status | Cause |
|---|---|
| `404` | Bundle post not found or no subscription linked |
| `400` | Renewal order creation failed |

### [Example](#example)

```bash
curl -X POST "https://example.com/wp-json/wicket_member/v1/bundle/123/create_renewal_order" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: {nonce}" \
  -d '{
    "product_id": 200
  }'
```
