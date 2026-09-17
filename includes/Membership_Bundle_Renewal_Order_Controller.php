<?php

namespace Wicket_Memberships;

use Wicket_Memberships\Helper;
use Wicket_Memberships\Utilities;

/**
 * Bundle renewal-order creation: claiming, queuing, and per-member repricing.
 *
 * Split out of Membership_Bundle_Cron_Controller (which still owns daily bundle
 * status cron and the post-payment member-provisioning batch). This class owns
 * everything from "a renewal order needs to be created" (admin action, member
 * confirm, or WCS's own native admin action) through the order actually being
 * built and priced. process_bundle_renewal_members() reads this class's output
 * (_wicket_bundle_renewal_resolved_tier_post_id item meta) but does not call
 * into it directly.
 *
 * @package Wicket_Memberships
 */
class Membership_Bundle_Renewal_Order_Controller {

  /**
   * In-request caches scoped to a single create_renewal_order_job() run — reset at
   * the start of that method.
   */
  private static array $tier_cache    = [];
  private static array $product_cache = [];
  private static array $user_id_cache = [];

  private static function reset_caches(): void {
    self::$tier_cache    = [];
    self::$product_cache = [];
    self::$user_id_cache = [];
  }

  private static function get_cached_tier( int $tier_post_id ): Membership_Tier {
    if ( ! isset( self::$tier_cache[ $tier_post_id ] ) ) {
      self::$tier_cache[ $tier_post_id ] = new Membership_Tier( $tier_post_id );
    }
    return self::$tier_cache[ $tier_post_id ];
  }

  private static function get_cached_product( int $product_id ): \WC_Product|false {
    if ( ! \array_key_exists( $product_id, self::$product_cache ) ) {
      self::$product_cache[ $product_id ] = wc_get_product( $product_id ) ?: false;
    }
    return self::$product_cache[ $product_id ];
  }

  /**
   * user_id meta for a membership post, cached per membership_post_id — avoids
   * apply_bundle_renewal_line_item_price_filter() and refresh_bundle_renewal_
   * line_item_meta() each re-reading the same meta for the same member.
   */
  private static function get_cached_membership_user_id( int $membership_post_id ): int {
    if ( ! isset( self::$user_id_cache[ $membership_post_id ] ) ) {
      self::$user_id_cache[ $membership_post_id ] = (int) get_post_meta( $membership_post_id, 'user_id', true );
    }
    return self::$user_id_cache[ $membership_post_id ];
  }

  public function __construct() {
    // Background renewal-order creation — dispatched by the create/confirm REST endpoints.
    add_action( 'wicket_bundle_create_renewal_order', [ __NAMESPACE__ . '\\Membership_Bundle_Renewal_Order_Controller', 'create_renewal_order_job' ], 10, 2 );

    // Intercept WCS's own "Create Pending Renewal Order" admin order action for bundle
    // subscriptions only. Priority 5, before WCS's own handler at priority 10 (class-wcs-
    // admin-meta-boxes.php), so this runs first and can remove_action() it away. WCS's own
    // handler calls wcs_create_renewal_order() synchronously on the admin request; this
    // redirects to the same queued job those REST endpoints already use.
    add_action( 'woocommerce_order_action_wcs_create_pending_renewal', [ __NAMESPACE__ . '\\Membership_Bundle_Renewal_Order_Controller', 'intercept_wcs_create_pending_renewal_for_bundle' ], 5, 1 );

    add_action( 'admin_notices', [ __NAMESPACE__ . '\\Membership_Bundle_Renewal_Order_Controller', 'render_queued_bundle_renewal_order_notice' ] );

    // Warns an admin away from WCS's native subscription edit screen for a large bundle,
    // before they submit its own order-actions form (see maybe_render_large_bundle_subscription_notice()).
    add_action( 'add_meta_boxes', [ __NAMESPACE__ . '\\Membership_Bundle_Renewal_Order_Controller', 'maybe_render_large_bundle_subscription_notice' ], 40 );

    // Client-specific extension point: per-member price/fee adjustment on the actual
    // renewal order WCS bills the customer on.
    add_filter( 'wcs_renewal_order_created', [ __NAMESPACE__ . '\\Membership_Bundle_Renewal_Order_Controller', 'apply_bundle_renewal_line_item_price_filter' ], 10, 2 );

    // Refreshes stale line-item identity meta on renewal. wcs_renewal_order_items is
    // renewal-specific (unlike wcs_new_order_items), so this never fires for a resubscribe.
    add_filter( 'wcs_renewal_order_items', [ __NAMESPACE__ . '\\Membership_Bundle_Renewal_Order_Controller', 'refresh_bundle_renewal_line_item_meta' ], 10, 3 );
  }

