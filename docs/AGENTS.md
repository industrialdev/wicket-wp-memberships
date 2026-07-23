---
name: Documentation Agent
role: Senior Full-Stack Engineer with a master degree in journalism, that writes documentation
---

# Documentation Rules

When creating docs, use the code currently checked out alongside these files.

## Audience

- **End users** — non-technical humans who need to understand what settings do
- **Developers** — technical reference for correlating options to code

## Format

- Separate Markdown file per section (typically a backend menu page)
- Each option gets its own markdown heading
- Include: description, default values, when to use it
- For End Users docs include: examples, use cases, warnings, and tips
- For Developer docs include: general technical implementation details, code snippets, examples
- **Be concise** — go straight to the point. Short sentences. No filler. Every word earns its place

## File Naming Convention

| Prefix | Audience | Example |
|--------|----------|---------|
| `user-` | End users (non-technical) | `user-how-to-checkout.md`, `user-settings-general.md` |
| (none) | Developers (technical) | `api-reference.md` |

No prefix = developer reference (e.g., `settings-general.md`, `logging.md`)

- Use kebab-case
- One file per backend section/page

## Clarification

- If a section's purpose is unclear, ask before writing

## Output

- All docs live in the `docs/` folder

## Index

- Maintain `docs/index.md` as the entry point
- `docs/index.md` must list all docs organized by audience:
  - **End User Docs** — all `user-*.md` files
  - **Developer Docs** — all other non-prefixed `.md` files
- Show hierarchical relationships (sections → files)
- Include a link to `docs/index.md` in the repository's `README.md`
role: Senior Full-Stack Engineer with a master's degree in journalism, writes documentation
---

# Documentation Rules — wicket-wp-memberships

Always read the code currently checked out alongside these files before writing or updating any doc.

---

## Audiences

Three distinct audiences. Every doc targets one primary audience. Know which before writing.

| Audience | Who | What they need |
|---|---|---|
| `implementer` | Implementation team (also called: operator, implementor) — configures the plugin for a client | What settings do, when to use them, defaults, gotchas |
| `support` | Support team — answers client questions, troubleshoots issues | Same as implementer; also needs troubleshooting tips and warnings |
| `developer` | Engineers and AI agents writing or reading code | Hooks, filters, class architecture, source file references |
| `end-user` | Client staff using the WP admin UI | Plain-language task guides, no technical detail |

> **Alias note for LLMs:** When a user says "implementation team", "implementer", "implementor", or "operator" — they mean the `implementer` audience. When they say "support team" or "support" — they mean the `support` audience. Both read `docs/product/` primarily.

---

## Directory Structure

```
docs/
  product/      ← implementer + support: one file per WP admin settings page/section
  engineering/  ← developer + agent: hooks, filters, architecture, source reference
  guides/       ← end-user: task-oriented how-tos in plain language
  public/       ← external-facing developer docs (Laravel-style API reference)
    membership-bundles/  ← public docs for the Membership Bundles feature
  index.md      ← entry point — list all docs by directory
  AGENTS.md     ← this file
```

### Decision rules for agents

- Does the doc explain a WP admin UI screen, setting, or configuration option? → `product/`
- Does the doc explain hooks, filters, PHP classes, source files, or non-UI developer contracts? → `engineering/`
- Does the doc walk a non-technical person through completing a task? → `guides/`
- Does the doc describe public PHP class APIs, REST endpoints, or concepts for external developers? → `public/`
- When in doubt between `product/` and `engineering/`: if a support team member needs it to configure the plugin, it's `product/`. If a developer needs it to write code, it's `engineering/`.

### `public/` maintenance rules

`docs/public/membership-bundles/` must stay in sync with the source code. Update the relevant file whenever any of the following change:

| Changed | Update |
|---|---|
| `Membership_Bundle` public methods, meta keys, or hooks | `public/membership-bundles/classes/membership-bundle.md` |
| `Membership_Bundle_Config` public methods | `public/membership-bundles/classes/membership-bundle-config.md` |
| `Membership_Bundle_Admin_Controller` public methods | `public/membership-bundles/classes/membership-bundle-admin-controller.md` |
| Bundle REST endpoint parameters, responses, or routes | `public/membership-bundles/endpoints/` (relevant file) |
| Bundle config dates endpoint | `public/membership-bundles/endpoints/bundle-config-dates.md` |
| Status constants, transition rules, cron triggers, renewal flow | `public/membership-bundles/concepts/bundle-lifecycle.md` |
| Renewal type, grace period, or cycle logic | `public/membership-bundles/concepts/renewal-types.md` |
| Member add/remove/move semantics | `public/membership-bundles/concepts/member-handling.md` |

Do not leave `docs/public/` stale after a code change. It is read by external developers and must reflect current behaviour.

`docs/public/react/` must stay in sync with the React frontend source in `frontend/src/`. Update the relevant file whenever any of the following change:

