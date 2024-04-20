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
   * Get the renewal window days
   *
   * @return int|bool Integer, false otherwise
   */
  public function get_renewal_window_days() {
    if ( isset( $this->renewal_window_data['days_count'] ) ) {
      return intval( $this->renewal_window_data['days_count'] );
    }

    return false;
  }

  /**
   * Get the renewal callout header
   *
   * @return int|bool Integer, false otherwise
   */
  public function get_renewal_window_callout_header() {
    if ( isset( $this->renewal_window_data['callout_header'] ) ) {
      return sanitize_title( $this->renewal_window_data['callout_header'] );
    }

    return false;
  }

  /**
   * Get the renewal callout header
   *
   * @return int|bool Integer, false otherwise
   */
  public function get_renewal_window_callout_content() {
    if ( isset( $this->renewal_window_data['callout_content'] ) ) {
      return sanitize_title( $this->renewal_window_data['callout_content'] );
    }

    return false;
  }

  /**
   * Get the renewal callout button label
   *
   * @return int|bool Integer, false otherwise
   */
  public function get_renewal_window_callout_button_label() {
    if ( isset( $this->renewal_window_data['callout_button_label'] ) ) {
      return sanitize_title( $this->renewal_window_data['callout_button_label'] );
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
   * Get the late fee window days
   *
   * @return int|bool Integer, false otherwise
   */
  public function get_late_fee_window_days() {
    if ( isset( $this->late_fee_window_data['days_count'] ) ) {
      return intval( $this->late_fee_window_data['days_count'] );
    }

    return false;
  }

  /**
   * Get the late fee window callout header
   *
   * @return int|bool Integer, false otherwise
   */
  public function get_late_fee_window_callout_header() {
    if ( isset( $this->late_fee_window_data['callout_header'] ) ) {
      return sanitize_title( $this->late_fee_window_data['callout_header'] );
    }

    return false;
  }

  /**
   * Get the late fee window callout content
   *
   * @return int|bool Integer, false otherwise
   */
  public function get_late_fee_window_callout_content() {
    if ( isset( $this->late_fee_window_data['callout_content'] ) ) {
      return sanitize_title( $this->late_fee_window_data['callout_content'] );
    }

    return false;
  }

  /**
   * Get the late fee window callout button label
   *
   * @return int|bool Integer, false otherwise
   */
  public function get_late_fee_window_callout_button_label() {
    if ( isset( $this->late_fee_window_data['callout_button_label'] ) ) {
      return sanitize_title( $this->late_fee_window_data['callout_button_label'] );
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

  public function get_calendar_seasons() {
    if ( ! isset( $this->cycle_data['calendar_items'] ) || ! is_array( $this->cycle_data['calendar_items'] ) ) {
      return false;
    }

  //  Data structure:
  //   (
  //     [season_name] => Season 2
  //     [active] => 1
  //     [start_date] => 2024-04-01
  //     [end_date] => 2024-04-30
  //   )

    // change start_date and end_date to ISO8601 format
    // and active to boolean
    $seasons = $this->cycle_data['calendar_items'];
    foreach ( $seasons as $key => $season ) {
      $seasons[ $key ]['start_date'] = (new \DateTime( date("Y-m-d", strtotime( $season['start_date'] )), wp_timezone() ))->format('c');
      $seasons[ $key ]['end_date'] = (new \DateTime( date("Y-m-d", strtotime( $season['end_date'] )), wp_timezone() ))->format('c');
      $seasons[ $key ]['active'] = $season['active'] === '1' ? true : false;
    }

    return $seasons;
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

  private function get_anniversary_start_date( $membership = null ) {
    if( empty( $membership )) {
      $start_date = (new \DateTime( date("Y-m-d"), wp_timezone() ))->format('c');
    } else {
      $start_date = (new \DateTime( date("Y-m-d", strtotime( $membership['membership_ends_at'] . '+1 day' )), wp_timezone() ))->format('c');
    }
    return $start_date;
  }

  private function get_seasonal_start_date( $membership = null ) {
    if( empty( $membership )) {
      $start_date = (new \DateTime( date("Y-m-d"), wp_timezone() ))->format('c');
    } else {
      $start_date = (new \DateTime( date("Y-m-d", strtotime( $membership['end_date'] . '+1 day')), wp_timezone() ))->format('c');
    }
    return $start_date;
  }

  private function get_seasonal_end_date( $membership ) {
    $seasons = $this->get_calendar_seasons();
    if( empty( $membership ) ) {
      $end_date = (new \DateTime( date("Y-m-d", strtotime("+1 year")), wp_timezone() ))->format('c');
      $membership_start_time = current_time( 'timestamp' );
    } else {
      $end_date = (new \DateTime( date("Y-m-d", strtotime($membership['end_date'] . "+1 year")), wp_timezone() ))->format('c');
      $membership_start_time = strtotime( $membership['end_date'] . '+1 day' );
    }
    foreach( $seasons as $season ) {
    if( /*$season['active'] && */ ( $membership_start_time >= strtotime( substr($season['start_date'], 0, 10) )) && ( $membership_start_time <= strtotime( substr($season['end_date'], 0, 10) ))) {
        $end_date = $season['end_date'];
      }
    } 
    return $end_date;
  }

  /**
   * Determine the STart And ENd Date based on config settings
   * If this is a renewal we need to consider early renewal still in previous membership date period
   * @return array membership dates
   */
  public function get_membership_dates( $membership = null) {
    $cycle_data = $this->get_cycle_data();
    if( $cycle_data['cycle_type'] == 'anniversary' ) {
      $dates['start_date'] = $this->get_anniversary_start_date( $membership );
      $period_type  = !in_array( $cycle_data['anniversary_data']["period_type"], ['year','month','day'] )
                        ? 'year' : $cycle_data['anniversary_data']["period_type"];
      $the_end_date = date("Y-m-d", strtotime($dates['start_date'] . "+1 ".$period_type));
      if( in_array( $period_type, ['year', 'month']) && $cycle_data['align_end_dates_enabled'] !== false ) {
        switch( $cycle_data['anniversary_data']["align_end_dates_type"] ) {
          case 'first-day-of-month':
            $the_end_date = date("Y-m-1", strtotime($dates['start_date'] . "+1 ".$period_type));
            break;
          case '15th-of-month':
            $the_end_date = date("Y-m-15", strtotime($dates['start_date'] . "+1 ".$period_type));
            break;
          case 'last-day-of-month':
            $the_end_date = date("Y-m-t", strtotime($dates['start_date'] . "+1 ".$period_type));
            break;
        }
      }
      $dates['end_date'] = (new \DateTime( $the_end_date, wp_timezone() ))->format('c');
    } else {    
      $dates['start_date'] = $this->get_seasonal_start_date( $membership );
      $dates['end_date'] = $this->get_seasonal_end_date( $membership );
      $current_time = current_time( 'timestamp' );
      $seasons = $this->get_calendar_seasons();
      foreach( $seasons as $season ) {
        if( $season['active'] && ( $current_time >= strtotime( $season['start_date'] )) && ( $current_time <= strtotime( $season['end_date'] ))) {
          $dates['end_date'] = $season['end_date'];
        }
      }
    }

    $grace_period = $this->get_late_fee_window_days();
    if( !empty ($grace_period )) {
      $adjusted_date_grace_period = date_add( new \DateTime($dates['end_date']), date_interval_create_from_date_string("$grace_period days")); //date( $dates['end_date'],  strtotime("+$grace_period days"));
      $dates['expires_at'] = $adjusted_date_grace_period->format('c');
    }

    $early_renewal_period = $this->get_renewal_window_days();
    if( !empty ($early_renewal_period )) {
      $adjusted_date_early_renewal = date_sub( new \DateTime($dates['end_date']), date_interval_create_from_date_string("$early_renewal_period days")); //date( $dates['end_date'],  strtotime("+$grace_period days"));
      $dates['early_renew_at'] = $adjusted_date_early_renewal->format('c');
    }

    return $dates;
  }

}