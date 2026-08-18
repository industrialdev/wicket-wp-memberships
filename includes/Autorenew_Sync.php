<?php

namespace Wicket_Memberships;

defined( 'ABSPATH' ) || exit;

/**
 * Stores and keeps fresh the `Autorenew::resolve_status()` result as meta on each
 * `wicket_membership` post. `Autorenew` computes; this class stores, times, and reacts to change.
 *
 * @package Wicket_Memberships
 */
class Autorenew_Sync {

  /**
   * Meta key for the stored bool result of `Autorenew::resolve_status()`.
   *
   * @var string
   */
  const META_KEY_RESULT = 'membership_is_autorenew';

  /**
   * Meta key for the stored plain-English reason, empty when the result is true.
   *
   * @var string
   */
  const META_KEY_REASON = 'membership_is_autorenew_reason';

  /**
   * How many `wicket_membership` posts `run_sweep_batch()` processes per Action Scheduler run.
   * Kept small and paired with `SWEEP_BATCH_GAP_SECONDS` so a bulk re-sweep stays paced rather
   * than hammering downstream systems in one burst.
   *
   * @var int
   */
  const SWEEP_BATCH_SIZE = 50;

  /**
   * Delay between one sweep batch finishing and the next one being scheduled. Running batches
   * back-to-back with no gap risks overwhelming any downstream system this sweep eventually
   * notifies per membership; this spaces that load out instead.
   *
   * @var int
   */
  const SWEEP_BATCH_GAP_SECONDS = 5 * MINUTE_IN_SECONDS;

  /**
   * How long a bulk-sweep trigger waits before actually running, to collapse a rapid flip-flop
   * (e.g. toggle on, then off again within this window) into a no-op. Each new trigger within the
   * window reschedules the wait from scratch.
   *
   * @var int
   */
  const SWEEP_DEBOUNCE_SECONDS = 5 * MINUTE_IN_SECONDS;

  /**
   * Transient holding `['total' => int, 'processed' => int]` for the sweep currently in flight, so
   * the settings page can show progress. `total` is a one-time snapshot taken when the sweep
   * starts, not re-queried per batch — new memberships created mid-sweep don't shift the
   * denominator. Deleted once the sweep finishes.
   *
   * @var string
   */
  const PROGRESS_KEY = 'wicket_mship_autorenew_sweep_progress';

  public function __construct() {
    // Subscription modified (status or payment method). Refresh just this subscription.
    // Both hooks fire with 3 args, so accepted_args must be explicit (add_action() defaults to 1).
    add_action( 'woocommerce_subscription_status_updated', [ __CLASS__, 'handle_subscription_status_updated' ], 10, 3 );
    add_action( 'woocommerce_subscription_payment_method_updated', [ __CLASS__, 'handle_subscription_payment_method_updated' ], 10, 3 );

    // Admin edited a subscription directly in wp-admin. Bypasses both hooks above (WCS calls
    // set_status()/set_payment_method()/save() directly). Priority 20 runs after WCS's own save
    // handling on the same hook (priority 10).
    add_action( 'woocommerce_process_shop_order_meta', [ __CLASS__, 'handle_admin_subscription_edit' ], 20 );

    // Subscription total recalculated. A total crossing zero <-> non-zero (coupon, line-item
    // change) changes resolve_status()'s answer even with no status/payment-method change.
    add_action( 'woocommerce_order_after_calculate_totals', [ __CLASS__, 'handle_subscription_totals_calculated' ], 10, 2 );

    // Subscription permanently deleted (not cancelled/trashed). Leaves a membership's cached
    // status stale with nothing left to recompute it. woocommerce_before_delete_order fires from
    // both the legacy post-based store and HPOS, unlike before_delete_post.
    add_action( 'woocommerce_before_delete_order', [ __CLASS__, 'handle_subscription_deleted' ], 10, 2 );

    // Runs one batch of the sweep per action, then self-re-enqueues the next batch until done.
    add_action( 'wicket_mship_autorenew_sweep_batch', [ __CLASS__, 'run_sweep_batch' ], 10, 1 );
  }

