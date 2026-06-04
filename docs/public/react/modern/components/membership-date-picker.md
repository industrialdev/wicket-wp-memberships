---
title: MembershipDatePicker
---

# MembershipDatePicker

**Source:** `frontend/src/shared/components/MembershipDatePicker.js`

Single labelled date picker field wrapping `react-datepicker`. Fully controlled — the parent owns the `Date` value and receives changes via `onChange`. Intended to be composed inside a `Flex` row; the component itself renders a `FlexBlock` wrapper.

## What it does

Renders a WordPress-styled label (via `LabelWpStyled`) above a `react-datepicker` instance. The picker includes month and year dropdowns (`showMonthDropdown`, `showYearDropdown`, `dropdownMode="select"`) and a calendar icon adornment rendered via a Dashicons span. The date format follows `DEFAULT_DATE_FORMAT` from `shared/constants` (`YYYY-MM-DD`).

The label is linked to the picker via the `name` prop (used as both `htmlFor` on the label and `name` / `aria-label` on the input).

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `name` | `string` | Yes | Input `name` attribute. Also used as `htmlFor` on the label and `aria-label` on the picker. |
| `label` | `string` | Yes | Visible label text rendered above the picker. |
| `value` | `Date \| null` | No | Controlled date value. Pass a `Date` object (not an ISO string). Defaults to `null`. |
| `disabled` | `boolean` | No | Disables the date picker input. Defaults to `false`. |
| `placeholder` | `string` | No | Placeholder text shown when no date is selected. Defaults to `"YYYY-MM-DD"` (localised). |
| `onChange` | `Function` | Yes | Called with a `Date` object when the user selects a date, or `null` if the field is cleared. |

## Layout

The component renders inside a `FlexBlock`, which makes it fill available horizontal space when placed inside a `@wordpress/components` `Flex` row. Use multiple `MembershipDatePicker` instances side-by-side within a single `Flex` to get an evenly spaced date row.

:::details Example
```jsx
import { Flex } from '@wordpress/components';
import MembershipDatePicker from 'shared/components/MembershipDatePicker';

<Flex>
  <MembershipDatePicker
    name="starts_at"
    label={__('Start Date', 'wicket-memberships')}
    value={startsAt}
    disabled={isSaving}
    onChange={(date) => setStartsAt(date)}
  />
  <MembershipDatePicker
    name="ends_at"
    label={__('End Date', 'wicket-memberships')}
    value={endsAt}
    disabled={isSaving}
    onChange={(date) => setEndsAt(date)}
  />
</Flex>
```
:::

::: warning
This component expects a `Date` object for `value`, not an ISO string. If your data layer stores dates as ISO strings, convert them before passing in (e.g. `new Date(isoString + 'T00:00:00')` for date-only strings) and convert back in your `onChange` handler. See `MembershipDatesSection` for an example of the `isoToPickerDate` / `pickerDateToIso` pattern.
:::
