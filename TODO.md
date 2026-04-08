# TODO

Tracked outstanding work items for the wicket-wp-memberships plugin.
Add an entry here whenever a `// TODO` comment is left in code.
Remove the entry when the work is completed.

---

## Membership_Group

| File | Method | Note | Asana |
|---|---|---|---|
| `includes/Membership_Group.php` | `create()` | Review bare-bones implementation before use in production | [task](https://app.asana.com/1/1138832104141584/project/1213403241762018/task/1213775558142167) |
| `includes/Membership_Group.php` | `add_individual_membership()` | Review implementation | [task](https://app.asana.com/1/1138832104141584/project/1213403241762018/task/1213781837058525) |

## Membership_Group_Config — Product ID concerns

| File | Method | Note | Asana |
|---|---|---|---|
| `includes/Membership_Group_Config.php` | `get_late_fee_window_product_id()` | **Temporary implementation.** Reads `product_id` from `late_fee_window_data` meta (same as `Membership_Config`). The late-fee product concept for group configs has not been defined — may need to be derived from the group's tier products instead of stored directly. Review and replace before production use. | — |
