---
title: "Membership Bundle Renewal — Implementation Plan"
audience: [developer]
source_files:
  - "includes/Membership_Controller.php"
  - "includes/Membership_Bundle.php"
  - "includes/Membership_Bundle_Config.php"
  - "wicket.php"
---

# Membership Bundle Renewal — Implementation Plan

## MDP Sync Scope — Important Constraint

**MDP handles individual bundle member sync automatically at the org/bundle level.**
Individual `wicket_mship` posts that belong to a bundle do **not** need direct MDP calls
for status or date changes — the MDP propagates those from the bundle record.

This means:
- No `update_mdp_record()` calls on individual memberships that have `membership_bundle_id` set.
- Local WP writes (post meta, status stamps) still happen as normal — WP is the local source of truth.
- `amend_membership_json()` is still called where appropriate (no-op for bundle members, harmless).
- MDP calls are only made at the **bundle level** (via `sync_mdp_update()` once that stub is implemented).

This constraint simplifies Phases 1, 3, and 4, and eliminates the need for any MDP batching.

---

## Phase 1: Fix Individual Membership Cancellation in Bundle Context

Currently `cancel_individual_membership()` in `Membership_Bundle` only stamps
`membership_status = cancelled` locally via `update_membership_status()`. It skips date
collapse and user meta updates.

**Fix:** Collapse dates correctly per current status and call `update_local_membership_record()`
+ `update_membership_status()` + `amend_membership_json()`. **No MDP call** — MDP handles
bundle members at the org level.

**File:** `includes/Membership_Bundle.php` — `cancel_individual_membership()` at [line 505](../includes/Membership_Bundle.php)

```php
/**
 * Cancel a single individual membership that belongs to this bundle.
 *
 * $sync_mdp controls whether the cancellation is pushed to MDP:
 *
 * - Pass true  when cancelling a SINGLE member (remove_member, move_individual_membership).
 *   MDP does not know about this operation from the bundle level — we must tell it directly.
 *
 * - Pass false when called from cascade_status_to_members() during a bundle-level cancel.
 *   MDP propagates the cancellation automatically from the bundle org record — a direct
 *   call per member would be redundant and risk double-processing.
 */
private function cancel_individual_membership( int $membership_post_id, bool $sync_mdp = true ): void {
    $mc             = new Membership_Controller();
    $now            = Utilities::get_mdp_now()->format( 'c' );
    $yesterday      = Utilities::get_mdp_day_start( '-1 day' )->format( 'c' );
    $current_status = get_post_meta( $membership_post_id, 'membership_status', true );

    // Collapse dates per current status — mirrors Admin_Controller::bundle_admin_manage_status()
    // date rules.
    // pending/delayed: back-date starts_at so the record is unambiguously in the past.
    // grace: only collapse expires_at; ends_at already passed.
    // all others: collapse both ends_at and expires_at to now.
    if ( in_array( $current_status, [ Wicket_Memberships::STATUS_PENDING, Wicket_Memberships::STATUS_DELAYED ], true ) ) {
        $meta_data = [
            'membership_starts_at'         => $yesterday,
            'membership_ends_at'           => $now,
            'membership_expires_at'        => $now,
            'membership_grace_period_days' => 0,
        ];
    } elseif ( $current_status === Wicket_Memberships::STATUS_GRACE ) {
        $meta_data = [
            'membership_expires_at'        => $now,
            'membership_grace_period_days' => 0,
        ];
    } else {
        $meta_data = [
            'membership_ends_at'           => $now,
            'membership_expires_at'        => $now,
            'membership_grace_period_days' => 0,
        ];
    }

    // update_local_membership_record() recalculates status from dates and would derive
    // 'expired' — call update_membership_status() after to stamp 'cancelled' on top.
    $mc->update_local_membership_record( $membership_post_id, $meta_data );
    $mc->update_membership_status( $membership_post_id, Wicket_Memberships::STATUS_CANCELLED );
    $mc->amend_membership_json( $membership_post_id, array_merge( $meta_data, [ 'membership_status' => Wicket_Memberships::STATUS_CANCELLED ] ) );

    // Only sync to MDP for single-member operations. Bundle-level cancellations propagate
    // automatically from the bundle org record — per-member calls would be redundant.
    if ( $sync_mdp ) {
        $wicket_uuid = get_post_meta( $membership_post_id, 'membership_wicket_uuid', true );
        if ( ! empty( $wicket_uuid ) && empty( $this->bypass_wicket ) ) {
            $membership_data = [
                'membership_type'        => get_post_meta( $membership_post_id, 'membership_type', true ),
                'membership_wicket_uuid' => $wicket_uuid,
                'membership_starts_at'   => get_post_meta( $membership_post_id, 'membership_starts_at', true ),
                'org_seats'              => get_post_meta( $membership_post_id, 'org_seats', true ),
            ];
            $mc->update_mdp_record( $membership_data, $meta_data );
        } else if ( empty( $wicket_uuid ) ) {
            Wicket()->log()->error( 'cancel_individual_membership: no membership_wicket_uuid, skipping MDP sync', [
                'source'             => 'wicket-memberships',
                'bundle_post_id'     => $this->post_id,
                'membership_post_id' => $membership_post_id,
            ] );
        }
    }
}
```

