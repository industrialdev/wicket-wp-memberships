---
title: "Subscription_Manager Class Reference"
audience: [developer]
php_class: Subscription_Manager
source_files: ["includes/Subscription_Manager.php"]
---

# Subscription_Manager Class Index

**File:** includes/Subscription_Manager.php

## Purpose

Intended eventual home for all logic that operates on WooCommerce Subscriptions (`WC_Subscription`) on behalf of a Wicket membership. Currently that logic is scattered across `Membership_Controller`, `Admin_Controller`, `Membership_Subscription_Controller`, and `Import_Controller` — subscription lookup by membership meta (duplicated ~30 times), status-transition handling, subscription creation, subscription-to-membership linking, payment/customer-id reassignment, and autorenew-flag handling.

`Subscription_Manager` starts with only the date-safety guards (the narrowest, first-landed slice — fixed for [WWID-1869](https://app.asana.com/1/1138832104141584/project/1216279585352300/task/1216241729331817)). Other subscription concerns should migrate here incrementally rather than being added to `Membership_Controller`/`Admin_Controller` going forward. **New subscription-related code should be added here, not to those classes.**

### Background: the bug that created this class (WWID-1869)

When a membership tier uses subscription-based renewal (`WICKET_MSHIP_SUBSCRIPTION_RENEW`) and its config has **no grace period** configured, WooCommerce Subscriptions throws `"Subscription #<id>: The end date must occur after the next payment date."` on save.

Root cause, in brief:
- WC Subscriptions (`class-wc-subscription.php`, `prepare_dates_for_update()`) enforces `end > next_payment` with a strict `<=` comparison — zero tolerance, not a "1 hour" buffer as originally assumed. This validation is a fully separate pass that completes (or throws) before any date is written — a failed `update_dates()` call is atomic, nothing gets persisted.
- `Membership_Config::get_membership_dates()` only offsets `expires_at` from `end_date` when a grace period is configured. With none, `expires_at == end_date`.
- `Membership_Controller::update_membership_subscription()` writes that `expires_at`/`end_date` value as the subscription's `end`, colliding with `next_payment` (which lands on the same renewal-cycle boundary) whenever no grace period exists. This function is reachable from three live paths: a React-UI manual date edit (`Admin_Controller::update_membership_entity_record()`), an admin pending→active status approval (`Admin_Controller::admin_manage_status()`), and membership/renewal record creation (`Membership_Controller::create_membership_record()`) — plus a fourth, deferred/async path (`catch_wicket_force_set_next_payment_date()`, +90s via Action Scheduler) that re-writes `next_payment` alone and would otherwise silently undo the synchronous fix.
- No WooCommerce Subscriptions hook exists to intercept/correct the date array before its internal validation runs — the fix has to live in our own code, immediately before calling `update_dates()`.

Fix direction: shift `next_payment` earlier rather than `end` later, since `end` is typically a day-boundary `23:59:59` timestamp (`Utilities::get_mdp_day_end()`) and bumping it forward would roll into the next calendar day.

Methods are static and dependency-free — they operate on a passed-in `\WC_Subscription`, with no reliance on `Membership_Controller` state — so the class composes cleanly into whatever eventually replaces the current controllers.

**Static is not the intended end state.** It fits today's date-guard methods, which are pure functions with no state to hold. As lookup, status-transition, creation, and linking logic migrate in, expect a shift toward instancing — e.g. a factory (`Subscription_Manager::for_membership($membership)`) returning an object that holds the loaded `$sub` (and membership context) so multi-step operations (`$manager->cancel()`, `$manager->relink()`, `$manager->safe_update_dates(...)`) don't need to re-derive or re-pass the subscription on every call. Static methods will likely still have a place for genuinely stateless one-shot checks (like the two below), but the class as a whole should not be assumed to stay all-static as it grows.

## Methods

- `prepare_dates($dates_to_update, $sub)` — Bootstrapper for all subscription date writes going through `Subscription_Manager`. For any shape of `update_dates()`-bound array — `end` alone, `next_payment` alone (e.g. the deferred/async `catch_wicket_force_set_next_payment_date()` job), or both together — ensures `end` always lands strictly after `next_payment`. Whichever key is missing from `$dates_to_update` is resolved from the subscription's own current stored value for comparison purposes only. Nudges `next_payment` earlier when they'd collide (never bumps `end`, since `end` is typically a day-boundary `23:59:59` timestamp and bumping it forward would roll into the next calendar day).
- `nudge_next_payment_before_end($next_payment_ts, $end_ts)` (private) — Owns the actual comparison, adjustment, and logging. `prepare_dates()` is a thin adapter around this.

**Open concern on the adjustment size — currently 1 second, likely needs widening.** Investigated (see conversation, not yet actioned in code): WC Subscriptions' payment/retry logic gates on subscription *status*, not on whether `end` has been reached — a failed renewal charge goes `on-hold` and enters a ~7-day dunning cycle, and that status change unschedules the independent `end`-date expiration action. However, `next_payment` (charge trigger) and `end` (expiration trigger) are two *independently scheduled* Action Scheduler jobs; if the charge-processing action is delayed (gateway latency, queue congestion) and the `end` job fires first while status is still `active`, `expire_subscription()` unconditionally flips the subscription to `expired` with no check of in-flight payment attempts. A 1-second gap leaves essentially no buffer against this race; a gap of several hours (or a day) costs nothing functionally (the only enforced invariant is `end > next_payment`, any positive gap satisfies WC's validation) and removes most of the exposure. Widening the offset in `nudge_next_payment_before_end()` is a pending follow-up, not yet implemented.

**Named `prepare_dates()`, not `update_dates()` — deliberately, for now.** Today it only returns a corrected array; callers still call `$sub->update_dates($dates_to_update)` themselves afterward. The natural next step (deliberately deferred, not done now) is for this same method to also perform that write itself and return `void` — at which point it should be renamed to `update_dates()`. Keeping that name free until then avoids `Subscription_Manager::update_dates()` and `$sub->update_dates()` sitting side-by-side at the call site with different effects, which would read ambiguously. This ties into the centralized-wrapper follow-up already logged in the collision doc — worth doing together, and worth remembering the rename when it happens.

## TODO: `add_order_note()` bootstrapper

Another candidate for the same bootstrapper pattern as `prepare_dates()`. `$sub->add_order_note(...)` / `$order->add_order_note(...)` calls are scattered across `Membership_Controller.php` (10+ sites), `Admin_Controller.php`, and `Import_Controller.php`, each writing its own free-form message string with no shared prefix or format — e.g. `'Wicket clear next payment schedule and date.'`, `"Admin changing membership dates in MDP. ($starts_at - $ends_at)"`, `"$renew_order_flag membership post id ".$membership_post_id`. Nothing marks these notes as plugin-generated versus a human admin's own note, and formatting is inconsistent (some end in a period, some don't; some use string concatenation, some interpolation).

