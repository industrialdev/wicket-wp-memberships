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

}
