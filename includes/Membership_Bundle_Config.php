<?php
namespace Wicket_Memberships;

/**
 * Represents a Membership Bundle Config CPT record.
 *
 * Combines the date/cycle/renewal-window logic of Membership_Config with the
 * approval and renewal-type logic of Membership_Tier, scoped specifically for
 * Membership Bundles. Renewal type is limited to 'subscription' and 'form_page'.
 *
 * Meta layout
 * -----------
 * Individual meta keys (matching Membership_Config pattern):
 *   renewal_window_data  — array
 *   late_fee_window_data — array
 *   cycle_data           — array
 *
 * Single serialised meta key (matching Membership_Tier pattern):
 *   bundle_config_data   — array containing:
 *     renewal_type           string  'subscription' | 'form_page'
 *     renewal_form_page_id   int
 *     approval_required      int     1 | 0
 *     grant_owner_assignment int     1 | 0
 *     approval_email_recipient string
 *     approval_callout_data  array
 */
class Membership_Bundle_Config {

  private $post_id;
  private $renewal_window_data;
  private $late_fee_window_data;
  private $cycle_data;
  private $bundle_config_data;

  public function __construct( $post_id ) {
    if ( ! get_post( $post_id ) ) {
      Wicket()->log()->error( 'Membership_Bundle_Config: Invalid post ID', [ 'source' => 'wicket-memberships', 'post_id' => $post_id ] );
      $this->post_id            = 0;
      $this->renewal_window_data  = [];
      $this->late_fee_window_data = [];
      $this->cycle_data           = [];
      $this->bundle_config_data    = [];
      return;
    }

    if ( get_post_type( $post_id ) !== Helper::get_membership_bundle_config_cpt_slug() ) {
      Wicket()->log()->error( 'Membership_Bundle_Config: Invalid post type', [ 'source' => 'wicket-memberships', 'post_id' => $post_id, 'post_type' => get_post_type( $post_id ) ] );
      $this->post_id            = 0;
      $this->renewal_window_data  = [];
      $this->late_fee_window_data = [];
      $this->cycle_data           = [];
      $this->bundle_config_data    = [];
      return;
    }

    $this->post_id             = $post_id;
    $this->renewal_window_data  = $this->get_renewal_window_data();
    $this->late_fee_window_data = $this->get_late_fee_window_data();
    $this->cycle_data           = $this->get_cycle_data();
    $this->bundle_config_data    = $this->get_bundle_config_data();
  }

  // -------------------------------------------------------------------------
  // Identifiers
  // -------------------------------------------------------------------------

  /**
   * Get the post ID for this bundle config.
   *
   * @return int
   */
  public function get_post_id() {
    return $this->post_id;
  }

  /**
   * Get the title of this bundle config post.
   *
   * @return string
   */
  public function get_title() {
    return get_the_title( $this->post_id );
  }

  /**
   * Get the meta field name used to store bundle config data.
   *
   * @return string
   */ 
  public static function get_meta_bundle_config_data_field_name() {
    return 'bundle_config_data';
  }

  // -------------------------------------------------------------------------
  // Renewal window
  // -------------------------------------------------------------------------

  /**
   * Get the renewal window days.
   *
   * @return int|false
   */
  public function get_renewal_window_days() {
    if ( isset( $this->renewal_window_data['days_count'] ) && is_numeric( $this->renewal_window_data['days_count'] ) ) {
      return intval( $this->renewal_window_data['days_count'] );
    }

    return false;
  }

  /**
   * Get the renewal window callout header.
   *
   * @param string $lang Language code.
   * @return string|false
   */
  public function get_renewal_window_callout_header( $lang = 'en' ) {
    if ( isset( $this->renewal_window_data['locales'][ $lang ]['callout_header'] ) ) {
      return sanitize_text_field( $this->renewal_window_data['locales'][ $lang ]['callout_header'] );
    }

    return false;
  }

  /**
   * Get the renewal window callout content.
   *
   * @param string $lang Language code.
   * @return string|false
   */
  public function get_renewal_window_callout_content( $lang = 'en' ) {
    if ( isset( $this->renewal_window_data['locales'][ $lang ]['callout_content'] ) ) {
      return sanitize_text_field( $this->renewal_window_data['locales'][ $lang ]['callout_content'] );
    }

    return false;
  }

