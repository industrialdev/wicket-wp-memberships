<?php

namespace Wicket_Memberships;

use Wicket_Memberships\Helper;

/**
 * Main controller methods
 * @package Wicket_Memberships
 */
class Membership_Controller {

  private $error_message = '';
  private $membership_cpt_slug = '';
  private $membership_config_cpt_slug = '';
  private $membership_tier_cpt_slug = '';

  public function __construct() {
    $this->membership_cpt_slug = Helper::get_membership_cpt_slug();
    $this->membership_config_cpt_slug = Helper::get_membership_config_cpt_slug();
    $this->membership_tier_cpt_slug = Helper::get_membership_tier_cpt_slug();
    add_action( 'woocommerce_order_status_completed', array ( $this, 'catch_order_completed' ), 10, 1);
    add_action( 'wicket_member_create_record', array( $this, 'create_membership_record'), 10, 1 );
  }

  /**
   * -=-=-=- Setup membership record data based on products in order -=-=-=- 
   */

   /**
   * Get all the membership data from the products on the order
   */
  private function get_membership_data_from_order( $order ) {
    $memberships = [];
    foreach( $order->get_items( 'line_item' ) as $item ) {
      $product_id = $item->get_product_id();
      $product = wc_get_product( $product_id );
      /*
      * TEMPORARY: Currently added as product attributes
      * PERMANENT: Use Membership Configuration Posts data
      * DON'T FORGET to add ['expires_at'] = 'ends_at' + 'grace_period'
      */
      $memberships[] = [
        'membership_uuid' => $product->get_attribute( 'membership_uuid' ),
        'starts_at' => $product->get_attribute( 'starts_at' ),
        'ends_at' => $product->get_attribute( 'ends_at' ),
      ];
    }
    return $memberships;
  }

  /**
   * Get memberships with config from tier by products on the order
   */
  private function get_memberships_data_from_products( $order ) {

    $memberships = [];
    foreach( $order->get_items( 'line_item' ) as $item ) {
      $product_id = $item->get_product_id();
      $membership_tiers = $this->get_tiers_from_product( $product_id );
      if( !empty( $membership_tiers )) {
        foreach ($membership_tiers as $membership_tier) {
          $config = get_post_meta( $membership_tier['config_id'], 'cycle_data' );      
          if( $config[0]['cycle_type'] == 'anniversary' ) {
            $dates = $this->get_anniversary_dates( $config );
          } else {
            $dates = $this->get_calendar_dates( $config );
          }
          $memberships[] = [
            'membership_wp_id' => $membership_tier->ID,
            'membership_uuid' => $membership_tier->tier_uuid,
            'member_type' => $membership_tier->type,
            'starts_at' => $dates['start_date'],
            'ends_at' =>  $dates['end_date'],
          ];
        }  
      }
    }
    return $memberships;
  }

  /**
   * Determine the STart And ENd Date based on Anniversary settings
   * array(1) {
  [0]=>
  array(3) {
    ["cycle_type"]=>
    string(11) "anniversary"
    ["anniversary_data"]=>
    array(4) {
      ["period_count"]=>
      int(1)
      ["period_type"]=>
      string(4) "year"
      ["align_end_dates_enabled"]=>
      bool(true)
      ["align_end_dates_type"]=>
      string(13) "15th-of-month"
    }
    ["calendar_items"]=>
    array(0) {
    }
  }
}
   */
  public function get_anniversary_dates( $config = null ) {
    if( ! $config[0]['anniversary_data']['align_end_dates_enabled'] ) {
      switch( $config[0]['anniversary_data']["period_type"]){
        case 'year':
          $dates['end_date'] = (new \DateTime( date("Y-m-d", strtotime("+1 year")), wp_timezone() ))->format('c');
          break;
        case 'month':
          $dates['end_date'] = (new \DateTime( date("Y-m-d", strtotime("+1 month")), wp_timezone() ))->format('c');
          break;
        case 'week':
          $dates['end_date'] = (new \DateTime( date("Y-m-d", strtotime("+1 week")), wp_timezone() ))->format('c');
          break;
      }          
    }
  }

  /**
   * Determine the STart and ENd Date based on Calendar settings
   */
  private function get_calendar_dates( $config ) {

  }


  /**
   * Get all tiers attached to a product
   */
  public function get_tiers_from_product( $product_id ) {

    $args = array(
      'post_type' => $this->membership_tier_cpt_slug,
      'post_status' => 'publish',
      'posts_per_page' => -1,
      'meta_query'     => array(
        array(
          'key'     => 'wc_product',
          'value'   => $product_id,
          'compare' => '='
        ),
      )
    );
    $tiers = get_posts( $args );
    foreach( $tiers as &$tier ) {
      $tier->meta = get_post_meta( $tier->ID);
    }
    return $tiers;
  }

