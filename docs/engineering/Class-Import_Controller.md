---
title: "Import_Controller Class Reference"
audience: [developer]
php_class: Import_Controller
source_files: ["includes/Import_Controller.php"]
---

# Import_Controller Class Index

**File:** includes/Import_Controller.php

CSV import engine for individual and organization memberships.

## Methods

- `__construct()`
- `create_individual_memberships( array $record ): \WP_REST_Response`
- `create_organization_memberships( array $record ): \WP_REST_Response`
- `local_membership_exists( string $wicket_uuid ): bool`

---

## Method Descriptions

**__construct()**
Resolves the membership/config/tier CPT slugs via `Helper`.

**create_individual_memberships( array $record )**
Per-row handler for the individual membership CSV. Skips duplicates by `Person_Membership_UUID` (via `local_membership_exists()`), resolves tier and user, derives dates/status, and creates the local membership record.

When the row carries a non-empty `Membership_Bundle_UUID` column, it branches early into `create_bundle_member()` instead: the row's own dates/status are ignored, and the member is created **through** its bundle via `Membership_Bundle::add_member( ..., skip_status_guard: true )` — inheriting the bundle's dates/status, its subscription line item, and correct MDP linkage. If the bundle can't be found (not yet imported), the row errors rather than silently falling back to a standalone create. See [Bundle Import](../public/membership-bundles/concepts/bundle-import.md) for the full bundle-then-members ordering requirement.

**create_organization_memberships( array $record )**
Per-row handler for the organization membership CSV. Same shape as the individual path; does not support bundle linkage.

**local_membership_exists( string $wicket_uuid )**
Queries `wicket_membership` posts by `membership_wicket_uuid` meta. Used as the duplicate-check/idempotency key for both import types.
