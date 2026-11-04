---
title: "Link a Membership Tier to a WooCommerce Product"
audience: end-user
---

# Link a Membership Tier to a WooCommerce Product

Membership tiers control what happens when a customer purchases a WooCommerce subscription product. To connect a tier to a product, you add the product from within the tier edit screen.

## Before You Start

- WooCommerce Subscriptions must be installed and active
- Wicket Memberships plugin must be active
- You need at least one Membership Tier and one WooCommerce subscription product already created

## Connect a Product to a Tier

1. In the WordPress admin, go to **Memberships → Membership Tiers**.
2. Click on the tier you want to link.
3. Scroll to the **Product Data** section (or the equivalent field in the React edit page).
4. Click **Add Product** and select your subscription product.
5. Optionally, set a **maximum seats** value if the tier supports a limited number of members.
6. Click **Update** to save the tier.

When a customer purchases that subscription product, a membership record is automatically created in the tier.

## Renewal Behavior

The tier's **Renewal Type** setting controls how renewals work:

- **Subscription** — renews automatically through WooCommerce Subscriptions
- **Current Tier** — directs the user to renew into the same tier (via cart)
- **Sequential Logic** — renews into a specific next tier you configure
- **Form Flow** — sends the user through a form for renewal

## Product Variations

If you use variable subscription products, you can link specific variations to the tier. Each variation can map to the same tier or to different tiers depending on your setup.
