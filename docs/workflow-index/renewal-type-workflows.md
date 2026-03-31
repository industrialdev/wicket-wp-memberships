# Renewal Type Workflows

How **Renewal Type** settings determine what happens when a member renews, and how these settings appear and can be overridden on membership records.

---

## Overview

The **Renewal Type** controls the renewal path a membership follows. It can be set at the **Tier level** (Membership Tier configuration) or **overridden at the membership record level** (Individual or Organization Member Management page).

### Two Levels of Control

1. **Tier Renewal Type** — Default renewal path for all members in that tier
2. **Membership Record Renewal Type** — Override the tier default for an individual membership

---

## Renewal Type Options

There are four primary renewal types, plus an "Inherited" option:

1. **Inherited from Tier** — Uses the renewal type set on the Membership Tier
2. **Current Tier** — Member renews into the same tier
3. **Sequential Logic** — Member is moved to a different tier on renewal
4. **Renewal Form Flow** — Member completes renewal via a specific webpage form
5. **Subscription Renewal** — Renewal managed automatically by a WooCommerce subscription

---

## Current Tier Renewal

The most common renewal type. Members renew back into their existing tier without changing.

### How It Works

When a member renews:
1. Their membership dates reset to the next cycle (calendar season or anniversary period)
2. They stay in the same tier
3. No additional decisions or form steps required

### Configuration at Tier Level

**Membership Tier Page:**
- Renewal Type: "Current Tier"
- No additional fields

### Membership Record Display

**On Member Management Page:**

```
Renewal Type: Inherited from Tier
↓ (with note showing)
Inherited Renewal Type: Current Tier
```

Or if overridden:

```
Renewal Type: Current Tier
```

### When to Use

- Standard memberships with no progression
- Flat-fee or simple tier structures
- Members who renew at the same level indefinitely

### Example Scenario

**Configuration:**
- Tier: "Annual Supporter"
- Renewal Type: Current Tier
- Cycle: Calendar (Jan 1 – Dec 31)

**Member:**
- Starts: Jan 1, 2025
- Ends: Dec 31, 2025
- Renews on Dec 15, 2025
- **Result:** New membership starts Jan 1, 2026; ends Dec 31, 2026; same tier

---

## Sequential Logic Renewal

Members are automatically moved to a different tier when they renew, enabling progression paths.

### How It Works

When a member in a tier with Sequential Logic renews:
1. Their membership in the current tier expires
2. New membership is created in the **Sequential Tier**
3. Renewal is a one-way upgrade/change to the next tier

### Configuration at Tier Level

**Membership Tier Page:**
- Renewal Type: "Sequential Logic"
- Sequential Tier **(required):** Dropdown selecting which tier they move to

### Membership Record Display

**On Member Management Page:**

```
Renewal Type: Sequential Logic
Sequential Tier: [Selected Tier Name | ID: 123]
```

If inherited from tier:

```
Renewal Type: Inherited from Tier
↓ (with note)
Inherited Renewal Type: Sequential Logic
Sequential Tier: [Auto-set from tier]
```

### Enabling Manual Override

On a membership record, you can override the sequential tier:

1. Change Renewal Type to "Sequential Logic" (if not already)
2. Select a different Sequential Tier than what the tier specifies
3. When member renews, they go to your selected tier instead

### When to Use

- **Tiered progression:** Bronze → Silver → Gold
- **Membership levels:** Student → Alumnus → Benefactor
- **Business tiers:** Startup → Scale-up → Enterprise
- **Upsell paths:** Basic → Pro → Enterprise

### Example Scenario 1: Straightforward Progression

**Configuration:**
- Tier 1: "Contributor" → Renews to → "Sustainer"
- Tier 2: "Sustainer" → Renews to → "Advocate"
- Tier 3: "Advocate" → Renews to → "Advocate" (stays)

