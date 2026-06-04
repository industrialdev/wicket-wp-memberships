---
title: Styled Elements
---

# Styled Elements

`shared/styled_elements.js` is the global styled-components library for the plugin admin UI. Import components from here rather than writing one-off inline styles. All components are built with `styled-components` and either extend plain HTML elements or wrap WordPress/third-party components.

---

## Layout Wrappers

### `AppWrap`

**Renders:** `<div>`

The outermost wrapper for every admin page in the plugin.

::: warning Date picker CSS requirement
`AppWrap` injects the full `react-datepicker` stylesheet as scoped CSS. **Any page that uses a date picker must have `AppWrap` as its outermost element**, or the date picker will render without styles. Only one `AppWrap` per page is needed — nesting multiple instances is harmless but unnecessary.
:::

In addition to injecting the date picker CSS, `AppWrap` overrides the default `react-datepicker` month/year dropdowns to match the WordPress admin font size and removes the redundant current-month text label.

:::details Example

```jsx
import { AppWrap } from 'shared/styled_elements';

export const MyAdminPage = () => (
  <AppWrap>
    {/* All page content here */}
  </AppWrap>
);
```

:::

---

### `EditWrap`

**Renders:** `<div>`

A constrained-width content wrapper for edit/detail pages. Sets `max-width: 1000px`. Use this as the inner content container on bundle and membership edit pages where a single-column form layout is appropriate.

---

## Form Elements

### `SelectWpStyled`

**Renders:** `react-select` `<Select>` component

A `react-select` single or multi select styled to match the WordPress admin UI. Applies WordPress-style borders (`1px solid #949494`, `border-radius: 2px`) and constrains the control height to `30px` to align with standard WP form inputs.

Supports the same props as `react-select`'s `Select`. The multi-value variant (`isMulti`) uses slightly increased padding in the value container to accommodate multiple chips.

:::details Example

```jsx
import { SelectWpStyled } from 'shared/styled_elements';

<SelectWpStyled
  classNamePrefix="select"
  options={tierOptions}
  value={selectedTier}
  onChange={setSelectedTier}
/>
```

:::

---

### `AsyncSelectWpStyled`

**Renders:** `react-select/async` `<AsyncSelect>` component

The async (typeahead) variant of `SelectWpStyled`. Identical visual styling. Use when options must be loaded from the server in response to user input — for example, the MDP person or organisation search fields.

Supports the same props as `react-select/async`'s `AsyncSelect`.

:::details Example

```jsx
import { AsyncSelectWpStyled } from 'shared/styled_elements';

<AsyncSelectWpStyled
  classNamePrefix="select"
  loadOptions={loadOrgOptions}
  placeholder="Search organisations..."
/>
```

:::

---

### `LabelWpStyled`

**Renders:** `<label>`

A form field label styled to match WordPress admin UI conventions. Renders in uppercase, `11px`, `font-weight: 500`, with `8px` bottom margin. Use above every form input in the plugin admin UI.

:::details Example

```jsx
import { LabelWpStyled } from 'shared/styled_elements';

<LabelWpStyled htmlFor="tier-select">Membership Tier</LabelWpStyled>
<SelectWpStyled id="tier-select" ... />
```

:::

---

## Layout Helpers

### `BorderedBox`

**Renders:** `<div>`

A simple bordered container with `1px solid #c3c4c7` border, `15px` padding, and `15px` top margin. Use to visually group a set of related form fields or settings within a larger form.

---

### `FormFlex`

**Renders:** WordPress `<Flex>` component

A `@wordpress/components` `Flex` wrapper with `15px` top margin. On screens narrower than `768px` the `align-items` override is removed, allowing the flex children to stack vertically without the horizontal alignment constraint. Use for side-by-side form field rows.

---

### `ActionRow`

**Renders:** `<div>`

A simple spacer container with `30px` top margin. Place the primary action button(s) for a form section (Save, Cancel, etc.) inside this component to maintain consistent spacing from the last field.

---

## Modal

### `ModalStyled`

**Renders:** WordPress `<Modal>` component

A `@wordpress/components` `Modal` with `overflow: visible` applied to both the modal root and its inner `.components-modal__content` element. Use whenever a modal contains a `<SelectWpStyled>`, `<AsyncSelectWpStyled>`, or date picker whose dropdown would otherwise be clipped by the modal's default `overflow: hidden` behaviour.

:::details Example

```jsx
import { ModalStyled } from 'shared/styled_elements';

<ModalStyled title="Add Member" onRequestClose={onClose}>
  <AsyncSelectWpStyled ... />
</ModalStyled>
```

:::

---

## Date Picker

### `ReactDatePickerStyledWrap`

**Renders:** `<div>`

A wrapper `<div>` that applies WordPress-consistent styling to a `react-datepicker` input and its dropdown calendar. Key style details:

- The text input inside `.react-datepicker__input-container` is styled to `30px` height with `1px solid #949494` border and `border-radius: 2px`, matching other WP form inputs.
- The calendar popover uses `z-index: 21` to appear above WP admin sidebars and modal overlays.
- The wrapper is `position: relative` to allow the optional calendar icon adornment (`.membership-date-picker__adornment`) to be absolutely positioned at the right edge of the input.

::: tip
`ReactDatePickerStyledWrap` handles the per-field visual styling. The global `react-datepicker` stylesheet (calendar popup, month/year headers, day grid) is injected by `AppWrap` — both wrappers must be present on the page.
:::

:::details Example

```jsx
import DatePicker from 'react-datepicker';
import { ReactDatePickerStyledWrap } from 'shared/styled_elements';

<ReactDatePickerStyledWrap>
  <DatePicker
    selected={startDate}
    onChange={setStartDate}
    dateFormat="yyyy-MM-dd"
    showMonthDropdown
    showYearDropdown
  />
  <span className="membership-date-picker__adornment">
    <span className="membership-date-picker__divider" />
    <span className="membership-date-picker__icon dashicons dashicons-calendar-alt" />
  </span>
</ReactDatePickerStyledWrap>
```

:::
