<?php

namespace Wicket_Memberships;

use Wicket_Memberships\Helper;
use Wicket_Memberships\Utilities;
use Wicket_Memberships\Membership_Bundle;
use Wicket_Memberships\Wicket_Memberships;

/**
 * Daily cron handlers for membership bundle status transitions.
 *
 * Mirrors the structure of Membership_Controller's three daily hooks but operates
 * exclusively on wicket_mship_bundle posts. Individual/org membership cron remains
 * in Membership_Controller.
 *
 * Each handler queries for groups due for a status change, instantiates a
 * Membership_Bundle object per result, and delegates to transition_to() so lifecycle
 * guards, cascade to child memberships, and MDP sync are applied consistently.
 *
 * @package Wicket_Memberships
 */
class Membership_Bundle_Cron_Controller {

  public function __construct() {
    add_action( 'wp', [ $this, 'schedule_daily_bundle_grace_period' ], 10, 2 );
    add_action( 'schedule_daily_bundle_grace_period_hook', [ __NAMESPACE__ . '\\Membership_Bundle_Cron_Controller', 'daily_bundle_grace_period_hook' ] );

    add_action( 'wp', [ $this, 'schedule_daily_bundle_expiry' ], 10, 2 );
    add_action( 'schedule_daily_bundle_expiry_hook', [ __NAMESPACE__ . '\\Membership_Bundle_Cron_Controller', 'daily_bundle_expiry_hook' ] );

    add_action( 'wp', [ $this, 'schedule_daily_bundle_activation' ], 10, 2 );
    add_action( 'schedule_daily_bundle_activation_hook', [ __NAMESPACE__ . '\\Membership_Bundle_Cron_Controller', 'daily_bundle_activation_hook' ] );

    // Date trigger job handlers — AutomateWoo hook entry points.
    add_action( 'wicket_bundle_early_renew_at', [ __NAMESPACE__ . '\\Membership_Bundle_Cron_Controller', 'catch_bundle_early_renew_at' ], 10, 1 );
    add_action( 'wicket_bundle_ends_at',        [ __NAMESPACE__ . '\\Membership_Bundle_Cron_Controller', 'catch_bundle_ends_at' ],        10, 1 );
    add_action( 'wicket_bundle_expires_at',     [ __NAMESPACE__ . '\\Membership_Bundle_Cron_Controller', 'catch_bundle_expires_at' ],     10, 1 );

    // Renewal batch processor — dispatched by handle_bundle_renewal() via Action Scheduler.
    add_action( 'wicket_bundle_renewal_process_members', [ __NAMESPACE__ . '\\Membership_Bundle_Cron_Controller', 'process_bundle_renewal_members' ], 10, 5 );

    // Early renewal transition — cancel old bundle + activate new bundle at new term start date.
    add_action( 'wicket_bundle_cancel_old_on_new_starts_at', [ __NAMESPACE__ . '\\Membership_Bundle_Cron_Controller', 'cancel_old_bundle_on_new_starts_at' ], 10, 2 );
  }

  // ---------------------------------------------------------------------------
  // Action Scheduler registrations
  // ---------------------------------------------------------------------------

  public static function schedule_daily_bundle_grace_period(): void {
    if ( ! as_next_scheduled_action( 'schedule_daily_bundle_grace_period_hook' ) ) {
      $next_run_time = new \DateTime( 'tomorrow', wp_timezone() );
      as_schedule_recurring_action( $next_run_time->getTimestamp(), DAY_IN_SECONDS, 'schedule_daily_bundle_grace_period_hook', [], 'wicket-memberships' );
    }
  }

  public static function schedule_daily_bundle_expiry(): void {
    if ( ! as_next_scheduled_action( 'schedule_daily_bundle_expiry_hook' ) ) {
      $next_run_time = new \DateTime( 'tomorrow', wp_timezone() );
      as_schedule_recurring_action( $next_run_time->getTimestamp(), DAY_IN_SECONDS, 'schedule_daily_bundle_expiry_hook', [], 'wicket-memberships' );
    }
  }

