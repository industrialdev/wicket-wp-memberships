
# Membership_Group Class Index

**File:** includes/Membership_Group.php

## Properties

- `$post_id` (public readonly int) — the WP post ID of this membership group; set once on construction, cannot be modified
- `$meta_data` (public) — all post meta for this group, populated on construction
- `$bypass_wicket` (public) — skips MDP API calls when `$_ENV['BYPASS_WICKET']` is set

## Methods

- `__construct($post_id)`
- `create($args = [])` (static)
- `add_individual_membership($membership_post_id)`
- `get_individual_memberships()`
- `set_group_owners(array $user_ids)`
- `is_group_owner(int $user_id)`
- `get_group_owners()`

---

## Method Descriptions

**__construct($post_id)**
Validates the post exists and is a `wicket_mship_group`. On success, sets `$post_id` and hydrates `$meta_data` from post meta. On failure, logs an error and sets `$post_id` to 0 with an empty `$meta_data`.

**create($args = [])** (static)
TODO: Creates a new `wicket_mship_group` post using `$args` and returns a new `Membership_Group` instance, or false on failure.

**add_individual_membership($membership_post_id)**
TODO: Sets `membership_group_id` meta on the individual membership post to associate it with this group.

**get_individual_memberships()**
Returns all `wicket_membership` posts (any status) whose `membership_group_id` meta value matches `$this->post_id`.

**set_group_owners(array $user_ids)**
Stores an array of WP user IDs as the `group_owner_ids` post meta on this group. Values are cast to integers and re-indexed before saving. Returns the saved owner IDs (via `get_group_owners()`) on success, or `false` on failure.

**is_group_owner(int $user_id)**
Returns `true` if the given user ID is present in the `group_owner_ids` meta, `false` otherwise.

**get_group_owners()**
Returns the stored `group_owner_ids` meta as an array of integers, or an empty array if none are set.
