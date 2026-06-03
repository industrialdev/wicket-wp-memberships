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

### Feature Plans
- [Create Membership Bundle Page](engineering/create-membership-bundle-page.md) — Implementation plan and progress tracker for the new membership bundle creation flow

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
- [Membership_Bundle](engineering/Membership_Bundle.md) — Core model for bundle posts: member management, status transitions, date cascade, WC subscription
- [Membership_Bundle_Admin_Controller](engineering/Membership_Bundle_Admin_Controller.md) — Orchestration layer for bundle admin operations: status, dates, ownership, member CRUD, renewal orders
- [Membership_Bundle_Config](engineering/Membership_Bundle_Config.md) — Model for bundle config posts: date/cycle calculations, renewal windows, approval settings
- [Membership_Bundle_Config_CPT_Hooks](engineering/Membership_Bundle_Config_CPT_Hooks.md) — Admin UI hooks for bundle configs: React edit page, list columns, trash protection
- [Membership_Bundle_Config_WP_REST_Controller](engineering/Membership_Bundle_Config_WP_REST_Controller.md) — REST endpoint for bundle config date calculation
- [Membership_Bundle_Cron_Controller](engineering/Membership_Bundle_Cron_Controller.md) — Daily Action Scheduler handlers for bundle grace-period, expiry, activation, and renewal batch processing
- [Membership_Bundle_WP_REST_Controller](engineering/Membership_Bundle_WP_REST_Controller.md) — REST endpoints for all membership bundle operations
- [Settings](engineering/Class-Settings.md) — Plugin options page: feature flags, debug toggles, scheduled action status
- [Utilities](engineering/Class-Utilities.md) — WooCommerce integration hooks: cart/checkout modifications, product protection, timezone date helpers

## Public Docs (External Developers & New Team Members)
- [Overview](public/index.md) — Entry point for all public developer documentation
- [Membership Bundles](public/membership-bundles/index.md) — Class APIs, REST endpoints, and conceptual guides for the Membership Bundles feature

## Guides (End Users)
- [Link a Membership Tier to a WooCommerce Product](guides/link-tier-to-product.md) — Connect tiers to subscription products so memberships are created on purchase
