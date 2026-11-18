<?php

namespace Wicket_Memberships;

/**
 * Central home for logic that operates on WooCommerce Subscriptions
 * (WC_Subscription) on behalf of a Wicket membership.
 *
 * Subscription-touching code is currently scattered across the membership
 * controllers. This class is its intended eventual home, starting with the
 * date-safety guards below; new subscription code belongs here rather than in
 * those controllers. Methods are static and take a passed-in
 * \WC_Subscription so nothing depends on controller state.
 *
 * @see atlas://packages/wicket-wp-memberships/engineering/Class-Subscription_Manager.md for the migration plan.
 */
class Subscription_Manager {

  /**
   * How far before 'end' a colliding 'next_payment' is pushed back.
   *
   * Any positive gap satisfies WooCommerce. An hour keeps the two dates
   * visibly distinct in the admin UI (which renders to the minute) and stays
   * inside the same day, so the nudge can't roll onto the previous date.
   */
  private const NEXT_PAYMENT_COLLISION_OFFSET = HOUR_IN_SECONDS;

  /**
   * Enforces the invariant that 'end' lands strictly after 'next_payment'.
   *
   * WC_Subscription::prepare_dates_for_update() throws on end <= next_payment,
   * and a tier with no grace period naturally produces end == next_payment for
   * the same renewal cycle — so this collision is expected, not exceptional.
   *
   * next_payment is the side that moves, never end: end is typically a
   * day-boundary timestamp (23:59:59) that would roll into the next day if
   * bumped forward, while next_payment is a scheduling marker, and moving it
   * earlier cannot cause a missed or duplicate charge.
   *
   * @param  int|null  $next_payment_ts  Proposed next_payment timestamp, or null if none exists.
   * @param  int|null  $end_ts           Comparison end timestamp, or null if none exists.
   *
   * @return int|null  $next_payment_ts, offset back before $end_ts if they collided;
   *                    null if either input was null.
   */
  private static function nudge_next_payment_before_end( ?int $next_payment_ts, ?int $end_ts ): ?int {
    if ( empty( $next_payment_ts ) || empty( $end_ts ) ) {
      return $next_payment_ts;
    }

    if ( $next_payment_ts >= $end_ts ) {
      $adjusted_ts = $end_ts - self::NEXT_PAYMENT_COLLISION_OFFSET;
      Utilities::wicket_logger(
        sprintf( 'Adjusted NEXT_PAYMENT date -%ds to avoid end date collision', self::NEXT_PAYMENT_COLLISION_OFFSET ),
        date( 'Y-m-d H:i:s', $adjusted_ts )
      );
      return $adjusted_ts;
    }

    return $next_payment_ts;
  }

  /**
   * Runs a dates array bound for WC_Subscription::update_dates() through the
   * collision guard, and returns the corrected array for the caller to write.
   *
   * Accepts 'end' and/or 'next_payment'; whichever key is absent is read from
   * the subscription for comparison only. Named prepare_dates() rather than
   * update_dates() because callers still perform the write themselves — see
   * the doc for the planned rename once this method writes too.
   *
   * @param  array             $dates_to_update  Dates array, expected to contain 'end' and/or 'next_payment'.
   * @param  \WC_Subscription  $sub              Subscription supplying whichever date this call doesn't set.
   *
   * @return array  The dates array, with a colliding 'next_payment' nudged before 'end'.
   */
  public static function prepare_dates( array $dates_to_update, \WC_Subscription $sub ): array {
    if ( empty( $dates_to_update['end'] ) && empty( $dates_to_update['next_payment'] ) ) {
      return $dates_to_update;
    }

    $end_ts = ! empty( $dates_to_update['end'] )
      ? strtotime( $dates_to_update['end'] )
      : $sub->get_time( 'end' );

    $next_payment_ts = ! empty( $dates_to_update['next_payment'] )
      ? strtotime( $dates_to_update['next_payment'] )
      : $sub->get_time( 'next_payment' );

    $adjusted_ts = self::nudge_next_payment_before_end( $next_payment_ts, $end_ts );

    if ( $adjusted_ts !== $next_payment_ts ) {
      $dates_to_update['next_payment'] = date( 'Y-m-d H:i:s', $adjusted_ts );
    }

    return $dates_to_update;
  }

