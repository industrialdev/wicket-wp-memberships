<?php
/**
 * Server-side render callback for the wicket-memberships/membership-bundle-coverage-callout block.
 *
 * Plain (non-ACF) dynamic block. WordPress includes this file directly per the
 * "render" key in block.json, with $attributes, $content, and $block in scope.
 *
 * Wording, site name and site contact link are block attributes edited in the
 * block sidebar; {organization_name} and {bundle_owner_name} come from the
 * covering bundle, and the list of covered memberships is always shown.
 *
 * Renders one callout per organization currently paying for the logged-in
 * member's membership through one or more bundles (see
 * Membership_Bundle::get_coverage_callouts_for_user()), and nothing at all
 * otherwise — so it is safe to place on a page every member sees, such as the account centre dashboard. Server-rendered only: no Alpine,
 * no REST call.
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

// Block attributes (see block.json / index.js). Empty heading/message fall back to
// Membership_Bundle::get_default_coverage_callout_copy().
$coverage_settings = [
  'heading'           => (string) ( $attributes['heading'] ?? '' ),
  'message'           => (string) ( $attributes['message'] ?? '' ),
  'site_name'         => (string) ( $attributes['siteName'] ?? '' ),
  'site_contact_link' => (string) ( $attributes['siteContactLink'] ?? '' ),
];

$coverage_callouts = Membership_Bundle::get_coverage_callouts_for_user( get_current_user_id(), $coverage_settings );

if ( empty( $coverage_callouts ) ) {
  return;
}

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'wicket-mship-bundle-coverage-callouts' ] ); // phpcs:ignore WordPress.Security.EscapeOutput -- core helper returns escaped attributes. ?>>
  <?php
  foreach ( $coverage_callouts as $coverage_callout ) {
    require WICKET_MEMBERSHIP_PLUGIN_DIR . 'templates/account-membership-bundles/coverage-callout.php';
  }
  ?>
</div>
