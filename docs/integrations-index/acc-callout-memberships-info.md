# AC Callout Block & Memberships

## Overview

The ACC Callout block is the primary way the Account Centre communicates membership-related messages to logged-in users. It is placed on pages in the WordPress editor and automatically shows or hides itself depending on each user's current membership status. There is no single static message — the block decides what to display (and whether to display at all) at the moment a user loads the page.

The block connects to the Wicket Memberships plugin to read each user's membership records, renewal windows, and profile state. It also integrates with WooCommerce to check order and subscription statuses.

---

## How the Block Decides What to Show

When a user visits a page containing the Callout block, the block runs through a set of checks in real time and chooses one of three behaviours:

1. **Become a Member** — shown to users who do not yet have a membership, or who have a pending application waiting for approval.
2. **Membership Renewal** — shown to users with an active membership that is approaching or has passed its expiry date.
3. **Complete Your Profile** — shown to users with an active membership who haven't filled in one or more required profile fields.

Which behaviour the block uses is set by the editor when placing the block on the page (see **Block Logic** below). A single page may contain multiple Callout blocks, each set to a different mode.

---

## Editor Options

These are the settings available when placing the Callout block on a page.

### Title

The heading text of the callout card. This is what the user reads first. It can be a plain prompt, a personalised message, or a status update depending on the block's mode.

### Description

A rich-text body for the callout. This can include formatted text, links inline in the text, and any supporting detail beneath the heading.

### Block Logic

The core setting that determines which membership scenario the block responds to. There are three options:

- **Become Member** — activates the "join" or "pending approval" behaviour
- **Renewal** — activates the renewal reminder behaviour
- **Profile** — activates the profile completion prompt

Only one mode is active per block instance. See the sections below for what each mode does.

### Renewal Period *(only shown when Block Logic is set to Renewal)*

A number of days (between 1 and 365) that defines how early the renewal callout begins appearing before a membership expires. For example, setting this to 30 means the callout starts showing 30 days before the expiry date and continues showing until the member renews.

### Mandatory Profile Fields *(only shown when Block Logic is set to Profile)*

A checklist of profile fields that the block treats as required. The callout will only show if at least one of the selected fields is missing from the user's profile. Available fields:

- **First Name**
- **Last Name**
- **Gender**
- **Birth Date**
- **Addresses** — requires a primary address with a country, city, street address, and postal code (postal code is only required for US and Canadian addresses)

### Links / Call-to-Action Buttons

A repeatable set of action buttons that appear at the bottom of the callout card. Each button has:

- **Link** — the destination URL and button label (can be any internal or external URL)
- **Style** — either **Primary** (filled button) or **Ghost** (outlined button)

Multiple buttons can be added. The order they are added is the order they appear.

### Capture Query String

When enabled, any URL parameters present on the current page are automatically appended to every link in the callout. For example, if a user arrived at the account page via a tracked link (`/account/?ref=email`), enabling this option passes that `ref=email` parameter through to the membership application or renewal link as well. If there is a conflict between a parameter in the link and a parameter from the page, the page's value takes priority.

---

## Logic Mode: Become Member

**Who sees this:** Users who do not have any active membership — whether from Wicket or WooCommerce.

**What it does:**

When a user has no active membership, the callout shows with whatever title, description, and links the editor has configured — typically a prompt to apply or purchase a membership.

### Pending Approval State

Before showing the standard "become a member" message, the block checks whether the user has recently placed a membership order that is waiting for manual approval. This happens when:

- The user placed a WooCommerce order for a subscription-based membership product, and
- That membership tier requires admin approval before it is granted

If a pending approval is found, the callout automatically switches its content to an approval-waiting message. The title, body text, and contact button are pulled directly from the membership tier's configuration in the Wicket Memberships plugin — not from what the editor typed into this block. The callout turns green to signal a pending state rather than a missing membership.

**When it hides:** As soon as the user has any active membership, this callout disappears entirely.

---

## Logic Mode: Renewal

**Who sees this:** Users with an active membership that is within the renewal window.

**What it does:**

The block watches each user's membership expiry dates. Once the current date falls within the configured **Renewal Period** (see above) before the expiry date, the callout appears. The title, description, and action links (including cart or form links for renewing) are generated from the membership tier's settings in the Wicket Memberships plugin.

### Renewal Types

The callout's visual appearance changes depending on how urgent the renewal situation is:

- **Early Renewal** — shown when the renewal window has just opened; displayed in yellow/amber to flag that renewal is available
- **Grace Period** — shown when the membership has already expired but the user is still within a grace period; displayed in red to signal urgency
- **Multi-Tier Renewal** — when a user holds multiple membership tiers that all need renewing at the same time, the block combines them into a single callout with a single cart link adding all relevant products at once

### Renewal Links

The renewal button destination is determined automatically based on what the membership tier requires:

1. If there is a new tier available (upgrade path) without needing a subscription renewal, the button adds that product to the WooCommerce cart directly
2. If a renewal form page is configured on the tier, the button links to that form
3. If the membership is subscription-based, the button links to the WooCommerce subscription renewal flow

### Hiding for Active Members

When a user has an active membership that is not yet in the renewal window, the block also outputs a hidden CSS class tied to that membership tier. Other blocks on the page can use this class to hide content that should not be visible to current members of that tier — without needing any additional logic.

**When it hides:** When the user's membership is active and the renewal window has not yet opened, or after the user has successfully renewed.

---

## Logic Mode: Profile (Complete Your Profile)

**Who sees this:** Users who have an active membership but have not yet completed all of the mandatory profile fields selected in the block's settings.

**What it does:**

The block checks the logged-in user's Wicket profile in real time and compares it against the list of mandatory fields chosen by the editor. If any of those fields are empty or incomplete, the callout appears prompting the user to fill them in. The title, description, and action button (typically a link to the profile editing page) are set by the editor.

**When it hides:** As soon as all selected mandatory fields are filled in, the callout disappears on the next page load. It also hides entirely if the user has no active membership.

---

## How the Block Handles Multiple Memberships

A single user can hold more than one membership tier simultaneously. In renewal mode, the block loops through all of the user's memberships and evaluates each one independently. If multiple renewals are due:

- Each renewal that is not a "multi-tier" renewal gets its own callout card rendered in sequence
- Renewals grouped as multi-tier by the Wicket Memberships plugin are consolidated into a single callout with a combined cart link

---

## Filtering and Customisation for Developers

Two hooks are available for developers who need to adjust the block's behaviour without editing its source:

- **Product category filter** — allows the list of WooCommerce product categories the block checks for membership products (used in Become Member logic) to be extended beyond the default `membership` category
- **Skip renewal record filter** — allows specific membership tiers to be excluded from renewal callout rendering entirely, on a per-tier basis

---

## Visual Styles at a Glance

| Mode | Sub-state | Colour |
|---|---|---|
| Become Member | Default | Light blue |
| Become Member | Pending approval | Green |
| Renewal | Early/standard | Yellow/amber |
| Renewal | Grace period | Red |
| Profile | All states | Grey |