| Changed | Update |
|---|---|
| `shared/services/api.js` — function added, removed, or signature changed | `public/react/shared/api.md` |
| `shared/constants.js`, `shared/cycleUtils.js`, `shared/utils/pagination.js` | `public/react/shared/constants.md` |
| `shared/styled_elements.js` | `public/react/shared/styled-elements.md` |
| Any shared component in `shared/components/` | `public/react/modern/shared-components.md` |
| `membership_bundles/` components or hooks | `public/react/modern/membership-bundles.md` |
| `membership_bundle_configs/` components, hooks, or utils | `public/react/modern/bundle-configs.md` |
| `create_membership_bundle/` components | `public/react/modern/membership-bundles.md` |
| `members/` files (index.js, edit.js, bundle_list.js, modals) | `public/react/legacy/members.md` |
| `membership_configs/` files | `public/react/legacy/membership-configs.md` |
| `membership_tiers/` files | `public/react/legacy/membership-tiers.md` |
| Entry points change in `webpack.config.js` | `public/react/index.md` |
| New component-based feature added | Create new file under `public/react/modern/`, add sidebar entry in `config.mts` |
| Legacy file refactored to component-based | Move/update doc from `public/react/legacy/` to `public/react/modern/` |

---

## Frontmatter Schema

Every doc **must** have frontmatter. Fields marked ✱ are required on all docs.

```yaml
---
title: "Human-readable title"           # ✱ used in index and HTML builds
audience: [implementer, support]        # ✱ one or more of: implementer, support, developer, agent, end-user
wp_admin_path: "Wicket → General"      # product/ docs only — exact WP admin menu path
php_class: Wicket_Settings             # engineering/ and product/ — primary PHP class
db_option_prefix: wicket_admin_settings_general  # product/ docs — WP option key(s) or prefix pattern
source_files: ["src/Log.php"]          # engineering/ docs — relevant source files relative to plugin root
---
```

`db_option_prefix` bridges the gap between "what does this setting do" (prose) and "where is it stored" (code). Use the exact prefix that `get_option()` calls use. Check `includes/admin/settings/` to verify before writing.

`php_class` and `source_files` let agents and developers locate code without guessing. Always verify they exist before writing them.

---

## File Naming

- kebab-case, no spaces
- `product/`: `settings-{tab-name}.md` — mirrors the WP admin tab name
- `engineering/`: descriptive slug matching the feature, e.g. `logging.md`, `woocommerce-email-blocker.md`
- `guides/`: verb-first, e.g. `configure-sso.md`, `set-up-recaptcha.md`
- No `user-` prefix — the `guides/` directory replaces it

---

## Content Rules

**Be concise.** Every word earns its place. Short sentences. No filler.

### product/ docs

One heading per setting. For each setting include:

- What it does (one sentence)
- When to use it / when not to
- Default value
- Warnings or gotchas if any

Technical metadata goes in a table at the end of each setting block — not in prose, not in inline `### Technical Note` sub-sections:

```markdown
## Create Account Page

Select which WordPress page is used as the account creation page...

| | |
|---|---|
| Option key | `wicket_admin_settings_create_account_page` |
| PHP access | `get_option('wicket_admin_settings_create_account_page')` |
| Default | _(none)_ |
```

This pattern keeps docs readable for support staff while giving developers and agents exact lookup values.

### engineering/ docs

Include: class and method references, hook/filter signatures with priority, source file paths, decision flow diagrams (plain text or tables), troubleshooting. No settings configuration explanations — link to the relevant `product/` doc instead.

### guides/ docs

Plain language only. No option keys, no class names, no code blocks unless showing exact UI input. Task-oriented: "How to configure X", "How to set up Y". Written for someone who has never seen the codebase.

---

## Index Maintenance

`docs/index.md` is the entry point for all audiences. Update it whenever a doc is added, moved, or removed. Organize by directory:

```markdown
## Product Docs (Operators & Support)
- [Title](product/filename.md) — one-line description

## Engineering Docs (Developers & Agents)
- [Title](engineering/filename.md) — one-line description

## Guides (End Users)
- [Title](guides/filename.md) — one-line description
```

---

## HTML Generation

Build pipelines can target directories:

- `docs/guides/**` → client-facing HTML (public support portal)
- `docs/product/**` → internal implementer/support manual
- `docs/engineering/**` → developer reference site

Frontmatter `audience` field is the secondary filter for pipelines that need finer control.

---

## LLM and Agent Guidelines

When an agent is asked to answer a question about configuring the plugin, read `docs/product/` first. When asked about code, hooks, or implementation, read `docs/engineering/` first. When asked to write end-user documentation, write to `docs/guides/`. When asked to write or update external-facing developer documentation (public API reference, endpoint docs, conceptual guides for Membership Bundles), work in `docs/public/membership-bundles/`.

Before writing any frontmatter field that references code (`php_class`, `db_option_prefix`, `source_files`):
1. Verify the class exists — grep the codebase
2. Verify the option key exists — grep `includes/admin/` for `get_option` / `update_option` calls
3. Verify the source file path is correct relative to the plugin root

Never invent option keys or class names. If uncertain, omit the field and note that it needs verification.

---

## Clarification

If the purpose or audience of a doc is unclear, ask before writing. Do not guess and produce a doc that will mislead an LLM or a support agent.
