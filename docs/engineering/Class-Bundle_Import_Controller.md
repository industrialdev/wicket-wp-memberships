---
title: "Bundle_Import_Controller Class Reference"
audience: [developer]
php_class: Bundle_Import_Controller
source_files: ["includes/Bundle_Import_Controller.php"]
---

# Bundle_Import_Controller Class Index

**File:** includes/Bundle_Import_Controller.php

Launch-time CSV import engine for membership bundles. Mirrors `Import_Controller`'s
shape (one record per call, synchronous, REST-driven) but creates `Membership_Bundle`
container posts instead of individual memberships. No member data is handled here —
bundle members arrive via `Import_Controller::create_individual_memberships()` when a
row carries the optional `Membership_Bundle_UUID` column.

## Methods

- `create_bundle( array $record ): \WP_REST_Response`
- `bundle_exists( string $mdp_uuid ): bool`
- `derive_status( string $starts_at, string $ends_at, string $expires_at ): string` _(private)_
- `resync_bundle_subscription( Membership_Bundle $bundle, array $date_window, string $expires_at, string $status ): void` _(private)_
- `log_and_respond( string $outcome, string $message, array $record, ?int $bundle_id ): \WP_REST_Response` _(private)_

## Method Descriptions

**create_bundle( array $record )**

Per-row handler. Flow:

1. Duplicate check via `bundle_exists()` on the CSV `Membership_Bundle_UUID` — returns `skipped` if already imported.
2. Reads and validates the manually-added `Bundle_Config_ID` column (a WP `wicket_mship_bcfg` post ID) — this column is **not** part of the standard MDP export; a team member adds it by hand before import. Returns `error` if empty/non-numeric/invalid.
3. Calls `Membership_Bundle::create( ..., sync_to_mdp: false )` — MDP creation is skipped because the bundle already exists there.
4. Overrides the config-derived date window with the explicit CSV `Starts_At` / `Ends_At` via `set_dates()`. `Expires_At` uses the CSV value if present, otherwise `Ends_At` + the linked config's grace period (`get_late_fee_window_days()`) — the same fallback `Import_Controller` uses for individual memberships.
5. Derives status from that date window via `derive_status()` (a private copy of `Import_Controller::get_status()`'s logic — only yields `active`/`delayed`/`expired`) and sets it via `set_membership_status()`. The CSV's own `Status` column is **not** read: the MDP export only ever distinguishes active/inactive, so a free-text status column would add validation burden without adding real signal. A bundle manually cancelled before its natural end date imports as active/expired instead — accepted, since MDP doesn't retain that distinction either.
6. Re-syncs the bundle's WooCommerce subscription (`resync_bundle_subscription()`) — dates always; status only for the `active`/`expired` cases that map directly to a WCS status; `delayed` bundles keep their subscription `pending`.
7. Stores `External_ID` as `membership_bundle_external_id` post meta, if present.
8. Seeds `membership_bundle_mdp_uuid` from the CSV **last**, only once every prior step has succeeded — this is what makes `bundle_exists()` skip-safe on re-run. A row that fails earlier never reached MDP linkage and simply retries as a fresh post on the next run.

Every outcome (`created`/`skipped`/`error`) is logged via `Wicket()->log()` under source `wicket-membership-plugin-import` — kept separate from `wicket-membership-plugin` (existing import) and `wicket-memberships` (operational) so launch-import runs can be filtered/cleared independently in WooCommerce → Status → Logs.

**Response shape:** on failure, `{ "error": string }`. On success (`created` or `skipped`), `{ "success": string, "outcome": "created"|"skipped", "bundle_id": int|null }` — `bundle_id` is the created post ID for `created`, `null` for `skipped` (no new post was made). Check `outcome`, not just the presence of `success`, to tell a fresh create apart from a re-run no-op.

**bundle_exists( string $mdp_uuid )**

Queries `wicket_mship_bundle` posts by `membership_bundle_mdp_uuid` meta. Returns `true` if any match.

**derive_status( string $starts_at, string $ends_at, string $expires_at )**

Deliberately duplicated from `Import_Controller::get_status()` rather than shared — see [Code Organization](#code-organization) note below.

## Code Organization

This class does not share code with `Import_Controller` via a trait or base class, even though `derive_status()` duplicates `get_status()`'s logic and the date-normalization lines are similar. `Import_Controller` is left completely untouched apart from the one new branch added for Feature 1a — the bundle import feature must not risk altering that core, long-standing class's existing behavior for the sake of avoiding a few duplicated lines.
