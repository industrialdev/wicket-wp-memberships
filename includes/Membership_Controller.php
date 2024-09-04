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
    $this->bypass_wicket = !empty( $_ENV['BYPASS_WICKET'] ) ?? false;
    $this->bypass_status_change_lockout = !empty( $_ENV['BYPASS_STATUS_CHANGE_LOCKOUT'] ) ?? false;
    $this->membership_cpt_slug = Helper::get_membership_cpt_slug();
    $this->membership_config_cpt_slug = Helper::get_membership_config_cpt_slug();
    $this->membership_tier_cpt_slug = Helper::get_membership_tier_cpt_slug();

    //Get onboarding data on add cart item
    add_filter( 'woocommerce_add_cart_item_data', [$this, 'add_cart_item_data'], 25, 2 );
    if( !empty( $_ENV['WICKET_MEMBERSHIPS_DEBUG_MODE'] ) ) {
      add_filter( 'woocommerce_get_item_data', [$this, 'get_item_data'] , 25, 2 ); //exposes in cart and checkout
      add_action( 'woocommerce_before_add_to_cart_button', [$this, 'product_add_on'], 9 ); //collects org data in cart
    }
    add_action( 'woocommerce_add_order_item_meta', [$this, 'add_order_item_meta'] , 10, 2);
  }

  //COLLECT CART ITEM FIELDS ON ADD TO CART
  function product_add_on() {
    //change to hidden fields and remove 'woocommerce_get_item_data' filter to hide data
    $value = isset( $_REQUEST['org_uuid'] ) ? sanitize_text_field( $_REQUEST['org_uuid'] ) : '';
    echo '<div><label>org_uuid</label><p><input type="text" name="org_uuid" value="' . $value . '"></p></div>';
    $value = isset( $_REQUEST['membership_post_id_renew'] ) ? sanitize_text_field( $_REQUEST['membership_post_id_renew'] ) : '';
    echo '<div><label>membership_post_id_renew</label><p><input type="text" name="membership_post_id_renew" value="' . $value . '"></p></div>';
}

function add_cart_item_data( $cart_item_meta, $product_id ) {
    if ( isset( $_REQUEST ['org_uuid'] ) ) {
      $cart_item_meta[ 'org_uuid' ] = isset( $_REQUEST['org_uuid'] ) ? sanitize_text_field ( $_REQUEST['org_uuid'] ): "" ;
    }
    if( isset( $_REQUEST['membership_post_id_renew']) ) {
      $cart_item_meta[ 'membership_post_id_renew' ] = isset( $_REQUEST['membership_post_id_renew'] ) ? sanitize_text_field ( $_REQUEST['membership_post_id_renew'] ): "" ;
    }
    return $cart_item_meta;
}

function get_item_data ( $other_data, $cart_item ) {
    if(!empty($cart_item['org_uuid'])) {
      $data[] = array( 'name' => 'Org UUID', 'display'  => $cart_item['org_uuid'] );
    }
    if(!empty($cart_item['membership_post_id_renew'])) {
      $data[] = array( 'name' => 'Renew Membership Post ID', 'display'  => $cart_item['membership_post_id_renew'] );            
    }
    return $data;
}

