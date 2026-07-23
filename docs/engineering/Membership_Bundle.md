---
title: "Membership_Bundle"
audience: [developer]
php_class: Membership_Bundle
source_files: ["includes/Membership_Bundle.php"]
---

# Membership_Bundle

**File:** `includes/Membership_Bundle.php`
**Namespace:** `Wicket_Memberships`

Represents a single Membership Bundle CPT record. Wraps post meta access and provides the canonical API for reading and writing bundle-level data.

**CPT slug:** `wicket_mship_bundle` — via `Helper::get_membership_bundle_cpt_slug()`

## Architecture position

`Membership_Bundle` is the model layer. It owns all post meta reads/writes, WooCommerce subscription side effects, and child membership cascade logic. It does not handle HTTP or REST concerns.

Call chain: `Membership_Bundle_WP_REST_Controller` → `Membership_Bundle_Admin_Controller` → `Membership_Bundle`

---

## Status Vocabulary

| Constant | Meta value | Description |
|---|---|---|
| `Wicket_Memberships::STATUS_PENDING` | `pending` | Created, not yet activated by admin |
| `Wicket_Memberships::STATUS_ACTIVE` | `active` | Active membership period |
| `Wicket_Memberships::STATUS_DELAYED` | `delayed` | Start date in future |
| `Wicket_Memberships::STATUS_GRACE` | `grace_period` | Past end date, within grace period |
| `Wicket_Memberships::STATUS_EXPIRED` | `expired` | Past grace period, no longer active |
| `Wicket_Memberships::STATUS_CANCELLED` | `cancelled` | Manually or programmatically cancelled — terminal |

---

## Error Code Reference

All `WP_Error` codes that can be returned by this class's public methods:

| Code | Source method(s) | Meaning |
|---|---|---|
| `invalid_bundle_status` | `add_member`, `remove_member`, `move_individual_membership` | Bundle not in a manageable status |
| `missing_user_id` | `add_member` | No user ID supplied for new-member path |
| `bundle_ended` | `add_member`, `move_individual_membership` | Today is past bundle end date |
| `bundle_no_dates` | `add_member`, `move_individual_membership` | Bundle has no date meta |
| `invalid_user` | `add_member`, `remove_member`, `move_individual_membership` | WP user cannot be resolved |
| `invalid_tier` | `add_member` | Tier post not found or wrong CPT |
| `ambiguous_product` | `add_member` | Tier has >1 product; `product_id` required |
| `no_product` | `add_member` | No product found for tier |
| `product_tier_mismatch` | `add_member` | Product does not belong to tier |
| `invalid_membership` | `add_member` (existing path), `remove_member`, `move_individual_membership` | Membership post not found or wrong CPT |
| `membership_not_in_bundle` | `remove_member`, `move_individual_membership` | Membership does not belong to this bundle |
| `create_failed` | `add_member`, `move_individual_membership` | Downstream membership creation failed |
| `mdp_create_failed` | `provision_individual_membership_record` | MDP returned an error on member create |
| `mdp_error` | `provision_individual_membership_record` | MDP API error (non-create) |
| `wcs_unavailable` | `remove_member`, `provision_standalone_individual_membership` | WooCommerce Subscriptions not active |
| `order_create_failed` | `provision_standalone_individual_membership` | WC order creation failed |
| `subscription_create_failed` | `provision_standalone_individual_membership` | WCS subscription creation failed |
| `membership_post_not_found` | `provision_standalone_individual_membership` | Cannot resolve membership post ID after creation |
| `no_subscription` | `add_subscription_line_item`, `remove_subscription_line_item` | No subscription linked to bundle |
| `subscription_not_found` | `add_subscription_line_item`, `remove_subscription_line_item` | Subscription post not found |
| `add_product_failed` | `add_subscription_line_item` | WCS line item add failed |
| `product_not_found` | `add_subscription_line_item` | WC product not found |

---

## Filters & Actions