  /**
   * Removes the pending payment from a subscription whose membership has been renewed elsewhere.
   *
   * A renewal that creates a NEW subscription leaves the old one with a live payment date and
   * nothing to stop it, so it keeps taking money for a term that is over. Clearing the date leaves
   * it inert: it takes no further payment and expires on its own end date without renewing.
   *
   * Monthly billing is the exception. Those payments are instalments against the term the member
   * has already taken, so they are still owed and the date is left in place.
   *
   * Deliberately does NOT route through prepare_dates(), which exists to PRESERVE a colliding
   * next_payment by nudging it earlier. Here the payment is the thing being removed.
   *
   * @param  \WC_Subscription  $sub  Superseded subscription to stop taking payment on.
   *
   * @return bool  True if the date was removed; false if there was nothing to remove, the billing
   *               period is monthly, or WooCommerce rejected the write.
   */
  public static function drop_superseded_next_payment( \WC_Subscription $sub ): bool {
    if ( ( $sub->get_billing_period() == 'month' && $sub->get_billing_interval() == 1 )
      || empty( $sub->get_time( 'next_payment' ) ) ) {
      return false;
    }

    try {
      // WooCommerce reads a 0 date as a delete.
      $sub->update_dates( [ 'next_payment' => 0 ] );
    } catch ( \Exception $e ) {
      Utilities::wc_log_mship_error( [ 'Failed to remove next payment date from superseded subscription', [ $sub->get_id(), $e->getMessage() ] ] );
      return false;
    }

    $sub->add_order_note( 'Wicket removed the next payment date: the membership this subscription was paying for has been renewed.' );

    return true;
  }

  /**
   * Filter callback for `woocommerce_subscription_calculated_next_payment_date`.
   *
   * WooCommerce Subscriptions recalculates the next payment date each time a subscription becomes
   * active with no date, or with one less than 2 hours away: the first payment, a paid renewal
   * order, or an admin reactivation (WC_Subscription::update_status()). For a Form Flow
   * membership that date is a term-end renewal that skips the form, so return 0 and WooCommerce
   * sets none.
   *
   * Instalments within the term keep their calculated date. Bundle memberships are left alone:
   * bundles apply their own next payment rule (Membership_Bundle).
   *
   * @param  string|int        $next_payment  Calculated date ('Y-m-d H:i:s'), or 0 when none.
   * @param  \WC_Subscription  $subscription  Subscription the date was calculated for.
   *
   * @return string|int  The calculated date, or 0 for a Form Flow term-end subscription.
   */
  public static function block_form_flow_next_payment( $next_payment, $subscription ) {
    if ( empty( $next_payment ) || ! is_a( $subscription, 'WC_Subscription' ) ) {
      return $next_payment;
    }

    if ( ! self::is_form_flow_term_end_subscription( $subscription ) ) {
      return $next_payment;
    }

    Utilities::wc_log_mship_error( [ 'Blocked calculated next payment date on Form Flow subscription', [ $subscription->get_id(), $next_payment ] ] );

    return 0;
  }

  /**
   * Action callback for `woocommerce_update_order`: removes the next payment date from a Form
   * Flow term-end subscription whenever it is saved.
   *
   * The other guards stop a date being written or calculated. This one removes a date that is
   * already there: one set before the fix, typed in by an admin, or left when a member uses the
   * My Account auto-renew toggle. Only linked memberships are checked, so subscriptions with no
   * membership pay no tier lookup on every save.
   *
   * @param  int $order_id  ID of the saved order or subscription.
   *
   * @return void
   */
  public static function clear_form_flow_next_payment_on_save( $order_id ): void {
    // Removing the date saves the subscription again, which fires this hook again.
    static $running = false;

    if ( $running || ! function_exists( 'wcs_is_subscription' ) || ! wcs_is_subscription( $order_id ) ) {
      return;
    }

    $sub = wcs_get_subscription( $order_id );
    if ( empty( $sub ) || empty( $sub->get_time( 'next_payment' ) )
      || ! self::is_form_flow_term_end_subscription( $sub, false ) ) {
      return;
    }

    $running = true;
    try {
      // WooCommerce reads a 0 date as a delete, and unschedules the renewal payment.
      $sub->update_dates( [ 'next_payment' => 0 ] );
      $sub->add_order_note( 'Wicket removed the next payment date: this membership renews through its renewal form.' );
    } catch ( \Exception $e ) {
      Utilities::wc_log_mship_error( [ 'Failed to remove next payment date from Form Flow subscription', [ $sub->get_id(), $e->getMessage() ] ] );
    } finally {
      $running = false;
    }
  }

