---
title: "WWID-2212 — Membership Dates Off By One Day: Code Fixes for the Timestamp Regression"
audience: [developer]
php_class: Membership_Config
source_files:
  - "includes/Membership_Config.php"
  - "includes/Utilities.php"
  - "includes/Membership_Post_Types.php"
  - "includes/Admin_Controller.php"
  - "frontend/src/membership_configs/edit.js"
ticket: WWID-2212
status: pending — no code change applied
branch: feature/users-multi-tier-renewal-subscriptions-merged
---

# WWID-2212 — Membership Dates Off By One Day: Code Fixes for the Timestamp Regression

> ## ⚠ Status: NOT APPLIED
>
> **No code in this repair has been written.** Every fix below is specified and verified against a
> reproduction harness, but the codebase still contains all of it unrepaired. Three code sites are
> open, two were repaired by earlier partial passes, and both manual phases (config re-save, record
> healing) are outstanding.
>
> This document lives in `docs/bugfix-pending/` for that reason. When the work ships, it moves to
> `docs/bugfix/` — see [`docs/bugfix-pending/README.md`](../README.md).

Reported case: a calendar-cycle config with a season ending **2026-11-30** and a 90-day
grace period produced a membership whose end date was **2026-11-29** and whose expiry was
**2027-02-27 23:59:59**, with the WooCommerce subscription `end` date matching the expiry.
Expected: **2026-11-30** and **2027-02-28**.

| Field | Expected | Observed |
|---|---|---|
| Season `end_date` (config) | 2026-11-30 | 2026-11-30 *(as stored)* |
| `membership_ends_at` | 2026-11-30 | **2026-11-29** |
| `membership_expires_at` (end + 90d) | 2027-02-28 | **2027-02-27 23:59:59** |
| Subscription `end` | 2027-02-28 | matches the wrong expiry |

