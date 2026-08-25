---
title: "Monthly Autopay Form Flow — Hidden Renewal Callout & Orphaned Subscription"
audience: [developer]
ticket: ""
branch: feature/users-multi-tier-renewal-subscriptions-merged
---

# Monthly Autopay Form Flow — Hidden Renewal Callout & Orphaned Subscription

Two coupled repairs for members on a **monthly autopay subscription** whose tier renews via
**Renewal Form Flow** (`renewal_type = form_flow`):

1. The Account Centre renewal callout never appeared, so there was no route into the form.
2. When such a member did renew, the old subscription kept billing monthly past the term it was
   paying for.

## Specified behaviour — accepted 2026-08-25

**The early-renewal behaviour below is signed off as complete and correct. Treat it as the
specification, not as a proposal.** Anything that changes these three statements is a new decision
needing its own sign-off, not a refinement of this repair.

1. **Who sees the callout.** A membership whose tier renews via `form_flow`, on a subscription
   billing monthly (`period == 'month'` and `interval == 1`) with autopay on, shows the renewal
   callout from the start of the early renewal window — the same as a manual-renewal member on that
   tier already did. Autopay no longer hides it.
2. **Why that is correct, not a leak.** Autopay cannot complete a form, so no automatic charge was
   ever going to carry out this renewal. Suppressing the callout removed the member's only route to
   it. This reasoning does **not** extend to `subscription` or `current_tier` tiers, where the
   automatic charge does perform the renewal and the suppression stays.
3. **What is untouched.** Every other `renewal_type`, every other billing period, manual-renewal
   members, the grace period, and the whole term outside the early renewal window all behave exactly
   as they did before. The change performs no writes.

The accompanying subscription clamp — the second of the two repairs named at the top — follows from
this: once the member can renew
early, the superseded subscription must stop with the term it was paying for. Its rule — end date
moved to the previous membership's `ends_at`, next payment wiped when it would fall on or after that
— is accepted on the same basis.

## Cause

### 1. The callout was suppressed for the whole renewal window

Two independent autopay checks in `Membership_Controller::get_membership_callouts()` hide the
callout, and both fired:

- **The global skip** (`Membership_Controller.php:2062`) `continue`s past the membership entirely
  when autopay is on, `next_payment` is non-empty, the subscription is in a live status, and
  `current_time('timestamp') < $next_payment_date`.
- **The early-renewal check** (`Membership_Controller.php:2184`) independently sets
  `$is_autopay_enabled = false` only when `next_payment` is missing, past, or the subscription is
  not live — so on a healthy subscription the `early_renewal` entry is never added at line 2196.

For a monthly subscription the plugin does not manage `next_payment` at all
(`Membership_Controller.php:966` excludes `period == 'month' && interval == 1` from the write), so
WooCommerce's own monthly schedule stands and `next_payment` is *always* a month or less in the
future. The first condition therefore held continuously, and the callout was hidden for the entire
renewal window rather than for a payment cycle.

The suppression is correct for `renewal_type = subscription` (autopay genuinely performs the
renewal) and for `current_tier` (the automatic charge renews the same product). It is wrong for
`form_flow`: autopay cannot complete a form, so no automatic charge can produce that renewal, and
hiding the callout removed the only route to it.

### 2. The old subscription outlived the term it was paying for

A Form Flow renewal is an ordinary cart purchase, so it creates a **new** subscription. The old one
was left untouched:

- For this flow `update_membership_subscription()` writes the subscription's `end` from
  `membership_expires_at`, not `membership_ends_at` (`Membership_Controller.php:959-963`), so the old
  subscription survives the whole grace window.
- `next_payment` keeps rolling monthly (the line 966 exclusion again), so it keeps charging.
- Nothing cancels it. `catch_expire_current_membership()` → `update_membership_status()`
  (`Membership_Controller.php:162-186`) only writes post meta; the sole subscription-cancelling code
  in the plugin is `Admin_Controller::admin_manage_status()` (`Admin_Controller.php:240`, `:270`).

So a member who renewed paid both subscriptions until the old one reached `membership_expires_at`.

## Effect

| Entry point | Symptom |
|---|---|
| Account Centre, renewal callout block | No card at any point in the renewal window for monthly autopay Form Flow memberships. The member has no link to the renewal form. |
| WooCommerce billing, after a Form Flow renewal | Old monthly subscription continues charging alongside the new one until `membership_expires_at`, then expires on its own. |

