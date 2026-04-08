# Membership_Tier Data Structure and MDP Connection

This document describes the structure and external linkage of membership tiers in the Wicket Memberships plugin.

## Overview
- Membership tiers are represented by the `Membership_Tier` class.
- Every tier in WordPress is always connected to a corresponding tier in an external data store called the MDP (Membership Data Platform).

## MDP Connection
- The meta field that connects a WordPress tier to the MDP is `mdp_tier_uuid`.
- `mdp_tier_uuid` is the unique identifier for the tier in the external MDP system.
- This value is stored in the `tier_data` array in post meta for the tier post.
- All tier lookups, synchronization, and cross-system operations use this UUID as the authoritative link.

## Config Reference
- Every tier will always have a reference to a config (via `config_id` in `tier_data`).
- The config defines the behavior and rules for memberships that use this tier (renewal, cycle, grace period, etc).
- This linkage ensures that when a membership is assigned to a tier, its behavior is governed by the correct config.

## Example Tier Meta Structure
```php
// Example of tier_data meta for a tier post
[
  'mdp_tier_uuid' => 'external-mdp-uuid-123',
  'mdp_tier_name' => 'Gold',
  'config_id' => 321, // Reference to the config post
  'type' => 'individual', // or 'organization'
  'renewal_type' => 'subscription', // or 'current_tier', 'sequential_logic', 'form_flow'
  'next_tier_id' => 42, // for sequential_logic
  'next_tier_form_page_id' => 99, // for form_flow
  'product_data' => [
    [
      'product_id' => 803,
      'variation_id' => 805,
      'max_seats' => -1,
    ],
  ],
  'seat_type' => 'per_seat', // or 'per_range_of_seats'
  'approval_required' => 1,
  'grant_owner_assignment' => 0,
  'approval_email_recipient' => 'admin@example.com',
  'approval_callout_data' => [
    'locales' => [
      'en' => [
        'callout_header' => 'Approval Needed',
        'callout_content' => 'Your membership requires approval.',
        'callout_button_label' => 'Request Approval',
      ],
    ],
  ],
  // ...other tier-specific fields...
]
```

## Approval Requirement Option
- The `approval_required` option on a tier determines if memberships created in this tier are set to `pending approval`.
- If `approval_required` is set (true/1), new memberships will be created with status `pending approval` and require an administrator to manually change the status to `active` before the membership is created in the MDP.
- If `approval_required` is not set (false/0), memberships will be created as `active` immediately and will be created in the MDP without manual intervention.

## Renewal Type Options and Callout Behavior

The `renewal_type` field on a tier controls how memberships are renewed and how the renewal callout is constructed and behaves in the account center. The main options and their effects are:

### renewal_type Values
- **subscription**: Membership is renewed by extending the same subscription. No next tier or form page is set. The callout prompts the user to renew their current subscription directly.
- **current_tier**: Membership is renewed into the same tier, but not as a subscription (e.g., manual renewal). The next tier is set to the current tier. The callout guides the user to renew into the same tier, possibly via a form or manual process.
- **sequential_logic**: Membership is renewed into a different, specific next tier. The next tier ID must be set. The callout prompts the user to renew into the next tier, and may display information about the new tier or upgrade path.
- **form_flow**: Membership is renewed via a form flow, with a specific next form page. The next tier form page ID must be set. The callout directs the user to a form for renewal, rather than a direct product or tier.

### Effect on Memberships
- Determines the renewal path: whether the user stays in the same tier, moves to a new tier, or completes a form.
- Controls what data is set on the membership record (e.g., next tier, next form page, renewal as subscription or not).
- Affects whether renewal is automatic (subscription) or requires user action (form or tier change).

### Effect on Callout Construction and Behavior
- The callout logic uses `renewal_type` to decide:
  - What action to present to the user (renew subscription, go to form, upgrade tier, etc).
  - How to construct the callout’s button, label, and destination (e.g., "Renew Now", "Go to Next Tier", "Open Renewal Form").
  - What messaging and options to show, based on the renewal path.
- This ensures the user sees the correct renewal prompt and is guided to the appropriate next step for their membership.

