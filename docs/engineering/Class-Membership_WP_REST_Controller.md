---
title: "Membership_WP_REST_Controller Class Reference"
audience: [developer]
php_class: Membership_WP_REST_Controller
source_files: ["includes/Membership_WP_REST_Controller.php"]
---

# Membership_WP_REST_Controller Class Index

**File:** includes/Membership_WP_REST_Controller.php

## Methods

- `__construct()`
- `register_routes()`
- `delete_all_person_memberships($request)`
- `mdp_person_lookup($request)`
- `update_membership_change_ownership($request)`
- `import_membership_organizations($request)`
- `import_person_memberships($request)`
- `get_membership_entity($request)`
- `get_edit_page_info($request)`
- `update_membership_entity($request)`
- `admin_manage_status($request)`
- `get_admin_status_options($request)`
- `get_membership_callouts($request)`
- `get_membership_filters($request)`
- `get_membership_lists($request)`
- `modify_subscription($request)`
- `get_membership_dates($request)`
- `get_product_tiers($request)`
- `get_orgs_mdp()`
- `get_org_info($request)`
- `get_org_data($request)`
- `get_tiers_mdp($request)`
- `get_tier_info($request)`
- `create_renewal_order($request)`
- `get_all_wp_pages($request)`
- `get_all_wc_products($request)`
- `get_wc_product_variations($request)`
- `parse_exclude_ids($raw)` (private)
- `get_memberships_table_data($categories = null, $filters = [])`
- `permissions_check_read($request)`
- `permissions_check_write($request)`
- `authorization_status_code()`

---

## Method Descriptions

**__construct()**
Initializes the REST controller, sets up the namespace, and registers the REST API routes.

**register_routes()**
Registers all REST API routes for membership, organization, tier, and related operations, mapping endpoints to controller methods.

**delete_all_person_memberships($request)**
Deletes all memberships for a given person UUID from the MDP and returns the result.

**mdp_person_lookup($request)**
Performs a person search in the MDP using the provided search term and returns the results.

**update_membership_change_ownership($request)**
Updates the owner of a membership by delegating to the Admin_Controller.

**import_membership_organizations($request)**
Creates organization memberships from the provided data by delegating to the Import_Controller.

**import_person_memberships($request)**
Creates individual memberships from the provided data by delegating to the Import_Controller.

**get_membership_entity($request)**
Retrieves membership entity records for a given entity ID by delegating to the Admin_Controller.

**get_edit_page_info($request)**
Retrieves edit page information for a given entity ID by delegating to the Admin_Controller.

**update_membership_entity($request)**
Updates a membership entity record with the provided data by delegating to the Admin_Controller.

**admin_manage_status($request)**
Changes the status of a membership post by delegating to the Admin_Controller.

**get_admin_status_options($request)**
Retrieves available status options for a membership post by delegating to the Admin_Controller.

**get_membership_callouts($request)**
Retrieves membership callouts (e.g., early renewal, grace periods) for a user by delegating to the Membership_Controller.

**get_membership_filters($request)**
Retrieves membership filters for a given type (individual or organization) by delegating to the Membership_Controller.

**get_membership_lists($request)**
Retrieves a list of memberships based on type, pagination, status, filters, and ordering by delegating to the Membership_Controller.

**modify_subscription($request)**
Modifies a membership subscription by delegating to the Membership_Subscription_Controller.

**get_membership_dates($request)**
Retrieves membership dates for a given config ID by delegating to the Membership_Controller.

**get_product_tiers($request)**
Retrieves the membership tier associated with a given product ID.

**get_orgs_mdp()**
Retrieves all organizations from the MDP.

**get_org_info($request)**
Retrieves organization info for a given org UUID and properties by delegating to the Membership_Controller.

**get_org_data($request)**
Retrieves organization data for a given org UUID by delegating to the Helper class.

**get_tiers_mdp($request)**
Retrieves all membership tiers from the MDP, filtered by categories and filters.

**get_tier_info($request)**
Retrieves tier info for a given tier UUID and properties by delegating to the Membership_Controller.

**create_renewal_order($request)**
Creates a renewal order for a membership by delegating to the Admin_Controller.

