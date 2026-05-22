---
title: "Bundle_Admin_Controller"
audience: [developer]
php_class: Bundle_Admin_Controller
source_files: ["includes/Bundle_Admin_Controller.php"]
---

# Bundle_Admin_Controller

**File:** `includes/Bundle_Admin_Controller.php`
**Namespace:** `Wicket_Memberships`

Admin operations for membership bundle posts. Mirrors the shape of `Admin_Controller` but operates exclusively on the `wicket_mship_bundle` CPT. Individual-membership concerns (MDP record sync, tier/config lookups, user-meta JSON blobs) are intentionally absent — groups are containers that hold individual memberships and do not have their own MDP membership records.

All public methods are `static` and return `\WP_REST_Response` or a plain `array` (for data retrieval methods).

## Architectural convention

`Bundle_Admin_Controller` is a thin orchestrator. Request validation, payload normalization, and transition planning stay here. Group-domain mutations and WooCommerce side effects are delegated to `Membership_Bundle`, which keeps WC coupling contained in the model and the controller readable as a sequence of model calls.

---

## Constructor

### `__construct()`

Stores the group CPT slug from `Helper::get_membership_group_cpt_slug()`. Also instantiated implicitly by the static methods via `new self()` where needed.

---

## Static Methods

### `get_admin_status_options( ?int $bundle_post_id = null ): array`

Returns available status options for a membership bundle.

- If `$bundle_post_id` is supplied, returns only valid transitions from the group's current `membership_status` meta value, via `Helper::get_allowed_transition_status()`.
- If omitted, returns all status names from `Helper::get_all_status_names()`.

---

### `admin_manage_status( int $bundle_post_id, string $new_status ): \WP_REST_Response`

Transitions a membership bundle to a new status. Supported transitions:

| From | To | Date behaviour |
|---|---|---|
| `pending` | `active` | Reads dates from the linked `Membership_Bundle_Config`; activates and dates the WC subscription |
| `pending` | `cancelled` | Sets start = yesterday, end = now, expires = now |
| `delayed` | `cancelled` | Same as pending → cancelled |
| `active` | `cancelled` | Sets end = tomorrow, expires = tomorrow; cancels subscription |
| `active` | `expired` | Sets end = tomorrow, expires = tomorrow; cancels subscription |
| `grace-period` | `cancelled` | Preserves existing end date; sets expires = now |

Delegates the full lifecycle operation to `Membership_Bundle::transition_to()`, which applies transition rules, plans dates, activates the linked subscription for `pending -> active`, and persists the new group status. Child-status cascading is currently a TODO placeholder and does not run.

Respects the `BYPASS_STATUS_CHANGE_LOCKOUT` env flag: when set, forces the status directly without enforcing transition rules.

Returns a `400` response for invalid transitions (unless bypass is active), `404` if the post is not found or is the wrong CPT.

> **TODO:** Cancel the linked WC subscription on `cancelled`/`expired` transitions once group subscription management is implemented — see TODO.md.

---

### `get_group_entity_records( int $bundle_post_id ): array|\WP_REST_Response`

Returns the data needed to populate the membership bundle entity view:

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

---

### `update_group_entity_record( array $data ): \WP_REST_Response`

Updates editable fields on a group post. Expects these keys in `$data`:

| Key | Required | Notes |
|---|---|---|
| `bundle_post_id` | Yes | Must be a valid group CPT post |
| `membership_starts_at` | No | Must be before `membership_ends_at` |
| `membership_ends_at` | No | Must not be after `membership_expires_at` |
| `membership_expires_at` | No | |
| `membership_renewal_type` | No | |

Validates date ordering (start < end ≤ expires) before writing.

After a successful edit, if `membership_renewal_type` changed, calls `maybe_sync_renewal_type_next_payment()` to update the linked subscription's next-payment date.

---

### `maybe_sync_renewal_type_next_payment( Membership_Bundle $group, string $pre_edit_renewal_type, string $new_renewal_type ): void` _(private static)_

Syncs the linked WC subscription's `next_payment` date when the renewal type changes on a group edit.

- **Changed to `subscription`:** sets `next_payment` to `ends_at` (day-end, via `Utilities::get_mdp_day_end()`).
- **Changed away from `subscription`:** calls `$subscription->delete_date('next_payment')`.

No-ops when: renewal type is unchanged, no subscription is linked, subscription status is not `active`, or WCS is unavailable.

---

### `get_group_edit_page_info( int $bundle_post_id ): array|\WP_REST_Response`

Returns all data required to populate the membership bundle edit form:

