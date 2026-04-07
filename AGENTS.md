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
- `docs/class-index/`: markdown reference docs for the classes in `includes/`.
- `docs/membership_config_data_structure.md`: membership config data shape reference.
- `docs/membership_tier_data_structure.md`: membership tier data shape reference.
- `CURRENT_SCOPE.md`: active product-scope document for features in development. Read this before working on group membership features.
- `frontend/src/`: React/admin UI source.
- `frontend/build/`: built frontend assets (committed, do not edit manually).
- `assets/`: plugin CSS/JS assets loaded by WordPress.
- `vendor/`: composer-managed dependencies (do not edit manually).

## Active Feature Context

`CURRENT_SCOPE.md` describes the **Group Memberships** feature currently in development. Key concepts:

- A **Membership Group** is a container (WP custom post) that holds individual membership records and is linked to an MDP organisation.
- A **Membership Group Config** defines date calculation, renewal windows, grace periods, and renewal type for groups.
- Membership Groups have their own WooCommerce subscription; individual memberships within a group are represented as line items on that subscription.
- Admin flows covered: Create Group, Add/Remove/Move members, Bulk CSV import, Cancel Group, and detail/list views.

Read `CURRENT_SCOPE.md` in full before modifying anything related to group memberships or the group subscription model.

## Membership Status Vocabulary

Use these exact status strings consistently — they appear in meta, REST responses, UI labels, and MDP sync logic:

`pending` | `active` | `delayed` | `grace-period` | `expired` | `cancelled`

When CURRENT_SCOPE.md refers to statuses, it uses the same terms. Do not invent synonyms.

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

## Documentation Rules

- The `docs/` folder is part of the maintained source of truth for this plugin.
- When changing a class in `includes/`, update the matching file in `docs/class-index/`.
- The class docs currently map 1:1 to the PHP files in `includes/`. Preserve that convention.
- When changing membership config or tier data structures, also update the related markdown file in `docs/`.
- Do not leave doc updates for later. Code and docs should change together in the same task whenever behavior, signatures, fields, hooks, or responsibilities change.

## External References

- `../wicket-wp-base-plugin` is an allowed reference dependency.
- You may inspect and rely on helper functions, autoloaded classes, and integration behavior from `wicket-wp-base-plugin`.
- Under no circumstances should you modify `wicket-wp-base-plugin` as part of memberships work unless the user explicitly changes that rule.
- If a memberships change appears to require a base-plugin fix, stop at the boundary, document the dependency, and ask before editing outside this plugin.

## Working Conventions

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

- Did the PHP implementation change in `includes/`, `custom/`, `frontend/src/`, or bootstrap code?
- If yes, were the corresponding docs in `docs/` updated in the same change?
- If a new class was added to `includes/`, was `composer dump-autoload` run?
- Did the work reference `wicket-wp-base-plugin` without modifying it?
- If tests were added or updated, were they placed in `qa/` and run from there when possible?
- If the change touches group membership logic, was `CURRENT_SCOPE.md` consulted first?

## Current Branch Scope

`AGENTS.md` is defined on the parent branch and describes stable conventions that apply across all work in this plugin.

`CURRENT_SCOPE.md` is the scope-of-work document for the **current branch**. It defines what is being built right now — features, acceptance criteria, and implementation notes specific to this development cycle. Always read `CURRENT_SCOPE.md` before beginning any task on this branch to understand the current goals and constraints.