| Hook | Type | Fired by | Args | Purpose |
|---|---|---|---|---|
| `wicket_memberships_individual_membership_created_for_bundle` | filter | `add_member()` | `array $result` | Allows modification of created membership result; must return array with `membership_post_id` |
| `wicket_bundle_early_renew_at` | AS action | `schedule_date_trigger_jobs()` | `[ 'bundle_post_id' => int ]` | Fires when `early_renew_at` date reached |
| `wicket_bundle_ends_at` | AS action | `schedule_date_trigger_jobs()` | `[ 'bundle_post_id' => int ]` | Fires when `ends_at` date reached |
| `wicket_bundle_expires_at` | AS action | `schedule_date_trigger_jobs()` | `[ 'bundle_post_id' => int ]` | Fires when `expires_at` date reached |

AS date-trigger jobs are consumed by `Membership_Bundle_Cron_Controller`, which re-fires them as `do_action` hooks for AutomateWoo. See `Membership_Bundle_Cron_Controller` for the downstream `do_action` names.

---

## Constructor

### `__construct( int $post_id )`

Loads the group by post ID. Sets `$post_id = 0` and `$meta_data = []` if the post does not exist or is not the membership bundle CPT type. Errors are logged via `Wicket()->log()`.

---

## Static Methods

### `create( string $name, int $membership_bundle_config_id, string $org_uuid, string $owner_uuid, string $start_date, bool $sync_to_mdp = true ): static|null`

Creates a new membership bundle post and populates all required meta in a single call. All parameters except `$sync_to_mdp` are required. Errors are logged via `Wicket()->log()`.

**Error handling:** Validation failures (empty `$name`, invalid config post ID, invalid UUID for `$org_uuid` or `$owner_uuid`, empty `$start_date`) throw `\RuntimeException` before any database writes. Post-creation failures (failed meta writes, failed date writes) return `null` after rolling back the partially-created post via `wp_delete_post()`. Callers must catch `\RuntimeException` in addition to checking for a `null` return.

**Parameters:**

| Parameter | Type | Description |
|---|---|---|
| `$name` | `string` | Post title for the bundle (must be non-empty) |
| `$membership_bundle_config_id` | `int` | Post ID of the linked `Membership_Bundle_Config` |
| `$org_uuid` | `string` | MDP organisation UUID |
| `$owner_uuid` | `string` | MDP person UUID of the bundle owner |
| `$start_date` | `string` | ISO 8601 start date for the membership period (must be non-empty) |
| `$sync_to_mdp` | `bool` | Default `true`. Set `false` when importing a bundle that already exists in MDP, to skip `sync_mdp_create()` and avoid creating a duplicate record there. The caller is then responsible for seeding `membership_bundle_mdp_uuid` directly. |

Initial membership status: if `start_date` is in the future → `delayed`; otherwise → `pending`. Bundle memberships always start `pending` so an admin must explicitly activate them. Dates (end, expiry, early-renewal) are derived from the linked config via `get_membership_dates()`, anchored to the supplied `start_date`. A pending WooCommerce subscription is created and linked via `membership_subscription_id` post meta; if subscription creation fails the bundle is still returned (non-fatal, logged). A fresh `membership_bundle_group_uuid` is generated — use `renew_bundle()` instead when creating a renewal term that must share the same series UUID.

---

### `renew_bundle( \WC_Subscription $subscription, array $new_dates ): static|null`

Instance method. Creates a new bundle post for a renewal term of the current bundle. **Use this instead of `create()` for all renewal flows.**

Key differences from `create()`:
- Reuses the existing WC subscription (updates its `next_payment` and `end` dates to the new term) — no new subscription created.
- Carries the existing `membership_bundle_group_uuid` forward so all renewal posts share a series link.
- Accepts pre-calculated `$new_dates` from `Membership_Bundle_Config::get_membership_dates()` rather than deriving internally.
- Does **not** cancel the old bundle — that is handled by the caller (`handle_bundle_renewal`).

**Parameters:**

| Parameter | Type | Description |
|---|---|---|
| `$subscription` | `\WC_Subscription` | The existing WC subscription to reuse |
| `$new_dates` | `array` | Output of `Membership_Bundle_Config::get_membership_dates()` for the new term |

