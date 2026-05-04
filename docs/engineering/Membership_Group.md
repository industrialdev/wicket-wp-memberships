# Membership_Group

**File:** `includes/Membership_Group.php`
**Namespace:** `Wicket_Memberships`

Represents a single Membership Group CPT record. Wraps post meta access and provides the canonical API for reading and writing group-level data.

---

## Constructor

### `__construct( int $post_id )`

Loads the group by post ID. Sets `$post_id = 0` and `$meta_data = []` if the post does not exist or is not the membership group CPT type. Errors are logged via `Wicket()->log()`.

---

## Static Methods

### `create( string $name, int $membership_group_config_id, string $org_uuid, int $owner_user_id, string $start_date ): static|null`

Creates a new membership group post and populates all required meta in a single call. All parameters are required. Returns a new `Membership_Group` instance on success, `null` on any failure (the partially-created post is deleted before returning). Errors are logged via `Wicket()->log()`.

**Parameters:**

| Parameter | Type | Description |
|---|---|---|
| `$name` | `string` | Post title for the group (must be non-empty) |
| `$membership_group_config_id` | `int` | Post ID of the linked `Membership_Group_Config` |
| `$org_uuid` | `string` | MDP organisation UUID |
| `$owner_user_id` | `int` | WP user ID of the group owner |
| `$start_date` | `string` | ISO 8601 start date for the membership period (must be non-empty) |

The post is created with `post_status = 'pending'`. Dates (end, expiry, early-renewal) are derived from the linked config via `get_membership_dates()`, anchored to the supplied `start_date`. A pending WooCommerce subscription is created and linked via `membership_subscription_id` post meta; if subscription creation fails the group is still returned (non-fatal, logged).

**Fields accessible on the returned object:**

| Field | Access |
|---|---|
| Post ID | `$group->post_id` |
| Post status (`pending`) | `get_post( $group->post_id )->post_status` |
| Membership status | `$group->get_membership_status()` → `'pending'` |
| Start date | `$group->get_dates()['starts_at']` |
| End date | `$group->get_dates()['ends_at']` |
| Expiration date | `$group->get_dates()['expires_at']` _(empty string if config has no grace period)_ |
| Org UUID | `$group->get_org_uuid()` |
| Owner user ID | `$group->get_owner_id()` |
| Subscription ID | `$group->get_subscription_id()` |
| Renewal type | `$group->get_config()->get_renewal_type()` _(not a direct field — via config)_ |

### `create_group_subscription(): int|false` _(private)_

Creates a pending WooCommerce subscription for a freshly-created group and writes two post meta keys onto it:

| Meta key | Value |
|---|---|
| `membership_group_id` | Group post ID |
| `_org_uuid` | Org UUID from the group |

`billing_period` and `billing_interval` are sourced from `$config->get_period_data()` so the values match the group config cycle (anniversary configs use their configured period; calendar configs fall back to `year` / `1`). `end` is set to `expires_at` (grace-period end), falling back to `ends_at` when no grace period is configured, mirroring `Membership_Subscription_Controller::create_subscriptions()`. `next_payment` is only set when `$config->is_renewal_subscription()` returns `true`; it maps to `ends_at` so WCS triggers renewal at the membership period end.

No product line items are added at creation — those are attached per member when `add_member()` is called. Called only from `create()` after `set_dates()` succeeds. Returns the subscription post ID on success, `false` on any failure.

### `add_subscription_line_item( int $membership_post_id, int $product_id, int $user_id ): int|WP_Error` _(private)_

Adds a WooCommerce subscription line item to the group subscription for an individual membership. Called automatically from `create_individual_membership_for_group()` after the membership record is created. Failure is non-fatal — the caller logs and continues, leaving the membership record intact.

**Line item meta written:**

| Meta key | Value |
|---|---|
| `_membership_post_id` | Individual membership post ID |
| `_member_name` | Member's `display_name` (omitted if user cannot be resolved) |

`$product_id` must be the variation ID when a variation is in use (caller passes `$variation_id ?? $product_id`, matching the precedence rule used for `membership_product_id`). Price comes from the WC product — no custom pricing logic. Calls `$sub->calculate_totals()` and `$sub->save()` after adding the item.

Fail states (all non-fatal at call site): `wcs_unavailable`, `no_subscription`, `subscription_not_found`, `product_not_found`, `add_product_failed`.

---

## Instance Methods

### `add_member( ?int $user_id, int $tier_post_id, ?int $product_id = null, ?int $variation_id = null, ?int $existing_membership_post_id = null ): int|WP_Error`

Single entry point for adding an individual membership to a group. Covers two flows:

- **New member** (`$existing_membership_post_id = null`): `$user_id` must be provided. Creates a fresh membership and links it to the group.
- **Existing member** (`$existing_membership_post_id` provided): cancels the existing membership (sets its status to `cancelled`), resolves the user ID from it, then creates a new membership with group dates and links it. `$user_id` is ignored.

