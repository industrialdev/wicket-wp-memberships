# Individual Member Management

The Individual Member Management page displays all memberships for a specific member and allows administrators to view and update membership details.

---

## Top Section — Member Information

### Email Address

The email address associated with this individual member's account.

### Identifying Number

A unique identifier assigned to this member in the Wicket Member Data Platform (MDP) for tracking and reporting purposes.

### Quick Actions

- **Switch To** — Temporarily log in as this member to see their account from their perspective.
- **View in MDP** — Open the member's record in the Wicket MDP in a new window.

---

## Membership Records

A list of all memberships held by this member. Each membership can be expanded to reveal detailed editing options.

### Membership List Columns

- **Membership Tier** — The name of the tier the member belongs to.
- **ID** — The unique ID of the membership record in WordPress.
- **Status** — The current status (e.g., Active, Expired, Cancelled).
- **Start Date** — When the membership became active.
- **End Date** — When the membership period ends.
- **Exp. Date** — When the membership expires (after the grace period, if applicable).

---

## Expanded Membership Details

Click the expand button (+ or -) to view and edit details for a specific membership.

### Billing Information

If the membership is powered by a WooCommerce subscription, this section displays:

- **Subscription** — A link to the subscription record with its ID number.
- **Next Payment Date** — When the next automatic payment will occur.

### Order Information

If the membership is linked to a WooCommerce order, a table displays:

- **Order Number** — A link to the order in WooCommerce.
- **Order Date** — When the order was created.
- **Order Total** — The total amount charged.
- **Order Status** — The current order status (e.g., Completed, Processing).

### Membership Status

A read-only field showing the current status of the membership. 

- **Manage Status** — Opens a modal dialog where you can change the membership status (e.g., from Active to Expired, or pause/resume).

### Create Renewal Order

A button to manually create a renewal order for this membership. This allows the member to renew outside of the normal renewal window if needed.

### Start Date, End Date, Expiration Date

Three date fields where you can manually adjust the membership timeline:

- **Start Date** — When the membership begins (e.g., January 1, 2025).
- **End Date** — When the membership period ends (e.g., December 31, 2025). This affects renewal eligibility based on the Renewal Window setting from the Configuration.
- **Expiration Date** — When the membership fully expires after any grace period. This is typically equal to or beyond the End Date depending on the grace period setting.

### Renewal Type

Controls how this specific membership renews. Options are:

- **Inherited from Tier** — Uses whatever renewal flow is set on the Membership Tier.
- **Current Tier** — The member renews back into the same tier.
- **Sequential Logic** — The member is moved to a different tier upon renewal. Requires selecting a **Sequential Tier**.
- **Renewal Form Flow** — Renewal is handled through a specific page. Requires selecting a **Form Page**.
- **Subscription Renewal** — Renewal is managed automatically by a WooCommerce subscription.

If Inherited is selected, the inherited renewal type will be shown below as a reference.

### Sequential Tier (when Sequential Logic is selected)

A dropdown to choose which Membership Tier this member will be moved to when they renew. This allows tiered progression (e.g., Bronze → Silver → Gold).

### Form Page (when Renewal Form Flow is selected)

A dropdown to select the WordPress page that contains the renewal form. Members will be directed to this page when they renew.

### Update Membership

A button to save all changes made to the membership. The button is disabled if the membership status is Cancelled or if an update is already in progress.
