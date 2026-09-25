---
title: "Membership_Bundle_Admin_Controller"
audience: [developer]
php_class: Membership_Bundle_Admin_Controller
source_files: ["includes/Membership_Bundle_Admin_Controller.php"]
---

# Membership_Bundle_Admin_Controller

**File:** `includes/Membership_Bundle_Admin_Controller.php`
**Namespace:** `Wicket_Memberships`

Admin operations for membership bundle posts. Mirrors the shape of `Admin_Controller` but operates exclusively on the `wicket_mship_bundle` CPT. Individual-membership concerns (MDP record sync, tier/config lookups, user-meta JSON blobs) are intentionally absent — bundles are containers that hold individual memberships and do not have their own MDP membership records.

All public methods are `static` and return `\WP_REST_Response` or a plain `array` (for data retrieval methods).

**CPT slug:** `wicket_mship_bundle` — via `Helper::get_membership_bundle_cpt_slug()`

**Architecture position:** Orchestration layer — sits between the REST controller and the model. Request validation, payload normalisation, and transition planning stay here. All bundle-domain mutations and WooCommerce side effects are delegated to `Membership_Bundle`.

Call chain: `Membership_Bundle_WP_REST_Controller` → **`Membership_Bundle_Admin_Controller`** → `Membership_Bundle`

---

## Constructor

### `__construct()`

Stores the bundle CPT slug from `Helper::get_membership_bundle_cpt_slug()`. Also instantiated implicitly by the static methods via `new self()` where needed.

---

## Static Methods

### `get_admin_status_options( ?int $bundle_post_id = null ): array`

Returns available status options for a membership bundle.

- If `$bundle_post_id` is supplied, returns only valid transitions from the bundle's current `membership_status` meta value, via `Helper::get_allowed_transition_status()`.
- If omitted, returns all status names from `Helper::get_all_status_names()`.

---

### `bundle_admin_manage_status( int $bundle_post_id, string $new_status ): \WP_REST_Response`

Transitions a membership bundle to a new status. Supported transitions:

| From | To | Date behaviour |
|---|---|---|
| `pending` | `active` | Reads dates from the linked `Membership_Bundle_Config`; activates and dates the WC subscription |
| `pending` | `cancelled` | Sets start = yesterday, end = now, expires = now |
| `delayed` | `cancelled` | Same as pending → cancelled |
| `active` | `cancelled` | Sets end = tomorrow, expires = tomorrow; cancels subscription |
| `active` | `expired` | Sets end = tomorrow, expires = tomorrow; cancels subscription |
| `grace-period` | `cancelled` | Preserves existing end date; sets expires = now |

Delegates the full lifecycle operation to `Membership_Bundle::transition_to()`, which applies transition rules, plans dates, activates the linked subscription for `pending -> active`, and persists the new bundle status.

Returns a `400` response for invalid transitions, `404` if the post is not found or is the wrong CPT.

---

### `get_bundle_entity_records( int $bundle_post_id ): array|\WP_REST_Response`

Returns the data needed to populate the membership bundle entity view:

