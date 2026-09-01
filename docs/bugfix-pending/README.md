---
title: "Pending Bug Fix Docs — Diagnosed, Not Yet Applied"
audience: [developer]
---

# Pending Bug Fixes

Diagnosed defects whose repair has **not been applied to the code**. Same content standard as
`docs/bugfix/`, different status: everything in `docs/bugfix/` describes work that is in the
codebase; everything here describes work that still needs doing.

Kept separate so nobody reads a proposed fix as a shipped one. A doc in `docs/bugfix/` answers
"why is the code like this"; a doc here answers "what is still broken, and what is the plan".

## What belongs here

- A bug that has been traced to a mechanism and cited to `file:line`, where the fix is specified
  but not written.
- A fix that is partially applied: some sites repaired, others still open. State per-site status
  explicitly — a partial fix is the easiest thing to mistake for a complete one.
- The manual steps (data healing, config re-saves, migrations) that a fix depends on, while those
  steps are still outstanding.

## What does not

- Speculation without a verified mechanism. Diagnose first; a doc here asserts a cause.
- Work already in the codebase. That moves to `docs/bugfix/`.

## Required sections

Follow [`docs/bugfix/README.md`](../bugfix/README.md), with two additions:

1. **A status banner at the top of every doc** stating that the repair is not applied, and what
   remains. Put it above the cause analysis, not at the end.
2. **Per-site status** where a bug spans several code paths — mark each one applied or open, and
   name the commit that fixed the applied ones. Do not describe a fixed site and an open site in
   the same voice.

## Conventions

- One folder per ticket, named `<TICKET>-<short-slug>`, when a ticket produces more than one doc.
- Prefix every filename in that folder with the ticket number.
- Add entries to `docs/index.md` under **Pending Bug Fixes**, not under **Bug Fixes**.

## On completion

When the repair ships, move the folder (or file) to `docs/bugfix/`, replace the status banner with
what was actually applied, fill in **Blast radius of the repair** and **Verification performed**
with real results rather than the proposal, and move its `docs/index.md` entry to the
**Bug Fixes** section.

## Contents

- [WWID-2212 — Membership Timestamp Date Offset Problems](WWID-2212-membership-timestamp-date-offset-problems/) —
  calendar dates carried as instants after the timestamp standardization, shifting membership end,
  expiry, tier-switch and early-renewal dates by one day. Three code sites still open, plus two
  manual phases (config re-save, record healing) not yet run. A third document traces the
  commit chain, because the write path was repeatedly reported as already fixed: the commit that
  introduced the instant conversion was itself a bug fix, and the two later fixes cited as closing
  it are PHP-only