  public static function schedule_daily_bundle_activation(): void {
    if ( ! as_next_scheduled_action( 'schedule_daily_bundle_activation_hook' ) ) {
      $next_run_time = new \DateTime( 'tomorrow', wp_timezone() );
      as_schedule_recurring_action( $next_run_time->getTimestamp(), DAY_IN_SECONDS, 'schedule_daily_bundle_activation_hook', [], 'wicket-memberships' );
    }
  }

  // ---------------------------------------------------------------------------
  // Hook handlers
  // ---------------------------------------------------------------------------

  /**
   * Transition active membership bundles to grace-period when membership_ends_at has passed.
   *
   * @return int Number of groups processed.
   */
  public static function daily_bundle_grace_period_hook(): int {
    $bundles_updated      = [];
    $yesterday_utc      = gmdate( 'Y-m-d\TH:i:sP', current_time( 'timestamp' ) - DAY_IN_SECONDS );
    $membership_ends_at = $yesterday_utc;

    $args = [
      'post_type'      => Helper::get_membership_bundle_cpt_slug(),
      'post_status'    => 'publish',
      'posts_per_page' => -1,
      'meta_query'     => [
        [
          'key'     => 'membership_status',
          'value'   => Wicket_Memberships::STATUS_ACTIVE,
          'compare' => '=',
        ],
        [
          'key'     => 'membership_ends_at',
          'value'   => $membership_ends_at,
          'compare' => '<',
          'type'    => 'CHAR',
        ],
      ],
    ];

    $bundles = get_posts( $args );
    foreach ( $bundles as $bundle_post ) {
      $bundle  = new Membership_Bundle( $bundle_post->ID );
      $result = $bundle->transition_to( Wicket_Memberships::STATUS_GRACE );
      if ( false === $result ) {
        Utilities::wc_log_mship_error( [ 'daily_bundle_grace_period_hook: transition failed', $bundle_post->ID ] );
      } else {
        $bundles_updated[] = [ $bundle_post->ID, $bundle_post->membership_status, $bundle_post->membership_ends_at ];
      }
    }

    Utilities::wc_log_mship_error( [ 'daily_bundle_grace_period_hook', $membership_ends_at, $bundles_updated ] );
    return count( $bundles );
  }

  /**
   * Transition active/grace-period membership bundles to expired when membership_expires_at has passed.
   *
   * @return int Number of groups processed.
   */
  public static function daily_bundle_expiry_hook(): int {
    $bundles_updated        = [];
    $yesterday_utc         = gmdate( 'Y-m-d\TH:i:sP', current_time( 'timestamp' ) - DAY_IN_SECONDS );
    $membership_expires_at = $yesterday_utc;

    $args = [
      'post_type'      => Helper::get_membership_bundle_cpt_slug(),
      'post_status'    => 'publish',
      'posts_per_page' => -1,
      'meta_query'     => [
        'relation' => 'AND',
        [
          'relation' => 'OR',
          [
            'key'     => 'membership_status',
            'value'   => Wicket_Memberships::STATUS_ACTIVE,
            'compare' => '=',
          ],
          [
            'key'     => 'membership_status',
            'value'   => Wicket_Memberships::STATUS_GRACE,
            'compare' => '=',
          ],
        ],
        [
          'key'     => 'membership_expires_at',
          'value'   => $membership_expires_at,
          'compare' => '<',
          'type'    => 'CHAR',
        ],
      ],
    ];

    $bundles = get_posts( $args );
    foreach ( $bundles as $bundle_post ) {
      $bundle  = new Membership_Bundle( $bundle_post->ID );
      $result = $bundle->transition_to( Wicket_Memberships::STATUS_EXPIRED );
      if ( false === $result ) {
        Utilities::wc_log_mship_error( [ 'daily_bundle_expiry_hook: transition failed', $bundle_post->ID ] );
      } else {
        $bundles_updated[] = [ $bundle_post->ID, $bundle_post->membership_status, $bundle_post->membership_expires_at ];
      }
    }

    Utilities::wc_log_mship_error( [ 'daily_bundle_expiry_hook', $membership_expires_at, $bundles_updated ] );
    return count( $bundles );
  }