  // ---------------------------------------------------------------------------
  // Claim + queue
  // ---------------------------------------------------------------------------

  /**
   * Claim a bundle's renewal-order-creation slot, or report it's already
   * claimed/complete. Must happen at enqueue time — once creation is deferred, no
   * order exists yet for a second request to detect via an order-existence check.
   *
   * Uses add_post_meta(unique: true) rather than update_post_meta's $prev_value: the
   * latter skips its compare-and-swap when $prev_value is empty, which is exactly a
   * bundle's first claim.
   *
   * @return true|array{status: int, order_id: ?int}
   */
  public static function claim_renewal_order_creation( int $bundle_post_id ) {
    $raw     = get_post_meta( $bundle_post_id, 'membership_renewal_order_creation', true );
    $current = $raw ? ( json_decode( $raw, true ) ?: [] ) : [];

    if ( ! empty( $current ) ) {
      if ( isset( $current['order_id'] ) ) {
        return [ 'status' => 409, 'order_id' => (int) $current['order_id'] ];
      }
      if ( ! isset( $current['failed_at'] ) ) {
        return [ 'status' => 409, 'order_id' => null ];
      }
      // A failed attempt doesn't block a retry — clear it first for a clean claim.
      delete_post_meta( $bundle_post_id, 'membership_renewal_order_creation' );
    }

    $claim = wp_json_encode( [
      'queued_at' => current_time( 'c' ),
      'phase'     => 'creating_order',
    ] );

    $claimed = add_post_meta( $bundle_post_id, 'membership_renewal_order_creation', $claim, true );

    if ( $claimed === false ) {
      // Lost the race — re-read to report the winner's state.
      $raw     = get_post_meta( $bundle_post_id, 'membership_renewal_order_creation', true );
      $current = $raw ? ( json_decode( $raw, true ) ?: [] ) : [];

      return [ 'status' => 409, 'order_id' => isset( $current['order_id'] ) ? (int) $current['order_id'] : null ];
    }

    return true;
  }

  /**
   * Clear a completed renewal-order claim so the bundle can be claimed again.
   *
   * claim_renewal_order_creation() blocks indefinitely once a claim completes
   * (order_id set) — correct for confirm_bundle_renewal(), where a second member
   * confirm in the same cycle should stay blocked, but wrong for the admin's manual
   * create_bundle_renewal_order action, which may legitimately be triggered again on
   * the same bundle post. Only call this from a caller that intentionally allows a
   * repeat manual renewal-order creation; it does nothing to an in-flight claim (no
   * order_id yet) so a genuine concurrent request is still blocked.
   */
  public static function clear_completed_renewal_order_claim( int $bundle_post_id ): void {
    $raw     = get_post_meta( $bundle_post_id, 'membership_renewal_order_creation', true );
    $current = $raw ? ( json_decode( $raw, true ) ?: [] ) : [];

    if ( isset( $current['order_id'] ) ) {
      delete_post_meta( $bundle_post_id, 'membership_renewal_order_creation' );
    }
  }

  /**
   * Redirects WCS's native "Create Pending Renewal Order" admin action to our own
   * queued job for bundle subscriptions, instead of letting WCS call
   * wcs_create_renewal_order() synchronously on the admin request. No-ops for a
   * non-bundle subscription.
   */
  public static function intercept_wcs_create_pending_renewal_for_bundle( \WC_Subscription $subscription ): void {
    $bundle_post_id = (int) get_post_meta( $subscription->get_id(), 'membership_bundle_id', true );
    if ( $bundle_post_id <= 0 || get_post_type( $bundle_post_id ) !== Helper::get_membership_bundle_cpt_slug() ) {
      return;
    }

    if ( class_exists( 'WCS_Admin_Meta_Boxes' ) ) {
      // No leading backslash — WCS registered this callback as ['WCS_Admin_Meta_Boxes', ...]
      // (unqualified, global namespace). class_exists() normalizes a leading backslash but
      // WordPress's callback identity check (_wp_filter_build_unique_id()) does not, so a
      // leading backslash here silently fails to match and remove_action() removes nothing.
      remove_action(
        'woocommerce_order_action_wcs_create_pending_renewal',
        [ 'WCS_Admin_Meta_Boxes', 'create_pending_renewal_action_request' ],
        10
      );
    }

    self::clear_completed_renewal_order_claim( $bundle_post_id );
    $claim = self::claim_renewal_order_creation( $bundle_post_id );

    if ( $claim !== true ) {
      set_transient( 'wicket_mship_bundle_renewal_order_notice_' . get_current_user_id(), [
        'type'    => 'warning',
        'message' => ! empty( $claim['order_id'] )
          ? __( 'A renewal order already exists for this membership bundle.', 'wicket-memberships' )
          : __( 'Renewal order creation is already in progress for this membership bundle.', 'wicket-memberships' ),
      ], 60 );
      return;
    }

    as_schedule_single_action(
      time(),
      'wicket_bundle_create_renewal_order',
      [ 'bundle_post_id' => $bundle_post_id, 'subscription_id' => $subscription->get_id() ],
      'wicket-memberships',
      false
    );

    set_transient( 'wicket_mship_bundle_renewal_order_notice_' . get_current_user_id(), [
      'type'    => 'success',
      'message' => __( 'This subscription belongs to a membership bundle. Renewal order creation has been queued in the background instead of running immediately.', 'wicket-memberships' ),
    ], 60 );
  }