  /**
   * -=-=-=- Process the Order Hook and Create the Wicket MDP Membership
   */

  /**
   * Catch the Order Status Changed hook
   * Process the order product(s) memberships
   */
  public static function catch_order_completed( $order_id ) {
    $order = wc_get_order( $order_id );
    $self = new self();

    //get membership_data
    $memberships = $self->get_membership_data_from_order( $order );

    //get_person_uuid
    $user_id = $order->get_user_id();
    $user = get_user_by( 'id', $user_id );
    $person_uuid = $user->data->user_login;

    //membership data arrays
    $memberships = array_map(function (array $arr) use ($user_id, $person_uuid) {
        $arr['person_uuid'] = $person_uuid;
        $arr['user_id'] = $user_id;
        return $arr;
    }, $memberships);

    //connect product memberships
    foreach ($memberships as $membership) {
      do_action( 'wicket_member_create_record' , $membership );
    }
  }

  /**
   * Create the membership records
   */
  public function create_membership_record( $membership ) {
      $wicket_uuid = $this->create_mdp_record( $membership );
      if( !empty( $wicket_uuid ) ) {
        return $this->create_local_membership_record(  $membership, $wicket_uuid);
      }
  }

  /**
   * Create the Membership Record in MDP
   */
  private function create_mdp_record( $membership ) {
    $wicket_uuid = $this->check_mdp_membership_record_exists( $membership );
    if( empty( $wicket_uuid ) ) {
      $response = wicket_assign_individual_membership( 
        $membership['person_uuid'],
        $membership['membership_uuid'], 
        $membership['starts_at'],
        $membership['ends_at']
      );
      if( is_wp_error( $response ) ) {
        $this->error_message = $response->get_error_message( 'wicket_api_error' );
        $this->surface_error();
        $wicket_uuid = '';
      } else {
        $wicket_uuid = $response['data']['id'];
      } 
    }
    return $wicket_uuid;
  }

  /**
   * Check if MDP Membership Record already exists
   */
  private function check_mdp_membership_record_exists( $membership ) {
    $wicket_uuid = wicket_get_person_membership_exists(
      $membership['person_uuid'], 
      $membership['membership_uuid'], 
      $membership['starts_at'], 
      $membership['ends_at']
    );
    return $wicket_uuid;
  }

  /**
   * Create the WP Membership Record
   */
  private function create_local_membership_record( $membership, $wicket_uuid ) {
    if( ! $this->check_local_membership_record_exists( $membership )) {
      return wp_insert_post( array (
        'post_type' => $this->membership_cpt_slug,
        'post_status' => 'publish',
        'meta_input'  => [
          'status' => 'active',
          'member_type' => $membership['member_type'],
          'user_id' => $membership['user_id'],
          'start_date' => $membership['starts_at'],
          'end_date' => $membership['ends_at'],
          'expiry_date' => !empty($membership['expires_at']) ? $membership['expires_at'] : $membership['ends_at'],
          'membership_uuid' => $membership['membership_uuid'],
          'wicket_uuid' => $wicket_uuid,
        ]
      ));
    }
  }

  /**
   * Check if the WP Membership Record already exists
   */
  private function check_local_membership_record_exists( $membership ) {
    $args = array(
      'post_type' => $this->membership_cpt_slug,
      'post_status' => 'publish',
      'posts_per_page' => -1,
      'meta_query'     => array(
        array(
          'key'     => 'user_id',
          'value'   => $membership['user_id'],
          'compare' => '='
        ),
        array(
          'key'     => 'start_date',
          'value'   => $membership['starts_at'],
          'compare' => '='
        ),
        array(
          'key'     => 'end_date',
          'value'   => $membership['ends_at'],
          'compare' => '='
        ),
        array(
          'key'     => 'membership_uuid',
          'value'   => $membership['membership_uuid'],
          'compare' => '='
        )
      )
    );
    $posts = new \WP_Query( $args );
    if( !empty( $posts->found_posts ) ) {      
      return true;
    }
    return false;
  }

  /**
   * Attempt to expose an error
   */
  private function surface_error() {
    WC()->session = new \WC_Session_Handler();
    WC()->session->init();
    wc_add_notice( 'Wicket API Error: '.$this->error_message, 'error' );
    add_action( 'admin_notices', array($this, 'error_notice' ) );
  }

  /**
   * Display an error notice
   */
  public function error_notice() {
    $error_message = '<p><strong>' . __( 'WICKET MDP MEMBERSHIP PROCESSING ERROR', 'wicket-memberships' ). '</strong></p>';
    $error_message .= '<p>'.$this->error_message.'</p>';
      echo "<div class=\"notice notice-error is-dismissible\"> <p>$error_message</p></div>"; 
  }
}
