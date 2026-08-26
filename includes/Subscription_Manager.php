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
 * See docs/engineering/Class-Subscription_Manager.md for the migration plan.
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
}