  /**
   * Registers a meta box (not a raw admin_notices print, so it can be positioned via
   * add_meta_box()'s own context/priority — directly under WCS's own "Order actions"
   * box) warning an admin viewing a bundle subscription's native WooCommerce edit screen
   * that a large member count risks PHP's own max_input_vars limit on that screen's own
   * "Save"/order-actions form submission. By the time our own
   * intercept_wcs_create_pending_renewal_for_bundle() runs, PHP-FPM has already parsed
   * (and silently truncated) the POST body if it exceeded the limit — that happens at
   * PHP request-startup, ahead of any WordPress or plugin code, so it cannot be detected
   * or prevented from inside a hook callback, only warned about beforehand.
   *
   * Points the admin at this bundle's own edit page instead, whose "Create Renewal Order"
   * action only ever sends a single field (bundle_post_id) regardless of member count.
   */
  public static function maybe_render_large_bundle_subscription_notice(): void {
    if ( ! function_exists( 'wcs_get_page_screen_id' ) || ! function_exists( 'wcs_get_subscription' ) ) {
      return;
    }

    $screen = get_current_screen();
    if ( ! $screen || $screen->id !== wcs_get_page_screen_id( 'shop_subscription' ) ) {
      return;
    }

    $subscription_id = (int) ( $_GET['post'] ?? $_GET['id'] ?? 0 );
    if ( ! $subscription_id ) {
      return;
    }

    $bundle_post_id = (int) get_post_meta( $subscription_id, 'membership_bundle_id', true );
    if ( $bundle_post_id <= 0 || get_post_type( $bundle_post_id ) !== Helper::get_membership_bundle_cpt_slug() ) {
      return;
    }

    $subscription = wcs_get_subscription( $subscription_id );
    if ( ! $subscription ) {
      return;
    }

    // A conservative threshold well under max_input_vars' own default (4000) — each line
    // item's own meta box renders roughly a dozen named fields, so even a few hundred
    // members can approach a lowered or already-consumed limit.
    if ( \count( $subscription->get_items() ) < 250 ) {
      return;
    }

    // Registered at 'side'/'high' so it renders directly under WCS's own "Order actions"
    // box (same context, and 'high' places it right after core's own boxes there) rather
    // than at the top of the page like a generic admin_notices print would.
    add_meta_box(
      'wicket_mship_bundle_large_subscription_notice',
      __( 'Membership Bundle', 'wicket-memberships' ),
      [ __CLASS__, 'render_large_bundle_subscription_notice_box' ],
      $screen->id,
      'side',
      'high',
      [ 'bundle_post_id' => $bundle_post_id ]
    );
  }

  /**
   * Render callback for the meta box maybe_render_large_bundle_subscription_notice()
   * registers. Kept separate so the box can be positioned via add_meta_box()'s own
   * context/priority instead of printing raw HTML from a generic action hook.
   */
  public static function render_large_bundle_subscription_notice_box( $post_or_order, array $metabox ): void {
    $bundle_post_id = (int) ( $metabox['args']['bundle_post_id'] ?? 0 );
    if ( ! $bundle_post_id ) {
      return;
    }

    $bundle   = new Membership_Bundle( $bundle_post_id );
    $edit_url = admin_url( 'admin.php?page=' . Membership_CPT_Hooks::EDIT_BUNDLE_MEMBER_PAGE_SLUG . '&id=' . $bundle->get_bundle_group_uuid() );

    printf(
      '<p>%s</p>',
      wp_kses_post( sprintf(
        /* translators: %s: link to the membership bundle's own edit page */
        __( 'This subscription belongs to a large membership bundle. Saving this screen or using its "Create pending renewal order" action may fail or silently drop data due to PHP\'s max_input_vars limit. Use <a href="%s">this bundle\'s own edit page</a> instead — its Create Renewal Order action is not affected by member count.', 'wicket-memberships' ),
        esc_url( $edit_url )
      ) )
    );
  }

