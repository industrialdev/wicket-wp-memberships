
DocAgentSupportQuery.md
Why would a Pending Approval callout continue to appear in the account center for a user with a cuurently active membership. Use the guidance in docs/AgentFiles/DocAgent.md and knowledge in docs/AgentFiles/integrations-index/ac-memebrship-callout-combined-doc.md and other doc files available put the answer at the end of docs/AgentFiles/DocAgentSupportQuery.md after the support query line.
Read DocAgentSupportQuery.md
Read DocAgent.md
Read ac-memebrship-callout-combined-doc.md
Globpattern: "docs/AgentFiles/**/*.md"
Found 4 files
Globpattern: "docs/**/*.md"
Found 34 files
Read ac-memebrship-callout-combined-doc.md
Now let me check the integration docs and the membership controller to understand how pending approval is evaluated alongside active memberships.

Read ac-callout-memberships.md
Read acc-callout-memberships-info.md
Let me also check the workflow docs and member management docs for more context on the pending state.

Read individual-member-management.md
Read membership-tier.md
Now I have enough to write the answer. Let me append it to the support query doc.

Edit DocAgentSupportQuery.md
Added 56 lines
The answer has been added to DocAgentSupportQuery.md after the support query line.

Summary of the answer:

The Pending Approval callout lives inside the "Become Member" block, which is designed to hide the moment a user has any active membership. If it's persisting for Eduardo, there are three ranked causes:

Orphaned Pending record (most likely) — When his membership was approved, a new Active record was created but the original Pending record was never closed out. The plugin queries for both Active and Pending status records simultaneously, finds the stale Pending one, and the Become Member block renders it. Fix: go to Individual Member Management, find the Pending record, and cancel it via Manage Status.

New application submitted while already active — Eduardo submitted a second membership application after already being active, creating a second Pending record for a different (or the same) tier.

Unresolved WooCommerce order — The block also checks WooCommerce order status directly. An order stuck in a non-Completed state can continue triggering the callout.

The colleague Bernadette not having the issue is consistent with all three scenarios — her workflow completed cleanly with no leftover records.

DocAgentSupportQuery.md
Client Question 2 was not answered accurately
- please review corrective comments I added in the file docs/AgentFiles/DocAgentSupportQuery.md
- produce a single NEW DOC FILE identifying how you would improve the supporting docs we previously created to improve your answer
- DO NOT CHANGE ANY EXISTING DOCS
- put your new revised answer under Answer (1st Attempt) generated entirely from the existing docs as if they already included the changes/updates in the NEW DOC FILE 
- DO NOT MAKE CHANGES TO EXISTING DOCS OR RESULTS simply redo the analysis so we can compare the results
Read DocAgentSupportQuery.md (from line 144)
Let me look at the code to understand the actual approval workflow and how the Become Member block evaluates pending orders vs. active membership.

Agent:Research approval workflow and Become Member block logic
IN
I need to understand two things about the wicket-wp-memberships plugin codebase at /home/gpomer/Development/1Wicket/MembershipTest/wicket-memberships-test/src/web/app/plugins/wicket-wp-memberships:

1. **How does the membership approval workflow work?** Specifically: when an admin approves a pending membership, does it CREATE a new Active membership record, or does it UPDATE the existing Pending record to Active status? Look for approval-related code in controllers, hooks, or REST API handlers.

2. **How does the AC Callout block (Become Member mode) determine whether to show the Pending Approval callout vs. hiding for active members?** Specifically: what does it check — does it check WooCommerce order status, Wicket membership status from the plugin API, or both? What is the order of these checks? Look for the JavaScript/PHP that powers the "Become Member" block mode.

3. **What WooCommerce order statuses cause the pending approval check to return true?** What order status(es) does the block treat as "pending approval"?

Please search thoroughly — look in src/blocks/, includes/, src/js/, and any relevant PHP controllers. Focus on finding the actual logic, not just guessing from names. Report back with specific file paths and line numbers for key logic.
Now let me look at the actual block code and Admin_Controller approval flow to understand what gets updated and what doesn't.

Read Admin_Controller.php (lines 101-220)
Globpattern: "src/blocks/**/*.js"
No files found
Globpattern: "**/*.js"
Found 1 file
Grep "pending_approval|pending.*approval|approval.*pending|wc_order|order_status|get_order" (in /home/gpomer/Development/1Wicket/MembershipTest/wicket-memberships-test/src/web/app/plugins/wicket-wp-memberships/includes/Membership_Controller.php)
145 lines of output
Now I have enough to produce both outputs. Let me write them.

Globpattern: "docs/workflow-index/*.md"
Found 6 files
Read Membership_Controller.php (lines 641-690)
Now I have the full picture from the code. Let me write both outputs simultaneously.

Write DocImprovements-PendingApproval.md
122 lines
---
name: Doc Improvements — Pending Approval Callout Persists After Activation
description: Identifies gaps in the existing docs that caused an incorrect support answer for the scenario where a Pending Approval callout persists for an already-active member.
type: project

Now write the revised answer in the support query file:

Read DocAgentSupportQuery.md (from line 201)
Edit DocAgentSupportQuery.md
Edit failed
Read DocAgentSupportQuery.md (from line 196)
Edit DocAgentSupportQuery.md
Added 69 lines
Both files are done. Here's what was produced:

---
Doc Correction File Written: docs/AgentFiles/DocImprovements-PendingApproval.md
---

Identifies four doc gaps that caused the wrong first answer:

No approval workflow doc exists — the workflow-index has grace period, renewal window, multi-tier, etc. but nothing describing the approval lifecycle. A new docs/workflow-index/approval-workflow.md is needed to document what each component (post meta, WooCommerce subscription, WooCommerce order, MDP record) looks like at each stage — including the critical fact that the WooCommerce ORDER is never touched by the approval flow.

The Become Member block's dual-check mechanism is undocumented — the combined callout doc only describes the plugin's pending_approval data pipeline (Mechanism A). It doesn't document that the block ALSO independently checks WooCommerce order/subscription state (Mechanism B). Without knowing about Mechanism B, the answer assumed the callout would stop once the membership record was Active.

"Hides for active members" is ambiguous about tier scope — the docs don't say whether "any active membership" means any tier or only the same tier as the pending application.

No troubleshooting doc for callout state mismatches — a docs/troubleshooting-index/pending-approval-callout-persists.md is needed.

Revised answer added to DocAgentSupportQuery.md after the 1st Attempt block:

The revised answer leads with the correct most-likely cause — out-of-band approval via the MDP bypassing the plugin UI, leaving the WordPress post meta as pending and the subscription as on-hold. Both Mechanism A (plugin API check) and Mechanism B (block's direct WooCommerce check) fire simultaneously. Resolution is a single action: use Manage Status → Active in the plugin UI, which triggers the full workflow and resolves both sides.