  /**
   * Get the renewal window callout button label.
   *
   * @param string $lang Language code.
   * @return string|false
   */
  public function get_renewal_window_callout_button_label( $lang = 'en' ) {
    if ( isset( $this->renewal_window_data['locales'][ $lang ]['callout_button_label'] ) ) {
      return sanitize_text_field( $this->renewal_window_data['locales'][ $lang ]['callout_button_label'] );
    }

    return false;
  }

  // -------------------------------------------------------------------------
  // Late fee / grace period window
  // -------------------------------------------------------------------------

  /**
   * Get the late fee window product ID.
   *
   * TODO [Product ID concerns]: Temporary implementation. The late-fee product
   * concept for Membership Bundle Configs has not yet been fully defined at the
   * product level. This currently reads product_id directly from
   * late_fee_window_data meta (matching Membership_Config behaviour) and may
   * need to be derived from the bundle's associated tier products instead.
   * Review and replace before production use.
   *
   * @return int|false
   */
  public function get_late_fee_window_product_id() {
    if ( isset( $this->late_fee_window_data['product_id'] ) ) {
      return intval( $this->late_fee_window_data['product_id'] );
    }

    return false;
  }

  /**
   * Get the late fee window days.
   *
   * @return int|false
   */
  public function get_late_fee_window_days() {
    if ( isset( $this->late_fee_window_data['days_count'] ) && is_numeric( $this->late_fee_window_data['days_count'] ) ) {
      return intval( $this->late_fee_window_data['days_count'] );
    }

    return false;
  }

  /**
   * Get the late fee window callout header.
   *
   * @param string $lang Language code.
   * @return string|false
   */
  public function get_late_fee_window_callout_header( $lang = 'en' ) {
    if ( isset( $this->late_fee_window_data['locales'][ $lang ]['callout_header'] ) ) {
      return sanitize_text_field( $this->late_fee_window_data['locales'][ $lang ]['callout_header'] );
    }

    return false;
  }

  /**
   * Get the late fee window callout content.
   *
   * @param string $lang Language code.
   * @return string|false
   */
  public function get_late_fee_window_callout_content( $lang = 'en' ) {
    if ( isset( $this->late_fee_window_data['locales'][ $lang ]['callout_content'] ) ) {
      return sanitize_text_field( $this->late_fee_window_data['locales'][ $lang ]['callout_content'] );
    }

    return false;
  }

  /**
   * Get the late fee window callout button label.
   *
   * @param string $lang Language code.
   * @return string|false
   */
  public function get_late_fee_window_callout_button_label( $lang = 'en' ) {
    if ( isset( $this->late_fee_window_data['locales'][ $lang ]['callout_button_label'] ) ) {
      return sanitize_text_field( $this->late_fee_window_data['locales'][ $lang ]['callout_button_label'] );
    }

    return false;
  }

  // -------------------------------------------------------------------------
  // Cycle / dates
  // -------------------------------------------------------------------------

  /**
   * Get the cycle data array.
   *
   * @return array|false
   */
  public function get_cycle_data() {
    $cycle_data = get_post_meta( $this->post_id, 'cycle_data', true );

    if ( is_array( $cycle_data ) ) {
      return $cycle_data;
    }

    return false;
  }

  /**
   * Get the cycle type.
   *
   * @return string|false 'calendar' | 'anniversary', or false.
   */
  public function get_cycle_type() {
    if ( isset( $this->cycle_data['cycle_type'] ) && in_array( $this->cycle_data['cycle_type'], [ 'calendar', 'anniversary' ] ) ) {
      return $this->cycle_data['cycle_type'];
    }

    return false;
  }

  /**
   * Get the calendar seasons from cycle data.
   *
   * Converts start_date and end_date to ISO 8601 using the WordPress timezone.
   *
   * @return array|false
   */
  public function get_calendar_seasons() {
    if ( ! isset( $this->cycle_data['calendar_items'] ) || ! is_array( $this->cycle_data['calendar_items'] ) ) {
      return false;
    }

    $seasons = $this->cycle_data['calendar_items'];

    // Raw stored dates are plain Y-m-d strings with no timezone. Convert to full
    // ISO 8601 with the MDP timezone offset here so all callers receive a consistent
    // format and never need to guess the implicit timezone.
    foreach ( $seasons as $key => $season ) {
      $start_date = date( 'Y-m-d', strtotime( $season['start_date'] ) );
      $end_date   = date( 'Y-m-d', strtotime( $season['end_date'] ) );

      $seasons[ $key ]['start_date'] = Utilities::get_mdp_day_start( $start_date )->format( 'c' );
      $seasons[ $key ]['end_date']   = Utilities::get_mdp_day_end( $end_date )->format( 'c' );
    }

    return $seasons;
  }

