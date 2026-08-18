---
title: "Membership Transfer — Engineering Reference"
audience: [developer, agent]
php_class: Admin_Controller
source_files: ["includes/Admin_Controller.php", "includes/Membership_WP_REST_Controller.php", "frontend/src/members/manage_membership.js", "frontend/src/services/api.js", "frontend/src/members/edit.js"]
---

# Membership Transfer — Engineering Reference

Moves an **individual** membership's remaining term to a **different person**. It does this by minting a brand-new membership (MDP record + WP post) for the new owner and cancelling the original — it does **not** edit the owner in place.

> **Not the same as "change owner".** A separate legacy flow (`/change_owner` → `Admin_Controller::update_membership_change_ownership()`) reassigns an organization membership's owner *in place* (same post, same MDP record) and re-links the order. The person-merge webhook (`/memberships/merge`) is a third, unrelated flow. This doc covers **transfer** only.

## Entry Points

| | |
|---|---|
| REST route | `POST wicket_member/v1/membership/{membership_post_id}/transfer_membership` |
| Registered | `Membership_WP_REST_Controller.php:332` |
| Handler | `Membership_WP_REST_Controller::transfer_membership()` (`:623`) |
| Business logic | `Admin_Controller::transfer_membership($membership_post_id, $new_owner_uuid)` (static, `:1449`) |
| Body param | `new_owner_uuid` — Wicket person UUID of the new owner (required) |
| Permission | `permissions_check_write` (`manage_options`) |

The handler returns `400` if `membership_post_id` or `new_owner_uuid` is missing, otherwise delegates and returns `{ success: <result> }`.

## Feature Flags / Settings

**None.** Transfer has no dedicated feature flag, option, or environment gate. The route is registered unconditionally and the UI is mounted unconditionally (`edit.js:817`). The only guard is client-side (see below).

Adjacent constant that affects the date math but does not gate transfer:

| Constant | Effect |
|---|---|
| `WICKET_MSHIP_MDP_TIMEZONE` | Timezone used by `Utilities::get_mdp_day_start()` for the transfer "now" date |

## Frontend UI

| | |
|---|---|
| Component | `SwitchMembership`'s sibling `ManageMembership` in `frontend/src/members/manage_membership.js` |
| Mount | `frontend/src/members/edit.js:817` — `<ManageMembership membership={membership} />`, per-membership block on the individual member edit page |
| API client | `transferMembership({ new_owner_uuid, membership_post_id })` — `frontend/src/services/api.js:190` |

`ManageMembership` renders a **Manage Membership** button opening a modal with a two-action select: **Transfer Membership** and **Switch Membership**. Behaviour:

- **Client gate:** the button refuses to open (shows a "future start" warning) unless `membership_starts_at` is in the past **and** status is `active`.
- **Owner search:** `POST {PLUGIN_API_URL}/mdp_person/search?term=...` (min 3 chars).
- **Two-step confirm** before submit. On success the response `redirect_url` is opened and the page reloads.

## Execution Flow (`transfer_membership`)

1. **Load original** — `get_post()`, read `user_id`, read JSON blob `_wicket_membership_{post_id}` into `$old_customer_meta_array`.
2. **Compute dates** — `$now = Utilities::get_mdp_day_start()`. Read `membership_tier_uuid`, `membership_ends_at`, `membership_expires_at`, `membership_grace_period_days` (derived from the ends→expires delta if empty).
3. **Create new MDP membership** for the new owner — `wicket_assign_individual_membership($new_owner_uuid, $tier_uuid, $starts_at=now, $ends_at, $grace_days)`. New Wicket UUID = `$response['data']['id']`. Error → `400`.
4. **Create new WP post** — `wp_insert_post()` copying type/title/status/content, `post_author => 0`.
5. **Copy all post meta** old → new (`maybe_unserialize`).
6. **Resolve new owner WP user** — `get_user_by('login', $new_owner_uuid)` or `wicket_create_wp_user_if_not_exist()`.
7. **Stamp new post** — `user_name`, `user_email`, `user_id`, `membership_user_uuid`, `membership_starts_at = now`, `membership_wicket_uuid`.
8. **Cancel old post** — `membership_ends_at`/`membership_expires_at = now`, `membership_grace_period_days = 0`, `membership_status = STATUS_CANCELLED`.
9. **Set external ID** on the new MDP membership — `wicket_update_membership_external_id()` (`person_memberships` / `organization_memberships`). Failure logs via `Utilities::wc_log_mship_error()`, does not abort.
10. **Update old MDP record** — `Membership_Controller::update_mdp_record()` with ends/expires = now, grace 0.
11. **Rewrite user-meta blobs** — old blob (cancelled, ends/expires = now) and new blob (new identity, start = now).
12. **Reassign subscription** (if `membership_subscription_id` + `membership_product_id` present) — rewrite subscription meta, `set_customer_id($new_user)`, repoint the renewal line item `_membership_post_id_renew` to the **new** post id, add a subscription note.
13. **Return** — `success`, `membership_user_uuid`, `membership_wicket_uuid`, `redirect_url` to the individual member edit screen.

## The "Order stays with the payer" design

The defining decision of this flow: the **WooCommerce parent order is deliberately left attached to the original paying customer**.

- `membership_parent_order_id` is copied to the new post but the order's own customer/meta are **not** changed (the reassignment block is intentionally commented out, `:1597`).
- Only the **subscription** is reassigned (`set_customer_id`, `:1581`) and its renewal item repointed (`:1589`).
- An order note documents this and warns operators:

  > *"Reassigned attached SUBSCRIPTION to {email} on an admin transfer of membership. WARNING: DO NOT MANIPULATE ORDER STATUS OR META. THIS IS A SUBSCRIPTION TRANSFER ONLY."*

## Data-Change Summary

| Target | Change |
|---|---|
| MDP | New membership created for new owner; old membership dates/grace collapsed to now |
| WP posts | New post created (full meta copy, new owner); old post → `cancelled`, ends/expires = now |
| User meta | New owner receives both new (active) and old (cancelled) JSON blobs |
| Subscription | Customer reassigned; renewal item `_membership_post_id_renew` → new post id; note added |
| Parent order | **Unchanged** (stays with original payer); note added |

## External Dependencies

`wicket_assign_individual_membership()`, `wicket_update_membership_external_id()`, `wicket_create_wp_user_if_not_exist()` (base Wicket plugin); `Membership_Controller::update_mdp_record()`; WCS `wcs_get_subscription()`, `$sub->set_customer_id()`; WP `wc_update_order_item_meta()`.

## Hooks

`transfer_membership()` fires **no** `do_action`/`apply_filters` of its own.

## Caveats

- **No server-side status/date guard** — the "membership must be active and started" check lives only in the React client. A direct REST call bypasses it.
- **Old blob is written to the new owner's user meta** (`:1555`) — the cancelled membership blob lands under the *new* owner's `user_id`, a known quirk.
- Individual-membership oriented — designed around a single person's term, not org seat reassignment.
