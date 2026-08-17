<?php

namespace Wicket_Memberships;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for whether an individual membership will actually auto-renew.
 * @package Wicket_Memberships
 */
class Autorenew {

  /**
   * Exact title of the AutomateWoo workflow that bypasses the gateway-support check. See
   * atlas/quirks/automatewoo-forced-autorenew-workflow.md.
   *
   * @var string
   */
  const FORCED_WORKFLOW_TITLE = 'Wicket: Force Subscription Auto-Renewal';

  public function __construct() {
    // Bust the cached forced-autorenew-workflow lookup whenever an aw_workflow post changes,
    // so a newly published/unpublished workflow is picked up without waiting for cache expiry.
    add_action( 'save_post_aw_workflow', [ __CLASS__, 'clear_forced_workflow_cache' ] );
    add_action( 'trashed_post', [ __CLASS__, 'clear_forced_workflow_cache' ] );
  }

  /**
   * Bool-only wrapper around `resolve_status()`.
   *
   * @param  array $membership  See `resolve_status()`.
   * @return bool
   */
  public static function is_autorenewing( $membership ) {
    return self::resolve_status( $membership )['result'];
  }

  /**
   * Single source of truth for whether a membership will actually auto-renew, and why not.
   * Computed fresh each call from the linked subscription, not from any stored flag.
   *
   * Point-in-time capability check, not a live-gateway guarantee — doesn't check the
   * membership CPT's own status meta, a disabled-but-registered gateway, or a dead payment
   * token.
   *
   * @param  array $membership  Must contain `membership_subscription_id`.
   * @return array{result: bool, reason: string|null}  `reason` is set only when false.
   */
  public static function resolve_status( $membership ) {
    if ( empty( $membership['membership_subscription_id'] ) ) {
      // No linked subscription (e.g. legacy/imported record): nothing can auto-renew it.
      return [ 'result' => false, 'reason' => __( 'No linked subscription.', 'wicket-memberships' ) ];
    }

    $subscription = wcs_get_subscription( $membership['membership_subscription_id'] );
    if ( empty( $subscription ) ) {
      return [ 'result' => false, 'reason' => __( 'Linked subscription not found.', 'wicket-memberships' ) ];
    }

    // A subscription that isn't active-like will never renew regardless of its manual-renewal
    // flag or payment method, so this check comes first.
    if ( ! $subscription->has_status( 'active' ) ) {
      return [ 'result' => false, 'reason' => __( 'Subscription is not active.', 'wicket-memberships' ) ];
    }

    // A staging/duplicate copy of the site forces manual renewal, to avoid charging real cards
    // from a clone. Not using WCS's own is_manual() here since it also treats every
    // non-WooCommerce-Payments gateway as manual, which would be wrong for our gateways.
    // To allow real auto-payments on a staging copy, hook the
    // `woocommerce_subscriptions_is_duplicate_site` filter (WCS core, class-wcs-staging.php) and
    // return false — this check picks that up automatically since it calls WCS's own method
    // rather than re-deriving the URL comparison. See:
    // https://woocommerce.com/document/subscriptions/renewals/#staging-sites
    if ( class_exists( 'WCS_Staging' ) && \WCS_Staging::is_duplicate_site() ) {
      return [ 'result' => false, 'reason' => __( 'Site is flagged as a staging/duplicate site.', 'wicket-memberships' ) ];
    }

    if ( ! empty( $subscription->get_requires_manual_renewal() ) ) {
      return [ 'result' => false, 'reason' => __( 'Subscription is set to require manual renewal.', 'wicket-memberships' ) ];
    }

    // Free ($0) subscriptions never need a real payment method and are excluded from WC
    // Subscriptions' own gateway checks, so treat them as a special case rather than asking
    // the gateway a question it isn't designed to answer.
    if ( (float) $subscription->get_total() <= 0 ) {
      return [ 'result' => true, 'reason' => null ];
    }

    if ( '' === $subscription->get_payment_method() ) {
      return [ 'result' => false, 'reason' => __( 'No payment method on file.', 'wicket-memberships' ) ];
    }

    // Some gateways don't declare scheduled-payment support even when they can genuinely
    // auto-charge. See atlas/quirks/stripe-gateway-scheduled-payments-gap.md.
    if ( ! $subscription->payment_method_supports( 'gateway_scheduled_payments' ) ) {
      // EXCEPTION: a documented AutomateWoo workaround bypasses this check for this specific
      // Stripe/WCS auto-renew situation. See atlas/quirks/automatewoo-forced-autorenew-workflow.md
      // — do not extend this to cover other gateway gaps.
      if ( self::has_forced_workflow() ) {
        return [ 'result' => true, 'reason' => null ];
      }

      // get_method_title() is the actual gateway name (e.g. "Stripe"); get_title() is the
      // checkout-facing, merchant-customizable label (e.g. "Credit / Debit Card").
      $gateway = function_exists( 'wc_get_payment_gateway_by_order' ) ? wc_get_payment_gateway_by_order( $subscription ) : false;
      $gateway_name = $gateway ? $gateway->get_method_title() : $subscription->get_payment_method();

      return [
        'result' => false,
        /* translators: %s: payment gateway name, e.g. "Stripe" */
        'reason' => sprintf( __( 'Payment gateway (%s) does not support automatic renewal.', 'wicket-memberships' ), $gateway_name ),
      ];
    }

    return [ 'result' => true, 'reason' => null ];
  }

  /**
   * Whether a published AutomateWoo workflow named exactly "Wicket: Force Subscription
   * Auto-Renewal" exists (exact match, not fuzzy). Cached; see atlas/quirks/automatewoo-forced-autorenew-workflow.md.
   * Public: also read directly by `Autorenew_Sync` to compare against its debounce baseline.
   *
   * @return bool
   */
  public static function has_forced_workflow() {
    // get_transient() returns false on a miss; stored values are always the strings 'yes'/'no',
    // so a strict false check unambiguously means "not cached yet".
    $cached = get_transient( self::forced_workflow_transient_key() );
    if ( false !== $cached ) {
      return 'yes' === $cached;
    }

    $result = false;

    if ( post_type_exists( 'aw_workflow' ) ) {
      $workflows = \get_posts( [
        'post_type' => 'aw_workflow',
        'post_status' => 'publish',
        'title' => self::FORCED_WORKFLOW_TITLE,
        'posts_per_page' => 1,
        'fields' => 'ids',
      ] );

      $result = ! empty( $workflows );
    }

    set_transient( self::forced_workflow_transient_key(), $result ? 'yes' : 'no', HOUR_IN_SECONDS );

    return $result;
  }

  /**
   * Clears the cached `has_forced_workflow()` result. Hooked to `save_post_aw_workflow` and
   * `trashed_post` so a newly published/unpublished workflow is picked up immediately.
   *
   * @return void
   */
  public static function clear_forced_workflow_cache() {
    delete_transient( self::forced_workflow_transient_key() );
  }

  /**
   * The transient key used to cache `has_forced_workflow()`'s result. Kept as one method
   * rather than a repeated literal, so the three call sites can't drift out of sync.
   *
   * @return string
   */
  private static function forced_workflow_transient_key() {
    return 'wicket_mship_has_forced_autorenew_workflow';
  }

}
