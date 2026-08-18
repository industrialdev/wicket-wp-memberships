---
title: "Membership Switch — Engineering Reference"
audience: [developer, agent]
php_class: Admin_Controller
source_files: ["includes/Admin_Controller.php", "includes/Membership_WP_REST_Controller.php", "includes/Membership_Controller.php", "frontend/src/members/switch_membership.js", "frontend/src/members/manage_membership.js", "frontend/src/services/api.js"]
---

# Membership Switch — Engineering Reference

Switches a member's current membership to a **different tier** from the admin edit screen. It mints a new membership (new MDP record + new WP post) on the target tier, preserves the original end date, and cancels the original — a replace, not an in-place edit.

## Entry Points

| | |
|---|---|
| REST route | `POST wicket_member/v1/membership/{membership_post_id}/switch_membership` |
| Registered | `Membership_WP_REST_Controller.php:346` |
| Handler | `Membership_WP_REST_Controller::switch_membership()` (`:635`) |
| Dispatcher | `Admin_Controller::switch_membership_request($args)` (static, `:1069`) → `create_switch_membership()` |
| Core logic | `Admin_Controller::create_switch_membership($membership_post_id, $new_tier_post_id, $switch_date = null)` (`:1178`) |
| Query params | `switch_post_id` (target tier post id), `switch_type` = `tier` — both required |
| Permission | `permissions_check_write` |

`permissions_check_write` (`:601`) returns true if `$_ENV['ALLOW_LOCAL_IMPORTS']` is set, otherwise requires `WICKET_MEMBERSHIPS_CAPABILITY` (`manage_options`). The handler returns `400` if `membership_post_id`, `switch_post_id`, or `switch_type` is missing.

## Feature Flags / Settings

**None.** No environment constant or `Settings.php` option gates switch. Guards are:

- REST write capability (above).
- Client-side: the modal only opens for an `active` membership with a past start date.

Base-plugin version constants that change MDP call shape during a switch:

| Check | Constant | Effect |
|---|---|---|
| `> 2.0.108` | `$_ENV['WICKET_BASE_PLUGIN_VERSION']` | Org assign passes `grant_owner_assignment` |
| `> 2.0.52` | `$_ENV['WICKET_BASE_PLUGIN_VERSION']` | Passes `previous_membership_uuid` (individual) |

## Frontend UI

| | |
|---|---|
| Component | `SwitchMembership` — `frontend/src/members/switch_membership.js` |
| Rendered by | `ManageMembership` modal (`manage_membership.js`), when action = `switch` |
| Mount | `frontend/src/members/edit.js:817` |
| API client | `switchMembership(membershipId, switchPostID, switchType)` — `api.js:206` |

Behaviour:

- The action select offers **Create Membership** (tier switch); the user picks a target tier.
- `loadTiers()` fetches tiers and **filters to the current membership's type** — an individual membership cannot switch to an org tier or vice-versa.
- The submit button is disabled until a tier is selected. On success it redirects to `response.redirect_url`.
- The parent `ManageMembership` button blocks opening unless the membership is active with a past start date.

## Switch Flow (`create_switch_membership`)

1. **Validate & resolve dates** — resolve `$switch_iso_date` (immediate = `Utilities::get_mdp_now()`; the method also accepts an optional future `$switch_date`, though the REST layer never passes one). Load user (owner UUID = `user_login`), target `Membership_Tier`, its `Membership_Config`, and type. New start = switch date; **new end = the preserved old `membership_ends_at`**; grace = target config's `get_late_fee_window_days()`.
2. **Capture old MDP identity before mutation** — `membership_wicket_uuid`, `membership_starts_at`, `membership_expires_at`, `membership_parent_order_id`, `membership_product_id`.
3. **Create new MDP membership:**
   - **Organization** → `wicket_assign_organization_membership()`, passing seats, grace, and the **old UUID as `previous_membership_uuid`** (triggers `copy_previous_assignments` so seat assignments carry forward). `grant_owner_assignment` follows the **new** tier's setting (base > 2.0.108).
   - **Individual** → `wicket_assign_individual_membership()`, passing the old UUID as `previous_membership_uuid` (base > 2.0.52) for record continuity.
   - `WP_Error` → abort `400` with payload.
