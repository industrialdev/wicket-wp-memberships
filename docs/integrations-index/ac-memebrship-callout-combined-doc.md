# Account Centre Callout Block — Complete Renewal Reference

This document covers the full renewal callout system end-to-end: how the **Account Centre Callout block** is configured by an editor, what it displays to a member at runtime, and how the **Wicket Memberships plugin** produces the underlying data that drives all of those decisions.

---

## The Two Parts of the System

The callout experience a member sees is the result of two separate pieces working together:

1. **The AC Callout Block** — a WordPress block placed on pages in the editor. It decides *which mode* to operate in (become a member, renew, or complete a profile) and presents the result to the visitor.
2. **The Memberships Plugin data pipeline** — supplies the block with all membership state at runtime: what callout type the member qualifies for, what text to show, and what renewal action to offer.

Neither part is sufficient alone. The block provides the display scaffolding; the plugin provides the real data.

---

## Block Editor Options

When a page editor places a Callout block on a page, the following settings are available:

### Title
The heading text of the callout card — the first line a member reads.

### Description
A rich-text body for the callout. Supports formatted text and inline links.

### Block Logic
The core setting controlling which membership scenario the block responds to. Three options:

- **Become Member** — activates the join/pending-approval experience
- **Renewal** — activates the renewal reminder experience
- **Profile** — activates the profile-completion prompt

Each block instance operates in exactly one mode. Multiple blocks can be placed on a page, each in a different mode, to handle several scenarios at once.

### Renewal Period *(Renewal mode only)*
A number of days (1–365) defining how early before a membership's expiry the renewal callout begins appearing. For example, 30 days means the callout starts appearing 30 days before the expiry date.

> **Note:** This block-level setting works alongside the **Renewal Window Days** configured in the Wicket Memberships plugin (Membership Config). The plugin calculates the precise date a member enters their renewal window; the block uses that calculation when deciding whether to display.

### Mandatory Profile Fields *(Profile mode only)*
A checklist of fields the block treats as required. The callout appears when at least one selected field is missing from the user's Wicket profile. Available fields:
- First Name
- Last Name
- Gender
- Birth Date
- Addresses (requires a primary address with country, city, street address, and postal code — postal code required for US and Canadian addresses only)

### Links / Call-to-Action Buttons
A repeatable set of action buttons at the bottom of the callout card. Each button has:
- **Link** — destination URL and button label
- **Style** — **Primary** (filled) or **Ghost** (outlined)

Multiple buttons can be added in any order.

### Capture Query String
When enabled, any URL parameters on the current page are automatically forwarded to every link in the callout. Useful for preserving tracking or referral parameters through the renewal journey. Page parameters override link parameters in the event of a conflict.

---

## The Three Block Modes

### Mode 1: Become Member

**Who sees it:** Users without any active membership (Wicket or WooCommerce).

The block shows the editor-configured title, description, and links — typically a prompt to apply or purchase a membership.

**Pending Approval sub-state:** Before showing the standard message, the block checks whether the user has a recent membership order waiting for manual approval. When found, the block switches to an approval-waiting message. The title, body, and contact button are pulled directly from the **Tier's approval callout fields** (configured in the Wicket Memberships plugin — not from what the editor typed into this block). The card turns green to signal a pending state.

**Hides when:** The user gains any active membership.

---

### Mode 2: Renewal

**Who sees it:** Users with an active membership that is within the renewal window or grace period.

The block pulls renewal data from the plugin in real time. The title, description, and action links are sourced entirely from the Membership Config and Tier settings — not the block's own text fields. The editor-configured text in this block is not shown; the block's role is to render what the plugin returns.

#### Callout States Within Renewal Mode

| State | When It Appears | Card Colour |
|---|---|---|
| Early Renewal | Today is within the Renewal Window but the membership has not yet ended | Yellow / amber |
| Grace Period | The membership has ended but the member is still within the Expiration buffer | Red |
| Multi-Tier | Multiple memberships are grouped for renewal simultaneously | Yellow / amber (combined) |

**Hiding for active members:** When a user holds an active membership not yet in the renewal window, the block outputs a hidden CSS class tied to that tier. Other blocks on the page can use this class to hide content that current members of that tier should not see.

**Hides when:** The membership is active and the renewal window has not yet opened, or after the member renews successfully.

---

### Mode 3: Profile (Complete Your Profile)

**Who sees it:** Users with an active membership who are missing one or more mandatory profile fields.

The block checks the logged-in user's Wicket profile against the mandatory fields list set by the editor. If any field is empty or incomplete, the callout appears prompting the user to fill it in.

**Hides when:** All selected mandatory fields are complete, or the user has no active membership.