Both paths share start-date logic via `resolve_member_start_date()`: if today is within the group date window, start = today; if today is before the group start, start = group start; if today is after the group end, returns `WP_Error('group_ended')`.

Group must be in `pending`, `active`, or `delayed` status; returns `WP_Error('invalid_group_status')` otherwise.

`product_id` is auto-resolved from the tier when omitted; fails with `WP_Error('ambiguous_product')` if the tier has more than one product. When `variation_id` is supplied, it is stored as `membership_product_id` instead of the parent `product_id` — matching the subscription-driven membership flow where variation ID takes precedence. Returns the new membership post ID on success.

Fires filter `wicket_memberships_individual_membership_created_for_group` after the membership record is created. Returning an array without `membership_post_id` from this filter will cause a `create_failed` error.

Fail states: `invalid_group_status`, `missing_user_id`, `group_ended`, `group_no_dates`, `invalid_user`, `invalid_tier`, `ambiguous_product`, `no_product`, `product_tier_mismatch`, `invalid_membership` (existing path), `missing_user_id` (existing record has no user_id meta), `create_failed`.

> **TODO:** Set membership status from the group's own status once group-driven status propagation is implemented.

> **TODO:** Link `membership_subscription_id` and `membership_parent_order_id` to the group's WooCommerce subscription once group subscription management exists.

> **TODO:** Line item removal is not yet implemented. When a member is removed or moved, the corresponding subscription line item is not removed from the group subscription. See `remove_subscription_line_item()` TODO in `TODO.md`.

---

### `remove_member( int $membership_post_id, string $mode ): int|WP_Error`

Removes an individual membership from this group. Two modes:

- **`cancel`**: cancels the group-linked membership immediately (sets status to `cancelled`). Returns the cancelled membership post ID.
- **`keep_as_individual`**: cancels the group-linked membership, then creates a new standalone individual membership (no `membership_group_id`) with start date resolved against this group's date window and `ends_at` inherited from this group. Copies tier, product, and user from the old membership. Returns the new membership post ID.

Group must be in `pending`, `active`, or `delayed` status; returns `WP_Error('invalid_group_status')` otherwise. An expired or cancelled group is blocked by the status gate before any date checks run.

Fail states: `invalid_group_status`, `invalid_membership`, `membership_not_in_group`, `group_no_dates`, `group_ended`, `invalid_user`, `create_failed`.

---

### `move_individual_membership( int $membership_post_id, Membership_Group $target_group ): int|WP_Error`

Moves an individual membership from this group to a target group. Cancels the source membership and creates a new one linked to the target group, inheriting the same user, tier, and product. Start date is resolved against the target group's date window via `resolve_member_start_date()`.

Both the source and target groups must be in `pending`, `active`, or `delayed` status. The membership must belong to the source group.

If creation of the new membership fails after cancellation, a `WP_Error` is returned with an explicit message noting that the source was cancelled and the member must be manually re-added. No rollback is attempted.

Fail states: `invalid_group_status` (source or target), `invalid_membership`, `membership_not_in_group`, `group_no_dates` (target), `group_ended` (target), `invalid_user`, `create_failed`.

---

### `get_name(): string`

Returns the post title (group name). Returns an empty string if the group post is not loaded.

---

### `set_owner( string $uuid ): int|false`

Accepts an MDP person UUID. Validates the format via `isValidUuid()`, then resolves or creates the corresponding WP user via `wicket_create_wp_user_if_not_exist()`. Stores only the WP user ID (`user_id`) and updates `post_author`. Derived fields — display name, email, and UUID — are intentionally not stored to avoid persisting values that can change independently of the membership record. Group ownership cannot be cleared through this method; malformed or unresolvable UUIDs are rejected. Returns the saved owner WP user ID on success and `false` on failure.

When ownership changes, this method also reassigns the linked WooCommerce parent order and subscription customers through private helper methods.

To retrieve owner details on demand:
- WP user object: `get_user_by( 'id', $owner_id )`
- MDP person record: `wicket_get_person_by_id( $user->user_login )` (UUID = `user_login`)

### `get_owner(): array|false`

Returns a structured snapshot of the group owner: `['user_id' => int, 'uuid' => string, 'name' => string, 'email' => string]`, or `false` if no owner is set. The UUID is derived from `user_login` — not stored as post meta. Prefer this over calling `get_owner_id()` + `get_user_by()` separately.

### `get_owner_id(): int|false`

Returns the canonical owner user ID stored in `user_id`, or `false` if not set or invalid.

### `get_owner_uuid(): string|false`

Derives and returns the MDP UUID for the owner by reading `user_login` from the WP user resolved via `get_owner_id()`. Returns `false` if no owner is set or the user cannot be resolved. The UUID is not stored as post meta.

