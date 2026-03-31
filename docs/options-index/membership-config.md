# Membership Configuration Options

A Membership Configuration defines the rules that govern how a membership behaves — when members can renew, how long a grace period lasts, and what schedule the membership follows. One configuration can be shared across multiple Membership Tiers.

---

## Configuration Name

A label used to identify this configuration throughout the plugin. It appears in dropdowns when setting up Membership Tiers, so choose a name that clearly describes what this configuration is for (e.g., "Annual Individual Membership" or "Corporate Calendar Year").

---

## Multi-Tier Renewal

When enabled, all Membership Tiers that use this configuration — and any other configuration also marked as Multi-Tier — will have their renewal prompts bundled together into a single callout in the Account Centre, rather than showing separate prompts for each tier.

Use this when a member belongs to multiple tiers that should all be renewed in one single step.

> This option is only visible if the Multi-Tier Renewals feature has been enabled in the plugin settings.

---

## Renewal Window (Days)

The number of days before a membership expires during which a member is eligible to renew. For example, setting this to `30` means members will be able to renew starting 30 days before their membership end date.

### Callout Configuration (Renewal Window)

Opened via the "Callout Configuration" button next to the Renewal Window field. This lets you define the message shown to members when they are inside the renewal window in the Account Centre.

- **Callout Header** — The title/heading of the renewal prompt.
- **Callout Content** — The body text of the renewal prompt, where you can explain what the member needs to do.
- **Button Label** — The text shown on the action button in the prompt.

If your site supports multiple languages, you can configure separate text for each language by switching the **Language** selector at the top of this form.

---

## Grace Period Window (Days)

The number of days after a membership expires during which the member is still considered active (i.e., in a "grace period"). During this window, the membership has technically lapsed but the member retains access.

Set this to `0` if you do not want any grace period.

### Product (Grace Period)

An optional WooCommerce product that can be associated with the grace period — for example, a late fee product. When a member is in the grace period and renews, this product can be included in their order.

Leave this blank if no late fee or grace period product applies.

### Callout Configuration (Grace Period Window)

Works the same way as the Renewal Window callout. This message is shown to members who are currently in the grace period in the Account Centre.

- **Callout Header** — The title of the grace period prompt.
- **Callout Content** — The body text explaining the member's situation and what they should do.
- **Button Label** — The text on the action button.

Language-specific versions can be configured using the **Language** selector.

---

## Cycle

Determines the type of schedule this membership follows. There are two options:

### Calendar

The membership runs on fixed calendar dates (e.g., January 1 – December 31 every year). You define one or more **Seasons** that represent the active membership periods.

#### Seasons

Each season is a named date range within a calendar cycle. You can add multiple seasons, and each one has:

- **Season Name** — A label for the season (e.g., "2025 Season").
- **Status** — Whether the season is currently Active or Inactive. Inactive seasons will not be offered for new memberships.
- **Start Date** — The date the membership period begins.
- **End Date** — The date the membership period ends.

Use the **Add Season** button to create a new season. Click the edit icon on an existing season to update it.

### Anniversary

The membership runs for a set duration starting from the date the member joins (e.g., one year from sign-up). You define the length of that period:

- **Membership Period** — A number representing the duration (e.g., `1`).
- **Period Type** — Whether that duration is measured in **Years**, **Months**, or **Weeks**.

#### Align End Dates

An optional setting within the Anniversary cycle. When enabled, all memberships using this configuration will have their end dates automatically adjusted to fall on the same day of the month, regardless of when the member joined. This makes reporting and renewals easier to manage.

- **Align by** — Choose whether end dates are aligned to the **First Day of the Month**, the **15th of the Month**, or the **Last Day of the Month**.
