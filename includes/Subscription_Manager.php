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
   * Clamps a subscription's 'end' to a membership's end date, dropping a 'next_payment' that
   * would fall on or after it.
   *
   * For a membership renewed into a NEW subscription, the old one is left billing to its own end
   * date with nothing to stop it. This terminates it with the term it was actually paying for.
   *
   * Deliberately does NOT route through prepare_dates(): that guard PRESERVES a colliding
   * next_payment by nudging it an hour before 'end', which here would take one more monthly
   * payment the member no longer owes. The intent is the opposite - remove it.
   *
   * WooCommerce treats a 0 date as a delete and excludes it from the end > next_payment
   * comparison (WC_Subscription::prepare_dates_for_update()), so both changes go in one call.
   *
   * @param  \WC_Subscription  $sub     Subscription to terminate.
   * @param  int               $end_ts  Membership end timestamp (UTC) to terminate on.
   *
   * @return bool  True if the dates were written; false if the clamp was skipped or rejected.
   *
   * @since 1.0.123
   */
  public static function terminate_at_membership_end( \WC_Subscription $sub, int $end_ts ): bool {
    if ( empty( $end_ts ) ) {
      return false;
    }

    if ( ! $sub->can_date_be_updated( 'end' ) ) {
      Utilities::wc_log_mship_error( [ 'Subscription end date not updatable, termination clamp skipped', [ $sub->get_id(), $sub->get_status() ] ] );
      return false;
    }

    // Only ever shorten. A subscription already ending on or before the term end needs nothing,
    // and extending one here would be the opposite of the intent.
    $current_end_ts = $sub->get_time( 'end' );
    if ( ! empty( $current_end_ts ) && $end_ts >= $current_end_ts ) {
      return false;
    }

    // WooCommerce rejects an 'end' that precedes the last payment. Renewing late enough that a
    // monthly charge already landed past the term end is the case that reaches this.
    $last_payment_ts = $sub->get_time( 'last_order_date_created' );
    if ( ! empty( $last_payment_ts ) && $end_ts <= $last_payment_ts ) {
      Utilities::wc_log_mship_error( [ 'Membership end precedes last subscription payment, termination clamp skipped', [ $sub->get_id(), gmdate( 'Y-m-d H:i:s', $end_ts ), gmdate( 'Y-m-d H:i:s', $last_payment_ts ) ] ] );
      return false;
    }

    // gmdate(), not date(): WooCommerce reads these as UTC. WordPress pins PHP's default timezone
    // to UTC so the two agree today, but this value has no reason to depend on that.
    $dates_to_update = [ 'end' => gmdate( 'Y-m-d H:i:s', $end_ts ) ];

    $next_payment_ts = $sub->get_time( 'next_payment' );
    $drop_next_payment = ! empty( $next_payment_ts ) && $next_payment_ts >= $end_ts;
    if ( $drop_next_payment ) {
      $dates_to_update['next_payment'] = 0;
    }

    try {
      $sub->update_dates( $dates_to_update );
    } catch ( \Exception $e ) {
      Utilities::wc_log_mship_error( [ 'Failed to terminate subscription at membership end', [ $sub->get_id(), $dates_to_update, $e->getMessage() ] ] );
      return false;
    }

    $sub->add_order_note(
      $drop_next_payment
        ? 'Wicket set this subscription to end ' . $dates_to_update['end'] . ' with the membership term it was paying for, and removed the next payment date, which fell after that.'
        : 'Wicket set this subscription to end ' . $dates_to_update['end'] . ' with the membership term it was paying for. Next payment date kept, it falls before that.'
    );
    Utilities::wicket_logger( 'Terminated subscription at membership end', [ $sub->get_id(), $dates_to_update ] );

    return true;
  }
}
