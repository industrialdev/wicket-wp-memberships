<?php
namespace Wicket_Memberships;

class Membership_Config {

  private $post_id;
  private $renewal_window_data;
  private $late_fee_window_data;
  private $cycle_data;

  public function __construct( $post_id ) {
    if ( ! get_post( $post_id ) ) {
      throw new \Exception( 'Invalid post ID' );
    }

    $this->post_id = $post_id;
    $this->renewal_window_data = $this->get_renewal_window_data();
    $this->late_fee_window_data = $this->get_late_fee_window_data();
    $this->cycle_data = $this->get_cycle_data();
	}

  /**
   * Get the membership config title
   */
  public function get_title() {
    return get_the_title( $this->post_id );
  }

  /**
   * Get the renewal window days
   *
   * @return int|bool Integer, false otherwise
   */
  public function get_renewal_window_days() {
    if ( isset( $this->renewal_window_data['days_count'] ) && is_numeric( $this->renewal_window_data['days_count'] ) ) {
      return intval( $this->renewal_window_data['days_count'] );
    }

    return false;
  }

  /**
   * Get the renewal callout header
   * @param string $lang, language code
   *
   * @return string|bool String, false otherwise
   */
  public function get_renewal_window_callout_header($lang = 'en') {
    if ( isset( $this->renewal_window_data['locales'][$lang]['callout_header'] ) ) {
      return sanitize_text_field( $this->renewal_window_data['locales'][$lang]['callout_header'] );
    }

    return false;
  }

  /**
   * Get the renewal callout header
   * @param string $lang, language code
   *
   * @return string|bool String, false otherwise
   */
  public function get_renewal_window_callout_content($lang = 'en') {
    if ( isset( $this->renewal_window_data['locales'][$lang]['callout_content'] ) ) {
      return sanitize_text_field( $this->renewal_window_data['locales'][$lang]['callout_content'] );
    }

    return false;
  }

  /**
   * Get the renewal callout button label
   * @param string $lang, language code
   *
   * @return string|bool String, false otherwise
   */
  public function get_renewal_window_callout_button_label($lang = 'en') {
    if ( isset( $this->renewal_window_data['locales'][$lang]['callout_button_label'] ) ) {
      return sanitize_text_field( $this->renewal_window_data['locales'][$lang]['callout_button_label'] );
    }

    return false;
  }

  /**
   * Get the renewal window data
   *
   * @return array|bool Array, false otherwise
   */
  private function get_renewal_window_data() {
    $renewal_window_data = get_post_meta( $this->post_id, 'renewal_window_data', true );

    if ( is_array( $renewal_window_data ) ) {
      return $renewal_window_data;
    }

    return false;
  }

  /**
   * Get the late fee window data
   *
   * @return array|bool Array, false otherwise
   */
  private function get_late_fee_window_data() {
    $late_fee_window_data = get_post_meta( $this->post_id, 'late_fee_window_data', true );

    if ( is_array( $late_fee_window_data ) ) {
      return $late_fee_window_data;
    }

    return false;
  }


  /**
   * Get the late fee window product_id
   *
   * @return int|bool Integer, false otherwise
   */
  public function get_late_fee_window_product_id() {
    if ( isset( $this->late_fee_window_data['product_id'] ) ) {
      return intval( $this->late_fee_window_data['product_id'] );
    }

    return false;
  }

  /**
   * Get the late fee window days
   *
   * @return int|bool Integer, false otherwise
   */
  public function get_late_fee_window_days() {
    if ( isset( $this->late_fee_window_data['days_count'] ) && is_numeric( $this->late_fee_window_data['days_count'] ) ) {
      return intval( $this->late_fee_window_data['days_count'] );
    }

    return false;
  }

  /**
   * Get the late fee window callout header
   * @param string $lang, language code
   *
   * @return string|bool String, false otherwise
   */
  public function get_late_fee_window_callout_header($lang = 'en') {
    if ( isset( $this->late_fee_window_data['locales'][$lang]['callout_header'] ) ) {
      return sanitize_text_field( $this->late_fee_window_data['locales'][$lang]['callout_header'] );
    }

    return false;
  }

  /**
   * Get the late fee window callout content
   * @param string $lang, language code
   *
   * @return string|bool String, false otherwise
   */
  public function get_late_fee_window_callout_content($lang = 'en') {
    if ( isset( $this->late_fee_window_data['locales'][$lang]['callout_content'] ) ) {
      return sanitize_text_field( $this->late_fee_window_data['locales'][$lang]['callout_content'] );
    }

    return false;
  }

  /**
   * Get the late fee window callout button label
   * @param string $lang, language code
   *
   * @return string|bool String, false otherwise
   */
  public function get_late_fee_window_callout_button_label($lang = 'en') {
    if ( isset( $this->late_fee_window_data['locales'][$lang]['callout_button_label'] ) ) {
      return sanitize_text_field( $this->late_fee_window_data['locales'][$lang]['callout_button_label'] );
    }

    return false;
  }

