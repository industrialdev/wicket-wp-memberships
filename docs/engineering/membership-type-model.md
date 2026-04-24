---
title: "Membership Type Model"
audience: [developer]
source_files: [
  "includes/Membership_Controller.php",
  "includes/Membership_Group.php",
  "includes/Membership_Group_Config.php",
  "includes/Admin_Controller.php"
]
---

# Membership Type Model

## Individual and Organization Memberships

Individual and organization memberships are stored as `wicket_membership` CPT records. The `membership_type` meta value is either `individual` or `organization` — set from `Membership_Tier::get_tier_type()` at creation time.

**Both types always require a `Membership_Tier` and a `Membership_Config`.** The tier links to a WooCommerce product and holds renewal configuration; the config holds cycle, renewal window, and grace period data. Any membership record missing a valid `membership_tier_uuid` is considered invalid and cannot be updated via `Admin_Controller::update_membership_entity_record()`.

## Membership Groups

A Membership Group is a separate `wicket_mship_group` CPT record — not a `wicket_membership` post. It is managed by `Membership_Group` and `Group_Admin_Controller`.

Membership Groups use `Membership_Group_Config` (`wicket_mship_grp_cfg` CPT) directly. This class combines the date/cycle/renewal-window logic of `Membership_Config` with the renewal-type and approval logic of `Membership_Tier` into a single record. **Membership Groups have no `Membership_Tier` — they do not need one.**

## Membership Group (member seats)

Individual or organization membership records can belong to a Membership Group. Membership in a group is indicated by the `membership_group_id` meta key on the `wicket_membership` post, checked via `Membership_Controller::is_membership_group()`.

**Being part of a group does not change the type requirements.** A group member seat is still an individual or organization membership and still requires its own `membership_tier_uuid` and `membership_tier_post_id`. The `membership_group_id` is an additional link, not a replacement for tier data.

## `membership_type` Value Reference

| Value | Where used | Tier required? |
|---|---|---|
| `individual` | `wicket_membership` CPT | Yes |
| `organization` | `wicket_membership` CPT | Yes |
| `group` | **Not a valid value** — legacy/invalid data only | N/A |

The value `group` for `membership_type` is not part of the current data model. If encountered, it indicates stale or incorrectly seeded data. Individual and organization memberships that belong to a group are identified by `membership_group_id`, not by `membership_type`.

## Common Mistakes

- **Do not skip the tier lookup for records with `membership_group_id`.** They still need a tier.
- **Do not treat `membership_type = 'group'` as a valid state.** There is no such type in the current model.
- **Do not add fallback logic in `update_membership_entity_record()` for missing tier UUIDs.** A missing tier UUID is a data error, not a recoverable edge case.
