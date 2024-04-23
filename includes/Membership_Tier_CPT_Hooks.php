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

    // Manipulate post data after saving if needed
    add_action( 'rest_after_insert_' . $this->membership_tier_cpt_slug, [ $this, 'rest_save_post_page' ], 10, 1);

    // Skip trash for membership tiers
    add_action('trashed_post', [ $this, 'directory_skip_trash' ]);
  }

  function directory_skip_trash($post_id) {
    if (get_post_type($post_id) === $this->membership_tier_cpt_slug) {
      // Force delete
      wp_delete_post( $post_id, true );
    }
  }

  function rest_save_post_page($post){
    if ( get_post_type( $post->ID ) !== $this->membership_tier_cpt_slug ) {
      return;
    }

    $tier = new Membership_Tier( $post->ID );
    $tier_data = $tier->tier_data;

    $next_tier_post_exists = get_post_status( $tier->get_next_tier_id() ) === false ? false : true;

    if ( !$next_tier_post_exists ) {
      // Set next tier id to the current tier
      $tier_data['next_tier_id'] = $post->ID;
      $tier->update_tier_data( $tier_data );
    }
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
    $all_tier_product_ids = implode( ',', Membership_Tier::get_all_tier_product_ids() );

    echo <<<HTML
      <div
        id="create_membership_tier"
        data-products-in-use="{$all_tier_product_ids}"
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
    $columns['tier_data'] = __( 'Tier Data', 'wicket-memberships' );
    unset($columns['date']);
    //unset($columns['title']);
    return $columns;
  }

  /**
   * Customize Membership Tier List Page Contents
   */
  public function table_content( $column_name, $post_id ) {
    if ( 'tier_data' !== $column_name ) {
      return;
    }

    $tier_data = get_post_meta( $post_id, 'tier_data', true );

    echo '<pre>';
    print_r($tier_data);
    echo '</pre>';
  }

}
