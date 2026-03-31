# Organization Member Management

The Organization Member Management page displays all memberships for a specific organization and allows administrators to view and update membership details, manage seat assignments, and change the organization owner.

---

## Top Section — Organization Information

### Organization Name

The legal name of the organization this membership belongs to.

### Location

The primary location or address associated with the organization.

### Identifying Number

A unique identifier assigned to this organization in the Wicket Member Data Platform (MDP) for tracking and reporting purposes.

### Quick Actions

- **View in MDP** — Open the organization's record in the Wicket MDP in a new window.

---

## Membership Records

A list of all memberships held by this organization. Each membership can be expanded to reveal detailed editing and management options.

### Membership List Columns

- **Membership Tier** — The name of the tier the organization belongs to.
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

A button to manually create a renewal order for this membership. This allows the organization to renew outside of the normal renewal window if needed.

### Start Date, End Date, Expiration Date

Three date fields where you can manually adjust the membership timeline:

- **Start Date** — When the membership begins (e.g., January 1, 2025).
- **End Date** — When the membership period ends (e.g., December 31, 2025). This affects renewal eligibility based on the Renewal Window setting from the Configuration.
- **Expiration Date** — When the membership fully expires after any grace period.

### Renewal Type

Controls how this specific membership renews. Options are:

- **Inherited from Tier** — Uses whatever renewal flow is set on the Membership Tier.
- **Current Tier** — The organization renews back into the same tier.
- **Sequential Logic** — The organization is moved to a different tier upon renewal. Requires selecting a **Sequential Tier**.
- **Renewal Form Flow** — Renewal is handled through a specific page. Requires selecting a **Form Page**.
- **Subscription Renewal** — Renewal is managed automatically by a WooCommerce subscription.

If Inherited is selected, the inherited renewal type will be shown below as a reference.

### Sequential Tier (when Sequential Logic is selected)

A dropdown to choose which Membership Tier this organization will be moved to when they renew. This allows tiered progression (e.g., Small Business → Medium Business → Enterprise).

### Form Page (when Renewal Form Flow is selected)

A dropdown to select the WordPress page that contains the renewal form. The organization's owner will be directed to this page when they renew.

---

## Organization-Specific Options

### Seats

A status display showing the current seat allocation and usage:

- **Total Seats** — The total number of seats purchased for this organization membership.
- **Assigned Seats** — The number of seats currently assigned to employees or individuals within the organization.
- **Unassigned** — The number of available seats not yet assigned.

Below the seat display is a link to **Manage Seats in MDP**, which opens the Wicket MDP in a new window where you can assign specific individuals to available seats.

### Membership Owner

The person responsible for managing and renewing the organization's membership. This is typically the authorized representative from the organization.

#### Quick Actions for Owner

- **Switch To** — Temporarily log in as this owner to see their account from their perspective.
- **View in MDP** — Open the owner's record in the Wicket MDP in a new window.

#### Changing the Owner

A searchable dropdown allows you to search for and assign a different person as the Membership Owner. Start typing a name to search; results appear as you type. This is useful when organizational contacts change or a different person needs to approve renewals.

---

## Update Membership

A button to save all changes made to the membership. The button is disabled if the membership status is Cancelled or if an update is already in progress.
