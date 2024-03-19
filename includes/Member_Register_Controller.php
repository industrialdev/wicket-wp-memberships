<?php

namespace Wicket_Memberships;

/**
 * Wicket Member base-functionality
 */
class Member_Register_Controller {

  public function __construct() {
    add_action('init', [ $this, 'register_post_type' ]);
    add_filter('manage_wicket_member_posts_columns', [ $this, 'table_head']);
    add_action('manage_wicket_member_posts_custom_column', [ $this, 'table_content'], 10, 2 );
    add_filter('the_title', [ $this, 'replace_title' ],10, 2);
  }

  /**
   * Create the wicket membership post type
   */
  public function register_post_type() {
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
      'description'       => 'The number of days after end date membership expires.',
      'single'            => true,
      'show_in_rest'      => true,
    );
    register_post_meta('wicket_member', 'grace_period', $args);
    $args = array(
      'type'              => 'string',
      'description'       => 'Person or Org membership.',
      'single'            => true,
      'show_in_rest'      => true,
    );
    register_post_meta('wicket_member', 'member_type', $args);
  }
  /**
   * Customize Wicket Member List Page Header
   */
  public function table_head( $columns ) {
    $columns['title'] = 'Wicket UUID';
    $columns['member_type']  = 'Type';
    $columns['user_id']  = 'User ID';
    $columns['status']  = 'Status';
    $columns['start_date']  = 'Start Date';
    $columns['end_date']  = 'End Date';
    $columns['grace_period']  = 'Grace Period';
    unset($columns['date']);
    return $columns;
  }

  /**
   * Customize Wicket Member List Page Contents
   */
  public function table_content( $column_name, $post_id ) {
    $meta = get_post_meta( $post_id );
    $keys = array_keys($meta);
    foreach ($keys as $key) {
        if( $column_name == $key) {
            echo $meta[$key][0];
        }
    }
  }
  /**
   * Customize Wicket Member List Page Title COlumn
   */
  public function replace_title($title, $id) {
    if( get_post_type( $id ) !== 'wicket_member') {
      return $title;
    }

    $wicket_uuid = get_post_meta( $id, 'wicket_uuid', true );
    if( empty( $wicket_uuid ) ) {
        return '(Unsynced)';
    }  else {
        return $wicket_uuid;
    }
  }
}
