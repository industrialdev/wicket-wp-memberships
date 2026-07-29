---
title: "Wicket Memberships Documentation Index"
audience: [implementer, support, developer, end-user]
---

# Wicket Memberships Documentation

## Product Docs (Operators & Support)
- [Overview](product/overview.md) — What the plugin does, CPTs, lifecycle, feature flags, requirements
- [Setting — WooCommerce API Keys with Membership API Access](product/settings-membership-api-access.md) — Choose which WooCommerce API keys may read/write membership records over the REST API

## Engineering Docs (Developers & Agents)

### Data Structures
- [Membership_Config Data Structure](engineering/membership_config_data_structure.md) — Renewal windows, late fees, calendar/anniversary cycles
- [Membership_Tier Data Structure](engineering/membership_tier_data_structure.md) — Tier-to-product linkage, renewal types, approval flows

### Features
- [Membership Transfer — Engineering Reference](engineering/membership_transfer.md) — Move an individual membership's term to a different owner; new record minted, order stays with the payer
- [Membership Switch — Engineering Reference](engineering/membership_switch.md) — Switch a membership to a different tier; preserves end date, re-points the expiry scheduler event
- [Membership CPT REST Access — Engineering Reference](engineering/membership-cpt-rest-access.md) — How the three CPTs are exposed over `wp/v2`, the `manage_options` gate, and admitting approved WooCommerce API keys

### Tools
- [Subscription Sync Tool — Engineering Reference](engineering/memberships_sync.md) — How `custom/memberships-sync.php` links subscriptions to memberships and syncs per-seat MDP seat counts

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
- [Transfer a Membership to a New Owner](guides/membership_transfer.md) — Move a membership to a different person; keeps the remaining term
- [Switch a Membership to a Different Tier](guides/membership_switch.md) — Move a member to a different tier; keeps the end date
- [Link a Membership Tier to a WooCommerce Product](guides/link-tier-to-product.md) — Connect tiers to subscription products so memberships are created on purchase
- [Subscription Sync Tool](guides/membership-sync.md) — Link existing subscriptions to imported membership records; per-seat org seat sync
- [Grant an Integration Access to Membership Data](guides/grant-api-access-to-memberships.md) — Let an external system reach membership records using its existing WooCommerce API key
