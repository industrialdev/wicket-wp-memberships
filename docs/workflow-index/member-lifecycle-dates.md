# Member Lifecycle & Dates Workflow

How Configuration cycle settings determine the Start, End, and Expiration dates displayed on membership records.

---

## Overview

The **Start Date**, **End Date**, and **Expiration Date** fields on a membership record are calculated based on the cycle type chosen in the Membership Configuration. These three dates form the complete membership timeline:

- **Start Date:** When the membership becomes active
- **End Date:** When the membership period ends
- **Expiration Date:** When the membership fully expires (typically after the grace period)

---

## Calendar Cycle

When you select **Calendar** as the cycle type, membership dates are tied to fixed calendar periods defined by **Seasons**.

### How It Works

1. You define one or more Seasons, each with a Start Date and End Date (e.g., "Fall Season: Sept 1 – Dec 31")
2. When a member is assigned to or renews into this tier, they are placed into an active season
3. The membership's Start and End dates match the selected season's boundaries
4. All members in the same season share identical Start and End dates

### What You See in Membership Records

| Field | Value | Source |
|-------|-------|--------|
| **Start Date** | First day of the active season | Season start date (e.g., September 1) |
| **End Date** | Last day of the active season | Season end date (e.g., December 31) |
| **Expiration Date** | End Date + Grace Period Window | Calculated from End Date |

### Example: Academic Calendar

**Configuration:**
- Cycle: Calendar
- Seasons:
  - Fall: Sept 1 – Dec 31
  - Spring: Jan 1 – May 31
  - Summer: June 1 – Aug 31
- Grace Period: 7 days

**Membership Record (Fall 2025 Member):**
- Start Date: Sept 1, 2025
- End Date: Dec 31, 2025
- Expiration Date: Jan 7, 2026 (Dec 31 + 7 days)

**Same Record (if member switches to Spring):**
- Start Date: Jan 1, 2026
- End Date: May 31, 2026
- Expiration Date: June 7, 2026

---

## Anniversary Cycle

When you select **Anniversary** as the cycle type, membership dates are based on the date the member joined, recurring annually (or at the configured interval).

### How It Works

1. You set a **Membership Period** (e.g., 1, 2, 3 years; or 1, 3, 6 months; or weeks)
2. When a member joins, their Start Date is recorded
3. Their End Date is calculated by adding the period to the Start Date
4. Each membership anniversary, the cycle repeats

### Optionally: Align End Dates

If you enable **Align End Dates**, all members' expiration dates are adjusted to a common day of the month, even though their individual anniversary dates differ.

#### Without Align End Dates

| Field | Value | Example |
|-------|-------|---------|
| **Start Date** | Date member joined | Jan 15, 2025 |
| **End Date** | Start Date + Period | Jan 15, 2026 |
| **Expiration Date** | End Date + Grace Period | Jan 22, 2026 |

Each member has unique dates based on their join date.

**Another Member Joined Later:**
- Start Date: March 22, 2025
- End Date: March 22, 2026
- Expiration Date: March 29, 2026

#### With Align End Dates

If you enable Align End Dates and choose "Last Day of the Month":

| Field | Value | Example |
|-------|-------|---------|
| **Start Date** | Date member joined | Jan 15, 2025 |
| **End Date** | Last day of member's birth month (aligned) | Jan 31, 2026 |
| **Expiration Date** | End Date + Grace Period | Feb 7, 2026 |

**Same Member Joined Later:**
- Start Date: March 22, 2025
- End Date: March 31, 2026 (aligned to month)
- Expiration Date: April 7, 2026

All members aligned to the same day see renewals happen on the same calendar day.

### Alignment Options

You can choose to align end dates to:

- **First Day of Month:** All memberships expire on the 1st (e.g., Feb 1, Mar 1, Apr 1)
- **15th of Month:** All memberships expire on the 15th (e.g., Feb 15, Mar 15, Apr 15)
- **Last Day of Month:** All memberships expire on the last day (e.g., Feb 28, Mar 31, Apr 30)

---

## Anniversary Cycle with Multi-Year or Multi-Month Periods

If you set an Anniversary cycle for 2 years or 6 months, the alignment still applies.

### Example: 2-Year Membership with Monthly Alignment

**Configuration:**
- Cycle: Anniversary
- Period: 2 Years
- Align End Dates: Yes, to the 15th of the month

**Membership Record (Joined March 5, 2025):**
- Start Date: March 5, 2025
- End Date: March 15, 2027 (2 years later, aligned to 15th)
- Expiration Date: March 22, 2027 (+ 7-day grace period)

**Next Year:**
- Start Date: March 15, 2027 (renewal date)
- End Date: March 15, 2029 (2 years from renewal)
- Expiration Date: March 22, 2029

---

## Comparing Calendar vs Anniversary at a Glance

| Aspect | **Calendar** | **Anniversary** |
|--------|-----------|-----------------|
| **Dates Vary?** | No — all members have same dates | Yes — each member has unique dates |
| **Date Source** | Predefined seasons | Member's join/renewal date |
| **Renewal Timing** | Predictable, seasonal | Individual per member |
| **Alignment Option** | N/A | Optional; aligns all members to common date |
| **Best For** | Fixed periods (fiscal year, academic year) | Individual subscriptions, birthdate-based tiers |

---

## When Dates Change

### Calendar Cycle
Membership dates change only when:
- A new season starts and the member moves to a different season
- An administrator manually changes the member's Start/End dates via the membership record

### Anniversary Cycle (Without Alignment)
Membership dates are unique per member and change only when:
- The member renews (anniversary date passes)
- An administrator manually changes the dates

### Anniversary Cycle (With Alignment)
All members within the configuration see End Dates move to the aligned day. On renewal:
- All members renewing this period get the same new End Date (the aligned day + period)
- Makes bulk renewals simpler to manage

---

## Grace Period Impact on Expiration Date

Regardless of cycle type, the **Expiration Date** includes the grace period:

```
Expiration Date = End Date + Grace Period Window
```

If Grace Period Window is 0 days, Expiration Date equals End Date.

### Example with Grace Period

**No Grace Period:**
- End Date: Dec 31, 2025
- Expiration Date: Dec 31, 2025
- Member loses access immediately when membership ends

**With 14-Day Grace Period:**
- End Date: Dec 31, 2025
- Expiration Date: Jan 14, 2026
- Member keeps access for 14 days after membership officially ends
