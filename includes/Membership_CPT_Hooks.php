<?php

namespace Wicket_Memberships;

use Wicket_Memberships\Helper;

/**
 * Wicket Member base-functionality
 */
class Membership_CPT_Hooks {

  private $membership_cpt_slug = '';
  private $membership_config_cpt_slug = '';
  private $membership_tier_cpt_slug = '';


  public function __construct() {
    $this->membership_cpt_slug = Helper::get_membership_cpt_slug();
    $this->membership_config_cpt_slug = Helper::get_membership_config_cpt_slug();
    $this->membership_tier_cpt_slug = Helper::get_membership_tier_cpt_slug();
    add_filter('manage_'.$this->membership_cpt_slug.'_posts_columns', [ $this, $this->membership_cpt_slug.'_table_head']);
    add_action('manage_'.$this->membership_cpt_slug.'_posts_custom_column', [ $this, $this->membership_cpt_slug.'_table_content'], 10, 2 );
    add_filter('manage_'.$this->membership_tier_cpt_slug.'_posts_columns', [ $this, $this->membership_tier_cpt_slug.'_table_head']);
    add_action('manage_'.$this->membership_tier_cpt_slug.'_posts_custom_column', [ $this, $this->membership_tier_cpt_slug.'_table_content'], 10, 2 );
  }

  /**
   * Customize Wicket Member List Page Header
   */
  public function wicket_membership_table_head( $columns ) {
    add_filter('the_title', [ $this, 'replace_title' ],10, 2);
    $columns['title'] = __( 'Wicket UUID', 'wicket-memberships' );
    $columns['member_type']  = __( 'Type', 'wicket-memberships' );
    $columns['membership_uuid']  = __( 'Membership ID', 'wicket-memberships' );
    $columns['user_id']  = __( 'User ID', 'wicket-memberships' );
    $columns['status']  = __( 'Status', 'wicket-memberships' );
    $columns['start_date']  = __( 'Start Date', 'wicket-memberships' );
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
    $keys = array_keys($meta);
    foreach ($keys as $key) {
      if( $column_name == $key) {
        echo $meta[$key][0];
      }
    }
  }

  /**
   * Customize Wicket Member List Page Header
   */
  public function wicket_mship_tier_table_head( $columns ) {
    add_filter('the_title', [ $this, 'replace_title' ],10, 2);
    $columns['tier_uuid'] = __( 'Membership UUID', 'wicket-memberships' );
    $columns['next_tier_uuid']  = __( 'Next Membership UUID', 'wicket-memberships' );
    $columns['config_id']  = __( 'Config ID', 'wicket-memberships' );
    $columns['type']  = __( 'Membership Type', 'wicket-memberships' );
    $columns['wc_product']  = __( 'Assigned Products', 'wicket-memberships' );
    $columns['seats']  = __( 'Org seats', 'wicket-memberships' );
    $columns['approval_required']  = __( 'Approval Required', 'wicket-memberships' );
    unset($columns['date']);
    //unset($columns['title']);
    return $columns;
  }

  /**
   * Customize Wicket Member List Page Contents
   */
  public function wicket_mship_tier_table_content( $column_name, $post_id ) {
    $meta = get_post_meta( $post_id );
    $keys = array_keys($meta);
    foreach ($keys as $key) {
      if( $key == $column_name ) {
        if( 'wc_product' == $key) {
            $meta_product = get_post_meta( $post_id , 'wc_product', false);
            echo implode(", ", $meta_product);
        } else {
            echo $meta[$key][0];
        }
      }
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
    } else if ( get_post_type( $id ) === $this->membership_tier_cpt_slug ) {
      $name = get_post_meta( $id, 'tier_name', true );
      if( empty( $name ) ) {
        return '(Unknown)';
      }  else {
        return $name;
      }  
    } else {
      return $title;
    }
  }
}
