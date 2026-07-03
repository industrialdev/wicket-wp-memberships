---
title: "WP-CLI Tier Sync Tool — Implementation Plan"
audience: [developer, agent]
status: proposal
php_class: null
source_files:
  - "includes/Membership_Tier.php"
  - "includes/Membership_Post_Types.php"
  - "includes/Membership_Config.php"
  - "includes/Helper.php"
  - "../wicket-wp-base-plugin/includes/helpers/helper-unsorted.php (get_individual_memberships)"
  - "../wicket-wp-base-plugin/includes/helpers/helper-init.php (wicket_api_client)"
---

# WP-CLI Tier Sync Tool — Implementation Plan

## 1. Goal

Provide a **WP-CLI command** that connects to the external Wicket **MDP** (Membership
Data Platform) through the API exposed by `wicket-wp-base-plugin`, reads the membership
tiers defined on the external site, and **creates matching `wicket_mship_tier` posts in
WordPress** — each one linked to its MDP tier via `mdp_tier_uuid`.

This automates what is today a manual process: an admin opening **Add New Tier**,
selecting the MDP tier from a dropdown, and saving. For a site with dozens of MDP tiers,
that is slow and error-prone. The CLI tool will fetch the authoritative list from the MDP
and reconcile local tier posts against it.

**Non-goals (this phase):** creating/editing configs, linking WooCommerce products,
setting renewal logic, or pushing data *up* to the MDP. Those remain manual or belong to a
later phase. See [§10 Out of Scope](#10-out-of-scope--future-work).

---

## 2. Background — how tiers link to the MDP today

Confirmed by reading the code (not assumed):

- **MDP tier fetch.** `get_individual_memberships( $id = '', $params = [] )` in
  `wicket-wp-base-plugin/includes/helpers/helper-unsorted.php` calls
  `wicket_api_client()->get('memberships', ['query' => $params])`. It returns a JSON:API
  payload — `['data' => [ { 'id' => <uuid>, 'attributes' => { 'name_en' => ..., 'category_weight' => ... } }, ... ]]`.
  This is the same call `Admin_Controller` (line ~416) and `Membership_Tier_CPT_Hooks` (line ~322)
  already use to populate the MDP-tier dropdown, so it is the authoritative source list.
- **API client.** `wicket_api_client()` (base plugin `helper-init.php`) builds a
  `\Wicket\Client` from `get_wicket_settings()` (JWT, endpoint, person_id) and returns
  `false` if the SDK or connection is unavailable. All MDP access flows through this.
- **Local tier storage.** Tiers are the `wicket_mship_tier` CPT
  (`Helper::get_membership_tier_cpt_slug()`). Their MDP linkage and behavior live in a
  serialized `tier_data` post-meta array. Key fields (from `Membership_Post_Types::register_membership_tier_cpt_fields()`
  and `docs/engineering/membership_tier_data_structure.md`):
  - `mdp_tier_uuid` (**required**, authoritative link — validated non-empty on REST save)
  - `mdp_tier_name` (**required**, validated non-empty)
  - `config_id` (**required** — every tier must reference a `wicket_mship_config` post)
  - `type` (`individual` | `organization`)
  - `renewal_type`, `seat_type`, `product_data[]`, `approval_required`, etc.
- **Idempotency hook.** `Membership_Tier::get_tier_id_by_wicket_uuid( $uuid )` iterates all
  tier posts and returns the post ID whose `tier_data['mdp_tier_uuid']` matches — this is
  how we detect "tier already exists" and avoid duplicates.

**Constraint that shapes the design:** a tier cannot be validly saved without a
`config_id`. The MDP does not supply a config (configs are a WordPress-side concept:
renewal windows, grace periods, billing cycle). **Decision:** the operator supplies the
config via a required `--config-id` flag; the CLI attaches it to every created tier
(option (a) below).

### 2.1 Confirmed MDP response shape (verified against staging)

Verified live on `2026-07-03` against `https://memberships-test-api.staging.wicketcloud.com`
via `wp eval` in the `php` container (`get_individual_memberships('', ['page' => ['size' => 100]])`).
Sample item and findings:

```jsonc
{
  "id": "6edcfa3a-d859-4f41-a19d-9cfe77ea85ff",   // → mdp_tier_uuid
  "type": "memberships",
  "attributes": {
    "name_en": "Full Member", "name_fr": "...", "name_es": "...",  // → mdp_tier_name (use name_en)
    "type": "individual",              // "individual" | "organization"  → tier_data['type']
    "approval": "approval_not_required", // "approval_not_required" | (assumed) "approval_required"
    "slug": "full-member", "code": "FULL", "active": true,
    "max_assignments": 1, "unlimited_assignments": false,  // org seat hints (see below)
    "default_grace_period_days": 0
  },
  "relationships": { "organization": { "data": null }, "parent": { "data": null }, ... }
}
```

Key confirmed facts (these resolve the former open questions):

- **Individual vs organization — distinguishable in ONE endpoint.** `attributes.type` is
  `"individual"` or `"organization"`. **Note the helper name is misleading:**
  `get_individual_memberships()` returns *both* types (staging: 21 individual + 9
  organization = 30). So `--type` filtering is real and must be done on `attributes.type`
  (client-side is fine at this scale; a server-side `filter[type]` is a possible
  optimization but unverified).
- **Pagination — JSON:API, opt-in.** A bare call returns `links => []` (empty). Passing
  `['page' => ['size' => N]]` returns a full `links` object (`self/first/last/prev/next`).
  With `page[size]=100`, staging returned all 30 with `next: null`, confirming 30 is the
  true total. **The CLI must paginate defensively:** pass an explicit `page[size]` and
  follow `links.next` until it is `null` — do not trust a single bare response to be
  complete.
- **Localization.** `name_en`, `name_fr`, `name_es` (plus base `name`) are all present.
  Use **`name_en`** for `mdp_tier_name` to match the existing admin dropdown.

**Bonus fields available to seed `tier_data` defaults** (nice-to-have, not required for v1):
`attributes.approval` → `approval_required` (staging is uniformly `approval_not_required`;
the `approval_required` value is assumed, not yet observed). `relationships.organization`
was `null` for all sampled org tiers, so it cannot be relied on to identify org tiers —
use `attributes.type`.

#### Organization seat type mapping

For `type: "organization"` tiers, the MDP `unlimited_assignments` flag maps to the
plugin's `tier_data['seat_type']`:

| MDP `unlimited_assignments` | → plugin `seat_type` | Plugin meaning |
|---|---|---|
| `false` | `per_seat` | Seat count is **limited to the purchased product quantity**. Exactly one product; `product_data[0].max_seats = -1` (a sentinel meaning "no fixed cap in `product_data`" — the real cap is the qty bought, pushed to MDP `max_assignments` by the sync tool). |
| `true`  | `per_range_of_seats` | The **range** setting: multiple products, each a bounded seat-range bucket. |

Observed org samples: `{max_assignments:5, unlimited:false}` ("Vet Clinic for 2-5") →
`per_seat`; `{max_assignments:null, unlimited:true}` ("Vet Clinic for 6+") →
`per_range_of_seats`.

**Why this is consistent (do not "fix" it):** `max_seats = -1` is *not* "unlimited seats" —
it is a sentinel meaning the cap is not hardcoded in `product_data`. For `per_seat` the
effective seat count is the purchased quantity (`memberships-sync.php:328-334` reads
`$item->get_quantity()` and writes it to MDP `max_assignments`), i.e. a **finite, limited**
count. `per_range_of_seats` is the open-ended / range configuration. So MDP
`unlimited_assignments:true → per_range_of_seats` and `false → per_seat` matches the
plugin's semantics exactly.

> Scope note: the CLI sets `seat_type` from this rule. The matching `product_data[]`
> (single `max_seats:-1` product for `per_seat`; range buckets for `per_range_of_seats`)
> and WooCommerce product linkage remain a later phase (§10) — done by the admin in the UI.
> See §6.9 for how product-less tiers are created safely.

---

## 3. Command design

Establish a new namespaced WP-CLI command (no WP-CLI commands exist in this stack yet, so
this sets the pattern).

```
wp wicket-mship tier sync   [--dry-run] [--type=<individual|organization|all>]
                            [--config-id=<post_id>] [--yes] [--format=<table|json|csv>]

wp wicket-mship tier list   [--source=<mdp|local>] [--format=<table|json|csv>]
```

### `tier sync` (the core tool)
For each MDP tier returned by the API:
1. Look up an existing local tier by `mdp_tier_uuid`.
2. If none exists → **create** a `wicket_mship_tier` post, populate `tier_data`
   (`mdp_tier_uuid`, `mdp_tier_name`, `config_id`, `type`, sensible defaults).
3. If one exists → **skip** by default; optionally refresh `mdp_tier_name` if it drifted
   (report as "updated").
4. Emit a per-tier result line and a final summary (`created`, `skipped`, `updated`, `errors`).

### `tier list` (read-only helper / verification)
Prints the MDP tier list (`--source=mdp`) or local tier posts (`--source=local`) so an
operator can preview before syncing and diff after. Cheap to build, high diagnostic value.

### Flags
| Flag | Purpose |
|---|---|
| `--dry-run` | Fetch + compute the plan, write **nothing**. Default-recommended first run. |
| `--config-id` | Config post to attach to newly created tiers (see §6). |
| `--type` | Filter which MDP tiers to sync (if individual/org is distinguishable — §6). |
| `--yes` | Skip the interactive confirmation before a live write. |
| `--format` | Standard WP-CLI output format for list/summary. |

**Safety defaults:** live writes require either `--yes` or an interactive
`WP_CLI::confirm()`. Mirrors the dry-run-first ethos of the existing subscription sync tool.

---

## 4. Where the code lives

- **New file:** `includes/CLI/Tier_Sync_Command.php`, class
  `Wicket_Memberships\CLI\Tier_Sync_Command` (PHP 8.1+, namespaced, `snake_case` methods —
  matches the plugin's conventions in CLAUDE.md).
- **Registration:** in `wicket.php`, guarded by `if ( defined('WP_CLI') && WP_CLI )`, call
  `WP_CLI::add_command( 'wicket-mship tier', ... )`. Load the class file there (or via the
  existing autoload path). This keeps CLI wiring out of the web request path entirely.
- **Reuse, don't reinvent:** use `get_individual_memberships()` for the fetch,
  `Membership_Tier::get_tier_id_by_wicket_uuid()` for idempotency,
  `Helper::get_membership_tier_cpt_slug()` for the CPT slug, and the same `tier_data` shape
  the REST controller writes. Do **not** duplicate the MDP HTTP logic.

---

## 5. Execution flow (`tier sync`)

1. **Preconditions.** Confirm `wicket_api_client()` returns a live client; abort with
   `WP_CLI::error()` if the MDP is unreachable (don't create half-linked tiers).
2. **Fetch.** Call `get_individual_memberships('', ['sort' => '-category_weight'])`.
   Handle empty/`null` responses gracefully (the helper swallows exceptions and can return
   `null`). Consider pagination — verify whether the MDP `memberships` endpoint paginates
   and, if so, loop over pages (see §6).
3. **Resolve config.** Determine the `config_id` to attach (from `--config-id`, or a
   documented default/lookup — §6). Validate the post exists and is a `wicket_mship_config`.
4. **Plan.** Build a reconciliation list: for each MDP tier, mark `create` / `skip` /
   `update`. In `--dry-run`, print the plan + summary and exit here.
5. **Confirm.** If not `--dry-run` and not `--yes`, `WP_CLI::confirm()` the write count.
6. **Apply.** For each `create`: `wp_insert_post()` a `wicket_mship_tier` (status
   `publish`), then persist the serialized `tier_data` meta. Set `mdp_tier_name` from
   `attributes.name_en`, `type` per §6, and defaults for the remaining fields.
7. **Report.** Per-tier line (`WP_CLI::log`) + a `WP_CLI\Utils\format_items()` summary
   table and final `WP_CLI::success()`.

---

## 6. Decisions (resolved) & remaining questions

### Resolved
1. **Config linkage.** ✅ **Decided:** operator supplies `--config-id` (required); the CLI
   attaches that single config to every created tier. The command validates the post
   exists and is a `wicket_mship_config` before writing.
2. **Individual vs organization.** ✅ **Confirmed distinguishable** via `attributes.type`
   in the one `memberships` endpoint (see §2.1). `--type` filters client-side on that
   field; `tier_data['type']` is set from it.
3. **Pagination.** ✅ **Confirmed JSON:API pagination** (opt-in via `page[size]`, follow
   `links.next`). The fetch loops until `links.next` is `null`.
4. **Localization.** ✅ Use `name_en` for `mdp_tier_name` (matches the admin dropdown);
   `name_fr` / `name_es` also available if multi-locale naming is wanted later.

### Resolved (cont.)
9. **Products — create tiers with empty `product_data`; no placeholder.** ✅ Product
   selection happens later in the admin UI. Verified:
   - The CLI writes `tier_data` directly (`wp_insert_post()` + `update_post_meta()`), which
     **bypasses the REST `product_data` validation** — so the "≥1 product required" rule
     does not apply to the CLI path. Empty `product_data` writes fine.
   - Empty `product_data` is safe downstream: `Membership_Tier::get_all_tier_product_ids()`
     guards with `if ( $products_data )` (`Membership_Tier.php:50`) and skips product-less
     tiers, so they never reach product lookups.
   - A **placeholder must NOT be used.** `Membership_Tier_CPT_Hooks::render_page()`
     (`:114-115`) calls `wc_get_product( $id )->is_type( … )` with no null guard while
     rendering the tier admin; a non-existent `product_id` returns `false` and fatals the
     **entire** tier admin screen (`Error: is_type() on bool`). Even a real non-subscription
     product would fail REST validation (`Membership_Post_Types.php:590`) when the admin
     later saves. So a placeholder would have to be a genuine subscription product — exactly
     the manual step we are deferring. Empty is correct.
   - ⚠️ **Quick manual verify:** confirm the React tier-edit app loads an existing tier with
     empty `product_data` without JS error. Low risk — the "Add New Tier" flow already
     starts from empty products, so the editor handles the empty case by design.

### Still to confirm during implementation
5. **Update policy.** On an existing tier, only refresh `mdp_tier_name`, or leave the local
   post fully untouched? Proposal: refresh name only, never touch config/products.
   - yes
6. **`approval_required` mapping.** Staging shows only `approval_not_required`. Confirm the
   opposite enum value (assumed `approval_required`) against a tenant that uses it before
   auto-seeding `tier_data['approval_required']`. Safe fallback: default to `false`.
   - yes always false
7. **Server-side type filter.** Client-side filtering on `attributes.type` is fine at
   current scale (30 tiers). A `filter[type]` query param could reduce payload but is
   unverified — optional optimization, not required.
   - no
8. **Naming / menu.** Confirm the command namespace (`wicket-mship tier`) fits any future
   `wp wicket-mship ...` command family.

---

## 7. Idempotency & safety

- **Keyed on `mdp_tier_uuid`** via `get_tier_id_by_wicket_uuid()` — re-running `sync` never
  creates duplicates.
- **`--dry-run` is the recommended first run** and writes nothing.
- **Live writes gated** behind `--yes` / `WP_CLI::confirm()`.
- **Fail closed on API errors** — if the MDP is down or returns empty, abort rather than
  create tiers with missing linkage.
- **No deletes.** The tool only creates (and optionally name-updates). MDP tiers that have
  no local match are *reported*, never auto-created-then-removed; local tiers absent from
  the MDP are left alone (flagged in output for operator review).

---

## 8. Testing plan

- **Unit (PHPUnit + Brain Monkey):** mock `get_individual_memberships()` to return a fixed
  JSON:API fixture; assert the reconciliation plan (create vs skip) and the exact
  `tier_data` written. Use existing `tests/factories/` for tier/config posts.
- **Idempotency:** run `sync` twice against the same fixture → second run creates 0.
- **Dry-run:** assert zero posts written and a correct plan printed.
- **Error path:** `wicket_api_client()` returns `false` → command errors, writes nothing.
- **Manual QA:** on a dev site with MDP creds, `tier list --source=mdp`, then
  `tier sync --dry-run`, then a live `sync`, then verify tiers appear in the admin list
  with correct UUID linkage.

---

## 9. Rollout

1. Land command + `tier list` + `--dry-run` first (read-only surface, low risk).
2. QA dry-run output against a real MDP tenant.
3. Enable live `sync` behind confirmation.
4. Document usage in `docs/guides/` (operator-facing) alongside `membership-sync.md`.

---

## 10. Out of scope / future work

- Linking WooCommerce products/variations to created tiers (`product_data`).
- Setting renewal type, seat type, approval, and callout content.
- Creating or reconciling `wicket_mship_config` posts.
- Pushing WordPress → MDP (this tool is read-from-MDP only).
- Deleting/archiving local tiers whose MDP tier was removed.

---

## 11. Effort estimate (rough)

| Piece | Est. |
|---|---|
| Command scaffold + registration + `tier list` | 0.5 day |
| `tier sync` fetch/reconcile/create + dry-run + summary | 1 day |
| Config-linkage decision + edge cases (pagination, type) | 0.5 day |
| PHPUnit tests | 0.5 day |
| Operator docs + QA | 0.5 day |
| **Total** | **~3 days** (pending §6 answers) |

---

## 12. Usage reference (as built)

> **Status: implemented.** Command class: `Wicket_Memberships\CLI\Tier_Sync_Command`
> (`includes/CLI/Tier_Sync_Command.php`), registered in `wicket.php` under a
> `defined( 'WP_CLI' ) && WP_CLI` guard. Verified end-to-end against the staging MDP
> (`memberships-test-api.staging.wicketcloud.com`).

### Prerequisites

- The `wicket-wp-memberships` plugin is active and WP-CLI is available in the environment.
- Wicket MDP settings are configured (JWT + endpoint); the command aborts if the API
  client is unavailable.
- A valid `wicket_mship_config` post exists on **this** site — its ID is passed to
  `--config-id`. Config IDs are environment-specific (they differ per site).

### Commands

```
wp wicket-mship tier sync --config-id=<post_id> [--type=<type>] [--dry-run] [--yes] [--format=<format>]
wp wicket-mship tier list [--source=<source>] [--format=<format>]
```

#### `tier sync` — create local tiers from the MDP

Fetches every MDP tier (paginated), matches each against local tiers by `mdp_tier_uuid`,
and creates the missing ones. Created tiers are attached to `--config-id`, linked by UUID,
and left with **no products** (added later in the admin UI). Existing tiers are skipped;
only a drifted `mdp_tier_name` is refreshed.

| Arg | Required | Default | Values | Purpose |
|---|---|---|---|---|
| `--config-id=<post_id>` | **Yes** | — | any valid `wicket_mship_config` post ID | Config attached to every created tier. Validated (`get_post()` + post type) before any MDP call. |
| `--type=<type>` | No | `all` | `all` \| `individual` \| `organization` | Only sync MDP tiers of this type (filtered on `attributes.type`). |
| `--dry-run` | No | off | flag | Compute and print the plan; write nothing. Recommended first run. |
| `--yes` | No | off | flag | Skip the interactive confirmation before writing. |
| `--format=<format>` | No | `table` | `table` \| `json` \| `csv` | Output format for the plan/results table. |

Behavior notes:
- **Fails fast** with a clear error if `--config-id` is missing/invalid, or if the MDP
  client is unavailable — before writing anything.
- **Idempotent** — re-running creates nothing already linked by `mdp_tier_uuid`.
- Live writes require `--yes` or an interactive `y/n` confirmation.
- Per created tier, `tier_data` is written as: `mdp_tier_uuid`, `mdp_tier_name` (`name_en`),
  `config_id`, `type`, `renewal_type: subscription` (benign default), `approval_required`
  (from MDP `approval`), `product_data: []`, and — for organization tiers — `seat_type`
  (`unlimited_assignments ? per_range_of_seats : per_seat`). `membership_tier_slug` is set
  from the MDP `slug` by the existing `save_post_wicket_mship_tier` hook.

#### `tier list` — read-only preview / diff

| Arg | Required | Default | Values | Purpose |
|---|---|---|---|---|
| `--source=<source>` | No | `mdp` | `mdp` \| `local` | Read tiers from the MDP, or from local tier posts. |
| `--format=<format>` | No | `table` | `table` \| `json` \| `csv` \| `count` \| `ids` | Output format. |

### What a dry run looks like

```console
$ wp wicket-mship tier sync --config-id=1704 --dry-run
Fetching membership tiers from the MDP...
action   type          name                                     uuid                                   post_id
skip     individual    Full Member                              6edcfa3a-d859-4f41-a19d-9cfe77ea85ff   1679
skip     individual    WooSimplePlan 1                          22f3984d-c6be-482b-8397-08ad1fb754e0   1230
create   individual    Student Member                           3fd27d2f-e47d-4a1d-9839-31097f9d8c7d
create   organization  Veterinary Clinic Membership for 2 - 5   53580191-ffad-477e-83ee-aceea9265a3b
create   organization  Veterinary Clinic Membership for 6+      4caf0d73-48e6-47e2-86a0-b642ea975e1d
...
Plan: 14 to create, 0 to update (name), 16 unchanged.
Success: Dry run complete — no changes written.
```

- `skip` — a local tier already links this UUID (its post ID is shown); nothing changes.
- `create` — no local tier links this UUID; it would be created (blank `post_id`).
- `update` — the local tier exists but its `mdp_tier_name` drifted; the name would be
  refreshed (not shown above; count reported in the `Plan:` line).

Dropping `--dry-run` prints the same plan, then prompts:

```console
Create 14 and update 0 local tier(s)? [y/n] y
Created tier #5168 "Student Member" (3fd27d2f-d859-...).
...
Success: Done. Created 14, updated 0, errors 0.
```

### Typical workflow

```console
# 1. See what the MDP has.
wp wicket-mship tier list --source=mdp

# 2. Preview the sync against the target config (writes nothing).
wp wicket-mship tier sync --config-id=1704 --dry-run

# 3. Apply it.
wp wicket-mship tier sync --config-id=1704

# 4. Finish each tier in the admin UI: attach the WooCommerce subscription product(s)
#    and confirm renewal settings.
```
