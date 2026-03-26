# Current Scope

This file is a working snapshot of the current project scope for `wicket-wp-memberships`.

- It is general guidance, not a strict requirements document.
- It is meant to help with quick reference, feature audits, and gap analysis.
- It should not be treated as absolute or exhaustive.

## Membership Group Configuration (Plugin)

As an Implementation Specialist, I can create a membership configuration for Membership Group.

Membership Group configs will combine aspects of Membership Config + Tier configuration for organizations and individuals.

- Membership Group Configuration determines
  - How dates are calculated when Group Membership records are created
  - When renewal callout show to Membership Owners (early + grace)
  - If new membership records become active immediately upon being created or if they are created in a pending state
  - The renewal behaviour in the member portal

Includes fields:

- `Name*`
- `Renewal Window (same as membership config)*`
  - `Callout configuration*`
- `Grace Period Window (same as membership config)*`
  - `Callout configuration*`
- `Cycle (same as membership config)*`
  - `Anniversary`
    - `Membership Period (Interval # and Type)`
    - `Align End Dates`
  - `Calendar`
    - `Season Config (name, dates, status)`
- `Approval Required (same as membership tier)`
  - Nice to have, as these are not self-serve yet
  - `Approval Email`
  - `Callout Config`
- `Renewal Type (same as membership tier)*`
  - Limited to `Renewal Form Flow` and `Subscription` renewal type
  - If `Renewal Form Flow` is selected, a page must be assigned

Group Membership Config:

- Set date/type
- Set renewal settings (only subscription or form)

Include global option to enable/disable group membership.

## Membership Group Entity / Object (Org)

A Membership Group record is a container that holds multiple individual memberships. Each Membership Group will be linked to an organization record in the MDP. All Membership Group Posts will be collected in a list view and each membership group will have a detail view. Each membership record related to the group will be collected there.

Attributes:

- `Name`
- `Membership Group ID (need to confirm if this is WP Post or MDP UUID)`
- `Membership Post ID (Membership Plugin)`
- `UUID (MDP)`
- `Organization`
  - UUID of a linked organization record in the MDP
- `Membership Group Config`
  - Reference to config post in Membership plugin `Membership Group Configuration (Plugin)`
- `Membership Owner`
  - UUID of person record in the MDP
  - User name
  - User email
- `Start Date`
- `End Date`
- `Expiration Date`
- `Status`
- `Subscription ID`
- `Parent Order ID`
- `Individual membership post ID(s)`
  - IDs of all individual memberships included in the Group
- `Renewal Type`
  - `Renewal Form Flow (membership_next_tier_form_page_id)`
  - `OR`
  - `Subscription Renewal (membership_next_tier_subscription_renewal)`

## Admin > Create Membership Group

As an admin, I can create a Membership Group.

The Membership Group record is a container that individual memberships can be added to.

- Admins can manually create the Membership Group in the membership plugin.
- Upon saving the new group post a subscription is created in WooCommerce.
- Subsequently, individual memberships can be added to the membership group

Steps:

- From the `Membership Groups` list page there is a button to `Create Membership Group`
- Create page/form
  - `Name` - single line text
  - `Membership Group Config`
    - Dropdown of available Membership Group Configs in plugin
  - `Organization` - lookup of all Organization records in the MDP, single select
    - Search on name or ID
    - Search displays
      - Org Name (ID)
      - City, State/Prov, Postal/Zip code
  - `Membership Owner` - lookup of all People records in the MDP, single select
    - Search on name, email or ID
    - Search displays
      - First Last Name (ID)
      - Email address
  - `Start date` - date/calendar selection
  - `Create Membership Group` - button

- Upon saving the new Membership Group
  - Membership Group post is created
    - ID assigned
    - The post status = `Pending`
    - Start date = date set by admin
    - End date = calculated based on selected Membership Group config
    - Expiration date = calculated based on selected Membership Group config
    - Renewal type = based on selected Membership Group config
    - Org + Membership owner assigned
  - A subscription is created for the group
    - Subscription status = `Pending`
    - Customer = Membership Owner
    - Subscription schedule based on membership dates
      - Next payment date only populated if Renewal Type = `Subscription`
    - Group Membership ID added to subscription as meta
    - Org UUID added to subscription as meta
    - No line items are added to the subscription yet
