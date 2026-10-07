<?php

namespace Wicket_Memberships;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for whether an individual membership will actually auto-renew.
 * @package Wicket_Memberships
 */
class Autorenew {

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
   * Runs each gate in order and stops at the first one that fails. Checks the gateway's
   * `tokenization` support (can it charge a stored payment method with no customer present), not
   * `gateway_scheduled_payments` (which only means the gateway self-schedules its own renewals
   * instead of relying on WCS's dispatch hook — both are compatible with automatic charging).
   *
   * @param  array $membership  Must contain `membership_subscription_id`.
   * @return array{result: bool, reason: string|null}  `reason` is set only when false.
   */
  public static function resolve_status( $membership ) {
    $subscription = self::check_linked_subscription( $membership );
    if ( is_array( $subscription ) ) {
      return $subscription;
    }

    // One-line-per-check, intentionally: mirrors the real renewal process flow, gate by gate and increases readability.
    if ( $result = self::check_subscription_active( $subscription ) ) return $result;
    if ( $result = self::check_not_staging_site( $subscription ) ) return $result;
    // Must run before check_not_manual_renewal(): WCS's own process_renewal() auto-completes a
    // $0 renewal order unconditionally (payment_complete()), never consulting is_manual() at
    // completion time — a free subscription still renews even when flagged manual.
    if ( $result = self::check_free_subscription( $subscription ) ) return $result;
    if ( $result = self::check_not_manual_renewal( $subscription ) ) return $result;
    if ( $result = self::check_payment_method_on_file( $subscription ) ) return $result;
    if ( $result = self::check_gateway_can_charge_unattended( $subscription ) ) return $result;

    return [ 'result' => true, 'reason' => null ];
  }

  /**
   * Resolves `$membership` to its linked `WC_Subscription`, or a final result if none exists.
   *
   * @param  array $membership  Must contain `membership_subscription_id`.
   * @return \WC_Subscription|array{result: bool, reason: string|null}  The subscription on
   *         success, or a final `resolve_status()` result when no usable subscription exists.
   */
  private static function check_linked_subscription( $membership ) {
    if ( empty( $membership['membership_subscription_id'] ) ) {
      // No linked subscription (e.g. legacy/imported record): nothing can auto-renew it.
      return [ 'result' => false, 'reason' => __( 'No linked subscription.', 'wicket-memberships' ) ];
    }

    $subscription = wcs_get_subscription( $membership['membership_subscription_id'] );
    if ( empty( $subscription ) ) {
      return [ 'result' => false, 'reason' => __( 'Linked subscription not found.', 'wicket-memberships' ) ];
    }

    return $subscription;
  }

  /**
   * WCS puts a subscription `on-hold` for every renewal attempt, including automatic retries
   * (`WC_Subscriptions_Manager::process_renewal()`, `WCS_Retry_Manager`) — a live `payment_retry`
   * date means it's still trying, not a hard failure.
   *
   * @param  \WC_Subscription $subscription  The subscription to check.
   * @return array{result: bool, reason: string|null}|null  A final result if not active (and not
   *         mid-automatic-retry), null to continue to the next check.
   */
  private static function check_subscription_active( $subscription ) {
    if ( $subscription->has_status( 'active' ) ) {
      return null;
    }

    if ( $subscription->has_status( 'on-hold' ) && self::has_pending_automatic_retry( $subscription ) ) {
      return null;
    }

    return [ 'result' => false, 'reason' => __( 'Subscription is not active.', 'wicket-memberships' ) ];
  }

  /**
   * `payment_retry` only exists while WCS has a retry actually pending (`WCS_Retry_Manager`) — an
   * exact signal, not a time-based guess.
   *
   * @param  \WC_Subscription $subscription  The subscription to check.
   * @return bool  True if a real, future automatic retry is scheduled.
   */
  private static function has_pending_automatic_retry( $subscription ) {
    $retry_timestamp = $subscription->get_time( 'payment_retry', 'gmt' );

    return ! empty( $retry_timestamp ) && $retry_timestamp > time();
  }

  /**
   * A staging/duplicate copy of the site forces manual renewal, to avoid charging real cards
   * from a clone. Not using WCS's own `is_manual()` here since it also treats every
   * non-WooCommerce-Payments gateway as manual, which would be wrong for our gateways.
   *
   * To allow real auto-payments on a staging copy, hook the
   * `woocommerce_subscriptions_is_duplicate_site` filter (WCS core, class-wcs-staging.php) and
   * return false — this check picks that up automatically since it calls WCS's own method
   * rather than re-deriving the URL comparison. See:
   * https://woocommerce.com/document/subscriptions/renewals/#staging-sites
   *
   * @param  \WC_Subscription $subscription  The subscription to check (unused; staging is a
   *         site-wide flag, not per-subscription — kept as a param for a uniform check signature).
   * @return array{result: bool, reason: string|null}|null  A final result if staging, null to
   *         continue to the next check.
   */
  private static function check_not_staging_site( $subscription ) {
    if ( class_exists( 'WCS_Staging' ) && \WCS_Staging::is_duplicate_site() ) {
      return [ 'result' => false, 'reason' => __( 'Site is flagged as a staging/duplicate site.', 'wicket-memberships' ) ];
    }

    return null;
  }

  /**
   * @param  \WC_Subscription $subscription  The subscription to check.
   * @return array{result: bool, reason: string|null}|null  A final result if manual renewal is
   *         required, null to continue to the next check.
   */
  private static function check_not_manual_renewal( $subscription ) {
    if ( ! empty( $subscription->get_requires_manual_renewal() ) ) {
      return [ 'result' => false, 'reason' => __( 'Subscription is set to require manual renewal.', 'wicket-memberships' ) ];
    }

    return null;
  }

  /**
   * Free ($0) subscriptions never need a real payment method and are excluded from WC
   * Subscriptions' own gateway checks, so treat them as a special case rather than asking
   * the gateway a question it isn't designed to answer.
   *
   * @param  \WC_Subscription $subscription  The subscription to check.
   * @return array{result: bool, reason: string|null}|null  A final `true` result if free, null to
   *         continue to the next check.
   */
  private static function check_free_subscription( $subscription ) {
    if ( (float) $subscription->get_total() <= 0 ) {
      return [ 'result' => true, 'reason' => null ];
    }

    return null;
  }

  /**
   * @param  \WC_Subscription $subscription  The subscription to check.
   * @return array{result: bool, reason: string|null}|null  A final result if no payment method is
   *         on file, null to continue to the next check.
   */
  private static function check_payment_method_on_file( $subscription ) {
    if ( '' === $subscription->get_payment_method() ) {
      return [ 'result' => false, 'reason' => __( 'No payment method on file.', 'wicket-memberships' ) ];
    }

    return null;
  }

  /**
   * `tokenization` is the flag that means the gateway can charge a stored payment method with
   * no customer present — the actual capability automatic renewal requires. Gateways that only
   * work with a live customer (cheque, COD, bank transfer) never declare it. This is distinct
   * from `gateway_scheduled_payments`, which only means the gateway self-manages its renewal
   * schedule instead of relying on WCS's own scheduled-payment hook — both are compatible with
   * automatic charging. See https://woocommerce.com/document/subscriptions/develop/payment-gateway-integration/
   *
   * Resolves the gateway directly rather than calling `$subscription->payment_method_supports()`:
   * that method returns `true` whenever `is_manual()` is true, for ANY reason `is_manual()` can be
   * true — including a gateway that's no longer registered at all (deactivated/deleted plugin).
   * Confirmed live: a subscription left manual by a deactivated Stripe still had
   * `payment_method_supports('tokenization')` return `true`, which is backwards.
   *
   * @param  \WC_Subscription $subscription  The subscription to check.
   * @return array{result: bool, reason: string|null}|null  A final result if the gateway can't
   *         charge unattended, null to continue (the last check, so null means autorenewing).
   */
  private static function check_gateway_can_charge_unattended( $subscription ) {
    $gateway = function_exists( 'wc_get_payment_gateway_by_order' ) ? wc_get_payment_gateway_by_order( $subscription ) : false;

    if ( $gateway && $gateway->supports( 'tokenization' ) ) {
      return null;
    }

    // No gateway resolves at all (plugin deactivated/deleted) is a different, more specific
    // problem than a gateway that exists but lacks the capability — worth its own reason.
    if ( ! $gateway ) {
      return [
        'result' => false,
        /* translators: %s: payment gateway ID stored on the subscription, e.g. "stripe" */
        'reason' => sprintf( __( 'Payment gateway (%s) is no longer installed or active.', 'wicket-memberships' ), $subscription->get_payment_method() ),
      ];
    }

    // get_method_title() is the actual gateway name (e.g. "Stripe"); get_title() is the
    // checkout-facing, merchant-customizable label (e.g. "Credit / Debit Card").
    $gateway_name = $gateway->get_method_title();

    return [
      'result' => false,
      /* translators: %s: payment gateway name, e.g. "Cheque" */
      'reason' => sprintf( __( 'Payment gateway (%s) cannot charge automatically without the customer present.', 'wicket-memberships' ), $gateway_name ),
    ];
  }

}
