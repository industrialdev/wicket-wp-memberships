---
title: MembershipRecordsSection
---

# MembershipRecordsSection

**Source:** `frontend/src/shared/components/MembershipRecordsSection.js`

Shared, data-agnostic table component for displaying a list of membership records in a bordered panel. Every row is independently expandable. Column structure is fully caller-defined, making the component reusable across individual, org, and bundle membership pages without forking.

## What it does

Renders a white bordered panel with a "Membership Records" heading, followed by a WordPress `widefat` table. The table header is built from the `columns` prop. Each data row is rendered by the internal `MembershipRecordRow` sub-component, which manages its own open/closed expand state. When no records exist, a "No membership records yet." placeholder cell spans the full column width.

While `isLoading` is `true`, the component renders `AdminLoadingSkeleton` with the `membershipTable` variant instead of the table.

## Expand / collapse behaviour

Each row has a toggle button in the rightmost cell — a `plus-alt2` icon when collapsed and a `minus` icon when expanded. Clicking the button toggles that row's local `isExpanded` state.

When expanded, a second `<tr class="membership_details">` is inserted immediately after the data row. It spans all columns (including the toggle column) and renders the output of `renderExpandedContent(record)`. The expanded row is hidden via `display: none` when collapsed; `renderExpandedContent` is only called while the row is in the expanded state.

Each row's expand state is independent — expanding one row does not collapse others.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `columns` | `Array<ColumnDefinition>` | No | Column definitions used to build both the `<thead>` and each data row. Defaults to `[]`. |
| `records` | `Array<object>` | No | Array of record objects passed as `record` to each column's `render` function and to `renderExpandedContent`. Each object must have an `ID` property used as the React key. Defaults to `[]`. |
| `isLoading` | `boolean` | No | When `true`, renders a loading skeleton instead of the table. Defaults to `false`. |
| `renderExpandedContent` | `Function` | No | Called with the `record` object when a row is expanded. Must return a `ReactNode`. Omit if no expanded detail panel is needed. |

### ColumnDefinition shape

| Field | Type | Description |
|---|---|---|
| `label` | `string` | Column header text rendered in `<th>`. |
| `render` | `(record: object) => ReactNode` | Returns the cell content for this column. Receives the full `record` object. |

## Column schema note

The component itself defines no column schema. Callers supply all column definitions. Common patterns used across pages include columns for membership post ID, tier name, status badge, start/end dates, and subscription ID. Adapter components in each page's `components/` folder handle any page-specific data mapping before passing records and columns to this component.

:::details Example
```jsx
const columns = [
  {
    label: __('Tier', 'wicket-memberships'),
    render: (record) => record.tier_name,
  },
  {
    label: __('Status', 'wicket-memberships'),
    render: (record) => <StatusBadge status={record.status} />,
  },
  {
    label: __('Start Date', 'wicket-memberships'),
    render: (record) => formatDateWithTooltip(record.membership_starts_at),
  },
];

<MembershipRecordsSection
  columns={columns}
  records={membershipRecords}
  isLoading={isLoading}
  renderExpandedContent={(record) => (
    <MembershipRecordDetail record={record} />
  )}
/>
```
:::

::: tip
Each `record` object must include an `ID` field — this is used as the React `key` on each row. If your API response uses a different identifier field, map it to `ID` before passing the array in.
:::

::: warning
`renderExpandedContent` is only called after the user expands a row. Avoid fetching data inside it on every call — fetch eagerly and pass the data in via the `record` object, or use the expansion event as a trigger to lazily load detail data with a local state flag.
:::
