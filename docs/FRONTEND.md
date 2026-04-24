# Wicket Memberships — Frontend Architecture

This document describes the React frontend architecture for `wicket-wp-memberships`. It exists to guide both human developers and AI agents when reading, extending, or modifying front-end code. Keep it up to date as the UI evolves.

---

## Two Eras, One Plugin

The frontend is in an intentional transition between two architectural approaches. Both coexist and that is by design. **Do not attempt to merge or unify them without an explicit task to do so.**

| Era | Location | Pattern | Status |
|-----|----------|---------|--------|
| Legacy | `members/`, `membership_configs/`, `membership_tiers/` | Monolithic single-component pages | Stable — leave alone unless fixing a specific bug |
| Modern | `membership_group_configs/` | Component-based, hooks, utilities | Active development target — follow its patterns for all new work |

---

## Directory Map

```
frontend/
├── src/
│   ├── shared/                      # Shared infrastructure — available to all pages
│   │   ├── components/              # Reusable UI components (data-agnostic)
│   │   ├── services/                # API communication layer
│   │   ├── constants.js             # Global constants (endpoints, slugs, settings keys)
│   │   ├── cycleUtils.js            # Cycle data helpers (normalizeCycleData, getDefaultCycleData)
│   │   └── styled_elements.js       # Styled-components wrappers and global CSS imports
│   ├── members/                     # Member list and edit pages (legacy)
│   ├── membership_configs/          # Membership tier config page (legacy)
│   ├── membership_group_configs/    # Group config page (modern)
│   │   ├── components/              # Thin adapter components — map form shape to shared UI
│   │   ├── hooks/                   # Custom hooks for this page
│   │   ├── pages/                   # Webpack entry points (one file per WP admin page)
│   │   └── utils/                   # Page-scoped utility and helper functions
│   ├── membership_groups/           # Membership group detail page (modern)
│   │   ├── components/              # Thin adapter components — map page data to shared UI
│   │   ├── hooks/                   # Custom hooks for this page
│   │   ├── pages/                   # Webpack entry points (one file per WP admin page)
│   │   └── utils/                   # Page-scoped utility and helper functions
│   └── membership_tiers/            # Membership tier edit pages (legacy)
├── build/                           # Compiled output — committed to repo
├── webpack.config.js                # Extends @wordpress/scripts; defines entry points
└── package.json                     # Dependencies and build scripts
```

---

## Build System

**Tool:** `@wordpress/scripts` (webpack underneath).

**Commands:**
- `npm run build` — production build
- `npm run start` — development watch mode

**Entry points** (defined in `webpack.config.js`):

| Entry name | Source file | WordPress page |
|-----------|-------------|----------------|
| `membership_config_create` | `membership_configs/edit.js` | Membership config create/edit |
| `membership_group_config_create` | `membership_group_configs/pages/edit.js` | Group config create/edit |
| `membership_tier_create` | `membership_tiers/edit.js` | Tier create/edit |
| `member_list` | `members/index.js` | Member list |
| `member_edit` | `members/edit.js` | Member detail/edit |
| `group_member_list` | `members/group_list.js` | Membership group list |
| `group_member_edit` | `membership_groups/pages/edit.js` | Membership group detail/edit |
| `tier_member_count` | `membership_tiers/member_count.js` | Inline tier table cell |
| `wicket_memberships_tier_cell_info` | `membership_tiers/tier_cell_info.js` | Inline tier table cell |

Built bundles live in `build/` and are committed. Each bundle has a companion `.asset.php` (dependency manifest for WordPress) and `.js.map` (source map).

---

## Shared Layer (`src/shared/`)

Everything in `src/shared/` is available to all pages — legacy and modern alike. Import from here; never duplicate these files.

### `shared/constants.js`
Global constants: REST API endpoint paths, CPT slugs, WooCommerce product types, plugin setting keys. Import from here rather than hardcoding strings in components.

### `shared/styled_elements.js`
`styled-components` wrappers for WordPress admin UI primitives (panels, rows, labels) and global CSS imports (e.g., react-datepicker). Use these wrappers before creating new styled elements so the visual language stays consistent.