  /**
   * Transition delayed membership bundles to active when membership_starts_at has passed.
   *
   * @return int Number of groups processed.
   */
  public static function daily_bundle_activation_hook(): int {
    $bundles_updated       = [];
    $yesterday_utc        = gmdate( 'Y-m-d\TH:i:sP', current_time( 'timestamp' ) - DAY_IN_SECONDS );
    $membership_starts_at = $yesterday_utc;

    $args = [
      'post_type'      => Helper::get_membership_bundle_cpt_slug(),
      'post_status'    => 'publish',
      'posts_per_page' => -1,
      'meta_query'     => [
        [
          'key'     => 'membership_status',
          'value'   => Wicket_Memberships::STATUS_DELAYED,
          'compare' => '=',
        ],
        [
          'key'     => 'membership_starts_at',
          'value'   => $membership_starts_at,
          'compare' => '<',
          'type'    => 'CHAR',
        ],
      ],
    ];

    $bundles = get_posts( $args );
    foreach ( $bundles as $bundle_post ) {
      $bundle  = new Membership_Bundle( $bundle_post->ID );
      $result = $bundle->transition_to( Wicket_Memberships::STATUS_ACTIVE );
      if ( false === $result ) {
        Utilities::wc_log_mship_error( [ 'daily_bundle_activation_hook: transition failed', $bundle_post->ID ] );
      } else {
        $bundles_updated[] = [ $bundle_post->ID, $bundle_post->membership_status, $bundle_post->membership_starts_at ];
      }
    }

    Utilities::wc_log_mship_error( [ 'daily_bundle_activation_hook', $membership_starts_at, $bundles_updated ] );
    return count( $bundles );
  }

  // ---------------------------------------------------------------------------
  // Date trigger job handlers — fire do_action hooks for AutomateWoo triggers.
  // No status transitions here; status is owned by the daily cron handlers above.
  // ---------------------------------------------------------------------------

  public static function catch_bundle_early_renew_at( int $bundle_post_id ): void {
    do_action( 'wicket_memberships_bundle_renewal_period_open', $bundle_post_id );
  }

  public static function catch_bundle_ends_at( int $bundle_post_id ): void {
    do_action( 'wicket_memberships_bundle_end_date_reached', $bundle_post_id );
  }

  public static function catch_bundle_expires_at( int $bundle_post_id ): void {
    do_action( 'wicket_memberships_bundle_grace_period_expired', $bundle_post_id );
  }

  // ---------------------------------------------------------------------------
  // Renewal batch processor
  // ---------------------------------------------------------------------------

