<?php
defined('ABSPATH') || exit;
require_once __DIR__ . '/MembershipsBaseTest.php';

/*
Test summaries MembershipsTestConfig.php:
- test_create_individual_membership_for_anniversary_config: Creates individual membership for anniversary config and checks meta dates.
- test_renew_individual_membership_for_anniversary_config: Renews anniversary membership and checks new dates.
- test_create_individual_membership_for_anniversary_config_first_day: Membership aligned to first day of month, checks meta dates.
- test_create_individual_membership_for_anniversary_config_15th_day: Membership aligned to 15th day of month, checks meta dates.
- test_create_individual_membership_for_anniversary_last_day: Membership aligned to last day of month next year, checks meta dates.
- test_create_individual_membership_for_calendar: Creates individual membership for calendar config and checks meta dates.
- test_renew_individual_membership_for_calendar_config: Renews calendar membership and checks new dates.
*/

class MembershipsCreateTest extends MembershipsBaseTest {
    /**
     * Summary of test_create_individual_membership_for_anniversary_config
     * @return void
     */
    public function test_create_individual_membership_for_anniversary_config() {
      $args['config_cycle'] = 'create_anniversary_config';
      //$args_array['align-end-dates-enabled'] = false; //default
      //$args_array['align-end-dates-type'] = 'last-day-of-month'; // 'last-day-of-month' '15th-of-month'
      //$args['early_renewal_days'] = 30; //default
      $args['grace_period_days'] = 10; //default
      [
        $tier_id,
        $user_id,
        $product_id,
        $subscriptions,
        $wicket_uuid
      ] = $this->create_individual_membership_for_config_and_product_on_tier( $args );

        foreach ($subscriptions as $subscription_id => $subscription) {
          foreach ( $subscription->get_items() as $item_id => $item ) {
              $meta_value = wc_get_order_item_meta( $item_id, '_membership_post_id_renew', true );
              $this->assertNotNull($meta_value, 'Meta key _membership_post_id_renew does not exist on subscription item.');
              $membership_post_id = $meta_value;
              $this->assertNotNull(get_post($membership_post_id), 'Membership post does not exist.');
              $this->assertEquals($wicket_uuid, get_post_meta($membership_post_id, 'membership_wicket_uuid', true), 'Membership Wicket UUID does not match.');
              $this->assertEquals($tier_id, get_post_meta($membership_post_id, 'membership_tier_post_id', true), 'Membership tier ID does not match.');
              $this->assertEquals($user_id, get_post_meta($membership_post_id, 'user_id', true), 'User ID does not match.');
              $this->assertEquals($product_id, get_post_meta($membership_post_id, 'membership_product_id', true), 'Membership product ID does not match.');

              $todays_date = gmdate('Y-m-d\T00:00:00+00:00');
              $ends_at_date = gmdate('Y-m-d\T00:00:00+00:00', strtotime($todays_date . ' +1 year'));
              $expires_at_date = gmdate('Y-m-d\T00:00:00+00:00', strtotime($ends_at_date . " + {$args['grace_period_days']} days"));
              $this->assertEquals( $todays_date, get_post_meta($membership_post_id, 'membership_starts_at', true), 'Membership start date does not match.');
              $this->assertEquals( $ends_at_date, get_post_meta($membership_post_id, 'membership_ends_at', true), 'Membership end date does not match.');
              $this->assertEquals( $expires_at_date, get_post_meta($membership_post_id, 'membership_expires_at', true), 'Membership expire date does not match.');
           }
        }
    }
    
    /**
     * Summary of test_renew_individual_membership_for_anniversary_config
     * perform a renewal on the created membership
     * @param mixed $subscription_id
     * @return void
     */

