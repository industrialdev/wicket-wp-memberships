---
title: useMembershipBundleBootstrap
---

# useMembershipBundleBootstrap

Loads all data required to populate the membership bundle detail page. Calls `fetchBundleEditPageInfo` on mount and re-runs whenever `bundleGroupUuid` changes. While a renewal batch is in progress, automatically polls the REST endpoint every 10 seconds to pick up progress updates and detect completion.

## Params

| Name | Type | Required | Description |
|---|---|---|---|
| `bundleGroupUuid` | `string` | Yes | The `membership_bundle_group_uuid` for the bundle series to load. |

:::details Returns

```ts
{
  // The loaded page data object, or null while loading / on error.
  pageData: object | null;

  // React state setter — allows callers to patch pageData in place
  // without triggering a full network reload (e.g. after an owner change).
  setPageData: React.Dispatch<React.SetStateAction<object | null>>;

  // Describes the current network state of the initial load.
  requestState: {
    status: "loading" | "success" | "error";
    error: Error | null;
  };

  // Re-runs the full load sequence, resetting requestState to "loading".
  // Wired to the "Retry loading" notice action.
  retryLoad: () => Promise<object | null>;

  // Parsed membership_renewal_processing meta from the current pageData,
  // or null when no renewal batch is active or when the batch has completed.
  // Consumed directly by RenewalProcessingOverlay.
  renewalProcessingMeta: object | null;
}
```

:::

## Renewal Polling Behaviour

The hook inspects `pageData.meta.membership_renewal_processing` after each successful load. A batch is considered **in progress** when that field is present and contains no `completed_at` timestamp. When in progress:

1. A `setTimeout` schedules a **silent refresh** after 10 seconds (`RENEWAL_POLL_INTERVAL_MS = 10000`).
2. The silent refresh calls `fetchBundleEditPageInfo` and updates `pageData` **without** resetting `requestState` to `"loading"`, so the page skeleton does not flash.
3. After each silent refresh, the hook checks `renewalProcessingMeta` again. If the batch is still running, it schedules another poll. If `completed_at` is now present (or the field is absent), polling stops.
4. Any in-flight `setTimeout` is cleared when `retryLoad` is called (full reload) or when the component unmounts (cleanup from `useEffect`).

The `RenewalProcessingOverlay` displays `offset` and `total_members` from `renewalProcessingMeta`, which update automatically with each poll cycle.

:::details Example

```js
const { pageData, setPageData, requestState, retryLoad, renewalProcessingMeta } =
  useMembershipBundleBootstrap({ bundleGroupUuid });
```

:::
