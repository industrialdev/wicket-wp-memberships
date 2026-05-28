---
title: "Membership_Bundle_Cron_Controller"
audience: [developer]
php_class: Membership_Bundle_Cron_Controller
source_files: ["includes/Membership_Bundle_Cron_Controller.php"]
---

# Membership_Bundle_Cron_Controller

Daily cron handlers for `wicket_mship_bundle` status transitions. Registered in `wicket.php` alongside the equivalent individual membership handlers in `Membership_Controller`.

Each handler uses Action Scheduler (`as_schedule_recurring_action`) to run once per day. On each run it queries group posts due for a status change and delegates to `Membership_Bundle::transition_to()`, which applies lifecycle guards, cascades the new status to child individual memberships, and triggers MDP sync.

## Registered Actions

| AS hook | Handler | Schedule method |
|---|---|---|
| `schedule_daily_bundle_grace_period_hook` | `daily_bundle_grace_period_hook()` | `schedule_daily_bundle_grace_period()` |
| `schedule_daily_bundle_expiry_hook` | `daily_bundle_expiry_hook()` | `schedule_daily_bundle_expiry()` |
| `schedule_daily_bundle_activation_hook` | `daily_bundle_activation_hook()` | `schedule_daily_bundle_activation()` |
| `wicket_bundle_renewal_process_members` | `process_bundle_renewal_members()` | dispatched by `Membership_Controller::handle_bundle_renewal()` |

Daily handlers are registered as recurring daily actions starting tomorrow (midnight, site timezone). The renewal batch handler is a single-action job dispatched on each renewal order.

## Methods

### `schedule_daily_bundle_grace_period(): void`
### `schedule_daily_bundle_expiry(): void`
### `schedule_daily_bundle_activation(): void`

Registration methods. Each checks `as_next_scheduled_action()` before scheduling to avoid duplicates. Called on the `wp` hook.

---

### `daily_bundle_grace_period_hook(): int`

Queries `wicket_mship_bundle` posts where `membership_status = active` and `membership_ends_at < yesterday`. Calls `transition_to('grace-period')` on each. Returns count of bundles processed.

**Timestamp:** uses UTC, matching `daily_membership_grace_period_hook` in `Membership_Controller`.

---

### `daily_bundle_expiry_hook(): int`

Queries `wicket_mship_bundle` posts where `membership_status IN (active, grace-period)` and `membership_expires_at < yesterday`. Calls `transition_to('expired')` on each. Returns count of bundles processed.

**Timestamp:** uses UTC, matching `daily_membership_expiry_hook` in `Membership_Controller`.

---

### `daily_bundle_activation_hook(): int`

Queries `wicket_mship_bundle` posts where `membership_status = delayed` and `membership_starts_at < yesterday`. Calls `transition_to('active')` on each. Returns count of bundles processed.

**Timestamp:** uses `wp_timezone()`, matching `daily_membership_activation_hook` in `Membership_Controller`.

---

### `process_bundle_renewal_members( int $old_bundle_post_id, int $new_bundle_post_id, int $renewal_order_id, int $offset, int $batch_size ): void`

Batch handler for membership renewal provisioning. Dispatched by `Membership_Controller::handle_bundle_renewal()` via Action Scheduler.

**Flow:**

1. Loads the renewal WC order and collects eligible line items — items where `_membership_post_id` is set. This is the authoritative member list for the renewal (not the full old bundle member list).
2. Slices `$batch_size` items starting at `$offset`.
3. For each item: resolves `user_id`, `tier_post_id`, and `product_id` from the old membership post meta, then calls `$new_bundle->add_member(..., is_renewal: true)`.
4. `is_renewal: true` sets the `processing_renewal` flag on `Membership_Controller`, causing `create_membership_record()` to skip the MDP create call — MDP handles bundle members at the org level, not per-member.
5. If more items remain beyond `$offset + $batch_size`, dispatches itself again with the next offset.
6. On the final batch: stamps `completed_at` on `membership_renewal_processing` meta for both old and new bundle posts, adds an order note, and fires `wicket_memberships_bundle_renewal_complete`.

**Error handling:** items with missing `user_id` or `tier_post_id` are skipped and logged. Failed `add_member()` calls are logged and recorded in the `errors` array in the completion meta.

**MDP note:** No per-member MDP calls are made. `add_member(is_renewal: true)` bypasses the MDP create path. MDP is updated at the bundle/org level by the higher-level orchestration.

---

## Date Handling

All three transitions are **status-only** — no dates are rewritten. This matches individual membership handler behaviour. Dates were set when the group was created; cron must not overwrite them.

`Membership_Bundle::plan_status_transition()` returns all-null transition dates for `delayed → active`, `active → grace-period`, and `* → expired`. `apply_status_transition()` skips null fields, so stored dates are preserved.

## Cascade Behaviour

`transition_to()` calls `cascade_status_to_members()` after updating the group, which propagates the new status to all non-cancelled child individual memberships. No additional cascade logic is needed in these handlers.

## Logging

Each handler logs via `Utilities::wc_log_mship_error()`:
- On failure per group: `['handler_name: transition failed', $bundle_post_id]`
- On completion: `['handler_name', $timestamp, $groups_updated]`

## Date Trigger Job Handlers

Three additional static methods handle one-time AS jobs scheduled by `Membership_Bundle::schedule_date_trigger_jobs()`. These fire `do_action` hooks consumed by AutomateWoo triggers — no status transitions.

| AS hook | Handler | `do_action` fired |
|---|---|---|
| `wicket_bundle_early_renew_at` | `catch_bundle_early_renew_at( int $bundle_post_id )` | `wicket_memberships_bundle_renewal_period_open` |
| `wicket_bundle_ends_at` | `catch_bundle_ends_at( int $bundle_post_id )` | `wicket_memberships_bundle_end_date_reached` |
| `wicket_bundle_expires_at` | `catch_bundle_expires_at( int $bundle_post_id )` | `wicket_memberships_bundle_grace_period_expired` |

Jobs are scheduled by `Membership_Bundle::schedule_date_trigger_jobs()` on bundle creation and on every date edit. Existing jobs are cancelled via `as_unschedule_action` before rescheduling to prevent stale jobs after date changes.

These hooks are the bundle equivalents of `add_membership_early_renew_at`, `add_membership_ends_at`, and `add_membership_expires_at` registered in `wicket.php` lines 216–218 for individual memberships.

## Related

- `Membership_Bundle::transition_to()` — lifecycle entrypoint used by all three daily handlers
- `Membership_Bundle::plan_status_transition()` — defines date behaviour per transition path
- `Membership_Controller::daily_membership_expiry_hook()` — individual equivalent
- `Membership_Controller::daily_membership_grace_period_hook()` — individual equivalent
- `Membership_Controller::daily_membership_activation_hook()` — individual equivalent
- `Membership_Controller::handle_bundle_renewal()` — orchestrator that dispatches `wicket_bundle_renewal_process_members`
- `Membership_Bundle::add_member()` — called per member by `process_bundle_renewal_members()`