  /**
   * Process one batch of individual member provisioning for a bundle renewal.
   *
   * Dispatched by handle_bundle_renewal() via Action Scheduler. Each invocation
   * processes up to $batch_size line items from the renewal order, creates new
   * individual membership records on the new bundle, and self-dispatches the next
   * batch when more remain. Action Scheduler enforces ~30 s per job; batch size 25
   * keeps each run well within that window.
   *
   * MDP note: add_member() calls create_membership_record() with is_renewal=true,
   * which sets processing_renewal=true on Membership_Controller so the MDP create
   * call is skipped during bundle-level ops — MDP handles the org at the bundle level.
   *
   * @param int $old_bundle_post_id Bundle post that was cancelled in step 4.
   * @param int $new_bundle_post_id Newly created bundle post for this renewal term.
   * @param int $renewal_order_id   WC order ID whose line items define eligible members.
   * @param int $offset             Line-item offset to start this batch from.
   * @param int $batch_size         Number of line items to process per batch.
   */
  public static function process_bundle_renewal_members(
    int $old_bundle_post_id,
    int $new_bundle_post_id,
    int $renewal_order_id,
    int $offset,
    int $batch_size
  ): void {
    $order = wc_get_order( $renewal_order_id );
    if ( ! $order ) {
      Utilities::wc_log_mship_error( [ 'process_bundle_renewal_members: renewal order not found', [
        'renewal_order_id'   => $renewal_order_id,
        'old_bundle_post_id' => $old_bundle_post_id,
        'new_bundle_post_id' => $new_bundle_post_id,
      ] ] );
      return;
    }

    $new_bundle = new Membership_Bundle( $new_bundle_post_id );

    // Anchor new individual memberships to the new bundle's starts_at — not to the
    // current timestamp. The batch job may run seconds or minutes after the renewal order;
    // without this override each member would get a slightly different start time.
    $new_bundle_dates      = $new_bundle->get_dates();
    $new_bundle_starts_at  = $new_bundle_dates['starts_at'] ?? null;

    // Collect eligible items — those with _membership_post_id set.
    // This mirrors the count logic in handle_bundle_renewal() and is authoritative.
    $eligible_items = [];
    foreach ( $order->get_items() as $item ) {
      $membership_post_id = (int) wc_get_order_item_meta( $item->get_id(), '_membership_post_id', true );
      if ( $membership_post_id > 0 ) {
        $eligible_items[] = [
          'item'               => $item,
          'membership_post_id' => $membership_post_id,
        ];
      }
    }

    $batch = array_slice( $eligible_items, $offset, $batch_size );

    $processed = 0;
    $errors     = [];

    foreach ( $batch as $entry ) {
      $old_membership_post_id = $entry['membership_post_id'];

      // Resolve user_id, tier, and product from the old membership post meta.
      // Keys match what create_local_membership_record() writes to post meta.
      $user_id      = (int) get_post_meta( $old_membership_post_id, 'user_id',               true );
      $tier_post_id = (int) get_post_meta( $old_membership_post_id, 'membership_tier_post_id', true );
      $product_id   = (int) get_post_meta( $old_membership_post_id, 'membership_product_id',  true ) ?: null;
      $variation_id = null; // Not stored as separate meta — product_id already reflects variation when applicable.

      if ( ! $user_id || ! $tier_post_id ) {
        Utilities::wc_log_mship_error( [ 'process_bundle_renewal_members: skipping item, missing user or tier', [
          'old_membership_post_id' => $old_membership_post_id,
          'new_bundle_post_id'     => $new_bundle_post_id,
        ] ] );
        $errors[] = $old_membership_post_id;
        continue;
      }

      // add_member() with is_renewal=true skips MDP create and subscription line item
      // creation. start_date_override anchors all new memberships to the bundle's
      // starts_at — not the current timestamp when the AS job happens to run.
      $result = $new_bundle->add_member(
        $user_id,
        $tier_post_id,
        $product_id,
        $variation_id,
        null,                  // no existing membership to cancel — new term, fresh record
        true,                  // is_renewal
        $new_bundle_starts_at  // start_date_override
      );

      if ( is_wp_error( $result ) ) {
        Utilities::wc_log_mship_error( [ 'process_bundle_renewal_members: add_member failed', [
          'user_id'                => $user_id,
          'tier_post_id'           => $tier_post_id,
          'old_membership_post_id' => $old_membership_post_id,
          'new_bundle_post_id'     => $new_bundle_post_id,
          'error'                  => $result->get_error_message(),
        ] ] );
        $errors[] = $old_membership_post_id;
      } else {
        $new_membership_post_id = $result;

        // Update the existing subscription line item in-place — swap _membership_post_id
        // from the old membership post ID to the new one. The subscription is shared across
        // renewals so we never add or remove line items here; only the pointer changes.
        // This prevents duplicate line items building up across renewal terms.
        if ( function_exists( 'wcs_get_subscription' ) ) {
          $sub_id = (int) get_post_meta( $new_bundle_post_id, 'membership_subscription_id', true );
          $sub    = $sub_id ? wcs_get_subscription( $sub_id ) : null;
          if ( $sub ) {
            foreach ( $sub->get_items() as $item ) {
              if ( (int) $item->get_meta( '_membership_post_id' ) === $old_membership_post_id ) {
                $item->update_meta_data( '_membership_post_id', $new_membership_post_id );
                $item->save();
                break;
              }
            }
          }
        }

        $processed++;
      }
    }

    $next_offset = $offset + $batch_size;
    $has_more    = $next_offset < count( $eligible_items );

    if ( $has_more ) {
      // ==========================================================================
      // DEBUG PAUSE MODE — FOR DEBUGGING ONLY, NOT FOR PRODUCTION
      //
      // When WICKET_MSHIP_BUNDLE_RENEWAL_DEBUG_PAUSE is set, each subsequent batch
      // schedules 24 hours out instead of immediately. This pauses the pipeline
      // between batches so you can inspect intermediate state.
      //
      // To advance to the next batch:
      //   WP Admin → Tools → Scheduled Actions → find "wicket_bundle_renewal_process_members"
      //   with offset={next_offset} → click "Run".
      //
      // To disable: unset WICKET_MSHIP_BUNDLE_RENEWAL_DEBUG_PAUSE or set it to false/0.
      // ==========================================================================
      $debug_pause    = ! empty( $_ENV['WICKET_MSHIP_BUNDLE_RENEWAL_DEBUG_PAUSE'] );
      $next_run_time  = $debug_pause ? ( time() + DAY_IN_SECONDS ) : time();

      as_schedule_single_action(
        $next_run_time,
        'wicket_bundle_renewal_process_members',
        [
          'old_bundle_post_id' => $old_bundle_post_id,
          'new_bundle_post_id' => $new_bundle_post_id,
          'renewal_order_id'   => $renewal_order_id,
          'offset'             => $next_offset,
          'batch_size'         => $batch_size,
        ],
        'wicket-memberships',
        false
      );
    } else {
      // Final batch done — stamp completion on both bundle posts and add order note.
      $completion_stamp = current_time( 'c' );

      foreach ( [ $old_bundle_post_id, $new_bundle_post_id ] as $post_id ) {
        $meta = json_decode( get_post_meta( $post_id, 'membership_renewal_processing', true ), true ) ?: [];
        $meta['completed_at'] = $completion_stamp;
        $meta['errors']       = $errors;
        update_post_meta( $post_id, 'membership_renewal_processing', wp_json_encode( $meta ) );
      }

      // Write completion note to both order and subscription — mirrors individual membership
      // pattern in scheduler_dates_for_expiry() which writes the same note to both.
      $completion_note = sprintf(
        'Membership bundle renewal complete. %d member(s) provisioned on new bundle #%d. Errors: %d.',
        $processed,
        $new_bundle_post_id,
        count( $errors )
      );

      $wc_order = wc_get_order( $renewal_order_id );
      if ( $wc_order ) {
        $wc_order->add_order_note( $completion_note );
      }

      $sub_id = (int) get_post_meta( $new_bundle_post_id, 'membership_subscription_id', true );
      if ( $sub_id && function_exists( 'wcs_get_subscription' ) ) {
        $sub = wcs_get_subscription( $sub_id );
        if ( $sub ) {
          $sub->add_order_note( $completion_note );
        }
      }

      do_action( 'wicket_memberships_bundle_renewal_complete', $new_bundle_post_id, $old_bundle_post_id, $renewal_order_id );
    }

    Utilities::wc_log_mship_error( [ 'process_bundle_renewal_members: batch done', [
      'old_bundle_post_id' => $old_bundle_post_id,
      'new_bundle_post_id' => $new_bundle_post_id,
      'offset'             => $offset,
      'processed'          => $processed,
      'errors'             => $errors,
      'has_more'           => $has_more,
    ] ] );
  }

