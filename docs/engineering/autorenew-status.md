---
title: "Autorenew Status: Autorenew::is_autorenewing() and local testing"
audience: [developer]
php_class: Autorenew
source_files: ["includes/Autorenew.php"]
---

# Autorenew Status

`Autorenew::is_autorenewing($membership)` is the single source of
truth for whether an individual membership's linked subscription will
actually auto-renew. See `Class-Autorenew.md` for the method summary.

It checks, in order: linked subscription exists, subscription status is
active, not a staging/duplicate site, the manual-renewal flag, and (for
non-free subscriptions) whether the payment method supports scheduled
payments — with one documented exception, below, for a payment method
that lacks that support.

## Exception: gateways that don't declare scheduled-payment support

Some payment gateway plugins never declare `gateway_scheduled_payments`
support at all, even when they can genuinely process an automatic charge
(confirmed for the WooCommerce Stripe gateway — see
`atlas/quirks/stripe-gateway-scheduled-payments-gap.md`). WooCommerce
Subscriptions' own cron path checks that declared support before
attempting an automatic charge, so on an affected site, subscriptions get
put on-hold for manual renewal regardless of their actual capability.

This situation — Stripe + WCS + auto-renewal — can be worked around with
an AutomateWoo workflow that fires the renewal-payment action directly,
skipping WCS's check entirely (see
`atlas/quirks/automatewoo-forced-autorenew-workflow.md` for the mechanism
and a real example). `Autorenew::resolve_status()` detects this
internally via a private `has_forced_workflow()` check (not part of the
public API — verify the exception through `resolve_status()`'s output,
not a direct call): if a **published** AutomateWoo workflow exists with
the exact title `Wicket: Force Subscription Auto-Renewal`, the
gateway-support check is skipped and the subscription is treated as
autorenewing.

The workflow lookup (`get_posts()`) is cached in the
`wicket_mship_has_forced_autorenew_workflow` transient for 1 hour, since
`Autorenew::resolve_status()` can run once per membership on an admin
list/report page. The transient is cleared immediately on any
`aw_workflow` save or trash (`save_post_aw_workflow`, `trashed_post`), so
publishing or disabling the workflow takes effect right away rather than
waiting out the hour.

This is a narrow, intentional exception — it exists only to match this
one documented workaround, not as a general escape hatch for other
payment-gateway gaps. See the linked quirk docs before creating this
workflow on a new site, and use the exact title above; the match is exact,
not fuzzy.

## Why local testing needs setup first

WooCommerce Subscriptions flags almost every local/dev environment as a
staging site (its stored `wc_subscriptions_siteurl` option won't match a
local URL like `https://localhost/wp`). On a flagged site, WCS itself
refuses to run automatic subscription charges and falls back to a manual
renewal order — this is a real block in WCS's own renewal logic, not
something specific to this plugin. `Autorenew::is_autorenewing()` checks
the same flag, so it will report `false` locally until this is addressed.

## Steps to test auto-renew locally

1. **Confirm the site is flagged as staging.**
   ```
   wicket wp option get wc_subscriptions_siteurl
   wicket wp option get siteurl
   ```
   If these don't match, WCS is treating this site as a staging copy.

2. **Override staging detection for this environment.**
   A ready-to-use mu-plugin is checked in at
   `src/web/app/mu-plugins/test-force-not-staging.php`:
   ```php
   add_filter( 'woocommerce_subscriptions_is_duplicate_site', '__return_false' );
   ```
   This hooks WCS's own sanctioned override filter
   (`woocommerce_subscriptions_is_duplicate_site`, WCS core
   `class-wcs-staging.php`).

3. **Verify the override took effect.**
   ```
   wicket wp eval 'echo (WCS_Staging::is_duplicate_site() ? "STILL STAGING" : "NOW LIVE");'
   ```
   Expect `NOW LIVE`.

4. **Set up a subscription to auto-renew.**
   - Ensure the subscription status is `active`.
   - Ensure `_requires_manual_renewal` is `false` (via the autorenew toggle,
     or `wcs_get_subscription($id)->update_meta_data('_requires_manual_renewal', 'false')`
     + `->save()`).
   - Ensure a payment method is set that supports scheduled payments (e.g.
     Stripe with a saved card; check the gateway's `supports` array if
     unsure).

5. **Confirm the computed status.**
   ```
   wicket wp eval 'echo (\Wicket_Memberships\Autorenew::is_autorenewing(["membership_subscription_id" => <id>]) ? "true" : "false");'
   ```

6. **Trigger the renewal** (via WCS's renewal cron/scheduled action, or by
   manually invoking the subscription's renewal processing) and confirm an
   automatic charge is attempted, not a manual renewal order.

7. **Remove or rename `test-force-not-staging.php` when done.** Mu-plugins
   load unconditionally with no admin toggle — it stays active for as long
   as the file exists in `mu-plugins/`.

## Related

- Method reference: [`Class-Autorenew.md`](Class-Autorenew.md)
- Full WCS staging mechanics: `woocommerce-subscriptions/includes/core/class-wcs-staging.php`
- Renewal execution path: `WC_Subscriptions_Manager::process_renewal()`,
  `woocommerce-subscriptions/includes/core/class-wc-subscriptions-manager.php:139`
- Gateway-support exception: `atlas/quirks/stripe-gateway-scheduled-payments-gap.md`,
  `atlas/quirks/automatewoo-forced-autorenew-workflow.md`
