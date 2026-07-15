---
title: "PHP Documentation Standards"
audience: [developer]
---

# PHP Documentation Standards

These rules exist to ensure developer clarity and understanding — so any engineer reading the code can quickly grasp intent, constraints, and non-obvious decisions without needing to trace through git history or ask the original author.

These rules apply to **every PHP file touched** — no exceptions, including single-line or trivial changes. No change is too small to be exempt. Skip only when explicitly told not to document in the current task.

## Before editing any PHP file — checklist

1. PHPDoc on every touched method (add if missing, update if signature changed).
2. Inline comments **must** be added to non-obvious logic within touched functions — prioritize clarifying *why* the code does what it does, not *what* it does.
3. These apply even to single-line variable renames or small tweaks.
4. When adding documentation only (PHPDoc, inline comments), never alter existing logic — formatting changes are allowed but no functional code changes.

## PHPDoc Blocks

Every function or method you **create or modify** must have a complete PHPDoc block. Existing functions that lack one must have one added when touched.

```php
/**
 * Brief one-line description ending with a period.
 *
 * Longer explanation when behaviour is non-obvious. Include side-effects,
 * WP hooks fired, external API calls made, or caveats a caller needs to know.
 *
 * @param  string   $membership_id  The Wicket membership UUID.
 * @param  int      $user_id        WP user ID; defaults to current user when 0.
 * @param  bool     $force_refresh  Bypass transient cache and re-query MDP.
 *
 * @return WP_Error|array  Membership data array on success, WP_Error on failure.
 */
```

**Required tags** (include every one that applies):

| Tag | When required |
|-----|--------------|
| `@param` | Every parameter, with type and description |
| `@return` | Always, even `void` functions — write `@return void` |
| `@throws` | When an exception can be thrown |
| `@since` | When adding a new public function |
| `@see` | When behaviour depends on another function or WP hook |
| `@global` | When accessing a WP global (`$wpdb`, `$post`, etc.) |

**Type syntax**: Use union types for nullable (`string|null`). Use `array<int, Membership>` or `string[]` when array shape is known. Use `WP_Error|TypeName` for WP error returns.

Descriptions must add information beyond the parameter name. `@param int $id The id.` is not acceptable.

## Inline Comments

Add a short inline comment wherever a reader would need to pause to reason through the code. Target the **goal** of the logic, not a restatement of what the code does.

**Correct** — states the objective of a multi-condition check:
```php
// Confirm the user holds an active, non-expired membership before granting access.
if ( $membership && 'active' === $membership['status'] && ! $membership['expired'] ) {
```

**Incorrect** — restates the code:
```php
// Check if membership exists, status equals active, and expired is false.
if ( $membership && 'active' === $membership['status'] && ! $membership['expired'] ) {
```

**Other situations that require an inline comment:**
- Non-obvious fallback or default value and the reason it exists.
- A WP quirk or third-party API limitation being worked around.
- Why a specific meta key or field is used instead of an obvious alternative.
- Branches that mirror a real-world business rule (grace-period logic, bundle vs. individual membership paths, calendar vs. anniversary cycle handling).
- Code written in a surprising way to avoid a known bug.

One short line is almost always enough. No paragraph-length comment blocks inside function bodies.

**Do not comment:**
- Code that already reads clearly from well-named identifiers.
- Standard WP boilerplate any WP developer recognises.
- The current task, PR number, or issue reference (those belong in the commit message).