The two compound: the fix for the first makes the second reachable *earlier* — a member acting at
the start of the window would have overlapped by the full renewal window plus the grace period.

## The repair

### Callout — two matched conditions

A single flag, computed once per membership immediately before the global skip
(`Membership_Controller.php:2044-2047`):

```php
$allow_form_flow_early_callout =
     ( $current_time >= $membership_early_renew_at && $current_time < $membership_ends_at )
  && empty( $next_tier_subscription_renewal )
  && ! empty( $next_tier_form_page_id );
```

Consumed at both autopay checks, in each case combined with the monthly test:

- `Membership_Controller.php:2060-2062` — negated into the skip condition, so the membership is no
  longer `continue`d.
- `Membership_Controller.php:2187-2193` — sets `$is_autopay_enabled = false`, reusing the existing
  mechanism so the emit condition at line 2196 is untouched.

Both had to change: relaxing only the skip leaves the early-renewal check suppressing the entry, and
nothing appears.

The monthly test (`get_billing_period() == 'month' && get_billing_interval() == 1`) is applied at
each use site rather than in the flag, so the subscription read stays inside the
`!empty($membership_data['meta']['membership_subscription_id'])` / `!empty($sub)` guards. `$sub` is
not reset per loop iteration, and evaluating it outside those guards would let one membership's
subscription decide another's visibility.

The flow test reads the **previous membership record's** meta (`membership_next_tier_form_page_id`,
`membership_next_tier_subscription_renewal`), not the tier's live `renewal_type`. The destination
resolution further down uses the same meta, so the gate and the resulting button can never disagree
about which flow the member is in.

`! empty( $next_tier_form_page_id )` is load-bearing, not a redundancy alongside the
`empty( $next_tier_subscription_renewal )` clause. With neither destination set, execution falls to
the `else` at `Membership_Controller.php:2145`, `new Membership_Tier('')` yields empty `tier_data`,
and the ACC link chain (`init.php:285-296`) matches no branch — producing a card with no button.

### Subscription — terminate with the term

New `Subscription_Manager::terminate_at_membership_end($sub, $end_ts)`
(`Subscription_Manager.php:120`): clamps `end` to the membership's end date and deletes
`next_payment` when it falls on or after it. Called from
`Membership_Controller::terminate_previous_form_flow_subscription()` (line 713), invoked from
`scheduler_dates_for_expiry()` (line 669-671) once, after both the Action Scheduler and `wp_cron`
branches, so scheduler availability does not change the outcome.

Chosen over the alternatives:

- **Not `prepare_dates()`.** That guard *preserves* a colliding `next_payment` by nudging it an hour
  before `end` — here that would take one more monthly payment the member no longer owes. This case
  wants it removed, which is the opposite behaviour, so it is a separate method rather than a flag on
  the existing one.
- **Not cancelling the subscription.** Setting `end` lets the remaining paid-for months run and
  leaves WooCommerce to expire it on schedule. Cancelling would stop mid-term billing the member has
  already committed to and lose the audit trail of a natural expiry.
- **`next_payment => 0` in the same `update_dates()` call.** WooCommerce routes a `0` date to
  `$delete_date_types` and removes it from the `end > next_payment` comparison
  (`WC_Subscription::prepare_dates_for_update()`, lines 2903-2910), so one call is safe and atomic.

Guards inside the clamp:

| Guard | Reason |
|---|---|
| `can_date_be_updated('end')` | WooCommerce refuses date changes on ended statuses; logged and skipped rather than thrown. |
| `$end_ts >= $current_end_ts` → skip | Only ever shorten. Never extends a subscription's life. |
| `$end_ts <= $last_order_date_created` → skip | WooCommerce throws when `end` precedes the last payment. Reached by renewing late enough that a monthly charge already landed past the term end. |
| `try`/`catch` around `update_dates()` | It throws; `get_membership_callouts()` and this path have no other handler. |

## Blast radius of the repair

**Callout change.** Scoped by four conjunctions — inside the early-renewal window, Form Flow
destination, no subscription-renewal flag, monthly interval-1 billing. Verified unaffected:

- **The subscription-mutating block is never entered.** `update_status('on-hold')`,
  `wcs_create_renewal_order()` and `wcs_get_early_renewal_url()` are all inside
  `if (!empty($next_tier_subscription_renewal))` (`Membership_Controller.php:2073`), which the flag
  requires to be empty. Newly-visible memberships take the form-page branch at line 2131, which only
  calls `get_the_title()` and `get_permalink()` — **this change performs no writes.**
