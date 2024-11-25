<?php

namespace Wicket_Memberships;

use Wicket_Memberships\Helper;

/**
 * Wicket Member base-functionality
 */
class Membership_CPT_Hooks {

  private $membership_cpt_slug = '';
  const LIST_INDIVIDUAL_MEMBER_PAGE_SLUG = 'individual_member_list';
  const EDIT_INDIVIDUAL_MEMBER_PAGE_SLUG = 'wicket_individual_member_edit';
  const LIST_ORG_MEMBER_PAGE_SLUG = 'org_member_list';
  const EDIT_ORG_MEMBER_PAGE_SLUG = 'wicket_org_member_edit';

  private $status_names;
  private $membership_tier_cpt_slug = '';

  public function __construct() {
    $this->membership_cpt_slug = Helper::get_membership_cpt_slug();
    $this->status_names = Helper::get_all_status_names();
    $this->membership_tier_cpt_slug = Helper::get_membership_tier_cpt_slug();
    
    add_filter('manage_'.$this->membership_cpt_slug.'_posts_columns', [ $this, $this->membership_cpt_slug.'_table_head']);
    add_action('manage_'.$this->membership_cpt_slug.'_posts_custom_column', [ $this, $this->membership_cpt_slug.'_table_content'], 10, 2 );

    add_action( 'admin_menu', [ $this, 'add_individual_members_page' ] );
    add_action( 'admin_menu', [ $this, 'add_org_members_page' ] );

    add_action( 'admin_menu', [ $this, 'edit_individual_member_page' ] );
    add_action( 'admin_menu', [ $this, 'edit_org_member_page' ] );

    add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
  }

  function edit_individual_member_page() {
    add_submenu_page(
      null,
      __( 'Edit Individual Member', 'wicket-memberships'),
      __( 'Edit Individual Member', 'wicket-memberships'),
      'edit_posts',
      self::EDIT_INDIVIDUAL_MEMBER_PAGE_SLUG,
      [ $this, 'render_edit_individual_member_page' ]
    );
  }

  function edit_org_member_page() {
    add_submenu_page(
      null,
      __( 'Edit Organization Member', 'wicket-memberships'),
      __( 'Edit Organization Member', 'wicket-memberships'),
      'edit_posts',
      self::EDIT_ORG_MEMBER_PAGE_SLUG,
      [ $this, 'render_edit_org_member_page' ]
    );
  }

  function render_edit_individual_member_page() {
    $record_id = isset( $_GET['id'] ) ? $_GET['id'] : '';
    $membership_uuid = isset( $_GET['membership_uuid'] ) ? $_GET['membership_uuid'] : '';

    // Since we use UUID we need to pass the WP user id to the react component
    $user = get_user_by( 'login', $record_id );
    $local_user_id = $user === false ? '' : $user->ID;

    echo <<<HTML
      <div
        id="edit_member"
        data-tier-cpt-slug="{$this->membership_tier_cpt_slug}"
        data-record-id="{$local_user_id}"
        data-membership-uuid="{$membership_uuid}"
        data-member-type="individual"></div>
    HTML;
  }

  function render_edit_org_member_page() {
    $record_id = isset( $_GET['id'] ) ? $_GET['id'] : '';
    $membership_uuid = isset( $_GET['membership_uuid'] ) ? $_GET['membership_uuid'] : '';

    echo <<<HTML
      <div
        id="edit_member"
        data-tier-cpt-slug="{$this->membership_tier_cpt_slug}"
        data-record-id="{$record_id}"
        data-membership-uuid="{$membership_uuid}"
        data-member-type="organization"></div>
    HTML;
  }

  function add_individual_members_page() {
    add_submenu_page(
      WICKET_MEMBERSHIP_PLUGIN_SLUG,
      __( 'Individual Members', 'wicket-memberships'),
      __( 'Individual Members', 'wicket-memberships'),
      'edit_posts',
      self::LIST_INDIVIDUAL_MEMBER_PAGE_SLUG,
      [ $this, 'render_individual_members_page' ]
    );
  }

  function add_org_members_page() {
    add_submenu_page(
      WICKET_MEMBERSHIP_PLUGIN_SLUG,
      __( 'Organization Members', 'wicket-memberships'),
      __( 'Organization Members', 'wicket-memberships'),
      'edit_posts',
      self::LIST_ORG_MEMBER_PAGE_SLUG,
      [ $this, 'render_org_members_page' ]
    );
  }