- Admin is prompted to add members to group

## Individual Membership (Group)

Individual membership records can be a part of a Membership Group.

Individual membership records in a membership group inherit dates and billing information from the group and must be detached from the group to be managed independently. These records are renewed via the group.

Attributes:

- `Person UUID`
- `Post ID (Membership Plugin)`
- `UUID (MDP)`
- `Membership Group ID (Membership Plugin)`
  - `Start Date - calculated upon creation`
    - If start date is within group start/end date = date of creation
  - `End Date (inherited from group)`
    - If end date is within group start/end date = date of cancelation
  - `Expiration Date (inherited from group, or NA for cancelation)`
  - `Status (inherited from group)`

## Admin > Add to Group (New Member)

As an admin, I can add new individuals to a Membership Group.

When adding to a Membership Group, individual membership records must be created, and the membership IDs added to the group.

Steps:

- Select the members group (in the plugin) > `Add to group`
  - Membership group must be `pending`, `active` or `delayed` status
- Select the user
  - Person lookup from MDP
- Select the membership tier
  - Dropdown of individual membership tiers configured in the Wicket Membership plugin
- `Create`

Result:

- Individual membership created
  - Group Membership ID assigned as meta
  - Membership status = inherited from group (`pending`, `active` or `delayed`)
  - Start date:
    - If today is within membership group start date and end date, start date = today
    - If today is before membership group start date, start date = membership group start date
    - If today is after membership group end date = error
      - Individuals cannot be added to a membership group beyond the membership group end date
  - End date + expiration date inherited from membership group
  - Individual membership post ID assigned to membership group
  - Membership tier line item assigned to group subscription with individual membership post ID

## Admin > Add to Group (Existing Member)

As an admin, I can add an existing member to a Membership Group.

When adding an existing member to a Membership Group, the original individual membership must be ended and a new individual membership record must be created. The new membership ID is added to the group.

Via Individual Membership Record

Steps:

- On individual membership records, there will be an option to `Add to Membership Group`
  - Only available if Membership Groups are enabled in the environment
- Selecting `Add to Membership Group` opens a modal to complete the action
  - User must select the Membership Group
    - Lookup of all Membership Groups in the plugin
      - Displays Name + Org Name
    - Membership group must be `pending`, `active` or `delayed` status
  - User must confirm the action before proceeding

Result:

- Existing individual membership is end dated (canceled)
- New individual membership created
  - Group Membership ID assigned as meta
  - Membership status = inherited from group (`pending`, `active` or `delayed`)
  - Start date:
    - If today is within membership group start date and end date, start date = today
    - If today is before membership group start date, start date = membership group start date
    - If today is after membership group end date = error
      - Individuals cannot be added to a membership group beyond the membership group end date
  - End date + expiration date inherited from membership group
  - Individual membership post ID assigned to membership group
  - Membership tier line item assigned to subscription with individual membership post ID

## Admin > Add to Group (Bulk)

As an admin, I can add multiple individuals to a Membership Group by importing a CSV file.

When adding individuals to a Membership Group in bulk, the system must process each row in the CSV, identify or create the person using their email address, and create a new individual membership record that is added to the group.

If the individual already has a matching individual membership, the original membership must be ended and a new individual membership record must be created and added to the group.

Via Membership Group Record

Steps:

- On Membership Group records, there will be an option to `Add to Group (Bulk Import)`
- Action is available via the `Membership Group` edit view
- Membership group must be `pending`, or `active` status
- User uploads a `CSV file`
- The CSV file must include the following fields per row:
  - First name
  - Last name
  - Email address, used as the unique identifier
  - Individual membership tier
- Optional fields:
  - Existing individual membership UUID
  - Existing individual membership post ID
- System validates the CSV structure before processing.
- System checks for duplicate rows in the file:
  - Duplicate rows with the same `email + membership tier` are not allowed.
  - Multiple rows for the same email are allowed only if the membership tier differs.
- User must confirm the import before processing begins.

Person Identification Rules:

For each row:

1. The system attempts to locate a person in the MDP using the `email address`.
  - If the email matches an existing person:
    - That person record is used.
  - If no person exists with that email:
    - A new person record is created in the MDP using the provided first name, last name, and email.