Returns `null` and logs on failure (post is rolled back). On success the new bundle post is always in `delayed` status — `renew_bundle()` unconditionally sets `STATUS_DELAYED` regardless of start date.

**Fields accessible on the returned object:**

| Field | Access |
|---|---|
| Post ID | `$group->post_id` |
| Post status | `get_post( $group->post_id )->post_status` |
| Membership status | `$group->get_membership_status()` → always `'delayed'` |
| Start date | `$group->get_dates()['starts_at']` |
| End date | `$group->get_dates()['ends_at']` |
| Expiration date | `$group->get_dates()['expires_at']` _(empty string if config has no grace period)_ |
| Org UUID | `$group->get_org_uuid()` |
| Owner user ID | `$group->get_owner_id()` |
| Subscription ID | `$group->get_subscription_id()` |
| Renewal type | `$group->get_config()->get_renewal_type()` _(not a direct field — via config)_ |

### `create_bundle_subscription(): int|false` _(private)_

Creates a pending WooCommerce subscription for a freshly-created bundle and writes two post meta keys onto it:

| Meta key | Value |
|---|---|
| `membership_bundle_id` | Bundle post ID |
| `_org_uuid` | Org UUID from the bundle |

`billing_period` and `billing_interval` are sourced from `$config->get_period_data()` so the values match the bundle config cycle (anniversary configs use their configured period; calendar configs fall back to `year` / `1`). `end` is set to `expires_at` (grace-period end), falling back to `ends_at` when no grace period is configured, mirroring `Membership_Subscription_Controller::create_subscriptions()`. `next_payment` is only set when `$config->is_renewal_subscription()` returns `true`; it maps to `ends_at` so WCS triggers renewal at the membership period end.

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

### `provision_individual_membership_record( int $user_id, int $tier_post_id, ?int $product_id, ?int $variation_id, string $start_date, int $link_to_bundle_id, bool $is_renewal = false ): int|WP_Error` _(private)_

Creates an individual membership record linked to a bundle. Used by `add_member()` and `move_individual_membership()`. Creates only:
- MDP record (via `Membership_Controller::create_membership_record()`)
- WP membership post with all meta (via `create_local_membership_record()`)
- Line item on the bundle subscription (via `add_subscription_line_item()`)

No personal WC order or subscription is created — the bundle's subscription covers billing for bundle-linked members.

`$link_to_bundle_id` is required (non-nullable). `membership_bundle_id` is written to the membership post after creation. Line item failure is non-fatal. When `$is_renewal` is `true`, the subscription line item add is skipped — the renewal batch handler updates the existing line item in-place.

Renamed from `create_individual_membership_for_group()`. Fail states: `invalid_user`, `invalid_tier`, `ambiguous_product`, `no_product`, `product_tier_mismatch`, `mdp_create_failed`, `create_failed`.

### `provision_standalone_individual_membership( int $user_id, int $tier_post_id, ?int $product_id, string $start_date, array $bundle_dates, string $admin_note = '', bool $skip_tier_approval = false ): int|WP_Error` _(private)_

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
5. Fire `do_action('wicket_member_create_record', $membership, false, false, $skip_tier_approval)` — triggers `create_membership_record()` which owns all downstream side effects including subscription date writes via `update_membership_subscription()` (MDP timezone-aware). `$skip_tier_approval` is passed by the caller (`remove_member()` / `cancel_keep_as_individual()`) as `true` when the bundle was `active` at the moment of release — this bypasses `Membership_Tier::is_approval_required()` so a member already vetted under an active bundle doesn't land back in `pending` on release. If the bundle was only `pending`/`delayed`, the tier gate still applies.

**Note:** `end` and `next_payment` dates are not set on the subscription at creation time. `update_membership_subscription()` inside `create_membership_record()` applies these values using `Utilities::get_mdp_day_end()` / `get_mdp_day_start()` for correct timezone pinning.

Resolves the resulting membership post ID by scanning subscription line item `_membership_post_id_renew` meta after the action fires, with a DB query fallback.

Fail states: `wcs_unavailable`, `invalid_user`, `order_create_failed`, `subscription_create_failed`, `membership_post_not_found`.

