# Account Centre Renewal Callouts — How the Data Is Produced

The Account Centre ("AC") displays renewal prompts — called **callouts** — to members when their membership is approaching expiration, in a grace period, or pending approval. These callouts are not hardcoded; they are built dynamically each time a member views the Account Centre. Understanding how the data is assembled helps explain why a particular callout appears, what text it shows, and what button/link it presents.

---

## Overview

When the Account Centre loads the membership renewal section, it calls the plugin's `get_membership_callouts` endpoint. The plugin looks up all of the current member's active (and related) membership records and examines each one to determine:

- Is this membership currently **in its renewal window**?
- Is it currently **past its end date** but still within the **grace period**?
- Is it **pending approval**?
- Has it **already been renewed** and therefore should be skipped?

Based on those checks, it assembles a data package containing separate lists for each callout type and returns that package to the Account Centre for display.

---

## What the Response Contains

The data package returned has five keys:

| Key | What It Contains |
|-----|-----------------|
| `early_renewal` | One entry per membership currently inside the renewal window |
| `grace_period` | One entry per membership currently past its end date but within the grace (expiration) buffer |
| `pending_approval` | One entry per membership waiting for admin approval before it activates |
| `membership_exists` | A flat list of normalized tier names for all active/delayed memberships |
| `debug` | Membership data included only when `WICKET_MEMBERSHIPS_DEBUG_ACC` is enabled — empty in normal operation |

The Account Centre reads these lists and renders the appropriate callout block for each entry.

---

## Language Selection

Before processing any membership, the plugin determines which language the member is currently viewing the site in. It first checks whether the WPML plugin has set a current language code (a two-letter ISO code like `en` or `fr`). If WPML is not active, it falls back to WordPress's site locale setting (the first two characters of the locale, e.g. `en` from `en_US`).

This ISO code is then used throughout all callout-text lookups. Every callout header, body content, and button label stored on configurations and tiers is keyed by language code, so the callout text automatically matches the member's active language.

---

## Which Memberships Are Included

The plugin queries for all membership records belonging to the current member that have a status of:

- **Active** — normal in-good-standing membership
- **Delayed** — purchased but not yet started
- **Grace** — past end date, still within the expiration buffer
- **Pending** — awaiting admin approval

Expired and cancelled memberships are excluded entirely.

---

## Pending Approval Callouts

A membership record with a **Pending** status is processed first and added to the `pending_approval` list. The content for this callout comes entirely from the **Tier** that the member is waiting on:

- **Header** — set in the Tier's "Approval Callout Header" field for the current language
- **Body content** — set in the Tier's "Approval Callout Content" field for the current language
- **Button label** — set in the Tier's "Approval Callout Button Label" field for the current language
- **Contact email** — set in the Tier's "Approval Email" field, presented to the member so they can follow up

Once a pending-approval membership is processed, no further callout checks are run for it (it cannot also be in early renewal or grace period).

---

## Calculating When the Renewal Window Opens

For all non-pending memberships, the plugin calculates the date on which the member becomes eligible to renew. It does **not** use the value stored on the membership record itself; instead it recalculates fresh from the **Membership Config** assigned to the tier:

1. Take the membership's **End Date**
2. Subtract the **Renewal Window Days** value from the Config
3. The result is the **Renewal Eligible Date** — the first day the member can renew

For example, if a membership ends on December 31 and the Config has a 30-day renewal window, the member becomes eligible to renew on December 1.

This means that changing the Renewal Window Days in the Config immediately affects what callouts appear for all memberships using that Config — no data migration is required.

---

## Early Renewal Callouts

A membership is added to the `early_renewal` list when:

- Today is **on or after** the Renewal Eligible Date, **and**
- Today is **before** the End Date (the membership has not yet ended)

In plain terms: the member is inside the renewal opportunity window but their membership is still active.

The callout content for early renewal comes from the **Membership Config**:

- **Header** — "Renewal Window Callout Header" for the current language
- **Body content** — "Renewal Window Callout Content" for the current language
- **Button label** — "Renewal Window Callout Button Label" for the current language

---

## Grace Period Callouts

A membership is added to the `grace_period` list when:

- Today is **on or after** the End Date (the membership has ended), **and**
- Today is **on or before** the Expiration Date (the grace buffer has not run out)

The callout content for grace period comes from the **Membership Config**:

- **Header** — "Grace Period Callout Header" for the current language
- **Body content** — "Grace Period Callout Content" for the current language
- **Button label** — "Grace Period Callout Button Label" for the current language

Additionally, a `late_fee_product_id` is included in each grace period entry. This is the **Grace Period Product** set in the Config. If configured, the Account Centre uses this product ID to include a late fee in the renewal cart.

