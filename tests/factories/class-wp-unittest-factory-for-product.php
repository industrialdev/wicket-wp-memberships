<?php
// tests/factories/class-wp-unittest-factory-for-product.php

class WP_UnitTest_Factory_For_Product extends WP_UnitTest_Factory_For_Post {
    public function __construct( $factory = null ) {
        parent::__construct( $factory, 'product' );
    }

    /**
     * Create a WooCommerce product post with meta.
     *
     * @param array $post_args Optional. Additional post args (title, price, etc).
     * @return int             Post ID of created product.
     */
    public function create_product($post_args = []) {
        $defaults = [
            'post_title' => 'Test Subscription Product',
            'post_status' => 'publish',
            'post_type' => 'product',
            'meta_input' => [
                '_subscription_period' => 'year', // 1 year billing term
                '_subscription_period_interval' => '1',
                '_subscription_length' => '1', // No renewal (1 cycle only)
                '_subscription_sign_up_fee' => '0',
                '_subscription_trial_length' => '0',
                '_subscription_trial_period' => '',
                '_subscription_price' => isset($post_args['regular_price']) ? $post_args['regular_price'] : '15.00',
                '_virtual' => 'yes',
                '_downloadable' => 'no',
                '_manage_stock' => 'no',
                '_stock' => isset($post_args['stock']) ? $post_args['stock'] : '50',
            ],
        ];
        $args = array_merge($defaults, $post_args);

        // Set product category taxonomy if provided
        $post_id = parent::create($args);
        $cat = isset($args['product_cat']) ? $args['product_cat'] : 'Membership';
        wp_set_object_terms($post_id, $cat, 'product_cat');

        // Set product type to 'subscription'
        wp_set_object_terms($post_id, 'subscription', 'product_type');

        return $post_id;
    }
}