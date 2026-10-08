---
title: "Settings Class Reference"
audience: [developer]
php_class: Settings
source_files: ["includes/Settings.php", "includes/Settings_Page.php", "includes/Settings_Fields.php", "includes/Settings_Jobs.php"]
---

# Settings Class Index

The Settings > Wicket Memberships page (`options-general.php?page=wicket-membership-settings`) is split across four classes.

| Class | File | Responsibility |
|---|---|---|
| `Settings` | includes/Settings.php | Public entry points used by `wicket.php` hooks and existing callers, options validation, MDP timezone default, tier slug migration. |
| `Settings_Page` | includes/Settings_Page.php | Page registration and render, tabs, notices, base plugin assets, body class, section intro text. |
| `Settings_Fields` | includes/Settings_Fields.php | Field registration and every field renderer. |
| `Settings_Jobs` | includes/Settings_Jobs.php | Scheduled Jobs tables and the manual Run Now triggers. |

Every public `Settings` method that existed before the split still exists and delegates to the class that now holds the code.

## Storage

All fields save to the single `wicket_membership_plugin_options` option. `Settings::wicket_membership_plugin_options_validate()` rebuilds the whole array from the posted fields, so every field must be posted on every save. The page therefore uses one form for all tabs; inactive tabs are only hidden.

Membership Categories are stored under `wicket_mship_membership_categories` and mirrored to the legacy `wicket_show_mship_order_org_search[categorychoice]` on save. Read them with `Helper::get_membership_category_ids()`.

## Tabs

| Tab | Sections |
|---|---|
| General | WooCommerce (Membership Categories, Membership Subscription Assignment), Wicket MDP (MDP Timezone, Merge Webhook API Key) |
| Renewals | Renewals, Auto-Renew |
| Import | Command-Line Import (with import steps), Import Subscription Options |
| Developer | Debug Settings, Admin Debug Output, Front-End Debug Output, Wicket MDP Sync, Scheduled Jobs |

Tabs map to Settings API page ids via `Settings_Page::settings_page_id( $tab )` (`wicket_membership_plugin_<tab>`).

## Settings

- `wicket_membership_add_settings_page()`, `wicket_membership_render_plugin_settings_page()`, `enqueue_settings_assets( $hook_suffix )`, `settings_admin_body_class( $classes )`: delegate to `Settings_Page`.
- `wicket_membership_register_settings()`, `get_form_membership_category_ids()` and each field renderer (for example `wicket_mship_disable_renewal()`): delegate to `Settings_Fields`.
- `get_next_scheduled_membership_activation()`, `get_next_scheduled_membership_grace_period()`, `get_next_scheduled_membership_expiry()`, `get_next_scheduled_membership_autorenew_audit()`: return the next run as `Y-m-d H:i`, or null. Delegate to `Settings_Jobs::get_next_run()`.
- `wicket_plugin_status_change_reporting()`, `handle_manual_action_links()`, `run_triggered_actions()`, `MANUAL_ACTION_TRIGGER_PARAMS`: delegate to `Settings_Jobs`.
- `wicket_show_mship_order_org_search()` (deprecated): renders the Membership Categories field.
- `wicket_membership_plugin_options_validate( $input )`: sanitizes the option on save and mirrors Membership Categories to the legacy key.
- `check_migrate_tier_slugs()`: adds missing tier slugs when the page renders.
- `ensure_timezone_default( $force_api_call = false )`, `fetch_mdp_timezone_from_api()`: set the MDP timezone default.

## Settings_Page

- `add_page()`, `render()`: register and render the page.
- `enqueue_assets( $hook_suffix )`: loads the base plugin's `wicket_admin.css`, select2 and `wicket_admin.js` on this screen only.
- `body_class( $classes )`: adds `wicket-admin-settings` so the base plugin's scoped styles apply.
- `render_environment_notices()`: warns when local import or sync tools are active.
- `render_category_switch_notice()`: before Membership Categories is first saved, warns when saving would change the base plugin's Hide redirects.
- `section_*()`: section intro text.

## Settings_Fields

- `register()`: registers sections and fields per tab. Field ids, option keys and input names are the option keys.
- `render_checkbox()` (private): shared checkbox renderer with inline label, description and default.
- `get_form_membership_category_ids()`: categories the form pre-selects; the "Membership" category on sites that never saved either key.
- One renderer per field, named after its option key.

## Settings_Jobs

- `get_next_run( $hook, $group = '' )`: next pending run of an Action Scheduler hook.
- `render()`: Daily Membership Jobs and Diagnostics & Maintenance tables. Job names link to Tools > Scheduled Actions filtered by hook.
- `MANUAL_ACTION_TRIGGER_PARAMS`, `handle_manual_action_links()`, `run_triggered_actions()`: Run Now / Refresh All Now links. Nonce (`wicket_mship_manual_action`) and `manage_options` checked; redirects to strip the trigger.
