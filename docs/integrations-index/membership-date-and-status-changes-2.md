# Membership Date & Status Changes — Admin Reference

How to understand what changes when you edit membership dates or change a membership status, and what effects those changes have on WooCommerce subscriptions and the Wicket MDP platform.

---

## The Four Date Fields on a Membership Record

Every membership record has four date fields that work together as a timeline:

| Field | Plain-Language Meaning |
|---|---|
| **Start Date** | The day the membership becomes active and the member gets access |
| **End Date** | The official end of the membership period — the member enters grace (if any) after this |
| **Expiration Date** | The day access is fully lost — End Date plus any grace period days |
| **Early Renewal Date** | The day the renewal window opens and the member sees renewal callouts |

These fields always follow this order:

```
Start Date  <  End Date  ≤  Expiration Date
```

The Early Renewal Date sits between Start and End Date, calculated automatically as End Date minus the renewal window days set on the tier.

Two fields — **Early Renewal Date** and **Grace Period Days** — are never entered manually. They are always calculated from the dates you do enter. See the relevant sections below for when and how they are recalculated.

---

## Part 1: Manual Date Changes

### What You Can Edit

From the membership record in **Memberships → Individual Member Management** (or the Organisation equivalent), an administrator can directly edit:

- **Start Date**
- **End Date**
- **Expiration Date**

### Validation Rules

The plugin enforces the date ordering rule before saving:

- Start Date must be earlier than End Date
- End Date must be on or before Expiration Date

If these conditions are not met, the edit is rejected and nothing is saved.

### What Is Automatically Recalculated

When valid dates are submitted, two fields are immediately recalculated and stored — you do not control these directly:

| Field | How It Is Calculated |
|---|---|
| **Early Renewal Date** | End Date minus the **Renewal Window Days** setting on the membership tier |
| **Grace Period Days** | Expiration Date minus End Date (in whole days) |

**Example:** If End Date is December 31 and the tier has a 30-day renewal window, Early Renewal Date is automatically set to December 1. If Expiration Date is January 14, Grace Period Days is set to 14.

---

### Effect on WooCommerce Subscription Dates

After saving, the plugin immediately updates the WooCommerce subscription linked to this membership. The specific subscription fields that change depend on the membership's **renewal type** and billing frequency:

| Renewal Type / Billing | Subscription End Date Set To | Next Payment Date Set To |
|---|---|---|
| Subscription renewal — **monthly** billing | Membership **End Date** | Not changed |
| Subscription renewal — **non-monthly** (e.g. annual) | Membership **Expiration Date** | Membership **End Date** |
| Current Tier, Sequential, or Form Flow renewal | Membership **Expiration Date** | Membership **End Date** |

**Why the difference for monthly vs. annual?**

For monthly subscriptions the subscription end date tracks the membership end date directly — if the member stops paying, the subscription and membership both close at the same time. For annual memberships the subscription end date is pushed out to the expiration date, keeping the subscription alive through the full grace period so the member retains access even if auto-renewal is off.

An order note is added to the subscription confirming the date change, for example:

```
Membership #1234 changed these subscription dates.
Next Payment Date: 2025-12-31
End Date: 2026-01-14
```

**Clearing the next payment date:** For subscription-type renewals where the next payment should be wiped, this is handled by a background job that runs approximately 90 seconds after saving. This delay is necessary because WooCommerce Subscriptions resets certain date fields when a subscription status changes to active — the background job re-applies the correct value after WooCommerce has finished its own update.

---

### Effect on Wicket MDP

After the subscription update, the plugin syncs the updated dates to the external Wicket MDP platform. The fields sent are:

- Start Date
- End Date
- Grace Period Days

For individual memberships and organisation memberships, the MDP record is updated via the Wicket API. If the sync fails, an error note is added to the subscription. If it succeeds, a success note is also recorded.

---

### What Is NOT Updated on a Manual Date Edit

**Background event schedule is not changed.** When a membership is first activated, three future events are scheduled — one for when the renewal window opens, one for when the End Date is reached, and one for when the Expiration Date is reached. Editing dates on an active membership does **not** reschedule these events. They will still fire on the original dates.

