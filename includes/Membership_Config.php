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
  private function get_cycle_data() {
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
}