  /**
   * Get the cycle data
   *
   * @return array|bool Array, false otherwise
   */
  public function get_cycle_data() {
    $cycle_data = get_post_meta( $this->post_id, 'cycle_data', true );

    if ( is_array( $cycle_data ) ) {
      return $cycle_data;
    }

    return false;
  }

  /**
   * Get the cycle type
   *
   * @return string|bool String, false otherwise
   */
  public function get_cycle_type() {
    if ( isset( $this->cycle_data['cycle_type'] ) && in_array( $this->cycle_data['cycle_type'], [ 'calendar', 'anniversary' ] ) ) {
      return $this->cycle_data['cycle_type'];
    }

    return false;
  }

  /**
   * Get the calendar seasons from the cycle data.
   *
   * If found, it processes each season to:
   * - Convert `start_date` and `end_date` into ISO 8601 format using the WordPress timezone.
   *
   * @return array|false An array of formatted seasons if available, or false if not set.
   */
  public function get_calendar_seasons() {
    if ( ! isset( $this->cycle_data['calendar_items'] ) || ! is_array( $this->cycle_data['calendar_items'] ) ) {
      return false;
    }

    //  Data structure:
    //   (
    //     [season_name] => Season 2
    //     [active] => true
    //     [start_date] => 2024-04-01
    //     [end_date] => 2024-04-30
    //   )

    // change start_date and end_date to ISO8601 format
    $seasons = $this->cycle_data['calendar_items'];

    foreach ( $seasons as $key => $season ) {

      $start_date = date("Y-m-d", strtotime($season['start_date']));
      $end_date = date("Y-m-d", strtotime($season['end_date']));

      $seasons[ $key ]['start_date'] = Utilities::get_mdp_day_start($start_date)->format('c');
      $seasons[ $key ]['end_date'] = Utilities::get_mdp_day_end($end_date)->format('c');
      // $seasons[ $key ]['active'] = $season['active'] === '1' ? true : false; // we don't this because it's already registered as boolean field
    }

    return $seasons;
  }

  /**
   * Get the current calendar season based on the current date.
   *
   * @return array|false An array of the current season if found, or false if no active season matches.
   */
  public function get_current_calendar_season() {
    $seasons = $this->get_calendar_seasons();
    
    if ( empty( $seasons ) ) {
      return false;
    }

    $current_time = strtotime('now');

    foreach( $seasons as $season ) {
      if ( $season['active'] &&
        ( $current_time >= strtotime( $season['start_date'] ) && ( $current_time <= strtotime( $season['end_date'] ) ) )
      ) {
        return $season;
      }
    }

    return false;
  }

  public function get_period_data() {
    if( $this->get_cycle_type() == 'anniversary') {
      $period['period_count'] = $this->cycle_data['anniversary_data']['period_count'];
      $period['period_type'] = $this->cycle_data['anniversary_data']['period_type'];
    } else {
      $period['period_count'] = 1;
      $period['period_type'] = 'year';
    }
    return $period;
  }

  private function get_anniversary_start_date( $membership = [] ) {

    // Is this a new membership or a renewal?
    $is_new_membership = empty( $membership );

    if (!$is_new_membership) {
      // For renewal: day after current membership ends in MDP timezone
      $datetime_string = strtotime($membership['membership_ends_at'] . '+1 day');
      $date = date("Y-m-d", $datetime_string);
      $utc_date = Utilities::get_mdp_day_start($date);
    } else {
      // For new membership: today at midnight in MDP timezone
      $utc_date = Utilities::get_mdp_day_start('now');
    }
    
    // Return ISO 8601 format
    return $utc_date->format('c');
  }

  private function get_seasonal_start_date( $membership = [] ) {

    // Is this a new membership or a renewal?
    $is_new_membership = empty($membership);

    if (!$is_new_membership) {
      // For renewal: day after current membership ends in MDP timezone
      $datetime_string = strtotime($membership['membership_ends_at'] . '+1 day');
      $date = date("Y-m-d", $datetime_string);
      $utc_date = Utilities::get_mdp_day_start($date);
    } else {
      // For new membership: today at midnight in MDP timezone
      $utc_date = Utilities::get_mdp_day_start('now');
    }
    
    // Return ISO 8601 format
    return $utc_date->format('c');
  }

