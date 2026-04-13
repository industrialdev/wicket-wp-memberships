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

After updating group post meta, cascades the new status to all child individual memberships that are not already `expired` or `cancelled`. Date changes are also cascaded via `Membership_Group::cascade_dates_to_members()`.

Respects the `BYPASS_STATUS_CHANGE_LOCKOUT` env flag: when set, forces the status directly without enforcing transition rules.

Returns a `400` response for invalid transitions (unless bypass is active), `404` if the post is not found or is the wrong CPT.

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

Validates date ordering (start < end ≤ expires) before writing. Cascades date changes to child individual memberships via `Membership_Group::cascade_dates_to_members()`.

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
  'owner'               => [ 'user_id', 'uuid', 'name', 'email', 'mdp_link', 'identifying_number' ],
  'config'              => array,   // post meta from the linked Membership_Group_Config
  'subscription_id'     => int|false,
  'dates'               => [ 'starts_at', 'ends_at', 'expires_at', 'early_renew_at' ],
  'statuses'            => array,   // all status names
  'allowed_transitions' => array,   // valid next statuses from current
]
```

Fetches organisation data from `Helper::get_org_data()` and person data from `wicket_get_person_by_id()`. Returns `404` if the post is not found.

---

### `update_group_change_ownership( array $params ): \WP_REST_Response`

Changes the membership owner on a group post. Expects `params`:

| Key | Required |
|---|---|
| `group_post_id` | Yes |
| `new_owner_uuid` | Yes — MDP person UUID |

- Resolves the WP user from the UUID; creates a new WP user via `wicket_create_wp_user_if_not_exist()` if not found.
- Updates `user_id`, `user_name`, `user_email`, `membership_user_uuid` post meta and `post_author`.
- Reassigns the WC subscription customer.

Returns `400` if the new owner is the same as the current owner, or if the user cannot be resolved.

---

### `create_group_renewal_order( array $params ): \WP_REST_Response`

Creates a renewal WC order and subscription for a group membership. Expects `params`:

| Key | Required |
|---|---|
| `group_post_id` | Yes |
| `product_id` | Yes |
| `variation_id` | No — overrides `product_id` if provided |

The group must be in `active`, `grace-period`, or `delayed` status. Sets `_group_membership_post_id_renew` meta on order and subscription line items. Returns the new order URL on success.

> **TODO:** Add multi-line-item support once the group subscription line item structure is finalised — see TODO.md.

---

## Private Helpers

### `cancel_group_subscription( int|false $subscription_id, array $meta_data ): void`

Cancels a WC subscription and updates its end date from `$meta_data['membership_ends_at']`. Clears `next_payment`. No-op if the subscription does not exist or WC Subscriptions is not active.

---

## Dependencies

| Class / Function | Purpose |
|---|---|
| `Membership_Group` | Model — reads/writes group post meta, cascades dates |
| `Membership_Group_Config` | Reads date calculation config for `pending → active` transition |
| `Helper` | `get_post_meta()`, `get_all_status_names()`, `get_allowed_transition_status()`, `get_org_data()` |
| `Utilities` | `wc_log_mship_error()` for error logging |
| `wicket_create_wp_user_if_not_exist()` | Base plugin helper — creates WP user from MDP UUID |
| `wicket_get_person_by_id()` | Base plugin helper — fetches MDP person record |
| `wcs_get_subscription()` | WooCommerce Subscriptions |
| `wcs_create_subscription()` | WooCommerce Subscriptions |
