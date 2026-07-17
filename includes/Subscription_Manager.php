<?php

namespace Wicket_Memberships;

/**
 * Central home for logic that operates on WooCommerce Subscriptions (WC_Subscription)
 * on behalf of a Wicket membership.
 *
 * Subscription-touching code in this plugin is currently scattered across
 * Membership_Controller, Admin_Controller, Membership_Subscription_Controller,
 * and Import_Controller — subscription lookup by membership meta (duplicated
 * ~30 times), status-transition handling (partially centralized, partially
 * re-implemented ad hoc), subscription creation (three near-identical
 * sequences), subscription-to-membership linking, payment/customer-id
 * reassignment on ownership transfer, and autorenew-flag handling.
 *
 * This class is the intended eventual home for all of that. It starts with
 * only the date-safety guards (the first, narrowest slice), and other
 * subscription concerns should be migrated in here incrementally rather than
 * added to Membership_Controller/Admin_Controller going forward. New
 * subscription-related code should be added here, not to those classes.
 *
 * Methods remain static and dependency-free (operate on a passed-in
 * \WC_Subscription, no reliance on Membership_Controller state) so this class
 * can be composed into whatever replaces the current controllers without
 * rework.
 */
class Subscription_Manager {

  /**
   * Core invariant check shared by both collision guards below: a subscription's
   * 'end' date must always land strictly after its 'next_payment' date.
   *
   * WooCommerce Subscriptions throws an exception when end <= next_payment — a
   * strict, zero-tolerance comparison in WC_Subscription::prepare_dates_for_update().
   * A tier with no grace period configured naturally produces end == next_payment
   * for the same renewal cycle, so this collision is expected, not exceptional.
   *
   * next_payment is the side that moves, never end: end is typically a
   * day-boundary timestamp (23:59:59 via Utilities::get_mdp_day_end()) and
   * bumping it forward would roll into the next calendar day. next_payment is
   * a billing scheduling marker with no user-visible meaning at the seconds
   * level, and moving it earlier (not later) cannot cause a missed or
   * duplicate charge.
   *
   * Owns the full decision: compares the two timestamps, adjusts and logs
   * when they collide, and returns the timestamp callers should use — either
   * one of them just resolves its inputs into timestamps and hands off here.
   *
   * @param  int|null  $next_payment_ts  Proposed next_payment timestamp, or null if none exists.
   * @param  int|null  $end_ts           Comparison end timestamp, or null if none exists.
   *
   * @return int|null  $next_payment_ts, or $end_ts - 1 if they collided; null if either input was null.
   */
  private static function nudge_next_payment_before_end( ?int $next_payment_ts, ?int $end_ts ): ?int {
    if ( empty( $next_payment_ts ) || empty( $end_ts ) ) {
      return $next_payment_ts;
    }

    if ( $next_payment_ts >= $end_ts ) {
      $adjusted_ts = $end_ts - 1;
      Utilities::wicket_logger( 'Adjusted NEXT_PAYMENT date -1s to avoid end date collision', date( 'Y-m-d H:i:s', $adjusted_ts ) );
      return $adjusted_ts;
    }

    return $next_payment_ts;
  }

  /**
   * Bootstrapper for all subscription date writes going through Subscription_Manager.
   *
   * NOTE: named prepare_dates(), not update_dates(), deliberately — for now it
   * only runs the dates through the end/next_payment collision guard and
   * returns the (possibly corrected) array; callers still call
   * $sub->update_dates($dates_to_update) themselves afterward. Once this
   * method also performs that write itself (the intended next step — see
   * Class-Subscription_Manager.md), it should be renamed to update_dates()
   * and return void; keeping that name free until then avoids two
   * same-named, different-effect calls sitting next to each other at the
   * call site. This is also the intended home for any other cross-field
   * invariant checks subscription dates need, so callers have one entry
   * point regardless of how many guards accumulate behind it.
   *
   * Handles any shape callers use with WC_Subscription::update_dates(): 'end'
   * alone or alongside 'next_payment', or 'next_payment' alone (e.g. the
   * deferred/async force-set job). Whichever key is missing from
   * $dates_to_update is resolved from the subscription's own current stored
   * value instead — since a single update_dates() call only ever needs the
   * *other* value for comparison, not to write it.
   *
   * @param  array             $dates_to_update  Dates array bound for WC_Subscription::update_dates(),
   *                                              may contain 'end' and/or 'next_payment' keys — at
   *                                              least one of the two is expected.
   * @param  \WC_Subscription  $sub              Subscription being updated; used to read whichever of
   *                                              'end'/'next_payment' this call doesn't set itself.
   *
   * @return array  The dates array, with 'next_payment' nudged 1 second before 'end' if they collided.
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
}
