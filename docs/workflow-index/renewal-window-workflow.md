# Renewal Window Workflow

How the **Renewal Window** setting determines when members become eligible to renew, and how this interacts with the membership lifecycle.

---

## Overview

The **Renewal Window** is the number of days before a membership ends during which a member is allowed to renew. It opens a "window" of opportunity for renewal action and is calculated backwards from the membership's End Date.

---

## How the Renewal Window Works

### The Timeline

```
Member Joins          Renewal Window Opens          End Date          Expiration Date
     |                       |                         |                    |
     [Start Date]----(days before End)----[30 days before]----[End]----[Exp]
                                              ↑
                                        Can renew NOW
```

### Configuration Setting

On the Membership Configuration page, you set **Renewal Window (Days)** — a number like `30`, `45`, or `90`.

### Calculation

```
Renewal Eligible Date = End Date - Renewal Window (Days)
```

**Example:**
- End Date: December 31, 2025
- Renewal Window: 30 days
- Member can renew starting: December 1, 2025

### In Account Centre

When a member logs into their Account Centre (the member-facing dashboard):

- **Before Renewal Window Opens:** No renewal prompt appears (membership still far from ending)
- **Inside Renewal Window:** A callout appears with the message you configured in "Renewal Window - Callout Configuration"
- **After End Date:** Renewal window is past; urgency increases (grace period may be active)

---

## Interaction with Grace Period

The renewal window and grace period work together on the membership timeline:

```
Renewal Opens    End Date    Grace Period Ends (Expiration)
     |             |                   |
-----[Renewal Window]----[Grace Period]-----
     ← can renew     ← already expired but still active
```

### Renewal Window First, Then Grace Period

If a member doesn't renew during the window:
1. The grace period extends their access after End Date
2. They still see renewal prompts (urgency increases)
3. If they renew during grace period, renewal dates are calculated from the time they renew

---

## Membership Record Impact

When viewing a membership record on the **Individual or Organization Member Management** page:

| Information | What It Shows |
|------------|---------------|
| **End Date** | When the renewal window opens (End Date minus this value) |
| **Expiration Date** | When the grace period ends |
| **Renewal Window Setting** | Not directly visible on record, but affects timing |

You cannot see the exact "renewal eligible date" on the record, but you can calculate it:

```
Renewal Eligible = End Date - Renewal Window Setting
```

---

## Common Renewal Window Configurations

### Short Window (7–14 days)
**Use When:**
- You want urgent renewal action
- Quick turnaround memberships
- Testing or short-term memberships

**Member Experience:**
- Short notice before renewal window opens
- More frequent renewal prompts in Account Centre
- Tighter deadline discipline

**Example:**
- End Date: Dec 31, 2025
- Renewal Window: 7 days
- Renewal opens: Dec 24, 2025
- Member has ~1 week to renew before end of membership

---

### Standard Window (30 days)
**Use When:**
- Typical annual memberships
- Monthly recurring memberships
- Standard business practice

**Member Experience:**
- About a month to handle renewal
- Sufficient time to arrange payment or approval
- Clear advance notice

**Example:**
- End Date: Dec 31, 2025
- Renewal Window: 30 days
- Renewal opens: Dec 1, 2025
- Member has October and November to renew before year-end

---

### Extended Window (60–90 days)
**Use When:**
- Complex organizations with multiple approvers
- Enterprise contracts with budget cycles
- High-touch, relationship-based memberships

**Member Experience:**
- Long advance notice
- Flexible renewal timeline
- Time for budget allocation or committee approval

**Example:**
- End Date: June 30, 2026
- Renewal Window: 90 days
- Renewal opens: April 1, 2026
- Member has 3 months to complete renewal process

---

## Multi-Tier Renewal Impact

If you enable **Multi-Tier Renewal** on the Membership Configuration:

The renewal window setting affects when the combined multi-tier callout appears in the Account Centre.

**Scenario: Member has two tiers from different configs**
- Config A (Multi-Tier: Yes): Renewal Window 45 days
- Config B (Multi-Tier: Yes): Renewal Window 30 days

**In Account Centre:**
- The renewal window that opens first triggers the combined callout
- Both tiers' renewal flows appear in one unified prompt
- Member renews both tiers in one action (instead of two separate actions)

---

## Editing Renewal Window on Membership Records

On the membership management page, you can manually override the renewal dates by editing:

- **Start Date** — Shifts the entire membership period forward/backward
- **End Date** — Changes when the membership period ends (also shifts renewal window)
- **Expiration Date** — Extends or shortens grace period (independent of End Date)

| Action | Effect on Renewal |
|--------|------------------|
| Change End Date to later | Renewal window opens later |
| Change End Date to earlier | Renewal window opens sooner |
| Change Renewal Window in config | Affects all new memberships; existing ones not retroactively changed |

---

## Renewal Window Callout

The **Renewal Window - Callout Configuration** on the Membership Configuration defines what members see when they're inside the renewal window:

- **Callout Header** — e.g., "Your membership renewal is due"
- **Callout Content** — e.g., "Please renew by December 31 to maintain benefits"
- **Button Label** — e.g., "Renew Now"

This callout is customizable per language, so multilingual sites can have localized renewal messages.

---

## Common Issues & Solutions

### Issue: Renewal Window Never Opens

**Possible Causes:**
- End Date is in the past (membership already expired)
- Renewal Window setting is 0 days
- Membership status is Cancelled

**Solution:** Check the End Date and Renewal Window values. An End Date in the past means the renewal window has already passed.

---

### Issue: Members Renew Too Early

**Problem:** Members are renewing months before their membership ends.

**Cause:** Renewal window is too long or End Date is misread.

**Solution:** Review whether your renewal window setting matches your business intent. Consider a shorter window if early renewals are causing accounting issues.

---

### Issue: Members Miss Renewal Deadline

**Problem:** Members don't see renewal prompts until it's too late.

**Cause:** Renewal window is too short for your audience, or callout text is unclear.

**Solution:** Increase renewal window and ensure callout message is clear and compelling. Consider sending email reminders in addition to Account Centre prompts.

---

## Checklist for Renewal Window Setup

- [ ] **Is the renewal window duration realistic for your members?** (too short = confusion; too long = premature renewals)
- [ ] **Have you configured the Renewal Window - Callout Configuration?** (with clear, translated text if multilingual)
- [ ] **Do your membership dates match your fiscal/calendar periods?** (e.g., fiscal year should align with business practices)
- [ ] **Have you tested renewal notifications with sample members?** (to ensure timing is appropriate)
- [ ] **Is your grace period configured if late renewals are acceptable?** (grace period only matters if members sometimes renew late)
