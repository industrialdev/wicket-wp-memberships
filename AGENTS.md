# Repository Guidelines

## Project Structure & Module Organization
This is a WordPress plugin (`wicket-wp-memberships`) rooted at `wicket.php`. It manages memberships on top of WooCommerce and WooCommerce Subscriptions, synchronizing with the Wicket MDP (Master Data Platform).

- `includes/`: Core PHP classes under `Wicket_Memberships\\` namespace — controllers, models, REST API, settings, helpers, and utilities.
- `frontend/`: React/webpack admin UI apps. Source in `frontend/src/`, built output in `frontend/build/`.
- `custom/`: Optional hook files loaded conditionally — Gravity Forms integration, multi-tier renewals, subscription sync tool.
- `automate-woo/triggers/`: AutomateWoo trigger classes for membership lifecycle events.
- `tests/`: PHPUnit tests and custom factories for CPTs and WooCommerce products.
- `docs/`: Feature documentation and reference material.
- `csv_import.php`, `csv_post.php`, `csv_import_threads.php`: CLI and HTTP CSV import tooling.

### Key Classes (includes/)
| Class | Purpose |
|---|---|
| `Membership_Controller` | Core business logic — creates memberships from orders, manages lifecycle, syncs to MDP, schedules events via Action Scheduler |
| `Membership_WP_REST_Controller` | REST API (`wicket_member/v1`) — search, CRUD, status management, merge webhook, import endpoints |
| `Admin_Controller` | Admin menu pages, status transition validation, React app mounting |
| `Membership_Post_Types` | Registers three CPTs (`wicket_membership`, `wicket_mship_tier`, `wicket_mship_config`) and their REST fields |
| `Membership_Tier` | Model for tier posts — product linkage, renewal type, MDP UUID lookup |
| `Membership_Config` | Model for config posts — renewal windows, grace periods, cycle calculations (calendar/anniversary) |
| `Membership_Subscription_Controller` | Creates WCS subscriptions from membership orders |
| `Import_Controller` | CSV import — individual and organization memberships, delta imports, delayed activation |
| `Helper` | Static utilities — CPT slugs, status names, allowed transitions, logging |
| `Utilities` | WooCommerce integration hooks — cart/checkout modifications, product protection, org search metabox, timezone date helpers |
| `Settings` | Plugin options page — feature flags, debug toggles, scheduled action status |
| `Membership_CPT_Hooks` | Admin list table columns and React edit page rendering for memberships |
| `Membership_Tier_CPT_Hooks` | Admin UI for tiers — list columns, trash protection, React edit page |
| `Membership_Config_CPT_Hooks` | Admin UI for configs — React edit page, trash protection |

### Custom Post Types
- **`wicket_membership`**: Individual membership records linked to users, WC orders, and subscriptions.
- **`wicket_mship_tier`**: Tier definitions linking MDP tiers to WC products/variations with renewal configuration.
- **`wicket_mship_config`**: Membership configurations defining renewal windows, grace periods, and billing cycles (calendar or anniversary).

### Frontend React Apps (frontend/src/)
| Entry | Purpose |
|---|---|
| `membership_configs/edit.js` | Create/edit membership configuration (renewal windows, late fees, cycle data) |
| `membership_tiers/edit.js` | Create/edit membership tier (product linkage, renewal type, grace periods) |
| `members/index.js` | Paginated membership list with status tabs, filters, and sorting |
| `members/edit.js` | Edit individual/org membership — dates, status, owner, renewal orders |
| `membership_tiers/member_count.js` | Member count widget for tier list |
| `membership_tiers/tier_cell_info.js` | Tier info cell display |

### REST API (wicket_member/v1)
Key endpoint groups:
- **Search & list**: `GET /memberships`, `GET /membership_filters`
- **Membership CRUD**: `GET /membership_entity`, `POST /membership_entity/{id}/update`, `POST /membership/{id}/change_owner`
- **Tier & org data**: `GET /membership_tiers`, `GET /membership_orgs`, `GET /product_tiers/{id}`
- **Status management**: `POST /admin/manage_status`, `GET /admin/status_options`
- **Renewals**: `POST /membership/{id}/create_renewal_order`, `GET /get_membership_callouts`
- **Webhook**: `POST /membership/merge` (HMAC-SHA256 verified from MDP)
- **Import** (conditional): `POST /import/person_memberships`, `POST /import/membership_organizations`