### Call sites that must pass `$sync_mdp = true` (single-member operations)

- `remove_member()` — cancels one member, MDP must be notified directly
- `move_individual_membership()` — cancels source membership, MDP must be notified
- `add_member( existing_membership_post_id )` — cancels the existing standalone membership before re-provisioning into the bundle (line 301); MDP must be notified of that cancellation

All three currently call `cancel_individual_membership()` without the flag — they will default to `true` once the parameter is added, which is correct.

### Call sites that must pass `$sync_mdp = false` (bundle-level operations)

- `cascade_status_to_members('cancelled')` — MDP propagates from bundle org record

### TODO: Bundle member MDP create call

`add_member()` → `provision_individual_membership_record()` → `create_membership_record()` → `create_mdp_record()` → `wicket_assign_individual_membership()`.

This MDP create call is the **standard individual membership API**. For bundle members, the correct API call is different — it must be provided in a later task. Until then, the existing `create_mdp_record()` path fires but may not be correct for bundle context.

**Track in TODO.md:** `provision_individual_membership_record()` uses `create_membership_record()` → `wicket_assign_individual_membership()` for MDP creation. Bundle members require a different MDP API call — replace once the correct endpoint/payload is confirmed.

---

### Impact on `cascade_status_to_members()`

`cascade_status_to_members('cancelled')` currently calls `update_post_meta()` directly per
member with no date collapse. Route the `cancelled` path through `cancel_individual_membership()`
instead:

**File:** `includes/Membership_Bundle.php` — `cascade_status_to_members()` at [line 1893](../includes/Membership_Bundle.php)

```php
private function cascade_status_to_members( string $new_status ): void {
    $memberships = $this->get_individual_memberships( false );

    foreach ( $memberships as $membership_post ) {
        $current = get_post_meta( $membership_post->ID, 'membership_status', true );

        if ( $current === Wicket_Memberships::STATUS_CANCELLED ) {
            continue;
        }

        if ( $new_status === Wicket_Memberships::STATUS_CANCELLED ) {
            // Bundle-level cancel — MDP propagates from the bundle org record.
            // Pass sync_mdp = false to skip the per-member MDP call.
            $this->cancel_individual_membership( $membership_post->ID, false );
        } else {
            // All other statuses: local stamp only. MDP handles bundle members at org level.
            update_post_meta( $membership_post->ID, 'membership_status', $new_status );
        }
    }
}
```

---

## Phase 2: Exclude Bundle Members from `Membership_Controller` Daily Cron Hooks

The three daily cron hooks in `Membership_Controller` query all `wicket_mship` posts with no
filter on `membership_bundle_id`. This causes bundle members to be double-processed — their
status is stamped locally by the individual cron with no MDP call, then again (correctly) by
the bundle cron via `transition_to()` → `cascade_status_to_members()`.

**Fix:** Add a `membership_bundle_id NOT EXISTS` condition to the `meta_query` in all three hooks.
Once excluded, bundle members receive status transitions exclusively through
`Membership_Bundle_Cron_Controller` → `transition_to()` → `cascade_status_to_members()`
(local writes only — MDP handles bundle members at org level).

**File:** `includes/Membership_Controller.php`

Add to `meta_query` in each of the three hooks:

```php
[
    'key'     => 'membership_bundle_id',
    'compare' => 'NOT EXISTS',
],
```

Affected methods:
- `daily_membership_grace_period_hook()` (~line 2522)
- `daily_membership_expiry_hook()` (~line 2430)
- `daily_membership_activation_hook()` (~line 2484)

