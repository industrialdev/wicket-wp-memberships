Client Question 1) Looking at changing the grace period copy for affiliate memberships right now, but I probably need to review all the callout copy for potential edits as I don't believe the copy that was dropped in has been reviewed/approved by CITT staff.

Support Query: Describe how to manage callout content for grace period and other renewal type callouts appearing in the account center

Prompt: Describe how to manage callout content for grace period and other renewal type callouts appearing in the account center. Use the guidance in docs/AgentFiles/DocAgent.md and knowledge in docs/AgentFiles/integrations-index/ac-memebrship-callout-combined-doc.md and put the answer in docs/AgentFiles/DocAgentSupportQuery.md

-----------------------------------------------

## Answer

Callout text in the Account Centre is managed in two places depending on the callout type. All text is stored per language, so if the site supports multiple languages each language has its own copy fields.

---

### Grace Period Callouts

When a membership has passed its end date but the member is still within the grace window, a **red** callout card appears. The text shown comes entirely from the **Membership Config** assigned to that member's tier:

- **Header** — the first line of the callout card
- **Body content** — the main message beneath the header
- **Button label** — the text on the renewal action button

To edit grace period copy, go to **Membership Config → Grace Period Callout** and update the Header, Content, and Button Label fields for each language used on the site.

---

### Early Renewal (Renewal Window) Callouts

When a membership is approaching its end date and falls within the configured renewal window, a **yellow/amber** callout appears. This text also comes from the **Membership Config**:

- **Header** — Membership Config → Renewal Window Callout Header
- **Body content** — Membership Config → Renewal Window Callout Content
- **Button label** — Membership Config → Renewal Window Callout Button Label

To edit early renewal copy, go to **Membership Config → Renewal Window Callout** and update the fields.

---

### Pending Approval Callouts

When a member has applied for a membership and it is awaiting admin approval, a **green** callout appears under the "Become Member" block. This text comes from the **Membership Tier** (not the Config):

- **Header** — Tier → Approval Callout Header
- **Body content** — Tier → Approval Callout Content
- **Button label** — Tier → Approval Callout Button Label
- **Contact email** — Tier → Approval Email (populates the subject line of a pre-filled contact link)

To edit pending approval copy, go to the relevant **Membership Tier → Callout Configuration** and update those fields.

---

### Key Points When Reviewing All Callout Copy

| Callout Type | Card Colour | Where to Edit |
|---|---|---|
| Early Renewal | Yellow / amber | Membership Config → Renewal Window Callout |
| Grace Period | Red | Membership Config → Grace Period Callout |
| Pending Approval | Green | Membership Tier → Approval Callout |

- Each Membership Config may be shared across multiple tiers. Editing copy on a Config changes the callout for **all tiers using that Config** — check which tiers are assigned before saving.
- If tiers need different callout text, they need separate Membership Configs.
- All text fields are per-language. Make sure to update copy for every active language (e.g., English and French) to avoid one language showing placeholder or old copy.
- Changes to callout text in a Config or Tier take effect immediately — no cache clearing or republishing is required.

---

### Related Documentation

- [docs/options-index/membership-config.md](../../docs/options-index/membership-config.md) — Full reference for all Membership Config settings including all callout text fields
- [docs/options-index/membership-tier.md](../../docs/options-index/membership-tier.md) — Tier settings including approval callout fields
- [docs/integrations-index/ac-memebrship-callout-combined-doc.md](../../docs/integrations-index/ac-memebrship-callout-combined-doc.md) — Complete reference for how callout data is built and displayed in the Account Centre

-----------------------------------------------
How to Track incoming questions to Transform to Standard Support Query:

This is a prompt engineering challenge — you want to teach the agent to recognize semantic similarity between a client's natural language question and a canonical support query, then route accordingly.

Here are a few approaches depending on your setup:

**Option 1: Few-shot examples in the system prompt**

The most direct method. Show the agent explicit mappings:

```markdown
## Query Resolution Examples

When a client asks something similar to the examples below, run the associated Support Query against the documentation.

---

Client Question: "Looking at changing the grace period copy for affiliate memberships... need to review all callout copy for potential edits"
Support Query: "Describe how to manage callout content for grace period and other renewal type callouts appearing in the account center"
Reasoning: Client is asking about editing UI copy for membership callouts → maps to callout content management docs.

---

[Add more examples below in the same format]
```

This works well when you have a small, curated set of known mappings.

**Option 2: Intent extraction step**

Instruct the agent to first extract intent before querying docs:

```markdown
## Step 1 — Classify the client's intent
Before searching documentation, restate the client's question as a precise, documentation-friendly query. 
Strip names, opinions, and uncertainty. Focus on the functional task they need help with.

Example:
- Raw: "I think maybe the grace period copy hasn't been reviewed and might need changing?"
- Extracted: "How to manage callout content for grace period and renewal type callouts in the account center"

## Step 2 — Run the extracted query against the docs
Use the extracted query (not the raw client question) to retrieve relevant documentation.
```