**get_all_wp_pages($request)**
Returns every published WP page (`id`, `title.rendered`) for admin page-picker UIs — the tier and membership renewal-form page selectors (`GET /wicket_member/v1/wp_pages_all`).

This exists instead of using core `GET /wp/v2/pages` because the WP Private Content Plus plugin hooks `rest_prepare_page` and `pre_get_posts` and strips out any page with a restricted `_wppcp_post_page_visibility` value from REST responses — including from plain admin list-building queries that aren't actual searches. Its `pre_get_posts` handler (`exclude_restricted_posts_from_search()` in `wp-private-content-plus/functions.php`) checks `isset( $query->query_vars['s'] )`, which WordPress sets (to `''`) on every `WP_Query`, so the check is always true under `REST_REQUEST` — the exclusion fires on every REST-context query, not just real searches. Renewal-form page pickers need to show *all* pages regardless of member-only visibility, since the tier/membership admin screens are staff-only already.

The fix wraps the `get_posts()` call with `add_filter( 'disable_restriction_checks', '__return_true' )` / `remove_filter(...)` — the escape hatch WP Private Content Plus's own code already uses internally to avoid infinite recursion — rather than patching the third-party plugin. Do not swap this endpoint back to core `/wp/v2/pages` for either of the two consumers (`membership_tiers/edit.js`, `members/edit.js`) without re-checking that filter behavior first.

**get_all_wc_products($request)**
Returns published WooCommerce products (`id`, `name`, `type`) for the plugin's admin product pickers (`GET /wicket_member/v1/wc_products_all`). Accepts `type` (single product type slug; omit for all types) and `exclude` (comma-separated or array of product IDs).

Same root cause as `get_all_wp_pages()` — WP Private Content Plus's `pre_get_posts` handler filters every REST-context query — but it breaks the product pickers in two distinct ways:

1. Products with a restricted `_wppcp_post_page_visibility` vanish from the list.
2. WPCP calls `$query->set( 'post__not_in', ... )`, which **overwrites rather than merges**. WooCommerce maps its REST `exclude` param onto that same key, so a single restricted post of *any* type anywhere on the site silently discards the picker's "already in use" exclusion list, and products assigned to other tiers reappear as selectable. This fires even when zero products are restricted.

Deliberately implemented as a plugin-namespaced endpoint rather than as a dispatch-level filter on `/wc/v3/products`. A dispatch filter cannot distinguish this plugin's requests from any other admin consumer of that route, so it would relax WPCP restrictions for WooCommerce itself and every other caller. Keeping the bypass on our own route confines it to requests this plugin's admin screens make. Verified: with a restricted product present, `/wc/v3/products`, `/wp/v2/pages` and direct `WP_Query` product queries all still filter it out.

**get_wc_product_variations($request)**
Returns published variations of one product as `[{ id }]` (`GET /wicket_member/v1/wc_product_variations/{id}`). Accepts `exclude` (comma-separated or array of variation IDs). Companion to `get_all_wc_products()` — WooCommerce's `/wc/v3/products/{id}/variations` route is filtered by the same handler, which also clobbers the exclusion list of variations already assigned to other tiers.

Both endpoints are consumed only through `fetchWcProducts()` / `fetchProductVariations()` in `frontend/src/services/api.js`, which serve the tier edit, config edit, member edit renewal-order, and switch-membership pickers. Do not point those helpers back at `/wc/v3/...` without re-checking the filter behavior above.

**parse_exclude_ids($raw)** (private)
Normalises a REST `exclude` parameter into a list of positive integer IDs. Accepts either a comma-separated string (how the product pickers pass `productsInUse`) or an array (how they pass `productVariationsInUse`), and drops zeros so a stray empty segment cannot become ID `0`.

**get_memberships_table_data($categories = null, $filters = [])**
Builds and returns an array of membership data for the memberships table, filtered by categories and filters.

**permissions_check_read($request)**
Checks if the current user has permission to read membership data via the REST API.

**permissions_check_write($request)**
Checks if the current user has permission to write membership data via the REST API.

**authorization_status_code()**
Returns the appropriate HTTP status code for authorization failures (401 if not logged in, 403 if logged in but unauthorized).
