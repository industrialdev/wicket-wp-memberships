<?php
/**
 * Membership Bundle - Renewal Reminder
 *
 * Owner-facing notice for the Account Centre landing page: one or more of the
 * bundles the member owns are due for renewal. A single card for all of them,
 * an optional list of the bundles that are due, and one button to the Manage
 * Membership Bundles page. No renewal or checkout starts from here — each
 * bundle is renewed from its own detail view (renewal-callout.php).
 *
 * Yellow (early_renewal) while every bundle is still inside its renewal window;
 * red (grace_period) as soon as any of them is past its end date. Colors match
 * renewal-callout.php. Copy and data come from
 * Membership_Bundle::get_renewal_reminder().
 *
 * Markup mirrors wicket-wp-base-plugin's card-call-out component
 * (component-card-call-out / __title / __links), like the other callouts here.
 *
 * Included by includes/blocks/membership-bundle-renewal-reminder/render.php,
 * which has already verified the requester is logged in and built the reminder
 * from bundles they own.
 *
 * @package Wicket_Memberships
 */

namespace Wicket_Memberships;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

/**
 * @var array $renewal_reminder           Set by render.php — see Membership_Bundle::get_renewal_reminder().
 * @var bool  $renewal_reminder_show_list Set by render.php — the block's showBundleList attribute.
 */

// Dates are shown on the MDP-local day (same as the list/detail views) with the
// raw ISO 8601 string in the title tooltip — the server-rendered equivalent of
// formatDateWithTooltip().
try {
  $reminder_timezone = new \DateTimeZone( $_ENV['WICKET_MSHIP_MDP_TIMEZONE'] ?? 'UTC' );
} catch ( \Exception $e ) {
  $reminder_timezone = new \DateTimeZone( 'UTC' );
}
$reminder_date_format = get_option( 'date_format' );

$reminder_manage_url = $renewal_reminder['manage_url'];
?>
<div
  class="wicket-mship-bundle-renewal-reminder wicket-mship-bundle-renewal-reminder--<?php echo esc_attr( $renewal_reminder['type'] ); ?>"
  role="region"
  aria-label="<?php echo esc_attr( $renewal_reminder['header'] ); ?>"
>
  <div class="component-card-call-out">
    <div class="text-heading-sm wicket-mship-bundle-renewal-reminder__title">
      <?php echo esc_html( $renewal_reminder['header'] ); ?>
    </div>

    <?php if ( trim( wp_strip_all_tags( $renewal_reminder['content'] ) ) !== '' ) : ?>
      <div class="wicket-mship-bundle-renewal-reminder__content">
        <?php echo wp_kses_post( $renewal_reminder['content'] ); ?>
      </div>
    <?php endif; ?>

    <?php if ( $renewal_reminder_show_list && ! empty( $renewal_reminder['bundles'] ) ) : ?>
      <ul class="wicket-mship-bundle-renewal-reminder__bundles">
        <?php foreach ( $renewal_reminder['bundles'] as $due_bundle ) : ?>
          <?php
          $is_lapsed = $due_bundle['type'] === Membership_Bundle::CALLOUT_GRACE_PERIOD;

          // Same "Org – Bundle" label as the list view's cards.
          $due_bundle_label = implode( ' – ', array_filter( [ $due_bundle['org_name'], $due_bundle['bundle_name'] ] ) );
          if ( $due_bundle_label === '' ) {
            $due_bundle_label = __( 'Membership bundle', 'wicket-memberships' );
          }

          // Lapsed: the date that matters is the end of the grace period. Otherwise
          // the renewal (end) date.
          $due_date_iso   = $is_lapsed && $due_bundle['expires_at'] !== '' ? $due_bundle['expires_at'] : $due_bundle['ends_at'];
          $due_date_label = $is_lapsed && $due_bundle['expires_at'] !== ''
            ? __( 'Renew by', 'wicket-memberships' )
            : __( 'Renewal date', 'wicket-memberships' );
          $due_date_ts    = $due_date_iso !== '' ? strtotime( $due_date_iso ) : false;

          // Deep link to this bundle's detail view on the manage page — the same
          // bundle_post_id param the list view's "Manage Bundle" buttons add.
          $due_bundle_url = $reminder_manage_url !== ''
            ? add_query_arg( 'bundle_post_id', (int) $due_bundle['bundle_post_id'], $reminder_manage_url )
            : '';
          ?>
          <li class="wicket-mship-bundle-renewal-reminder__bundle">
            <?php if ( $due_bundle_url !== '' ) : ?>
              <a class="wicket-mship-bundle-renewal-reminder__bundle-name" href="<?php echo esc_url( $due_bundle_url ); ?>"><?php echo esc_html( $due_bundle_label ); ?></a>
            <?php else : ?>
              <span class="wicket-mship-bundle-renewal-reminder__bundle-name"><?php echo esc_html( $due_bundle_label ); ?></span>
            <?php endif; ?>

            <span class="wicket-mship-bundle-renewal-reminder__badge wicket-mship-bundle-renewal-reminder__badge--<?php echo esc_attr( $due_bundle['type'] ); ?>">
              <?php
              echo esc_html( $is_lapsed
                ? __( 'Lapsed', 'wicket-memberships' )
                : __( 'Renewal due', 'wicket-memberships' ) );
              ?>
            </span>

            <?php if ( $due_date_ts ) : ?>
              <span class="wicket-mship-bundle-renewal-reminder__date">
                <?php echo esc_html( $due_date_label ); ?>:
                <time datetime="<?php echo esc_attr( $due_date_iso ); ?>" title="<?php echo esc_attr( $due_date_iso ); ?>"><?php echo esc_html( wp_date( $reminder_date_format, $due_date_ts, $reminder_timezone ) ); ?></time>
              </span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ( $reminder_manage_url !== '' ) : ?>
      <div class="component-card-call-out__links wicket-mship-bundle-renewal-reminder__links">
        <?php
        if ( function_exists( 'get_component' ) ) {
          get_component( 'button', [
            'variant' => 'primary',
            'size'    => 'sm',
            'label'   => $renewal_reminder['button_label'],
            'a_tag'   => true,
            // The button component interpolates this into href='' unescaped.
            'link'    => esc_url( $reminder_manage_url ),
          ] );
        } else {
          printf(
            '<a class="button wicket-mship-bundle-renewal-reminder__button" href="%1$s">%2$s</a>',
            esc_url( $reminder_manage_url ),
            esc_html( $renewal_reminder['button_label'] )
          );
        }
        ?>
      </div>
    <?php elseif ( current_user_can( 'manage_options' ) ) : ?>
      <p class="wicket-mship-bundle-renewal-reminder__admin-note">
        <?php esc_html_e( 'Admin note: no "Manage Membership Bundles" page is set (Settings > Wicket Memberships > Membership Bundles), so this reminder has no button. Members see the reminder without a link.', 'wicket-memberships' ); ?>
      </p>
    <?php endif; ?>
  </div>
</div>
