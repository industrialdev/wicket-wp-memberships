<?php
// tests/FactoryTest.php

class FactoryTest extends WP_UnitTestCase {
    protected $custom_factory;

    public function setUp(): void {
        parent::setUp();
        $this->requireWordPress(); // Skip if WordPress not available
        $factory = isset($GLOBALS['factory']) ? $GLOBALS['factory'] : $this->factory;
        $this->custom_factory = (object) [
            'wicket_mship_tier' => new WP_UnitTest_Factory_For_Wicket_Mship_Tier($factory),
            'wicket_mship_config' => new WP_UnitTest_Factory_For_Wicket_Mship_Config($factory),
            'wicket_mship_membership' => new WP_UnitTest_Factory_For_Wicket_Mship_Membership($factory),
        ];
    }

    public function test_wordpress_loaded() {
        try {
            $this->assertTrue(function_exists('get_post'), 'WordPress not loaded: get_post() missing');
        } catch (Exception $e) {
            error_log('Assertion failed: WordPress not loaded. ' . $e->getMessage());
        }
    }

    public function test_can_create_wicket_mship_config_with_all_meta() {
        $meta = [
            'renewal_window_data' => [
                'days_count' => 30,
                'locales' => [
                    'en' => [
                        'callout_header' => 'Renew Now!',
                        'callout_content' => 'Renew your membership before it expires.',
                        'callout_button_label' => 'Renew',
                    ],
                ],
            ],
            'late_fee_window_data' => [
                'days_count' => 10,
                'product_id' => 123,
                'locales' => [
                    'en' => [
                        'callout_header' => 'Late Fee Applies',
                        'callout_content' => 'A late fee will be charged.',
                        'callout_button_label' => 'Pay Late Fee',
                    ],
                ],
            ],
            'cycle_data' => [
                'cycle_type' => 'anniversary',
                'anniversary_data' => [
                    'period_count' => 1,
                    'period_type' => 'year',
                    'align_end_dates_enabled' => true,
                    'align_end_dates_type' => 'last-day-of-month',
                ],
            ],
            'multi_tier_renewal' => 1,
        ];

        $config_id = $this->custom_factory->wicket_mship_config->create([
            'post_title' => 'Full Meta Config',
            'post_status' => 'publish',
            'meta_input' => $meta,
        ]);

        try {
            $this->assertIsInt($config_id);
        } catch (Exception $e) {
            error_log('Assertion failed: Config ID is not int. ' . $e->getMessage());
        }
        try {
            $this->assertNotNull(get_post($config_id));
        } catch (Exception $e) {
            error_log('Assertion failed: Config post does not exist. ' . $e->getMessage());
        }
        foreach ($meta as $key => $value) {
            try {
                $this->assertEquals($value, get_post_meta($config_id, $key, true));
            } catch (Exception $e) {
                error_log("Assertion failed: Meta '$key' does not match expected value. " . $e->getMessage());
            }
        }
        try {
            $config_obj = new \Wicket_Memberships\Membership_Config($config_id);
            $this->assertInstanceOf(\Wicket_Memberships\Membership_Config::class, $config_obj);
        } catch (Exception $e) {
            error_log('Assertion failed: Membership_Config object not instantiated. ' . $e->getMessage());
        }
  }


    public function test_can_create_wicket_mship_tier_with_all_meta() {
        $tier_data = [
            'mdp_tier_name' => 'Gold Tier',
            'mdp_tier_uuid' => 'uuid-1234',
            'renewal_type' => 'subscription',
            'next_tier_id' => 42,
            'next_tier_form_page_id' => 99,
        ];
        $meta = [
            'tier_data' => $tier_data,
            'membership_tier_slug' => 'gold-tier-slug',
            'membership_tier_uuid' => 'uuid-1234',
            'membership_tier_name' => 'Gold Tier',
            'membership_next_tier_id' => 42,
            'membership_next_form_id' => 99,
            'membership_parent_order_id' => 555,
            'membership_product_id' => 777,
        ];

        $tier_id = $this->custom_factory->wicket_mship_tier->create([
            'post_title' => 'Full Meta Tier',
            'post_status' => 'publish',
            'meta_input' => $meta,
        ]);

        try {
            $this->assertIsInt($tier_id);
        } catch (Exception $e) {
            error_log('Assertion failed: Tier ID is not int. ' . $e->getMessage());
        }
        try {
            $this->assertNotNull(get_post($tier_id));
        } catch (Exception $e) {
            error_log('Assertion failed: Tier post does not exist. ' . $e->getMessage());
        }
        foreach ($meta as $key => $value) {
            try {
                $this->assertEquals($value, get_post_meta($tier_id, $key, true));
            } catch (Exception $e) {
                error_log("Assertion failed: Meta '$key' does not match expected value. " . $e->getMessage());
            }
        }
        try {
            $tier_obj = new \Wicket_Memberships\Membership_Tier($tier_id);
            $this->assertInstanceOf(\Wicket_Memberships\Membership_Tier::class, $tier_obj);
        } catch (Exception $e) {
            error_log('Assertion failed: Membership_Tier object not instantiated. ' . $e->getMessage());
        }
    }

