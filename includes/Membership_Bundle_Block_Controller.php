<?php

namespace Wicket_Memberships;

/**
 * Registers the membership-bundles-list Gutenberg block.
 *
 * Plain (non-ACF) dynamic block: block.json declares a "render" file, so
 * WordPress calls includes/blocks/membership-bundles-list/render.php directly
 * with no editor script and no attributes to manage. Kept intentionally
 * separate from the REST/admin controllers in this plugin since block
 * registration is a distinct concern (init-time, editor-facing) from REST
 * routing or admin CRUD.
 *
 * @package Wicket_Memberships
 */
class Membership_Bundle_Block_Controller {

  /**
   * Nav menu item IDs (or CSS IDs assigned to the menu item's markup) that the
   * "requires attention" badge is rendered against. Covers the desktop and
   * mobile account-menu placements, each duplicated for the logged-in-member
   * "two" variant used elsewhere in the theme.
   */
  private const NAV_BADGE_MENU_IDS = [
    'wicket-acc-menu',
    'wicket-acc-menu-mobile',
    'wicket-acc-menu-two',
    'wicket-acc-menu-mobile-two',
  ];

  /**
   * admin-post.php action (and nonce action prefix) for the renewal modal's
   * "Generate Order" form in templates/account-membership-bundles/renew-modal.php.
   */
  public const RENEWAL_ORDER_ACTION = 'wicket_mship_bundle_renewal_order';

  /**
   * Query arg the renewal order handler adds to the return URL on failure;
   * read back by detail.php to show get_renewal_error_message().
   */
  public const RENEWAL_ERROR_QUERY_ARG = 'bundle_renewal_error';

  public function __construct() {
    add_action( 'init', [ $this, 'register_block' ] );
    add_action( 'wp_head', [ $this, 'render_nav_badge_style' ] );
    // Logged-in only: no nopriv hook, so an anonymous POST never reaches the handler.
    add_action( 'admin_post_' . self::RENEWAL_ORDER_ACTION, [ $this, 'handle_renewal_order_request' ] );
  }

