# Troubleshooting: Organization Membership Renewing Without Required Seats

## Question

**My organization membership is renewing without the required number of seats available.**

---

## Answer

This issue typically stems from a mismatch between your **Seat Settings configuration** and what's being purchased on renewal. Here are the key areas to check:

### 1. Check Your Tier's Seat Settings Configuration

Navigate to your **Organization Membership Tier** page and verify:

- **Seat Settings Type:** Is it set to "Per Seat" or "Per Range of Seats"?
- **Products Configured:** 
  - If using **Per Seat:** Confirm the product granted actually grants the required number of seats
  - If using **Per Range of Seats:** Verify the product configuration includes ranges that cover your organization's seat count

**Likely Root Cause:** The renewal order product is configured to grant fewer seats than the organization previously had (e.g., product only grants 5 seats but organization needs 10).

---

### 2. Check the Renewal Order Creation

When the organization renews, check what's actually being added to the renewal order:

- **Use "Create Renewal Order"** on the membership record to manually test the renewal
- **Review the order** to see which product(s) are being added
- **Verify the product's seat grant value** in your tier configuration

**If seats are missing:** The product linked to your seat range doesn't grant the appropriate number of seats.

---

### 3. Check "Automatically Grant Owner Seat"

On the Organization Tier page:

- **If enabled:** The owner gets 1 automatic seat, but there may be a shortfall if your organization needs more
- **Example:** Organization has 10 seats, but renewal product only grants 9 + automatic owner seat = 10 total (this works)
- **Example:** Organization has 10 seats, renewal product grants 5 + automatic owner seat = 6 total (shortfall of 4 seats)

**Action:** If this is the issue, either:
- Increase the renewal product's seat grant value, OR
- Disable auto-grant owner if it's not aligned with your seat model

---

### 4. Check Renewal Type Configuration

Your tier's **Renewal Type** affects what happens during renewal:

- **Current Tier:** Organization should renew to the same tier (same seat products)
- **Sequential Logic:** Organization moves to a *different* tier—verify that tier's seat configuration also grants sufficient seats
- **Form Flow:** Check if the renewal form allows seat adjustments or if it auto-selects a product with insufficient seats

**If using Sequential Logic:** The next tier may have different seat products configured. Review that tier's settings.

---

### 5. Verify Organization's Current Membership Seats

On the **Organization Member Management** page, check:

```
Total Seats:    [X]
Assigned Seats: [Y]
Unassigned:     [X - Y]
```

Compare this to what the renewal product is configured to grant. The renewal should maintain or increase seats, not decrease them.

---

## Quick Diagnostic Checklist

- [ ] What is the organization's current **Total Seats**? (shown on member management page)
- [ ] What **Renewal Type** is configured on the tier? (Current Tier, Sequential, Form Flow, or Subscription?)
- [ ] If Sequential Logic: What tier are they renewing *into*, and what are its seat products?
- [ ] How many seats does the **renewal product grant**? (configured in tier seat settings)
- [ ] Is **"Automatically Grant Owner Seat"** enabled? (adds +1 to the granted seats)
- [ ] Are you using **Per Seat** or **Per Range of Seats**? (affects product configuration)

---

## Most Common Solutions

1. **Update the renewal product** to grant the correct number of seats
2. **If using Sequential Logic:** Verify the next tier's products also grant sufficient seats
3. **If using Per Range of Seats:** Ensure the range includes products for your organization's seat count
4. **Review Form Flow submissions** if using that renewal type—confirm the correct product is being selected

---

## Related Documentation

- [Organization Member Management](../options-index/organization-member-management.md) — Understanding seat display and management
- [Membership Tier Options](../options-index/membership-tier.md#organization-tier-options) — Seat Settings configuration details
- [Renewal Type Workflows](../workflow-index/renewal-type-workflows.md) — How different renewal types affect seat updates
