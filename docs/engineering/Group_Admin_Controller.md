---
title: "Group_Admin_Controller"
audience: [developer]
php_class: Group_Admin_Controller
source_files: ["includes/Group_Admin_Controller.php"]
---

# Group_Admin_Controller

**File:** `includes/Group_Admin_Controller.php`
**Namespace:** `Wicket_Memberships`

Admin operations for membership group posts. Mirrors the shape of `Admin_Controller` but operates exclusively on the `wicket_mship_group` CPT. Individual-membership concerns (MDP record sync, tier/config lookups, user-meta JSON blobs) are intentionally absent — groups are containers that hold individual memberships and do not have their own MDP membership records.

All public methods are `static` and return `\WP_REST_Response` or a plain `array` (for data retrieval methods).

## Architectural convention

`Group_Admin_Controller` is a thin orchestrator. Request validation, payload normalization, and transition planning stay here. Group-domain mutations and WooCommerce side effects are delegated to `Membership_Group`, which keeps WC coupling contained in the model and the controller readable as a sequence of model calls.

---

## Constructor

### `__construct()`

Stores the group CPT slug from `Helper::get_membership_group_cpt_slug()`. Also instantiated implicitly by the static methods via `new self()` where needed.

---

## Static Methods

### `get_admin_status_options( ?int $group_post_id = null ): array`

Returns available status options for a group membership.

- If `$group_post_id` is supplied, returns only valid transitions from the group's current `membership_status` meta value, via `Helper::get_allowed_transition_status()`.
- If omitted, returns all status names from `Helper::get_all_status_names()`.

---

### `admin_manage_status( int $group_post_id, string $new_status ): \WP_REST_Response`

Transitions a group membership to a new status. Supported transitions:

| From | To | Date behaviour |
|---|---|---|
| `pending` | `active` | Reads dates from the linked `Membership_Group_Config`; activates and dates the WC subscription |
| `pending` | `cancelled` | Sets start = yesterday, end = now, expires = now |
| `delayed` | `cancelled` | Same as pending → cancelled |
| `active` | `cancelled` | Sets end = tomorrow, expires = tomorrow; cancels subscription |
| `active` | `expired` | Sets end = tomorrow, expires = tomorrow; cancels subscription |
| `grace-period` | `cancelled` | Preserves existing end date; sets expires = now |

Delegates the full lifecycle operation to `Membership_Group::transition_to()`, which applies transition rules, plans dates, activates the linked subscription for `pending -> active`, and persists the new group status. Child-status cascading is currently a TODO placeholder and does not run.

Respects the `BYPASS_STATUS_CHANGE_LOCKOUT` env flag: when set, forces the status directly without enforcing transition rules.

Returns a `400` response for invalid transitions (unless bypass is active), `404` if the post is not found or is the wrong CPT.

> **TODO:** Cancel the linked WC subscription on `cancelled`/`expired` transitions once group subscription management is implemented — see TODO.md.

---

### `get_group_entity_records( int $group_post_id ): array|\WP_REST_Response`

Returns the data needed to populate the group membership entity view:

```php
[
  'ID'                 => int,
  'title'              => string,
  'data'               => [  // all post meta + formatted fields
    'membership_status'      => string,  // human-readable label
    'membership_status_slug' => string,
    'membership_starts_at'   => string,  // m/d/Y
    'membership_ends_at'     => string,
    'membership_expires_at'  => string,
    ...rest of post meta
  ],
  'individual_members' => int[],  // post IDs of child memberships
]
```

Returns `404` if the post is not found or wrong CPT.

> **TODO:** Enrich response with WC subscription and order data — see TODO.md.

---

### `update_group_entity_record( array $data ): \WP_REST_Response`

Updates editable fields on a group post. Expects these keys in `$data`:

| Key | Required | Notes |
|---|---|---|
| `group_post_id` | Yes | Must be a valid group CPT post |
| `membership_starts_at` | No | Must be before `membership_ends_at` |
| `membership_ends_at` | No | Must not be after `membership_expires_at` |
| `membership_expires_at` | No | |
| `membership_renewal_type` | No | |

Validates date ordering (start < end ≤ expires) before writing.

