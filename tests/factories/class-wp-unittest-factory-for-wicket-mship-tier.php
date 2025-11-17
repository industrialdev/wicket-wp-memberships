<?php
// tests/factories/class-wp-unittest-factory-for-wicket-mship-tier.php


class WP_UnitTest_Factory_For_Wicket_Mship_Tier extends WP_UnitTest_Factory_For_Post {
    public function __construct( $factory = null ) {
        parent::__construct( $factory, 'wicket_mship_tier' );
    }

    /**
     * Create a tier with all necessary tier data and config association.
     *
     * @param int   $config_id   The ID of the associated Membership_Config post.
     * @param array $tier_data   Array of tier data fields. Recommended keys:
     *   - mdp_tier_name (string)
     *   - mdp_tier_uuid (string)
     *   - renewal_type (string: 'subscription', 'form_page', etc)
     *   - type (string: 'organization' or 'individual')
     *   - seat_type (string: 'per_seat', 'per_range_of_seats', etc)
     *   - next_tier_id (int)
     *   - next_tier_form_page_id (int)
     *   - product_data (array of arrays: each with 'product_id', 'variation_id', 'max_seats')
     *   - approval_required (bool|int)
     *   - grant_owner_assignment (bool|int)
     *   - approval_email_recipient (string)
     *   - approval_callout_data (array: 'locales' => [ 'en' => [ 'callout_header', 'callout_content', 'callout_button_label' ] ])
     *   - ...any other custom tier meta fields
     * @param array $post_args   Optional. Additional post args (title, status, etc).
     * @return int               Post ID of created tier.
     */
    public function create_tier_with_config($config_id, $tier_data = [], $post_args = []) {
        $default_tier_data = [
            'config_id' => $config_id,
            'mdp_tier_name' => 'Test Tier',
            'mdp_tier_uuid' => uniqid('tier-uuid-'),
            'renewal_type' => 'subscription',
            'type' => 'individual',
            'seat_type' => 'per_seat',
            'next_tier_id' => 0,
            'next_tier_form_page_id' => 0,
            'product_data' => [
                [
                    'product_id' => 1001,
                    'variation_id' => 0,
                    'max_seats' => -1,
                ]
            ],
            'approval_required' => 0,
            'grant_owner_assignment' => 0,
            'approval_email_recipient' => '',
            'approval_callout_data' => [
                'locales' => [
                    'en' => [
                        'callout_header' => 'Approval Needed',
                        'callout_content' => 'Approval is required for this tier.',
                        'callout_button_label' => 'Request Approval',
                    ]
                ]
            ],
        ];
        $tier_data = array_merge($default_tier_data, $tier_data);
        $meta_input = [ 'tier_data' => $tier_data ];
        $defaults = [
            'post_title' => $tier_data['mdp_tier_name'],
            'post_status' => 'publish',
            'meta_input' => $meta_input,
            'post_type' => 'wicket_mship_tier',
        ];
        $args = array_merge($defaults, $post_args);
        return $this->create($args);
    }
}
