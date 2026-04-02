# Membership Date & Status Changes

How manual date edits and status transitions affect membership records, WooCommerce subscriptions, and the external Wicket MDP system.

---

## Overview

A membership record holds four date fields that define its full lifecycle. These dates can be changed in two ways:

1. **Manual date edit** — An administrator directly updates Start, End, or Expiration Date on the membership record (`update_membership_entity_record()` in `Admin_Controller.php`)
2. **Status change** — An administrator changes the membership status, which recalculates and overwrites dates according to business rules (`admin_manage_status()` in `Admin_Controller.php`)

Both paths trigger a cascading update across three systems: the local WordPress database, the WooCommerce subscription, and the external Wicket MDP platform.

---

## Membership Date Fields

| Field | Description | Time of Day |
|-------|-------------|-------------|
| **Start Date** (`membership_starts_at`) | When membership access begins | Midnight (start of day) |
| **End Date** (`membership_ends_at`) | When the membership period officially ends | 23:59:59 (end of day) |
| **Expiration Date** (`membership_expires_at`) | When access is fully lost (End Date + grace period) | 23:59:59 (end of day) |
| **Early Renewal Date** (`membership_early_renew_at`) | When the renewal window opens (End Date − renewal window days) | Calculated from tier config |

The relationship between these fields is always:

```
Start Date < End Date ≤ Expiration Date
```

And the grace period can be derived from any record:

```
Grace Period Days = Expiration Date − End Date
```

---

## Part 1: Manual Date Changes

### What Happens When You Edit Dates

When an administrator updates **Start Date**, **End Date**, or **Expiration Date** on a membership record, the system calls `update_membership_entity_record()` in `Admin_Controller.php`.

**Validation performed:**
- `start_date < end_date ≤ expiry_date` must hold; the update is rejected if not
- All dates are normalized to MDP timezone (Start Date = midnight, End/Expiry = 23:59:59)

**Fields automatically recalculated from your input:**

| Calculated Field | How It Is Derived |
|---|---|
| `membership_early_renew_at` | `end_date − renewal_window_days` (from tier config) |
| `membership_grace_period_days` | `expiry_date − end_date` (absolute days) |

You do not set these directly — they are always derived from the dates you enter.

---

### Effect on WooCommerce Subscription Dates

After local dates are saved, `update_membership_subscription()` in `Membership_Controller.php` is called with `['start_date', 'end_date']` flags (and optionally `'next_payment_date'`).

**How membership dates map to subscription dates:**

| Membership Date | Subscription Field | Condition |
|---|---|---|
| `membership_ends_at` | Subscription **end date** | Monthly subscription renewals |
| `membership_expires_at` | Subscription **end date** | Non-monthly renewals, or subscription-type renewal |
| `membership_ends_at` | Subscription **next payment** | Non-monthly subscriptions only |

> **Why the difference?** For monthly subscription-based renewals, the subscription ends when the membership ends. For annual or multi-period memberships, the subscription is kept active through the grace period (expiration date), giving the member time to renew before the subscription fully closes.

An order note is added to the subscription:

```
Membership #<post_id> changed these subscription dates.
Next Payment Date: <date>
End Date: <date>
```

**Clearing next payment date:** If the renewal type is set to subscription and the next payment should be cleared, a background action (`schedule_wicket_wipe_next_payment_date`) is scheduled via Action Scheduler to delete the `_schedule_next_payment` post meta 90 seconds later. This handles a WooCommerce Subscriptions edge case where the date cannot be cleared synchronously.

---

### Effect on Wicket MDP

After the subscription update, `update_mdp_record()` in `Membership_Controller.php` syncs the following fields to the external Wicket platform:

- `membership_starts_at`
- `membership_ends_at`
- `membership_grace_period_days`

For **individual memberships:**
```
wicket_update_individual_membership_dates(uuid, starts_at, ends_at, grace_period_days)
```

For **organization memberships:**
```
wicket_update_organization_membership_dates(uuid, starts_at, ends_at, max_assignments, grace_period_days)
```

Errors are recorded as subscription order notes. Success is also noted.

---

### Effect on Renewal Type (When Changing Dates)

`update_membership_entity_record()` also accepts a `renewal_type` parameter. If the renewal type is changed at the same time as the dates:

| Renewal Type | What Is Stored |
|---|---|
| `subscription` | Clears `membership_next_tier_id`; sets `membership_next_tier_subscription_renewal = 1` |
| `sequential_logic` | Sets `membership_next_tier_id` from submitted data |
| `current_tier` | Sets `membership_next_tier_id` to the current tier's post ID |
| `inherited` | Loads renewal configuration from the tier config |

---

### Full Cascade: Manual Date Change

