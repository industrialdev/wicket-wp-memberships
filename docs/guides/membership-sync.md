---
title: "Subscription Sync Tool"
audience: [implementer, support]
---

# Subscription Sync Tool

The Subscription Sync tool links existing WooCommerce subscriptions to their membership records. It is meant for use **after a membership import** where subscriptions were *not* created during the import, but matching subscriptions already exist and need to be connected so renewals work correctly.

For each active or on-hold subscription, the tool finds the membership belonging to the same user (matched by email) on the same tier (resolved from the product on the subscription), and links the subscription, order, and product information back to that membership record.

## Opening the Tool

The tool is only available while **MDP Import** is enabled in the Wicket Memberships settings. When it is enabled, a link to open the tool appears in the import instructions on the settings page. The tool opens on its own page showing only the WordPress admin bar.

Opening the page does **not** run anything — it shows the controls so you can choose what to do first.

## The Controls

- **Mode — Dry run / LIVE.** Dry run is always selected when the page loads. A dry run shows exactly what *would* happen without changing anything. To make real changes you must select **LIVE** and tick the **"I understand LIVE writes data"** box; only then will the Run button apply changes.
- **Run.** Performs the sync for the current page using the selected mode.
- **Count Only.** Reports how many subscriptions exist and how many contain membership products. It makes no changes and is a safe way to gauge the size of the job.
- **Created after.** Optional date filter. Leave it empty to include everything; once set, it stays applied as you move between pages. Use the clear icon to remove it.
- **Per page.** How many subscriptions to process per page.
- **Prev / Next.** Move between pages. The bar shows your current page and position within the total. Moving between pages always runs as a safe dry run — it never writes.
- **Single subscription ID.** Process just one subscription by its ID. This overrides the pagination and date filter.

## Special Functionality

### Per-seat organization tiers — seat count sync

When a subscription being synced belongs to an **organization** tier configured as **per seat**, the tool uses the **subscription's product quantity** as the membership's seat count. That quantity is saved on the membership and written back to the MDP as the maximum number of people who can be assigned to the membership.

Notes:

- This applies **only** to per-seat organization tiers. Individual tiers and range-based organization tiers are not affected by this behavior.
- A quantity below 1 is treated as **unlimited**.
- The seat count is only written to the MDP on a **LIVE** run. A dry run just reports what the seat count would be.
