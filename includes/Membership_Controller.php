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

  //don't create wicket connection - for testing locally
  private $bypass_wicket = true;

  public function __construct() {
    $this->membership_cpt_slug = Helper::get_membership_cpt_slug();
    $this->membership_config_cpt_slug = Helper::get_membership_config_cpt_slug();
    $this->membership_tier_cpt_slug = Helper::get_membership_tier_cpt_slug();

    //Get onboarding data on add cart item
    add_filter( 'woocommerce_add_cart_item_data', [$this, 'add_cart_item_data'], 25, 2 );
    add_filter( 'woocommerce_get_item_data', [$this, 'get_item_data'] , 25, 2 ); //exposes in cart and checkout
    add_action( 'woocommerce_add_order_item_meta', [$this, 'add_order_item_meta'] , 10, 2);
    add_action( 'woocommerce_before_add_to_cart_button', [$this, 'product_add_on'], 9 ); //collects org data in cart

    // TEMPORY -- INJECT MEMBERSHIP META DATA into order and subscription pages -- org_id on checkout page
    add_action( 'woocommerce_admin_order_data_after_shipping_address', [$this, 'wps_select_checkout_field_display_admin_order_meta'], 10, 1 );
    add_action( 'wcs_subscription_details_table_before_dates', [$this, 'wps_select_checkout_field_display_admin_order_meta'], 10, 1 );
  }

  // TEMPORARILY INJECT MEMBERSHIP META DATA into order and subscription pages
  function wps_select_checkout_field_display_admin_order_meta( $post ) {
    $post_meta = get_post_meta( $post->get_id() );
    foreach($post_meta as $key => $val) {
    if( str_starts_with( $key, '_wicket_membership_')) {
        echo '<br>'.$post->get_id().'<strong>'.$key.':</strong><pre>';echo maybe_unserialize( $val[0] ); echo '</pre>';
      }
    }
  }

  //COLLECT CART ITEM FIELDS ON ADD TO CART
  function product_add_on() {
      //change to hidden fields and remove 'woocommerce_get_item_data' filter to hide data
      $value = isset( $_REQUEST['org_name'] ) ? sanitize_text_field( $_REQUEST['org_name'] ) : '';
      echo '<div><label>org_name</label><p><input type="text" name="org_name" value="' . $value . '"></p></div>';
      $value = isset( $_REQUEST['org_uuid'] ) ? sanitize_text_field( $_REQUEST['org_uuid'] ) : '';
      echo '<div><label>org_uuid</label><p><input type="text" name="org_uuid" value="' . $value . '"></p></div>';
  }

  function add_cart_item_data( $cart_item_meta, $product_id ) {
      if ( isset( $_REQUEST ['org_name'] ) && isset( $_REQUEST ['org_uuid'] ) ) {
          $org_data[ 'org_name' ] = isset( $_REQUEST['org_name'] ) ?  sanitize_text_field ( $_REQUEST['org_name'] ) : "" ;
          $org_data[ 'org_uuid' ] = isset( $_REQUEST['org_uuid'] ) ? sanitize_text_field ( $_REQUEST['org_uuid'] ): "" ;
          $cart_item_meta['org_data'] = $org_data ;
      }
      return $cart_item_meta;
  }

  function get_item_data ( $other_data, $cart_item ) {
      if ( isset( $cart_item [ 'org_data' ] ) ) {
          $org_data  = $cart_item [ 'org_data' ];
          $data[] = array( 'name' => 'Org Name', 'display'  => $org_data['org_name'] );
          $data[] = array( 'name' => 'Org UUID', 'display'  => $org_data['org_uuid'] );
      }
      return $data;
  }

  function add_order_item_meta ( $item_id, $values ) {
      if ( isset( $values [ 'org_data' ] ) ) {
          $custom_data  = $values [ 'org_data' ];
          wc_add_order_item_meta( $item_id, '_org_name', $custom_data['org_name'] );
          wc_add_order_item_meta( $item_id, '_org_uuid', $custom_data['org_uuid'] );
      }
  }

  ///////////////////////////////////////////////////////////////////////////////////////////////////////
  // Membership_Controller methods start here
  ///////////////////////////////////////////////////////////////////////////////////////////////////////

  /**
   * Get memberships with config from tier by products on the order
   */
  private function get_memberships_data_from_subscription_products( $order ) {
    $seats = 0;
    $memberships = [];
    $order_id = $order->get_id();
    
    $subscriptions = wcs_get_subscriptions( ['order_type' => 'parent', 'order_id' => $order_id] );
    //$subscriptions_ids = wcs_get_subscriptions_for_order( $order_id, ['order_type' => 'any'] );
    foreach( $subscriptions as $subscription_id => $subscription ) {
        $subscription_products = $subscription->get_items();
        foreach( $subscription_products as $item ) {
          $product_id = $item->get_product_id();
          $membership_tier = Membership_Tier::get_tier_by_product_id( $product_id ); 
          if( !empty( $membership_tier->tier_data )) {
              $config = new Membership_Config( $membership_tier->tier_data['config_id'] );
              $period_data = $config->get_period_data();
              $dates = $config->get_membership_dates();

              $membership = [
                'membership_parent_order_id' => $order_id,
                'membership_subscription_id' => $subscription_id,
                'membership_product_id' => $product_id,
                'membership_tier_post_id' => 0,
                'membership_tier_name' => $membership_tier->tier_data['mdp_tier_name'],
                'membership_tier_uuid' => $membership_tier->tier_data['mdp_tier_uuid'],
                'membership_type' => $membership_tier->tier_data['type'],
                'membership_starts_at' => $dates['start_date'],
                'membership_ends_at' =>  $dates['end_date'],
                'membership_expires_at' => !empty($dates['expires_at']) ? $dates['expires_at'] : $dates['end_date'],
                'membership_early_renew_at' => !empty($dates['early_renew_at']) ? $dates['early_renew_at'] : $dates['end_date'],
                'membership_period' => $period_data['period_type'],
                'membership_interval' => $period_data['period_count'],
                'membership_subscription_period' => get_post_meta( $subscription_id, '_billing_period')[0],
                'membership_subscription_interval' => get_post_meta( $subscription_id, '_billing_interval')[0],
                'membership_wp_user_id' => get_current_user_id()
              ];

              if( $membership_tier->tier_data['type'] == 'organization' ) {
                    $membership['organization_name'] = wc_get_order_item_meta( $item->get_id(), '_org_name', true);
                    $membership['organization_uuid'] = wc_get_order_item_meta( $item->get_id(), '_org_uuid', true);
                    $membership['membership_seats'] = $membership_tier->tier_data['product_data']['max_seats'];
              }

              $order_meta_id = add_post_meta( $order_id, '_wicket_membership_'.$product_id,  json_encode( $membership ), 0 );
              $subscription_meta_id = add_post_meta( $subscription_id, '_wicket_membership_'.$product_id,  json_encode( $membership ),0 );
              $membership['order_meta_id'] = $order_meta_id;
              $membership['subscription_meta_id'] = $subscription_meta_id;
              $memberships[] = $membership;
          }
        }
      }
      return $memberships;
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

    //get_person_uuid
    $user_id = $order->get_user_id();
    $user = get_user_by( 'id', $user_id );
    $person_uuid = $user->data->user_login;
    
    $subscriptions = wcs_get_subscriptions( ['order_type' => 'parent', 'order_id' => $order_id] );
    if( empty( $subscriptions) ) {
      //create subscriptions for non-subscription products tied to tiers
      $MSC = new Membership_Subscription_Controller(); 
      $MSC->create_subscriptions( $order, $user ); // create subscriptions
    }

    //get membership_data from subscriptions
    $memberships = $self->get_memberships_data_from_subscription_products( $order ); // get_membership_data_from_order

    //membership data arrays
    $memberships = array_map(function (array $arr) use ($user_id, $person_uuid) {
        $arr['person_uuid'] = $person_uuid;
        $arr['user_id'] = $user_id;
        return $arr;
    }, $memberships);

    //connect product memberships and create subscriptions
    foreach ($memberships as $membership) {
      do_action( 'wicket_member_create_record' , $membership );
    }
  }

  /**
   * Create the membership records
   */
  public static function create_membership_record( $membership ) {
    $self = new self();
    if($self->bypass_wicket) {
      //Don't create the wicket connection when testing
      $self->create_local_membership_record(  $membership, $self->guidv4().'-fake' );
      return $membership;  
    }
    $wicket_uuid = $self->create_mdp_record( $membership );
    if( !empty( $wicket_uuid ) ) {
      $self->create_local_membership_record(  $membership, $wicket_uuid );
    }
    $self->update_membership_subscription( $membership );
    return $membership;
  }

  /**
   * Gen UUID
   */
  private function guidv4($data = null) {
    $data = $data ?? random_bytes(16);
    assert(strlen($data) == 16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
  }

  /** 
   * Update the subscription
   */
  public function update_membership_subscription( $membership ) {
    $start_date   = $membership['membership_starts_at'];
    $end_date     = $membership['membership_ends_at'];
    $expire_date  = $membership['membership_expires_at'];
    $dates_to_update['start_date']    = date('Y-m-d H:i:s', strtotime( substr($start_date,0,10)." 00:00:00"));
    $dates_to_update['end']           = date('Y-m-d H:i:s', strtotime( substr($expire_date,0,10)." 00:00:00" ));
    $dates_to_update['next_payment']  = date('Y-m-d H:i:s', strtotime( substr($end_date,0,10)." 00:00:00" ));
    //var_dump($dates_to_update);exit;
    $sub = wcs_get_subscription( $membership['membership_subscription_id'] ); 
    $sub->update_dates($dates_to_update);
  }

  
  /**
   * Create the Membership Record in MDP
   */
  private function create_mdp_record( $membership ) {
    $wicket_uuid = $this->check_mdp_membership_record_exists( $membership );
    if( empty( $wicket_uuid ) ) {
      if( $membership['membership_type'] == 'individual' ) {
        $response = wicket_assign_individual_membership( 
          $membership['person_uuid'],
          $membership['membership_tier_uuid'], 
          $membership['membership_starts_at'],
          $membership['membership_ends_at']
        );  
      } else {
        $response = wicket_assign_organization_membership( 
          $membership['person_uuid'],
          $membership['organization_uuid'],
          $membership['membership_tier_uuid'], 
          $membership['membership_starts_at'],
          $membership['membership_ends_at']
        );  
      }
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
      $membership['membership_tier_uuid'], 
      $membership['membership_starts_at'], 
      $membership['membership_ends_at']
    );
    return $wicket_uuid;
  }

  /**
   * Create the WP Membership Record
   */
  private function create_local_membership_record( $membership, $wicket_uuid ) {
    if( ! $membership_post = $this->check_local_membership_record_exists( $membership )) {
      $meta = [
        'status' => 'active',
        'member_type' => $membership['membership_type'],
        'user_id' => $membership['user_id'],
        'start_date' => $membership['membership_starts_at'],
        'end_date' => $membership['membership_ends_at'],
        'expiry_date' => !empty($membership['membership_expires_at']) ? $membership['membership_expires_at'] : $membership['membership_ends_at'],
        'early_renew_date' => !empty($membership['membership_early_renew_at']) ? $membership['membership_early_renew_at'] : $membership['membership_ends_at'],
        'membership_tier_uuid' => $membership['membership_tier_uuid'],
        'wicket_uuid' => $wicket_uuid,
      ];
      if( $membership['membership_type'] == 'organization') {
        $meta['org_name'] = $membership['organization_name'];
        $meta['org_uuid'] = $membership['organization_uuid'];
        $meta['org_seats'] = $membership['membership_seats'];
      }
      $membership_post = wp_insert_post([
        'post_type' => $this->membership_cpt_slug,
        'post_status' => 'publish',
        'meta_input'  => $meta
      ]);
    }
      
    $order_meta = get_post_meta( $membership['membership_parent_order_id'], '_wicket_membership_'.$membership['membership_product_id'] );
    $order_meta_array = json_decode( $order_meta[0], true);
    $order_meta_array['membership_post_id'] = $membership_post;
    $order_meta_array['membership_wicket_uuid'] = $wicket_uuid;
    update_post_meta( $membership['membership_parent_order_id'], '_wicket_membership_'.$membership['membership_product_id'], json_encode( $order_meta_array) );

    $subscription_meta = get_post_meta( $membership['membership_subscription_id'], '_wicket_membership_'.$membership['membership_product_id'] );
    $subscription_meta_array = json_decode( $subscription_meta[0], true);
    $subscription_meta_array['membership_post_id'] = $membership_post;
    $subscription_meta_array['membership_wicket_uuid'] = $wicket_uuid;
    update_post_meta( $membership['membership_subscription_id'], '_wicket_membership_'.$membership['membership_product_id'], json_encode( $subscription_meta_array) );

    return $membership_post;
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
          'value'   => $membership['membership_starts_at'],
          'compare' => '='
        ),
        array(
          'key'     => 'end_date',
          'value'   => $membership['membership_ends_at'],
          'compare' => '='
        ),
        array(
          'key'     => 'membership_tier_uuid',
          'value'   => $membership['membership_tier_uuid'],
          'compare' => '='
        )
      )
    );;
    $posts = new \WP_Query( $args );
    if( !empty( $posts->found_posts ) ) {      
      return $posts->posts[0]->ID;
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

  public function get_members_list_group_by_filter($groupby){
    global $wpdb;
    return $wpdb->postmeta . '.meta_value ';
 }

  public function get_members_list( $type, $page, $posts_per_page, $status, $filter = [], $order_col = null, $order_dir = null ) {
    if( (! in_array( $type, ['individual', 'organization'] ))) {
      return;
    }
    if( ! $page = intval( $page )) {
      $page = 1;
    }
    if( ! $posts_per_page = intval( $posts_per_page )) {
      $posts_per_page = 25;
    }
    if( ! $status = sanitize_text_field( $status )) {
      $status = "active";
    }
    $args = array(
      'post_type' => $this->membership_cpt_slug,
      'post_status' => 'publish',
      'posts_per_page' => $posts_per_page,
      'paged' => $page,
      'meta_key' => $order_col,
      'orderby'   => 'meta_value',
      'order' => $order_dir,
      'meta_query'     => array(
        array(
          'key'     => 'member_type',
          'value'   => $type,
          'compare' => '='
        )
      )
    );

    if( ! empty( $filter ) ) {
      foreach($filter as $key => $val) {
        if( in_array( $key,  ['location', 'status', 'tier'] )) {
          $args['meta_query'][] = array(
            'key'     => $key,
            'value'   => $val,
            'compare' => '='
          );
        }
      }
    }
    
    if( $type == 'organization' ) {
      add_filter('posts_groupby', [ $this, 'get_members_list_group_by_filter' ]);
      $args['meta_key'] = 'org_uuid';
      $tiers = new \WP_Query( $args );
      remove_filter('posts_groupby', [ $this, 'get_members_list_group_by_filter' ]);
    } else {
      $tiers = new \WP_Query( $args );
    }
    foreach( $tiers->posts as &$tier ) {
      $tier_meta = get_post_meta( $tier->ID );
      $tier->meta = array_map( function( $item ) {
        if( ! str_starts_with( key( (array) $item), '_' ) ) {
          return $item[0];
        }
      }, $tier_meta);
      if( $type != 'organization' ) {
        $user = get_userdata( $tier->meta['user_id'][0]);
        $tier->user = $user->data;
      } else {
        $tier->user = new \stdClass();
      }
    }
    return [ 'results' => $tiers->posts, 'page' => $page, 'posts_per_page' => $posts_per_page, 'count' => count( $tiers->posts ) ];
  }
}
