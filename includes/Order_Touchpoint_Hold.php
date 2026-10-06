<?php

namespace Wicket_Memberships;

/**
 * Holds wicket-wp-base-plugin's WooCommerce order touchpoint until a bundle renewal order
 * is fully built.
 *
 * Membership_Bundle_Renewal_Order_Controller::create_renewal_order_job() builds the renewal
 * order in an Action Scheduler job. The base plugin's woocommerce_order_touchpoint() fires
 * on that order's first save, before its line items, org, or repriced totals exist, and
 * the MDP keeps only the first touchpoint per order + status, so it would record an empty
 * "Order Pending" that can never be corrected.
 *
 * @package Wicket_Memberships
 */
class Order_Touchpoint_Hold {

  /** Priority the base plugin registers woocommerce_order_touchpoint() at. */
  private const PRIORITY = 9999;

  /**
   * Create the subscription's renewal order with the order touchpoint unhooked, then
   * write it once for the finished order. Writes nothing if creation fails, and does
   * nothing extra when the base plugin's order touchpoints are disabled.
   *
   * @return \WC_Order|\WP_Error The result of wcs_create_renewal_order().
   */
  public static function create_renewal_order( \WC_Subscription $subscription ): \WC_Order|\WP_Error {
    if ( false === has_action( 'woocommerce_new_order', 'woocommerce_order_touchpoint' ) ) {
      return wcs_create_renewal_order( $subscription );
    }

    remove_action( 'woocommerce_new_order', 'woocommerce_order_touchpoint', self::PRIORITY );
    remove_action( 'woocommerce_order_status_changed', 'woocommerce_order_touchpoint', self::PRIORITY );
    try {
      $result = wcs_create_renewal_order( $subscription );
    } finally {
      // Same priority and arg counts as the base plugin's own registration.
      add_action( 'woocommerce_order_status_changed', 'woocommerce_order_touchpoint', self::PRIORITY );
      add_action( 'woocommerce_new_order', 'woocommerce_order_touchpoint', self::PRIORITY, 2 );
    }

    if ( $result instanceof \WC_Order ) {
      woocommerce_order_touchpoint( $result->get_id(), wc_get_order( $result->get_id() ) );
    }

    return $result;
  }
}
