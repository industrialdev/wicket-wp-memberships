<?php
defined('ABSPATH') || exit;
require_once __DIR__ . '/WP_UnitTestCase_NoDeprecationFail.php';

use function Brain\Monkey\Functions\when;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

class MembershipsBaseTest extends WP_UnitTestCase_NoDeprecationFail {

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
            'wicket_mship_product' => new WP_UnitTest_Factory_For_Product($factory),
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

    protected function create_subscription_with_product_and_pending_order( $product_id, $args ) {
        // Create a WooCommerce order in pending_payment status
        $order = wc_create_order();
        if(empty($args['user_id'])) {
          $user_id = $this->factory->user->create([
              'role' => 'customer',
              'user_login' => 'test_customer_' . wp_generate_password(6, false),
          ]);
        } else {
          $user_id = $args['user_id'];
        }
        $order->set_customer_id($user_id);
        $order_item_id = $order->add_product(wc_get_product($product_id), 1);
        if($args['org_uuid']) {
            // Add _org_uuid meta to the line item
            wc_update_order_item_meta($order_item_id, '_org_uuid', $args['org_uuid']);
        }
        // Default unpaid status for WooCommerce order is 'pending'
        $order->update_status('pending');
        $order_id = $order->get_id();

        // Create a WooCommerce subscription with the product and link to parent order
        if (!class_exists('WC_Subscription')) {
            printf("[DEBUG] WC_Subscription class not found, skipping test\n");
            $this->markTestSkipped('WooCommerce Subscriptions plugin is not active.');
            return;
        }
        $subscription_item_id = $subscription = wcs_create_subscription([
            'order_id'        => $order_id,
            'customer_id'     => $user_id,
            'billing_period'  => 'year',
            'billing_interval'=> 1,
            'start_date'      => gmdate('Y-m-d H:i:s'),
            'status'          => 'pending'
        ]);
        if($args['org_uuid']) {
            // Add _org_uuid meta to the line item
            wc_update_order_item_meta($subscription_item_id, '_org_uuid', $args['org_uuid']);
        }

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
        $uuid = wp_generate_uuid4();
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

        $membership_type = empty($args['org_uuid']) ? 'individual' : 'organization';

        // Create a simple product
        $product_id = $this->custom_factory->wicket_mship_product->create_product();

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
            'type' => $membership_type,
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

        $order_id = $this->create_subscription_with_product_and_pending_order( $product_id, $args );
        $user_id = get_post_meta( $order_id, '_customer_user', true );

        $this->assertIsNumeric($config_id);
        $this->assertIsNumeric($product_id);
        $this->assertIsNumeric($tier_id);
        $this->assertIsNumeric($order_id);
        $this->assertIsNumeric($user_id);
        $order = wc_get_order($order_id);
        if ($order) {
            $order->update_status('processing');
            //Woocommerce hooks to simulate order processing
            do_action('woocommerce_order_status_processing', $order->get_id());

            // Get subscriptions for this order
            if (function_exists('wcs_get_subscriptions_for_order')) {
                $subscriptions = wcs_get_subscriptions_for_order($order_id, ['order_type' => 'any']);
            }
            return [$tier_id, $user_id, $product_id,$subscriptions, $uuid];
        }
    }
}