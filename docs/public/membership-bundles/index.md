# Membership Bundles

Membership Bundles let an organization purchase a block of membership seats that are managed as a single unit. The bundle itself belongs to an organization and has one designated owner (typically an association contact). Individual members are added to, removed from, or moved between bundles without requiring separate purchases.

This section covers everything you need to work with the Membership Bundles feature programmatically — from core concepts and class APIs to REST endpoints.

## When to use this feature

Membership Bundles are the right tool when:

- An organization buys memberships on behalf of its employees or members
- Seat counts, billing, and renewals should be managed at the organization level rather than per-person
- You need to transfer members between bundles without disrupting their membership records

## How bundles differ from individual and organization memberships

The plugin has three distinct membership models. Knowing which is which prevents a lot of confusion.

**Individual membership** (`wicket_membership`, `membership_type = individual`) — a single person purchases and owns their membership. Billing, renewals, and lifecycle are all tied to that person. Requires a `Membership_Tier` and a `Membership_Config`.

**Organization membership** (`wicket_membership`, `membership_type = organization`) — structurally identical to an individual membership at the data layer. It is assigned to an organization rather than a person, but it is still a single `wicket_membership` record with one tier, one config, and its own WooCommerce subscription.

**Membership Bundle** (`wicket_mship_bundle`) — a separate CPT entirely. A bundle is a container that an organization uses to purchase and manage *multiple* seats in one transaction. Key differences:

| | Individual / Org membership | Membership Bundle |
|---|---|---|
| CPT | `wicket_membership` | `wicket_mship_bundle` |
| Config class | `Membership_Config` + `Membership_Tier` | `Membership_Bundle_Config` (combined) |
| Seats | One | Many — each seat is a child `wicket_membership` post |
| Billing | One subscription per membership | One subscription shared across all seats |
| Owner | The member themselves | A designated contact person for the org |
| MDP sync | Per-person record | At the org level; no per-member MDP calls on renewal |

A bundle member seat *is* a `wicket_membership` post — it just has `membership_bundle_id` set to link it back to the bundle. The seat still requires its own `Membership_Tier`. What it does **not** have is its own WooCommerce subscription; billing runs through the bundle's subscription and each seat is a line item on it.

## Feature flag

The Membership Bundles admin UI is disabled by default. Enable it in the WordPress admin under **Settings → Wicket Memberships**, in the **Membership Bundles** section — check **Enable Membership Bundles** and save.

Toggling this setting only controls visibility of the admin menu pages (Membership Bundles list and Bundle Configs). It does not affect existing bundle data or any scheduled actions. The PHP classes and REST endpoints remain fully functional regardless of this setting.

For programmatic or environment-based control, the underlying option is `wicket_mship_enable_bundles` (stored in `wicket_membership_plugin_options`) and the corresponding env flag is `WICKET_MSHIP_ENABLE_BUNDLES`.

## Glossary

These terms appear throughout the documentation. If you're new to the Wicket stack, read this first.

| Term | What it is |
|---|---|
| **MDP** | Wicket Master Data Platform — the external data service that is the system of record for people, organizations, and memberships. WordPress is a client of MDP. Every bundle owner and org is identified by an MDP UUID. |
| **MDP UUID** | A string identifier (e.g. `"xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"`) that uniquely identifies a person or organization in MDP. Used wherever `person_uuid` or `org_uuid` is required. |
| **Action Scheduler** | A WordPress job queue library bundled with WooCommerce. The plugin uses it for all scheduled tasks — daily lifecycle transitions, date-trigger hooks, and renewal batch processing. It runs automatically as part of WooCommerce; no additional setup is required. |
| **AutomateWoo** | An optional WooCommerce marketing automation plugin. The bundle lifecycle fires `do_action` hooks at key dates (`early_renew_at`, `ends_at`, `expires_at`) that AutomateWoo can listen to for sending emails or triggering workflows. AutomateWoo is not required — the hooks fire regardless and can be used by any `add_action()` listener. |
| **WooCommerce Subscriptions** | A WooCommerce extension that manages recurring billing. Each bundle has exactly one WC subscription that covers billing for all its seats. Required — bundles cannot be created without it active. |
| **`BYPASS_WICKET`** | A setting that skips all MDP API calls. Enable it in the WordPress admin under **Settings → Wicket Memberships** — same page as the Membership Bundles toggle. Use during local development when MDP is unavailable. |
| **Bundle group UUID** | A UUID stored as `membership_bundle_group_uuid` on every bundle post. All renewal-term posts for the same bundle share this UUID, linking them into a series. Use it to query the full renewal history of a bundle. |
| **Seat / member seat** | A single `wicket_membership` post linked to a bundle via `membership_bundle_id`. Seats are the individual membership records within a bundle. |

## Documentation map

### Concepts

Understanding these mental models will make the class and endpoint docs much easier to follow.

- [Bundle Lifecycle](concepts/bundle-lifecycle.md) — status states, transition rules, cron triggers, and the renewal flow
- [Renewal Types](concepts/renewal-types.md) — subscription vs. form-page renewal, renewal windows, and the grace period
- [Member Handling](concepts/member-handling.md) — how seats relate to individual memberships; add, remove, and move semantics

### Getting started

- [Getting Started](getting-started.md) — create your first bundle and add a member in a few steps

### Classes

- [Membership_Bundle](classes/membership-bundle.md) — the core model; owns all bundle data, member management, and lifecycle transitions
- [Membership_Bundle_Config](classes/membership-bundle-config.md) — defines date calculation, renewal windows, grace periods, and renewal type
- [Membership_Bundle_Admin_Controller](classes/membership-bundle-admin-controller.md) — orchestration layer between the REST API and the model

### REST API endpoints

- [Overview](endpoints/overview.md) — base URL, authentication, and response conventions
- [Bundles](endpoints/bundles.md) — create, retrieve, update, filter, and inspect bundles
- [Bundle Members](endpoints/bundle-members.md) — add, remove, and move individual members
- [Bundle Status](endpoints/bundle-status.md) — status transitions, cancellation, and renewal orders
- [Bundle Config Dates](endpoints/bundle-config-dates.md) — calculate membership dates from a config record
