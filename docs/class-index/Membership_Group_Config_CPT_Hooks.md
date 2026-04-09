# Membership_Group_Config_CPT_Hooks

**File:** `includes/Membership_Group_Config_CPT_Hooks.php`  
**Namespace:** `Wicket_Memberships`

## Responsibility

Admin hooks for the `wicket_mship_grp_cfg` custom post type. Mirrors
`Membership_Config_CPT_Hooks` for the standard membership config CPT.

- Registers a hidden submenu page (`wicket_mship_grp_cfg_edit`) that hosts the
  React-based create/edit form.
- Intercepts native WordPress new-post and edit-post screens for the CPT and
  redirects them to the React page.
- Enqueues `membership_group_config_create.js` (built from
  `frontend/src/membership_group_configs/edit.js`) only on the edit page.
- Adds custom list-table columns: **Cycle** (calendar/anniversary) and
  **Renewal Type** (Subscription/Form Flow).
- Removes "Quick Edit" from row actions.
- Prevents trashing a group config that has linked membership groups.
- Force-deletes (bypasses trash) when a group config post is trashed.

## Constants

| Constant | Value | Purpose |
|---|---|---|
| `EDIT_PAGE_SLUG` | `wicket_mship_grp_cfg_edit` | Admin page slug for the React edit form |

## Hooks registered

| Hook | Method | Notes |
|---|---|---|
| `admin_menu` | `add_edit_page()` | Registers hidden submenu page |
| `admin_init` | `create_edit_page_redirects()` | Redirects native WP edit/new to React page |
| `admin_enqueue_scripts` | `enqueue_scripts()` | Loads React bundle on edit page only |
| `manage_wicket_mship_grp_cfg_posts_columns` | `table_head()` | Adds Cycle and Renewal Type columns |
| `manage_wicket_mship_grp_cfg_posts_custom_column` | `table_content()` | Populates custom columns |
| `post_row_actions` (filter) | `row_actions()` | Removes Quick Edit |
| `trashed_post` | `directory_skip_trash()` | Force-deletes on trash |
| `pre_trash_post` (filter) | `prevent_trash()` | Blocks trash when config has linked groups |

## React mount point

The `render_page()` method outputs a `<div id="create_membership_group_config">` with
the following `data-*` attributes passed to the React component:

| Attribute | Value |
|---|---|
| `data-group-config-cpt-slug` | `wicket_mship_grp_cfg` |
| `data-group-config-list-url` | URL of the CPT list page |
| `data-language-codes` | Comma-separated ISO language codes |
| `data-post-id` | Post ID when editing; empty string when creating |
