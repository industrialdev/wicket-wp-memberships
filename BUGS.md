# Bugs

Tracked bugs for the wicket-wp-memberships plugin.
Add an entry here when a bug is identified. Remove it when resolved.

---

| File | Area | Note | Asana |
|---|---|---|---|
| `includes/Group_Admin_Controller.php` | `update_group_entity_record()` | Suspected timezone drift on start/end/expiry dates. The frontend converts picker dates to ISO via `pickerDateToIso` (MDP timezone → UTC), PHP then re-interprets via `strtotime` and `Utilities::get_utc_datetime`. Round-trip needs audit to confirm the stored value matches the user's intended calendar day after a save-reload cycle. | — |
