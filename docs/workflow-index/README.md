# Membership Configuration Workflows — Overview

This section explains how the configuration options set in Phase 1 (Membership Configuration and Membership Tier settings) directly impact the membership records you see and manage in Phase 2 (Individual and Organization Member Management pages).

---

## How Configuration Affects Membership Records

When you create a **Membership Configuration**, you establish rules for an entire category of memberships. When you later view individual membership records, those configuration rules determine:

1. **When the membership is active** (Start, End, Expiration dates)
2. **When members can renew** (Renewal Window)
3. **How long they have after expiration** (Grace Period)
4. **What happens when they renew** (Renewal Type options)
5. **How multiple tiers interact** (Multi-Tier Renewal)

---

## Key Relationships

### Configuration → Tier → Membership Record

```
Configuration (sets the rules)
    ↓
Tier (uses the configuration)
    ↓
Membership Record (displays the results)
```

The membership record is the output. It reflects the choices made in the configuration and tier setup.

---

## Document Overview

The following documents explore specific areas where configuration choices produce observable outcomes in membership records:

### [Member Lifecycle & Dates](member-lifecycle-dates.md)
How different **cycle types** (Calendar vs Anniversary) and **date alignment** settings determine the Start, End, and Expiration dates shown on membership records.

**Applies to:** Individual and Organization Member Management pages  
**Key Configuration Options:** Cycle, Seasons (Calendar), Period & Align End Dates (Anniversary)

---

### [Renewal Window Workflow](renewal-window-workflow.md)
How the **Renewal Window** setting enables members to renew before their membership expires, and how this interacts with calendar and grace period settings.

**Applies to:** Individual Member Management → Renewal management  
**Key Configuration Options:** Renewal Window (Days), Grace Period Window

---

### [Grace Period & Expiration Workflow](grace-period-workflow.md)
How the **Grace Period Window** extends the membership timeline beyond the End Date, and what Expiration Date means in relation to the grace period.

**Applies to:** Individual and Organization Member Management → Date fields  
**Key Configuration Options:** Grace Period Window (Days), Grace Period Product

---

### [Renewal Type Workflows](renewal-type-workflows.md)
How **Renewal Type** settings on the tier (and overrides on the membership record) determine which renewal pathways appear and which fields are editable on the membership management page.

**Applies to:** Individual and Organization Member Management → Renewal Type field  
**Key Configuration Options:** Renewal Type (Tier level), Multi-Tier Renewal

---

### [Multi-Tier Renewal Workflow](multi-tier-renewal-workflow.md)
How enabling **Multi-Tier Renewal** on a configuration causes multiple tiers' renewal callouts to combine into a single prompt in the Account Centre, affecting user-facing renewal experience.

**Applies to:** Account Centre (member-facing) and Callout Configuration  
**Key Configuration Options:** Multi-Tier Renewal flag on Membership Config

---

## Common Configuration Scenarios

Below are some typical configuration combinations and what you would observe on the membership management page:

### Scenario 1: Simple Calendar Year Membership
- **Cycle:** Calendar
- **Seasons:** Fall 2025 (Sept 1 – Dec 31) + Winter 2026 (Jan 1 – Aug 31)
- **Renewal Window:** 30 days
- **Grace Period:** 7 days

**Membership Record Shows:**
- Start Date: First day of the season
- End Date: Last day of the season
- Exp. Date: 7 days after End Date
- Member can renew starting 30 days before End Date

---

### Scenario 2: Anniversary Membership with Aligned End Dates
- **Cycle:** Anniversary
- **Period:** 1 Year (from sign-up date)
- **Align End Dates:** Yes, to Last Day of Month
- **Renewal Window:** 45 days
- **Grace Period:** 14 days

**Membership Record Shows:**
- Start Date: Date member joined (varies per person)
- End Date: Adjusted to align to last day of member's birth month every year
- Exp. Date: 14 days after End Date
- Member can renew starting 45 days before aligned End Date

---

### Scenario 3: Base Tier with Sequential Renewal
- **Cycle:** Calendar
- **Renewal Type (Tier):** Sequential Logic → moves to "Premium Tier"
- **Multi-Tier Renewal:** Disabled

**Membership Record Shows:**
- Renewal Type field displays: "Inherited from Tier" or can override
- If inherited: members are moved to Premium Tier upon renewal
- Renewal Form Flow and Subscription options remain available as overrides

---

### Scenario 4: Multi-Tier Membership Structure
- **Config A:** Multi-Tier Renewal enabled
- **Config B:** Multi-Tier Renewal enabled
- **Tier 1:** Uses Config ADocAgentPrompts.md
- **Tier 2:** Uses Config B
- **Member:** Belongs to both Tier 1 and Tier 2

**Member Experience:**
- Renewal prompts for both tiers combine into one callout in Account Centre
- Callout text from both configurations is shown in one unified prompt

---

## Reading the Workflow Documents

Each workflow document describes:

1. **The Configuration Option** — What setting you're controlling
2. **How It Works** — The logic behind the option
3. **What You See in Membership Records** — The observable result
4. **Examples** — Concrete scenarios showing different choices and outcomes
5. **Common Pitfalls** — Mistakes to avoid and how to recognize them

---

## When to Revisit This Guide

Refer to these workflow documents when:

- You're setting up a new Membership Configuration and want to understand what membership records will look like
- You're editing a membership record and wondering why certain fields are available or disabled
- A member reports confusion about their renewal date, grace period, or membership status
- You need to explain to colleagues how a specific configuration setting affects the member experience
