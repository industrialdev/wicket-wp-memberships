<?php

namespace Wicket_Memberships;

/**
 * Bundle Member Resync: the Tools tab's support tool that realigns bundle members with
 * their bundle after a bug has caused drift.
 *
 * Owns the run lifecycle: queueing a Check Only or Resync run, processing it in Action
 * Scheduler batches, and writing each run's own log. The per-member compare-and-fix lives
 * in Membership_Bundle::resync_members(), which shares its write paths with the live cascades.
 *
 * @see Membership_Bundle::resync_members()
 * @see Settings_Jobs::render_bundle_resync_section()  UI and request handler.
 * @package Wicket_Memberships
 */
class Bundle_Member_Resync {

  /** Most bundle IDs one run accepts. */
  const MAX_IDS = 500;

  /** Action Scheduler hook for one Bundle Member Resync batch. */
  const HOOK = 'wicket_bundle_resync_members_batch';

  /** Most members one resync batch processes, so no single action runs long. */
  const BATCH_SIZE = 50;

  /** Option holding the last Check Only and last Resync run, keyed 'check' / 'resync'. */
  const LAST_RUNS_OPTION = 'wicket_mship_bundle_resync_last_runs';

  public function __construct() {
    add_action( self::HOOK, [ __CLASS__, 'process_batch' ], 10, 1 );
  }

  /**
   * Start a Check Only or Resync run over the given bundles, or over every bundle.
   *
   * Only one run, of either mode, can be in progress at a time. Run state, including the
   * bundle queue and member cursor (last member ID seen), lives in LAST_RUNS_OPTION; the
   * Action Scheduler job gets only the mode, so no request input reaches it.
   *
   * @param int[] $bundle_ids Validated bundle post IDs; empty for every bundle.
   * @param bool  $dry_run    True for Check Only.
   * @return true|\WP_Error
   */
  public static function enqueue( array $bundle_ids, bool $dry_run ) {
    if ( as_has_scheduled_action( self::HOOK, null, 'wicket-memberships' ) ) {
      return new \WP_Error( 'resync_in_progress', 'A Bundle Member Resync or Check Only run is already in progress. Wait for it to finish.' );
    }

    $queue = $bundle_ids;
    if ( empty( $queue ) ) {
      $queue = get_posts( [
        'post_type'   => Helper::get_membership_bundle_cpt_slug(),
        'post_status' => 'any',
        'numberposts' => -1,
        'fields'      => 'ids',
        'orderby'     => 'ID',
        'order'       => 'ASC',
      ] );
    }
    $queue = array_values( array_map( 'intval', $queue ) );

    if ( empty( $queue ) ) {
      return new \WP_Error( 'resync_no_bundles', 'There are no membership bundles to check.' );
    }

    $total = count( get_posts( [
      'post_type'   => Helper::get_membership_cpt_slug(),
      'post_status' => 'any',
      'numberposts' => -1,
      'fields'      => 'ids',
      'meta_query'  => [ [ 'key' => 'membership_bundle_id', 'value' => $queue, 'compare' => 'IN' ] ],
    ] ) );

    $mode   = $dry_run ? 'check' : 'resync';
    $source = 'wicket-membership-bundle-' . $mode . '-' . time();
    $runs   = get_option( self::LAST_RUNS_OPTION, [] );
    $runs   = is_array( $runs ) ? $runs : [];

    $runs[ $mode ] = [
      'source'        => $source,
      'started'       => time(),
      'finished'      => 0,
      'bundle_ids'    => $bundle_ids,
      'queue'         => $queue,
      'index'         => 0,
      'member_cursor' => 0,
      'total'         => $total,
      'processed'     => 0,
      'changed'       => 0,
      'unchanged'     => 0,
      'skipped'       => 0,
      'failed'        => 0,
      'broken_link'   => 0,
    ];
    update_option( self::LAST_RUNS_OPTION, $runs, false );

    self::log( $source, sprintf(
      '%s started by user %d: %s, %d members.',
      $dry_run ? 'Check Only' : 'Resync',
      get_current_user_id(),
      empty( $bundle_ids ) ? 'all ' . count( $queue ) . ' bundles' : 'bundles ' . implode( ', ', $queue ),
      $total
    ) );

    as_enqueue_async_action( self::HOOK, [ 'mode' => $mode ], 'wicket-memberships' );

    return true;
  }

