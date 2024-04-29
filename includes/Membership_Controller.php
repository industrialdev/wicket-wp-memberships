<?php

namespace Wicket_Memberships;

use Wicket_Memberships\Helper;
use Wicket_Memberships\Membership_Tier;
use Wicket_Memberships\Membership_Config;

/**
 * Main controller methods
 * @package Wicket_Memberships
 */
class Membership_Controller {

  private $error_message = '';
  private $membership_cpt_slug = '';
  private $membership_config_cpt_slug = '';
  private $membership_tier_cpt_slug = '';
  private $membership_search_term = '';

  //don't create wicket connection - for testing locally
  public $bypass_wicket;
  public $bypass_status_change_lockout;

  public function __construct() {
    $this->bypass_wicket = env('BYPASS_WICKET') ?? false;
    $this->bypass_status_change_lockout = env('BYPASS_STATUS_CHANGE_LOCKOUT') ?? false;
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
        echo '<br>'.$post->get_id().'<strong>'.$key.':</strong><pre>';var_dump( json_decode( maybe_unserialize( $val[0] ), true) ); echo '</pre>';
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
      $value = isset( $_REQUEST['org_uuid'] ) ? sanitize_text_field( $_REQUEST['membership_post_id_renew'] ) : '';
      echo '<div><label>membership_post_id_renew</label><p><input type="text" name="membership_post_id_renew" value="' . $value . '"></p></div>';
  }

  function add_cart_item_data( $cart_item_meta, $product_id ) {
      if ( isset( $_REQUEST ['org_name'] ) && isset( $_REQUEST ['org_uuid'] ) ) {
          $org_data[ 'org_name' ] = isset( $_REQUEST['org_name'] ) ?  sanitize_text_field ( $_REQUEST['org_name'] ) : "" ;
          $org_data[ 'org_uuid' ] = isset( $_REQUEST['org_uuid'] ) ? sanitize_text_field ( $_REQUEST['org_uuid'] ): "" ;
          $org_data[ 'membership_post_id_renew' ] = isset( $_REQUEST['membership_post_id_renew'] ) ? sanitize_text_field ( $_REQUEST['membership_post_id_renew'] ): "" ;
          $cart_item_meta['org_data'] = $org_data ;
      }
      return $cart_item_meta;
  }

  function get_item_data ( $other_data, $cart_item ) {
      if ( isset( $cart_item [ 'org_data' ] ) ) {
          $org_data  = $cart_item [ 'org_data' ];
          $data[] = array( 'name' => 'Org Name', 'display'  => $org_data['org_name'] );
          $data[] = array( 'name' => 'Org UUID', 'display'  => $org_data['org_uuid'] );
          $data[] = array( 'name' => 'Renew Membership Post ID', 'display'  => $org_data['membership_post_id_renew'] );
      }
      return $data;
  }

  function add_order_item_meta ( $item_id, $values ) {
      if ( isset( $values [ 'org_data' ] ) ) {
          $custom_data  = $values [ 'org_data' ];
          wc_add_order_item_meta( $item_id, '_org_name', $custom_data['org_name'] );
          wc_add_order_item_meta( $item_id, '_org_uuid', $custom_data['org_uuid'] );
          wc_add_order_item_meta( $item_id, '_membership_post_id_renew', $custom_data['membership_post_id_renew'] );
      }
  }

  ///////////////////////////////////////////////////////////////////////////////////////////////////////
  // Membership_Controller methods start here
  ///////////////////////////////////////////////////////////////////////////////////////////////////////

  /**
   * Change the status on a membership record
   *
   * @param integer $id
   * @param string $status
   * @return integer
   */
  public function update_membership_status( $id, $status ) {
    if( is_numeric( $id )) {
      $post_id = $id;
    } else {
      $wicket_uuid = $id;
      $query = new \WP_Query( 
        array(
        'posts_per_page'   => 1,
        'post_type'        => $this->membership_cpt_slug,
        'meta_key'         => 'wicket_uuid',
        'meta_value'       => $wicket_uuid
    ) );
      $post_id = $query->posts[0]->ID;
    }
    
    $meta['membership_status'] = $status;

    $response = wp_update_post([
      'ID' => $post_id,
      'post_type' => $this->membership_cpt_slug,
      'meta_input'  => $meta
    ]);

    return $response;
  }

