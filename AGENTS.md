# Repository Guidelines

## Project Structure & Module Organization
This repository is a WordPress plugin centered on membership workflows.
- `wicket.php`: plugin bootstrap and entry point.
- `includes/`: PHP domain logic (controllers, CPT hooks, settings, utilities) under `Wicket_Memberships\\` PSR-4 autoloading.
- `tests/`: Pest/PHPUnit tests, bootstrap, and WP test factories.
- `frontend/src/`: admin UI source files; builds output to `frontend/build/`.
- `assets/`: static JS/CSS consumed by WordPress screens.
- `docs/`: data structure notes and class index docs.
- `bin/install-wp-tests.sh`: local WP test environment helper.

## Build, Test, and Development Commands
Run commands from the plugin root unless noted.
- `composer install`: install PHP dependencies.
- `composer test`: run full Pest suite.
- `composer run test:unit`: run unit-focused tests.
- `composer run test:coverage`: generate HTML coverage in `coverage/`.
- `composer run lint`: dry-run PHP-CS-Fixer checks.
- `composer run format`: apply PHP-CS-Fixer formatting.
- `npm --prefix frontend run start`: watch/rebuild frontend assets during development.
- `npm --prefix frontend run build`: production frontend bundle.

## Coding Style & Naming Conventions
- PHP target: 8.0+ (prefer 8.2-compatible code), PSR-12 formatting.
- Enforce style with PHP-CS-Fixer (`.php-cs-fixer.dist.php`).
- Use strict validation/sanitization for all user input and escape output in templates.
- Prefer WordPress APIs (`wpdb`, hooks, capability checks, nonces) over custom plumbing.
- Naming: classes in `PascalCase` (e.g., `Membership_Controller`), methods as verbs, variables as descriptive nouns.

## Testing Guidelines
- Frameworks: Pest with PHPUnit + WordPress test bootstrap.
- Add tests under `tests/` with `*Test.php` suffix.
- Extend shared base test classes when available to reuse setup.
- Cover membership creation/renewal flows and permission/security checks for changed behavior.

## Commit & Pull Request Guidelines
- Current history favors short, imperative commit subjects (e.g., `restore format`, `better composer test`). Keep subject lines specific and scoped.
- PRs should include:
  - concise problem/solution summary,
  - linked issue or task reference,
  - test evidence (`composer test`, lint output),
  - screenshots or GIFs for admin/frontend UI changes.
- Keep diffs focused; avoid bundling refactors with behavior changes.

## Security & Configuration Tips
- Never bypass nonce/capability checks in admin or REST handlers.
- Sanitize on input, validate before persistence, escape on output.
- Use `composer run production` only for release-ready, no-dev dependency installs.