  /**
   * Computes and stores the current autorenew result and reason for one membership post, and
   * pushes the change to the MDP if the computed result actually changed. Safe to call more than
   * once for the same membership in a request — each call just overwrites both meta values with
   * the freshly computed answer.
   *
   * @param  int $membership_post_id  The `wicket_membership` post ID to refresh.
   * @return void
   */
  public static function refresh_for_membership_post( $membership_post_id ) {
    $membership_meta = Helper::get_post_meta( $membership_post_id );
    $status = Autorenew::resolve_status( $membership_meta );

    $previous_result = metadata_exists( 'post', $membership_post_id, self::META_KEY_RESULT )
      ? (bool) get_post_meta( $membership_post_id, self::META_KEY_RESULT, true )
      : null;

    update_post_meta( $membership_post_id, self::META_KEY_RESULT, $status['result'] );
    update_post_meta( $membership_post_id, self::META_KEY_REASON, $status['reason'] ?? '' );

    // Push to the MDP only on a real change (including the first-ever computation, where
    // $previous_result is null) — a bulk sweep or a listener firing with no actual change
    // shouldn't PATCH the MDP for every membership it happens to touch.
    if ( $previous_result !== $status['result'] ) {
      self::push_to_mdp( $membership_post_id, $membership_meta, $status['result'] );
    }
  }

  /**
   * Pushes a membership's autorenew status to the MDP. Individual memberships only — organization
   * memberships are out of scope for this feature (see the plan doc's org deferral); a membership
   * with no `membership_wicket_uuid` yet (not yet created in the MDP) is skipped, since there's no
   * MDP record to patch.
   *
   * `wicket_update_individual_membership_dates()` always sends `starts_at`/`ends_at` in its
   * payload — unlike `grace_period_days`/`is_autorenew`, it has no way to omit them — and defaults
   * empty values to "now" / "one year from now." An autorenew-only push must read the membership's
   * real, precise dates directly via `get_post_meta()` (not `Helper::get_post_meta()`, which
   * truncates `_at` fields to a plain date, dropping the time/timezone) and pass them through
   * unchanged, or this call would silently corrupt the membership's real MDP dates as a side
   * effect of a change that was only ever about autorenew status.
   *
   * @param  int    $membership_post_id  The `wicket_membership` post ID being pushed.
   * @param  array  $membership_meta     This post's own meta, as returned by `Helper::get_post_meta()`.
   * @param  bool   $is_autorenew        The freshly computed autorenew result to push.
   * @return void
   */
  private static function push_to_mdp( $membership_post_id, $membership_meta, $is_autorenew ) {
    if ( ( $membership_meta['membership_type'] ?? '' ) !== 'individual' ) {
      return;
    }

    if ( empty( $membership_meta['membership_wicket_uuid'] ) ) {
      return;
    }

    $response = wicket_update_individual_membership_dates(
      $membership_meta['membership_wicket_uuid'],
      get_post_meta( $membership_post_id, 'membership_starts_at', true ),
      get_post_meta( $membership_post_id, 'membership_ends_at', true ),
      false, // grace_period_days: not this push's concern, omit
      $is_autorenew
    );

    if ( is_wp_error( $response ) ) {
      Utilities::wc_log_mship_error( sprintf(
        'Autorenew sync: failed to push is_autorenew=%s to MDP for membership #%d: %s',
        $is_autorenew ? 'true' : 'false',
        $membership_post_id,
        $response->get_error_message( 'wicket_api_error' )
      ) );
    }
  }

  /**
   * Reads the stored autorenew status for a membership post, computing and storing it first if
   * it has never been computed (e.g. a membership created before this cache existed). This is a
   * one-time cost per membership, not a live recompute on every read: once stored, every later
   * call reads the cached meta directly.
   *
   * @param  int $membership_post_id  The `wicket_membership` post ID to read.
   * @return array{result: bool, reason: string|null}
   */
  public static function get_stored_status( $membership_post_id ) {
    if ( ! metadata_exists( 'post', $membership_post_id, self::META_KEY_RESULT ) ) {
      self::refresh_for_membership_post( $membership_post_id );
    }

    return [
      'result' => (bool) get_post_meta( $membership_post_id, self::META_KEY_RESULT, true ),
      'reason' => get_post_meta( $membership_post_id, self::META_KEY_REASON, true ) ?: null,
    ];
  }

  /**
   * Refreshes every `wicket_membership` post linked to a given subscription. Not always exactly
   * one: bundle/multi-item orders create one membership post per line item on the same
   * subscription, so every match must be refreshed, not just the first.
   *
   * @param  int|string $subscription_id  The WooCommerce Subscriptions post ID.
   * @return void
   */
  public static function refresh_for_subscription( $subscription_id ) {
    $membership_post_ids = \get_posts( [
      'post_type' => 'wicket_membership',
      'post_status' => 'publish',
      'numberposts' => -1,
      'meta_query' => [
        [ 'key' => 'membership_subscription_id', 'value' => $subscription_id, 'compare' => '=' ],
      ],
      'fields' => 'ids',
    ] );

    foreach ( $membership_post_ids as $membership_post_id ) {
      self::refresh_for_membership_post( $membership_post_id );
    }
  }

