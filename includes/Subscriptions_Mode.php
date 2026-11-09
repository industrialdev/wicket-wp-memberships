<?php

namespace Wicket_Memberships;

/**
 * WooCommerce Subscriptions live/staging mode, shown and switched from the settings page.
 *
 * WooCommerce Subscriptions treats the site as staging when its URL differs from the live URL
 * saved in the `wc_subscriptions_siteurl` option.
 *
 * @see \WCS_Staging
 * @package Wicket_Memberships
 */
class Subscriptions_Mode {

  /**
   * Manual-action query arg that switches WooCommerce Subscriptions to live mode.
   *
   * @var string
   */
  const LIVE_TRIGGER = 'wicket_mship_wcs_mode_live';

  /**
   * Manual-action query arg that switches WooCommerce Subscriptions to staging mode.
   *
   * @var string
   */
  const STAGING_TRIGGER = 'wicket_mship_wcs_mode_staging';

  /**
   * Whether the WooCommerce Subscriptions staging API is loaded.
   *
   * @since  1.0.123
   * @return bool
   */
  public static function is_available() {
    return class_exists( 'WCS_Staging' )
      && method_exists( 'WCS_Staging', 'is_duplicate_site' )
      && method_exists( 'WCS_Staging', 'set_duplicate_site_url_lock' )
      && method_exists( 'WCS_Staging', 'get_site_url_from_source' );
  }

  /**
   * Whether WooCommerce Subscriptions currently treats this site as staging.
   *
   * @since  1.0.123
   * @return bool
   */
  public static function is_staging() {
    return self::is_available() && \WCS_Staging::is_duplicate_site();
  }

  /**
   * Whether the saved live URL is this site, ignoring any `woocommerce_subscriptions_is_duplicate_site` filter.
   *
   * This is the state the switches change, so it decides which switch to offer.
   *
   * @since  1.0.123
   * @see    \WCS_Staging::is_duplicate_site()  Same host and path comparison, before the filter.
   * @return bool
   */
  public static function is_live_url_saved() {
    if ( ! self::is_available() ) {
      return false;
    }

    $site = wp_parse_url( \WCS_Staging::get_site_url_from_source( 'current_wp_site' ) );
    $live = wp_parse_url( \WCS_Staging::get_site_url_from_source( 'subscriptions_install' ) );

    // WCS ignores the scheme so http/https mismatches on hosts like WP Engine don't count as a move.
    return isset( $site['host'], $live['host'] )
      && $site['host'] === $live['host']
      && ( $site['path'] ?? '' ) === ( $live['path'] ?? '' );
  }

  /**
   * Saves this site's URL as the live URL, so automatic payments and subscription emails run here.
   *
   * @since  1.0.123
   * @return bool  False when WooCommerce Subscriptions is not loaded.
   */
  public static function switch_to_live() {
    if ( ! self::is_available() ) {
      return false;
    }

    \WCS_Staging::set_duplicate_site_url_lock();
    self::after_switch( 'live' );

    return true;
  }

  /**
   * Clears the saved live URL, so this site no longer matches it and runs in staging mode.
   *
   * @since  1.0.123
   * @return bool  False when WooCommerce Subscriptions is not loaded.
   */
  public static function switch_to_staging() {
    if ( ! self::is_available() ) {
      return false;
    }

    delete_option( 'wc_subscriptions_siteurl' );
    self::after_switch( 'staging' );

    return true;
  }

  /**
   * Stored auto-renew status depends on the mode, so refresh it for every membership.
   *
   * @param  string $mode  'live' or 'staging'.
   * @return void
   */
  private static function after_switch( $mode ) {
    Autorenew_Sync::enqueue_sweep();
    Utilities::wc_log_mship_error( [ 'wicket_mship_wcs_mode', 'Switched WooCommerce Subscriptions to ' . $mode . ' mode; autorenew status sweep enqueued' ] );
  }