The grace arithmetic is not broken in isolation: 90 days after Nov 29 *is* Feb 27. Both
observed values follow from one wrong end date — plus a second, opposite-direction error in
the grace path that happens to cancel out (see
[The two shifts cancel](#the-two-shifts-cancel-which-is-why-it-looks-internally-consistent)).

This is not one bug. It is a **family** of off-by-one-day errors introduced when date-only
storage was replaced with timestamps, all sharing one mechanism: **a calendar date carried
as an instant in time, then truncated back to a calendar day in the wrong timezone.** Six
sites are catalogued below. The direction of each error — a day early or a day late —
depends on which conversion is wrong and on whether the MDP timezone is behind or ahead of
UTC.

## Outstanding work at a glance

Nothing in the **Fix** column has been applied.

| # | Site | State | Fix |
|---|---|---|---|
| 1 | Calendar-cycle end date (`Membership_Config.php:244-245` → `353-371`) | **Partially repaired.** `ad8c900` fixed the legacy date-only shape. A boundary already stored on the wrong MDP day is beyond any code fix | Fix 5 + **Phase 1** (manual re-save) |
| 2 | Grace / early-renewal anchors (`Membership_Config.php:428,436,548,554`) | **Repaired** by `fec6a0d` | none — listed because records written earlier carry its output |
| 3 | Tier-switch expiry (`Admin_Controller.php:1482`) | **Open** — a day late in behind-UTC installs | Fix 2 |
| 4 | Admin-edit early-renewal recompute (`Admin_Controller.php:721-723`) | **Open** — a day late, and fires on *every* admin save | Fix 3 — deploy before any manual phase |
| 5 | Anniversary term end (`Membership_Config.php:400`, `:525`) | **Open** — a day early in ahead-of-UTC installs | Fix 4 |
| 6 | Importer expiry (`Import_Controller.php:80,192`) | **Open, unverified** | out of scope; see Known remaining exposure |
| — | `mdp_local_ymd()` private to `Membership_Config` | **Open** — the reason site 3 hand-rolled a broken copy | Fix 1 |
| — | Season boundaries stored as instants | **Open** — the shape that makes all of this possible | Fix 5 (separate PR) |
| — | Silent `'UTC'` fallbacks, PHP and React | **Open** — wrong dates instead of an error | Fix 6 |

Manual work, also outstanding: **Phase 1** (config season re-save — required, completes the forward
fix) and **Phase 2** (heal existing records — remediation), both in
[the healing document](WWID-2212-membership-dates-day-shift-data-healing.md).

## Fixing this takes two steps, in this order — neither is sufficient alone

**Step 1 — deploy the code changes** in [The repair](#the-repair) below.

**Step 2 — then manually re-save every calendar config's season dates in the admin**, picking
the intended day in each season's date picker. Procedure:
[Data Healing, Phase 1](WWID-2212-membership-dates-day-shift-data-healing.md#2-phase-1--re-save-the-config-season-dates-required-completes-the-forward-fix).

The order is not interchangeable, and stopping after either one leaves the bug live:

- **Code without the re-save.** Any season boundary already stored as an instant sitting on the
  wrong MDP day stays wrong. The corrected code reads that instant faithfully and keeps
  producing an end date a day early — the intended day is not present in the data for any code
  to recover. Every new term and every renewal on that config is still shifted.
- **Re-save without the code.** The current write path re-derives the boundary through the same
  broken conversion, so a save re-persists the same wrong day (see
  [the trap in the normalizer](#what-the-season-save-does-to-the-date-and-time-specifically)).
  The re-save has to run on the fixed code to take effect.

**Together, these two steps close the problem going forward** — every date computed after them
is correct. They do nothing for terms already written; that is remediation, and it is
[Phase 2](WWID-2212-membership-dates-day-shift-data-healing.md#3-phase-2--heal-existing-membership-records-remediation-of-history)
of the healing document.

**Scope of this document: the code changes (Step 1).** Both manual phases live in
[Membership Date Shift — Data Healing Instructions](WWID-2212-membership-dates-day-shift-data-healing.md).
Note in particular that
[Fix 3](#fix-3--admin-edit-early-renewal-recompute-admin_controllerphp721-723) must be deployed
before either phase: it fires on every admin save, so any record or config touched beforehand
picks up a wrong early-renewal date.

---

## Cause

### The pre-timestamp contract, and why this class of bug could not occur

Before the timestamp work, calendar season boundaries were stored **date-only** and read
back date-only. The read path (`includes/Membership_Config.php`, pre-`adc517f`):

```php
// get_calendar_seasons(), pre-adc517f
$seasons[ $key ]['start_date'] = (new \DateTime( date("Y-m-d", strtotime( $season['start_date'] )), wp_timezone() ))->format('c');
$seasons[ $key ]['end_date']   = (new \DateTime( date("Y-m-d", strtotime( $season['end_date'] )),   wp_timezone() ))->format('c');
```

And the admin UI stored exactly what the picker produced — a bare `Y-m-d`, no time
component at all:

```js
// frontend/src/membership_configs/edit.js, pre-19fb38d
end_date: moment(value).format("YYYY-MM-DD"),   // "2026-11-30"
```

The important property: **`2026-11-30` is a calendar date, not an instant.** It has no
timezone, so no timezone conversion can move it. The time component was appended as
`00:00:00` in the *site* timezone at read time, and every consumer that wanted the day back
read the first ten characters. A season boundary was incapable of drifting, which is why
this never surfaced before.

### What the timestamp standardization changed

| Commit | Date | Change |
|---|---|---|
| `adc517f` | 2026-01-26 | *"Standardizing data to MDP TZ but saving in UTC across the board"* — introduced `Utilities::get_mdp_day_start()` / `get_mdp_day_end()` |
| `19fb38d` | 2026-05-11 | *"fix: Calender configs were incorrectly storing dates"* — admin UI switched to writing full ISO UTC instants; PHP added an explicit UTC round-trip before the day snap |
| `ad8c900` | 2026-08-05 | *"fix: normalize calendar season dates and fix bare-date timezone shift"* — added `parse_as_mdp_date()` and a save-time normalizer |
| `fec6a0d` | 2026-08-11 | *"fix(dates): stop calendar season and grace dates drifting a day"* — added `mdp_local_ymd()` for the grace / early-renewal anchors |

`adc517f` is where the defect enters. As introduced, the helpers **relabel** whatever they
are given into the MDP timezone before snapping the time:

```php
// includes/Utilities.php as of adc517f .. pre-ad8c900
$mdp_timezone = new \DateTimeZone($_ENV['WICKET_MSHIP_MDP_TIMEZONE'] ?? 'UTC');
$mdp_date = new \DateTime($date_string);   // bare "2026-11-30" -> parsed in PHP's default tz (UTC under WordPress)
$mdp_date->setTimezone($mdp_timezone);    // -> 2026-11-29 19:00 in America/Toronto
$mdp_date->setTime(23, 59, 59);           // -> 2026-11-29 23:59:59  <-- the day is now permanently wrong
return $mdp_date->setTimezone(new \DateTimeZone('UTC'));
```

WordPress forces PHP's default timezone to UTC, so a bare `2026-11-30` is read as **UTC
midnight**, and for any MDP timezone behind UTC that instant belongs to the **previous** MDP
calendar day. `setTime(23,59,59)` then locks the wrong day in. The legacy date-only data was
fine; the new helpers could not read it.

`19fb38d` entrenched it by making the UTC round-trip explicit on the read path:

```php
// includes/Membership_Config.php @19fb38d — get_calendar_seasons()
$end_dt = new \DateTime( $season['end_date'], new \DateTimeZone('UTC') );   // "2026-11-30T00:00:00+00:00"
$seasons[ $key ]['end_date'] = Utilities::get_mdp_day_end( $end_dt->format('c') )->format('c');
```

### The exact chain that produces the reported numbers

Reproduced end-to-end (MDP timezone `America/Toronto`, PHP default `UTC` as WordPress sets
it, legacy season `end_date` = `2026-11-30`, grace 90 days):

```
stored end_date                   : 2026-11-30                      <- date-only, pre-timestamp shape
UTC round-trip (19fb38d)          : 2026-11-30T00:00:00+00:00
get_calendar_seasons() end_date   : 2026-11-30T04:59:59+00:00   local 2026-11-29 23:59:59
membership_ends_at                : 2026-11-30T04:59:59+00:00   local 2026-11-29 23:59:59   <- reported
grace: +90d then format('Y-m-d')  : 2027-02-28                      <- truncated in UTC, not MDP
membership_expires_at             : 2027-02-28T04:59:59+00:00   local 2027-02-27 23:59:59   <- reported
subscription 'end'                : 2027-02-28 04:59:59 (UTC render of the same instant)
```

`get_seasonal_end_date()` passes the season boundary straight through to
`membership_ends_at` (`includes/Membership_Config.php:353-371`), so the shift lands in the
membership record verbatim, then in the MDP record, then in the subscription end date via
`includes/Membership_Subscription_Controller.php:76`.

### The two shifts cancel, which is why it looks internally consistent

The grace block *before* `fec6a0d` truncated in UTC:

```php
// includes/Membership_Config.php @92b884c — pre-fec6a0d
$expires_at_utc = new \DateTime($dates['end_date'], new \DateTimeZone('UTC'));
$expires_date_string = $expires_at_utc->modify('+'.$grace_period_in_days.' days')->format('Y-m-d');
$dates['expires_at'] = Utilities::get_mdp_day_end($expires_date_string)->format('c');
```

From the (already wrong) `2026-11-30T04:59:59Z` end instant, `+90 days` and a UTC truncation
give `2027-02-28` — a day **later** than the wrong end date warrants. That bare `2027-02-28`
then goes back through `get_mdp_day_end()`, which shifts it a day **earlier** again, landing
on `2027-02-27 23:59:59`. Two independent conversion errors in opposite directions produce a
pair of dates that are 90 days apart and therefore look self-consistent. Do not read the
internal consistency as evidence that only the end date is wrong: there are two defects on
this path, and fixing one without the other will make the expiry visibly disagree with the
end date.

### The general rule (why some fields land early and others late)

Once an end-of-day instant is stored in UTC, the *stored date string* is not the business
day:

| MDP timezone | Nov 30 23:59:59 local, stored as UTC | Naive `Y-m-d` of the stored value |
|---|---|---|
| `America/Toronto` (behind UTC) | `2026-12-01T04:59:59Z` | **2026-12-01** — a day late |
| `UTC` | `2026-11-30T23:59:59Z` | 2026-11-30 — correct |
| `Australia/Sydney` (ahead of UTC) | `2026-11-30T12:59:59Z` | 2026-11-30 — correct |

So, in behind-UTC zones:

- Any consumer that truncates a stored **end-of-day instant** with `format('Y-m-d')` or
  `date('Y-m-d', strtotime(...))` reads **a day late**.
- Any **legacy bare date** pushed through `get_mdp_day_start/end` reads **a day early**.
- A **start-of-day instant** (`…T00:00:00Z`-shaped) read as an MDP day is **a day early**.

In ahead-of-UTC zones the anniversary path fails instead, because a start-of-day MDP instant
sits on the previous UTC day. Every timezone breaks something; only `UTC` is safe throughout,
which is why this is invisible on UTC-configured sites.

### What the season save does to the date and time, specifically

This is the part worth internalising, because the stored value no longer resembles the value
the operator picked.

**The write.** `frontend/src/membership_configs/edit.js:180-184`:

```js
const convertSeasonDate = (dateValue, isEndDate = false) => {
  const m = moment.tz([dateValue.getFullYear(), dateValue.getMonth(), dateValue.getDate()], mdpTimezone);
  return isEndDate ? m.endOf('day').utc().toISOString() : m.startOf('day').utc().toISOString();
};
```

Picking **Nov 30** as a season end in an `America/Toronto` install stores
`2026-12-01T04:59:59.999Z`. The date part of the stored string is **December 1**. The
operator's Nov 30 exists only as `stored instant + MDP timezone`; drop the timezone anywhere
downstream and the season silently becomes a December season. Start dates are stored at
`00:00:00` of the MDP day (`2026-12-01T05:00:00.000Z` for a Dec 1 start), so a season's start
and end are now *asymmetric* in shape — a start-of-day instant and an end-of-day instant —
and they fail in opposite directions when mishandled.

**No data migration accompanied the change.** Only the season whose picker was actually
touched got rewritten; the rest kept their bare `Y-m-d`, because until `ad8c900` nothing
normalized `calendar_items` on save. `ad8c900`'s own commit message records the consequence:
configs with three or more seasons ended up with one normalized season and several legacy
ones, and the adjacency/overlap validators
(`includes/Membership_Post_Types.php:357-403`) then rejected any attempt to correct a single
season in isolation, because a corrected boundary no longer lined up with its un-normalized
neighbour.

**The save-time normalizer, and the trap in it.** `includes/Membership_Post_Types.php:1143`
(wired into the `cycle_data` sanitize callback at lines 289 and 329) now re-derives every
season boundary on every config save:

```php
$calendar_items[ $key ]['end_date'] = Utilities::get_mdp_day_end( $item['end_date'] )->format( 'c' );
```

For a bare `2026-11-30` this self-heals (post-`ad8c900`, bare dates parse in the MDP
timezone). But it is **idempotent on an already-shifted instant**: feed it a value that
already sits on the previous MDP day and it re-snaps that wrong day to 23:59:59 and persists
it. A single save can therefore convert a read-time bug into a permanently wrong stored
value, after which the config screen, the computation, and the membership record all agree on
the wrong day and nothing looks anomalous.

**Timezone source divergence.** PHP resolves the zone from
`$_ENV['WICKET_MSHIP_MDP_TIMEZONE'] ?? 'UTC'`; the React pages resolve it from
`PLUGIN_SETTINGS.WICKET_MSHIP_MDP_TIMEZONE || 'UTC'`. Both fall back to `UTC` silently and
independently. If one resolves and the other falls back, season dates drift a day on open or
on save with no error anywhere — and a config written under one resolution is read under the
other.

### Establishing which state a given config is in

```bash
# 1. which zone does PHP actually resolve?
wp eval 'var_dump($_ENV["WICKET_MSHIP_MDP_TIMEZONE"] ?? "UNSET (falls back to UTC)");'

# 2. what is literally stored for the season?
wp eval 'var_export(get_post_meta(<CONFIG_POST_ID>, "cycle_data", true)["calendar_items"]);'

# 3. what does the read path return today?
wp eval '$c = new \Wicket\Memberships\Membership_Config(<CONFIG_POST_ID>); var_export($c->get_calendar_seasons());'
```

Interpretation of (2), for a season intended to end 2026-11-30:

| Stored `end_date` | Meaning |
|---|---|
| `2026-11-30` | legacy date-only. Correct under current code; was a day early on builds between `19fb38d` and `ad8c900` |
| `2026-12-01T04:59:59…Z` (behind-UTC zone) | correctly normalized end-of-day for Nov 30 — the config is fine, look downstream |
| `2026-11-30T04:59:59…Z` (behind-UTC zone) | **persisted shift** — a Nov 29 end-of-day; the intended day is already lost from the data |
| `2026-11-30T00:00:00…Z` | start-of-day shape on an end date — reads as Nov 29 in any behind-UTC zone |

The last two rows are data damage, not a code bug, and belong to the
[data healing document](WWID-2212-membership-dates-day-shift-data-healing.md). The rest of this
document is the code.

---

## Effect

Six failure modes with different blast radii. Status is relative to the released code on
`feature/users-multi-tier-renewal-subscriptions-merged` (1.0.122).

### 1. Calendar-cycle end date a day early — *the reported bug*

`includes/Membership_Config.php:244-245` → `353-371`. Behind-UTC MDP zones, legacy or
already-shifted season boundaries. Fixed by `ad8c900` for the legacy date-only shape; **not**
fixed for a persisted shifted instant, which no code change can recover.

Propagates to: `membership_ends_at` / `membership_expires_at`
(`includes/Membership_Controller.php:324`), the MDP membership record, the WooCommerce
subscription `end` (`includes/Membership_Subscription_Controller.php:76`), the
`add_membership_ends_at` scheduled action (`includes/Membership_Controller.php:635,653`), the
active→grace→expired transitions in `update_local_membership_record()`
(`includes/Membership_Controller.php:1412-1421`), renewal callouts, and the *next* term — a
renewal start is `previous end + 1 day` (`includes/Membership_Config.php:295,313,334`), so one
wrong end date shifts every subsequent term until someone intervenes.

### 2. Grace / early-renewal anchor truncated in UTC — a day late

Pre-`fec6a0d` grace and early-renewal blocks. **Fixed** by `mdp_local_ymd()`
(`includes/Membership_Config.php:428,436` and `548,554`). Listed because it is what masked
failure mode 1, and because records written before that fix carry its output.

### 3. Tier-switch expiry a day late — **open**

`includes/Admin_Controller.php:1482`, `derive_switch_expiry()`. `format('Y-m-d')` on a
UTC-offset instant. Verified: a correct Nov 30 end date plus 90 days yields **2027-03-01**
instead of 2027-02-28 in `America/Toronto`. The docblock claims it "mirrors
`Membership_Config::get_membership_dates()`" — it no longer does, since that method moved to
`mdp_local_ymd()` in `fec6a0d`. A switched membership gets an expiry one day later than the
same tier's create flow produces.

### 4. Admin-edit early-renewal recompute a day late — **open**

`includes/Admin_Controller.php:721-723`. Same UTC truncation. Verified: a 30-day window off a
Nov 30 end date yields **2026-11-01** instead of 2026-10-31 in `America/Toronto`.

This one matters disproportionately, because **every** admin save of a membership rewrites
`membership_early_renew_at` — including saves that had nothing to do with dates, and including
every save the data team will make while healing records. Fix it before any healing run.

### 5. Anniversary-cycle end date a day early in ahead-of-UTC zones — **open**

`includes/Membership_Config.php:400`, and the identical expression in
`get_membership_dates_for_start()` at `525`:

```php
$the_end_date = date("Y-m-d", strtotime($dates['start_date'] . "+".$period_count . " " . $period_type));
```

`$dates['start_date']` is an MDP midnight expressed as UTC. In an ahead-of-UTC zone that
instant sits on the *previous* UTC day, and `date()` truncates in PHP's default timezone
(UTC), so the term ends a day early. Verified: a Dec 1 2025 start in `Australia/Sydney` yields
**2026-11-30** where `UTC` yields 2026-12-01. This is the one place `mdp_local_ymd()` — added
in `fec6a0d` precisely for this class — was not applied. The `align_end_dates` variants at
`404-412` (`Y-m-1`, `Y-m-15`, `Y-m-t`) are computed from the same truncated instant and inherit
the shift, `Y-m-t` intermittently, since it only changes value when the lost day crosses a
month boundary.

### 6. Importer expiry — **open, unverified**

`includes/Import_Controller.php:80,192`:
`date('Y-m-d', strtotime($record['Ends_At'] . " + {grace} days"))`. Same family:
arithmetic-then-truncate in the server timezone. Direction depends on the shape of `Ends_At`
in the CSV; not exercised here.

---

## The repair

**Not yet applied.** Recorded here so the approach is reviewable before it touches a date
engine four controllers depend on. Fixes 1–4 are mechanical and low-risk; Fix 5 changes a
stored data shape and should be a separate PR.

### Fix 1 — promote the MDP-local truncation helper to `Utilities`

`mdp_local_ymd()` is private to `Membership_Config` (`includes/Membership_Config.php:454`),
which is exactly why `derive_switch_expiry()` hand-rolled a broken copy. Move it to
`Utilities` alongside `parse_as_mdp_date()` and the day-boundary helpers, keeping the
signature:

```php
Utilities::mdp_local_ymd( string $iso_date, string $modify = '' ) : string
```

It is the only correct way to get a calendar day out of a stored instant: convert to the MDP
zone **first**, then apply the offset, then truncate. Have `Membership_Config` delegate to it
so there is one implementation. Every fix below is a call to this helper.

### Fix 2 — tier-switch expiry (`Admin_Controller.php:1482`)

```php
// before
$expires_date = ( new \DateTime( $end_date_iso, new \DateTimeZone( 'UTC' ) ) )
  ->modify( '+' . $grace_days . ' days' )
  ->format( 'Y-m-d' );

// after
$expires_date = Utilities::mdp_local_ymd( $end_date_iso, '+' . $grace_days . ' days' );
```

Update the docblock's "Mirrors `Membership_Config::get_membership_dates()`" claim — it becomes
true again.

### Fix 3 — admin-edit early-renewal recompute (`Admin_Controller.php:721-723`)

```php
// before
$membership_early_renew_at = clone $membership_ends_at;
$membership_early_renew_at->modify("-$renewal_window_days days");
$membership_early_renew_at = Utilities::get_mdp_day_start($membership_early_renew_at->format('Y-m-d'));

// after
$membership_early_renew_at = Utilities::get_mdp_day_start(
  Utilities::mdp_local_ymd( $membership_ends_at->format('c'), "-$renewal_window_days days" )
);
```

Ship this before any data healing: it fires on every admin membership save.

### Fix 4 — anniversary term end (`Membership_Config.php:400` and `:525`)

```php
// before
$the_end_date = date("Y-m-d", strtotime($dates['start_date'] . "+".$period_count . " " . $period_type));

// after
$the_end_date = Utilities::mdp_local_ymd( $dates['start_date'], '+' . $period_count . ' ' . $period_type );
```

The `align_end_dates` branches at `404-412` and `529-539` must then align **from that bare
day**, not from a re-truncated instant:

```php
case 'last-day-of-month':
  $the_end_date = date( 'Y-m-t', strtotime( $the_end_date ) );   // $the_end_date is now a bare Y-m-d
```

A bare `Y-m-d` parses and formats in the same frame, so this is timezone-safe. Note the
existing `Y-m-1` should be `Y-m-01` for consistency; `date()` accepts both, but the
inconsistency invites a copy-paste error later.

### Fix 5 — stop carrying season boundaries as instants (separate PR)

Season boundaries are **calendar dates**, not moments. Store them as bare `Y-m-d` in
`cycle_data` and construct instants only at the point of use, where the MDP timezone is known:

1. `frontend/src/membership_configs/edit.js:180-184` — submit `Y-m-d`; drop the
   `endOf('day').utc().toISOString()` conversion. Display then needs no timezone at all
   (`edit.js:885,888` can render the stored string directly).
2. `includes/Membership_Post_Types.php:1143` — normalize to `Y-m-d` rather than to an instant,
   so a save can no longer entrench a wrong day.
3. `includes/Membership_Config.php:244-245` — unchanged: keep passing the raw stored value to
   `get_mdp_day_start/end`, which handle bare dates correctly since `ad8c900`. Season
   *boundaries* become timezone-proof; the membership dates derived from them stay instants,
   which is what MDP and WooCommerce want.

Alternative considered and rejected: keep the instants and audit every truncation site. That
is what `19fb38d`, `ad8c900` and `fec6a0d` each attempted, and failure modes 3, 4 and 5 are
the sites those passes missed. Every new consumer that writes `format('Y-m-d')` on a stored
date reintroduces the bug, in a direction that depends on the customer's timezone, and there
is no lint for it.

### Fix 6 — remove the silent timezone fallbacks

`$_ENV['WICKET_MSHIP_MDP_TIMEZONE'] ?? 'UTC'` (PHP, ~14 call sites) and
`PLUGIN_SETTINGS.WICKET_MSHIP_MDP_TIMEZONE || 'UTC'` (React) both degrade silently and
independently. Centralize on `Utilities::mdp_timezone()` that logs once when the variable is
unset, and surface an admin notice. A misconfigured install currently produces wrong dates
rather than an error, and PHP and JS can disagree with no signal at all.

### Required post-deploy step — re-save the config season dates

The code changes above stop *new* boundaries from being written wrong, and let the plugin read a
bare `Y-m-d` correctly. They cannot repair a boundary already stored as an instant on the wrong
MDP day: after Fix 5 that value normalizes to the wrong calendar date, because that is genuinely
what the instant represents. Only an operator knows the intended day.

So the deployment is not complete until every calendar config has had its season dates re-picked
and saved in the admin. Treat it as part of shipping this fix, not as follow-up cleanup — until
it is done, affected configs keep minting shifted terms on the fixed code. Procedure, including
the reason a plain save is not enough:
[Data Healing, Phase 1](WWID-2212-membership-dates-day-shift-data-healing.md#2-phase-1--re-save-the-config-season-dates-required-completes-the-forward-fix).

### Not in this repair

- Failure mode 6 (importer). Same family, unverified; needs a CSV fixture before it is worth
  changing.
- Anything in [Known remaining exposure](#known-remaining-exposure).

---

## Blast radius of the repair

Fixes 2–4 change returned values by one day for behind- or ahead-of-UTC installs. That is the
point, but every consumer below computes from those values and must be re-checked, not just
the reported one.

**Direct consumers of `get_calendar_seasons()`** — `includes/Membership_Config.php`:
`get_current_calendar_season()` (258), `get_seasonal_end_date()` (329),
`season_end_containing()` (475, the importer's entry point), `get_season_start_for_date()`
(572). All four do MDP-timezone comparisons against the season array; Fix 5 changes the shape
they compare.

**Season validators** — `includes/Membership_Post_Types.php:357-403`. Adjacency and overlap
are tested with `strtotime()` on the stored strings. Mixing shapes across seasons in one
config is exactly what deadlocked these validators before `ad8c900`, so Fix 5 must convert
*all* seasons in a config atomically. Re-verify against seasons that abut (one ending Nov 30,
the next starting Dec 1): an end stored at `00:00:00` instead of `23:59:59` changes whether an
abutting pair reads as adjacent or overlapping.

**Membership creation and renewal** — `includes/Membership_Controller.php:306,324` (create and
renewal), `1412-1421` (status recompute), `635,653` (`add_membership_ends_at` scheduling),
`1074-1100` and `1133-1240` (MDP pushes carrying `ends_at`).

**Subscriptions** — `includes/Membership_Subscription_Controller.php:50,76`,
`Membership_Controller::update_membership_subscription()`, and the collision guards in
`includes/Subscription_Manager.php`. A changed end date moves `end` and `next_payment`, and
the collision-offset logic there was tuned against current values.

**Admin surfaces** — `includes/Admin_Controller.php:123-135` (pending→active), `567`/`761`
(list and update payloads), `795-810` (subscription date propagation), `1384-1482` (tier
switch), `1599` (transfer). The transfer path *infers* grace days from `expires_at - ends_at`
when the meta is absent (`1516-1520`), so any fix that moves one date and not the other changes
an inferred grace period.

**Importer** — `includes/Import_Controller.php:76-80,188-192,318-331`.

**Frontend** — `frontend/src/membership_configs/edit.js` (season table and pickers) and
`frontend/src/members/edit.js:114-122,442-462` (membership date display and pickers). Both read
`PLUGIN_SETTINGS.WICKET_MSHIP_MDP_TIMEZONE`; both need rebuilding, and `frontend/build/*` is
committed, so a stale build silently keeps the old write shape.

**AutomateWoo** — `automate-woo/triggers/wicket_mship_end_date.php` and
`wicket_mship_renew_early.php` fire off these dates. Moving an end date on a live membership can
re-arm a trigger that already fired, or skip one that has not.

**Regression to expect and accept.** Fixes 2–4 make newly computed dates disagree by one day
with dates computed by the current code. On a behind-UTC install, a membership switched
yesterday has an expiry one day later than the same operation performed after the fix. That
divergence is the reason the [data healing](WWID-2212-membership-dates-day-shift-data-healing.md) exercise
exists; do not paper over it by leaving one site unfixed for consistency.

---

## Verification performed

A standalone harness reproduced the reported values exactly, using the plugin's own logic
transcribed at each historical revision. PHP 8.5.4 CLI with
`date_default_timezone_set('UTC')`, matching what WordPress forces at runtime.

```php
<?php
date_default_timezone_set('UTC');                     // WordPress default
$mdp = new DateTimeZone('America/Toronto');

// Utilities::get_mdp_day_end as of adc517f .. pre-ad8c900 (no bare-date handling)
function old_day_end($s, $mdp) {
  $d = new DateTime($s); $d->setTimezone($mdp); $d->setTime(23, 59, 59);
  return $d->setTimezone(new DateTimeZone('UTC'));
}

$stored_end = '2026-11-30';                           // legacy date-only season boundary

// get_calendar_seasons() @19fb38d: explicit UTC round-trip before the day snap
$round_trip = (new DateTime($stored_end, new DateTimeZone('UTC')))->format('c');
$end_date   = old_day_end($round_trip, $mdp)->format('c');

// grace block @92b884c (pre-fec6a0d): +90 days, truncate in UTC, re-snap
$ymd     = (new DateTime($end_date, new DateTimeZone('UTC')))->modify('+90 days')->format('Y-m-d');
$expires = old_day_end($ymd, $mdp)->format('c');
```

Output:

```
stored end_date        : 2026-11-30
UTC round-trip         : 2026-11-30T00:00:00+00:00
season end             : 2026-11-30T04:59:59+00:00   local 2026-11-29 23:59:59
membership_ends_at     : 2026-11-30T04:59:59+00:00   local 2026-11-29 23:59:59   <-- reported
grace ymd (UTC trunc)  : 2027-02-28
membership_expires_at  : 2027-02-28T04:59:59+00:00   local 2027-02-27 23:59:59   <-- reported
subscription 'end'     : 2027-02-28 04:59:59 (UTC render)
```

Also verified:

- **Stored-shape matrix** for the current read path across `America/Toronto`,
  `America/Vancouver`, `UTC`, `Australia/Sydney`. A bare `2026-11-30` and a correctly normalized
  end-of-day instant both give Nov 30 / Feb 28 in every zone; a `2026-11-30T00:00:00Z` stored
  value gives **Nov 29 / Feb 27 23:59:59** in the two behind-UTC zones and correct values in
  `UTC` and `Australia/Sydney`. This establishes both preconditions: a behind-UTC zone **and** a
  previous-day instant.
- **Failure mode 3**: `derive_switch_expiry()` → `2027-03-01` (want 2027-02-28) in
  `America/Toronto`; correct in `UTC` and `Australia/Sydney`.
- **Failure mode 4**: admin early-renewal → `2026-11-01` (want 2026-10-31) in `America/Toronto`;
  correct in `UTC` and `Australia/Sydney`.
- **Failure mode 5**: anniversary `+1 year` → `2026-11-30` in `Australia/Sydney` and `Asia/Tokyo`
  where `UTC` gives `2026-12-01`; behind-UTC zones unaffected on this path.
- **Deployment state**: `ad8c900` and `fec6a0d` are ancestors of the released branch head
  (1.0.122) and are carried by no tag.
- **REST read path**: the `cycle_data` `get_callback` (`includes/Membership_Post_Types.php:277-279`)
  returns raw post meta, so the config admin UI loads the stored shape rather than
  `get_calendar_seasons()` output. The React page cannot be the source of a persisted shift by
  simply round-tripping a value it displayed.

Not verified — needs the affected environment:

- The actual stored `cycle_data` and resolved `WICKET_MSHIP_MDP_TIMEZONE` on the affected site.
- Failure mode 6 (importer paths).
- MDP-side behaviour on receipt of a shifted `ends_at`, and whether MDP re-derives anything from
  its own tier configuration.

---

## Known remaining exposure

Same root cause, not covered by the fixes above:

- `includes/Membership_Controller.php:2008` —
  `date("Y-m-d", strtotime($membership_starts_at . "-1 days"))`, used to match a previous term's
  end date when suppressing duplicate renewal callouts. Truncates a stored instant in the server
  timezone; a mismatch makes a renewal callout appear or vanish rather than corrupting a date, so
  it fails quietly.
- `includes/Membership_Controller.php:622` — order-note text built with `date('Y-m-d', $ends_at)`.
  Cosmetic, but it is the audit trail people read when reconstructing what happened, and it will
  disagree with the record by a day.
- `includes/Import_Controller.php:80,192,318`.
- `includes/Admin_Controller.php:97` (`get_mdp_day_start("-1 day")`) and `1599` — relative offsets
  through the same helpers. Correct today, same fragility.
- `includes/Membership_Config.php:404-412` — the `align_end_dates` variants, which shift only when
  the lost day crosses a month boundary. Intermittent by construction, and the hardest of the set
  to reproduce on demand. Addressed by Fix 4 only if Fix 4's align changes land with it.
- The `?? 'UTC'` / `|| 'UTC'` fallback divergence between PHP and the React bundles, unless Fix 6
  is taken.
- Any future `date('Y-m-d', …)` or `->format('Y-m-d')` applied to a stored membership or season
  date. Until Fix 5 lands, this remains a live trap with no automated guard.

## See also

- [Membership Date Shift — Data Healing Instructions](WWID-2212-membership-dates-day-shift-data-healing.md) — the data-team runbook for records already written with a shifted date
- [The Season Date Write Path — Three Fixes, Still Broken](WWID-2212-season-date-write-path-partial-fixes.md) — which commits actually touched the season date write path, and why `ad8c900` / `fec6a0d` are cited as fixing it but did not
- [Membership_Config — Class Reference](../../engineering/Class-Membership_Config.md)
- [Utilities — Class Reference](../../engineering/Class-Utilities.md) — the MDP timezone date helpers
- [Membership_Config Data Structure](../../engineering/membership_config_data_structure.md) — `cycle_data` / `calendar_items` shape
- [Membership Switch — Engineering Reference](../../engineering/membership_switch.md) — failure mode 3 lives on this path
