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

Membership bundle functionality is complete. Key concepts:
- A **Membership Bundle** (`wicket_mship_bundle`) is a container post that holds individual membership records and is linked to an MDP organisation.
- A **Membership Bundle Config** (`wicket_mship_bcfg`) defines date calculation, renewal windows, grace periods, and renewal type.
- Bundles have their own WooCommerce subscription; individual memberships are line items on that subscription.

For full implementation details refer to `docs/engineering/Membership_Bundle.md` and related `docs/engineering/Membership_Bundle_*.md` docs.

## Membership Status Vocabulary

Use these exact status strings consistently — they appear in meta, REST responses, UI labels, and MDP sync logic:

`pending` | `active` | `delayed` | `grace-period` | `expired` | `cancelled`

Do not invent synonyms.

## Terminology Convention

- Use `membership bundle` as the preferred term everywhere going forward.
- Do not introduce the previous terminology in new code, docs, UI copy, comments, or implementation notes.
- When older context or comments use outdated wording, rewrite that to `membership bundle`.
- If a rename touches identifiers, keep the implementation internally consistent across PHP, JS, docs, and QA.

## Plugin Environment Flags

These options (stored in `wicket_membership_plugin_options`) are read at bootstrap and set `$_ENV` flags. Know them before adding conditional behaviour:

| Option key | `$_ENV` flag | Effect |
|---|---|---|
| `wicket_mship_subscription_renew` | `WICKET_MSHIP_SUBSCRIPTION_RENEW` | Enables subscription-based renewal path |
| `bypass_wicket` | `BYPASS_WICKET` | Skips MDP API calls (local dev / testing) |
| `wicket_membership_debug_mode` | `WICKET_MEMBERSHIPS_DEBUG_MODE` | Enables verbose debug output |
| `bypass_status_change_lockout` | `BYPASS_STATUS_CHANGE_LOCKOUT` | Disables status-transition guards |
| `wicket_show_order_debug_data` | `WICKET_SHOW_ORDER_DEBUG_DATA` | Renders order debug info in admin |
| `allow_local_imports` | `ALLOW_LOCAL_IMPORTS` | Enables local CSV import path and memberships-sync.php |
| `wicket_mship_enable_bundles` | `WICKET_MSHIP_ENABLE_BUNDLES` | Shows Membership Bundles and Bundle Configs admin pages; off by default |

## TODO Tracking

- `TODO.md` in the plugin root is the authoritative list of outstanding work items.
- Whenever a `// TODO` comment is added to any file in this plugin, add a matching row to `TODO.md` in the same task. Include the file, method, a short note, and an Asana link if one exists.
- When a TODO is resolved, remove it from `TODO.md` and the code comment in the same change.
- Do not leave TODO comments in code without a corresponding `TODO.md` entry.

## Documentation Rules

- The `docs/` folder is part of the maintained source of truth for this plugin.
- When changing a class in `includes/`, update the matching file in `docs/class-index/`.
- When adding a new class to `includes/`, create a matching `.md` file in `docs/class-index/` in the same task.
- The class docs map 1:1 to the PHP files in `includes/`. Preserve that convention — never leave a class undocumented.
- When changing membership config or tier data structures, also update the related markdown file in `docs/`.
- Do not leave doc updates for later. Code and docs must change together whenever behavior, signatures, properties, fields, hooks, or responsibilities change.
- **Membership Bundles public docs** live in `docs/public/membership-bundles/`. When changing any of the following, update the corresponding public doc in the same task:
  - `Membership_Bundle` → `docs/public/membership-bundles/classes/membership-bundle.md`
  - `Membership_Bundle_Config` → `docs/public/membership-bundles/classes/membership-bundle-config.md`
  - `Membership_Bundle_Admin_Controller` → `docs/public/membership-bundles/classes/membership-bundle-admin-controller.md`
  - `Membership_Bundle_WP_REST_Controller` → `docs/public/membership-bundles/endpoints/` (relevant file)
  - `Membership_Bundle_Config_WP_REST_Controller` → `docs/public/membership-bundles/endpoints/bundle-config-dates.md`
  - Bundle status constants, lifecycle transitions, or cron behaviour → `docs/public/membership-bundles/concepts/bundle-lifecycle.md`
  - Renewal type, grace period, or cycle logic → `docs/public/membership-bundles/concepts/renewal-types.md`
  - Member add/remove/move semantics → `docs/public/membership-bundles/concepts/member-handling.md`
