---
title: Membership_Bundle_Admin_Controller
---

# Membership_Bundle_Admin_Controller

`Membership_Bundle_Admin_Controller` is the orchestration layer between the REST API and the `Membership_Bundle` model. It validates inputs, normalizes payloads, and delegates all mutations to the model. If you are building a custom integration that needs to operate on bundles outside the REST layer, this is the right class to call.

**File:** `includes/Membership_Bundle_Admin_Controller.php`  
**Namespace:** `Wicket_Memberships`

All public methods are `static`. They return either a plain `array` (for data-retrieval methods) or a `\WP_REST_Response` (for mutation methods).

```
Membership_Bundle_WP_REST_Controller
    → Membership_Bundle_Admin_Controller   ← you are here
        → Membership_Bundle
```

## Method summary

- **[Retrieving bundles](#retrieving-bundles)**
  - [`get_membership_bundles_list()`](#get_membership_bundles_list) — Search or browse bundles by status, org, or name — use this to power a list view or lookup UI
  - [`get_bundle_entity_records()`](#get_bundle_entity_records) — Load a single bundle's data for display — includes formatted dates and child membership IDs
  - [`get_bundle_edit_page_info()`](#get_bundle_edit_page_info) — Populate an edit form — returns org, owner, config, subscription, orders, and the full renewal series in one call
  - [`get_membership_bundle_filters()`](#get_membership_bundle_filters) — Populate status filter dropdowns before rendering the bundle list
  - [`get_bundle_members_by_tier()`](#get_bundle_members_by_tier) — Show a seat utilization breakdown — how many active members per tier

- **[Status management](#status-management)**
  - [`get_admin_status_options()`](#get_admin_status_options) — Determine which status transitions to offer an admin for a given bundle
  - [`bundle_admin_manage_status()`](#bundle_admin_manage_status) — Activate, cancel, or otherwise transition a bundle when an admin takes action
  - [`cancel_bundle()`](#cancel_bundle) — Cancel a bundle and choose what happens to its members — immediately, at end date, or release as standalone memberships

- **[Updating a bundle](#updating-a-bundle)**
  - [`update_bundle_entity_record()`](#update_bundle_entity_record) — Correct or extend a bundle's dates, or switch its renewal type

- **[Ownership](#ownership)**
  - [`update_bundle_change_ownership()`](#update_bundle_change_ownership) — Reassign a bundle to a different org contact

- **[Member operations](#member-operations)**
  - [`add_member()`](#add_member) — Enrol a person in a bundle, or pull an existing standalone membership into it
  - [`remove_member()`](#remove_member) — Remove a person from a bundle — either cancelling their membership or preserving it as a standalone
  - [`move_individual_membership()`](#move_individual_membership) — Transfer a member from one bundle to another without losing their membership record

- **[Renewal](#renewal)**
  - [`create_bundle_renewal_order()`](#create_bundle_renewal_order) — Manually trigger a renewal payment when automatic subscription renewal is not handling it

## Basic usage

All methods are static — no instantiation needed:

```php
use Wicket_Memberships\Membership_Bundle_Admin_Controller;

$result = Membership_Bundle_Admin_Controller::get_bundle_members_by_tier( $bundle_post_id );
```

## Retrieving bundles

### `get_membership_bundles_list()`

```php
public static function get_membership_bundles_list(
    int     $page            = 1,
    int     $posts_per_page  = 25,
    string  $status          = 'all',
    string  $search          = '',
    array   $filter          = [],
    ?string $order_col       = null,
    ?string $order_dir       = null
): array
```

Returns a paginated list of membership bundles with their post meta formatted for display.

**Parameters**

| Name | Type | Required | Description |
|---|---|---|---|
| `$page` | `int` | No | Page number. Default `1`. |
| `$posts_per_page` | `int` | No | Results per page. Default `25`. |
| `$status` | `string` | No | Filter by status slug, or `'all'` for all statuses. |
| `$search` | `string` | No | Search term matched against post title. |
| `$filter` | `array` | No | Additional filter key-value pairs. |
| `$order_col` | `string\|null` | No | Column to sort by. |
| `$order_dir` | `string\|null` | No | Sort direction: `'ASC'` or `'DESC'`. |

:::details Returns
```php
[
    'results'        => [
        [
            'id'          => string,   // bundle_group_uuid
            'bundle_name' => string,
            'org_name'    => string,
            'org_uuid'    => string,
            'owner'       => [ 'name' => string, 'email' => string ],
            'status'      => [ 'slug' => string, 'label' => string ],
            'last_updated'=> string,   // Y-m-d H:i:s
            'mdp_link'    => string,   // URL to org in MDP admin (empty if unavailable)
            'bundle_mdp_link' => string, // URL to bundle record in MDP admin
        ],
        // ...one entry per bundle in the current page
    ],
    'page'           => int,
    'posts_per_page' => int,
    'count'          => int,   // total matching bundles across all pages
]
```
:::

:::details Example
```php
$list = Membership_Bundle_Admin_Controller::get_membership_bundles_list(
    page:           1,
    posts_per_page: 10,
    status:         'active',
    search:         'Acme',
);

foreach ( $list['results'] as $row ) {
    echo $row['bundle_name'];
}
```
:::

### `get_bundle_entity_records()`

```php
public static function get_bundle_entity_records( int $bundle_post_id ): array|\WP_REST_Response
```

Returns the display data for a single bundle entity view: post meta, formatted dates, owner details, and child membership post IDs.

**Parameters**

| Name | Type | Required | Description |
|---|---|---|---|
| `$bundle_post_id` | `int` | Yes | Post ID of the `wicket_mship_bundle`. |

**Returns:** data array on success, `WP_REST_Response` 404 if the post is not found or wrong CPT.

```php
[
    'ID'                => int,
    'bundle_group_uuid' => string,
    'title'             => string,
    'data'              => [
        // All post meta, plus the following formatted/overridden fields:
        'membership_status'         => string,  // human-readable label e.g. "Active"
        'membership_status_slug'    => string,  // raw slug e.g. "active"
        'membership_starts_at'      => string,  // ISO 8601 UTC
        'membership_ends_at'        => string,  // ISO 8601 UTC
        'membership_expires_at'     => string,  // ISO 8601 UTC
        'membership_early_renew_at' => string,  // ISO 8601 UTC
        // ...remaining post meta keys
    ],
    'individual_members' => int[],  // post IDs of active child wicket_membership posts
]
```

### `get_bundle_edit_page_info()`

```php
public static function get_bundle_edit_page_info( string $bundle_group_uuid ): array|\WP_REST_Response
```

Returns all data needed to populate the bundle edit form. Accepts the bundle's series UUID (not a post ID) and fetches all bundle posts in that series, sorted newest first. Returns a rich data object with org, owner, config, subscription, and renewal history.

**Parameters**

| Name | Type | Required | Description |
|---|---|---|---|
| `$bundle_group_uuid` | `string` | Yes | The `membership_bundle_group_uuid` shared by all renewal-term posts in a series. |

**Returns:** data array on success (see structure below), `WP_REST_Response` 404 if no posts found.

:::details Returns
```php
[
    'ID'                  => int,
    'title'               => string,
    'meta'                => array,    // raw post meta array
    'bundle_group_uuid'   => string,
    'org'                 => [
        'uuid'     => string,
        'name'     => string,
        'location' => string,
        'mdp_link' => string,          // URL to org in MDP admin (empty if unavailable)
    ],
    'owner'               => [         // null if no owner set
        'user_id'             => int,
        'uuid'                => string,
        'name'                => string,
        'email'               => string,
        'mdp_link'            => string,
        'switch_to_url'       => string, // empty when User Switching plugin is inactive
    ] | null,
    'config'              => array,    // raw post meta from the linked Membership_Bundle_Config
                                       // see Membership_Bundle_Config for field reference
    'subscription_id'     => int|false,
    'subscription'        => [         // null if no subscription linked
        'id'                => int,
        'link'              => string,  // WP admin URL
        'status'            => string,  // WC subscription status e.g. 'active'
        'next_payment_date' => string,  // ISO 8601 or empty string
    ] | null,
    'orders'              => [
        [
            'id'           => int,
            'link'         => string,   // WP admin URL
            'total'        => string,   // formatted amount e.g. "120.00"
            'status'       => string,
            'date_created' => string,   // ISO 8601
            'type'         => string,   // 'parent' | 'renewal' | 'other'
        ],
        // ...
    ],
    'dates'               => [
        'starts_at'      => string,     // ISO 8601 UTC
        'ends_at'        => string,     // ISO 8601 UTC
        'expires_at'     => string,     // ISO 8601 UTC
        'early_renew_at' => string,     // ISO 8601 UTC
    ],
    'statuses'            => [
        'slug' => [ 'name' => string, 'slug' => string ],
        // ...one entry per status (all statuses, not filtered)
    ],
    'allowed_transitions' => [
        'slug' => [ 'name' => string, 'slug' => string ],
        // ...only transitions valid from the current bundle status
        // empty array when bundle is in a terminal status
    ],
    'membership_records'  => [
        [
            'ID'                     => int,    // bundle post ID for this term
            'name'                   => string,
            'status'                 => string, // human-readable label
            'starts_at'              => string, // ISO 8601 UTC
            'ends_at'                => string, // ISO 8601 UTC
            'expires_at'             => string, // ISO 8601 UTC
            'renewal_type'           => string, // 'subscription' | 'form_page'
            'next_tier_form_page_id' => int|null,
        ],
        // ...one entry per bundle post in the renewal series, newest first
    ],
]
```
:::

`membership_records` lists one entry per bundle post in the series — not per individual member seat. `owner.switch_to_url` is empty when the User Switching plugin is inactive.

### `get_membership_bundle_filters()`

```php
public static function get_membership_bundle_filters(): array
```

Returns available filter options for the bundle list. Used to populate filter dropdowns in the admin UI.

:::details Returns
```php
[
    'membership_status' => [
        [ 'name' => string, 'value' => string ],
        // ...one entry per status slug
        // 'name'  — the status slug e.g. "active"
        // 'value' — the human-readable label e.g. "Active"
    ],
]
```
:::

### `get_bundle_members_by_tier()`

```php
public static function get_bundle_members_by_tier( int $bundle_post_id ): array|\WP_REST_Response
```

Returns the total active member count and a per-tier breakdown for a bundle.

**Parameters**

| Name | Type | Required | Description |
|---|---|---|---|
| `$bundle_post_id` | `int` | Yes | Post ID of the `wicket_mship_bundle`. |

:::details Returns
```php
[
    'total_members' => int,
    'tiers'         => [
        [
            'tier_uuid'    => string,
            'tier_name'    => string,
            'member_count' => int,
        ],
        // ...sorted alphabetically by tier_name
    ],
]
```
:::

## Status management

### `get_admin_status_options()`

```php
public static function get_admin_status_options( ?int $bundle_post_id = null ): array
```

Returns available status options.

**Parameters**

| Name | Type | Required | Description |
|---|---|---|---|
| `$bundle_post_id` | `int\|null` | No | When provided, returns only valid transitions from the bundle's current status. When omitted, returns all status names. |

**Returns:** when `$bundle_post_id` is omitted — `[ 'slug' => [ 'name' => string, 'slug' => string ], ... ]` for all statuses. When provided — same shape but filtered to valid transitions from the current status; empty array when the bundle is in a terminal status.

:::details Example
```php
// All statuses
$all = Membership_Bundle_Admin_Controller::get_admin_status_options();

// Only valid next statuses for a specific bundle
$transitions = Membership_Bundle_Admin_Controller::get_admin_status_options( $bundle_post_id );
```
:::

### `bundle_admin_manage_status()`

```php
public static function bundle_admin_manage_status(
    int    $bundle_post_id,
    string $new_status
): \WP_REST_Response
```

Transitions a bundle to a new status. Delegates lifecycle logic — date recalculation, subscription activation, child cascade — to `Membership_Bundle::transition_to()`.

**Parameters**

| Name | Type | Required | Description |
|---|---|---|---|
| `$bundle_post_id` | `int` | Yes | Post ID of the bundle to transition. |
| `$new_status` | `string` | Yes | Target status slug. |

**Returns:** `WP_REST_Response` 200 on success, 400 for invalid transitions, 404 if the post is not found.

:::details Example
```php
$response = Membership_Bundle_Admin_Controller::bundle_admin_manage_status(
    bundle_post_id: $bundle_post_id,
    new_status:     'active',
);
```
:::

### `cancel_bundle()`

```php
public static function cancel_bundle(
    int    $bundle_post_id,
    string $member_handling,
    string $timing = ''
): \WP_REST_Response
```

Cancels a bundle with three configurable behaviours.

**Parameters**

| Name | Type | Required | Description |
|---|---|---|---|
| `$bundle_post_id` | `int` | Yes | Post ID of the bundle to cancel. |
| `$member_handling` | `string` | Yes | `"cancel_all"` — cancels all members. `"keep_as_individual"` — converts all members to standalone memberships. |
| `$timing` | `string` | Conditional | Required when `$member_handling` is `"cancel_all"`. `"immediately"` — hard cancel now. `"at_end_date"` — preserve access until `ends_at`. |

**Returns:** `WP_REST_Response` 200 on success. Body is `{ success: string }` for paths A and B; `{ success: string, warnings?: string[] }` for path C where per-member conversion errors are non-fatal. Returns 400 for invalid parameters or transition failures, 404 if the bundle is not found.

:::details Example
```php
// Cancel immediately
Membership_Bundle_Admin_Controller::cancel_bundle( $id, 'cancel_all', 'immediately' );

// Cancel at end of term, members keep access
Membership_Bundle_Admin_Controller::cancel_bundle( $id, 'cancel_all', 'at_end_date' );

// Convert all members to standalone and cancel bundle
Membership_Bundle_Admin_Controller::cancel_bundle( $id, 'keep_as_individual' );
```
:::

See [Bundle Lifecycle — Cancellation paths](../concepts/bundle-lifecycle.md#cancellation-paths) for full detail on each path.

## Updating a bundle

### `update_bundle_entity_record()`

```php
public static function update_bundle_entity_record( array $data ): \WP_REST_Response
```

Updates editable fields on a bundle post. Validates date ordering before writing.

**Parameters (as array keys)**

| Key | Required | Description |
|---|---|---|
| `bundle_post_id` | Yes | Post ID of the bundle to update. |
| `membership_starts_at` | No | ISO 8601. Must be before `membership_ends_at`. |
| `membership_ends_at` | No | ISO 8601. Must not be after `membership_expires_at`. |
| `membership_expires_at` | No | ISO 8601. |
| `membership_renewal_type` | No | `'subscription'` or `'form_page'`. Changing this updates the WC subscription's `next_payment` date accordingly. |

:::details Example
```php
$response = Membership_Bundle_Admin_Controller::update_bundle_entity_record([
    'bundle_post_id'        => 123,
    'membership_ends_at'    => '2026-12-31',
    'membership_expires_at' => '2027-01-30',
]);
```
:::

After a successful date edit, Action Scheduler date-trigger jobs are rescheduled automatically.

## Ownership

### `update_bundle_change_ownership()`

```php
public static function update_bundle_change_ownership( array $params ): \WP_REST_Response
```

Changes the owner of a bundle.

**Parameters (as array keys)**

| Key | Required | Description |
|---|---|---|
| `bundle_post_id` | Yes | Post ID of the bundle. |
| `new_owner_uuid` | Yes | MDP person UUID of the new owner. |

**Returns:** 204 if the new owner is the same as the current owner (no-op), 200 on success, 500 if the user cannot be resolved.

:::details Example
```php
$response = Membership_Bundle_Admin_Controller::update_bundle_change_ownership([
    'bundle_post_id' => 123,
    'new_owner_uuid' => 'new-person-uuid',
]);
```
:::

## Member operations

### `add_member()`

```php
public static function add_member( array $params ): array
```

Adds a member to a bundle. Resolves the WP user from the MDP person UUID, then delegates to `Membership_Bundle::add_member()`.

**Parameters (as array keys)**

| Key | Required | Description |
|---|---|---|
| `bundle_post_id` | Yes | Post ID of the bundle. |
| `mode` | Yes | `"new"` or `"existing"`. |
| `tier_post_id` | Yes | Post ID of the `Membership_Tier`. |
| `person_uuid` | Conditional | MDP person UUID. Required when `mode` is `"new"`. |
| `existing_membership_post_id` | Conditional | Post ID of existing membership to cancel and replace. Required when `mode` is `"existing"`. |
| `product_id` | No | WC product ID. Auto-resolved from tier when omitted. |

**Returns:** `['success' => string, 'membership_post_id' => int]` on success; `['error' => string, 'code' => string]` on failure.

:::details Example
```php
$result = Membership_Bundle_Admin_Controller::add_member([
    'bundle_post_id' => 123,
    'mode'           => 'new',
    'tier_post_id'   => 88,
    'person_uuid'    => 'person-uuid',
]);

$membership_post_id = $result['membership_post_id'];
```
:::

### `remove_member()`

```php
public static function remove_member( array $params ): array
```

Removes a member from a bundle.

**Parameters (as array keys)**

| Key | Required | Description |
|---|---|---|
| `bundle_post_id` | Yes | Post ID of the bundle. |
| `membership_post_id` | Yes | Post ID of the individual membership to remove. |
| `mode` | Yes | `"cancel"` or `"keep_as_individual"`. |

**Returns:** `['success' => string, 'membership_post_id' => int]` on success; `['error' => string, 'code' => string]` on failure.

### `move_individual_membership()`

```php
public static function move_individual_membership( array $params ): array
```

Moves a member from one bundle to another.

**Parameters (as array keys)**

| Key | Required | Description |
|---|---|---|
| `source_bundle_post_id` | Yes | Post ID of the source bundle. |
| `membership_post_id` | Yes | Post ID of the membership to move. |
| `target_bundle_post_id` | Yes | Post ID of the target bundle. |

**Returns:** `['success' => string, 'membership_post_id' => int]` (new membership ID) on success; `['error' => string, 'code' => string]` on failure.

::: danger No rollback on partial failure
If the source membership is cancelled but the target creation fails, the error message will explicitly note that the source was cancelled with no rollback. Re-add the member manually using `add_member` in `"new"` mode.
:::

## Renewal

### `create_bundle_renewal_order()`

```php
public static function create_bundle_renewal_order( array $params ): \WP_REST_Response
```

Creates a WooCommerce renewal order for the bundle by calling `wcs_create_renewal_order()` on the linked subscription.

**Parameters (as array keys)**

| Key | Required | Description |
|---|---|---|
| `bundle_post_id` | Yes | Post ID of the bundle. |
| `product_id` | Yes | WC product ID to include in the renewal order. |
| `variation_id` | No | WC variation ID. Overrides `product_id` when provided. |

**Returns:** `WP_REST_Response` 200 with `{ order_url, order_id }` on success.
