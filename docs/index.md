---
title: "Wicket Memberships Documentation Index"
audience: [implementer, support, developer, end-user]
---

# Wicket Memberships Documentation

## Product Docs (Operators & Support)
- [Overview](product/overview.md) — What the plugin does, CPTs, lifecycle, feature flags, requirements

## Engineering Docs (Developers & Agents)

### Data Structures
- [Membership_Config Data Structure](engineering/membership_config_data_structure.md) — Renewal windows, late fees, calendar/anniversary cycles
- [Membership_Tier Data Structure](engineering/membership_tier_data_structure.md) — Tier-to-product linkage, renewal types, approval flows

### Features
- [Membership Transfer — Engineering Reference](engineering/membership_transfer.md) — Move an individual membership's term to a different owner; new record minted, order stays with the payer
- [Membership Switch — Engineering Reference](engineering/membership_switch.md) — Switch a membership to a different tier; preserves end date, re-points the expiry scheduler event

### Tools
- [Subscription Sync Tool — Engineering Reference](engineering/memberships_sync.md) — How `custom/memberships-sync.php` links subscriptions to memberships and syncs per-seat MDP seat counts

### Class Reference
- [Admin_Controller](engineering/Class-Admin_Controller.md) — Admin menu pages, status transition validation, React app mounting
- [Helper](engineering/Class-Helper.md) — Static utilities: CPT slugs, status names, allowed transitions, logging
- [Import_Controller](engineering/Class-Import_Controller.md) — CSV import for individual and organization memberships
- [Membership_Config](engineering/Class-Membership_Config.md) — Model for config posts: renewal windows, grace periods, cycle calculations
- [Membership_Config_CPT_Hooks](engineering/Class-Membership_Config_CPT_Hooks.md) — Admin UI for configs: React edit page, trash protection
- [Membership_Controller](engineering/Class-Membership_Controller.md) — Core business logic: creates memberships, manages lifecycle, syncs to MDP
- [Membership_CPT_Hooks](engineering/Class-Membership_CPT_Hooks.md) — Admin list table columns and React edit page rendering for memberships
- [Membership_Post_Types](engineering/Class-Membership_Post_Types.md) — Registers three CPTs and their REST fields
- [Membership_Subscription_Controller](engineering/Class-Membership_Subscription_Controller.md) — Creates WCS subscriptions from membership orders
- [Membership_Tier](engineering/Class-Membership_Tier.md) — Model for tier posts: product linkage, renewal type, MDP UUID lookup
- [Membership_Tier_CPT_Hooks](engineering/Class-Membership_Tier_CPT_Hooks.md) — Admin UI for tiers: list columns, trash protection, React edit page
- [Membership_WP_REST_Controller](engineering/Class-Membership_WP_REST_Controller.md) — REST API (wicket_member/v1): search, CRUD, status management, merge webhook
- [Settings](engineering/Class-Settings.md) — Plugin options page: feature flags, debug toggles, scheduled action status
- [Subscription_Manager](engineering/Class-Subscription_Manager.md) — Intended eventual home for all WC_Subscription-touching logic; currently holds end-date/next-payment collision guards
- [Utilities](engineering/Class-Utilities.md) — WooCommerce integration hooks: cart/checkout modifications, product protection, timezone date helpers

## Bug Fix Docs (Developers)
Repair work only — kept separate from Engineering Docs so novel feature work is distinguishable from fixes to discovered issues. See [scope & required sections](bugfix/README.md).

- [Monthly Autopay Form Flow — Hidden Renewal Callout & Orphaned Subscription](bugfix/monthly-autopay-form-flow-renewal-callout.md) — Why the renewal callout was hidden for the whole renewal window on monthly autopay Form Flow tiers, and why the old subscription kept billing past the term after a renewal. Both autopay checks in `get_membership_callouts()`, and the new `Subscription_Manager::terminate_at_membership_end()` clamp
- [Annual Autopay Form Flow — Callout Still Hidden & Term-End Auto Payment](bugfix/annual-autopay-form-flow-renewal-callout.md) — Why the monthly repair did not reach annual subscriptions, and why those carry an armed term-end payment that auto-creates a membership with the renewal form bypassed. Drops the billing-period test from both autopay gates and removes the superseded subscription's `next_payment` so no renewal order is generated
- [WP Private Content Plus Collision — Admin Product & Page Pickers](bugfix/wppcp-product-picker-collision.md) — Why product/variation/page lookups use plugin endpoints instead of `/wc/v3/products` and `/wp/v2/pages`; WPCP filters every REST query and clobbers `post__not_in`. Includes regression coverage, the three harness prerequisites, and proposed coverage for the pages endpoint (WWID-1763)


## Pending Bug Fixes (Developers)
Diagnosed defects whose repair is **not yet in the codebase** — kept apart from Bug Fix Docs so a proposed fix is never mistaken for a shipped one. See [scope & required sections](bugfix-pending/README.md).

- [WWID-2212 — Membership Dates Off By One Day: Code Fixes](bugfix-pending/WWID-2212-membership-timestamp-date-offset-problems/WWID-2212-membership-dates-off-by-one-day.md) — **not applied.** Why a season ending 2026-11-30 with a 90-day grace produced an end date of 2026-11-29 and an expiry of 2027-02-27. The timestamp standardization made calendar dates travel as instants; the pre-timestamp date-only contract that made this impossible, six catalogued sites (three code sites still open, two repaired by earlier partial passes), the two errors that cancel out, and six specified fixes with their blast radius
- [WWID-2212 — Membership Date Shift: Data Healing Instructions](bugfix-pending/WWID-2212-membership-timestamp-date-offset-problems/WWID-2212-membership-dates-day-shift-data-healing.md) — **not started, blocked on the code fixes.** Data-team runbook: re-save the config season dates (required, completes the forward fix), then heal existing memberships through the admin datetime update API so the MDP record and the WooCommerce subscription end date move with them. Preconditions, payload, what one call rewrites automatically, and the cases to escalate instead
- [WWID-2212 — The Season Date Write Path: Three Fixes, Still Broken](bugfix-pending/WWID-2212-membership-timestamp-date-offset-problems/WWID-2212-season-date-write-path-partial-fixes.md) — **write path not applied.** Provenance record for the belief that the calendar season dates were already fixed. `19fb38d` — itself a bug fix — replaced the date-only season write with a UTC instant; `ad8c900` and `fec6a0d` are PHP-only and repaired its read half, leaving the `moment` conversion at `edit.js:180-184` untouched on every branch in the repo. Includes the four-commit chain, why the save-time normalizer entrenches a wrong day, and what the committed bundle hides

## Guides (End Users)
- [Transfer a Membership to a New Owner](guides/membership_transfer.md) — Move a membership to a different person; keeps the remaining term
- [Switch a Membership to a Different Tier](guides/membership_switch.md) — Move a member to a different tier; keeps the end date
- [Link a Membership Tier to a WooCommerce Product](guides/link-tier-to-product.md) — Connect tiers to subscription products so memberships are created on purchase
- [Subscription Sync Tool](guides/membership-sync.md) — Link existing subscriptions to imported membership records; per-seat org seat sync