### `is_owner( string $uuid ): bool`

Returns `true` if the given MDP person UUID matches the group owner (resolved via `user_login`).

### `set_organization( string $org_uuid ): array|false`

Associates an MDP organization with this group. Fetches the org via `Helper::get_org_data()` and stores `org_uuid` and `org_name` as post meta. Organization assignment cannot be cleared through this method; invalid values are rejected. Returns the org data array on success and `false` on failure.

### `get_org_uuid(): string|false`

Returns the `org_uuid` meta value, or `false` if not set.

### `get_organization(): array|false`

Returns the full organization data array from `Helper::get_org_data()` for the stored UUID, or `false` if not set or UUID is invalid.

### `get_config(): Membership_Group_Config|false`

Returns the linked `Membership_Group_Config` object from `membership_group_config_id`, or `false` if not set.

### `set_config( int $config_post_id ): bool`

Validates that `$config_post_id` resolves to a valid `Membership_Group_Config`, writes `membership_group_config_id` meta, and reloads `$this->meta_data`. Returns `true` on success, logs and returns `false` on failure.

### `set_dates( array $dates ): bool`

Writes membership date meta. Accepts the same keys returned by `get_dates()`. Optional keys are skipped when `null`.

```php
[
  'starts_at'      => string,       // required — membership_starts_at
  'ends_at'        => string,       // required — membership_ends_at
  'expires_at'     => string|null,  // optional — membership_expires_at
  'early_renew_at' => string|null,  // optional — membership_early_renew_at
]
```

Reloads `$this->meta_data` on success. Returns `true` on success, logs and returns `false` on failure.

### `get_parent_order_id(): int|false`

Returns the linked WooCommerce parent order ID from `membership_parent_order_id`, or `false` if not set.

### `get_subscription_id(): int|false`

Returns the linked WooCommerce subscription ID from `membership_subscription_id`, or `false` if not set.

### `get_dates(): array`

Returns the stored group dates as:

```php
[
  'starts_at'      => string,
  'ends_at'        => string,
  'expires_at'     => string,
  'early_renew_at' => string,
]
```

> **Date convention:** All date boundaries are computed in the MDP timezone (via `Utilities::get_mdp_day_start()` / `get_mdp_day_end()`) and then stored and returned in UTC. `starts_at` is snapped to the start of the MDP day; `ends_at`, `expires_at`, and `early_renew_at` are snapped to the end of the MDP day. Never snap boundaries to UTC midnight directly.

---

### `get_membership_status(): string|false`

Returns the `membership_status` meta value for this group, or `false` if not set.

### `set_membership_status( string $status ): bool`

Sets the `membership_status` meta value directly. This remains public as a low-level developer escape hatch, but normal lifecycle flows should use `transition_to()` so transition rules, dates, and side effects are applied consistently. The value must be one of the slugs returned by `Helper::get_all_status_names()`. Returns `true` on success. Logs an error and returns `false` if the status is not in the allowed list or if the meta update fails.

### `transition_to( string $new_status ): array|false`

Executes a group status transition and its side effects. This is the supported lifecycle entrypoint for status changes. It applies transition rules, plans transition dates, activates the linked subscription for `pending -> active`, and persists the new group state. Child-status cascading is currently a TODO placeholder and does not run. Returns an array containing `success_message` and `bypassed` on success, or `false` when the requested transition cannot be performed.

### `apply_edit_fields( array $normalized_fields ): bool`

Persists normalized group edit fields to the group post.

- Treats unchanged values as a successful no-op, because WordPress returns
  `false` from `update_post_meta()` when the submitted value already matches the
  stored meta.
- Returns `false` only when a meta write genuinely fails and the persisted
  value still does not match the submitted value after the update attempt.

### `cascade_dates_to_members( array $normalized_fields ): void`

Currently a TODO placeholder. It intentionally performs no updates until group/member edit propagation rules are finalized.

---

### `get_individual_memberships(): array`

Returns all individual membership CPT posts that have `membership_group_id` set to this group's post ID.

---

## Meta Keys

| Key | Type | Description |
|---|---|---|
| `user_id` | `int` | WP user ID of the group owner — the only owner field stored; derive email/name/UUID from the WP user at runtime |
| `org_uuid` | `string` | MDP organisation UUID |
| `org_name` | `string` | MDP organisation legal name (cached) |
| `membership_status` | `string` | Membership group status (see vocabulary above) |
| `membership_group_config_id` | `int` | Linked membership group config post ID |
| `membership_parent_order_id` | `int` | Linked WooCommerce order ID |
| `membership_subscription_id` | `int` | Linked WooCommerce subscription ID |
| `membership_group_id` | `int` | Set on individual membership posts to link them to this group |

---