---

## Phase 3: Local Status Cascade Improvements for All Statuses

Phase 1 routes `cancelled` through `cancel_individual_membership()` with proper date collapse.
Non-cancel statuses still use raw `update_post_meta()` with no date handling. Phase 3 extends
the cascade to apply correct local date mutations for `expired` and use
`update_local_membership_record()` + `update_membership_status()` consistently. No MDP calls —
MDP handles bundle members at org level.

### Per-status local behaviour

**`expired`:** Collapse `ends_at` and `expires_at` to tomorrow, `grace_period_days` = 0.
Mirrors `bundle_admin_manage_status()` date rules for expired status.

**`active`, `delayed`, `grace-period`:** No date mutation — provision-time dates are authoritative.
Stamp status locally only.

**`cancelled`:** Delegates to `cancel_individual_membership()` (Phase 1).

### Updated `cascade_status_to_members()` — Phase 3 shape

**File:** `includes/Membership_Bundle.php` — `cascade_status_to_members()` at [line 1893](../includes/Membership_Bundle.php)

```php
private function cascade_status_to_members( string $new_status ): void {
    $mc      = new Membership_Controller();
    $members = $this->get_individual_memberships( false );

    foreach ( $members as $membership_post ) {
        $membership_post_id = $membership_post->ID;
        $current            = get_post_meta( $membership_post_id, 'membership_status', true );

        if ( $current === Wicket_Memberships::STATUS_CANCELLED ) {
            continue;
        }

        if ( $new_status === Wicket_Memberships::STATUS_CANCELLED ) {
            // Bundle-level cancel — MDP propagates from the bundle org record.
            // Pass sync_mdp = false to skip the per-member MDP call.
            $this->cancel_individual_membership( $membership_post_id, false );
            continue;
        }

        // expired: collapse dates locally. All others: no date mutation.
        if ( $new_status === Wicket_Memberships::STATUS_EXPIRED ) {
            $tomorrow  = Utilities::get_mdp_day_end( '+1 day' )->format( 'c' );
            $meta_data = [
                'membership_ends_at'           => $tomorrow,
                'membership_expires_at'        => $tomorrow,
                'membership_grace_period_days' => 0,
            ];
        } else {
            $meta_data = [];
        }

        // Local writes only — MDP handles bundle members at org level.
        $mc->update_local_membership_record( $membership_post_id, array_merge( $meta_data, [ 'membership_status' => $new_status ] ) );
        $mc->update_membership_status( $membership_post_id, $new_status );
        $mc->amend_membership_json( $membership_post_id, array_merge( $meta_data, [ 'membership_status' => $new_status ] ) );
    }
}
```

---

## Phase 4: Status Skip Guard for `transition_to_cancelled_at_end_date()`

The existing loop writes `membership_expires_at = current_ends_at` per member. No MDP call
needed. Add only the status skip guard to avoid touching already-final members.

**File:** `includes/Membership_Bundle.php` — `transition_to_cancelled_at_end_date()` at [line 1615](../includes/Membership_Bundle.php)

Replace the loop at lines 1648–1652 with:

```php
// Collapse expires_at on each individual membership. Local write only — MDP handles
// bundle members at org level.
foreach ( $this->get_individual_memberships( false ) as $membership_post ) {
    $member_id = $membership_post->ID;
    $status    = get_post_meta( $member_id, 'membership_status', true );

    // Skip final states — expires_at collapse is meaningless for already-done members.
    if ( in_array( $status, [ Wicket_Memberships::STATUS_CANCELLED, Wicket_Memberships::STATUS_EXPIRED ], true ) ) {
        continue;
    }

    update_post_meta( $member_id, 'membership_expires_at', $current_ends_at );
}
```

---

## Phase 5: Bundle Subscription Renewal Handler

