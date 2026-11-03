<?php
/**
 * Membership Bundle - Coverage Callout
 *
 * One member-facing notice saying which organization is paying for the
 * member's membership(s) through one or more membership bundles. No buttons; the message may
 * contain the {site_name_contact_link} link. Copy and placeholder replacement
 * come from Membership_Bundle::get_coverage_callout().
 *
 * Markup mirrors wicket-wp-base-plugin's card-call-out component
 * (component-card-call-out) so it picks up the same theme styles as the
 * renewal callout (renewal-callout.php).
 *
 * Included by includes/blocks/membership-bundle-coverage-callout/render.php,
 * once per covering organization (see
 * Membership_Bundle::get_coverage_callouts_for_user()), after it has verified
 * the requester is logged in.
 *
 * @package Wicket_Memberships
 */

namespace Wicket_Memberships;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

/** @var array $coverage_callout Set by render.php — see Membership_Bundle::get_coverage_callouts_for_user(). */
?>
<div
  class="wicket-mship-bundle-coverage-callout"
  role="region"
  aria-label="<?php echo esc_attr( $coverage_callout['header'] ); ?>"
>
  <div class="component-card-call-out">
    <div class="text-heading-sm wicket-mship-bundle-coverage-callout__title">
      <?php echo esc_html( $coverage_callout['header'] ); ?>
    </div>

    <?php if ( ! empty( $coverage_callout['memberships'] ) ) : ?>
      <p class="wicket-mship-bundle-coverage-callout__memberships">
        <?php echo esc_html( implode( ', ', $coverage_callout['memberships'] ) ); ?>
      </p>
    <?php endif; ?>

    <?php if ( trim( wp_strip_all_tags( $coverage_callout['content'] ) ) !== '' ) : ?>
      <div class="wicket-mship-bundle-coverage-callout__content">
        <?php echo wp_kses_post( $coverage_callout['content'] ); ?>
      </div>
    <?php endif; ?>
  </div>
</div>
