<?php

namespace Wicket_Memberships;

use Wicket_Memberships\Helper;

/**
 * Wicket Member base-functionality
 */
class Membership_CPT_Hooks {

  private $membership_cpt_slug = '';


  public function __construct() {
    $this->membership_cpt_slug = Helper::get_membership_cpt_slug();
    add_filter('manage_'.$this->membership_cpt_slug.'_posts_columns', [ $this, $this->membership_cpt_slug.'_table_head']);
    add_action('manage_'.$this->membership_cpt_slug.'_posts_custom_column', [ $this, $this->membership_cpt_slug.'_table_content'], 10, 2 );
  }

  /**
   * Customize Wicket Member List Page Header
   */
  public function wicket_membership_table_head( $columns ) {
    add_filter('the_title', [ $this, 'replace_title' ],10, 2);
    $columns['title'] = __( 'Wicket UUID', 'wicket-memberships' );
    $columns['member_type']  = __( 'Type', 'wicket-memberships' );
    $columns['membership_tier_uuid']  = __( 'Membership Tier ID', 'wicket-memberships' );
    $columns['user_id']  = __( 'User ID', 'wicket-memberships' );
    $columns['status']  = __( 'Status', 'wicket-memberships' );
    $columns['start_date']  = __( 'Start Date', 'wicket-memberships' );
    $columns['early_renew_date']  = __( 'Early Renew Date', 'wicket-memberships' );
    $columns['end_date']  = __( 'End Date', 'wicket-memberships' );
    $columns['expiry_date']  = __( 'Expiry Date', 'wicket-memberships' );
    unset($columns['date']);
    return $columns;
  }

  /**
   * Customize Wicket Member List Page Contents
   */
  public function wicket_membership_table_content( $column_name, $post_id ) {
    $meta = get_post_meta( $post_id );
    echo $meta[$column_name][0];
    if( $column_name = 'membership_type' && $meta[$column_name][0] == 'organization') {
      echo ' - ' . $meta['org_uuid'][0];
      echo '<br>Seats: ' . $meta['org_seats'][0];
    }
  }

  /**
   * Customize Wicket Member List Page Title COlumn
   */
  public function replace_title($title, $id) {
    if( get_post_type( $id ) === $this->membership_cpt_slug ) {
      $wicket_uuid = get_post_meta( $id, 'wicket_uuid', true );
      if( empty( $wicket_uuid ) ) {
        return '(Unsynced)';
      }  else {
        return $wicket_uuid;
      }
    } else {
      return $title;
    }
  }
}
