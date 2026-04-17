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

### `create( array $args = [] ): static|false`

Creates a new membership group post via `wp_insert_post`. Accepts `title` and `status` keys in `$args`. Returns a new `Membership_Group` instance on success, `false` on failure.

> **TODO:** Implementation should be reviewed before production use. See Asana task linked in source.

---

## Instance Methods

### `add_individual_membership( int $membership_post_id ): void`

Sets `membership_group_id` meta on an individual membership post to link it to this group.

> **TODO:** Pending review. See Asana task linked in source.

---

### `set_owner( int $user_id ): int|false`

Stores only the WP user ID (`user_id`) and updates `post_author`. Derived fields — display name, email, and MDP UUID — are intentionally not stored to avoid persisting values that can change independently of the membership record. Group ownership cannot be cleared through this method; invalid owner IDs are rejected. Returns the saved owner ID on success and `false` on failure.

When ownership changes, this method also reassigns the linked WooCommerce parent order and subscription customers through private helper methods.

To retrieve owner details on demand:
- WP user object: `get_user_by( 'id', $owner_id )`
- MDP person record: `wicket_get_person_by_id( $user->user_login )` (UUID = `user_login`)

### `get_owner_id(): int|false`

Returns the canonical owner user ID stored in `user_id`, or `false` if not set or invalid.

### `get_owner_uuid(): string|false`

Derives and returns the MDP UUID for the owner by reading `user_login` from the WP user resolved via `get_owner_id()`. Returns `false` if no owner is set or the user cannot be resolved. The UUID is not stored as post meta.

### `is_owner( int $user_id ): bool`

Returns `true` if the given user ID is the group owner.

### `set_organization( string $org_uuid ): array|false`

Associates an MDP organization with this group. Fetches the org via `Helper::get_org_data()` and stores `org_uuid` and `org_name` as post meta. Organization assignment cannot be cleared through this method; invalid values are rejected. Returns the org data array on success and `false` on failure.

### `get_org_uuid(): string|false`

Returns the `org_uuid` meta value, or `false` if not set.

### `get_organization(): array|false`

Returns the full organization data array from `Helper::get_org_data()` for the stored UUID, or `false` if not set or UUID is invalid.

### `get_config(): Membership_Group_Config|false`

Returns the linked `Membership_Group_Config` object from `membership_group_config_id`, or `false` if not set.

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
| `membership_status` | `string` | Group membership status (see vocabulary above) |
| `membership_group_config_id` | `int` | Linked membership group config post ID |
| `membership_parent_order_id` | `int` | Linked WooCommerce order ID |
| `membership_subscription_id` | `int` | Linked WooCommerce subscription ID |
| `membership_group_id` | `int` | Set on individual membership posts to link them to this group |

---