See full detail in the sections below: [Overview](#overview--renewal-flow), [Trigger Point](#trigger-point), [Detection](#detection-is-this-a-bundle-renewal), Steps 1–6, [Idempotency Guard](#idempotency-guard), and [New Method](#new-method-membership_controllerhandle_bundle_renewal).

---

## Phase 6: Renewal Processing Indicator

Surface the batch job progress in the admin UI so admins understand what is happening
during the access gap window.

### Meta key: `membership_renewal_processing`

Stored as a JSON blob on both the old and new bundle posts. Set before the first batch
job dispatches, updated each job, deleted when the last job completes.

**Shape:**
```php
[
    'started_at'         => current_time( 'c' ),   // ISO — when renewal triggered
    'renewal_order_id'   => $order->get_id(),       // WC order that triggered renewal
    'old_bundle_post_id' => $old_bundle->post_id,  // source bundle
    'new_bundle_post_id' => $new_bundle->post_id,  // bundle being built
    'total_members'      => int,                   // total members to provision
    'batch_size'         => 25,                    // members per AS job
    'offset'             => int,                   // members provisioned so far — updated each job
]
```

**Lifecycle:**
- Written to both bundle posts before first AS job dispatches (Phase 5)
- `offset` incremented by `batch_size` at the start of each job
- Deleted from both posts when final job completes (no members remain)
- Absence of the key = renewal complete

### Frontend use

The bundle detail page reads `membership_renewal_processing` from the REST response and
renders a full-page blocking overlay when present. The overlay prevents all interaction
with the bundle record until the batch completes.

**Component:** `RenewalProcessingOverlay` in `frontend/src/membership_bundles/components/`

**Overlay behaviour:**
- Covers the entire bundle detail page content area (position fixed or absolute over the React root)
- Blocks pointer events on everything beneath it — admin cannot edit, cancel, or manage members during processing
- Auto-dismisses (unmounts) when `membership_renewal_processing` meta is absent from the REST response
- Page should poll the REST endpoint periodically (e.g. every 10 seconds) to detect completion and remove the overlay

**Overlay content (matching mockup):**
- Spinner icon (WordPress dashicon `dashicons-update-alt` spinning via CSS, or a simple CSS spinner)
- Heading: **"Renewal In Progress"**
- Subtext: "Please wait for the renewal process to finish"
- Progress: `{offset}/{total_members} records processed.`
- Started at: formatted `started_at` timestamp

**Styling:**
- Semi-transparent white background over page content (e.g. `rgba(255,255,255,0.92)`)
- Centred content block, heading in dark grey, progress in body text, started_at in muted grey
- Consistent with existing bundle admin UI styles

### Files touched

| File | Change |
|---|---|
| `includes/Membership_Controller.php` | Write `membership_renewal_processing` meta before dispatching first batch job |
| `includes/Membership_Bundle_Cron_Controller.php` | Update `offset` each job; delete meta on completion |
| `frontend/src/membership_bundles/components/RenewalProcessingOverlay.js` | New React component — full-page blocking overlay with spinner, progress, elapsed time |
| `frontend/src/membership_bundles/` | Mount `RenewalProcessingOverlay` on bundle detail page; poll REST endpoint every 10s to detect completion |

---

## Overview — Renewal Flow

When a Membership Bundle subscription generates a renewal order and that order enters
`processing` status, the system must:

1. Detect that the order belongs to a bundle subscription (not an individual membership subscription).
2. Create a new bundle post for the next term with new dates, same org/owner/config.
3. Create a new WC subscription for the new bundle post.
4. Cancel the old bundle post — cascades locally to all child individual memberships (local writes only, no MDP per member).
5. Dispatch a batch Action Scheduler job to renew individual memberships asynchronously.

Individual membership renewal is handled by the batch job, not inline.

---

## Trigger Point

**Hook:** `woocommerce_order_status_processing`
**Handler:** `Membership_Controller::catch_order_completed()` ([Membership_Controller.php:468](../includes/Membership_Controller.php))

The pre-check for bundle renewal must run **before line 507** inside `catch_order_completed()` —
before the `foreach($subscriptions as $subscription)` loop that contains the monthly subscription
guard (lines 512–519). Bundle configs support both `year` and `month` period types. If a bundle
subscription is monthly, the guard at line 512 (`billing_period == 'month' && created_date !== today`)
would fire first and `return` — killing the bundle renewal before it is detected.

Insert the bundle pre-check immediately after the `$subscriptions` assignment at line 498, before
the monthly guard loop. If the order belongs to a bundle subscription, call `handle_bundle_renewal()`
and `return` — do not fall through to the individual membership path.

**Hook registration:** No new `add_action` needed. Keep a single `woocommerce_order_status_processing`
pointing to `catch_order_completed()` and add the bundle pre-check inline, gated by `WICKET_MSHIP_ENABLE_BUNDLES`.

---

## Detection: Is This a Bundle Renewal?

`create_bundle_subscription()` already writes `membership_bundle_id` = bundle post ID onto
the WC subscription via `update_post_meta()` at [Membership_Bundle.php:2593](../includes/Membership_Bundle.php).
WCS carries this meta forward onto renewal orders. Use it for detection.

```php
// Inside catch_order_completed(), before the existing memberships loop:
if ( empty( $_ENV['WICKET_MSHIP_ENABLE_BUNDLES'] ) ) {
    // Bundles not enabled — skip bundle path entirely.
} else {
    foreach ( $subscriptions as $subscription ) {
        $bundle_post_id = (int) get_post_meta( $subscription->get_id(), 'membership_bundle_id', true );
        if ( $bundle_post_id > 0
            && get_post_type( $bundle_post_id ) === Helper::get_membership_bundle_cpt_slug()
            && wcs_order_contains_subscription( $order, 'renewal' )
        ) {
            self::handle_bundle_renewal( $bundle_post_id, $subscription, $order );
            return;
        }
    }
}
```

---

## Step 1: Resolve Old Bundle and Calculate New Dates

Inside `handle_bundle_renewal()`:

```php
$old_bundle = new Membership_Bundle( $old_bundle_post_id );
$config     = $old_bundle->get_config();  // Membership_Bundle_Config instance
$old_dates  = $old_bundle->get_dates();   // ['starts_at', 'ends_at', 'expires_at', 'early_renew_at']
```

Calculate new term dates via `Membership_Bundle_Config::get_membership_dates()` ([Membership_Bundle_Config.php:372](../includes/Membership_Bundle_Config.php)).

Pass the old bundle's dates as the `$membership` input array so the config calculates the
next term anchored to the old `ends_at` (the config treats `membership_ends_at` as the
current period end and returns dates for the following period — start = day after old `ends_at`,
matching the individual membership renewal pattern):

```php
$new_dates = $config->get_membership_dates( [
    'membership_ends_at'   => $old_dates['ends_at'],
    'membership_starts_at' => $old_dates['starts_at'],
] );
// $new_dates keys: start_date, end_date, expires_at?, early_renew_at?
```

---

## Step 2: Create New Bundle Post

Call `Membership_Bundle::create()` ([Membership_Bundle.php:56](../includes/Membership_Bundle.php)):

| Argument | Source |
|---|---|
| `$name` | Old bundle post title |
| `$membership_bundle_config_id` | `$config->get_post_id()` |
| `$org_uuid` | `$old_bundle->get_org_uuid()` |
| `$owner_uuid` | `$old_bundle->get_owner_uuid()` (person UUID of the bundle owner) |
| `$start_date` | `$new_dates['start_date']` |
| `$group_uuid` | `$old_bundle->get_bundle_group_uuid()` — pass directly so `create()` uses it instead of generating a new one |

`create()` should accept an optional `$group_uuid` parameter. When provided it uses that value
for `membership_bundle_group_uuid` instead of generating a new one. This avoids the need to
overwrite the UUID after the fact and keeps the renewal series link atomic with creation.

**Required change to `Membership_Bundle::create()`:** Add optional `$group_uuid = null` parameter.
If provided, write it as `membership_bundle_group_uuid` post meta instead of calling `wp_generate_uuid4()`.

---

## Step 3: New Bundle WC Subscription

`Membership_Bundle::create()` calls `create_bundle_subscription()` internally and stores
the subscription ID in `membership_subscription_id` post meta. `create_bundle_subscription()`
already writes `membership_bundle_id` = new bundle post ID onto the new subscription
([line 2593](../includes/Membership_Bundle.php)). **No additional subscription creation code needed.**

The new subscription status is `pending` at this point. Activation occurs after the old
bundle is cancelled (Step 4) to avoid two active subscriptions simultaneously.

---

## Step 4: Cancel Old Bundle

Use `transition_to('cancelled')` on the old bundle. This cascades locally to all child
individual memberships via `cascade_status_to_members()` (Phase 1 + Phase 3) — local writes
only, no MDP calls per member. MDP is notified at the bundle level and handles its own
cascade on that side.

```php
$old_bundle->transition_to( Wicket_Memberships::STATUS_CANCELLED );
```

Also cancel the old bundle's WC subscription to stop WCS from scheduling another renewal:

```php
$old_sub_id = $old_bundle->get_subscription_id();
if ( $old_sub_id ) {
    $old_sub = wcs_get_subscription( $old_sub_id );
    if ( $old_sub ) {
        $old_sub->update_status( 'cancelled' );
    }
}
```

**Known limitation — access gap:** Old individual memberships are cancelled locally the moment
this step runs. New memberships are created asynchronously by the batch job (Step 5). For a
100-member bundle this gap is ~60 seconds; for 1,000 members ~10 minutes. Accepted limitation
— no current solution. Log batch job start/end for visibility into the gap window.

---

## Step 5: Dispatch Batch Job for Individual Member Renewal

After the new bundle post exists and the old bundle is cancelled, dispatch the first
batch job via Action Scheduler. The batch job handles one thing per member:

- **Create a new individual membership on the new bundle** (MDP call via `add_member()` → `create_mdp_record()`)

Old individual membership cancellation already happened in Step 4 via `transition_to('cancelled')` → `cascade_status_to_members()`. The batch job does not re-cancel them.

```php
// Write processing indicator to both bundles — see Phase 6 for full meta shape.
$processing_meta = [
    'started_at'         => current_time( 'c' ),
    'renewal_order_id'   => $order->get_id(),
    'old_bundle_post_id' => $old_bundle->post_id,
    'new_bundle_post_id' => $new_bundle->post_id,
    'total_members'      => count( array_filter( $order->get_items(), fn( $item ) => (bool) wc_get_order_item_meta( $item->get_id(), '_membership_post_id', true ) ) ),
    'batch_size'         => 25,
    'offset'             => 0,
];
update_post_meta( $old_bundle->post_id, 'membership_renewal_processing', wp_json_encode( $processing_meta ) );
update_post_meta( $new_bundle->post_id, 'membership_renewal_processing', wp_json_encode( $processing_meta ) );

as_schedule_single_action(
    time(),
    'wicket_bundle_renewal_process_members',
    [
        'old_bundle_post_id' => $old_bundle->post_id,
        'new_bundle_post_id' => $new_bundle->post_id,
        'renewal_order_id'   => $order->get_id(),
        'offset'             => 0,
        'batch_size'         => 25,
    ],
    'wicket-memberships',
    false
);
```

### Source of truth: renewal order line items

The renewal order's line items are the authoritative list of what gets renewed — not the
full old bundle member list. WCS clones subscription line items onto the renewal order.
Bundle subscription line items carry `_membership_post_id` meta (written by
`add_subscription_line_item()` at line 1017) pointing to the individual membership post ID.

Note: individual/org membership renewal uses `_membership_post_id_renew` — bundle subscriptions
use `_membership_post_id` instead (no `_renew` suffix). This is because bundle members are
added via `add_subscription_line_item()` directly, not through the WC checkout cart flow that
writes `_membership_post_id_renew`.

Only line items with `_membership_post_id` set are renewed. Members added mid-term after the
last subscription cycle will appear on the subscription but their line item will carry the
new membership post ID — they will be renewed correctly.

After creating each new membership, update the subscription line item's `_membership_post_id`
to the **new** membership post ID — this keeps it current for the next renewal cycle.

### `add_member()` signature change

Add `bool $is_renewal = false` as a sixth parameter. Pass it through to
`provision_individual_membership_record()` and on to `create_membership_record( $data, $is_renewal )`.
This ensures `processing_renewal` is set correctly on `Membership_Controller` so approval
logic and MDP create gating behave as renewal — not a fresh signup.

```php
public function add_member(
    ?int $user_id,
    int $tier_post_id,
    ?int $product_id = null,
    ?int $variation_id = null,
    ?int $existing_membership_post_id = null,
    bool $is_renewal = false
): int|\WP_Error
```

All existing call sites default to `false` — no breaking changes. The renewal batch job
passes `true`.

### Batch job handler — `wicket_bundle_renewal_process_members`

Registered in `Membership_Bundle_Cron_Controller`. Payload includes `renewal_order_id` so
each job can read line items directly.

Per job execution:

1. Load a chunk of line items from the renewal order at `offset` with `batch_size` limit
2. For each line item in the chunk:
   - Read `_membership_post_id` from item meta — skip if empty
   - Read `user_id`, `membership_tier_post_id`, `membership_product_id` from old membership post meta
   - Call `$new_bundle->add_member( $user_id, $tier_post_id, $product_id, null, null, true )` — creates new individual membership + MDP create call with `$is_renewal = true`
   - Set `previous_membership_post_id` meta on the new membership post to the old membership post ID
   - Update the new bundle's subscription line item's `_membership_post_id` to the new membership post ID
3. Update `offset` in `membership_renewal_processing` meta on both bundle posts
4. If more line items remain, dispatch next job with `offset + batch_size`
5. If no items remain:
   - Delete `membership_renewal_processing` meta from both old and new bundle posts
   - Add completion order note (Step 6)
   - Log completion

Old individual membership cancellation is **not** handled here — Step 4's `transition_to('cancelled')` already cascaded that locally to all members before the batch started.

### Why batching is mandatory

Each `add_member()` call makes **2 blocking MDP HTTP calls** via `create_membership_record()`:
- `POST /person_memberships` (~200–800ms)
- `wicket_update_membership_external_id()` (~200–800ms)

At 300ms per call (conservative): 25 members × 600ms = **~15s per job** — safe within AS's 30s timeout.

100 members inline = ~60s — exceeds PHP `max_execution_time` in a web or WCS renewal context.

**Batch size of 25 is the safe default.**

---

## Step 6: Add Order Notes

Two notes are added to the renewal order — one when the process starts, one when the batch completes.

**On renewal start** (inside `handle_bundle_renewal()`, after dispatching the first batch job):
```php
$order->add_order_note( sprintf(
    'Membership bundle renewal process has begun. Creating %d new individual memberships. Old bundle: #%d → New bundle: #%d.',
    $total_members,
    $old_bundle->post_id,
    $new_bundle->post_id
) );
```

**On batch completion** (inside the batch job handler, when no members remain):
```php
$order = wc_get_order( $renewal_order_id ); // stored in processing meta
$order->add_order_note( sprintf(
    'Membership bundle renewal process has been completed. %d new individual memberships have been created.',
    $total_members
) );
```

`renewal_order_id` is available from the `membership_renewal_processing` meta stored on the bundle post.

---

## New Method: `Membership_Controller::handle_bundle_renewal()`

**File:** `includes/Membership_Controller.php`

```php
private static function handle_bundle_renewal(
    int $old_bundle_post_id,
    \WC_Subscription $subscription,
    \WC_Abstract_Order $order
): void
```

Owns Steps 1–6. `catch_order_completed()` calls this and returns immediately after.

---

## Idempotency Guard

Before creating the new bundle (Step 2), check whether a bundle in the same renewal series
with a `membership_starts_at` matching the new term's `start_date` already exists. If yes,
log and return — the order status was cycled, not a genuine renewal.

```php
// Query for existing bundle in same series with same start date
$existing = get_posts( [
    'post_type'   => Helper::get_membership_bundle_cpt_slug(),
    'post_status' => 'any',
    'numberposts' => 1,
    'fields'      => 'ids',
    'meta_query'  => [
        [ 'key' => 'membership_bundle_group_uuid', 'value' => $old_bundle->get_bundle_group_uuid() ],
        [ 'key' => 'membership_starts_at',         'value' => $new_dates['start_date'] ],
    ],
] );

if ( ! empty( $existing ) ) {
    Wicket()->log()->info( 'handle_bundle_renewal: new-term bundle already exists, skipping', [...] );
    return;
}
```

---

## Resolved Implementation Details

**Detection meta key:** The subscription already carries `membership_bundle_id` (not
`membership_group_id` from the spec's older terminology). No backfill needed — written
by `create_bundle_subscription()` at [line 2593](../includes/Membership_Bundle.php).

**Line item meta key:** Bundle subscription line items use `_membership_post_id` (written by
`add_subscription_line_item()` at [line 1017](../includes/Membership_Bundle.php)). WCS clones
this onto renewal order line items automatically.

**Date calculation:** `Membership_Bundle_Config::get_membership_dates()` is the current-codebase
equivalent of the spec's `Membership_Group_Config::get_membership_dates()`.

**Bundle-level cancel uses `transition_to('cancelled')`:** Step 4 calls `$old_bundle->transition_to('cancelled')`,
which cascades locally to all child individual memberships via `cascade_status_to_members()`.
This is correct — WP records must reflect cancelled status, and MDP handles its own cascade
from the bundle org record. Do not use `set_membership_status('cancelled')` for Step 4, as that
skips the child membership cascade.

**Individual member renewal:** Handled by the batch job, not inline. `add_member()` is the
correct method to call per member inside that job.

**Activation of new bundle subscription:** The new bundle starts `pending` or `delayed` after
`create()` (delayed when `start_date` is a future date, which is typical for renewals where
start = day after old `ends_at`). The batch job handler activates the new bundle subscription
when the final batch completes (no members remain). Call `$new_sub->update_status('active')`
at that point. The new bundle post's own status will transition via the Cron Controller's
daily activation hook when `starts_at` is reached — do not force-activate the post directly.

---

## Remaining Open Questions

1. **Monthly subscription guard:** ~~Resolved~~ — bundle configs support `month` period type
   (`Membership_Bundle_Config.php:381`). Bundle pre-check is inserted before the monthly guard loop
   (before line 507) to prevent early return. No bypass needed.

2. **New bundle status after `create()`:** `create()` sets `pending` or `delayed` based on
   start date. For renewals where start = day after old ends_at (a future date), the bundle
   will be `delayed`. Confirm this is the correct initial status for a renewal-created bundle,
   or whether it should be set explicitly to `active` after the batch completes.

3. **Active cancellation of old individual memberships:** Does the renewal flow require
   actively calling MDP to cancel old individual membership records, or can they be left to
   expire via their existing Action Scheduler lifecycle jobs? Active cancellation adds one
   MDP HTTP call per member (~300–800ms) to the batch cost. Recommend letting them expire
   naturally unless MDP requires explicit cancellation to prevent grace-period overlap. This
   decision affects batch size and whether a cancellation batch is needed in addition to the
   creation batch.

---

## Completion Checklist (per phase)

After completing each phase, verify:

- [ ] Code change implemented and reviewed
- [ ] QA tests updated or added in `qa/tests/WordPress/Memberships/` to cover new behaviour
- [ ] Tests pass locally before marking phase done
- [ ] Plan doc updated to reflect any decisions made during implementation
- [ ] `docs/class-index/` updated if method signatures changed

---

## Files Touched

| File | Change |
|---|---|
| `includes/Membership_Bundle.php` | **Phase 1:** rewrite `cancel_individual_membership()` with date collapse; route `cascade_status_to_members('cancelled')` through it. **Phase 3:** extend cascade for `expired` date collapse + consistent local writes for all statuses. **Phase 4:** add status skip guard to `transition_to_cancelled_at_end_date()` loop. |
| `includes/Membership_Controller.php` | **Renewal:** bundle pre-check in `catch_order_completed()`; new `handle_bundle_renewal()` |
| `wicket.php` | Confirm existing `woocommerce_order_status_processing` hook registration covers bundle path (no new add_action needed if pre-check is inline) |
| `qa/tests/WordPress/Memberships/bundle-admin-controller.pest.php` | **Phase 1:** update cascade cancel tests to assert date collapse; add pending and grace-period branch tests |
| `docs/engineering/membership-bundle-renewal.md` | This file |
| `docs/engineering/Class-Membership_Controller.md` | Document new method |
| `CURRENT_SCOPE.md` | Add bundle renewal to scope |
| `TODO.md` | Add entries for open questions and batch job dependency |

---

## Related Code Pointers

| Symbol | Location |
|---|---|
| `catch_order_completed()` | [Membership_Controller.php:468](../includes/Membership_Controller.php) |
| `Membership_Bundle::create()` | [Membership_Bundle.php:56](../includes/Membership_Bundle.php) |
| `Membership_Bundle_Config::get_membership_dates()` | [Membership_Bundle_Config.php:372](../includes/Membership_Bundle_Config.php) |
| `Membership_Bundle::get_individual_memberships()` | [Membership_Bundle.php:218](../includes/Membership_Bundle.php) |
| `Membership_Bundle::add_member()` | [Membership_Bundle.php:267](../includes/Membership_Bundle.php) |
| `Membership_Bundle::set_membership_status()` | [Membership_Bundle.php:1825](../includes/Membership_Bundle.php) |
| `Membership_Bundle::get_bundle_group_uuid()` | [Membership_Bundle.php:1369](../includes/Membership_Bundle.php) |
| `create_bundle_subscription()` meta writes | [Membership_Bundle.php:2593](../includes/Membership_Bundle.php) |
| Hook registration | [wicket.php:215](../wicket.php) |
