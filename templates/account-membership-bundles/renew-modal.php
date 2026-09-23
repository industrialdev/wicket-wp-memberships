<?php
/**
 * Membership Bundle - Renew Modal
 *
 * Server-rendered confirmation modal for renewing a membership bundle through
 * its WooCommerce subscription, opened by the "Renew" button in
 * renewal-callout.php. Everything it displays — the membership count and the
 * per-tier summary — is rendered here in PHP from
 * Membership_Bundle::get_renewal_summary(); Alpine only toggles visibility.
 *
 * "Generate Order" is a plain form POST to admin-post.php, handled by
 * Membership_Bundle_Block_Controller::handle_renewal_order_request(), which
 * re-validates the renewal window, reuses an unpaid renewal order for this
 * term if one exists (otherwise creates one), and redirects to the
 * WooCommerce pay-for-order page. Failures come back to the detail view with
 * ?bundle_renewal_error=<code>.
 *
 * Only rendered for the subscription renewal flow — form-page renewals link
 * straight to the form from the callout instead.
 *
 * Included by detail.php, which has already verified the requester is
 * logged in, is the bundle owner, and passes
 * Membership_Bundle::check_current_user_access(). Do not include this file
 * directly from anywhere else without the same guards.
 *
 * @package Wicket_Memberships
 */

namespace Wicket_Memberships;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

/**
 * @var int   $bundle_post_id  Set by detail.php before requiring this file.
 * @var array $renewal_summary Set by detail.php — see Membership_Bundle::get_renewal_summary().
 */

$renew_title = sprintf(
  /* translators: %d: number of memberships in the bundle that will be renewed. */
  _n( 'You are going to renew %d membership', 'You are going to renew %d memberships', $renewal_summary['total'], 'wicket-memberships' ),
  $renewal_summary['total']
);

// Back to this same detail view on failure, minus any error from a previous attempt.
$renew_return_url = remove_query_arg( Membership_Bundle_Block_Controller::RENEWAL_ERROR_QUERY_ARG );
?>
<div
  class="wicket-mship-renew-modal"
  x-data="{ open: false, submitting: false }"
  x-on:wicket-mship-open-renew-modal.window="open = true"
  x-on:keydown.escape.window="if ( ! submitting ) open = false"
  x-show="open"
  x-cloak
  style="display: none;"
  role="dialog"
  aria-modal="true"
  aria-labelledby="wicket-mship-renew-modal-title-<?php echo esc_attr( $bundle_post_id ); ?>"
>
  <div class="wicket-mship-renew-modal__overlay" x-on:click="if ( ! submitting ) open = false"></div>

  <div class="wicket-mship-renew-modal__panel">
    <div class="wicket-mship-renew-modal__header">
      <h2 class="text-heading-xs wicket-mship-renew-modal__title" id="wicket-mship-renew-modal-title-<?php echo esc_attr( $bundle_post_id ); ?>">
        <?php echo esc_html( $renew_title ); ?>
      </h2>

      <button type="button" class="wicket-mship-renew-modal__close" x-on:click="open = false" :disabled="submitting">
        <?php esc_html_e( 'Close', 'wicket-memberships' ); ?>
        <span aria-hidden="true">✕</span>
      </button>
    </div>

    <div class="wicket-mship-renew-modal__body">
      <p class="wicket-mship-renew-modal__summary-heading"><?php esc_html_e( 'Summary', 'wicket-memberships' ); ?></p>

      <?php if ( empty( $renewal_summary['tiers'] ) ) : ?>
        <p class="wicket-mship-renew-modal__empty">
          <?php esc_html_e( 'There are no memberships in this bundle to renew.', 'wicket-memberships' ); ?>
        </p>
      <?php else : ?>
        <table class="wicket-mship-renew-modal__summary">
          <caption class="screen-reader-text"><?php esc_html_e( 'Memberships to renew by tier', 'wicket-memberships' ); ?></caption>
          <thead class="screen-reader-text">
            <tr>
              <th scope="col"><?php esc_html_e( 'Tier', 'wicket-memberships' ); ?></th>
              <th scope="col"><?php esc_html_e( 'Memberships', 'wicket-memberships' ); ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ( $renewal_summary['tiers'] as $tier_row ) : ?>
              <tr>
                <td class="wicket-mship-renew-modal__tier-name"><?php echo esc_html( $tier_row['name'] ); ?></td>
                <td class="wicket-mship-renew-modal__tier-count"><?php echo esc_html( number_format_i18n( $tier_row['count'] ) ); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <form
        class="wicket-mship-renew-modal__footer"
        method="post"
        action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
        x-on:submit="submitting = true"
      >
        <input type="hidden" name="action" value="<?php echo esc_attr( Membership_Bundle_Block_Controller::RENEWAL_ORDER_ACTION ); ?>" />
        <input type="hidden" name="bundle_post_id" value="<?php echo esc_attr( $bundle_post_id ); ?>" />
        <input type="hidden" name="return_url" value="<?php echo esc_attr( $renew_return_url ); ?>" />
        <?php
        wp_nonce_field( Membership_Bundle_Block_Controller::RENEWAL_ORDER_ACTION . '_' . $bundle_post_id );

        // Carry the QA date override through the POST so the handler evaluates the
        // same window the callout was rendered for (see get_renewal_reference_time()).
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- debug-only read, gated by env flag.
        if ( ! empty( $_ENV['WICKET_MEMBERSHIPS_DEBUG_RENEW'] ) && ! empty( $_GET['wicket_wp_membership_debug_days'] ) ) :
          ?>
          <input type="hidden" name="wicket_wp_membership_debug_days" value="<?php echo esc_attr( (int) $_GET['wicket_wp_membership_debug_days'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>" />
          <?php
        endif;

        get_component( 'button', [
          'variant' => 'secondary',
          'size'    => 'sm',
          'label'   => __( 'Cancel', 'wicket-memberships' ),
          'type'    => 'button',
          'atts'    => [
            'x-on:click="open = false"',
            ':disabled="submitting"',
          ],
        ] );
        get_component( 'button', [
          'variant' => 'primary',
          'size'    => 'sm',
          'label'   => '',
          'type'    => 'submit',
          'atts'    => [
            // Nothing to bill → never submittable (the handler would reject it anyway).
            empty( $renewal_summary['total'] ) ? ':disabled="true"' : ':disabled="submitting"',
            'x-text' => sprintf(
              'submitting ? %s : %s',
              wp_json_encode( __( 'Generating order…', 'wicket-memberships' ) ),
              wp_json_encode( __( 'Generate Order', 'wicket-memberships' ) )
            ),
          ],
        ] );
        ?>
      </form>
    </div>
  </div>
</div>
