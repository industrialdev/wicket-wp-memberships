# Multi-Tier Renewal Workflow

How enabling **Multi-Tier Renewal** on Membership Configurations affects how members with multiple memberships experience renewal in the Account Centre and on the member management pages.

---

## Overview

**Multi-Tier Renewal** is a setting on the Membership Configuration that affects *group behavior* — when multiple tiers from different configurations are designed to work together, they can combine their renewal prompts into a single unified callout in the Account Centre.

---

## The Problem It Solves

### Without Multi-Tier Renewal

A member might belong to multiple membership tiers:
- Main membership: $100/year
- Add-on credential: $50/year
- Special interest group: $25/year

Without multi-tier renewal, the member sees **three separate renewal prompts** in their Account Centre:

```
"Your Main Membership renews Dec 31. Renew now."         [RENEW]
"Your Credential renews Dec 31. Renew now."               [RENEW]
"Your Interest Group renews Dec 31. Renew now."           [RENEW]
```

**Friction:** Member must click "renew" three times, possibly on three different forms, paying in batches.

### With Multi-Tier Renewal

The same member with multi-tier renewal enabled on all configurations sees **one combined prompt**:

```
"Your memberships renew Dec 31. Renew all now."

Features:
  • Main Membership ($100)
  • Credential ($50)
  • Interest Group ($25)
  
  Total: $175                                             [RENEW ALL]
```

**Benefit:** One click, one checkout process, one payment for all tiers.

---

## How Multi-Tier Renewal Works

### 1. Enable on Configuration

On the **Membership Configuration** edit page:

- Check the box: "Multi-Tier Renewal"
- This setting only appears if the feature is enabled in plugin settings

### 2. Attach Tiers to Configuration

Each **Membership Tier** must specify which Configuration it uses. The tier "inherits" the Multi-Tier Renewal setting from its configuration.

**Configuration 1 (Multi-Tier Renewal: YES)**
  - Tier A ← uses this config
  - Tier B ← uses this config

**Configuration 2 (Multi-Tier Renewal: YES)**
  - Tier C ← uses this config

**Configuration 3 (Multi-Tier Renewal: NO)**
  - Tier D ← uses this config (NO multi-tier)

### 3. Member Behavior in Account Centre

If a member has memberships in **any combination of "multi-tier enabled" tiers**, those tiers appear in a combined callout.

- Tiers from Configuration 1, 2 (both multi-tier enabled) → appears in one combined callout
- Tier D (from non-multi-tier config) → appears in a separate callout or separately

---

## Callout Configuration Impact

If multi-tier renewal is enabled, each Membership Configuration has a **Renewal Window - Callout Configuration** that defines the combined message.

### Single Tier Callout (Multi-Tier OFF)

```
Configuration: Annual Membership (Multi-Tier: NO)

Renewal Window - Callout:
  Header: "Your membership renews in December"
  Content: "Keep your benefits active by renewing today"
  Button: "Renew Now"
```

**Result in Account Centre:**
```
Your membership renews in December
Keep your benefits active by renewing today
                                    [Renew Now]
```

### Multi-Tier Combined Callout (Multi-Tier ON)

```
Configuration A: Main Membership (Multi-Tier: YES)
Configuration B: Add-on Services (Multi-Tier: YES)

Both configs have:
  Header: "Your memberships renew in December"
  Content: "Renew all your memberships to maintain full benefits"
  Button: "Review & Renew"
```

**Result in Account Centre (Member in both Tier A from Config A and Tier B from Config B):**
```
Your memberships renew in December
Renew all your memberships to maintain full benefits

Memberships to Renew:
  □ Main Membership (Annual)
  □ Add-on Services (Monthly)

                                    [Review & Renew]
```

---

## Decision Tree: Should You Enable Multi-Tier Renewal?

### Enable Multi-Tier Renewal If:

- [ ] Members often hold multiple membership types simultaneously
- [ ] You want to encourage bulk renewals (simpler payment process)
- [ ] Your tiers should be conceptually grouped (e.g., "individual memberships" category)
- [ ] Renewal dates align (same season or anniversary date) across tiers
- [ ] You have translated renewal messages that make sense for combined callouts
- [ ] Your checkout process handles multiple products well

### Do NOT Enable Multi-Tier Renewal If:

- [ ] Tiers are independent (members never hold multiple)
- [ ] Renewal dates differ significantly across tiers
- [ ] Renewal pathways differ significantly (one form flow, one subscription, etc.)
- [ ] Callout messaging for each tier needs to be distinct
- [ ] Members should manage each membership independently

---

## Practical Configuration Examples

### Example 1: Non-Profit with Multiple Benefit Levels

```
Configuration: Annual Membership (Multi-Tier: YES)
├── Tier: Individual Supporter ($50)
├── Tier: Family Supporter ($100)
├── Tier: Organizational Supporter ($500)

Result:
  All three tiers appear in one renewal callout
  Member in Family tier renews once for family package
  Upsell to Organization tier presented as upgrade option
```

---

### Example 2: Professional Association with Specialties

