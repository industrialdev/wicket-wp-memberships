<?php
defined('ABSPATH') || exit;
require_once __DIR__ . '/MembershipsBaseTest.php';

/*
Test summaries MembershipsMergeTest.php:
- test_merge_memberships_after_webhook_received: Merges memberships for two users and verifies membership post IDs after merge.
*/

/**
 * Summary of MembershipsMergeTest
 * we are not testing the webhook & authentication here, just the function it calls
 */
class MembershipsMergeTest extends MembershipsBaseTest {
    
    public function test_merge_memberships_after_webhook_received() {
      $args['config_cycle'] = 'create_anniversary_config';
      [
        $tier_id,
        $user_id,
        $product_id,
        $subscriptions
      ] = $this->create_individual_membership_for_config_and_product_on_tier( $args );
        $args['user_id'] = $user_id; //use this user for subsequent subscriptions

      [
        $tier_id,
        $user_id,
        $product_id,
        $subscriptions
      ] = $this->create_individual_membership_for_config_and_product_on_tier( $args );

      [
        $tier_id,
        $user_id,
        $product_id,
        $subscriptions
      ] = $this->create_individual_membership_for_config_and_product_on_tier( $args );
      
      $subscriptions = wcs_get_users_subscriptions($user_id);
        foreach ($subscriptions as $subscription_id => $subscription) {
          foreach ( $subscription->get_items() as $item_id => $item ) {
              $meta_value = wc_get_order_item_meta( $item_id, '_membership_post_id_renew', true );
              $this->assertNotNull($meta_value, 'Meta key _membership_post_id_renew does not exist on subscription item.');
              $membership_post_id[] = $meta_value;
          }
        }

      $merge_from_user_id = $user_id;
      unset($args['user_id']); //stop using this user for subsequent subscriptions

      [
        $tier_id,
        $user_id,
        $product_id,
        $subscriptions
      ] = $this->create_individual_membership_for_config_and_product_on_tier( $args );

      $merge_to_user_id = $user_id;
      $user = get_user_by( 'id', $merge_to_user_id );
      $merge_to_user_uuid = $user->user_login;

      $subscriptions = wcs_get_users_subscriptions($user_id);
        foreach ($subscriptions as $subscription_id => $subscription) {
          foreach ( $subscription->get_items() as $item_id => $item ) {
              $meta_value = wc_get_order_item_meta( $item_id, '_membership_post_id_renew', true );
              $this->assertNotNull($meta_value, 'Meta key _membership_post_id_renew does not exist on subscription item.');
              $membership_post_id[] = $meta_value;
          }
        }

    //DEBUGGING OUTPUT
      //foreach ($membership_post_id as $post_id) {
      //  echo 'User_id: ' . get_post_meta($post_id, 'user_id', true) . ' Membership Post ID: ' . $post_id . PHP_EOL; 
      //} 

      //Call the merge function - we are not testing the webhook & authentication here, just the function it calls
      \Wicket_Memberships\Admin_Controller::update_memberships_owner($merge_from_user_id, $merge_to_user_uuid);

      $membership_posts = get_posts([
        'post_type' => 'wicket_membership',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
          [
            'key' => 'user_id',
            'value' => $merge_to_user_id,
            'compare' => '='
          ]
        ]
      ]);

      $membership_post_ids = array_map(function($post) { return $post->ID; }, $membership_posts);
      $this->assertCount(count(value: $membership_post_id), $membership_posts, 'Number of memberships after merge does not match expected count.');
      foreach ($membership_post_id as $post_id) {
        $this->assertContains($post_id, $membership_post_ids, 'Missing membership after merge.');
      }
    }
  }

  