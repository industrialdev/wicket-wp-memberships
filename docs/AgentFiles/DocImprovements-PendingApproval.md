---
name: Doc Improvements — Pending Approval Callout Persists After Activation
description: Identifies gaps in the existing docs that caused an incorrect support answer for the scenario where a Pending Approval callout persists for an already-active member.
type: project
---

# Doc Improvements: Pending Approval Callout Persists After Activation

This file identifies the gaps in the current documentation that resulted in an inaccurate first-attempt answer to Client Question 2 (Eduardo Fonseca Arraes — Pending Approval callout persisting despite active membership). For each gap, a specific improvement is described.

---

## Gap 1 — No Approval Workflow Document Exists

**Problem:**
The workflow-index contains documents for grace period, renewal window, renewal types, and multi-tier renewal — but there is no document for the **approval workflow**. The existing docs describe _what_ the pending approval callout looks like and _where_ its text is configured, but they do not describe the end-to-end approval process: what happens at each step, on each system component.

**Why this mattered:**
Without a workflow doc, it was impossible to reason correctly about what state each component (Wicket membership record, WooCommerce subscription, WooCommerce order) is in at each stage of the approval lifecycle, and what gets updated vs. left unchanged when an admin approves.

**Improvement: Create `docs/workflow-index/approval-workflow.md`**

This document should cover:

1. **At purchase (for an approval-required tier):**
   - A local WordPress membership record is created with status `pending`
   - The MDP (Wicket) record is **NOT created yet** — it is deferred until approval
   - The WooCommerce subscription is set to status `on-hold`
   - The WooCommerce order completes normally (payment received)
   - An approval notification email is sent to the tier's Approval Email Recipient

2. **At approval (via the plugin's admin UI — Individual Member Management → Manage Status → Active):**
   - The WordPress membership post meta status is updated to `active`
   - Membership dates (Start, End, Expiration) are calculated from the Membership Config and written to the record
   - The MDP record is created at this point
   - The WooCommerce subscription status is updated to `active`
   - The WooCommerce ORDER status is **not changed** — it retains whatever status WooCommerce set it to at purchase

3. **What is NOT updated and why it matters:**
   - The WooCommerce ORDER is not touched during the approval workflow. If the original order was placed in an `on-hold` state (common for subscriptions awaiting approval), it remains on-hold after the membership is approved. The Become Member block independently checks WooCommerce order status, so a stale on-hold order can cause the pending approval callout to keep appearing.

4. **Out-of-band approval (approving via Wicket MDP, not the plugin UI):**
   - If an admin activates the membership directly in the Wicket MDP instead of using the plugin UI, only the MDP record status is updated.
   - The WordPress membership post meta status remains `pending`.
   - The WooCommerce subscription remains `on-hold`.
   - The plugin's `get_membership_callouts()` reads the WordPress post meta — it will still return this membership in `pending_approval` even though the MDP shows it as active.
   - Resolution: Use the plugin UI to set the status to Active, which triggers the full local + WooCommerce update.

---

## Gap 2 — Become Member Block's Dual-Check Mechanism Is Not Clearly Documented

**Problem:**
The combined callout doc (`ac-memebrship-callout-combined-doc.md`) and the integration docs describe the plugin's `get_membership_callouts()` data pipeline in detail, and separately describe the block "hiding when the user has an active membership." But they do not clearly state that the Become Member block has **two independent mechanisms** that can each trigger the pending approval callout:

- **Mechanism A** — The plugin's `pending_approval` response (driven by WordPress membership post meta status = `pending`)
- **Mechanism B** — The block's own direct WooCommerce order/subscription check (independent of the Wicket membership record status)

Because of this, the first-attempt answer incorrectly assumed that once the membership record is Active, the plugin API would return no `pending_approval` entries and the callout would stop. It missed that Mechanism B can still fire independently.

**Improvement: Add to `ac-memebrship-callout-combined-doc.md` — Become Member mode section**

Under **Mode 1: Become Member → Pending Approval sub-state**, add a subsection:

> **Two independent checks can trigger the Pending Approval callout**
>
> The Pending Approval callout is triggered by whichever of the following is true first:
>
> 1. The plugin's `get_membership_callouts()` returns a `pending_approval` entry — this happens when the WordPress membership post meta status is `pending`.
> 2. The block's own direct WooCommerce check finds a membership order/subscription in a non-resolved state (`on-hold` subscription, or an order that has not been moved to a completed status) for a tier with `Approval Required` enabled.
>
> These two checks are independent. A membership can be Active in the Wicket/WordPress record but still trigger the callout via check #2 if the WooCommerce side has not been fully resolved. This most commonly occurs when:
> - The WooCommerce ORDER was left in `on-hold` status at purchase and not updated after approval
> - The membership was approved outside the plugin UI (via MDP), leaving the WooCommerce subscription in `on-hold`

---

## Gap 3 — "Hides for Active Members" Is Ambiguous About Tier Scope

**Problem:**
The documentation states: "Hides when: The user gains any active membership." It is not stated whether "any active membership" means:
- Any active membership of any tier, OR
- Only an active membership of the **same tier** as the pending application

This ambiguity matters when a user submits a new pending application for a different (additional) tier while already holding an active membership in another tier. In that scenario, the block might correctly show the pending approval callout for the new tier without hiding, because the user's active membership is a different tier.

**Improvement: Clarify in `ac-memebrship-callout-combined-doc.md` and `acc-callout-memberships-info.md`**

Update the "Hides when" note to:

> **Hides when:** The user has an active membership of **any** tier. If the user is actively applying for a second tier while already holding an active membership, the Become Member block still hides — including its pending approval sub-state — because any active membership suppresses it. A user in this situation would need admin-side visibility (via Individual Member Management) rather than Account Centre callouts to track their pending additional tier application.

(If the actual block behaviour differs from this — i.e., it evaluates per-tier — the doc should state that explicitly with a clear explanation of when each applies.)

---

## Gap 4 — No Troubleshooting Document for Callout State Mismatches

**Problem:**
There is a `docs/troubleshooting-index/` directory with one document (organization renewal missing seats). There is no troubleshooting document covering callout display mismatches — situations where what a member sees in the Account Centre does not match their actual membership status.

**Improvement: Create `docs/troubleshooting-index/pending-approval-callout-persists.md`**

This document should cover the specific scenario: "A member's Pending Approval callout continues to appear in the Account Centre even though their membership is now active."

It should include:
- Root cause explanation (dual-check mechanism, WordPress/WooCommerce sync)
- Step-by-step diagnostic checklist
- Specific resolution steps depending on which cause is found
- Reference to the approval workflow doc for context

---

## Summary of Proposed New/Updated Files

| Action | File | Content Added |
|---|---|---|
| **Create** | `docs/workflow-index/approval-workflow.md` | End-to-end approval flow including what each system component's state is at each step, and what the out-of-band approval scenario looks like |
| **Update** | `docs/integrations-index/ac-memebrship-callout-combined-doc.md` | Clarify the dual-check mechanism (plugin API vs. direct WooCommerce check) under Become Member → Pending Approval sub-state |
| **Update** | `docs/integrations-index/acc-callout-memberships-info.md` | Same dual-check clarification; also clarify "any tier" vs "same tier" for the hide condition |
| **Create** | `docs/troubleshooting-index/pending-approval-callout-persists.md` | Diagnostic and resolution steps for callout persisting after membership activation |