  /**
   * Hooked to `woocommerce_subscription_status_updated`. Fires once per transition, after the
   * new status is already persisted (WCS core's `WC_Subscription::status_transition()`), so
   * `refresh_for_subscription()` reads a consistent, up-to-date subscription state.
   *
   * @param  \WC_Subscription $subscription  The subscription whose status changed.
   * @param  string           $new_status    The status transitioned to.
   * @param  string           $old_status    The status transitioned from.
   * @return void
   */
  public static function handle_subscription_status_updated( $subscription, $new_status, $old_status ) {
    self::refresh_for_subscription( $subscription->get_id() );
  }

  /**
   * Hooked to `woocommerce_subscription_payment_method_updated`. Covers customer-initiated
   * changes only (checkout, pay-shortcode, REST API) — see `handle_admin_subscription_edit()`
   * for the admin-direct-edit path this doesn't fire for.
   *
   * @param  \WC_Subscription $subscription       The subscription whose payment method changed.
   * @param  string           $new_payment_method  The gateway ID transitioned to.
   * @param  string           $old_payment_method  The gateway ID transitioned from.
   * @return void
   */
  public static function handle_subscription_payment_method_updated( $subscription, $new_payment_method, $old_payment_method ) {
    self::refresh_for_subscription( $subscription->get_id() );
  }

  /**
   * Hooked to `woocommerce_process_shop_order_meta`. Fires for every order type saved via the
   * admin edit screen, so this confirms it's actually a subscription first.
   *
   * `$order` is unused and optional: the legacy post-based edit screen fires this hook with 2
   * args, but WooCommerce's HPOS order-edit screen fires it with only 1 — a required second
   * param here would throw `ArgumentCountError` on HPOS.
   *
   * @param  int              $order_id  The saved order/subscription's post ID.
   * @param  \WC_Order|null   $order     The saved order object, when the caller provides one.
   * @return void
   */
  public static function handle_admin_subscription_edit( $order_id, $order = null ) {
    if ( ! function_exists( 'wcs_is_subscription' ) || ! wcs_is_subscription( $order_id ) ) {
      return;
    }

    self::refresh_for_subscription( $order_id );
  }

  /**
   * Hooked to `woocommerce_order_after_calculate_totals`. Fires for every order type on every
   * recalculation (cart/checkout included), so this filters to subscriptions first.
   *
   * @param  bool                          $and_taxes  Whether taxes were recalculated too; unused.
   * @param  \WC_Order|\WC_Subscription    $order      The order/subscription whose totals were recalculated.
   * @return void
   */
  public static function handle_subscription_totals_calculated( $and_taxes, $order ) {
    if ( ! function_exists( 'wcs_is_subscription' ) || ! wcs_is_subscription( $order ) ) {
      return;
    }

    self::refresh_for_subscription( $order->get_id() );
  }

  /**
   * Hooked to `woocommerce_before_delete_order`, which fires *before* the subscription is gone —
   * so `refresh_for_subscription()` would still find it and re-store the same, soon-to-be-wrong
   * "still valid" answer. This writes the same result `resolve_status()` gives once a subscription
   * can't be found, directly, matching its wording exactly.
   *
   * @param  int        $order_id  The subscription's order/post ID being deleted.
   * @param  \WC_Order  $order     The order/subscription object being deleted.
   * @return void
   */
  public static function handle_subscription_deleted( $order_id, $order ) {
    if ( ! function_exists( 'wcs_is_subscription' ) || ! wcs_is_subscription( $order ) ) {
      return;
    }

    $membership_post_ids = \get_posts( [
      'post_type' => 'wicket_membership',
      'post_status' => 'publish',
      'numberposts' => -1,
      'meta_query' => [
        [ 'key' => 'membership_subscription_id', 'value' => $order_id, 'compare' => '=' ],
      ],
      'fields' => 'ids',
    ] );

    foreach ( $membership_post_ids as $membership_post_id ) {
      $previous_result = metadata_exists( 'post', $membership_post_id, self::META_KEY_RESULT )
        ? (bool) get_post_meta( $membership_post_id, self::META_KEY_RESULT, true )
        : null;

      update_post_meta( $membership_post_id, self::META_KEY_RESULT, false );
      update_post_meta( $membership_post_id, self::META_KEY_REASON, __( 'Linked subscription not found.', 'wicket-memberships' ) );

      if ( false !== $previous_result ) {
        self::push_to_mdp( $membership_post_id, Helper::get_post_meta( $membership_post_id ), false );
      }
    }
  }

