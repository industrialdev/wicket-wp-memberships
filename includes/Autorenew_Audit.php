<?php

namespace Wicket_Memberships;

defined( 'ABSPATH' ) || exit;

/**
 * Opt-in, report-only audit of `Autorenew_Sync`'s stored autorenew meta: compares it against a
 * fresh `Autorenew::resolve_status()` recompute for a rotating batch of memberships and logs any
 * disagreement. Separate from `Autorenew_Sync` on purpose — that class stores and keeps the value
 * fresh; this class only checks its own work and never writes anything back.
 *
 * @package Wicket_Memberships
 */
class Autorenew_Audit {

  /**
   * How many `wicket_membership` posts one audit run checks, whether triggered by the nightly job
   * or the manual "Run Now" link.
   *
   * @var int
   */
  const BATCH_SIZE = 100;

  /**
   * Option storing the last-audited `wicket_membership` post ID, so each run continues where the
   * previous one left off instead of re-checking the same rows. Wraps back to the start once past
   * the highest ID, so coverage rotates through every membership over multiple runs.
   *
   * @var string
   */
  const CURSOR_OPTION = 'wicket_mship_autorenew_audit_cursor';

  public function __construct() {
    // Opt-in: only scheduled/registered while the WICKET_MSHIP_AUTORENEW_AUDIT setting is on.
    if ( ! empty( $_ENV['WICKET_MSHIP_AUTORENEW_AUDIT'] ) ) {
      add_action( 'wicket_mship_autorenew_audit_hook', [ __CLASS__, 'run_batch' ] );
    }
  }

  /**
   * Fetches up to `$limit` published `wicket_membership` post IDs greater than `$after_id`,
   * ordered ascending. `get_posts()`/`WP_Query` have no built-in "ID greater than" comparator, so
   * this queries `$wpdb` directly rather than paging by offset (which would shift under
   * concurrent inserts/deletes in a way a plain ID cursor doesn't).
   *
   * @param  int $after_id  Only IDs strictly greater than this are returned.
   * @param  int $limit     Maximum number of IDs to return.
   * @return int[]
   */
  private static function get_membership_ids_after( $after_id, $limit ) {
    global $wpdb;

    if ( $limit <= 0 ) {
      return [];
    }

    $ids = $wpdb->get_col( $wpdb->prepare(
      "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND ID > %d ORDER BY ID ASC LIMIT %d",
      'wicket_membership',
      'publish',
      $after_id,
      $limit
    ) );

    return array_map( 'intval', $ids );
  }

  /**
   * Runs one drift-audit batch: checks `BATCH_SIZE` `wicket_membership` posts, starting after the
   * last-audited ID, comparing their stored `Autorenew::resolve_status()` meta (via
   * `Autorenew_Sync`'s meta keys) against a fresh recompute. Report-only — never writes meta or
   * pushes to the MDP, since this exists to catch cases where an `Autorenew_Sync` trigger should
   * have refreshed a membership but didn't; a self-healing audit would silently hide exactly the
   * gap it's meant to surface.
   *
   * Wraps back to the lowest ID once the batch runs past the highest, so repeated runs rotate
   * through every membership over time rather than only ever checking the same 100.
   *
   * @return void
   */
  public static function run_batch() {
    $cursor = (int) get_option( self::CURSOR_OPTION, 0 );

    $membership_post_ids = self::get_membership_ids_after( $cursor, self::BATCH_SIZE );

    // Fewer than a full batch past the cursor means the end of the table was reached — wrap
    // around and fill the rest from the start, so a run always audits a full BATCH_SIZE unless
    // the whole table has fewer memberships than that.
    if ( count( $membership_post_ids ) < self::BATCH_SIZE ) {
      $remaining = self::BATCH_SIZE - count( $membership_post_ids );
      $wrapped_ids = self::get_membership_ids_after( 0, $remaining );
      // array_unique guards against auditing the same row twice in one run when the table has
      // fewer memberships than BATCH_SIZE (the two queries would otherwise overlap).
      $membership_post_ids = array_values( array_unique( array_merge( $membership_post_ids, $wrapped_ids ) ) );
    }

    if ( empty( $membership_post_ids ) ) {
      return;
    }

    $lines = [];
    $drift_count = 0;

    // A single membership's resolve_status() blowing up (e.g. corrupt subscription meta — exactly
    // the kind of row this audit exists to visit) must not abort the batch: the cursor advances
    // per row in the finally block below, so a poison row is logged and skipped rather than
    // pinning every future run at the same cursor position. Mirrors
    // Autorenew_Sync::run_sweep_batch()'s identical guard.
    foreach ( $membership_post_ids as $membership_post_id ) {
      try {
        $cached_result = (bool) get_post_meta( $membership_post_id, Autorenew_Sync::META_KEY_RESULT, true );
        $cached_reason = get_post_meta( $membership_post_id, Autorenew_Sync::META_KEY_REASON, true ) ?: null;

        $membership_meta = Helper::get_post_meta( $membership_post_id );
        $calculated = Autorenew::resolve_status( $membership_meta );

        $line = sprintf(
          'Membership %d: cached=%s, calculated=%s',
          $membership_post_id,
          $cached_result ? 'true' : 'false',
          $calculated['result'] ? 'true' : 'false'
        );

        if ( $cached_result !== $calculated['result'] ) {
          $drift_count++;
          $line .= sprintf(
            "\n  cached reason: %s\n  calculated reason: %s",
            $cached_reason ?: '(none)',
            $calculated['reason'] ?: '(none)'
          );
        }

        $lines[] = $line;
      } catch ( \Throwable $e ) {
        $lines[] = sprintf( 'Membership %d: audit failed: %s', $membership_post_id, $e->getMessage() );
        Utilities::wc_log_mship_error( sprintf(
          'Autorenew audit: failed to check membership #%d: %s',
          $membership_post_id,
          $e->getMessage()
        ) );
      } finally {
        // Advances past this row whether it succeeded or threw, so a poison row can never pin the
        // cursor: it's skipped on all future runs instead of re-fataling this action forever.
        update_option( self::CURSOR_OPTION, $membership_post_id );
      }
    }

    $checked = count( $membership_post_ids );
    $correct = $checked - $drift_count;

    $message = sprintf(
      "%s — %d memberships audited\n%s\nSummary: %d of %d autorenew states correct%s",
      current_time( 'mysql' ),
      $checked,
      implode( "\n", $lines ),
      $correct,
      $checked,
      $drift_count > 0 ? " ({$drift_count} drifted)" : ''
    );

    // Logged at 'error' regardless of drift: the base plugin's Log::log() only writes 'info'
    // when WP_DEBUG is on, and this audit trail needs to exist every run on every site,
    // including production with WP_DEBUG off.
    $source = 'wicket-membership-autorenew-drift-' . time();
    if ( class_exists( '\Wicket' ) ) {
      \Wicket()->log( 'error', $message, [ 'source' => $source ] );
    } elseif ( class_exists( 'WC_Logger' ) ) {
      ( new \WC_Logger() )->log( 'error', $message, [ 'source' => $source ] );
    }
  }

}
