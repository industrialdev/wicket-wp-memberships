---
title: React Frontend
---

# React Frontend

The Wicket Memberships plugin ships a React-powered admin UI built as **multiple independent React roots** — one per WordPress admin page. There is no single-page application shell or client-side router that owns the whole admin area. Each page loads its own self-contained bundle, mounts a root on a dedicated `<div>`, and renders independently of any other page.

::: warning WordPress admin only
All React components in this plugin render exclusively inside the **WordPress admin dashboard** (`/wp-admin`). They are not used on the public-facing site, in shortcodes, or in any frontend context. They depend on WordPress globals (`wp`, `wpApiSettings`), WooCommerce REST API access, and the `wicketMembershipsSettings` object injected by PHP — none of which are available outside the admin.
:::

## Two Distinct Worlds

The frontend has grown in two different eras. Understanding which world a file belongs to explains nearly everything about how it is structured, how much it can be reused, and how it should be changed.

| | Modern | Legacy |
|---|---|---|
| **Pages covered** | Membership bundles, bundle configs, create bundle | Members list, member edit, membership configs, membership tiers |
| **Location** | `src/membership_bundles/`, `src/membership_bundle_configs/`, `src/create_membership_bundle/` | `src/members/`, `src/membership_configs/`, `src/membership_tiers/` |
| **File count** | ~20 focused components | 5–6 large files |
| **Typical file size** | 30–250 lines | 600–1,300 lines |
| **State management** | Bootstrap hook → props drilling | All state in one root component |
| **Reusability** | Adapter + shared UI components | Logic and UI mixed; hard to share |

::: tip
New features should follow the modern pattern. The legacy files are maintained but not extended with new patterns.
:::

## How Pages Are Mounted

Every React root follows the same four-step mount sequence:

1. **Webpack entry point** — a thin entry file in `frontend/src/` that imports the root component.
2. **PHP template enqueues the bundle** — the admin controller calls `wp_enqueue_script()` with the compiled asset, then renders a `<div>` with `data-*` attributes carrying server-side values.
3. **Entry file reads the container** — `document.getElementById()` finds the div by its known ID.
4. **`createRoot().render()`** — mounts the component tree, spreading `dataset` as props.

:::details Example — membership bundle edit page

```js
// src/membership_bundles/pages/edit.js
const app = document.getElementById("bundle_member_edit");
if (app) {
  createRoot(app).render(
    <MembershipBundlePage
      bundleGroupUuid={app.dataset.bundleGroupUuid}
      listUrl={app.dataset.listUrl}
      individualMembersUrl={app.dataset.individualMembersUrl}
    />
  );
}
```

PHP renders: `<div id="bundle_member_edit" data-bundle-group-uuid="..." data-list-url="..." data-individual-members-url="..."></div>`
:::

::: tip
Two entries — `tier_member_count` and `wicket_memberships_tier_cell_info` — use `querySelectorAll` instead of `getElementById` because they mount on multiple cells in a WordPress list table rather than a single container.
:::

## Webpack Entry Points

All ten entry points are declared in `frontend/webpack.config.js` and extend the default `@wordpress/scripts` webpack config.

| Entry key | Source file | Documentation |
|---|---|---|
| `membership_config_create` | `src/membership_configs/edit.js` | [Membership Configs](./legacy/membership-configs) |
| `membership_bundle_config_create` | `src/membership_bundle_configs/pages/edit.js` | [Bundle Configs](./modern/bundle-configs/) |
| `membership_tier_create` | `src/membership_tiers/edit.js` | [Membership Tiers](./legacy/membership-tiers) |
| `member_list` | `src/members/index.js` | [Members](./legacy/members) |
| `bundle_member_list` | `src/members/bundle_list.js` | [Members](./legacy/members) |
| `bundle_member_edit` | `src/membership_bundles/pages/edit.js` | [Membership Bundles](./modern/membership-bundles/) |
| `create_membership_bundle` | `src/create_membership_bundle/pages/create.js` | [Create Bundle](./modern/create-bundle/) |
| `member_edit` | `src/members/edit.js` | [Members](./legacy/members) |
| `tier_member_count` | `src/membership_tiers/member_count.js` | [Membership Tiers](./legacy/membership-tiers) |
| `wicket_memberships_tier_cell_info` | `src/membership_tiers/tier_cell_info.js` | [Membership Tiers](./legacy/membership-tiers) |

Built assets land in `frontend/build/` as `<entry_key>.js` and `<entry_key>.asset.php`.

## Further Reading

- [Architecture & Patterns](./architecture) — component layers, data flow, API service, styling, and build system