  private function get_seasonal_end_date( $membership = [] ) {
    // Get MDP timezone, fallback to UTC
    $mdp_timezone = new \DateTimeZone( $_ENV['WICKET_MSHIP_MDP_TIMEZONE'] ?? 'UTC' );
    
    $is_new_membership = empty($membership);
    $seasons = $this->get_calendar_seasons();

    // Create membership start and default end in MDP timezone
    if ( ! $is_new_membership ) {
      // For renewal: day after current membership ends in MDP timezone
      $datetime_string = strtotime($membership['membership_ends_at'] . '+1 day');
      $date = date("Y-m-d", $datetime_string);
      $membership_start_dt = Utilities::get_mdp_day_start($date);
      $membership_start_dt->setTimezone($mdp_timezone);
      
      $membership_default_end_dt = clone $membership_start_dt;
      $membership_default_end_dt->modify('+1 year');
    } else {
      // For new membership: today at midnight in MDP timezone
      $membership_start_dt = Utilities::get_mdp_day_start('now');
      $membership_start_dt->setTimezone($mdp_timezone);
      
      $membership_default_end_dt = clone $membership_start_dt;
      $membership_default_end_dt->modify('+1 year');
    }

    // Use default end date unless a matching season overrides it
    $selected_end_dt = $membership_default_end_dt;

    // Compare using MDP timezone DateTime math
    foreach ($seasons as $season ) {
      // Parse season boundaries in MDP timezone
      $season_start_utc = new \DateTime($season['start_date'], new \DateTimeZone('UTC'));
      $season_start_mdp = $season_start_utc->setTimezone($mdp_timezone);
      
      $season_end_utc = new \DateTime($season['end_date'], new \DateTimeZone('UTC'));
      $season_end_mdp = $season_end_utc->setTimezone($mdp_timezone);

      // The active flag was commented out, retaining for possible future use
      /*if ( !$season['active'] ) {
        continue;
      }*/

      if ( $membership_start_dt >= $season_start_mdp && $membership_start_dt <= $season_end_mdp ) {
        $selected_end_dt = $season_end_mdp;
      }
    }

    // Extract date string and use get_mdp_day_end helper for consistency
    $end_date_string = $selected_end_dt->format('Y-m-d');
    return Utilities::get_mdp_day_end($end_date_string)->format('c');
  }

  public function is_valid_renewal_date( $membership, $date = null ) {
    if( $date ) {
      $current_timestamp = strtotime( $date );
    } else {
      $current_timestamp = current_time( 'timestamp' );
    }
    $dates = $this->get_membership_dates( $membership );

    if( ( $current_timestamp <= strtotime( $dates['early_renew_at'] ) ) || ( $current_timestamp >= strtotime( $dates['expires_at'] ) ) ) {
      return $membership['membership_early_renew_at'] ;
    }
  }

  /**
   * Determine the STart And ENd Date based on config settings
   * If this is a renewal we need to consider early renewal still in previous membership date period
   * @return array membership dates
   */
  public function get_membership_dates( $membership = [] ) {
    $cycle_data = $this->get_cycle_data();
    if( $cycle_data['cycle_type'] == 'anniversary' ) {
      $dates['start_date'] = $this->get_anniversary_start_date( $membership );
      $period_count  = ! empty( $cycle_data['anniversary_data']["period_count"] ) && is_numeric ($cycle_data['anniversary_data']["period_count"]) ? $cycle_data['anniversary_data']["period_count"] : 1; 
      $period_type  = !in_array( $cycle_data['anniversary_data']["period_type"], ['year','month','day'] )
                        ? 'year' : $cycle_data['anniversary_data']["period_type"];      
      $the_end_date = date("Y-m-d", strtotime($dates['start_date'] . "+".$period_count . " " . $period_type));
      if( in_array( $period_type, ['year', 'month'])
          && (! empty($cycle_data['anniversary_data']['align_end_dates_enabled']) && $cycle_data['anniversary_data']['align_end_dates_enabled'] !== false ) ) {
        switch( $cycle_data['anniversary_data']["align_end_dates_type"] ) {
          case 'first-day-of-month':
            $the_end_date = date("Y-m-1", strtotime($dates['start_date'] . "+".$period_count . " ".$period_type));
            break;
          case '15th-of-month':
            $the_end_date = date("Y-m-15", strtotime($dates['start_date'] . "+".$period_count . " ".$period_type));
            break;
          case 'last-day-of-month':
            $the_end_date = date("Y-m-t", strtotime($dates['start_date'] . "+".$period_count . " ".$period_type));
            break;
        }
      }
      $dates['end_date'] = Utilities::get_mdp_day_end($the_end_date)->format('c');
    } else {
      $dates['start_date'] = $this->get_seasonal_start_date( $membership );
      $dates['end_date'] = $this->get_seasonal_end_date( $membership );
    }


    $grace_period_in_days = $this->get_late_fee_window_days();
    if (!empty($grace_period_in_days)) {
      // Parse end_date (which is in UTC) and add grace period days
      $expires_at_utc = new \DateTime($dates['end_date'], new \DateTimeZone('UTC'));
      $expires_date_string = $expires_at_utc->modify('+'.$grace_period_in_days.' days')->format('Y-m-d');
      
      // Get end of that day in MDP timezone, converted to UTC
      $dates['expires_at'] = Utilities::get_mdp_day_end($expires_date_string)->format('c');
    }

    $early_renewal_in_days = $this->get_renewal_window_days();
    if (!empty($early_renewal_in_days)) {
      // Parse start_date (which is in UTC) and subtract early renewal days
      $early_renew_at_utc = new \DateTime($dates['start_date'], new \DateTimeZone('UTC'));
      $early_renew_date_string = $early_renew_at_utc->modify('-'.$early_renewal_in_days.' days')->format('Y-m-d');
      
      // Get start of that day in MDP timezone, converted to UTC
      $dates['early_renew_at'] = Utilities::get_mdp_day_start($early_renew_date_string)->format('c');
    }

    return $dates;
  }

  public function is_multitier_renewal() {
    return  get_post_meta( $this->post_id, 'multi_tier_renewal', true );
  }
}