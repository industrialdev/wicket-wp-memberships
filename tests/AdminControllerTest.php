<?php
defined('ABSPATH') || exit;
require_once __DIR__ . '/MembershipsBaseTest.php';

use function Brain\Monkey\Functions\when;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;


/*
Test summaries AdminControllerTest.php:
*/

/**
 * Summary of MembershipsMergeTest
 * we are not testing the webhook & authentication here, just the function it calls
 */
class AdminControllerTest extends MembershipsBaseTest {

  /**
   * Summary of test_admin_manage_status_change_pending_active
   * @return void
   */
  public function test_admin_manage_status_change_pending_active() {
    // Create membership with pending status
    $args['config_cycle'] = 'create_anniversary_config';
    $args['renewal_type'] = 'form_flow';
    $args['approval_required'] = 1;
    $args['approval_email_recipient'] = 'membership-test@wicket.io';
    [
      $tier_id,
      $user_id,
      $product_id,
      $subscriptions
    ] = $this->create_individual_membership_for_config_and_product_on_tier( $args );

    $membership_post_id = $this->return_first_membership_post_id_from_subscription_items( $subscriptions );
    $membership = \Wicket_Memberships\Helper::get_post_meta($membership_post_id);
    $subscription = wcs_get_subscription($membership['membership_subscription_id']);
    
    $this->assertEquals( 'on-hold', $subscription->get_status(), 'Subscription status is not initially on-hold as expected.'); 
    $this->assertEquals( 'pending', $membership['membership_status'], 'Membership status is not initially pending as expected.' );  
    $this->assertNull($membership['wicket_membership_uuid'], 'Membership UUID should be null before status change.');

    //change startus to active
    $admin_controller = new \Wicket_Memberships\Admin_Controller();
    $http_response_object = $admin_controller->admin_manage_status( $membership_post_id, 'active' );
    $data = $http_response_object->get_data();

    //verify status was changed, the MDP endpoint returned UUID was stored on the membership
    $membership = \Wicket_Memberships\Helper::get_post_meta($membership_post_id);    
    $subscription = wcs_get_subscription($membership['membership_subscription_id']);
    $this->assertEquals( 'active', $subscription->get_status(), 'Subscription status is not initially on-hold as expected.'); 
    $this->assertEquals( 'active', $membership['membership_status'], 'Membership status is not updated to active as expected.' );  
    $this->assertEquals( $data['response']['membership_wicket_uuid'], $membership['membership_wicket_uuid'], 'Membership UUID does not match expected value.' );    
    $this->assertEmpty( $subscription->get_date('next_payment'), 'Next payment date should be empty for non-subscription memberships.' );
  }

  /**
   * Summary of test_admin_manage_status_change_pending_cancelled
   * @return void
   */
  public function test_admin_manage_status_change_pending_cancelled() {
    $uuid = wp_generate_uuid4();
    when('wicket_update_individual_membership_dates')->justReturn([
            'success' => true,
            'data' => ['id' => $uuid]
        ]);
    when('wicket_update_organization_membership_dates')->justReturn([
            'success' => true,
            'data' => ['id' => $uuid]
        ]);

    // Create membership with pending status
    $args['config_cycle'] = 'create_anniversary_config';
    $args['renewal_type'] = 'form_flow';
    $args['approval_required'] = 1;
    $args['approval_email_recipient'] = 'membership-test@wicket.io';
    [
      $tier_id,
      $user_id,
      $product_id,
      $subscriptions
    ] = $this->create_individual_membership_for_config_and_product_on_tier( $args );

    $membership_post_id = $this->return_first_membership_post_id_from_subscription_items( $subscriptions );

    //change startus to cancelled
    $admin_controller = new \Wicket_Memberships\Admin_Controller();
    $http_response_object = $admin_controller->admin_manage_status( $membership_post_id, 'cancelled' );
    $data = $http_response_object->get_data();

    //verify status was changed, the MDP endpoint returned UUID was stored on the membership
    $membership = \Wicket_Memberships\Helper::get_post_meta($membership_post_id);  
    $subscription = wcs_get_subscription($membership['membership_subscription_id']);
    $today = date("Y-m-d");
    $this->assertEquals( $today, date("Y-m-d", strtotime($subscription->get_date('end'))), 'Subscription status is not end dated today.'); 
    $this->assertEquals( 'cancelled', $subscription->get_status(), 'Subscription status is not initially on-hold as expected.'); 

    $this->assertEquals( 'cancelled', $membership['membership_status'], 'Membership status is not updated to active as expected.' );  
    $this->assertEquals( $today, $membership['membership_ends_at'], 'Membership is not end dated today.'); 
    $this->assertEquals( $today, $membership['membership_expires_at'], 'Membership is not expire dated today.'); 
    
    $this->assertEquals( $data['response']['membership_wicket_uuid'], $membership['membership_wicket_uuid'], 'Membership UUID does not match expected value.' );    
    $this->assertEmpty( $subscription->get_date('next_payment'), 'Next payment date should be empty for non-subscription memberships.' );
   }