```
Configuration A: Core Professional (Multi-Tier: YES)
├── Tier: Architecture Professional ($150)
├── Tier: Engineering Professional ($150)

Configuration B: Specialty Credentials (Multi-Tier: YES)
├── Tier: Green Building Cert ($75)
├── Tier: Safety Director Cert ($75)

Member has: Professional (Arch) + Green Building Cert

Result:
  Callout shows both memberships (multi-tier on both configs)
  Single renewal process for both
  Could upsell Engineering or Safety Director at same time
```

---

### Example 3: Separated Tier Groups (Selective Multi-Tier)

```
Configuration A: Core Membership (Multi-Tier: NO)
├── Tier: Core Annual ($100)

Configuration B: Premium Services (Multi-Tier: YES)
├── Tier: Email Support ($20)
├── Tier: Priority Support ($50)
├── Tier: Concierge ($100)

Member has: Core Annual + Email Support

Result:
  Core Annual renews separately (NO multi-tier configured)
  Email Support appears in multi-tier with other support tiers
  Two separate renewal callouts in Account Centre
  Keeps core membership distinct from premium add-ons
```

---

## Membership Record Display

The **Multi-Tier Renewal** setting doesn't change the Individual or Organization Member Management page appearance. The setting affects the member-facing Account Centre, not the admin interface.

However, when viewing a tier that has multi-tier renewal enabled, you'll see a note:

```
Membership Config: [Config Name] | ID: [ID]
  • Multi-Tier Renewal Enabled
```

---

## Language Support with Multi-Tier Renewal

The **Renewal Window - Callout Configuration** for each language lets you customize the multi-tier message per language.

**Example with multiple languages:**

```
Configuration: Annual Membership (Multi-Tier: YES)

Renewal Window Callout Configuration:
  
  Language: English
    Header: "Your memberships renew in December"
    Content: "Renew all your memberships at once"
    Button: "Renew All"
  
  Language: Spanish
    Header: "Sus membresías se renuevan en diciembre"
    Content: "Renueve todas sus membresías a la vez"
    Button: "Renovar Todo"
  
  Language: French
    Header: "Vos adhésions se renouvellent en décembre"
    Content: "Renouvelez toutes vos adhésions à la fois"
    Button: "Renouveler Tout"
```

Each language sees the localized version of the combined callout.

---

## Interaction with Renewal Types

Multi-Tier Renewal works with all renewal types:

| Renewal Type | Multi-Tier ON | Multi-Tier OFF |
|---|---|---|
| **Current Tier** | Combined callout; renew all to same tiers | Individual callouts per tier |
| **Sequential Logic** | Combined callout; move to next tiers together | Individual tier progressions |
| **Form Flow** | Combined callout; one form for all tiers | Individual forms per tier |
| **Subscription** | Combined callout; auto-renew together | Individual subscriptions |

**Note:** If different tiers have different renewal types, the multi-tier interface typically shows the renewal type of the primary/first tier, with others as variants.

---

## Editing Membership Records with Multi-Tier Renewal

When editing an individual membership record (on Member Management page), multi-tier renewal doesn't directly affect the fields you see. However:

| Scenario | What You See |
|---|---|
| Membership is part of multi-tier group | Renewal Fields appear normally; multi-tier affects Account Centre, not admin interface |
| Editing renewal dates | All related memberships' renewal dates update independently |
| Changing membership status | Only this membership's status changes; others remain unchanged |

The multi-tier grouping is logic only; each membership record is still edited individually.

---

## Common Issues & Troubleshooting

### Issue: Renewal Callout Not Combining

**Problem:** Members see separate renewal callouts instead of one combined callout.

**Possible Causes:**
- Multi-Tier Renewal is only enabled on ONE configuration, not both
- Member doesn't have memberships from both configs
- Renewal windows are misaligned (one opens now, other opens next month)
- Feature is not enabled in plugin settings

**Solution:** 
- Check both configurations have Multi-Tier Renewal enabled
- Verify member has memberships from both configurations
- Check renewal dates align or are close
- Confirm plugin settings enable the feature

---

### Issue: Mixed Renewal Types Causing Confusion

**Problem:** Member has one form-flow tier and one subscription tier set to multi-tier renewal; unclear what happens.

**Possible Outcome:**
- Account Centre shows combined callout
- Clicking button may open form (if form-flow is primary)
- Subscription component may auto-renew separately

**Solution:**
- Consider whether those tiers should be in same configuration
- If they must be together, clarify in callout message what to expect
- Test the renewal flow end-to-end before going live

---

### Issue: Renewal Callout Text Doesn't Match Tier Types

**Problem:** Callout says "Renew your memberships" but one is subscription (no renewal action needed).

**Solution:**
- Write callout text to be inclusive: "Update your memberships" instead of "Renew"
- Or separate subscription tiers into different configuration (not multi-tier)
- Or test how subscription renews in combo and document for members

---

## Checklist for Multi-Tier Renewal Setup

- [ ] **Do you have multiple tiers that members might hold simultaneously?**
- [ ] **Have you enabled Multi-Tier Renewal on at least 2 configurations?**
- [ ] **Have you assigned tiers to those configurations?**
- [ ] **Have you configured the Renewal Window Callout for the multi-tier message?**
- [ ] **Have you tested the renewal experience as a test member?**
- [ ] **If multilingual, have you added callout text for all languages?**
- [ ] **Have you documented the multi-tier behavior for your team?**
- [ ] **Are renewal dates sufficiently aligned across multi-tier tiers?** (within weeks ideally)