  /**
   * Render the one-time admin notice set by intercept_wcs_create_pending_renewal_for_bundle().
   * A transient (not a query-string redirect) survives WCS's own admin-notice/redirect
   * handling for this same order-actions save, and is cleared on display so it shows once.
   */
  public static function render_queued_bundle_renewal_order_notice(): void {
    $key    = 'wicket_mship_bundle_renewal_order_notice_' . get_current_user_id();
    $notice = get_transient( $key );

    if ( ! $notice ) {
      return;
    }

    delete_transient( $key );

    printf(
      '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
      esc_attr( $notice['type'] === 'success' ? 'success' : 'warning' ),
      esc_html( $notice['message'] )
    );
  }

  /**
   * Create a bundle's renewal order in the background — dispatched by the REST
   * endpoints instead of calling wcs_create_renewal_order() inline in the request.
   * Writes the result (order_id or failure) back onto the claim's own meta; that's
   * the terminal state the UI polls for and a later claim attempt checks against.
   */
  public static function create_renewal_order_job( int $bundle_post_id, int $subscription_id ): void {
    self::reset_caches();

    $subscription = function_exists( 'wcs_get_subscription' ) ? wcs_get_subscription( $subscription_id ) : false;

    if ( ! $subscription ) {
      Utilities::wc_log_mship_error( [ 'create_renewal_order_job: subscription not found', [
        'bundle_post_id'   => $bundle_post_id,
        'subscription_id'  => $subscription_id,
      ] ] );
      self::mark_renewal_order_creation_failed( $bundle_post_id, 'subscription_not_found' );
      return;
    }

    // Prime post-meta + user caches in one query each for every member on the
    // subscription — wcs_create_renewal_order() copies these same line items to the
    // renewal order, and the hooks below read tier/product/user_id per member. For a
    // bundle with hundreds of members this collapses thousands of queries into two.
    $membership_post_ids = [];
    foreach ( $subscription->get_items() as $item ) {
      $membership_post_id = (int) $item->get_meta( '_membership_post_id' );
      if ( $membership_post_id > 0 ) {
        $membership_post_ids[] = $membership_post_id;
      }
    }
    if ( ! empty( $membership_post_ids ) ) {
      update_meta_cache( 'post', $membership_post_ids );

      $user_ids = [];
      foreach ( $membership_post_ids as $membership_post_id ) {
        $user_id = self::get_cached_membership_user_id( $membership_post_id );
        if ( $user_id > 0 ) {
          $user_ids[] = $user_id;
        }
      }
      if ( ! empty( $user_ids ) && function_exists( 'cache_users' ) ) {
        cache_users( array_unique( $user_ids ) );
      }
    }

    // WooCommerce Subscriptions' own pay-for-order page only accepts payment for a
    // subscription whose status is 'on-hold' or 'pending' (WCS_Cart_Renewal::maybe_setup_cart()) —
    // an 'active' subscription is refused outright regardless of the order's own status.
    // Mirrors the existing individual-membership subscription-renewal path in
    // Membership_Controller.php, which puts the subscription on hold before creating its
    // renewal order for the same reason.
    $subscription->update_status( 'on-hold', __( 'Membership plugin set subscription on-hold generating a pending bundle renewal order.', 'wicket-memberships' ) );

    // Action Scheduler never interrupts a running job — the only real ceiling is PHP's
    // own max_execution_time. wc_set_time_limit() gives this job headroom for a large
    // bundle's per-member repricing loop below; a host that blocks set_time_limit()
    // leaves this as a silent no-op.
    if ( function_exists( 'wc_set_time_limit' ) ) {
      wc_set_time_limit( 300 );
    }

    $renewal_order = wcs_create_renewal_order( $subscription );

    if ( is_wp_error( $renewal_order ) ) {
      Utilities::wc_log_mship_error( [ 'create_renewal_order_job: wcs_create_renewal_order failed', [
        'bundle_post_id'  => $bundle_post_id,
        'subscription_id' => $subscription_id,
        'error'           => $renewal_order->get_error_message(),
      ] ] );
      self::mark_renewal_order_creation_failed( $bundle_post_id, $renewal_order->get_error_code() );
      return;
    }

    $raw   = get_post_meta( $bundle_post_id, 'membership_renewal_order_creation', true );
    $claim = $raw ? ( json_decode( $raw, true ) ?: [] ) : [];

    update_post_meta( $bundle_post_id, 'membership_renewal_order_creation', wp_json_encode( [
      'queued_at'    => $claim['queued_at'] ?? current_time( 'c' ),
      'completed_at' => current_time( 'c' ),
      'order_id'     => $renewal_order->get_id(),
    ] ) );
  }

