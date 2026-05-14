<?php

namespace Wicket_Memberships;

use Wicket_Memberships\Helper;
use Wicket_Memberships\Utilities;
use Wicket_Memberships\Membership_Group;
use Wicket_Memberships\Wicket_Memberships;

/**
 * Daily cron handlers for membership group status transitions.
 *
 * Mirrors the structure of Membership_Controller's three daily hooks but operates
 * exclusively on wicket_mship_group posts. Individual/org membership cron remains
 * in Membership_Controller.
 *
 * Each handler queries for groups due for a status change, instantiates a
 * Membership_Group object per result, and delegates to transition_to() so lifecycle
 * guards, cascade to child memberships, and MDP sync are applied consistently.
 *
 * @package Wicket_Memberships
 */
class Membership_Group_Cron_Controller {

  public function __construct() {
    add_action( 'wp', [ $this, 'schedule_daily_group_grace_period' ], 10, 2 );
    add_action( 'schedule_daily_group_grace_period_hook', [ __NAMESPACE__ . '\\Membership_Group_Cron_Controller', 'daily_group_grace_period_hook' ] );

    add_action( 'wp', [ $this, 'schedule_daily_group_expiry' ], 10, 2 );
    add_action( 'schedule_daily_group_expiry_hook', [ __NAMESPACE__ . '\\Membership_Group_Cron_Controller', 'daily_group_expiry_hook' ] );

    add_action( 'wp', [ $this, 'schedule_daily_group_activation' ], 10, 2 );
    add_action( 'schedule_daily_group_activation_hook', [ __NAMESPACE__ . '\\Membership_Group_Cron_Controller', 'daily_group_activation_hook' ] );

    // Date trigger job handlers — AutomateWoo hook entry points.
    add_action( 'wicket_group_early_renew_at', [ __NAMESPACE__ . '\\Membership_Group_Cron_Controller', 'catch_group_early_renew_at' ], 10, 1 );
    add_action( 'wicket_group_ends_at',        [ __NAMESPACE__ . '\\Membership_Group_Cron_Controller', 'catch_group_ends_at' ],        10, 1 );
    add_action( 'wicket_group_expires_at',     [ __NAMESPACE__ . '\\Membership_Group_Cron_Controller', 'catch_group_expires_at' ],     10, 1 );
  }

  // ---------------------------------------------------------------------------
  // Action Scheduler registrations
  // ---------------------------------------------------------------------------

  public static function schedule_daily_group_grace_period(): void {
    if ( ! as_next_scheduled_action( 'schedule_daily_group_grace_period_hook' ) ) {
      $next_run_time = new \DateTime( 'tomorrow', wp_timezone() );
      as_schedule_recurring_action( $next_run_time->getTimestamp(), DAY_IN_SECONDS, 'schedule_daily_group_grace_period_hook', [], 'wicket-memberships' );
    }
  }

  public static function schedule_daily_group_expiry(): void {
    if ( ! as_next_scheduled_action( 'schedule_daily_group_expiry_hook' ) ) {
      $next_run_time = new \DateTime( 'tomorrow', wp_timezone() );
      as_schedule_recurring_action( $next_run_time->getTimestamp(), DAY_IN_SECONDS, 'schedule_daily_group_expiry_hook', [], 'wicket-memberships' );
    }
  }

  public static function schedule_daily_group_activation(): void {
    if ( ! as_next_scheduled_action( 'schedule_daily_group_activation_hook' ) ) {
      $next_run_time = new \DateTime( 'tomorrow', wp_timezone() );
      as_schedule_recurring_action( $next_run_time->getTimestamp(), DAY_IN_SECONDS, 'schedule_daily_group_activation_hook', [], 'wicket-memberships' );
    }
  }

  // ---------------------------------------------------------------------------
  // Hook handlers
  // ---------------------------------------------------------------------------

