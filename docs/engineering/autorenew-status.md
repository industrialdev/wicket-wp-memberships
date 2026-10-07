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
non-free subscriptions) whether a payment method is on file.

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
   - Ensure a payment method is on file (e.g. Stripe with a saved card).

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