**Member Path:**
- Year 1: Starts in "Contributor"
- Year 2: Renews → automatically moves to "Sustainer"
- Year 3: Renews → automatically moves to "Advocate"
- Year 4+: Renews → stays in "Advocate"

---

### Example Scenario 2: Manual Override

**Tier Configuration:**
- Tier: "Mid-Level"
- Sequential Logic: Normally moves to "Senior Level"

**Your Override (on membership record):**
- Change Sequential Tier to: "Expert Level"

**Result:** When this member renews, they skip "Senior Level" and move directly to "Expert Level."

---

## Renewal Form Flow

Members complete renewal through a webpage form instead of automatic renewal.

### How It Works

When a member in a tier with Form Flow renews:
1. Member clicks "Renew" in their Account Centre
2. They are directed to a specific WordPress page
3. That page contains a renewal form (usually Gravity Forms or similar)
4. Member completes the form
5. Renewal is processed based on form submission

### Configuration at Tier Level

**Membership Tier Page:**
- Renewal Type: "Renewal Form Flow"
- Form Page **(required):** Dropdown selecting which WordPress page contains the form

### Membership Record Display

**On Member Management Page:**

```
Renewal Type: Form Flow
Form Page: [Page Name | ID: 456]
```

If inherited:

```
Renewal Type: Inherited from Tier
↓ (with note)
Inherited Renewal Type: Form Flow
Form Page: [Auto-set from tier]
```

### Enabling Manual Override

On a membership record, you can override the form page:

1. Change Renewal Type to "Form Flow" (if not already)
2. Select a different Form Page
3. When member clicks renew, they go to your selected page instead

### Why Use Form Flow

- **Complex renewal logic:** Collect additional information, upsell options, or conditional pricing
- **Approval workflow:** Form submission triggers approval process (e.g., org admin approves employee renewal)
- **Custom messaging:** Present context-specific renewal information before form
- **Conditional offers:** Show discounts based on tenure, membership history, or other factors
- **Organization renewals:** Collect seat counts, billing changes, or user assignment updates

### Example Scenario

**Tier Configuration:**
- Tier: "Organization"
- Renewal Type: Form Flow
- Form Page: "Organization Renewal Form"

**Form Contains:**
- Organization name (pre-filled)
- Number of employees/seats (confirmation or update)
- Primary contact email (update if changed)
- Billing address (confirmation or update)
- Special requests or comments
- Seat assignments (drag-and-drop interface)

**Member Experience:**
- Member logs in to Account Centre
- Sees "Time to Renew" callout
- Clicks renew button
- Directed to the renewal form page
- Completes the form (and assigns new seats)
- Form submission creates renewal order and updates membership

---

## Subscription Renewal

Membership is renewed automatically through a **WooCommerce subscription**, with billing handled by subscription product settings.

### How It Works

When a tier uses Subscription Renewal:
1. The member's membership is tied to a recurring WooCommerce subscription
2. Renewal happens automatically on the subscription's billing date
3. No manual form or tier change required
4. Billing and renewal are synchronized

### Configuration at Tier Level

**Membership Tier Page:**
- Renewal Type: "Subscription"
- No additional tier-level fields (subscription is configured on the Granted Via product)

### Membership Record Display

**On Member Management Page:**

```
Renewal Type: Subscription Renewal
```

**Billing Section (if subscription exists):**
```
Subscription: #12345 (link)
Next Payment Date: 2026-01-15
```

The membership record does not have a "Sequential Tier" or "Form Page" field when subscription renewal is active.

### When to Use

- **Recurring billing:** Automatic monthly/annual charges
- **Continuous memberships:** Members want "set it and forget it" experience
- **Premium tiers:** Auto-renew with billing convenience
- **Retention:** Reduced churn from failed renewals

### Example Scenario

**Tier Configuration:**
- Tier: "Premium Monthly"
- Renewal Type: Subscription
- Granted Via: "Premium Monthly ($19.99/month)" WooCommerce product (subscription type)