  private static function mark_renewal_order_creation_failed( int $bundle_post_id, string $error_code ): void {
    update_post_meta( $bundle_post_id, 'membership_renewal_order_creation', wp_json_encode( [
      'failed_at'  => current_time( 'c' ),
      'error_code' => $error_code,
    ] ) );
  }

  // ---------------------------------------------------------------------------
  // Per-member renewal-order price/fee extension point
  // ---------------------------------------------------------------------------

  /**
   * Fire wicket_mship_bundle_renewal_line_item_price once per member/line-item as a
   * bundle's renewal order is built, then recalculate totals once for the whole order.
   *
   * Hooked to WCS's native `wcs_renewal_order_created` filter (always return
   * $renewal_order — WCS requires a WC_Order back).
   *
   * The filter's return value is discarded; a callback communicates any change (price
   * adjustment, added fee/product line, whole-order effect) by mutating
   * $item/$renewal_order directly via the normal WC API.
   *
   * @param \WC_Order        $renewal_order The freshly created renewal order.
   * @param \WC_Subscription $subscription  The subscription the renewal is related to.
   * @return \WC_Order The same renewal order.
   */
  public static function apply_bundle_renewal_line_item_price_filter( $renewal_order, $subscription ) {
    if ( ! $renewal_order instanceof \WC_Order ) {
      return $renewal_order;
    }

    // Scope to bundle subscriptions only — a subscription is linked to a bundle when some
    // bundle post's membership_subscription_id meta points back to it.
    $bundle_posts = get_posts( [
      'post_type'      => Helper::get_membership_bundle_cpt_slug(),
      'post_status'    => 'any',
      'posts_per_page' => 1,
      'fields'         => 'ids',
      'meta_query'     => [
        [ 'key' => 'membership_subscription_id', 'value' => $subscription->get_id() ],
      ],
    ] );

    if ( empty( $bundle_posts ) ) {
      return $renewal_order;
    }

    $old_bundle_post_id = (int) $bundle_posts[0];
    $reprice_failures   = [];

    foreach ( $renewal_order->get_items() as $item_id => $item ) {
      $membership_post_id = (int) wc_get_order_item_meta( $item_id, '_membership_post_id', true );
      if ( ! $membership_post_id ) {
        continue;
      }
      $user_id = self::get_cached_membership_user_id( $membership_post_id );

      // Backstops refresh_bundle_renewal_line_item_meta() regardless of WCS's meta copy.
      $user = $user_id ? get_user_by( 'id', $user_id ) : false;
      if ( $user && '' === (string) $item->get_meta( '_member_name' ) ) {
        $item->update_meta_data( '_member_name', $user->display_name );
        $item->save();
      }

      // Reprice to the term ahead before Milestone 4's fee/discount filter runs.
      $reprice_result = self::reprice_bundle_renewal_line_item( $item, $item_id, $membership_post_id, $user_id, $old_bundle_post_id );
      if ( $reprice_result !== true ) {
        $reprice_failures[ $membership_post_id ] = $reprice_result;
      }

      $original_product_price = (string) $item->get_total();

      try {
        // The callback mutates $item and/or $renewal_order directly — e.g.
        // $item->set_total()/set_subtotal() for a price adjustment,
        // $renewal_order->add_fee()/add_product() for a separate line. The return
        // value is intentionally discarded.
        apply_filters(
          'wicket_mship_bundle_renewal_line_item_price',
          null,
          $item,
          $item_id,
          $membership_post_id,
          $user_id,
          $renewal_order
        );

        // Only a direct set_total()/set_subtotal() override is worth recording here —
        // not a discount/coupon (WC already tracks those natively) and not a product
        // swap (already explained by the tier/product decision meta above). Compare
        // against the product's own price before this filter ran, since WC applies
        // discounts later via its own line-item fields, not by mutating this total.
        // The item's own get_total() is the current/final price — no need to duplicate it.
        if ( (string) $item->get_total() !== $original_product_price ) {
          $item->update_meta_data( '_wicket_bundle_renewal_original_product_price', $original_product_price );
          // Mirrors _wicket_bundle_renewal_decision_source (tier/product): names what
          // changed the price, distinct from what changed the tier/product. Only one
          // mechanism can force a raw price override, so this is always 'filter_override'.
          $item->update_meta_data( '_wicket_bundle_renewal_price_decision_source', 'filter_override' );
        }

        $item->save();
      } catch ( \Throwable $e ) {
        // A single member's callback failing (e.g. an external lookup throwing) must not
        // abort processing for the rest of the order's members, and must not skip the
        // calculate_totals() call below.
        Utilities::wc_log_mship_error( [ 'wicket_mship_bundle_renewal_line_item_price filter failed', [
          'item_id'            => $item_id,
          'membership_post_id' => $membership_post_id,
          'error'              => $e->getMessage(),
        ] ] );
      }
    }

    $renewal_order->calculate_totals();

    if ( ! empty( $reprice_failures ) ) {
      $note = 'Bundle renewal repricing failed for member(s): ' . implode( ', ', array_map(
        static fn( $post_id, $code ) => "#{$post_id} ({$code})",
        array_keys( $reprice_failures ),
        $reprice_failures
      ) );
      $renewal_order->update_status( 'on-hold', $note );
      Utilities::wc_log_mship_error( [ 'apply_bundle_renewal_line_item_price_filter: repricing failures, order held', [
        'renewal_order_id' => $renewal_order->get_id(),
        'failures'          => $reprice_failures,
      ] ] );
    }

    return $renewal_order;
  }

