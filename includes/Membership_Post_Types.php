<?php
namespace Wicket_Memberships;

use Wicket_Memberships\Helper;

defined( 'ABSPATH' ) || exit;

class Membership_Post_Types {

  private $membership_config_cpt_slug = '';

  public function __construct() {
    $this->membership_config_cpt_slug = Helper::get_membership_config_cpt_slug();
    add_action('init', [ $this, 'register_member_post_type' ]);
    add_action('init', [ $this, 'register_membership_config_post_type' ]);
  }

  /**
   * Create the membership post type
   */
  public function register_member_post_type() {
    $supports = array(
      'custom-fields',
    );

    $labels = array(
      'name' => _x('Members', 'plural'),
    );

    $args = array(
      'supports' => $supports,
      'labels' => $labels,
      'description'        => __( 'Members of the Wicket Memberships', 'wicket' ),
      'public'             => true,
      'publicly_queryable' => true,
      'show_ui'            => true,
      'show_in_menu'       => WICKET_MEMBER_PLUGIN_SLUG,
      'query_var'          => true,
      'rewrite'            => array( 'slug' => 'wicket_member' ),
      'capability_type'    => 'post',
      'map_meta_cap'       => true, //permissions same as 'posts'
      'has_archive'        => true,
      'hierarchical'       => false,
      'menu_position'      => null,
      'show_in_rest'       => true,
      'rest_base'          => 'wicket_member',
      'rest_controller_class' => 'Wicket_Memberships\Member_WP_REST_Controller',
    );

    register_post_type('wicket_member', $args);

    $args = array(
      'type'              => 'string',
      'description'       => 'The status of this membership record',
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta('wicket_member', 'status', $args);

    $args = array(
      'type'              => 'integer',
      'description'       => 'The UserID owns this membership record',
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta('wicket_member', 'user_id', $args);

    $args = array(
      'type'              => 'string',
      'description'       => 'The UUID in wicket of this membership record',
      'single'            => true,
      'show_in_rest'      => true,
    );
    register_post_meta('wicket_member', 'wicket_uuid', $args);

    $args = array(
      'type'              => 'string',
      'description'       => 'The start date membership.',
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta('wicket_member', 'start_date', $args);

    $args = array(
      'type'              => 'string',
      'description'       => 'The end date membership.',
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta('wicket_member', 'end_date', $args);

    $args = array(
      'type'              => 'string',
      'description'       => 'The expiry date (end_date plus grace_period) of the membership in wordpress.',
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta('wicket_member', 'expiry_date', $args);

    $args = array(
      'type'              => 'string',
      'description'       => 'Person or Org membership.',
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta('wicket_member', 'member_type', $args);

    $args = array(
      'type'              => 'string',
      'description'       => 'MDP Membership ID.',
      'single'            => true,
      'show_in_rest'      => true,
    );

    register_post_meta('wicket_member', 'membership_uuid', $args);
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
      'description' => __( 'Renewal window data', 'wicket-memberships' ),
      'show_in_rest' => array(
        'schema' => array(
          'type'       => 'object',
          'items' => array(
            'days_count' => array(
              'type' => 'integer',
            ),
          ),
        ),
      ),
    ] );
  }

}