---
title: "Monthly Subscription Inherits a Sibling Annual Subscription's Next Payment Date"
audience: [developer]
php_class: Membership_Controller
source_files:
  - "includes/Membership_Controller.php"
  - "includes/Subscription_Manager.php"
  - "includes/Helper.php"
ticket: WWID-2425
branch: bugfix/WWID-2425-monthly-next-payment-inherited-from-annual-sibling
---

# Monthly Subscription Inherits a Sibling Annual Subscription's Next Payment Date

A single checkout that creates **two subscriptions** (one monthly, one annual) and **several
memberships** leaves the *monthly* subscription with the *annual* subscription's next payment
date — the membership term end, 13 months out — instead of its own monthly instalment date.
The member stops being billed monthly. Nothing is written to the monthly subscription's order
notes, so the change is invisible in the admin history.

The carrier is the deferred `woocommerce_subscription_status_updated` closure in
`Membership_Controller::update_membership_subscription()`
(`includes/Membership_Controller.php:990-997`). It is registered once per membership, on a
global hook, captures that membership's `$dates_to_update` by value, and applies it to
*whichever* subscription next changes status — not the one it was built for.

A **grace period of 0** on the tier config is what makes the leaked value pass WooCommerce's
own validation instead of throwing, which is why it lands silently. This is a direct follow-on
to the collision guard added for WWID-1869; see
[Subscription_Manager](../engineering/Class-Subscription_Manager.md).

**Repaired** by giving each deferred closure the id of the subscription it was computed for and
having it return early for any other — 21 lines added, 4 removed, in one file, changing no date
calculation. The second closure in the same method (the final-stage `next_payment` clear) had
the identical defect in the opposite direction and got the same guard. See
[The repair](#the-repair).

---

## Evidence base

Production database dump from `members.physiotherapy.ca` (CPA), file
`jyynbkbqdt (1).sql.gz`, taken 2026-09-02. HPOS is enabled — subscription date state lives in
`wp_wc_orders` / `wp_wc_orders_meta`, not `wp_postmeta`.

Reported case, order **532497**, customer `michael.novikov@physiooutaouais.ca` (user 57875),
placed 2026-09-01 12:39:07 UTC:

| Object | Type | Detail |
|---|---|---|
| 532497 | `shop_order` | parent order, `wc-completed`, CAD 77.65 |
| **532498** | `shop_subscription` | **monthly** — `_billing_period=month`, `_billing_interval=1`, `_requires_manual_renewal='false'` |
| 532499 | `shop_subscription` | **annual** — `_billing_period=year`, `_billing_interval=1`, `_requires_manual_renewal='false'` |
| 532500 | `wicket_membership` | tier *Practising* (tier post 518581, product 518576) → subscription **532498** |
| 532501 | `wicket_membership` | tier *AQP* (tier post 2429, product 2283) → subscription **532498** |
| 532502–532507 | `wicket_membership` | tiers *Sports*, etc. → subscription 532499 |

All eight membership posts carry:

```
membership_starts_at    2026-10-01T00:00:00+00:00
membership_ends_at      2027-09-30T23:59:59+00:00
membership_expires_at   2027-09-30T23:59:59+00:00   ← equal to ends_at ⇒ grace period 0 days
membership_next_tier_subscription_renewal   ''      ← empty on every one
```

Final state of the monthly subscription 532498 (`wp_wc_orders_meta`):

```
6386655  _billing_period            month
6386656  _billing_interval          1
6386659  _requires_manual_renewal   false
6386663  _schedule_next_payment     2027-09-30 22:59:59   ← end minus exactly 3600s
6386665  _schedule_end              2027-09-30 23:59:59
6386667  _schedule_start            2026-09-01 12:39:07
```

The annual subscription 532499 holds the identical pair (`6386712` = `2027-09-30 22:59:59`,
`6386714` = `2027-09-30 23:59:59`).

Action Scheduler agrees — the monthly subscription has exactly one armed payment, 13 months
out:

```
7494285  woocommerce_scheduled_subscription_expiration  pending  2027-09-30 23:59:59  {"subscription_id":532498}
7494287  woocommerce_scheduled_subscription_payment     pending  2027-09-30 22:59:59  {"subscription_id":532498}
7494288  woocommerce_scheduled_subscription_payment     pending  2027-09-30 22:59:59  {"subscription_id":532499}
7494289  woocommerce_scheduled_subscription_expiration  pending  2027-09-30 23:59:59  {"subscription_id":532499}
```

Order notes on 532498 record **only** the end-date changes (comments `1454148`, `1454151`):

```
Membership 532500 changed these subscription dates. <br> End Date: 2027-09-30(2027-09-30 23:59:59)
Membership 532501 changed these subscription dates. <br> End Date: 2027-09-30(2027-09-30 23:59:59)
```

whereas the annual subscription's notes for memberships 532502–532507 (comments `1454155`,
`1454158`, `1454161`, `1454164`, `1454167`, `1454170`) record both:

```
Membership 532502 changed these subscription dates. <br> Next Payment Date: 2027-09-30(2027-09-30 22:59:59)<br> End Date: 2027-09-30(2027-09-30 23:59:59)
```

There is **no** `wicket_force_set_next_payment_date` action for 532498 and **no**
`Wicket forced next payment date to:` note on it. Those exist only for 532499 — six of them
(`7494257`, `7494262`, `7494267`, `7494272`, `7494277`, `7494282`), which ran at 12:42:12 and
left comments `1454208`–`1454213`. So the monthly subscription's `next_payment` was written by
a path that adds neither a note nor a follow-up action.

---

## Cause

> Line numbers in this section are the **pre-fix** file (commit `231bcfd`), so the defect can be
> read against the code as it shipped. Post-fix locations are given in [The repair](#the-repair).

### 1. Grace 0 collapses `expires_at` onto `ends_at`

`Membership_Config` only offsets `expires_at` past `end_date` when a late-fee/grace window is
configured. With none, `expires_at == ends_at == 2027-09-30 23:59:59` for all eight
memberships in this order.

### 2. `has_next_payment_date()` returns `true` on the autopay branch

`Helper::has_next_payment_date()` (`includes/Helper.php:558`) short-circuits to `true` when
*either* `membership_next_tier_subscription_renewal` is set **or** autopay is on:

```php
$is_autopay_enabled = $subscription->get_requires_manual_renewal() ? false : true;

if ( !empty( $membership['membership_next_tier_subscription_renewal'] ) || $is_autopay_enabled ) {
  // "The date is always set to the Membership End Date (membership_ends_at)"
  $has_next_payment_date = true;
}
```

Here `membership_next_tier_subscription_renewal` is empty on all eight memberships, but
`_requires_manual_renewal='false'` on both subscriptions, so `$is_autopay_enabled` is `true`
and the function returns `true` — for the monthly subscription too. Intent per the docblock:
`next_payment` = membership end date.

`Membership_Controller::create_membership_record()` then passes that through
(`includes/Membership_Controller.php:846-852`):

```php
$date_flags_array = [ 'start_date', 'end_date' ];
if( $has_next_payment_date = Helper::has_next_payment_date( $membership )) {
  $date_flags_array['next_payment_date'] = $has_next_payment_date;   // sets a KEY, value = true
}
$self->update_membership_subscription( $membership, $date_flags_array, true );
```

Note the mismatch: the flag is stored as an array **key**, while
`update_membership_subscription()` tests membership by **value** —
`in_array('next_payment_date', $fields)` at `includes/Membership_Controller.php:967`. That test
only passes because of PHP's loose string-vs-bool comparison against the `true` value. The
`'clear'` case a few lines later reads the **key** instead
(`!empty($fields['next_payment_date'])`, line 977). Both work today, by coincidence rather than
design.

### 3. The monthly guard suppresses `next_payment` — for the direct write only

`includes/Membership_Controller.php:953-971`:

```php
if( in_array ( 'end_date', $fields ) ) {
  if(!empty($membership['membership_next_tier_subscription_renewal']) && !empty($sub) && ($sub->get_billing_period() == 'month' && $sub->get_billing_interval() == 1)) {
    $dates_to_update['end'] = /* day_end(membership_ends_at) */;
  } else {
    $dates_to_update['end'] = /* day_end(membership_expires_at) */;
  }
}
if( in_array ( 'next_payment_date', $fields ) && !empty($sub) && !($sub->get_billing_period() == 'month' && $sub->get_billing_interval() == 1)) {
  $dates_to_update['next_payment'] = /* day_end(membership_ends_at) */;
}
```

For **532498** (monthly, interval 1): `next_tier_subscription_renewal` is empty, so `end` comes
from `expires_at` (`2027-09-30 23:59:59` — same value as `ends_at` under grace 0), and the
`next_payment` branch is skipped by the monthly guard. `$dates_to_update` is `['end' => …]`
only. This matches its two order notes exactly.

For **532499** (annual): `next_payment = day_end(ends_at) = 2027-09-30 23:59:59`, which equals
the `end` being written in the same call. `Subscription_Manager::prepare_dates()`
(`includes/Subscription_Manager.php:77`) detects the collision and
`nudge_next_payment_before_end()` (line 46) subtracts `NEXT_PAYMENT_COLLISION_OFFSET`
(`HOUR_IN_SECONDS`, line 26), producing **`2027-09-30 22:59:59`**. This is the fingerprint of
the stored value: exactly 3600s before `end`, with machine `:59:59` seconds that the
minute-precision admin schedule metabox cannot produce.

### 4. The leak — an unbound closure on a global hook

`includes/Membership_Controller.php:986-998`:

```php
if(!empty($dates_to_update)) {
  $dates_to_update = Subscription_Manager::prepare_dates( $dates_to_update, $sub );
  $sub->update_dates($dates_to_update);
  Utilities::wicket_logger( 'SUBSCRIPTION DATES BEING UPDATED MANUALLY: dates_to_update', $dates_to_update);
  add_action('woocommerce_subscription_status_updated', function( $subscription_id ) use ( $dates_to_update ) {
    $sub = \wcs_get_subscription( $subscription_id );
    if( empty( $sub ) ) {
      return;
    }
    $sub->update_dates($dates_to_update);
    ...
  }, 10, 2 );
}
```

Three properties make this leak across subscriptions:

- **No subscription binding.** The closure re-applies its captured array to whatever
  subscription the hook hands it. WooCommerce Subscriptions fires
  `do_action( 'woocommerce_subscription_status_updated', $this, ... )`
  (`woocommerce-subscriptions/includes/core/class-wc-subscription.php:666`) with the
  `WC_Subscription` **object**; the parameter is named `$subscription_id` but
  `wcs_get_subscription()` accepts either, so it resolves and writes successfully to the wrong
  subscription without erroring.
- **One registration per membership, never removed.** Each membership processed in the request
  adds another closure (distinct objects ⇒ distinct WordPress filter IDs, so none replaces
  another). No `remove_action` after firing.
- **It bypasses every guard around it.** It calls `update_dates()` directly — not through
  `prepare_dates()`, not through the monthly-billing test at line 967, and not through the
  note/`schedule_force_set_next_payment_date()` block at lines 999-1006. Hence no order note
  and no force-set action on the victim subscription.

### 5. Sequence in the reported request

Membership creation runs inside the same request as the payment, on
`woocommerce_order_status_processing` → `Membership_Controller::catch_order_completed()`
(`wicket.php:208`), and completes *before* WooCommerce Subscriptions activates the
subscriptions. Comment IDs confirm the ordering — all membership notes (`1454145`–`1454170`)
precede the activation notes (`1454172`–`1454175`):

| Time (UTC) | Event |
|---|---|
| 12:39:07 | Order 532497 created; subscriptions 532498 (monthly) + 532499 (annual) created `pending` |
| 12:39:31–32 | Stripe charge captured; order → `processing` |
| 12:39:32–33 | Memberships 532500, 532501 processed against **532498** → **2 closures** registered, each capturing `['end' => '2027-09-30 23:59:59']` |
| 12:39:34–37 | Memberships 532502–532507 processed against 532499 → **6 closures** registered, each capturing `['end' => '2027-09-30 23:59:59', 'next_payment' => '2027-09-30 22:59:59']` |
| 12:39:37 | WCS marks payment complete and flips **both** subscriptions `pending` → `active` in this same request. Every one of the 8 closures fires on **each** transition. The six annual ones write `next_payment = 2027-09-30 22:59:59` onto the monthly subscription 532498. Last registration wins. |
| 12:41:04–07 | Six `wicket_force_set_next_payment_date` actions scheduled — for 532499 only |
| 12:42:12 | Those run, re-affirming `22:59:59` on 532499 with notes |

WooCommerce then reschedules 532498's `woocommerce_scheduled_subscription_payment` action from
the corrupted date, producing the pending 2027-09-30 22:59:59 row (`7494287`).

### 6. Why grace 0 is load-bearing

`WC_Subscription::prepare_dates_for_update()` rejects `end <= next_payment` with
*"The end date must occur after the next payment date."*
(`woocommerce-subscriptions/includes/core/class-wc-subscription.php:2930`). Grace 0 does two
things here:

- It makes `end == next_payment` for the annual membership, so the nudge fires and the captured
  value becomes `end − 1h`.
- That pre-nudged value is **valid** when replayed onto the monthly subscription (22:59:59 <
  23:59:59), so the wrong date is accepted silently. Had the closure carried the raw
  `23:59:59`, WCS would have thrown and the `catch` block would have written an
  `attempted to change these subscription dates` note — the corruption would have been loud, or
  not happened at all.

With a grace period > 0 the same leak still moves the monthly subscription's `next_payment` to
the membership end date (just without the 1-hour offset, since `end` would then be
`ends_at + grace`). Grace 0 governs *visibility*, not whether the leak occurs.

### Ruled out

| Candidate | Why not |
|---|---|
| `catch_wicket_force_set_next_payment_date()` (`includes/Membership_Controller.php:544`+) | Adds a `Wicket forced next payment date to:` note; no such note and no action row for 532498 |
| The direct write at line 988 | Would have printed `Next Payment Date` in the note (line 1002); both 532498 notes show End Date only |
| WCS activation recalculation (`class-wc-subscription.php:559`) | `calculate_next_payment_date()` returns `0` past the end date, never `end` (`class-wc-subscription.php:1794`); a monthly recalc from `_schedule_start` yields 2026-10-01 |
| Admin schedule edit | The metabox is minute-precision; it cannot produce `:59` seconds. `2027-09-30 22:59:59` is machine-generated |
| WCS health check | `wp_wcs_health_check_runs` holds one run, 2026-06-08; no `wp_wcs_health_check_candidates` row for 532498 |
| A later cron/job | No membership- or subscription-related Action Scheduler action referencing 532498 ran after 12:42 |
| The later row touches (`date_updated_gmt` 14:25:48 on 532498, 14:31:20 on 532499) | An admin screen render, not a date write. Those timestamps coincide with the first appearance of `_relationship = 'Subscription'` (meta `6391499` / `6391632`), written by `WCS_Meta_Box_Related_Orders` (`woocommerce-subscriptions/includes/core/admin/meta-boxes/class-wcs-meta-box-related-orders.php:97`) when the related-orders box renders, plus `_edit_lock` and an ENR cache key. No date meta changed |

---

## Effect

**1. Monthly billing stops (financial).** The monthly subscription holds a single armed payment
at the membership term end instead of monthly instalments. For 532498 that is one CAD 6.37
charge due 2027-09-30 rather than ~13 of them — the member keeps a full membership term while
the instalments they agreed to are never taken. Recovery of missed instalments after the fact
is a manual finance exercise.

**2. Silent in the audit trail.** The subscription's order notes claim only an end-date change.
Support sees a monthly subscription whose next payment is 13 months away with nothing in the
history explaining it, and there is no `wicket_force_set_next_payment_date` action to point at.
Diagnosis currently requires reading `wp_wc_orders_meta` and Action Scheduler directly.

**3. Term-end payment on a subscription that should not have one.** The armed 2027-09-30
payment sits one hour before the subscription's own `end`, so it fires while the subscription is
still `active` and will generate a renewal order at term end — the same class of unwanted
term-end auto payment addressed for annual subscriptions in the
[annual autopay follow-on](annual-autopay-form-flow-renewal-callout.md), reintroduced here by a
different route.

**4. Blast radius beyond this order.** Any request that processes more than one subscription
through `update_membership_subscription()` and then transitions a subscription's status can
cross-contaminate. Preconditions: a single checkout producing ≥2 subscriptions where at least
one membership yields a `next_payment` (autopay on, or next-tier subscription renewal), and a
status transition occurring in the same request — which is the normal paid-checkout path.
Mixed monthly/annual carts are the damaging combination; all-annual carts leak an identical
value between siblings and are therefore harmless. Multi-membership orders on this site are
routine (this one carried eight), so this is not an edge case.

---

## The repair

Applied to `includes/Membership_Controller.php` — **+21 / −4 lines, one file, 5 of the added
lines are comments.** No date calculation, hook, order note, scheduled action or public
signature changed. The design constraint was that the change must be a provable no-op for every
configuration except the defect itself, so the repair is an *identity check*, not a change to
what dates get computed or when they are written.

### Fix 1 — the deferred date write (was `:990-997`, now `:990-1004`)

```php
            //the hook below is global: it fires for EVERY subscription that transitions in this request,
            //not just this one. One order can carry several subscriptions and a closure is registered per
            //membership, so without the id test a sibling subscription inherits these dates (WWID-2425).
            $target_subscription_id = (int) $sub->get_id();
            add_action('woocommerce_subscription_status_updated', function( $updated_subscription ) use ( $dates_to_update, $target_subscription_id ) {
              $sub = \wcs_get_subscription( $updated_subscription );
              if( empty( $sub ) ) {
                return;
              }
              if( (int) $sub->get_id() !== $target_subscription_id ) {
                return;
              }
              $sub->update_dates($dates_to_update);
              Utilities::wicket_logger( '---woocommerce_subscription_status_updated--- SUBSCRIPTION DATES BEING UPDATED ', [$sub->get_id(), $dates_to_update ]);
            }, 10, 2 );
```

Three substantive changes:

1. **`$target_subscription_id` captured at registration**, taken from `$sub->get_id()` rather
   than from `$subscription_id` (`:936`). Both hold the same value here, but `$sub` is the object
   the dates were just written to, so the guard is anchored to the actual write target and
   cannot drift if that variable is ever reused. Cast to `int` once, at capture.
2. **The identity test** — return unless the transitioning subscription *is* the target.
3. **Parameter renamed `$subscription_id` → `$updated_subscription`.** Cosmetic but load-bearing
   for maintenance: the old name was a lie (WCS passes the `WC_Subscription` object at
   `class-wc-subscription.php:666`), and it is precisely the shadowing trap that made Fix 2
   non-obvious. A future `use ( $subscription_id )` on the old signature would have been
   silently overwritten by the parameter, producing a guard that always passes.

### Fix 2 — the final-stage `next_payment` clear (was `:1031-1034`, now `:1038-1047`)

```php
          //same global-hook caveat as the date write above: clear the date only on the subscription this
          //call is about, or a sibling subscription in the same order loses its own next payment (WWID-2425).
          $clear_target_subscription_id = (int) $sub->get_id();
          add_action('woocommerce_subscription_status_updated', function( $updated_subscription ) use ( $clear_target_subscription_id ) {
            $sub = \wcs_get_subscription( $updated_subscription );
            if( empty( $sub ) || (int) $sub->get_id() !== $clear_target_subscription_id ) {
              return;
            }
            $sub->update_dates(['next_payment' => 0]);
          }, 10, 2 );
```

Same guard, plus the `empty( $sub )` check the original lacked — the pre-fix closure called
`$sub->update_dates()` unconditionally and would raise a fatal on `wcs_get_subscription()`
returning `false` (a trashed or unloadable subscription transitioning in the same request). A
distinct variable name (`$clear_target_subscription_id`) keeps the two closures independent and
sidesteps the shadowing trap described above.

This is the defect in reverse: unguarded, it **removes** a payment date from a sibling
subscription. It is the most probable origin of `_schedule_next_payment = 0` on subscription
532791 (an insurance subscription with no membership of its own, in a two-subscription order —
see *Residual risk* below).

### How the bug is accounted for by these two guards

Walking the reported sequence with the fix in place:

| Step | Pre-fix | Post-fix |
|---|---|---|
| Memberships 532500/532501 processed → 2 closures registered, target **532498** | `['end' => 23:59:59]` captured | identical, plus `$target_subscription_id = 532498` |
| Memberships 532502–532507 → 6 closures registered, target 532499 | `['end' => 23:59:59, 'next_payment' => 22:59:59]` captured | identical, plus `$target_subscription_id = 532499` |
| 532498 transitions `pending`→`active` | **all 8** closures write; the 6 annual ones set `next_payment = 2027-09-30 22:59:59` on the monthly subscription | only the 2 closures targeting 532498 write; the 6 annual ones return at the id test. 532498 keeps its own `next_payment` (2026-10-01) |
| 532499 transitions `pending`→`active` | all 8 closures write; harmless, values identical | only the 6 closures targeting 532499 write; outcome unchanged |

The corrupting write is the only thing removed. The two closures that legitimately target
532498 still re-apply their end date after WCS's activation recalculation — the documented
reason the deferred write exists (`:928-933`).

**What was deliberately *not* changed**, because each would alter behaviour in configurations
that cannot be enumerated from one dump:

- `Helper::has_next_payment_date()` (`Helper.php:570`) still returns `true` on the autopay
  branch for a monthly subscription paying an annual membership. Only the monthly guard at
  `:967` stops that becoming a `next_payment` write — a guard the deferred path still skips by
  design, since it replays an already-computed array.
- The key-vs-value flag confusion (`:849` sets a key, `:967` tests a value, `:977` reads the
  key) is untouched; correcting it flips which branches run under unknown configurations.
- No dedupe/merge of closures targeting the *same* subscription. Two memberships on one
  subscription still both write, last one wins — pre-existing behaviour, intentionally
  preserved.
- No `remove_action` after firing. It would not have prevented this bug (the monthly
  subscription transitioned *first*, before any unhooking could occur) and unhooking mid-`do_action`
  risks perturbing WordPress's callback iteration for other listeners.
- The deferred write still routes around `Subscription_Manager::prepare_dates()`. Adding it
  would change what gets written whenever the subscription's stored `end` differs from the
  captured array — a real behavioural change, and unnecessary once the array can only reach its
  own subscription.
- No order note on the deferred write. Desirable for diagnosis, but it would add
  customer-visible order-history entries on every activation across every site.

---

## Blast radius of the repair

**Every caller reaches the same code, and for its own subscription the behaviour is byte-identical.**
The guard can only suppress a write to a subscription the date array was never computed for.

| Consumer | Effect of the fix |
|---|---|
| `create_membership_record()` (`:852`) — new membership + renewal creation, the path in this ticket | Multi-subscription orders: cross-writes stop. Single-subscription orders: no change (only one subscription can transition, guard never fires) |
| `Admin_Controller::admin_manage_status()` (`Admin_Controller.php:759`) — pending→active approval | No change. It transitions the subscription it is approving, so the guard passes and dates still survive WCS's activation recalculation |
| `Admin_Controller::update_membership_entity_record()` (`Admin_Controller.php:176`) — React UI date edit | No change. No status transition normally occurs, so the closure was already a no-op; if one does occur it is for the same subscription |
| `catch_wicket_force_set_next_payment_date()` (`:544`+) — the +90s async re-write | Untouched. Runs in its own request, registers no closure, still routes through `prepare_dates()` |
| Final-stage `next_payment` clear (autopay off, or `'clear'`) | Clears only the subscription the call is about. A sibling that was being wiped now keeps its own date — the corrected outcome, and the one intentional behavioural change on other sites |
| `Import_Controller`, `Membership_Subscription_Controller`, `csv_post.php` | Not touched; they do not call `update_membership_subscription()` |

**Invariant relied on** (the reason this is a no-op for the intended target): the block is
gated on `!empty( $sub )` (`:973`), and `$sub` was loaded from
`$membership['membership_subscription_id']` (`:937`), so `$sub->get_id()` *is* by construction
the subscription this membership names. Whenever that subscription transitions, the guard
passes.

**Configuration-independent:** the guard reads no tier config, grace period, billing
period/interval, autopay flag, approval setting, renewal type, gateway or product setup. It
compares two integers.

**Argument-shape safe:** correct whether the hook is fired with the `WC_Subscription` object (as
WCS does) or a bare id, since `wcs_get_subscription()` accepts both. A third party firing the
hook with an id still resolves correctly.

**Residual risk — one scenario, checked but not exhaustively disproved.** A site could have a
subscription in a multi-subscription order with *no membership of its own* whose only usable end
date came from inheriting a sibling's. The guard would stop that. Evidence against it: order
532789 in the dump is exactly that shape — subscription 532791 (Professional Liability
Insurance, no `wicket_membership` post referencing it) carries
`_schedule_end = 2027-09-30 23:59:00`. The minute-precision `:00` seconds rule this closure out
as its source, since `Utilities::get_mdp_day_end()` (`Utilities.php:1285-1297`) always emits
`:59:59`. That is one instance, not a proof across all configurations — see the pre-flight query
under *Known remaining exposure*.

**Regression introduced by the fix:** none identified. The `empty( $sub )` check added in Fix 2
removes a latent fatal rather than adding one.

---

## Verification performed

### Of the diagnosis

- **Forensic, against the production dump only.** All findings above were read out of
  `jyynbkbqdt (1).sql.gz` (2026-09-02): `wp_wc_orders`, `wp_wc_orders_meta`, `wp_posts`,
  `wp_postmeta`, `wp_comments`, `wp_actionscheduler_actions`, `wp_actionscheduler_logs`,
  `wp_wcs_health_check_runs`, `wp_wcs_health_check_candidates`.
- **Code citations verified in this checkout** (`wicket-wp-stack`): `Membership_Controller.php`
  pre-fix lines 544, 846-852, 934, 953-971, 986-1006, 1031; `Subscription_Manager.php` lines
  26, 46, 77; `Helper.php` line 558.
- **WooCommerce Subscriptions citations verified against the copy in this checkout, v8.5.0**
  (`class-wc-subscription.php:559`, `:666`, `:1794`, `:2930`;
  `class-wcs-meta-box-related-orders.php:97`). Production was running **WCS 9.1.0**; the four
  cited behaviours are identical in both versions.
- **Alternative writers eliminated by evidence**, not by reasoning alone — see *Ruled out*
  above.

### Of the repair

- `php -l includes/Membership_Controller.php` — no syntax errors.
- Diff reviewed line by line; the only executable additions are two `$target…_id` captures and
  two early-return tests. Both closures' write bodies are unchanged.
- Formatting: the repo's `.php-cs-fixer.dist.php` enables `line_ending` only; file remains LF,
  no CR introduced.
- Shadowing checked: each closure captures a uniquely named variable, neither of which collides
  with its own parameter (the trap the pre-fix `$subscription_id` naming set).
- Invariant `$sub->get_id() === (int) $membership['membership_subscription_id']` re-read against
  `:936-937` and the `!empty( $sub )` gate at `:973`.

### Not verified — read this before deploying

- **No runtime execution of any kind.** This plugin has no test suite and no CI workflows (only
  `.github/PULL_REQUEST_TEMPLATE.md`), so nothing was exercised. The fix has not been run
  against a live checkout.
- **No runtime reproduction of the bug**, before or after. The cross-write is inferred from the
  code plus the absence of any other writer able to produce that exact value silently; it has
  not been observed live.
- The functional checks that would close this out have **not** been run: single-subscription
  membership checkout (expect no change); mixed monthly+annual multi-membership checkout (expect
  the monthly subscription to keep a monthly `next_payment`); admin pending→active approval via
  `admin_manage_status()` (expect dates to survive activation); a grace-0 tier (expect the
  `end − 1h` nudge on the correct subscription only); the autopay-off / `'clear'` path (expect
  the clear to hit only its own subscription).
- The exact firing order of the eight closures during the 12:39:37 transitions is inferred from
  WordPress hook registration semantics and comment-ID ordering, not from a log. All six annual
  closures carry the same payload, so which one wins does not change the outcome.
- **Uncommitted at time of writing**, in the working tree of branch
  `feature/users-multi-tier-renewal-subscriptions-merged` (base commit `231bcfd`), not on the
  `bugfix/WWID-2425-…` branch named in this document's frontmatter.

---

## Known remaining exposure

- **Unquantified affected population.** A sweep is needed for active `shop_subscription`
  records with `_billing_period='month'` / `_billing_interval=1` whose `_schedule_next_payment`
  is more than ~2 months out, or within an hour of `_schedule_end`, on orders that produced
  more than one subscription. Any such subscription has stopped billing monthly and needs both
  a date correction and a finance decision on missed instalments. Not started.
- **The reported records are still corrupted.** The fix stops new corruption; it repairs
  nothing already written. 532498 keeps `_schedule_next_payment = 2027-09-30 22:59:59` and
  Action Scheduler row `7494287`. Remediation needs a corrected `_schedule_next_payment`, a
  re-armed `woocommerce_scheduled_subscription_payment` action, and a finance decision on
  instalments already missed — track it as its own ticket so it is not assumed covered here.
- **Retire the `empty()` residual risk with a pre-flight query.** Look for active
  `shop_subscription` records whose `_schedule_end` is exactly `23:59:59` and which no
  `wicket_membership` post references via `membership_subscription_id`. An empty result across
  the estate closes the "inherited its only end date" scenario outright; a non-empty result
  names the subscriptions to inspect before deploying.
- **Sibling `next_payment` clears already applied are not reversed.** Fix 2 stops future
  cross-clears but does not restore a payment date wiped earlier — subscription 532791 is the
  probable example. Any such subscription silently stopped billing and is not detectable from
  order notes.
- **Key/value flag confusion persists.** `$date_flags_array['next_payment_date']` is set as a
  key (`:849`) and tested as a value (`:967`), passing only by loose comparison, while the
  `'clear'` case reads the key (`:977`). Any future change to what `has_next_payment_date()`
  returns can silently invert either test. Deliberately out of scope here — see *What was
  deliberately not changed*.
- **Any future closure on a global WCS hook reintroduces this class of bug.** Both closures in
  this method are now bound, but nothing structural prevents the next one from being unbound.
  The durable fix is a `Subscription_Manager` entry point that owns deferred subscription writes
  and takes the target id as an argument; see
  [Subscription_Manager](../engineering/Class-Subscription_Manager.md), where new
  subscription-touching logic is supposed to live.
- **Grace 0 remains the norm on these tiers**, so `end == next_payment` collisions — and the
  1-hour nudge — will keep occurring by design. The nudge itself is correct; it is the
  unguarded replay of its output that is not.

---

## See Also

- [Subscription_Manager](../engineering/Class-Subscription_Manager.md) — `prepare_dates()` /
  `nudge_next_payment_before_end()`, and the WWID-1869 background on grace-0 date collisions
- [Membership_Controller](../engineering/Class-Membership_Controller.md) —
  `update_membership_subscription()`, current owner of the leaking closure
- [Helper](../engineering/Class-Helper.md) — `has_next_payment_date()`
- [Annual Autopay Form Flow — Callout Still Hidden & Term-End Auto Payment](annual-autopay-form-flow-renewal-callout.md)
  — the term-end auto-payment failure mode this reintroduces by another route
- [Monthly Autopay Form Flow — Hidden Renewal Callout & Orphaned Subscription](monthly-autopay-form-flow-renewal-callout.md)
  — prior monthly-subscription date repair
