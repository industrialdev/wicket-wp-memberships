<?php
namespace Wicket_Memberships;

/**
 * Represents a Membership Bundle Config CPT record.
 *
 * Combines the date/cycle/renewal-window logic of Membership_Config with the
 * approval and renewal-type logic of Membership_Tier, scoped specifically for
 * Membership Bundles. Renewal type is limited to 'subscription' and
 * 'form_page'.
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
 *     eligible_tier_ids      int[]   Post IDs of eligible Membership_Tier posts.
 *                                    Empty (default) = all active individual
 *                                    tiers eligible.
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
    // get_the_title( 0 ) falls back to the global $post.
    return $this->post_id ? get_the_title( $this->post_id ) : '';
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
   * Not used for bundles: no bundle renewal path charges this product. It is only
   * passed through in grace-period callout data. Bundle late fees are applied by
   * client code via wicket_mship_bundle_renewal_line_item_price.
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

    // Pass the raw date straight through; collapsing via strtotime()/date('Y-m-d')
    // first reads the UTC day and shifts the boundary a day forward.
    foreach ( $seasons as $key => $season ) {
      $seasons[ $key ]['start_date'] = Utilities::get_mdp_day_start( $season['start_date'] )->format( 'c' );
      $seasons[ $key ]['end_date']   = Utilities::get_mdp_day_end( $season['end_date'] )->format( 'c' );
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
   * Check whether a renewal is being attempted outside the renewal window.
   *
   * Mirrors Membership_Config::is_valid_renewal_date(): a value is returned only when the
   * renewal is NOT allowed (before the early-renewal window opens).
   *
   * @param array       $membership Current membership data array (needs membership_ends_at).
   * @param string|null $date       Date to test instead of now (any strtotime() format).
   * @return string|null The window's opening (UTC ISO 8601) when blocked, null when renewal is allowed.
   */
  public function is_valid_renewal_date( $membership, $date = null ) {
    if ( $date ) {
      $current_timestamp = strtotime( $date );
    } else {
      $current_timestamp = time();
    }

    // The window opens N days before the CURRENT membership's end.
    $early_renew_at = $this->get_early_renew_at_for_end( $membership['membership_ends_at'] );

    if ( $early_renew_at !== null && $current_timestamp < strtotime( $early_renew_at ) ) {
      return $early_renew_at;
    }
  }

  /**
   * The early-renewal opening for a membership ending at $end_date_iso.
   *
   * MDP midnight of the end's MDP calendar day minus the renewal window. Mirrors
   * Membership_Config::get_early_renew_at_for_end().
   *
   * @param string $end_date_iso Membership end (ISO 8601, typically MDP end-of-day as UTC).
   * @return string|null UTC ISO 8601, or null when no renewal window is configured.
   */
  public function get_early_renew_at_for_end( $end_date_iso ) {
    $early_renewal_in_days = $this->get_renewal_window_days();
    if ( empty( $early_renewal_in_days ) ) {
      return null;
    }

    $early_renew_date_string = $this->mdp_local_ymd( $end_date_iso, '-' . $early_renewal_in_days . ' days' );
    return Utilities::get_mdp_day_start( $early_renew_date_string )->format( 'c' );
  }

  /**
   * Convert an ISO datetime to its Y-m-d in the MDP timezone, optionally after a relative
   * modify ('+5 days', '-30 days'). Mirrors Membership_Config::mdp_local_ymd(): a bare Y-m-d
   * is already an MDP calendar day.
   *
   * @param string $iso_date ISO 8601 datetime (typically a get_mdp_day_* result) or a bare Y-m-d.
   * @param string $modify   Optional relative modify applied in the MDP timezone.
   * @return string Y-m-d in the MDP timezone.
   */
  private function mdp_local_ymd( $iso_date, $modify = '' ) {
    $mdp_timezone = Utilities::get_mdp_timezone();
    if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', trim( (string) $iso_date ) ) ) {
      $dt = new \DateTime( trim( $iso_date ), $mdp_timezone );
    } else {
      $dt = new \DateTime( $iso_date );
      $dt->setTimezone( $mdp_timezone );
    }
    if ( $modify !== '' ) {
      $dt->modify( $modify );
    }
    return $dt->format( 'Y-m-d' );
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
      // Count the period from the start's MDP calendar day; its UTC day is a day early east of UTC.
      $the_end_date = $this->mdp_local_ymd( $dates['start_date'], '+' . $period_count . ' ' . $period_type );

      // End-date alignment snaps the calculated end to a predictable day within the
      // month so admins can batch-process renewals on a fixed schedule. Only applies
      // to year/month periods — day periods are already precise enough.
      if ( in_array( $period_type, [ 'year', 'month' ] )
        && ( ! empty( $cycle_data['anniversary_data']['align_end_dates_enabled'] ) && $cycle_data['anniversary_data']['align_end_dates_enabled'] !== false )
      ) {
        switch ( $cycle_data['anniversary_data']['align_end_dates_type'] ) {
          case 'first-day-of-month':
            $the_end_date = date( 'Y-m-1', strtotime( $the_end_date ) );
            break;
          case '15th-of-month':
            $the_end_date = date( 'Y-m-15', strtotime( $the_end_date ) );
            break;
          case 'last-day-of-month':
            $the_end_date = date( 'Y-m-t', strtotime( $the_end_date ) );
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
    // Window day maths runs on the MDP-local day: a UTC Y-m-d of an MDP day boundary is
    // off by one for zones behind UTC.
    $grace_period_in_days = $this->get_late_fee_window_days();
    if ( ! empty( $grace_period_in_days ) ) {
      $expires_date_string = $this->mdp_local_ymd( $dates['end_date'], '+' . $grace_period_in_days . ' days' );
      $dates['expires_at'] = Utilities::get_mdp_day_end( $expires_date_string )->format( 'c' );
    }

    // Renewal window opens a self-serve renewal portal before the period ends so the
    // member can renew without a lapse. Counted back from this term's own end; a renewal
    // anchored to the previous end would store an opening that has already passed.
    $early_renew_at = $this->get_early_renew_at_for_end( $dates['end_date'] );
    if ( $early_renew_at !== null ) {
      $dates['early_renew_at'] = $early_renew_at;
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
  // Eligible tiers
  // -------------------------------------------------------------------------

  /**
   * Get the post IDs of individual membership tiers eligible for this bundle config.
   *
   * @return int[] Empty array means all active individual tiers are eligible (fallback rule).
   */
  public function get_eligible_tier_ids() {
    if ( isset( $this->bundle_config_data['eligible_tier_ids'] ) && is_array( $this->bundle_config_data['eligible_tier_ids'] ) ) {
      return array_map( 'intval', $this->bundle_config_data['eligible_tier_ids'] );
    }

    return [];
  }

  /**
   * Check whether the given tier is eligible for this bundle config.
   *
   * An empty eligible-tiers list means every active individual tier is eligible
   * (the field's own help text: "If no tiers are selected, all membership tiers
   * are eligible for this configuration.").
   *
   * @param int $tier_post_id Post ID of the individual Membership_Tier to check.
   * @return bool
   */
  public function is_tier_eligible( $tier_post_id ) {
    $eligible_tier_ids = $this->get_eligible_tier_ids();

    return empty( $eligible_tier_ids ) || in_array( (int) $tier_post_id, $eligible_tier_ids, true );
  }

  /**
   * Null-safe eligibility check against a bundle's linked config.
   *
   * Membership_Bundle::get_config() returns false when a bundle has no config
   * linked, or the linked config post is invalid — treat that as "all tiers
   * eligible" rather than calling is_tier_eligible() on a non-object.
   *
   * @param Membership_Bundle $bundle       Bundle to resolve the config from.
   * @param int               $tier_post_id Post ID of the individual Membership_Tier to check.
   * @return bool
   */
  public static function is_tier_eligible_for_bundle( Membership_Bundle $bundle, $tier_post_id ) {
    $config = $bundle->get_config();

    return ! $config instanceof self || $config->is_tier_eligible( $tier_post_id );
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
      // Day after the current end in MDP time; the UTC day of an MDP end-of-day is a day late west of UTC.
      $utc_date = Utilities::get_mdp_day_start( $membership['membership_ends_at'] );
      $utc_date->modify( '+1 day' );
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
      // Day after the current end in MDP time; the UTC day of an MDP end-of-day is a day late west of UTC.
      $utc_date = Utilities::get_mdp_day_start( $membership['membership_ends_at'] );
      $utc_date->modify( '+1 day' );
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
    $mdp_timezone = Utilities::get_mdp_timezone();

    $is_new_membership = empty( $membership ) || ! isset( $membership['membership_ends_at'] );
    $seasons           = $this->get_calendar_seasons();

    if ( ! $is_new_membership ) {
      // Day after the current end in MDP time; the UTC day of an MDP end-of-day is a day late west of UTC.
      $membership_start_dt = Utilities::get_mdp_day_start( $membership['membership_ends_at'] );
      $membership_start_dt->modify( '+1 day' );
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
