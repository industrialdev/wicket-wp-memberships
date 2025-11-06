<?php
// tests/factories/class-wp-unittest-factory-for-wicket-mship-config.php


class WP_UnitTest_Factory_For_Wicket_Mship_Config extends WP_UnitTest_Factory_For_Post {
    public function __construct( $factory = null ) {
        parent::__construct( $factory, 'wicket_mship_config' );
    }

    /**
     * Create an anniversary-based config with custom early renewal and grace period.
     *
     * @param array $args Override post args.
     * @param int $early_renewal_days Number of days before end date for early renewal.
     * @param int $grace_period_days Number of days after end date for grace period.
     * @return int Post ID of created config.
     */
    public function create_anniversary_config($early_renewal_days = 30, $grace_period_days = 10, $args = []) {
        $renewal_window_data = [
            'days_count' => $early_renewal_days,
            'locales' => [
                'en' => [
                    'callout_header' => 'Anniversary Renewal (Factory)',
                    'callout_content' => 'Renewal window: ' . $early_renewal_days . ' days before end. Created by anniversary config factory.',
                    'callout_button_label' => 'Renew Anniversary',
                ],
            ],
        ];
        $late_fee_window_data = [
            'days_count' => $grace_period_days,
            'product_id' => 123,
            'locales' => [
                'en' => [
                    'callout_header' => 'Anniversary Grace Period (Factory)',
                    'callout_content' => 'Grace period: ' . $grace_period_days . ' days after end. Created by anniversary config factory.',
                    'callout_button_label' => 'Pay Grace Fee',
                ],
            ],
        ];
        $cycle_data = [
            'cycle_type' => 'anniversary',
            'anniversary_data' => [
                'period_count' => 1,
                'period_type' => 'year',
                'align_end_dates_enabled' => true,
                'align_end_dates_type' => 'last-day-of-month',
            ],
        ];
        $meta_input = [
            'renewal_window_data' => $renewal_window_data,
            'late_fee_window_data' => $late_fee_window_data,
            'cycle_data' => $cycle_data,
            'multi_tier_renewal' => 1,
        ];
        $defaults = [
            'post_title' => 'Anniversary Config (Factory)',
            'post_status' => 'publish',
            'meta_input' => $meta_input,
        ];
        $args = array_merge($defaults, $args);
        return $this->create($args);
    }

        /**
     * Create a calendar-based config with custom early renewal and grace period.
     *
     * @param int $early_renewal_days Number of days before end date for early renewal.
     * @param int $grace_period_days Number of days after end date for grace period.
     * @param array $args Override post args.
     * @return int Post ID of created config.
     */
    public function create_calendar_config($early_renewal_days = 30, $grace_period_days = 10, $args = []) {
        $renewal_window_data = [
            'days_count' => $early_renewal_days,
            'locales' => [
                'en' => [
                    'callout_header' => 'Calendar Renewal (Factory)',
                    'callout_content' => 'Renewal window: ' . $early_renewal_days . ' days before end. Created by calendar config factory.',
                    'callout_button_label' => 'Renew Calendar',
                ],
            ],
        ];
        $late_fee_window_data = [
            'days_count' => $grace_period_days,
            'product_id' => 123,
            'locales' => [
                'en' => [
                    'callout_header' => 'Calendar Grace Period (Factory)',
                    'callout_content' => 'Grace period: ' . $grace_period_days . ' days after end. Created by calendar config factory.',
                    'callout_button_label' => 'Pay Grace Fee',
                ],
            ],
        ];
        $cycle_data = [
            'cycle_type' => 'calendar',
            'calendar_items' => [
                [
                    'season_name' => 'Spring',
                    'active' => true,
                    'start_date' => '2025-03-01',
                    'end_date' => '2025-05-31',
                ],
                [
                    'season_name' => 'Summer',
                    'active' => false,
                    'start_date' => '2025-06-01',
                    'end_date' => '2025-08-31',
                ],
            ],
        ];
        $meta_input = [
            'renewal_window_data' => $renewal_window_data,
            'late_fee_window_data' => $late_fee_window_data,
            'cycle_data' => $cycle_data,
            'multi_tier_renewal' => 1,
        ];
        $defaults = [
            'post_title' => 'Calendar Config (Factory)',
            'post_status' => 'publish',
            'meta_input' => $meta_input,
        ];
        $args = array_merge($defaults, $args);
        return $this->create($args);
    }
}