  /**
   * Get the current calendar season based on today's date.
   *
   * @return array|false
   */
  public function get_current_calendar_season() {
    $seasons = $this->get_calendar_seasons();

    if ( empty( $seasons ) ) {
      return false;
    }

    $current_time = strtotime( 'now' );

    foreach ( $seasons as $season ) {
      if ( $season['active'] &&
        ( $current_time >= strtotime( $season['start_date'] ) && $current_time <= strtotime( $season['end_date'] ) )
      ) {
        return $season;
      }
    }

    return false;
  }

  /**
   * Get the period count and type for the membership cycle.
   *
   * @return array { period_count: int, period_type: string }
   */
  public function get_period_data() {
    if ( $this->get_cycle_type() == 'anniversary' ) {
      $period['period_count'] = $this->cycle_data['anniversary_data']['period_count'];
      $period['period_type']  = $this->cycle_data['anniversary_data']['period_type'];
    } else {
      $period['period_count'] = 1;
      $period['period_type']  = 'year';
    }

    return $period;
  }

  /**
   * Check whether the given date falls within the valid renewal window.
   *
   * @param array       $membership Membership data array.
   * @param string|null $date       Optional date string; defaults to current time.
   * @return mixed Early-renew date string if outside window, void otherwise.
   */
  public function is_valid_renewal_date( $membership, $date = null ) {
    if ( $date ) {
      $current_timestamp = strtotime( $date );
    } else {
      $current_timestamp = current_datetime()->getTimestamp();
    }

    $dates = $this->get_membership_dates( $membership );

    // A renewal is only valid inside the window between early_renew_at and expires_at.
    // Outside that window the member is either too early (before the window opens) or
    // too late (past the grace period). Returning the early_renew_at date tells the
    // caller when the window will open rather than just returning a generic false.
    if ( ( $current_timestamp <= strtotime( $dates['early_renew_at'] ) ) || ( $current_timestamp >= strtotime( $dates['expires_at'] ) ) ) {
      return $membership['membership_early_renew_at'];
    }
  }