---

## Instance Methods

### `add_member( ?int $user_id, int $tier_post_id, ?int $product_id = null, ?int $variation_id = null, ?int $existing_membership_post_id = null, bool $is_renewal = false, ?string $start_date_override = null, bool $skip_status_guard = false ): int|WP_Error`

Single entry point for adding an individual membership to a bundle. Covers two flows:

- **New member** (`$existing_membership_post_id = null`): `$user_id` must be provided. Creates a fresh membership and links it to the bundle.
- **Existing member** (`$existing_membership_post_id` provided): cancels the existing membership (sets its status to `cancelled`), resolves the user ID from it, then creates a new membership with bundle dates and links it. `$user_id` is ignored.

Both paths share start-date logic via `resolve_member_start_date()` unless `$start_date_override` is provided: if today is within the bundle date window, start = today; if today is before the bundle start, start = bundle start; if today is after the bundle end, returns `WP_Error('bundle_ended')`. When `$start_date_override` is provided it is used directly, bypassing `resolve_member_start_date()` — used by the renewal batch handler to anchor new memberships to the bundle's `starts_at`.

The new membership inherits the bundle's current `membership_status` as its initial status. `create_local_membership_record()` may override this: if the tier requires approval the status becomes `pending`; if the start date is in the future the status becomes `delayed`. These overrides take precedence.

Bundle must be in `pending`, `active`, or `delayed` status; returns `WP_Error('invalid_bundle_status')` otherwise — unless `$skip_status_guard` is `true`, which skips this check entirely. This bypass exists **only** for the bundle import path, so historical members can attach to bundles imported as `expired`/`cancelled`/`grace-period`. Do not use it from any other caller — it defeats `assert_bundle_is_manageable()` intentionally.

`product_id` is auto-resolved from the tier when omitted; fails with `WP_Error('ambiguous_product')` if the tier has more than one product. When `variation_id` is supplied, it is stored as `membership_product_id` instead of the parent `product_id` — matching the subscription-driven membership flow where variation ID takes precedence. Returns the new membership post ID on success.

Fires filter `wicket_memberships_individual_membership_created_for_bundle` after the membership record is created. Returning an array without `membership_post_id` from this filter will cause a `create_failed` error.

Fail states: `invalid_bundle_status`, `missing_user_id`, `bundle_ended`, `bundle_no_dates`, `invalid_user`, `invalid_tier`, `ambiguous_product`, `no_product`, `product_tier_mismatch`, `invalid_membership` (existing path), `missing_user_id` (existing record has no user_id meta), `create_failed`.

---

### `remove_member( int $membership_post_id, string $mode ): int|WP_Error`

Removes an individual membership from this group. Two modes:

- **`cancel`**: cancels the group-linked membership immediately (sets status to `cancelled`). Returns the cancelled membership post ID.
- **`keep_as_individual`**: captures all group and membership meta **before** any state mutations, cancels the group-linked membership, removes its subscription line item, then calls `provision_standalone_individual_membership()` to create a fully-backed standalone membership. Start date is always today (UTC) — `resolve_member_start_date()` is intentionally not used because it returns `WP_Error` when today > `ends_at`, which would block releases from grace-period groups. Dates inherited from group: `ends_at`, `expires_at`, `early_renew_at`. Returns the new membership post ID.

In both modes, after cancellation, calls `remove_subscription_line_item()` to remove the matching line item from the bundle subscription. Failure to remove the line item is non-fatal — it is logged and the method continues.

Bundle must be in `pending`, `active`, or `delayed` status; returns `WP_Error('invalid_bundle_status')` otherwise. An expired or cancelled bundle is blocked by the status gate before any date checks run.

Fail states: `invalid_bundle_status`, `invalid_membership`, `membership_not_in_bundle`, `invalid_user`, `wcs_unavailable`, `order_create_failed`, `subscription_create_failed`, `membership_post_not_found`.

---

### `move_individual_membership( int $membership_post_id, Membership_Bundle $target_bundle ): int|WP_Error`