  /**
   * Whether a subscription pays for a Form Flow membership with one payment per term, so any
   * next payment date is a term-end renewal that skips the form. Instalments within the term are
   * not term-end charges.
   *
   * @param  \WC_Subscription  $sub                     Subscription to check.
   * @param  bool              $allow_product_fallback  Use the product's tier when no membership is linked.
   *
   * @return bool
   */
  public static function is_form_flow_term_end_subscription( \WC_Subscription $sub, bool $allow_product_fallback = true ): bool {
    $tier_post_id = self::get_form_flow_tier_post_id( $sub, $allow_product_fallback );

    return null !== $tier_post_id && ! Helper::is_instalment_subscription( $sub, $tier_post_id );
  }

  /**
   * Whether a subscription pays for a Form Flow membership.
   *
   * @param  \WC_Subscription  $sub  Subscription to check.
   *
   * @return bool  True for an individual Form Flow membership; false otherwise, including bundles.
   */
  public static function is_form_flow_subscription( \WC_Subscription $sub ): bool {
    return null !== self::get_form_flow_tier_post_id( $sub );
  }

  /**
   * The tier of the Form Flow membership a subscription pays for.
   *
   * Reads the newest membership linked to the subscription, so a per-membership renewal override
   * is respected. Before the first payment no membership exists yet, so it can fall back to the
   * tier of the subscription's products.
   *
   * @param  \WC_Subscription  $sub                     Subscription to check.
   * @param  bool              $allow_product_fallback  Use the product's tier when no membership is linked.
   *
   * @return int|null  Tier post ID (0 when the membership has none), or null when the subscription
   *                   is not an individual Form Flow membership.
   */
  private static function get_form_flow_tier_post_id( \WC_Subscription $sub, bool $allow_product_fallback = true ): ?int {
    $membership_post_ids = get_posts( [
      'post_type' => 'wicket_membership',
      'post_status' => 'publish',
      'numberposts' => 1,
      'orderby' => 'ID',
      'order' => 'DESC',
      'meta_query' => [
        [ 'key' => 'membership_subscription_id', 'value' => $sub->get_id(), 'compare' => '=' ],
      ],
      'fields' => 'ids',
    ] );

    if ( ! empty( $membership_post_ids ) ) {
      $membership_post_id = $membership_post_ids[0];

      if ( ! empty( get_post_meta( $membership_post_id, 'membership_bundle_id', true ) ) ) {
        return null;
      }

      $is_form_flow = Helper::is_form_flow_renewal( [
        'membership_next_tier_form_page_id' => get_post_meta( $membership_post_id, 'membership_next_tier_form_page_id', true ),
        'membership_next_tier_subscription_renewal' => get_post_meta( $membership_post_id, 'membership_next_tier_subscription_renewal', true ),
      ] );

      return $is_form_flow ? (int) get_post_meta( $membership_post_id, 'membership_tier_post_id', true ) : null;
    }

    if ( ! $allow_product_fallback ) {
      return null;
    }

    foreach ( $sub->get_items() as $item ) {
      $product_id = $item->get_variation_id() ?: $item->get_product_id();
      if ( function_exists( 'wicket_get_multilang_product_id' ) ) {
        $product_data = wicket_get_multilang_product_id( $product_id, 'en' );
        if ( ! empty( $product_data['was_translated'] ) ) {
          $product_id = $product_data['translated'];
        }
      }

      $tier = Membership_Tier::get_tier_by_product_id( $product_id );
      // A membership product on a Form Flow tier that has a renewal form to send the member to.
      if ( ! empty( $tier->tier_data )
        && 'form_flow' === $tier->get_tier_renewal_type()
        && ! empty( $tier->get_next_tier_form_page_id() ) ) {
        return (int) $tier->get_membership_tier_post_id();
      }
    }

    return null;
  }
}
