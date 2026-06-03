# Wicket Memberships — Developer Documentation

This is the public API reference for the Wicket Memberships WordPress plugin. It covers the classes, REST endpoints, and conceptual models that external developers and new team members need to build on top of the plugin.

The plugin manages membership lifecycle on top of WooCommerce and WooCommerce Subscriptions, synchronized with the Wicket MDP (Master Data Platform). It provides three distinct membership models — individual memberships, organization memberships, and membership bundles — each with their own CPTs, config classes, REST APIs, and lifecycle rules.

## Sections

### [Membership Bundles](membership-bundles/index.md)

An organization purchases a block of seats managed as a single unit. One WooCommerce subscription covers all seats. Members can be added, removed, and moved between bundles. Includes full class API, REST endpoint, and concept documentation.

---

### Individual Memberships

_Documentation coming soon._

A single person purchases and owns their membership. Billing, renewals, and lifecycle are tied to that person. Uses the `wicket_membership` CPT with `membership_type = individual`. Backed by `Membership_Controller`, `Membership_WP_REST_Controller`, and `Admin_Controller`.

---

### Organization Memberships

_Documentation coming soon._

Structurally identical to an individual membership at the data layer, but assigned to an organization rather than a person. Uses the `wicket_membership` CPT with `membership_type = organization`.

---

### Membership Configs & Tiers

_Documentation coming soon._

`Membership_Config` defines date calculation, renewal windows, and grace periods. `Membership_Tier` links a config to a WooCommerce product and defines the membership type. Both are required for individual and organization memberships.

---

### REST API

_Documentation coming soon._

The plugin registers all endpoints under the `wicket_member/v1` namespace. The full endpoint surface covers membership CRUD, status management, tier and config lookups, org browsing, merge webhooks, and CSV import. Membership Bundles endpoints are documented now — see [Membership Bundles → REST API](membership-bundles/endpoints/overview.md).

---

### Lifecycle & Scheduling

_Documentation coming soon._

Action Scheduler drives all time-based transitions — activation, grace periods, expiry, and renewal batch processing — for both individual memberships and bundles. AutomateWoo hooks fire at key lifecycle dates for notification and automation workflows.
