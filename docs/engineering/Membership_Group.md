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

### `set_group_owners( array $user_ids ): int[]|false`

Replaces the stored `group_owner_ids` meta with the provided array of user IDs. Returns the saved owner array on success, `false` on failure.

### `get_group_owners(): int[]`

Returns the array of group owner user IDs stored in `group_owner_ids` meta. Returns an empty array if not set.

### `is_group_owner( int $user_id ): bool`

Returns `true` if the given user ID is in the group owners list.

---

### `set_organization( ?string $org_uuid ): array|true|false`

Associates an MDP organization with this group. Fetches the org via `Helper::get_org_data()` and stores `org_uuid` and `org_name` as post meta. Pass `null` to remove the organization. Returns the org data array on success, `true` when cleared, `false` on failure.

### `get_org_uuid(): string|false`

Returns the `org_uuid` meta value, or `false` if not set.

### `get_organization(): array|false`

Returns the full organization data array from `Helper::get_org_data()` for the stored UUID, or `false` if not set or UUID is invalid.

---

### `get_membership_status(): string|false`

Returns the `membership_status` meta value for this group, or `false` if not set.

### `set_membership_status( string $status ): bool`

Sets the `membership_status` meta value. The value must be one of the slugs returned by `Helper::get_all_status_names()`. Returns `true` on success. Logs an error and returns `false` if the status is not in the allowed list or if the meta update fails.

---

### `get_individual_memberships(): array`

Returns all individual membership CPT posts that have `membership_group_id` set to this group's post ID.

---

## Meta Keys

| Key | Type | Description |
|---|---|---|
| `group_owner_ids` | `int[]` | WP user IDs of group owners |
| `org_uuid` | `string` | MDP organisation UUID |
| `org_name` | `string` | MDP organisation legal name (cached) |
| `membership_status` | `string` | Group membership status (see vocabulary above) |
| `membership_group_id` | `int` | Set on individual membership posts to link them to this group |

---