   public function test_admin_manage_status_change_grace_cancelled() {
    $uuid = wp_generate_uuid4();
    when('wicket_update_individual_membership_dates')->justReturn([
            'success' => true,
            'data' => ['id' => $uuid]
        ]);
    when('wicket_update_organization_membership_dates')->justReturn([
            'success' => true,
            'data' => ['id' => $uuid]
        ]);

    // Create membership with pending status
    $args['config_cycle'] = 'create_anniversary_config';
    $args['renewal_type'] = 'form_flow';
    [
      $tier_id,
      $user_id,
      $product_id,
      $subscriptions
    ] = $this->create_individual_membership_for_config_and_product_on_tier( $args );

    $membership_post_id = $this->return_first_membership_post_id_from_subscription_items( $subscriptions );
    update_post_meta($membership_post_id, 'membership_status', 'grace');

    //verify status was changed, the MDP endpoint returned UUID was stored on the membership
    $today = date("Y-m-d");
    $yesterday = date("Y-m-d", strtotime("-1 day"));
    update_post_meta($membership_post_id, 'membership_ends_at', $yesterday);
    update_post_meta($membership_post_id, 'membership_expires_at', $yesterday);

     //change startus to cancelled
    $admin_controller = new \Wicket_Memberships\Admin_Controller();
    $http_response_object = $admin_controller->admin_manage_status( $membership_post_id, 'cancelled' );
    $data = $http_response_object->get_data();

    $membership = \Wicket_Memberships\Helper::get_post_meta($membership_post_id);  
    $subscription = wcs_get_subscription($membership['membership_subscription_id']);

    $this->assertEquals( 'cancelled', $membership['membership_status'], 'Membership status is not updated to active as expected.' );  
    $this->assertEquals( $yesterday, $membership['membership_ends_at'], 'Membership is not still end dated yesterday.'); 
    $this->assertEquals( $today, $membership['membership_expires_at'], 'Membership is not expire dated today.'); 

    $this->assertEquals( 'cancelled', $subscription->get_status(), 'Subscription status is not initially on-hold as expected.'); 
  }

   public function test_admin_manage_status_change_active_expired() {
    $uuid = wp_generate_uuid4();
    when('wicket_update_individual_membership_dates')->justReturn([
            'success' => true,
            'data' => ['id' => $uuid]
        ]);
    when('wicket_update_organization_membership_dates')->justReturn([
            'success' => true,
            'data' => ['id' => $uuid]
        ]);

    // Create membership with pending status
    $args['config_cycle'] = 'create_anniversary_config';
    $args['renewal_type'] = 'form_flow';
    [
      $tier_id,
      $user_id,
      $product_id,
      $subscriptions
    ] = $this->create_individual_membership_for_config_and_product_on_tier( $args );

    $membership_post_id = $this->return_first_membership_post_id_from_subscription_items( $subscriptions );
    update_post_meta($membership_post_id, 'membership_status', 'grace');

    //verify status was changed, the MDP endpoint returned UUID was stored on the membership
    $tomorrow = date("Y-m-d", strtotime("+1 day"));

     //change startus to cancelled
    $admin_controller = new \Wicket_Memberships\Admin_Controller();
    $http_response_object = $admin_controller->admin_manage_status( $membership_post_id, 'expired' );
    $data = $http_response_object->get_data();

    $membership = \Wicket_Memberships\Helper::get_post_meta($membership_post_id);  
    $subscription = wcs_get_subscription($membership['membership_subscription_id']);

    $this->assertEquals( 'expired', $membership['membership_status'], 'Membership status is not updated to active as expected.' );  
    $this->assertEquals( $tomorrow, $membership['membership_ends_at'], 'Membership is not still end dated yesterday.'); 
    $this->assertEquals( $tomorrow, $membership['membership_expires_at'], 'Membership is not expire dated today.'); 

    $this->assertEquals( 'cancelled', $subscription->get_status(), 'Subscription status is not initially on-hold as expected.'); 
  }


}