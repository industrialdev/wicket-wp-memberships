# Membership_Bundle

`Membership_Bundle` is the core model class for the `wicket_mship_bundle` custom post type. Every bundle-level operation — reading and writing post meta, managing member seats, executing lifecycle transitions, and coordinating WooCommerce subscriptions — goes through this class.

**File:** `includes/Membership_Bundle.php`  
**Namespace:** `Wicket_Memberships`  
**CPT slug:** `wicket_mship_bundle`

The class sits at the bottom of the call chain. REST controllers and admin controllers delegate to it; it does not call up the stack.

```
Membership_Bundle_WP_REST_Controller
    → Membership_Bundle_Admin_Controller
        → Membership_Bundle   ← you are here
```

## [Method summary](#method-summary)

- **[Creating a bundle](#creating-a-bundle)**
  - [`create()`](#create) — Create a new bundle post with all required meta, a WooCommerce subscription, and scheduled date jobs
  - [`renew_bundle()`](#renew_bundle) — Create a new bundle term for an existing renewal series, reusing the current subscription

- **[Member management](#member-management)**
  - [`add_member()`](#add_member) — Enrol a new person or convert an existing standalone membership into a bundle seat
  - [`remove_member()`](#remove_member) — Cancel a seat or release it as a standalone membership with the remaining term
  - [`move_individual_membership()`](#move_individual_membership) — Transfer a member from this bundle to another without losing their record
  - [`get_individual_memberships()`](#get_individual_memberships) — Retrieve active (or all) child membership posts belonging to this bundle

- **[Lifecycle transitions](#lifecycle-transitions)**
  - [`transition_to()`](#transition_to) — Execute a status change with full lifecycle side effects (date recalc, subscription, cascade)
  - [`get_allowed_status_transitions()`](#get_allowed_status_transitions) — Determine which transitions are available to offer an admin from the current status
  - [`get_membership_status()`](#get_membership_status) — Read the current status slug
  - [`set_membership_status()`](#set_membership_status) — Write status directly, bypassing lifecycle guards — use only when `transition_to()` is not appropriate
  - [`transition_to_cancelled_at_end_date()`](#transition_to_cancelled_at_end_date) — Cancel a bundle while preserving member access until the paid term ends

- **[Dates](#dates)**
  - [`get_dates()`](#get_dates) — Read all four lifecycle date fields (starts, ends, expires, early-renew)
  - [`set_dates()`](#set_dates) — Write lifecycle date fields

- **[Owner](#owner)**
  - [`set_owner()`](#set_owner) — Assign a new owner by MDP UUID, resolving or creating the WP user
  - [`get_owner()`](#get_owner) — Get the owner's user ID, UUID, name, and email in one call
  - [`get_owner_id()`](#get_owner_id) — Get the owner's WP user ID
  - [`get_owner_uuid()`](#get_owner_uuid) — Get the owner's MDP UUID
  - [`is_owner()`](#is_owner) — Check whether a given MDP UUID matches the current owner

- **[Organization](#organization)**
  - [`set_organization()`](#set_organization) — Associate an MDP org with this bundle
  - [`get_org_uuid()`](#get_org_uuid) — Read the stored org UUID
  - [`get_organization()`](#get_organization) — Fetch full org data from MDP for the stored UUID

- **[Config and subscription](#config-and-subscription)**
  - [`get_config()`](#get_config) — Get the linked `Membership_Bundle_Config` object
  - [`set_config()`](#set_config) — Link a different config record to this bundle
  - [`get_subscription_id()`](#get_subscription_id) — Get the linked WooCommerce subscription post ID
  - [`get_post_id()`](#get_post_id) — Get this bundle's WordPress post ID
  - [`get_name()`](#get_name) — Get the bundle's display name (post title)
  - [`get_bundle_group_uuid()`](#get_bundle_group_uuid) — Get the series UUID shared across all renewal-term posts

## [Basic usage](#basic-usage)

Instantiate the class with a post ID to load an existing bundle:

```php
use Wicket_Memberships\Membership_Bundle;

$bundle = new Membership_Bundle( $bundle_post_id );

if ( $bundle->get_post_id() === 0 ) {
    // Post does not exist or is not a wicket_mship_bundle post
}
```

## [Creating a bundle](#creating-a-bundle)

### [`create()`](#create)

```php
public static function create(
    string $name,
    int    $membership_bundle_config_id,
    string $org_uuid,
    string $owner_uuid,
    string $start_date
): static|null
```

Creates a new bundle post, populates all required meta, creates a linked WooCommerce subscription, and schedules Action Scheduler date-trigger jobs. All five parameters are required.

**Parameters**

| Name | Type | Description |
|---|---|---|
| `$name` | `string` | Post title for the bundle. Must be non-empty. |
| `$membership_bundle_config_id` | `int` | Post ID of a `wicket_mship_bcfg` record that defines cycle, dates, and renewal settings. |
| `$org_uuid` | `string` | MDP organisation UUID to associate with this bundle. |
| `$owner_uuid` | `string` | MDP person UUID of the bundle owner. The corresponding WP user is resolved or created. |
| `$start_date` | `string` | ISO 8601 start date (e.g. `2025-01-01`). Dates are derived from the config anchored to this value. |

**Returns:** `static` on success, `null` if a post-creation meta write fails (the partial post is rolled back). Throws `\RuntimeException` if parameter validation fails before any database writes.

**Initial status:** `delayed` when `$start_date` is in the future; `pending` otherwise. A bundle always requires explicit admin activation before becoming `active`.

```php
$bundle = Membership_Bundle::create(
    name:                         'Acme Corp 2025',
    membership_bundle_config_id:  42,
    org_uuid:                     'org-uuid',
    owner_uuid:                   'person-uuid',
    start_date:                   '2025-01-01',
);
```

### [`renew_bundle()`](#renew_bundle)

```php
public function renew_bundle(
    \WC_Subscription $subscription,
    array            $new_dates
): static|null
```

Creates a new bundle post for a renewal term. Use this instead of `create()` for all renewal flows. The new post carries the same `membership_bundle_group_uuid` as the current bundle, linking both posts into the same renewal series. The existing WooCommerce subscription is reused (dates are updated on it rather than creating a new subscription).

**Parameters**

| Name | Type | Description |
|---|---|---|
| `$subscription` | `\WC_Subscription` | The existing bundle subscription to reuse. |
| `$new_dates` | `array` | Output of `Membership_Bundle_Config::get_membership_dates()` for the new term. |

**Returns:** `static` on success, `null` on failure. The new bundle always starts in `delayed` status, regardless of the start date.

```php
$config    = $bundle->get_config();
$new_dates = $config->get_membership_dates([
    'membership_ends_at' => $bundle->get_dates()['ends_at'],
]);

$subscription  = wcs_get_subscription( $bundle->get_subscription_id() );
$renewed_bundle = $bundle->renew_bundle( $subscription, $new_dates );
```

## [Member management](#member-management)

### [`add_member()`](#add_member)

```php
public function add_member(
    ?int    $user_id,
    int     $tier_post_id,
    ?int    $product_id             = null,
    ?int    $variation_id           = null,
    ?int    $existing_membership_post_id = null,
    bool    $is_renewal             = false,
    ?string $start_date_override    = null
): int|\WP_Error
```

Adds an individual membership seat to this bundle. The `$existing_membership_post_id` parameter switches between two flows:

- **New member** (`$existing_membership_post_id = null`): `$user_id` is required. A new `wicket_membership` post is created and linked to the bundle.
- **Existing member** (`$existing_membership_post_id` provided): the existing membership is cancelled and replaced with a new one using the bundle's dates. `$user_id` is ignored — it is read from the existing membership.

**Parameters**

| Name | Type | Required | Description |
|---|---|---|---|
| `$user_id` | `int\|null` | Conditional | WP user ID of the member. Required for new-member path; ignored for existing-member path. |
| `$tier_post_id` | `int` | Yes | Post ID of the `Membership_Tier` CPT defining the seat type. |
| `$product_id` | `int\|null` | No | WC product ID. Auto-resolved from the tier when omitted. Required when the tier has more than one product. |
| `$variation_id` | `int\|null` | No | WC product variation ID. Takes precedence over `$product_id` when supplied. |
| `$existing_membership_post_id` | `int\|null` | No | Post ID of an existing `wicket_membership` to cancel and replace. |
| `$is_renewal` | `bool` | No | Pass `true` when called by the renewal batch processor. Skips the subscription line item add. |
| `$start_date_override` | `string\|null` | No | ISO 8601 date. Bypasses normal start-date resolution. |

**Returns:** new membership post ID on success, `\WP_Error` on failure.

| Error code | Cause |
|---|---|
| `invalid_bundle_status` | Bundle is not in `pending`, `active`, or `delayed` status |
| `missing_user_id` | New-member path called without a user ID |
| `bundle_ended` | Today is past the bundle's end date |
| `bundle_no_dates` | Bundle has no date meta |
| `invalid_user` | WP user cannot be resolved |
| `invalid_tier` | Tier post not found or wrong CPT |
| `ambiguous_product` | Tier has more than one product and `$product_id` was not supplied |
| `no_product` | No product found for tier |
| `product_tier_mismatch` | Supplied product does not belong to the tier |
| `invalid_membership` | Existing membership post not found or wrong CPT |
| `create_failed` | Downstream membership creation failed |

```php
$membership_post_id = $bundle->add_member(
    user_id:      get_current_user_id(),
    tier_post_id: 88,
);
```

See [Member Handling](../concepts/member-handling.md) for an explanation of new vs. existing member modes, start-date resolution, and what gets created.

### [`remove_member()`](#remove_member)

```php
public function remove_member(
    int    $membership_post_id,
    string $mode
): int|\WP_Error
```

Removes an individual membership from this bundle.

**Parameters**

| Name | Type | Description |
|---|---|---|
| `$membership_post_id` | `int` | Post ID of the `wicket_membership` to remove. |
| `$mode` | `string` | `"cancel"` — cancels the membership immediately. `"keep_as_individual"` — converts it to a standalone membership with its own order and subscription. |

**Returns:** post ID of the affected membership. For `cancel` mode, this is the cancelled post. For `keep_as_individual`, this is the newly created standalone post.

```php
// Cancel the seat
$result = $bundle->remove_member( $membership_post_id, 'cancel' );

// Convert to standalone
$new_membership_id = $bundle->remove_member( $membership_post_id, 'keep_as_individual' );
```

See [Member Handling](../concepts/member-handling.md) for a detailed explanation of the `keep_as_individual` flow.

### [`move_individual_membership()`](#move_individual_membership)

```php
public function move_individual_membership(
    int               $membership_post_id,
    Membership_Bundle $target_bundle
): int|\WP_Error
```

Moves a member from this bundle to another. Cancels the seat in the source bundle and creates a new seat in the target bundle with the same tier and product.

**Parameters**

| Name | Type | Description |
|---|---|---|
| `$membership_post_id` | `int` | Post ID of the membership to move. Must belong to this bundle. |
| `$target_bundle` | `Membership_Bundle` | Destination bundle. Must be in `pending`, `active`, or `delayed` status. |

**Returns:** post ID of the new membership in the target bundle, or `\WP_Error` on failure.

> There is no rollback if the source is cancelled but the target creation fails. The error message will note this explicitly.

### [`get_individual_memberships()`](#get_individual_memberships)

```php
public function get_individual_memberships(
    bool $active_only = true
): array
```

Returns the child `wicket_membership` WP_Post objects for this bundle.

**Parameters**

| Name | Type | Description |
|---|---|---|
| `$active_only` | `bool` | When `true` (default), excludes `cancelled` and `expired` memberships. Pass `false` to retrieve all. |

**Returns:** array of `\WP_Post` objects.

```php
// Count active seats
$active_count = count( $bundle->get_individual_memberships() );

// Audit all seats including past members
$all_seats = $bundle->get_individual_memberships( active_only: false );
```

## [Lifecycle transitions](#lifecycle-transitions)

### [`transition_to()`](#transition_to)

```php
public function transition_to( string $new_status ): array|false
```

The main entry point for all status changes. Applies lifecycle guards, recalculates dates where applicable, activates the WooCommerce subscription on `pending → active`, and cascades the new status to all child memberships.

**Parameters**

| Name | Type | Description |
|---|---|---|
| `$new_status` | `string` | Target status slug (e.g. `'active'`, `'cancelled'`). |

**Returns:** `['success_message' => string, 'bypassed' => bool]` on success, `false` when the transition is not allowed from the current status.

```php
$result = $bundle->transition_to( 'active' );

if ( $result === false ) {
    // Not a valid transition from current status
}
```

### [`get_allowed_status_transitions()`](#get_allowed_status_transitions)

```php
public function get_allowed_status_transitions(): array
```

Returns the admin-selectable transitions from the current status. Does not include cron-only transitions such as `active → grace-period`.

**Returns:** associative array keyed by status slug, e.g. `['active' => ['name' => 'Active', 'slug' => 'active']]`. Empty array when the bundle is in a terminal status.

```php
$transitions = $bundle->get_allowed_status_transitions();

foreach ( $transitions as $slug => $label ) {
    echo $label['name']; // 'Active', 'Cancelled', etc.
}
```

### [`get_membership_status()`](#get_membership_status)

```php
public function get_membership_status(): string|false
```

Returns the current `membership_status` meta value, or `false` if not set.

### [`set_membership_status()`](#set_membership_status)

```php
public function set_membership_status( string $status ): bool
```

Writes `membership_status` directly. Use `transition_to()` in normal flows — this method bypasses lifecycle guards and side effects. It exists as a low-level escape hatch.

### [`transition_to_cancelled_at_end_date()`](#transition_to_cancelled_at_end_date)

```php
public function transition_to_cancelled_at_end_date(): array|false
```

Cancels the bundle while preserving `ends_at`, so members retain access until the paid period runs out. Does not cancel child memberships — they keep their current status until the daily expiry cron expires them naturally on `ends_at`.

Use this when an admin cancels a bundle but wants existing members to finish out their term.

**Returns:** `['success_message' => string]` on success, `false` if the bundle has no end date or is already in a terminal status.

## [Dates](#dates)

### [`get_dates()`](#get_dates)

```php
public function get_dates(): array
```

Returns the bundle's stored date fields.

**Returns:**

```php
[
    'starts_at'      => string,  // ISO 8601 UTC
    'ends_at'        => string,  // ISO 8601 UTC
    'expires_at'     => string,  // ISO 8601 UTC (empty if no grace period)
    'early_renew_at' => string,  // ISO 8601 UTC (empty if no renewal window)
]
```

All boundaries are stored in UTC, snapped to the MDP timezone day start/end. Do not compare dates to UTC midnight directly.

### [`set_dates()`](#set_dates)

```php
public function set_dates( array $dates ): bool
```

Writes date meta. Optional keys are skipped when `null`.

**Parameters**

| Key | Type | Required |
|---|---|---|
| `starts_at` | `string` | Yes |
| `ends_at` | `string` | Yes |
| `expires_at` | `string\|null` | No |
| `early_renew_at` | `string\|null` | No |

**Returns:** `true` on success, `false` on failure.

## [Owner](#owner)

### [`set_owner()`](#set_owner)

```php
public function set_owner( string $uuid ): int|false
```

Sets the bundle owner by MDP person UUID. Resolves or creates the corresponding WP user, writes the WP user ID to `user_id` post meta, and updates the linked WooCommerce order and subscription customer.

**Parameters**

| Name | Type | Description |
|---|---|---|
| `$uuid` | `string` | MDP person UUID. |

**Returns:** saved WP user ID on success, `false` on failure.

### [`get_owner()`](#get_owner)

```php
public function get_owner(): array|false
```

Returns a snapshot of the bundle owner. Prefer this over calling `get_owner_id()` + `get_user_by()` separately.

**Returns:**

```php
[
    'user_id' => int,
    'uuid'    => string,  // MDP UUID derived from user_login
    'name'    => string,
    'email'   => string,
]
```

Returns `false` if no owner is set.

### [`get_owner_id()`](#get_owner_id)

```php
public function get_owner_id(): int|false
```

Returns the WP user ID of the owner from post meta, or `false` if not set.

### [`get_owner_uuid()`](#get_owner_uuid)

```php
public function get_owner_uuid(): string|false
```

Returns the MDP UUID for the owner (derived from `user_login`), or `false` if unresolvable.

### [`is_owner()`](#is_owner)

```php
public function is_owner( string $uuid ): bool
```

Returns `true` if the given MDP person UUID matches the bundle owner.

## [Organization](#organization)

### [`set_organization()`](#set_organization)

```php
public function set_organization( string $org_uuid ): array|false
```

Associates an MDP organization with this bundle. Fetches and caches the org name.

**Returns:** org data array on success, `false` on failure.

### [`get_org_uuid()`](#get_org_uuid)

```php
public function get_org_uuid(): string|false
```

Returns the stored `org_uuid` meta value, or `false` if not set.

### [`get_organization()`](#get_organization)

```php
public function get_organization(): array|false
```

Returns the full organization data array from MDP for the stored UUID, or `false`.

## [Config and subscription](#config-and-subscription)

### [`get_config()`](#get_config)

```php
public function get_config(): Membership_Bundle_Config|false
```

Returns the linked `Membership_Bundle_Config` object, or `false` if not set.

```php
$config = $bundle->get_config();

if ( $config ) {
    $renewal_type = $config->get_renewal_type(); // 'subscription' or 'form_page'
}
```

### [`set_config()`](#set_config)

```php
public function set_config( int $config_post_id ): bool
```

Validates and writes `membership_bundle_config_id` post meta. Returns `true` on success.

### [`get_subscription_id()`](#get_subscription_id)

```php
public function get_subscription_id(): int|false
```

Returns the linked WooCommerce subscription post ID from post meta, or `false` if not set.

### [`get_post_id()`](#get_post_id)

```php
public function get_post_id(): int
```

Returns the WordPress post ID for this bundle. Returns `0` if the bundle failed to load.

### [`get_name()`](#get_name)

```php
public function get_name(): string
```

Returns the post title. Returns an empty string if the bundle post is not loaded.

### [`get_bundle_group_uuid()`](#get_bundle_group_uuid)

```php
public function get_bundle_group_uuid(): string|false
```

Returns the `membership_bundle_group_uuid` meta value. This UUID is shared across all renewal-term posts in the same series. Use it to retrieve the full renewal history of a bundle.

## [Post meta reference](#post-meta-reference)

| Key | Type | Description |
|---|---|---|
| `user_id` | `int` | WP user ID of the bundle owner |
| `org_uuid` | `string` | MDP organisation UUID |
| `org_name` | `string` | Cached MDP organisation legal name |
| `membership_status` | `string` | Current bundle status |
| `membership_bundle_config_id` | `int` | Post ID of the linked `Membership_Bundle_Config` |
| `membership_parent_order_id` | `int` | Linked WooCommerce order ID |
| `membership_subscription_id` | `int` | Linked WooCommerce subscription ID |
| `membership_starts_at` | `string` | ISO 8601 UTC start date |
| `membership_ends_at` | `string` | ISO 8601 UTC end date |
| `membership_expires_at` | `string` | ISO 8601 UTC grace period end date |
| `membership_early_renew_at` | `string` | ISO 8601 UTC early renewal open date |
| `membership_bundle_group_uuid` | `string` | Shared UUID across all renewal-term posts for this bundle |

## [Hooks](#hooks)

| Hook | Type | When fired | Args |
|---|---|---|---|
| `wicket_memberships_individual_membership_created_for_bundle` | filter | After a new member seat is created by `add_member()` | `array $result` |
| `wicket_memberships_bundle_renewal_period_open` | action | `early_renew_at` date reached | `int $bundle_post_id` |
| `wicket_memberships_bundle_end_date_reached` | action | `ends_at` date reached | `int $bundle_post_id` |
| `wicket_memberships_bundle_grace_period_expired` | action | `expires_at` date reached | `int $bundle_post_id` |

The `wicket_memberships_individual_membership_created_for_bundle` filter receives the result array from `add_member()`. It must return an array containing `membership_post_id` — returning an array without that key will cause a `create_failed` error.

```php
add_filter( 'wicket_memberships_individual_membership_created_for_bundle', function( array $result ): array {
    // Inspect or augment the result
    return $result;
} );
```
