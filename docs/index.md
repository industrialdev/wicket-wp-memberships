---
title: "Wicket Memberships Documentation Index"
audience: [implementer, support, developer, end-user]
---

# Wicket Memberships Documentation

## Product Docs (Operators & Support)
- [Overview](product/overview.md) — What the plugin does, CPTs, lifecycle, feature flags, requirements

## Engineering Docs (Developers & Agents)

### Data Structures
- [Membership_Config Data Structure](engineering/membership_config_data_structure.md) — Renewal windows, late fees, calendar/anniversary cycles
- [Membership_Tier Data Structure](engineering/membership_tier_data_structure.md) — Tier-to-product linkage, renewal types, approval flows

### Class Reference
- [Admin_Controller](engineering/Class-Admin_Controller.md) — Admin menu pages, status transition validation, React app mounting
- [Helper](engineering/Class-Helper.md) — Static utilities: CPT slugs, status names, allowed transitions, logging
- [Import_Controller](engineering/Class-Import_Controller.md) — CSV import for individual and organization memberships
- [Membership_Config](engineering/Class-Membership_Config.md) — Model for config posts: renewal windows, grace periods, cycle calculations
- [Membership_Config_CPT_Hooks](engineering/Class-Membership_Config_CPT_Hooks.md) — Admin UI for configs: React edit page, trash protection
- [Membership_Controller](engineering/Class-Membership_Controller.md) — Core business logic: creates memberships, manages lifecycle, syncs to MDP
- [Membership_CPT_Hooks](engineering/Class-Membership_CPT_Hooks.md) — Admin list table columns and React edit page rendering for memberships
- [Membership_Post_Types](engineering/Class-Membership_Post_Types.md) — Registers three CPTs and their REST fields
- [Membership_Subscription_Controller](engineering/Class-Membership_Subscription_Controller.md) — Creates WCS subscriptions from membership orders
- [Membership_Tier](engineering/Class-Membership_Tier.md) — Model for tier posts: product linkage, renewal type, MDP UUID lookup
- [Membership_Tier_CPT_Hooks](engineering/Class-Membership_Tier_CPT_Hooks.md) — Admin UI for tiers: list columns, trash protection, React edit page
- [Membership_WP_REST_Controller](engineering/Class-Membership_WP_REST_Controller.md) — REST API (wicket_member/v1): search, CRUD, status management, merge webhook
- [Settings](engineering/Class-Settings.md) — Plugin options page: feature flags, debug toggles, scheduled action status
- [Utilities](engineering/Class-Utilities.md) — WooCommerce integration hooks: cart/checkout modifications, product protection, timezone date helpers

## Guides (End Users)
- [Link a Membership Tier to a WooCommerce Product](guides/link-tier-to-product.md) — Connect tiers to subscription products so memberships are created on purchase
