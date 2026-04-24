# Bugs

Tracked bugs for the wicket-wp-memberships plugin.
Add an entry here when a bug is identified. Remove it when resolved.

---

| File | Area | Note | Asana |
|---|---|---|---|
| `includes/Group_Admin_Controller.php` | `update_group_change_ownership()` | When saving dates without changing the owner, `MembershipDetailsForm` fires both `onSave` (dates) and `onOwnerSave` (unchanged owner) together. The ownership endpoint returns 400 "This user is already selected." even though only the dates changed. Fix: either skip the owner call when the selection hasn't changed (frontend), or treat same-owner as a no-op success on the PHP side rather than a 400. | — |
| `includes/Group_Admin_Controller.php` | `update_group_entity_record()` | Suspected timezone drift on start/end/expiry dates. The frontend converts picker dates to ISO via `pickerDateToIso` (MDP timezone → UTC), PHP then re-interprets via `strtotime` and `Utilities::get_utc_datetime`. Round-trip needs audit to confirm the stored value matches the user's intended calendar day after a save-reload cycle. | — |
