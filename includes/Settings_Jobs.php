<?php

namespace Wicket_Memberships;

/**
 * Scheduled Jobs on the settings page: job tables and the manual Run Now triggers.
 *
 * @see Settings  Public entry points that delegate here.
 * @package Wicket_Memberships
 */
class Settings_Jobs {

  /**
   * Next pending run of a scheduled action, as "Y-m-d H:i" in the MDP timezone.
   *
   * @param  string  $hook   Action Scheduler hook.
   * @param  string  $group  Action Scheduler group; empty matches any group.
   *
   * @return string|null  Next run, or null when nothing is pending.
   */
  public static function get_next_run( $hook, $group = '' ) {
    $args = [ 'hook' => $hook, 'status' => \ActionScheduler_Store::STATUS_PENDING ];
    if ( $group ) {
      $args['group'] = $group;
    }
    $action = as_get_scheduled_actions( $args );
    if ( ! empty( $action ) ) {
      foreach ( $action as $a ) {
        $next = $a->get_schedule()->get_date();
        return $next ? Utilities::format_mdp_date( 'Y-m-d H:i', $next->getTimestamp() ) : null;
      }
    }
    return null;
  }

  /**
   * Render the Scheduled Jobs tables: daily membership jobs, then diagnostics and maintenance tools.
   *
   * A job that is not scheduled shows no Run Now button, as before. A daily job that is not
   * scheduled is flagged, since those should always be scheduled.
   *
   * @see Settings_Jobs::run_triggered_actions()  Handles the Run Now links.
   *
   * @return void
   */
  public static function render() {
    // Each row: [ job, what it does, next run or status, flag status, hook, trigger param (null hides the button), button label ].
    $daily_rows = [];
    foreach ( [
      [ 'Membership Activation', 'Delayed → Active', self::get_next_run( 'schedule_daily_membership_activation_hook' ), 'schedule_daily_membership_activation_hook' ],
      [ 'Membership Grace Period', 'Active → Grace Period', self::get_next_run( 'schedule_daily_membership_grace_period_hook' ), 'schedule_daily_membership_grace_period_hook' ],
      [ 'Membership Expiry', 'Active / Grace Period → Expired', self::get_next_run( 'schedule_daily_membership_expiry_hook' ), 'schedule_daily_membership_expiry_hook' ],
    ] as [ $job, $description, $next_run, $hook ] ) {
      $daily_rows[] = [ $job, $description, $next_run ?: 'Not scheduled', ! $next_run, $hook, $next_run ? $hook : null, 'Run Now' ];
    }

    $audit_next_run = self::get_next_run( 'wicket_mship_autorenew_audit_hook', 'wicket-memberships' );
    if ( ! empty( $audit_next_run ) ) {
      $audit_status = 'On: ' . $audit_next_run;
    } elseif ( ! empty( $_ENV['WICKET_MSHIP_AUTORENEW_AUDIT'] ) ) {
      $audit_status = 'On: schedules on the next page load';
    } else {
      $audit_status = null;
    }

    if ( as_has_scheduled_action( 'wicket_mship_autorenew_sweep_batch', null, 'wicket-memberships' ) ) {
      $progress = Autorenew_Sync::get_sweep_progress();
      // The transient can be missing even mid-sweep (expired or cleared out of band), so fall back
      // to a plain label. Clicking during a sweep restarts it from offset 0 via enqueue_sweep().
      $sweep_status = $progress ? "In progress: {$progress['processed']} of {$progress['total']} memberships" : 'In progress';
    } else {
      $sweep_status = 'On demand';
    }

    $tool_rows = [
      [ 'Auto-Renew Drift Audit', [ 'Logs auto-renew status mismatches. Report only.', 'Compares 100 memberships\' cached auto-renew status against a fresh calculation and logs any mismatch to WooCommerce > Status > Logs (source starting wicket-membership-autorenew-drift-). It never corrects the stored value. Run nightly is saved with Save Changes.' ], $audit_status ?: 'Off', false, 'wicket_mship_autorenew_audit_hook', $audit_status ? 'wicket_mship_run_autorenew_audit' : null, 'Run Now', [ Settings_Fields::class, 'wicket_mship_autorenew_audit' ] ],
      [ 'Auto-Renew Status Refresh', 'Recomputes and recaches every membership\'s auto-renew status in batches of ' . Autorenew_Sync::SWEEP_BATCH_SIZE . '.', $sweep_status, false, 'wicket_mship_autorenew_sweep_batch', 'wicket_mship_refresh_autorenew_status', 'Refresh All Now' ],
    ];
    ?>
    <h3>Daily Membership Jobs</h3>
    <p>These jobs move memberships between statuses daily through Action Scheduler and should always be scheduled. Run Now triggers a job immediately. Next Run times are in the MDP timezone (<?php echo esc_html( Utilities::get_mdp_timezone()->getName() ); ?>).</p>
    <?php self::render_jobs_table( $daily_rows ); ?>

    <h3>Diagnostics &amp; Maintenance</h3>
    <p>Tools to run when needed. They do not need a schedule.</p>
    <?php self::render_jobs_table( $tool_rows ); ?>
    <?php
  }