    public function test_renew_individual_membership_for_anniversary_config() {

      $args['config_cycle'] = 'create_anniversary_config';
      //$args_array['align-end-dates-enabled'] = false; //default
      //$args_array['align-end-dates-type'] = 'last-day-of-month'; // 'last-day-of-month' '15th-of-month'
      //$args['early_renewal_days'] = 30; //default
      $args['grace_period_days'] = 12; //default
      [
        $tier_id,
        $user_id,
        $product_id,
        $subscriptions
      ] = $this->create_individual_membership_for_config_and_product_on_tier( $args );

        foreach ($subscriptions as $subscription_id => $subscription) {
          $subscription = wcs_get_subscription( $subscription_id );
          //generate renewal order for subscription
          $order = wcs_create_renewal_order( $subscription );
          if ($order) {
              $order->update_status('processing');
              //Woocommerce hooks to simulate order processing
              do_action('woocommerce_order_status_processing', $order->get_id());
          }
          //verify the new membership was created with correct dates
          $subscription = wcs_get_subscription( $subscription_id );
          foreach ( $subscription->get_items() as $item_id => $item ) {
              $meta_value = wc_get_order_item_meta( $item_id, '_membership_post_id_renew', true );
              $this->assertNotNull($meta_value, 'Meta key _membership_post_id_renew does not exist on subscription item.');
              $membership_post_id = $meta_value;
              $this->assertNotNull(get_post($membership_post_id), 'Membership post does not exist.');
          }
          $todays_date = gmdate('Y-m-d\T00:00:00+00:00');
          $starts_at_date = gmdate('Y-m-d\T00:00:00+00:00', strtotime($todays_date . ' +366 days'));
          $ends_at_date = gmdate('Y-m-d\T00:00:00+00:00', strtotime($starts_at_date . ' +1 year'));
          $expires_at_date = gmdate('Y-m-d\T00:00:00+00:00', strtotime($ends_at_date . " + {$args['grace_period_days']} days"));
          $this->assertEquals( $starts_at_date, get_post_meta($membership_post_id, 'membership_starts_at', true));
          $this->assertEquals( $ends_at_date, get_post_meta($membership_post_id, 'membership_ends_at', true));
          $this->assertEquals( $expires_at_date, get_post_meta($membership_post_id, 'membership_expires_at', true));
        }
    }

    /**
     * Summary of test_create_individual_membership_for_anniversary_config_first_day
     * when should be aligned to first day of month
     * @return void
     */
    public function test_create_individual_membership_for_anniversary_config_first_day() {
      $args['config_cycle'] = 'create_anniversary_config';
      $args['align-end-dates-enabled'] = true;
      $args['align-end-dates-type'] = 'first-day-of-month';
      $args['early_renewal_days'] = 30;
      $args['grace_period_days'] = 5;
      [
        $tier_id,
        $user_id,
        $product_id,
        $subscriptions
      ] = $this->create_individual_membership_for_config_and_product_on_tier( $args );

        foreach ($subscriptions as $subscription_id => $subscription) {
          foreach ( $subscription->get_items() as $item_id => $item ) {
              $meta_value = wc_get_order_item_meta( $item_id, '_membership_post_id_renew', true );
              $this->assertNotNull($meta_value, 'Meta key _membership_post_id_renew does not exist on subscription item.');
              $membership_post_id = $meta_value;
              $this->assertNotNull(get_post($membership_post_id), 'Membership post does not exist.');
              $this->assertEquals($tier_id, get_post_meta($membership_post_id, 'membership_tier_post_id', true), 'Membership tier ID does not match.');
              $this->assertEquals($user_id, get_post_meta($membership_post_id, 'user_id', true), 'User ID does not match.');
              $this->assertEquals($product_id, get_post_meta($membership_post_id, 'membership_product_id', true), 'Membership product ID does not match.');

              $todays_date = gmdate('Y-m-d\T00:00:00+00:00');
              $first_day_of_month = gmdate('Y-m-01\T00:00:00+00:00');
              $ends_at_date = gmdate('Y-m-d\T00:00:00+00:00', strtotime($first_day_of_month . ' +1 year'));
              $expires_at_date = gmdate('Y-m-d\T00:00:00+00:00', strtotime($ends_at_date . " + {$args['grace_period_days']} days"));
              $this->assertEquals( $todays_date, get_post_meta($membership_post_id, 'membership_starts_at', true), 'Membership start date does not match.');
              $this->assertEquals( $ends_at_date, get_post_meta($membership_post_id, 'membership_ends_at', true), 'Membership end date does not match.');
              $this->assertEquals( $expires_at_date, get_post_meta($membership_post_id, 'membership_expires_at', true), 'Membership expire date does not match.');
           }
        }
    }