  /**
   * Resolve the tier/product a renewing member's sequential_logic tier succeeds to.
   * Non-sequential_logic tiers pass through unchanged.
   *
   * @return array{tier_post_id: int, product_id: int|null, variation_id: int|null, decision_source: 'unchanged'|'sequential_logic'}|\WP_Error
   */
  private static function resolve_sequential_logic_succession( int $tier_post_id, ?int $product_id, int $old_membership_post_id ): array|\WP_Error {
    $old_tier = self::get_cached_tier( $tier_post_id );
    if ( $old_tier->get_tier_renewal_type() !== 'sequential_logic' ) {
      return [ 'tier_post_id' => $tier_post_id, 'product_id' => $product_id, 'variation_id' => null, 'decision_source' => 'unchanged' ];
    }

    $next_tier_id = $old_tier->get_next_tier_id();
    if ( $next_tier_id === false ) {
      Utilities::wc_log_mship_error( [ 'resolve_sequential_logic_succession: sequential_logic tier has no next_tier_id configured', [
        'old_membership_post_id' => $old_membership_post_id,
        'tier_post_id'           => $tier_post_id,
      ] ] );
      return new \WP_Error( 'no_next_tier', 'sequential_logic tier has no next_tier_id configured.' );
    }

    $next_tier = self::get_cached_tier( $next_tier_id );
    $next_tier_products = $next_tier->get_products_data();
    if ( empty( $next_tier_products ) ) {
      Utilities::wc_log_mship_error( [ 'resolve_sequential_logic_succession: next tier has no products configured', [
        'old_membership_post_id' => $old_membership_post_id,
        'tier_post_id'           => $tier_post_id,
        'next_tier_id'           => $next_tier_id,
      ] ] );
      return new \WP_Error( 'no_next_tier_product', 'sequential_logic next tier has no products configured.' );
    }

    // First product, preferring the variation over the parent product — matches
    // Import_Controller::create_bundle_member()'s ambiguous_product handling. A next
    // tier configured with more than one product is not an expected configuration;
    // this deterministic pick exists for correctness/safety, mirroring the import
    // precedent, not because multi-product next tiers are a real scenario to support.
    $variation_id = ! empty( $next_tier_products[0]['variation_id'] ) ? (int) $next_tier_products[0]['variation_id'] : null;

    return [
      'tier_post_id'     => $next_tier_id,
      'product_id'       => (int) $next_tier_products[0]['product_id'],
      'variation_id'     => $variation_id,
      'decision_source'  => 'sequential_logic',
    ];
  }