  /**
   * Calculate membership start, end, early-renewal, and expiration dates.
   *
   * Follows the same logic as Membership_Config::get_membership_dates().
   *
   * @param array $membership Optional membership data array (empty for new memberships).
   *   - `membership_ends_at` (string) — ISO 8601 end date of the current period; used to
   *     calculate the renewal start as the following day.
   *   - `start_date` (string) — ISO 8601 date override for a new membership start. When
   *     provided on a new membership (no `membership_ends_at`), all date calculations are
   *     anchored to this date instead of 'now'.
   * @return array { start_date, end_date, expires_at?, early_renew_at? }
   */
  public function get_membership_dates( $membership = [] ) {
    $cycle_data = $this->get_cycle_data();

    // Anniversary cycle: end date is calculated relative to the individual member's
    // start date, so each member has their own personal period.
    if ( $cycle_data['cycle_type'] == 'anniversary' ) {
      $dates['start_date'] = $this->get_anniversary_start_date( $membership );
      $period_count = ! empty( $cycle_data['anniversary_data']['period_count'] ) && is_numeric( $cycle_data['anniversary_data']['period_count'] )
        ? $cycle_data['anniversary_data']['period_count'] : 1;
      $period_type = ! in_array( $cycle_data['anniversary_data']['period_type'], [ 'year', 'month', 'day' ] )
        ? 'year' : $cycle_data['anniversary_data']['period_type'];
      $the_end_date = date( 'Y-m-d', strtotime( $dates['start_date'] . '+' . $period_count . ' ' . $period_type ) );

      // End-date alignment snaps the calculated end to a predictable day within the
      // month so admins can batch-process renewals on a fixed schedule. Only applies
      // to year/month periods — day periods are already precise enough.
      if ( in_array( $period_type, [ 'year', 'month' ] )
        && ( ! empty( $cycle_data['anniversary_data']['align_end_dates_enabled'] ) && $cycle_data['anniversary_data']['align_end_dates_enabled'] !== false )
      ) {
        switch ( $cycle_data['anniversary_data']['align_end_dates_type'] ) {
          case 'first-day-of-month':
            $the_end_date = date( 'Y-m-1', strtotime( $dates['start_date'] . '+' . $period_count . ' ' . $period_type ) );
            break;
          case '15th-of-month':
            $the_end_date = date( 'Y-m-15', strtotime( $dates['start_date'] . '+' . $period_count . ' ' . $period_type ) );
            break;
          case 'last-day-of-month':
            $the_end_date = date( 'Y-m-t', strtotime( $dates['start_date'] . '+' . $period_count . ' ' . $period_type ) );
            break;
        }
      }

      $dates['end_date'] = Utilities::get_mdp_day_end( $the_end_date )->format( 'c' );
    } else {
      $dates['start_date'] = $this->get_seasonal_start_date( $membership );
      $dates['end_date']   = $this->get_seasonal_end_date( $membership );
    }

    // Grace period extends the expiration beyond the nominal end date, giving members
    // time to renew after lapsing without immediately losing access in MDP.
    $grace_period_in_days = $this->get_late_fee_window_days();
    if ( ! empty( $grace_period_in_days ) ) {
      $end_date_ymd        = ( new \DateTime( $dates['end_date'] ) )->format( 'Y-m-d' );
      $expires_at_utc      = new \DateTime( $end_date_ymd, new \DateTimeZone( 'UTC' ) );
      $expires_date_string = $expires_at_utc->modify( '+' . $grace_period_in_days . ' days' )->format( 'Y-m-d' );
      $dates['expires_at'] = Utilities::get_mdp_day_end( $expires_date_string )->format( 'c' );
    }

    // Renewal window opens a self-serve renewal portal before the period ends so
    // the member can renew without a lapse. For renewals, anchor to the current
    // period end; for new memberships (no current end), fall back to the calculated
    // next end date.
    $early_renewal_in_days = $this->get_renewal_window_days();
    if ( ! empty( $early_renewal_in_days ) ) {
      $early_renew_base        = ! empty( $membership['membership_ends_at'] )
        ? $membership['membership_ends_at']
        : $dates['end_date'];
      $end_date_ymd            = ( new \DateTime( $early_renew_base ) )->format( 'Y-m-d' );
      $early_renew_at_utc      = new \DateTime( $end_date_ymd, new \DateTimeZone( 'UTC' ) );
      $early_renew_date_string = $early_renew_at_utc->modify( '-' . $early_renewal_in_days . ' days' )->format( 'Y-m-d' );
      $dates['early_renew_at'] = Utilities::get_mdp_day_start( $early_renew_date_string )->format( 'c' );
    }

    return $dates;
  }

  // -------------------------------------------------------------------------
  // Renewal type  (limited to 'subscription' and 'form_page')
  // -------------------------------------------------------------------------

  /**
   * Get the renewal type.
   *
   * @return string|false 'subscription' | 'form_page', or false if not set.
   */
  public function get_renewal_type() {
    if ( ! empty( $this->bundle_config_data['renewal_type'] ) ) {
      return $this->bundle_config_data['renewal_type'];
    }

    return false;
  }

  /**
   * Check whether the renewal type is subscription-based.
   *
   * @return bool
   */
  public function is_renewal_subscription() {
    return $this->get_renewal_type() === 'subscription';
  }

  /**
   * Check whether the renewal type is form-page-based.
   *
   * @return bool
   */
  public function is_renewal_form_page() {
    return $this->get_renewal_form_page_id() !== false;
  }

  /**
   * Get the renewal form page post ID.
   *
   * @return int|false
   */
  public function get_renewal_form_page_id() {
    if ( isset( $this->bundle_config_data['renewal_form_page_id'] ) && $this->bundle_config_data['renewal_form_page_id'] !== 0 ) {
      return intval( $this->bundle_config_data['renewal_form_page_id'] );
    }

    return false;
  }

  // -------------------------------------------------------------------------
  // Approval
  // -------------------------------------------------------------------------

  /**
   * Check whether approval is required for this bundle config.
   *
   * @return int|false 1 if required, false otherwise.
   *
   * @refactor-candidate Return type should be bool (true/false) rather than int|false.
   *   The meta value is stored as int 1 and returned as-is, which makes the return type
   *   inconsistent with a method named is_*(). Before changing, audit all callers for
   *   strict comparisons (=== 1, !== 1) that would silently break if the return became true.
   */
  public function is_approval_required() {
    if ( isset( $this->bundle_config_data['approval_required'] ) && $this->bundle_config_data['approval_required'] == 1 ) {
      return $this->bundle_config_data['approval_required'];
    }

    return false;
  }