    /**
     * Summary of test_create_individual_membership_for_anniversary_config_15th_day
     * when should be aligned to 15th day of month
     * @return void
     */
    public function test_create_individual_membership_for_anniversary_config_15th_day() {
      $args['config_cycle'] = 'create_anniversary_config';
      $args['align-end-dates-enabled'] = true;
      $args['align-end-dates-type'] = '15th-of-month';
      $args['early_renewal_days'] = 30;
      $args['grace_period_days'] = 45;
      [
        $tier_id,
        $user_id,
        $product_id,
        $subscriptions
      ] = $this->create_individual_membership_for_config_and_product_on_tier( $args );

        foreach ($subscriptions as $subscription_id => $subscription) {
          foreach ( $subscription->get_items() as $item_id => $item ) {
              $meta_value = wc_get_order_item_meta( $item_id, '_membership_post_id_renew', true );
              $this->assertNotNull($meta_value, 'Meta key _membership_post_id_renew does not exist on subscription item.');
              $membership_post_id = $meta_value;
              $this->assertNotNull(get_post($membership_post_id), 'Membership post does not exist.');
              $this->assertEquals($tier_id, get_post_meta($membership_post_id, 'membership_tier_post_id', true), 'Membership tier ID does not match.');
              $this->assertEquals($user_id, get_post_meta($membership_post_id, 'user_id', true), 'User ID does not match.');
              $this->assertEquals($product_id, get_post_meta($membership_post_id, 'membership_product_id', true), 'Membership product ID does not match.');

              $todays_date = gmdate('Y-m-d\T00:00:00+00:00');
              $day_15th_of_month = gmdate('Y-m-15\T00:00:00+00:00');
              $ends_at_date = gmdate('Y-m-d\T00:00:00+00:00', strtotime($day_15th_of_month . ' +1 year'));
              $expires_at_date = gmdate('Y-m-d\T00:00:00+00:00', strtotime($ends_at_date . " + {$args['grace_period_days']} days"));
              $this->assertEquals( $todays_date, get_post_meta($membership_post_id, 'membership_starts_at', true), 'Membership start date does not match.');
              $this->assertEquals( $ends_at_date, get_post_meta($membership_post_id, 'membership_ends_at', true), 'Membership end date does not match.');
              $this->assertEquals( $expires_at_date, get_post_meta($membership_post_id, 'membership_expires_at', true), 'Membership expire date does not match.');
           }
        }
    }

    /**
     * Summary of test_create_individual_membership_for_anniversary_config_last_day
     * when should be aligned to last day of month
     * @return void
     */
    public function test_create_individual_membership_for_anniversary_last_day() {
      $args['config_cycle'] = 'create_anniversary_config';
      $args['align-end-dates-enabled'] = true;
      $args['align-end-dates-type'] = 'last-day-of-month';
      $args['early_renewal_days'] = 30;
      $args['grace_period_days'] = 10;
      [
        $tier_id,
        $user_id,
        $product_id,
        $subscriptions
      ] = $this->create_individual_membership_for_config_and_product_on_tier( $args );

        foreach ($subscriptions as $subscription_id => $subscription) {
          foreach ( $subscription->get_items() as $item_id => $item ) {
              $meta_value = wc_get_order_item_meta( $item_id, '_membership_post_id_renew', true );
              $this->assertNotNull($meta_value, 'Meta key _membership_post_id_renew does not exist on subscription item.');
              $membership_post_id = $meta_value;
              $this->assertNotNull(get_post($membership_post_id), 'Membership post does not exist.');
              $this->assertEquals($tier_id, get_post_meta($membership_post_id, 'membership_tier_post_id', true), 'Membership tier ID does not match.');
              $this->assertEquals($user_id, get_post_meta($membership_post_id, 'user_id', true), 'User ID does not match.');
              $this->assertEquals($product_id, get_post_meta($membership_post_id, 'membership_product_id', true), 'Membership product ID does not match.');

              $todays_date = gmdate('Y-m-d\T00:00:00+00:00');
              $last_day_next_year = gmdate('Y-m-t\T00:00:00+00:00', strtotime('+1 year'));
              //we specifically look ahead a year for single case for february leap year handling
              $ends_at_date = gmdate('Y-m-d\T00:00:00+00:00', strtotime($last_day_next_year));
              $expires_at_date = gmdate('Y-m-d\T00:00:00+00:00', strtotime($ends_at_date . " + {$args['grace_period_days']} days"));
              $this->assertEquals( $todays_date, get_post_meta($membership_post_id, 'membership_starts_at', true), 'Membership start date does not match.');
              $this->assertEquals( $ends_at_date, get_post_meta($membership_post_id, 'membership_ends_at', true), 'Membership end date does not match.');
              $this->assertEquals( $expires_at_date, get_post_meta($membership_post_id, 'membership_expires_at', true), 'Membership expire date does not match.');
           }
        }
    }

