---
title: Bundle Status
---

# Bundle Status Endpoints

These endpoints manage bundle status transitions, cancellation, and renewal orders.

For a full explanation of the status model and what each transition does, see [Bundle Lifecycle](../concepts/bundle-lifecycle.md).

---

## Get available status transitions

**`GET /wp-json/wicket_member/v1/bundle/admin/status_options`**

Returns status options. When `bundle_post_id` is provided, returns only the transitions valid from the bundle's current status. When omitted, returns all status names.

### Query parameters

| Name | Type | Required | Description |
|---|---|---|---|
| `bundle_post_id` | `integer` | No | Post ID of the bundle. When provided, filters to valid transitions only. |

### Response

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

## Transition a bundle to a new status

**`POST /wp-json/wicket_member/v1/bundle/admin/manage_status`**

Executes a status transition on a bundle. Applies lifecycle rules, recalculates dates where applicable, activates the WooCommerce subscription on `pending → active`, and cascades the new status to all child individual memberships.

### Request body

| Name | Type | Required | Description |
|---|---|---|---|
| `bundle_post_id` | `integer` | Yes | Post ID of the bundle to transition. |
| `status` | `string` | Yes | Target status slug (e.g. `"active"`, `"cancelled"`). |

### Response

`200 OK`

```json
{
    "success": "Bundle status updated to active.",
    "bypassed": false
}
```

`bypassed` is `true` only when the `BYPASS_STATUS_CHANGE_LOCKOUT` environment flag is set (development/testing only).

### Errors

| Status | Cause |
|---|---|
| `400` | Transition is not valid from the current status |
| `404` | Bundle post not found |

### Example

Activate a pending bundle:

:::details Example
```bash
curl -X POST "https://example.com/wp-json/wicket_member/v1/bundle/admin/manage_status" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: {nonce}" \
  -d '{
    "bundle_post_id": 123,
    "status": "active"
  }'
```
:::

---

## Cancel a bundle

**`POST /wp-json/wicket_member/v1/bundle/{bundle_post_id}/cancel`**

Cancels a bundle with configurable member handling and timing. Three distinct paths are available.

### URL parameters

| Name | Type | Required | Description |
|---|---|---|---|
| `bundle_post_id` | `integer` | Yes | Post ID of the bundle to cancel. |

### Request body

| Name | Type | Required | Description |
|---|---|---|---|
| `member_handling` | `string` | Yes | `"cancel_all"` — cancel all member seats. `"keep_as_individual"` — convert all seats to standalone memberships. |
| `timing` | `string` | Conditional | Required when `member_handling` is `"cancel_all"`. `"immediately"` — hard cancel now. `"at_end_date"` — preserve member access until `ends_at`. |

### Cancellation paths

**Path A — `cancel_all` + `immediately`**

All child memberships are cancelled immediately. Dates are collapsed to now. The WooCommerce subscription is hard-cancelled.

**Path B — `cancel_all` + `at_end_date`**

Bundle status becomes `cancelled` but existing `ends_at` is preserved. Child memberships retain their `active` status and members keep access until the original end date. The subscription is set to `pending-cancel` and a deferred job hard-cancels it at `ends_at`. No manual follow-up is required.

**Path C — `keep_as_individual`**

Each active bundle member is converted to a standalone individual membership. The released membership inherits the bundle's remaining `ends_at`, `expires_at`, and `early_renew_at`. Each member receives their own WooCommerce order and subscription. The bundle is then cancelled.

### Response

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

### Errors

| Status | Cause |
|---|---|
| `400` | Invalid `member_handling` or `timing` value |
| `400` | Path B requested but bundle has no `ends_at` date |
| `400` | Transition not valid from current status |
| `404` | Bundle post not found |

### Examples

**Cancel immediately:**

:::details Example
```bash
curl -X POST "https://example.com/wp-json/wicket_member/v1/bundle/123/cancel" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: {nonce}" \
  -d '{
    "member_handling": "cancel_all",
    "timing": "immediately"
  }'
```
:::

**Cancel at end of term:**

:::details Example
```bash
curl -X POST "https://example.com/wp-json/wicket_member/v1/bundle/123/cancel" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: {nonce}" \
  -d '{
    "member_handling": "cancel_all",
    "timing": "at_end_date"
  }'
```
:::

**Convert members to standalone and cancel:**

:::details Example
```bash
curl -X POST "https://example.com/wp-json/wicket_member/v1/bundle/123/cancel" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: {nonce}" \
  -d '{
    "member_handling": "keep_as_individual"
  }'
```
:::

---

## Create a renewal order

**`POST /wp-json/wicket_member/v1/bundle/{bundle_post_id}/create_renewal_order`**

Queues background creation of a WooCommerce renewal order for the bundle's linked subscription (Milestone 10). Use this to manually trigger a renewal payment when automatic subscription renewal is not configured or when a manual renewal order is needed.

The order is not created synchronously in the request — `wcs_create_renewal_order()` plus Milestone 9's per-member repricing can take several seconds for a bundle with 100+ members, well past what a proxy or browser will hold an HTTP request open for. The endpoint instead validates the bundle/subscription, clears any *completed* renewal-order claim from a prior call against this same bundle post (this endpoint may legitimately be called again — e.g. to manually create a second ad-hoc renewal order), claims the renewal slot, queues the actual creation as an Action Scheduler job (`wicket_bundle_create_renewal_order`, group `wicket-memberships`), and returns immediately.

