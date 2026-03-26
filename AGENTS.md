# Wicket Memberships Agent Guide

This file applies to work inside `src/web/app/plugins/wicket-wp-memberships`.

## Scope

- Focus changes in this plugin unless the user explicitly asks for cross-repo work.
- Treat `wicket.php` as the plugin bootstrap and dependency entry point.
- Keep changes aligned with existing WordPress, WooCommerce, and WooCommerce Subscriptions patterns already used here.

## Primary Code Map

- `includes/`: main PHP classes, autoloaded via PSR-4 as `Wicket_Memberships\\`.
- `docs/class-index/`: markdown reference docs for the classes in `includes/`.
- `docs/membership_config_data_structure.md`: membership config data shape reference.
- `docs/membership_tier_data_structure.md`: membership tier data shape reference.
- `frontend/src/`: React/admin UI source.
- `frontend/build/`: built frontend assets.
- `assets/`: plugin CSS/JS assets loaded by WordPress.
- `custom/`: plugin-specific custom hooks and local integration code.
- `automate-woo/`: AutomateWoo triggers.

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
- When changing frontend behavior, update source in `frontend/src/` and rebuild only if the task requires committed built assets.
- Do not edit `vendor/` or committed generated dependencies manually.

## Tests

- Repository-wide rule: add new automated tests in `/srv/wicket-wp-stack/qa`, not in this plugin.
- Begin any testing task by reading `/srv/wicket-wp-stack/qa/README.md` and `/srv/wicket-wp-stack/qa/AGENTS.md`.
- Use the cheapest suite that proves the behavior, but prefer the QA repo as the home for all new coverage.
- The local `tests/` directory in this plugin is legacy/reference material unless the user explicitly instructs otherwise.

## Practical Review Checklist

- Did the PHP implementation change in `includes/`, `custom/`, `frontend/src/`, or bootstrap code?
- If yes, were the corresponding docs in `docs/` updated in the same change?
- Did the work reference `wicket-wp-base-plugin` without modifying it?
- If tests were added or updated, were they placed in `qa/` and run from there when possible?
