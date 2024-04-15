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

              if ( empty( $value['callout_header'] ) ) {
                $errors->add( 'rest_invalid_param_callout_header', __( 'The renewal window callout header must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( empty( $value['callout_content'] ) ) {
                $errors->add( 'rest_invalid_param_callout_content', __( 'The renewal window callout content must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( empty( $value['callout_button_label'] ) ) {
                $errors->add( 'rest_invalid_param_callout_button_label', __( 'The renewal window callout button label must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
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
            'callout_header'       => array(
              'type'        => 'string',
              'description' => 'The header for the renewal window callout',
            ),
            'callout_content'      => array(
              'type'        => 'string',
              'description' => 'The content for the renewal window callout',
            ),
            'callout_button_label' => array(
              'type'        => 'string',
              'description' => 'The label for the renewal window callout button',
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
                $errors->add( 'rest_invalid_param', __( 'The late fee window data must be an object.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( intval( $value['days_count'] ) < 1 ) {
                $errors->add( 'rest_invalid_param_days_count', __( 'The late fee window days count must be greater than 0.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              $wc_product = new \WC_Product( $value['product_id'] );
              if ( $wc_product->exists() === false ) {
                $errors->add( 'rest_invalid_param_product_id', __( 'The late fee window product must be a valid product.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( empty( $value['callout_header'] ) ) {
                $errors->add( 'rest_invalid_param_callout_header', __( 'The late fee window callout header must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( empty( $value['callout_content'] ) ) {
                $errors->add( 'rest_invalid_param_callout_content', __( 'The late fee window callout content must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( empty( $value['callout_button_label'] ) ) {
                $errors->add( 'rest_invalid_param_callout_button_label', __( 'The late fee window callout button label must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
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
            'callout_header' => array(
              'type' => 'string',
            ),
            'callout_content' => array(
              'type' => 'string',
            ),
            'callout_button_label' => array(
              'type' => 'string',
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

                // Validate that the season dates do not overlap
                $seasons = $value['calendar_items'];

                $season_overlaps = false;
                foreach ( $seasons as $key => $season ) {
                  $season_start = strtotime( $season['start_date'] );
                  $season_end = strtotime( $season['end_date'] );

                  foreach ( $seasons as $inner_key => $inner_season ) {
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
          'description' => 'Renewal Window Data',
          'arg_options' => [
            'validate_callback' => function( $value ) {
              $errors = new WP_Error();

              if ( ! is_array( $value ) ) {
                $errors->add( 'rest_invalid_param', __( 'The tier data must be an object.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( is_bool( $value['approval_required'] ) === false ) {
                $errors->add( 'rest_invalid_param_approval_required', __( 'The approval required value must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( empty( $value['mdp_tier_name'] ) ) {
                $errors->add( 'rest_invalid_param_mdp_tier_name', __( 'The MDP Tier Name must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( empty( $value['mdp_tier_uuid'] ) ) {
                $errors->add( 'rest_invalid_param_mdp_tier_uuid', __( 'The MDP Tier UUID must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

              if ( empty( $value['mdp_next_tier_uuid'] ) ) {
                $errors->add( 'rest_invalid_param_mdp_next_tier_uuid', __( 'The Next Tier MDP UUID must not be empty.', 'wicket-memberships' ), array( 'status' => 400 ) );
              }

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
                  $errors->add( 'rest_invalid_param_product_data', __( 'Product IDs must be unique for organization tier types with "per_range_of_seats" type.', 'wicket-memberships' ), array( 'status' => 400 ) );
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
            'mdp_tier_name' => array(
              'type'        => 'string',
              'description' => 'MPD Tier Name',
            ),
            'mdp_tier_uuid' => array(
              'type'        => 'string',
              'description' => 'MPD Tier UUID',
            ),
            'mdp_next_tier_uuid' => array(
              'type'        => 'string',
              'description' => 'Next Tier MDP UUID',
            ),
            'config_id' => array(
              'type'        => 'integer',
              'description' => 'Membership Config Post ID',
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
                'max_seats' => array(
                  'type' => 'integer',
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

    $args = array(
      'supports' => $supports,
      'labels' => $labels,
      'description'        => __( 'Records of the Wicket Memberships', 'wicket-memberships' ),
      'public'             => true,
      'publicly_queryable' => true,
      'show_ui'            => true,
      'show_in_menu'       => WICKET_MEMBERSHIP_PLUGIN_SLUG,
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

    register_post_meta($this->membership_cpt_slug, 'status', $args);

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
    register_post_meta($this->membership_cpt_slug, 'wicket_uuid', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'The start date membership.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'start_date', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'The end date membership.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'end_date', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'The expiry date of the membership in wordpress.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'expiry_date', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'Person or Org membership.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'member_type', $args);

    $args = array(
      'type'              => 'string',
      'description'       => __( 'MDP Membership ID.', 'wicket-memberships' ),
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'membership_uuid', $args);
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
      'public'                => true,
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
      'public'                => true,
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