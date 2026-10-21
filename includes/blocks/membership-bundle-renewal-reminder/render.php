<?php
/**
 * Server-side render callback for the wicket-memberships/membership-bundle-renewal-reminder block.
 *
 * Plain (non-ACF) dynamic block. WordPress includes this file directly per the
 * "render" key in block.json, with $attributes, $content, and $block in scope.
 *
 * Intended for the Account Centre landing page: tells a bundle owner that one or
 * more of the bundles they own are due for renewal and links them to the
 * configured Manage Membership Bundles page, where each bundle is reviewed and
 * renewed. It never starts a renewal itself — an owner may have several bundles
 * due in the same period.
 *
 * Renders nothing for logged-out visitors, when membership bundles are disabled,
 * or when the user owns no bundle that is due (see
 * Membership_Bundle::get_owner_renewal_due_bundles()) — so it is safe on a page
 * every member sees. Server-rendered only: no Alpine, no REST call.
 *
 * @package Wicket_Memberships
 */

namespace Wicket_Memberships;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

if ( ! is_user_logged_in() ) {
  return;
}

// Respect the global membership bundles toggle: with bundles switched off the
// owner has no Manage Membership Bundles page to go to.
if ( empty( $_ENV['WICKET_MSHIP_ENABLE_BUNDLES'] ) ) {
  return;
}

// QA hint for ?wicket_wp_membership_debug_days=N — renders only when
// WICKET_MEMBERSHIPS_DEBUG_RENEW is on. Shown before the "nothing due" bail-out
// so a tester can tell "no bundle due on the simulated date" apart from
// "the override isn't active". Same notice as the Membership Bundles List block.
if ( ! empty( $_ENV['WICKET_MEMBERSHIPS_DEBUG_RENEW'] ) ) {
  // The notice's styles live in the list block's stylesheet, which only loads on
  // the manage page. Enqueue it here for debug sessions only; every selector in it
  // is scoped to .wicket-mship-* classes. A late enqueue is printed in the footer.
  wp_enqueue_style( generate_block_asset_handle( 'wicket-memberships/membership-bundles-list', 'style' ) );
  require WICKET_MEMBERSHIP_PLUGIN_DIR . 'templates/account-membership-bundles/debug-notice.php';
}

// Block attributes (see block.json / index.js). Empty strings fall back to
// Membership_Bundle::get_default_renewal_reminder_copy().
$renewal_reminder = Membership_Bundle::get_renewal_reminder( get_current_user_id(), [
  'heading'      => (string) ( $attributes['heading'] ?? '' ),
  'message'      => (string) ( $attributes['message'] ?? '' ),
  'button_label' => (string) ( $attributes['buttonLabel'] ?? '' ),
] );

if ( ! $renewal_reminder ) {
  return;
}

$renewal_reminder_show_list = ! isset( $attributes['showBundleList'] ) || (bool) $attributes['showBundleList'];

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'wicket-mship-bundle-renewal-reminder-block' ] ); // phpcs:ignore WordPress.Security.EscapeOutput -- core helper returns escaped attributes. ?>>
  <?php require WICKET_MEMBERSHIP_PLUGIN_DIR . 'templates/account-membership-bundles/renewal-reminder.php'; ?>
</div>
