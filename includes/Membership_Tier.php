<?php
namespace Wicket_Memberships;

class Membership_Tier {

  private $post_id;
  public $tier_data;

  public function __construct( $post_id ) {
    if ( ! get_post( $post_id ) ) {
      //throw new \Exception( 'Invalid post ID' );
      error_log('Membership_Tier: Invalid post ID: ' . $post_id);

      $this->post_id = 0;
      $this->tier_data = [];

      return;
    }

    if ( get_post_type( $post_id ) !== Helper::get_membership_tier_cpt_slug() ) {
      //throw new \Exception( 'Invalid post type' );
      return;
    }

    $this->post_id = $post_id;
    $this->tier_data = $this->get_tier_data();
  }

  /**
   * Get all tier WC Product IDs
   *
   * @return array Array of WC Product Post IDs
   */
  public static function get_all_tier_product_ids() {
    $args = array(
      'post_type' => Helper::get_membership_tier_cpt_slug(),
      'posts_per_page' => -1,
    );

    $tiers = get_posts( $args );

    $product_ids = [];

    foreach ( $tiers as $tier ) {
      $tier_obj = new Membership_Tier( $tier->ID );

      $products_data = $tier_obj->get_products_data();

      if ( $products_data ) {
        foreach ( $products_data as $product_data ) {
          $product_ids[] = $product_data['product_id'];
        }
      }
    }

    return array_unique( $product_ids );
  }

  /**
   * Get all tier WC Product Variation IDs
   *
   * @return array Array of WC Product Post Variation IDs
   */
  public static function get_all_tier_product_variation_ids() {
    $args = array(
      'post_type' => Helper::get_membership_tier_cpt_slug(),
      'posts_per_page' => -1,
    );

    $tiers = get_posts( $args );

    $product_variation_ids = [];

    foreach ( $tiers as $tier ) {
      $tier_obj = new Membership_Tier( $tier->ID );

      $products_data = $tier_obj->get_products_data();

      if ( $products_data ) {
        foreach ( $products_data as $product_data ) {
          $product_variation_ids[] = $product_data['variation_id'];
        }
      }
    }

    return array_unique( $product_variation_ids );
  }

  /**
   * Get the tier by product ID
   *
   * @param int $product_id
   *
   * @return Membership_Tier|bool Membership_Tier object, false otherwise
   */
  public static function get_tier_by_product_id( $product_id ) {
    // TODO: Check if it's possible to use a WP_Query instead of get_posts
    $args = array(
      'post_type' => Helper::get_membership_tier_cpt_slug(),
      'posts_per_page' => -1,
    );

    $tiers = get_posts( $args );

    foreach ( $tiers as $tier ) {
      $tier_obj = new Membership_Tier( $tier->ID );

      $products_data = $tier_obj->get_products_data();

      if ( $products_data ) {
        foreach ( $products_data as $product_data ) {
          if ( !empty($product_data['variation_id']) && $product_data['variation_id'] == $product_id ) {
            $tier_obj->tier_data['product_data'] = $product_data;
            return $tier_obj;
          }
          if ( $product_data['product_id'] == $product_id ) {
            $tier_obj->tier_data['product_data'] = $product_data;
            return $tier_obj;
          }
        }
      }
    }

    return false;
  }

    /**
   * Get the tier Post ID from UUID
   *
   * @param int $uuid
   *
   * @return int|bool Post ID, false otherwise
   */
  public static function get_tier_id_by_wicket_uuid( $uuid ) {
    $args = array(
      'post_type' => Helper::get_membership_tier_cpt_slug(),
      'posts_per_page' => -1,
    );

    $tiers = get_posts( $args );
    foreach ( $tiers as $tier ) {
      $tier_obj = new Membership_Tier( $tier->ID );
      if ( $tier_obj->get_mdp_tier_uuid() == $uuid ) {
        return $tier->ID;
      }
    }
    return false;
  }

