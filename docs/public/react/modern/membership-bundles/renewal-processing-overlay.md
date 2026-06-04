---
title: RenewalProcessingOverlay
---

# RenewalProcessingOverlay

Full-area blocking overlay displayed while a bundle renewal batch is in progress. Covers the bundle detail content area and prevents all user interaction with the form beneath it. Automatically unmounts when `processingMeta` becomes `null`.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `processingMeta` | `object\|null` | Yes | Parsed `membership_renewal_processing` meta object, or `null`. Provided by `useMembershipBundleBootstrap`. When `null`, the component renders nothing. |

## When It Renders

The overlay renders when `processingMeta` is a non-null object — meaning the `membership_renewal_processing` post meta is present on the bundle and does **not** yet contain a `completed_at` timestamp. It unmounts when the bootstrap hook's polling detects that `completed_at` has been set, clearing `renewalProcessingMeta` to `null`.

## What It Blocks

The overlay is rendered inside the `ContentArea` wrapper in `MembershipBundlePage`, which is `position: relative`. The overlay itself is `position: absolute; inset: 0; z-index: 100; pointer-events: all`, so it covers the entire content area and intercepts all pointer events, preventing any interaction with `MembershipBundleForm` and its child sections until the renewal batch completes.

## Display

The overlay shows:

- A spinning `dashicons-update-alt` icon.
- A "Renewal In Progress" heading.
- A subtext prompt asking the admin to wait.
- A progress counter in the form `{offset}/{total_members} records processed.` sourced from `processingMeta.offset` and `processingMeta.total_members`.
- A started-at timestamp (`processingMeta.started_at`) formatted in the MDP timezone using `moment-timezone`.

:::details Example

```jsx
<RenewalProcessingOverlay processingMeta={renewalProcessingMeta} />
```

:::
