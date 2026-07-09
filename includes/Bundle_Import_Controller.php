<?php

namespace Wicket_Memberships;

defined( 'ABSPATH' ) || exit;

/**
 * Launch-time CSV import engine for membership bundles. Mirrors Import_Controller's
 * shape (one record per call, synchronous, REST-driven) but creates
 * Membership_Bundle container posts instead of individual memberships.
 *
 * No member data is handled here — bundle members arrive via the existing
 * individual membership import (Import_Controller::create_individual_memberships())
 * when a row carries the optional Membership_Bundle_UUID column.
 */
class Bundle_Import_Controller {

  /**
   * Import a single membership bundle row.
   *
   * @param array $record CSV row, header-normalized (spaces -> underscores).
   * @return \WP_REST_Response
   */
  public function create_bundle( $record ) {
    if ( empty( $record ) ) {
      return new \WP_REST_Response( [ 'error' => 'File line read error.' ] );
    }

    $mdp_uuid = $record['Membership_Bundle_UUID'] ?? '';
    if ( empty( $mdp_uuid ) ) {
      return $this->log_and_respond( 'error', 'Missing Membership Bundle UUID.', $record );
    }

    // Duplicate check — matched on the same MDP bundle UUID re-run after re-run.
    if ( $this->bundle_exists( $mdp_uuid ) ) {
      return $this->log_and_respond( 'skipped', "Already exists: Membership Bundle UUID#{$mdp_uuid}", $record );
    }

    // Config resolution — Bundle_Config_ID is a manually-added column, not part of
    // the standard MDP export. A team member fills in the WP post ID of the target
    // wicket_mship_bcfg for each row before import.
    $config_post_id = $record['Bundle_Config_ID'] ?? '';
    if ( ! ctype_digit( (string) $config_post_id ) || (int) $config_post_id <= 0 ) {
      return $this->log_and_respond( 'error', "Missing or invalid Bundle_Config_ID for Membership Bundle UUID#{$mdp_uuid}", $record );
    }
    $config = new Membership_Bundle_Config( (int) $config_post_id );
    if ( $config->get_post_id() <= 0 ) {
      return $this->log_and_respond( 'error', "Bundle_Config_ID#{$config_post_id} does not resolve to a valid Membership Bundle Config for Membership Bundle UUID#{$mdp_uuid}", $record );
    }

    try {
      // sync_to_mdp: false — this bundle already exists in MDP (it's being imported
      // from there); creating it again would produce a duplicate MDP record.
      $bundle = Membership_Bundle::create(
        $record['Name'],
        (int) $config_post_id,
        $record['Organization_UUID'],
        $record['Owner_UUID'],
        $record['Starts_At'],
        false
      );
    } catch ( \RuntimeException $e ) {
      return $this->log_and_respond( 'error', "Membership_Bundle::create failed for Membership Bundle UUID#{$mdp_uuid}: {$e->getMessage()}", $record );
    }

    if ( empty( $bundle ) ) {
      return $this->log_and_respond( 'error', "Membership_Bundle::create returned null for Membership Bundle UUID#{$mdp_uuid}", $record );
    }

    // All dates are explicit in the CSV — override create()'s config-derived window.
    $date_window = [
      'starts_at' => Utilities::get_mdp_day_start( $record['Starts_At'] )->format( 'c' ),
      'ends_at'   => Utilities::get_mdp_day_end( $record['Ends_At'] )->format( 'c' ),
    ];

    if ( ! empty( $record['Expires_At'] ) ) {
      $expires_at = Utilities::get_mdp_day_end( $record['Expires_At'] )->format( 'c' );
    } else {
      // Same fallback Import_Controller uses: derive expires_at from ends_at + the
      // config's grace period when the CSV doesn't supply one explicitly.
      $grace_period_days = $config->get_late_fee_window_days();
      $expires_date = date( 'Y-m-d', strtotime( $record['Ends_At'] . " + {$grace_period_days} days" ) );
      $expires_at = Utilities::get_mdp_day_end( $expires_date )->format( 'c' );
    }

    $dates_ok = $bundle->set_dates( [
      'starts_at'  => $date_window['starts_at'],
      'ends_at'    => $date_window['ends_at'],
      'expires_at' => $expires_at,
    ] );

    // Derive status from dates, matching Import_Controller::get_status() — the MDP export
    // only ever distinguishes active/inactive, so there is no richer status column to trust.
    // This can only yield active/delayed/expired; a bundle that was manually cancelled before
    // its natural end date will import as active or expired instead — an accepted limitation
    // since MDP itself doesn't retain that distinction either.
    $status = $this->derive_status( $date_window['starts_at'], $date_window['ends_at'], $expires_at );

    if ( ! $dates_ok || ! $bundle->set_membership_status( $status ) ) {
      return $this->log_and_respond( 'error', "Failed to apply CSV dates/status to bundle post#{$bundle->post_id} (Membership Bundle UUID#{$mdp_uuid})", $record );
    }

    $this->resync_bundle_subscription( $bundle, $date_window, $expires_at, $status );

    if ( ! empty( $record['External_ID'] ) ) {
      update_post_meta( $bundle->post_id, 'membership_bundle_external_id', $record['External_ID'] );
    }

    // Seed the MDP UUID last, only after every prior step succeeded — this is what
    // makes the bundle skip-safe (bundle_exists()) on a re-run. A row that fails
    // before this point never reached MDP linkage and will retry as a fresh post.
    update_post_meta( $bundle->post_id, 'membership_bundle_mdp_uuid', $mdp_uuid );

    return $this->log_and_respond( 'created', "Membership Bundle created: post#{$bundle->post_id}, Membership Bundle UUID#{$mdp_uuid}", $record, $bundle->post_id );
  }