Moves an individual membership from this bundle to a target bundle. Cancels the source membership, removes its line item from the source bundle subscription, then creates a new membership linked to the target bundle. The new membership inherits the same user, tier, and product; start date is resolved against the target bundle's date window via `resolve_member_start_date()`. The target bundle's `add_member()` path adds a new line item to the target bundle subscription.

Both the source and target bundles must be in `pending`, `active`, or `delayed` status. The membership must belong to the source bundle.

If creation of the new membership fails after cancellation, a `WP_Error` is returned with an explicit message noting that the source was cancelled and the member must be manually re-added. No rollback is attempted. Line item removal failure on the source bundle is non-fatal — logged and continues.

Fail states: `invalid_bundle_status` (source or target), `invalid_membership`, `membership_not_in_bundle`, `bundle_no_dates` (target), `bundle_ended` (target), `invalid_user`, `create_failed`.

---

### `get_name(): string`

Returns the post title (group name). Returns an empty string if the group post is not loaded.

---

### `set_owner( string $uuid ): int|false`

Accepts an MDP person UUID. Validates the format via `isValidUuid()`, then resolves the corresponding WP user. Resolution strategy depends on the `BYPASS_WICKET` flag:

- **Normal mode:** calls `wicket_create_wp_user_if_not_exist()` — creates the WP user from MDP if not already present.
- **Bypass mode (`BYPASS_WICKET`):** calls `get_user_by( 'login', $uuid )` only — no MDP API call. Returns `false` if the user does not already exist locally.

Stores only the WP user ID (`user_id`) and updates `post_author`. Derived fields — display name, email, and UUID — are intentionally not stored to avoid persisting values that can change independently of the membership record. Group ownership cannot be cleared through this method; malformed or unresolvable UUIDs are rejected. Returns the saved owner WP user ID on success and `false` on failure.

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

### `get_config(): Membership_Bundle_Config|false`

Returns the linked `Membership_Bundle_Config` object from `membership_bundle_config_id`, or `false` if not set.

### `set_config( int $config_post_id ): bool`

Validates that `$config_post_id` resolves to a valid `Membership_Bundle_Config`, writes `membership_bundle_config_id` meta, and reloads `$this->meta_data`. Returns `true` on success, logs and returns `false` on failure.

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

Reloads `$this->meta_data` on success. Returns `true` on success, logs and returns `false` on failure. A field whose new value is identical to the currently stored one is treated as a no-op success, not a failure — `update_post_meta()` returns `false` in that case even though nothing is wrong, so the current value is checked first.

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

`pending` permits `active` and `cancelled`. All other non-terminal statuses (`active`, `delayed`, `grace-period`) permit only `cancelled`. `expired` is never offered — expiry is driven automatically by `daily_membership_expiry_hook`.

Terminal statuses (`expired`, `cancelled`) return an empty array (no further transitions).

When the `BYPASS_STATUS_CHANGE_LOCKOUT` env flag is set, all statuses are returned (dev/testing only).

### `can_transition_to( string $new_status ): bool`

Full programmatic lifecycle guard used by `transition_to()`. Wider than `get_allowed_status_transitions()` — covers transitions that are valid but not manually selectable in the admin UI:

| From | Allowed |
|---|---|
| `pending` | `active` (admin UI only), `cancelled` |
| `active` | `grace-period`, `expired` (expiry hook), `cancelled` |
| `delayed` | `active`, `cancelled` |
| `grace-period` | `expired` (expiry hook), `cancelled` |
| `expired`, `cancelled` | _(none)_ |

`delayed → active`, `active → grace-period`, and `* → expired` are intentionally absent from `get_allowed_status_transitions()` — they are driven by daily cron hooks, not manual admin action. `pending → active` is the exception: it is performed manually via the group admin UI status select.

### `set_membership_status( string $status ): bool`

Sets the `membership_status` meta value directly. This remains public as a low-level developer escape hatch, but normal lifecycle flows should use `transition_to()` so transition rules, dates, and side effects are applied consistently. The value must be one of the slugs returned by `Helper::get_all_status_names()`. Returns `true` on success. Logs an error and returns `false` if the status is not in the allowed list or if the meta update genuinely fails — setting a status identical to the currently stored one is treated as a no-op success (see the `set_dates()` note above for why).

