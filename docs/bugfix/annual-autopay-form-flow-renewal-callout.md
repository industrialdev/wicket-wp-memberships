---
title: "Annual Autopay Form Flow — Callout Still Hidden & Term-End Auto Payment"
audience: [developer]
ticket: ""
branch: bugfix/autorenew-hiding-early-renewal-callout
---

# Annual Autopay Form Flow — Callout Still Hidden & Term-End Auto Payment

Follow-on repair to
[Monthly Autopay Form Flow](monthly-autopay-form-flow-renewal-callout.md) (commit `d1907bd`), which
stays the record for that fix. That one reached monthly subscriptions only. This one covers the
remaining, worse case:

1. On an **annual** (or any non-monthly) autopay subscription the callout was still hidden for the
   whole renewal window.
2. Those subscriptions carry an armed term-end payment, which fires, generates a renewal order, and
   auto-creates a membership with the renewal form bypassed entirely.

Net change is a reduction: **48 lines added, 103 removed.**

## Cause

### 1. The exception carried a monthly billing test, so annual never qualified

The monthly repair computed a flag and then, at each of the two autopay gates, ANDed it with a
billing-period test. An annual subscription fails that test, so the global skip fired exactly as
before and the membership was `continue`d before any window evaluation.

Confirmed live on membership 359254 (annual product, annual term, autopay on) from the ACC debug
output:

```
<!--SKIPPING for Auto-Renew: membership_id:359254|-1787688929//-->
```

That message is only emitted by the global skip. (The number after the pipe is meaningless — the
existing debug echo applies `strtotime()` to a value that is already a timestamp, so it prints
`-current_time('timestamp')`.)

The billing period was never the reason autopay could not serve this renewal. **The form is.** A form
has to be completed by a person whatever the billing period, so scoping the exception by period
excluded memberships for exactly the same reason it included others.

### 2. Non-monthly subscriptions have an armed term-end payment, and it is honoured

Unlike monthly, the plugin *writes* `next_payment` for these subscriptions:

- `Helper::has_next_payment_date()` (`Helper.php:570`) returns `true` for **any** autopay
  subscription — `!empty($membership['membership_next_tier_subscription_renewal']) || $is_autopay_enabled` —
  regardless of `renewal_type`.
- `Membership_Controller::update_membership_subscription()` line 965 then writes it as
  `get_mdp_day_end($end_date)`, the membership's `ends_at`. Only monthly interval-1 is excluded from
  that write.

So a term-end charge is scheduled. When it fires, the renewal order flows into
`get_memberships_data_from_subscription_products()`, and the guard that would skip it
(`Membership_Controller.php:288`) only matches **monthly** interval-1 subscriptions. An annual
renewal order is therefore processed: `get_tier_by_product_id()` resolves the subscription's current
product, and a new membership is created at the current tier with no form data captured.

That is the substantive difference from monthly. On monthly the hidden callout was only a missing
route; here the member is silently auto-renewed down a path the tier was configured to avoid.

### 3. The date comparison in the subscription action was coupled to the collision guard

The monthly repair removed `next_payment` only when it fell on or after the membership's `ends_at`.
For an annual subscription with a grace period that comparison happens to match — `next_payment` and
the target are both `ends_at` — but with **no grace period configured** it does not: `expires_at`
equals `ends_at`, so `Subscription_Manager::prepare_dates()` had already nudged `next_payment` an hour
earlier to satisfy WooCommerce's `end > next_payment` rule. The comparison then reads it as "before
the term end, still owed" and preserves the very charge that needs stopping.

A rule whose correctness depends on whether another guard moved the date by an hour is the wrong rule.

## Effect

| Entry point | Symptom |
|---|---|
| Account Centre, renewal callout block | No card at any point in the renewal window for non-monthly autopay Form Flow memberships, even after the monthly repair. |
| WooCommerce, at the term end | Scheduled payment taken on a subscription whose membership renews by form. |
| Membership records | A renewal order auto-creates a membership at the *current* tier with the form bypassed — no form data, no tier progression decision. |
| WooCommerce, zero-grace tiers | Even where the subscription action ran, the nudged `next_payment` an hour before the term end survived it. |

## The repair

Two behavioural edits and a set of removals. Every changed line is accounted for below.

### How it makes the callout appear for annual

Deleting the billing-period test at the flag's two use sites. The flag itself
(`Membership_Controller.php:2032-2035`) is unchanged from the monthly repair:

```php
$allow_form_flow_early_callout =
     ( $current_time >= $membership_early_renew_at && $current_time < $membership_ends_at )
  && empty( $next_tier_subscription_renewal )
  && ! empty( $next_tier_form_page_id );
```

- **Gate 3** (`Membership_Controller.php:2045`) — the trailing condition becomes
  `&& ! $allow_form_flow_early_callout`, replacing `&& ! $is_monthly_form_flow_exception`. The
  membership is no longer `continue`d, so it reaches the window tests.
