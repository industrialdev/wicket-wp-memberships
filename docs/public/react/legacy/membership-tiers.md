---
title: Membership Tiers (Legacy)
---

# Membership Tiers (Legacy)

::: warning Pending rework
These files are monolithic legacy components scheduled for refactoring into the component-based architecture used by the Membership Bundles feature. Do not use these as a pattern for new development — refer to the [Modern Component Architecture](../modern/) section instead.
:::

::: tip Modern equivalent
The component-based pattern to follow when refactoring these files is documented in [Modern Component Architecture](../modern/). The shared components that should replace inline rendering are in [Shared Components](../modern/components/).
:::

## PHP Integration

The admin controller (`Membership_Tier_CPT_Hooks`) enqueues `wicket-memberships_membership_tier_create` (`frontend/build/membership_tier_create.js`) on the page `admin_page_wicket_membership_tier_edit` and renders:

```html
<div
  id="create_membership_tier"
  data-products-in-use="101,202,303"
  data-product-variations-in-use="501,502"
  data-tier-cpt-slug="wicket_mship_tier"
  data-config-cpt-slug="wicket_mship_cfg"
  data-tier-list-url="https://example.com/wp-admin/edit.php?post_type=wicket_mship_tier"
  data-individual-list-url="https://example.com/wp-admin/admin.php?page=individual_member_list"
  data-org-list-url="https://example.com/wp-admin/admin.php?page=org_member_list"
  data-language-codes="en,fr"
  data-post-id="789"
></div>
```