This means: if you move the End Date earlier or later, the background event that moves the membership to Grace status will still run at the originally scheduled time unless action is taken separately to cancel and reschedule it. Daily cron fallbacks exist as a safety net, but there can be a window where the status has not yet caught up to the edited dates.

---

### Summary: Manual Date Change Effects

| What Changes | Where |
|---|---|
| Start Date, End Date, Expiration Date | WordPress membership post meta |
| Early Renewal Date (recalculated) | WordPress membership post meta |
| Grace Period Days (recalculated) | WordPress membership post meta |
| Subscription end date | WooCommerce Subscription |
| Subscription next payment date (where applicable) | WooCommerce Subscription |
| Start Date, End Date, Grace Period Days | Wicket MDP record |
| Membership JSON on order / subscription meta | WordPress order + subscription meta |
| Background events for renewal / end / expiry | **Not changed** |

---

## Part 2: Status Changes

### Allowed Transitions

Not every status change is permitted. The plugin enforces a one-way lifecycle:

| Current Status | Can Be Changed To |
|---|---|
| **Pending** | Active |
| **Active** | Cancelled, Expired |
| **Grace** | Cancelled, Expired |
| **Delayed** | Cancelled |
| **Cancelled** | No further changes allowed |
| **Expired** | No further changes allowed |

Attempting a transition that is not in this list will be blocked.

---

### Pending → Active

This is the most complete transition and has the most effects. When a Pending membership is activated:

**Dates are fully recalculated from the tier configuration** — the Start, End, Expiration, and Early Renewal dates are all set fresh using the tier's cycle type (Calendar vs. Anniversary), alignment settings, and grace period. Any dates that were stored while the record was Pending are overwritten.

What happens in sequence:

1. All four date fields are recalculated and saved
2. Grace Period Days is recalculated from the new dates
3. Background lifecycle events are scheduled for the renewal window, End Date, and Expiration Date
4. The WooCommerce subscription moves from **On-Hold** to **Active**
5. Subscription end and next payment dates are updated to match the new membership dates
6. The Wicket MDP record is created or updated with the new dates
7. A `wicket_membership_created_mdp` event fires, which can trigger downstream integrations

> **Practical note:** If a membership was approved outside the plugin (e.g., directly in the Wicket MDP admin), the WordPress and WooCommerce records remain in their Pending/On-Hold state. The callout in the Account Centre will continue to show "pending" until an admin uses the plugin's **Manage Status → Active** action to run this full transition.

---

### Active or Grace → Cancelled

Cancellation cuts off the membership immediately. The exact date changes depend on the current status:

| Current Status | End Date (`membership_ends_at`) | Expiration Date (`membership_expires_at`) |
|---|---|---|
| **Active** | Set to right now | Not changed — existing expiry stands |
| **Grace** | Not changed | Set to right now |
| **Pending or Delayed** | Set to right now | Set to right now |

In all cases:
- **Grace Period Days is set to 0**
- The linked WooCommerce subscription is **cancelled**
- The Wicket MDP record is updated

**What this means for the member:**

- Cancelling an **Active** membership ends the membership period now. If the tier had grace period days, the member would normally have had some access past the End Date — but the Grace Period Days field is zeroed out, so from the system's perspective there is no grace remaining. The Expiration Date is still in the future (unchanged), so technically the member's access window extends to that date, but they will not be renewed.
- Cancelling a **Grace** membership terminates access immediately. The expiry is moved to now, and the member loses access on the next page load.

---

### Active → Expired (Manual)

An administrator can force a membership to Expired status. When this happens:

| Field | Value Set |
|---|---|
| **End Date** | Tomorrow (start of day) |
| **Expiration Date** | Tomorrow (start of day) |

Setting both to tomorrow gives a short buffer before the record is fully treated as expired by any background processes. The WooCommerce subscription is **cancelled**, and the Wicket MDP record is updated.

> This action is different from the membership reaching its Expiration Date naturally. Natural expiry is handled by a scheduled background event (or the daily cron fallback). Using **Manage Status → Expired** is an admin override that forces the record to close now with a one-day buffer.

