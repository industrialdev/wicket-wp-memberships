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

Initial membership status is determined by the same 3-way logic used for individual memberships: if the config requires approval → `pending`; if `start_date` is in the future → `delayed`; otherwise → `active`. Dates (end, expiry, early-renewal) are derived from the linked config via `get_membership_dates()`, anchored to the supplied `start_date`. A pending WooCommerce subscription is created and linked via `membership_subscription_id` post meta; if subscription creation fails the group is still returned (non-fatal, logged).

**Fields accessible on the returned object:**

| Field | Access |
|---|---|
| Post ID | `$group->post_id` |
| Post status | `get_post( $group->post_id )->post_status` |
| Membership status | `$group->get_membership_status()` → `'pending'`, `'delayed'`, or `'active'` depending on config and start date |
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

Adds a WooCommerce subscription line item to the group subscription for an individual membership. Called automatically from `provision_individual_membership_record()` after the membership record is created. Failure is non-fatal — the caller logs and continues, leaving the membership record intact.

**Line item meta written:**

| Meta key | Value |
|---|---|
| `_membership_post_id` | Individual membership post ID |
| `_member_name` | Member's `display_name` (omitted if user cannot be resolved) |

`$product_id` must be the variation ID when a variation is in use (caller passes `$variation_id ?? $product_id`, matching the precedence rule used for `membership_product_id`). Price comes from the WC product — no custom pricing logic. Calls `$sub->calculate_totals()` and `$sub->save()` after adding the item.

Fail states (all non-fatal at call site): `wcs_unavailable`, `no_subscription`, `subscription_not_found`, `product_not_found`, `add_product_failed`.

### `remove_subscription_line_item( int $membership_post_id ): true|WP_Error` _(private)_

Removes the WooCommerce subscription line item for an individual membership from the group subscription. Scans all items on the subscription for one whose `_membership_post_id` meta matches `$membership_post_id`, removes it, then calls `$sub->calculate_totals()` and `$sub->save()`. If no matching item is found the call is a no-op and returns `true` — this handles the case where the item was never added (e.g. the non-fatal add path failed).

Called from `remove_member()` (both modes) and from the source-group side of `move_individual_membership()`. Failure is non-fatal in both callers — the membership cancellation has already occurred; a stale line item is a billing gap the admin can reconcile.

Fail states (all non-fatal at call site): `wcs_unavailable`, `no_subscription`, `subscription_not_found`.

### `provision_individual_membership_record( int $user_id, int $tier_post_id, ?int $product_id, ?int $variation_id, string $start_date, int $link_to_group_id ): int|WP_Error` _(private)_

Creates an individual membership record linked to a group. Used by `add_member()` and `move_individual_membership()`. Creates only:
- MDP record (via `Membership_Controller::create_membership_record()`)
- WP membership post with all meta (via `create_local_membership_record()`)
- Line item on the group subscription (via `add_subscription_line_item()`)

No personal WC order or subscription is created — the group's subscription covers billing for group-linked members.

`$link_to_group_id` is required (non-nullable). `membership_group_id` is written to the membership post after creation. Line item failure is non-fatal.

Renamed from `create_individual_membership_for_group()`. Fail states: `invalid_user`, `invalid_tier`, `ambiguous_product`, `no_product`, `product_tier_mismatch`, `create_failed`.

### `provision_standalone_individual_membership( int $user_id, int $tier_post_id, ?int $product_id, string $start_date, array $group_dates ): int|WP_Error` _(private)_

Creates a fully-backed standalone individual membership for a member released from the group via the `keep_as_individual` mode of `remove_member()`. Replicates the checkout-driven membership creation flow in code. The resulting membership is identical to what a checkout purchase produces.

**Objects created:**

| Object | Notes |
|---|---|
| WC order (`pending`) | Required — `scheduler_dates_for_expiry()` only schedules Action Scheduler lifecycle jobs when `membership_parent_order_id` is non-zero |
| WC subscription (`pending`) | Created explicitly via `wcs_create_subscription()` — WCS does not auto-create for programmatic orders |
| WP membership post | Created via `do_action('wicket_member_create_record')` → `Membership_Controller::create_membership_record()` |
| MDP record | Created by `create_membership_record()` via `wicket_assign_individual_membership()` |
| Action Scheduler jobs | Scheduled by `scheduler_dates_for_expiry()` inside `create_membership_record()` |

**Sequence:**

1. Create WC order (`wc_create_order`) with product line item. Order stays `pending` — no payment.
2. Create WC subscription (`wcs_create_subscription`) with `start_date` = group start. Inherits `_requires_manual_renewal` from the group subscription rather than re-deriving from user autopay preference.
3. Build membership data array with group dates (`ends_at`, `expires_at`, `early_renew_at`) instead of `$config->get_membership_dates()` — member inherits remaining group term, not a fresh full-length term. Billing period sourced from `Membership_Config::get_period_data()`.
4. Write `_wicket_membership_{product_id}` JSON blob to both order and subscription post meta.
5. Fire `do_action('wicket_member_create_record', $membership, false, false)` — triggers `create_membership_record()` which owns all downstream side effects including subscription date writes via `update_membership_subscription()` (MDP timezone-aware).