This is more flexible than hardcoded mappings because it generalizes to novel questions.

**Option 3: Hybrid — extraction with canonical anchors**

Combine both: extract intent freely, but anchor to a known query if the extracted intent closely matches one:

```markdown
After extracting intent, check if it matches a known support query from the list below.
If it does, use the canonical query exactly — it has been optimized for the docs.
If it doesn't match, use your extracted query.

Known Support Queries:
- "Describe how to manage callout content for grace period and other renewal type callouts appearing in the account center"
- [others...]
```

**Which to use?**

- Small, predictable query set → Option 1 or 3
- Broad, open-ended client questions → Option 2
- Production system where accuracy matters → Option 3, with Option 2 as fallback

The key principle in all cases is to give the agent an explicit intermediate step — transforming informal client language into the structured form your docs are indexed against — rather than expecting it to bridge that gap implicitly.

=================================================

Client Question 2) Eduardo currently is an active member however the 'membership under review' call out remains in the profile. Colleague from same organization Bernadette-- profile does not have the call out. Please advise.

Support Query: Why would a Pending Approval callout continue to appear in the account center for a user with a cuurently active membership.

-----------------------------------------------

## Answer (1st Attempt)

The Pending Approval callout is rendered by the **"Become Member"** block mode — a block specifically designed for users who do not yet have an active membership. According to the documented behaviour, this block **hides entirely as soon as the user has any active membership**. If the callout is persisting for Eduardo despite him being an active member, one of the following conditions is the cause.

---

### Most Likely Cause: Orphaned Pending Membership Record - WRONG REASONING - LEAST LIKELY

When a membership is approved, a new **Active** membership record is created. If the original **Pending** record was not properly closed out at the same time, both records co-exist on the member's account. The plugin queries for memberships with any of these statuses: Active, Delayed, Grace, and **Pending**. It will find the orphaned Pending record and place it in the `pending_approval` response list, which the Become Member block then renders.

This is consistent with Eduardo's colleague Bernadette not having the issue — her approval workflow completed cleanly with no leftover Pending record.

**To confirm and resolve:**
1. Go to **Memberships → Individual Member Management** and look up Eduardo's account.
2. In the Membership Records list, check whether there is a record with a **Pending** status alongside his Active record.
3. If a stale Pending record exists, click to expand it, open **Manage Status**, and change the status to **Cancelled** (or the appropriate terminal state). This removes it from the plugin's query results and the callout will stop appearing.

Correction: New Active Memberships ARE NOT CREATED on approval. The existing Membership Record is UPDATED TO ACTIVE status. The condition identified in this response would produce the issue identified, but is least likey to be the cause. 
---

### Second Cause: A New Pending Application Submitted While Active - POTENTIAL ISSUE - NEEDS TO BE INVESTIGATED

Eduardo may have submitted a new membership application (e.g., applying for a different tier, or re-applying) while his existing membership was already active. That new application is sitting in Pending status and is being returned by the plugin in the `pending_approval` list.

The Become Member block is documented to hide for any user with an active membership. If it is still showing the pending callout, it suggests the block is evaluating the pending WooCommerce order state before fully resolving the active Wicket membership check — resulting in the pending callout being rendered despite the active membership.

**To confirm:** Check whether Eduardo has a WooCommerce order in **Pending** or **Processing** status for a membership product placed after his current membership was approved. If so, that order is driving the callout. The resolution depends on intent — if it is a duplicate or accidental application, cancel the order and the corresponding Pending membership record.

---

### Third Cause: WooCommerce Order Status Not Resolved - MOST LIKELY - NEEDS TO BE INVESTIGATED

The Become Member block also checks WooCommerce order status directly when determining whether to show the pending approval state. If Eduardo has a WooCommerce order that was placed for a membership requiring approval but the order status was never moved to **Completed** (even after the Wicket membership was manually approved by an admin outside the normal order flow), the block may still see an unresolved pending order.

**To confirm:** Open Eduardo's WooCommerce order history and check for any membership-related orders that are not in a Completed or Cancelled state.

---

### Why Bernadette Is Not Affected

Bernadette is in the same organization but does not have the callout. This is consistent with all three scenarios above — her membership approval workflow completed cleanly, leaving no orphaned Pending record and no unresolved WooCommerce order. The callout is user-specific, not organization-wide.

CORRECTION: There is unlikely to be an prphaned record following the expected workflow - approved memberships are updated to active status - no new membership is created through this workflow.

---

### Summary: Recommended Admin Steps

