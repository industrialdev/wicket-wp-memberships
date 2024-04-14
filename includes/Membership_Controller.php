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

    // TEMPORARILY INJECT MEMBERSHIP META DATA into order and subscription pages
    add_action( 'woocommerce_admin_order_data_after_shipping_address', [$this, 'wps_select_checkout_field_display_admin_order_meta'], 10, 1 );
    add_action( 'wcs_subscription_details_table_before_dates', [$this, 'wps_select_checkout_field_display_admin_order_meta'], 10, 1 );
  }

    // TEMPORARILY INJECT MEMBERSHIP META DATA into order and subscription pages
    function wps_select_checkout_field_display_admin_order_meta( $post ) {
    $post_meta = get_post_meta( $post->get_id() );
    foreach($post_meta as $key => $val) {
     if( str_starts_with( $key, '_wicket_membership_')) {
        echo '<br>'.$post->get_id().'<strong>'.$key.':</strong><pre>';var_dump( maybe_unserialize( $val[0] )); echo '</pre>';
      }
    }
}

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
          $membership_tiers = $this->get_tiers_from_product( $product_id );
          //echo '<pre>'; var_dump( $membership_tiers );

          if( !empty( $membership_tiers )) {
            foreach ($membership_tiers as $membership_tier) {
              $config = new Membership_Config( $membership_tier->config_id );
              $period_data = $config->get_period_data();
              $dates = $this->get_membership_dates( $config );

              $membership = [
                'membership_parent_order_id' => $order_id,
                'membership_subscription_id' => $subscription_id,
                'membership_product_id' => $product_id,
                'membership_wp_id' => $membership_tier->ID,
                'membership_tier_uuid' => $membership_tier->tier_uuid,
                'member_type' => $membership_tier->type,
                'membership_starts_at' => $dates['start_date'],
                'membership_ends_at' =>  $dates['end_date'],
                'membership_expires_at' => !empty($dates['expires_at']) ? $dates['expires_at'] : $dates['ends_at'],
                'membership_period' => $period_data['period_type'],
                'membership_interval' => $period_data['period_count'],
                'membership_subscription_period' => get_post_meta( $subscription_id, '_billing_period')[0],
                'membership_subscription_interval' => get_post_meta( $subscription_id, '_billing_interval')[0],
              ];

              $org_uuid = $this->guidv4(); // <!------- Random Org ID Set <!------- Random Org ID Set <!---------
              if( $membership_tier->type == 'organization' && !empty( $org_uuid ) ) {
                foreach( $membership_tier->wc_products as $tier_product ) {
                  if( $tier_product['product_id'] == $product_id ) {
                    $membership['organization_uuid'] = $org_uuid;
                    $membership['membership_seats'] = $tier_product['seats'];
                  }
                }
              }

              $order_meta_id = add_post_meta( $order_id, '_wicket_membership_'.$product_id,  json_encode( $membership ), 0 );
              $subscription_meta_id = add_post_meta( $subscription_id, '_wicket_membership_'.$product_id,  json_encode( $membership ),0 );
              $membership['order_meta_id'] = $order_meta_id;
              $membership['subscription_meta_id'] = $subscription_meta_id;
              $memberships[] = $membership;
            }
          }
        }
      }
      return $memberships;
  }

  /**
   * Determine the STart And ENd Date based on config settings
   * If this is a renewal we need to consider early renewal still in previous membership date period
   */
  public function get_membership_dates( $config ) {
    $cycle_data = $config->get_cycle_data();
    if( $cycle_data['cycle_type'] == 'anniversary' ) {
      $dates['start_date'] = (new \DateTime( date("Y-m-d"), wp_timezone() ))->format('c');
      $period_type  = !in_array( $cycle_data['anniversary_data']["period_type"], ['year','month','day'] )
                        ? 'year' : $cycle_data['anniversary_data']["period_type"];
      $the_end_date = date("Y-m-d", strtotime("+1 ".$period_type));
      if( in_array( $period_type, ['year', 'month']) && $cycle_data['align_end_dates_enabled'] !== false ) {
        switch( $cycle_data['anniversary_data']["align_end_dates_type"] ) {
          case 'first-day-of-month':
            $the_end_date = date("Y-m-1", strtotime("+1 ".$period_type));
            break;
          case '15th-of-month':
            $the_end_date = date("Y-m-15", strtotime("+1 ".$period_type));
            break;
          case 'last-day-of-month':
            $the_end_date = date("Y-m-t", strtotime("+1 ".$period_type));
            break;
        }
      }
      $dates['end_date'] = (new \DateTime( $the_end_date, wp_timezone() ))->format('c');
    } else {    
      $dates['start_date'] = (new \DateTime( date("Y-m-d"), wp_timezone() ))->format('c');
      $dates['end_date'] = (new \DateTime( date("Y-m-d", strtotime("+1 year")), wp_timezone() ))->format('c');
      $current_time = current_time( 'timestamp' );
      $seasons = $config->get_calendar_seasons();
      foreach( $seasons as $season ) {
        if( $season['active'] && ( $current_time >= strtotime( $season['start_date'] )) && ( $current_time <= strtotime( $season['end_date'] ))) {
          $dates['end_date'] = $season['end_date'];
        }
      }
    }
    if( $grace_period = $config->get_late_fee_window_days()) {
      $dates['expires_at'] = (new \DateTime( date($dates['end_date'],  strtotime("+$grace_period days")), wp_timezone() ))->format('c');
    }
    return $dates;
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
      if( $membership['member_type'] == 'individual' ) {
        $response = wicket_assign_individual_membership( 
          $membership['person_uuid'],
          $membership['membership_tier_uuid'], 
          $membership['membership_starts_at'],
          $membership['membership_ends_at']
        );  
      } else {
        $response = wicket_assign_organization_membership( 
          $membership['person_uuid'],
          $membership['membership_tier_uuid'], 
          $membership['organization_uuid'],
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
      $membership_post = wp_insert_post([
        'post_type' => $this->membership_cpt_slug,
        'post_status' => 'publish',
        'meta_input'  => [
          'status' => 'active',
          'member_type' => $membership['member_type'],
          'user_id' => $membership['user_id'],
          'start_date' => $membership['membership_starts_at'],
          'end_date' => $membership['membership_ends_at'],
          'expiry_date' => !empty($membership['membership_expires_at']) ? $membership['membership_expires_at'] : $membership['membership_ends_at'],
          'membership_tier_uuid' => $membership['membership_tier_uuid'],
          'wicket_uuid' => $wicket_uuid,
        ]
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
}