```php
[
  'ID'                 => int,
  'bundle_group_uuid'  => string,
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

### `update_bundle_entity_record( array $data ): \WP_REST_Response`

Updates editable fields on a bundle post. Expects these keys in `$data`:

| Key | Required | Notes |
|---|---|---|
| `bundle_post_id` | Yes | Must be a valid bundle CPT post |
| `membership_starts_at` | No | Must be before `membership_ends_at` |
| `membership_ends_at` | No | Must not be after `membership_expires_at` |
| `membership_expires_at` | No | |
| `membership_renewal_type` | No | |

Validates date ordering (start < end ≤ expires) before writing.

After a successful edit, if `membership_renewal_type` changed, calls `maybe_sync_renewal_type_next_payment()` to update the linked subscription's next-payment date.

---

### `maybe_sync_renewal_type_next_payment( Membership_Bundle $bundle, string $pre_edit_renewal_type, string $new_renewal_type ): void` _(private static)_

Syncs the linked WC subscription's `next_payment` date when the renewal type changes on a bundle edit.

- **Changed to `subscription`:** sets `next_payment` to `ends_at` (day-end, via `Utilities::get_mdp_day_end()`).
- **Changed away from `subscription`:** calls `$subscription->delete_date('next_payment')`.

No-ops when: renewal type is unchanged, no subscription is linked, subscription status is not `active`, or WCS is unavailable.

---

### `get_bundle_edit_page_info( string $bundle_group_uuid ): array|\WP_REST_Response`

Returns all data required to populate the membership bundle edit form. Accepts a bundle group UUID, queries all bundle posts sharing that UUID, and sorts them newest-first by `post_date`. The newest post supplies all top-level fields (org, owner, config, subscription). `membership_records` is built from every post in the series so the UI can stack all yearly instances.

Returns `404` if no posts in the series are found.

```php
[
  'ID'                  => int,
  'title'               => string,
  'meta'                => array,   // raw post meta
  'bundle_group_uuid'   => string,
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
      'ID'                     => int,     // the bundle post ID
      'name'                   => string,  // stored bundle name or post title
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

`membership_records` contains one entry per bundle post in the series (newest first). Child individual memberships are not listed there; they remain available through the separate bundle-members breakdown and filtered member-management links.

Fetches organisation data from `Helper::get_org_data()` and person data from `wicket_get_person_by_id()`. Fetches the linked WC subscription via `wcs_get_subscription()` and related orders via `WC_Subscription::get_related_orders('all')`. All date fields are ISO 8601.

---

### `get_bundle_members_by_tier( int $bundle_post_id ): array|\WP_REST_Response`

Returns the total member count and per-tier breakdown for a bundle:

```php
[
  'total_members' => int,
  'tiers'         => [
    [ 'tier_uuid' => string, 'tier_name' => string, 'member_count' => int ],
    // ...sorted alphabetically by tier_name
  ],
]
```

Queries child memberships via `Membership_Bundle::get_individual_memberships()`, groups them by `membership_tier_uuid` meta, and returns the totals. Members without a `tier_uuid` are skipped entirely (they do not count toward `total_members` within tiers). Tiers are sorted alphabetically by `tier_name`. Returns `404` if the post is not found or is the wrong CPT.

---

### `update_bundle_change_ownership( array $params ): \WP_REST_Response`

Changes the membership owner on a bundle post. Expects `params`:

| Key | Required |
|---|---|
| `bundle_post_id` | Yes |
| `new_owner_uuid` | Yes — MDP person UUID |

- Returns `204` if the new owner is the same as the current owner (no-op).
- Delegates ownership storage (including WP user resolution/creation) to `Membership_Bundle::set_owner()` — see `Membership_Bundle` docs for rationale.
- Reassigns the linked WC order and subscription customers (handled internally by `set_owner()`).

Returns `500` if the user cannot be resolved.

Ownership reassignment of linked WooCommerce order/subscription records is handled internally by `Membership_Bundle::set_owner()`.

---

### `get_eligible_tiers_for_bundle( int $bundle_post_id, string $person_uuid = '' ): array|\WP_REST_Response`

Returns the individual `Membership_Tier` posts eligible for a bundle's add-member flow, each with its resolvable WooCommerce product/variation options. Backs the member-scoped `GET /bundle/{bundle_post_id}/eligible_tiers/mine` route used by the account-center "Add Member" modal's results step.

Mirrors the tier + product loading logic of the legacy React `AddMemberToBundleModal` (`frontend/src/membership_bundles/components/AddMemberToBundleModal.js`), but as a member-scoped server-side endpoint: `wicket_mship_tier` is registered with `public => false`, so a logged-in member cannot read it via the native `/wp/v2/{slug}` REST route, and the staff-only `/membership_products` route can't be reused to resolve names/prices either.

Filtering:

- Queries `wicket_mship_tier` posts with `post_status = publish`, constrained to `Membership_Bundle_Config::get_eligible_tier_ids()` when the bundle's config restricts them.
- An empty `eligible_tier_ids` means all active individual tiers are eligible (the config field's own fallback rule) — the query is unconstrained in that case, not empty.
- Non-individual tiers (`Membership_Tier::is_individual_tier()` false) are skipped even if they somehow matched `post__in`.

For each surviving tier, `product_data` entries (`product_id`, `variation_id`) are collected across all tiers first, then resolved to WC names/prices in a single pass (`wc_get_product()` per unique ID, preferring `variation_id` over `product_id` when both are present — same lookup precedence the legacy React modal used).

Returns `404` (`\WP_REST_Response`) if `$bundle_post_id` does not resolve to a `Membership_Bundle`. Otherwise returns:

```php
[
  [
    'id'       => 88,
    'name'     => 'Gold',
    'products' => [
      [ 'product_id' => 803, 'variation_id' => 805, 'name' => 'Gold — Annual', 'price' => '150.00' ],
    ],
  ],
]
```

**Per-person eligibility annotation.** When `$person_uuid` is non-empty, each tier row is additionally merged with `eligibility_status`, `membership_status`, `membership_status_label`, `starts_at`, and `ends_at` — this is what drives the Eligible/In Bundle/Not Eligible badges, status badge, and date range in the add-member modal's results step. Skipped entirely (no extra keys) when `$person_uuid` is empty, e.g. the bundle-level tier listing before a member has been selected.

Resolution, per tier:

1. The local WP user is resolved once via `get_user_by( 'login', $person_uuid )` so it can be passed to the eligibility filter (`0` when no local user exists).
2. `find_active_bundled_membership_for_person_and_tier()` looks for a `wicket_membership` post matching `membership_user_uuid` (the MDP person UUID — **not** a `person_uuid` meta key, which is never actually persisted; it's only an in-flight array key used en route to the MDP API call elsewhere in this class) + `membership_tier_uuid`, with a non-empty `membership_bundle_id` and a `membership_status` that isn't `cancelled`/`expired` — **not scoped to the bundle being added to**. Each match's containing `Membership_Bundle` (resolved via `Membership_Controller::get_membership_bundle()`) is checked; the first one whose own `get_membership_status()` is `active` wins → `eligibility_status = 'in_bundle'`. This means a person already holding this tier's seat in a *different* active bundle is flagged `in_bundle` here too, not just re-adds to the same bundle.
3. Otherwise, `find_active_membership_for_person_and_tier()` looks for *any* `wicket_membership` post (bundle-linked or standalone) matching `membership_user_uuid` + `membership_tier_uuid` + `membership_status = Wicket_Memberships::STATUS_ACTIVE`. If found → `eligibility_status = 'eligible'`; otherwise → `'not_eligible'`.
4. `membership_status`/`membership_status_label`/`starts_at`/`ends_at` are read from whichever membership post backed the status above (the in-bundle post, or the active-elsewhere post) — all `null` when neither was found.
5. The status is passed through the `wicket_mship_bundle_tier_eligibility_status` filter (see below). A `not_eligible` row can therefore still carry a status/dates when a filter rejected a person who does hold an active membership for the tier.

**Filter: `wicket_mship_bundle_tier_eligibility_status`.** Site-specific eligibility rules (e.g. requiring an MDP person status of `good_standing`) are not built into the plugin — add them in the child theme or a site plugin.

```php
apply_filters(
  'wicket_mship_bundle_tier_eligibility_status',
  string   $eligibility_status, // 'eligible' | 'in_bundle' | 'not_eligible' (plugin default)
  array    $tier_row,           // fully built row incl. membership_status, starts_at, ends_at
  string   $person_uuid,        // MDP person UUID
  int      $user_id,            // local WP user ID, 0 if none
  int      $bundle_post_id,
  ?WP_Post $active_post         // the active membership backing 'eligible', or null
);
```

- Applied to every status, including `in_bundle`. Overriding `in_bundle` only changes what the modal shows — `add_member()` still rejects a duplicate seat with `already_in_bundle`.
- Return values other than `'eligible'`/`'in_bundle'`/`'not_eligible'` are ignored and the default is kept.
- Runs once per tier row, so callbacks making remote calls (e.g. `wicket_get_person_by_id()`) should cache per `$person_uuid`.

```php
[
  'id'                       => 88,
  'name'                     => 'Gold',
  'products'                 => [ /* ... */ ],
  'eligibility_status'       => 'eligible', // 'eligible' | 'in_bundle' | 'not_eligible'
  'membership_status'        => 'active',
  'membership_status_label'  => 'Active',
  'starts_at'                => '2026-07-06T00:00:00+00:00',
  'ends_at'                  => '2026-12-31T23:59:59+00:00',
]
```

---

### `add_member( array $params ): array`

Adds an individual membership to a bundle. Dispatches to `Membership_Bundle::add_member()` based on `mode`.

| Key | Required | Description |
|---|---|---|
| `bundle_post_id` | Yes | Post ID of the `Membership_Bundle` |
| `mode` | Yes | `"new"` or `"existing"` |
| `tier_post_id` | Yes | Post ID of the individual `Membership_Tier` CPT |
| `person_uuid` | Conditional | MDP person UUID — required when `mode = "new"` |
| `existing_membership_post_id` | Conditional | Existing membership post ID to cancel — required when `mode = "existing"` |
| `product_id` | No | WC product ID — auto-resolved from tier when omitted |

For `mode = "new"`: resolves a WP user from `person_uuid` before delegating to `Membership_Bundle::add_member()`. Resolution strategy depends on the `BYPASS_WICKET` flag (read from `$bundle->bypass_wicket`):

- **Normal mode:** calls `wicket_create_wp_user_if_not_exist()` — creates the WP user from MDP if not already present.
- **Bypass mode (`BYPASS_WICKET`):** calls `get_user_by( 'login', $person_uuid )` only — no MDP API call. Returns `user_resolve_failed` error if the user does not already exist locally.

**Duplicate-seat guard (both modes).** After the tier-eligibility gate and before any user creation, `find_active_bundled_membership_for_person_and_tier()` (the same check that produces the `in_bundle` badge) is run for the person + tier. If the person already holds this tier's seat in *any* active bundle, the request is rejected with code `already_in_bundle`. The person is taken from `person_uuid` in `new` mode, and from the existing membership's `membership_user_uuid` meta in `existing` mode (the request value is not trusted there). This is the server-side guarantee behind the `in_bundle` lock in the modal, which is otherwise UI-only and filterable.

Returns `['success' => '...', 'membership_post_id' => int]` on success or `['error' => '...', 'code' => '...']` on failure. All model `WP_Error` values are mapped to the error-array shape so callers never receive a `WP_Error` directly.

---

### `remove_member( array $params ): array`

Removes an individual membership from a bundle. Dispatches to `Membership_Bundle::remove_member()`.

| Key | Required | Description |
|---|---|---|
| `bundle_post_id` | Yes | Post ID of the `Membership_Bundle` |
| `membership_post_id` | Yes | Post ID of the individual membership to remove |
| `mode` | Yes | `"cancel"` or `"keep_as_individual"` |

Returns `['success' => '...', 'membership_post_id' => int]` on success or `['error' => '...', 'code' => '...']` on failure.

---

### `move_individual_membership( array $params ): array`

Moves an individual membership from one bundle to another. Dispatches to `Membership_Bundle::move_individual_membership()`.

| Key | Required | Description |
|---|---|---|
| `source_bundle_post_id` | Yes | Post ID of the source `Membership_Bundle` |
| `membership_post_id` | Yes | Post ID of the individual membership to move |
| `target_bundle_post_id` | Yes | Post ID of the target `Membership_Bundle` |

Returns `['success' => '...', 'membership_post_id' => int]` on success or `['error' => '...', 'code' => '...']` on failure. If the new membership cannot be created after the source is cancelled, returns an error with code from the underlying failure and a message noting the source was cancelled with no rollback.

---

### `cancel_bundle( int $bundle_post_id, string $member_handling, string $timing ): \WP_REST_Response`

Cancels a membership bundle with three configurable paths based on `$member_handling` and `$timing`.

**Path A — `cancel_all` + `immediately`:**
- Calls `Membership_Bundle::transition_to('cancelled')`, which collapses dates to now and handles the cascade via `plan_status_transition`.
- Calls `cancel_bundle_subscription()` to immediately hard-cancel the WC subscription.
- Calls `$bundle->transition_to(STATUS_CANCELLED)` which cascades cancellation to child memberships via `cascade_status_to_members()`. No replacement memberships are created.

**Path B — `cancel_all` + `at_end_date`:**
- Calls `Membership_Bundle::transition_to_cancelled_at_end_date()`, which sets bundle status to cancelled while preserving `ends_at`, collapses `expires_at` to `ends_at`, and updates individual membership `expires_at` without touching their active status.
- Individual memberships **keep their active status** — members retain access until the original bundle end date. `daily_membership_expiry_hook` expires them naturally on that date. No per-member scheduled job.
- Sets the bundle WC subscription to `pending-cancel`.
- Schedules one `as_schedule_single_action` at `ends_at` with hook `wicket_bundle_cancel_subscription` + arg `$bundle_post_id`. The handler in `wicket.php` calls `$subscription->update_status('cancelled')`.
- No replacement memberships created.

**Path C — `keep_as_individual`:**
- Delegates entirely to `Membership_Bundle::cancel_keep_as_individual()`. See that method's documentation for the full conversion loop detail.
- Returns `400` if the bundle transition fails, `200` (with optional `warnings` array) on success.

Returns `400` for invalid transitions or missing end date (path B), `404` if bundle not found, `200` on success.

---

### `create_bundle_renewal_order( array $params ): \WP_REST_Response`

Creates a WooCommerce renewal order for the bundle by calling `wcs_create_renewal_order()` on the linked subscription. Returns `200` with the order URL and ID on success. Expects `params`:

| Key | Required |
|---|---|
| `bundle_post_id` | Yes |
| `product_id` | Yes |
| `variation_id` | No — overrides `product_id` if provided |

---

## Private Helpers

### `build_membership_bundles_row( \WP_Post $post ): array`

Builds one list-table row per bundle post. Owner `name` and `email` are resolved in controller response-shaping code from the live WP user via the stored `user_id`.

### `cancel_bundle_subscription( int|false $subscription_id, array $meta_data ): void`

Cancels a WC subscription linked to a membership bundle. Sets the subscription end date from `$meta_data['ends_at']`, writes a completion note to the subscription, then calls `update_status('cancelled')`. No-op if the subscription does not exist or WC Subscriptions is not active. Used by path A of `cancel_bundle()`.

---

## Dependencies

| Class / Function | Purpose |
|---|---|
| `Membership_Bundle` | Model — reads/writes bundle post meta; child date/status cascade is fully implemented |
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
