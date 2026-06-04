---
title: CalendarSeasonsTable
---

# CalendarSeasonsTable

**Source:** `frontend/src/shared/components/CalendarSeasonsTable.js`

Presentational table that lists all seasons defined on a membership tier config. Renders a WordPress-styled `widefat` table with alternating row shading and a per-row edit button. The component is display-only — it never mutates data; all writes happen through the `onEditSeason` callback.

## What it does

Renders a `Seasons` heading followed by a table with four data columns — Season Name, Status, Start Date, End Date — and an action column containing an edit button on each row. Clicking the edit button calls `onEditSeason` with the zero-based row index of that season. When `disabled` is `true` all edit buttons are inert.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `seasons` | `Array<SeasonObject>` | Yes | Array of season objects to display. Each object must have `season_name`, `active`, `start_date`, and `end_date`. |
| `disabled` | `boolean` | Yes | When `true`, the edit button on every row is disabled. Pass the form-level `isSaving` flag here. |
| `onEditSeason` | `Function` | Yes | Called with the zero-based index of the row whose edit button was clicked. Use this to open `SeasonConfigModal` pre-populated with that season. |

### SeasonObject shape

| Field | Type | Description |
|---|---|---|
| `season_name` | `string` | Display name of the season. |
| `active` | `boolean` | When `true`, the Status column renders "Active"; otherwise "Inactive". |
| `start_date` | `string` | ISO date string (`YYYY-MM-DD`) shown in the Start Date column. |
| `end_date` | `string` | ISO date string (`YYYY-MM-DD`) shown in the End Date column. |

## Behaviour notes

- Row key is derived from `season_name` and the array index, so rows re-key if a season is renamed.
- Even-indexed rows (0, 2, 4…) receive the `alternate` CSS class, matching standard WordPress table row styling.
- The edit button icon is the WordPress `edit` dashicon.

:::details Example
```jsx
<CalendarSeasonsTable
  seasons={tierConfig.seasons}
  disabled={isSaving}
  onEditSeason={(index) => {
    setEditingSeasonIndex(index);
    setIsSeasonModalOpen(true);
  }}
/>
```
:::

::: tip
Pair this component with `SeasonConfigModal`. Pass `null` as `seasonIndex` to `SeasonConfigModal` when adding a new season, or pass the index received from `onEditSeason` when editing an existing one.
:::