  // ---------------------------------------------------------------------------
  // Post-renewal old bundle cancellation
  // ---------------------------------------------------------------------------

  /**
   * Cancel the old bundle after all renewal members have been provisioned.
   *
   * Hooked to wicket_memberships_bundle_renewal_complete which fires at the end of the
   * final renewal batch. Deferring cancellation to here (rather than in handle_bundle_renewal)
   * ensures the old bundle's child memberships are still active when the batch handler reads
   * their meta — preventing check_local_membership_record_exists() from overwriting records.
   *
   * For early renewals (new term starts in the future) this cancels the old bundle
   * immediately after provisioning. The new bundle remains delayed until its start date,
   * at which point wicket_bundle_cancel_old_on_new_starts_at would normally fire — but
   * since we cancel here first, that AS job is a no-op (old bundle already cancelled).
   *
   * @param int $new_bundle_post_id Newly provisioned bundle post ID.
   * @param int $old_bundle_post_id Bundle post that was superseded by the renewal.
   * @param int $renewal_order_id   WC order ID that triggered the renewal.
   */
  public static function cancel_old_bundle_after_renewal(
    int $new_bundle_post_id,
    int $old_bundle_post_id,
    int $renewal_order_id
  ): void {
    // Early renewals: new term starts in the future. Old bundle must stay active until then.
    // wicket_bundle_cancel_old_on_new_starts_at is already scheduled at the new start date.
    // Skip here — cancelling now would end-date an active bundle prematurely.
    $processing_meta = json_decode( get_post_meta( $old_bundle_post_id, 'membership_renewal_processing', true ), true ) ?: [];
    if ( ! empty( $processing_meta['is_early_renewal'] ) ) {
      Utilities::wc_log_mship_error( [ 'cancel_old_bundle_after_renewal: skipping — early renewal, deferred to new starts_at', [
        'old_bundle_post_id' => $old_bundle_post_id,
        'new_bundle_post_id' => $new_bundle_post_id,
        'renewal_order_id'   => $renewal_order_id,
      ] ] );
      return;
    }

    $old_bundle = new Membership_Bundle( $old_bundle_post_id );
    $new_bundle = new Membership_Bundle( $new_bundle_post_id );

    // cancel_for_renewal() marks the bundle cancelled without cascading to child memberships.
    // Child members are historical records of the old term — they must stay in their current
    // status so the per-bundle member count remains accurate after renewal.
    $old_bundle->cancel_for_renewal();

    // Activate the new bundle — cascades active status to its child memberships.
    // For same-day/grace renewals the new bundle starts as pending; this is the activation step.
    // (Early renewals are activated by cancel_old_bundle_on_new_starts_at() instead.)
    $new_bundle->transition_to( Wicket_Memberships::STATUS_ACTIVE );

    Utilities::wc_log_mship_error( [ 'cancel_old_bundle_after_renewal: old bundle cancelled, new bundle activated', [
      'old_bundle_post_id' => $old_bundle_post_id,
      'new_bundle_post_id' => $new_bundle_post_id,
      'renewal_order_id'   => $renewal_order_id,
    ] ] );
  }