| Attribute | Source | Description |
|---|---|---|
| `data-products-in-use` | `Membership_Tier::get_all_tier_product_ids()` (minus variable/variable-subscription types, minus current tier's own products in edit mode) as CSV | WC product IDs already assigned to other tiers; excluded from the product picker |
| `data-product-variations-in-use` | `Membership_Tier::get_all_tier_product_variation_ids()` (minus current tier's own variations in edit mode) as CSV | WC variation IDs already in use; excluded from variation pickers |
| `data-tier-cpt-slug` | `Helper::get_membership_tier_cpt_slug()` | WP REST API post-type slug for tiers |
| `data-config-cpt-slug` | `Helper::get_membership_config_cpt_slug()` | WP REST API post-type slug for configs |
| `data-tier-list-url` | `admin_url('edit.php?post_type=<tier_slug>')` | Redirect target after a successful save |
| `data-individual-list-url` | `admin_url('admin.php?page=individual_member_list')` | Used by the "View All Members" link for individual tiers |
| `data-org-list-url` | `admin_url('admin.php?page=org_member_list')` | Used by the "View All Members" link for organisation tiers |
| `data-language-codes` | `Helper::get_wp_languages_iso()` imploded as CSV | Active WP site languages; drives locale tabs in `ApprovalCalloutModal` |
| `data-post-id` | `$_GET['post_id']` | WP post ID of the tier being edited; absent (empty string) in create mode |

The admin URL for editing an existing tier is `admin.php?page=wicket_membership_tier_edit&post_id=<ID>`. WordPress `post.php?action=edit` and `post-new.php` for this CPT are intercepted by `create_edit_page_redirects()` and 302-redirected automatically.

### Global JS object (`wicketMembershipsSettings`)

The page also has access to the `wicketMembershipsSettings` global injected by `Admin_Controller::admin_footer_scripts()`. React code accesses it as `PLUGIN_SETTINGS` (re-exported from `shared/constants.js`):

| Key | Source | Description |
|---|---|---|
| `WICKET_MSHIP_MERGE_TOOLS` | `$_ENV['WICKET_MSHIP_MERGE_TOOLS']` | Feature flag for the merge-tools UI |
| `WICKET_MSHIP_MULTI_TIER_RENEWALS` | `$_ENV['WICKET_MSHIP_MULTI_TIER_RENEWALS']` | Feature flag read in the tier editor for multi-tier renewal behaviour |
| `adminUrl` | `admin_url()` | WordPress admin base URL |
| `WICKET_MSHIP_MDP_TIMEZONE` | `$_ENV['WICKET_MSHIP_MDP_TIMEZONE']` (falls back to `UTC`) | Timezone used for date display |

---

## membership_tiers/edit.js — Tier Editor (727 lines)

Mounts on `#create_membership_tier`. Handles both create and edit modes for a `wicket_mship_tier` post. The component is named `CreateMembershipTier` internally. The `postId` dataset prop distinguishes the two modes.

### What it renders

- Page heading ("Add New Membership Tier" or "Edit Membership Tier")
- Validation error notices
- The entire form is wrapped in `CustomDisabled` and is non-interactive until all remote data (MDP tiers and membership config options) has loaded
- **MDP Tier selector** — a React Select populated from the live MDP tiers list; selecting a tier also populates the read-only tier info bar (status, type, category, grace period days, member count with "View All Members" link)
- **Membership Config selector** — links this tier to an existing config post
- **Approval settings** — "Approval Required" and "Renew Approval Required" checkboxes, an approval email recipient field (enabled only when at least one approval flag is checked), and a "Callout Configuration" button that opens `ApprovalCalloutModal`
- **Renewal Type selector** — `current_tier`, `sequential_logic`, `form_flow`, or `subscription`
  - `sequential_logic`: shows a "Sequential Tier" selector (WP tier posts)
  - `form_flow`: shows a "Form Page" selector (published WP pages)
- **Product / seat configuration** — rendered by `ManageTierProducts` (see below)
  - Individual tier: a single `ManageTierProducts` labelled "Granted Via"
  - Organisation tier: a "Seat Settings" dropdown (`per_seat` or `per_range_of_seats`), an "Automatically Grant Owner Seat" checkbox, and a `ManageTierProducts` panel whose `maxRangeEnabled` flag depends on the seat type
- Save button

### Dataset props

| Name | Type | Required | Description |
|---|---|---|---|
| `tierCptSlug` | `string` | Yes | WP REST API post-type slug for tiers (e.g. `wicket_mship_tier`) |
| `configCptSlug` | `string` | Yes | WP REST API post-type slug for configs |
| `tierListUrl` | `string` | Yes | URL to redirect to after a successful save |
| `postId` | `string` | No | WP post ID of the tier being edited; absent means create mode |
| `productsInUse` | `string` | No | Comma-separated WC product IDs already in use; excluded from the product picker |
| `productVariationsInUse` | `string` | No | Comma-separated variation IDs already in use; excluded from the variation picker |
| `individualListUrl` | `string` | Yes | URL for the individual member list ("View All Members" link for individual tiers) |
| `orgListUrl` | `string` | Yes | URL for the organisation member list |
| `languageCodes` | `string` | Yes | Comma-separated language codes passed to `ApprovalCalloutModal` |

### Fetches on mount

All fetches run in parallel from a single `useEffect`:

| Request | Purpose |
|---|---|
| `apiFetch` on `/wp/v2/pages` | Published WP pages for the "Form Page" selector |
| `apiFetch` on `/${tierCptSlug}` | Published WP tier posts for the "Sequential Tier" selector |
| `apiFetch` on `/${configCptSlug}` | Published config posts for the "Membership Config" selector |
| `fetchMembershipTiers()` | Live MDP tier list |
| `apiFetch` on `/${tierCptSlug}/${postId}` | Existing tier data in edit mode (skipped in create mode) |

On save, a `POST` is sent to `/${tierCptSlug}` or `/${tierCptSlug}/${postId}` with `{ title, status: 'publish', tier_data: form }`. On success the browser redirects to `tierListUrl`.

### Key state variables

| Variable | Purpose |
|---|---|
| `form` | The committed tier data; see shape below |
| `mdpTiers` | Array of tiers fetched from the MDP |
| `wpTierOptions` | WP tier posts, used by the sequential-tier selector |
| `wpPagesOptions` | WP pages, used by the form-page selector |
| `membershipConfigOptions` | Config post options for the config selector |
| `tierInfo` | Member count and other metadata for the currently selected MDP tier (fetched separately from the tier list) |
| `isSubmitting` | Busy state for the save button |
| `errors` | Validation/API error strings |

### Form data shape

```js
{
  grant_owner_assignment: false,
  approval_required: false,
  renew_approval_required: false,
  approval_email_recipient: '',
  mdp_tier_name: '',
  mdp_tier_uuid: '',
  next_tier_id: '',
  next_tier_form_page_id: '',
  config_id: '',
  renewal_type: 'current_tier', // 'current_tier' | 'sequential_logic' | 'form_flow' | 'subscription'
  type: '',                      // 'individual' | 'organization' (set from the selected MDP tier)
  seat_type: 'per_seat',         // 'per_seat' | 'per_range_of_seats'
  product_data: [],              // [{ product_id, max_seats, variation_id }]
  approval_callout_data: {
    locales: { en: { callout_header: '', callout_content: '', callout_button_label: '' } }
  }
}
```

`max_seats` is stored as `0` in state for unlimited seats and converted to `-1` on submit.

---

## membership_tiers/manage_products.js — Product Management Sub-component (551 lines)

Not mounted directly. Rendered inside `CreateMembershipTier` whenever product configuration is needed. Exports `ManageTierProducts` as the default export.

### What it does

Displays a table of WooCommerce products assigned to a tier (used for both "Granted Via" and seat configuration). Add/edit operations are performed through a modal that lets the admin select a product, optionally select a variation (for `variable-subscription` products), and optionally set a `max_seats` range (when `maxRangeEnabled` is true). The component calls `saveProductChanges(updatedProductArray)` to propagate changes back to the parent; it does not persist anything directly.

### Props

| Name | Type | Required | Description |
|---|---|---|---|
| `saveProductChanges` | `function` | Yes | Callback invoked with the updated `product_data` array whenever a product is added, edited, or deleted |
| `products` | `array` | Yes | Current array of `{ product_id, max_seats, variation_id }` objects from the parent form state |
| `maxRangeEnabled` | `boolean` | No | When `true`, shows the "Range Max" column and a max-seats input in the modal (default `false`) |
| `limit` | `number` | No | Maximum number of products allowed; the "Add Product" button is disabled once reached. `-1` means unlimited (default `-1`) |
| `productsInUse` | `string` | No | Comma-separated product IDs to exclude from the product picker |
| `productVariationsInUse` | `array` | No | Variation IDs to exclude from variation pickers |
| `productListLabel` | `string` | No | Override for the table heading; defaults to "Product" (limit=1) or "Products" |

### Fetches on mount

1. `getAllWcProducts()` — fetches all published WC products across each type in `WC_PRODUCT_TYPES` in parallel and merges results into `wcProductOptions`. Products in `productsInUse` are excluded via the `exclude` API param.
2. For each product in the initial `products` prop that has a `variation_id`, `getProductVariations(productId)` is called to pre-load variation options for display.

Additional variation fetches are triggered lazily when the user selects a `variable-subscription` product in the modal.

### Key state variables

| Variable | Purpose |
|---|---|
| `wcProductOptions` | All available WC products as React Select options |
| `productVariations` | Object keyed by product ID, each value an array of variation objects |
| `tempProduct` | The product being created or edited in the modal (`{ product_id, max_seats, variation_id }`) |
| `tempProductErrors` | Validation errors shown inside the modal |
| `currentProductIndex` | Index of the product being edited; `null` means adding a new product |
| `isModalOpen` | Whether the add/edit modal is visible |

:::details Example usage from the tier editor
```jsx
// Individual tier — product grants
<ManageTierProducts
  saveProductChanges={updateProductData}
  products={form.product_data}
  limit={99}
  productsInUse={productsInUse}
  productVariationsInUse={productVariationsInUseArray}
  productListLabel={__('Granted Via', 'wicket-memberships')}
/>

// Organisation tier — per range of seats
<ManageTierProducts
  saveProductChanges={updateProductData}
  maxRangeEnabled={true}
  products={form.product_data}
  productsInUse={productsInUse}
  productVariationsInUse={productVariationsInUseArray}
  productListLabel={'Seats Data'}
/>
```
:::
