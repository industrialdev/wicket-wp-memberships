<?php
/**
 * Membership Bundle - Renewal Callout
 *
 * Server-rendered callout shown at the top of the bundle detail view when the
 * bundle is inside its renewal window (early_renewal, yellow) or its grace
 * period (grace_period, red). Whether it shows at all, and its copy, come
 * from Membership_Bundle::get_renewal_callout() — the same rules that feed
 * bundle owner callouts to ACC's ac-callout block — so no REST call is needed.
 *
 * Markup mirrors wicket-wp-base-plugin's card-call-out component
 * (component-card-call-out / __title / __links) so it picks up the same theme
 * styles, but is written out here rather than rendered via
 * get_component( 'card-call-out' ): that component only renders <a> links,
 * and the subscription flow needs a <button> that opens renew-modal.php.
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

/** @var array $renewal_callout Set by detail.php — see Membership_Bundle::get_renewal_callout(). */
?>
<div
  class="wicket-mship-bundle-renewal-callout wicket-mship-bundle-renewal-callout--<?php echo esc_attr( $renewal_callout['type'] ); ?>"
  role="region"
  aria-label="<?php echo esc_attr( $renewal_callout['header'] ); ?>"
>
  <div class="component-card-call-out">
    <div class="text-heading-sm wicket-mship-bundle-renewal-callout__title">
      <?php echo esc_html( $renewal_callout['header'] ); ?>
    </div>

    <?php if ( $renewal_callout['content'] !== '' ) : ?>
      <div class="wicket-mship-bundle-renewal-callout__content">
        <?php echo wp_kses_post( $renewal_callout['content'] ); ?>
      </div>
    <?php endif; ?>

    <div class="component-card-call-out__links wicket-mship-bundle-renewal-callout__links">
      <?php
      if ( $renewal_callout['flow'] === Membership_Bundle::RENEWAL_FLOW_FORM_PAGE ) {
        // Form Flow: the form collects payment and creates the new term itself,
        // so there is nothing to confirm here — link straight to it.
        get_component( 'button', [
          'variant' => 'primary',
          'size'    => 'sm',
          'label'   => $renewal_callout['button_label'],
          'a_tag'   => true,
          // The button component interpolates this into href='' unescaped.
          'link'    => esc_url( $renewal_callout['form_url'] ),
        ] );
      } else {
        // Subscription flow: open the summary/confirmation modal (renew-modal.php),
        // decoupled via a window event like the Add/Remove Member modals.
        get_component( 'button', [
          'variant' => 'primary',
          'size'    => 'sm',
          'label'   => $renewal_callout['button_label'],
          'type'    => 'button',
          'atts'    => [
            'x-on:click="window.dispatchEvent(new CustomEvent(\'wicket-mship-open-renew-modal\'))"',
          ],
        ] );
      }
      ?>
    </div>
  </div>
</div>
