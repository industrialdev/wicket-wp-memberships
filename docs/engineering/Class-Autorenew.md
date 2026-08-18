---
title: "Autorenew Class Reference"
audience: [developer]
php_class: Autorenew
source_files: ["includes/Autorenew.php"]
---

# Autorenew Class Index

**File:** includes/Autorenew.php

## Methods

- `resolve_status($membership)` (static)
- `is_autorenewing($membership)` (static)

## Method Descriptions

**resolve_status($membership)** (static)
Single source of truth for whether a membership's linked subscription will actually auto-renew, and why not when it won't. Checks subscription status, the manual-renewal flag, staging/duplicate-site detection, and payment-method presence. Returns `['result' => bool, 'reason' => string|null]` — `reason` is a plain-English sentence (not a code), ready to display as-is. See [`autorenew-status.md`](autorenew-status.md) for full details and local testing steps.

**is_autorenewing($membership)** (static)
Bool-only wrapper around `resolve_status()`, kept for callers that only need the boolean.
