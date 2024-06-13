<?php

namespace Wicket_Memberships;

/**
 * Class Admin_Controller
 * @package Wicket_Memberships
 */
class Admin_Controller {

  private $membership_cpt_slug = '';

  /**
   * Admin_Controller constructor.
   */
  public function __construct() {
    $this->membership_cpt_slug = Helper::get_membership_cpt_slug();
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

  /**
   * API Response with all status (no post id received) or only
   * allowed status transitions based on current membership post status
   *
   * @param integer $membership_post_id
   * @return array
   */
  public static function get_admin_status_options( $membership_post_id = null ) {
    if( !empty( $membership_post_id ) ) {
      $membership_post_status = get_post_meta( $membership_post_id, 'membership_status', true );
      $statuses = Helper::get_allowed_transition_status( $membership_post_status );
    } else {
      $statuses = Helper::get_all_status_names();
    }
    return $statuses;
  }

  /**
   * API Request to update Status function
   * Rules: https://docs.google.com/document/d/1X3EuDnq9QHZI9DaK4OcqvlDVKrVqMnu9HQOZWVS-ptU/edit#heading=h.61a0nijp1ufb
   *
   * @param integer $membership_post_id
   * @param string $new_post_status
   * @return \WP_REST_Response
   */
  public static function admin_manage_status( $membership_post_id, $new_post_status ) {
    $tomorrow_iso_date = (new \DateTime( date("Y-m-d", strtotime( "+1 day" )), wp_timezone() ))->format('c');
    $yesterday_iso_date = (new \DateTime( date("Y-m-d", strtotime( "-1 day" )), wp_timezone() ))->format('c');
    $now_iso_date = (new \DateTime( date("Y-m-d"), wp_timezone() ))->format('c');
    //get membership records
    $current_post_status = get_post_meta( $membership_post_id, 'membership_status', true );
    $previous_membership_post_id = get_post_meta( $membership_post_id, 'previous_membership_post_id', true );
    //load membership order json data
    $Membership_Controller = new Membership_Controller();
    $user_id = $Membership_Controller->get_user_id_from_membership_post( $membership_post_id );
    $membership_new = $Membership_Controller->get_membership_array_from_user_meta_by_post_id( $membership_post_id, $user_id );

    if( empty( $new_post_status )) {
      $response_array['error'] = 'Invalid status transition. Requested status was not received.';
    } else if( empty( $membership_new )) {
      $current_post_status = $new_post_status = '';
      $response_array['error'] = 'Membership not found. Request did not succeed.';
    } else {
      $membership_current = $Membership_Controller->get_membership_array_from_user_meta_by_post_id( $previous_membership_post_id, $user_id );
      //get tier configuration
      $membership_tier = new Membership_Tier( $membership_new['membership_tier_post_id'] );
      $config = new Membership_Config( $membership_tier->tier_data['config_id'] );
      //get membership dates ( new or based on previous membership if exists )
      $dates = $config->get_membership_dates( $membership_current );
      //echo json_encode( [$current_post_status, $new_post_status] );exit;
    }
    if( $current_post_status == Wicket_Memberships::STATUS_PENDING && $new_post_status == Wicket_Memberships::STATUS_ACTIVE ) {
      // ------ WE RETURN EARLY HERE ONLY ------
      // THIS IS A SPECIAL CASE OF STATUS UPDATE
      //apply the rules
      $meta_data = [
        'membership_status' => $new_post_status,
        'membership_starts_at' => $dates['start_date'],
        'membership_ends_at' =>  $dates['end_date'],
        'membership_expires_at' => !empty($dates['expires_at']) ? $dates['expires_at'] : $dates['end_date'],
        'membership_early_renew_at' => !empty($dates['early_renew_at']) ? $dates['early_renew_at'] : $dates['end_date'],
      ];
      $membership = array_merge( $membership_new, $meta_data );

      $user = get_user_by( 'id', $membership['user_id'] );
      $membership['person_uuid'] = $user->data->user_login;

      //create the mdp record we skipped before
      if( empty( $membership['membership_wicket_uuid'] ) ) {
        $membership_data = $membership;
        $membership_data['membership_grace_period_days'] = $config->get_late_fee_window_days();
        $membership['membership_wicket_uuid'] = $meta_data['membership_wicket_uuid'] = $Membership_Controller->create_mdp_record( $membership_data );
      } else {
        $Membership_Controller->update_mdp_record( $membership, $meta_data );
      }
      if( empty( $meta_data['membership_wicket_uuid'] ) ) {
        $meta_data['membership_wicket_uuid'] = $membership['membership_wicket_uuid'];
      }
      //update the membership post
      $membership_post_meta_data = Helper::get_membership_post_data_from_membership_json( $membership, false);
      $response = $Membership_Controller->update_local_membership_record( $membership_post_id, $membership_post_meta_data );
      $Membership_Controller->amend_membership_json( $membership_post_id, $meta_data );

      //set the renewal scheduler dates
      $Membership_Controller->scheduler_dates_for_expiry( $membership );
      //update subscription dates
      $Membership_Controller->update_membership_subscription( $membership, ['start_date', 'end_date', 'next_payment_date'] );
      $Membership_Controller->update_membership_status( $membership_post_id, $new_post_status);
      //set subscription active
      $Membership_Controller->update_subscription_status(
        $membership['membership_subscription_id'],
        'active',
        'Membership approved and dates updated.'
      );
      $response_array['success'] = 'Pending membership activated successfully.';
      $response_array['response'] = Helper::get_post_meta( $membership_post_id );
      $response_code = 200;

      // ------ WE RETURN EARLY HERE ONLY ------
      // THIS IS A SPECIAL CASE OF STATUS UPDATE
      return new \WP_REST_Response($response_array, $response_code);
    } else if( $new_post_status == Wicket_Memberships::STATUS_CANCELLED ) {
      //apply the rules
      if( $current_post_status == Wicket_Memberships::STATUS_PENDING  || $current_post_status == Wicket_Memberships::STATUS_DELAYED) {
        $meta_data = [
          'membership_status' => $new_post_status,
          'membership_starts_at' => $yesterday_iso_date,
          'membership_ends_at' =>  $now_iso_date,
        ];
      }
      else if( $current_post_status == Wicket_Memberships::STATUS_GRACE) {
        $meta_data = [
          'membership_status' => $new_post_status,
          'membership_expires_at' => $now_iso_date,
        ];
      }
      else {
        $meta_data = [
          'membership_status' => $new_post_status,
          'membership_ends_at' => $tomorrow_iso_date,
        ];
      }
      // cancel the associated subscription
      if( function_exists( 'wcs_get_subscription' )) {
        $sub = wcs_get_subscription( $membership_new['membership_subscription_id'] );
        if(! empty( $sub )) {
          $sub->update_status( 'cancelled' );
        }  
      }
      //return the order id ( FE will redirect user to refund order )
      $response_array['order_id'] = $membership_new['membership_parent_order_id'];
    } else if( $current_post_status == Wicket_Memberships::STATUS_GRACE && $new_post_status == Wicket_Memberships::STATUS_EXPIRED ) {
      //apply the rules
      $meta_data = [
        'membership_status' => $new_post_status,
        'membership_expires_at' => $now_iso_date,
      ];
    }
    $meta_data['membership_type'] = $membership_new['membership_type'];
    //update the membership post and order json data
    if( ! empty( $meta_data ) ) {
      $membership_post_meta_data = Helper::get_membership_post_data_from_membership_json( json_encode($meta_data) );
      $updated = $Membership_Controller->update_local_membership_record( $membership_post_id, $membership_post_meta_data );
      $Membership_Controller->amend_membership_json( $membership_post_id, $meta_data );
    } else if( empty( $_ENV['BYPASS_STATUS_CHANGE_LOCKOUT'] ) ) {
      // WE ONLY ALLOW CERTAIN TRANSITIONS ACCORDING TO RULES
      //
      if( empty($response_array) ) {
        $response_array['error'] = 'Invalid status transition. Request did not succeed.';
      }
      return new \WP_REST_Response($response_array, 400);
    } else {
      ( new Membership_Controller() )->update_membership_status( $membership_post_id, $new_post_status);
      // temprarily return a debug message to show undefined status change
      if( empty($response_array) ) {
        $response_array['success'] = 'BYPASSED STATUS LOCKOUT --- DEBUG ENABLED --- SET AS '. $new_post_status;
      }
      return new \WP_REST_Response($response_array, 200);
    }

    //update membership dates in MDP
    if( !empty( $updated ) && ! $Membership_Controller->bypass_wicket ) {
      $response = $Membership_Controller->update_mdp_record( $membership_new, $meta_data );
      if ( strstr( $response['error'], '404 Not Found') !== false && ! empty( $_ENV['BYPASS_STATUS_CHANGE_LOCKOUT'] ) ) {
        $Membership_Controller->update_membership_status( $membership_post_id, $new_post_status);
        return new \WP_REST_Response(['success' => 'Status Changed. NOTE: No mdp record exists for this membership.'], 200);
      }
      if( empty ( $response['error'] ) ) {
        $Membership_Controller->update_membership_status( $membership_post_id, $new_post_status);
        $response_array['success'] = 'Status was updated successfully.';
        $response_array['response'] = Helper::get_post_meta( $membership_post_id );
        $response_code = 200;
      } else {
        $response_array['error'] = $response['error'];
        $response_array['response'] = Helper::get_post_meta( $membership_post_id );
        $response_code = 400;
      }
      return new \WP_REST_Response($response_array, $response_code);
    } else {
      return new \WP_REST_Response(['error' => 'Failed status transition. No change was made.'], 400);
    }
  }

  public static function get_edit_page_info( $id ) {
    $wicket_settings = get_wicket_settings( $_ENV['WP_ENV'] );
    if( is_numeric( $id ) ) {
      $user = get_user_by( 'id', $id );
      $person_uuid = $user->user_login;
      $response = wicket_get_person_by_id( $person_uuid );
      return [
        'identifying_number' => $response->getAttribute('identifying_number'),
        'data' => $response->getAttribute('user')['email'],
        'mdp_link' => $wicket_settings['wicket_admin'] . '/people/' . $person_uuid
      ];  
    } else if(preg_match('/^[a-f\d]{8}(-[a-f\d]{4}){4}[a-f\d]{8}$/i', $id)) {
      $response = wicket_get_organization( $id );
      $org_data = Helper::get_org_data( $id, false, true );
      return [
        'identifying_number' => $response['data']['attributes']['identifying_number'],
        'data' => $org_data['location'],
        'mdp_link' => $wicket_settings['wicket_admin'] . '/organizations/' . $id
      ];  
    }

  }

  public static function get_membership_entity_records( $id ) {
    $self = new self();
    $statuses = Helper::get_all_status_names();
    $wicket_settings = get_wicket_settings( $_ENV['WP_ENV'] );

    $args = array(
      'post_type' => $self->membership_cpt_slug,
      'post_status' => 'publish',
      'posts_per_page' => -1,
      'orderby'   => 'meta_value',
      'meta_key' => 'membership_starts_at',
      'order' => 'DESC',
    );
    if( is_numeric( $id ) ) {
     $args['meta_query'] = array(
        array(
          'key'     => 'user_id',
          'value'   => $id,
          'compare' => '='
        ),
        array(
          'key'     => 'membership_type',
          'value'   => 'individual',
          'compare' => '='
        ),
      );
    } else {
      $org_memberships = wicket_get_org_memberships( $id );
      //echo json_encode( $org_memberships );exit;
      $mdp_link = $wicket_settings['wicket_admin'] . '/organizations/' . $id;
      $args['meta_query'] = array(
        array(
          'key'     => 'org_uuid',
          'value'   => $id,
          'compare' => '='
        ),
        array(
          'key'     => 'membership_type',
          'value'   => 'organization',
          'compare' => '='
        ),
      );
    }
    $memberships = get_posts( $args );
    foreach( $memberships as &$membership) {
      $meta_data = get_post_meta( $membership->ID );
      $meta = [];
      array_walk(
        $meta_data,
        function(&$val, $key) use ( &$meta )
        {
          if( ! str_starts_with( $key, '_' ) ) {
            $meta[$key] = $val[0];
          }
        }
      );
      $membership_item['ID'] = $membership->ID;
      if( !empty( $mdp_link )) {
        $membership_item['mdp_membership_link'] = $mdp_link . '/memberships/' . $meta['membership_wicket_uuid'];
        $membership_item['max_assignments'] = $org_memberships[ $meta['membership_wicket_uuid'] ]['membership']['attributes']['max_assignments'] ?? 0;
        $membership_item['active_assignments_count'] = $org_memberships[ $meta['membership_wicket_uuid'] ]['membership']['attributes']['active_assignments_count'];
      }
      $membership_data = Membership_Controller::get_membership_array_from_user_meta_by_post_id( $membership->ID, $meta['user_id'] );
      if( !empty( $membership_data ) ) {
        $membership_item['data'] = $membership_data;
        $membership_item['data']['membership_status'] = $statuses[ $meta['membership_status'] ]['name'];
        $membership_item['data']['membership_starts_at'] = date( "m/d/Y", strtotime( $meta['membership_starts_at'] ) );
        $membership_item['data']['membership_ends_at'] = date( "m/d/Y", strtotime( $meta['membership_ends_at'] ) );
        $membership_item['data']['membership_expires_at'] = date( "m/d/Y", strtotime( $meta['membership_expires_at'] ) );
        $membership_item['data']['membership_early_renew_at'] = date( "m/d/Y", strtotime( $meta['membership_early_renew_at'] ) );
      } else {
        $membership_item['data'] = [];
      }
      $membership_item['order'] = [];
      $membership_item['subscription'] = [];

      if( !empty( $membership_item['data']['membership_parent_order_id'] ) && !empty( $membership_item['data']['membership_subscription_id'] )) {
        $order = wc_get_order( $membership_item['data']['membership_parent_order_id'] );
        $membership_item['order']['id'] = $membership_item['data']['membership_parent_order_id'];
        $membership_item['order']['link'] = admin_url( '/post.php?action=edit&post=' . $membership_item['data']['membership_parent_order_id'] );
        $membership_item['order']['total'] = $order->get_total();
        $membership_item['order']['status'] = $order->get_status();
        $membership_item['order']['date_created'] =  $order->get_date_created()->format('Y-m-d');
        if(!empty( $order->get_date_completed() )) {
          $membership_item['order']['date_completed'] = $order->get_date_completed()->format('Y-m-d');
        }
        if( function_exists( 'wcs_get_subscription' )) {
          $sub = wcs_get_subscription( $membership_item['data']['membership_subscription_id'] );
          $membership_item['subscription']['id'] = $membership_item['data']['membership_subscription_id'];
          $membership_item['subscription']['link'] = admin_url( '/post.php?action=edit&post=' . $membership_item['data']['membership_subscription_id'] );
          $membership_item['subscription']['status'] = $sub->get_status();
          $membership_item['subscription']['next_payment_date'] = (new \DateTime( date("Y-m-d", $sub->get_time('next_payment')), wp_timezone() ))->format('Y-m-d');
        }
      }
      $membership_items[] = $membership_item;
    }
    return $membership_items;
  }

  public static function update_membership_entity_record( $data ) {
    $Membership_Controller = new Membership_Controller();
    $membership_post_id = $data['membership_post_id'];
    if(
        ! array_key_exists( 'membership_starts_at', $data )
        || ! array_key_exists( 'membership_ends_at', $data )
        || ! array_key_exists( 'membership_expires_at', $data )
      ) {
      $response_array['error'] = 'Membership update failed. All dates required.';
      $response_array['response'] = [];
      $response_code = 400;
      return new \WP_REST_Response($response_array, $response_code);
    } else {
      $membership_ends_at_seconds = strtotime( $data[ 'membership_ends_at' ] );
      $membership_expires_at_seconds = strtotime( $data[ 'membership_expires_at' ] );
      $grace_period_days = abs(round( ( $membership_expires_at_seconds - $membership_ends_at_seconds ) / 86400 ) );

      $data[ 'membership_starts_at' ]  = (new \DateTime( date("Y-m-d", strtotime( $data[ 'membership_starts_at' ] )), wp_timezone() ))->format('c');
      $data[ 'membership_ends_at' ]  = (new \DateTime( date("Y-m-d", $membership_ends_at_seconds ), wp_timezone() ))->format('c');
      $data[ 'membership_expires_at' ]  = (new \DateTime( date("Y-m-d", $membership_expires_at_seconds ), wp_timezone() ))->format('c');
      $data[ 'grace_period_days' ] = $grace_period_days; 
    }

    if ( Helper::is_valid_membership_post( $membership_post_id ) ) {
      $membership_post = get_post_meta( $membership_post_id );
      $local_response = $Membership_Controller->update_local_membership_record( $membership_post_id, $data );
    }

    if( empty( $local_response ) || is_wp_error( $local_response ) ) {
      $response_array['error'] = 'Membership update failed.';
      $response_array['response'] = [];
      $response_code = 400;
      return new \WP_REST_Response($response_array, $response_code);
    }
    $membership['membership_type'] = $membership_post['membership_type'][0];
    $membership['membership_wicket_uuid'] = $membership_post['membership_wicket_uuid'][0];
    $wicket_response = $Membership_Controller->update_mdp_record( $membership, $data );

    if( is_wp_error( $wicket_response ) ) {
      $local_response = $Membership_Controller->update_local_membership_record( $membership_post_id, $membership_post );
      $response_array['error'] = 'Membership dates update failed. '.$wicket_response->get_error_message( 'wicket_api_error' );
      $response_array['response'] = [];
      $response_code = 400;
    } else {
      $Membership_Controller->amend_membership_json( $membership_post_id, $data );
      $response_array['success'] = 'Membership was updated successfully.';
      $response_array['response'] = Helper::get_post_meta( $membership_post_id );
      $response_code = 200;
    }

    return new \WP_REST_Response($response_array, $response_code);
  }
}