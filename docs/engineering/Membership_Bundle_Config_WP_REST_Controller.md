---
title: "Membership_Bundle_Config_WP_REST_Controller"
audience: [developer]
php_class: Membership_Bundle_Config_WP_REST_Controller
source_files: ["includes/Membership_Bundle_Config_WP_REST_Controller.php"]
---

# Membership_Bundle_Config_WP_REST_Controller

**File:** `includes/Membership_Bundle_Config_WP_REST_Controller.php`
**REST Namespace:** `wicket_member/v1`

**Architecture position:** Thin REST wrapper around `Membership_Bundle_Config::get_membership_dates()`. Adds only the custom `membership_dates` endpoint — standard CRUD for bundle config records goes through the WP REST API at `/wp/v2/wicket_mship_bcfg` via REST fields registered in `Membership_Post_Types`.

## Methods

- `__construct()`
- `register_routes()`
- `get_bundle_config_membership_dates($request)`
- `permissions_check_read($request)`
- `authorization_status_code()`

---

## Method Descriptions

**__construct()**
Initializes the REST controller, sets the namespace to `wicket_member/v1`, and registers the REST API routes.

**register_routes()**
Registers custom REST API routes for Membership Bundle Config operations that are not covered by the standard WP REST API.

Routes registered:

| Method | Route | Description |
|---|---|---|
| `GET` | `/bundle_config/{id}/membership_dates` | Calculate membership dates from a bundle config |

**get_bundle_config_membership_dates($request)**
Calculates and returns membership dates (`start_date`, `end_date`, `expires_at`, `early_renew_at`) by delegating to `Membership_Bundle_Config::get_membership_dates()`. Accepts an optional `membership` param (array) for renewal date calculation on existing membership bundles. Returns 404 if the post does not exist or is the wrong post type.

**permissions_check_read($request)**
Checks if the current user has permission to read bundle config data via the REST API. Bypassed when `ALLOW_LOCAL_IMPORTS` env flag is set.

**authorization_status_code()**
Returns the appropriate HTTP status code for authorization failures: 401 if the user is not logged in, 403 if logged in but unauthorized.

---

## Notes

- Standard CRUD (list, get, create, update) for group config records goes through the standard WP REST API at `/wp/v2/wicket_mship_bcfg`, using the REST fields registered in `Membership_Post_Types::register_membership_group_config_cpt_fields()`. This matches the pattern used by normal membership configs at `/wp/v2/wicket_mship_config`.
- This controller only adds the `membership_dates` custom endpoint, which has no equivalent in the standard WP REST API — mirroring how `Membership_WP_REST_Controller` handles `/config/{id}/membership_dates` for normal configs.
