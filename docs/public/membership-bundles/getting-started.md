---
title: Getting Started
---

# Getting Started

This guide walks you through the minimum steps to create a membership bundle, add a member, and verify the result — using either the PHP API or the REST endpoints.

## Prerequisites

- WordPress with the Wicket Memberships plugin active
- WooCommerce and WooCommerce Subscriptions installed and active
- At least one `wicket_mship_bcfg` (Bundle Config) post exists
- At least one `Membership_Tier` CPT post exists (for the individual seat)
- The acting user has the `wicket_memberships_admin` capability

If you are working locally without the Wicket MDP API, enable **Bypass Wicket** in the WordPress admin under **Settings → Wicket Memberships** to skip all MDP calls.

## Step 1 — Create a bundle

Use `Membership_Bundle::create()` to create a new bundle. All five parameters are required.

```php
use Wicket_Memberships\Membership_Bundle;

$bundle = Membership_Bundle::create(
    name:                         'Acme Corp 2025 Membership',
    membership_bundle_config_id:  42,   // post ID of your wicket_mship_bcfg record
    org_uuid:                     'org-uuid-here',
    owner_uuid:                   'person-uuid-here',
    start_date:                   '2025-01-01',
);

$bundle_post_id = $bundle->get_post_id();
// Initial status: 'pending' (or 'delayed' if start_date is in the future)
```

::: details What happens under the hood
`create()` derives `ends_at`, `expires_at`, and `early_renew_at` from the linked config, creates a `pending` WooCommerce subscription, and schedules Action Scheduler date-trigger jobs.
:::

## Step 2 — Activate the bundle

A newly created bundle starts in `pending` status. An admin must explicitly activate it. Use `transition_to()` on the bundle object, or call the REST endpoint (see [Bundle Status](endpoints/bundle-status.md)).

```php
$result = $bundle->transition_to( 'active' );

// $result['success_message'] — human-readable confirmation
// $result['bypassed']        — true only when BYPASS_STATUS_CHANGE_LOCKOUT is set
```

Activating a `pending` bundle recalculates dates anchored to today and activates the linked WooCommerce subscription.

## Step 3 — Add a member

With the bundle active, add an individual member by providing their MDP person UUID and the post ID of the `Membership_Tier` they should be enrolled under.

```php
use Wicket_Memberships\Membership_Bundle_Admin_Controller;

$result = Membership_Bundle_Admin_Controller::add_member([
    'bundle_post_id' => $bundle_post_id,
    'mode'           => 'new',
    'tier_post_id'   => 88,   // post ID of the Membership_Tier CPT
    'person_uuid'    => 'member-person-uuid',
]);

$membership_post_id = $result['membership_post_id'];
```

::: tip
The controller resolves (or creates) the WP user from the MDP person UUID, creates an individual `wicket_membership` post linked to the bundle, and adds a line item to the bundle's WooCommerce subscription.
:::

## Step 4 — Verify the bundle

Retrieve the bundle's member breakdown to confirm the seat was added:

```php
$breakdown = Membership_Bundle_Admin_Controller::get_bundle_members_by_tier( $bundle_post_id );

// $breakdown['total_members'] => 1
// $breakdown['tiers']         => [ [ 'tier_uuid' => '...', 'tier_name' => '...', 'member_count' => 1 ] ]
```

## Using the REST API

If you prefer HTTP, the same flow maps directly to REST endpoints:

:::details REST API equivalent
```bash
# Create a bundle
POST /wp-json/wicket_member/v1/bundle
{
  "name": "Acme Corp 2025 Membership",
  "membership_bundle_config_id": 42,
  "org_uuid": "org-uuid-here",
  "owner_uuid": "person-uuid-here",
  "start_date": "2025-01-01"
}

# Activate it
POST /wp-json/wicket_member/v1/bundle/admin/manage_status
{
  "bundle_post_id": 123,
  "status": "active"
}

# Add a member
POST /wp-json/wicket_member/v1/bundle/123/add_member
{
  "mode": "new",
  "tier_post_id": 88,
  "person_uuid": "member-person-uuid"
}
```
:::

All endpoints require authentication. See [Endpoints Overview](endpoints/overview.md) for details.

## What's next

- Read [Bundle Lifecycle](concepts/bundle-lifecycle.md) to understand how status transitions work and when they happen automatically
- See [Member Handling](concepts/member-handling.md) for the remove and move flows
- Review [Membership_Bundle](classes/membership-bundle.md) for the complete class API reference
