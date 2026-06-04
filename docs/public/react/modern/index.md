---
title: Modern Component Architecture
---

# Modern Component Architecture

The modern React features in this plugin follow a consistent **Adapter + Orchestrator + Sections** pattern. Each feature has a top-level page component (the orchestrator) that owns the error boundary and mounts the content; a bootstrap hook that loads all required data via REST; a form component that renders named section components in a fixed order; and thin adapter components that map page-specific data shapes to the flat props expected by shared UI components. Shared components are deliberately data-agnostic — they receive only the props they render and do not know which page they are on.

Three features currently use this pattern:

- [Membership Bundles UI](./membership-bundles/) — the bundle detail/edit page (`wicket_mship_bundle`)
- [Bundle Config UI](./bundle-configs/) — the bundle configuration editor (`wicket_mship_bcfg`)
- [Create Bundle](./create-bundle/) — the new-bundle creation form

All shared infrastructure components, form sections, specialised inputs, and hooks are catalogued in [Component Reference](./components/).

## File Structure

```
frontend/src/
├── shared/
│   ├── components/     ← 22 shared UI components
│   ├── hooks/          ← useResolvedOption
│   ├── services/       ← api.js
│   ├── utils/          ← pagination.js
│   ├── constants.js
│   ├── cycleUtils.js
│   └── styled_elements.js
├── membership_bundles/
│   ├── pages/          ← entry point
│   ├── components/     ← 9 components
│   ├── hooks/          ← useMembershipBundleBootstrap
│   └── utils/
├── membership_bundle_configs/
│   ├── pages/
│   ├── components/
│   ├── hooks/          ← useBundleConfigBootstrap
│   └── utils/          ← formUtils.js
└── create_membership_bundle/
    ├── pages/
    └── components/
```

## Pattern at a Glance

| Layer | Responsibility | Example |
|---|---|---|
| Entry point | Mount root, spread dataset as props | `pages/edit.js` |
| Page component | Error boundary, notices, bootstrap hook | `MembershipBundlePage` |
| Bootstrap hook | All data fetching + loading/error state | `useMembershipBundleBootstrap` |
| Form/Orchestrator | Render sections in order | `MembershipBundleForm` |
| Section adapter | Map pageData → flat props | `IntroBlockSection` |
| Shared component | Data-agnostic UI | `IntroBlock`, `MembershipOwnerSection` |

::: tip Feature flag
All bundle-related admin pages are hidden behind the `wicket_mship_enable_bundles` plugin option (`WICKET_MSHIP_ENABLE_BUNDLES` env flag). The flag is off by default. See the plugin option table in `CLAUDE.md` for the full list of flags.
:::
