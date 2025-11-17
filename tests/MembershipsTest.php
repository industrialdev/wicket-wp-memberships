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
<?php
require_once __DIR__ . '/WP_UnitTestCase_NoDeprecationFail.php';

use function Brain\Monkey\Functions\when;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

defined('ABSPATH') || exit;

class MembershipsTestConfig extends WP_UnitTestCase_NoDeprecationFail {
    protected $custom_factory;

    public function setUp(): void {
        parent::setUp();
        error_reporting(E_ALL & ~E_WARNING);
        setUp();
        $factory = isset($GLOBALS['factory']) ? $GLOBALS['factory'] : $this->factory;
        $this->custom_factory = (object) [
            'wicket_mship_tier' => new WP_UnitTest_Factory_For_Wicket_Mship_Tier($factory),
            'wicket_mship_config' => new WP_UnitTest_Factory_For_Wicket_Mship_Config($factory),
            'wicket_mship_membership' => new WP_UnitTest_Factory_For_Wicket_Mship_Membership($factory),
        ];

    }

    protected function tearDown(): void {
        parent::tearDown();
        tearDown();
        $this->local_teardown();
    }

    private function local_teardown() {
        // Cleanup posts
        $post_types = [
            'wicket_mship_membership',
            'wicket_mship_config',
            'wicket_mship_tier',
            'product',
            'shop_order',
            'shop_subscription'
        ];
        foreach ($post_types as $type) {
            $posts = get_posts([
                'post_type' => $type,
                'post_status' => 'any',
                'numberposts' => -1,
                'fields' => 'ids'
            ]);
            foreach ($posts as $post_id) {
                wp_delete_post($post_id, true);
            }
        }
        // Cleanup users
        $users = get_users([
            'fields' => 'ID'
        ]);
        foreach ($users as $user_id) {
            if ($user_id > 1) { // Don't delete admin
                wp_delete_user($user_id);
            }
        }
        // Cleanup user meta
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '_wicket_membership_%'");
        // Cleanup post meta
        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_wicket_membership_%'");
    }

    private function generate_test_uuid() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Summary of test_can_create_membership_post
     * @return void
     */
    public function test_can_create_membership_post() {
        // Create related config and tier for foreign keys
        $config_id = $this->custom_factory->wicket_mship_config->create([
            'post_title' => 'Related Config',
            'post_status' => 'publish',
        ]);
        $tier_id = $this->custom_factory->wicket_mship_tier->create([
            'post_title' => 'Related Tier',
            'post_status' => 'publish',
        ]);

        $meta = [
            'membership_status' => 'active',
            'user_id' => rand(1, 1000),
            'membership_wicket_uuid' => 'wkt-uuid-' . rand(1000, 9999),
            'membership_starts_at' => '2025-01-01',
            'membership_ends_at' => '2025-12-31',
            'membership_expires_at' => '2026-01-31',
            'membership_early_renew_at' => '2025-11-01',
            'membership_type' => 'person',
            'org_name' => 'Test Org',
            'org_uuid' => 'org-uuid-' . rand(1000, 9999),
            'org_seats' => rand(1, 50),
            'membership_uuid' => 'mem-uuid-' . rand(1000, 9999),
            'membership_tier_uuid' => 'tier-uuid-' . rand(1000, 9999),
            'membership_tier_name' => 'Gold',
            'membership_next_tier_id' => rand(100, 200),
            'membership_next_form_id' => rand(200, 300),
            'membership_parent_order_id' => rand(1000, 9999),
            'membership_subscription_id' => rand(1000, 9999),
            'membership_product_id' => rand(1000, 9999),
            // Foreign keys to config and tier
            'membership_config_id' => $config_id,
            'membership_tier_id' => $tier_id,
        ];

        $membership_id = $this->custom_factory->wicket_mship_membership->create([
            'post_title' => 'Test Membership',
            'post_status' => 'publish',
            'meta_input' => $meta,
        ]);

        $this->assertIsInt($membership_id);
        $this->assertNotNull(get_post($membership_id));
        foreach ($meta as $key => $value) {
            $this->assertEquals($value, get_post_meta($membership_id, $key, true));
        }
    }

