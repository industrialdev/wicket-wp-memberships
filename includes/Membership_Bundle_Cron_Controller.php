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

    // Client-specific extension point: per-member price/fee adjustment on the actual
    // renewal order WCS bills the customer on (distinct from this plugin's own batch
    // cron, which re-provisions membership records on a decoupled cadence).
    add_filter( 'wcs_renewal_order_created', [ __NAMESPACE__ . '\\Membership_Bundle_Cron_Controller', 'apply_bundle_renewal_line_item_price_filter' ], 10, 2 );

    // Refreshes stale line-item identity meta on renewal. wcs_renewal_order_items is
    // renewal-specific (unlike wcs_new_order_items), so this never fires for a resubscribe.
    add_filter( 'wcs_renewal_order_items', [ __NAMESPACE__ . '\\Membership_Bundle_Cron_Controller', 'refresh_bundle_renewal_line_item_meta' ], 10, 3 );
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
        $bundles_updated[] = [ $bundle_post->ID, $bundle->get_membership_status(), $bundle->get_dates()['starts_at'] ];
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
   * Resolve the tier/product a renewing member's sequential_logic tier succeeds to.
   * Non-sequential_logic tiers pass through unchanged. Callable from both the
   * post-payment batch cron and the pre-payment repricing phase — takes no
   * new-bundle-post argument since that post doesn't exist yet at the earlier point.
   *
   * @return array{tier_post_id: int, product_id: int|null, variation_id: int|null, decision_source: 'unchanged'|'sequential_logic'}|\WP_Error
   */
  public static function resolve_sequential_logic_succession( int $tier_post_id, ?int $product_id, int $old_membership_post_id ): array|\WP_Error {
    $old_tier = new Membership_Tier( $tier_post_id );
    if ( $old_tier->get_tier_renewal_type() !== 'sequential_logic' ) {
      return [ 'tier_post_id' => $tier_post_id, 'product_id' => $product_id, 'variation_id' => null, 'decision_source' => 'unchanged' ];
    }

    $next_tier_id = $old_tier->get_next_tier_id();
    if ( $next_tier_id === false ) {
      Utilities::wc_log_mship_error( [ 'resolve_sequential_logic_succession: sequential_logic tier has no next_tier_id configured', [
        'old_membership_post_id' => $old_membership_post_id,
        'tier_post_id'           => $tier_post_id,
      ] ] );
      return new \WP_Error( 'no_next_tier', 'sequential_logic tier has no next_tier_id configured.' );
    }

    $next_tier = new Membership_Tier( $next_tier_id );
    $next_tier_products = $next_tier->get_products_data();
    if ( empty( $next_tier_products ) ) {
      Utilities::wc_log_mship_error( [ 'resolve_sequential_logic_succession: next tier has no products configured', [
        'old_membership_post_id' => $old_membership_post_id,
        'tier_post_id'           => $tier_post_id,
        'next_tier_id'           => $next_tier_id,
      ] ] );
      return new \WP_Error( 'no_next_tier_product', 'sequential_logic next tier has no products configured.' );
    }

    // First product, preferring the variation over the parent product — matches
    // Import_Controller::create_bundle_member()'s ambiguous_product handling. A next
    // tier configured with more than one product is not an expected configuration;
    // this deterministic pick exists for correctness/safety, mirroring the import
    // precedent, not because multi-product next tiers are a real scenario to support.
    $variation_id = ! empty( $next_tier_products[0]['variation_id'] ) ? (int) $next_tier_products[0]['variation_id'] : null;

    return [
      'tier_post_id'     => $next_tier_id,
      'product_id'       => (int) $next_tier_products[0]['product_id'],
      'variation_id'     => $variation_id,
      'decision_source'  => 'sequential_logic',
    ];
  }

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

    // Stamp current offset into the processing meta so the frontend polling sees
    // progress after each batch, not just at the end. Errors will be appended
    // after the batch loop runs — see the post-loop meta update below.
    foreach ( [ $old_bundle_post_id, $new_bundle_post_id ] as $post_id ) {
      $pm = json_decode( get_post_meta( $post_id, 'membership_renewal_processing', true ), true ) ?: [];
      $pm['offset'] = $offset;
      update_post_meta( $post_id, 'membership_renewal_processing', wp_json_encode( $pm ) );
    }

    $processed = 0;
    $errors     = [];

    foreach ( $batch as $entry ) {
      $old_membership_post_id = $entry['membership_post_id'];
      $item                   = $entry['item'];

      $user_id = (int) get_post_meta( $old_membership_post_id, 'user_id', true );

      if ( ! $user_id ) {
        Utilities::wc_log_mship_error( [ 'process_bundle_renewal_members: skipping item, missing user', [
          'old_membership_post_id' => $old_membership_post_id,
          'new_bundle_post_id'     => $new_bundle_post_id,
        ] ] );
        $errors[] = $old_membership_post_id;
        continue;
      }

      // Read the tier reprice_bundle_renewal_line_item() already decided and charged
      // the customer for at order-creation time — this must never re-resolve after
      // payment, or the record could disagree with what was actually billed. Product/
      // variation come straight off the item, which set_product() already set correctly.
      $tier_post_id = (int) $item->get_meta( '_wicket_bundle_renewal_resolved_tier_post_id' );
      $product_id   = (int) $item->get_product_id();
      $variation_id = (int) $item->get_variation_id() ?: null;

      // resolved_tier_post_id is written on every successful repricing (unchanged or
      // not) — its absence here means repricing never completed for this member (e.g.
      // a renewal order created before this stamp existed, or reprice_bundle_renewal_
      // line_item() failed and the order was held). Only fall back to the old
      // membership's own tier/product when that tier was never expected to produce a
      // new decision in the first place (current_tier/form_flow) — a sequential_logic
      // tier's absence of a decision means its own succession genuinely failed (e.g. a
      // misconfigured next tier), and must hard-fail rather than silently renew at the
      // outgoing tier as if nothing was supposed to change.
      if ( ! $tier_post_id ) {
        $old_tier_post_id   = (int) get_post_meta( $old_membership_post_id, 'membership_tier_post_id', true );
        $old_renewal_type   = $old_tier_post_id ? ( new Membership_Tier( $old_tier_post_id ) )->get_tier_renewal_type() : null;

        if ( $old_renewal_type !== 'sequential_logic' ) {
          $tier_post_id = $old_tier_post_id;
          $product_id   = (int) get_post_meta( $old_membership_post_id, 'membership_product_id', true );
          $variation_id = null;
        }
      }

      if ( ! $tier_post_id || ! $product_id ) {
        Utilities::wc_log_mship_error( [ 'process_bundle_renewal_members: missing charge-decision meta on renewal item and old membership', [
          'old_membership_post_id' => $old_membership_post_id,
          'item_id'                => $item->get_id(),
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
        //
        // Other line-item meta (extra_meta filter, _member_name) is refreshed separately
        // in refresh_bundle_renewal_line_item_meta(), not here — this runs before the new
        // term's member is known, so it would read the outgoing member's data.
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

    // Persist any errors from this batch into processing meta immediately so that
    // subsequent batches and the final completion block see the full error history.
    if ( ! empty( $errors ) ) {
      foreach ( [ $old_bundle_post_id, $new_bundle_post_id ] as $post_id ) {
        $pm               = json_decode( get_post_meta( $post_id, 'membership_renewal_processing', true ), true ) ?: [];
        $pm['errors']     = array_merge( $pm['errors'] ?? [], $errors );
        update_post_meta( $post_id, 'membership_renewal_processing', wp_json_encode( $pm ) );
      }
    }

    $next_offset = $offset + $batch_size;
    $has_more    = $next_offset < count( $eligible_items );

    if ( $has_more ) {
      as_schedule_single_action(
        time(),
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
        // Merge prior-batch errors (already in meta) with this batch's errors so the
        // completion note reflects ALL failures, not just the final batch.
        $prior_errors         = $meta['errors'] ?? [];
        $all_errors           = array_merge( $prior_errors, $errors );
        $meta['completed_at'] = $completion_stamp;
        $meta['errors']       = $all_errors;
        update_post_meta( $post_id, 'membership_renewal_processing', wp_json_encode( $meta ) );
      }

      // Write completion note to both order and subscription — mirrors individual membership
      // pattern in scheduler_dates_for_expiry() which writes the same note to both.
      // Use total_members from processing meta for the count — $processed is only this batch.
      $total_provisioned = (int) ( $meta['total_members'] ?? 0 ) - \count( $all_errors );
      $completion_note = sprintf(
        'Membership bundle renewal complete. %d member(s) provisioned on new bundle #%d. Errors: %d.',
        $total_provisioned,
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
  // Per-member renewal-order price/fee extension point
  // ---------------------------------------------------------------------------

  /**
   * Fire wicket_mship_bundle_renewal_line_item_price once per member/line-item as a
   * bundle's renewal order is built, then recalculate totals once for the whole order.
   *
   * Hooked to WCS's native `wcs_renewal_order_created` filter (always return
   * $renewal_order — WCS requires a WC_Order back). Fires on WCS's own per-subscription
   * renewal schedule, not this plugin's process_bundle_renewal_members() batch cron,
   * which re-provisions membership records on a decoupled cadence and would not
   * reliably affect the order actually being charged.
   *
   * The filter's return value is discarded; a callback communicates any change (price
   * adjustment, added fee/product line, whole-order effect) by mutating
   * $item/$renewal_order directly via the normal WC API.
   *
   * @param \WC_Order        $renewal_order The freshly created renewal order.
   * @param \WC_Subscription $subscription  The subscription the renewal is related to.
   * @return \WC_Order The same renewal order.
   */
  public static function apply_bundle_renewal_line_item_price_filter( $renewal_order, $subscription ) {
    if ( ! $renewal_order instanceof \WC_Order ) {
      return $renewal_order;
    }

    // Scope to bundle subscriptions only — a subscription is linked to a bundle when some
    // bundle post's membership_subscription_id meta points back to it.
    $bundle_posts = get_posts( [
      'post_type'      => Helper::get_membership_bundle_cpt_slug(),
      'post_status'    => 'any',
      'posts_per_page' => 1,
      'fields'         => 'ids',
      'meta_query'     => [
        [ 'key' => 'membership_subscription_id', 'value' => $subscription->get_id() ],
      ],
    ] );

    if ( empty( $bundle_posts ) ) {
      return $renewal_order;
    }

    $old_bundle_post_id = (int) $bundle_posts[0];
    $reprice_failures   = [];

    foreach ( $renewal_order->get_items() as $item_id => $item ) {
      $membership_post_id = (int) wc_get_order_item_meta( $item_id, '_membership_post_id', true );
      if ( ! $membership_post_id ) {
        continue;
      }
      $user_id = (int) get_post_meta( $membership_post_id, 'user_id', true );

      // Backstops refresh_bundle_renewal_line_item_meta() regardless of WCS's meta copy.
      $user = $user_id ? get_user_by( 'id', $user_id ) : false;
      if ( $user && '' === (string) $item->get_meta( '_member_name' ) ) {
        $item->update_meta_data( '_member_name', $user->display_name );
        $item->save();
      }

      // Reprice to the term ahead before Milestone 4's fee/discount filter runs.
      $reprice_result = self::reprice_bundle_renewal_line_item( $item, $item_id, $membership_post_id, $user_id, $old_bundle_post_id );
      if ( $reprice_result !== true ) {
        $reprice_failures[ $membership_post_id ] = $reprice_result;
      }

      $original_product_price = (string) $item->get_total();

      try {
        // The callback mutates $item and/or $renewal_order directly — e.g.
        // $item->set_total()/set_subtotal() for a price adjustment,
        // $renewal_order->add_fee()/add_product() for a separate line. The return
        // value is intentionally discarded.
        apply_filters(
          'wicket_mship_bundle_renewal_line_item_price',
          null,
          $item,
          $item_id,
          $membership_post_id,
          $user_id,
          $renewal_order
        );

        // Only a direct set_total()/set_subtotal() override is worth recording here —
        // not a discount/coupon (WC already tracks those natively) and not a product
        // swap (already explained by the tier/product decision meta above). Compare
        // against the product's own price before this filter ran, since WC applies
        // discounts later via its own line-item fields, not by mutating this total.
        // The item's own get_total() is the current/final price — no need to duplicate it.
        if ( (string) $item->get_total() !== $original_product_price ) {
          $item->update_meta_data( '_wicket_bundle_renewal_original_product_price', $original_product_price );
          // Mirrors _wicket_bundle_renewal_decision_source (tier/product): names what
          // changed the price, distinct from what changed the tier/product. Only one
          // mechanism can force a raw price override, so this is always 'filter_override'.
          $item->update_meta_data( '_wicket_bundle_renewal_price_decision_source', 'filter_override' );
        }

        $item->save();
      } catch ( \Throwable $e ) {
        // A single member's callback failing (e.g. an external lookup throwing) must not
        // abort processing for the rest of the order's members, and must not skip the
        // calculate_totals() call below.
        Utilities::wc_log_mship_error( [ 'wicket_mship_bundle_renewal_line_item_price filter failed', [
          'item_id'            => $item_id,
          'membership_post_id' => $membership_post_id,
          'error'              => $e->getMessage(),
        ] ] );
      }
    }

    $renewal_order->calculate_totals();

    if ( ! empty( $reprice_failures ) ) {
      $note = 'Bundle renewal repricing failed for member(s): ' . implode( ', ', array_map(
        static fn( $post_id, $code ) => "#{$post_id} ({$code})",
        array_keys( $reprice_failures ),
        $reprice_failures
      ) );
      $renewal_order->update_status( 'on-hold', $note );
      Utilities::wc_log_mship_error( [ 'apply_bundle_renewal_line_item_price_filter: repricing failures, order held', [
        'renewal_order_id' => $renewal_order->get_id(),
        'failures'          => $reprice_failures,
      ] ] );
    }

    return $renewal_order;
  }

  /**
   * Resolve and apply the term-ahead tier/product/price to one member's renewal
   * line item, before the price/fee filter runs. Fires
   * wicket_mship_bundle_renewal_charge_tier_product for a client override, validated
   * against the target tier and failed closed to the resolved default on mismatch.
   *
   * @param \WC_Order_Item_Product $item
   * @return true|string true on success, else an error code for the caller to collect.
   */
  private static function reprice_bundle_renewal_line_item( $item, int $item_id, int $membership_post_id, int $user_id, int $old_bundle_post_id ) {
    $tier_post_id = (int) get_post_meta( $membership_post_id, 'membership_tier_post_id', true );
    if ( ! $tier_post_id ) {
      Utilities::wc_log_mship_error( [ 'reprice_bundle_renewal_line_item: missing tier_post_id', [
        'item_id'            => $item_id,
        'membership_post_id' => $membership_post_id,
      ] ] );
      return 'missing_tier_post_id';
    }

    $stored_product_id = (int) get_post_meta( $membership_post_id, 'membership_product_id', true ) ?: null;

    // membership_product_id may hold a variation ID — disambiguate before use.
    $current_tier_variation_ids = array_map( 'intval', ( new Membership_Tier( $tier_post_id ) )->get_product_variation_ids() );
    $product_id = ( $stored_product_id !== null && \in_array( $stored_product_id, $current_tier_variation_ids, true ) )
      ? null
      : $stored_product_id;

    $previous_tier_post_id = $tier_post_id;
    $previous_product_id   = $stored_product_id;

    $resolved = self::resolve_sequential_logic_succession( $tier_post_id, $product_id, $membership_post_id );
    if ( is_wp_error( $resolved ) ) {
      return $resolved->get_error_code();
    }

    // Default (null) means "no override — the resolution above stands."
    $override = apply_filters(
      'wicket_mship_bundle_renewal_charge_tier_product',
      null,
      $membership_post_id,
      $user_id,
      $old_bundle_post_id,
      $resolved
    );

    if ( is_array( $override ) ) {
      $validated = self::validate_charge_tier_product_override( $override );
      if ( $validated === null ) {
        Utilities::wc_log_mship_error( [ 'reprice_bundle_renewal_line_item: invalid wicket_mship_bundle_renewal_charge_tier_product override, falling back to core default', [
          'membership_post_id' => $membership_post_id,
          'override'           => $override,
        ] ] );
      } else {
        $resolved = $validated + [ 'decision_source' => 'filter_override' ];
      }
    }

    $product = wc_get_product( $resolved['variation_id'] ?? $resolved['product_id'] );
    if ( ! $product ) {
      Utilities::wc_log_mship_error( [ 'reprice_bundle_renewal_line_item: resolved product could not be loaded', [
        'item_id'            => $item_id,
        'membership_post_id' => $membership_post_id,
        'resolved'           => $resolved,
      ] ] );
      return 'product_not_found';
    }

    $item->set_product( $product );

    $price = (float) $product->get_price();
    $item->set_subtotal( (string) $price );
    $item->set_total( (string) $price );

    // Record which tier this charge belongs to, so process_bundle_renewal_members()
    // reads it back instead of re-deriving it post-payment — product/variation are
    // already on the item natively via set_product() above. Always written on success
    // (even when unchanged): its presence is process_bundle_renewal_members()'s only
    // signal that repricing actually completed, vs. failed outright (e.g. a
    // sequential_logic next tier with no products) — those two cases must not be
    // confused with each other, or a failed member could wrongly fall back to renewing
    // at their old tier/product instead of being skipped and logged as an error.
    $item->update_meta_data( '_wicket_bundle_renewal_resolved_tier_post_id', $resolved['tier_post_id'] );

    // The rest of the decision record is only worth writing when something actually
    // changed — an "unchanged" renewal has nothing more to say than the line above.
    if ( $resolved['decision_source'] !== 'unchanged' ) {
      $item->update_meta_data( '_wicket_bundle_renewal_decision_source', $resolved['decision_source'] );
      $item->update_meta_data( '_wicket_bundle_renewal_previous_tier_post_id', $previous_tier_post_id );
      $item->update_meta_data( '_wicket_bundle_renewal_previous_product_id', $previous_product_id ?? 0 );

      // ISO 8601 with offset (matches process_bundle_renewal_members()'s completed_at
      // stamp) — render with formatDateWithTooltip() wherever this surfaces in the
      // admin UI, per this plugin's date-display convention.
      $item->update_meta_data( '_wicket_bundle_renewal_decided_at', current_time( 'c' ) );
    }

    return true;
  }

  /**
   * Validate a charge_tier_product override against its claimed tier. Fails closed
   * (null) rather than trust a product the tier doesn't actually offer.
   *
   * @return array{tier_post_id: int, product_id: int, variation_id: int|null}|null
   */
  private static function validate_charge_tier_product_override( array $override ): ?array {
    $tier_post_id = (int) ( $override['tier_post_id'] ?? 0 );
    $product_id   = (int) ( $override['product_id'] ?? 0 );
    $variation_id = isset( $override['variation_id'] ) ? (int) $override['variation_id'] : null;

    if ( ! $tier_post_id || ! $product_id ) {
      return null;
    }

    $tier = new Membership_Tier( $tier_post_id );
    $tier_product_ids   = array_map( 'intval', $tier->get_product_ids() );
    $tier_variation_ids = array_map( 'intval', $tier->get_product_variation_ids() );

    // Normalise a variation ID returned in the product_id slot.
    if ( ! \in_array( $product_id, $tier_product_ids, true ) && \in_array( $product_id, $tier_variation_ids, true ) ) {
      $variation_id = $product_id;
      $product_id   = (int) $tier->get_product_ids()[0] ?? 0;
    }

    if ( ! \in_array( $product_id, $tier_product_ids, true ) ) {
      return null;
    }
    if ( $variation_id !== null && ! \in_array( $variation_id, $tier_variation_ids, true ) ) {
      return null;
    }

    return [ 'tier_post_id' => $tier_post_id, 'product_id' => $product_id, 'variation_id' => $variation_id ];
  }

  // ---------------------------------------------------------------------------
  // Per-member line-item meta refresh on renewal
  // ---------------------------------------------------------------------------

  /**
   * Refresh wicket_mship_bundle_line_item_extra_meta and _member_name on each bundle
   * member's subscription line item before WCS copies it to the renewal order.
   *
   * $items are the subscription's own items — wcs_copy_order_item() copies all their
   * meta (except _reduced_stock) onto the renewal order right after this filter runs,
   * so writing here reaches both objects. Unlike a price/tier write, this carries none
   * of Milestone 9's pre-payment risk: identity meta is correct whether or not the
   * order is ever paid.
   *
   * @param \WC_Order_Item[] $items        Subscription's own line items.
   * @param \WC_Order        $new_order    The renewal order being built.
   * @param \WC_Subscription $subscription The subscription the renewal is related to.
   * @return \WC_Order_Item[] The same $items array, mutated in place.
   */
  public static function refresh_bundle_renewal_line_item_meta( $items, $new_order, $subscription ) {
    if ( empty( $_ENV['WICKET_MSHIP_ENABLE_BUNDLES'] ) || ! is_array( $items ) ) {
      return $items;
    }

    // Scope to bundle subscriptions only — a subscription is linked to a bundle when some
    // bundle post's membership_subscription_id meta points back to it.
    $bundle_posts = get_posts( [
      'post_type'      => Helper::get_membership_bundle_cpt_slug(),
      'post_status'    => 'any',
      'posts_per_page' => 1,
      'fields'         => 'ids',
      'meta_query'     => [
        [ 'key' => 'membership_subscription_id', 'value' => $subscription->get_id() ],
      ],
    ] );

    if ( empty( $bundle_posts ) ) {
      return $items;
    }

    foreach ( $items as $item_id => $item ) {
      $membership_post_id = (int) $item->get_meta( '_membership_post_id' );
      if ( ! $membership_post_id ) {
        continue;
      }
      $user_id = (int) get_post_meta( $membership_post_id, 'user_id', true );
      $user    = $user_id ? get_user_by( 'id', $user_id ) : false;

      try {
        if ( $user ) {
          $item->update_meta_data( '_member_name', $user->display_name );
        }

        $product_id = (int) $item->get_product_id();

        // Same filter Milestone 1 fires at add-time; trailing true marks this a refresh.
        $extra_meta = apply_filters(
          'wicket_mship_bundle_line_item_extra_meta',
          [],
          $item_id,
          $user,
          $membership_post_id,
          $product_id,
          true
        );
        foreach ( $extra_meta as $meta_key => $meta_value ) {
          $item->update_meta_data( $meta_key, $meta_value );
        }

        $item->save();
      } catch ( \Throwable $e ) {
        Utilities::wc_log_mship_error( [ 'wicket_mship_bundle_line_item_extra_meta refresh failed', [
          'item_id'            => $item_id,
          'membership_post_id' => $membership_post_id,
          'error'              => $e->getMessage(),
        ] ] );
      }
    }

    return $items;
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

    // cancel_for_renewal() marks the bundle cancelled and cascades cancelled to all child
    // memberships so past-term seats are unambiguously terminal after renewal.
    $old_bundle->cancel_for_renewal();

    // Activate the new bundle — cascades active status to its child memberships.
    // For same-day/grace renewals the new bundle starts as delayed; this is the activation step.
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

    // cancel_for_renewal(true) marks the bundle cancelled, cascades cancelled to all child
    // memberships, and preserves ends_at so the record reflects the full term that was paid for.
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
