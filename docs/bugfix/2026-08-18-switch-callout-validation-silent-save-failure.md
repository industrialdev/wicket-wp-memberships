# Tier form: callout validation failed silently

**Date:** 2026-08-18
**Branch:** `users-multi-tier-renewal-subscriptions-merged-transfer-switch-order`
**Component:** `includes/Membership_Post_Types.php`, `frontend/src/membership_tiers/edit.js`
**Feature:** Callout Configuration modals — Self-Serve Membership Switch and Approval

## Symptom
With **Enable Self-Serve Membership Switch** checked and the Callout Configuration modal
left incomplete for one or more WPML languages, saving the tier appeared to do nothing:
the button flipped to "Saving now...", the page stayed put, and no error appeared. The
tier was not saved.

The **Approval** callout carried the identical defect on its own path (reached when
`approval_required` or `renew_approval_required` is set) and was fixed alongside it.

## Root cause
Two independent defects, both on the failure path.

1. **The admin form could not read the error it was sent.**
   `handleSubmit`'s `.catch()` did `Object.keys(error.data.params)` with no guards. Any
   rejection that is not a well-formed REST validation body — a 500, a PHP warning
   printed ahead of the JSON, a dropped connection — has no `error.data`, so the
   handler itself threw a `TypeError`. That rejection went unhandled, which meant
   `setErrors()` and `setSubmitting(false)` never ran: no notice, and a form stuck
   in its submitting state.

2. **The REST validator could produce exactly that malformed response.**
   Both locale checks indexed straight into
   `$value['<...>_callout_data']['locales'][$lang]['callout_header']`. A tier saved before
   self-serve switching carries no `switch_callout_data` key at all, and either callout's
   `locales` can be missing a WPML language added after the tier was last saved — so with
   `WP_DEBUG` display on, PHP emitted undefined-key warnings ahead of the JSON body and
   corrupted it, feeding defect 1.

The messages were also unusable even when they did render: one sentence each
("The switch callout data must not be empty." / "The approval callout data must not be
empty."), with no indication of which language or which of the three fields was short.

Separately, `validateApprovalCallout()` on the frontend was a stub — it built an empty
error array, set it, and returned `true` unconditionally, so the approval modal could
always be closed while incomplete.

## Fix
- `includes/Membership_Post_Types.php` — both callout locale checks now read
  `<...>_callout_data.locales` defensively (`isset` + `is_array`, `trim()` on each
  field), and accumulate one entry per missing field. The errors name them:
  `The Self-Serve Switch callout configuration is incomplete, missing: Callout Header [EN], Button Label [FR]`
  and the equivalent `The Approval callout configuration is incomplete, missing: ...`.
- `frontend/src/membership_tiers/edit.js`
  - `.catch()` tolerates any rejection shape, falls back to `error.message` or a
    generic string when there is no `data.params`, always clears `isSubmitting`, and
    scrolls the notice row into view.
  - New `validateSwitchCallout()` and `collectApprovalCalloutIssues()` mirror the REST
    rules client-side and block submit with the same per-language messages — no round
    trip needed to learn what is missing. The approval pre-check runs only when
    `approval_required || renew_approval_required`, matching the REST condition.
  - `validateApprovalCallout()` is no longer a stub: it delegates to
    `collectApprovalCalloutIssues()`. Its existing call site and `boolean` return are
    unchanged; it now takes the source form as an argument.
  - Both Callout Configuration modals now render their own notices and stay open when
    incomplete. Each commits what was typed to `form` either way, so partial copy is
    never lost, and each clears stale notices when reopened.
  - New `getApprovalCalloutLocale()` guards the approval modal's three value reads,
    mirroring the existing `getSwitchCalloutLocale()`. Previously a WPML language added
    after the tier was last saved threw while rendering the modal.
- `frontend/build/membership_tier_create.js` rebuilt (`npx wp-scripts build`).

## Blast radius
- **The hardened `.catch()` is shared by every tier save**, not just switch-enabled
  ones. Behaviour on a well-formed 400 is unchanged (same `params` split, same
  notices), with two additions that apply to all failures: duplicate messages are
  deduped, and the page scrolls to the top. Previously-invisible failures (500s,
  network errors) now surface a message where they used to show nothing.
- The new client-side gates run **only** under the same conditions the REST route
  enforces: `self_serve_switch_enabled` for the switch, `approval_required ||
  renew_approval_required` for approval. Tiers with both off reach the REST call
  exactly as before.
- Both backend changes stay inside their existing conditional blocks. The error codes
  (`rest_invalid_param_switch_callout_data`, `rest_invalid_param_approval_callout_data`)
  and HTTP status (400) are unchanged; only the message text is.
- The approval modal is the older, widely-used path. Its submit handler already called
  `validateApprovalCallout()` and already rendered `approvalCalloutErrors` — that wiring
  was in place and unused. The change fills the stub behind it; no control flow was added
  to the handler. The visible consequence: **a tier that could previously be saved with
  incomplete approval callout copy now cannot.** That was already the REST route's rule —
  it just used to fail invisibly — so any existing tier in that state will now report why
  on the next save.

## Not verified
Not exercised against a running site. The per-language message text and the modal
notices need a manual pass on a WPML install with two or more active languages.