    protected function create_subscription_with_product_and_pending_order( $product_id ) {
        // Create a WooCommerce order in pending_payment status
        $order = wc_create_order();
        $user_id = $this->factory->user->create([
            'role' => 'customer',
            'user_login' => 'test_customer_' . wp_generate_password(6, false),
        ]);
        $order->set_customer_id($user_id);
        $order->add_product(wc_get_product($product_id), 1);
        // Default unpaid status for WooCommerce order is 'pending'
        $order->update_status('pending');
        $order_id = $order->get_id();

        // Create a WooCommerce subscription with the product and link to parent order
        if (!class_exists('WC_Subscription')) {
            printf("[DEBUG] WC_Subscription class not found, skipping test\n");
            $this->markTestSkipped('WooCommerce Subscriptions plugin is not active.');
            return;
        }
        $subscription = wcs_create_subscription([
            'order_id'        => $order_id,
            'customer_id'     => $user_id,
            'billing_period'  => 'year',
            'billing_interval'=> 1,
            'start_date'      => gmdate('Y-m-d H:i:s'),
            'status'          => 'pending'
        ]);

        $subscription->add_product(wc_get_product($product_id), 1);
        $subscription_id = $subscription->get_id();

        // Assertions with console reporting on fail
        try {
            $this->assertIsInt($product_id);
        } catch (Exception $e) {
            error_log('Assertion failed: product_id is not int. ' . $e->getMessage());
        }
        try {
            $this->assertIsInt($order_id);
        } catch (Exception $e) {
            error_log('Assertion failed: order_id is not int. ' . $e->getMessage());
        }
        try {
            $this->assertIsInt($subscription_id);
        } catch (Exception $e) {
            error_log('Assertion failed: subscription_id is not int. ' . $e->getMessage());
        }
        try {
            $this->assertEquals($order_id, $subscription->get_parent_id());
        } catch (Exception $e) {
            error_log('Assertion failed: subscription parent_id does not match order_id. ' . $e->getMessage());
        }
        try {
            $this->assertNotEquals('active', $subscription->get_status()); // Should be unpaid status
        } catch (Exception $e) {
            error_log('Assertion failed: subscription status is active, should be unpaid. ' . $e->getMessage());
        }
        /* Renewal calls previous test resulting in multiple items */
        try {
            $this->assertCount(1, $subscription->get_items());
        } catch (Exception $e) {
            error_log('Assertion failed: subscription does not have exactly 1 item. ' . $e->getMessage());
        }
        return $order_id;
    }

