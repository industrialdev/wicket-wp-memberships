<?php

namespace Wicket_Memberships;

/**
 * Wicket Member base-functionality
 */
class Membership_CPT_Hooks {

  public function __construct() {
    add_filter('manage_wicket_member_posts_columns', [ $this, 'table_head']);
    add_action('manage_wicket_member_posts_custom_column', [ $this, 'table_content'], 10, 2 );
    add_filter('the_title', [ $this, 'replace_title' ],10, 2);
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