### `shared/cycleUtils.js`
Pure cycle data helpers: `getDefaultCycleData()` and `normalizeCycleData(cycleData)`. Extracted here so they can be used by both shared UI components and page-specific form utilities without circular imports. `formUtils.js` re-exports these for backwards compatibility — always import from `shared/cycleUtils` in shared components and from `formUtils` in group-config-specific code.

### `shared/services/api.js`
See [Service Layer](#service-layer-srcsharedservicesapijs) below.

---

## Shared UI + Adapter Pattern

This is the core structural pattern for all modern section components. Understanding it is essential before adding or modifying any UI section.

### The problem it solves

Several UI sections (approval, cycle, grace period, renewal window, renewal type, general settings) will appear on multiple pages — group config today, membership tiers later — with **identical UI but different underlying data shapes**. The form key for "days count" might be `renewal_window_data.days_count` on one page and something else on another. Without a separation of concerns, the UI component would need to know about every page's form structure, or would have to be duplicated.

### How it works

Every section is split into two layers:

**1. Shared UI component (`src/shared/components/`)**
- Receives only flat, named props — no `form` object, no form shape knowledge
- Handles all rendering, loading states, and user interaction callbacks
- Has no opinion about where data comes from or how it gets saved
- Can be reused on any page by giving it the right props

**2. Adapter component (`src/<page>/components/`)**
- One per page per section
- Knows the page's form shape intimately
- Reads the right fields out of `form`, computes any derived values (e.g. `findOptionByValue`)
- Calls `onChange` with the correct immutable update pattern for that page's state
- Passes everything down as flat props to the shared UI component
- Contains no JSX of its own beyond the shared component invocation

### Example

```
shared/components/RenewalWindowSection.js   ← pure UI: daysCount, onDaysCountChange, onConfigureCallout, disabled, isLoading
membership_group_configs/components/RenewalWindowSection.js   ← adapter: reads form.renewal_window_data.days_count, wires onChange
membership_tiers/components/RenewalWindowSection.js           ← (future) adapter: reads tier form shape, wires onChange
```

### Rules

- **Shared components never import from a page directory.** The dependency only flows inward: page → shared.
- **Adapters never contain JSX sections.** If you find yourself writing UI markup in an adapter, it belongs in the shared component instead.
- **`onChange` in adapters always uses the immutable updater pattern** — `onChange((currentForm) => ({ ...currentForm, ... }))` — so state updates compose correctly with React's batching.
- **Error messages are resolved in the adapter**, not the shared component. The adapter calls `getPrimaryErrorMessage()` and passes a plain string; the shared component just renders it.
- **Loading state is computed in the adapter** — `isLoading={isEditing && !isRecordReady}` — and passed as a boolean prop. The shared component calls `AdminLoadingSkeleton` when true.

---

## Shared Components (`src/shared/components/`)

These are available to all pages — legacy and modern alike.

All shared components are data-agnostic — they receive flat props and have no knowledge of any page's form shape. Page-specific adapter components (in each page's `components/` folder) handle the mapping between form state and these shared props.

