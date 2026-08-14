---
title: "Autorenew Class Reference"
audience: [developer]
php_class: Autorenew
source_files: ["includes/Autorenew.php"]
---

# Autorenew Class Index

**File:** includes/Autorenew.php

## Methods

- `__construct()`
- `resolve_status($membership)` (static)
- `is_autorenewing($membership)` (static)
- `has_forced_workflow()` (private, static)
- `clear_forced_workflow_cache()` (static)

## Method Descriptions

**__construct()**
Hooks `save_post_aw_workflow` and `trashed_post` to `clear_forced_workflow_cache()`.

**resolve_status($membership)** (static)
Single source of truth for whether a membership's linked subscription will actually auto-renew, and why not when it won't. Checks subscription status, the manual-renewal flag, staging/duplicate-site detection, and payment-method support for scheduled payments — with a documented exception for the forced-autorenew AutomateWoo workflow (see `has_forced_workflow()`). Returns `['result' => bool, 'reason' => string|null]` — `reason` is a plain-English sentence (not a code), ready to display as-is. See [`autorenew-status.md`](autorenew-status.md) for full details and local testing steps.

**is_autorenewing($membership)** (static)
Bool-only wrapper around `resolve_status()`, kept for callers that only need the boolean.

**has_forced_workflow()** (private, static)
Whether a published AutomateWoo workflow named exactly "Wicket: Force Subscription Auto-Renewal" exists. Detects the documented exception where a site bypasses WCS's standard renewal-payment gate. Result is cached in the `wicket_mship_has_forced_autorenew_workflow` transient (1 hour), cleared on `save_post_aw_workflow` / `trashed_post` — see `clear_forced_workflow_cache()`. See `atlas/quirks/automatewoo-forced-autorenew-workflow.md`. Private: only called internally by `resolve_status()`; verify the exception via `resolve_status()`'s output, not directly.

**clear_forced_workflow_cache()** (static)
Deletes the `wicket_mship_has_forced_autorenew_workflow` transient. Hooked to `save_post_aw_workflow` and `trashed_post`. Public because WordPress hook callbacks must be publicly callable.
