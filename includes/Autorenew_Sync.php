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
   * than hammering downstream systems in one burst — see quirks/autorenew-sync-bulk-resweep-triggers.md
   * for the open question on what pace is actually safe.
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
   * Transient storing the forced-workflow rule's published-state value from before the *first*
   * trigger in the current debounce window — the baseline a flip-flop needs to revert to for the
   * window to resolve as a no-op. Set once per window (a later trigger in the same window must
   * not overwrite it with an intermediate value), cleared when the debounced decision runs.
   *
   * @var string
   */
  const BASELINE_KEY_WORKFLOW = 'wicket_mship_autorenew_sweep_baseline_workflow';

  /**
   * Transient storing the gateway-support rule's snapshot from before the *first* trigger in the
   * current debounce window — same baseline/no-op semantics as `BASELINE_KEY_WORKFLOW`, just
   * holding a `[gateway_id => bool]` snapshot instead of a single bool.
   *
   * @var string
   */
  const BASELINE_KEY_GATEWAY_SUPPORT = 'wicket_mship_autorenew_sweep_baseline_gateway_support';

  /**
   * Option storing the last-seen `[gateway_id => bool enabled]` snapshot, so a WooCommerce
   * settings save can tell whether any gateway's enabled state actually changed rather than
   * sweeping on every save. No expiry: a running snapshot, not a debounce window baseline.
   *
   * @var string
   */
  const GATEWAY_SUPPORT_SNAPSHOT_KEY = 'wicket_mship_autorenew_gateway_support_snapshot';

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

    // Bulk re-sweep triggers: forced-autorenew workflow published/unpublished, or payment gateway
    // settings changed (three separate save paths — classic form, legacy wc-ajax toggle, and the
    // newer REST-based Payments screen, which fires neither of the other two hooks). Each is a
    // rule change, not one subscription's data, so every stored value can go stale at once.
    add_action( 'transition_post_status', [ __CLASS__, 'handle_aw_workflow_status_transition' ], 10, 3 );
    add_action( 'woocommerce_update_options', [ __CLASS__, 'handle_woocommerce_settings_updated' ] );
    add_action( 'updated_option', [ __CLASS__, 'handle_option_updated' ], 10, 1 );

    // A bulk-sweep trigger's debounce window elapsed. Sweep only if the rule's current value
    // actually differs from the baseline captured before the window opened.
    add_action( 'wicket_mship_autorenew_sweep_decision_workflow', [ __CLASS__, 'run_sweep_decision_workflow' ], 10, 1 );
    add_action( 'wicket_mship_autorenew_sweep_decision_gateway_support', [ __CLASS__, 'run_sweep_decision_gateway_support' ], 10, 1 );

    // Runs one batch of the sweep per action, then self-re-enqueues the next batch until done.
    add_action( 'wicket_mship_autorenew_sweep_batch', [ __CLASS__, 'run_sweep_batch' ], 10, 1 );
  }

  /**
   * Computes and stores the current autorenew result and reason for one membership post.
   * Safe to call more than once for the same membership in a request — each call just
   * overwrites both meta values with the freshly computed answer.
   *
   * @param  int $membership_post_id  The `wicket_membership` post ID to refresh.
   * @return void
   */
  public static function refresh_for_membership_post( $membership_post_id ) {
    $membership_meta = Helper::get_post_meta( $membership_post_id );
    $status = Autorenew::resolve_status( $membership_meta );

    update_post_meta( $membership_post_id, self::META_KEY_RESULT, $status['result'] );
    update_post_meta( $membership_post_id, self::META_KEY_REASON, $status['reason'] ?? '' );
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
      update_post_meta( $membership_post_id, self::META_KEY_RESULT, false );
      update_post_meta( $membership_post_id, self::META_KEY_REASON, __( 'Linked subscription not found.', 'wicket-memberships' ) );
    }
  }

  /**
   * Hooked to `transition_post_status`. Only the forced-autorenew workflow
   * (`Autorenew::FORCED_WORKFLOW_TITLE`) affects `Autorenew::resolve_status()`'s answer, and only
   * when its published-ness actually flips (e.g. publish -> draft/trash, or draft -> publish) —
   * any other save (editing an unrelated field, a resave with no status change) is a no-op here.
   *
   * @param  string   $new_status  The status transitioned to.
   * @param  string   $old_status  The status transitioned from.
   * @param  \WP_Post $post        The post transitioning status.
   * @return void
   */
  public static function handle_aw_workflow_status_transition( $new_status, $old_status, $post ) {
    if ( 'aw_workflow' !== $post->post_type || $post->post_title !== Autorenew::FORCED_WORKFLOW_TITLE ) {
      return;
    }

    $was_published = 'publish' === $old_status;
    $is_published = 'publish' === $new_status;

    if ( $was_published === $is_published ) {
      return;
    }

    self::debounce_sweep_decision(
      self::BASELINE_KEY_WORKFLOW,
      $was_published,
      'wicket_mship_autorenew_sweep_decision_workflow'
    );
  }

  /**
   * Hooked to `woocommerce_update_options`. Fires on the classic settings-tab form save and the
   * legacy wc-ajax gateway enable/disable toggle. Not the only way a gateway can change — see
   * `handle_option_updated()` for the newer REST-based Payments screen, which fires neither.
   *
   * @return void
   */
  public static function handle_woocommerce_settings_updated() {
    self::check_gateway_support_snapshot();
  }

  /**
   * Hooked to `updated_option`. WooCommerce's newer REST-based Payments settings screen calls
   * `update_option()` directly and fires none of this plugin's other hooks — `updated_option` is
   * the one hook guaranteed to fire regardless of which gateway-settings UI made the change, since
   * it's WordPress core firing on the actual DB write. Fires for every option on the site, so this
   * filters to option keys belonging to a currently-registered gateway first.
   *
   * @param  string $option  The option name that was just updated.
   * @return void
   */
  public static function handle_option_updated( $option ) {
    if ( ! self::option_belongs_to_a_gateway( $option ) ) {
      return;
    }

    self::check_gateway_support_snapshot();
  }

  /**
   * Whether `$option` is a currently-registered payment gateway's own settings option
   * (`WC_Settings_API::get_option_key()`, e.g. `woocommerce_stripe_settings`) — checked against
   * live gateway instances rather than a hardcoded naming pattern, since a gateway's `plugin_id`
   * can vary.
   *
   * @param  string $option  The option name to check.
   * @return bool
   */
  private static function option_belongs_to_a_gateway( $option ) {
    // WC()->payment_gateways must be called as a method: WC()'s magic __get() that resolves the
    // bare property to this method didn't reliably fire at this hook's point in the request.
    if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
      return false;
    }

    foreach ( WC()->payment_gateways()->payment_gateways() as $gateway ) {
      if ( method_exists( $gateway, 'get_option_key' ) && $gateway->get_option_key() === $option ) {
        return true;
      }
    }

    return false;
  }

  /**
   * Shared end of both `handle_woocommerce_settings_updated()` and `handle_option_updated()`:
   * takes a fresh gateway snapshot and debounces a sweep decision unless it matches the last
   * stored one.
   *
   * An unset previous snapshot (this method's first-ever run on a site) still debounces a
   * decision rather than treating it as a no-op: being called at all means a gateway option was
   * just written to, so there's no legitimate "nothing changed" outcome on a first run.
   *
   * @return void
   */
  private static function check_gateway_support_snapshot() {
    $current_snapshot = self::current_gateway_support_snapshot();
    $previous_snapshot = get_option( self::GATEWAY_SUPPORT_SNAPSHOT_KEY, null );

    if ( null !== $previous_snapshot && $current_snapshot === $previous_snapshot ) {
      return;
    }

    update_option( self::GATEWAY_SUPPORT_SNAPSHOT_KEY, $current_snapshot, false );

    self::debounce_sweep_decision(
      self::BASELINE_KEY_GATEWAY_SUPPORT,
      $previous_snapshot,
      'wicket_mship_autorenew_sweep_decision_gateway_support'
    );
  }

  /**
   * Builds a `[gateway_id => bool]` map of every registered payment gateway's current enabled
   * state. Tracks enabled/disabled rather than declared `gateway_scheduled_payments` support:
   * a gateway's declared support is effectively static (e.g. Stripe never declares it at all,
   * regardless of enabled state), while enabling/disabling is the real-world event that can force
   * subscriptions using that gateway toward manual renewal.
   *
   * @return array<string, bool>
   */
  private static function current_gateway_support_snapshot() {
    if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
      return [];
    }

    $snapshot = [];
    foreach ( WC()->payment_gateways()->payment_gateways() as $gateway_id => $gateway ) {
      $snapshot[ $gateway_id ] = isset( $gateway->enabled ) ? 'yes' === $gateway->enabled : true;
    }

    return $snapshot;
  }

  /**
   * Hooked to `wicket_mship_autorenew_sweep_decision_gateway_support`, once the gateway-support
   * trigger's debounce window elapses. Sweeps only if the current snapshot still differs from the
   * pre-window baseline — a settings save that gets reverted within the window resolves as a
   * no-op, same as the forced-workflow trigger.
   *
   * @return void
   */
  public static function run_sweep_decision_gateway_support() {
    self::resolve_sweep_decision( self::BASELINE_KEY_GATEWAY_SUPPORT, self::current_gateway_support_snapshot() );
  }

  /**
   * Schedules (or reschedules) a debounced decision for one rule. Captures `$baseline_value` as
   * the pre-window baseline only if this is the first trigger in the current window — a second
   * trigger before the decision has run must not overwrite it with an intermediate value, or a
   * later revert back to the true original couldn't be recognized as a no-op.
   *
   * @param  string $baseline_transient_key  One of the `BASELINE_KEY_*` constants.
   * @param  mixed  $baseline_value          The rule's value from before this trigger fired.
   * @param  string $decision_hook           The rule's own `wicket_mship_autorenew_sweep_decision_*` hook.
   * @return void
   */
  private static function debounce_sweep_decision( $baseline_transient_key, $baseline_value, $decision_hook ) {
    // Wrapped in an array so a legitimately falsy baseline (false, '', '0') is never confused
    // with get_transient()'s own false-means-miss sentinel.
    if ( false === get_transient( $baseline_transient_key ) ) {
      set_transient( $baseline_transient_key, [ 'value' => $baseline_value ], self::SWEEP_DEBOUNCE_SECONDS + MINUTE_IN_SECONDS );
    }

    as_unschedule_all_actions( $decision_hook, [], 'wicket-memberships' );
    as_schedule_single_action( time() + self::SWEEP_DEBOUNCE_SECONDS, $decision_hook, [], 'wicket-memberships' );
  }

  /**
   * Hooked to `wicket_mship_autorenew_sweep_decision_workflow`, once the forced-workflow
   * trigger's debounce window elapses. Sweeps only if the workflow's current published state
   * differs from its pre-window baseline.
   *
   * @return void
   */
  public static function run_sweep_decision_workflow() {
    self::resolve_sweep_decision( self::BASELINE_KEY_WORKFLOW, Autorenew::has_forced_workflow() );
  }

  /**
   * Shared end of every debounce window: compares the rule's current value (re-read live, not
   * trusted from whenever the window was scheduled) against its pre-window baseline, sweeps only
   * if they differ, and always clears the baseline transient to close out the window.
   *
   * @param  string $baseline_transient_key  One of the `BASELINE_KEY_*` constants.
   * @param  mixed  $current_value           The rule's current, freshly-read value.
   * @return void
   */
  private static function resolve_sweep_decision( $baseline_transient_key, $current_value ) {
    $baseline = get_transient( $baseline_transient_key );
    delete_transient( $baseline_transient_key );

    if ( is_array( $baseline ) && $baseline['value'] === $current_value ) {
      // Net no-op over the window (e.g. toggled on, then back off): nothing to sweep.
      return;
    }

    self::enqueue_sweep();
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