**Note:** `end` and `next_payment` dates are not set on the subscription at creation time. `update_membership_subscription()` inside `create_membership_record()` applies these values using `Utilities::get_mdp_day_end()` / `get_mdp_day_start()` for correct timezone pinning.

Resolves the resulting membership post ID by scanning subscription line item `_membership_post_id_renew` meta after the action fires, with a DB query fallback.

Fail states: `wcs_unavailable`, `invalid_user`, `order_create_failed`, `subscription_create_failed`, `membership_post_not_found`.

---

## Instance Methods

### `add_member( ?int $user_id, int $tier_post_id, ?int $product_id = null, ?int $variation_id = null, ?int $existing_membership_post_id = null ): int|WP_Error`

Single entry point for adding an individual membership to a group. Covers two flows:

- **New member** (`$existing_membership_post_id = null`): `$user_id` must be provided. Creates a fresh membership and links it to the group.
- **Existing member** (`$existing_membership_post_id` provided): cancels the existing membership (sets its status to `cancelled`), resolves the user ID from it, then creates a new membership with group dates and links it. `$user_id` is ignored.

Both paths share start-date logic via `resolve_member_start_date()`: if today is within the group date window, start = today; if today is before the group start, start = group start; if today is after the group end, returns `WP_Error('group_ended')`.

The new membership inherits the group's current `membership_status` as its initial status. `create_local_membership_record()` may override this: if the tier requires approval the status becomes `pending`; if the start date is in the future the status becomes `delayed`. These overrides take precedence.

Group must be in `pending`, `active`, or `delayed` status; returns `WP_Error('invalid_group_status')` otherwise.

`product_id` is auto-resolved from the tier when omitted; fails with `WP_Error('ambiguous_product')` if the tier has more than one product. When `variation_id` is supplied, it is stored as `membership_product_id` instead of the parent `product_id` — matching the subscription-driven membership flow where variation ID takes precedence. Returns the new membership post ID on success.

Fires filter `wicket_memberships_individual_membership_created_for_group` after the membership record is created. Returning an array without `membership_post_id` from this filter will cause a `create_failed` error.

Fail states: `invalid_group_status`, `missing_user_id`, `group_ended`, `group_no_dates`, `invalid_user`, `invalid_tier`, `ambiguous_product`, `no_product`, `product_tier_mismatch`, `invalid_membership` (existing path), `missing_user_id` (existing record has no user_id meta), `create_failed`.

> **TODO:** Link `membership_subscription_id` and `membership_parent_order_id` to the group's WooCommerce subscription once group subscription management exists.

---

### `remove_member( int $membership_post_id, string $mode ): int|WP_Error`

Removes an individual membership from this group. Two modes:

- **`cancel`**: cancels the group-linked membership immediately (sets status to `cancelled`). Returns the cancelled membership post ID.
- **`keep_as_individual`**: captures all group and membership meta **before** any state mutations, cancels the group-linked membership, removes its subscription line item, then calls `provision_standalone_individual_membership()` to create a fully-backed standalone membership. Start date is always today (UTC) — `resolve_member_start_date()` is intentionally not used because it returns `WP_Error` when today > `ends_at`, which would block releases from grace-period groups. Dates inherited from group: `ends_at`, `expires_at`, `early_renew_at`. Returns the new membership post ID.

In both modes, after cancellation, calls `remove_subscription_line_item()` to remove the matching line item from the group subscription. Failure to remove the line item is non-fatal — it is logged and the method continues.

Group must be in `pending`, `active`, or `delayed` status; returns `WP_Error('invalid_group_status')` otherwise. An expired or cancelled group is blocked by the status gate before any date checks run.

Fail states: `invalid_group_status`, `invalid_membership`, `membership_not_in_group`, `invalid_user`, `wcs_unavailable`, `order_create_failed`, `subscription_create_failed`, `membership_post_not_found`.

---

### `move_individual_membership( int $membership_post_id, Membership_Group $target_group ): int|WP_Error`

Moves an individual membership from this group to a target group. Cancels the source membership, removes its line item from the source group subscription, then creates a new membership linked to the target group. The new membership inherits the same user, tier, and product; start date is resolved against the target group's date window via `resolve_member_start_date()`. The target group's `add_member()` path adds a new line item to the target group subscription.

Both the source and target groups must be in `pending`, `active`, or `delayed` status. The membership must belong to the source group.

If creation of the new membership fails after cancellation, a `WP_Error` is returned with an explicit message noting that the source was cancelled and the member must be manually re-added. No rollback is attempted. Line item removal failure on the source group is non-fatal — logged and continues.

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

### `get_allowed_status_transitions(): array<string, array{name: string, slug: string}>`

Returns the admin-facing status transitions allowed from the current group status.

All non-terminal statuses (`pending`, `active`, `delayed`, `grace-period`) permit only `cancelled` as a manual transition. `expired` is never offered — expiry is driven automatically by `daily_membership_expiry_hook`. `active` is not offered from `pending` — group activation is driven by subscription payment confirmation, not manual admin action.

