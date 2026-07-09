---
title: Bundle Import
---

# Bundle Import

A launch-time CSV import for membership bundles, used to migrate bundle data from a legacy or staging environment into a live site. It mirrors the existing individual/organization membership import: synchronous, one record per REST call, no batch job machinery.

Members are **not** part of the bundle CSV. A bundle row creates only the bundle container (post + subscription). Members are added separately through the individual membership import — see [Importing bundle members](#importing-bundle-members) below.

## Requirements

- `wicket_mship_enable_bundles` feature flag enabled (`WICKET_MSHIP_ENABLE_BUNDLES`).
- `ALLOW_LOCAL_IMPORTS` environment flag enabled — this gates the import endpoint entirely, same as the existing membership import. It disables authentication, so only enable it for the duration of a launch migration.

## CSV format

One row = one membership bundle. Columns match the standard MDP bundle export, normalized the same way as the existing import (spaces → underscores):

| Column | Use |
|---|---|
| `Name` | Bundle post title |
| `Name (FR)` | Not imported — no FR-name storage exists for bundles |
| `Organization` / `Organization Identifying Number` | Reference only |
| `Owner` / `Owner Identifying Number` / `Owner Primary Email Address` | Reference only |
| `Starts At` / `Ends At` / `Expires At` | Explicit date window — used as-is, not derived from the config. `Expires At` may be left blank; it's then derived from `Ends At` + the linked config's grace period. |
| `Status` | Not used by the importer — see [Status is derived from dates](#status-is-derived-from-dates-not-read-from-the-csv) below |
| `In Grace` / `Grace Period Days` | Reference only |
| `External ID` | Stored as `membership_bundle_external_id` post meta |
| `Organization UUID` | MDP org UUID |
| `Owner UUID` | MDP person UUID of the bundle owner |
| `Membership Bundle UUID` | The bundle's existing MDP UUID. Stored as `membership_bundle_mdp_uuid` — this is also the idempotency key: re-running the import skips any row whose UUID already exists locally. |
| `Created At` / `Updated At` | Reference only |

::: warning Manually-added column: `Bundle_Config_ID`
`Bundle_Config_ID` is **not** part of the standard MDP export. It must be added by hand to the CSV by whoever prepares the import file — one WP post ID (of the target `wicket_mship_bcfg` Membership Bundle Config) per row, before the file is imported. The import rejects a row with this column empty, non-numeric, or pointing at a post ID that isn't a valid bundle config.

This requirement exists because the MDP export has no reliable way to identify which bundle config a given bundle should use — the only tier information in the export doesn't map to the bundle config CPT. This is a known gap; the person preparing the import file is responsible for supplying the correct config ID for each row.
:::

## Status is derived from dates, not read from the CSV

The MDP export's `Status` column only ever distinguishes active/inactive — it carries no signal that the dates don't already encode. So the importer ignores it and derives status from the date window instead, the same way the individual membership import derives status for a `wicket_membership` record: `active`, `delayed`, or `expired`, computed from `Starts At` / `Ends At` / `Expires At` against the current date.

This means a bundle that was manually cancelled before its natural end date will import as `active` or `expired` instead of `cancelled` — accepted as a limitation, since MDP itself doesn't retain that distinction either.

## What the import does

1. Skips the row if its `Membership Bundle UUID` already exists locally (`skipped`).
2. Validates `Bundle_Config_ID`.
3. Creates the bundle via `Membership_Bundle::create()`, **without** creating it again in MDP — the bundle already exists there, since it's being migrated from it.
4. Overrides the bundle's date window with the CSV's explicit values (`create()` would otherwise derive dates from the config), then derives and sets status from that window (see above).
5. Re-syncs the bundle's WooCommerce subscription dates and status to match.
6. Seeds `membership_bundle_mdp_uuid` from the CSV, last — only after every prior step succeeds, so a partially-failed row is safe to re-run.

## Importing bundle members

Bundle members are imported through the **individual membership import**, not this one. Add the optional `Membership_Bundle_UUID` column to a member's row in the individual membership CSV — when present, that member is created through the matching bundle (inheriting its dates and status) instead of as a standalone membership.

::: danger Import bundles before members
The individual import's duplicate check runs before bundle-link logic, so a member row processed before its bundle exists cannot be linked afterward on a re-run. Always import all bundles, then import members.
:::

## REST endpoint

| Method | Route | Purpose |
|---|---|---|
| `POST` | `/wp-json/wicket_member/v1/import/membership_bundle` | Import a single bundle row |

Registered in `Membership_WP_REST_Controller`, alongside the existing `import/person_memberships` and `import/membership_organizations` endpoints.

## CLI script

`csv_bundle_import_threads.php` mirrors the existing `csv_import_threads.php`: reads a CSV from `/wp-content/uploads/`, normalizes and sanitizes headers/rows, and POSTs rows to the REST endpoint in concurrent batches of 25 via `curl_multi`.

```
php csv_bundle_import_threads.php { file_path from /uploads/ } { api_domain - optional } { skip_approval - optional }
```

`{ file_path from /uploads/ }` is relative to `wp_upload_dir()`, e.g. `2026/07/membership_bundles.csv` for a file at `wp-content/uploads/2026/07/membership_bundles.csv` — not relative to the plugin directory or the repo root.

### Running under Docker

From the stack root (where `docker-compose.yml` lives), `cd` into the plugin directory inside the `php` container first — the script locates `wp-load.php` via a path relative to its own directory (`../../../wp/wp-load.php`), which only resolves correctly when the shell's current directory is the plugin folder:

```bash
docker compose exec php sh -c "cd /var/www/html/web/app/plugins/wicket-wp-memberships && php csv_bundle_import_threads.php 2026/07/membership_bundles.csv https://nginx"
```

Always pass `https://nginx` (or your equivalent internal HTTPS hostname) as the `api_domain` argument explicitly — see [Troubleshooting](#troubleshooting) below for why this matters.

Same CLI, same requirements, same Docker command shape apply to `csv_import_threads.php` for the individual/organization membership import (just with the `individual|organization` type argument first).

## Troubleshooting

**`{"error":"File line read error."}` with no other detail, or an empty response body.**
This almost always means the POST body never reached the endpoint — not that the CSV itself failed to parse. The most common cause: nginx 301-redirects plain HTTP to HTTPS, and `curl`'s `CURLOPT_FOLLOWLOCATION` does not preserve the POST method/body across that redirect by default (no `CURLOPT_POSTREDIR` is set), so the followed request silently becomes an empty GET. Fix by passing the HTTPS URL directly as `api_domain` instead of relying on the default (which uses `get_site_url()`, or a bare `http://` domain) — e.g. `https://nginx` rather than `http://nginx` or leaving the argument blank. `get_site_url()`'s value may also not resolve correctly from inside a container network, independent of the HTTP/HTTPS issue.

**`{"error":"Failed to apply CSV dates/status to bundle post#N (...)"}`**
The bundle post itself was created successfully (note the post ID in the message) but a later `set_dates()` or `set_membership_status()` call failed. If you're re-running an import against a bundle that partially succeeded on a prior attempt, this can happen if a CSV value exactly matches what's already stored — `Membership_Bundle::set_dates()` / `set_membership_status()` correctly treat an unchanged value as success, so if you still see this error, check the WC log (see below) for the specific field or status that failed and inspect the bundle post's current meta directly.

**`{"error":"Bundle_Config_ID#N does not resolve to a valid Membership Bundle Config..."}`**
The `Bundle_Config_ID` column value isn't a real `wicket_mship_bcfg` post ID on this site. Check the ID against **Wicket → Membership Bundle Configs** in wp-admin.

**Script hangs indefinitely with no output.**
Neither this script nor `csv_import_threads.php` sets a curl timeout — if the target URL is unreachable (wrong hostname, network partition, nginx down), the request blocks forever rather than failing fast. Ctrl+C and re-check the `api_domain` argument and container networking.

## Logging

All import activity is logged under source `wicket-membership-plugin-import` (WooCommerce → Status → Logs) — kept separate from the existing import's `wicket-membership-plugin` source and the operational `wicket-memberships` source, so a launch-import run can be reviewed or cleared independently.
