---
title: Bundle Members
---

# Bundle Members Endpoints

These endpoints manage individual member seats within a bundle — adding new seats, removing members with configurable handling, and moving members between bundles.

For the conceptual explanation of how seats relate to individual membership records, see [Member Handling](../concepts/member-handling.md).

---

## Add a member to a bundle

**`POST /wp-json/wicket_member/v1/bundle/{bundle_post_id}/add_member`**

Adds an individual member seat to a bundle. Supports two modes: enrolling a new person, or converting an existing standalone membership into a bundle seat.

### URL parameters

| Name | Type | Required | Description |
|---|---|---|---|
| `bundle_post_id` | `integer` | Yes | Post ID of the bundle to add the member to. |

### Request body

| Name | Type | Required | Description |
|---|---|---|---|
| `mode` | `string` | Yes | `"new"` — enrol a new person. `"existing"` — convert an existing membership. |
| `tier_post_id` | `integer` | Yes | Post ID of the `Membership_Tier` CPT defining the seat type. |
| `person_uuid` | `string` | Conditional | MDP person UUID. Required when `mode` is `"new"`. |
| `existing_membership_post_id` | `integer` | Conditional | Post ID of the existing `wicket_membership` to cancel and replace. Required when `mode` is `"existing"`. |
| `product_id` | `integer` | No | WC product ID. Auto-resolved from the tier when omitted. Required when the tier has more than one product. |

### Response

`200 OK`

```json
{
    "success": "Member added successfully.",
    "membership_post_id": 456
}
```

`membership_post_id` is the post ID of the newly created `wicket_membership` record.

### Errors

:::details Error codes
| Status | Code | Cause |
|---|---|---|
| `400` | `invalid_bundle_status` | Bundle is not in `pending`, `active`, or `delayed` status |
| `400` | `bundle_ended` | Today is past the bundle's end date |
| `400` | `ambiguous_product` | Tier has multiple products and `product_id` was not supplied |
| `400` | `invalid_user` | MDP person UUID could not be resolved to a WP user |
| `400` | `invalid_tier` | Tier post not found or wrong CPT |
| `400` | `create_failed` | Membership record creation failed |
:::

### Examples

**Enrol a new person:**

:::details Example
```bash
curl -X POST "https://example.com/wp-json/wicket_member/v1/bundle/123/add_member" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: {nonce}" \
  -d '{
    "mode": "new",
    "tier_post_id": 88,
    "person_uuid": "member-person-uuid"
  }'
```
:::

**Convert an existing membership to a bundle seat:**

:::details Example
```bash
curl -X POST "https://example.com/wp-json/wicket_member/v1/bundle/123/add_member" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: {nonce}" \
  -d '{
    "mode": "existing",
    "tier_post_id": 88,
    "existing_membership_post_id": 456
  }'
```
:::

---

## Remove a member from a bundle

**`POST /wp-json/wicket_member/v1/bundle/{bundle_post_id}/remove_member`**

Removes an individual member seat from a bundle with two configurable handling modes.

### URL parameters

| Name | Type | Required | Description |
|---|---|---|---|
| `bundle_post_id` | `integer` | Yes | Post ID of the bundle. |

### Request body

| Name | Type | Required | Description |
|---|---|---|---|
| `membership_post_id` | `integer` | Yes | Post ID of the individual `wicket_membership` to remove. |
| `mode` | `string` | Yes | `"cancel"` — cancels the membership immediately. `"keep_as_individual"` — converts the seat to a standalone membership with its own WooCommerce order and subscription. |

### Response

`200 OK`

```json
{
    "success": "Member removed successfully.",
    "membership_post_id": 456
}
```

The returned `membership_post_id` differs by mode:

- `cancel` — the post ID of the cancelled membership
- `keep_as_individual` — the post ID of the **newly created** standalone membership

### Errors