  /**
   * Get the Tier UUIDs by config ID
   *
   * @param int $config_id
   *
   * @return array Array of tier UUIDs
   */
  public static function get_tier_uuids_by_config_id( $config_id ) {
    if ( ! $config_id ) {
      return [];
    }

    $args = array(
      'post_type' => Helper::get_membership_tier_cpt_slug(),
      'posts_per_page' => -1,
      'meta_query' => array(
        array(
          'key' => 'tier_data',
          'value' => ':"config_id";i:' . $config_id . ';',
          'compare' => 'LIKE'
        )
      )
    );

    $tiers = get_posts( $args );

    $tier_uuids = [];

    foreach ( $tiers as $tier ) {
      $tier_obj = new Membership_Tier( $tier->ID );
      $tier_uuids[] = $tier_obj->get_mdp_tier_uuid();
    }

    return $tier_uuids;
  }

  /**
   * Get the Tier IDs by config ID
   *
   * @param int $config_id
   *
   * @return array Array of tier post IDs
   */
  public static function get_tier_ids_by_config_id( $config_id ) {
    $args = array(
      'post_type' => Helper::get_membership_tier_cpt_slug(),
      'posts_per_page' => -1,
      'meta_query' => array(
        array(
          'key' => 'tier_data',
          'value' => ':"config_id";i:' . $config_id . ';',
          'compare' => 'LIKE'
        )
      )
    );

    $tiers = get_posts( $args );

    $tier_ids = [];

    foreach ( $tiers as $tier ) {
      $tier_ids[] = $tier->ID;
    }

    return $tier_ids;
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
   * Is form page renewal type
   *
   * @return bool
   */
  public function is_renewal_form_page() {
    return ($this->get_next_tier_form_page_id() === false ? false : true);
  }

  /**
   * Is tier renewal type
   *
   * @return bool
   */
  public function is_renewal_tier() {
    return ($this->get_next_tier_id() === false ? false : true);
  }

  /**
   * Get the next tier post ID
   *
   * @return int|bool Integer, false otherwise
   */
  public function get_next_tier_id() {
    if ( isset( $this->tier_data['next_tier_id'] ) && $this->tier_data['next_tier_id'] !== 0 ) {
      return intval( $this->tier_data['next_tier_id'] );
    }

    return false;
  }

  /**
   * Get the next tier form post ID
   *
   * @return int|bool Integer, false otherwise
   */
  public function get_next_tier_form_page_id() {
    if ( isset( $this->tier_data['next_tier_form_page_id'] ) && $this->tier_data['next_tier_form_page_id'] !== 0 ) {
      return intval( $this->tier_data['next_tier_form_page_id'] );
    }

    return false;
  }

  /**
   * Get product IDs attached to the tier
   *
   * @return array Array of WC Product Post IDs
   */
  public function get_product_ids() {
    $product_ids = [];
    $products_data = $this->get_products_data();

    if ( $products_data ) {
      foreach ( $products_data as $product_data ) {
        $product_ids[] = $product_data['product_id'];
      }
    }

    return $product_ids;
  }

  /**
   * Get product variation IDs attached to the tier
   *
   * @return array Array of WC Product Variation Post IDs
   */
  public function get_product_variation_ids() {
    $product_variation_ids = [];
    $products_data = $this->get_products_data();

    if ( $products_data ) {
      foreach ( $products_data as $product_data ) {
        $product_variation_ids[] = $product_data['variation_id'];
      }
    }

    return $product_variation_ids;
  }

  /**
   * Get the next tier
   *
   * @return Membership_Tier|bool Membership_Tier object, false otherwise
   */
  public function get_next_tier() {
    $next_tier_id = $this->get_next_tier_id();

    if ( ! $next_tier_id ) {
      return false;
    }

    return new Membership_Tier( $next_tier_id );
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
    //           [variation_id] => 805
    //           [max_seats] => -1
    //       )

    //   [1] => Array
    //       (
    //           [product_id] => 804
    //           [variation_id] => 806
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
   * Check if grant_owner_assignment is required for this tier
   */
  public function is_grant_owner_assignment() {
    if ( isset( $this->tier_data['grant_owner_assignment'] ) && $this->tier_data['grant_owner_assignment'] == 1 ) {
      return $this->tier_data['grant_owner_assignment'];
    }

    return false;
  }

  /**
   * Get the approval email
   *
   * @return string|bool String, false otherwise
   */
  public function get_approval_email() {
    if ( isset( $this->tier_data['approval_email_recipient'] ) ) {
      return $this->tier_data['approval_email_recipient'];
    }

    return false;
  }

  /**
   * Get the approval callout data
   *
   * @return array|bool Array, false otherwise
   *
   * Example return:
   * [
   *    'callout_header' => 'Example Header',
   *    'callout_body' => 'Example Body',
   *    'callout_button_text' => 'Example Button Text',
   * ]
   */
  private function get_approval_callout_data() {
    if ( isset( $this->tier_data['approval_callout_data'] ) && is_array( $this->tier_data['approval_callout_data'] ) ) {
      return $this->tier_data['approval_callout_data'];
    }

    return false;
  }

  /**
   * Get the approval callout header
   * @param string $lang Language code
   *
   * @return string|bool String, false otherwise
   */
  public function get_approval_callout_header($lang = 'en') {
    $approval_callout_data = $this->get_approval_callout_data();

    if ( isset( $approval_callout_data['locales'][$lang]['callout_header'] ) ) {
      return $approval_callout_data['locales'][$lang]['callout_header'];
    }

    return false;
  }

  /**
   * Get the approval callout content
   * @param string $lang Language code
   *
   * @return string|bool String, false otherwise
   */
  public function get_approval_callout_content($lang = 'en') {
    $approval_callout_data = $this->get_approval_callout_data();

    if ( isset( $approval_callout_data['locales'][$lang]['callout_content'] ) ) {
      return $approval_callout_data['locales'][$lang]['callout_content'];
    }

    return false;
  }

  /**
   * Get the approval callout button label
   * @param string $lang Language code
   *
   * @return string|bool String, false otherwise
   */
  public function get_approval_callout_button_label($lang = 'en') {
    $approval_callout_data = $this->get_approval_callout_data();

    if ( isset( $approval_callout_data['locales'][$lang]['callout_button_label'] ) ) {
      return $approval_callout_data['locales'][$lang]['callout_button_label'];
    }

    return false;
  }

  /**
   * Get the cycle data
   *
   * @return array|bool Array, false otherwise
   */
  private function get_tier_data() {
    $tier_data = get_post_meta( $this->post_id, self::get_meta_tier_data_field_name(), true );

    if ( is_array( $tier_data ) ) {
      return $tier_data;
    }

    return false;
  }

  /**
   * Update the tier data
   *
   * @param array $tier_data
   */
  public function update_tier_data( $new_tier_data ) {
    update_post_meta( $this->post_id, self::get_meta_tier_data_field_name(), $new_tier_data );
  }

  /**
   * Get the meta tier data field name
   *
   * @return string
   */
  public static function get_meta_tier_data_field_name() {
    return 'tier_data';
  }

  public function get_membership_tier_post_id() {
    return $this->post_id;
  }

  public function get_seat_count() {
    $seats = 0;
    $product_data = $this->get_products_data();
    $seats = $product_data['max_seats'];
    return $seats;
  }

  public function get_membership_posts() {
    $args = array(
      'post_type' => Helper::get_membership_cpt_slug(),
      'posts_per_page' => -1,
      'meta_query' => array(
        array(
          'key' => 'membership_tier_uuid',
          'value' => $this->get_mdp_tier_uuid(),
          'compare' => '='
        )
      )
    );

    $memberships = get_posts( $args );

    return $memberships;
  }
}