---

### Summary: Status Change Effects

| Transition | Dates Changed | Subscription | MDP Synced | Background Events Scheduled |
|---|---|---|---|---|
| **Pending → Active** | All dates recalculated from tier config | Set to Active; dates updated | Yes — record created/updated | Yes — renewal window, end, expiry all scheduled |
| **Active → Cancelled** | `ends_at` = now; grace period = 0 | Cancelled | Yes | No |
| **Grace → Cancelled** | `expires_at` = now; grace period = 0 | Cancelled | Yes | No |
| **Active → Expired** | `ends_at` and `expires_at` = tomorrow | Cancelled | Yes | No |
| **Pending/Delayed → Cancelled** | Both = now; grace period = 0 | Cancelled | Yes | No |

---

## Part 3: Interaction Between Date Edits and Grace Period

The grace period is not a setting you configure per-membership record — it is always derived from the gap between End Date and Expiration Date on that record. Any time those dates change, grace period days is recalculated automatically.

**Extending the grace period:** Move the Expiration Date further out while leaving End Date unchanged.

| | Before | After Edit |
|---|---|---|
| End Date | Dec 31 | Dec 31 (unchanged) |
| Expiration Date | Jan 7 | Jan 21 |
| Grace Period Days | 7 | 21 |

**Accidentally widening the grace period:** Move End Date earlier while leaving Expiration Date unchanged.

| | Before | After Edit |
|---|---|---|
| End Date | Dec 31 | Nov 30 |
| Expiration Date | Jan 7 | Jan 7 (unchanged) |
| Grace Period Days | 7 | 38 |

If you only intend to shorten the membership period, make sure to also bring the Expiration Date in by the same amount — otherwise the effective grace window grows unexpectedly.

See [grace-period-workflow.md](../../workflow-index/grace-period-workflow.md) for how grace period days affect the member's experience in the Account Centre.

---

## Common Scenarios

### "I need to extend a member's membership by one month"

Edit the **End Date** and **Expiration Date** on the membership record, adding one month to each. The Early Renewal Date and Grace Period Days will recalculate automatically. The WooCommerce subscription end date and the Wicket MDP record will both update immediately. Keep in mind that background events (the scheduled jobs that fire at the original End and Expiry dates) are not rescheduled — the daily cron jobs will catch up on the correct status, but there may be a short window of inconsistency around the original scheduled dates.

### "I need to cancel a member immediately"

Use **Manage Status → Cancelled**. This sets the End Date to now, zeroes out the grace period, cancels the WooCommerce subscription, and updates the MDP record in a single action. Do not manually edit the dates to today's date as a way of cancelling — use the status change, which also handles the subscription and MDP sync.

### "A member's subscription renewal didn't go through and I want to mark them expired"

Use **Manage Status → Expired**. This sets both End and Expiration Date to tomorrow, cancels the WooCommerce subscription, and syncs to MDP. The member retains access until end of tomorrow, after which the record is treated as expired.

### "I approved a membership in the Wicket MDP portal directly and the member still sees a pending callout"

The MDP record is active but the WordPress membership record and WooCommerce subscription are still in their Pending/On-Hold state. Go to **Memberships → Individual Member Management**, find the member's Pending record, and use **Manage Status → Active**. This runs the full activation workflow — recalculates dates, activates the subscription, and syncs everything. The callout in the Account Centre will clear on the member's next page load.

---

## Related Documentation

- [membership-date-and-status-changes.md](../../integrations-index/membership-date-and-status-changes.md) — Technical reference for the same behaviour, including code flow diagrams
- [member-lifecycle-dates.md](../../workflow-index/member-lifecycle-dates.md) — How Calendar vs. Anniversary cycle types determine initial dates on activation
- [grace-period-workflow.md](../../workflow-index/grace-period-workflow.md) — Grace period configuration and the member-facing experience
- [renewal-type-workflows.md](../../workflow-index/renewal-type-workflows.md) — Renewal type options and how they affect subscription behaviour
- [individual-member-management.md](../../options-index/individual-member-management.md) — Admin UI reference for managing individual membership records