  /**
   * Derive membership status from a start/end/expires date window. Mirrors
   * Import_Controller::get_status() — only yields active/delayed/expired.
   */
  private function derive_status( $starts_at, $ends_at, $expires_at ) {
    $starts_at = strtotime( $starts_at );
    $ends_at = strtotime( $ends_at );
    $expires_at = strtotime( $expires_at );
    if ( current_time( 'timestamp' ) >= $starts_at && current_time( 'timestamp' ) < $ends_at ) {
      return Wicket_Memberships::STATUS_ACTIVE;
    } else if ( current_time( 'timestamp' ) >= $ends_at && current_time( 'timestamp' ) < $expires_at ) {
      return Wicket_Memberships::STATUS_ACTIVE;
    } else if ( $starts_at > current_time( 'timestamp' ) ) {
      return Wicket_Memberships::STATUS_DELAYED;
    } else if ( $ends_at < current_time( 'timestamp' ) ) {
      return Wicket_Memberships::STATUS_EXPIRED;
    }
  }

  /**
   * True if a membership bundle with this MDP UUID already exists locally.
   */
  public function bundle_exists( $mdp_uuid ) {
    $args = [
      'post_type'   => Helper::get_membership_bundle_cpt_slug(),
      'post_status' => 'any',
      'meta_query'  => [
        [
          'key'     => 'membership_bundle_mdp_uuid',
          'value'   => $mdp_uuid,
          'compare' => '=',
        ],
      ],
      'posts_per_page' => 1,
      'fields'         => 'ids',
    ];
    $query = new \WP_Query( $args );
    return $query->have_posts();
  }

  /**
   * Align the bundle's WC subscription dates and status to the CSV-imported values.
   * create_bundle_subscription() (run inside Membership_Bundle::create()) derives dates
   * from the config and always starts the subscription pending, so both diverge from the
   * CSV once set_dates()/set_membership_status() override the bundle post.
   */
  private function resync_bundle_subscription( Membership_Bundle $bundle, array $date_window, $expires_at, $status ) {
    $subscription_id = $bundle->get_subscription_id();
    if ( ! $subscription_id ) {
      return;
    }
    $subscription = wcs_get_subscription( $subscription_id );
    if ( ! $subscription ) {
      return;
    }

    $next_payment_mysqltime = date( 'Y-m-d H:i:s', strtotime( $date_window['ends_at'] ) );
    $end_mysqltime = date( 'Y-m-d H:i:s', strtotime( $expires_at ) );

    $dates = [ 'next_payment' => $next_payment_mysqltime, 'end' => $end_mysqltime ];
    if ( $dates['next_payment'] === $dates['end'] ) {
      $dates['end'] = date( 'Y-m-d H:i:s', strtotime( $dates['end'] . ' + 1 second' ) );
    }
    $subscription->update_dates( $dates );

    // derive_status() only yields active/delayed/expired. active/expired map directly
    // to WCS statuses; delayed bundles keep the subscription pending — no billing
    // should fire before the bundle's start date arrives.
    $subscription_status_map = [
      Wicket_Memberships::STATUS_ACTIVE  => 'active',
      Wicket_Memberships::STATUS_EXPIRED => 'expired',
    ];
    if ( isset( $subscription_status_map[ $status ] ) ) {
      $subscription->update_status( $subscription_status_map[ $status ] );
    }

    $subscription->save();
  }

  /**
   * Log the outcome under the import-specific source tag and return the REST response.
   */
  private function log_and_respond( $outcome, $message, array $record, $bundle_id = null ) {
    $level = $outcome === 'error' ? 'error' : 'info';
    Wicket()->log( $level, $message, [
      'source'                  => 'wicket-membership-plugin-import',
      'outcome'                 => $outcome,
      'membership_bundle_uuid'  => $record['Membership_Bundle_UUID'] ?? '',
      'name'                    => $record['Name'] ?? '',
    ] );

    if ( $outcome === 'error' ) {
      return new \WP_REST_Response( [ 'error' => $message ] );
    }

    // 'skipped' (already imported) and 'created' both report success, but distinguish
    // them via 'outcome' so callers (and re-run reports) can tell the two apart.
    return new \WP_REST_Response( [ 'success' => $message, 'outcome' => $outcome, 'bundle_id' => $bundle_id ] );
  }

}