**Member Experience:**
- Member purchases "Premium Monthly" product
- WooCommerce creates a recurring subscription
- Every 30 days (or as configured), subscription auto-renews
- Member is charged $19.99
- Membership record's dates are automatically updated
- No renewal callout appears (automatic)
- Next Payment Date shows in Billing Info

---

## Inherited from Tier

A special option that delegates the renewal type to whatever is set on the Membership Tier.

### How It Works

When a membership record has Renewal Type set to "Inherited from Tier":

1. The actual renewal path is determined by the tier's renewal type
2. If tier has Sequential Logic, member uses sequential logic
3. If tier has Form Flow, member uses form flow
4. Membership record shows as "inherited" with the inherited type noted below

### Membership Record Display

```
Renewal Type: Inherited from Tier
↓
Inherited Renewal Type: [Current Tier | Sequential Logic | Form Flow | Subscription]
```

The inherited type is read-only and shows what the tier specifies.

### When to Use

- **Consistency:** Keep all members in a tier following the same renewal path
- **Default behavior:** Don't override tier settings unless necessary
- **Simplicity:** Easier to manage if tier-level policy is sufficient

### When to Override

Change from "Inherited" to a specific type when:
- A member needs a different renewal path than their tier
- Business circumstances change for a specific member
- Special contracts or negotiated terms apply
- Testing or troubleshooting tier settings

---

## Choosing Renewal Types for Your Tiers

|  Renewal Type  | Best For | Complexity | Member Effort |
|---|---|---|---|
| **Current Tier** | Flat memberships, no progression | Low | Minimal (1-click) |
| **Sequential Logic** | Tiered progression (Bronze→Silver→Gold) | Low | Minimal (1-click) |
| **Form Flow** | Complex renewals, approvals, custom logic | High | Medium (fill form) |
| **Subscription** | Recurring billing, high retention | Medium | None (automatic) |

---

## Example Tier Structure with Mixed Renewal Types

**Configuration Setup:**

```
Tier: Bronze Supporter
  Renewal Type: Current Tier
  (Members renew at Bronze level indefinitely)

Tier: Silver Supporter
  Renewal Type: Sequential Logic → Gold Supporter
  (Members who've been Silver 2 years now upgrade to Gold)

Tier: Gold Supporter
  Renewal Type: Current Tier
  (Members stay at Gold)

Tier: Enterprise
  Renewal Type: Form Flow → Enterprise Renewal Form
  (Complex org renewals via form with custom fields)

Tier: Premium Monthly
  Renewal Type: Subscription
  (Automatic billing monthly)
```

**Member Journeys:**

- Bronze supporter: Renews to Bronze year after year
- Silver supporter (renewing after 2 years): Moves to Gold, stays there
- Enterprise: Completes annual renewal form with seat/billing updates
- Premium subscriber: Renews automatically each month

---

## Overriding Renewal Type on Membership Records

When to override:

1. **Special negotiation:** A member negotiated a form-flow renewal instead of automatic subscription
2. **Troubleshooting:** Testing a different renewal workflow
3. **Exception handling:** This member has unique business circumstances
4. **Tier migration:** Member moving to different tier needs custom pathway

### How to Override

1. Open membership record
2. Change Renewal Type dropdown from "Inherited from Tier" to desired type
3. Additional fields appear (Sequential Tier, Form Page) if applicable
4. Save
5. When member renews, your override takes effect

---

## Interaction with Account Centre Callouts

The renewal type affects what the member sees in their Account Centre:

| Renewal Type | Account Centre Shows |
|---|---|
| Current Tier | Action button: "Renew Membership" |
| Sequential Logic | Action button: "Renew and Upgrade to [Next Tier]" |
| Form Flow | Action button: "Complete Renewal Form" (links to form page) |
| Subscription | Usually no action needed; may show "manage billing" |

The **Renewal Window - Callout Configuration** message displays regardless of renewal type, but the button behavior changes based on the renewal type.
