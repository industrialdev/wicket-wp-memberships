---
title: "Bug Fix Docs — Scope & Required Sections"
audience: [developer]
---

# Bug Fix Docs

Repair work only. Kept separate from `docs/engineering/` so it is immediately clear what is
novel feature work versus what was done to fix a discovered issue.

## What belongs here

- Fixes to behaviour that was supposed to work and did not.
- Collisions with third-party plugins that forced a change to our code.
- Workarounds for upstream (WordPress, WooCommerce, plugin) bugs or quirks.

## What does not

- A repair that has not been applied to the code. Those go in
  [`docs/bugfix-pending/`](../bugfix-pending/README.md) and move here when they ship, so a proposed
  fix is never read as a shipped one.


- New features, new endpoints added for new capability, refactors. Those are
  `docs/engineering/`.
- An endpoint added *solely* to route around someone else's bug is a repair, not a feature,
  even though it is new code. See `wppcp-product-picker-collision.md`.

## Required sections

1. **Cause** — the actual mechanism, cited to `file:line`. Verify it in the checked-out code;
   do not restate the ticket's theory.
2. **Effect** — observed symptoms, per affected screen or entry point. Separate distinct
   failure modes; they often have different blast radii.
3. **The repair** — what changed and why this approach over the alternatives considered.
4. **Blast radius of the repair** — *mandatory.* A fix frequently interferes with existing,
   fully operating functionality. Record every consumer the change touched, not just the one
   in the ticket, and any regression the fix introduced and how it was resolved. Note
   behavioural changes (removed limits, changed payloads) even when intended.
5. **Verification performed** — what was actually checked, so the next reader knows what was
   not.
6. **Known remaining exposure** — same root cause, paths not covered by this fix.

## Conventions

- kebab-case filename, no `user-` prefix (developer audience).
- Frontmatter: `title`, `audience`, `ticket`, `branch`.
- Cross-link the relevant `docs/engineering/` class reference in both directions.
- Add an entry to `docs/index.md` under **Bug Fixes & Third-Party Collisions**.