    /**
     * Summary of test_create_individual_membership_for_calendar
     * when in current season with fixed calendar dates
     * @return void
     */
    public function test_create_individual_membership_for_calendar() {
      $args['config_cycle'] = 'create_calendar_config';
      $args['cycle_type'] = 'calendar';

      //we are generating season wrapping the current date
      $season_start = gmdate('Y-m-d', strtotime('-1 month'));
      $season_end = gmdate('Y-m-d', strtotime('+2 months'));
      $args['calendar_items'] =
                [ 
                  [
                    'season_name' => 'Season 1',
                    'active' => true,
                    'start_date' => $season_start,
                    'end_date' => $season_end,
                  ]
                ];
      
      $args['early_renewal_days'] = 30; //default
      $args['grace_period_days'] = 10; //default
      [
        $tier_id,
        $user_id,
        $product_id,
        $subscriptions
      ] = $this->create_individual_membership_for_config_and_product_on_tier( $args );

        foreach ($subscriptions as $subscription_id => $subscription) {
          foreach ( $subscription->get_items() as $item_id => $item ) {
              $meta_value = wc_get_order_item_meta( $item_id, '_membership_post_id_renew', true );
              $this->assertNotNull($meta_value, 'Meta key _membership_post_id_renew does not exist on subscription item.');
              $membership_post_id = $meta_value;
              $this->assertNotNull(get_post($membership_post_id), 'Membership post does not exist.');
              $this->assertEquals($tier_id, get_post_meta($membership_post_id, 'membership_tier_post_id', true), 'Membership tier ID does not match.');
              $this->assertEquals($user_id, get_post_meta($membership_post_id, 'user_id', true), 'User ID does not match.');
              $this->assertEquals($product_id, get_post_meta($membership_post_id, 'membership_product_id', true), 'Membership product ID does not match.');

              $todays_date = gmdate('Y-m-d\T00:00:00+00:00');
              $ends_at_date = gmdate('Y-m-d\T00:00:00+00:00', strtotime($season_end));
              $expires_at_date = gmdate('Y-m-d\T00:00:00+00:00', strtotime($ends_at_date . " + {$args['grace_period_days']} days"));
              $this->assertEquals( $todays_date, get_post_meta($membership_post_id, 'membership_starts_at', true), 'Membership start date does not match.');
              $this->assertEquals( $ends_at_date, get_post_meta($membership_post_id, 'membership_ends_at', true), 'Membership end date does not match.');
              $this->assertEquals( $expires_at_date, get_post_meta($membership_post_id, 'membership_expires_at', true), 'Membership expire date does not match.');
           }
        }
    }

        /**
     * Summary of test_renew_individual_membership_for_anniversary_config
     * perform a renewal on the created membership
     * @param mixed $subscription_id
     * @return void
     */

    public function test_renew_individual_membership_for_calendar_config() {

      $args['config_cycle'] = 'create_calendar_config';
      $args['cycle_type'] = 'calendar';

      //we are generating season wrapping the current date
      $season_start = gmdate('Y-m-d', strtotime('-1 month'));
      $season_end = gmdate('Y-m-d', strtotime('+2 months'));
      $next_season_start = gmdate('Y-m-d', strtotime($season_start . ' +3 month'));
      $next_season_start = gmdate('Y-m-d', strtotime($next_season_start . ' +1 day'));
      $next_season_end = gmdate('Y-m-d', strtotime($next_season_start . ' +3 months'));

      $args['calendar_items'] =
                [ 
                  [
                    'season_name' => 'Season 1',
                    'active' => true,
                    'start_date' => $season_start,
                    'end_date' => $season_end,
                  ],
                 [
                    'season_name' => 'Season 2',
                    'active' => true,
                    'start_date' => $next_season_start,
                    'end_date' => $next_season_end,
                  ]

                ];
      
      $args['early_renewal_days'] = 30; //default
      $args['grace_period_days'] = 10; //default
      [
        $tier_id,
        $user_id,
        $product_id,
        $subscriptions
      ] = $this->create_individual_membership_for_config_and_product_on_tier( $args );

        foreach ($subscriptions as $subscription_id => $subscription) {
          $subscription = wcs_get_subscription( $subscription_id );
          //generate renewal order for subscription
          $order = wcs_create_renewal_order( $subscription );
          if ($order) {
              $order->update_status('processing');
              //Woocommerce hooks to simulate order processing
              do_action('woocommerce_order_status_processing', $order->get_id());
          }
          //verify the new membership was created with correct dates
          $subscription = wcs_get_subscription( $subscription_id );
          foreach ( $subscription->get_items() as $item_id => $item ) {
              $meta_value = wc_get_order_item_meta( $item_id, '_membership_post_id_renew', true );
              $this->assertNotNull($meta_value, 'Meta key _membership_post_id_renew does not exist on subscription item.');
              $membership_post_id = $meta_value;
              $this->assertNotNull(get_post($membership_post_id), 'Membership post does not exist.');
          }

          $starts_at_date = gmdate('Y-m-d\T00:00:00+00:00', strtotime($next_season_start ));
          $ends_at_date = gmdate('Y-m-d\T00:00:00+00:00', strtotime($next_season_end ));
          $expires_at_date = gmdate('Y-m-d\T00:00:00+00:00', strtotime($ends_at_date . " + {$args['grace_period_days']} days"));
          $this->assertEquals( $starts_at_date, get_post_meta($membership_post_id, 'membership_starts_at', true));
          $this->assertEquals( $ends_at_date, get_post_meta($membership_post_id, 'membership_ends_at', true));
          $this->assertEquals( $expires_at_date, get_post_meta($membership_post_id, 'membership_expires_at', true));
        }
    }

