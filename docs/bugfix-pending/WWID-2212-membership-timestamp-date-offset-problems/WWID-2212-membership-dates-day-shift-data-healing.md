---
title: "WWID-2212 — Membership Date Shift: Data Healing Instructions"
audience: [developer, support]
php_class: Admin_Controller
source_files:
  - "includes/Admin_Controller.php"
  - "includes/Membership_Controller.php"
  - "includes/Membership_Post_Types.php"
  - "includes/Membership_WP_REST_Controller.php"
ticket: WWID-2212
status: pending — not yet run, and blocked on the code fixes
branch: feature/users-multi-tier-renewal-subscriptions-merged
---

# WWID-2212 — Membership Date Shift: Data Healing Instructions

> ## ⚠ Status: NOT STARTED, AND BLOCKED
>
> Neither phase in this document has been run, and neither can be until the code fixes in
> [WWID-2212 — Code Fixes](WWID-2212-membership-dates-off-by-one-day.md) are deployed — and **none
> of those fixes has been applied yet; three of the sites are still open.** Running either phase on
> today's code writes fresh wrong dates: a config re-save re-persists the same shifted day, and
> every record saved through the update API picks up a wrong early-renewal date.
>
> This is a runbook awaiting its prerequisite, not a record of work performed. It lives in
> `docs/bugfix-pending/` until both phases are complete — see
> [`docs/bugfix-pending/README.md`](../README.md).

Companion to
[WWID-2212 — Membership Dates Off By One Day: Code Fixes](WWID-2212-membership-dates-off-by-one-day.md). That
document explains why dates drifted a day and the code changes that stop it happening again.
This document holds the two **manual** phases, and is written to be handed to the data team.

## The three steps, in order

