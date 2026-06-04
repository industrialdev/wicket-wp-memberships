---
title: MembershipDatesSection
---

# MembershipDatesSection

**Source:** `frontend/src/shared/components/MembershipDatesSection.js`

Presentational three-field date picker row for Start Date, End Date, and Expiration Date. Fully controlled — the parent owns all `Date` state and receives changes via individual callbacks.

## Exported helpers

In addition to the component, this module exports two utility functions used by `MembershipDetailsForm`:

- `isoToPickerDate(isoString)` — converts a stored ISO string to a `Date` object interpreted in the MDP timezone (from `PLUGIN_SETTINGS.WICKET_MSHIP_MDP_TIMEZONE`).
- `pickerDateToIso(dateValue, field)` — converts a `Date` back to a UTC ISO string. End (`membership_ends_at`) and expiry (`membership_expires_at`) fields are stored at end-of-day; start is at start-of-day.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `startsAt` | `Date \| null` | No | Controlled start date value. Defaults to `null`. |
| `endsAt` | `Date \| null` | No | Controlled end date value. Defaults to `null`. |
| `expiresAt` | `Date \| null` | No | Controlled expiry date value. Defaults to `null`. |
| `disabled` | `boolean` | No | Disables all three date pickers. Defaults to `false`. |
| `onStartsAtChange` | `Function` | Yes | Called with a `Date` when the start date changes. |
| `onEndsAtChange` | `Function` | Yes | Called with a `Date` when the end date changes. |
| `onExpiresAtChange` | `Function` | Yes | Called with a `Date` when the expiry date changes. |

## When to use

This component is used directly by `MembershipDetailsForm`. Use it standalone only if you need date-picker fields outside of that form's context. The `isoToPickerDate` / `pickerDateToIso` helpers must still be used to convert between the ISO strings from the API and the `Date` objects this component expects.
