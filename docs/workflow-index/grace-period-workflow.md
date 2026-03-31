# Grace Period & Expiration Workflow

How the **Grace Period Window** extends membership access after the official End Date, and how the Expiration Date provides a deadline for members to renew.

---

## Overview

The **Grace Period** is a buffer period of time after a membership officially ends during which the member:

- Still has access to membership benefits
- Can renew their membership (even though it's technically expired)
- Sees urgent renewal prompts in their Account Centre

At the end of the grace period, the membership reaches its **Expiration Date** and the member loses access.

---

## The Expiration Timeline

```
Membership Active       End Date       Grace Period       Expiration Date
     |                    |              (inactive)            |
[Active]-----[Renew Here]--[EXPIRED]-----[Still Has Access]-----[LOSE ACCESS]
     |                    |              but not renewed yet    |
  Start Date           5-14 days              |          End Date + Grace Period
                                           Warning Phase
```

---

## Configuration: Grace Period Window (Days)

On the Membership Configuration page, you set **Grace Period Window (Days)** — a number like `0`, `7`, `14`, or `30`.

### Calculation

```
Expiration Date = End Date + Grace Period Window (Days)
```

**Examples:**

| End Date | Grace Period | Expiration Date | Interpretation |
|----------|-------------|-----------------|-----------------|
| Dec 31, 2025 | 0 days | Dec 31, 2025 | No grace period; lose access immediately |
| Dec 31, 2025 | 7 days | Jan 7, 2026 | 7 days of access after membership ends |
| Dec 31, 2025 | 14 days | Jan 14, 2026 | 2 weeks of post-expiration access |
| Dec 31, 2025 | 30 days | Jan 30, 2026 | Full month of grace to renew |

---

## Why Grace Periods Matter

### For Members

- **Forgiveness:** Life happens. Members get sick, travel, or simply forget. Grace period gives them a second chance.
- **Cash flow:** Organizations may need time to get budget approval or collect funds.
- **No service interruption:** If they're even a day late renewing, they don't lose access immediately.

### For Organizations

- **Retention:** Members are less likely to cancel if they don't lose access immediately.
- **Late renewals:** You can collect renewal revenue even if members miss the official End Date.
- **Dispute resolution:** If a member questions their renewal obligation, you have time to discuss.

---

## Grace Period Product

The **Grace Period Product** is an optional WooCommerce product linked to the grace period. When a member renews during their grace period, this product can be automatically added to their renewal order.

### Use Cases

- **Late Fee:** A $10 late renewal fee for members who don't renew by the End Date
- **Expedited Renewal:** A $5 charge for rush processing if renewing in grace period
- **Grace Period Surcharge:** Any product representing "renewal after deadline"

### How It Works

When a member renews during the grace period:

1. The renewal order is created
2. The Grace Period Product is automatically added to the order
3. The order total includes the base renewal cost + the grace period surcharge
4. The member pays more, but their membership is restored

### Example: Grace Period Late Fee Setup

**Configuration:**
- Grace Period Window: 14 days
- Grace Period Product: "Late Renewal Fee" (WooCommerce product priced at $10)

**Member Timeline:**
- End Date: Dec 31, 2025
- Expiration Date: Jan 14, 2026
- Member renews on Jan 10, 2026 (during grace period)
- Renewal order includes: [Membership Renewal] + [Late Renewal Fee ($10)]
- Member pays extra but keeps their membership

---

## Membership Record Impact

When viewing a membership on the **Individual or Organization Member Management** page:

| Field | What It Shows |
|-------|---------------|
| **End Date** | When the membership officially ends |
| **Expiration Date** | When the grace period ends; your final deadline |
| **Grace Period Setting** | Not visible on record, inferred from End vs Expiration date |

### Calculating Grace Period from Record

To see how long the grace period is for a membership:

```
Grace Period Days = Expiration Date - End Date
```

**Example from Record:**
- End Date: 2025-12-31
- Expiration Date: 2026-01-14
- Grace Period Duration: 14 days

---

## Grace Period Scenarios

### Scenario 1: No Grace Period (0 Days)

**Configuration:**
- Grace Period Window: 0 days
- Status: Strict enforcement

**Membership Record:**
```
Start Date:      2025-01-01
End Date:        2025-12-31
Expiration Date: 2025-12-31 (same as End Date)
```

**Member Experience:**
- Membership ends on Dec 31
- Cannot access benefits starting Jan 1
- Must renew before Dec 31 or lose access immediately
- No opportunity to renew late

**Best For:** Highly regulated memberships where access must be strictly enforced (e.g., professional licenses, certifications where expired = non-compliant).

---

### Scenario 2: Short Grace Period (7 Days)

**Configuration:**
- Grace Period Window: 7 days
- Grace Period Product: None (or optional discount)
- Status: Flexible with urgency

**Membership Record:**
```
Start Date:      2025-01-01
End Date:        2025-12-31
Expiration Date: 2026-01-07
```

**Member Experience:**
- Membership ends on Dec 31
- Access continues through Jan 7
- Strong incentive to renew by Dec 31, but not trapped if a few days late
- If renews on Jan 5, they keep access
- If doesn't renew by Jan 7, they lose access

**Best For:** Memberships where you want most renewals by deadline but are flexible for edge cases. Common for annual memberships.

---

### Scenario 3: Standard Grace Period (14 Days) + Late Fee

**Configuration:**
- Grace Period Window: 14 days
- Grace Period Product: $15 Late Renewal Fee
- Status: Encouraging on-time renewal with penalty for late

**Membership Record:**
```
Start Date:      2025-01-01
End Date:        2025-12-31
Expiration Date: 2026-01-14
Grace Period Product: Late Renewal Fee ($15)
```

**Member Experience:**
- Membership ends on Dec 31
- Can access through Jan 14
- Account Centre shows urgent renewal callout starting ~Dec 1
- If renews by Dec 31: standard price
- If renews Jan 1–Jan 14: standard price + $15 late fee
- If doesn't renew by Jan 14: lose access on Jan 15

**Best For:** Organizations balancing member flexibility with the need to discourage late renewals. The late fee incentivizes on-time payment.

---

### Scenario 4: Extended Grace Period (30 Days)

**Configuration:**
- Grace Period Window: 30 days
- Grace Period Product: None
- Status: Very forgiving

**Membership Record:**
```
Start Date:      2025-01-01
End Date:        2025-12-31
Expiration Date: 2026-01-30
```

**Member Experience:**
- Membership ends Dec 31
- Access continues through Jan 30 (full month)
- Very relaxed deadline; most members won't feel urgent until mid-January
- If renews anytime in January: membership restored with no penalty
- If doesn't renew by Jan 30: lose access on Jan 31

**Best For:** Memberships serving busy organizations (non-profits, enterprises) where a month-long flexibility window is necessary to navigate budgets, approvals, and organizational change.

---

## Multi-Year/Multi-Month Memberships with Grace Periods

Grace periods apply at each renewal cycle:

**Example: 2-Year Anniversary Membership with 14-Day Grace**

| Year 1 | Year 2 | Year 3 |
|--------|--------|--------|
| Start: Jan 1, 2025 | Start: Jan 1, 2027 | Start: Jan 1, 2029 |
| End: Dec 31, 2026 | End: Dec 31, 2028 | End: Dec 31, 2030 |
| Exp: Jan 14, 2027 | Exp: Jan 14, 2029 | Exp: Jan 14, 2031 |
| Grace: 14 days | Grace: 14 days | Grace: 14 days |

Each 2-year cycle has its own grace period.

---

## Grace Period and Renewal Window Interaction

These two settings create the full renewal opportunity window:

```
Renewal Opens        End Date        Renewal Deadline (Exp Date)
     |                 |                        |
[--------Renewal Window-------][-Grace--------|
     ↓                 ↓                        ↓
  Can renew now   Too late for discounts   Lost access
```

### Full Timeline Example

**Configuration:**
- Renewal Window: 45 days
- End Date: June 30, 2026
- Grace Period: 14 days

**Member Timeline:**
- May 16: Renewal window opens (June 30 - 45 days)
- Until May 31: Renewal eligible; standard pricing
- June 1–June 30: Renewal eligible; possibly with late fee
- July 1–July 14: Grace period; must renew or lose access
- July 15: Lost access permanently

---

## Edge Cases & Considerations

### What If Grace Period > Renewal Window?

**Example:**
- Renewal Window: 7 days
- Grace Period: 30 days

**Timeline:**
- Renewal opens: 7 days before End Date
- End Date: Membership officially ends
- Grace Period: 30 days of continued access
- Member can renew for 30 days after End Date (much longer than the 7-day renewal window)

**This is intentional:** The renewal window is when you proactively notify members. The grace period is how long they have before losing access. Members can renew anytime from when the window opens until the grace period ends.

---

### Calculating Actual Member Access Duration

If you want to know the total time a member has to complete renewal:

```
Total Renewal Time = Renewal Window + Grace Period
```

**Example:**
- Renewal Window: 45 days (before End Date)
- Grace Period: 14 days (after End Date)
- Total: 45 + 14 = 59 days

A member has almost 2 months from when renewal prompts start appearing until they lose access.

---

## Editing Grace Period on Individual Membership Records

When viewing a membership record, you can manually adjust:

- **End Date** — If you change this, renewal window shifts but grace period days stay constant
- **Expiration Date** — If you change this, grace period in days changes

**Example:**
- Current End Date: Dec 31
- Current Expiration Date: Jan 14 (14-day grace)
- You change Expiration Date to: Jan 7
- New grace period: 7 days instead of 14

---

## Checklist for Grace Period Setup

- [ ] **Is a grace period appropriate for your membership type?** (strict enforcement vs flexibility)
- [ ] **If yes, how many days?** (7, 14, 30 days typical)
- [ ] **Should there be a grace period surcharge/fee?** (incentivizes on-time renewal)
- [ ] **If yes, have you created the WooCommerce product?** (late fee, expedited processing, etc.)
- [ ] **Have you communicated the grace period policy to members?** (so they understand "expired" ≠ "no access")
- [ ] **How will you treat members who don't renew by expiration?** (automatic cancellation, manual intervention?)