  function render_org_members_page() {
    $edit_org_member_page_url = admin_url( 'admin.php?page=' . self::EDIT_ORG_MEMBER_PAGE_SLUG );

    echo <<<HTML
      <div
        id="member_list"
        data-edit-member-url="{$edit_org_member_page_url}"
        data-member-type="organization""></div>
    HTML;
  }

  function render_individual_members_page() {
    $edit_individual_member_page_url = admin_url( 'admin.php?page=' . self::EDIT_INDIVIDUAL_MEMBER_PAGE_SLUG );

    echo <<<HTML
      <div
        id="member_list"
        data-edit-member-url="{$edit_individual_member_page_url}"
        data-member-type="individual""></div>
    HTML;
  }

  function enqueue_scripts() {

    $page = get_current_screen();

    // Only load react script on the certain pages
    $list_page_slugs = [
      'wicket-memberships_page_' . self::LIST_INDIVIDUAL_MEMBER_PAGE_SLUG,
      'wicket-memberships_page_' . self::LIST_ORG_MEMBER_PAGE_SLUG,
    ];

    if ( in_array( $page->id, $list_page_slugs ) ) {
      $asset_file = include( WICKET_MEMBERSHIP_PLUGIN_DIR . 'frontend/build/membership_config_create.asset.php' );

      wp_enqueue_script(
        WICKET_MEMBERSHIP_PLUGIN_SLUG . '_member_list',
        WICKET_MEMBERSHIP_PLUGIN_URL . '/frontend/build/member_list.js',
        $asset_file['dependencies'],
        $asset_file['version'],
        true
      );
    }

    $edit_page_slugs = [
      'admin_page_' . self::EDIT_INDIVIDUAL_MEMBER_PAGE_SLUG,
      'admin_page_' . self::EDIT_ORG_MEMBER_PAGE_SLUG,
    ];

    if ( in_array( $page->id, $edit_page_slugs ) ) {
      $asset_file = include( WICKET_MEMBERSHIP_PLUGIN_DIR . 'frontend/build/member_edit.asset.php' );

      wp_enqueue_script(
        WICKET_MEMBERSHIP_PLUGIN_SLUG . '_edit_member',
        WICKET_MEMBERSHIP_PLUGIN_URL . '/frontend/build/member_edit.js',
        $asset_file['dependencies'],
        $asset_file['version'],
        true
      );
    }
  }

  /**
   * Customize Wicket Member List Page Header
   */
  public function wicket_membership_table_head( $columns ) {
    add_filter('the_title', [ $this, 'replace_title' ],10, 2);
    $columns['title'] = __( 'Wicket UUID', 'wicket-memberships' );
    $columns['membership_parent_order_id']  = __( 'Order ID', 'wicket-memberships' );
    $columns['user_email']  = __( 'Email', 'wicket-memberships' );
    $columns['membership_type']  = __( 'Type', 'wicket-memberships' );
    $columns['membership_tier_uuid']  = __( 'Membership Tier ID', 'wicket-memberships' );
    $columns['membership_status']  = __( 'Status', 'wicket-memberships' );
    $columns['membership_starts_at']  = __( 'Start Date', 'wicket-memberships' );
    $columns['membership_early_renew_at']  = __( 'Early Renew Date', 'wicket-memberships' );
    $columns['membership_ends_at']  = __( 'End Date', 'wicket-memberships' );
    $columns['membership_expires_at']  = __( 'Expiry Date', 'wicket-memberships' );
    unset($columns['date']);
    return $columns;
  }

  /**
   * Customize Wicket Member List Page Contents
   */
  public function wicket_membership_table_content( $column_name, $post_id ) {
    $meta = get_post_meta( $post_id );
    if( $column_name == 'membership_status' ) {
      echo $this->status_names[ $meta[$column_name][0] ][ 'name' ];
    } else if( $column_name == 'membership_type' ) {
      echo ucfirst( $meta[$column_name][0] );
      if( $meta[$column_name][0] == 'organization') {
        echo '<!--';
        echo '<br>UUID: ' . $meta['org_uuid'][0];
        echo '<br>Name: ' . $meta['org_name'][0];
        echo '<br>Seats: ' . $meta['org_seats'][0];
        echo '-->';
      }
    } else {
      echo $meta[$column_name][0];
    }
  }

  /**
   * Customize Wicket Member List Page Title COlumn
   */
  public function replace_title($title, $id) {
    if( get_post_type( $id ) === $this->membership_cpt_slug ) {
      $membership_wicket_uuid = get_post_meta( $id, 'membership_wicket_uuid', true );
      if( empty( $membership_wicket_uuid ) ) {
        return '(Unsynced)';
      }  else {
        return $membership_wicket_uuid;
      }
    } else {
      return $title;
    }
  }
}