    /**
     * Summary of create_individual_membership_for_config_and_product_on_tier
     * @param mixed $args_array
     * @return array<int|mixed>
     */
    public function create_individual_membership_for_config_and_product_on_tier( $args ) {
        $uuid = $this->generate_test_uuid();
        //BrainMonkey mock base plugin and MDP API responses
        when('wicket_assign_individual_membership')->justReturn([
            'success' => true,
            'data' => ['id' => $uuid]
        ]);
        when('wicket_assign_organization_membership')->justReturn([
            'success' => true,
            'data' => ['id' => $uuid]
        ]);
        when('wicket_get_person_membership_exists')->justReturn([]);
        when('wicket_update_membership_external_id')->justReturn([]);

        // Create a simple product
        $product_factory = new WP_UnitTest_Factory_For_Product($this->factory);
        $product_id = $product_factory->create_product();

        $config_id = $this->custom_factory->wicket_mship_config->{$args['config_cycle']}(
          $args['early_renewal_days'],
          $args['grace_period_days'], 
          $args
        );

        // Create a tier using both config and product
        $tier_data = [
            'mdp_tier_name' => 'Tier With Product',
            'mdp_tier_uuid' => uniqid('tier-uuid-'),
            'renewal_type' => 'subscription',
            'type' => 'individual',
            'seat_type' => 'per_seat',
            'product_data' => [
                [
                    'product_id' => $product_id,
                    'variation_id' => 0,
                    'max_seats' => 0,
                ]
            ],
        ];
        $tier_id = $this->custom_factory->wicket_mship_tier->create_tier_with_config($config_id, $tier_data);

        $order_id = $this->create_subscription_with_product_and_pending_order( $product_id );
        $user_id = get_post_meta( $order_id, '_customer_user', true );

        $this->assertIsNumeric($config_id);
        $this->assertIsNumeric($product_id);
        $this->assertIsNumeric($tier_id);
        $this->assertIsNumeric($order_id);
        $this->assertIsNumeric($user_id);
        //echo 'fo'.$order_id;
        $order = wc_get_order($order_id);
        if ($order) {
            $order->update_status('processing');
            //Woocommerce hooks to simulate order processing
            do_action('woocommerce_order_status_processing', $order->get_id());

            // Get subscriptions for this order
            if (function_exists('wcs_get_subscriptions_for_order')) {
                $subscriptions = wcs_get_subscriptions_for_order($order_id, ['order_type' => 'any']);
            }
            return [$tier_id, $user_id, $product_id,$subscriptions];
        }
    }

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
        $subscriptions
      ] = $this->create_individual_membership_for_config_and_product_on_tier( $args );

        foreach ($subscriptions as $subscription_id => $subscription) {
          foreach ( $subscription->get_items() as $item_id => $item ) {
              $meta_value = wc_get_order_item_meta( $item_id, '_membership_post_id_renew', true );
              $this->assertNotNull($meta_value, 'Meta key _membership_post_id_renew does not exist on subscription item.');
              $membership_post_id = $meta_value;
              //echo 'f'.$membership_post_id;
              $this->assertNotNull(get_post($membership_post_id), 'Membership post does not exist.');
              $this->assertEquals($tier_id, get_post_meta($membership_post_id, 'membership_tier_post_id', true));
              $this->assertEquals($user_id, get_post_meta($membership_post_id, 'user_id', true));
              $this->assertEquals($product_id, get_post_meta($membership_post_id, 'membership_product_id', true));

              $todays_date = gmdate('Y-m-d\T00:00:00+00:00');
              $ends_at_date = gmdate('Y-m-d\T00:00:00+00:00', strtotime($todays_date . ' +1 year'));
              $expires_at_date = gmdate('Y-m-d\T00:00:00+00:00', strtotime($ends_at_date . " + {$args['grace_period_days']} days"));
              $this->assertEquals( $todays_date, get_post_meta($membership_post_id, 'membership_starts_at', true));
              $this->assertEquals( $ends_at_date, get_post_meta($membership_post_id, 'membership_ends_at', true));
              $this->assertEquals( $expires_at_date, get_post_meta($membership_post_id, 'membership_expires_at', true));
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
              $this->assertEquals($tier_id, get_post_meta($membership_post_id, 'membership_tier_post_id', true));
              $this->assertEquals($user_id, get_post_meta($membership_post_id, 'user_id', true));
              $this->assertEquals($product_id, get_post_meta($membership_post_id, 'membership_product_id', true));

              $todays_date = gmdate('Y-m-d\T00:00:00+00:00');
              $first_day_of_month = gmdate('Y-m-01\T00:00:00+00:00');
              $ends_at_date = gmdate('Y-m-d\T00:00:00+00:00', strtotime($first_day_of_month . ' +1 year'));
              $expires_at_date = gmdate('Y-m-d\T00:00:00+00:00', strtotime($ends_at_date . " + {$args['grace_period_days']} days"));
              $this->assertEquals( $todays_date, get_post_meta($membership_post_id, 'membership_starts_at', true));
              $this->assertEquals( $ends_at_date, get_post_meta($membership_post_id, 'membership_ends_at', true));
              $this->assertEquals( $expires_at_date, get_post_meta($membership_post_id, 'membership_expires_at', true));
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
              $this->assertEquals($tier_id, get_post_meta($membership_post_id, 'membership_tier_post_id', true));
              $this->assertEquals($user_id, get_post_meta($membership_post_id, 'user_id', true));
              $this->assertEquals($product_id, get_post_meta($membership_post_id, 'membership_product_id', true));

              $todays_date = gmdate('Y-m-d\T00:00:00+00:00');
              $day_15th_of_month = gmdate('Y-m-15\T00:00:00+00:00');
              $ends_at_date = gmdate('Y-m-d\T00:00:00+00:00', strtotime($day_15th_of_month . ' +1 year'));
              $expires_at_date = gmdate('Y-m-d\T00:00:00+00:00', strtotime($ends_at_date . " + {$args['grace_period_days']} days"));
              $this->assertEquals( $todays_date, get_post_meta($membership_post_id, 'membership_starts_at', true));
              $this->assertEquals( $ends_at_date, get_post_meta($membership_post_id, 'membership_ends_at', true));
              $this->assertEquals( $expires_at_date, get_post_meta($membership_post_id, 'membership_expires_at', true));
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
              $this->assertEquals($tier_id, get_post_meta($membership_post_id, 'membership_tier_post_id', true));
              $this->assertEquals($user_id, get_post_meta($membership_post_id, 'user_id', true));
              $this->assertEquals($product_id, get_post_meta($membership_post_id, 'membership_product_id', true));

              $todays_date = gmdate('Y-m-d\T00:00:00+00:00');
              $last_day_next_year = gmdate('Y-m-t\T00:00:00+00:00', strtotime('+1 year'));
              //we specifically look ahead a year for single case for february leap year handling
              $ends_at_date = gmdate('Y-m-d\T00:00:00+00:00', strtotime($last_day_next_year));
              $expires_at_date = gmdate('Y-m-d\T00:00:00+00:00', strtotime($ends_at_date . " + {$args['grace_period_days']} days"));
              $this->assertEquals( $todays_date, get_post_meta($membership_post_id, 'membership_starts_at', true));
              $this->assertEquals( $ends_at_date, get_post_meta($membership_post_id, 'membership_ends_at', true));
              $this->assertEquals( $expires_at_date, get_post_meta($membership_post_id, 'membership_expires_at', true));
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
              $this->assertEquals($tier_id, get_post_meta($membership_post_id, 'membership_tier_post_id', true));
              $this->assertEquals($user_id, get_post_meta($membership_post_id, 'user_id', true));
              $this->assertEquals($product_id, get_post_meta($membership_post_id, 'membership_product_id', true));

              $todays_date = gmdate('Y-m-d\T00:00:00+00:00');
              $ends_at_date = gmdate('Y-m-d\T00:00:00+00:00', strtotime($season_end));
              $expires_at_date = gmdate('Y-m-d\T00:00:00+00:00', strtotime($ends_at_date . " + {$args['grace_period_days']} days"));
              $this->assertEquals( $todays_date, get_post_meta($membership_post_id, 'membership_starts_at', true));
              $this->assertEquals( $ends_at_date, get_post_meta($membership_post_id, 'membership_ends_at', true));
              $this->assertEquals( $expires_at_date, get_post_meta($membership_post_id, 'membership_expires_at', true));
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
}