### `transition_to( string $new_status ): array{success_message: string, bypassed: bool}|false`

Executes a group status transition and its side effects. This is the supported lifecycle entrypoint for status changes. It applies transition rules via `can_transition_to()`, plans transition dates via `plan_status_transition()`, activates the linked subscription for `pending → active` and `delayed → active`, persists the new group state via `apply_status_transition()`, and cascades the new status to child memberships via `cascade_status_to_members()`. Returns an array containing `success_message` and `bypassed` on success, or `false` when the requested transition cannot be performed.

When `BYPASS_STATUS_CHANGE_LOCKOUT` is set, only the `can_transition_to()` guard is skipped — subscription activation, member cascade, and MDP sync still run for any transition `plan_status_transition()` recognizes. `bypassed` is only `true` when the requested transition falls outside `plan_status_transition()`'s recognized paths and the method falls back to a raw `set_membership_status()` write with no side effects (dev/testing use only).

`plan_status_transition()` covers the following paths:
- `pending → active`: recalculates dates from config anchored to activation date (today); activates subscription.
- `delayed → active`: preserves dates set at creation; status change only (no subscription activation).
- `active → grace-period`: preserves all existing dates; status change only.
- `* → expired`: sets `ends_at` and `expires_at` to end-of-day +1; no subscription change.
- `* → cancelled`: date behaviour varies by source status (see `transition_to_cancelled_at_end_date()` for the date-preserving cancel path).

All other combinations return `false`, causing `transition_to()` to abort.

---

### `transition_to_cancelled_at_end_date(): array{success_message: string}|false`

Cancels the group while preserving the existing `ends_at`, so members retain access until the paid period runs out. This is a specialised path that cannot be expressed through `transition_to('cancelled')` because `plan_status_transition()` always recalculates `ends_at` on cancel.

What it does:
- Validates the group is in a cancellable status (`pending`, `delayed`, `active`, `grace_period`). Returns `false` otherwise.
- Returns `false` if `membership_ends_at` is empty — without an end date there is no meaningful point to defer cancellation to. (The controller guards this too, but the method is self-contained.)
- Calls `apply_status_transition('cancelled', ...)` with `ends_at` preserved and `expires_at` collapsed to `ends_at` (removes grace period).
- Updates `membership_expires_at` on each individual membership to match `ends_at`. **Does not change their status** — members keep active access until that date, and `daily_membership_expiry_hook` handles expiry naturally.

Subscription handling (pending-cancel + deferred hard-cancel AS job) is the caller's responsibility — see `Membership_Bundle_Admin_Controller::cancel_bundle()` path B.

### `cancel_keep_as_individual(): array{success_message: string, warnings?: string[]}|false`

Cancels the group and converts every individual group membership to a standalone individual membership, preserving the remaining group term.

This is Path C of the bundle cancellation flow (see `Membership_Bundle_Admin_Controller::cancel_bundle()`). It encapsulates the full conversion loop so the controller stays a thin delegator.

**Phase 1 — Read before cancel.** `assert_group_is_manageable()` rejects calls on a cancelled group, so all member meta (user ID, tier, product ID, variation resolution) is collected from `get_individual_memberships()` before the group status changes. `$skip_tier_approval` is also captured here (`get_membership_status() === STATUS_ACTIVE`) — must happen before Phase 2, since the transition below overwrites the status this check reads.

**Phase 2 — Cancel the group.** Calls `transition_to('cancelled')`, which cascades the cancelled status to child memberships and cancels the group WC subscription.

**Phase 3 — Per-member conversion.** For each member:
- Calls `Membership_Controller::update_membership_status()` to explicitly cancel the existing group membership post (the cascade in Phase 2 already does this, but the explicit call ensures the post is in the correct state before provisioning).
- Calls `provision_standalone_individual_membership()` with the group dates and the Phase 1 `$skip_tier_approval` flag, so each member inherits the remaining term rather than a fresh full-length period, and does not get stranded in `pending` by tier approval if the group was active before cancellation. The admin note on the resulting order and subscription records the origin group ID.

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

