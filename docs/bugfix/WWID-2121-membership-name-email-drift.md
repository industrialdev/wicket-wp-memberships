---
title: "WWID-2121 — Membership Name/Email Drift from MDP Profile Updates"
audience: [developer, agent]
status: implemented — see Implementation log
ticket: WWID-2121
php_class: Utilities
source_files: ["includes/Utilities.php", "includes/Membership_Controller.php", "includes/Admin_Controller.php", "includes/Helper.php", "wicket.php"]
---

# WWID-2121 — Membership Name/Email Drift from MDP Profile Updates

## Summary

When a person changes their email or name in the MDP, the MDP pushes the change into
WordPress and the WP user record is updated correctly. The membership plugin never hears
about it, so the name and email it cached on the membership post — and in three JSON
copies of that data on the user, the parent order, and the subscription — keep the values
they had at purchase time. Admin search stops matching, the member edit page shows the old
name, and one cleanup guard silently stops working.

This document describes the correction and, in the [Implementation log](#implementation-log),
records what was built and where it diverged from the design.

## How the MDP change arrives

The MDP does not call a Wicket-specific webhook. It calls WooCommerce's own customer
endpoint, `PUT wc/v3/customers/{id}`. The base plugin's only participation is one filter
that makes this legal for Wicket-created accounts:

`wicket-wp-base-plugin/includes/integrations/org-search-select-woocommerce.php:10-16` adds
the `user` role to `woocommerce_rest_customer_allowed_roles` — the comment on it reads
"for mdp user sync". Without that filter WooCommerce refuses email edits on these accounts
(`class-wc-rest-customers-v1-controller.php:230-248`).

The resulting write chain, with every hook it fires:

```
PUT wc/v3/customers/{id}
  │
  └─ WC_REST_Customers_V1_Controller::update_item()        :513
       │  set_email(), update_customer_meta_fields()
       └─ WC_Customer::save() → WC_Customer_Data_Store::update()   :191
            ├─ wp_update_user( ID, user_email, display_name )
            │     └─ fires  profile_update ( $user_id, $old_user_data )
            ├─ update_user_meta( first_name, last_name )
            └─ fires  woocommerce_update_customer ( $id, $customer )
       └─ fires  woocommerce_rest_insert_customer ( $user_data, $request, false )
```

Before this ticket the memberships plugin subscribed to none of these — no `profile_update`,
`user_register`, `woocommerce_rest_*`, or `woocommerce_update_customer` handler existed in
`includes/`, `custom/`, `automate-woo/`, or `wicket.php`. It now hooks `profile_update`.

### What can and cannot change

| Field | Changes? | Notes |
|---|---|---|
| `user_email` | **yes** | `set_email()` → `wp_update_user` |
| `display_name` | **yes** | recomputed downstream, see hook-ordering note |
| `first_name` / `last_name` | **yes** | written as user meta |
| billing / shipping fields | only if the MDP payload includes a `billing` object | see [Phase 2](#phase-2--woocommerce-billing-email-deferred) |
| `user_login` | **no** | WooCommerce rejects it outright: `class-wc-rest-customers-v1-controller.php:532-534`, *"Username isn't editable."* |

`user_login` holds the MDP person UUID. Because it cannot change, **every join key the
membership record relies on survives a profile update** — `user_id`,
`membership_user_uuid`, and `membership_wp_user_id` all stay correct. Only the display
copies drift. A UUID change happens solely on a person *merge*, which is already covered by
the `/membership/merge` webhook → `Admin_Controller::update_memberships_owner()`.

## The stale copies

Name and email are written once at membership creation
(`Membership_Controller.php:331-334`) and on subscription assignment
(`Utilities.php:917-921`), then never revisited. There are four storage locations:

| # | Location | Meta key | Fields |
|---|---|---|---|
| 1 | `wicket_membership` post meta | flat keys | `user_name`, `user_email` (and `membership_wp_user_email` where written by `Membership_Controller.php:1509`) |
| 2 | user meta (JSON) | `_wicket_membership_{membership_post_id}` | `user_name`, `user_email`, `membership_wp_user_display_name`, `membership_wp_user_last_name`, `membership_wp_user_email` |
| 3 | parent order meta (JSON) | `_wicket_membership_{membership_product_id}` | same blob as #2 |
| 4 | subscription meta (JSON) | `_wicket_membership_{membership_product_id}` | same blob as #2 |

> **Corrected by live testing.** An earlier draft of this document asserted that locations
> 2–4 hold the *same serialized array*. They do not. Measured on real records, the user meta
> copy carries `user_name`/`user_email` while the order and subscription copies carry
> `membership_wp_user_*` plus period, grace-period and org keys the user meta copy lacks —
> and the split is not even consistent between records. They are related but genuinely
> different documents. See [Step 5](#step-5--live-verification-against-the-docker-stack).

## Observed impact

1. **Admin member search silently fails.** `Membership_Controller.php:2114-2127` searches
   the `user_name` / `user_email` **post meta**, while the list *display* builds
   `$tier->user` live from `get_userdata()` (`:2178-2216`). After an email change the list
   shows the new address but searching for it returns nothing, and the old address still
   matches. Display and search disagree.
2. **A cleanup guard stops working.** `Utilities::handle_wp_delete_user()` at
   `Utilities.php:767` gated cleanup on
   `get_post_meta($mship->ID,'user_email') == $user->user_email`. Once the copy is stale
   that comparison never passes, so deleting the user orphans the membership post and
   leaves the order and subscription blobs behind. See
   [Related correction](#related-correction--handle_wp_delete_user-guard).
3. **Stale name in the member edit UI.** `frontend/src/members/edit.js:270` and `:490` read
   `data.user_name` from the cached blob. Sorting by `user_name` is stale too; sorting by
   last name happens to be correct because `Membership_Controller.php:2057` joins live
   `usermeta`.
4. **Cosmetic** — order notes and the member link built from `membership_wp_user_email`
   (`Membership_Controller.php:1511`).
5. **Renewal notices route to the old address.** Deferred to Phase 2; this is WooCommerce
   data rather than membership data.

## Existing precedent

`Admin_Controller::update_org_name_on_memberships()` (`:390-411`) already solves the
identical problem on the organization side: `get_edit_page_info()` compares the cached
`org_name` against a fresh MDP lookup (`:368-373`) and rewrites it across all matching
membership posts.

We follow its shape but fix two things it gets wrong:

- It is **lazy** — it only runs when an admin happens to open the edit page. Ours is
  event-driven, so search is correct without anyone visiting a page.
- It **hand-rolls the blob write** and, in doing so, covers only locations #1 and #2 — the
  order and subscription copies stay stale. That is precisely the mistake reusing
  [`amend_membership_json()`](#propagation--reuse-amend_membership_json) avoids.

## Correction — hook choice

**Use `profile_update`, priority 1000, 2 args.**

```
add_action( 'profile_update', [ Utilities::class, 'sync_membership_owner_profile' ], 1000, 2 );
```

> Built differently: registered in the `Utilities` constructor as an instance method, next
> to its `delete_user` sibling. See [Step 2](#step-2--utilitiessync_membership_owner_profile).

### Why `profile_update` over the alternatives

| Hook | Verdict |
|---|---|
| `woocommerce_rest_insert_customer` | Most precise — fires only on the REST path, so we know it came from the MDP, and it hands us `$request` so we can see exactly which fields were sent. **But** it misses admin-panel edits and the CAS login sync (`sync_wicket_data` on `wp_login`, `wicket-cas-role-sync.php:100-106`, which also writes `user_email`). Those produce the same drift. |
| `woocommerce_update_customer` | Same coverage gap, and gives us a `WC_Customer` rather than the old values, so we cannot cheaply diff. |
| **`profile_update`** | **Chosen.** Fires on every path that writes the user — REST, admin, CAS sync — and passes `$old_user_data`, so we can compare and no-op when nothing relevant changed. |

### Two hazards this priority addresses

**Ordering.** The base plugin registers `wicket_sync_display_name_from_profile` on
`profile_update` at **priority 999** (`wicket-cas-role-sync.php:211`). That handler
recomputes `display_name` from the `first_name` / `last_name` meta and calls
`wp_update_user()` again. Running at any priority below 1000 would capture the
*pre-recompute* `display_name` and cache a value that is about to change. Priority 1000
guarantees we read the settled value.

**Reentrancy.** Because that priority-999 handler calls `wp_update_user()` from inside a
`profile_update` handler, `profile_update` fires again for the same user in the same
request. Our handler needs its own guard — a `static $syncing = []` keyed by user ID,
released in a `finally`, mirroring the pattern the base plugin already uses
(`wicket-cas-role-sync.php:212-216`). Without it the sync runs twice per update: wasted
writes, and duplicated log lines that make the audit trail hard to read.

## Propagation — per-location in-place patch

> **This section was rewritten after live testing overturned the original design.** The plan
> was to reuse `Membership_Controller::amend_membership_json()` (`:448-459`), which reads the
> user meta blob, merges a patch, and writes the result to all three JSON homes. Measurement
> on the Docker stack showed that would **destroy data**, because the three documents are not
> copies of one another.

Simulated against membership 3214 — what `amend_membership_json()` would have written to the
order and subscription blobs:

```
KEYS DESTROYED on order (8):        KEYS INJECTED on order (10):
  - membership_period = 'year'        + membership_status      + org_location
  - membership_interval = 1           + user_id                + org_name
  - membership_subscription_period    + user_name              + org_uuid
  - membership_subscription_interval  + user_email             + org_seats
  - membership_wp_user_id = 53        + previous_membership_post_id
  - membership_grace_period_days = 30 + membership_wp_user_last_name
  - organization_uuid = '67484dfa…'
  - membership_seats = '5'
```

That loss is a **pre-existing defect in `amend_membership_json()`** — its three current
callers (`Admin_Controller.php:158,290,759`) already inflict it on every admin membership
edit. Routing a `profile_update` hook through it would have widened the blast radius from
rare admin actions to every profile change. Logged as a follow-up, not fixed here.

**What was built instead:** each JSON document is patched where it lives, and only for keys
it already contains. `Utilities::refresh_membership_json_owner_fields()` reads one document,
updates the owner fields present in it, and writes it back — so every document keeps its own
shape and no key is added or removed. Verified: key counts unchanged across all records
(25→25, 22→22, 21→21, 24→24).

The candidate owner fields, applied as an intersection per document:

| Field | Found in |
|---|---|
| `user_name`, `user_email` | user meta copy; also the order/subscription copy on some records |
| `membership_wp_user_display_name`, `membership_wp_user_email` | order/subscription copy on most records |
| `membership_wp_user_last_name` | absent from every record measured — never introduced |

`array_key_exists()` rather than `isset()`, so a key present but stored as `null` still counts
as part of the document's shape and gets refreshed.

## Correction — flow

Two entry points, one worker.

```
profile_update( $user_id, $old_user_data )        priority 1000
   covers admin edits and the CAS login sync

woocommerce_rest_insert_customer( $user_data, … ) priority 10
   covers the MDP path, where the name meta is written after profile_update fires
        └─ both call ─┐
                      ▼
 1. Load the WP_User; bail if gone
 2. Early-out: $old_user_data present and user_email + display_name both unchanged → return
 3. Memo: md5(email|display_name|last_name) already synced for this user this request → return
 4. Query wicket_membership posts, meta user_id = $user_id, status publish; none → return
 5. Per membership post:

      update_post_meta( $post_id, 'user_name',  $user->display_name );   // location #1
      update_post_meta( $post_id, 'user_email', $user->user_email );

      refresh_membership_json_owner_fields( 'user', $user_id,         '_wicket_membership_'.$post_id, $fields )
      refresh_membership_json_owner_fields( 'post', $order_id,        '_wicket_membership_'.$product_id, $fields )
      refresh_membership_json_owner_fields( 'post', $subscription_id, '_wicket_membership_'.$product_id, $fields )
      // each returns the field names it actually refreshed, for the audit line

 6. One summary log line via Utilities::wc_log_mship_error() (Utilities.php:206), including
    a per-membership, per-location breakdown of exactly which fields changed
```

### Why two entry points

`WC_Customer_Data_Store::update()` calls `wp_update_user()` at
`class-wc-customer-data-store.php:192` — which fires `profile_update` — and only writes the
`first_name` / `last_name` user meta at `:215`. On the MDP path the name meta therefore does
not exist yet when `profile_update` runs.

This was not theory. The first live `PUT` changed both email and last name; the email
propagated to all twelve locations and the name did not. `woocommerce_rest_insert_customer`
fires after `save()` completes and sees the finished record. The value memo means the second
pass is a no-op whenever the first pass already did the work.

The same ordering also explains a **residual one-update lag in WordPress itself**: the base
plugin recomputes `display_name` from first/last at priority 999, before the new name meta is
written, so WP's own `display_name` trails a last-name change by one update. The membership
record mirrors `display_name` faithfully — the lag is upstream, and it self-corrects on the
next write. Confirmed in testing.

### Notes on the write groups

- **No ordering dependency** between the flat post meta writes and the three JSON patches;
  they read and write disjoint storage.
- **`membership_wp_user_email` is deliberately not written to post meta.** Only one narrow
  branch ever writes it there (`Membership_Controller.php:1509`) and nothing reads it back
  from post meta — search and display use `user_email`.
- **No key is ever created.** Only fields already present in a given document are refreshed,
  so `membership_wp_user_last_name` — absent from every record measured — stays absent.

### Fields deliberately left alone

`user_id`, `membership_user_uuid`, and `membership_wp_user_id` are **not** written. They
cannot have changed (see the `user_login` row above), and touching them would blur the line
between this fix and the merge/transfer flows that legitimately re-own a record.

## HPOS and legacy order storage

Locations #3 and #4 live on WooCommerce orders and subscriptions, so storage mode matters.
Stack versions: **WooCommerce 10.6.1**, **WooCommerce Subscriptions 8.5.0** — both
HPOS-capable.

### Current state of the plugin

The memberships plugin contains **no `FeaturesUtil::declare_compatibility()` call** — no
HPOS declaration of any kind. WooCommerce therefore classes it as *uncertain* for the
`custom_order_tables` feature (`FeaturesController.php:1070-1072`), which surfaces a warning
on the HPOS settings screen. Every order and subscription meta access in the plugin goes
through `get_post_meta` / `update_post_meta` / `delete_post_meta` — roughly 14 call sites
for the `_wicket_membership_*` key alone (`Membership_Controller.php:365,367,434,454,455,1376,1380,1385,1389`,
`Admin_Controller.php:817,823,836,842,1044,1054,1570,1579,1600,1609`, `Utilities.php:773,1088,1089`,
`custom/memberships-sync.php:811,814`).

So the plugin is, as written, bound to legacy post storage for order meta. This ticket does
not change that, and must not silently diverge from it.

### Why postmeta still functions under HPOS

Since WooCommerce 8.8.0, `OrdersTableDataStore::persist_order_to_db()` calls
`maybe_create_backup_post()` unconditionally on order creation (`:2103-2107`,
`:2193-2213`). Every HPOS order gets a real `wp_posts` row carrying **the same ID** — post
type `shop_order_placehold` when sync is disabled, the true order type when it is enabled.

Two consequences:

- **No ID collision risk.** The `wp_posts` row is reserved for that order ID, so
  `update_post_meta( $order_id, ... )` cannot land on an unrelated post.
- **Writes and reads agree.** `update_post_meta` and `get_post_meta` both hit `wp_postmeta`,
  so the plugin stays internally consistent under HPOS.

The cost is that the blob is invisible to `$order->get_meta()`, to the WooCommerce order
admin, and to HPOS tooling and exports — but that is already true of every other
`_wicket_membership_*` write in the plugin, and it is not a regression this ticket
introduces.

### Decision: match the existing convention, do not switch to CRUD here

Migrating just this write to `$order->update_meta_data()` + `save_meta_data()` would be
**actively harmful**. Under HPOS the value would land in `wp_wc_orders_meta` while every
existing reader continues to read `wp_postmeta`. Result: the stale postmeta copy is still
what the plugin reads, so the drift we set out to fix persists — and we would have created a
*second*, divergent copy of the blob. Strictly worse than doing nothing.

Mixed storage is only safe once reads and writes move together. That means:

- **This ticket:** nothing to decide — locations #3 and #4 are written by
  `amend_membership_json()`, which already uses `update_post_meta`. Reusing the primitive
  keeps us on the same storage as every existing reader by construction, under both modes.
  This is a further argument for not hand-rolling the blob writes.
- **Separate ticket:** migrate every `_wicket_membership_*` access to CRUD atomically
  (`wc_get_order()` / `wcs_get_subscription()` + `update_meta_data()` / `get_meta()`), add
  the `FeaturesUtil::declare_compatibility()` declaration, and handle records written before
  the migration. Too large to ride along here, and it needs its own test pass.

Subscriptions behave identically — WCS 8.5.0 stores them in the same orders tables, and
`wcs_get_subscription()` is CRUD-based, so the reasoning above applies unchanged.

### Pre-existing HPOS bug found nearby (not caused by this fix)

`Membership_Controller.php:329-330` reads WooCommerce-native subscription meta through
postmeta:

```php
'membership_subscription_period'   => get_post_meta( $subscription_id, '_billing_period')[0],
'membership_subscription_interval' => get_post_meta( $subscription_id, '_billing_interval')[0],
```

`_billing_period` and `_billing_interval` are **WooCommerce's own** keys, not plugin-private
ones. Under HPOS they live in the orders meta table, and the placeholder post has no
postmeta — so these return an empty array, `[0]` emits an undefined-key warning, and `null`
is silently stored as the membership's billing period and interval. These two need CRUD
(`$subscription->get_billing_period()` / `get_billing_interval()`) regardless of what
happens to the plugin-private blob.

Out of scope for WWID-2121, but it belongs on the HPOS migration ticket and is worth raising
now — it fails quietly, which is how it has survived.

### Phase 2 is already HPOS-safe

`Helper::change_order_address_match_customer()` (`Helper.php:469-501`) uses CRUD setters and
`$order->save()` throughout, so the deferred billing-email work needs no storage-mode
special casing.

## Related correction — `handle_wp_delete_user` guard

`Utilities.php:767` compared stale cached email against live email to decide whether to
clean up. Even with the sync in place, that comparison is the wrong test — it is asking "is
this cache fresh?" when it means "does this membership belong to this user?". The `user_id`
meta already answers that, and the `meta_query` directly above it (`:618-624`) has already
filtered on exactly that value, so the guard is redundant as well as fragile.

Proposal: replace the email comparison with a `user_id` check, or drop the condition. Small
enough to land in the same PR; called out separately so review can weigh it on its own.

## Phase 2 — WooCommerce billing email (deferred)

`WC_Customer::set_email()` writes `user_email` only. The WooCommerce **billing** email is a
separate prop, updated solely when the MDP payload includes a `billing` object. If it does
not, `_billing_email` on the parent order and the subscription stays stale, and renewal and
early-renewal notices go to the old address.

`Helper::change_order_address_match_customer()` (`Helper.php:469-501`) already exists to
resync order billing from the customer record and is used by the merge and transfer flows,
so the tool is in hand.

**Blocked on one fact we could not determine from the code: what the MDP payload actually
contains.** Capture a real payload from a non-prod environment first. If it carries
`billing`, WooCommerce already handles it and Phase 2 is unnecessary. If it does not, we
call the existing helper from step 4 of the flow and decide whether it needs an opt-out
setting, since it overwrites billing fields an admin may have edited by hand.

Not in Phase 1 scope, and Phase 1 does not depend on the answer.

## Testing

| Scenario | Expected |
|---|---|
| MDP changes email on a member with one active membership | All four locations updated; admin search finds the new address and no longer the old one |
| MDP changes name | `user_name` / `membership_wp_user_display_name` / `membership_wp_user_last_name` updated; edit page header shows the new name |
| MDP changes email on a member with several memberships | Every membership post and every blob updated; one log line listing all post IDs |
| Update with no relevant field change (e.g. billing phone only) | No writes, no log line — confirms the step-2 diff short-circuits |
| Member with no membership record | No writes, no error |
| Membership with no user-meta blob (imported record) | `amend_membership_json()` returns `false`; post meta still updated; logged; no fatal |
| Membership with no subscription (or no parent order) | Remaining blobs updated; the write against the empty ID is a silent no-op, no stray `postmeta` row on ID `0` |
| Admin edits the user in wp-admin | Same sync runs — confirms coverage beyond the REST path |
| Reentrancy | Sync body executes once per update despite the priority-999 handler re-firing `profile_update` |
| Priority ordering | Cached `user_name` matches the *final* `display_name`, not the pre-recompute value |
| **Legacy post storage** (HPOS off) | Order and subscription blobs updated; `$order->get_meta()` also sees the new value |
| **HPOS authoritative, sync off** | Order and subscription blobs updated in `wp_postmeta` against the `shop_order_placehold` row; every existing reader (`Membership_Controller.php:434,1376,1385`) returns the new value |
| **HPOS with sync enabled** | Same as above; confirm the sync process does not resurrect the pre-update blob |
| Membership whose order predates an HPOS migration | Blob still resolves; no duplicate divergent copy in `wp_wc_orders_meta` |

Run the storage-mode rows under both settings of
`woocommerce_custom_orders_table_enabled`. The blob assertions should read through the same
accessor the plugin uses (`get_post_meta`) *and* through `wc_get_order()->get_meta()`, so a
future CRUD migration inherits a test that already distinguishes the two.

Prefer the centralized QA suite at `./qa` per the stack AGENTS.md. `profile_update` firing
from `wp_update_user()` is directly exercisable in PHPUnit against `MembershipsBaseTest`.

## Implementation log

Built, then exercised end-to-end against the local Docker stack. Live testing overturned the
central design decision and surfaced a hook-ordering bug, so the sections above were revised
to match what shipped. Final state: **one file changed** (`includes/Utilities.php`).

### Step 1 — reverted

`Membership_Controller::amend_membership_json()` was first made `static` so the new handler
could call it without instantiating the controller (whose constructor registers WooCommerce
cart hooks as a side effect). **Reverted in full** once testing showed the method must not be
used here at all — see Step 5. `Membership_Controller.php` is untouched in the final diff.

### Step 2 — `Utilities::sync_membership_owner_profile()`

`Utilities.php:679-797`, registered at `:27-33`. Plus
`sync_membership_owner_profile_rest()` (`:621-646`) and the private worker
`refresh_membership_json_owner_fields()` (`:800-838`).

> **Deviation 1 — registration site.** Planned for `wicket.php` as a static callable; built in
> the `Utilities` constructor as an instance method, beside its `delete_user` sibling.

> **Deviation 2 — the planned reentrancy guard would never have fired.** The design used a
> `static $syncing` flag set on entry and released in a `finally`. The priority-999 handler
> calls `wp_update_user()`, and that nested `profile_update` runs our priority-1000 handler
> *to completion* before the outer one is reached — the flag is unset both times. Replaced
> with a per-request memo of the synced value signature.

> **Deviation 3 — `last_name` dropped from the early-out diff.** `WP_User` resolves
> meta-backed properties lazily against the current value, so `$old_user_data->last_name`
> returns the *new* name and the comparison is always equal. Only `user_email` and
> `display_name` — real `wp_users` columns — are compared.

> **Deviation 4 — minor.** Query uses `'fields' => 'ids'`; log level `info`, not the
> `wc_log_mship_error()` default of `error`.

### Step 3 — `handle_wp_delete_user()` guard

`Utilities.php:833-845` (PHPDoc) and `:861-864` (the guard). Stale-email comparison replaced
with a `user_id` comparison, as designed. PHPDoc added, which the method lacked.

### Step 4 — static verification

`php -l` clean. `vendor/` is absent from this checkout, so PHPUnit could not run.

### Step 5 — live verification against the Docker stack

The container bind-mounts `./src/` to `/var/www/html`, so the working-tree edits were already
live — no deployment step needed. Environment: **HPOS enabled and authoritative, sync off**,
WooCommerce 10.6.1, WCS 8.5.0. That is the storage mode the [HPOS](#hpos-and-legacy-order-storage)
section most wanted exercised, and it is the stack default here.

Method: created a temporary `read_write` WooCommerce API key, snapshotted every storage
location for a test user, issued real `PUT wc/v3/customers/{id}` calls, diffed, then restored
the user through the same API and deleted the key. Final diff against the pre-test snapshot is
empty — **the stack is back to its original state**.

**Finding A — the reuse design was wrong, and would have destroyed data.** Locations #2, #3
and #4 are not copies of one array. A simulated `amend_membership_json()` write showed 8 keys
deleted from the order and subscription blobs, including `membership_period='year'`,
`membership_grace_period_days=30`, `organization_uuid` and `membership_seats='5'`, with 10
unrelated keys injected. Rewrote the propagation as a per-location in-place patch; verified
key counts unchanged (25→25, 22→22, 21→21, 24→24) across four records. The underlying defect
in `amend_membership_json()` is pre-existing and still affects its three current callers —
raised as a follow-up, deliberately not fixed here.

**Finding B — `profile_update` alone does not see name changes on the MDP path.** First live
`PUT` changed email *and* last name; only the email propagated.
`WC_Customer_Data_Store::update()` fires `profile_update` from `wp_update_user()` at
`:192` but writes the name meta at `:215`. Added `woocommerce_rest_insert_customer` as a
second entry point. Re-tested: both fields propagate to all twelve locations.

**Finding C — empirical confirmation of the HPOS analysis.** Reading the blob back through
the CRUD layer returned `''` while `get_post_meta()` returned the document — exactly the
split the HPOS section predicted, and the reason a partial migration to
`$order->update_meta_data()` would have been worse than doing nothing.

**Finding D — the MDP cannot update every membership owner.** A `PUT` against a user with
role `org_editor` was rejected: *"email cannot be updated via this endpoint for a user with
role org_editor"* (HTTP 403). `woocommerce_rest_customer_allowed_roles` resolves to
`customer,user` only. Owners on other Wicket roles never receive MDP profile changes at all —
a base-plugin gap, outside this ticket, but it caps the fix's reach and is worth raising.

**Finding E — upstream `display_name` lag.** WP's own `display_name` trails a last-name change
by one update, because the base plugin recomputes it at priority 999 before the new name meta
is written. The membership record mirrors `display_name` correctly; the lag is upstream and
self-corrects on the next write.

### Still not run

The PHPUnit matrix in [Testing](#testing). Live testing covered the substance of the
HPOS-authoritative, multi-membership, email-change and name-change rows, but as manual
verification against one dataset — not as repeatable tests. `composer install` and the table
above still belong in the PR.

## Out of scope

- **Org data drift.** `org_name` and `org_location` go stale the same way when an MDP
  *organization* record changes. Partially covered by the lazy
  `update_org_name_on_memberships()` path, and driven by an organization webhook rather
  than the person one. Same fix shape, separate ticket.
- **Person merges.** Already handled by `/membership/merge` →
  `Admin_Controller::update_memberships_owner()`.
- **Backfill of records that have already drifted.** The hook only corrects records from
  the next profile update onward. If drift is already widespread, a one-off WP-CLI
  reconciliation pass is the remedy — worth confirming the scale before deciding.
- **HPOS/CRUD migration of `_wicket_membership_*` and the `_billing_period` reads.** Needed,
  but must move all ~14 call sites at once. See [HPOS](#hpos-and-legacy-order-storage).