```php
[
  'ID'                  => int,
  'title'               => string,
  'meta'                => array,   // raw post meta
  'org'                 => [ 'uuid', 'name', 'location', 'mdp_link' ],
  'owner'               => [ 'user_id', 'uuid', 'name', 'email', 'mdp_link', 'identifying_number', 'switch_to_url' ] | null,
  'config'              => array,   // post meta from the linked Membership_Bundle_Config
  'subscription_id'     => int|false,
  'subscription'        => [ 'id', 'link', 'status', 'next_payment_date' ] | null,
  'order'               => [ 'id', 'link', 'total', 'status', 'date_created', 'date_completed' ] | null,
  'orders'              => [
    [ 'id', 'link', 'total', 'status', 'date_created', 'date_completed', 'type' ],
    // type: 'parent' | 'renewal' | 'other'
  ],
  'dates'               => [ 'starts_at', 'ends_at', 'expires_at', 'early_renew_at' ],
  'statuses'            => array,   // all status names
  'allowed_transitions' => array,   // valid next statuses from current
  'membership_records'  => [
    [
      'ID'                     => int,     // the group post ID
      'name'                   => string,  // stored group name or post title
      'status'                 => string,  // human-readable label
      'starts_at'              => string,
      'ends_at'                => string,
      'expires_at'             => string,
      'renewal_type'           => string,
      'next_tier_form_page_id' => int|null,
      'next_tier_id'           => int|null,
    ],
  ],
]
```

`membership_records` contains the singular membership bundle record shown in the edit-page "Membership Records" table. Child individual memberships are not listed there; they remain available through the separate group-members breakdown and filtered member-management links.

Fetches organisation data from `Helper::get_org_data()` and person data from `wicket_get_person_by_id()`. Fetches the linked WC subscription via `wcs_get_subscription()` and related orders via `WC_Subscription::get_related_orders('all')`. All date fields are ISO 8601. Returns `404` if the post is not found.

---

### `get_group_members_by_tier( int $bundle_post_id ): array|\WP_REST_Response`

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

Queries child memberships via `Membership_Bundle::get_individual_memberships()`, groups them by `membership_tier_name` meta, and returns the totals. Members without a tier name count toward `total_members` but are omitted from `tiers`. Tiers are sorted alphabetically. Returns `404` if the post is not found or is the wrong CPT.

---

### `update_group_change_ownership( array $params ): \WP_REST_Response`

Changes the membership owner on a group post. Expects `params`:

| Key | Required |
|---|---|
| `bundle_post_id` | Yes |
| `new_owner_uuid` | Yes — MDP person UUID |

- Rejects the request when the selected UUID is already the canonical owner.
- Delegates ownership storage (including WP user resolution/creation) to `Membership_Bundle::set_owner()` — see `Membership_Bundle` docs for rationale.
- Reassigns the linked WC order and subscription customers (handled internally by `set_owner()`).

Returns `400` if the new owner is the same as the current owner, or if the user cannot be resolved.

Ownership reassignment of linked WooCommerce order/subscription records is handled internally by `Membership_Bundle::set_owner()`.

---

### `add_member( array $params ): array`

Adds an individual membership to a group. Dispatches to `Membership_Bundle::add_member()` based on `mode`.

| Key | Required | Description |
|---|---|---|
| `bundle_post_id` | Yes | Post ID of the `Membership_Bundle` |
| `mode` | Yes | `"new"` or `"existing"` |
| `tier_post_id` | Yes | Post ID of the individual `Membership_Tier` CPT |
| `person_uuid` | Conditional | MDP person UUID — required when `mode = "new"` |
| `existing_membership_post_id` | Conditional | Existing membership post ID to cancel — required when `mode = "existing"` |
| `product_id` | No | WC product ID — auto-resolved from tier when omitted |

For `mode = "new"`: resolves a WP user from `person_uuid` before delegating to `Membership_Bundle::add_member()`. Resolution strategy depends on the `BYPASS_WICKET` flag (read from `$group->bypass_wicket`):

- **Normal mode:** calls `wicket_create_wp_user_if_not_exist()` — creates the WP user from MDP if not already present.
- **Bypass mode (`BYPASS_WICKET`):** calls `get_user_by( 'login', $person_uuid )` only — no MDP API call. Returns `user_resolve_failed` error if the user does not already exist locally.

Returns `['success' => '...', 'membership_post_id' => int]` on success or `['error' => '...', 'code' => '...']` on failure. All model `WP_Error` values are mapped to the error-array shape so callers never receive a `WP_Error` directly.

---

### `remove_member( array $params ): array`

Removes an individual membership from a group. Dispatches to `Membership_Bundle::remove_member()`.

| Key | Required | Description |
|---|---|---|
| `bundle_post_id` | Yes | Post ID of the `Membership_Bundle` |
| `membership_post_id` | Yes | Post ID of the individual membership to remove |
| `mode` | Yes | `"cancel"` or `"keep_as_individual"` |