  /**
   * Check whether grant owner assignment is enabled for this bundle config.
   *
   * @return int|false 1 if enabled, false otherwise.
   *
   * @refactor-candidate Return type should be bool (true/false) rather than int|false.
   *   Same pattern as is_approval_required() — audit callers for strict int comparisons
   *   before changing.
   */
  public function is_grant_owner_assignment() {
    if ( isset( $this->bundle_config_data['grant_owner_assignment'] ) && $this->bundle_config_data['grant_owner_assignment'] == 1 ) {
      return $this->bundle_config_data['grant_owner_assignment'];
    }

    return false;
  }

  /**
   * Get the approval email recipient.
   *
   * @return string|false
   */
  public function get_approval_email() {
    if ( ! empty( $this->bundle_config_data['approval_email_recipient'] ) ) {
      return $this->bundle_config_data['approval_email_recipient'];
    }

    return false;
  }

  /**
   * Get the approval callout header.
   *
   * @param string $lang Language code.
   * @return string|false
   */
  public function get_approval_callout_header( $lang = 'en' ) {
    $approval_callout_data = $this->get_approval_callout_data();

    if ( isset( $approval_callout_data['locales'][ $lang ]['callout_header'] ) ) {
      return $approval_callout_data['locales'][ $lang ]['callout_header'];
    }

    return false;
  }

  /**
   * Get the approval callout content.
   *
   * @param string $lang Language code.
   * @return string|false
   */
  public function get_approval_callout_content( $lang = 'en' ) {
    $approval_callout_data = $this->get_approval_callout_data();

    if ( isset( $approval_callout_data['locales'][ $lang ]['callout_content'] ) ) {
      return $approval_callout_data['locales'][ $lang ]['callout_content'];
    }

    return false;
  }

  /**
   * Get the approval callout button label.
   *
   * @param string $lang Language code.
   * @return string|false
   */
  public function get_approval_callout_button_label( $lang = 'en' ) {
    $approval_callout_data = $this->get_approval_callout_data();

    if ( isset( $approval_callout_data['locales'][ $lang ]['callout_button_label'] ) ) {
      return $approval_callout_data['locales'][ $lang ]['callout_button_label'];
    }

    return false;
  }

  // -------------------------------------------------------------------------
  // Write
  // -------------------------------------------------------------------------

  /**
   * Persist updated bundle config data (renewal type and approval settings) to post meta.
   * Also refreshes the in-memory cache so subsequent reads on this instance reflect the change.
   *
   * @param array $new_bundle_config_data Replacement array for bundle_config_data meta.
   */
  public function update_bundle_config_data( array $new_bundle_config_data ) {
    update_post_meta( $this->post_id, self::get_meta_bundle_config_data_field_name(), $new_bundle_config_data );
    $this->bundle_config_data = $new_bundle_config_data;
  }

  // -------------------------------------------------------------------------
  // Private helpers
  // -------------------------------------------------------------------------

  /**
   * Load renewal window data from post meta.
   *
   * @return array|false
   */
  private function get_renewal_window_data() {
    $data = get_post_meta( $this->post_id, 'renewal_window_data', true );

    return is_array( $data ) ? $data : false;
  }

  /**
   * Load late fee window data from post meta.
   *
   * @return array|false
   */
  private function get_late_fee_window_data() {
    $data = get_post_meta( $this->post_id, 'late_fee_window_data', true );

    return is_array( $data ) ? $data : false;
  }

  /**
   * Load bundle config data (renewal type, approval settings) from post meta.
   *
   * @return array|false
   */
  private function get_bundle_config_data() {
    $data = get_post_meta( $this->post_id, self::get_meta_bundle_config_data_field_name(), true );

    return is_array( $data ) ? $data : false;
  }

  /**
   * Get the approval callout data sub-array from bundle config data.
   *
   * @return array|false
   */
  private function get_approval_callout_data() {
    if ( isset( $this->bundle_config_data['approval_callout_data'] ) && is_array( $this->bundle_config_data['approval_callout_data'] ) ) {
      return $this->bundle_config_data['approval_callout_data'];
    }

    return false;
  }