:::details Error codes
| Status | Code | Cause |
|---|---|---|
| `400` | `invalid_bundle_status` | Bundle is not in `pending`, `active`, or `delayed` status |
| `400` | `invalid_membership` | Membership post not found |
| `400` | `membership_not_in_bundle` | Membership does not belong to this bundle |
| `400` | `invalid_user` | User cannot be resolved (required for `keep_as_individual`) |
:::

### Examples

**Cancel the seat immediately:**

:::details Example
```bash
curl -X POST "https://example.com/wp-json/wicket_member/v1/bundle/123/remove_member" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: {nonce}" \
  -d '{
    "membership_post_id": 456,
    "mode": "cancel"
  }'
```
:::

**Convert seat to standalone membership:**

:::details Example
```bash
curl -X POST "https://example.com/wp-json/wicket_member/v1/bundle/123/remove_member" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: {nonce}" \
  -d '{
    "membership_post_id": 456,
    "mode": "keep_as_individual"
  }'
```
:::

---

## Move a member to another bundle

**`POST /wp-json/wicket_member/v1/bundle/{bundle_post_id}/move_individual_membership`**

Moves a member seat from the specified bundle to a target bundle. Cancels the seat in the source bundle and creates a new seat in the target bundle with the same tier and product.

### URL parameters

| Name | Type | Required | Description |
|---|---|---|---|
| `bundle_post_id` | `integer` | Yes | Post ID of the **source** bundle. |

### Request body

| Name | Type | Required | Description |
|---|---|---|---|
| `membership_post_id` | `integer` | Yes | Post ID of the individual membership to move. |
| `target_bundle_post_id` | `integer` | Yes | Post ID of the destination bundle. |

### Response

`200 OK`

```json
{
    "success": "Member moved successfully.",
    "membership_post_id": 789
}
```

`membership_post_id` is the post ID of the **new** membership in the target bundle.

### Errors

:::details Error codes
| Status | Code | Cause |
|---|---|---|
| `400` | `invalid_bundle_status` | Source or target bundle is not in `pending`, `active`, or `delayed` status |
| `400` | `invalid_membership` | Membership post not found |
| `400` | `membership_not_in_bundle` | Membership does not belong to the source bundle |
| `400` | `bundle_ended` | Target bundle's end date has passed |
| `400` | `create_failed` | New membership could not be created in the target bundle |
:::

:::warning
There is no rollback if the source membership is successfully cancelled but the target creation fails. The error message will explicitly note this. Use `add_member` with `mode: "new"` to re-add the member to a bundle manually.
:::

### Example

:::details Example
```bash
curl -X POST "https://example.com/wp-json/wicket_member/v1/bundle/123/move_individual_membership" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: {nonce}" \
  -d '{
    "membership_post_id": 456,
    "target_bundle_post_id": 789
  }'
```
:::

---

## Change the bundle owner

**`POST /wp-json/wicket_member/v1/bundle/{bundle_post_id}/change_owner`**

Replaces the bundle's owner with a new person. Resolves or creates the WP user from the MDP person UUID, updates the bundle post meta, and reassigns the linked WooCommerce order and subscription to the new owner.

### URL parameters

| Name | Type | Required | Description |
|---|---|---|---|
| `bundle_post_id` | `integer` | Yes | Post ID of the bundle. |

### Request body

| Name | Type | Required | Description |
|---|---|---|---|
| `new_owner_uuid` | `string` | Yes | MDP person UUID of the new owner. |

### Response

`200 OK` — ownership changed successfully.  
`204 No Content` — the new owner is the same as the current owner (no-op).

### Errors

| Status | Cause |
|---|---|
| `404` | Bundle post not found |
| `500` | User cannot be resolved from the provided UUID |

### Example

:::details Example
```bash
curl -X POST "https://example.com/wp-json/wicket_member/v1/bundle/123/change_owner" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: {nonce}" \
  -d '{
    "new_owner_uuid": "new-person-uuid"
  }'
```
:::