Returns `['success' => '...', 'membership_post_id' => int]` on success or `['error' => '...', 'code' => '...']` on failure.

---

### `move_individual_membership( array $params ): array`

Moves an individual membership from one group to another. Dispatches to `Membership_Bundle::move_individual_membership()`.

| Key | Required | Description |
|---|---|---|
| `source_bundle_post_id` | Yes | Post ID of the source `Membership_Bundle` |
| `membership_post_id` | Yes | Post ID of the individual membership to move |
| `target_bundle_post_id` | Yes | Post ID of the target `Membership_Bundle` |

Returns `['success' => '...', 'membership_post_id' => int]` on success or `['error' => '...', 'code' => '...']` on failure. If the new membership cannot be created after the source is cancelled, returns an error with code from the underlying failure and a message noting the source was cancelled with no rollback.

---

### `cancel_group( int $bundle_post_id, string $member_handling, string $timing ): \WP_REST_Response`

Cancels a membership bundle with three configurable paths based on `$member_handling` and `$timing`.

**Path A — `cancel_all` + `immediately`:**
- Calls `Membership_Bundle::transition_to('cancelled')`, which collapses dates to now and handles the cascade via `plan_status_transition`.
- Calls `cancel_group_subscription()` to immediately hard-cancel the WC subscription.
- Calls `Membership_Controller::update_membership_status($post_id, 'cancelled')` on each individual membership. `membership_group_id` meta is preserved for historical reference. No replacement memberships are created.

**Path B — `cancel_all` + `at_end_date`:**
- Calls `Membership_Bundle::transition_to_cancelled_at_end_date()`, which sets group status to cancelled while preserving `ends_at`, collapses `expires_at` to `ends_at`, and updates individual membership `expires_at` without touching their active status.
- Individual memberships **keep their active status** — members retain access until the original group end date. `daily_membership_expiry_hook` expires them naturally on that date. No per-member scheduled job.
- Sets the group WC subscription to `pending-cancel`.
- Schedules one `as_schedule_single_action` at `ends_at` with hook `wicket_group_cancel_subscription` + arg `$bundle_post_id`. The handler in `wicket.php` calls `$subscription->update_status('cancelled')`.
- No replacement memberships created.

**Path C — `keep_as_individual`:**
- Delegates entirely to `Membership_Bundle::cancel_keep_as_individual()`. See that method's documentation for the full conversion loop detail.
- Returns `400` if the group transition fails, `200` (with optional `warnings` array) on success.

Returns `400` for invalid transitions or missing end date (path B), `404` if group not found, `200` on success.

---

### `create_group_renewal_order( array $params ): \WP_REST_Response`

Creates a renewal WC order and subscription for a membership bundle. Expects `params`:

| Key | Required |
|---|---|
| `bundle_post_id` | Yes |
| `product_id` | Yes |
| `variation_id` | No — overrides `product_id` if provided |

This is currently a stub that returns `501 Not yet implemented.` The remaining blocker is the group subscription line item structure.

---

## Private Helpers

### `build_membership_groups_row( \WP_Post $post ): array`

Builds one list-table row per group post. Owner `name` and `email` are resolved in controller response-shaping code from the live WP user via the stored `user_id`.

### `cancel_group_subscription( int|false $subscription_id, array $meta_data ): void`

Cancels a WC subscription linked to a membership bundle. Sets the subscription end date from `$meta_data['ends_at']` then calls `update_status('cancelled')`. No-op if the subscription does not exist or WC Subscriptions is not active. Used by path A of `cancel_group()`.

---

## Dependencies

| Class / Function | Purpose |
|---|---|
| `Membership_Bundle` | Model — reads/writes group post meta; child date/status cascade hooks are currently TODO placeholders |
| `Membership_Bundle_Config` | Reads date calculation config for `pending → active` transition |
| `Helper` | `get_post_meta()`, `get_all_status_names()`, `get_allowed_transition_status()`, `get_org_data()` |
| `Utilities` | `wc_log_mship_error()` for error logging |
| `wicket_create_wp_user_if_not_exist()` | Base plugin helper — creates WP user from MDP UUID |
| `wicket_get_person_by_id()` | Base plugin helper — fetches MDP person record |
| `wcs_get_subscription()` | WooCommerce Subscriptions — fetch subscription by ID |
| `wcs_create_subscription()` | WooCommerce Subscriptions — create new subscription |
| `wc_get_order()` | WooCommerce — fetch order by ID |
| `wcs_order_contains_renewal()` | WooCommerce Subscriptions — classify order type |
| `wcs_order_contains_subscription()` | WooCommerce Subscriptions — classify order type |
