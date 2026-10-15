<?php
/**
 * Membership Bundle - Renewal Callout Debug Notice
 *
 * QA helper shown at the top of the Membership Bundles List block (list and
 * detail views) when WICKET_MEMBERSHIPS_DEBUG_RENEW is on. Mirrors the
 * ACC callout debug notice so testers can preview the early_renewal /
 * grace_period callouts N days ahead via ?wicket_wp_membership_debug_days=N.
 *
 * Gated on WICKET_MEMBERSHIPS_DEBUG_RENEW (not WICKET_MEMBERSHIPS_DEBUG_ACC)
 * because that is the flag Membership_Bundle::get_renewal_reference_time()
 * checks — showing the hint without it would advertise a param that does nothing.
 *
 * Included by includes/blocks/membership-bundles-list/render.php.
 *
 * @package Wicket_Memberships
 */

namespace Wicket_Memberships;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

if ( empty( $_ENV['WICKET_MEMBERSHIPS_DEBUG_RENEW'] ) ) {
  return;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- debug-only read, gated by env flag.
$debug_days = isset( $_GET['wicket_wp_membership_debug_days'] ) ? (int) $_GET['wicket_wp_membership_debug_days'] : 0;
?>
<div class="wicket-mship-bundle-debug-notice" role="note">
  <p>
    <?php esc_html_e( 'For testing statuses and callouts add', 'wicket-memberships' ); ?>
    <code class="wicket-mship-bundle-debug-notice__code" title="<?php esc_attr_e( 'Click to copy', 'wicket-memberships' ); ?>">?wicket_wp_membership_debug_days=123</code>
    <?php esc_html_e( 'to see what statuses and callouts would appear in 123 days. Negative values look back in time.', 'wicket-memberships' ); ?>
  </p>
  <?php if ( $debug_days !== 0 ) : ?>
    <p class="wicket-mship-bundle-debug-notice__active">
      <?php
      printf(
        /* translators: 1: signed number of days, 2: simulated date. */
        esc_html__( 'Previewing statuses and callouts %1$s days from today (%2$s).', 'wicket-memberships' ),
        esc_html( sprintf( '%+d', $debug_days ) ),
        esc_html( wp_date( get_option( 'date_format' ), Membership_Bundle::get_renewal_reference_time() ) )
      );
      ?>
    </p>
  <?php endif; ?>
</div>
<script>
(function () {
  if (window.__wicketMshipBundleDebugCopyInit) { return; }
  window.__wicketMshipBundleDebugCopyInit = true;

  var showFeedback = function (code) {
    code.classList.remove('is-copied');
    void code.offsetWidth; // restart the animation on repeat clicks
    code.classList.add('is-copied');
    clearTimeout(code.__copyTimeout);
    code.__copyTimeout = setTimeout(function () { code.classList.remove('is-copied'); }, 1200);
  };

  document.addEventListener('click', function (event) {
    var code = event.target.closest('.wicket-mship-bundle-debug-notice__code');
    if (!code) { return; }
    var text = code.textContent.trim();
    var done = function () { showFeedback(code); };

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done);
      return;
    }
    // Fallback for non-secure (http) local dev sites, where navigator.clipboard is undefined.
    var input = document.createElement('textarea');
    input.value = text;
    document.body.appendChild(input);
    input.select();
    try { document.execCommand('copy'); done(); } catch (e) {}
    document.body.removeChild(input);
  });
})();
</script>
