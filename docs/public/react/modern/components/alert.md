---
title: Alert
---

# Alert

**Source:** `frontend/src/shared/components/Alert.js`

Displays a dismissible WordPress-style notice bar for save results. Renders nothing when `saveResult` is `null`.

## What it does

Wraps `@wordpress/components` Notice. Maps `saveResult.type === "error"` to `status="error"` and any other type to `status="success"`. The notice is always dismissible — clicking the dismiss button calls `onDismiss`.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `saveResult` | `object \| null` | Yes | Result object. When `null` nothing is rendered. Shape: `{ type: "success" \| "error", message: string \| ReactNode }`. |
| `onDismiss` | `Function` | Yes | Called when the user clicks the notice dismiss button. |

:::details Example
```jsx
const [saveResult, setSaveResult] = useState(null);

// Show on save
setSaveResult({ type: "success", message: "Saved successfully." });

// Render
<Alert
  saveResult={saveResult}
  onDismiss={() => setSaveResult(null)}
/>
```
:::

::: tip
This component is intentionally stateless. The parent component owns `saveResult`. Clear it via `onDismiss` so the notice disappears after the user acknowledges it.
:::