function add_order_item_meta ( $item_id, $values ) {
    if(empty(wc_get_order_item_meta( $item_id, '_org_uuid', true) && !empty($values['org_uuid']))) {
      wc_add_order_item_meta( $item_id, '_org_uuid', $values['org_uuid'] );
    }
    if(empty(wc_get_order_item_meta( $item_id, '_membership_post_id_renew', true)) && !empty($values['membership_post_id_renew'])) {
      wc_add_order_item_meta( $item_id, '_membership_post_id_renew', $values['membership_post_id_renew'] );
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
      $membership_wicket_uuid = $id;
      $query = new \WP_Query( 
        array(
        'posts_per_page'   => 1,
        'post_type'        => $this->membership_cpt_slug,
        'meta_key'         => 'membership_wicket_uuid',
        'meta_value'       => $membership_wicket_uuid
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
    $product_id = $item->get_variation_id();
    if(empty($product_id)) {
      $product_id = $item->get_product_id();
    }
    $membership_tier = Membership_Tier::get_tier_by_product_id( $product_id );
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
      $membership_current = $self->get_membership_array_from_user_meta_by_post_id( $membership_post_id_renew, $order->get_user_id() );
      $early_renewal_date = $config->is_valid_renewal_date( $membership_current );
    }
    if( !empty( $early_renewal_date ) && empty( $_ENV['WICKET_MEMBERSHIPS_DEBUG_RENEW'] )) {
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
          $product_id = $item->get_variation_id();
          if(empty($product_id)) {
            $product_id = $item->get_product_id();
          }
          $membership_tier = Membership_Tier::get_tier_by_product_id( $product_id );
          if( !empty( $membership_tier->tier_data )) {
              $config = new Membership_Config( $membership_tier->tier_data['config_id'] );
              $period_data = $config->get_period_data();
              //if we have the current membership_post ID in the renew field on cart item
              if( $membership_post_id_renew = wc_get_order_item_meta( $item->get_id(), '_membership_post_id_renew', true) ) {
                $membership_current = $this->get_membership_array_from_user_meta_by_post_id( $membership_post_id_renew, $order->get_user_id() );
                $processing_renewal = true;
              }
              //TODO: do renewal memberships start on current date or end date of previous membership - current is end_date last membersrship
              $dates = $config->get_membership_dates( $membership_current );
              $user_object = get_user_by( 'id', $order->get_user_id() );
              $membership = [
                'membership_parent_order_id' => $order_id,
                'membership_subscription_id' => $subscription_id,
                'membership_product_id' => $product_id,
                'membership_tier_post_id' => $membership_tier->get_membership_tier_post_id(),
                'membership_tier_name' => $membership_tier->tier_data['mdp_tier_name'],
                'membership_tier_uuid' => $membership_tier->tier_data['mdp_tier_uuid'],
                'membership_next_tier_id' => $membership_tier->get_next_tier_id(),
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
                'membership_wp_user_email' => $user_object->user_email,
                'membership_grace_period_days' => $config->get_late_fee_window_days()
              ];
              if(!empty($processing_renewal) && empty( $_ENV['WICKET_MEMBERSHIPS_DEBUG_RENEW'] ) ) {
                if( $config->is_valid_renewal_date( $membership_current ) ) {
                  $order->update_status('on-hold', __('Attempted to renew outside of valid renewal period. Membership not created.'));
                  return;
                }
              }
              if( $membership_tier->tier_data['type'] == 'organization' ) {
                    if(empty($membership_current['org_uuid'])) {
                      $membership['organization_uuid'] = wc_get_order_item_meta( $item->get_id(), '_org_uuid', true);
                    } else {
                      $membership['organization_uuid'] = $membership_current['org_uuid'];
                    }
                    if( $membership_tier->is_per_seat() ) {
                      $seats = $item->get_quantity();
                    } else if ( $membership_tier->is_per_range_of_seats() ) {
                      $seats = $membership_tier->get_seat_count();
                    }
                    $membership['membership_seats'] = $seats;
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
  public static function get_membership_array_from_user_meta_by_post_id( $membership_post_id, $user_id = null ) {
    if( empty( $user_id ) ) {
      $user_id = get_current_user_id();
    }
    $customer_meta = get_user_meta( $user_id, '_wicket_membership_' . $membership_post_id, true ); 
    return json_decode( $customer_meta, true ); 
  }

  /**
   * Get the membership json data on order using membership post_id
   *
   * @param integer $membership_post_id
   * @return array
   */
  public static function get_membership_array_from_post_id( $membership_post_id ) {
    $self = new self();
    $mship_order_id = get_post_meta( $membership_post_id, 'membership_parent_order_id', true );
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
   * @return boolean
   */
  public function amend_membership_json( $membership_post_id, $meta_array ) {
    $user_id = $this->get_user_id_from_membership_post( $membership_post_id );
    $membership_array = $this->get_membership_array_from_user_meta_by_post_id( $membership_post_id, $user_id );
    if( ! empty( $membership_array ) ) {
      $updated_membership_array = array_merge($membership_array, $meta_array);
      update_user_meta( $membership_array['user_id'], '_wicket_membership_'.$membership_post_id, json_encode( $updated_membership_array) );
      update_post_meta( $membership_array['membership_parent_order_id'], '_wicket_membership_'.$membership_array['membership_product_id'], json_encode( $updated_membership_array) );
      update_post_meta( $membership_array['membership_subscription_id'], '_wicket_membership_'.$membership_array['membership_product_id'], json_encode( $updated_membership_array) );  
      return true;
    }
    return false;
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
    if(empty($memberships)) {
      return;
    }
    
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
  public function scheduler_dates_for_expiry( $membership ) {
    $membership_starts_at = strtotime( $membership['membership_starts_at'] );
    $membership_early_renew_at = strtotime( $membership['membership_early_renew_at'] );
    $membership_ends_at = strtotime( $membership['membership_ends_at'] );
    $membership_expires_at = strtotime( $membership['membership_expires_at'] );

    $args = [
      'membership_parent_order_id' => $membership['membership_parent_order_id'],
      'membership_product_id' => $membership['membership_product_id'],
    ];

    if ( function_exists('as_schedule_single_action') ) {
      //as_schedule_single_action( $timestamp, $hook, $args, $group, $unique, $priority );
      as_schedule_single_action( $membership_early_renew_at, 'add_membership_early_renew_at', $args, 'wicket-membership-plugin', true );
      as_schedule_single_action( $membership_ends_at, 'add_membership_ends_at', $args, 'wicket-membership-plugin', true );
      as_schedule_single_action( $membership_expires_at, 'add_membership_expires_at', $args, 'wicket-membership-plugin', true );
      //to expire old membership when new one starts
      if( !empty( $membership['previous_membership_post_id'] ) ) {
        if( current_time( 'timestamp' ) >= $membership_starts_at ) {
          $this->catch_expire_current_membership( $membership['previous_membership_post_id'] );
        } else {
          as_schedule_single_action( $membership_starts_at, 'expire_old_membership_on_new_starts_at', [ 'previous_membership_post_id' => $membership['previous_membership_post_id'], 'new_membership_post_id' => $membership['membership_post_id'] ], 'wicket-membership-plugin', true );
        }
      }
    } else {
      wp_schedule_single_event( $membership_early_renew_at, 'add_membership_early_renew_at', $args );
      wp_schedule_single_event( $membership_ends_at, 'add_membership_ends_at', $args );
      wp_schedule_single_event( $membership_expires_at, 'add_membership_expires_at', $args );
      //to expire old membership when new one starts
      if( !empty( $membership['previous_membership_post_id'] ) ) {
        if( current_time( 'timestamp' ) >= $membership_starts_at ) {
          $this->catch_expire_current_membership( $membership['previous_membership_post_id'] );
        } else {
          wp_schedule_single_event( $membership_starts_at, 'expire_old_membership_on_new_starts_at', [ 'previous_membership_post_id' => $membership['previous_membership_post_id'], 'new_membership_post_id' => $membership['membership_post_id'] ] );
        }
      }
    }
  }

  function catch_membership_early_renew_at( $membership_parent_order_id, $membership_product_id ) {
    $membership = $this->get_membership_array_from_order_and_product_id( $membership_parent_order_id, $membership_product_id );
    $this->membership_early_renew_at_date_reached( $membership );
  }

  function catch_membership_ends_at( $membership_parent_order_id, $membership_product_id ) {
    $membership = $this->get_membership_array_from_order_and_product_id( $membership_parent_order_id, $membership_product_id );
    $this->membership_ends_at_date_reached( $membership );
  }

  function catch_membership_expires_at( $membership_parent_order_id, $membership_product_id ) {
    $membership = $this->get_membership_array_from_order_and_product_id( $membership_parent_order_id, $membership_product_id );
    $this->membership_expires_at_date_reached( $membership );
  }

  public function membership_early_renew_at_date_reached( $membership ) {
    do_action( 'wicket_memberships_renewal_period_open', $membership );
  }

  public function membership_ends_at_date_reached( $membership ) {
    do_action( 'wicket_memberships_end_date_reached', $membership );
  }

  public function membership_expires_at_date_reached( $membership ) {
    do_action( 'wicket_memberships_grace_period_expired', $membership );
  }

  /**
   * Create the membership records
   */
  public static function create_membership_record( $membership ) {
    $membership_wicket_uuid = '';
    $self = new self();

    if($self->bypass_wicket) {
      //Don't create the wicket connection when testing
      $self->create_local_membership_record(  $membership, $self->guidv4().'-fake' );
      return $membership;  
    }

    $tier = new Membership_Tier( $membership['membership_tier_post_id'] );
    //we only create the mdp record if not pending approval / not debug 
    if( ! $tier->is_approval_required() && ! $self->bypass_wicket ) {
      $membership_wicket_uuid = $self->create_mdp_record( $membership );
    }
    
    //always create the local membership record to get post_id
    $membership['membership_post_id'] = $self->create_local_membership_record(  $membership, $membership_wicket_uuid );

    //we are pending approval so change some statuses and send email
    if( $tier->is_approval_required() ) {
      $self->update_subscription_status( $membership['membership_subscription_id'], 'on-hold', 'Subscription pending approval.');
      //update membership status to pending approval
      $self->update_membership_status( $membership['membership_post_id'], Wicket_Memberships::STATUS_PENDING);
      //send the approval email notification
      $email_address = $tier->get_approval_email();
      if( !empty($membership['organization_uuid']) ) {
        $path = 'admin.php?page=wicket_org_member_edit&id=' . $membership['organization_uuid']; //PATH TO THE ORG EDIT PAGE
      } else {
        $user = get_user_by( 'id', $membership['user_id'] );    
        $membership_person_uuid = $user->data->user_login;
        $path = 'admin.php?page=wicket_individual_member_edit&id=' . $membership_person_uuid; //PATH TO THE PERSON EDIT PAGE
      }
      $member_page_link = '<a href="'.admin_url( $path ).'">'.admin_url( $path ).'</a>';
      send_approval_required_email( $email_address, $member_page_link );
    } else {
      //set the scheduled tasks
      $self->scheduler_dates_for_expiry( $membership );
      //update subscription dates
      $self->update_membership_subscription( $membership, [ 'start_date', 'end_date' ] );
      $membership_post_data = Helper::get_post_meta( $$membership['membership_post_id'] );
      do_action('wicket_membership_created_mdp', $membership_post_data);
    }
    return $membership;
  }

  /** 
   * Update subscription status
   */

   public function update_subscription_status( $membership_subscription_id, $status, $note = '' ) {
    if( function_exists( 'wcs_get_subscription' )) {
      $sub = wcs_get_subscription( $membership_subscription_id );
      if(! empty($sub)) {
        try {
          $sub->update_status( $status, $note );
        } catch (\Exception $e) {
          $sub->update_status( 'active', 'Subscription temporarily set active.' );
          $sub->update_status( $status, $note );
        }  
      }
    }
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
  public function update_membership_subscription( $membership, $fields = [ 'start_date', 'end_date', 'next_payment_date' ] ) {
    if( function_exists( 'wcs_get_subscription' )) {
      $start_date   = $membership['membership_starts_at'];
      $end_date     = $membership['membership_ends_at'];
      $expire_date  = $membership['membership_expires_at'];
      if( in_array ( 'start_date', $fields ) ) {
        $dates_to_update['start_date']    = date('Y-m-d H:i:s', strtotime( substr($start_date,0,10)." 00:00:00"));
      }
      if( in_array ( 'end_date', $fields ) ) {
        $dates_to_update['end']           = date('Y-m-d H:i:s', strtotime( substr($expire_date,0,10)." 00:00:01" ));
      }
      if( in_array ( 'next_payment_date', $fields ) ) {
        $dates_to_update['next_payment']  = date('Y-m-d H:i:s', strtotime( substr($end_date,0,10)." 00:00:00" ));
      }
      $sub = wcs_get_subscription( $membership['membership_subscription_id'] );
      if( !empty( $sub )) {
        $sub->update_dates($dates_to_update);
      }
    }
  }

  /**
   * Update the membership record in MDP
   */

   public function update_mdp_record( $membership, $meta_data ) {
    if( !empty( $_ENV['BYPASS_WICKET'] )) {
      return;
    }
    $starts_at = '';
    $ends_at = '';
    $grace_period_days = false;
    $max_assignments = false;

    if( ! empty( $meta_data['membership_starts_at'] ) ) {
      $starts_at = $meta_data['membership_starts_at'];
    }

    if( ! empty( $meta_data['membership_ends_at'] ) ) {
      $ends_at = $meta_data['membership_ends_at'];
    }

    if( $meta_data['grace_period_days'] == '0' || ! empty( $meta_data['grace_period_days'] ) ) {
      $grace_period_days = $meta_data['grace_period_days'];
    }

    if( $meta_data['max_assignments'] == '0' || ! empty( $meta_data['max_assignments'] ) ) {
      $max_assignments = $meta_data['max_assignments'];
    } else  if( $meta_data['membership_seats'] == '0' || ! empty( $meta_data['membership_seats'] ) ) {
      $max_assignments = $meta_data['membership_seats'];
    }

    if( $membership['membership_type'] == 'individual' ) {
      $response = wicket_update_individual_membership_dates( 
        $membership['membership_wicket_uuid'], 
        $starts_at,
        $ends_at,
        $grace_period_days
      );  
    } else {
      $response = wicket_update_organization_membership_dates(
        $membership['membership_wicket_uuid'], 
        $starts_at,
        $ends_at,
        $max_assignments,
        $grace_period_days
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
  public function create_mdp_record( $membership ) {
    $membership_wicket_uuid = $this->check_mdp_membership_record_exists( $membership );
    if( empty( $membership_wicket_uuid ) ) {
      if( $membership['membership_type'] == 'individual' ) {
        $response = wicket_assign_individual_membership( 
          $membership['person_uuid'],
          $membership['membership_tier_uuid'], 
          $membership['membership_starts_at'],
          $membership['membership_ends_at'],
          $membership['membership_grace_period_days']
        );  
      } else {
        if(empty($membership['organization_uuid'])) {
          $membership['organization_uuid'] = $membership['org_uuid'] ;
        }
        $response = wicket_assign_organization_membership( 
          $membership['person_uuid'],
          $membership['organization_uuid'],
          $membership['membership_tier_uuid'], 
          $membership['membership_starts_at'],
          $membership['membership_ends_at'],
          $membership['membership_seats'],
          $membership['membership_grace_period_days']
        );  
      }
      if( is_wp_error( $response ) ) {
        $this->error_message = $response->get_error_message( 'wicket_api_error' );
        $this->surface_error();
        $membership_wicket_uuid = '';
      } else {
        $membership_wicket_uuid = $response['data']['id'];
      } 
    }
    return $membership_wicket_uuid;
  }

  /**
   * Check if MDP Membership Record already exists
   */
  private function check_mdp_membership_record_exists( $membership ) {
    $membership_wicket_uuid = wicket_get_person_membership_exists(
      $membership['person_uuid'], 
      $membership['membership_tier_uuid'], 
      $membership['membership_starts_at'], 
      $membership['membership_ends_at']
    );
    return $membership_wicket_uuid;
  }

  public function update_local_membership_record( $membership_post_id, $meta_data ) {
    return wp_update_post([
      'ID' => $membership_post_id,
      'post_type' => $this->membership_cpt_slug,
      'post_status' => 'publish',
      'meta_input'  => $meta_data
    ]);
  }

  public function get_person_uuid( $user_id ) {
    $user = get_user_by( 'id', $user_id );
    return $user->user_login;
  }

  public static function get_user_id_from_membership_post( $membership_post_id ) {
    $membership_post = get_post( $membership_post_id );
    return $membership_post->user_id;
  }

  /**
   * Create the WP Membership Record
   */
  public function create_local_membership_record( $membership, $membership_wicket_uuid, $skip_approval = false ) {
    $wicket_membership_type = 'person_memberships';
    if( ! empty( $membership['membership_status'] )) {
      $status = $membership['membership_status'];
    } else {
      $status = Wicket_Memberships::STATUS_ACTIVE;
    }

    if( !($skip_approval) && (new Membership_Tier( $membership['membership_tier_post_id'] ))->is_approval_required() ) {
      $status = Wicket_Memberships::STATUS_PENDING;
    } else if( strtotime( $membership['membership_starts_at'] ) > current_time( 'timestamp' ) ) {
      $status = Wicket_Memberships::STATUS_DELAYED;
    }

    $meta = [
      'membership_status' => $status,
      'membership_type' => $membership['membership_type'],
      'user_id' => $membership['user_id'],
      'membership_starts_at' => $membership['membership_starts_at'],
      'membership_ends_at' => $membership['membership_ends_at'],
      'membership_expires_at' => !empty($membership['membership_expires_at']) ? $membership['membership_expires_at'] : $membership['membership_ends_at'],
      'membership_early_renew_at' => !empty($membership['membership_early_renew_at']) ? $membership['membership_early_renew_at'] : $membership['membership_ends_at'],
      'membership_tier_uuid' => $membership['membership_tier_uuid'],
      'membership_tier_name' => $membership['membership_tier_name'],
      'membership_next_tier_id' => $membership['membership_next_tier_id'],
      'membership_wicket_uuid' => $membership_wicket_uuid,
      'user_name' => $membership['membership_wp_user_display_name'],
      'user_email' => $membership['membership_wp_user_email'],
      'membership_parent_order_id' => $membership['membership_parent_order_id'],
      'membership_product_id' => $membership['membership_product_id'],
      'membership_subscription_id' => $membership['membership_subscription_id'],
    ];
    if( $membership['membership_type'] == 'organization') {
      $org_data = Helper::get_org_data( $membership['organization_uuid'] );
      $meta['org_location'] = $org_data['location'];
      $meta['org_name'] = $org_data['name'];
      $meta['org_uuid'] = $membership['organization_uuid'];
      $meta['org_seats'] = $membership['membership_seats'];
      $wicket_membership_type = 'organization_memberships';
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
        wicket_update_membership_external_id( $membership_wicket_uuid, $wicket_membership_type, $membership_post );
      }

    if( !empty( $membership['membership_parent_order_id'] )) {
      $order_meta = get_post_meta( $membership['membership_parent_order_id'], '_wicket_membership_'.$membership['membership_product_id'] );
      $order_meta_array = json_decode( $order_meta[0], true);
      $order_meta_array['membership_post_id'] = $membership_post;
      $order_meta_array['membership_wicket_uuid'] = $membership_wicket_uuid;
      update_post_meta( $membership['membership_parent_order_id'], '_wicket_membership_'.$membership['membership_product_id'], json_encode( $order_meta_array) );
      $customer_meta_array['membership_parent_order_id'] = $membership['membership_parent_order_id'];
    }

    if( !empty( $membership['membership_subscription_id'] )) {
      $subscription_meta = get_post_meta( $membership['membership_subscription_id'], '_wicket_membership_'.$membership['membership_product_id'] );
      $subscription_meta_array = json_decode( $subscription_meta[0], true);
      $subscription_meta_array['membership_post_id'] = $membership_post;
      $subscription_meta_array['membership_wicket_uuid'] = $membership_wicket_uuid;
      update_post_meta( $membership['membership_subscription_id'], '_wicket_membership_'.$membership['membership_product_id'], json_encode( $subscription_meta_array) );  
      $customer_meta_array['membership_subscription_id'] = $membership['membership_subscription_id'];
    }

    $customer_meta = get_user_meta( $membership['user_id'], '_wicket_membership_'.$membership['membership_post_id'] );
    if( empty( $customer_meta ) || empty( $customer_meta[0]['membership_post_id']) ) {
      $customer_meta_array = $meta;
    } else {
      $customer_meta_array = json_decode( $customer_meta[0], true);
      $customer_meta_array = array_merge( $customer_meta_array, $meta );
    }

    $customer_meta_array['membership_post_id'] = $membership_post;
    $customer_meta_array['membership_tier_post_id'] = $membership['membership_tier_post_id'];
    $customer_meta_array['membership_wicket_uuid'] = $membership_wicket_uuid;
    update_user_meta( $membership['user_id'], '_wicket_membership_'.$membership_post, json_encode( $customer_meta_array) );
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
          'key'     => 'membership_starts_at',
          'value'   => $membership['membership_starts_at'],
          'compare' => '='
        ),
        array(
          'key'     => 'membership_ends_at',
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

    if( ! empty( $membership['membership_seats']) ) {
      $args['meta_query'][] = array(
        'key'     => 'org_seats',
        'value'   => $membership['membership_seats'],
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
   public function get_membership_callouts( $user_id = null ) {
    $early_renewal = [];
    $grace_period = [];
    $pending_approval = [];
    $debug = [];

    $iso_code = apply_filters( 'wpml_current_language', null );
    if( empty( $iso_code )) {
      $locale = get_locale(); // Get the full locale (e.g., en_US)
      $iso_code = substr($locale, 0, 2); // Extract the first two characters  
    }

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
        'relation' => 'OR', 
          array(
            'key'     => 'membership_status',
            'value'   => Wicket_Memberships::STATUS_ACTIVE,
            'compare' => '='
          ),
          array(
            'key'     => 'membership_status',
            'value'   => Wicket_Memberships::STATUS_PENDING,
            'compare' => '='
          ),
        )
      )
    );
    $memberships = get_posts( $args );
    foreach( $memberships as &$membership) {
      $membership_data['ID'] = $membership->ID;

      $meta_data = get_post_meta( $membership->ID );
      $membership_data['meta'] = array_map( function( $item ) {
        if( ! str_starts_with( key( (array) $item), '_' ) ) {
          return $item[0];
        }
      }, $meta_data);

      $membership_json_data = $this->get_membership_array_from_user_meta_by_post_id( $membership->ID, $user_id );
      $Membership_Tier = new Membership_Tier( $membership_json_data['membership_tier_post_id'] );
      $membership_data['next_tier'] = false;
      $membership_data['form_page'] = false;

      if ( $membership->membership_status == 'pending') {
        $callout['type'] = 'pending_approval';
        $callout['header'] = $Membership_Tier->get_approval_callout_header( $iso_code );
        $callout['content'] = $Membership_Tier->get_approval_callout_content( $iso_code );
        $callout['button_label'] = $Membership_Tier->get_approval_callout_button_label( $iso_code );
        $callout['email'] = $Membership_Tier->get_approval_email() . '?subject=' . __( 'Re: Pending Membership Request', 'wicket-memberships');

        $pending_approval[] = [
          'membership' => $membership_data,
          'callout' => $callout
        ];
        continue;   
      }

      $membership_early_renew_at = strtotime( $membership->membership_early_renew_at );
      $membership_ends_at = strtotime( $membership->membership_ends_at );
      $membership_expires_at = strtotime( $membership->membership_expires_at );
      $current_time =  current_time( 'timestamp' );
      $membership_data['ends_in_days'] = ( ( $membership_ends_at - $current_time ) / 86400 );
      if( !empty( $_ENV['WICKET_MEMBERSHIPS_DEBUG_MODE'] ) && !empty( $_REQUEST['wicket_wp_membership_debug_days'] ) ) {
        $current_time =  strtotime ( date( "Y-m-d") . '+' . $_REQUEST['wicket_wp_membership_debug_days'] . ' days');
      }

      $config_id = $Membership_Tier->get_config_id();
      $Membership_Config = new Membership_Config( $config_id );

      $next_tier_id = $Membership_Tier->get_next_tier_id();

      if( !empty( $next_tier_id ) ) {
        $next_tier = new Membership_Tier( $next_tier_id );
        $membership_data['next_tier'] = $next_tier->tier_data;  
      } else {
        $page_id = $Membership_Tier->get_next_tier_form_page_id();
        $membership_data['form_page'] = [
          'title' => get_the_title( $page_id ),
          'permalink' => get_permalink( $page_id ),
          'page_id'=> $page_id,
        ];
      }

      if( $current_time >= $membership_early_renew_at && $current_time < $membership_ends_at ) {
        $callout['type'] = 'early_renewal';
        $callout['header'] = $Membership_Config->get_renewal_window_callout_header( $iso_code );
        $callout['content'] = $Membership_Config->get_renewal_window_callout_content( $iso_code );
        $callout['button_label'] = $Membership_Config->get_renewal_window_callout_button_label( $iso_code );
        $early_renewal[] = [
          'membership' => $membership_data,
          'callout' => $callout
        ];
      } else if ( $current_time >= $membership_ends_at && $current_time <= $membership_expires_at ) {
        $callout['type'] = 'grace_period';
        $callout['header'] = $Membership_Config->get_late_fee_window_callout_header( $iso_code );
        $callout['content'] = $Membership_Config->get_late_fee_window_callout_content( $iso_code );
        $callout['button_label'] = $Membership_Config->get_late_fee_window_callout_button_label( $iso_code );
        $grace_period[] = [
          'membership' => $membership_data,
          'callout' => $callout,
          'late_fee_product_id' => $Membership_Config->get_late_fee_window_product_id()
        ];
      } else if( $_ENV['WICKET_MEMBERSHIPS_DEBUG_MODE'] ) {
        $debug[] = [
          'membership' => $membership_data,
        ];

      }
    }

    return ['early_renewal' => $early_renewal, 'grace_period' => $grace_period, 'pending_approval' => $pending_approval, 'debug' => $debug];
  }

  public function get_members_list_group_by_filter($groupby){
    global $wpdb;
    return $wpdb->postmeta . '.meta_value ';
 }

  public function get_members_list( $type, $page, $posts_per_page, $status, $search = '', $filter = [], $order_col = null, $order_dir = null ) {
    if( (! in_array( $type, ['individual', 'organization'] ))) {
      return;
    }
    $wicket_settings = get_wicket_settings( $_ENV['WP_ENV'] );
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
          'key'     => 'membership_type',
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
            'key'     => 'org_name',
            'value'   => $search,
            'compare' => 'LIKE'
          ),
          array(
            'key'     => 'org_location',
            'value'   => $search,
            'compare' => 'LIKE'
          ),
        );
    }

    if( ! empty( $filter ) ) {
      foreach($filter as $key => $val) {
        if( in_array( $key,  ['membership_status', 'membership_tier'] )) {
          if( $key == 'membership_tier' ) {
            $key = 'membership_tier_uuid';
          }
          $args['meta_query'][] = array(
            'key'     => $key,
            'value'   => $val,
            'compare' => '='
          );
        }
      }
    }
    
    add_filter('posts_groupby', [ $this, 'get_members_list_group_by_filter' ]);
    if( $type == 'organization' ) {
      $args['meta_key'] = 'org_uuid';
    } else {
      $args['meta_key'] = 'user_id';
    }
    $tiers = new \WP_Query( $args );
    remove_filter('posts_groupby', [ $this, 'get_members_list_group_by_filter' ]);
    foreach( $tiers->posts as $tier ) {
      $tier_meta = get_post_meta( $tier->ID );
      $user_id = $tier_meta['user_id'][0];
      $tier_new_meta = [];
      array_walk(
        $tier_meta,
        function(&$val, $key) use ( &$tier_new_meta )
        {
          if( $key == 'membership_tier_name' || str_starts_with( $key, '_' ) ) {
            return;
          }
          $tier_new_meta[$key] = $val[0];
        }
      );  
      $tier->meta = $tier_new_meta;
        $user = get_userdata( $user_id );
        if( $user->display_name == $user->user_login ) {
          $user->display_name = $user->first_name . ' ' . $user->last_name;
        }
        unset( $user->user_pass );
        $tier->user = $user->data;
        if( $type != 'organization' ) {
          $tier->user->mdp_link = $wicket_settings['wicket_admin'].'/people/'.$user->data->user_login;
          //$tiers_by_uuid = $this->get_tier_info(null);
          $args = array(
            'post_type' => $this->membership_cpt_slug,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => array(
              array(
                'key'     => 'user_id',
                'value'   => $tier->meta['user_id'][0],
                'compare' => '='
              )
            )
          );
          $user_tiers = new \WP_Query( $args );
          foreach( $user_tiers->posts as $user_tier ) {
            $user_tier_uuid = get_post_meta( $user_tier->ID, 'membership_tier_uuid', true );
            $tier->user->all_membership_tiers[] =  [ 
              'uuid' => $user_tier_uuid, 
              //'name' => $tiers_by_uuid['tier_data'][ $user_tier_uuid ]['name'] ,
            ];
          }
        } else {
          if(! empty($tier->user ) ) {
            $tier->user->mdp_link = $wicket_settings['wicket_admin'].'/organizations/' . $tier->meta['org_uuid'];
          }
        }
      }
    return [ 'results' => $tiers->posts, 'page' => $page, 'posts_per_page' => $posts_per_page, 'count' => $tiers->found_posts ];
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
        if( ! isset( $mship_tiers[$tier->meta['membership_tier_uuid']] )) {
          $mship_tiers[$tier->meta['membership_tier_uuid']] = 0;
        }
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

    if(! is_array( $org_uuids ) ) {
      $allorgs = wicket_get_organizations();
      $all_orgs = array_reduce($allorgs['data'], function($acc, $item) {
        $acc[$item['id']] = $item;
        return $acc;
      }, []);
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

    if( in_array('location', $properties ) ) {
      foreach ($org_uuids as $org_uuid) {
        $org_data[ $org_uuid ] = Helper::get_org_data( $org_uuid, true );
        if( empty( $allorgs )) {
          $all_orgs[ $org_uuid ]['attributes']['alternate_name'] = $org_data[ $org_uuid ][ 'name' ];
        }
      }
    }

    foreach( $orgs->posts as $org ) {
      $mship_org_array[ $org->meta['org_uuid'] ]['name']  = $all_orgs[ $org->meta['org_uuid'] ]['attributes']['alternate_name'];
      if( in_array( 'count', $properties ) ) {
        if( !isset( $mship_orgs[$org->meta['org_uuid']] )) {
          $mship_orgs[$org->meta['org_uuid']] = 0;
        }
        $mship_orgs[$org->meta['org_uuid']] = $mship_orgs[$org->meta['org_uuid']] + 1;
        $mship_org_array[ $org->meta['org_uuid'] ]['count'] = $mship_orgs[ $org->meta['org_uuid']];
      }
      if(!empty( $org_data[ $org->meta['org_uuid'] ] )) {
        $mship_org_array[ $org->meta['org_uuid'] ]['location'] = $org_data[ $org_uuid ]['location'];
      }
    }
    foreach( $all_orgs as $key => $org_item ) {
      if( ! array_key_exists( $key, $mship_org_array ) && in_array( $key, $org_uuids )) {
        $mship_org_array[ $key ]['name'] = $org_item['attributes']['alternate_name'];
        if( in_array( 'count', $properties ) ) {
          $mship_org_array[ $key ]['count'] = 0;
        }
        if(!empty( $org_data[ $key ] )) {
          $mship_org_array[ $key ]['location'] = $org_data[ $key ]['location'];
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
            'key'     => 'membership_type',
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
        'name' => $tier->membership_status,
        'value' => ucfirst( $tier->membership_status )
      ];
    }
    if( $type == 'organization' ) {
      // get locations assigned to membership records as filters???
    }
    return $filters;
  }
}
