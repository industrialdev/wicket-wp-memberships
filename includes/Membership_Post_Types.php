<?php
namespace Wicket_Memberships;

use Wicket_Memberships\Helper;

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
  }

  /**
   * Create the membership post type
   */
  public function register_membership_post_type() {
    $supports = array(
      'custom-fields',
    );

    $labels = array(
      'name' => _x('Memberships', 'plural'),
    );

    $args = array(
      'supports' => $supports,
      'labels' => $labels,
      'description'        => __( 'Records of the Wicket Memberships', 'wicket' ),
      'public'             => true,
      'publicly_queryable' => true,
      'show_ui'            => true,
      'show_in_menu'       => WICKET_MEMBER_PLUGIN_SLUG,
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
      'description'       => 'The UserID owns this membership record',
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'user_id', $args);

    $args = array(
      'type'              => 'string',
      'description'       => 'The UUID in wicket of this membership record',
      'single'            => true,
      'show_in_rest'      => true,
    );
    register_post_meta($this->membership_cpt_slug, 'wicket_uuid', $args);

    $args = array(
      'type'              => 'string',
      'description'       => 'The start date membership.',
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'start_date', $args);

    $args = array(
      'type'              => 'string',
      'description'       => 'The end date membership.',
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'end_date', $args);

    $args = array(
      'type'              => 'string',
      'description'       => 'The expiry date (end_date plus grace_period) of the membership in wordpress.',
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'expiry_date', $args);

    $args = array(
      'type'              => 'string',
      'description'       => 'Person or Org membership.',
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_cpt_slug, 'member_type', $args);

    $args = array(
      'type'              => 'string',
      'description'       => 'MDP Membership ID.',
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
      'show_in_menu'          => WICKET_MEMBER_PLUGIN_SLUG,
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

    // Register the meta fields
    register_post_meta( $this->membership_config_cpt_slug, 'renewal_window_data', [
      'type' => 'object',
      'single' => true,
      'description' => __( 'Renewal Window Data', 'wicket-memberships' ),
      'show_in_rest' => array(
        'schema' => array(
          'type'  => 'object',
          'properties' => array(
            'days_count' => array(
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
      ),
    ] );

    register_post_meta( $this->membership_config_cpt_slug, 'late_fee_window_data', [
      'type' => 'object',
      'single' => true,
      'description' => __( 'Late Fee Window Data', 'wicket-memberships' ),
      'show_in_rest' => array(
        'schema' => array(
          'type'  => 'object',
          'properties' => array(
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
      ),
    ] );

    register_post_meta( $this->membership_config_cpt_slug, 'cycle_data', [
      'type' => 'object',
      'single' => true,
      'description' => __( 'Cycle Data', 'wicket-memberships' ),
      // TODO: Add sanitize callback to accept only valid data
      // 'sanitize_callback' => [ $this, 'sanitize_late_fee_window_data' ],
      'show_in_rest' => array(
        'schema' => array(
          'type'  => 'object',
          'properties' => array(
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
      ),
    ] );
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
      'show_in_menu'          => WICKET_MEMBER_PLUGIN_SLUG,
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

    $args = array(
      'type'              => 'boolean',
      'description'       => 'Approval required.',
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_tier_cpt_slug, 'approval_required', $args);

    $args = array(
      'type'              => 'string',
      'description'       => 'The membership tier name.',
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_tier_cpt_slug, 'tier_name', $args);

    $args = array(
      'type'              => 'string',
      'description'       => 'The membership tier uuid.',
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_tier_cpt_slug, 'tier_uuid', $args);

    $args = array(
      'type'              => 'string',
      'description'       => 'The next membership tier uuid in sequence.',
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_tier_cpt_slug, 'next_tier_uuid', $args);

    $args = array(
      'type'              => 'integer',
      'description'       => 'The associated config id.',
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta($this->membership_tier_cpt_slug, 'config_id', $args);

    $args = array(
      'type'              => 'string',
      'description'       => 'individual or organization type membership.',
      'single'            => true,
      'show_in_rest'      => true,
    );
    register_post_meta($this->membership_tier_cpt_slug, 'type', $args);


    /**
     * Seat Range and Org/Ind Type
     *
      {
        "status": "publish",
        "meta": {
            "tier_name": "Membership Tier Vert",
            "tier_uuid": "u-u-i-d",
            "next_tier_uuid": "u-u-i-d-2",
            "type": "organization",
            "approval_required": true,
            "config_id": 1,
            "wc_products": [{"wc_product_id": 1, "seats": 5 }]
        }
      }
     */
    $args = array(
      'type'              => 'object',
      'description'       => 'The associated products - seats.',
      'single'            => true,
      'show_in_rest'      => array (
        'schema' => array(
              'type' => 'array',
              'items' => array (
                'wc_product_id' => array (
                  'type' => 'integer'
                ),
                  /*
                  0 = individual
                  1 = org per seat
                  5 = org seat range (1-5)
                  10 = org seat range (6-10) etc.
                  */
                  'seats' => array (
                    'type' => 'integer'
                )
              )
            ),
          ),
    );

    register_post_meta($this->membership_tier_cpt_slug, 'wc_products', $args);
  }

}