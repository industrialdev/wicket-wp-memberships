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
   * Next pending run of a scheduled action, as "Y-m-d H:i" in site time.
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
        return date( "Y-m-d H:i", strtotime( json_decode( json_encode( $a->get_schedule()->get_date() ) )->date ) );
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
    <p>These jobs move memberships between statuses daily through Action Scheduler and should always be scheduled. Run Now triggers a job immediately.</p>
    <?php self::render_jobs_table( $daily_rows ); ?>

    <h3>Diagnostics &amp; Maintenance</h3>
    <p>Tools to run when needed. They do not need a schedule.</p>
    <?php self::render_jobs_table( $tool_rows ); ?>
    <?php
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
   * Builds a nonced settings-page URL for one manual "Run Now" / "Refresh All Now" trigger link.
   *
   * @param  string $trigger_param  One of `MANUAL_ACTION_TRIGGER_PARAMS`.
   * @return string  Nonced URL; verified by `run_triggered_actions()` via `check_admin_referer()`.
   */
  private static function get_manual_action_link( $trigger_param ) {
    return wp_nonce_url(
      "options-general.php?page=wicket-membership-settings&tab=developer&{$trigger_param}=1",
      'wicket_mship_manual_action'
    );
  }

  /**
   * Query args that trigger one of this page's manual "Run Now" / "Refresh All Now" links.
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
  ];

  /**
   * Hooked to `admin_init`. Runs the four "Run Now" / "Refresh All Now" manual-trigger links on
   * this settings page. These are plain GET links (not a form submit), so they can't live inside
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
   * start real background jobs (or run one inline), so both a capability check and nonce
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

    return true;
  }
}
