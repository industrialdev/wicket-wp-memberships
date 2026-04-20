---
title: "Membership_CPT_Hooks Class Reference"
audience: [developer]
php_class: Membership_CPT_Hooks
source_files: ["includes/Membership_CPT_Hooks.php"]
---

# Membership_CPT_Hooks Class Index

**File:** includes/Membership_CPT_Hooks.php

## Methods

- `__construct()`
- `edit_individual_member_page()`
- `edit_org_member_page()`
- `render_edit_individual_member_page()`
- `render_edit_org_member_page()`
- `add_individual_members_page()`
- `add_org_members_page()`
- `render_org_members_page()`
- `render_individual_members_page()` — Reads optional `$_GET['filter_group_id']` and `$_GET['filter_tier_name']` to emit `data-filter-group-id` and `data-filter-tier-name` on the `#member_list` div, pre-seeding the React component's initial filter state.
- `edit_group_member_page()`
- `render_group_members_page()`
- `render_edit_group_member_page()` — Emits `data-individual-members-url` on `#group_member_edit` so the Group Members section can construct filtered links to the individual members page.
- `enqueue_scripts()`
- `wicket_membership_table_head($columns)`
- `wicket_membership_table_content($column_name, $post_id)`
- `replace_title($title, $id)`
