# Membership Tier Options

A Membership Tier is the specific membership product offered to members or organizations. It links a tier defined in the Wicket Member Data Platform (MDP) to WooCommerce products and controls how renewals, approvals, and seat assignments work.

---

## Membership Tier

A dropdown listing all tiers available from the Wicket MDP. Selecting a tier connects this WordPress record to the corresponding tier in the central membership database.

Once a tier is selected, the following read-only information is displayed for reference:

- **Status** — Whether the tier is currently Active or Inactive in the MDP.
- **Type** — Whether this is an **Individual** or **Organization** tier. This controls which other options appear below.
- **Category** — The category the tier belongs to in the MDP, if one is assigned.
- **Grace Period (Days)** — The grace period configured on the MDP side for this tier.
- **# of Members** — The current count of members in this tier, with a link to view all of them.

---

## Membership Config

Links this tier to a Membership Configuration, which defines the renewal window, grace period, and membership cycle. Every tier must be assigned a configuration.

The dropdown lists all available configurations. If a configuration is set up for Multi-Tier Renewal, this will be noted next to its name.

---

## Approval Required

When checked, new memberships under this tier will require manual approval before they are activated. An email notification will be sent to the address you specify.

- **Approval Email Recipient** — The email address that will receive a notification when someone applies for this tier and approval is needed.

### Callout Configuration (Approval)

Available when Approval Required is enabled. Defines the message shown to a member in the Account Centre while their application is pending approval.

- **Callout Header** — The title of the pending approval message.
- **Callout Content** — The body text explaining to the member that their application is under review.
- **Button Label** — The text on the action button shown in the prompt.

Language-specific versions can be configured using the **Language** selector.

---

## Renewal Type

Controls what happens when a member renews this tier. There are four options:

### Current Tier

The member renews back into the same tier they are already in. This is the most common option.

### Sequential Logic

When the member renews, they are moved to a different tier. Use the **Sequential Tier** field that appears to select which tier the member will be moved to upon renewal.

- **Sequential Tier** — The Membership Tier the member will be upgraded or changed to when they renew.

### Renewal Form Flow

Renewal is handled through a specific page on your website (e.g., a Gravity Forms page). Use the **Form Page** field that appears to select which page the member will be directed to.

- **Form Page** — The WordPress page that contains the renewal form.

### Subscription

Renewal is managed automatically through a WooCommerce subscription product. The membership does not use sequential logic or a form flow; instead it is renewed on the billing schedule of the linked subscription product.

---

## Individual Tier Options

The following options appear when the selected tier is of type **Individual**.

### Granted Via

A list of WooCommerce products that grant this membership when purchased. You can add multiple products.

Each product entry includes:
- **Product** — The WooCommerce product (or product variation) that, when purchased, grants the member this tier.

Use the **Add Product** button to add products to the list.

---

## Organization Tier Options

The following options appear when the selected tier is of type **Organization**.

### Seat Settings

Determines how seats (i.e., the number of people covered by an organization's membership) are priced and managed. There are two options:

- **Per Seat** — Each individual seat is purchased and priced separately.
- **Per Range of Seats** — Seats are sold in ranges or bundles (e.g., 1–10 seats for one price, 11–25 for another). Use this when pricing is based on seat volume rather than individual seats.

Changing this setting will clear any products already added.

#### Products (Per Seat)

The WooCommerce products that correspond to a single seat purchase for this tier.

#### Seats Data (Per Range of Seats)

Each entry defines a product for a specific seat range, including a maximum seat count for that range. This allows different pricing tiers based on how many seats an organization needs.

### Automatically Grant Owner Seat

When checked, the person who purchases the organization membership (the "owner") will automatically be assigned a seat without needing to be manually added. This saves an extra step for the administrator or the member.