  /**
   * Process one Bundle Member Resync batch: up to BATCH_SIZE members, across bundles.
   *
   * Picks up from the run's cursor (bundle index + last member ID), logs every change or
   * would-be change, then queues the next batch or stamps the run finished.
   *
   * @param string $mode 'check' or 'resync'.
   * @return void
   */
  public static function process_batch( $mode ): void {
    if ( ! in_array( $mode, [ 'check', 'resync' ], true ) ) {
      return;
    }

    $runs = get_option( self::LAST_RUNS_OPTION, [] );
    $run  = is_array( $runs ) ? ( $runs[ $mode ] ?? null ) : null;
    if ( empty( $run['started'] ) || ! empty( $run['finished'] ) ) {
      return;
    }

    $dry_run = 'check' === $mode;
    $budget  = self::BATCH_SIZE;
    $queue   = $run['queue'] ?? [];

    while ( $budget > 0 && $run['index'] < count( $queue ) ) {
      $bundle_id = (int) $queue[ $run['index'] ];

      try {
        $result = ( new Membership_Bundle( $bundle_id ) )->resync_members( $dry_run, (int) ( $run['member_cursor'] ?? 0 ), $budget );
      } catch ( \Throwable $e ) {
        // One broken bundle must not stop the run; record it and move on.
        self::log( $run['source'], "Bundle {$bundle_id}: failed: " . $e->getMessage() );
        $run['failed']++;
        $run['index']++;
        $run['member_cursor'] = 0;
        continue;
      }

      if ( $result['error'] !== '' ) {
        self::log( $run['source'], "Bundle {$bundle_id}: skipped: " . $result['error'] );
        $run['failed']++;
      }

      foreach ( [ 'processed', 'changed', 'unchanged', 'skipped', 'failed', 'broken_link' ] as $key ) {
        $run[ $key ] = ( $run[ $key ] ?? 0 ) + $result[ $key ];
      }

      foreach ( $result['changes'] as $change ) {
        $fields = [];
        foreach ( $change['fields'] as $field => [ $old, $new ] ) {
          $fields[] = sprintf( "%s '%s' → '%s'", $field, $old, $new );
        }
        if ( $change['error'] !== '' ) {
          $verb = 'Failed';
        } elseif ( empty( $fields ) ) {
          $verb = 'Unchanged';
        } else {
          $verb = $dry_run ? 'Would change' : 'Changed';
        }
        self::log( $run['source'], sprintf(
          '%s member %d (bundle %d)%s%s%s',
          $verb,
          $change['member_id'],
          $bundle_id,
          $fields ? ': ' . implode( '; ', $fields ) : '',
          $change['error'] !== '' ? ' — ' . $change['error'] : '',
          $change['mdp_link'] !== '' ? ' — MDP link broken (' . $change['mdp_link'] . '); run `wp wicket-mship external-id repair`' : ''
        ) );
      }

      // A bundle with no members still costs one slot, so the loop always advances.
      $budget -= max( 1, $result['processed'] );

      if ( $result['has_more'] ) {
        $run['member_cursor'] = $result['last_id'];
      } else {
        $run['index']++;
        $run['member_cursor'] = 0;
      }
    }

    if ( $run['index'] < count( $queue ) ) {
      $runs[ $mode ] = $run;
      update_option( self::LAST_RUNS_OPTION, $runs, false );
      as_enqueue_async_action( self::HOOK, [ 'mode' => $mode ], 'wicket-memberships' );
      return;
    }

    $run['finished'] = time();
    unset( $run['queue'] );
    $runs[ $mode ] = $run;
    update_option( self::LAST_RUNS_OPTION, $runs, false );

    self::log( $run['source'], sprintf(
      '%s finished: %d bundles, %d members checked, %d %s, %d unchanged, %d skipped, %d failed, %d with a broken MDP link.',
      $dry_run ? 'Check Only' : 'Resync',
      count( $queue ),
      $run['processed'],
      $run['changed'],
      $dry_run ? 'would change' : 'changed',
      $run['unchanged'],
      $run['skipped'],
      $run['failed'],
      $run['broken_link'] ?? 0
    ) );
  }

  /**
   * Write one line to a resync run's own log.
   *
   * @param string $source  The run's log source.
   * @param string $message Line to write.
   * @return void
   */
  private static function log( string $source, string $message ): void {
    // 'audit' is always written; base plugins that predate it only write 'error' without WP_DEBUG.
    $level = defined( '\WicketWP\Log::LOG_LEVEL_AUDIT' ) ? 'audit' : 'error';

    if ( function_exists( 'Wicket' ) ) {
      \Wicket()->log( $level, $message, [ 'source' => $source ] );
    } elseif ( class_exists( 'WC_Logger' ) ) {
      ( new \WC_Logger() )->log( 'info', $message, [ 'source' => $source ] );
    }
  }

  /**
   * URL of a run's log in WooCommerce > Status > Logs.
   *
   * Each run logs under its own source, so the newest file for that source is the run's log.
   * Falls back to the logs tab when the file-based log viewer is unavailable or the file is gone.
   *
   * @param  string $source  Log source the run wrote under.
   * @return string
   */
  public static function get_log_url( string $source ): string {
    $logs_url = class_exists( '\Automattic\WooCommerce\Utilities\LoggingUtil' )
      ? \Automattic\WooCommerce\Utilities\LoggingUtil::get_logs_tab_url()
      : admin_url( 'admin.php?page=wc-status&tab=logs' );

    $controller_class = '\Automattic\WooCommerce\Internal\Admin\Logging\FileV2\FileController';
    if ( $source === '' || ! function_exists( 'wc_get_container' ) || ! class_exists( $controller_class ) ) {
      return $logs_url;
    }

    try {
      $files = wc_get_container()->get( $controller_class )->get_files( [ 'source' => $source, 'exact_source' => true, 'per_page' => 1 ] );
    } catch ( \Throwable $e ) {
      return $logs_url;
    }

    if ( is_wp_error( $files ) || empty( $files ) ) {
      return $logs_url;
    }

    return add_query_arg( [ 'view' => 'single_file', 'file_id' => reset( $files )->get_file_id() ], $logs_url );
  }
}