| Step | What to Check | Where |
|---|---|---|
| 1 | Look for a Pending membership record alongside the Active one | Memberships → Individual Member Management → Eduardo's account |
| 2 | If found, cancel the Pending record via Manage Status | Same page, expand the Pending record |
| 3 | Check for unresolved WooCommerce orders for membership products | WooCommerce → Orders → filter by Eduardo's email |
| 4 | If a pending order exists that should be closed, update it to Completed or Cancelled | WooCommerce order detail page |

Once the orphaned Pending record or unresolved order is cleared, the Become Member block will detect only the Active membership and the callout will stop appearing. No config changes, no cache clearing required — the change takes effect immediately on the next page load.

-----------------------------------------------

## Answer (Revised — Generated as if Doc Improvements Were Already in Place)

The Pending Approval callout is rendered by the **"Become Member"** block. It has two independent mechanisms that can each trigger the callout, and both must be resolved for the callout to disappear.

---

### How the Pending Approval Callout Is Triggered

**Mechanism A — Plugin membership record status:**
The plugin's `get_membership_callouts()` reads the WordPress membership post meta status. If the status is `pending`, the membership is placed into the `pending_approval` response and the Become Member block renders the callout. This resolves when the post meta status is updated to `active`.

**Mechanism B — Block's direct WooCommerce check:**
The Become Member block also independently checks WooCommerce for membership orders or subscriptions in a non-resolved state — specifically subscriptions in `on-hold` status, or orders that have not reached a completed state for a tier with **Approval Required** enabled. It renders the pending callout regardless of what the Wicket membership record says.

These two checks are independent. A membership can appear Active on the Wicket/WordPress side and still trigger the callout through Mechanism B if the WooCommerce side has not been fully resolved.

---

### Most Likely Cause: Membership Approved Outside the Plugin UI

When an admin activates a membership directly via the **Wicket MDP** (rather than through the WordPress plugin's admin interface — **Memberships → Individual Member Management → Manage Status**), only the MDP record is updated. The plugin's approval workflow — which updates the WordPress post meta status to `active` and sets the WooCommerce subscription from `on-hold` to `active` — is **not triggered**.

This leaves Eduardo's account in a split state:

| Component | Expected State After Approval | Actual State (Out-of-Band) |
|---|---|---|
| Wicket MDP record | Active | Active |
| WordPress membership post meta | active | still `pending` → Mechanism A fires |
| WooCommerce subscription | active | still `on-hold` → Mechanism B fires |

**Why Bernadette is not affected:** Her membership was approved through the plugin UI, or her tier does not require approval. All components were updated through the full workflow.

**Resolution:** Go to **Memberships → Individual Member Management**, expand Eduardo's Pending membership record, and use **Manage Status → Active**. This triggers the full plugin approval workflow: the post meta is updated to `active`, the WooCommerce subscription moves from `on-hold` to `active`, and membership dates are calculated and saved. Both Mechanism A and Mechanism B are cleared in one action.

---

### Second Possible Cause: WooCommerce Order Left On-Hold After Plugin-UI Approval

When the plugin's approval UI runs correctly, it updates the WooCommerce **subscription** to `active` — but it does not update the WooCommerce **order** status. If the original purchase order was placed in `on-hold` (common when WooCommerce holds subscriptions pending approval), that order status is not changed by the approval flow.

If Mechanism B checks WooCommerce order status (not just subscription status), the on-hold order continues to trigger the callout even though the subscription and membership record are both Active.

**To confirm and resolve:** Open WooCommerce → Orders, filter by Eduardo's email, and look for any membership-related order in **On-Hold**, **Pending Payment**, or **Processing** status. If found, update it to **Completed**. The callout stops on the next page load.

---

### Third Possible Cause: A Second Pending Application

Eduardo may have submitted a new membership application (same or different tier) while his existing membership was already active. This creates a second record with `pending` status, which Mechanism A picks up. The Become Member block should hide for any user with any active membership — but if a WooCommerce state mismatch (Causes 1 or 2) is also present, the combined effect keeps the callout visible.

**To confirm:** Check Eduardo's membership list in Individual Member Management for two records — one Active and one Pending. If the Pending record is a new application that should be cancelled, use **Manage Status → Cancelled** to remove it.

---

### Diagnostic Steps in Priority Order

| Step | What to Check | Where |
|---|---|---|
| 1 | Is Eduardo's WordPress membership post meta status `pending`? | Memberships → Individual Member Management → Status column |
| 2 | If Pending: use Manage Status → Active (triggers full approval workflow) | Same page, expand the record |
| 3 | Is there a WooCommerce order in On-Hold or Pending Payment status? | WooCommerce → Orders, filter by Eduardo's email |
| 4 | If found: update the order to Completed | WooCommerce order detail |
| 5 | Is there a second Pending membership record (duplicate application)? | Individual Member Management |
| 6 | If found and unwanted: cancel it via Manage Status | Same page |

No cache clearing or config changes are required. Changes take effect immediately on Eduardo's next page load.