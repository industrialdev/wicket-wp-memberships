<?php
/**
 * WP-CLI command for repairing membership external_id assignments.
 *
 * @package Wicket
 */

namespace Wicket_Memberships\CLI;

use Wicket_Memberships\Membership_Controller;
use WP_CLI;
use WP_CLI\Utils;

/**
 * Finds membership posts left without an MDP external_id by a prior collision
 * or PATCH failure, and re-runs the assignment.
 *
 * Registered as `wp wicket-mship external-id repair`. external_id is the WP
 * membership post ID and the MDP holds a unique index on it per membership
 * type, so a foreign record squatting on our post ID (typically a QA install
 * sharing the MDP staging tenant, WWID-2629) silently leaves the post unlinked.
 * The repair re-runs assign_membership_external_id(): it succeeds once the
 * squatter is gone and reports the owning record while it is not.
 *
 * Clearing a squatter's external_id on the MDP is intentionally out of scope:
 * this command never writes to a membership it does not own.
 *
 * @since 1.0.123
 */
class External_Id_Repair_Command {

  /**
   * Flag post meta keys this command treats as repairable breakage.
   *
   * @var list<string>
   */
  private const FLAG_KEYS = [
    '_wicket_membership_external_id_collision',
    '_wicket_membership_external_id_failed',
  ];

  /**
   * Re-attempt external_id assignment on flagged membership posts.
   *
   * Scans for membership posts carrying a collision or failed flag, re-runs the
   * assignment for each, and reports one row per post: reassigned (healthy),
   * blocked (a foreign MDP membership still owns the external_id — clear it on
   * the MDP first), failed (the MDP PATCH errored again), or invalid (post is
   * not a repairable membership).
   *
   * ## OPTIONS
   *
   * [--post=<id>]
   * : Repair a single membership post ID instead of scanning all flagged posts.
   *
   * [--dry-run]
   * : List flagged posts and their current collision state without writing
   *   anything. Recommended first run.
   *
   * [--yes]
   * : Skip the interactive confirmation before writing.
   *
   * [--format=<format>]
   * : Output format for the results table.
   * ---
   * default: table
   * options:
   *   - table
   *   - json
   *   - csv
   * ---
   *
   * ## EXAMPLES
   *
   *     # See what is flagged and why, before changing anything.
   *     $ wp wicket-mship external-id repair --dry-run
   *
   *     # Repair everything flagged (prompts for confirmation).
   *     $ wp wicket-mship external-id repair
   *
   *     # Re-check a single post.
   *     $ wp wicket-mship external-id repair --post=29636
   *
   * @param array<int, string>    $args        Positional args (unused).
   * @param array<string, string> $assoc_args  Associative args from the flags above.
   *
   * @return void
   */
  public function repair( $args, $assoc_args ) {
    $dry_run = (bool) Utils\get_flag_value( $assoc_args, 'dry-run', false );
    $yes     = (bool) Utils\get_flag_value( $assoc_args, 'yes', false );
    $format  = (string) Utils\get_flag_value( $assoc_args, 'format', 'table' );
    $single  = (int) Utils\get_flag_value( $assoc_args, 'post', 0 );

    $post_ids = $single > 0 ? [ $single ] : $this->flagged_post_ids();

    if ( empty( $post_ids ) ) {
      WP_CLI::success( 'No membership posts carry an external_id collision or failed flag. Nothing to do.' );

      return;
    }

    WP_CLI::log( sprintf( 'Found %d flagged membership post(s).', count( $post_ids ) ) );

    if ( $dry_run ) {
      $this->render( array_map( [ $this, 'describe' ], $post_ids ), $format );
      WP_CLI::success( 'Dry run complete — nothing was written. Clear the squatter external_ids on the MDP first, then run without --dry-run.' );

      return;
    }

    if ( ! $yes ) {
      WP_CLI::confirm( sprintf( 'Re-run external_id assignment for %d membership post(s)? This PATCHes the MDP for posts we own only.', count( $post_ids ) ) );
    }

    $controller = new Membership_Controller();
    $rows       = [];
    $tally      = [
      'reassigned' => 0,
      'blocked'    => 0,
      'failed'     => 0,
      'invalid'    => 0,
    ];

    foreach ( $post_ids as $post_id ) {
      $result         = $controller->repair_membership_external_id( $post_id );
      $rows[]         = array_merge( [ 'post_id' => $post_id ], $result );
      $status         = (string) ( $result['status'] ?? 'invalid' );
      $tally[ $status ] = ( $tally[ $status ] ?? 0 ) + 1;
    }

    $this->render( $rows, $format );

    WP_CLI::log( sprintf(
      'reassigned: %1$d, blocked: %2$d, failed: %3$d, invalid: %4$d',
      $tally['reassigned'],
      $tally['blocked'],
      $tally['failed'],
      $tally['invalid']
    ) );

    if ( $tally['blocked'] > 0 ) {
      WP_CLI::warning( 'Blocked posts still have a foreign MDP membership owning their external_id. Clear those on the MDP, then re-run this command.' );
    }

    if ( $tally['reassigned'] > 0 ) {
      WP_CLI::success( sprintf( '%d membership post(s) reassigned; MDP link restored.', $tally['reassigned'] ) );
    }
  }

  /**
   * Collect the post IDs carrying either flag meta, newest first.
   *
   * @return list<int>
   */
  private function flagged_post_ids(): array {
    global $wpdb;

    // Flag rows are rare by construction (only created on assignment failure),
    // so the meta_key scan stays cheap in practice. DISTINCT collapses posts
    // carrying both keys to one entry.
    $placeholders = implode( ', ', array_fill( 0, count( self::FLAG_KEYS ), '%s' ) );
    $query        = "SELECT DISTINCT pm.post_id
      FROM {$wpdb->postmeta} pm
      INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
      WHERE pm.meta_key IN ( {$placeholders} )
        AND p.post_type = 'wicket_membership'
      ORDER BY pm.post_id DESC
      LIMIT 500";

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
    $rows = $wpdb->get_col( $wpdb->prepare( $query, ...self::FLAG_KEYS ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

    return array_map( 'intval', (array) $rows );
  }

  /**
   * Read-only description of a flagged post for --dry-run output.
   *
   * @param int $post_id Membership post ID.
   * @return array<string, mixed>
   */
  private function describe( int $post_id ): array {
    $collision = get_post_meta( $post_id, '_wicket_membership_external_id_collision', true );
    $failed    = get_post_meta( $post_id, '_wicket_membership_external_id_failed', true );

    return [
      'post_id' => $post_id,
      'status'  => ! empty( $collision ) ? 'blocked' : ( ! empty( $failed ) ? 'failed' : 'no-flag' ),
      'owner'   => (string) ( $collision['owner'] ?? '' ),
      'error'   => (string) ( $failed['error'] ?? '' ),
      'time'    => (string) ( $collision['time'] ?? $failed['time'] ?? '' ),
    ];
  }

  /**
   * Print the result rows in the requested format.
   *
   * @param list<array<string, mixed>> $rows   Result rows.
   * @param string                     $format table|json|csv.
   * @return void
   */
  private function render( array $rows, string $format ): void {
    if ( $format === 'json' ) {
      WP_CLI::log( (string) wp_json_encode( $rows, JSON_PRETTY_PRINT ) );

      return;
    }

    if ( $format === 'csv' ) {
      Utils\write_csv( STDOUT, $rows );

      return;
    }

    Utils\format_items( 'table', $rows, array_keys( (array) reset( $rows ) ) );
  }
}
