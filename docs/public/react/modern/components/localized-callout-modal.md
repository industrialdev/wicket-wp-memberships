---
title: LocalizedCalloutModal
---

# LocalizedCalloutModal

**Source:** `frontend/src/shared/components/LocalizedCalloutModal.js`

Modal for editing per-locale callout copy (header, body content, and button label). Supports multiple languages by providing a language switcher dropdown inside the modal. Each language's fields are edited independently without leaving the modal.

## What it does

Wraps `WicketModal` with a form containing a language selector and three text fields for the selected locale. On open, the component initialises a working copy of the callout data by merging the passed `value` with empty defaults for every language code in `languageCodes`, ensuring all locales exist in state even if the saved data is missing some. Changes are tracked in local state and only committed to the caller when the **Save** button is clicked.

When the modal opens, the active locale resets to the first entry in `languageCodes`.

## Data structure

The callout data object has this shape:

```js
{
  locales: {
    "en": {
      callout_header: "string",
      callout_content: "string",
      callout_button_label: "string",
    },
    "fr": {
      callout_header: "string",
      callout_content: "string",
      callout_button_label: "string",
    },
    // …one key per language code
  }
}
```

Fields default to empty strings for any locale not present in the saved `value`.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `isOpen` | `boolean` | Yes | Controls modal visibility. State resets each time this transitions to `true`. |
| `title` | `string` | Yes | Modal header title. |
| `languageCodes` | `string[]` | Yes | Array of locale/language code strings (e.g. `["en", "fr"]`). Determines which language options appear in the switcher and which locale keys are included in the saved output. |
| `value` | `CalloutDataObject \| null` | Yes | Current saved callout data. Merged with empty defaults on modal open. Pass `null` or `{}` for a blank form. |
| `onClose` | `Function` | Yes | Called when the modal requests to close. |
| `onSave` | `Function` | Yes | Called with the updated `CalloutDataObject` when the Save button is clicked. The caller is responsible for persisting the value. |

## Fields per locale

| Field key | Input type | Label |
|---|---|---|
| `callout_header` | Text | Callout Header |
| `callout_content` | Textarea | Callout Content |
| `callout_button_label` | Text | Button Label |

:::details Example
```jsx
const [calloutModalOpen, setCalloutModalOpen] = useState(false);
const [calloutData, setCalloutData] = useState(savedCalloutData);

<LocalizedCalloutModal
  isOpen={calloutModalOpen}
  title={__('Edit Callout', 'wicket-memberships')}
  languageCodes={['en', 'fr']}
  value={calloutData}
  onClose={() => setCalloutModalOpen(false)}
  onSave={(updated) => {
    setCalloutData(updated);
    setCalloutModalOpen(false);
  }}
/>
```
:::

::: tip
The `languageCodes` array drives both the language switcher options and the keys written to `locales` in the saved output. Ensure `languageCodes` matches the language codes expected by the PHP layer when saving callout data.
:::

::: warning
`onSave` does not persist data. The caller must handle the API save after receiving the updated value from `onSave`.
:::