  // ---------------------------------------------------------------------------
  // Early renewal transition handler
  // ---------------------------------------------------------------------------

  /**
   * Cancel the old bundle and activate the new bundle at the start of the new term.
   *
   * Scheduled by handle_bundle_renewal() via Action Scheduler when a renewal order
   * is processed before the old bundle's term has ended (early renewal). Fires at
   * $new_starts_at_ts so the old bundle stays active until the new term begins.
   *
   * Mirrors expire_old_membership_on_new_starts_at() for individual memberships but
   * uses transition_to() so cascade to child memberships and MDP sync are applied.
   *
   * Old WC subscription is already cancelled by handle_bundle_renewal() at renewal
   * time — no further subscription work needed here.
   *
   * @param int $old_bundle_post_id Bundle post that was superseded by the renewal.
   * @param int $new_bundle_post_id Newly created bundle post for the new term.
   */
  public static function cancel_old_bundle_on_new_starts_at(
    int $old_bundle_post_id,
    int $new_bundle_post_id
  ): void {
    $old_bundle = new Membership_Bundle( $old_bundle_post_id );
    $new_bundle = new Membership_Bundle( $new_bundle_post_id );

    // cancel_for_renewal(true) marks the bundle cancelled without cascading to child memberships,
    // and preserves ends_at so the record reflects the full term that was paid for.
    // This fires at new term start — old bundle's paid period has only just ended.
    $old_result = $old_bundle->cancel_for_renewal( true );

    // Activate new bundle — cascades active status to child memberships.
    $new_result = $new_bundle->transition_to( Wicket_Memberships::STATUS_ACTIVE );

    Utilities::wc_log_mship_error( [ 'cancel_old_bundle_on_new_starts_at: done', [
      'old_bundle_post_id' => $old_bundle_post_id,
      'new_bundle_post_id' => $new_bundle_post_id,
      'old_result'         => $old_result,
      'new_result'         => $new_result,
    ] ] );
  }

}