| File | Purpose |
|------|---------|
| `AdminNoticeStack.js` | Renders a list of admin notices (error / warning / info / success). Pass an array of notice objects. |
| `AdminPageErrorBoundary.js` | Class-based error boundary. Wrap any top-level page component with this so JS errors show a graceful message instead of a blank screen. |
| `AdminLoadingSkeleton.js` | Animated skeleton loaders. Variants: `singleField`, `multiField`, `fieldWithAction`, `cycle`. Use during async data loads. |
| `ApprovalSettingsSection.js` | Approval checkbox + email input + callout config button. |
| `CalendarSeasonsTable.js` | Read-only table of calendar seasons with edit buttons. Used as a partial inside `CycleSection`. |
| `CycleAnniversaryFields.js` | Membership period inputs + align-end-dates controls for anniversary cycle type. Used as a partial inside `CycleSection`. |
| `CycleSection.js` | Cycle type dropdown; delegates to `CycleAnniversaryFields` or `CalendarSeasonsTable` depending on selection. |
| `GeneralSettingsSection.js` | Single text input for a record name. Label is configurable via `loadingLabel` prop. |
| `GracePeriodSection.js` | Grace period days input + late-fee product selector + callout config button. |
| `LocalizedCalloutModal.js` | Multi-locale callout text editor (header, body, button label per locale). Opens as a modal. |
| `RenewalTypeSection.js` | Renewal type dropdown; conditionally renders a page selector when `form_page` renewal type is selected. |
| `RenewalWindowSection.js` | Renewal window days input + callout config button. |
| `SeasonConfigModal.js` | Create/edit a membership season (name, active flag, start/end dates). |
| `WicketButton.js` | Thin wrapper around `@wordpress/components` `Button` with Wicket-specific defaults. |
| `WicketModal.js` | Thin wrapper around `@wordpress/components` `Modal` with a fixed 840px max-width. Use this instead of the raw Modal for visual consistency. |
| `IntroBlock.js` | Header block for membership entity detail pages. Renders entity name, action buttons, and a configurable `infoFields` metadata bar. Props: `title`, `infoFields` (`[{ label, value }]`), `actions` (`[{ label, href, target?, icon? }]`), `isLoading`. |
| `MembershipRecordsSection.js` | Titled bordered panel for membership record tables. Shell only — no data rows rendered yet. Props: `isLoading`. |

---

## Legacy Pages (Leave Alone)

These pages work. They are large, not decomposed into sub-components, and mix state, API calls, and rendering in one file. **Do not refactor them without a specific task.** Bug fixes and targeted feature additions are fine.

### `members/index.js`
Member list page. Filterable table with tabs (individual / org), sorting, and pagination. Mounts to `#member_list`.

### `members/edit.js`
Member detail/edit page (~800 lines). Displays membership data and status; handles status transitions and renewal order creation. Mounts to `#member_edit`.

Supporting files:
- `members/ManageStatusModal.js` — status transition modal with confirmation for destructive actions
- `members/create_renewal_order.js` — renewal order creation modal (product/variation picker)

### `membership_configs/edit.js`
Membership config create/edit page (~500+ lines). Manages seasons, renewal windows, and linked tiers. Mounts to `#membership_config_create`.

Supporting file:
- `membership_configs/tiers.js` — read-only table of connected MDP tiers

### `membership_tiers/edit.js`
Membership tier create/edit page (~600+ lines). Covers tier basics, linked WooCommerce products, approval settings, and callout text. Mounts to `#membership_tier_create`.

Supporting files:
- `membership_tiers/manage_products.js` — modal to add/remove products and set seat limits
- `membership_tiers/member_count.js` — micro-component rendering member count inline in a table cell
- `membership_tiers/tier_cell_info.js` — micro-component rendering any tier field inline in a table cell
- `membership_tiers/ApprovalCalloutModal.js` — tier-specific callout editor modal

---

## Modern Page: Group Config (`membership_group_configs/`)

This is the reference architecture for all new work. Follow the patterns here when adding new pages or sections.

### Guiding principles

1. **One section = one component.** Each logical group of fields lives in its own file under `components/`.
2. **Data loading lives in hooks.** Components receive data as props; they do not call `apiFetch` directly.
3. **Business logic lives in utilities.** Serialization, validation, and data normalization belong in `formUtils.js`, not in components.
4. **Shared UI, thin adapters.** Section components in `components/` are thin adapters — they read from/write to the form shape and delegate rendering to the data-agnostic components in `shared/components/`. New pages that share the same sections create their own adapter in their own `components/` folder, leaving the shared UI component untouched.
5. **Wrap pages in `AdminPageErrorBoundary`.** Every top-level page component gets an error boundary.
6. **Use `AdminLoadingSkeleton` during loads.** Pass the appropriate variant to each section while data is pending.
7. **Keep entry points minimal.** `edit.js` should only import and mount the page component — nothing else.

