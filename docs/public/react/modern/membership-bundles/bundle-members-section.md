---
title: BundleMembersSection
---

# BundleMembersSection

Displays a membership bundle's member count broken down by tier. Fetches from `GET /wicket_member/v1/bundle/{postId}/members_by_tier` on mount and whenever `refreshKey` changes. Renders without its own bordered wrapper — intended to be placed inside an existing container such as `MembershipDetailsForm`'s `BorderedBox`.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `pageData` | `object\|null` | Yes | Data returned by `fetchBundleEditPageInfo`. Used to derive the bundle post ID when `bundlePostId` is not provided. |
| `bundlePostId` | `number\|null` | No | Explicit bundle post ID to query. When provided, overrides `pageData.ID`. Used by expanded record rows so each row fetches members for its own specific bundle post, not the primary bundle. |
| `isLoading` | `boolean` | No | When `true`, renders an `AdminLoadingSkeleton` in place of the table. |
| `individualMembersUrl` | `string` | Yes | Base URL of the individual members list admin page. Used to build filtered links. |
| `refreshKey` | `number` | No | Incrementing this value causes the section to re-fetch member data. Used after a member is added. |

## Members-by-Tier Table

The table has three columns: **Membership Tier**, **Member Count**, and a per-row **View members** link. Each "View members" link appends `filter_bundle_id` and `filter_tier_uuid` query parameters to `individualMembersUrl`, filtering the individual members list to show only members of that tier within this bundle.

A total member count is shown in the section header alongside the "Bundle Members" heading.

A **Manage Bundle Members** link below the table links to `individualMembersUrl` filtered by `filter_bundle_id` only, showing all members of the bundle regardless of tier.

The section renders nothing (no table, no manage link) when no tier data is available or when the fetch fails.

:::details Example

```jsx
<BundleMembersSection
  pageData={bundlePageData}
  bundlePostId={record.ID}
  individualMembersUrl={individualMembersUrl}
  refreshKey={memberRefreshKey}
/>
```

:::