  /**
   * Check if the renewal order date for this item is within the renewal period
   */
  public static function validate_renewal_order_items( $item, $cart_item_key, $values, $order ) {
    $self = new self();
    $membership_tier = Membership_Tier::get_tier_by_product_id( $item->get_product_id() );
    $config = new Membership_Config( $membership_tier->tier_data['config_id'] );

    // do we have a current membership post_id from cart for renewal
    $membership_post_id_renew = wc_get_order_item_meta( $item->get_id(), '_membership_post_id_renew', true);
    if( empty( $membership_post_id_renew ) ) {
      //check if the order is renewing an active membership into the same tier
      $self_renew = $self->get_my_memberships( 'active', $membership_tier->tier_data['mdp_tier_uuid'] );
      if( !empty( $self_renew ) ) {
        $membership_post_id_renew = $self_renew[0]->ID;
      }
    }
    //if we have a current membership_post ID now  get the current membership data
    if( !empty( $membership_post_id_renew ) ) {
      $membership_current = $self->get_membership_array_from_post_id( $membership_post_id_renew );
    }
    if( !empty( $membership_current ) && $early_renewal_date = $config->is_valid_renewal_date( $membership_current ) ) {
      $error_text = sprintf( __("Your membership is not due for renewal yet. You can renew starting %s.", "wicket-memberships" ), date("l jS \of F Y", strtotime($early_renewal_date)));
      $_SESSION['wicket_membership_error'] = $error_text;
      throw new \Exception( $error_text );
    }
  }

