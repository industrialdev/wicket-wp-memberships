---
title: "WWID-2212 — The Season Date Write Path: Three Fixes, Still Broken"
audience: [developer]
php_class: Membership_Config
source_files:
  - "frontend/src/membership_configs/edit.js"
  - "includes/Membership_Post_Types.php"
  - "includes/Membership_Config.php"
  - "includes/Utilities.php"
ticket: WWID-2212
status: pending — write path unrepaired; verified against the checked-out branch
branch: feature/users-multi-tier-renewal-subscriptions-merged
---

# WWID-2212 — The Season Date Write Path: Three Fixes, Still Broken

> ## ⚠ Status: NOT APPLIED — and the fix that introduced the defect was itself a bug fix
>
> The `moment` conversion that writes a config season date, `frontend/src/membership_configs/edit.js:180-184`,
> is **unrepaired on this branch**. It is byte-for-byte what commit `19fb38d` added — a commit
> whose own subject line is *"fix: Calender configs were incorrectly storing dates"*.
>
> Two later commits, `ad8c900` and `fec6a0d`, are frequently cited as having fixed the calendar
> season dates. They did not fix this. Both are **PHP-only** and repaired the *read* side of
> `19fb38d`; the *write* side has never been touched by any commit on any branch in this
> repository. See [Per-site status](#per-site-status).
>
> This document exists because "the season date handling was already fixed" is half true, and the
> half that is true is not the half that writes the data. The repair itself is specified as
> [Fix 5](WWID-2212-membership-dates-off-by-one-day.md#fix-5--stop-carrying-season-boundaries-as-instants-separate-pr)
> in the code-fix document and is not restated here.

Third document on WWID-2212, companion to
[Code Fixes](WWID-2212-membership-dates-off-by-one-day.md) (what to change) and
[Data Healing](WWID-2212-membership-dates-day-shift-data-healing.md) (what to re-save afterward).
This one covers a narrower question that kept being answered wrongly: **which commits actually
touched the season date write path, and what each of them left behind.** It is a provenance
record, so the next person told "that was fixed in August" can check in one place rather than
re-deriving it from four commits.

## Per-site status

| Site | Generation | State |
|---|---|---|
| Picker write — `edit.js:180-184` (`convertSeasonDate`) | added `19fb38d` | **Open.** Never modified since it was introduced |
| Picker read-back — `edit.js:174-178` (`getSeasonDatePickerValue`) | added `19fb38d` | **Open.** Round-trips the instant back through the MDP zone; correct only while every other layer agrees on that zone |
| Season table display — `edit.js:885,888` | added `19fb38d` | **Open.** `moment.tz(...).format("YYYY-MM-DD")` — shows the operator's intended day even when the stored instant is wrong, which is why the screen looks right |
| Save-time normalizer — `Membership_Post_Types.php:1151,1161` | added `ad8c900` | **Open as a hazard.** Fixes legacy bare dates; entrenches an already-shifted instant |
| PHP read path — `Membership_Config.php:238-245` (`get_calendar_seasons()`) | broken by `19fb38d`, fixed by `fec6a0d` | **Repaired** |
| Bare-date parsing — `Utilities::parse_as_mdp_date():1233` | added `ad8c900` | **Repaired** |

Compiled output is committed. `frontend/build/membership_config_create.js` still contains the
compiled `endOf('day')` write, so a source-only change to `edit.js` has no effect until the bundle
is rebuilt and committed.

---

## Cause

### Four passes over the same eight lines

Every commit below is a fix. Three of them changed how a season date is written, and each one was
a reasonable response to the failure the previous one produced.

| Commit | Date | Author | What it did to the season date write |
|---|---|---|---|
| *(original)* | — | — | `moment.utc(value).format('YYYY-MM-DD')`. Date-only storage, but the picker's browser-local `Date` was read in UTC. In an ahead-of-UTC browser, local midnight falls on the previous UTC day, so the stored day was one early |
| `4f69016` | 2025-05-22 | Alex Masliychuk | *"bugfix: Timezone issues when the date picker is used."* Dropped `.utc()` → `moment(value).format('YYYY-MM-DD')`. **Correct.** Picker frame and format frame now match, so the picked day is stored verbatim. This is the date-only contract the code-fix document calls timezone-proof |
| `adc517f` | 2026-01-26 | Adrian Bradley | *"Standardizing data to MDP TZ but saving in UTC across the board."* PHP only; established the store-as-UTC-instant convention for membership datetimes. Did not touch the frontend |
| `19fb38d` | 2026-05-11 | Adrian | *"fix: Calender configs were incorrectly storing dates."* Applied `adc517f`'s instant convention to the season pickers — added `convertSeasonDate`, `getSeasonDatePickerValue`, and the `moment.tz` display. **This is the regression under investigation** |
| `ad8c900` | 2026-08-05 | Adrian | *"fix: normalize calendar season dates and fix bare-date timezone shift."* PHP only — `Membership_Post_Types.php`, `Utilities.php` |
| `fec6a0d` | 2026-08-11 | EstebanForge | *"fix(dates): stop calendar season and grace dates drifting a day."* PHP only — `Membership_Config.php` |

### What `19fb38d` actually did, and why it is the origin of the current shape

`19fb38d` changed the write from a calendar date to an instant:

```js
// before — date-only, timezone-proof
end_date: moment(value).format("YYYY-MM-DD"),

// after — an instant in UTC, added by 19fb38d
const convertSeasonDate = (dateValue, isEndDate = false) => {
  if (!dateValue) return '';
  const m = moment.tz([dateValue.getFullYear(), dateValue.getMonth(), dateValue.getDate()], mdpTimezone);
  return isEndDate ? m.endOf('day').utc().toISOString() : m.startOf('day').utc().toISOString();
};
```

The conversion is not arithmetically wrong. `moment.tz([y, m, d], mdpTimezone).endOf('day').utc()`
is a faithful instant for the end of the picked MDP day. The defect is one of **kind**: a season
boundary is a calendar date, and this stores it as a moment in time. Picking Nov 30 in an
`America/Toronto` install persists `2026-12-01T04:59:59.999Z`, whose date part is December 1. The
operator's Nov 30 survives only as *instant + timezone*; any consumer that drops the timezone gets
a December season.

`adc517f`'s convention was right for the values it was written for — membership start, end, expiry,
early-renewal — which genuinely are instants that MDP and WooCommerce want in UTC. `19fb38d`
extended it to the one value in `cycle_data` that is not an instant. That is the whole mistake, and
it is why the code-fix document's remedy is to exempt season boundaries from the convention rather
than to correct the conversion.

### Why the two August fixes did not reach it

`19fb38d` changed two things in one commit: the **write** (`edit.js`) and the **read**
(`Membership_Config::get_calendar_seasons()`, where it inserted an explicit UTC `DateTime`
round-trip before the MDP day snap). The August commits found the read half and stopped there.

```
ad8c900   includes/Membership_Post_Types.php | 40 ++++
          includes/Utilities.php             | 36 ++++----

fec6a0d   includes/Membership_Config.php     | 176 +++++++++----
```

Neither diffstat contains a frontend path. `fec6a0d` removed exactly the round-trip `19fb38d` had
added; `ad8c900` made bare `Y-m-d` parse in the MDP timezone instead of the server default. Both are
correct and both are load-bearing. But they made the **read** side handle a legacy bare date
properly — which is a repair to the *old* storage shape, not to the *new* write. After both fixes,
picking Nov 30 in a behind-UTC install still persists a December 1 instant.

This is the mechanism behind the mistaken belief that the season dates were fixed. Two commits
whose subject lines say "calendar season dates" and "stop calendar season dates drifting a day"
landed a week apart, and both are real fixes — of the read path. Nothing announced that the write
path was out of scope.

### The normalizer makes the partial state worse, not neutral

`ad8c900` added `normalize_calendar_season_dates()` (`Membership_Post_Types.php:1143`, wired into
the `cycle_data` sanitize callback at `:289` and `:329`) to re-derive every season boundary on every
config save. It fixed the real problem it targeted: before it existed, only the season whose picker
was actually touched got rewritten, so configs with three or more seasons carried one normalized
season and several legacy ones, and the adjacency validators (`:357-403`) then rejected any attempt
to correct a single season.

But `Utilities::get_mdp_day_end( $item['end_date'] )->format( 'c' )` is **idempotent on an
already-shifted instant**. Feed it a value sitting on the wrong MDP day and it re-snaps that wrong
day to 23:59:59 and persists it. Combined with the unrepaired write path, the result is a ratchet:

1. The picker writes an instant whose date part is the wrong calendar day.
2. Any subsequent save — including one touching an unrelated field — normalizes that wrong day and
   makes it permanent.
3. The season table renders it back through `moment.tz`, so the screen shows the operator's
   intended day.
4. PHP computes membership dates from the stored instant, so the membership is shifted while the
   config screen agrees with the operator.

Step 3 is why this survived so long without being reported as a config bug. It was reported as a
membership end-date bug, three layers downstream.

## Effect

No new symptom beyond the six failure modes catalogued in
[Code Fixes › Effect](WWID-2212-membership-dates-off-by-one-day.md#effect). What this document adds
is the reason the state is confusing rather than simply broken:

- **A partial fix reads as a complete one.** `git log --oneline -- includes/` shows two commits
  fixing calendar season dates in August 2026. `git log --oneline -- frontend/` shows the last
  change to the season pickers was in May, by the commit that broke them.
- **The config screen exonerates itself.** Display goes through the same MDP timezone as the write,
  so a wrong stored instant renders as the right day. An operator verifying the config sees nothing
  wrong.
- **Fixing the read path alone cannot converge.** Every save re-derives the boundary through the
  unrepaired write, so the read fixes are correcting a value that is regenerated wrong.
- **Anyone re-deriving this reaches the same wrong conclusion.** Hence this document.

## The repair

Specified in full as
[Fix 5](WWID-2212-membership-dates-off-by-one-day.md#fix-5--stop-carrying-season-boundaries-as-instants-separate-pr)
— submit bare `Y-m-d` from the pickers, normalize to `Y-m-d` rather than to an instant, leave
`Membership_Config.php:244-245` alone. Not restated here; this document is the provenance record for
why that fix is still needed, not a second copy of it.

Three points about *this* history that the fix depends on:

1. **Fix 5 is a partial revert of `19fb38d`, not new work.** The target shape is `4f69016`'s
   `moment(value).format("YYYY-MM-DD")` — the contract that held for a year without a single
   date-shift report. `19fb38d`'s read-side changes stay reverted as `fec6a0d` left them; only its
   write-side and display changes come out.
2. **Do not "fix the conversion."** Every attempt so far has corrected a conversion. `4f69016`
   corrected the frame, `ad8c900` corrected bare-date parsing, `fec6a0d` corrected a round-trip.
   Each was right and none of them removed the class of bug, because the class comes from storing a
   calendar date as an instant at all. The code-fix document rejects the audit-every-truncation-site
   alternative for this reason.
3. **The build must ship with it.** `frontend/build/*` is committed, and the compiled write shape
   lives in `membership_config_create.js`. A merged source fix with a stale bundle changes nothing
   and produces a convincing false negative on retest.

## Blast radius of the repair

Inherited from
[Code Fixes › Blast radius](WWID-2212-membership-dates-off-by-one-day.md#blast-radius-of-the-repair).
Specific to reverting the write path:

- **Mixed shapes within one config.** `19fb38d` shipped without a migration, so live configs hold
  bare `Y-m-d` seasons, correctly-normalized instants, and shifted instants — sometimes in the same
  config. Fix 5 must convert **all** seasons in a config atomically; the adjacency and overlap
  validators (`Membership_Post_Types.php:357-403`) use `strtotime()` on the stored strings and
  deadlock on mixed shapes. That deadlock is documented in `ad8c900`'s own commit message and is
  not hypothetical.
- **Abutting seasons.** One season ending Nov 30 and the next starting Dec 1 currently compare as an
  end-of-day instant against a start-of-day instant. Moving both to bare dates changes what the
  validators see as adjacent versus overlapping. Re-verify with an abutting pair, not a single
  season.
- **The normalizer's original purpose must survive.** Whatever replaces
  `normalize_calendar_season_dates()` still has to rewrite *every* season on save. Dropping it to
  avoid the entrenchment hazard reopens the untouched-season problem `ad8c900` fixed.
- **Display simplifies, and must.** `edit.js:885,888` should render the stored string directly once
  it is a bare date. Leaving `moment.tz(...).format("YYYY-MM-DD")` in place over a bare date
  reintroduces a shift on the read side of a now-correct write.
- **Phase 1 is still mandatory.** A boundary already stored on the wrong MDP day is not recoverable
  by code — after Fix 5 it normalizes to the wrong calendar date, because that is genuinely what the
  instant represents. See
  [Data Healing, Phase 1](WWID-2212-membership-dates-day-shift-data-healing.md#2-phase-1--re-save-the-config-season-dates-required-completes-the-forward-fix).

## Verification performed

Against `feature/users-multi-tier-renewal-subscriptions-merged` at `17c6697`, in
`src/web/app/plugins/wicket-wp-memberships`. Static and git-history verification; no runtime
reproduction was run for this document — the reproduction harness is in
[Code Fixes › Verification performed](WWID-2212-membership-dates-off-by-one-day.md#verification-performed).

1. **The write path is unrepaired.** `edit.js:180-184` matches `19fb38d`'s addition exactly, and
   `git diff HEAD -- frontend/src/membership_configs/edit.js` is empty, so there is no uncommitted
   fix either. Both pickers call it live: `:1250` (`start_date`, `convertSeasonDate(value, false)`)
   and `:1275` (`end_date`, `convertSeasonDate(value, true)`).
2. **`19fb38d` is the last commit to touch the file.** `git log --oneline -- frontend/src/membership_configs/edit.js`
   → `19fb38d`, then `dcc825f` (formatting), then `4f69016`. Nothing after May 2026.
3. **Neither August commit touched the frontend.** `git show --stat ad8c900` → `Membership_Post_Types.php`,
   `Utilities.php`. `git show --stat fec6a0d` → `Membership_Config.php`. No frontend path in either.
4. **No branch in the repository carries the fix.** All 53 local and remote refs holding
   `frontend/src/membership_configs/edit.js` were classified by their `end_date` write:
   **22** on `19fb38d`'s instant write, **26** on `4f69016`'s date-only write, **5** on the
   pre-`4f69016` `moment.utc()` write. Three generations, no fourth. In particular
   `feature/users-multi-tier-renewal-subscriptions-merged-timestamp-standardization` is a
   *pre*-`19fb38d` branch, not a fixed one — its `0` hits for the instant write mean the function
   does not exist there, which is easy to misread as repaired.
5. **The normalizer still writes instants.** `Membership_Post_Types.php:1151` (`get_mdp_day_start(...)->format('c')`)
   and `:1161` (`get_mdp_day_end(...)->format('c')`), inside `normalize_calendar_season_dates()` at
   `:1143`, reached from the sanitize callback at `:289` and `:329`.
6. **The committed bundle carries the old write.** `grep -l "endOf('day')" frontend/build/` matches
   `membership_config_create.js` and `member_edit.js`.
7. **Line references in the sibling documents are current**, with one drift: the code-fix document
   cites the normalizer as `Membership_Post_Types.php:1143`, which is the function declaration; the
   two assignments are at `:1151` and `:1161`.

What was **not** checked: whether any unmerged PR or fork outside this clone's refs contains the
fix; the `frontend/src/members/edit.js` membership date pickers, which have the same `moment.tz`
shape and are listed in the code-fix document's blast radius but were not part of this
investigation.

## Known remaining exposure

- **`frontend/src/members/edit.js:114-122,442-462`** — the membership date pickers, same
  `PLUGIN_SETTINGS.WICKET_MSHIP_MDP_TIMEZONE || 'UTC'` resolution and the same instant conversion.
  Those values *are* instants, so the convention fits; but the silent `'UTC'` fallback
  ([Fix 6](WWID-2212-membership-dates-off-by-one-day.md#fix-6--remove-the-silent-timezone-fallbacks))
  applies identically and is unrepaired.
- **The convention has no guard.** Nothing prevents the next developer from applying `adc517f`'s
  store-as-UTC-instant rule to another calendar-date field, and there is no lint for it. The season
  boundary is the only such field today; a documented rule in
  [Membership_Config Data Structure](../../engineering/membership_config_data_structure.md) about
  which `cycle_data` fields are dates and which are instants would be cheaper than a fourth pass.
- **Commit subject lines will keep misleading.** Three commits in this chain say they fix calendar
  season date timezone handling and all three are genuine, so the history cannot be read as
  "the last one won." That is what this document is for.

## See also

- [WWID-2212 — Membership Dates Off By One Day: Code Fixes](WWID-2212-membership-dates-off-by-one-day.md) — the six failure modes and the specified fixes, including Fix 5
- [WWID-2212 — Membership Date Shift: Data Healing Instructions](WWID-2212-membership-dates-day-shift-data-healing.md) — Phase 1 (config re-save) and Phase 2 (record healing)
- [Membership_Config Data Structure](../../engineering/membership_config_data_structure.md) — `cycle_data` / `calendar_items` shape
- [Class-Utilities](../../engineering/Class-Utilities.md) — `parse_as_mdp_date`, `get_mdp_day_start`, `get_mdp_day_end`
