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
            'post_title' => 'Test Product',
            'post_status' => 'publish',
            'post_type' => 'product',
            'meta_input' => [],
        ];
        $args = array_merge($defaults, $post_args);

        // Set product category taxonomy if provided
        $post_id = parent::create($args);
        $cat = isset($args['product_cat']) ? $args['product_cat'] : 'Membership';
        wp_set_object_terms($post_id, $cat, 'product_cat');
        return $post_id;
    }
}