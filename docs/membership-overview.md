# Wicket Memberships Overview

Wicket Memberships manages your organization's memberships inside WordPress. Each membership connects to a WooCommerce order or subscription and stays in step with the Wicket member database.

## What it does

- Creates a membership when a related order or subscription completes.
- Keeps membership data synchronized with the Wicket member database in real time.
- Handles the full life of a membership automatically: early renewal windows, expiry dates, and grace periods.
- Offers self-service actions for members: switch to a different tier, or transfer a membership to a new owner.

## The life of a membership

1. **Pending** — the membership exists but is not active yet.
2. **Active** — the member can use their benefits.
3. **Grace period** — the end date passed, but the member keeps access for a limited time.
4. **Expired** — the grace period is over. A renewal reactivates the membership.

Memberships can also be delayed (scheduled to start later) or cancelled.

## Common tasks

- [Link a Membership Tier to a Product](link-tier-to-product.md)
- [Switch a Membership to a Different Tier](membership_switch.md)
- [Transfer a Membership to a New Owner](membership_transfer.md)