  /**
   * Renders the Subscriptions Mode field: current mode, switch link and what staging changes.
   *
   * @since  1.0.123
   * @return void
   */
  public static function render() {
    if ( ! self::is_available() ) {
      echo '<p class="description">WooCommerce Subscriptions is not active.</p>';
      return;
    }

    // Core admin palette: dark yellow (#996800) and green (#008a20) stay readable on the grey background.
    if ( self::is_staging() ) {
      echo '<p><strong style="color:#996800;">Staging</strong>: automatic payments and subscription emails are off.</p>';
    } else {
      echo '<p><strong style="color:#008a20;">Live</strong>: automatic payments and subscription emails run from this site.</p>';
    }

    // A filter can force the reported mode, so the saved live URL picks the switch.
    if ( ! self::is_live_url_saved() ) {
      $link    = Settings_Jobs::get_manual_action_link( self::LIVE_TRIGGER );
      $label   = 'Switch to Live Mode';
      $confirm = 'Automatic payments and subscription emails will run from this site, so saved cards can be charged for real. Continue?';
    } else {
      $link    = Settings_Jobs::get_manual_action_link( self::STAGING_TRIGGER );
      $label   = 'Switch to Staging Mode';
      $confirm = 'Subscriptions will renew manually and subscription emails will stop on this site. Continue?';
    }

    printf(
      '<p><a class="button" href="%1$s" onclick="return confirm(%2$s);">%3$s</a></p>',
      esc_url( $link ),
      esc_attr( wp_json_encode( $confirm ) ),
      esc_html( $label )
    );
    echo '<p class="description">Controls whether subscriptions renew automatically and subscription emails are sent from this site. Payment gateway settings do not change: a gateway in sandbox or test mode stays there.</p>';
    Settings_Page::render_accordion( 'wicket-mship-mode-comparison', 'Live vs. Staging', [ __CLASS__, 'render_mode_comparison' ] );
  }

  /**
   * Echoes a table of what differs between live and staging mode, plus the auto-renew testing steps.
   *
   * @since  1.0.123
   * @return void
   */
  public static function render_mode_comparison() {
    $rows = [
      'Subscription renewals'            => [ 'Charged automatically for subscriptions set to auto-renew.', 'Created as pending manual orders. No payment is taken.' ],
      'Subscription emails and reminders' => [ 'Sent.', 'Not sent, unless <code>WCS_FORCE_EMAIL</code> is defined.' ],
      'Member auto-renew toggle'         => [ 'Members can turn auto-renew on.', 'Members cannot turn auto-renew on.' ],
      'Wicket Memberships auto-renew'    => [ 'Reported from each subscription.', 'Reported as off for every membership.' ],
      'WooPayments subscriptions'        => [ 'Synced with Stripe.', 'Not synced; Stripe webhooks are ignored.' ],
      'Deleting a Stripe saved card'     => [ 'Also removes it in Stripe.', 'Keeps it in Stripe.' ],
      'Admin screens'                    => [ 'No staging notice.', 'Staging badge and notice on every admin page.' ],
      'Payment gateway settings'         => [ 'Not changed. Sandbox or test mode stays on.', 'Not changed. Sandbox or test mode stays on.' ],
    ];
    ?>
      <table class="widefat striped">
        <?php // td, not th: the settings page's .form-table th styles would leak into this nested table. ?>
        <thead>
          <tr><td><strong>Setting</strong></td><td><strong>Live</strong></td><td><strong>Staging</strong></td></tr>
        </thead>
        <tbody>
          <?php foreach ( $rows as $setting => [ $live, $staging ] ) : ?>
            <tr><td><strong><?php echo esc_html( $setting ); ?></strong></td><td><?php echo $live; ?></td><td><?php echo $staging; ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p>Each subscription keeps its own auto-renew setting, so switching back restores it.</p>
      <p><strong>Testing auto-renewals on a staging site:</strong> put the payment gateways in sandbox or test mode, switch to Live, run the renewals, then switch back to Staging.</p>
    <?php
  }
}
