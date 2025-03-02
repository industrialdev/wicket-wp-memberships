<?php
namespace Wicket_Memberships;

use Wicket_Memberships\Helper;
use WP_Error;

defined( 'ABSPATH' ) || exit;

class Membership_Post_Types {

  private $membership_cpt_slug = '';
  private $membership_config_cpt_slug = '';
  private $membership_tier_cpt_slug = '';


  public function __construct() {
    $this->membership_cpt_slug = Helper::get_membership_cpt_slug();
    $this->membership_config_cpt_slug = Helper::get_membership_config_cpt_slug();
    $this->membership_tier_cpt_slug = Helper::get_membership_tier_cpt_slug();
    add_action('init', [ $this, 'register_membership_post_type' ]);
    add_action('init', [ $this, 'register_membership_config_post_type' ]);
    add_action('init', [ $this, 'register_membership_tier_post_type' ]);

    // Register the fields to the REST API and validate the data
    add_action('rest_api_init', [ $this, 'register_membership_config_cpt_fields' ]);
    add_action('rest_api_init', [ $this, 'register_membership_tier_cpt_fields' ]);
  }

  /**
   * Register and validate rest fields for the membership config post type
   */
  public function register_membership_config_cpt_fields() {
    // Renewal Window Data
    $field = 'renewal_window_data';

    register_rest_field(
      $this->membership_config_cpt_slug,
      $field,
      array(
        'get_callback'    => function ( $object ) use ( $field ) {
          return get_post_meta( $object['id'], $field, true );
        },
        'update_callback' => function ( $value, $object ) use ( $field ) {
          update_post_meta( $object->ID, $field, $value );
        },
        'schema'          => array(
          'type'        => 'object',
          'description' => 'Renewal Window Data',
          'arg_options' => [
            'validate_callback' => function( $value ) {
              $errors = new WP_Error();

              if ( ! is_array( $value ) ) {
                $errors->add( 'rest_invalid_param', __( 'The renewal window data must be an object.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( intval( $value['days_count'] ) < 1 ) {
                $errors->add( 'rest_invalid_param_days_count', __( 'The renewal window days count must be greater than 0.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              // Validate locales to be not empty
              $language_codes = Helper::get_wp_languages_iso();
              $locales_valid = true;
              foreach ( $language_codes as $language_code ) {
                if ( empty( $value['locales'][ $language_code ]['callout_header'] ) ) {
                  $locales_valid = false;
                }

                if ( empty( $value['locales'][ $language_code ]['callout_content'] ) ) {
                  $locales_valid = false;
                }

                if ( empty( $value['locales'][ $language_code ]['callout_button_label'] ) ) {
                  $locales_valid = false;
                }
              }

              if ( $locales_valid === false ) {
                $errors->add( 'rest_invalid_param_locales', __( 'The renewal window callout data must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( $errors->has_errors() ) {
                return $errors;
              }

              return true;
            },
          ],
          'properties'  => array(
            'days_count'           => array(
              'type'        => 'integer',
              'description' => 'The number of days before the end of the membership that the renewal window starts',
            ),
            'locales' => array(
              'type'        => 'object',
              'description' => 'Localized renewal window callout data',
              'properties'  => array(
                'type'        => 'object',
                'properties'  => array(
                  'callout_header'       => array(
                    'type'        => 'string',
                    'description' => 'The localized header for the renewal window callout',
                  ),
                  'callout_content'      => array(
                    'type'        => 'string',
                    'description' => 'The localized content for the renewal window callout',
                  ),
                  'callout_button_label' => array(
                    'type'        => 'string',
                    'description' => 'The localized label for the renewal window callout button',
                  ),
                ),
              ),
            ),
          ),
        ),
      )
    );

    // Late Fee Window Data
    $field = 'late_fee_window_data';

    register_rest_field(
      $this->membership_config_cpt_slug,
      $field,
      array(
        'get_callback'    => function ( $object ) use ( $field ) {
          return get_post_meta( $object['id'], $field, true );
        },
        'update_callback' => function ( $value, $object ) use ( $field ) {
          update_post_meta( $object->ID, $field, $value );
        },
        'schema'          => array(
          'type'        => 'object',
          'description' => 'Late Fee Window Data',
          'arg_options' => [
            'validate_callback' => function( $value ) {
              $errors = new WP_Error();

              if ( ! is_array( $value ) ) {
                $errors->add( 'rest_invalid_param', __( 'The grace period window data must be an object.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( ! isset( $value['days_count'] ) || intval( $value['days_count'] ) < 0 ) {
                $errors->add( 'rest_invalid_param_days_count', __( 'The grace period window days count must be equal or greater than 0.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( isset( $value['product_id'] ) && intval( $value['product_id'] ) > 0 ) {
                $wc_product = new \WC_Product( $value['product_id'] );
                if ( $wc_product->exists() === false ) {
                  $errors->add( 'rest_invalid_param_product_id', __( 'The grace period window product must be a valid product.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }
              }

              // Validate locales to be not empty
              $language_codes = Helper::get_wp_languages_iso();
              $locales_valid = true;
              foreach ( $language_codes as $language_code ) {
                if ( empty( $value['locales'][ $language_code ]['callout_header'] ) ) {
                  $locales_valid = false;
                }

                if ( empty( $value['locales'][ $language_code ]['callout_content'] ) ) {
                  $locales_valid = false;
                }

                if ( empty( $value['locales'][ $language_code ]['callout_button_label'] ) ) {
                  $locales_valid = false;
                }
              }

              if ( $locales_valid === false ) {
                $errors->add( 'rest_invalid_param_locales', __( 'The grace period window callout data must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( $errors->has_errors() ) {
                return $errors;
              }

              return true;
            },
          ],
          'properties'  => array(
            'days_count' => array(
              'type' => 'integer',
            ),
            'product_id' => array(
              'type' => 'integer',
            ),
            'locales' => array(
              'type'        => 'object',
              'description' => 'Localized late fee window callout data',
              'properties'  => array(
                'type'        => 'object',
                'properties'  => array(
                  'callout_header'       => array(
                    'type'        => 'string',
                    'description' => 'The localized header for the late fee window callout',
                  ),
                  'callout_content'      => array(
                    'type'        => 'string',
                    'description' => 'The localized content for the late fee window callout',
                  ),
                  'callout_button_label' => array(
                    'type'        => 'string',
                    'description' => 'The localized label for the late fee window callout button',
                  ),
                ),
              ),
            ),
          ),
        ),
      )
    );

    // Cycle Data
    $field = 'cycle_data';

    register_rest_field(
      $this->membership_config_cpt_slug,
      $field,
      array(
        'get_callback'    => function ( $object ) use ( $field ) {
          return get_post_meta( $object['id'], $field, true );
        },
        'update_callback' => function ( $value, $object ) use ( $field ) {
          update_post_meta( $object->ID, $field, $value );
        },
        'schema'          => array(
          'type'        => 'object',
          'description' => 'Late Fee Window Data',
          'arg_options' => [
            'validate_callback' => function( $value ) {
              $errors = new WP_Error();

              if ( ! is_array( $value ) ) {
                $errors->add( 'rest_invalid_param', __( 'The cycle data must be an object.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( ! in_array( $value['cycle_type'], [ 'calendar', 'anniversary' ] ) ) {
                $errors->add( 'rest_invalid_param_cycle_type', __( 'The cycle type must be either calendar or anniversary.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( $value['cycle_type'] === 'anniversary' ) {
                if ( intval( $value['anniversary_data']['period_count'] ) < 1 ) {
                  $errors->add( 'rest_invalid_param_anniversary_period_count', __( 'The anniversary period count must be greater than 0.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }

                if ( ! in_array( $value['anniversary_data']['period_type'], [ 'year', 'month', 'week' ] ) ) {
                  $errors->add( 'rest_invalid_param_anniversary_period_type', __( 'The anniversary period type must be year, month, or week.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }

                if ( $value['anniversary_data']['align_end_dates_enabled'] === true ) {
                  if ( ! in_array( $value['anniversary_data']['align_end_dates_type'], [ 'first-day-of-month', '15th-of-month', 'last-day-of-month' ] ) ) {
                    $errors->add( 'rest_invalid_param_anniversary_align_end_dates_type', __( 'The anniversary align end dates type must be first-day-of-month, 15th-of-month, or last-day-of-month.', 'wicket-memberships' ), array( 'status' => 400 ) );
                  }
                }
              }

              if ( $value['cycle_type'] === 'calendar' ) {

                if ( ! is_array( $value['calendar_items'] ) ) {
                  $errors->add( 'rest_invalid_param_calendar_items', __( 'The calendar items must be an array.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }

                if ( count( $value['calendar_items'] ) < 1 ) {
                  $errors->add( 'rest_invalid_param_calendar_items', __( 'At least one season item must be defined.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }

                $active_seasons = array_filter( $value['calendar_items'], function( $item ) {
                  return $item['active'] === true;
                });

                if ( count( $active_seasons ) < 1 ) {
                  $errors->add( 'rest_invalid_param_calendar_active_seasons', __( 'At least one season must be active.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }

                // Validate each season item
                foreach ( $value['calendar_items'] as $item ) {
                  if ( empty( $item['season_name'] ) ) {
                    $errors->add( 'rest_invalid_param_calendar_season_name', __( 'The calendar season name must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
                  }

                  if ( empty( $item['start_date'] ) ) {
                    $errors->add( 'rest_invalid_param_calendar_start_date', __( 'The calendar start date must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
                  }

                  if ( empty( $item['end_date'] ) ) {
                    $errors->add( 'rest_invalid_param_calendar_end_date', __( 'The calendar end date must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
                  }

                  if ( strtotime( $item['start_date'] ) > strtotime( $item['end_date'] ) ) {
                    $errors->add( 'rest_invalid_param_calendar_dates', __( 'The season start date must be before the end date.', 'wicket-memberships' ), array( 'status' => 400 ) );
                  }
                }

                // Validate that the season start right after the previous season
                $seasons = $value['calendar_items'];
                foreach ( $seasons as $i => $season) {
                  // if next season does not exist, break
                  if ( $i + 1 >= count( $seasons ) ) {
                    break;
                  }

                  $next_season_start = strtotime( $seasons[ $i + 1 ]['start_date'] );
                  $next_correct_season_start = strtotime( $season['end_date'] . ' +1 day' );

                  if ( $next_season_start !== $next_correct_season_start ) {
                    $errors->add( 'rest_invalid_param_calendar_dates', __( 'The season dates must be consecutive.', 'wicket-memberships' ), array( 'status' => 400 ) );
                  }
                }

                // Validate that the season dates do not overlap
                $season_overlaps = false;
                foreach ( $value['calendar_items'] as $key => $season ) {
                  $season_start = strtotime( $season['start_date'] );
                  $season_end = strtotime( $season['end_date'] );

                  foreach ( $value['calendar_items'] as $inner_key => $inner_season ) {
                    if ( $key === $inner_key ) {
                      continue;
                    }

                    $inner_season_start = strtotime( $inner_season['start_date'] );
                    $inner_season_end = strtotime( $inner_season['end_date'] );

                    if ( $season_start >= $inner_season_start && $season_start <= $inner_season_end ) {
                      $season_overlaps = true;
                    }

                    if ( $season_end >= $inner_season_start && $season_end <= $inner_season_end ) {
                      $season_overlaps = true;
                    }
                  }
                }

                if ( $season_overlaps ) {
                  $errors->add( 'rest_invalid_param_calendar_dates', __( 'The season dates must not overlap.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }

              }

              if ( $errors->has_errors() ) {
                return $errors;
              }

              return true;
            },
          ],
          'properties'  => array(
            'cycle_type' => array(
              'type' => 'string', // calendar/anniversary
            ),
            'anniversary_data' => array(
              'type' => 'object',
              'properties' => array(
                'period_count' => array(
                  'type' => 'integer',
                ),
                'period_type' => array(
                  'type' => 'string', // year/month/week
                ),
                'align_end_dates_enabled' => array(
                  'type' => 'boolean',
                ),
                'align_end_dates_type' => array(
                  'type' => 'string', // first-day-of-month | 15th-of-month | last-day-of-month
                ),
              ),
            ),
            'calendar_items' => array(
              'type' => 'array',
              'properties' => array(
                'season_name' => array(
                  'type' => 'string',
                ),
                'active' => array(
                  'type' => 'boolean',
                ),
                'start_date' => array(
                  'type' => 'string',
                ),
                'end_date' => array(
                  'type' => 'string',
                ),
              ),
            ),
          ),
        ),
      )
    );
  }

  /**
   * Register rest fields for the membership tier post type
   */
  public function register_membership_tier_cpt_fields() {
    // Tier Data
    $field = 'tier_data';

    register_rest_field(
      $this->membership_tier_cpt_slug,
      $field,
      array(
        'get_callback'    => function ( $object ) use ( $field ) {
          return get_post_meta( $object['id'], $field, true );
        },
        'update_callback' => function ( $value, $object ) use ( $field ) {
          update_post_meta( $object->ID, $field, $value );
        },
        'schema'          => array(
          'type'        => 'object',
          'description' => 'Tier Additional Data',
          'arg_options' => [
            'validate_callback' => function( $value, $request, $key ) {
              $errors = new WP_Error();

              $tier_post_id = $request->get_param('id');

              if ( ! is_array( $value ) ) {
                $errors->add( 'rest_invalid_param', __( 'The tier data must be an object.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( is_bool( $value['approval_required'] ) === false ) {
                $errors->add( 'rest_invalid_param_approval_required', __( 'The approval required value must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              // if approval required, then approval email recipient must not be empty and must be a valid email
              if ( $value['approval_required'] === true ) {
                if ( empty( $value['approval_email_recipient'] ) ) {
                  $errors->add( 'rest_invalid_param_approval_email_recipient', __( 'The approval email recipient must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }

                if ( ! is_email( $value['approval_email_recipient'] ) ) {
                  $errors->add( 'rest_invalid_param_approval_email_recipient', __( 'The approval email recipient must be a valid email.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }

                // validate locales to be not empty
                $locales_valid = true;
                $language_codes = Helper::get_wp_languages_iso();
                foreach ( $language_codes as $language_code ) {
                  if ( empty( $value['approval_callout_data']['locales'][ $language_code ]['callout_header'] ) ) {
                    $locales_valid = false;
                  }

                  if ( empty( $value['approval_callout_data']['locales'][ $language_code ]['callout_content'] ) ) {
                    $locales_valid = false;
                  }

                  if ( empty( $value['approval_callout_data']['locales'][ $language_code ]['callout_button_label'] ) ) {
                    $locales_valid = false;
                  }
                }

                if ( $locales_valid === false ) {
                  $errors->add( 'rest_invalid_param_approval_callout_data', __( 'The approval callout data must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }
              }

              if ( empty( $value['mdp_tier_name'] ) ) {
                $errors->add( 'rest_invalid_param_mdp_tier_name', __( 'The MDP Tier Name must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( empty( $value['mdp_tier_uuid'] ) ) {
                $errors->add( 'rest_invalid_param_mdp_tier_uuid', __( 'The MDP Tier UUID must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( empty( $value['renewal_type'] ) ) {
                $errors->add( 'rest_invalid_param_renewal_type', __( 'The Renewal Type must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( $value['renewal_type'] === 'current_tier' ) {
                if ( ! empty( $value['next_tier_id'] ) ) {
                  $errors->add( 'rest_invalid_param_next_tier_id', __( 'The Next Tier ID must be empty for current tier renewal type.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }
              }

              if ( $value['renewal_type'] === 'current_tier' || $value['renewal_type'] === 'sequential_logic' ) {
                if ( ! empty( $value['next_tier_form_page_id'] ) ) {
                  $errors->add( 'rest_invalid_param_next_tier_form_page_id', __( 'The Next Tier Form Page ID must be empty for current tier or sequential logic renewal type.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }
              }

              if ( $value['renewal_type'] === 'sequential_logic' ) {
                if ( empty( $value['next_tier_id'] ) ) {
                  $errors->add( 'rest_invalid_param_next_tier_id', __( 'The Next Tier ID must not be empty for sequential logic renewal type.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }
              }

              if ( $value['renewal_type'] === 'form_flow' ) {
                if ( empty( $value['next_tier_form_page_id'] ) ) {
                  $errors->add( 'rest_invalid_param_next_tier_form_page_id', __( 'The Next Tier Form Page ID must not be empty for form flow renewal type.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }

                if ( ! empty( $value['next_tier_id'] ) ) {
                  $errors->add( 'rest_invalid_param_next_tier_id', __( 'The Next Tier ID must be empty for form flow renewal type.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }
              }

              // if ( empty( $value['next_tier_id'] ) ) {
              //   $errors->add( 'rest_invalid_param_next_tier_id', __( 'The Next Tier ID must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
              // }

              if ( empty( $value['config_id'] ) ) {
                $errors->add( 'rest_invalid_param_config_id', __( 'The Membership Config Post ID must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              // if config_id is not a valid Config Post ID
              $config_post = get_post( $value['config_id'] );

              if ( ! $config_post || $config_post->post_type !== $this->membership_config_cpt_slug ) {
                $errors->add( 'rest_invalid_param_config_id', __( 'The Membership Config Post ID must be a valid post ID.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              // only allow 'individual' or 'organization' type
              if ( ! in_array( $value['type'], [ 'individual', 'organization' ] ) ) {
                $errors->add( 'rest_invalid_param_type', __( 'The tier type must be either individual or organization.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              // at least one product is required for all tier types
              if ( count( $value['product_data'] ) < 1 ) {
                $errors->add( 'rest_invalid_param_product_data', __( 'At least one product is required.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( count( $value['product_data'] ) > 0 ) {
                foreach ( $value['product_data'] as $product ) {
                  $wc_product = wc_get_product( $product['product_id'] );

                  // each product object must have a product_id, variation_id, and max_seats
                  foreach ( [ 'product_id', 'variation_id', 'max_seats' ] as $key ) {
                    if ( ! array_key_exists( $key, $product ) ) {
                      $errors->add( 'rest_invalid_param_product_data', __( 'The product data must have a ' . $key . ' key.', 'wicket-memberships' ), array( 'status' => 400 ) );
                    }
                  }

                  // each product object must be "variable-subscription" or "subscription"
                  if ( ! $wc_product->is_type( 'variable-subscription' ) && ! $wc_product->is_type( 'subscription' ) ) {
                    $errors->add( 'rest_invalid_param_product_data', __( 'All products must be either variable-subscription or subscription.', 'wicket-memberships' ), array( 'status' => 400 ) );
                  }

                  // dissalow products with max_seats less than -1
                  if ( intval( $product['max_seats'] ) < -1 ) {
                    $errors->add( 'rest_invalid_param_product_data', __( 'Max seats must be greater than or equal to -1.', 'wicket-memberships' ), array( 'status' => 400 ) );
                  }

                  // product_id must be unique for all tiers
                  if ( $tier_post_id === null ) {
                    if ( Membership_Tier::get_tier_by_product_id( $product['product_id'] ) !== false ) {
                      //$errors->add( 'rest_invalid_param_product_data', __( 'Product IDs must be unique for all tiers.', 'wicket-memberships' ), array( 'status' => 400 ) );
                    }
                  } else {
                    // if we are editing a tier, then we need to exclude the current tier product id from the list
                    $tier = new Membership_Tier( $tier_post_id );
                    $tier_product_ids = $tier->get_product_ids();

                    if ( Membership_Tier::get_tier_by_product_id( $product['product_id'] ) !== false && ! in_array( $product['product_id'], $tier_product_ids ) ) {
                      //$errors->add( 'rest_invalid_param_product_data', __( 'Product IDs must be unique for all tiers.', 'wicket-memberships' ), array( 'status' => 400 ) );
                    }
                  }

                  // return error if wc product is variable-subscription and variation_id is not set
                  if ( $wc_product->is_type( 'variable-subscription' ) && empty( $product['variation_id'] ) ) {
                    $errors->add( 'rest_invalid_param_product_data', __( 'Variation ID must be set for variable subscription products.', 'wicket-memberships' ), array( 'status' => 400 ) );
                  }

                }

                // Validate if all product variations are unique
                $product_variations = array_map( function( $product ) {
                  return $product['variation_id'];
                }, $value['product_data'] );

                // Remove empty values
                $product_variations = array_filter( $product_variations );

                if ( count( $product_variations ) !== count( array_unique( $product_variations ) ) ) {
                  $errors->add( 'rest_invalid_param_product_data', __( 'Variation IDs must be unique for all products.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }
              }

              // if individual type, then max_seats must be -1 for all products
              if ( $value['type'] === 'individual' ) {
                foreach ( $value['product_data'] as $product ) {
                  if ( intval( $product['max_seats'] ) !== -1 ) {
                    $errors->add( 'rest_invalid_param_product_data', __( 'Max seats must be -1 for individual tier types.', 'wicket-memberships' ), array( 'status' => 400 ) );
                  }
                }
              }

              // if type is organization and seat type is per_seat, max 1 product is allowed
              if ( $value['type'] === 'organization' && $value['seat_type'] === 'per_seat' && count( $value['product_data'] ) > 1 ) {
                $errors->add( 'rest_invalid_param_product_data', __( 'Only one product is allowed for organization tier types.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              // if type is organization and seat type is per_seat, max_seats must be -1
              if ( $value['type'] === 'organization' && $value['seat_type'] === 'per_seat' && isset( $value['product_data'][0] ) ) {
                if ( intval( $value['product_data'][0]['max_seats'] ) !== -1 ) {
                  $errors->add( 'rest_invalid_param_product_data', __( 'Max seats must be -1 for organization tier types with "per_seat" type.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }
              }

              // if type is organization and seat type is per_range_of_seats, product id cannot be same
              if ( $value['type'] === 'organization' && $value['seat_type'] === 'per_range_of_seats' && count( $value['product_data'] ) > 1 ) {
                $product_ids = array_map( function( $product ) {
                  return $product['product_id'];
                }, $value['product_data'] );

                if ( count( $product_ids ) !== count( array_unique( $product_ids ) ) ) {
                  //$errors->add( 'rest_invalid_param_product_data', __( 'Product IDs must be unique for organization tier types with "per_range_of_seats" type.', 'wicket-memberships' ), array( 'status' => 400 ) );
                }
              }

              if ( $errors->has_errors() ) {
                return $errors;
              }

              return true;
            },
          ],
          'properties'  => array(
            'approval_required' => array(
              'type'        => 'boolean',
              'description' => 'Approval Required',
            ),
            'approval_email_recipient' => array(
              'type'        => 'strong',
              'description' => 'Approval Email Recipient',
            ),
            'mdp_tier_name' => array(
              'type'        => 'string',
              'description' => 'MPD Tier Name',
            ),
            'mdp_tier_uuid' => array(
              'type'        => 'string',
              'description' => 'MPD Tier UUID',
            ),
            'next_tier_id' => array(
              'type'        => 'integer',
              'description' => 'Next Tier ID',
            ),
            'next_tier_form_page_id' => array(
              'type'        => 'integer',
              'description' => 'Next Tier Form Page ID',
            ),
            'config_id' => array(
              'type'        => 'integer',
              'description' => 'Membership Config Post ID',
            ),
            'renewal_type' => array(
              'type'        => 'string',
              'description' => 'Renewal Type',
            ),
            'type' => array(
              'type'        => 'string',
              'description' => 'Tier Type',
            ),
            'seat_type' => array(
              'type'        => 'string',
              'description' => 'Seat Type',
            ),
            'product_data' => array(
              'type'        => 'array',
              'description' => 'Product Data',
              'properties' => array(
                'product_id' => array(
                  'type' => 'integer',
                ),
                'variation_id' => array(
                  'type' => 'integer',
                ),
                'max_seats' => array(
                  'type' => 'integer',
                ),
              ),
            ),
            'approval_callout_data' => array(
              'type'        => 'object',
              'description' => 'Approval Callout Data',
              'properties'  => array(
                'locales' => array(
                  'type'        => 'object',
                  'description' => 'Localized approval callout data',
                  'properties'  => array(
                    'type'        => 'object',
                    'properties'  => array(
                      'callout_header'       => array(
                        'type'        => 'string',
                        'description' => 'The localized header for the approval callout',
                      ),
                      'callout_content'      => array(
                        'type'        => 'string',
                        'description' => 'The localized content for the approval callout',
                      ),
                      'callout_button_label' => array(
                        'type'        => 'string',
                        'description' => 'The localized label for the approval callout button',
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      )
    );

  }

  /**
   * Create the membership post type
   */
  public function register_membership_post_type() {
    $supports = array(
      'custom-fields',
    );

    $labels = array(
      'name' => _x('Memberships', 'plural', 'wicket-memberships' ),
    );

    $membership_menu_item = false;
    if( ! empty( $_ENV['WICKET_MEMBERSHIPS_DEBUG_MODE'] ) ) {
      $membership_menu_item = WICKET_MEMBERSHIP_PLUGIN_SLUG;
    }

    $args = array(
      'supports' => $supports,
      'labels' => $labels,
      'description'        => __( 'Records of the Wicket Memberships', 'wicket-memberships' ),
      'public'             => false,
      'publicly_queryable' => false,
      'show_ui'            => true,
      'show_in_menu'       => $membership_menu_item,
      'query_var'          => true,
      'capability_type'    => 'post',
      'map_meta_cap'       => true, //permissions same as 'posts'
      'has_archive'        => true,
      'hierarchical'       => false,
      'menu_position'      => null,
      'show_in_rest'       => true,
    );

    register_post_type($this->membership_cpt_slug, $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'The status of this membership record', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      =>  true,
    );

    register_post_meta($this->membership_cpt_slug, 'membership_status', $args);

    $args = array(
      'type'              => 'integer',
      'description'       => __( 'The UserID owns this membership record', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'user_id', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'The UUID in wicket of this membership record', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );
    register_post_meta($this->membership_cpt_slug, 'membership_wicket_uuid', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'The start date membership.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'membership_starts_at', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'The end date membership.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'membership_ends_at', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'The expiry date of the membership in wordpress.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'membership_expires_at', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'The early renew date of the membership in wordpress.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'membership_early_renew_at', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'Person or Org membership.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'membership_type', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'Org Name.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'org_name', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'Org UUID.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'org_uuid', $args);

    $args = array(
      'type'              => 'integer',
      'description'       => __( 'Org max seats.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'org_seats', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'MDP Membership ID.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'membership_uuid', $args);

    /** Tier Info */

    $args = array(
      'type'              => 'string',
      'description'       => __( 'MDP Tier UUID.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'membership_tier_uuid', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'Tier Name.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'membership_tier_name', $args);

    $args = array(
      'type'              => 'integer',
      'description'       => __( 'Sequential Tier WP Post_ID.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'membership_next_tier_id', $args);

    $args = array(
      'type'              => 'integer',
      'description'       => __( 'Renewal Form WP Page_ID.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'membership_next_form_id', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'Order ID.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'membership_parent_order_id', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'Subscription ID.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'membership_subscription_id', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'Product ID.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'membership_product_id', $args);
  }

  /**
   * Create the Wicket Membership Config post type
   */
  public function register_membership_config_post_type() {
    $labels = array(
      'name'                  => _x( 'Membership Configs', 'Post Type General Name', 'wicket-memberships' ),
      'singular_name'         => _x( 'Membership Config', 'Post Type Singular Name', 'wicket-memberships' ),
      'menu_name'             => __( 'Post Types', 'wicket-memberships' ),
      'name_admin_bar'        => __( 'Post Type', 'wicket-memberships' ),
      'archives'              => __( 'Item Archives', 'wicket-memberships' ),
      'attributes'            => __( 'Item Attributes', 'wicket-memberships' ),
      'parent_item_colon'     => __( 'Parent Item:', 'wicket-memberships' ),
      'all_items'             => __( 'Membership Configs', 'wicket-memberships' ),
      'add_new_item'          => __( 'Add New Item', 'wicket-memberships' ),
      'add_new'               => __( 'Add New', 'wicket-memberships' ),
      'new_item'              => __( 'New Item', 'wicket-memberships' ),
      'edit_item'             => __( 'Edit Item', 'wicket-memberships' ),
      'update_item'           => __( 'Update Item', 'wicket-memberships' ),
      'view_item'             => __( 'View Item', 'wicket-memberships' ),
      'view_items'            => __( 'View Items', 'wicket-memberships' ),
      'search_items'          => __( 'Search Item', 'wicket-memberships' ),
      'not_found'             => __( 'Not found', 'wicket-memberships' ),
      'not_found_in_trash'    => __( 'Not found in Trash', 'wicket-memberships' ),
      'featured_image'        => __( 'Featured Image', 'wicket-memberships' ),
      'set_featured_image'    => __( 'Set featured image', 'wicket-memberships' ),
      'remove_featured_image' => __( 'Remove featured image', 'wicket-memberships' ),
      'use_featured_image'    => __( 'Use as featured image', 'wicket-memberships' ),
      'insert_into_item'      => __( 'Insert into item', 'wicket-memberships' ),
      'uploaded_to_this_item' => __( 'Uploaded to this item', 'wicket-memberships' ),
      'items_list'            => __( 'Items list', 'wicket-memberships' ),
      'items_list_navigation' => __( 'Items list navigation', 'wicket-memberships' ),
      'filter_items_list'     => __( 'Filter items list', 'wicket-memberships' ),
    );

    $args = array(
      'label'                 => __( 'Membership Config', 'wicket-memberships' ),
      'description'           => __( 'Membership Configurations are defined here', 'wicket-memberships' ),
      'labels'                => $labels,
      'supports'              => array( 'title', 'custom-fields' ),
      'hierarchical'          => false,
      'public'                => false,
      'show_ui'               => true,
      'show_in_menu'          => WICKET_MEMBERSHIP_PLUGIN_SLUG,
      'menu_position'         => 5,
      'show_in_admin_bar'     => true,
      'show_in_nav_menus'     => true,
      'can_export'            => true,
      'has_archive'           => true,
      'exclude_from_search'   => false,
      'publicly_queryable'    => false,
      'capability_type'       => 'page',
      'show_in_rest'          => true,
    );

    register_post_type( $this->membership_config_cpt_slug, $args );
  }

    /**
   * Create the Wicket Membership Tier post type
   */
  public function register_membership_tier_post_type() {
    $labels = array(
      'name'                  => _x( 'Membership Tiers', 'Post Type General Name', 'wicket-memberships' ),
      'singular_name'         => _x( 'Membership Tiers', 'Post Type Singular Name', 'wicket-memberships' ),
      'menu_name'             => __( 'Post Types', 'wicket-memberships' ),
      'name_admin_bar'        => __( 'Post Type', 'wicket-memberships' ),
      'archives'              => __( 'Item Archives', 'wicket-memberships' ),
      'attributes'            => __( 'Item Attributes', 'wicket-memberships' ),
      'parent_item_colon'     => __( 'Parent Item:', 'wicket-memberships' ),
      'all_items'             => __( 'Membership Tiers', 'wicket-memberships' ),
      'add_new_item'          => __( 'Add New Item', 'wicket-memberships' ),
      'add_new'               => __( 'Add New', 'wicket-memberships' ),
      'new_item'              => __( 'New Item', 'wicket-memberships' ),
      'edit_item'             => __( 'Edit Item', 'wicket-memberships' ),
      'update_item'           => __( 'Update Item', 'wicket-memberships' ),
      'view_item'             => __( 'View Item', 'wicket-memberships' ),
      'view_items'            => __( 'View Items', 'wicket-memberships' ),
      'search_items'          => __( 'Search Item', 'wicket-memberships' ),
      'not_found'             => __( 'Not found', 'wicket-memberships' ),
      'not_found_in_trash'    => __( 'Not found in Trash', 'wicket-memberships' ),
      'featured_image'        => __( 'Featured Image', 'wicket-memberships' ),
      'set_featured_image'    => __( 'Set featured image', 'wicket-memberships' ),
      'remove_featured_image' => __( 'Remove featured image', 'wicket-memberships' ),
      'use_featured_image'    => __( 'Use as featured image', 'wicket-memberships' ),
      'insert_into_item'      => __( 'Insert into item', 'wicket-memberships' ),
      'uploaded_to_this_item' => __( 'Uploaded to this item', 'wicket-memberships' ),
      'items_list'            => __( 'Items list', 'wicket-memberships' ),
      'items_list_navigation' => __( 'Items list navigation', 'wicket-memberships' ),
      'filter_items_list'     => __( 'Filter items list', 'wicket-memberships' ),
    );

    $args = array(
      'label'                 => __( 'Membership Tiers', 'wicket-memberships' ),
      'description'           => __( 'Membership Tiers are defined here', 'wicket-memberships' ),
      'labels'                => $labels,
      'supports'              => array( 'title', 'custom-fields' ),
      'hierarchical'          => false,
      'public'                => false,
      'show_ui'               => true,
      'show_in_menu'          => WICKET_MEMBERSHIP_PLUGIN_SLUG,
      'menu_position'         => 5,
      'show_in_admin_bar'     => true,
      'show_in_nav_menus'     => true,
      'can_export'            => true,
      'has_archive'           => true,
      'exclude_from_search'   => false,
      'publicly_queryable'    => false,
      'capability_type'       => 'page',
      'show_in_rest'          => true
    );

    register_post_type( $this->membership_tier_cpt_slug, $args );
  }

}