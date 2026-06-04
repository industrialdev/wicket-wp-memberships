---
title: MembershipBillingInfoSection
---

# MembershipBillingInfoSection

**Source:** `frontend/src/shared/components/MembershipBillingInfoSection.js`

Billing summary block rendered inside an expanded membership record row. Displays the subscription ID (optionally linked to the WooCommerce admin edit page) and the next payment date.

Dates are rendered via `formatDateWithTooltip` from `shared/constants.js`, which shows the date in MDP timezone with the full ISO 8601 string (including UTC offset) as a hover tooltip.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `subscriptionId` | `string \| number \| null` | No | WooCommerce subscription ID. Displayed as `#ID`. Defaults to `null`. |
| `subscriptionLink` | `string \| null` | No | Admin edit URL for the subscription. When provided, wraps the ID in an `<a>` tag. Defaults to `null`. |
| `nextPaymentDate` | `string \| null` | No | Next payment date as an ISO string. Rendered via `formatDateWithTooltip`. Defaults to `null`. |

::: warning
Do not pre-format dates before passing them to this component. Pass the raw ISO string from the API so `formatDateWithTooltip` can apply the correct timezone and tooltip.
:::