```
Admin edits dates on membership record
        │
        ▼
update_membership_entity_record() [Admin_Controller.php]
        │
        ├─► Validate: start < end ≤ expiry
        ├─► Normalize dates to MDP timezone
        ├─► Recalculate: early_renew_at, grace_period_days
        │
        ├─► update_local_membership_record() ──► WordPress post meta updated
        │                                        User meta JSON updated
        │
        ├─► update_mdp_record() ──────────────► Wicket MDP API synced
        │
        ├─► update_membership_subscription() ──► WooCommerce subscription
        │                                         end date updated
        │                                         next_payment updated (if applicable)
        │
        └─► amend_membership_json() ──────────► User meta, order meta, subscription
                                                 meta JSON all updated
```

> **Note:** `scheduler_dates_for_expiry()` is **not** called during a manual date edit. Lifecycle event scheduling only occurs when a new membership is created or when a PENDING membership is activated. If you change dates on an active membership, you should be aware that previously scheduled background events (early renewal, ends, expiry) will still fire at their original times unless you cancel and reschedule them manually.

---

## Part 2: Status Changes

### Allowed Status Transitions

Not all status changes are valid. `get_admin_status_options()` enforces the permitted transitions:

| Current Status | Can Change To |
|---|---|
| **Pending** | Active |
| **Active** | Cancelled, Expired |
| **Grace** | Cancelled, Expired |
| **Delayed** | Cancelled |
| **Cancelled** | _(no transitions allowed)_ |
| **Expired** | _(no transitions allowed)_ |

---

### How Each Transition Affects Dates

All status changes go through `admin_manage_status()` in `Admin_Controller.php`. The date effects depend on which transition occurs.

---

#### PENDING → ACTIVE

This is the most complete transition. Dates are fully recalculated from the membership tier configuration via `Membership_Config::get_membership_dates()`.

**Dates set:**

| Field | Value |
|---|---|
| `membership_starts_at` | Calculated start date from tier config |
| `membership_ends_at` | Calculated end date from tier config |
| `membership_expires_at` | Calculated expiry (or `end_date` if empty) |
| `membership_early_renew_at` | Calculated early renew date (or `end_date` if empty) |

The calculated dates respect the cycle type (calendar vs. anniversary), alignment settings, and the grace period window configured on the membership tier. See [member-lifecycle-dates.md](../../workflow-index/member-lifecycle-dates.md) for how these are determined.

**After dates are set:**
- `scheduler_dates_for_expiry()` is called — schedules background events for early renewal, end date, and expiry date
- Subscription status is set to **active**
- `update_membership_subscription()` is called with the new dates
- `update_mdp_record()` syncs to Wicket MDP
- `wicket_membership_created_mdp` action is fired

---

#### ACTIVE / GRACE → CANCELLED

Cancellation immediately truncates the membership.

**Dates set (varies by current status):**

| Current Status | `membership_ends_at` | `membership_expires_at` |
|---|---|---|
| **Pending or Delayed** | Set to NOW | Set to NOW |
| **Grace** | _(not changed)_ | Set to NOW |
| **Active (standard)** | Set to NOW | _(not changed — existing expiry stands)_ |

Additionally:
- `membership_grace_period_days` is set to `0`
- Associated WooCommerce subscription is cancelled
- MDP record is updated

> **Practical effect:** Cancelling an active membership cuts off the end date immediately. If the member was in a grace period, the expiry is moved to now (immediate loss of access). If the member had grace period days remaining on an active membership, those days are wiped.

---

#### ACTIVE → EXPIRED (Manual)

Forcing a membership to expired sets both the end and expiry dates to **tomorrow** (the next day at midnight), giving a short buffer before the system processes it as truly expired.

**Dates set:**

| Field | Value |
|---|---|
| `membership_ends_at` | Tomorrow (start of day) |
| `membership_expires_at` | Tomorrow (start of day) |

- Associated WooCommerce subscription is cancelled
- MDP record is updated

---

### Full Cascade: Status Change

```
Admin changes status on membership record
        │
        ▼
admin_manage_status() [Admin_Controller.php]
        │
        ├─► Validate status transition is allowed
        ├─► Recalculate dates (rules depend on transition — see above)
        │
        ├─► update_local_membership_record() ──► WordPress post meta updated
        │
        ├─► update_mdp_record() ──────────────► Wicket MDP API synced
        │
        ├─► update_membership_subscription() ──► WooCommerce subscription
        │   (on PENDING → ACTIVE)                end/next_payment updated
        │
        ├─► scheduler_dates_for_expiry() ──────► Background events scheduled
        │   (on PENDING → ACTIVE only)           (early_renew, ends_at, expires_at)
        │
        ├─► amend_membership_json() ──────────► All JSON meta copies updated
        │
        └─► do_action('wicket_membership_created_mdp')
            (on PENDING → ACTIVE only)
```

