<?php

namespace Wicket_Memberships;

use Wicket_Memberships\Helper;

/**
 * Membership Config CPT Hooks
 */
class Membership_Config_CPT_Hooks {

  const EDIT_PAGE_SLUG = 'wicket_membership_config_edit';

  public function __construct() {
	  add_action( 'admin_menu', [ $this, 'add_edit_page' ] );
    add_action( 'admin_init', [ $this, 'create_edit_page_redirects' ] );
    add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
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
    echo '<div id="create_membership_config" ></div>';
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

}
