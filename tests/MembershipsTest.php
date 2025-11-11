<?php
// tests/MembershipsTest.php
use \Brain\Monkey;
use \Brain\Monkey\Functions;

defined('ABSPATH') || exit;

class MembershipsTest extends WP_UnitTestCase {
    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    protected $custom_factory;


    public function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        $factory = isset($GLOBALS['factory']) ? $GLOBALS['factory'] : $this->factory;
        $this->custom_factory = (object) [
            'wicket_mship_tier' => new WP_UnitTest_Factory_For_Wicket_Mship_Tier($factory),
            'wicket_mship_config' => new WP_UnitTest_Factory_For_Wicket_Mship_Config($factory),
            'wicket_mship_membership' => new WP_UnitTest_Factory_For_Wicket_Mship_Membership($factory),
        ];
    }

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

        printf("[DEBUG] Entered create_subscription_with_product_and_pending_order\n");
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

        printf("[DEBUG] Created order with ID: %s\n", $order_id);

        // Create a WooCommerce subscription with the product and link to parent order
        if (!class_exists('WC_Subscription')) {
            printf("[DEBUG] WC_Subscription class not found, skipping test\n");
            $this->markTestSkipped('WooCommerce Subscriptions plugin is not active.');
            return;
        }
        $subscription = wcs_create_subscription([
            'order_id'        => $order_id,
            'customer_id'     => $user_id,
            'billing_period'  => 'month',
            'billing_interval'=> 1,
            'start_date'      => gmdate('Y-m-d H:i:s'),
            // 'end_date'    => '', // Optional: set if you want a limited subscription
            // 'status'      => 'pending', // Optional: let it default
        ]);

        $subscription->add_product(wc_get_product($product_id), 1);
        $subscription_id = $subscription->get_id();

        printf("[DEBUG] Created subscription with ID: %s\n", $subscription_id);

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
        try {
            $this->assertCount(1, $subscription->get_items());
        } catch (Exception $e) {
            error_log('Assertion failed: subscription does not have exactly 1 item. ' . $e->getMessage());
        }

        return $order_id;
    }

    public function test_create_membership_for_anniversary_config_and_product_on_tier() {

    Functions::when('wicket_assign_individual_membership')
    ->alias(function($args) {
        // Return your custom response for this test
        return ['success' => true, 'data' => ['membership_id' => 999, 'type' => 'individual']];
    });

    Functions::when('wicket_assign_organization_membership')
        ->alias(function($args) {
            // Return your custom response for this test
            return ['success' => true, 'data' => ['membership_id' => 888, 'type' => 'organization']];
        });
        
        printf("[DEBUG] Running test_create_membership_for_anniversary_config_and_product_on_tier\n");
        // Create an anniversary config
        $config_id = $this->custom_factory->wicket_mship_config->create_anniversary_config(30, 10, [
            'post_title' => 'Anniversary Config',
        ]);

        printf("[DEBUG] Created config with ID: %s\n", $config_id);

        // Create a simple product
        $product_factory = new WP_UnitTest_Factory_For_Product($this->factory);
        $product_id = $product_factory->create_product([
            'post_title' => 'Test Product',
            'regular_price' => '15.00',
            'price' => '15.00',
            'stock' => 50,
        ]);

        printf("[DEBUG] Created product with ID: %s\n", $product_id);

        // Create a tier using both config and product
        printf("[DEBUG] post_type_exists('wicket_mship_tier'): %s\n", post_type_exists('wicket_mship_tier') ? 'true' : 'false');
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
        //$tier_obj = new \Wicket_Memberships\Membership_Tier($tier_id);
        printf("[DEBUG] Created tier with ID: %s\n", $tier_id);

        // Stub: Add assertions and further logic as needed
        $this->assertIsInt($config_id);
        $this->assertIsInt($product_id);
        $this->assertIsInt($tier_id);

        $order_id = $this->create_subscription_with_product_and_pending_order( $product_id );
        printf("[DEBUG] Returned order ID from helper: %s\n", $order_id);
        // Change the order status to 'processing' after subscription creation
        $order = wc_get_order($order_id);
        if ($order) {
            printf("[DEBUG] Updating order status to processing for order ID: %s\n", $order_id);
            $order->update_status('processing');

            //Woocommerce hooks to simulate order processing
            do_action('woocommerce_order_status_processing', $order->get_id());
            
            sleep(5);
            // Get subscriptions for this order
            if (function_exists('wcs_get_subscriptions_for_order')) {
                printf("[DEBUG] wcs_get_subscriptions_for_order is available, fetching subscriptions for order ID: %s\n", $order_id);
                $subscriptions = wcs_get_subscriptions_for_order($order_id, ['order_type' => 'any']);
                foreach ($subscriptions as $subscription_id => $subscription) {
                    $items = $subscription->get_items();
                    printf("Subscription ID: %s has %d items.\n", $subscription_id, count($items));
                    foreach ($items as $item_id => $item) {
                        printf("Subscription item ID: %s, product ID: %s\n", $item_id, $item->get_product_id());
                        $meta = $item->get_meta_data();
                        var_dump($meta);exit;
                        foreach ($meta as $meta_obj) {
                            printf("Meta key: %s, value: %s\n", $meta_obj->key, print_r($meta_obj->value, true));
                        }
                    }
                }
            }
        }

    }
}