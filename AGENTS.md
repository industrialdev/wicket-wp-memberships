# Repository Guidelines

## Architectural Guardrails: Preserving Procedural Design (Gary's Core Engine)

This plugin manages active subscriptions, recurring billing, and real client financial transactions. Gary authored the initial architecture around fast procedural WordPress flows, hooks, and direct state handling (`Membership_Controller.php`, `Membership_Config.php`, `Membership_Tier.php`).

### Non-Negotiable Agent Constraints

1. **Do not attempt OOP or coordinator refactoring**:
   - AI coding agents **MUST NOT** attempt to refactor the procedural or hook-driven architecture of this plugin into full OOP service layers, coordinator patterns, or micro-classes. This covers the entire plugin (`includes/`, `custom/`, `automate-woo/`, hooks, and CLI tools).
   - Any architectural modernization is strictly human-led. Modernization occurs only upon explicit human instruction, in a planned, dedicated effort. It must **never** be initiated or pushed by AI agents.
   - **Bug fixes and routine maintenance are welcome**: Normal bug fixes, field additions, and maintenance edits using the existing procedural and hook-driven style are expected and permitted.
2. **Follow code doc links to Atlas**:
   - Consult Atlas for shared contracts, public surfaces, and known traps:
     - `@see atlas://packages/wicket-wp-memberships.md`
     - `@see atlas://conventions/code-annotations.md`
     - `@see atlas://conventions/php-doc-comment-style.md`
     - `@see atlas://conventions/plugin-initialization.md#scope`
   - Atlas documents the **outer boundary** (which hooks fire, which REST routes are exposed, and quirks to avoid). Atlas does not mandate changing the internal coding style of this plugin.
3. **Strict adherence to DRY, KISS, and YAGNI**:
   - Keep implementations minimal, straightforward, and grounded in the immediate ticket requirements.
   - Do not create speculative abstractions, defensive wrapper classes, or handlers for hypothetical edge cases.
   - Preserve existing method signatures, global functions, and procedural hook handlers.

## Project Structure & Module Organization

This is a WordPress plugin rooted at `wicket.php`. It manages memberships on top of WooCommerce and WooCommerce Subscriptions, synchronizing with the Wicket MDP (Master Data Platform).

- `includes/`: Core PHP classes under `Wicket_Memberships\` namespace (controllers, models, REST API, settings, helpers, and utilities).
- `frontend/`: React/webpack admin UI apps. Source in `frontend/src/`, built output in `frontend/build/`.
- `custom/`: Optional hook integration files (Gravity Forms integration, multi-tier renewals, subscription sync).
- `automate-woo/triggers/`: AutomateWoo trigger classes for membership lifecycle events.
- `tests/`: PHPUnit tests and custom factories for CPTs and WooCommerce products.
- `docs/`: Feature documentation and reference material.
- `csv_import.php`, `csv_post.php`, `csv_import_threads.php`: CLI and HTTP CSV import tooling.

> For the complete class inventory, custom post type specifications, lifecycle state machine, and REST routes, see `@see atlas://packages/wicket-wp-memberships.md`.

## Build, Test, and Development Commands

- `composer install`: Install PHP dependencies.
- `cd frontend && npm install && npm run build`: Build React admin apps (webpack).
- `cd frontend && npm run start`: Watch mode for frontend development.
- `vendor/bin/phpunit`: Run local PHPUnit test suite.
- Integration tests: Run centralized QA suite at `./qa` (`wicket-warden`).
- Dependencies: Requires `wicket-wp-base-plugin`, WooCommerce, and WooCommerce Subscriptions.

## Coding Style & Documentation Standards

- PHP 8.1+, namespaced under `Wicket_Memberships\`.
- Classes use `PascalCase` with underscores (e.g. `Membership_Controller`). Methods use `snake_case`.
- Meta keys: Flat `post_meta` on membership posts; serialized arrays (`tier_data`, `cycle_data`) on tier/config posts.
- Dates: ISO 8601 strings anchored in UTC via `Utilities::get_mdp_day_start()` / `get_mdp_day_end()`.
- Documentation: Strictly follow `@see atlas://conventions/php-doc-comment-style.md`.
  - Add or update complete PHPDoc blocks on touched functions and methods.
  - Inline comments must explain *why* the code does what it does, never restate what the code does.

## Security & WordPress-Specific Requirements

- REST endpoints require `manage_options` capability (`WICKET_MEMBERSHIPS_CAPABILITY`).
- Merge webhook validates HMAC-SHA256 signatures against stored API key.
- Sanitize and validate all input; escape all output.
- Use WordPress APIs for data access and capability checks.

## Release & Branch Workflow

All work happens on branches. `main` is locked; changes land via peer-reviewed Pull Request (devs cross-review each other). Never commit to `main` directly, and never push or open a PR without explicit human approval.

Merging a PR to `main` **auto-releases** via the `wicket-release-bot` GitHub App: version bump, `CHANGELOG.md` update, git tag. Never bump versions or create tags by hand. The bump level comes from a marker in the PR title (squash-merge makes it the commit message): `_(none)_` / `#patch` = patch, `#minor`, `#major`, or `#norelease`. Conventional commit prefixes (`feat:`, `fix:`, `docs:`, ...) drive changelog grouping; a `!` (e.g. `feat!:`) flags a BREAKING change.
