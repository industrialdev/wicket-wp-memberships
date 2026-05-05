# Bugs

Tracked bugs for the wicket-wp-memberships plugin.
Add an entry here when a bug is identified. Remove it when resolved.

---

| File | Area | Note | Asana |
|---|---|---|---|
| `includes/Membership_Group.php` + `includes/Membership_Controller.php` | `add_subscription_line_item()` / `create_membership_record()` | **Group subscription line items silently skipped under SQLite.** See full write-up below. | — |
| `includes/Group_Admin_Controller.php` | `update_group_entity_record()` | Suspected timezone drift on start/end/expiry dates. The frontend converts picker dates to ISO via `pickerDateToIso` (MDP timezone → UTC), PHP then re-interprets via `strtotime` and `Utilities::get_utc_datetime`. Round-trip needs audit to confirm the stored value matches the user's intended calendar day after a save-reload cycle. | — |
| `includes/Admin_Controller.php` | Individual memberships group filter | Investigation needed: on the individual memberships list page, filtering by a group with no matching members may be behaving incorrectly because the expected group name is not shown in the results list. Confirm whether this is a display issue on the list page or a true filtering/no-results bug. | — |

---

## Group subscription line items silently skipped under SQLite

**Status:** Known. Not a production bug. Tests worked around with `BYPASS_WICKET`. Needs a proper fix before this feature ships.

### What happens

When `Membership_Group::add_member()` is called, `provision_individual_membership_record()` does two things back-to-back in the same PHP request:

1. Calls `Membership_Controller::create_membership_record()` — a heavyweight write chain:
   - `wp_insert_post()` (membership CPT)
   - Multiple `update_post_meta()` and `update_user_meta()` calls
   - `wicket_update_membership_external_id()` (MDP API + local write)
   - `scheduler_dates_for_expiry()` → `update_membership_subscription()` → `$sub->save()` (WC subscription write)

2. Calls `add_subscription_line_item()` → `WC_Order::add_product()` → `WC_Order_Item::save()` → `wpdb->insert()` into `woocommerce_order_items`.

The test environment uses the **wp-sqlite-integration** drop-in (SQLite). SQLite permits only one writer at a time across the entire database file. The rapid back-to-back write sequence means the `INSERT` in step 2 fires while SQLite is still holding (or has not fully released) the write lock from step 1. SQLite returns `SQLSTATE[HY000]: General error: 5 database is locked`. WC's `$item->save()` returns `0`, `add_product()` returns `false`, and `add_subscription_line_item()` logs a non-fatal `WP_Error` and returns — leaving **zero line items** on the subscription.

**This does not happen in production.** MySQL uses row-level locking and handles sequential writes within the same connection without contention.

### Why it is non-fatal by design

`add_subscription_line_item()` failure is deliberately non-fatal (`Membership_Group.php` ~line 611). A missing line item is a billing gap an admin can reconcile; it should never roll back a successfully created membership record.

### Current workaround

The 6 QA tests that verify line item behaviour set `$_ENV['BYPASS_WICKET'] = '1'` before calling `add_member()`. This causes `create_membership_record()` to take the short-circuit path (`Membership_Controller.php` line 729) — only a local WP post is created, the heavyweight write chain is skipped, and the SQLite lock clears in time for the line item INSERT. The flag is unset immediately after `add_member()` returns so it does not affect the rest of each test.

This is correct for those tests because they cover **line item behaviour**, not MDP API integration.

### Proper fix options

1. **Increase SQLite busy timeout** in the wp-sqlite-integration drop-in for the test environment. The current default is 10 seconds (`DEFAULT_SQLITE_TIMEOUT`), but the lock is never released because all writes are on the same PDO connection/transaction — a higher timeout won't help.

2. **Defer `add_subscription_line_item` out of the current request** using a WP action fired on `shutdown` or Action Scheduler scheduled for `time()` (immediate). This decouples the line item write from the membership creation write chain entirely and would work on both SQLite and MySQL.

3. **Restructure `create_membership_record`** to avoid holding write locks across the full chain — e.g. by deferring `update_membership_subscription` to a separate step.

Option 2 is the most targeted fix without changing the write order of existing code.

### Files involved

- `includes/Membership_Group.php` — `provision_individual_membership_record()` (line ~555), `add_subscription_line_item()` (line ~638)
- `includes/Membership_Controller.php` — `create_membership_record()` (line ~717), `scheduler_dates_for_expiry()` (line ~609), `update_membership_subscription()` (line ~791)
- `qa/tests/WordPress/Memberships/membership-group.pest.php` — 6 tests from line ~2010, all using `BYPASS_WICKET` workaround