### File reference

| File | Role |
|------|------|
| `pages/edit.js` | Entry point. Mounts `GroupConfigPage` into the DOM via `createRoot`. No logic here. |
| `components/GroupConfigPage.js` | Top-level container. Owns form state, wires up the bootstrap hook, renders the error boundary and notice stack, passes state + setters down to `GroupConfigForm`. |
| `components/GroupConfigForm.js` | Form orchestrator. Renders all section components in order, manages modal open/close state, handles form submission. |
| `components/GeneralSettingsSection.js` | Adapter. Maps `form.name` → `shared/components/GeneralSettingsSection`. |
| `components/ApprovalSection.js` | Adapter. Maps `form.group_config_data` approval fields → `shared/components/ApprovalSettingsSection`. |
| `components/CycleSection.js` | Adapter. Maps `form.cycle_data` → `shared/components/CycleSection`. |
| `components/RenewalTypeSection.js` | Adapter. Maps `form.group_config_data` renewal fields → `shared/components/RenewalTypeSection`. |
| `components/RenewalWindowSection.js` | Adapter. Maps `form.renewal_window_data` → `shared/components/RenewalWindowSection`. |
| `components/GracePeriodSection.js` | Adapter. Maps `form.late_fee_window_data` → `shared/components/GracePeriodSection`. |
| `hooks/useGroupConfigBootstrap.js` | Custom hook. Loads the existing record (if editing), available pages, and available products. Returns `{ formData, pages, products, requestState }`. |
| `utils/formUtils.js` | Pure functions: build default form state, normalize API responses into form shape, serialize form state back to API payload, validate fields, merge locale strings, extract API error messages. Re-exports `normalizeCycleData` and `getDefaultCycleData` from `shared/cycleUtils.js` for backwards compatibility. |

---

## Service Layer (`src/shared/services/api.js`)

Centralized API communication using `@wordpress/api-fetch`. All REST calls go through here.

Covers: membership CRUD, member listing/filtering, tier info, status management, WooCommerce products/variations, MDP person search, renewal order creation, and custom plugin endpoints under `/wicket_member/v1/`.

**Rule:** Components and hooks must not call `apiFetch` directly. Add a named function to `api.js` and import it.

---

## Tech Stack Quick Reference

| Concern | Library |
|---------|---------|
| UI components | `@wordpress/components` |
| Styling | `styled-components` v6 |
| Translations | `@wordpress/i18n` |
| REST calls | `@wordpress/api-fetch` |
| Date picking | `react-datepicker` |
| Select inputs | `react-select` |
| Date math | `moment` |
| State management | React hooks — no Redux or Context |

---

## Guardrails for AI Agents

- **Do not merge legacy and modern patterns.** They serve different purposes. Improvements to legacy pages should stay within their existing style.
- **Do not introduce new state management libraries** (Redux, Zustand, Jotai, etc.) without an explicit request.
- **Do not add a Context provider** unless the component tree depth makes prop-drilling genuinely unworkable.
- **New pages follow the group config pattern:** `pages/` entry point → page container → form orchestrator → section components + hook + formUtils.
- **New section components follow the shared UI + adapter pattern.** UI goes in `shared/components/`, form-shape mapping goes in the page's `components/` folder. Read the [Shared UI + Adapter Pattern](#shared-ui--adapter-pattern) section before writing any new section.
- **New shared components go in `src/shared/components/`.** Page-scoped components stay in the page's own `components/` folder.
- **Shared components must not import from any page directory.** Dependencies flow inward only: page → shared.
- **New API calls go in `src/shared/services/api.js`.** Never inline `apiFetch` in a component.
- **Run `npm run build` after any source change** so the `build/` output stays in sync.
- **Do not modify `build/` files directly.** They are generated artifacts.

---

## Keeping This Document Up to Date

Update this file whenever:
- A new entry point or page is added
- A shared component is created or removed
- The modern architecture guidelines change
- A legacy page is refactored to the modern pattern (move it to the modern section)
