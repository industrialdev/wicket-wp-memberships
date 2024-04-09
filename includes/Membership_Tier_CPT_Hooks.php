<?php

namespace Wicket_Memberships;

use Wicket_Memberships\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Membership Tier CPT Hooks
 */
class Membership_Tier_CPT_Hooks {

  const EDIT_PAGE_SLUG = 'wicket_membership_tier_edit';
  private $membership_tier_cpt_slug = '';
  private $membership_config_cpt_slug = '';

  public function __construct() {
    $this->membership_tier_cpt_slug = Helper::get_membership_tier_cpt_slug();
    $this->membership_config_cpt_slug = Helper::get_membership_config_cpt_slug();

	  add_action( 'admin_menu', [ $this, 'add_edit_page' ] );
    add_action( 'admin_init', [ $this, 'create_edit_page_redirects' ] );
    add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    add_action('manage_'.$this->membership_tier_cpt_slug.'_posts_columns', [ $this, 'table_head'] );
    add_action('manage_'.$this->membership_tier_cpt_slug.'_posts_custom_column', [ $this, 'table_content'], 10, 2 );
  }

  function add_edit_page() {
    add_submenu_page(
      NULL,
      __( 'Add New Membership Tier', 'wicket-memberships'),
      __( 'Add New Membership Tier', 'wicket-memberships'),
      'edit_posts',
      self::EDIT_PAGE_SLUG,
      [ $this, 'render_page' ]
    );
  }

  function render_page() {
    $tier_list_page = admin_url( 'edit.php?post_type=' . $this->membership_tier_cpt_slug );

    $post_id = isset( $_GET['post_id'] ) ? $_GET['post_id'] : '';

    echo <<<HTML
      <div
        id="create_membership_tier"
        data-tier-cpt-slug="{$this->membership_tier_cpt_slug}"
        data-config-cpt-slug="{$this->membership_config_cpt_slug}"
        data-tier-list-url="{$tier_list_page}"
        data-post-id="{$post_id}"></div>
    HTML;
  }

  function create_edit_page_redirects() {
    global $pagenow;

    if ( $pagenow == 'post-new.php' && isset( $_GET['post_type'] ) && $_GET['post_type'] == $this->membership_tier_cpt_slug ) {
      wp_safe_redirect( admin_url( '/admin.php?page=' . self::EDIT_PAGE_SLUG ) );
      exit;
    }

    if ( $pagenow == 'post.php' &&
        isset( $_GET['post'] ) &&
        ( isset( $_GET['action'] ) && $_GET['action'] == 'edit' )
      ) {
      $post_id = $_GET['post'];
      $post_type = get_post_type( $post_id );

      if ( $post_type === $this->membership_tier_cpt_slug ) {
        wp_safe_redirect( admin_url( '/admin.php?page=' . self::EDIT_PAGE_SLUG . '&post_id=' . $post_id ) );
        exit;
      }
    }
  }

  function enqueue_scripts() {

    $page = get_current_screen();

    // Load react script on the certain pages only
    $react_page_slugs = [
      'admin_page_' . self::EDIT_PAGE_SLUG
    ];

    if ( ! in_array( $page->id, $react_page_slugs ) ) {
      return;
    }

    $asset_file = include( WICKET_MEMBERSHIP_PLUGIN_DIR . 'frontend/build/membership_tier_create.asset.php' );

    wp_enqueue_script(
      WICKET_MEMBERSHIP_PLUGIN_SLUG . '_membership_tier_create',
      WICKET_MEMBERSHIP_PLUGIN_URL . '/frontend/build/membership_tier_create.js',
      $asset_file['dependencies'],
      $asset_file['version'],
      true
    );
  }

  /**
   * Customize Membership Tier List Page Header
   */
  public function table_head( $columns ) {
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
   * Customize Membership Tier List Page Contents
   */
  public function table_content( $column_name, $post_id ) {
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
   * Customize Membership Tier List Page Title Column
   */
  public function replace_title($title, $id) {
    if ( get_post_type( $id ) === $this->membership_tier_cpt_slug ) {
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