---

## Part 3: Automated Date-Based Status Transitions

Beyond manual changes, the system automatically moves memberships through statuses based on their scheduled dates. These transitions are handled in `Membership_Controller.php`.

### Scheduled Background Events

When a membership becomes active (`scheduler_dates_for_expiry()`), three future events are registered via Action Scheduler:

| Event | Trigger Date | Hook | What Happens |
|---|---|---|---|
| Renewal window opens | `membership_early_renew_at` | `add_membership_early_renew_at` | Fires `wicket_memberships_renewal_period_open` action |
| Membership ends | `membership_ends_at` | `add_membership_ends_at` | Moves status to **Grace** (or Expired if no grace period); fires `wicket_memberships_end_date_reached` |
| Grace period expires | `membership_expires_at` | `add_membership_expires_at` | Moves status to **Expired**; fires `wicket_memberships_grace_period_expired` |

### Daily Cron Fallback

Two daily cron hooks act as a safety net in case scheduled events are missed:

- `daily_membership_grace_period_hook()` — Moves memberships to Grace whose end date was yesterday
- `daily_membership_expiry_hook()` — Expires memberships whose expiration date was yesterday

---

## Part 4: How Manual Changes Interact with the Grace Period

The grace period is always derived from the gap between End Date and Expiration Date. When you manually edit either of these dates, the stored `membership_grace_period_days` value is automatically recalculated.

**Example: Extending grace period manually**

| | Before | After Admin Edit |
|---|---|---|
| End Date | Dec 31, 2025 | Dec 31, 2025 _(unchanged)_ |
| Expiration Date | Jan 7, 2026 | Jan 21, 2026 |
| Grace Period Days | 7 | 21 |

The extended expiry is synced to MDP and to the WooCommerce subscription end date (for non-monthly renewals). The member retains access until the new Expiration Date.

**Example: Reducing end date only**

| | Before | After Admin Edit |
|---|---|---|
| End Date | Dec 31, 2025 | Nov 30, 2025 |
| Expiration Date | Jan 7, 2026 | Jan 7, 2026 _(unchanged)_ |
| Grace Period Days | 7 | 38 |

Changing only the End Date while leaving Expiration Date intact increases the effective grace period. The member's last day of "active" status moves earlier, but their access continues until the original expiry.

See [grace-period-workflow.md](../../workflow-index/grace-period-workflow.md) for more on how grace periods work in general.

---

## Part 5: Behavior by Renewal Type

The renewal type on the membership record affects how `update_membership_subscription()` behaves when dates are changed.

| Renewal Type | Subscription End Date Uses | Next Payment Updated? |
|---|---|---|
| **Subscription (monthly)** | `membership_ends_at` | No |
| **Subscription (non-monthly)** | `membership_expires_at` | Yes (set to `membership_ends_at`) |
| **Current Tier / Sequential / Form Flow** | `membership_expires_at` | Yes (set to `membership_ends_at`) |

> **Why does subscription renewal type use `membership_ends_at` for monthly?** Monthly subscriptions are expected to renew continuously; the subscription end date is kept aligned with the membership end date so the subscription closes if the member stops paying. For annual or multi-period memberships, the subscription end date is set to the expiration date so the member retains access through the grace period even if auto-renewal is off.

See [renewal-type-workflows.md](../../workflow-index/renewal-type-workflows.md) for full renewal type documentation.

---

## Summary: What Changes When

| Action | Dates Changed | Subscription Updated | MDP Synced | Events Rescheduled |
|---|---|---|---|---|
| Manual date edit | Start, End, Expiry (as entered) | Yes — end & next_payment | Yes | No |
| PENDING → ACTIVE | All dates recalculated from tier config | Yes | Yes | Yes |
| ACTIVE → CANCELLED | `ends_at` = NOW | Subscription cancelled | Yes | No |
| GRACE → CANCELLED | `expires_at` = NOW | Subscription cancelled | Yes | No |
| ACTIVE → EXPIRED | Both = tomorrow | Subscription cancelled | Yes | No |

---

## Related Documentation

- [member-lifecycle-dates.md](../../workflow-index/member-lifecycle-dates.md) — How Calendar vs Anniversary cycles determine initial dates
- [grace-period-workflow.md](../../workflow-index/grace-period-workflow.md) — Grace period configuration and member experience
- [renewal-type-workflows.md](../../workflow-index/renewal-type-workflows.md) — Renewal type options and their behavior
- [Admin_Controller.md](../../class-index/Admin_Controller.md) — Admin_Controller method reference
- [Membership_Controller.md](../../class-index/Membership_Controller.md) — Membership_Controller method reference