- Outside the early-renewal window the global skip still `continue`s exactly as before, so nothing
  changes for the rest of the term.
- The grace period is untouched: the global skip never fires past `membership_ends_at` regardless.
- Untouched: `renewal_type` `subscription` / `current_tier` / `sequential_logic` / `inherited`,
  non-monthly billing, manual-renewal members, `pending_approval`, `membership_exists`, the `debug`
  bucket, and `WICKET_MSHIP_DISABLE_RENEWALS`.

**Behavioural changes that are intended but new:**

- **Overlapping billing is now reachable earlier.** The clamp bounds it: the old subscription now
  ends at `membership_ends_at` instead of `membership_expires_at`, so the overlap is the gap between
  the renewal purchase and the term end, and the grace-period months are no longer billed at all.
- **The old subscription's stored `end` moves earlier** for this population — visible in the
  WooCommerce admin and in any report reading subscription end dates.
- **Members who renew late may see the clamp skipped.** If a monthly charge landed after
  `membership_ends_at`, the last-payment guard declines and the subscription keeps its original
  `end`, logged via `wc_log_mship_error`. Pre-fix behaviour for that member.

**Pre-existing exposure reaching a new population:** `wicket_ac_memberships_get_product_link_data()`
calls `$product->get_name()` without checking the lookup succeeded (`legacy.php:481-485`). Form Flow
memberships use `wicket_ac_memberships_get_page_link_data()` instead, so this change does **not**
route anyone into it — noted only because it is the adjacent hazard in the same render path.

**Not addressed, unchanged:** there is still no server-side enforcement of the renewal window.
`validate_renewal_order_items()` is not hooked (`wicket.php:224`) and the order-processing check's
enforcement is commented out (`Membership_Controller.php:338-344`), so callout visibility remains the
only gate on when a renewal can be purchased. This change widens that gate for one population
without restoring the guard behind it.

## Verification performed

- `php -l` on both modified files.
- Traced both autopay checks by hand for the four states of `next_payment` (future / empty / past /
  equal) against every `renewal_type`, confirming only monthly Form Flow inside the window changes.
- Confirmed the subscription-mutating block is unreachable for this flow by following
  `renewal_type = form_flow` through `Admin_Controller.php:691-695` to
  `membership_next_tier_subscription_renewal = 0`.
- Read WooCommerce's `prepare_dates_for_update()` to confirm `next_payment => 0` is treated as a
  delete and excluded from the `end > next_payment` comparison, and `can_be_updated_to()` /
  `can_date_be_updated()` for the status preconditions.

**Reviewed and accepted** (2026-08-25): the specified early-renewal behaviour, restated at the top
of this doc, was confirmed complete and correct on review. That sign-off covers the intended
behaviour and its scope, not a runtime execution of it.

**Not performed:** no automated test run — the plugin checkout has no `vendor/` or `tests/`
directory, so PHPUnit is unavailable here. No live verification against a real monthly autopay Form
Flow membership. Both should still happen before merge; the sign-off above does not substitute for
them.

Suggested manual check: a monthly autopay membership on a `form_flow` tier, `ends_at` set inside the
renewal window. Expect the card at the start of the window with the form page as its destination.
Complete the renewal and confirm the old subscription shows `end` = the old `ends_at`, a next payment
date either before that or removed, and the order note recording which.

## Known remaining exposure

- **Same root cause, other flows.** `sequential_logic` still hides the callout for monthly autopay
  members while the automatic charge renews them at the *current* tier, silently ignoring the tier
  progression. Deliberately out of scope here.
- **`Helper::has_next_payment_date()` (`Helper.php:570`)** returns `true` for any autopay
  subscription regardless of `renewal_type`, arming a `next_payment` at `membership_ends_at` on
  flows autopay cannot execute. Harmless for monthly (the line 966 exclusion discards it), live for
  every other billing period.
- **Only the previous membership's subscription is clamped.** A member holding several stale
  subscriptions from earlier renewals keeps them; this acts on `previous_membership_post_id` only.

## See Also

- [Subscription_Manager](../engineering/Class-Subscription_Manager.md) — the clamp's home and the
  `prepare_dates()` guard it deliberately bypasses
- [Callout Display Conditions](../local/callout-display-conditions.md) — every condition governing
  the renewal callout, both periods