Existing Membership Identification Rules:

- If the CSV row includes an existing membership UUID or membership post ID:
  - That specific membership record is canceled.
- If no UUID or post ID is provided:
  - The system searches for an existing individual membership for the person that matches the membership tier provided in the CSV.
    - If exactly one matching membership exists:
      - That membership is canceled.
  - If multiple memberships exist for that person with the same tier:
    - The row is skipped
    - The row is included in the error report

Group Membership Validation:

- If the person already has a membership in the selected Membership Group with the same membership tier:
  - The row is skipped
  - The row is included in the error report

Result (Per Successful Row):

- Individual membership created
  - If applicable, existing individual membership is end dated (canceled)
  - Group Membership ID assigned as meta
  - Membership status = inherited from group (`pending`, `active` or `delayed`)
  - `Start Date`
    - If today is within membership group start date and end date, start date = `today`
    - If today is before membership group start date, start date = `membership group start date`
    - If today is after membership group end date = `error`
      - Individuals cannot be added to a membership group beyond the membership group end date
  - `End Date`
    - End date + expiration date inherited from membership group
  - Individual membership post ID assigned to membership group
  - Membership tier line item assigned to group subscription with individual membership post ID

Post Import Result:

- After processing the file, the system displays an import summary including:
  - Total rows processed
  - Successful additions
  - Skipped rows
  - Failed rows
- Skipped or failed rows include a reason for failure.
- Admin can download an error report CSV containing all rows that were not processed.

Acceptance Criteria:

CSV Import

- Admin can launch bulk import from the Membership Group edit view
- Import is only available when Membership Groups are enabled
- Import is only available when the Membership Group status is `pending`, `active`, or `delayed`
- System validates CSV structure before processing
- Required fields are first name, last name, email, and membership tier
- Optional fields are membership UUID and membership post ID

Person Identification

- Email address is used as the unique identifier
- If a matching person exists, that record is used
- If no matching person exists, a new person record is created in the MDP

Membership Handling

- A new individual membership record is created for each valid row
- If an existing membership UUID or post ID is provided, that membership is canceled
- If no identifier is provided, the system cancels the existing membership matching the provided membership tier
- If multiple memberships match the tier, the row is skipped and included in the error report
- Membership status is inherited from the group
- Membership start date follows the defined group rules
- Membership end date and expiration date are inherited from the group
- Group membership ID is stored on the individual membership
- Individual membership post ID is assigned to the Membership Group
- Membership tier line item is assigned to the group subscription with the individual membership post ID

Duplicate Prevention

- Duplicate rows with the same email and membership tier are rejected
- Multiple rows for the same email are allowed if the membership tier differs

Group Validation

- If a person already has a membership in the group with the same tier, the row is skipped
- Skipped rows are included in the error report

Import Processing

- Import supports partial success
- Valid rows are processed even if other rows fail
- Admin receives a summary of results after import
- Admin can download an error report CSV listing failed rows and reasons

## Admin > Remove from Group

As an admin, I can remove an individual membership from a Membership Group and choose what should happen to the member's membership after removal.

When removing a member from a Membership Group, the admin must choose one of two actions:

1. Cancel the individual membership
2. Continue the membership as an individual membership

Via Individual Membership Record

Steps:

- On individual membership records, there will be an option to `Remove from Membership Group`
- This option is only available when the individual membership is associated with a Membership Group
- Selecting `Remove from Membership Group` opens a modal
- The modal displays the following options:
  1. `Cancel Individual Membership`
  2. `Continue membership as Individual`
- Admin must confirm the action before proceeding

Option 1: Cancel Individual Membership

Result:

- The individual membership is end dated (canceled)
- Membership post ID is removed from the Membership Group
- Membership line item associated with the membership ID (`membership renewl id` meta) is removed from the subscription
- No replacement membership is created

Option 2: Continue Membership as Individual

Result:

- The existing individual membership associated with the group is end dated (canceled)
- Membership post ID is removed from the Membership Group
- Membership line item associated with the membership ID (`membership renewl id` meta) is removed from the subscription
- A new individual membership record is created:
  - Membership tier = same individual tier as the original membership
  - Start date = today
  - End date = same end date as the membership group
  - Expiration date = same expiration date as the membership group
  - Membership renewal type = determined by the membership tier default
