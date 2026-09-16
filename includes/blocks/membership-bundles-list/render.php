<?php
/**
 * Server-side render callback for the wicket-memberships/membership-bundles-list block.
 *
 * Plain (non-ACF) dynamic block. WordPress includes this file directly per the
 * "render" key in block.json, with $attributes, $content, and $block already
 * defined in scope. No configurable attributes — this is a static list, not a
 * configurable widget, so nothing here needs saving back to post content. The
 * editor preview (index.js) calls this same render path via ServerSideRender.
 *
 * This one block renders TWO views of the same "Manage Group Membership"
 * flow, both living on whatever single page a site editor places it on:
 * the bundle list (default) and the bundle detail/manage screen, switched by
 * the presence of a `bundle_post_id` query param on the current URL. This
 * avoids needing a second page (and a way to find it) for the detail screen —
 * list.php's "Manage Bundle" links already just add that param to the
 * current URL.
 *
 * @package Wicket_Memberships
 */

namespace Wicket_Memberships;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

// Member-scoped: nothing to show a logged-out visitor, so bail before touching
// Alpine/REST at all rather than rendering an empty or broken shell.
if ( ! is_user_logged_in() ) {
  return;
}

// Alpine.js is expected to already be on the page — either enqueued by
// wicket-wp-base-plugin (non-Wicket-theme sites) or shipped by the Wicket
// theme itself. Neither path is guaranteed, so fail loudly with a visible
// notice instead of shipping x-data markup that silently does nothing.
if ( ! Membership_Bundle_Block_Controller::is_alpine_available() ) {
  echo '<div class="wicket-mship-bundle-list-error" role="alert">' .
    esc_html__( 'This feature is temporarily unavailable. Please try again later.', 'wicket-memberships' ) .
    '</div>';

  if ( current_user_can( 'manage_options' ) ) {
    echo '<p class="wicket-mship-bundle-list-error-detail">' .
      esc_html__( 'Admin note: Alpine.js is not available on this page, so the Membership Bundles List block cannot render. Enable Alpine via wicket-wp-base-plugin or the active theme.', 'wicket-memberships' ) .
      '</p>';
  }
  return;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view switch, not a form submission.
$bundle_post_id = isset( $_GET['bundle_post_id'] ) ? (int) $_GET['bundle_post_id'] : 0;

if ( $bundle_post_id > 0 ) {
  require WICKET_MEMBERSHIP_PLUGIN_DIR . 'templates/account-membership-bundles/detail.php';
  return;
}

require WICKET_MEMBERSHIP_PLUGIN_DIR . 'templates/account-membership-bundles/list.php';
