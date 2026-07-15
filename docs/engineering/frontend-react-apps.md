---
title: "Frontend React Apps"
audience: [developer]
source_files: ["frontend/webpack.config.js", "frontend/src"]
---

# Frontend React Apps

React admin UI, built with `@wordpress/scripts` webpack config. Source in `frontend/src/`, built output in `frontend/build/`. Uses WordPress components (`@wordpress/components`, `@wordpress/element`) and styled-components.

## Build Entry Points (frontend/webpack.config.js)

Each row is a separate webpack entry, enqueued independently on its admin page.

| Entry (webpack name) | Source | Purpose |
|---|---|---|
| `membership_config_create` | `membership_configs/edit.js` | Create/edit membership configuration (renewal windows, late fees, cycle data) |
| `membership_tier_create` | `membership_tiers/edit.js` | Create/edit membership tier (product linkage, renewal type, grace periods) |
| `member_list` | `members/index.js` | Paginated membership list with status tabs, filters, and sorting |
| `member_edit` | `members/edit.js` | Edit individual/org membership — dates, status, owner, renewal orders |
| `tier_member_count` | `membership_tiers/member_count.js` | Member count widget for tier list |
| `wicket_memberships_tier_cell_info` | `membership_tiers/tier_cell_info.js` | Tier info cell display |

## Supporting Modules (not separate entries)

Imported by the entry points above, not built standalone.

| File | Purpose |
|---|---|
| `membership_tiers/manage_products.js` | Product/variation linkage UI, used by `membership_tiers/edit.js` |
| `membership_configs/tiers.js` | Lists MDP tiers linked to a config, used by `membership_configs/edit.js` |
| `members/create_renewal_order.js` | Renewal order creation modal, used by `members/edit.js` |
| `services/` | API fetch helpers (`fetchWcProducts`, `fetchMembershipTiers`, `createRenewalOrder`, etc.) |
| `constants.js` | Shared constants (`PLUGIN_API_URL`, `WC_PRODUCT_TYPES`) |
| `styled_elements.js` | Shared styled-components primitives (form rows, labels, modals) |

## Build Commands

- `cd frontend && npm install && npm run build`: production build.
- `cd frontend && npm run start`: webpack watch mode for development.