- **VitePress sidebar must be kept in sync.** The sidebar is defined in `docs/public/.vitepress/config.mts` — VitePress does not auto-discover pages. When adding a new `.md` file anywhere under `docs/public/`, add a matching entry to the `sidebar` array in `config.mts` in the same task. Never add a page without a sidebar entry, and never leave a sidebar entry pointing to a file that does not exist.

## External References

- `../wicket-wp-base-plugin` is an allowed reference dependency.
- You may inspect and rely on helper functions, autoloaded classes, and integration behavior from `wicket-wp-base-plugin`.
- Under no circumstances should you modify `wicket-wp-base-plugin` as part of memberships work unless the user explicitly changes that rule.
- If a memberships change appears to require a base-plugin fix, stop at the boundary, document the dependency, and ask before editing outside this plugin.

## Frontend UI Conventions

- **All dates displayed in the admin UI must use `formatDateWithTooltip(isoString)` from `frontend/src/shared/constants.js`.** This renders the date in the MDP timezone with the full ISO 8601 string (including UTC offset) as a hover tooltip. Never render a raw date string directly. Pass the raw ISO string from PHP — do not pre-format dates to `Y-m-d` before sending them to the frontend, as that discards timezone information needed by the tooltip.

## Working Conventions

- **Inline comments on non-trivial methods:** For methods that involve multiple logical phases (validation, DB writes, rollback, derived calculations, etc.), add a short inline comment before each phase explaining *why* it exists — not what the code does. This applies especially to static factory methods like `Membership_Bundle::create()` where the sequence of operations and their failure modes are not obvious from reading the code alone. Comments should explain constraints, invariants, and rollback guarantees; they should not restate what the method call already says.

- Check existing docs in `docs/` before changing class behavior so implementation and documentation stay aligned.
- Prefer extending existing classes/files over adding new abstractions unless the current structure is clearly insufficient.
- Keep public hooks, meta keys, option names, and status transitions backward-compatible unless the user asks for a breaking change.
- **After adding a new PHP class** in `includes/`, run `composer dump-autoload` to regenerate the PSR-4 autoload map. For production builds use `composer dump-autoload -o`.
- **Frontend changes**: edit source in `frontend/src/`. Use `npm run start` (from `frontend/`) for development builds and `npm run build` for production. Only commit built assets when the task explicitly requires it.
- Do not edit `vendor/` or `frontend/build/` manually.

## Tests

- Repository-wide rule: add new automated tests in `/srv/wicket-wp-stack/qa`, not in this plugin.
- Begin any testing task by reading `/srv/wicket-wp-stack/qa/README.md` and `/srv/wicket-wp-stack/qa/AGENTS.md`.
- Use the cheapest suite that proves the behavior, but prefer the QA repo as the home for all new coverage.
- The local `tests/` directory in this plugin is legacy/reference material unless the user explicitly instructs otherwise.

## Practical Review Checklist

- Were any `// TODO` comments added? If yes, was a matching row added to `TODO.md`?
- Were any TODOs resolved? If yes, was the row removed from `TODO.md`?
- Did the PHP implementation change in `includes/`, `custom/`, `frontend/src/`, or bootstrap code?
- If yes, were the corresponding docs in `docs/class-index/` updated (or created) in the same change?
- If a new class was added to `includes/`, was a matching `.md` created in `docs/class-index/`?
- If a new class was added to `includes/`, was `composer dump-autoload` run?
- Did the work reference `wicket-wp-base-plugin` without modifying it?
- If tests were added or updated, were they placed in `qa/` and run from there when possible?
- If the change touches membership bundle logic, were the relevant `docs/engineering/Membership_Bundle*.md` docs consulted?

## Current Branch Scope

`AGENTS.md` is defined on the parent branch and describes stable conventions that apply across all work in this plugin.