  /**
   * Get memberships with config from tier by products on the order
   */
  private function get_memberships_data_from_subscription_products( $order ) {
    $membership_current = null;
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
              //if we have the current membership_post ID in the renew field on cart item
              if( $membership_post_id_renew = wc_get_order_item_meta( $item->get_id(), '_membership_post_id_renew', true) ) {
                $membership_current = $this->get_membership_array_from_post_id( $membership_post_id_renew );
              }
              //TODO: do renewal memberships start on current date or end date of previous membership - current is end_date last membersrship
              $dates = $config->get_membership_dates( $membership_current );
              $user_object = wp_get_current_user();
              $membership = [
                'membership_parent_order_id' => $order_id,
                'membership_subscription_id' => $subscription_id,
                'membership_product_id' => $product_id,
                'membership_tier_post_id' => $membership_tier->get_membership_tier_post_id(),
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
                'membership_wp_user_id' => $user_object->ID,
                'membership_wp_user_display_name' => $user_object->display_name,
                'membership_wp_user_email' => $user_object->user_email
              ];

              if( $membership_tier->tier_data['type'] == 'organization' ) {
                    $membership['organization_name'] = wc_get_order_item_meta( $item->get_id(), '_org_name', true);
                    $membership['organization_uuid'] = wc_get_order_item_meta( $item->get_id(), '_org_uuid', true);
                    $membership['membership_seats'] = $membership_tier->tier_data['product_data']['max_seats'];
              }
              if( !empty( $membership_post_id_renew )) {
                $membership['previous_membership_post_id'] = $membership_post_id_renew;
              }
              delete_post_meta( $order_id, '_wicket_membership_'.$product_id );
              $order_meta_id = add_post_meta( $order_id, '_wicket_membership_'.$product_id,  json_encode( $membership ), 1 );
              delete_post_meta( $subscription_id, '_wicket_membership_'.$product_id );
              $subscription_meta_id = add_post_meta( $subscription_id, '_wicket_membership_'.$product_id,  json_encode( $membership ), 1 );

              $membership['order_meta_id'] = $order_meta_id;
              $membership['subscription_meta_id'] = $subscription_meta_id;
              $memberships[] = $membership;
          }
        }
      }
      return $memberships;
  }

  /**
   * Get the membership json data on order using membership post_id
   *
   * @param integer $membership_post_id
   * @return array
   */
  public static function get_membership_array_from_post_id( $membership_post_id ) {
    $self = new self();
    $mship_order_id = get_post_meta( $membership_post_id, 'membership_order_id', true );
    $mship_product_id = get_post_meta( $membership_post_id, 'membership_product_id', true );
    if( empty( $mship_order_id ) || empty( $mship_product_id ) ) {
      return [];
    }
    $membership_current = $self->get_membership_array_from_order_and_product_id( $mship_order_id, $mship_product_id ); 
    return $membership_current; 
  }

    /**
   * Get the membership json data on order using order post_id and subscription post_id
   *
   * @param integer $mship_order_id
   * @param integer $mship_product_id
   * @return array
   */
  public static function get_membership_array_from_order_and_product_id( $mship_order_id, $mship_product_id ) {
    $membership_current = get_post_meta( $mship_order_id, '_wicket_membership_'.$mship_product_id, true ); 
    if( empty( $membership_current ) ) {
      return [];
    }
    return json_decode( $membership_current, true ); 
  }

  /**
   * Update the membership json data stored on order and subscription
   *
   * @param integer $membership_post_id
   * @param array $meta_array
   * @return void
   */
  public function amend_membership_order_json( $membership_post_id, $meta_array ) {
    $membership_array = $this->get_membership_array_from_post_id( $membership_post_id );
    $updated_membership_array = array_merge($membership_array, $meta_array);
    update_post_meta( $membership_array['membership_parent_order_id'], '_wicket_membership_'.$membership_array['membership_product_id'], json_encode( $updated_membership_array) );
    update_post_meta( $membership_array['membership_subscription_id'], '_wicket_membership_'.$membership_array['membership_product_id'], json_encode( $updated_membership_array) );
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
    if( 0 && empty( $subscriptions ) ) {
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
   * Add renewal transition dates to Advanced Scheduler - fallback with wp_cron
   */
  private function scheduler_dates_for_expiry( $membership ) {
    $start_date = strtotime( $membership['membership_starts_at'] );
    $early_renew_date = strtotime( $membership['membership_early_renew_at'] );
    $end_date = strtotime( $membership['membership_ends_at'] );
    $expiry_date = strtotime( $membership['membership_expires_at'] );

    $args = [
      'membership_order_id' => $membership['membership_parent_order_id'],
      'membership_product_id' => $membership['membership_product_id'],
    ];

    if ( function_exists('as_schedule_single_action') ) {
      //as_schedule_single_action( $timestamp, $hook, $args, $group, $unique, $priority );
      as_schedule_single_action( $early_renew_date, 'add_membership_early_renew_at', $args, 'wicket-membership-plugin', true );
      as_schedule_single_action( $end_date, 'add_membership_ends_at', $args, 'wicket-membership-plugin', true );
      as_schedule_single_action( $expiry_date, 'add_membership_expires_at', $args, 'wicket-membership-plugin', true );
      //to expire old membership when new one starts
      if( !empty( $membership['previous_membership_post_id'] ) ) {
        if( current_time( 'timestamp' ) >= $start_date ) {
          $this->catch_expire_current_membership( $membership['previous_membership_post_id'] );
        } else {
          as_schedule_single_action( $start_date, 'expire_old_membership_on_new_starts_at', [ 'previous_membership_post_id' => $membership['previous_membership_post_id'], 'new_membership_post_id' => $membership['membership_post_id'] ], 'wicket-membership-plugin', true );
        }
      }
    } else {
      wp_schedule_single_event( $early_renew_date, 'add_membership_early_renew_at', $args );
      wp_schedule_single_event( $end_date, 'add_membership_ends_at', $args );
      wp_schedule_single_event( $expiry_date, 'add_membership_expires_at', $args );
      //to expire old membership when new one starts
      if( !empty( $membership['previous_membership_post_id'] ) ) {
        if( current_time( 'timestamp' ) >= $start_date ) {
          $this->catch_expire_current_membership( $membership['previous_membership_post_id'] );
        } else {
          wp_schedule_single_event( $start_date, 'expire_old_membership_on_new_starts_at', [ 'previous_membership_post_id' => $membership['previous_membership_post_id'], 'new_membership_post_id' => $membership['membership_post_id'] ] );
        }
      }
    }
  }

  function catch_membership_early_renew_at( $membership_order_id, $membership_product_id ) {
    $membership = $this->get_membership_array_from_order_and_product_id( $membership_order_id, $membership_product_id );
    $this->membership_early_renew_at_date_reached( $membership );
  }

  function catch_membership_ends_at( $membership_order_id, $membership_product_id ) {
    $membership = $this->get_membership_array_from_order_and_product_id( $membership_order_id, $membership_product_id );
    $this->membership_ends_at_date_reached( $membership );
  }

  function catch_membership_expires_at( $membership_order_id, $membership_product_id ) {
    $membership = $this->get_membership_array_from_order_and_product_id( $membership_order_id, $membership_product_id );
    $this->membership_expires_at_date_reached( $membership );
  }

  public function membership_early_renew_at_date_reached( $membership ) {

  }

  public function membership_ends_at_date_reached( $membership ) {

  }

  public function membership_expires_at_date_reached( $membership ) {

  }

  /**
   * Create the membership records
   */
  public static function create_membership_record( $membership ) {
    $self = new self();
    $self->scheduler_dates_for_expiry( $membership );

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
   * Update the membership record in MDP
   */

   public function update_mdp_record( $membership, $meta_data ) {
    if( $membership['membership_type'] == 'individual' ) {
      $response = wicket_update_individual_membership_dates( 
        $membership['membership_wicket_uuid'], 
        $meta_data['membership_starts_at'],
        $meta_data['membership_ends_at']
      );  
    } else {
      $response = wicket_update_organization_membership_dates(
        $membership['membership_wicket_uuid'], 
        $meta_data['membership_starts_at'],
        $meta_data['membership_ends_at']
      );  
    }
    if( is_wp_error( $response ) ) {
      return [ 'error' => $response->get_error_message( 'wicket_api_error' ) ];
    } else {
      return $response;
    } 
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

  public function update_local_membership_record( $membership_post_id, $meta_data ) {
    return wp_update_post([
      'ID' => $membership_post_id,
      'post_type' => $this->membership_cpt_slug,
      'post_status' => 'publish',
      'meta_input'  => $meta_data
    ]);
  }

  /**
   * Create the WP Membership Record
   */
  private function create_local_membership_record( $membership, $wicket_uuid ) {
    $status = Wicket_Memberships::STATUS_ACTIVE;
    if( (new Membership_Tier( $membership['membership_tier_post_id'] ))->is_approval_required() ) {
      $membership['membership_status'] = Wicket_Memberships::STATUS_PENDING;
    } else if( strtotime( $membership['membership_starts_at'] ) > current_time( 'timestamp' ) ) {
      $status = Wicket_Memberships::STATUS_DELAYED;
    }

    $meta = [
      'membership_status' => $status,
      'member_type' => $membership['membership_type'],
      'user_id' => $membership['user_id'],
      'start_date' => $membership['membership_starts_at'],
      'end_date' => $membership['membership_ends_at'],
      'expiry_date' => !empty($membership['membership_expires_at']) ? $membership['membership_expires_at'] : $membership['membership_ends_at'],
      'early_renew_date' => !empty($membership['membership_early_renew_at']) ? $membership['membership_early_renew_at'] : $membership['membership_ends_at'],
      'membership_tier_uuid' => $membership['membership_tier_uuid'],
      'membership_tier_name' => $membership['membership_tier_name'],
      'wicket_uuid' => $wicket_uuid,
      'user_name' => $membership['membership_wp_user_display_name'],
      'user_email' => $membership['membership_wp_user_email'],
      'membership_order_id' => $membership['membership_parent_order_id'],
      'membership_product_id' => $membership['membership_product_id'],
    ];
    if( $membership['membership_type'] == 'organization') {
      $meta['org_name'] = $membership['organization_name'];
      $meta['org_uuid'] = $membership['organization_uuid'];
      $meta['org_seats'] = $membership['membership_seats'];
    }

    $membership_post = $this->check_local_membership_record_exists( $membership );
      if( $membership_post ) {
        wp_update_post([
          'ID' => $membership_post,
          'post_type' => $this->membership_cpt_slug,
          'post_status' => 'publish',
          'meta_input'  => $meta
        ]);
      } else {
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
   * Called from schedule_dates to expire the previous membership and enable a new membership if required
   * Called directly to expire membership now if we are in grace period
   * Called through a scheduled hook if we are in early renewal and this is where it will also activate the new membership when hook fires
   *
   * @param integer $previous_membership_post_id
   * @param integer $new_membership_post_id
   * @return void
   */
  public function catch_expire_current_membership( $previous_membership_post_id, $new_membership_post_id = 0 ) {
    if( ! empty( $previous_membership_post_id ) ) {
      $this->update_membership_status( $previous_membership_post_id, Wicket_Memberships::STATUS_EXPIRED );
    }
    if( ! empty( $new_membership_post_id ) ) {
      $this->update_membership_status( $new_membership_post_id, Wicket_Memberships::STATUS_ACTIVE );
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
    );

    if( ! empty( $membership['organization_uuid']) ) {
      $args['meta_query'][] = array(
        'key'     => 'org_uuid',
        'value'   => $membership['organization_uuid'],
        'compare' => '='
      );
    }

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

  /**
   * Get membership record(s)
   *
   * @param string $flag membership state or all
   * @param string $tier_uuid 
   * @param integer|null $user_id
   * @return array
   */
  public function get_my_memberships( $flag = 'all', $tier_uuid = '', $user_id = null ) {
    if( empty( $user_id ) ) {
      $user_id = get_current_user_id();
    }

    if( $flag == 'all' ) {
      $status = [ 
        Wicket_Memberships::STATUS_ACTIVE, 
        Wicket_Memberships::STATUS_EXPIRED, 
        Wicket_Memberships::STATUS_DELAYED
      ];
    } else {
      $status[] = $flag;
    }

    $args = array(
      'post_type' => $this->membership_cpt_slug,
      'post_status' => 'publish',
      'posts_per_page' => -1,
      'meta_query'     => array(
        array(
          'key'     => 'user_id',
          'value'   => $user_id,
          'compare' => '='
        ),
        array(
          'key'     => 'membership_status',
          'value'   => $status,
          'compare' => 'IN'
        ),
      )
    );

    if( ! empty( $tier_uuid )) {
      $args['meta_query'][] = [
        'key'     => 'membership_tier_uuid',
        'value'   => $tier_uuid,
        'compare' => '='
      ];
    }
    $memberships = get_posts( $args );
    return $memberships;
  }

  /**
   * Get Memberships in Renewal Periods
   */
   public function get_my_early_renewals( $user_id = null ) {
    $early_renewal = [];
    $grace_period = [];

    //TODO: remove open lookup
    if( empty( $user_id ) ) {
      $user_id = get_current_user_id();
    }
    
    $args = array(
      'post_type' => $this->membership_cpt_slug,
      'post_status' => 'publish',
      'posts_per_page' => -1,
      'meta_query'     => array(
        array(
          'key'     => 'user_id',
          'value'   => $user_id,
          'compare' => '='
        ),
        array(
          'key'     => 'membership_status',
          'value'   => Wicket_Memberships::STATUS_ACTIVE,
          'compare' => '='
        ),
      )
    );
    $memberships = get_posts( $args );
    foreach( $memberships as &$membership) {
      $meta_data = get_post_meta( $membership->ID );
      $membership->meta = array_map( function( $item ) {
        if( ! str_starts_with( key( (array) $item), '_' ) ) {
          return $item[0];
        }
      }, $meta_data);

      $membership->data = $this->get_membership_array_from_post_id( $membership->ID );
      
      $early_renew_date = strtotime( $membership->early_renew_date );
      $end_date = strtotime( $membership->end_date );
      $expiry_date = strtotime( $membership->expiry_date );
      $current_time = current_time( 'timezone' ); //strtotime ( date( "Y-m-d") . '+18 days'); //debug
      $Membership_Tier = new Membership_Tier( $membership->data['membership_tier_post_id'] );
      $next_tier = $Membership_Tier->get_next_tier();
      $config_id = $Membership_Tier->get_config_id();
      $Membership_Config = new Membership_Config( $config_id );
      $membership->next_tier = $next_tier->tier_data;
      if( $current_time >= $early_renew_date && $current_time < $end_date ) {
        $callout['callout_header'] = $Membership_Config->get_renewal_window_callout_header();
        $callout['callout_content'] = $Membership_Config->get_renewal_window_callout_content();
        $callout['callout_button_label'] = $Membership_Config->get_renewal_window_callout_button_label();
        $early_renewal[] = [
          'membership' => $membership,
          'callout' => $callout
        ];
      } else if ( $current_time >= $end_date && $current_time <= $expiry_date ) {
        $callout['callout_header'] = $Membership_Config->get_late_fee_window_callout_header();
        $callout['callout_content'] = $Membership_Config->get_late_fee_window_callout_content();
        $callout['callout_button_label'] = $Membership_Config->get_late_fee_window_callout_button_label();
        $grace_period[]  =[
          'membership' => $membership,
          'callout' => $callout
        ];
      }
    }
    return ['early_renewal' => $early_renewal, 'grace_period' => $grace_period];
  }

  public function get_members_list_group_by_filter($groupby){
    global $wpdb;
    return $wpdb->postmeta . '.meta_value ';
 }

  public function get_members_list( $type, $page, $posts_per_page, $status, $search = '', $filter = [], $order_col = null, $order_dir = null ) {
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
      $status = Wicket_Memberships::STATUS_ACTIVE;
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

    if( ! empty( $search ) ) {  
      $args['meta_query'][] = array(
        'relation' => 'OR',
          array(
            'key'     => 'user_name',
            'value'   => $search,
            'compare' => 'LIKE'
          ), 
          array(
            'key'     => 'user_email',
            'value'   => $search,
            'compare' => 'LIKE'
          ), 
          array(
            'key'     => 'membership_tier_name',
            'value'   => $search,
            'compare' => 'LIKE'
        )
      );
    }

    if( ! empty( $filter ) ) {
      foreach($filter as $key => $val) {
        if( in_array( $key,  ['membership_status', 'membership_tier_uuid'] )) {
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
        $user = get_userdata( $tier->meta['user_id'][0]);
        $tier->user = $user->data;
    }
    return [ 'results' => $tiers->posts, 'page' => $page, 'posts_per_page' => $posts_per_page, 'count' => count( $tiers->posts ) ];
  }

  public static function get_tier_info( $tier_uuids, $properties = [] ) {
    $self = new self();
    $mship_tier_array = [];
    if(! is_array( $properties ) ) {
      $properties = [];
    }
    $alltiers = get_individual_memberships();
    $all_tiers = array_reduce($alltiers['data'], function($acc, $item) {
      $acc[$item['id']] = $item;
      return $acc;
    }, []);
    if(! is_array( $tier_uuids ) ) {
      $tier_uuids = array_keys($all_tiers);
    }
    if( in_array( 'count', $properties ) ) {
      $args = array(
        'post_type' => $self->membership_cpt_slug,
        'post_status' => 'publish',
        'posts_per_page' => -1,
      );
        $args['meta_query'] = array(
          'relation' => 'OR',
      );
      
      foreach ($tier_uuids as $tier_uuid) {
          $tier_arg = array(
              'key'     => 'membership_tier_uuid',
              'value'   => $tier_uuid,
              'compare' => '='
          );
          $args['meta_query'][] = $tier_arg;
      }
      $tiers = new \WP_Query( $args );
      foreach( $tiers->posts as $tier ) {
        $tier_meta = get_post_meta( $tier->ID );
        $tier->meta = array_map( function( $item ) {
            return $item[0];
        }, $tier_meta);
      }  
    }    
    foreach( $tiers->posts as $tier ) {
      $mship_tier_array[ $tier->meta['membership_tier_uuid'] ]['name']  = $all_tiers[ $tier->meta['membership_tier_uuid'] ]['attributes']['name'];
      if( in_array( 'count', $properties ) ) {
        $mship_tiers[$tier->meta['membership_tier_uuid']] = $mship_tiers[$tier->meta['membership_tier_uuid']] + 1;
        $mship_tier_array[ $tier->meta['membership_tier_uuid'] ]['count'] = $mship_tiers[ $tier->meta['membership_tier_uuid']];
      }
    }

    foreach( $all_tiers as $key => $tier_item ) {
      if( ! array_key_exists( $key, $mship_tier_array ) && in_array( $key, $tier_uuids )) {
        $mship_tier_array[ $key ]['name'] = $tier_item['attributes']['name'];
        if( in_array( 'count', $properties ) ) {
          $mship_tier_array[ $key ]['count'] = 0;
        }
      }
    }
    return ['tier_data' => $mship_tier_array];
  }


  public static function get_org_info( $org_uuids, $properties = [] ) {
    $self = new self();
    $mship_org_array = [];
    if(! is_array( $properties ) ) {
      $properties = [];
    }
    $allorgs = wicket_get_organizations();
    $all_orgs = array_reduce($allorgs['data'], function($acc, $item) {
      $acc[$item['id']] = $item;
      return $acc;
    }, []);
    if(! is_array( $org_uuids ) ) {
      $org_uuids = array_keys($all_orgs);
    }
    if( in_array( 'count', $properties ) ) {
      $args = array(
        'post_type' => $self->membership_cpt_slug,
        'post_status' => 'publish',
        'posts_per_page' => -1,
      );
        $args['meta_query'] = array(
          'relation' => 'OR',
      );
      
      foreach ($org_uuids as $org_uuid) {
          $org_arg = array(
              'key'     => 'org_uuid',
              'value'   => $org_uuid,
              'compare' => '='
          );
          $args['meta_query'][] = $org_arg;
      }
      $orgs = new \WP_Query( $args );
      foreach( $orgs->posts as $org ) {
        $org_meta = get_post_meta( $org->ID );
        $org->meta = array_map( function( $item ) {
            return $item[0];
        }, $org_meta);
      }  
    }
    foreach( $orgs->posts as $org ) {
      $mship_org_array[ $org->meta['org_uuid'] ]['name']  = $all_orgs[ $org->meta['org_uuid'] ]['attributes']['alternate_name'];
      if( in_array( 'count', $properties ) ) {
        $mship_orgs[$org->meta['org_uuid']] = $mship_orgs[$org->meta['org_uuid']] + 1;
        $mship_org_array[ $org->meta['org_uuid'] ]['count'] = $mship_orgs[ $org->meta['org_uuid']];
      }
    }
    foreach( $all_orgs as $key => $org_item ) {
      if( ! array_key_exists( $key, $mship_org_array ) && in_array( $key, $org_uuids )) {
        $mship_org_array[ $key ]['name'] = $org_item['attributes']['alternate_name'];
        if( in_array( 'count', $properties ) ) {
          $mship_org_array[ $key ]['count'] = 0;
        }
      }
    }
    return ['org_data' => $mship_org_array];
  }

  public function get_members_filters( $type ) {
    $args = array(
      'post_type' => $this->membership_cpt_slug,
      'post_status' => 'publish',
      'posts_per_page' => -1,
    );
    if( $type != 'both' ) {
      $args['meta_query'] = array(
          array(
            'key'     => 'member_type',
            'value'   => $type,
            'compare' => '='
          )
        );
    }
    
    //tiers assigned to membership records as filters
    add_filter('posts_groupby', [ $this, 'get_members_list_group_by_filter' ]);
    $args['meta_key'] = 'membership_tier_uuid';
    $tiers = new \WP_Query( $args );
    remove_filter('posts_groupby', [ $this, 'get_members_list_group_by_filter' ]);
    foreach ($tiers->posts as $tier) {
      $filters['tiers'][] = [
        'name' => $tier->membership_tier_name,
        'value' => $tier->membership_tier_uuid
      ];
    }

    //status assigned to membership records as filters
    add_filter('posts_groupby', [ $this, 'get_members_list_group_by_filter' ]);
    $args['meta_key'] = 'membership_status';
    $tiers = new \WP_Query( $args );
    remove_filter('posts_groupby', [ $this, 'get_members_list_group_by_filter' ]);
    foreach ($tiers->posts as $tier) {
      $filters['membership_status'][] = [
        'name' => $tier->status,
        'value' => ucfirst( $tier->status )
      ];
    }
    if( $type == 'organization' ) {
      // get locations assigned to membership records as filters???
    }
    return $filters;
  }
}
