---
title: SeasonConfigModal
---

# SeasonConfigModal

**Source:** `frontend/src/shared/components/SeasonConfigModal.js`

Modal for creating, editing, or archiving a single season on a membership tier config. Manages its own transient field state internally and only calls the parent back on confirmed save or delete.

## What it does

Wraps `WicketModal` with a form containing four fields — Season Name (text), Status (Active/Inactive select), Start Date, and End Date — plus validation. On open, the modal copies `initialSeason` (or an empty season template) into local state and clears any previous errors. On submit it validates all fields and, if valid, calls `onSave` then `onClose`.

When `seasonIndex` is not `null` (i.e. editing an existing season), an **Archive** destructive button is shown on the left side of the footer. Clicking it calls `onDelete` and `onClose`.

## Validation rules

| Field | Rule |
|---|---|
| Season Name | Required. |
| Start Date | Required. |
| End Date | Required; must be greater than or equal to Start Date. |

Validation errors are displayed as WordPress `Notice` components (`status="warning"`) above the form fields. The modal will not call `onSave` if validation fails.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `isOpen` | `boolean` | Yes | Controls modal visibility. The modal resets its state whenever this transitions to `true`. |
| `seasonIndex` | `number \| null` | Yes | Zero-based index of the season being edited, or `null` when adding a new season. Controls the modal title ("Add Season" vs "Edit Season") and Archive button visibility. |
| `initialSeason` | `SeasonObject \| null` | No | Initial field values. When `null` or omitted the form starts empty with `active: true`. |
| `onClose` | `Function` | Yes | Called when the modal should close (cancel, successful save, or archive). |
| `onSave` | `Function` | Yes | Called with the updated `SeasonObject` after successful validation. The caller is responsible for persisting or merging the returned value. |
| `onDelete` | `Function` | Yes | Called (without arguments) when the Archive button is clicked. The caller is responsible for removing the season from the list. |

### SeasonObject shape (input and output)

| Field | Type | Description |
|---|---|---|
| `season_name` | `string` | Display name of the season. |
| `active` | `boolean` | `true` for Active, `false` for Inactive. |
| `start_date` | `string` | ISO date string (`YYYY-MM-DD`). |
| `end_date` | `string` | ISO date string (`YYYY-MM-DD`). |

## Date formatting

Dates are stored and returned as `YYYY-MM-DD` strings. The `react-datepicker` instances use `DEFAULT_DATE_FORMAT` from `shared/constants`. When parsing existing dates, the component appends `T00:00:00` before constructing a `Date` object to avoid UTC-offset issues.

:::details Example
```jsx
const [isSeasonModalOpen, setIsSeasonModalOpen] = useState(false);
const [editingIndex, setEditingIndex] = useState(null);

<SeasonConfigModal
  isOpen={isSeasonModalOpen}
  seasonIndex={editingIndex}
  initialSeason={editingIndex !== null ? seasons[editingIndex] : null}
  onClose={() => setIsSeasonModalOpen(false)}
  onSave={(updatedSeason) => {
    if (editingIndex === null) {
      setSeasons((prev) => [...prev, updatedSeason]);
    } else {
      setSeasons((prev) =>
        prev.map((s, i) => (i === editingIndex ? updatedSeason : s))
      );
    }
  }}
  onDelete={() => {
    setSeasons((prev) => prev.filter((_, i) => i !== editingIndex));
  }}
/>
```
:::

::: warning
`onDelete` and `onSave` do not persist data themselves. The caller must update its own state and trigger any API save after these callbacks fire.
:::
