# Wicket Memberships

WordPress plugin for managing memberships on top of WooCommerce and WooCommerce Subscriptions, synchronized with the Wicket MDP (Master Data Platform).

## Requirements

- WordPress 6.5+
- PHP 8.1+
- [wicket-wp-base-plugin](../wicket-wp-base-plugin)
- WooCommerce
- WooCommerce Subscriptions

## Features

- Creates membership records when WooCommerce orders or subscriptions complete
- Syncs membership data to the Wicket MDP in real time
- Manages membership lifecycle via Action Scheduler (activation, grace periods, expiry)
- AutomateWoo triggers for early renewal, end-date, and grace-period events
- React-based admin UI for memberships, tiers, and configs
- REST API (`wicket_member/v1`) for search, CRUD, status management, and merge webhooks
- CSV import tooling for bulk individual and organization memberships
- Multi-tier renewal workflows (optional, via feature flag)
- Subscription-based renewal (optional, via feature flag)

## Architecture

Three custom post types drive the data model:

| CPT | Slug | Purpose |
|---|---|---|
| Membership | `wicket_membership` | Individual membership records |
| Tier | `wicket_mship_tier` | Tier definitions linked to WooCommerce products |
| Config | `wicket_mship_config` | Renewal windows, grace periods, billing cycles |

Key classes:

| Class | Role |
|---|---|
| `Membership_Controller` | Core business logic — order processing, MDP sync, lifecycle scheduling |
| `Membership_WP_REST_Controller` | REST API endpoints (`wicket_member/v1`) |
| `Admin_Controller` | Admin menus, status transitions, React app mounting |
| `Settings` | Plugin options page — feature flags and debug toggles |
| `Utilities` | WooCommerce hooks, cart/checkout modifications, date helpers |
| `Import_Controller` | CSV import for bulk membership creation |

## Development

Install PHP dependencies:

```bash
composer install
```

Build the React admin UI:

```bash
cd frontend && npm install && npm run build
```

Watch mode for frontend development:

```bash
cd frontend && npm run start
```

## Testing

Tests live in the centralized QA suite at `./qa` (from the stack root). Do not run PHPUnit directly from this plugin directory.

```bash
# From the stack root:
cd qa && composer test:wordpress:memberships
```

See `./qa/README.md` for full setup and usage instructions.

## Documentation

For complete documentation, see **[docs/index.md](docs/index.md)**.

| Audience | Directory |
|---|---|
| Operators & Support | [docs/product/](docs/product/) |
| Developers & Agents | [docs/engineering/](docs/engineering/) |
| End Users | [docs/guides/](docs/guides/) |