- **Gate 8** (`Membership_Controller.php:2172`) — `if ( $allow_form_flow_early_callout )` replaces the
  two-line condition that also tested billing period. Without this the entry is still suppressed at
  the emit on line 2176; the two gates are independent, not layered.

Both had to change, and both are now period-agnostic. A side effect worth having: the flag no longer
reads `$sub` at all, so it cannot be influenced by the `$sub` value a previous membership left behind
in the same loop.

### How it prevents the renewal order and the auto payment

`Subscription_Manager::drop_superseded_next_payment()` (`Subscription_Manager.php:117`) removes the
subscription's `next_payment`, and the payment date is what schedules the charge. Traced through
WooCommerce:

1. `update_dates( [ 'next_payment' => 0 ] )` — `prepare_dates_for_update()` routes a `0` into
   `$delete_date_types` and drops it from the ordering checks
   (`class-wc-subscription.php:2903-2910`).
2. Writing the date fires the scheduler hook, which `WCS_Scheduler` listens on
   (`abstract-wcs-scheduler.php:22-24`).
3. `WCS_Action_Scheduler::update_date()` maps `next_payment` to
   `woocommerce_scheduled_subscription_payment` (`class-wcs-action-scheduler.php:33`), and with a
   timestamp of `0` calls `unschedule_actions()` then returns before rescheduling
   (lines 57-64).

No scheduled payment action means `wcs_create_renewal_order()` never runs at the term end: no renewal
order, no charge, and nothing for `get_memberships_data_from_subscription_products()` to turn into a
form-bypassing membership. The subscription stays active but inert and expires on its own end date.

Monthly is excluded inside the method — those payments are instalments against a term the member has
already taken, so they are still owed.

### Line-by-line justification

**Serves the callout fix (goal 1):**

| Change | Location | Why |
|---|---|---|
| `! $is_monthly_form_flow_exception` → `! $allow_form_flow_early_callout` | `Membership_Controller.php:2045` | The edit that stops the skip firing for non-monthly. |
| Deleted `$is_monthly_form_flow_exception` assignment and its 3-line comment | was above 2045 | Nothing references it; it existed only to add the period test. |
| Gate 8 condition reduced to the flag | `Membership_Controller.php:2172` | Same removal at the second, independent gate. Required — gate 8 alone would still suppress the entry. |
| Flag docblock rewritten | `Membership_Controller.php:2024-2031` | Records that billing period is deliberately *not* a condition, so it does not get reinstated as an obvious-looking narrowing. |

**Serves the auto-payment fix (goal 2):**

| Change | Location | Why |
|---|---|---|
| Dropped `get_billing_period()`/`get_billing_interval()` from the caller's guard | `Membership_Controller.php:740` | Without this the subscription action never runs on the annual subscriptions that carry the armed payment. |
| `next_payment` cleared unconditionally rather than when `>= $end_ts` | `Subscription_Manager.php:118-121` | Removes the dependency on the collision guard's hour nudge (Cause 3). The payment is being stopped, so its exact position relative to the term end is not the question. |
| Monthly exclusion added to the method | `Subscription_Manager.php:118` | Monthly payments are owed instalments. The exclusion moved from the caller to the method so the caller does not decide a payment question. |
| Caller docblock rewritten | `Membership_Controller.php:695-713` | States the new scope and that the monthly decision is delegated; adds `@see`. |

**Removals — neither goal, so they went:**

| Removed | Was at | Why it is not needed |
|---|---|---|
| The `end` write, plus its only-ever-shorten and not-before-last-payment guards | `Subscription_Manager.php` | Moving `end` changes nothing about billing once the payment date is gone — only how the record reads in the WooCommerce admin. It also forced a third guard, because WooCommerce rejects `end <= next_payment` and monthly instalments are retained. |
| `$end_ts` parameter, and the caller's `membership_ends_at` lookup and guard | both files | Dead once the `end` write went. |
| Method renamed `terminate_at_membership_end` → `drop_superseded_next_payment` | `Subscription_Manager.php:117` | The old name and its unused date parameter would describe behaviour the method no longer has. |
| `can_date_be_updated( 'end' )` precheck and its log line | `Subscription_Manager.php` | Not enforced by `update_dates()`; the `try`/`catch` already handles a rejection. Pure defence. |
| `Utilities::wicket_logger()` success line | `Subscription_Manager.php` | `wicket_logger()` writes only in development environments. The order note is the durable record. |
| `@since 1.0.123` | `Subscription_Manager.php` | No `@since` exists anywhere in `includes/`, and the version was a guess against the release bot's bump. |
| Two-branch order note reduced to one sentence | `Subscription_Manager.php:134` | One operation now, so one sentence. |

**Neither goal, kept deliberately:**

| Kept | Why |
|---|---|
| Form Flow test read from the membership record, not the tier | The destination resolution downstream reads the same meta, so the gate and the rendered button cannot disagree about the flow. |
| Different-subscription check | Without it a renewal that reused the same subscription would have its live payment date removed. |
| Autopay-only guard in the caller | Narrowing. A manual-renewal subscription takes no payment on its own. |
| `try`/`catch` around `update_dates()` | It throws; unhandled that would fault renewal processing mid-`scheduler_dates_for_expiry()`. |
| Order note | The only record on the subscription of why its payment date disappeared. |

