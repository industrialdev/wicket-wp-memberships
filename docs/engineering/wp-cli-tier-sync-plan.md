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
                            [--config-id=<post_id>] [--create-products]
                            [--backfill-products] [--yes] [--format=<table|json|csv>]

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
| `--create-products` | Auto-create a matching simple subscription product per created tier and link it in `product_data` (opt-in — see §13). |
| `--backfill-products` | Also create + link a product for **existing** product-less tiers, not just newly created ones (opt-in — see §13). |
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

- Linking WooCommerce products/variations to created tiers (`product_data`) — **partially
  addressed** by the optional `--create-products` extension (§13), which creates one simple
  subscription product per tier. Multi-product, variable-subscription, and range-bucket
  linkage remain manual.
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

> With `--create-products` (§13), step 4's product attachment is done for you for the common
> one-product-per-tier case; you only confirm/adjust pricing and renewal settings.

---

## 13. Extension — auto-create subscription products per tier (`--create-products`, `--backfill-products`)

> **Status: implemented.** Extends the shipped `tier sync` command (§12) in
> `includes/CLI/Tier_Sync_Command.php`. Opt-in; the default behavior (empty `product_data`)
> is unchanged when neither flag is present.

### 13.1 Goal

Optionally have `tier sync` create one **WooCommerce simple subscription** product per tier,
named after the tier, and link it into that tier's `product_data`. This removes the manual
"attach a subscription product" admin step for the common one-product-per-tier case, so a
synced tier is immediately valid, orderable, and safe for the "create subscriptions for all
records" import path (§13.10).

Two independent, composable flags control the scope:

- **`--create-products`** — attach a product to tiers **created this run**.
- **`--backfill-products`** — attach a product to **existing** local tiers that currently
  have empty `product_data` (the `skip`/`update` rows), remediating tiers synced before this
  feature existed. Runnable on its own against an already-synced site.

Both share the same product-creation machinery (§13.3–13.4) and both are off by default.

### 13.2 The flags

| Flag | Required | Default | Applies to | Purpose |
|---|---|---|---|---|
| `--create-products` | No | off | rows with `action = create` | Create + link a product for each newly created tier. |
| `--backfill-products` | No | off | `skip`/`update` rows whose `product_data` is empty | Create + link a product for existing product-less tiers. |

Pass either, both, or neither. Both together = every tier ends the run with a product,
whether it was just created or already existed empty.

**Precondition (fail closed).** Either flag requires WooCommerce Subscriptions active
(`class_exists( 'WC_Product_Subscription' )`). If a product flag is passed and WCS is
unavailable, the command aborts before any write — same posture as the existing
config-validity and MDP-client checks in §5.

### 13.3 Product spec

Each auto-created product:

- **Type:** `subscription` (`WC_Product_Subscription`) — a *simple* subscription. This
  satisfies the tier product rule (`is_type( 'subscription' )`, `Membership_Post_Types.php:591`)
  and, crucially, renders safely in the tier admin: `Membership_Tier_CPT_Hooks::render_page()`
  (`:114-115`) calls `wc_get_product( $id )->is_type( … )` **unguarded**, so a real product
  avoids the fatal that a bogus/placeholder id would cause. This extension is therefore
  *safer* than hand-inserting a fake product id.
- **Name / title:** the tier's `name_en` (identical to `mdp_tier_name` and the tier post title).
- **SKU (idempotency key):** deterministic, derived from the tier UUID —
  `wicket-tier-<mdp_tier_uuid>`. Before creating, look up `wc_get_product_id_by_sku()` and
  **reuse** the existing product if found rather than duplicating. WooCommerce enforces SKU
  uniqueness, keeping both the create and backfill paths idempotent across re-runs — and it
  means a backfill will re-link an orphaned product a prior create had already made for that
  UUID rather than making a second one.
- **Billing defaults (placeholders):** price `0`, period `year`, interval `1`, length `0`
  (never auto-expires), no sign-up fee, no trial. These are safe placeholders the admin
  adjusts afterward — the tool's job is *linkage*, not pricing.