- A subscription is created to match the membership:
  - Uses the WooCommerce product associated with the membership tier
  - New membership post ID added as `membership_renewal_id` meta on the line item
  - Subscription schedule matches the membership record
    - Next payment date is only populated if the membership renewal type = `subscription`

Acceptance Criteria:

Access

- `Remove from Membership Group` action is available on individual membership records associated with a group
- Selecting the action opens a modal with removal options

Cancel Individual Membership

- Membership is end dated
- Membership post ID is removed from the membership group
- Membership line item is removed from the group subscription
- No new membership is created

Continue as Individual

- Existing membership is end dated
- Membership post ID removed from the membership group
- Membership line item removed from the group subscription
- A new individual membership is created with the same membership tier
- Start date = today
- End date and expiration date match the membership group the user was removed from
- Membership renewal type determined by tier default
- A subscription is created using the WooCommerce product associated with the membership tier
- New membership post ID is stored as `membership_renewal_id` on the subscription line item
- Subscription schedule matches the membership record
- Next payment date is only populated if the membership renewal type = `subscription`

## Admin > Move to Another Group

As an admin, I can move an individual membership from one Membership Group to another Membership Group.

When moving a member between Membership Groups, the individual membership associated with the original group must be ended and a new individual membership record must be created and assigned to the new group.

Via Individual Membership Record

Steps:

- On individual membership records, there will be an option to `Move to Another Membership Group`
- This option is only available when the individual membership is associated with a group
- Selecting `Move to Another Membership Group` opens a modal
- User must select the destination Membership Group
- Lookup of all Membership Groups in the plugin
  - Displays:
    - Membership Group Name
    - Organization Name
- Destination Membership Group must be `pending`, `active` or `delayed` status
- Admin must confirm the action before proceeding

Result:

- The existing individual membership associated with the original group is end dated (canceled)
- Membership post ID is removed from the original Membership Group
- Membership related line item is removed from the group subscription
- The system then performs the `Add to Group (Existing Member)` process for the selected Membership Group.

New Membership Created:

- A new individual membership record is created.
- Group Membership ID assigned as meta.
- Membership status = inherited from the new group (`pending`, `active` or `delayed`)
  - `Start Date`
    - If today is within membership group start date and end date, start date = `today`
    - If today is before membership group start date, start date = `membership group start date`
    - If today is after membership group end date = `error`
    - Individuals cannot be added to a membership group beyond the membership group end date.
  - `End Date`
    - End date + expiration date inherited from the new membership group
  - Individual membership post ID assigned to the new membership group
  - Membership tier line item assigned to the new group subscription with individual membership post ID

Acceptance Criteria:

Access

- `Move to Another Membership Group` action is available on individual membership records associated with a group
- Selecting the action opens a modal
- Admin must select a destination membership group

Membership Group Selection

- Lookup displays all available membership groups
- Destination membership group must be `pending`, `active`, or `delayed`
- Admin must confirm the action before processing

Original Membership Handling

- Existing individual membership associated with the original group is end dated
- Membership post ID is removed from the original membership group
- Membership line item is removed from the original group subscription

New Membership Creation

- A new individual membership record is created
- Membership is associated with the selected membership group
- Membership status is inherited from the group
- Start date follows group start date rules
- End date and expiration date are inherited from the new membership group
- Individual membership post ID is assigned to the membership group
- Membership tier line item is assigned to the group subscription with the individual membership post ID

Validation Rules:

- A member cannot be moved to a group after the destination group end date
- A member cannot be moved if they already have the same membership tier in the destination group
- Destination group must allow additional members

Implementation Note (Important):

This feature should reuse the existing logic from:

- `Remove from Membership Group`
- `Add to Group (Existing Member)`

to ensure:

- Membership creation rules remain consistent
- Group membership metadata is handled the same way
- Subscription line item updates remain consistent

## Admin > Cancel Group

As an admin, I can cancel a Membership Group and choose what should happen to the individual memberships associated with the group.

When canceling a Membership Group, the admin must choose what should happen to the individual memberships currently in the group.

Via Membership Group Record

Steps:

