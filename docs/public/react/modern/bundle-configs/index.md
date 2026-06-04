---
title: Bundle Config UI
---

# Bundle Config UI

The Bundle Config UI allows administrators to create and edit **Membership Bundle Configurations** (`wicket_mship_bcfg` CPT). A bundle config defines how a membership bundle behaves — its renewal window, grace period, billing cycle, and renewal type.

## Entry Point

`frontend/src/membership_bundle_configs/pages/edit.js`

The entry script mounts `BundleConfigPage` onto the `#create_membership_bundle_config` DOM element and passes all `data-*` attributes from that element as props.

```js
createRoot(app).render(<BundleConfigPage {...app.dataset} />);
```

## Components

| Component | File | Description |
|---|---|---|
| `BundleConfigPage` | `components/BundleConfigPage.js` | Top-level page shell. Wraps content in an error boundary and renders the WP admin heading. |
| `BundleConfigForm` | `components/BundleConfigForm.js` | Form that renders all config sections and the save/cancel action row. |
| `GeneralSettingsSection` | `components/GeneralSettingsSection.js` | Config name and top-level settings. |
| `CycleSection` | `components/CycleSection.js` | Billing cycle type and calendar season management. |
| `RenewalTypeSection` | `components/RenewalTypeSection.js` | Renewal type (subscription or form-page flow). |
| `RenewalWindowSection` | `components/RenewalWindowSection.js` | Days-before-expiry renewal window and its callout copy. |
| `GracePeriodSection` | `components/GracePeriodSection.js` | Grace period length, optional late-fee product, and callout copy. |
| `ApprovalSection` | `components/ApprovalSection.js` | Approval workflow settings. Currently commented out in `BundleConfigForm`. |

## Hooks

| Hook | File | Description |
|---|---|---|
| `useBundleConfigBootstrap` | `hooks/useBundleConfigBootstrap.js` | Loads the existing record (edit mode), lazy-loads WP posts and WC products, manages request states. |

## Utilities

| Utility | File | Description |
|---|---|---|
| Form helpers | `utils/formUtils.js` | `normalizeBundleConfigFormData`, `validateBundleConfigFormData`, payload builder, and locale merging helpers. |

## Pages

- [BundleConfigPage](./bundle-config-page)
- [BundleConfigForm](./bundle-config-form)
- [useBundleConfigBootstrap](./use-bundle-config-bootstrap)
- [Form Utilities](./form-utils)
