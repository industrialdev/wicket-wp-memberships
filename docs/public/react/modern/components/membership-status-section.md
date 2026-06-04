---
title: MembershipStatusSection
---

# MembershipStatusSection

**Source:** `frontend/src/shared/components/MembershipStatusSection.js`

Displays the current membership status as a read-only text field with a "Manage Status" button. Clicking the button opens a WicketModal where the admin can select a new status and submit the change.

## What it does

- Renders a disabled `TextControl` with the current status.
- "Manage Status" button is disabled when `postId` is falsy.
- On modal open, calls `fetchStatuses(postId)` and populates a `SelectControl` with the results.
- When `cancelled` or `expired` is selected, shows a destructive confirmation step before enabling the submit button.
- When `onCancelIntercept` is provided and the user selects `"cancelled"`, the modal closes immediately and `onCancelIntercept` is called instead — allowing the bundle cancel flow to take over.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `postId` | `number \| string \| null` | No | WP post ID of the record. Passed to `fetchStatuses` and `updateStatus`. |
| `currentStatus` | `string` | No | Current status label/slug shown in the read-only field. Defaults to `""`. |
| `fetchStatuses` | `Function` | Yes | `(postId) => Promise<statusMap>`. Status map shape: `{ [slug]: { name: string, slug: string } }`. |
| `updateStatus` | `Function` | Yes | `(postId, newStatus) => Promise<{ success?, error? }>`. |
| `onStatusUpdated` | `Function` | No | Called with `(postId, newStatus, responseData)` after a successful status change. |
| `onCancelIntercept` | `Function` | No | When provided and the user selects `"cancelled"`, the modal closes and this callback fires instead of showing the built-in confirmation step. Used to open `CancelMembershipBundleModal`. |

## Confirmation behaviour

Selecting `"cancelled"` or `"expired"` shows a warning notice and a "Confirm Action" button. The submit button remains disabled until the admin clicks "Confirm Action". This two-step guard applies to both statuses.

When `onCancelIntercept` is provided, selecting `"cancelled"` bypasses the built-in confirmation entirely.