### `cascade_dates_to_members( array $normalized_fields, array $pre_edit_dates ): void`

Iterates all child individual memberships and propagates date field changes from `$normalized_fields` to each member, applying per-field exception logic. Takes two parameters: `array $normalized_fields` (the edited fields) and `array $pre_edit_dates` (the dates before the edit). Skips members in `cancelled` or `expired` status.

Per-field exception rules:
- `starts_at`: skip if the member's current `starts_at` is after the bundle's pre-edit `starts_at` (member has a later custom start).
- `ends_at`: skip if the member's current `ends_at` is before the bundle's pre-edit `ends_at` (member has an earlier custom end). When `ends_at` is written, `early_renew_at` is recalculated using the config renewal window to stay consistent.
- `expires_at`: same rule as `ends_at`.

---

### `get_individual_memberships( bool $active_only = true ): array`

Returns individual membership CPT posts that have `membership_bundle_id` set to this bundle's post ID.

When `$active_only` is `true` (default), memberships with `membership_status` of `cancelled` or `expired` are excluded. Pass `false` to retrieve all memberships regardless of status — required for internal operations that must act on every member (status cascades, date cascades, bulk cancel).

---

## Meta Keys

| Key | Type | Description |
|---|---|---|
| `user_id` | `int` | WP user ID of the bundle owner — the only owner field stored; derive email/name/UUID from the WP user at runtime |
| `org_uuid` | `string` | MDP organisation UUID |
| `org_name` | `string` | MDP organisation legal name (cached) |
| `membership_status` | `string` | Membership bundle status (see vocabulary above) |
| `membership_bundle_config_id` | `int` | Linked membership bundle config post ID |
| `membership_parent_order_id` | `int` | Linked WooCommerce order ID |
| `membership_subscription_id` | `int` | Linked WooCommerce subscription ID |
| `membership_bundle_id` | `int` | Set on individual membership posts to link them to this bundle |
| `membership_bundle_group_uuid` | `string` | Unique series UUID shared across all renewal-term posts for this bundle |

---

## Additional Methods

### `get_bundle_group_uuid(): string|false`

Returns the `membership_bundle_group_uuid` meta value for this bundle, or `false` if not set. This UUID is shared across all renewal-term posts in the same series.

### `set_bundle_group_uuid( string $uuid ): bool`

Writes `membership_bundle_group_uuid` post meta. Returns `true` on success, `false` on failure. Used by `create()` (new UUID) and `renew_bundle()` (carries the existing UUID forward).

### `cancel_for_renewal( bool $preserve_end_date = false ): bool`

Cancels the bundle post as part of a renewal without cascading the cancellation status to child individual memberships. Child memberships are historical records of the old term and must remain accessible for per-bundle member count queries; the new term's members already exist on the new bundle post. When `$preserve_end_date` is `true` (early renewal path), the existing `ends_at` is preserved so the bundle record reflects the full paid term. When `false` (same-day renewal), `ends_at` is collapsed to now. Returns `true` on success, `false` if the status write failed.

---

## Action Scheduler Date Trigger Jobs

### `schedule_date_trigger_jobs(): void`

Schedules (or reschedules) three one-time Action Scheduler jobs for this bundle — one each for `early_renew_at`, `ends_at`, and `expires_at`. Jobs fire `do_action` hooks consumed by AutomateWoo triggers. No status transitions are performed.

Called from `create()` after dates are set, and from `Membership_Bundle_Admin_Controller::update_bundle_entity_record()` after dates are edited. Existing jobs are cancelled via `as_unschedule_action` before scheduling new ones, so date edits never leave stale jobs.

Skips scheduling for any date that is empty. Args passed to AS: `[ 'bundle_post_id' => $this->post_id ]` — used by the handlers in `Membership_Bundle_Cron_Controller` to fire the correct `do_action`.

See `Membership_Bundle_Cron_Controller` for the handler methods and the `do_action` hooks fired.

---