  /**
   * Calculate the anniversary start date for a new or renewing membership.
   *
   * @param array $membership Membership data array (empty for new memberships).
   * @return string ISO 8601 date string.
   */
  private function get_anniversary_start_date( $membership = [] ) {
    $is_new_membership = empty( $membership ) || ! isset( $membership['membership_ends_at'] );

    if ( ! $is_new_membership ) {
      $datetime_string = strtotime( $membership['membership_ends_at'] . '+1 day' );
      $date            = date( 'Y-m-d', $datetime_string );
      $utc_date        = Utilities::get_mdp_day_start( $date );
    } elseif ( ! empty( $membership['start_date'] ) ) {
      $utc_date = Utilities::get_mdp_day_start( $membership['start_date'] );
    } else {
      $utc_date = Utilities::get_mdp_day_start( 'now' );
    }

    return $utc_date->format( 'c' );
  }

  /**
   * Calculate the seasonal (calendar) start date for a new or renewing membership.
   *
   * @param array $membership Membership data array (empty for new memberships).
   * @return string ISO 8601 date string.
   */
  private function get_seasonal_start_date( $membership = [] ) {
    $is_new_membership = empty( $membership ) || ! isset( $membership['membership_ends_at'] );

    if ( ! $is_new_membership ) {
      $datetime_string = strtotime( $membership['membership_ends_at'] . '+1 day' );
      $date            = date( 'Y-m-d', $datetime_string );
      $utc_date        = Utilities::get_mdp_day_start( $date );
    } elseif ( ! empty( $membership['start_date'] ) ) {
      $utc_date = Utilities::get_mdp_day_start( $membership['start_date'] );
    } else {
      $utc_date = Utilities::get_mdp_day_start( 'now' );
    }

    return $utc_date->format( 'c' );
  }

  /**
   * Calculate the seasonal (calendar) end date for a new or renewing membership.
   *
   * Compares the membership start date against configured seasons and uses the
   * season end date when one matches, falling back to start + 1 year.
   *
   * @param array $membership Membership data array (empty for new memberships).
   * @return string ISO 8601 date string.
   */
  private function get_seasonal_end_date( $membership = [] ) {
    $mdp_timezone = new \DateTimeZone( $_ENV['WICKET_MSHIP_MDP_TIMEZONE'] ?? 'UTC' );

    $is_new_membership = empty( $membership ) || ! isset( $membership['membership_ends_at'] );
    $seasons           = $this->get_calendar_seasons();

    if ( ! $is_new_membership ) {
      $datetime_string       = strtotime( $membership['membership_ends_at'] . '+1 day' );
      $date                  = date( 'Y-m-d', $datetime_string );
      $membership_start_dt   = Utilities::get_mdp_day_start( $date );
      $membership_start_dt->setTimezone( $mdp_timezone );
      $membership_default_end_dt = clone $membership_start_dt;
      $membership_default_end_dt->modify( '+1 year' );
    } elseif ( ! empty( $membership['start_date'] ) ) {
      $membership_start_dt = Utilities::get_mdp_day_start( $membership['start_date'] );
      $membership_start_dt->setTimezone( $mdp_timezone );
      $membership_default_end_dt = clone $membership_start_dt;
      $membership_default_end_dt->modify( '+1 year' );
    } else {
      $membership_start_dt = Utilities::get_mdp_day_start( 'now' );
      $membership_start_dt->setTimezone( $mdp_timezone );
      $membership_default_end_dt = clone $membership_start_dt;
      $membership_default_end_dt->modify( '+1 year' );
    }

    $selected_end_dt = $membership_default_end_dt;

    foreach ( $seasons as $season ) {
      $season_start_utc = new \DateTime( $season['start_date'], new \DateTimeZone( 'UTC' ) );
      $season_start_mdp = $season_start_utc->setTimezone( $mdp_timezone );

      $season_end_utc = new \DateTime( $season['end_date'], new \DateTimeZone( 'UTC' ) );
      $season_end_mdp = $season_end_utc->setTimezone( $mdp_timezone );

      if ( $membership_start_dt >= $season_start_mdp && $membership_start_dt <= $season_end_mdp ) {
        $selected_end_dt = $season_end_mdp;
      }
    }

    $end_date_string = $selected_end_dt->format( 'c' );
    return Utilities::get_mdp_day_end( $end_date_string )->format( 'c' );
  }
}