- **Status:** `publish`.

### 13.4 `product_data` written

A single entry linking the new product:

```php
[ 'product_id' => <new_product_id>, 'variation_id' => 0, 'max_seats' => <-1 | 1> ]
```

- `variation_id` is `0` — simple (non-variable) subscriptions have no variation. Validation
  only demands a variation for `variable-subscription` (`Membership_Post_Types.php:616`), and
  order→tier matching falls back to `product_id` (`Membership_Tier::get_tier_by_product_id`),
  so a `0`/empty variation matches correctly at checkout.
- `max_seats` per the seat-type table in §13.5.

### 13.5 Seat-type handling

A product is created for **every** synced tier regardless of type — including
`per_range_of_seats`. Organization products start at **qty 1**, which the admin can adjust
afterward for `per_seat`.

| Tier | Behavior with `--create-products` |
|---|---|
| individual | Create one product, `product_data` `max_seats = -1` (required by validation, `:638`). |
| organization, `per_seat` | Create one product, `max_seats = 1` (starting qty; the created membership's seat count later **tracks the purchased quantity**, so this is just a default the admin can raise). |
| organization, `per_range_of_seats` | Create one product, `max_seats = 1` (starting qty). The created membership's `max_seats` is `-1`/`null` at runtime (open-ended range) regardless, so qty 1 is a fine starting point. Multi-bucket range products remain a manual admin refinement (§10). |

> **Seat semantics (why the two org rows differ at runtime, not at creation):** `per_seat`
> memberships take their `max_seats` from the purchased order quantity (finite); range
> memberships get `-1`/`null`. The CLI does not encode that distinction into the product — it
> just seeds a qty-1 product for both. See §2.1 for the `unlimited_assignments` → `seat_type`
> mapping.

### 13.6 Idempotency & safety

- **Scoped by flag.** `--create-products` touches only `action = create` rows;
  `--backfill-products` touches only `skip`/`update` rows whose `product_data` is **empty**. A
  tier that already has products is never modified by either flag — backfill only fills a gap,
  it never clobbers or replaces an existing linkage.
- **Backfill is the one sanctioned exception to the name-only update policy.** Normally an
  existing tier's `tier_data` is only name-refreshed (§6.5); `--backfill-products` additionally
  writes `product_data` on an otherwise-empty tier. It is gated behind the explicit flag and
  the write confirmation, and it leaves every other `tier_data` field untouched.
- **SKU keyed on UUID** → re-running never creates a duplicate product, even after a
  half-completed prior run; a create and a later backfill for the same UUID resolve to the
  same product.
- **Ordering & cleanup.**
  - *Create path:* build (or find by SKU) the product first, then `wp_insert_post()` the tier
    with the product already in `product_data`. If the tier insert fails, **trash the
    just-created product** so no orphan is left; count the row as an error.
  - *Backfill path:* the tier already exists, so build (or find by SKU) the product, then
    `update_tier_data()` with the single-entry `product_data`. If the meta update fails, trash
    a product this run created (leave a reused one alone); count the row as an error.
- **`--dry-run` creates no products.** The plan gains a `product` column
  (`create` / `backfill` / `exists` / `—`) so the operator sees exactly what would be made and
  against which tiers. The column only appears when a product flag is set.

### 13.7 Code changes

- `sync()`: read `--create-products` and `--backfill-products`; when either is set, assert WCS
  availability alongside the existing config/MDP preconditions.
- **New** `product_for_tier( string $uuid, string $name, string $type, string $seat_type ): int|\WP_Error`
  — resolve SKU (`wicket-tier-<uuid>`), reuse via `wc_get_product_id_by_sku()` or build a
  `WC_Product_Subscription`, set name/SKU/billing meta, save, return the id. Shared by both
  paths (create passes MDP-derived values; backfill passes the existing tier's `tier_data`).
- **New** `single_product_data( int $product_id, string $type, string $seat_type ): array`
  — builds the one-entry `product_data` (`max_seats` per the §13.5 table). Used by both paths.
- `create_tier()` / `build_tier_data()`: when `--create-products` is on, seed `product_data`
  from `single_product_data()` instead of `[]`.
- **New** `backfill_tier_product( int $post_id )` — for a `skip`/`update` row whose tier has
  empty `product_data`: create/find the product, then `update_tier_data()` with the new
  `product_data`; trash a freshly-created product on failure.
- The plan builder: for `skip`/`update` rows, also detect "empty `product_data`" so the
  `product` column can show `backfill "<name>"`; fold the backfill count into the confirm
  prompt and final summary.
- `print_results()` / the plan rows: add a `product` column for dry-run visibility.

### 13.8 Decisions / open questions

- **Backfill existing product-less tiers.** ✅ **Adopted** as `--backfill-products` (§13.2).
  It is the sanctioned exception to the name-only update policy (§13.6) and the remediation
  path for tiers synced empty before this feature — directly the failure surfaced in §13.10.
  Kept as a *separate* opt-in flag (not folded into `--create-products`) so an operator can
  remediate an existing site without also re-running a create pass.
- **Configurable billing?** v1 hardcodes yearly / price `0` placeholders. `--product-price`
  / `--product-period` could follow; not required for linkage.
- **`per_range_of_seats`** gets a single qty-1 product like every other tier; multi-bucket
  range refinement stays manual (§10).

### 13.9 Usage

```console
# Create missing tiers AND a matching product for each (preview, then apply).
wp wicket-mship tier sync --config-id=1704 --create-products --dry-run
wp wicket-mship tier sync --config-id=1704 --create-products

# Remediate an already-synced site: add products to existing product-less tiers only.
wp wicket-mship tier sync --config-id=1704 --backfill-products --dry-run
wp wicket-mship tier sync --config-id=1704 --backfill-products

# Belt and braces: new tiers get products AND existing empty ones are backfilled.
wp wicket-mship tier sync --config-id=1704 --create-products --backfill-products
```

Dry-run plan with the extra `product` column (both flags on):

```console
action   type          name              uuid                                   post_id  product
create   individual    Student Member    3fd27d2f-e47d-4a1d-9839-31097f9d8c7d            create
create   organization  Vet Clinic 6+     4caf0d73-48e6-47e2-86a0-b642ea975e1d            create
skip     individual    Full Member       6edcfa3a-d859-4f41-a19d-9cfe77ea85ff   1679     backfill
skip     individual    WooSimplePlan 1   22f3984d-c6be-482b-8397-08ad1fb754e0   1230     exists
```

- `create` — a new tier and its product would both be made.
- `backfill` — an existing tier has empty `product_data`; a product would be made and linked.
- `exists` — the existing tier already has products; left untouched.
- `—` — no product action for this row (flag not set for this action, or `--type` filtered it).

### 13.10 Interaction with the membership import ("create subscriptions for all records")

This extension directly fixes a latent failure in the import path. When
`wicket_mship_import_create_subscriptions` is enabled, every active imported membership calls
`Import_Controller::createSubscriptionForRenewal()` (`includes/Import_Controller.php:129`,
`:243`, `:308`), which does:

```php
$products   = $Membership_Tier->get_products_data();
$product_id = !empty( $products[0]['variation_id'] ) ? $products[0]['variation_id'] : $products[0]['product_id'];
$wc_product = wc_get_product( $product_id );   // false when product_data is empty
$subscription->add_product( $wc_product );      // fatal on false (:338)
```

- **Product-less tier (today):** `$products[0]` is undefined → `$product_id` is `null` →
  `wc_get_product( null )` returns `false` → `add_product( false )` **fatals** the record.
- **After `--create-products` / `--backfill-products`:** `product_data[0]` resolves to a real
  simple subscription product (`variation_id` is `0`, so it falls through to `product_id`),
  `wc_get_product()` returns a valid product, and the import succeeds. Price `0` is correct —
  the method creates no order and takes no payment.

**Operator sequencing:** run the product-creating sync **before** the import. For a site whose
tiers were already synced empty, `--backfill-products` is the remediation to run first.