  /**
   * Resolve and apply the term-ahead tier/product/price to one member's renewal
   * line item, before the price/fee filter runs. Fires
   * wicket_mship_bundle_renewal_charge_tier_product for a client override, validated
   * against the target tier and failed closed to the resolved default on mismatch.
   *
   * @param \WC_Order_Item_Product $item
   * @return true|string true on success, else an error code for the caller to collect.
   */
  private static function reprice_bundle_renewal_line_item( $item, int $item_id, int $membership_post_id, int $user_id, int $old_bundle_post_id ) {
    $tier_post_id = (int) get_post_meta( $membership_post_id, 'membership_tier_post_id', true );
    if ( ! $tier_post_id ) {
      Utilities::wc_log_mship_error( [ 'reprice_bundle_renewal_line_item: missing tier_post_id', [
        'item_id'            => $item_id,
        'membership_post_id' => $membership_post_id,
      ] ] );
      return 'missing_tier_post_id';
    }

    $stored_product_id = (int) get_post_meta( $membership_post_id, 'membership_product_id', true ) ?: null;

    // membership_product_id may hold a variation ID — disambiguate before use.
    $current_tier_variation_ids = array_map( 'intval', self::get_cached_tier( $tier_post_id )->get_product_variation_ids() );
    $product_id = ( $stored_product_id !== null && \in_array( $stored_product_id, $current_tier_variation_ids, true ) )
      ? null
      : $stored_product_id;

    $previous_tier_post_id = $tier_post_id;
    $previous_product_id   = $stored_product_id;

    $resolved = self::resolve_sequential_logic_succession( $tier_post_id, $product_id, $membership_post_id );
    if ( is_wp_error( $resolved ) ) {
      return $resolved->get_error_code();
    }

    // Default (null) means "no override — the resolution above stands."
    $override = apply_filters(
      'wicket_mship_bundle_renewal_charge_tier_product',
      null,
      $membership_post_id,
      $user_id,
      $old_bundle_post_id,
      $resolved
    );

    if ( is_array( $override ) ) {
      $validated = self::validate_charge_tier_product_override( $override );
      if ( $validated === null ) {
        Utilities::wc_log_mship_error( [ 'reprice_bundle_renewal_line_item: invalid wicket_mship_bundle_renewal_charge_tier_product override, falling back to core default', [
          'membership_post_id' => $membership_post_id,
          'override'           => $override,
        ] ] );
      } else {
        $resolved = $validated + [ 'decision_source' => 'filter_override' ];
      }
    }

    $product = self::get_cached_product( (int) ( $resolved['variation_id'] ?? $resolved['product_id'] ) );
    if ( ! $product ) {
      Utilities::wc_log_mship_error( [ 'reprice_bundle_renewal_line_item: resolved product could not be loaded', [
        'item_id'            => $item_id,
        'membership_post_id' => $membership_post_id,
        'resolved'           => $resolved,
      ] ] );
      return 'product_not_found';
    }

    $item->set_product( $product );

    $price = (float) $product->get_price();
    $item->set_subtotal( (string) $price );
    $item->set_total( (string) $price );

    // Record which tier this charge belongs to, so process_bundle_renewal_members()
    // reads it back instead of re-deriving it post-payment — product/variation are
    // already on the item natively via set_product() above. Always written on success
    // (even when unchanged): its presence is process_bundle_renewal_members()'s only
    // signal that repricing actually completed, vs. failed outright (e.g. a
    // sequential_logic next tier with no products) — those two cases must not be
    // confused with each other, or a failed member could wrongly fall back to renewing
    // at their old tier/product instead of being skipped and logged as an error.
    $item->update_meta_data( '_wicket_bundle_renewal_resolved_tier_post_id', $resolved['tier_post_id'] );

    // The rest of the decision record is only worth writing when something actually
    // changed — an "unchanged" renewal has nothing more to say than the line above.
    if ( $resolved['decision_source'] !== 'unchanged' ) {
      $item->update_meta_data( '_wicket_bundle_renewal_decision_source', $resolved['decision_source'] );
      $item->update_meta_data( '_wicket_bundle_renewal_previous_tier_post_id', $previous_tier_post_id );
      $item->update_meta_data( '_wicket_bundle_renewal_previous_product_id', $previous_product_id ?? 0 );

      // ISO 8601 with offset (matches process_bundle_renewal_members()'s completed_at
      // stamp) — render with formatDateWithTooltip() wherever this surfaces in the
      // admin UI, per this plugin's date-display convention.
      $item->update_meta_data( '_wicket_bundle_renewal_decided_at', current_time( 'c' ) );
    }

    return true;
  }