        /**
     * Summary of test_create_individual_membership_for_anniversary_config
     * @return void
     */
    public function test_create_organization_membership_for_anniversary_config() {
      $args['config_cycle'] = 'create_anniversary_config';
      $args['org_uuid'] = wp_generate_uuid4();
      //$args_array['align-end-dates-enabled'] = false; //default
      //$args_array['align-end-dates-type'] = 'last-day-of-month'; // 'last-day-of-month' '15th-of-month'
      //$args['early_renewal_days'] = 30; //default
      $args['grace_period_days'] = 10; //default
      [
        $tier_id,
        $user_id,
        $product_id,
        $subscriptions,
        $wicket_uuid
      ] = $this->create_individual_membership_for_config_and_product_on_tier( $args );

        foreach ($subscriptions as $subscription_id => $subscription) {
          foreach ( $subscription->get_items() as $item_id => $item ) {
              $org_meta_value = wc_get_order_item_meta( $item_id, '_org_uuid', true );
              $this->assertNotNull($org_meta_value, 'Meta key _org_uuid_renew does not exist on subscription item.');
              $membership_org_uuid = $org_meta_value;

              $renew_meta_value = wc_get_order_item_meta( $item_id, '_membership_post_id_renew', true );
              $this->assertNotNull($renew_meta_value, 'Meta key _membership_post_id_renew does not exist on subscription item.');
              $membership_post_id = $renew_meta_value;
              $this->assertNotNull(get_post($membership_post_id), 'Membership post does not exist.');
              $this->assertEquals($wicket_uuid, get_post_meta($membership_post_id, 'membership_wicket_uuid', true), 'Membership Wicket UUID does not match.');
              $this->assertEquals($tier_id, get_post_meta($membership_post_id, 'membership_tier_post_id', true), 'Membership tier ID does not match.');
              $this->assertEquals($user_id, get_post_meta($membership_post_id, 'user_id', true), 'User ID does not match.');

              //check organization membership specific meta was attached correctly from subscription item
              $this->assertEquals($product_id, get_post_meta($membership_post_id, 'membership_product_id', true), 'Membership product ID does not match.');
              $this->assertEquals('organization', get_post_meta($membership_post_id, 'membership_type', true), 'Membership type is not organization.');
              $this->assertEquals($membership_org_uuid, get_post_meta($membership_post_id, 'org_uuid', true), 'Organization UUID does not match.');

              $todays_date = gmdate('Y-m-d\T00:00:00+00:00');
              $ends_at_date = gmdate('Y-m-d\T00:00:00+00:00', strtotime($todays_date . ' +1 year'));
              $expires_at_date = gmdate('Y-m-d\T00:00:00+00:00', strtotime($ends_at_date . " + {$args['grace_period_days']} days"));
              $this->assertEquals( $todays_date, get_post_meta($membership_post_id, 'membership_starts_at', true), 'Membership start date does not match.');
              $this->assertEquals( $ends_at_date, get_post_meta($membership_post_id, 'membership_ends_at', true), 'Membership end date does not match.');
              $this->assertEquals( $expires_at_date, get_post_meta($membership_post_id, 'membership_expires_at', true), 'Membership expire date does not match.');
           }
        }
    }
}