## Build, Test, and Development Commands
- `composer install`: install PHP dependencies.
- `cd frontend && npm install && npm run build`: build React admin apps (webpack).
- `cd frontend && npm run start`: watch mode for frontend development.
- `vendor/bin/phpunit`: run test suite.
- Plugin depends on: `wicket-wp-base-plugin`, WooCommerce, WooCommerce Subscriptions.

## Coding Style & Naming Conventions
- PHP 8.1+, namespaced under `Wicket_Memberships\\`.
- Classes use `PascalCase` with underscores (e.g., `Membership_Controller`). Methods use `snake_case`.
- CPT slugs: `wicket_membership`, `wicket_mship_tier`, `wicket_mship_config`.
- Meta keys are stored as flat `post_meta` on membership posts, and as serialized arrays (`tier_data`, `cycle_data`, etc.) on tier/config posts.
- Frontend uses React with WordPress components (`@wordpress/components`, `@wordpress/element`) and styled-components.
- Dates are stored as ISO 8601 strings, timezone-aware via `Utilities::get_mdp_day_start()` / `get_mdp_day_end()`.

## Testing Guidelines
- Frameworks: PHPUnit 8, Yoast PHPUnit Polyfills, Brain Monkey.
- Tests live in `tests/` with custom factories in `tests/factories/` for products, tiers, configs, and memberships.
- Base test class: `MembershipsBaseTest` (sets up factories and environment).
- Key test areas: membership creation, merge webhook, admin status transitions, factory functionality, helper utilities.
- When adding tests, prefer using the centralized QA suite at `./qa` per the stack AGENTS.md.

## Membership Lifecycle
Understanding the lifecycle is essential for working on this plugin:

1. **Order completed** -> `Membership_Controller::catch_order_completed()` creates membership record + MDP sync.
2. **Action Scheduler** runs daily hooks at 3:00/3:30/4:00 AM for activation, grace period, and expiry.
3. **Early renewal window** opens at `membership_early_renew_at` -> AutomateWoo trigger fires.
4. **End date reached** at `membership_ends_at` -> enters grace period if configured, AutomateWoo trigger fires.
5. **Grace period expires** at `membership_expires_at` -> membership expires, AutomateWoo trigger fires.
6. **Status transitions** are validated — see `Helper::get_allowed_transition_status()` for the state machine.

Valid statuses: `pending`, `active`, `grace_period`, `delayed`, `expired`, `cancelled`.

## Feature Flags (Environment / Settings)
Key flags that alter behavior:
- `WICKET_MSHIP_MULTI_TIER_RENEWALS`: Enables multi-tier renewal workflows and Gravity Forms integration.
- `WICKET_MSHIP_SUBSCRIPTION_RENEW`: Enables subscription-based renewal.
- `WICKET_MSHIP_ASSIGN_SUBSCRIPTION`: Enables linking subscriptions to memberships.
- `WICKET_MSHIP_AUTORENEW_TOGGLE`: Shows autorenew checkbox on subscriptions.
- `BYPASS_WICKET`: Skips MDP sync (useful for local dev).
- `ALLOW_LOCAL_IMPORTS`: Enables CSV import REST endpoints.
- `WICKET_MSHIP_MDP_TIMEZONE`: Timezone for date calculations (default: UTC).

## Security & WordPress-Specific Requirements
- REST endpoints require `manage_options` capability (`WICKET_MEMBERSHIPS_CAPABILITY`).
- Merge webhook validates HMAC-SHA256 signatures against stored API key.
- Sanitize and validate all input; escape output.
- Use WordPress APIs for data access and capability checks.

## PHP Documentation Standards

These rules exist to ensure developer clarity and understanding — so any engineer reading the code can quickly grasp intent, constraints, and non-obvious decisions without needing to trace through git history or ask the original author.

These rules apply to **every PHP file touched** — no exceptions, including single-line or trivial changes. No change is too small to be exempt. Skip only when explicitly told not to document in the current task.

### Before editing any PHP file — checklist
1. PHPDoc on every touched method (add if missing, update if signature changed).
2. Inline comments **must** be added to non-obvious logic within touched functions — prioritize clarifying *why* the code does what it does, not *what* it does.
3. These apply even to single-line variable renames or small tweaks.
4. When adding documentation only (PHPDoc, inline comments), never alter existing logic — formatting changes are allowed but no functional code changes.
### PHPDoc Blocks

Every function or method you **create or modify** must have a complete PHPDoc block. Existing functions that lack one must have one added when touched.