---

## What Renewal Link Is Attached to Each Callout

Each callout entry (whether early renewal or grace period) also carries the information the Account Centre needs to present a renewal action. There are three possible paths, determined by what is stored on the membership record:

### Path 1: Subscription Renewal

If `next_tier_subscription_renewal` is set on the membership, the renewal is handled through an existing WooCommerce subscription. The plugin looks for any pending or failed renewal orders on that subscription and generates a payment link. If no payment is owed yet (the end date hasn't passed), it generates an early-renewal URL. The Account Centre receives a `subscription_renewal` block containing a title and a payment/renewal permalink.

### Path 2: Form Page Flow

If a `next_tier_form_page_id` is stored on the membership, the renewal should direct the member to a form on a specific WordPress page (typically a Gravity Forms or similar custom form page). The Account Centre receives a `form_page` block containing the page title, URL, and page ID.

### Path 3: Direct Add-to-Cart (Next Tier products)

If neither of the above is set, the renewal uses the products assigned to the **Next Tier**. The Account Centre receives a `next_tier` block containing the tier's product information. The Account Centre will render an Add to Cart button for each product on the next tier.

---

## Memberships That Are Skipped

Not every membership in the query result produces a callout. Several conditions cause a membership to be skipped entirely:

### Already Renewed
If a membership record includes a `previous_membership_post_id`, it means it is already a renewal of an older membership. The plugin tracks the post IDs of "previous" memberships, and when it encounters a membership that matches one of those IDs, it skips it — the older membership has already been renewed, so no callout is needed for it.

Similarly, the plugin tracks renewal pairings by tier/form-page combinations. If a newer membership is found that pairs with an older one (same renewal target and overlapping end date), the older one is suppressed.

### Auto-Pay Enabled
If the membership's associated WooCommerce subscription has **automatic payment enabled** (the subscription does not require manual renewal) and has an upcoming payment date that hasn't passed, the membership is skipped. Auto-pay members do not need a manual renewal prompt because their renewal is handled automatically.

---

## The `membership_exists` List

The `membership_exists` key contains a simple flat list of tier names. Each active or delayed membership contributes its tier name to this list (normalized to lowercase with spaces, hyphens, and commas removed).

The Account Centre uses this list to know which memberships the member currently holds or has pending. This can be used by the AC block to conditionally show or hide content based on membership type without needing to parse the full callout objects.

---

## The `WICKET_MSHIP_DISABLE_RENEWALS` Flag

If the server environment variable `WICKET_MSHIP_DISABLE_RENEWALS` is set, the function still runs its full lookup but returns **empty arrays** for `early_renewal`, `grace_period`, and `pending_approval`. Only the `membership_exists` and `debug` keys contain data. This flag is intended for maintenance windows or testing scenarios where renewal callouts should be suppressed without modifying configuration.

---

## Summary: Configuration Settings That Directly Shape the Callout Data

| What You Configure | Where You Configure It | What It Affects in the Callout |
|---|---|---|
| Renewal Window Days | Membership Config | The date on which early-renewal callouts begin appearing |
| Renewal Window Callout text (Header / Content / Button) | Membership Config (per language) | The text shown in early-renewal callouts |
| Grace Period Window Days | Membership Config | How long after End Date grace-period callouts continue to appear |
| Grace Period Product | Membership Config | The `late_fee_product_id` passed to the Account Centre for inclusion in the renewal cart |
| Grace Period Callout text (Header / Content / Button) | Membership Config (per language) | The text shown in grace-period callouts |
| Approval Callout text (Header / Content / Button / Email) | Membership Tier (per language) | The text shown in pending-approval callouts |
| Renewal Type (Subscription / Form Page / Add-to-Cart) | Membership Record (set at time of purchase) | Which renewal link path is attached to the callout |
| Next Tier | Membership Record | Which tier/products the renewal callout directs the member toward |

---

## Related Documents

- [docs/options-index/membership-config.md](../options-index/membership-config.md) — Configuration options for renewal window, grace period, and callout text
- [docs/options-index/membership-tier.md](../options-index/membership-tier.md) — Tier options for approval callouts
- [docs/workflow-index/renewal-window-workflow.md](../workflow-index/renewal-window-workflow.md) — How the renewal window timing works end-to-end
- [docs/workflow-index/grace-period-workflow.md](../workflow-index/grace-period-workflow.md) — How the grace period works end-to-end
- [docs/workflow-index/renewal-type-workflows.md](../workflow-index/renewal-type-workflows.md) — Detailed explanation of each renewal path type