---

## How Multiple Memberships Are Handled

A member can hold more than one membership tier simultaneously. In Renewal mode, the block evaluates each membership independently:

- Each non-multi-tier renewal gets its own callout card, rendered in sequence
- Renewals flagged as multi-tier by the Memberships plugin are consolidated into a single callout with a combined cart link covering all relevant products

---

## The Data Pipeline: How the Plugin Builds Callout Data

When the block runs in Renewal or Become Member mode, it requests membership callout data from the plugin. The plugin executes a real-time lookup and returns a structured data package. Here is how that package is built.

### What the Response Contains

| Key | Contents |
|-----|----------|
| `early_renewal` | One entry per membership currently inside the renewal window |
| `grace_period` | One entry per membership currently past its End Date but within the Expiration buffer |
| `pending_approval` | One entry per membership awaiting admin approval |
| `membership_exists` | Flat list of normalized tier names for all active/delayed memberships |
| `debug` | Populated only when `WICKET_MEMBERSHIPS_DEBUG_ACC` is enabled — empty in normal operation |

### Language Selection

Before processing any membership, the plugin detects the member's active language. It first checks whether WPML has set a current ISO language code (`en`, `fr`, etc.). If WPML is not active, it falls back to the first two characters of the WordPress site locale (e.g. `en` from `en_US`).

This ISO code is used for every callout text lookup — headers, body content, and button labels are all stored per language on Configs and Tiers, so callout text automatically matches the member's current language without any extra configuration.

### Which Memberships Are Queried

The plugin fetches all membership records for the current user with a status of:

- **Active** — in-good-standing membership
- **Delayed** — purchased but start date is in the future
- **Grace** — past End Date, still within the Expiration buffer
- **Pending** — awaiting admin approval

Expired and cancelled memberships are excluded entirely.

---

## Callout Type: Pending Approval

A membership in **Pending** status is processed first. Its callout content is sourced entirely from the **Tier**:

| Field | Source |
|---|---|
| Header | Tier → Approval Callout Header (current language) |
| Body content | Tier → Approval Callout Content (current language) |
| Button label | Tier → Approval Callout Button Label (current language) |
| Contact email | Tier → Approval Email (pre-populated contact link subject line) |

Once processed as pending-approval, the membership is not evaluated for any other callout type.

---

## Callout Type: Early Renewal

### When It Triggers

The plugin recalculates the Renewal Eligible Date fresh from the **Membership Config** every time — it does not use a stored value on the membership record. The formula is:

> **Renewal Eligible Date = End Date − Renewal Window Days (from Config)**

A membership enters early renewal when:

- Today ≥ Renewal Eligible Date, **and**
- Today < End Date (the membership is still active)

Changing Renewal Window Days in a Config immediately affects all memberships using that Config — no data migration is needed.

### Callout Content Source

| Field | Source |
|---|---|
| Header | Membership Config → Renewal Window Callout Header (current language) |
| Body content | Membership Config → Renewal Window Callout Content (current language) |
| Button label | Membership Config → Renewal Window Callout Button Label (current language) |

---

## Callout Type: Grace Period

### When It Triggers

A membership enters grace period when:

- Today ≥ End Date (membership has ended), **and**
- Today ≤ Expiration Date (grace buffer has not run out)

### Callout Content Source

| Field | Source |
|---|---|
| Header | Membership Config → Grace Period Callout Header (current language) |
| Body content | Membership Config → Grace Period Callout Content (current language) |
| Button label | Membership Config → Grace Period Callout Button Label (current language) |

Grace period entries also include a `late_fee_product_id` — the **Grace Period Product** set in the Config. The Account Centre uses this product ID to add a late fee to the renewal cart if configured.

---

## Renewal Link Paths

Each renewal callout entry includes the information the block needs to render an action button. There are three possible paths, determined by what is stored on the membership record at the time of purchase:

### Path 1: Subscription Renewal
Used when `next_tier_subscription_renewal` is set on the membership. The plugin checks the associated WooCommerce subscription for pending or failed renewal orders and generates a payment link. If no payment is due yet, it generates an early-renewal URL. The block receives a `subscription_renewal` object with a title and permalink.

### Path 2: Form Page Flow
Used when `next_tier_form_page_id` is set on the membership. The renewal directs the member to a specific WordPress page (typically hosting a Gravity Forms or other custom form). The block receives a `form_page` object with the page title, URL, and page ID.

### Path 3: Direct Add-to-Cart
Used when neither of the above is set. The plugin returns the products assigned to the **Next Tier**. The block renders an Add to Cart button for each product on that tier.

---

## When Memberships Are Skipped