4. **Create new WP post** — clone type/title/status/content (author 0), **copy all post meta**, then overwrite tier-identity meta (`membership_tier_post_id`, `membership_tier_uuid`, `membership_tier_name`, `membership_next_tier_*`, `membership_type`, `membership_wicket_uuid`, `membership_starts_at`, `membership_grace_period_days`).
5. **Recompute expiry** — `derive_switch_expiry(new ends_at, grace)` → new `membership_expires_at`. End date is inherited unchanged; only the expiry can shift because the target tier may carry a different grace period.
6. **Re-point the scheduled expiry event** (see below).
7. **Cancel old post** — `membership_status = STATUS_CANCELLED`, `membership_ends_at`/`membership_expires_at` = switch date, `membership_grace_period_days = 0`.
8. **MDP sync of cancelled membership** — org → `wicket_update_organization_membership_dates(old_uuid, old_start, switch_date, false, 0)`; individual → `wicket_update_individual_membership_dates(old_uuid, old_start, switch_date, 0)`. Failure is logged via `Utilities::wc_log_mship_error()` and **does not block** the switch.
9. **Return** — `success`, `membership_wicket_uuid`, and a `redirect_url` to the org or individual member edit screen.

### `derive_switch_expiry($end_date_iso, $grace_days)` (`:1423`)

Pure helper mirroring `Membership_Config::get_membership_dates()`: expiry = end date + grace days, snapped to end-of-day in the MDP timezone. Grace `<= 0` returns the end date unchanged. Kept separate so the offset math is testable independently.

## Advanced Scheduler Date Handling

The Action Scheduler event `add_membership_expires_at` is keyed by `membership_parent_order_id` + `membership_product_id` — both inherited by the new post via the meta copy. The switch re-points that event **only when the expiry actually moved**:

```php
if ( ! empty( $old_membership_parent_order_id ) && ! empty( $old_membership_product_id )
     && strtotime( $new_membership_expires_at ) !== strtotime( (string) $old_membership_expires_at )
     && function_exists( 'as_unschedule_action' ) && function_exists( 'as_schedule_single_action' ) ) {
  $expiry_event_args = [
    'membership_parent_order_id' => $old_membership_parent_order_id,
    'membership_product_id'      => $old_membership_product_id,
  ];
  as_unschedule_action( 'add_membership_expires_at', $expiry_event_args, 'wicket-membership-plugin' );
  as_schedule_single_action( strtotime( $new_membership_expires_at ), 'add_membership_expires_at', $expiry_event_args, 'wicket-membership-plugin', false );
}
```

- **End date** (`add_membership_ends_at`) and **early-renew** (`add_membership_early_renew_at`) events are left untouched — those dates are inherited unchanged.
- Unscheduling and re-adding the same key simply moves the existing event to the new expiry date.
- Event keying is defined in `Membership_Controller::scheduler_dates_for_expiry()`, group `wicket-membership-plugin`.

## External Dependencies

`wicket_assign_organization_membership`, `wicket_assign_individual_membership`, `wicket_update_organization_membership_dates`, `wicket_update_individual_membership_dates` (base plugin); `Utilities::get_mdp_now()/get_mdp_day_end()/wc_log_mship_error()`; `Membership_Config::get_late_fee_window_days()`; `Membership_Tier` accessors; `Helper::is_valid_membership_post()/get_post_meta()`; Action Scheduler `as_unschedule_action`/`as_schedule_single_action`.

## Hooks

The switch method fires no `do_action`/`apply_filters` of its own. The only scheduler interaction is the `add_membership_expires_at` unschedule/reschedule above.

## Caveats

- **No server-side active-status/date guard** — the active-and-started check is client-side only. A direct REST switch bypasses it.