  /**
   * Validate a charge_tier_product override against its claimed tier. Fails closed
   * (null) rather than trust a product the tier doesn't actually offer.
   *
   * @return array{tier_post_id: int, product_id: int, variation_id: int|null}|null
   */
  private static function validate_charge_tier_product_override( array $override ): ?array {
    $tier_post_id = (int) ( $override['tier_post_id'] ?? 0 );
    $product_id   = (int) ( $override['product_id'] ?? 0 );
    $variation_id = isset( $override['variation_id'] ) ? (int) $override['variation_id'] : null;

    if ( ! $tier_post_id || ! $product_id ) {
      return null;
    }

    $tier = self::get_cached_tier( $tier_post_id );
    $tier_product_ids   = array_map( 'intval', $tier->get_product_ids() );
    $tier_variation_ids = array_map( 'intval', $tier->get_product_variation_ids() );

    // Normalise a variation ID returned in the product_id slot.
    if ( ! \in_array( $product_id, $tier_product_ids, true ) && \in_array( $product_id, $tier_variation_ids, true ) ) {
      $variation_id = $product_id;
      $product_id   = (int) $tier->get_product_ids()[0] ?? 0;
    }

    if ( ! \in_array( $product_id, $tier_product_ids, true ) ) {
      return null;
    }
    if ( $variation_id !== null && ! \in_array( $variation_id, $tier_variation_ids, true ) ) {
      return null;
    }

    return [ 'tier_post_id' => $tier_post_id, 'product_id' => $product_id, 'variation_id' => $variation_id ];
  }

  // ---------------------------------------------------------------------------
  // Per-member line-item meta refresh on renewal
  // ---------------------------------------------------------------------------

  /**
   * Refresh wicket_mship_bundle_line_item_extra_meta and _member_name on each bundle
   * member's subscription line item before WCS copies it to the renewal order.
   *
   * $items are the subscription's own items — wcs_copy_order_item() copies all their
   * meta (except _reduced_stock) onto the renewal order right after this filter runs,
   * so writing here reaches both objects. Unlike a price/tier write, this carries none
   * of Milestone 9's pre-payment risk: identity meta is correct whether or not the
   * order is ever paid.
   *
   * @param \WC_Order_Item[] $items        Subscription's own line items.
   * @param \WC_Order        $new_order    The renewal order being built.
   * @param \WC_Subscription $subscription The subscription the renewal is related to.
   * @return \WC_Order_Item[] The same $items array, mutated in place.
   */
  public static function refresh_bundle_renewal_line_item_meta( $items, $new_order, $subscription ) {
    if ( empty( $_ENV['WICKET_MSHIP_ENABLE_BUNDLES'] ) || ! is_array( $items ) ) {
      return $items;
    }

    // Scope to bundle subscriptions only — a subscription is linked to a bundle when some
    // bundle post's membership_subscription_id meta points back to it.
    $bundle_posts = get_posts( [
      'post_type'      => Helper::get_membership_bundle_cpt_slug(),
      'post_status'    => 'any',
      'posts_per_page' => 1,
      'fields'         => 'ids',
      'meta_query'     => [
        [ 'key' => 'membership_subscription_id', 'value' => $subscription->get_id() ],
      ],
    ] );

    if ( empty( $bundle_posts ) ) {
      return $items;
    }

    foreach ( $items as $item_id => $item ) {
      $membership_post_id = (int) $item->get_meta( '_membership_post_id' );
      if ( ! $membership_post_id ) {
        continue;
      }
      $user_id = self::get_cached_membership_user_id( $membership_post_id );
      $user    = $user_id ? get_user_by( 'id', $user_id ) : false;

      try {
        if ( $user ) {
          $item->update_meta_data( '_member_name', $user->display_name );
        }

        $product_id = (int) $item->get_product_id();

        // Same filter Milestone 1 fires at add-time; trailing true marks this a refresh.
        $extra_meta = apply_filters(
          'wicket_mship_bundle_line_item_extra_meta',
          [],
          $item_id,
          $user,
          $membership_post_id,
          $product_id,
          true
        );
        foreach ( $extra_meta as $meta_key => $meta_value ) {
          $item->update_meta_data( $meta_key, $meta_value );
        }

        $item->save();
      } catch ( \Throwable $e ) {
        Utilities::wc_log_mship_error( [ 'wicket_mship_bundle_line_item_extra_meta refresh failed', [
          'item_id'            => $item_id,
          'membership_post_id' => $membership_post_id,
          'error'              => $e->getMessage(),
        ] ] );
      }
    }

    return $items;
  }
}