```php
/**
 * Brief one-line description ending with a period.
 *
 * Longer explanation when behaviour is non-obvious. Include side-effects,
 * WP hooks fired, external API calls made, or caveats a caller needs to know.
 *
 * @param  string   $membership_id  The Wicket membership UUID.
 * @param  int      $user_id        WP user ID; defaults to current user when 0.
 * @param  bool     $force_refresh  Bypass transient cache and re-query MDP.
 *
 * @return WP_Error|array  Membership data array on success, WP_Error on failure.
 */
```

**Required tags** (include every one that applies):

| Tag | When required |
|-----|--------------|
| `@param` | Every parameter, with type and description |
| `@return` | Always, even `void` functions — write `@return void` |
| `@throws` | When an exception can be thrown |
| `@since` | When adding a new public function |
| `@see` | When behaviour depends on another function or WP hook |
| `@global` | When accessing a WP global (`$wpdb`, `$post`, etc.) |

**Type syntax**: Use union types for nullable (`string|null`). Use `array<int, Membership>` or `string[]` when array shape is known. Use `WP_Error|TypeName` for WP error returns.

Descriptions must add information beyond the parameter name. `@param int $id The id.` is not acceptable.

### Inline Comments

Add a short inline comment wherever a reader would need to pause to reason through the code. Target the **goal** of the logic, not a restatement of what the code does.

**Correct** — states the objective of a multi-condition check:
```php
// Confirm the user holds an active, non-expired membership before granting access.
if ( $membership && 'active' === $membership['status'] && ! $membership['expired'] ) {
```

**Incorrect** — restates the code:
```php
// Check if membership exists, status equals active, and expired is false.
if ( $membership && 'active' === $membership['status'] && ! $membership['expired'] ) {
```

**Other situations that require an inline comment:**
- Non-obvious fallback or default value and the reason it exists.
- A WP quirk or third-party API limitation being worked around.
- Why a specific meta key or field is used instead of an obvious alternative.
- Branches that mirror a real-world business rule (grace-period logic, bundle vs. individual membership paths, calendar vs. anniversary cycle handling).
- Code written in a surprising way to avoid a known bug.

One short line is almost always enough. No paragraph-length comment blocks inside function bodies.

**Do not comment:**
- Code that already reads clearly from well-named identifiers.
- Standard WP boilerplate any WP developer recognises.
- The current task, PR number, or issue reference (those belong in the commit message).

---

## Commit & Pull Request Guidelines
Keep commits focused with short, imperative messages. PRs should include purpose, test evidence, and screenshots for UI changes. Link related issues and note breaking changes.

## Release Process (Automated)

Releases are **fully automated**. Merging a PR to `main` cuts a release via the `wicket-release-bot` GitHub App: it bumps the version, prepends `CHANGELOG.md`, commits `chore(release): x.y.z`, and pushes the matching git tag. No one needs push access to `main`.

**Never do these by hand:** bump the version, edit `composer.json` / the main file header / `*_VERSION` constants (and `style.css` for the theme), or create git tags. The bot owns all of that after merge.

### Releasing (default behavior)

Every PR merged to `main` releases automatically with a **patch** bump. Control the bump by putting a marker in the **PR title** (squash-merge makes the title the commit message):

| Marker | Result |
|---|---|
| _(none)_ | patch (`2.4.10` -> `2.4.11`) |
| `#minor` | minor (`2.4.10` -> `2.5.0`) |
| `#major` | major (`2.4.10` -> `3.0.0`) |
| `#norelease` | no bump, no tag |

### Not releasing

Add `#norelease` to the PR title for docs/tooling-only changes that should not cut a version. **Every merge releases unless the message contains `#norelease`.**

### Commit conventions that affect the changelog

- Use conventional prefixes: `feat:`, `fix:`, `docs:`, `chore:`, `perf:`, `refactor:`, etc. The changelog groups entries by prefix.
- `feat!:` (or any `!:`) flags a **BREAKING** change in the changelog.
- **Squash-merge** yields the cleanest changelog (one PR = one line). Merge commits list each individual commit.
- A release lists **everything merged since the last tag**, not just the triggering PR. Catch-up is expected.

### Local version bump (optional)

`composer version-bump` (or `php .ci/version-bump.php`) edits version files only; it never commits or tags. Use it to preview, not to release.

Full details, markers, and troubleshooting: [`docs/engineering/release-automation.md`](docs/engineering/release-automation.md).
