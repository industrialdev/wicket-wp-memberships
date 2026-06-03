---
title: "Membership_Bundle_Config_CPT_Hooks"
audience: [developer]
php_class: Membership_Bundle_Config_CPT_Hooks
source_files: ["includes/Membership_Bundle_Config_CPT_Hooks.php"]
---

# Membership_Bundle_Config_CPT_Hooks

**File:** `includes/Membership_Bundle_Config_CPT_Hooks.php`
**Namespace:** `Wicket_Memberships`

**CPT slug:** `wicket_mship_bcfg` — via `Helper::get_membership_bundle_config_cpt_slug()`

**Architecture position:** Admin UI hooks only. No business logic, no REST concerns. Mirrors `Membership_Config_CPT_Hooks` for the standard membership config CPT. Gated by the `WICKET_MSHIP_ENABLE_BUNDLES` env flag — most hooks only register when the flag is set.

## Responsibility

Admin hooks for the `wicket_mship_bcfg` custom post type.

- Registers a hidden submenu page (`wicket_mship_bcfg_edit`) that hosts the
  React-based create/edit form.
- Intercepts native WordPress new-post and edit-post screens for the CPT and
  redirects them to the React page.
- Enqueues `membership_bundle_config_create.js` (built from
  `frontend/src/membership_bundle_configs/`) only on the edit page.
- Adds custom list-table columns: **Cycle** (calendar/anniversary) and
  **Renewal Type** (Subscription/Form Flow).
- Removes "Quick Edit" from row actions.
- Prevents trashing a bundle config that has linked membership bundles.
- Force-deletes (bypasses trash) when a bundle config post is trashed.

## Constants

| Constant | Value | Purpose |
|---|---|---|
| `EDIT_PAGE_SLUG` | `wicket_mship_bcfg_edit` | Admin page slug for the React edit form |

## Hooks registered

| Hook | Method | Notes |
|---|---|---|
| `admin_menu` | `add_bundle_configs_submenu()` (priority 28) | Registers "Bundle Configs" submenu entry on the CPT list screen, guarded by `WICKET_MSHIP_ENABLE_BUNDLES` |
| `admin_menu` | `add_edit_page()` | Registers hidden submenu page |
| `admin_init` | `create_edit_page_redirects()` | Redirects native WP edit/new to React page |
| `admin_enqueue_scripts` | `enqueue_scripts()` | Loads React bundle on edit page only |
| `manage_wicket_mship_bcfg_posts_columns` | `table_head()` | Adds Cycle and Renewal Type columns |
| `manage_wicket_mship_bcfg_posts_custom_column` | `table_content()` | Populates custom columns |
| `post_row_actions` (filter) | `row_actions()` | Removes Quick Edit |
| `trashed_post` | `directory_skip_trash()` | Force-deletes on trash |
| `pre_trash_post` (filter) | `prevent_trash()` | Blocks trash when config has linked groups |

## React mount point

The `render_page()` method outputs a `<div id="create_membership_bundle_config">` with
the following `data-*` attributes passed to the React component:

| Attribute | Value |
|---|---|
| `data-bundle-config-cpt-slug` | `wicket_mship_bcfg` |
| `data-bundle-config-list-url` | URL of the CPT list page |
| `data-language-codes` | Comma-separated ISO language codes |
| `data-post-id` | Post ID when editing; empty string when creating |

## Method Notes

**`add_bundle_configs_submenu()`**
Registers the "Bundle Configs" submenu entry in the WP admin menu, pointing to the CPT list screen (`edit.php?post_type=wicket_mship_bcfg`). Only called when the `WICKET_MSHIP_ENABLE_BUNDLES` env flag is set (priority 28 on `admin_menu`).

**`register_rest_fields()`**
Registers `cycle_type` and `renewal_type` as REST fields on the WP REST API response for the `wicket_mship_bcfg` CPT. Each field's `get_callback` instantiates a `Membership_Bundle_Config` and returns the corresponding value, making these fields available at `/wp/v2/wicket_mship_bcfg` without a custom endpoint.