Poll [`GET .../renewal_order_status`](#check-renewal-order-creation-status) for the job's outcome — `order_id` and `order_url` are not available in this endpoint's own response.

### URL parameters

| Name | Type | Required | Description |
|---|---|---|---|
| `bundle_post_id` | `integer` | Yes | Post ID of the bundle. |

### Request body

None. (An earlier synchronous version of this endpoint took `product_id`/`variation_id`; the queued job resolves the term-ahead product itself via Milestone 9's repricing — see [Renewal Types](../concepts/renewal-types.md).)

### Response

`202 Accepted` — creation has been queued:

```json
{
    "success": "Renewal order creation has been queued.",
    "bundle_post_id": 123
}
```

`409 Conflict` — creation is still in progress from an earlier request against this bundle post:

```json
{
    "error": "Renewal order creation is already in progress for this membership bundle.",
    "order_id": null
}
```

A completed prior claim (a renewal order that already finished creating) does **not** produce a `409` here — it is cleared automatically before the new claim is made, so this endpoint can be called again on the same bundle post. `order_id` in the response body is only ever non-null in the rare case where a concurrent request's job completes in the brief window between the claim clear and the new claim.

### Errors

| Status | Cause |
|---|---|
| `400` | Invalid `bundle_post_id`, bundle has no linked subscription, or the linked subscription could not be loaded |
| `404` | Bundle post not found |
| `409` | Renewal order already exists for this cycle, or creation is already in progress (see response shape above) |

### Example

:::details Example
```bash
curl -X POST "https://example.com/wp-json/wicket_member/v1/bundle/123/create_renewal_order" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: {nonce}"
```
:::

---

## Confirm a renewal (confirmation_renewal bundles)

**`POST /wp-json/wicket_member/v1/bundle/{bundle_post_id}/confirm_renewal`**

Member-facing confirm action for a bundle configured with `renewal_type` `"confirmation_renewal"` (see [Renewal Types](../concepts/renewal-types.md)). Unlike `create_renewal_order` above (an unconditional admin override), this endpoint is restricted to the bundle's own owner, only works while the renewal confirmation window is open, and only for bundles actually configured with `confirmation_renewal`. It creates the same kind of WooCommerce renewal order `create_renewal_order` does — there is no separate order-creation logic — but with a permission, timing, and idempotency model suited to a member-initiated confirm click rather than an admin override.

### URL parameters

| Name | Type | Required | Description |
|---|---|---|---|
| `bundle_post_id` | `integer` | Yes | Post ID of the bundle. |

### Request body

None.

### Response

Like `create_renewal_order` above (Milestone 10), this endpoint queues background order creation rather than creating it inline — the same `wicket_bundle_create_renewal_order` Action Scheduler job the admin endpoint uses.

`202 Accepted` — confirmed, order creation has been queued:

```json
{
    "success": "Your renewal invoice is being prepared.",
    "bundle_post_id": 123
}
```

No `order_id` or `order_url` is returned — the order does not exist yet when this response is sent. Poll [`GET .../renewal_order_status`](#check-renewal-order-creation-status) for the outcome.

`409 Conflict` — already renewed this cycle, or a confirm is already in progress:

```json
{
    "error": "This membership bundle has already been renewed for the current cycle.",
    "order_id": 500
}
```

`order_id` is `null` in the 409 body while creation is still in flight (the confirm click already claimed the slot, but the job hasn't finished yet).

### Errors

| Status | Cause |
|---|---|
| `400` | Invalid `bundle_post_id`, bundle has no linked subscription, or the linked subscription could not be loaded |
| `400` | Bundle is not configured with `renewal_type` `"confirmation_renewal"` |
| `400` | The renewal confirmation window is not currently open (before `early_renew_at` or on/after `ends_at`) |
| `403` | Requesting user is not the bundle's owner |
| `404` | Bundle post not found |
| `409` | Already renewed this cycle, or confirmation is already in progress (see response shape above) |

### Example

:::details Example
```bash
curl -X POST "https://example.com/wp-json/wicket_member/v1/bundle/123/confirm_renewal" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: {nonce}"
```
:::

---

## Check renewal order creation status

**`GET /wp-json/wicket_member/v1/bundle/{bundle_post_id}/renewal_order_status`**

Poll target for the background job `create_renewal_order` and `confirm_renewal` queue (Milestone 10). Reads `membership_renewal_order_creation` post meta and reports whichever of three states that meta currently represents — there is no per-item progress to report, since `wcs_create_renewal_order()` runs as one atomic call, not a batch.

Owner-gated the same way `confirm_renewal` is — only the bundle's own owner may poll this.

### URL parameters

| Name | Type | Required | Description |
|---|---|---|---|
| `bundle_post_id` | `integer` | Yes | Post ID of the bundle. |

### Response

`200 OK` — no claim exists yet, or a claim is queued but the job hasn't run:

```json
{
    "status": "pending"
}
```

`200 OK` — the job created the order:

```json
{
    "status": "complete",
    "payment_url": "https://example.com/checkout/order-pay/500/?pay_for_order=true&key=wc_order_abc123"
}
```

`payment_url` is `null` if the order ID recorded on the claim no longer resolves to a real order.

`200 OK` — the job failed (e.g. the linked subscription could not be found, or `wcs_create_renewal_order()` errored):

```json
{
    "status": "failed"
}
```

A failed claim is cleared automatically on the next `create_renewal_order` or `confirm_renewal` call, so the member or admin can simply retry.

### Errors

| Status | Cause |
|---|---|
| `403` | Requesting user is not the bundle's owner |
| `404` | Bundle post not found |

### Example

:::details Example
```bash
curl "https://example.com/wp-json/wicket_member/v1/bundle/123/renewal_order_status" \
  -H "X-WP-Nonce: {nonce}"
```
:::
