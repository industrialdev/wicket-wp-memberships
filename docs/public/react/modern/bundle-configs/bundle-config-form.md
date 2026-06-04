---
title: BundleConfigForm
---

# BundleConfigForm

`frontend/src/membership_bundle_configs/components/BundleConfigForm.js`

Renders the full bundle configuration form. Manages two local modal states — the active callout modal key and the season config modal state — and delegates all other state to the parent via props.

## Props

| Name | Type | Required | Description |
|---|---|---|---|
| `form` | `object` | Yes | Current form values (see shape below). |
| `setForm` | `function` | Yes | Form state setter (React `useState` dispatcher). |
| `onSubmit` | `function` | Yes | Form `onSubmit` handler from the parent page. |
| `isSubmitting` | `boolean` | Yes | When `true`, disables all inputs and shows the busy state on the submit button. |
| `bundleConfigListUrl` | `string` | Yes | URL for the Cancel button href. |
| `postId` | `string` | No | Present when editing; controls the submit button label. |
| `languageCodes` | `string[]` | Yes | Array of active language codes, passed to `LocalizedCalloutModal`. |
| `isRecordReady` | `boolean` | Yes | `false` while the existing record is still loading; locks the form. |
| `isEditing` | `boolean` | Yes | `true` when `postId` is set. |
| `wpPostsOptions` | `array` | Yes | Cached WP post options for `RenewalTypeSection`. |
| `wcProductOptions` | `array` | Yes | Cached WC product options for `GracePeriodSection`. |
| `loadPostOptions` | `function` | Yes | Lazy loader for WP posts; called by `RenewalTypeSection` on first open. |
| `loadProductOptions` | `function` | Yes | Lazy loader for WC products; called by `GracePeriodSection` on first open. |

## Interaction lock

`isInteractionLocked` is `true` when either `isSubmitting` or `!isRecordReady`. All section components receive this as `isDisabled` and the submit button is `disabled` + `isBusy`.

## Sections rendered (in order)

1. **GeneralSettingsSection** — config name field and any top-level options.
2. **RenewalWindowSection** — number of days before expiry the renewal window opens; opens `LocalizedCalloutModal` for `renewal_window_data` callout copy.
3. **GracePeriodSection** — grace period length in days, optional late-fee WC product, and callout copy for `late_fee_window_data`.
4. **CycleSection** — cycle type selector and calendar season list; opens `SeasonConfigModal` to add or edit individual seasons.
5. ~~**ApprovalSection**~~ — commented out (`{/* ApprovalSection hidden — approval system not in use */}`). The component and its `approval_callout_data` callout modal handler still exist in the codebase but are not rendered.
6. **RenewalTypeSection** — `subscription` or `form_page` selector; shows page picker when `form_page` is selected.

::: warning
`ApprovalSection` is intentionally excluded. Do not re-enable it without confirming the approval workflow is fully implemented on the backend.
:::

## Modals

### LocalizedCalloutModal

Opened for three keys — `renewal_window_data`, `late_fee_window_data`, and `approval_callout_data` (unused). The `activeCalloutConfig` memo resolves the correct title, current value, and `onSave` handler based on `activeCalloutModal`.

### SeasonConfigModal

Controlled by `seasonModalState` (`{ isOpen, seasonIndex }`). When `seasonIndex` is `null`, the modal is in "add new" mode and starts from `createEmptySeason()`. On save, appends or replaces the season in `form.cycle_data.calendar_items`. On delete, filters the season out by index.

## Form shape

```ts
{
  name: string;
  renewal_window_data: {
    days_count: string;
    locales: Record<string, { callout_header: string; callout_content: string; callout_button_label: string }>;
  };
  late_fee_window_data: {
    days_count: string;
    product_id: string;
    locales: Record<string, { callout_header: string; callout_content: string; callout_button_label: string }>;
  };
  cycle_data: object;           // shape managed by cycleUtils
  bundle_config_data: {
    renewal_type: "subscription" | "form_page";
    renewal_form_page_id: string;
    approval_required: boolean;
    grant_owner_assignment: boolean;
    approval_email_recipient: string;
    approval_callout_data: { locales: Record<string, object> };
  };
}
```
