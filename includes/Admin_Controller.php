<?php

namespace Wicket_Memberships;

/**
 * Class Admin_Controller
 * @package Wicket_Memberships
 */
class Admin_Controller {

  /**
   * Admin_Controller constructor.
   */
  public function __construct() {
    add_action( 'admin_menu', array( $this, 'init_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
  }

	/**
   * Initialize the admin menu
   */
	public function init_menu() {
    $menu_slug = WICKET_MEMBERSHIP_PLUGIN_SLUG;
		$capability = 'manage_options';

		add_menu_page(
			esc_attr__( 'Wicket Memberships', 'wicket-memberships' ),
			esc_attr__( 'Wicket Memberships', 'wicket-memberships' ),
			$capability,
			$menu_slug,
			'',
			'dashicons-list-view'
		);
	}

	/**
	 * Enqueue scripts and styles
	 */
	public function enqueue_scripts() {
		wp_enqueue_style(
      WICKET_MEMBERSHIP_PLUGIN_SLUG . '_styles',
      WICKET_MEMBERSHIP_PLUGIN_URL . '/assets/css/wicket-memberships-main.css',
      [ 'wp-components' ],
      false
    );
	}

  public static function get_admin_status_options( $membership_post_id = null ) {
    if( !empty( $membership_post_id ) ) {
      $membership_post_status = get_post_meta( $membership_post_id, 'membership_status', true );
      $statuses = Helper::get_allowed_transition_status( $membership_post_status );
    } else {
      $statuses = Helper::get_all_status_names();
    }
    return $statuses;
  }

  public static function admin_manage_status( $membership_post_id, $new_post_status ) {
    $yesterday_iso_date = (new \DateTime( date("Y-m-d", strtotime( "-1 day" )), wp_timezone() ))->format('c');
    $now_iso_date = (new \DateTime( date("Y-m-d"), wp_timezone() ))->format('c');
    //get membership records
    $current_post_status = get_post_meta( $membership_post_id, 'membership_status', true );
    $previous_membership_post_id = get_post_meta( $membership_post_id, 'previous_membership_post_id', true );
    //load membership order json data
    $Membership_Controller = new Membership_Controller();
    $membership_new = $Membership_Controller->get_membership_array_from_post_id( $membership_post_id );
    $membership_current = $Membership_Controller->get_membership_array_from_post_id( $previous_membership_post_id );
    //get tier configuration
    $membership_tier = new Membership_Tier( $membership_new['membership_tier_post_id'] );
    $config = new Membership_Config( $membership_tier->tier_data['config_id'] );
    //get membership dates ( new or based on previous membership if exists )
    $dates = $config->get_membership_dates( $membership_current );
    //echo json_encode( [$current_post_status, $new_post_status] );exit;
    if( $current_post_status == Wicket_Memberships::STATUS_PENDING && $new_post_status == Wicket_Memberships::STATUS_ACTIVE ) {
      //apply the rules
      /**
          --Pending Approval > Active
          Updated manually by admins. See Membership Approval 
          Membership Start Date and Membership End Date will be 
          calculated based on the Membership Tier configuration
       */
      $meta_data = [
        'membership_status' => $new_post_status,
        'membership_starts_at' => $dates['start_date'],
        'membership_ends_at' =>  $dates['end_date'],
        'membership_expires_at' => !empty($dates['expires_at']) ? $dates['expires_at'] : $dates['end_date'],
        'membership_early_renew_at' => !empty($dates['early_renew_at']) ? $dates['early_renew_at'] : $dates['end_date'],
      ];
      $membership = array_merge( $membership_new, $meta_data );

      $user = get_user_by( 'id', $membership['membership_wp_user_id'] );
      $membership['person_uuid'] = $user->data->user_login;

      //create the mdp record we skipped before
      if( empty( $membership['membership_wicket_uuid'] ) ) {
        $membership['membership_wicket_uuid'] = $meta_data['wicket_uuid'] = $Membership_Controller->create_mdp_record( $membership );
      } else {
        $Membership_Controller->update_mdp_record( $membership, $meta_data );
      }
      if( empty( $meta_data['membership_wicket_uuid'] ) ) {
        $meta_data['membership_wicket_uuid'] = $membership['membership_wicket_uuid'];
      }
      //update the membership post
      $membership_post_meta_data = Helper::get_membership_post_data_from_membership_json( json_encode($membership) );
      $response = $Membership_Controller->update_local_membership_record( $membership_post_id, $membership_post_meta_data );
      $Membership_Controller->amend_membership_order_json( $membership_post_id, $meta_data );

      //set the renewal scheduler dates
      $Membership_Controller->scheduler_dates_for_expiry( $membership );
      //update subscription dates
      $Membership_Controller->update_membership_subscription( $membership, ['start_date', 'end_date'] );  
      $Membership_Controller->update_membership_status( $membership_post_id, $new_post_status);
      //set subscription active
      $Membership_Controller->update_subscription_status( 
        $membership['membership_subscription_id'], 
        'active', 
        'Membership approved and dates updated.'
      );
      $response_array['success'] = 'Pending membership activated successfully.';
      $response_array['response'] = $response;
      $response_code = 400;

      return new \WP_REST_Response($response_array, $response_code);  
    } else if( $new_post_status == Wicket_Memberships::STATUS_CANCELLED ) {
      //apply the rules
      /**
          --Pending Approval > Canceled 
          Updated manually by admins. See Membership Approval 
          Cancellation date is recorded as start date and end date
          Related subscription is canceled
          Admin user is prompted to refund related order
      */
      /**
          --Delayed Status > Canceled Status
          Updated manually by admins. See Membership Approval 
          Cancellation date is recorded as start date and end date
          Related subscription is canceled
          Admin user is prompted to refund related order
      */
      if( $current_post_status == Wicket_Memberships::STATUS_PENDING  || $current_post_status == Wicket_Memberships::STATUS_DELAYED) {
        $meta_data = [
          'membership_status' => $new_post_status,
          'membership_starts_at' => $yesterday_iso_date,
          'membership_ends_at' =>  $now_iso_date,
        ];
      }
      /**
          --Grace Period Status > Canceled
          Updated manually by admins
          Membership expiration date is updated to the date of the update
      */
      else if( $current_post_status == Wicket_Memberships::STATUS_GRACE) {
        $meta_data = [
          'membership_status' => $new_post_status,
          'membership_expires_at' => $now_iso_date,
        ];
      }
      /**
          --record is set to ‘Canceled’
          The cancellation date is added as the end date
          The related subscription is canceled (confirmation?)
       */
      else {
        $meta_data = [
          'membership_status' => $new_post_status,
          'membership_ends_at' => $now_iso_date,
        ];
      }
      // cancel the associated subscription
      $sub = wcs_get_subscription( $membership_new['membership_subscription_id'] );
      $sub->update_status( 'cancelled' );
      //return the order id ( FE will redirect user to refund order )
      $response_array['order_id'] = $membership_new['membership_parent_order_id'];     
    } else if( $new_post_status == Wicket_Memberships::STATUS_EXPIRED && $current_post_status == Wicket_Memberships::STATUS_GRACE ) {
      //apply the rules
      /**
          --Graced Period Status > Expired
          Applied dynamically when the membership expiration date is reached
          If update manually by admins, the membership expiration date is updated to the date of the update
       */
      $meta_data = [
        'membership_status' => $new_post_status,
        'membership_expires_at' => $now_iso_date,
      ];
    }
    //update the membership post and order json data
    if( ! empty( $meta_data ) ) {
      $membership_post_meta_data = Helper::get_membership_post_data_from_membership_json( json_encode($meta_data) );
      $updated = $Membership_Controller->update_local_membership_record( $membership_post_id, $membership_post_meta_data );
      $Membership_Controller->amend_membership_order_json( $membership_post_id, $meta_data );
    } else if( ! $Membership_Controller->bypass_status_change_lockout ) {
      return new \WP_REST_Response(['error' => 'Invalid status transition. Request did not succeed.'], 400);
    } else {
      ( new Membership_Controller() )->update_membership_status( $membership_post_id, $new_post_status);
      // temprarily return a debug message to show undefined status change
      return new \WP_REST_Response(['debug' => 'BYPASSED STATUS LOCKOUT --- DEBUG ENABLED --- SET AS '. $new_post_status], 200);
    } 
  
    //update membership dates in MDP
    if( !empty( $updated ) && ! $Membership_Controller->bypass_wicket ) {
      $response = $Membership_Controller->update_mdp_record( $membership_new, $meta_data );
      if( empty ( $response['error'] ) ) {
        $Membership_Controller->update_membership_status( $membership_post_id, $new_post_status);
        $response_array['success'] = 'Status was updated successfully.';
        $response_array['response'] = $response;
        $response_code = 400;
      } else {
        $response_array['error'] = $response['error'];
        $response_array['response'] = [];
        $response_code = 200;
      }
      return new \WP_REST_Response($response_array, $response_code);  
    } else {
      return new \WP_REST_Response(['error' => 'Failed status transition. No change was made.'], 400);
    }
  }
}