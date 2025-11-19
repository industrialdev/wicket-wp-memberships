
# Membership_Tier_CPT_Hooks Class Index

**File:** includes/Membership_Tier_CPT_Hooks.php

## Methods

- `__construct()`
- `prevent_trash($trash, $post)`
- `row_actions($actions, $post)`
- `directory_skip_trash($post_id)`
- `rest_save_post_page($post)`
- `add_edit_page()`
- `render_page()`
- `create_edit_page_redirects()`
- `enqueue_list_page_scripts()`
- `enqueue_scripts()`
- `table_head($columns)`
- `table_content($column_name, $post_id)`
- `tier_id_post_link($actions, $post)`

---

## Method Descriptions

**__construct()**
Initializes the class, sets up CPT slugs, and registers all WordPress hooks, actions, and filters for the membership tier admin UI.

**prevent_trash($trash, $post)**
Prevents a membership tier from being moved to trash if it has associated memberships; otherwise, allows trashing.

**row_actions($actions, $post)**
Removes the "Quick Edit" action from the row actions for membership tier posts.

**directory_skip_trash($post_id)**
Forcibly deletes a membership tier post (bypassing trash) when triggered.

**rest_save_post_page($post)**
After saving a membership tier via REST, ensures next tier IDs are set if missing.

**add_edit_page()**
Adds a hidden submenu page for editing or creating membership tiers in the admin menu.

**render_page()**
Renders the React-based admin page for creating or editing a membership tier, passing relevant data as HTML data attributes.

**create_edit_page_redirects()**
Redirects the default post-new and post edit screens to the custom React-based edit page for membership tiers.

**enqueue_list_page_scripts()**
Enqueues JavaScript assets for the membership tier list page in the admin, including member count and cell info scripts.

**enqueue_scripts()**
Enqueues JavaScript assets for the React-based membership tier create/edit page in the admin.

**table_head($columns)**
Customizes the columns displayed in the membership tier admin list table, adding status, category, member count, config, and slug columns.

**table_content($column_name, $post_id)**
Renders the content for each custom column in the membership tier admin list table, including React components and debug info.

**tier_id_post_link($actions, $post)**
Adds a custom row action for updating the tier UUID if local imports are allowed, including a dropdown and button for changing the UUID.
