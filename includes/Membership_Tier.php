<?php
namespace Wicket_Memberships;

class Membership_Tier {

  private $post_id;
  private $tier_data;

  public function __construct( $post_id ) {
    if ( ! get_post( $post_id ) ) {
      throw new \Exception( 'Invalid post ID' );
    }

    if ( get_post_type( $post_id ) !== Helper::get_membership_tier_cpt_slug() ) {
      throw new \Exception( 'Invalid post type' );
    }

    $this->post_id = $post_id;
    $this->tier_data = $this->get_tier_data();
	}

  /**
   * Get the MDP tier name
   *
   * @return string|bool String, false otherwise
   */
  public function get_mdp_tier_name() {
    if ( isset( $this->tier_data['mdp_tier_name'] ) ) {
      return $this->tier_data['mdp_tier_name'];
    }

    return false;
  }

  /**
   * Get the MDP tier UUID
   *
   * @return string|bool String, false otherwise
   */
  public function get_mdp_tier_uuid() {
    if ( isset( $this->tier_data['mdp_tier_uuid'] ) ) {
      return $this->tier_data['mdp_tier_uuid'];
    }

    return false;
  }

  /**
   * Get the next MDP tier UUID
   *
   * @return string|bool String, false otherwise
   */
  public function get_mdp_next_tier_uuid() {
    if ( isset( $this->tier_data['mdp_next_tier_uuid'] ) ) {
      return $this->tier_data['mdp_next_tier_uuid'];
    }

    return false;
  }

  /**
   * Get the config ID
   *
   * @return int|bool Integer, false otherwise
   */
  public function get_config_id() {
    if ( isset( $this->tier_data['config_id'] ) ) {
      return intval( $this->tier_data['config_id'] );
    }

    return false;
  }

  /**
   * Get the config
   *
   * @return Membership_Config
   */
  public function get_config() {
    $config_id = $this->get_config_id();

    if ( ! $config_id ) {
      return false;
    }

    return new Membership_Config( $config_id );
  }

  /**
   * Check if the tier is an organization tier
   *
   * @return bool
   */
  public function is_organization_tier() {
    if ( $this->get_tier_type() !== false ) {
      return $this->get_tier_type() === 'organization';
    }

    return false;
  }

  /**
   * Check if the tier is an individual tier
   *
   * @return bool
   */
  public function is_individual_tier() {
    if ( $this->get_tier_type() !== false ) {
      return $this->get_tier_type() === 'individual';
    }

    return false;
  }

  /**
   * Get the seat type
   *
   * @return string|bool String, false otherwise
   */
  public function get_seat_type() {
    if ( isset( $this->tier_data['seat_type'] ) ) {
      return $this->tier_data['seat_type'];
    }

    return false;
  }

  /**
   * Check if the tier is per seat
   *
   * @return bool
   */
  public function is_per_seat() {
    if ( $this->get_seat_type() !== false ) {
      return $this->get_seat_type() === 'per_seat';
    }

    return false;
  }

  /**
   * Check if the tier is per range of seats
   *
   * @return bool
   */
  public function is_per_range_of_seats() {
    if ( $this->get_seat_type() !== false ) {
      return $this->get_seat_type() === 'per_range_of_seats';
    }

    return false;
  }

  /**
   * Get the tier type
   *
   * @return string|bool String, false otherwise
   */
  public function get_tier_type() {
    if ( isset( $this->tier_data['type'] ) ) {
      return $this->tier_data['type'];
    }

    return false;
  }

  /**
   * Get the products data
   *
   * @return array|bool Array, false otherwise
   */
  public function get_products_data() {

    // Example return:
    // [
    //   [0] => Array
    //       (
    //           [product_id] => 803
    //           [max_seats] => -1
    //       )

    //   [1] => Array
    //       (
    //           [product_id] => 804
    //           [max_seats] => -1
    //       )
    // ]

    if ( isset( $this->tier_data['product_data'] ) ) {
      return $this->tier_data['product_data'];
    }

    return false;
  }

  /**
   * Check if approval is required for this tier
   */
  public function is_approval_required() {
    if ( isset( $this->tier_data['approval_required'] ) && $this->tier_data['approval_required'] == 1 ) {
      return $this->tier_data['approval_required'];
    }

    return false;
  }

  /**
   * Get the cycle data
   *
   * @return array|bool Array, false otherwise
   */
  private function get_tier_data() {
    $tier_data = get_post_meta( $this->post_id, 'tier_data', true );

    if ( is_array( $tier_data ) ) {
      return $tier_data;
    }

    return false;
  }

}