Proposed: `Subscription_Manager::add_order_note($order_or_sub, $message)` — for now, just prefixes every message with a standard tag (e.g. `[Wicket Memberships] `) before calling `->add_order_note()`, so notes are consistently identifiable at a glance in the WooCommerce order/subscription timeline. Same shape as `prepare_dates()`: starts as a thin bootstrapper, room to grow later (e.g. structured note types, consistent date formatting inside messages) without every call site needing to change again.

## TODO: full inventory of `update_dates()` call sites to migrate

Not actioned — noted here for future reference only. Every place in the plugin that calls `update_dates()` on a `WC_Subscription`, catalogued during the WWID-1869 investigation, so migrating them onto `Subscription_Manager` later doesn't require re-auditing the codebase from scratch:

| # | Location | Shape | Migrates cleanly? |
|---|---|---|---|
| 1 | `Membership_Controller.php:918` (Path B, live) | `end` + maybe `next_payment` | Yes — already migrated (`prepare_dates()`) |
| 2 | `Membership_Controller.php:925` (status-updated closure, Path B replay) | same array as #1 | Yes — already migrated (reuses #1's corrected array) |
| 3 | `Membership_Controller.php:910` (clear, `next_payment=''`) | clear-only | Yes |
| 4 | `Membership_Controller.php:556` (Path B4, forced next_payment) | `next_payment` only, `end` on subscription | Yes — already migrated (`prepare_dates()`) |
| 5 | `Membership_Controller.php:963` (status-updated closure, clear) | clear-only | Yes |
| 6 | `Admin_Controller.php:238-240` (cancel) | `end` only | Yes |
| 7 | `Admin_Controller.php:242-244` (cancel) | clear-only | Yes |
| 8 | `Admin_Controller.php:268-270` (expire) | `end` only | Yes |
| 9 | `Admin_Controller.php:272-274` (expire) | clear-only | Yes |
| 10 | `Membership_Subscription_Controller.php:79` (Path A, dead code) | `end` only | Yes |
| 11 | `Import_Controller.php:336` | `end` + `next_payment` together, has own inline guard | Yes — replace inline guard with a call to `Subscription_Manager` |
| 12 | `csv_post.php:128` (clear-only, debug utility) | clear-only | Yes |
| 13 | `catch_wicket_wipe_next_payment_date()` (`Membership_Controller.php:567-583`) | raw `$wpdb->update()` directly on `wp_postmeta`, bypasses `update_dates()`/WCS API entirely | **No — explicitly excluded from any future migration.** Different mechanism (writes `_schedule_next_payment` postmeta directly, then separately calls `$sub->delete_date('next_payment')`), poorly understood rationale for bypassing the normal API, and higher-risk to touch than the value of including it. Leave as-is; do not fold into `Subscription_Manager` or rewrite as part of this future work. |