| # | Step | Owner | Purpose |
|---|---|---|---|
| 1 | Deploy the code changes — **not done; 3 sites open** | Engineering | Stops the conversions that shift a day. Prerequisite for both phases below — see [Preconditions](#1-preconditions--do-not-start-before-all-three-are-true) |
| 2 | **Phase 1** — re-save every calendar config's season dates in the admin — **not started** | Data team | Restores the intended day in `cycle_data`. **Completes the forward fix** |
| 3 | **Phase 2** — heal existing membership records — **not started** | Data team | Remediation of terms already written. Does not affect future terms |

**Steps 1 and 2 together are what solve the problem going forward, and neither is sufficient
alone.** Code alone leaves any season boundary already stored on the wrong MDP day still wrong —
the intended day is not in the data for any code to recover, so every new term and renewal on
that config stays shifted. A re-save alone, on the current code, re-persists the same wrong day
through the same broken conversion. The re-save must follow the deploy.

Phase 2 is a separate matter: it corrects history and changes nothing about future terms. It can
be scheduled independently, and if it is deferred, the forward fix still holds.

Both phases go through the plugin's own admin interfaces, never the database directly. For
membership records that means the **existing admin membership datetime update API** — adjusting
the dates in the plugin writes the change to the MDP automatically and updates the WooCommerce
subscription end date in the same request. Direct database writes to `membership_ends_at` /
`membership_expires_at` leave the MDP record and the subscription behind and must not be used.

---

## 1. Preconditions — do not start before all three are true

1. **The code fixes are deployed.** This gates *both* phases, not just Phase 2. On the
   current code a config re-save re-persists the same wrong day, and every save through the
   update API recomputes `membership_early_renew_at` from the end date via a conversion that is
   itself a day late in behind-UTC installs (Fix 3 in the code document,
   `includes/Admin_Controller.php:721-723`). Working before the deploy produces records with a
   correct end date and a wrong early-renewal date, and configs that look saved but are not
   corrected.
2. **The MDP timezone is confirmed resolved**, not silently defaulting:
   ```bash
   wp eval 'var_dump($_ENV["WICKET_MSHIP_MDP_TIMEZONE"] ?? "UNSET (falls back to UTC)");'
   ```
   If this returns `UNSET`, stop. Every date the API normalizes will be snapped to UTC day
   boundaries, and the healing run will bake in a *second* shift.
3. **Phase 1 is complete before Phase 2 begins.** Membership records are healed against the
   corrected season end date, so Phase 1 produces Phase 2's target values. Healing memberships
   while a config is still wrong also means the next renewal on that config re-creates the shift,
   and the run has to be repeated.

---

## 2. Phase 1 — re-save the config season dates (required, completes the forward fix)

Applies to calendar-cycle configs. Anniversary configs have no stored boundary to correct; their
end dates are computed, so for those the code deploy alone is the whole forward fix.

This phase is what makes the deploy effective. Until it is done, an affected config keeps minting
shifted terms even on fixed code, because the wrong day is in the stored data rather than in the
logic. It cannot be scripted from the stored value — a boundary sitting on the wrong MDP day is
indistinguishable from a boundary an operator genuinely set to that day, so the intended date has
to be re-entered by a human who knows it.

### 1A. Inventory what is stored

```bash
wp eval 'var_export(get_post_meta(<CONFIG_POST_ID>, "cycle_data", true)["calendar_items"]);'
```

For a season intended to end **2026-11-30**, in an `America/Toronto` install:

| Stored `end_date` | Verdict | Action |
|---|---|---|
| `2026-11-30` | legacy date-only, reads correctly on current code | Re-save to normalize (optional, tidies the shape) |
| `2026-12-01T04:59:59…Z` | correct end-of-day for Nov 30 | Leave alone |
| `2026-11-30T04:59:59…Z` | **wrong** — this is a Nov 29 end-of-day | Correct it (A2) |
| `2026-11-30T00:00:00…Z` | **wrong** — start-of-day shape on an end date, reads as Nov 29 | Correct it (A2) |

Read the stored instant in the MDP timezone to see what day it actually represents — the date
part of the stored string is *not* the business day:

```bash
wp eval '$tz = new DateTimeZone($_ENV["WICKET_MSHIP_MDP_TIMEZONE"]);
foreach (get_post_meta(<CONFIG_POST_ID>, "cycle_data", true)["calendar_items"] as $s) {
  printf("%-24s start %s  end %s\n", $s["season_name"],
    (new DateTime($s["start_date"]))->setTimezone($tz)->format("Y-m-d H:i:s"),
    (new DateTime($s["end_date"]))->setTimezone($tz)->format("Y-m-d H:i:s"));
}';
```

The `end` column is what the plugin will use. If it reads `2026-11-29 23:59:59`, that season is
shifted.

### 1B. Correct a shifted season

Use the config admin screen (Membership Configs → edit → Seasons → edit the season) and
**explicitly re-pick the intended date in the date picker**. Do not simply open and save: the
picker is pre-filled from the wrong stored instant, so a plain save re-writes the same wrong
day. Re-picking Nov 30 makes the UI compute a fresh end-of-day instant for Nov 30.

Two constraints:

- **Correct every season in the config in one sitting.** The save-time normalizer
  (`includes/Membership_Post_Types.php:1143`) rewrites all seasons together, and the
  adjacency/overlap validators (`includes/Membership_Post_Types.php:357-403`) compare seasons
  against each other. A config with mixed shapes can reject a single-season correction with
  *"The season dates must not overlap"* or *"must not be empty"* — that is the validator seeing
  a corrected boundary next to an uncorrected neighbour, not a real overlap.
- **Verify with the 1A command after saving**, not by eye on the screen. The screen renders
  through the same timezone assumption that produced the problem.

### 1C. Record what changed

For each config: post ID, season name, stored value before, stored value after, MDP-local end
date before and after. Phase 2 needs the corrected season end as its target value.

---

## 3. Phase 2 — heal existing membership records (remediation of history)

### 2A. The tool

**Endpoint**

```
POST /wp-json/wicket_member/v1/membership_entity/<membership_post_id>/update
```

Registered at `includes/Membership_WP_REST_Controller.php:160`; handled by
`Admin_Controller::update_membership_entity_record()` (`includes/Admin_Controller.php:636`).
This is the same endpoint the admin membership edit screen posts to, so the screen is a valid
way to do this one record at a time — the API is for volume.

**Payload**

| Field | Required | Notes |
|---|---|---|
| `membership_starts_at` | yes | All three dates must be present on every call, even the ones not changing. Omitting any returns *"Membership update failed. All dates required."* |
| `membership_ends_at` | yes | |
| `membership_expires_at` | yes | |
| `renewal_type` | yes in practice | Read unconditionally at `includes/Admin_Controller.php:747`. Send the record's **current** value (`inherited`, `sequential_logic`, `form_flow`, `subscription`, `current_tier`) or the save will re-point the renewal target as a side effect |
| `membership_post_id` | supplied by the URL | The route parameter is merged into the params, so it does not need to be in the body |

**Send bare `Y-m-d` dates, never ISO instants.** The endpoint normalizes on entry
(`includes/Admin_Controller.php:640-642`):

```php
$membership_starts_at  = Utilities::get_mdp_day_start( $data['membership_starts_at'] );
$membership_ends_at    = Utilities::get_mdp_day_end(   $data['membership_ends_at'] );
$membership_expires_at = Utilities::get_mdp_day_end(   $data['membership_expires_at'] );
```

A bare `2026-11-30` is parsed **as a calendar day in the MDP timezone** and snapped to
`00:00:00` / `23:59:59` correctly. An ISO instant is converted from whatever offset it carries,
which is how the original shift happened. Bare dates are unambiguous; use them.

Example:

```bash
curl -sS -X POST \
  "https://<site>/wp-json/wicket_member/v1/membership_entity/12345/update" \
  -H "Content-Type: application/json" \
  -u "<user>:<application-password>" \
  -d '{"membership_starts_at":"2025-12-01","membership_ends_at":"2026-11-30","membership_expires_at":"2027-02-28","renewal_type":"inherited"}'
```

**Reading the response.** The endpoint returns **HTTP 200 even on failure**, with the reason in
an `error` key (`includes/Admin_Controller.php:646-663`, `768-776`, `785-792`). Never treat a
200 as success — parse the body and require a `success` key. A failed MDP write is reported
this way too, and the local meta is rolled back to its previous values before the error is
returned (`includes/Admin_Controller.php:786-788`), so a failed call leaves the record as it
was rather than half-updated.

**Validation to expect**

- `start < end <= expiry`, else *"Invalid date sequence."* (`includes/Admin_Controller.php:645`)
- Cancelled memberships are refused outright: *"Cannot update a cancelled membership record."*
  (`includes/Admin_Controller.php:673`) — escalate, do not work around.
- The tier must resolve from `membership_tier_uuid`, else *"Membership tier not found."*
  (`includes/Admin_Controller.php:708`)

### 2B. What one call does automatically

This is why the API is the required route rather than a database update:

1. Normalizes the three dates to MDP day boundaries stored as UTC.
2. Recomputes `membership_early_renew_at` from the end date and the config's renewal window
   (`includes/Admin_Controller.php:721-723`).
3. Re-derives `membership_grace_period_days` as the day difference between the end and expiry
   dates you supplied (`includes/Admin_Controller.php:726`). **The grace period is inferred from
   your input, not read from the config** — if you submit an end/expiry pair that is not
   `grace_days` apart, you silently rewrite the record's grace period.
4. Writes the local record and **recomputes the membership status** against today —
   delayed / active / grace / expired (`Membership_Controller::update_local_membership_record()`,
   `includes/Membership_Controller.php:1412-1421`).
5. **Pushes the change to the MDP** (`update_mdp_record()`), rolling the local record back if
   the MDP write fails.
6. Refreshes the customer's user-meta membership JSON (`amend_membership_json()`).
7. **Updates the WooCommerce subscription**
   (`Membership_Controller::update_membership_subscription()`): sets the subscription `end` from
   `membership_expires_at` — or from `membership_ends_at` for the monthly subscription-renewal
   flow — and sets `next_payment` from the end date when the membership has one, then writes an
   order note recording the change.

### 2C. Determining the correct dates

Rules, in order of precedence:

- **End date** = the corrected season end for the config that governs the membership
  (Phase 1 output). For anniversary configs, the term start plus the configured period,
  read as an MDP calendar day.
- **Expiry** = end date + the config's grace-period days, as calendar days. For the reported
  case: `2026-11-30` + 90 = `2027-02-28`. Verify against the config rather than adding a day to
  the stored expiry — the stored expiry may carry its own independent shift (see "the two shifts
  cancel" in the code document).
- **Start date** = unchanged, unless it is itself shifted. A renewal's start is
  `previous term's end + 1 day`, so a shifted end date on term *N* has usually shifted the start
  of term *N+1* as well.
- **Chains.** Where a member has consecutive terms, heal them **oldest first**, because each
  term's start is derived from the previous term's end. Healing the newest first can leave a gap
  or an overlap between terms.

Do not infer the intended date by adding one day to whatever is stored. Derive it from the
config, then compare with what is stored. Records whose stored dates are *correct* must be left
alone, and a blanket +1 day would break them.

### 2D. Building the work list

Starting point for the inventory. **Run it as a dry run and have the output reviewed before any
write** — it was written from the code, not executed against a live database, so treat its
output as a candidate list to be confirmed, not as instructions:

```bash
wp eval '
$tz = new DateTimeZone($_ENV["WICKET_MSHIP_MDP_TIMEZONE"]);
$posts = get_posts([
  "post_type"   => \Wicket\Memberships\Helper::get_membership_cpt_slug(),
  "post_status" => "publish",
  "numberposts" => -1,
  "fields"      => "ids",
]);
printf("%-8s %-12s %-12s %-12s %-12s %s\n", "POST", "STATUS", "START", "END", "EXPIRY", "TIER_UUID");
foreach ($posts as $id) {
  $m = \Wicket\Memberships\Helper::get_post_meta($id);
  if (empty($m["membership_ends_at"])) { continue; }
  $fmt = function($v) use ($tz) {
    return $v ? (new DateTime($v))->setTimezone($tz)->format("Y-m-d") : "-";
  };
  printf("%-8s %-12s %-12s %-12s %-12s %s\n",
    $id,
    $m["membership_status"] ?? "-",
    $fmt($m["membership_starts_at"] ?? ""),
    $fmt($m["membership_ends_at"]),
    $fmt($m["membership_expires_at"] ?? ""),
    $m["membership_tier_uuid"] ?? "-");
}' > /tmp/membership-dates-inventory.txt
```

The `END` column is the MDP-local calendar day the plugin treats as the term end — that is the
value to compare against the corrected season end. Group the output by tier UUID; every
membership on a tier whose config season was corrected in Phase 1 is a candidate, and the
shift should be uniform across the group. A membership in the group whose end date does *not*
match the others is a signal to inspect, not to bulk-correct.

### 2E. Order of operations for a healing run

1. Confirm the three preconditions (§1).
2. Complete Phase 1 for every affected config, and record the corrected season ends.
3. Produce the inventory (2D) and agree the target dates per group (2C).
4. Capture the before-state for every record in the run: post ID, `membership_starts_at`,
   `membership_ends_at`, `membership_expires_at`, `membership_early_renew_at`,
   `membership_grace_period_days`, `membership_status`,
   `membership_subscription_id`, and the subscription's current `end` and `next_payment`. There is
   no automated rollback; this capture *is* the rollback plan.
5. Heal **one record** end to end and verify it fully (2F) before proceeding.
6. Work in small batches, oldest term first within each member's chain. Check the response body
   of every call.
7. Re-run the inventory afterwards and diff against the target list.

### 2F. Per-record verification

After each call, confirm all five:

- `membership_ends_at` and `membership_expires_at` read as the intended MDP-local days.
- `membership_early_renew_at` matches `end − renewal window days` for the config.
- `membership_grace_period_days` is unchanged from before the call (if it moved, the end/expiry
  pair submitted was not grace-days apart — see 2B.3).
- `membership_status` is still correct for today's date. A one-day move across a boundary can
  flip active↔grace or grace↔expired; §4 covers what to do when it does.
- The WooCommerce subscription's `end` date moved to match, and an order note recording the
  change was written to the subscription.

Then confirm the MDP record shows the new dates, since that write is what downstream Wicket
systems read.

---

## 4. Cases to escalate rather than heal

| Case | Why | Action |
|---|---|---|
| Cancelled memberships | The endpoint refuses them outright (`includes/Admin_Controller.php:673`) | Escalate; needs a product decision on whether a cancelled term's dates matter |
| A one-day move changes the status | The status is recomputed on save; a member can flip active→grace or grace→expired, which can trigger member-facing effects | Escalate before saving; batch these separately with the effects understood |
| Monthly subscription-renewal memberships | The subscription `end` comes from `membership_ends_at`, not `membership_expires_at` (`Membership_Controller::update_membership_subscription()`), so the subscription moves differently from other tiers | Heal, then verify the subscription explicitly |
| No subscription linked, or the subscription is already ended | The subscription branch is skipped silently | Heal, then record that no subscription update occurred |
| Organization / per-seat memberships | The MDP push carries seat counts; a failed push rolls the local record back and must be retried, not re-derived | Heal one first and confirm the seat count survived |
| Memberships whose config season is *not* the one that was corrected | The membership may be shifted for a different reason, or not shifted at all | Inspect individually; do not bulk-correct |
| Terms that have already been renewed onto a later term | Correcting term *N* changes the derived start of term *N+1* | Heal the whole chain in one pass, oldest first, or leave the chain alone |
| Records where the intended date cannot be established | A stored `2026-11-30T04:59:59Z` is a valid Nov 29 end-of-day; nothing in the data distinguishes a shifted Nov 30 from a deliberate Nov 29 | Escalate; the intended date has to come from the config, the order, or the member's agreement — never from a guess |

---

## 5. What healing does not fix

- **Order notes and log lines already written** carry the old dates. They are the audit trail and
  are not rewritten by a healing run; a note recording the correction is added to the subscription
  by the update itself.
- **AutomateWoo triggers that already fired** (`automate-woo/triggers/wicket_mship_end_date.php`,
  `wicket_mship_renew_early.php`) are not un-fired, and moving an end date forward can re-arm one.
  Check the workflow logs for any tier where the end date moved into the future.
- **`add_membership_ends_at` scheduled actions** are keyed to the old end timestamp
  (`includes/Membership_Controller.php:635,653`). The update API does not re-schedule them; confirm
  with the data team whether any healed membership has a pending action that now fires on the wrong
  day.
- **Renewal orders already placed** against a shifted term keep their original dates in the order
  record.

## See also

- [Membership Dates Off By One Day — Code Fixes](WWID-2212-membership-dates-off-by-one-day.md) — the cause, the six broken sites, and the fixes that must ship before this runbook is used
- [The Season Date Write Path — Three Fixes, Still Broken](WWID-2212-season-date-write-path-partial-fixes.md) — which commits actually touched the season date write path, and why `ad8c900` / `fec6a0d` are cited as fixing it but did not
- [Admin_Controller — Class Reference](../../engineering/Class-Admin_Controller.md)
- [Membership_Controller — Class Reference](../../engineering/Class-Membership_Controller.md) — `update_local_membership_record()`, `update_mdp_record()`, `update_membership_subscription()`
- [Membership_Config Data Structure](../../engineering/membership_config_data_structure.md) — `cycle_data` / `calendar_items` shape
