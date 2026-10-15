---
title: "Settings Class Reference"
audience: [developer]
php_class: Settings
source_files: ["includes/Settings.php", "includes/Settings_Page.php", "includes/Settings_Fields.php", "includes/Settings_Jobs.php", "includes/Subscriptions_Mode.php"]
---

# Settings Class Index

The Settings > Wicket Memberships page (`options-general.php?page=wicket-membership-settings`) is split across four classes, plus `Subscriptions_Mode` for one Developer field.

| Class | File | Responsibility |
|---|---|---|
| `Settings` | includes/Settings.php | Public entry points used by `wicket.php` hooks and existing callers, options validation, MDP timezone default, tier slug migration. |
| `Settings_Page` | includes/Settings_Page.php | Page registration and render, tabs, notices, base plugin assets, body class, section intro text. |
| `Settings_Fields` | includes/Settings_Fields.php | Field registration and every field renderer. |
| `Settings_Jobs` | includes/Settings_Jobs.php | Scheduled Jobs tables and the manual Run Now triggers. |
| `Subscriptions_Mode` | includes/Subscriptions_Mode.php | Shows and switches WooCommerce Subscriptions' live/staging mode. |

Every public `Settings` method that existed before the split still exists and delegates to the class that now holds the code.

## Storage

All fields save to the single `wicket_membership_plugin_options` option. `Settings::wicket_membership_plugin_options_validate()` rebuilds the whole array from the posted fields, so every field must be posted on every save. The page therefore uses one form for all tabs; inactive tabs are only hidden.

`wicket_mship_hide_subscription_cancel`, `wicket_mship_hide_subscription_reactivate` and `wicket_mship_hide_subscription_resubscribe` (all default off) remove the Cancel, Reactivate and Resubscribe actions from the member's View Subscription page via `Utilities::maybe_hide_subscription_actions()` on `wcs_view_subscription_actions`. They only hide the buttons; admins can still change subscriptions in WooCommerce.

Membership Categories are stored under `wicket_mship_membership_categories` and mirrored to the legacy `wicket_show_mship_order_org_search[categorychoice]` on save. Read them with `Helper::get_membership_category_ids()`.

## Tabs

| Tab | Sections |
|---|---|
| General | WooCommerce (Membership Categories, Membership Subscription Assignment, Hide Subscription Self-Serve Cancellation, Reactivation and Resubscribe), Wicket MDP (MDP Timezone, Merge Webhook API Key) |
| Renewals | Renewals, Auto-Renew |
| Import | Command-Line Import (with import steps), Import Subscription Options |
| Developer | Debug Settings, Admin Debug Output, Front-End Debug Output, Wicket MDP Sync, WooCommerce (Subscriptions Mode), Scheduled Jobs |

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
- `render_accordion( $id, $title, $content )`: collapsed panel using core's admin accordion markup and script.
- `section_*()`: section intro text.

## Settings_Fields

- `register()`: registers sections and fields per tab. Field ids, option keys and input names are the option keys.
- `render_checkbox()` (private): shared checkbox renderer with inline label, description and default.
- `get_form_membership_category_ids()`: categories the form pre-selects; the "Membership" category on sites that never saved either key.
- One renderer per field, named after its option key.

## Settings_Jobs

- `get_next_run( $hook, $group = '' )`: next pending run of an Action Scheduler hook.
- `render()`: Daily Membership Jobs and Diagnostics & Maintenance tables. Job names link to Tools > Scheduled Actions filtered by hook.
- `MANUAL_ACTION_TRIGGER_PARAMS`, `handle_manual_action_links()`, `run_triggered_actions()`: Run Now / Refresh All Now links and the Subscriptions Mode switches. Nonce (`wicket_mship_manual_action`) and `manage_options` checked; redirects to strip the trigger.
- `get_manual_action_link( $trigger_param )`: nonced URL for one of those triggers.

## Subscriptions_Mode

WooCommerce Subscriptions treats the site as staging when its URL differs from the live URL saved in `wc_subscriptions_siteurl`. Staging forces manual renewal and stops subscription emails. Nothing is stored in `wicket_membership_plugin_options`, so copying settings to another site never carries live mode with it.

- `is_available()`, `is_staging()`: whether `WCS_Staging` is loaded, and the current mode.
- `switch_to_live()`: saves this site's URL as the live URL (`WCS_Staging::set_duplicate_site_url_lock()`).
- `switch_to_staging()`: deletes `wc_subscriptions_siteurl`, so the URLs no longer match.
- Both switches queue `Autorenew_Sync::enqueue_sweep()`, because `Autorenew::resolve_status()` reports auto-renew off in staging.
- `is_live_url_saved()`: whether the saved live URL is this site, before any `woocommerce_subscriptions_is_duplicate_site` filter. It picks which switch to offer, so the switch works both ways even when a filter forces the reported mode.
- `render()`: current mode, the switch button (with a confirm), a one-line impact description, and a collapsed Live vs. Staging panel (`Settings_Page::render_accordion()`, `render_mode_comparison()`) comparing the two modes, with the auto-renew testing steps.