2 of 13 (#1, #4) are already migrated as part of the WWID-1869 fix. The remaining 10 migratable sites (everything but #13) are mechanical: swap `$sub->update_dates($dates)` for a call through `Subscription_Manager` once it also performs the write (see the `prepare_dates()` → `update_dates()` rename plan above). Touches 5 files total (`Membership_Controller.php`, `Admin_Controller.php`, `Membership_Subscription_Controller.php`, `Import_Controller.php`, `csv_post.php`) — small individual diffs, but broad regression-test surface since it's every date-mutation path in the plugin. Sized as its own deliberate follow-up, not something to batch into an unrelated change.

## Open design concern: one-way date sync (membership → subscription only)

Confirmed (investigated during the WWID-1869 fix): the membership CPT (`membership_ends_at`, `membership_expires_at`) is the sole source of truth for dates today. Dates are computed once, independently, via `Membership_Config::get_membership_dates()`, then pushed to the subscription via `update_dates()`. Nothing anywhere reads the subscription's actual stored `end`/`next_payment` back into the membership CPT — it's a one-way push, never reconciled.

This means any adjustment a guard makes on the subscription side (e.g. `prepare_dates()`'s 1-second `next_payment` nudge) is invisible to the membership record by design. Not a new divergence risk today — `end` is never adjusted by the existing guards, and the membership CPT has no `next_payment` equivalent to keep in sync in the first place, so there's nothing for today's fix to reconcile.

**Where this becomes a real problem**: if `Subscription_Manager` eventually becomes the sole writer of subscription state (per the roadmap above — memberships stop writing directly to subscriptions, going through this class instead), and especially if date *computation* itself ever moves into the subscription layer rather than just the write, this one-way-only relationship needs a deliberate decision:
- Does a subscription-side adjustment ever need to propagate back to the membership CPT, or does the membership stay intentionally unaware (current behavior)?
- If propagation is ever needed, what's the mechanism — read-back after `update_dates()`, a hook, something else?

Flagging this now as unresolved and needing further investigation before that migration happens — not blocking today's fix, but a structural question the eventual `Subscription_Manager` design needs to answer explicitly rather than inherit by accident.

## See Also

- [Membership_Controller](Class-Membership_Controller.md) — current owner of most subscription-touching logic not yet migrated here