Terminal statuses (`expired`, `cancelled`) return an empty array (no further transitions).

When the `BYPASS_STATUS_CHANGE_LOCKOUT` env flag is set, all statuses are returned (dev/testing only).

### `can_transition_to( string $new_status ): bool`

Full programmatic lifecycle guard used by `transition_to()`. Wider than `get_allowed_status_transitions()` — covers transitions that are valid but not manually selectable in the admin UI:

| From | Allowed |
|---|---|
| `pending` | `active` (payment confirmation), `cancelled` |
| `active` | `grace-period`, `expired` (expiry hook), `cancelled` |
| `delayed` | `active`, `cancelled` |
| `grace-period` | `expired` (expiry hook), `cancelled` |
| `expired`, `cancelled` | _(none)_ |

`pending → active` and `*→ expired` are intentionally absent from `get_allowed_status_transitions()` — they must not appear in the admin UI.

### `set_membership_status( string $status ): bool`

Sets the `membership_status` meta value directly. This remains public as a low-level developer escape hatch, but normal lifecycle flows should use `transition_to()` so transition rules, dates, and side effects are applied consistently. The value must be one of the slugs returned by `Helper::get_all_status_names()`. Returns `true` on success. Logs an error and returns `false` if the status is not in the allowed list or if the meta update fails.

### `transition_to( string $new_status ): array{success_message: string, bypassed: bool}|false`

Executes a group status transition and its side effects. This is the supported lifecycle entrypoint for status changes. It applies transition rules via `can_transition_to()`, plans transition dates via `plan_status_transition()`, activates the linked subscription for `pending → active`, persists the new group state via `apply_status_transition()`, and cascades the new status to child memberships via `cascade_status_to_members()`. Returns an array containing `success_message` and `bypassed` on success, or `false` when the requested transition cannot be performed.

---

### `transition_to_cancelled_at_end_date(): array{success_message: string}|false`

Cancels the group while preserving the existing `ends_at`, so members retain access until the paid period runs out. This is a specialised path that cannot be expressed through `transition_to('cancelled')` because `plan_status_transition()` always recalculates `ends_at` on cancel.

What it does:
- Validates the group is in a cancellable status (`pending`, `delayed`, `active`, `grace_period`). Returns `false` otherwise.
- Returns `false` if `membership_ends_at` is empty — without an end date there is no meaningful point to defer cancellation to. (The controller guards this too, but the method is self-contained.)
- Calls `apply_status_transition('cancelled', ...)` with `ends_at` preserved and `expires_at` collapsed to `ends_at` (removes grace period).
- Updates `membership_expires_at` on each individual membership to match `ends_at`. **Does not change their status** — members keep active access until that date, and `daily_membership_expiry_hook` handles expiry naturally.

Subscription handling (pending-cancel + deferred hard-cancel AS job) is the caller's responsibility — see `Group_Admin_Controller::cancel_group()` path B.

### `cancel_keep_as_individual(): array{success_message: string, warnings?: string[]}|false`

Cancels the group and converts every individual group membership to a standalone individual membership, preserving the remaining group term.

This is Path C of the group cancellation flow (see `Group_Admin_Controller::cancel_group()`). It encapsulates the full conversion loop so the controller stays a thin delegator.

**Phase 1 — Read before cancel.** `assert_group_is_manageable()` rejects calls on a cancelled group, so all member meta (user ID, tier, product ID, variation resolution) is collected from `get_individual_memberships()` before the group status changes.

**Phase 2 — Cancel the group.** Calls `transition_to('cancelled')`, which cascades the cancelled status to child memberships and cancels the group WC subscription.

**Phase 3 — Per-member conversion.** For each member:
- Calls `Membership_Controller::update_membership_status()` to explicitly cancel the existing group membership post (the cascade in Phase 2 already does this, but the explicit call ensures the post is in the correct state before provisioning).
- Calls `provision_standalone_individual_membership()` with the group dates, so each member inherits the remaining term rather than a fresh full-length period. The admin note on the resulting order and subscription records the origin group ID.

Non-fatal per-member errors (missing user, WCS unavailable, order/subscription failure) are collected in `warnings` and returned alongside `success_message`. Returns `false` only when the group transition itself fails.

### `apply_edit_fields( array $normalized_fields ): bool`

Persists normalized group edit fields to the group post.

- Treats unchanged values as a successful no-op, because WordPress returns
  `false` from `update_post_meta()` when the submitted value already matches the
  stored meta.
- Returns `false` only when a meta write genuinely fails and the persisted
  value still does not match the submitted value after the update attempt.

### `cascade_status_to_members( string $new_status ): void` _(private)_

Called by `transition_to()` after the group status write succeeds. Iterates all child individual memberships via `get_individual_memberships()` and writes `$new_status` to `membership_status` meta on each one. Skips any membership already in `cancelled` status — that is a final state. Logs an error for each write failure but continues processing remaining members.

---

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
