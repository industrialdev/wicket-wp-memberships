# Membership_Config_CPT_Hooks Class Index

**File:** includes/Membership_Config_CPT_Hooks.php

## Methods

- `__construct()`
- `register_hooks()`
- `register_post_type()`
- `register_meta_boxes()`
- `save_post($post_id)`
- `enqueue_admin_scripts($hook)`
- `add_columns($columns)`
- `render_column($column, $post_id)`
- `make_columns_sortable($columns)`
- `filter_by_tier($query)`
- `filter_by_tier_dropdown()`

---

## Method Descriptions

**__construct()**
Initializes the hooks for the Membership Config custom post type.

**register_hooks()**
Registers all WordPress hooks for the custom post type.

**register_post_type()**
Registers the Membership Config custom post type with WordPress.

**register_meta_boxes()**
Registers meta boxes for the Membership Config post type in the admin UI.

**save_post($post_id)**
Handles saving of meta box data when a Membership Config post is saved.

**enqueue_admin_scripts($hook)**
Enqueues admin scripts and styles for the Membership Config post type screens.

**add_columns($columns)**
Adds custom columns to the admin list table for Membership Config posts.

**render_column($column, $post_id)**
Renders the content for custom columns in the admin list table.

**make_columns_sortable($columns)**
Makes custom columns sortable in the admin list table.

**filter_by_tier($query)**
Filters the admin list table by membership tier if a filter is selected.

**filter_by_tier_dropdown()**
Outputs a dropdown filter for membership tier in the admin list table.
