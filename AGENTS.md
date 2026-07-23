# Wicket Memberships Agent Guide

This file applies to work inside `src/web/app/plugins/wicket-wp-memberships`.

## Requirements

- PHP 8.1+, WordPress 6.5+
- Required plugins: `wicket-wp-base-plugin`, `woocommerce`, `woocommerce-subscriptions`
- PSR-4 namespace: `Wicket_Memberships\`

## Scope

- Focus changes in this plugin unless the user explicitly asks for cross-repo work.
- Treat `wicket.php` as the plugin bootstrap and dependency entry point.
- Keep changes aligned with existing WordPress, WooCommerce, and WooCommerce Subscriptions patterns already used here.

## Primary Code Map

- `wicket.php`: plugin bootstrap — defines constants, loads composer autoloader, initialises all controllers.
- `includes/`: main PHP classes, autoloaded via PSR-4 as `Wicket_Memberships\`.
- `custom/gravity-forms-multi-tier.php`: Gravity Forms multi-tier membership integration hooks.
- `custom/membership-code-hooks.php`: membership code / promo-code hook integration.
- `custom/memberships-sync.php`: MDP sync hooks (loaded only when `allow_local_imports` option is enabled).
- `automate-woo/triggers/`: AutomateWoo trigger classes.
- `csv_import.php`, `csv_import_threads.php`, `csv_post.php`: root-level CSV bulk-import entry points (used by Import_Controller).
- `bin/`: shell utilities (e.g., `install-wp-tests.sh` for setting up a local PHPUnit environment).
- `docs/class-index/`: markdown reference docs for the classes in `includes/`. Every PHP class file in `includes/` must have a corresponding `.md` file here — 1:1 mapping is required.
- `docs/membership_config_data_structure.md`: membership config data shape reference.
- `docs/membership_tier_data_structure.md`: membership tier data shape reference.
- `frontend/src/`: React/admin UI source.
- `frontend/build/`: built frontend assets (committed, do not edit manually).
- `assets/`: plugin CSS/JS assets loaded by WordPress.
- `vendor/`: composer-managed dependencies (do not edit manually).

## Active Feature Context


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

## Commit & Pull Request Guidelines
Keep commits focused with short, imperative messages. PRs should include purpose, test evidence, and screenshots for UI changes. Link related issues and note breaking changes.

## Release & Branch Workflow
All work happens on branches. `main` is locked; changes land via peer-reviewed
Pull Request (devs cross-review each other). Never commit to `main` directly, and never push or open a
PR without explicit human approval.

Merging a PR to `main` **auto-releases** via the `wicket-release-bot` GitHub
App: version bump, `CHANGELOG.md` update, git tag. Never bump versions or
create tags by hand. The bump level comes from a marker in the PR title
(squash-merge makes it the commit message): _(none)_ / `#patch` = patch, `#minor`,
`#major`, or `#norelease` (no release; use for docs/tooling-only merges).
Conventional commit prefixes (`feat:`, `fix:`, `docs:`, ...) drive changelog
grouping; a `!` (e.g. `feat!:`) flags a BREAKING change.
