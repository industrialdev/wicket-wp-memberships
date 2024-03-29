<?php

namespace Wicket_Memberships;

use Wicket_Memberships\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Membership Config CPT Hooks
 */
class Membership_Config_CPT_Hooks {

  const EDIT_PAGE_SLUG = 'wicket_membership_config_edit';
  private $membership_config_cpt_slug = '';

  public function __construct() {
    $this->membership_config_cpt_slug = Helper::get_membership_config_cpt_slug();

	  add_action( 'admin_menu', [ $this, 'add_edit_page' ] );
    add_action( 'admin_init', [ $this, 'create_edit_page_redirects' ] );
    add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    add_action('manage_'.$this->membership_config_cpt_slug.'_posts_columns', [ $this, 'table_head'] );
    add_action('manage_'.$this->membership_config_cpt_slug.'_posts_custom_column', [ $this, 'table_content'], 10, 2 );
  }

  function add_edit_page() {
    add_submenu_page(
      NULL,
      __( 'Add New Membership Configuration', 'wicket-memberships'),
      __( 'Add New Membership Configuration', 'wicket-memberships'),
      'edit_posts',
      self::EDIT_PAGE_SLUG,
      [ $this, 'render_page' ]
    );
  }

  function render_page() {
    $config_list_page = admin_url( 'edit.php?post_type=' . $this->membership_config_cpt_slug );
    echo "<div id='create_membership_config' data-config-cpt-slug='{$this->membership_config_cpt_slug}' data-config-list-url='{$config_list_page}' ></div>";
  }

  function create_edit_page_redirects() {
    global $pagenow;

    if ( $pagenow == 'post-new.php' && isset( $_GET['post_type'] ) && $_GET['post_type'] == Helper::get_membership_config_cpt_slug() ) {
      wp_safe_redirect( admin_url( '/admin.php?page=' . self::EDIT_PAGE_SLUG ) );
      exit;
    }

    // TODO: Edit page redirect
    // if ( $pagenow == 'post.php' && isset( $_GET['post_type'] ) && $_GET['post_type'] == 'wicket_mship_config' ) {
    //   wp_safe_redirect( admin_url( '/post-new.php?post_type=page' ) );
    //   exit;
    // }
  }

  function enqueue_scripts() {

    $page = get_current_screen();

    // Only load react script on the certaion pages
    $react_page_slugs = [
      'admin_page_' . self::EDIT_PAGE_SLUG
    ];

    if ( ! in_array( $page->id, $react_page_slugs ) ) {
      return;
    }

    $asset_file = include( WICKET_MEMBER_PLUGIN_DIR . 'frontend/build/membership_config_create.asset.php' );

    wp_enqueue_script(
      WICKET_MEMBER_PLUGIN_SLUG . '_membership_config_create',
      WICKET_MEMBER_PLUGIN_URL . '/frontend/build/membership_config_create.js',
      $asset_file['dependencies'],
      $asset_file['version'],
      true
    );
  }


  /**
   * Customize Wicket Member List Page Header
   */
  public function table_head( $columns ) {
    $columns['renewal_window_data'] = __( 'Renewal Window Data', 'wicket-memberships' );
    $columns['late_fee_window_data'] = __( 'Late Fee Window Data', 'wicket-memberships' );
    $columns['cycle_data'] = __( 'Cycle Data', 'wicket-memberships' );
    unset($columns['date']);
    return $columns;
  }

    /**
   * Customize Wicket Member List Page Contents
   */
  public function table_content( $column_name, $post_id ) {

    if ( $column_name === 'renewal_window_data' ) {
      $meta = get_post_meta( $post_id, 'renewal_window_data', true );

      echo '<pre>';
      var_dump($meta);
      echo '</pre>';
    } else if ( $column_name === 'late_fee_window_data' ) {
      $meta = get_post_meta( $post_id, 'late_fee_window_data', true );

      echo '<pre>';
      var_dump($meta);
      echo '</pre>';
    } else if ( $column_name === 'cycle_data' ) {
      $meta = get_post_meta( $post_id, 'cycle_data', true );

      echo '<pre>';
      var_dump($meta);
      echo '</pre>';
    }

    // $keys = array_keys($meta);
    // foreach ($keys as $key) {
    //   if( $column_name == $key) {
    //     echo $meta[$key][0];
    //   }
    // }
  }

}
