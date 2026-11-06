---
title: "Prorate the First Payment for Mid-Season Joins"
audience: end-user
---

# Prorate the First Payment for Mid-Season Joins

A calendar membership runs on fixed season dates, for example January 1 to December 31. Without proration, a member who joins in June pays the same price as a member who joins in January.

Proration lowers the first payment for a member who joins mid-season. The member pays only for the part of the season that is left. Every renewal after that charges the full price.

You turn proration on in two places:

1. On the **Membership Config**, you turn it on and choose a rate.
2. On each **Membership Tier**, you choose which products use it.

## Before You Start

- The Membership Config must use the **Calendar** cycle. Anniversary configs do not support proration, because each member already starts their own full term.
- The config must have at least one season that covers the dates when members join.
- The tier products must be WooCommerce subscription products, as for any tier.

## Step 1: Turn On Proration for a Config

1. In the WordPress admin, go to **Wicket Memberships → Membership Configs**.
2. Open the config you want to change.
3. Make sure **Cycle** is set to **Calendar**.
4. Below the **Seasons** table, select **Enable Proration**.
5. Choose a **Proration Rate**. See [Choose a Proration Rate](#choose-a-proration-rate).
6. Click **Save Membership Configuration**.

You cannot save the config with proration on and no rate selected.

## Step 2: Choose Which Products Are Prorated

Proration applies only to the products you select. This lets you prorate one product on a tier and charge full price for another.

1. Go to **Wicket Memberships → Membership Tiers**.
2. Open a tier that uses the config from Step 1.
3. In the **Products** list, select the **Prorate** checkbox for each product that should get a prorated first payment.
4. Click **Save Membership Tier**.

The **Prorate** column shows only when the tier's config has proration turned on. If you do not see it, check Step 1.

## Choose a Proration Rate

The rate controls how the plugin counts the part of the season that is left.

| Rate | What the member pays |
|---|---|
| **Daily (recommended)** | Only the days left in the season, including the day they join. This is the most exact "pay for what you use" option. |
| **Weekly** | The weeks left in the season. The member pays the full week they join in. |
| **Monthly** | The months left in the season. The member pays the full month they join in. |
| **Quarterly** | The 3-month periods left in the season. The member pays the full quarter they join in. |
| **Semi-Annually** | The 6-month periods left in the season. The member pays the full half they join in. |
| **Annually** | The full price. Use this to keep proration set up but charge full price for now. |

Weeks, months, quarters, and halves count from the season start date, not from the calendar year.

### Examples

These examples use a season from January 1 to December 31 and a product priced at $120.00 per year.

| Rate | Join date | First payment | Renewal price |
|---|---|---|---|
| Daily | June 15 | $65.75 | $120.00 |
| Monthly | June 15 | $70.00 | $120.00 |
| Quarterly | May 10 | $90.00 | $120.00 |
| Semi-Annually | August 1 | $60.00 | $120.00 |
| Annually | Any date | $120.00 | $120.00 |

Prices round to the nearest cent.

## What the Member Sees

- **Product and shop pages** show the full price.
- **Cart and checkout** show the prorated first payment as the order total. The **Recurring totals** section shows the full renewal price, for example $120.00 / year.
- **The order** charges the prorated amount. The subscription and every renewal order charge the full price.

If the product has a sign-up fee, the member pays the full sign-up fee on top of the prorated price.

## When the Member Pays Full Price

Proration applies only to a new purchase. The member pays the full price in these cases:

- The member renews a membership, including an early renewal.
- The member already has an active membership on the same tier.
- The member resubscribes to a cancelled or expired subscription.
- The member switches a subscription from one product to another.
- The product has a free trial. WooCommerce Subscriptions charges only the sign-up fee up front, as usual.
- The purchase date is not inside any season of the config.
- The membership comes from a membership bundle, or an admin creates the order in the WordPress admin.

## Important Notes

- **Do not use synchronised renewals on prorated products.** WooCommerce Subscriptions has its own "synchronise renewals" proration. If a product uses it, WooCommerce Subscriptions sets the first payment, and Wicket proration does not apply to that product.
- **Turning proration off keeps your product choices.** If you clear **Enable Proration** on the config, the **Prorate** column hides on the tier, but the plugin keeps your selections. Turn proration back on to use them again.
- **A product on two tiers follows the first tier.** If one product is linked to more than one tier, the first tier that lists it decides whether the product is prorated.
- **Refunds use the amount the member paid.** The plugin does not add special refund logic. The order stores the proration details (rate, share of the price, full price, and season dates) for support staff and developers.
- **Custom rules need a developer.** A developer can change the prorated price with code, for example to set a minimum charge. There is no admin setting for a minimum charge.