- On Membership Group records, there will be an option to `Cancel Membership Group`
- This action is available when the Membership Group status is `pending`, `active` or `delayed`
- Selecting Manage Status opens a modal
  - Admin can update the status to `Cancelled`
  - The modal displays the following options for handling the individual memberships in the group:
    1. `Cancel all individual memberships`
    2. `Continue memberships as individual memberships`
- The modal displays the number of individual memberships currently in the group
- Additional option, displayed only when `Cancel all individual memberships` is selected
  - Admin must choose when the cancellation should take effect:
    1. `Cancel Immediately`
    2. `Cancel at Membership Group End Date`
- Admin must confirm the action before proceeding

Option 1: Cancel All Individual Memberships

Cancellation Timing:

Admin must choose one of the following:

Cancel Immediately

- The Membership Group is canceled immediately
- The group subscription is canceled immediately
- For each individual membership associated with the group:
  - The individual membership is end dated (canceled)
  - Group Membership ID meta remains for historical reference
- Members lose membership access when the cancellation takes effect.

Cancel at Membership Group End Date

- The Membership Group is marked as canceled (non-renewing)
- The group subscription is set to not renew
  - The expiration date is updated to match the end date (remove grace period)
- The group and associated memberships remain active until the existing group end date
  - At the group end date:
    - The Membership Group expires
    - The group subscription is canceled
- For each individual membership in the group:
  - The individual membership is end dated (canceled)
- No replacement memberships are created.

Option 2: Continue Memberships as Individual

Result:

- The Membership Group is canceled
- The group subscription is canceled
- For each individual membership associated with the group:
  - The existing individual membership associated with the group is end dated (canceled)
- A new individual membership record is created:
  - Membership tier = same individual tier as the original membership
  - Start date = today
  - End date = same end date as the membership group
  - Expiration date = same expiration date as the membership group
  - Membership renewal type = determined by the membership tier default
- A subscription is created to match the membership:
  - The individual member is assigned as the customer
  - Uses the WooCommerce product associated with the membership tier
  - New membership post ID added as `membership_renewal_id` meta on the line item
  - Subscription schedule matches the membership record
    - Next payment date is only populated if the membership renewal type = `subscription`
- Each member continues their membership independently after the group is canceled

Acceptance Criteria:

Access

- `Cancel Membership Group` action is available on Membership Group records
- Action is available when the Membership Group status is `pending`, `active`, or `delayed`
- Selecting the action opens a confirmation modal

Admin Selection

- Admin must choose how individual memberships should be handled:
  - Cancel all memberships
  - Continue memberships as individual
- If `Cancel all memberships` is selected:
  - Admin must choose cancellation timing:
    - Cancel immediately
    - Cancel at membership group end date
- Admin must confirm the action before processing

Cancel All Memberships

- Membership group status becomes canceled
- Group subscription is canceled
- All individual memberships associated with the group are end dated
- No new memberships are created

Continue as Individual Memberships

- Membership group status becomes canceled
- Group subscription is canceled
- Each individual membership in the group is end dated
- A new individual membership is created for each member
- Membership tier matches the original individual tier
- Start date = today
- End date and expiration date match the membership group
- Subscription is created using the WooCommerce product associated with the membership tier
- New membership post ID is stored as `membership_renewal_id` on the subscription line item
- Subscription schedule matches the membership record
- Next payment date is only populated if renewal type = `subscription`

## View Group Membership (Table)

As an admin, I can view Membership Groups in a table.

- The option is available in the Wicket Memberships menu when Membership Groups are enabled
- The table displays all unique membership groups, based on Membership Group ID
- The table includes columns for:
  - `Organization Name (MDP)`
  - `Owners (email)`
    - Lists email address(es) of each unique membership owner associated with a membership group
  - `Groups` column
    - Show all groups (names) in `Active`, `Delayed`, `Grace Period`, or `Pending` status, comma separated
    - Display = `Group 1(Status), Group 2(Status)`
    - If no record exists in any of `Active`, `Delayed`, `Grace Period`, or `Pending` status, meaning `Expired` or `Cancelled`, column displays `Inactive` label with no groups
  - `Last Updated Date`
    - Date of most recent update to any membership group record for the org
  - `Link to MDP`
    - Links to org record in MDP

Sorting:

- Ability to sort by column headers
- Default sorting by `Last Updated Date` (most recent)

Additional Tabs:

