---
title: "Wicket Memberships Overview"
audience: [implementer, support]
wp_admin_path: "Settings → Wicket Memberships"
php_class: Membership_Controller
---

# Overview

Wicket Memberships manages membership records on top of WooCommerce and WooCommerce Subscriptions, synchronizing with the Wicket MDP (Master Data Platform).

## What It Does

- Creates membership records when WooCommerce orders or subscriptions are completed
- Syncs membership data to the Wicket MDP in real time
- Schedules and handles lifecycle events: early renewal windows, expiry, grace periods
- Provides a REST API (`wicket_member/v1`) for managing memberships
- Supports AutomateWoo triggers for membership lifecycle events

## Requirements

- WordPress 6.5+
- PHP 8.1+
- `wicket-wp-base-plugin`
- WooCommerce
- WooCommerce Subscriptions

## Custom Post Types

| CPT | Slug | Purpose |
|---|---|---|
| Membership | `wicket_membership` | Individual membership records linked to users, orders, and subscriptions |
| Membership Tier | `wicket_mship_tier` | Tier definitions linking MDP tiers to WooCommerce products with renewal configuration |
| Membership Config | `wicket_mship_config` | Renewal windows, grace periods, and billing cycles (calendar or anniversary) |

## Membership Lifecycle

1. **Order completed** → `Membership_Controller::catch_order_completed()` creates membership record + MDP sync
2. **Action Scheduler** runs daily hooks at 3:00/3:30/4:00 AM for activation, grace period, and expiry
3. **Early renewal window** opens at `membership_early_renew_at` → AutomateWoo trigger fires
4. **End date reached** at `membership_ends_at` → enters grace period if configured
5. **Grace period expires** at `membership_expires_at` → membership expires
6. **Status transitions** are validated — see `Helper::get_allowed_transition_status()` for the state machine

Valid statuses: `pending`, `active`, `grace_period`, `delayed`, `expired`, `cancelled`

## Feature Flags (Settings)

| Flag | What it controls |
|---|---|
| `WICKET_MSHIP_MULTI_TIER_RENEWALS` | Multi-tier renewal workflows and Gravity Forms integration |
| `WICKET_MSHIP_SUBSCRIPTION_RENEW` | Subscription-based renewal |
| `WICKET_MSHIP_ASSIGN_SUBSCRIPTION` | Linking subscriptions to memberships |
| `WICKET_MSHIP_AUTORENEW_TOGGLE` | Shows autorenew checkbox on subscriptions |
| `BYPASS_WICKET` | Skips MDP sync (local dev) |
| `ALLOW_LOCAL_IMPORTS` | Enables CSV import REST endpoints |

## Documentation Links

- [Membership_Config Data Structure](engineering/membership_config_data_structure.md) — renewal windows, late fees, calendar/anniversary cycles
- [Membership_Tier Data Structure](engineering/membership_tier_data_structure.md) — tier-to-product linkage, renewal types, approval flows