  /**
   * Enqueues the first batch of a full bulk re-sweep, starting at offset 0. Cancels any sweep
   * batches still in flight first: if the rule changes again before a prior sweep finishes, the
   * old sweep is still computing against a stale rule, so it must not keep running rather than
   * merely running alongside the new one.
   *
   * @return void
   */
  public static function enqueue_sweep() {
    as_unschedule_all_actions( 'wicket_mship_autorenew_sweep_batch', [], 'wicket-memberships' );

    $total = (int) wp_count_posts( 'wicket_membership' )->publish;
    set_transient( self::PROGRESS_KEY, [ 'total' => $total, 'processed' => 0 ], DAY_IN_SECONDS );

    as_enqueue_async_action( 'wicket_mship_autorenew_sweep_batch', [ 'offset' => 0 ], 'wicket-memberships' );
  }

  /**
   * Reads the current sweep's progress for display, e.g. on the settings page. Returns null when
   * no sweep is in flight (nothing to show).
   *
   * @return array{total: int, processed: int}|null
   */
  public static function get_sweep_progress() {
    $progress = get_transient( self::PROGRESS_KEY );
    return is_array( $progress ) ? $progress : null;
  }

  /**
   * Runs one bounded batch of the bulk re-sweep, then schedules the next batch
   * `SWEEP_BATCH_GAP_SECONDS` out until every `wicket_membership` post is refreshed. Never runs as
   * a single unbounded loop — a few thousand memberships at ~2 queries each risks
   * `max_execution_time` in one request. The gap between batches (rather than back-to-back via
   * `as_enqueue_async_action`) keeps the sweep's overall rate of work bounded and predictable.
   *
   * Offset-based paging can skip or double-count a row if a membership is created mid-sweep;
   * accepted as harmless (a missed row is caught by the per-subscription listeners or the next
   * sweep, a doubled row is just an extra recompute).
   *
   * @param  int $offset  How many `wicket_membership` posts to skip before this batch.
   * @return void
   */
  public static function run_sweep_batch( $offset ) {
    $membership_post_ids = \get_posts( [
      'post_type' => 'wicket_membership',
      'post_status' => 'publish',
      'posts_per_page' => self::SWEEP_BATCH_SIZE,
      'offset' => $offset,
      'orderby' => 'ID',
      'order' => 'ASC',
      'fields' => 'ids',
    ] );

    // A single membership's resolve_status() blowing up (e.g. corrupt subscription meta) must not
    // abort the batch — the reschedule below has to run regardless, or the sweep silently stalls
    // with no further batches ever enqueued and no operator-visible signal on the settings page.
    foreach ( $membership_post_ids as $membership_post_id ) {
      try {
        self::refresh_for_membership_post( $membership_post_id );
      } catch ( \Throwable $e ) {
        Utilities::wc_log_mship_error( sprintf(
          'Autorenew sweep: failed to refresh membership #%d (offset %d): %s',
          $membership_post_id,
          $offset,
          $e->getMessage()
        ) );
      }
    }

    // Advances the same progress snapshot enqueue_sweep() started; if it's missing (e.g. the
    // transient expired or was cleared out of band), there's no total to report against, so
    // progress just isn't tracked for the rest of this run rather than guessing a total.
    $progress = get_transient( self::PROGRESS_KEY );
    if ( is_array( $progress ) ) {
      $progress['processed'] = $offset + count( $membership_post_ids );
      set_transient( self::PROGRESS_KEY, $progress, DAY_IN_SECONDS );
    }

    // A short batch means this was the last one; a full batch means more may remain, so
    // schedule the next one rather than querying again just to check.
    if ( count( $membership_post_ids ) === self::SWEEP_BATCH_SIZE ) {
      as_schedule_single_action(
        time() + self::SWEEP_BATCH_GAP_SECONDS,
        'wicket_mship_autorenew_sweep_batch',
        [ 'offset' => $offset + self::SWEEP_BATCH_SIZE ],
        'wicket-memberships'
      );
    } else {
      delete_transient( self::PROGRESS_KEY );
    }
  }

}
