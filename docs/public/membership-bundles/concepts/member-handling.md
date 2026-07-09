---
title: Member Handling
---

# Member Handling

Understanding how bundle seats relate to individual membership records — and how the add, remove, and move operations work — will help you avoid common mistakes and handle edge cases correctly.

## The relationship between a bundle and its members

A membership bundle (`wicket_mship_bundle`) is a container. Each seat in the bundle is a standard `wicket_membership` CPT post — the same type used for memberships that exist outside of any bundle. The term **standalone** refers to a `wicket_membership` that has no `membership_bundle_id` — it is not part of any bundle. The term **individual membership** refers to the CPT type itself, regardless of whether it is a bundle seat or standalone. What distinguishes a bundle seat is the `membership_bundle_id` post meta on the individual membership, which points back to the bundle post.

This means:

- A bundle member is still an individual membership. It still requires a `Membership_Tier` and has its own membership status.
- When you query bundle members, you are querying `wicket_membership` posts filtered by `membership_bundle_id`.
- Bundle seats share the bundle's WooCommerce subscription as a billing vehicle. Each seat is a line item on that subscription, not a separate subscription.

## Adding a member

There are two modes for adding a member to a bundle.

### Mode: `new`

Creates a fresh individual membership and links it to the bundle. You must supply the MDP person UUID and the `Membership_Tier` post ID. The tier determines what type of seat is being added and which WooCommerce product covers it.

```php
use Wicket_Memberships\Membership_Bundle_Admin_Controller;

$result = Membership_Bundle_Admin_Controller::add_member([
    'bundle_post_id' => 123,
    'mode'           => 'new',
    'tier_post_id'   => 88,
    'person_uuid'    => 'member-person-uuid',
    // 'product_id'  => 200,  // optional — auto-resolved from tier when omitted
]);
```

If the tier has more than one product, you must pass `product_id` explicitly. Omitting it when the tier is ambiguous returns an `ambiguous_product` error.

### Mode: `existing`

Takes a pre-existing `wicket_membership` post (one that was created outside the bundle) and converts it to a bundle seat. The existing membership is cancelled and a new one is created with the bundle's dates, linked to the bundle.

```php
$result = Membership_Bundle_Admin_Controller::add_member([
    'bundle_post_id'              => 123,
    'mode'                        => 'existing',
    'tier_post_id'                => 88,
    'existing_membership_post_id' => 456,
]);
```

The `person_uuid` parameter is ignored in `existing` mode — the user is resolved from the existing membership post.

### Start date resolution

When adding a new member, the seat's start date is derived from the bundle's current date window:

- If today falls within the bundle's active period → start date is today
- If today is before the bundle's start date → start date equals the bundle's start date
- If today is after the bundle's end date → error (`bundle_ended`)

Pass `start_date_override` to bypass this logic (used internally by the renewal batch processor).

::: info Import path
The bundle CSV import (see [Bundle Import](./bundle-import.md)) calls `Membership_Bundle::add_member()` directly with `skip_status_guard: true`, bypassing the `pending`/`active`/`delayed` bundle-status requirement described below. This lets historical members be attached to bundles imported as `expired`, `cancelled`, or `grace-period`. This bypass is reserved for the import path — `Membership_Bundle_Admin_Controller::add_member()` does not expose it.
:::

### What gets created

Adding a member creates:

1. An MDP membership record (unless in `BYPASS_WICKET` mode)
2. A `wicket_membership` WordPress post with `membership_bundle_id` pointing to the bundle
3. A line item on the bundle's WooCommerce subscription

The line item carries `_membership_post_id` and `_member_name` meta so it can be traced back to the individual membership.

## Removing a member

There are two modes for removing a member.

### Mode: `cancel`

Cancels the individual membership immediately. The line item is removed from the bundle's WooCommerce subscription. No replacement membership is created.

```php
$result = Membership_Bundle_Admin_Controller::remove_member([
    'bundle_post_id'      => 123,
    'membership_post_id'  => 456,
    'mode'                => 'cancel',
]);
```

### Mode: `keep_as_individual`

Converts the bundle seat to a standalone individual membership. The existing bundle-linked membership is cancelled, and a new standalone membership is created with:

- Start date: today (UTC)
- End, expiry, and early-renewal dates inherited from the bundle (so the member keeps the remaining paid term)
- A new WooCommerce order and subscription of their own

```php
$result = Membership_Bundle_Admin_Controller::remove_member([
    'bundle_post_id'     => 123,
    'membership_post_id' => 456,
    'mode'               => 'keep_as_individual',
]);

// On success, $result['membership_post_id'] is the new standalone membership post ID
```

::: warning
`keep_as_individual` works even when the bundle is in `grace-period` status — this is intentional so that members can be released from an expired-but-still-accessible bundle. This is why it does not use the same start-date guard as `add_member`.
:::

Both modes require the bundle to be in `pending`, `active`, or `delayed` status. Attempting to remove from an `expired` or `cancelled` bundle returns `invalid_bundle_status`.

## Moving a member between bundles

Moving cancels the seat in the source bundle and creates a new seat in the target bundle. The member retains the same tier and product; the start date is resolved against the target bundle's date window.

```php
$result = Membership_Bundle_Admin_Controller::move_individual_membership([
    'source_bundle_post_id' => 123,
    'membership_post_id'    => 456,
    'target_bundle_post_id' => 789,
]);

// On success, $result['membership_post_id'] is the new membership post ID in the target bundle
```

Both source and target bundles must be in `pending`, `active`, or `delayed` status.

::: danger No rollback on partial failure
If the source membership is successfully cancelled but the new membership cannot be created in the target bundle, the method returns an error and the member ends up with no active seat. There is no automatic rollback. The error message will explicitly note that the source was cancelled. In this case, re-add the member manually using `add_member` in `new` mode.
:::

## Retrieving a bundle's members

```php
$bundle = new \Wicket_Memberships\Membership_Bundle( $bundle_post_id );

// Active members only (default)
$memberships = $bundle->get_individual_memberships();

// All members including cancelled and expired
$memberships = $bundle->get_individual_memberships( active_only: false );

// Returns an array of WP_Post objects (wicket_membership CPT)
foreach ( $memberships as $membership_post ) {
    $user_id   = get_post_meta( $membership_post->ID, 'user_id', true );
    $tier_uuid = get_post_meta( $membership_post->ID, 'membership_tier_uuid', true );
    $status    = get_post_meta( $membership_post->ID, 'membership_status', true );
}
```

For a grouped summary by tier (total count per tier type):

```php
use Wicket_Memberships\Membership_Bundle_Admin_Controller;

$breakdown = Membership_Bundle_Admin_Controller::get_bundle_members_by_tier( $bundle_post_id );

// $breakdown['total_members'] => 12
// $breakdown['tiers'] => [
//   [ 'tier_uuid' => 'abc-123', 'tier_name' => 'Standard', 'member_count' => 8 ],
//   [ 'tier_uuid' => 'def-456', 'tier_name' => 'Premium',  'member_count' => 4 ],
// ]
```

## Status cascade

When a bundle transitions to a new status, all non-cancelled child memberships receive the same status automatically. You do not need to update individual membership statuses separately. The only exception is `cancelled` child memberships — they are in a terminal state and are skipped during any cascade.

This cascade happens for both manual transitions (via `transition_to()`) and automatic cron-driven transitions (grace period, expiry, activation). If you need to change one member's status independently, update the `membership_status` post meta on the individual `wicket_membership` post directly.