  /**
   * Render the Tools tab's Bundle Member Resync section: target IDs, Check Only / Resync buttons, and the help panel.
   *
   * Tabs render inside the settings page's options.php form, so the controls bind via the
   * form attribute to a separate form printed in the footer; nesting would also submit its action=update.
   *
   * @see Settings_Jobs::handle_bundle_resync_request()  Handles both buttons.
   *
   * @return void
   */
  public static function render_bundle_resync_section() {
    if ( empty( $_ENV['WICKET_MSHIP_ENABLE_BUNDLES'] ) ) {
      echo '<p class="description">Available when Membership Bundles are enabled.</p>';
      return;
    }

    $user_id = get_current_user_id();
    $notice  = get_transient( 'wicket_mship_bundle_resync_notice_' . $user_id );
    if ( $notice ) {
      delete_transient( 'wicket_mship_bundle_resync_notice_' . $user_id );
    }
    add_action( 'admin_footer', function () {
      ?>
      <form id="wicket-mship-bundle-resync-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
        <input type="hidden" name="action" value="wicket_mship_bundle_resync">
        <?php wp_nonce_field( 'wicket_mship_bundle_resync', 'wicket_mship_bundle_resync_nonce', false ); ?>
      </form>
      <?php
    } );
    ?>
    <p style="max-width:990px">Realigns bundle members with their bundle: status, end, expiry and early renewal dates, and grace period, in WordPress and MDP. On healthy data a run changes nothing. Check Only reports what would change without writing anything.</p>
    <p style="max-width:990px"><strong>Use only to repair drift:</strong> members normally stay in step with their bundle automatically. Run this when a bug has caused members to drift from their bundle and they need to be realigned.</p>
    <?php if ( $notice ) : ?>
      <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> inline"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
    <?php endif; ?>
    <?php // A form panel, not a jobs-table row: one input, a status and two actions read better as label → control rows. ?>
    <div class="widefat" style="max-width:990px;box-sizing:border-box;padding:16px 20px">
      <div style="display:grid;grid-template-columns:140px minmax(0,1fr);gap:16px 20px;align-items:start">
        <label for="wicket-mship-bundle-resync-ids" style="padding-top:5px"><strong>Bundles</strong></label>
        <div>
          <input type="text" id="wicket-mship-bundle-resync-ids" form="wicket-mship-bundle-resync-form" name="wicket_mship_bundle_resync_ids" style="width:100%;max-width:420px;min-width:0;box-sizing:border-box" placeholder="All bundles" autocomplete="off" aria-describedby="wicket-mship-bundle-resync-ids-help">
          <p class="description" id="wicket-mship-bundle-resync-ids-help">Bundle post IDs, separated by commas, up to <?php echo (int) Bundle_Member_Resync::MAX_IDS; ?>. Leave empty to check every bundle.</p>
        </div>

        <strong>Status</strong>
        <div>
          <?php $last_runs = get_option( Bundle_Member_Resync::LAST_RUNS_OPTION, [] ); ?>
          <?php foreach ( [ 'check' => 'Last check', 'resync' => 'Last resync' ] as $mode => $label ) : ?>
            <?php $run = is_array( $last_runs ) ? ( $last_runs[ $mode ] ?? null ) : null; ?>
            <?php echo esc_html( $label ); ?>:
            <?php if ( empty( $run['started'] ) ) : ?>
              Never run
            <?php else : ?>
              <?php
              $parts = [ Utilities::format_mdp_date( 'Y-m-d H:i', (int) $run['started'] ) ];
              $parts[] = empty( $run['bundle_ids'] ) ? 'all bundles' : count( $run['bundle_ids'] ) . ' bundle(s)';
              if ( empty( $run['finished'] ) ) {
                // Unfinished with nothing queued means a batch died (timeout, out of memory) and the chain stopped.
                $queued  = as_has_scheduled_action( Bundle_Member_Resync::HOOK, [ 'mode' => $mode ], 'wicket-memberships' );
                $parts[] = ( $queued ? 'in progress: ' : 'stopped before finishing: ' ) . (int) ( $run['processed'] ?? 0 ) . ' of ' . (int) ( $run['total'] ?? 0 ) . ' members';
              } else {
                $verb    = 'check' === $mode ? 'would change' : 'changed';
                $parts[] = (int) ( $run['changed'] ?? 0 ) . ' ' . $verb . ', ' . (int) ( $run['failed'] ?? 0 ) . ' failed';
              }
              if ( ! empty( $run['broken_link'] ) ) {
                $parts[] = (int) $run['broken_link'] . ' with a broken MDP link';
              }
              echo esc_html( implode( ' · ', $parts ) );
              ?>
              · <a href="<?php echo esc_url( Bundle_Member_Resync::get_log_url( (string) ( $run['source'] ?? '' ) ) ); ?>">View log</a>
            <?php endif; ?>
            <br>
          <?php endforeach; ?>
          <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'action-scheduler', 's' => 'wicket_bundle_resync_members_batch' ], admin_url( 'tools.php' ) ) ); ?>">View runs in Scheduled Actions</a>
          · <code title="Action Scheduler hook">wicket_bundle_resync_members_batch</code>
        </div>
      </div>

      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px;padding-top:16px;border-top:1px solid #c3c4c7">
        <button type="submit" class="button button-primary" form="wicket-mship-bundle-resync-form" name="wicket_mship_bundle_resync_mode" value="check">Check Only</button>
        <button type="submit" class="button button-secondary" form="wicket-mship-bundle-resync-form" name="wicket_mship_bundle_resync_mode" value="resync" onclick="return confirm(<?php echo esc_attr( wp_json_encode( 'Updates status, dates and grace period on bundle members, and pushes them to MDP. Continue?' ) ); ?>);">Resync</button>
        <span class="description" style="align-self:center">Check Only writes nothing. Resync changes WordPress and MDP.</span>
      </div>
    </div>
    <?php
    Settings_Page::render_accordion( 'wicket-mship-bundle-resync', 'What Bundle Member Resync checks', [ __CLASS__, 'render_bundle_resync_details' ] );
  }

  /**
   * Echoes the help panel for Bundle Member Resync: what is synced, and which records are included.
   *
   * @return void
   */
  public static function render_bundle_resync_details() {
    // Each field: [ written to the WordPress member, sent to MDP, note ].
    $synced = [
      'Status'             => [ true, false, 'Matches the bundle. A member whose own start date is in the future stays delayed.' ],
      'End date'           => [ true, true, '' ],
      'Expiry date'        => [ true, true, '' ],
      'Grace period'       => [ true, true, 'Days from the bundle\'s end date to its expiry date, not the config default.' ],
      'Early renewal date' => [ true, false, '' ],
      'Start date'         => [ false, false, 'Not synced. Members keep the date they joined.' ],
      'Tier, product, seat' => [ false, false, 'Not synced.' ],
    ];
    // Each record: [ included, updated in WordPress, sent to MDP, note ].
    // Ordered included then skipped; within each, WordPress and MDP, WordPress only, MDP only, neither.
    $included = [
      'Pending member'                                   => [ true, true, true, 'Realigned with its bundle.' ],
      'Delayed member'                                   => [ true, true, true, 'Realigned with its bundle. Stays delayed while its own start date is in the future.' ],
      'Active member'                                    => [ true, true, true, 'Realigned with its bundle.' ],
      'Grace-period member'                              => [ true, true, true, 'Realigned with its bundle.' ],
      'Member with a broken MDP link'                    => [ true, true, true, 'Updated by MDP ID. Flagged in the log; repair the link with wp wicket-mship external-id repair.' ],
      'Member of an expired or cancelled bundle'         => [ true, true, false, 'Moved to the bundle\'s status, as when the bundle changed status.' ],
      'Member not yet in MDP'                            => [ true, true, false, 'MDP is skipped until the member has an MDP record.' ],
      'Bundle: pending, delayed, active or grace period' => [ true, false, true, 'The bundle\'s own MDP record is updated from its dates.' ],
      'Cancelled member'                                 => [ false, false, false, 'Final state, never revived.' ],
      'Expired member'                                   => [ false, false, false, 'Final state, never revived.' ],
      'Membership not in a bundle'                       => [ false, false, false, 'Never touched.' ],
      'Bundle: expired or cancelled'                     => [ false, false, false, 'Past dates break MDP updates.' ],
    ];
    $mark = function ( $yes ) {
      return $yes
        ? '<span class="dashicons dashicons-yes" aria-hidden="true"></span><span class="screen-reader-text">Yes</span>'
        : '<span class="dashicons dashicons-no-alt" aria-hidden="true"></span><span class="screen-reader-text">No</span>';
    };
    ?>
      <p><strong>What is synced</strong> from the bundle to each member</p>
      <table class="widefat striped" style="max-width:990px">
        <?php // td, not th: the settings page's .form-table th styles would leak into this nested table. ?>
        <colgroup>
          <col style="width:22%">
          <col style="width:13%">
          <col style="width:13%">
          <col>
        </colgroup>
        <thead>
          <tr><td><strong>Field</strong></td><td style="text-align:center"><strong>WordPress</strong></td><td style="text-align:center"><strong>MDP</strong></td><td><strong>Notes</strong></td></tr>
        </thead>
        <tbody>
          <?php foreach ( $synced as $field => [ $wordpress, $mdp, $note ] ) : ?>
            <tr>
              <td><strong><?php echo esc_html( $field ); ?></strong></td>
              <td style="text-align:center"><?php echo $mark( $wordpress ); ?></td>
              <td style="text-align:center"><?php echo $mark( $mdp ); ?></td>
              <td><?php echo esc_html( $note ); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <p><strong>Who is included</strong></p>
      <table class="widefat striped" style="max-width:990px">
        <colgroup>
          <col style="width:28%">
          <col style="width:12%">
          <col style="width:12%">
          <col style="width:12%">
          <col>
        </colgroup>
        <thead>
          <tr><td><strong>Record</strong></td><td><strong>Status</strong></td><td style="text-align:center"><strong>WordPress</strong></td><td style="text-align:center"><strong>MDP</strong></td><td><strong>Notes</strong></td></tr>
        </thead>
        <tbody>
          <?php foreach ( $included as $record => [ $is_included, $wordpress, $mdp, $note ] ) : ?>
            <tr>
              <td><strong><?php echo esc_html( $record ); ?></strong></td>
              <td><?php echo $is_included ? 'Included' : 'Skipped'; ?></td>
              <td style="text-align:center"><?php echo $mark( $wordpress ); ?></td>
              <td style="text-align:center"><?php echo $mark( $mdp ); ?></td>
              <td><?php echo esc_html( $note ); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p>Runs in the background through Action Scheduler, at most <?php echo (int) Bundle_Member_Resync::BATCH_SIZE; ?> members per batch. Each run writes its own log to WooCommerce &gt; Status &gt; Logs, by post ID: <code>wicket-membership-bundle-check-…</code> for Check Only and <code>wicket-membership-bundle-resync-…</code> for Resync. The Status row links to the latest of each.</p>
    <?php
  }

  /**
   * Handle the Check Only / Resync buttons: verify the request, validate target IDs, and report back.
   *
   * @return void  Redirects back to the Tools tab.
   */
  public static function handle_bundle_resync_request() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'wicket-memberships' ), 403 );
    }
    check_admin_referer( 'wicket_mship_bundle_resync', 'wicket_mship_bundle_resync_nonce' );

    $redirect = admin_url( 'options-general.php?page=wicket-membership-settings&tab=tools' );
    $user_id  = get_current_user_id();

    if ( empty( $_ENV['WICKET_MSHIP_ENABLE_BUNDLES'] ) ) {
      wp_safe_redirect( $redirect );
      exit;
    }

    $dry_run = ( sanitize_key( wp_unslash( $_POST['wicket_mship_bundle_resync_mode'] ?? '' ) ) !== 'resync' );
    $raw_ids = sanitize_text_field( wp_unslash( $_POST['wicket_mship_bundle_resync_ids'] ?? '' ) );

    $requested = array_values( array_unique( array_filter( array_map( 'absint', preg_split( '/[\s,]+/', $raw_ids, -1, PREG_SPLIT_NO_EMPTY ) ) ) ) );
    $valid     = array_values( array_filter( $requested, fn( $id ) => get_post_type( $id ) === Helper::get_membership_bundle_cpt_slug()
      // Trashed bundles have had their MDP record deleted; "all bundles" excludes them too.
      && get_post_status( $id ) !== 'trash' ) );
    $rejected  = array_diff( $requested, $valid );

    if ( count( $requested ) > Bundle_Member_Resync::MAX_IDS ) {
      $notice = [ 'type' => 'error', 'message' => sprintf( 'Too many IDs: %d given, the limit is %d. Nothing was queued.', count( $requested ), Bundle_Member_Resync::MAX_IDS ) ];
    } elseif ( $raw_ids !== '' && empty( $valid ) ) {
      $notice = [ 'type' => 'error', 'message' => 'None of the IDs are membership bundles that are not in the trash. Nothing was queued.' ];
    } else {
      $queued = Bundle_Member_Resync::enqueue( $valid, $dry_run );
      if ( is_wp_error( $queued ) ) {
        $notice = [ 'type' => 'error', 'message' => $queued->get_error_message() ];
      } else {
        $target  = empty( $valid ) ? 'all bundles' : count( $valid ) . ' bundle(s): ' . implode( ', ', $valid );
        $message = sprintf( '%s started for %s. It runs in the background; progress shows under Status.', $dry_run ? 'Check Only' : 'Resync', $target );
        if ( $rejected ) {
          $message .= ' Ignored, not membership bundles or in the trash: ' . implode( ', ', $rejected ) . '.';
        }
        $notice = [ 'type' => 'success', 'message' => $message ];
      }
    }

    set_transient( 'wicket_mship_bundle_resync_notice_' . $user_id, $notice, MINUTE_IN_SECONDS );
    wp_safe_redirect( $redirect );
    exit;
  }

  /**
   * Render one Scheduled Jobs table.
   *
   * @param  array  $rows  Rows of [ job, description (string, or [ short text, details shown on hover ]), status,
   *                       flag status, hook, trigger param or null, button label,
   *                       optional callable that renders a control in the actions column ].
   *
   * @return void
   */
  private static function render_jobs_table( $rows ) {
    ?>
    <table class="widefat fixed striped" style="max-width:990px">
      <?php // Same width cap as the base plugin's overview table, and the same column widths in every jobs table so they line up. ?>
      <colgroup>
        <col style="width:22%">
        <col style="width:40%">
        <col style="width:18%">
        <col style="width:20%">
      </colgroup>
      <thead>
        <tr>
          <th scope="col">Job</th>
          <th scope="col">Description</th>
          <th scope="col">Next Run</th>
          <th scope="col"><span class="screen-reader-text">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $rows as $row ) : ?>
          <?php [ $job, $description, $status, $flag_status, $hook, $trigger, $button ] = $row; ?>
          <tr>
            <td>
              <?php // Opens Tools > Scheduled Actions filtered to this job's hook (all statuses, so past runs show too). ?>
              <strong><a href="<?php echo esc_url( add_query_arg( [ 'page' => 'action-scheduler', 's' => $hook ], admin_url( 'tools.php' ) ) ); ?>"><?php echo esc_html( $job ); ?></a></strong>
            </td>
            <td>
              <?php [ $summary, $details ] = is_array( $description ) ? $description : [ $description, '' ]; ?>
              <?php echo esc_html( $summary ); ?>
              <?php if ( $details ) : ?>
                <?php // Core dashicon with a native tooltip; focusable and labelled for keyboard and screen readers. ?>
                <span class="dashicons dashicons-info-outline" tabindex="0" role="img" title="<?php echo esc_attr( $details ); ?>" aria-label="<?php echo esc_attr( $details ); ?>"></span>
              <?php endif; ?>
              <br><code title="Action Scheduler hook"><?php echo esc_html( $hook ); ?></code>
            </td>
            <td<?php echo $flag_status ? ' class="attention"' : ''; ?>><?php echo esc_html( $status ); ?></td>
            <td>
              <?php if ( ! empty( $row[7] ) ) : ?>
                <?php call_user_func( $row[7] ); ?>
              <?php endif; ?>
              <?php if ( $trigger ) : ?>
                <a class="button button-secondary" href="<?php echo esc_url( self::get_manual_action_link( $trigger ) ); ?>"><?php echo esc_html( $button ); ?></a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php
  }

  /**
   * Builds a nonced settings-page URL for one manual trigger link (Run Now, Refresh All Now, or a
   * Subscriptions Mode switch).
   *
   * @param  string $trigger_param  One of `MANUAL_ACTION_TRIGGER_PARAMS`.
   * @return string  Nonced URL; verified by `run_triggered_actions()` via `check_admin_referer()`.
   */
  public static function get_manual_action_link( $trigger_param ) {
    return wp_nonce_url(
      "options-general.php?page=wicket-membership-settings&tab=developer&{$trigger_param}=1",
      'wicket_mship_manual_action'
    );
  }

  /**
   * Query args that trigger one of this page's manual links (Run Now, Refresh All Now, Subscriptions
   * Mode switches).
   * Shared by `handle_manual_action_links()` (to detect and strip them) and tests (to assert
   * against `run_triggered_actions()` without needing the redirect/exit wrapper).
   *
   * @var string[]
   */
  const MANUAL_ACTION_TRIGGER_PARAMS = [
    'schedule_daily_membership_expiry_hook',
    'schedule_daily_membership_grace_period_hook',
    'schedule_daily_membership_activation_hook',
    'wicket_mship_refresh_autorenew_status',
    'wicket_mship_run_autorenew_audit',
    Subscriptions_Mode::LIVE_TRIGGER,
    Subscriptions_Mode::STAGING_TRIGGER,
  ];

  /**
   * Hooked to `admin_init`. Runs the manual-trigger links on this settings page (Run Now,
   * Refresh All Now, Subscriptions Mode switches). These are plain GET links (not a form submit), so they can't live inside
   * `wicket_membership_plugin_options_validate()` — that only runs when the settings form itself
   * is POSTed to options.php, which a bare link to this page never does.
   *
   * Redirects back to a clean URL (trigger query var stripped) after handling one, since the
   * trigger param otherwise stays in the address bar — admin_init fires on every load, so without
   * the redirect, simply reloading or revisiting the page re-runs the same trigger indefinitely
   * (observed: repeatedly restarting the autorenew sweep from scratch on every page view). The
   * actual side effects live in `run_triggered_actions()` so tests can exercise them without
   * hitting this method's `exit`.
   *
   * @return void
   */
  public static function handle_manual_action_links() {
    if ( empty( $_GET['page'] ) || 'wicket-membership-settings' !== $_GET['page'] ) {
      return;
    }

    if ( ! self::run_triggered_actions() ) {
      return;
    }

    wp_safe_redirect( remove_query_arg( self::MANUAL_ACTION_TRIGGER_PARAMS ) );
    exit;
  }

  /**
   * Runs whichever of this page's manual triggers are present in `$_GET`, if any. These triggers
   * start real background jobs (or run one inline) or switch WooCommerce Subscriptions' mode, so both a capability check and nonce
   * verification gate every branch below, not just the outer "is a trigger present" check — a
   * capability-only guard would still let an attacker-crafted link ride an admin's session via a
   * plain GET (CSRF), since GET requests carry no proof the admin actually clicked "Run Now" on
   * this page.
   *
   * @return bool  True if at least one trigger ran (caller should redirect to clear the query
   *               string), false if none were present.
   */
  public static function run_triggered_actions() {
    if ( empty( array_filter( self::MANUAL_ACTION_TRIGGER_PARAMS, fn( $param ) => ! empty( $_GET[ $param ] ) ) ) ) {
      return false;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
      return false;
    }

    check_admin_referer( 'wicket_mship_manual_action' );

    if ( ! empty( $_GET['schedule_daily_membership_expiry_hook'] ) ) {
      $count = Membership_Controller::daily_membership_expiry_hook();
      Utilities::wc_log_mship_error( [ 'schedule_daily_membership_expiry_hook', 'Count: ' . $count ] );
    }
    if ( ! empty( $_GET['schedule_daily_membership_grace_period_hook'] ) ) {
      $count = Membership_Controller::daily_membership_grace_period_hook();
      Utilities::wc_log_mship_error( [ 'schedule_daily_membership_grace_period_hook', 'Count: ' . $count ] );
    }
    if ( ! empty( $_GET['schedule_daily_membership_activation_hook'] ) ) {
      $count = Membership_Controller::daily_membership_activation_hook();
      Utilities::wc_log_mship_error( [ 'schedule_daily_membership_activation_hook', 'Count: ' . $count ] );
    }
    if ( ! empty( $_GET['wicket_mship_refresh_autorenew_status'] ) ) {
      // Queues the real chunked Action Scheduler batch job rather than running inline, since a
      // manual sweep can cover thousands of memberships and must not risk timing out this request.
      Autorenew_Sync::enqueue_sweep();
      Utilities::wc_log_mship_error( [ 'wicket_mship_refresh_autorenew_status', 'Autorenew status sweep enqueued' ] );
    }
    if ( ! empty( $_GET['wicket_mship_run_autorenew_audit'] ) ) {
      // Runs inline: a single audit batch is capped at Autorenew_Audit::BATCH_SIZE (100)
      // memberships, well within one request's execution time, unlike the full sweep above.
      Autorenew_Audit::run_batch();
    }
    // Both links can't be shown at once, but if both are present, staging wins as the safer mode.
    if ( ! empty( $_GET[ Subscriptions_Mode::LIVE_TRIGGER ] ) && empty( $_GET[ Subscriptions_Mode::STAGING_TRIGGER ] ) ) {
      Subscriptions_Mode::switch_to_live();
    }
    if ( ! empty( $_GET[ Subscriptions_Mode::STAGING_TRIGGER ] ) ) {
      Subscriptions_Mode::switch_to_staging();
    }

    return true;
  }
}