  /**
   * Transition active membership groups to grace-period when membership_ends_at has passed.
   *
   * @return int Number of groups processed.
   */
  public static function daily_group_grace_period_hook(): int {
    $groups_updated      = [];
    $yesterday_utc      = gmdate( 'Y-m-d\TH:i:sP', current_time( 'timestamp' ) - DAY_IN_SECONDS );
    $membership_ends_at = $yesterday_utc;

    $args = [
      'post_type'      => Helper::get_membership_group_cpt_slug(),
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

    $groups = get_posts( $args );
    foreach ( $groups as $group_post ) {
      $group  = new Membership_Group( $group_post->ID );
      $result = $group->transition_to( Wicket_Memberships::STATUS_GRACE );
      if ( false === $result ) {
        Utilities::wc_log_mship_error( [ 'daily_group_grace_period_hook: transition failed', $group_post->ID ] );
      } else {
        $groups_updated[] = [ $group_post->ID, $group_post->membership_status, $group_post->membership_ends_at ];
      }
    }

    Utilities::wc_log_mship_error( [ 'daily_group_grace_period_hook', $membership_ends_at, $groups_updated ] );
    return count( $groups );
  }

  /**
   * Transition active/grace-period membership groups to expired when membership_expires_at has passed.
   *
   * @return int Number of groups processed.
   */
  public static function daily_group_expiry_hook(): int {
    $groups_updated        = [];
    $yesterday_utc         = gmdate( 'Y-m-d\TH:i:sP', current_time( 'timestamp' ) - DAY_IN_SECONDS );
    $membership_expires_at = $yesterday_utc;

    $args = [
      'post_type'      => Helper::get_membership_group_cpt_slug(),
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

    $groups = get_posts( $args );
    foreach ( $groups as $group_post ) {
      $group  = new Membership_Group( $group_post->ID );
      $result = $group->transition_to( Wicket_Memberships::STATUS_EXPIRED );
      if ( false === $result ) {
        Utilities::wc_log_mship_error( [ 'daily_group_expiry_hook: transition failed', $group_post->ID ] );
      } else {
        $groups_updated[] = [ $group_post->ID, $group_post->membership_status, $group_post->membership_expires_at ];
      }
    }

    Utilities::wc_log_mship_error( [ 'daily_group_expiry_hook', $membership_expires_at, $groups_updated ] );
    return count( $groups );
  }

  /**
   * Transition delayed membership groups to active when membership_starts_at has passed.
   *
   * @return int Number of groups processed.
   */
  public static function daily_group_activation_hook(): int {
    $groups_updated       = [];
    $yesterday_utc        = gmdate( 'Y-m-d\TH:i:sP', current_time( 'timestamp' ) - DAY_IN_SECONDS );
    $membership_starts_at = $yesterday_utc;

    $args = [
      'post_type'      => Helper::get_membership_group_cpt_slug(),
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

    $groups = get_posts( $args );
    foreach ( $groups as $group_post ) {
      $group  = new Membership_Group( $group_post->ID );
      $result = $group->transition_to( Wicket_Memberships::STATUS_ACTIVE );
      if ( false === $result ) {
        Utilities::wc_log_mship_error( [ 'daily_group_activation_hook: transition failed', $group_post->ID ] );
      } else {
        $groups_updated[] = [ $group_post->ID, $group_post->membership_status, $group_post->membership_starts_at ];
      }
    }

    Utilities::wc_log_mship_error( [ 'daily_group_activation_hook', $membership_starts_at, $groups_updated ] );
    return count( $groups );
  }

  // ---------------------------------------------------------------------------
  // Date trigger job handlers — fire do_action hooks for AutomateWoo triggers.
  // No status transitions here; status is owned by the daily cron handlers above.
  // ---------------------------------------------------------------------------

  public static function catch_group_early_renew_at( int $group_post_id ): void {
    do_action( 'wicket_memberships_group_renewal_period_open', $group_post_id );
  }

  public static function catch_group_ends_at( int $group_post_id ): void {
    do_action( 'wicket_memberships_group_end_date_reached', $group_post_id );
  }

  public static function catch_group_expires_at( int $group_post_id ): void {
    do_action( 'wicket_memberships_group_grace_period_expired', $group_post_id );
  }

}