Not every membership in the query produces a callout. Several conditions cause a record to be bypassed:

### Already Renewed
The plugin tracks all memberships that are renewals of older records (identified by `previous_membership_post_id`). When a membership matches an ID in that list, it is skipped — the older record has already been renewed and needs no callout. The plugin also cross-checks by tier/form-page pairing and matching end dates to catch renewals where the tier changed.

### Auto-Pay Enabled
If the membership's WooCommerce subscription has automatic payment enabled (the subscription does not require manual renewal) and has an upcoming payment date that has not yet passed, the membership is skipped entirely. These members will be renewed automatically and do not need a manual renewal prompt.

---

## The `membership_exists` List

The `membership_exists` key in the response contains a flat list of tier names (normalised to lowercase, with spaces, hyphens, and commas removed). Every active or delayed membership contributes to this list.

The Account Centre block uses this list to know which tiers the member currently holds. It also outputs hidden CSS classes based on these tier names so that other blocks on the page can conditionally show or hide content for members of specific tiers — without needing additional query logic.

---

## The `WICKET_MSHIP_DISABLE_RENEWALS` Flag

If the server environment variable `WICKET_MSHIP_DISABLE_RENEWALS` is set, the plugin returns **empty arrays** for `early_renewal`, `grace_period`, and `pending_approval`. Only `membership_exists` and `debug` contain data. Intended for maintenance windows or controlled test environments where callouts should be suppressed without touching any configuration.

---

## Developer Customisation Hooks

Two hooks allow developers to adjust block behaviour without modifying its source:

- **Product category filter** — extends the list of WooCommerce product categories the block checks when looking for membership products (used in Become Member logic; default is the `membership` category)
- **Skip renewal record filter** — allows specific membership tiers to be excluded from renewal callout rendering on a per-tier basis

---

## Full Configuration Reference

The table below maps every relevant setting to what it affects in the callout output — covering both the block editor and the Memberships plugin:

| Setting | Where Configured | What It Affects |
|---|---|---|
| Block Logic mode (Become Member / Renewal / Profile) | AC Callout Block | Which membership scenario the block responds to |
| Renewal Period (days on the block) | AC Callout Block | Additional control over when the renewal callout begins appearing |
| Mandatory Profile Fields | AC Callout Block | Which fields trigger the Profile mode callout |
| Links / CTA buttons | AC Callout Block | The action buttons shown on the card |
| Capture Query String | AC Callout Block | Whether page URL parameters are forwarded to callout links |
| Renewal Window Days | Membership Config | The date on which early-renewal callouts begin appearing |
| Renewal Window Callout text (Header / Content / Button) | Membership Config (per language) | Text shown in early-renewal callouts |
| Grace Period Window Days | Membership Config | How long after End Date grace-period callouts continue to appear |
| Grace Period Product | Membership Config | The late-fee product ID passed to the Account Centre for renewal cart inclusion |
| Grace Period Callout text (Header / Content / Button) | Membership Config (per language) | Text shown in grace-period callouts |
| Approval Callout text (Header / Content / Button / Email) | Membership Tier (per language) | Text shown in pending-approval callouts |
| Renewal Type (Subscription / Form Page / Add-to-Cart) | Membership Record (set at purchase) | Which renewal link path is attached to the callout |
| Next Tier | Membership Record | Which tier/products the renewal callout directs the member toward |

---

## Visual Styles at a Glance

| Mode | Sub-state | Card Colour |
|---|---|---|
| Become Member | Default | Light blue |
| Become Member | Pending approval | Green |
| Renewal | Early / standard | Yellow / amber |
| Renewal | Grace period | Red |
| Profile | All states | Grey |

---

## Related Documents

- [docs/options-index/membership-config.md](../options-index/membership-config.md) — All Membership Config options including renewal window, grace period, and callout text fields
- [docs/options-index/membership-tier.md](../options-index/membership-tier.md) — Tier options for approval callouts and renewal type
- [docs/workflow-index/renewal-window-workflow.md](../workflow-index/renewal-window-workflow.md) — How the renewal window timing works end-to-end
- [docs/workflow-index/grace-period-workflow.md](../workflow-index/grace-period-workflow.md) — How the grace period works end-to-end
- [docs/workflow-index/renewal-type-workflows.md](../workflow-index/renewal-type-workflows.md) — Detailed explanation of each renewal path type
- [docs/workflow-index/multi-tier-renewal-workflow.md](../workflow-index/multi-tier-renewal-workflow.md) — How multi-tier renewal grouping works
- [docs/integrations-index/ac-callout-memberships.md](ac-callout-memberships.md) — Deep-dive on how the plugin produces the callout data array