**No behavioural content:** guard-chain compression in the caller (`$previous_membership_post_id` →
`$previous_id`, six `if`/`return` pairs merged into three) and the method's two early returns merged
into one. Same conditions, same order, fewer statements.

## Blast radius of the repair

**Now visible that was not:** the early-renewal callout for Form Flow memberships on any billing
period, inside the renewal window only. Still no writes on that path — the flag requires
`empty($next_tier_subscription_renewal)`, which is the condition guarding the subscription-mutating
block at `Membership_Controller.php:2056`, so these memberships take the read-only form-page branch.

**Now acted on that was not:** non-monthly superseded subscriptions lose their payment date at
renewal. That is the fix.

**Changed from the monthly repair, deliberately:**

- **A monthly instalment falling after the term end is now retained.** The previous rule cleared it
  (`next_payment >= ends_at`). Because a monthly subscription's `end` is `membership_expires_at`, such
  an instalment sits in the grace window and will now be taken. Per instruction: those payments are
  owed.
- **No subscription's `end` date is modified any more.** The previous repair moved it to `ends_at`.
  Superseded subscriptions now keep their original end and stay active-but-inert until it arrives.

**Untouched:** `renewal_type` `subscription` / `current_tier` / `sequential_logic` / `inherited`;
manual-renewal members; the grace period; the whole term outside the renewal window; any subscription
not superseded by a renewal; `pending_approval`; `membership_exists`; the `debug` bucket;
`WICKET_MSHIP_DISABLE_RENEWALS`; and the `_requires_manual_renewal` flag, which still reads "autopay
on" for a stopped subscription.

**Not addressed, unchanged:** no server-side enforcement of the renewal window.
`validate_renewal_order_items()` is not hooked (`wicket.php:224`) and the order-processing check's
enforcement is commented out (`Membership_Controller.php:338-344`), so callout visibility remains the
only gate on when a renewal can be purchased.

## Verification performed

- `php -l` on both modified files.
- The failure was reproduced from production ACC output before the change
  (`SKIPPING for Auto-Renew: membership_id:359254`), confirming the global skip as the suppressing
  gate on an annual autopay membership.
- Traced the unschedule path end to end in WooCommerce source: `prepare_dates_for_update()` delete
  handling, `WCS_Scheduler`'s hook registration, and `WCS_Action_Scheduler::update_date()`'s
  `unschedule_actions()` + early return on a zero timestamp.
- Confirmed the annual renewal order is *not* skipped by `Membership_Controller.php:288`, which is
  what makes the term-end charge create a form-bypassing membership.
- Confirmed the subscription-mutating block is unreachable for this flow by following
  `renewal_type = form_flow` through `Admin_Controller.php:691-695` to
  `membership_next_tier_subscription_renewal = 0`.

**Not performed:** no automated test run — this checkout has no `vendor/` or `tests/`, so PHPUnit is
unavailable. No live verification that the payment date is removed after a real annual Form Flow
renewal, and no live confirmation that the term-end charge does not fire afterwards. Both should
happen before merge.

Suggested manual check: an annual autopay membership on a `form_flow` tier with `ends_at` inside the
renewal window. Expect the card at the start of the window pointing at the form page. Complete the
renewal, then confirm on the old subscription that the next payment date is empty, the order note is
present, and Action Scheduler holds no pending `woocommerce_scheduled_subscription_payment` for it.

## Known remaining exposure

- **A member who does not renew is still auto-renewed.** The payment date is only removed when a
  renewal happens. Ignore the callout and the term-end charge still fires and still creates a
  membership with no form. Root cause is `Helper::has_next_payment_date()` (`Helper.php:570`) arming a
  payment for any autopay subscription regardless of `renewal_type`. Deliberately out of scope —
  analysed in [`../local/monthly-billing-window-and-grace-callout.md`](../local/monthly-billing-window-and-grace-callout.md).
- **The grace-period callout can still be suppressed.** For a member who has not renewed, an armed or
  rolling `next_payment` keeps the global skip firing past `ends_at`. Same doc.
- **`sequential_logic` is untouched** — still hides the callout while the automatic charge renews the
  member at the *current* tier, ignoring the tier progression.
- **Only the previous membership's subscription is acted on**, via `previous_membership_post_id`.
  Older stale subscriptions keep their payment dates.

## See Also

- [Monthly Autopay Form Flow — Hidden Renewal Callout & Orphaned Subscription](monthly-autopay-form-flow-renewal-callout.md)
  — the repair this extends; still the record for the monthly case
- [Subscription_Manager](../engineering/Class-Subscription_Manager.md) — the method's home and the
  `prepare_dates()` guard it deliberately bypasses
- [Callout Display Conditions](../local/callout-display-conditions.md) — every condition governing
  the renewal callout, both periods