> **TODO:** Wire in subscription date updates when renewal type changes — see TODO.md.

---

### `get_group_edit_page_info( int $group_post_id ): array|\WP_REST_Response`

Returns all data required to populate the group membership edit form:

```php
[
  'ID'                  => int,
  'title'               => string,
  'meta'                => array,   // raw post meta
  'org'                 => [ 'uuid', 'name', 'location', 'mdp_link' ],
  'owner'               => [ 'user_id', 'uuid', 'name', 'email', 'mdp_link', 'identifying_number', 'switch_to_url' ] | null,
  'config'              => array,   // post meta from the linked Membership_Group_Config
  'subscription_id'     => int|false,
  'dates'               => [ 'starts_at', 'ends_at', 'expires_at', 'early_renew_at' ],
  'statuses'            => array,   // all status names
  'allowed_transitions' => array,   // valid next statuses from current
]
```

Fetches organisation data from `Helper::get_org_data()` and person data from `wicket_get_person_by_id()`. Returns `404` if the post is not found.

---

### `get_group_members_by_tier( int $group_post_id ): array|\WP_REST_Response`

Returns the total member count and per-tier breakdown for a group:

```php
[
  'total_members' => int,
  'tiers'         => [
    [ 'tier_name' => string, 'member_count' => int ],
    // ...sorted alphabetically by tier_name
  ],
]
```

Queries child memberships via `Membership_Group::get_individual_memberships()`, groups them by `membership_tier_name` meta, and returns the totals. Members without a tier name count toward `total_members` but are omitted from `tiers`. Tiers are sorted alphabetically. Returns `404` if the post is not found or is the wrong CPT.

---

### `update_group_change_ownership( array $params ): \WP_REST_Response`

Changes the membership owner on a group post. Expects `params`:

| Key | Required |
|---|---|
| `group_post_id` | Yes |
| `new_owner_uuid` | Yes — MDP person UUID |

- Resolves the WP user from the UUID; creates a new WP user via `wicket_create_wp_user_if_not_exist()` if not found.
- Rejects the request when the selected user is already the canonical owner.
- Delegates ownership storage to `Membership_Group::set_owner()` — only `user_id` and `post_author` are stored (see `Membership_Group` docs for rationale).
- Reassigns the linked WC order customer when `membership_parent_order_id` is present.
- Reassigns the WC subscription customer.

Returns `400` if the new owner is the same as the current owner, or if the user cannot be resolved.

Ownership reassignment of linked WooCommerce order/subscription records is handled internally by `Membership_Group::set_owner()`.

---

### `create_group_renewal_order( array $params ): \WP_REST_Response`

Creates a renewal WC order and subscription for a group membership. Expects `params`:

| Key | Required |
|---|---|
| `group_post_id` | Yes |
| `product_id` | Yes |
| `variation_id` | No — overrides `product_id` if provided |

This is currently a stub that returns `501 Not yet implemented.` The remaining blocker is the group subscription line item structure.

---

## Private Helpers

### `build_group_memberships_row( \WP_Post $post ): array`

Builds one list-table row per group post. Owner `name` and `email` are resolved in controller response-shaping code from the live WP user via the stored `user_id`.

### `cancel_group_subscription( int|false $subscription_id, array $meta_data ): void`

Cancels a WC subscription and updates its end date from `$meta_data['membership_ends_at']`. Clears `next_payment`. No-op if the subscription does not exist or WC Subscriptions is not active.

---

## Dependencies

| Class / Function | Purpose |
|---|---|
| `Membership_Group` | Model — reads/writes group post meta; child date/status cascade hooks are currently TODO placeholders |
| `Membership_Group_Config` | Reads date calculation config for `pending → active` transition |
| `Helper` | `get_post_meta()`, `get_all_status_names()`, `get_allowed_transition_status()`, `get_org_data()` |
| `Utilities` | `wc_log_mship_error()` for error logging |
| `wicket_create_wp_user_if_not_exist()` | Base plugin helper — creates WP user from MDP UUID |
| `wicket_get_person_by_id()` | Base plugin helper — fetches MDP person record |
| `wcs_get_subscription()` | WooCommerce Subscriptions |
| `wcs_create_subscription()` | WooCommerce Subscriptions |