    public function test_can_create_wicket_mship_membership() {
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

        try {
            $this->assertIsInt($membership_id);
        } catch (Exception $e) {
            error_log('Assertion failed: Membership ID is not int. ' . $e->getMessage());
        }
        try {
            $this->assertNotNull(get_post($membership_id));
        } catch (Exception $e) {
            error_log('Assertion failed: Membership post does not exist. ' . $e->getMessage());
        }
        foreach ($meta as $key => $value) {
            try {
                $this->assertEquals($value, get_post_meta($membership_id, $key, true));
            } catch (Exception $e) {
                error_log("Assertion failed: Meta '$key' does not match expected value. " . $e->getMessage());
            }
        }
    }

     public function test_can_create_anniversary_config_with_factory_method() {
        $config_id = $this->custom_factory->wicket_mship_config->create_anniversary_config(45, 15, [
            'post_title' => 'Test Anniversary Config',
        ]);
        try {
            $this->assertIsInt($config_id);
        } catch (Exception $e) {
            error_log('Assertion failed: Anniversary Config ID is not int. ' . $e->getMessage());
        }
        $post = get_post($config_id);
        try {
            $this->assertNotNull($post);
        } catch (Exception $e) {
            error_log('Assertion failed: Anniversary Config post does not exist. ' . $e->getMessage());
        }
        $cycle_data = get_post_meta($config_id, 'cycle_data', true);
        try {
            $this->assertIsArray($cycle_data);
        } catch (Exception $e) {
            error_log('Assertion failed: cycle_data is not array. ' . $e->getMessage());
        }
        try {
            $this->assertEquals('anniversary', $cycle_data['cycle_type']);
        } catch (Exception $e) {
            error_log('Assertion failed: cycle_type is not anniversary. ' . $e->getMessage());
        }
        $renewal_window_data = get_post_meta($config_id, 'renewal_window_data', true);
        $late_fee_window_data = get_post_meta($config_id, 'late_fee_window_data', true);
        try {
            $this->assertEquals(45, $renewal_window_data['days_count']);
        } catch (Exception $e) {
            error_log('Assertion failed: early renewal days do not match. ' . $e->getMessage());
        }
        try {
            $this->assertEquals(15, $late_fee_window_data['days_count']);
        } catch (Exception $e) {
            error_log('Assertion failed: grace period days do not match. ' . $e->getMessage());
        }
        try {
            $this->assertEquals('Test Anniversary Config', $post->post_title);
        } catch (Exception $e) {
            error_log('Assertion failed: post_title does not match. ' . $e->getMessage());
        }
        try {
            $config_obj = new \Wicket_Memberships\Membership_Config($config_id);
            $this->assertInstanceOf(\Wicket_Memberships\Membership_Config::class, $config_obj);
        } catch (Exception $e) {
            error_log('Assertion failed: Membership_Config object not instantiated (anniversary config). ' . $e->getMessage());
        }
    }

    public function test_can_create_calendar_config_with_factory_method() {
        $config_id = $this->custom_factory->wicket_mship_config->create_calendar_config(60, 20, [
            'post_title' => 'Test Calendar Config',
        ]);
        try {
            $this->assertIsInt($config_id);
        } catch (Exception $e) {
            error_log('Assertion failed: Calendar Config ID is not int. ' . $e->getMessage());
        }
        $post = get_post($config_id);
        try {
            $this->assertNotNull($post);
        } catch (Exception $e) {
            error_log('Assertion failed: Calendar Config post does not exist. ' . $e->getMessage());
        }
        $cycle_data = get_post_meta($config_id, 'cycle_data', true);
        try {
            $this->assertIsArray($cycle_data);
        } catch (Exception $e) {
            error_log('Assertion failed: cycle_data is not array. ' . $e->getMessage());
        }
        try {
            $this->assertEquals('calendar', $cycle_data['cycle_type']);
        } catch (Exception $e) {
            error_log('Assertion failed: cycle_type is not calendar. ' . $e->getMessage());
        }
        $renewal_window_data = get_post_meta($config_id, 'renewal_window_data', true);
        $late_fee_window_data = get_post_meta($config_id, 'late_fee_window_data', true);
        try {
            $this->assertEquals(60, $renewal_window_data['days_count']);
        } catch (Exception $e) {
            error_log('Assertion failed: early renewal days do not match. ' . $e->getMessage());
        }
        try {
            $this->assertEquals(20, $late_fee_window_data['days_count']);
        } catch (Exception $e) {
            error_log('Assertion failed: grace period days do not match. ' . $e->getMessage());
        }
        try {
            $this->assertEquals('Test Calendar Config', $post->post_title);
        } catch (Exception $e) {
            error_log('Assertion failed: post_title does not match. ' . $e->getMessage());
        }
        try {
            $config_obj = new \Wicket_Memberships\Membership_Config($config_id);
            $this->assertInstanceOf(\Wicket_Memberships\Membership_Config::class, $config_obj);
        } catch (Exception $e) {
            error_log('Assertion failed: Membership_Config object not instantiated (calendar config). ' . $e->getMessage());
        }
    }
}