- Tab for active membership group records
  - Filtered view of group table for any organization with at least one membership group record in status = `active`
  - Label shows count of total records in that status
- Tab for pending membership group records
  - Filtered view of group table for any organization with at least one membership group record in status = `pending`
  - Label shows count of total records in that status
- Tab for grace period membership record
  - Filtered view of members table for any organization with at least one membership group record in status = `Grace Period`
  - Label shows count of total records in that status

## View Individual Group Members (Table)

Individual members who are part of a Membership Group will be listed in the Individual Members Table. Need to add a `Group` column to that table.

If any current membership for an individual is part of a Membership Group, the Group name will be listed.

Current = `Active`, `Pending`, `Delayed`, or `Grace Period` status.

- Sorting available by Membership Group name
- The individual members table can be filtered by Group
- Add filter option to table
- Default is `All Groups`
- Single select of all current groups in the plugin
- Current = `Active`, `Pending`, `Delayed`, or `Grace Period` status
- This could be many so should be a searchable dropdown

## View > Membership Group (Detail)

As an admin I can view the details of Membership Groups.

The detail page will include each record associated with the membership group.

Note: This page is modeled after existing membership plugin views.

- The detail view will include a title block for the membership group, including:
  - Membership Group Name
  - Link to Group in MDP (destination pending)
  - Organization name

- Unique records will be displayed as accordions
- Each item will include
  - Name
  - Record ID
  - Status
  - Start Date
  - End Date
  - Exp. Date
- Expanded details
  - Billing Info:
    - Subscription ID (linked)
    - Next payment date (from subscription schedule)(if applicable)
    - Order Details (if applicable)
      - Order # (linked)
      - Order date
      - Order Total
      - Order status
  - Admins can manage the record
    - Status
    - Dates
    - Renewal type
    - Membership owner
  - Admin can view a summary of group members
    - Includes a count of all individual members active in the group
    - Includes a breakdown of each individual membership tier included in the group. For each:
      - Tier Name
      - Member Count
      - Link to `View Members`
        - leads to individual membership table filtered by Tier + Group
    - Includes link to `Manage Group Members`
      - leads to individual membership table filtered by Group
- Admins can access `Group Actions`

## Group Membership Subscription

Membership Group subscriptions will have a line item for each individual membership included in the group. Subscriptions will include Membership Group ID as meta at the subscription level, and individual membership IDs as meta at the line item level.

- Subscription Customer = Membership Group Owner
- Subscription level meta:
  - Membership Group ID
  - Membership Group Membership Post ID
  - Organization (`UUID`, Org Name for display)
- Subscription line items:
  - 1 line item for each individual membership in the group
    - Product is determined by the individual membership tier
  - Each line item must include the individual membership post ID as meta
  - Each line item should include the individual member name (`Firstname Lastname`) as meta
- Subscription schedule is defined by the Membership Group config
  - Start date = date assigned at creation
  - Next Payment Date = Membership End Date
    - This value is calculated based on Membership Group config. Uses same logic as Membership Config (Wicket membership plugin)
  - End Date = Membership Expiration Date
    - This value is calculated based on Membership Group config. Uses same logic as Membership Config (Wicket membership plugin)
- Membership IDs on line items for the tier

## Edit Membership Group

As an admin I can edit details of the Membership Group.

- Admins can update the status of the membership group from:
  - `Active` to `Cancelled`
  - `Pending` to `Active` (TBC)
- Admins can update the dates on the Membership Group
  - Edits to the membership group dates cascade to individual memberships in the group
    - Edits do not cascade to individual records with an `expired` or `canceled` status
    - If the start date on the individual record is after the Group start date (before the change), the start date on the individual record is maintained
    - If the End date on the individual record is before the Group end date (before the change), the individual membership end date is maintained
    - If the Exp date on the individual record is before the Group exp date (before the change), the individual membership exp date is maintained
- Admins can update the Renewal Type
  - Follows established membership plugin rules
  - Subscription to Form Flow
    - Requires admin to select the URL for renewal
    - Removes Next payment date from related subscription
  - Form Flow to Subscription
    - Adds next payment date to related subscription
    - Next payment date = Membership Group End date
- Admins can update the Membership owner
  - Follows established membership plugin rules
  - Updates the customer on the related subscription and order