  /**
   * Handle the renewal modal's "Generate Order" submission.
   *
   * A plain form POST (not REST) since the whole detail view's renewal UI is
   * server-rendered: on success the owner is redirected straight to the
   * WooCommerce pay-for-order page; on failure back to the detail view with
   * RENEWAL_ERROR_QUERY_ARG set so the template can explain what happened.
   *
   * @return void
   */
  public function handle_renewal_order_request(): void {
    // phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified below, once bundle_post_id is known.
    $bundle_post_id = isset( $_POST['bundle_post_id'] ) ? absint( $_POST['bundle_post_id'] ) : 0;
    $return_url     = isset( $_POST['return_url'] ) ? esc_url_raw( wp_unslash( $_POST['return_url'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Missing

    // Only ever send the owner back to a same-site URL, whatever was posted.
    $return_url = wp_validate_redirect( $return_url, home_url( '/' ) );
    $return_url = remove_query_arg( self::RENEWAL_ERROR_QUERY_ARG, $return_url );

    // Nonce is scoped to the bundle so a token rendered for one bundle can't be replayed on another.
    check_admin_referer( self::RENEWAL_ORDER_ACTION . '_' . $bundle_post_id );

    $bundle = new Membership_Bundle( $bundle_post_id );
    if ( ! $bundle->post_id ) {
      $this->redirect_with_renewal_error( $return_url, 'not_found' );
    }

    // Owner first (cheap, local): the order is the subscription customer's to pay.
    // Then the same org-connection check the member REST routes use.
    if ( ! $bundle->is_current_user_owner() || is_wp_error( Membership_Bundle::check_current_user_access( $bundle_post_id ) ) ) {
      $this->redirect_with_renewal_error( $return_url, 'forbidden' );
    }

    $order = $bundle->get_or_create_renewal_order();
    if ( is_wp_error( $order ) ) {
      Utilities::wc_log_mship_error( [ 'handle_renewal_order_request: renewal order failed', [
        'bundle_post_id' => $bundle_post_id,
        'user_id'        => get_current_user_id(),
        'error'          => $order->get_error_code() . ': ' . $order->get_error_message(),
      ] ] );
      $this->redirect_with_renewal_error( $return_url, $order->get_error_code() );
    }

    wp_safe_redirect( $order->get_checkout_payment_url() );
    exit;
  }

  /**
   * Redirect back to the detail view with a renewal error code and stop.
   *
   * @param string $return_url
   * @param string $code
   * @return never
   */
  private function redirect_with_renewal_error( string $return_url, string $code ): never {
    wp_safe_redirect( add_query_arg( self::RENEWAL_ERROR_QUERY_ARG, sanitize_key( $code ), $return_url ) );
    exit;
  }

  /**
   * Member-facing message for a renewal error code set by handle_renewal_order_request().
   *
   * Plain language only — the underlying WP_Error message is logged, never shown.
   *
   * @param string $code
   * @return string Empty string for an unknown/empty code.
   */
  public static function get_renewal_error_message( string $code ): string {
    switch ( $code ) {
      case '':
        return '';
      case 'not_renewable':
        return __( 'This membership bundle is not currently open for renewal. It may already have been renewed.', 'wicket-memberships' );
      case 'no_members':
        return __( 'There are no memberships in this bundle to renew.', 'wicket-memberships' );
      case 'forbidden':
      case 'not_found':
        return __( 'You do not have permission to renew this membership bundle.', 'wicket-memberships' );
      default:
        return __( "We couldn't generate your renewal order. Please try again, or contact us if the problem continues.", 'wicket-memberships' );
    }
  }

  /**
   * Register the membership-bundles-list block from its block.json.
   */
  public function register_block(): void {
    register_block_type( __DIR__ . '/blocks/membership-bundles-list' );
  }

  /**
   * Determine whether Alpine.js is available on the current page.
   *
   * Mirrors wicket-wp-base-plugin's Assets::enqueue_plugin_scripts() logic:
   * that class enqueues its own Alpine build (handle
   * 'wicket-plugin-alpine-script') only on non-Wicket themes, and assumes the
   * Wicket theme ships its own Alpine instance otherwise. Neither path can be
   * taken for granted here (base-plugin version drift, deactivated plugin, a
   * theme that doesn't actually bundle Alpine), so callers should treat a
   * `false` result as "do not render Alpine-dependent markup."
   *
   * @return bool
   */
  public static function is_alpine_available(): bool {

    if ( wp_script_is( 'wicket-plugin-alpine-script', 'registered' ) || wp_script_is( 'wicket-plugin-alpine-script', 'enqueued' ) ) {
      return true;
    }

    if ( function_exists( 'is_wicket_theme_active' ) && is_wicket_theme_active() ) {
      return true;
    }

    return false;
  }

  /**
   * Count the current member's owned membership bundles that require
   * attention: bundles currently showing a renewal callout on the detail
   * view — early_renewal (inside the renewal window) or grace_period.
   *
   * Delegates the decision to Membership_Bundle::get_renewal_callout() rather
   * than matching statuses here, so the account-menu badge counts exactly the
   * bundles the owner will find a "Renew" prompt on: already-renewed terms,
   * autopay early renewals, bundles with no usable renewal flow, and the
   * global "Disable Renewal Callouts" setting are all excluded the same way.
   * Expired bundles are not counted — they get no renewal callout.
   *
   * Only bundles in a status get_renewal_state() can match (active, delayed,
   * grace_period) are loaded, keeping the per-bundle work to the few that can
   * actually qualify.
   *
   * @return int
   */
  public static function get_bundles_requiring_attention_count(): int {
    if ( ! is_user_logged_in() ) {
      return 0;
    }

    $bundle_ids = get_posts( [
      'post_type'   => Helper::get_membership_bundle_cpt_slug(),
      'post_status' => 'publish',
      'numberposts' => -1,
      'fields'      => 'ids',
      'meta_query'  => [
        'relation' => 'AND',
        [
          'key'   => 'user_id',
          'value' => get_current_user_id(),
        ],
        [
          'key'     => 'membership_status',
          'value'   => [
            Wicket_Memberships::STATUS_ACTIVE,
            Wicket_Memberships::STATUS_DELAYED,
            Wicket_Memberships::STATUS_GRACE,
          ],
          'compare' => 'IN',
        ],
      ],
    ] );

    $count = 0;
    foreach ( $bundle_ids as $bundle_id ) {
      if ( ( new Membership_Bundle( (int) $bundle_id ) )->get_renewal_callout() !== null ) {
        $count++;
      }
    }

    return $count;
  }

  /**
   * Determine the href substring(s) identifying the account-menu link the
   * "requires attention" badge attaches to.
   *
   * Derived from the actual slug of the configured "Manage Membership
   * Bundles" page (Settings > Wicket Memberships > Membership Bundles),
   * since that's the real URL the account nav links to. Returns an empty
   * array when no page is configured yet, or the configured page no longer
   * exists/is unpublished — callers should treat that as "nothing to match"
   * and render no badge.
   *
   * @return string[]
   */
  private static function get_nav_badge_href_fragments(): array {
    $page_id = Helper::get_membership_bundles_manage_page_id();

    if ( $page_id > 0 && get_post_status( $page_id ) === 'publish' ) {
      $slug = get_post_field( 'post_name', $page_id );
      if ( ! empty( $slug ) ) {
        return [ $slug ];
      }
    }

    return [];
  }

  /**
   * Echo a <style> block with a ::after badge on the membership-bundles /
   * membership-groups link inside each account nav menu ID variant, showing
   * the current member's "requires attention" bundle count. Hooked to
   * wp_head. Renders nothing when the count is zero so no empty/zero badge
   * is ever shown.
   *
   * Targets the specific `<a>` (matched by href substring), not the menu
   * container itself, so the badge only appears on the membership-bundles
   * link and not on every item in the account menu.
   *
   * @return void
   */
  public function render_nav_badge_style(): void {
    $count = self::get_bundles_requiring_attention_count();

    if ( $count <= 0 ) {
      return;
    }

    $href_fragments = self::get_nav_badge_href_fragments();

    // Nothing to attach the badge to until a "Manage Membership Bundles" page is configured.
    if ( empty( $href_fragments ) ) {
      return;
    }

    $badge_text = (string) $count;

    $selectors = [];
    foreach ( self::NAV_BADGE_MENU_IDS as $menu_id ) {
      foreach ( $href_fragments as $href_fragment ) {
        $selectors[] = '#' . $menu_id . ' > .menu-item > a[href*=' . esc_attr( $href_fragment ) . ']::after';
      }
    }
    ?>
    <style id="wicket-mship-nav-badge-style">
      <?php echo implode( ",\n      ", $selectors ); // phpcs:ignore WordPress.Security.EscapeOutput -- selectors built from hardcoded IDs and esc_attr()'d page slug above. ?> {
        content: "<?php echo esc_html( $badge_text ); ?>";
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
        min-width: var(--spacing-200, 1rem);
        height: var(--spacing-200, 1rem);
        margin-left: var(--spacing-75, 0.375rem);
        padding: 0 var(--spacing-50, 0.25rem);
        border-radius: var(--border-radius-600, 3rem);
        background-color: var(--state-error, #F26C6C);
        color: var(--text-content-reversed, #FFFFFF);
        font-family: inherit;
        font-size: var(--label-sm-font-size, 11px);
        font-weight: 600;
        line-height: 1;
        vertical-align: middle;
        white-space: nowrap;
      }
    </style>
    <?php
  }
}