#### Callout Destinations by Renewal Type
- For **current_tier** and **sequential_logic**: The callout will direct the user to the cart with the appropriate product for renewal already added. The user completes the renewal by checking out with this product.
- For **subscription**: The callout will send the user directly to a subscription renewal order checkout page, streamlining the renewal of their existing subscription.

## Assigning Subscription Products to Tiers

To enable automatic membership creation when a user purchases a subscription, you must assign one or more WooCommerce subscription products to each tier. This association is managed via the `product_data` field in the tier's meta data.

- Each entry in `product_data` links a WooCommerce product (and optionally a variation) to the tier.
- When a user purchases a subscription product that is assigned to a tier, a membership in that tier is automatically generated for the user.
- This linkage ensures that the correct membership tier is granted based on the product purchased, and that renewal and upgrade logic can be applied according to the tier's configuration.

## Key Points
- Always set and use `mdp_tier_uuid` when creating or referencing a tier.
- The `Membership_Tier` class provides methods to get the UUID and to look up tiers by UUID.
- This linkage ensures that all tier operations in WordPress can be mapped to the correct entity in the MDP.

## Key Methods in Membership_Tier

### MDP and Config Linkage
- `get_mdp_tier_uuid()` — Returns the MDP UUID for a tier.
- `get_mdp_tier_name()` — Returns the MDP name for a tier.
- `get_tier_id_by_wicket_uuid($uuid)` — Finds the WordPress tier post ID by MDP UUID.
- `get_config_id()` — Returns the config post ID linked to this tier.
- `get_config()` — Returns the `Membership_Config` object for this tier's config.

### Product and Seat Methods
- `get_product_ids()` — Returns all WooCommerce product IDs for this tier.
- `get_product_variation_ids()` — Returns all product variation IDs for this tier.
- `get_products_data()` — Returns the full product data array for this tier.
- `get_seat_type()` — Returns the seat type (e.g., 'per_seat').
- `is_per_seat()` — True if seat type is 'per_seat'.
- `is_per_range_of_seats()` — True if seat type is 'per_range_of_seats'.
- `get_seat_count()` — Returns the seat count for this tier (if set).

### Tier Type and Renewal Logic
- `get_tier_type()` — Returns the tier type ('individual' or 'organization').
- `is_organization_tier()` — True if tier is for organizations.
- `is_individual_tier()` — True if tier is for individuals.
- `get_tier_renewal_type()` — Returns the renewal type for this tier.
- `is_renewal_subscription()` — True if renewal type is 'subscription'.
- `is_renewal_form_page()` — True if renewal is via form page.
- `is_renewal_tier()` — True if renewal is to another tier.
- `get_next_tier_id()` — Returns the next tier post ID (if sequential logic).
- `get_next_tier_form_page_id()` — Returns the next form page ID (if form flow).
- `get_next_tier()` — Returns the next `Membership_Tier` object (if set).

### Approval and Owner Assignment
- `is_approval_required()` — True if approval is required for this tier.
- `is_grant_owner_assignment()` — True if owner assignment is required.
- `get_approval_email()` — Returns the approval email recipient.
- `get_approval_callout_header($lang = 'en')` — Returns the approval callout header for a given language.
- `get_approval_callout_content($lang = 'en')` — Returns the approval callout content for a given language.
- `get_approval_callout_button_label($lang = 'en')` — Returns the approval callout button label for a given language.

### Utility and Data Methods
- `update_tier_data($new_tier_data)` — Updates the tier's data in post meta.
- `get_meta_tier_data_field_name()` — Returns the meta field name for tier data.
- `get_membership_tier_post_id()` — Returns the post ID for this tier.
- `get_membership_posts()` — Returns all membership posts linked to this tier.
- `get_membership_tier_slug()` — Returns the slug for this tier.
- `get_tier_post_id()` — Returns the post ID for this tier.

---

This doc should be referenced by developers and integrators working with tier synchronization, import/export, or any logic that requires mapping between WordPress and the external MDP system. All method names above are the real method names from the `Membership_Tier` class for accurate reference.
