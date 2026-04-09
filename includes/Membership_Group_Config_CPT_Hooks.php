<?php

namespace Wicket_Memberships;

use Wicket_Memberships\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Membership Group Config CPT Hooks
 *
 * Provides admin list customisation and a React-based create/edit page
 * for the wicket_mship_grp_cfg post type. Mirrors the structure of
 * Membership_Config_CPT_Hooks for the standard membership config CPT.
 */
class Membership_Group_Config_CPT_Hooks {

  const EDIT_PAGE_SLUG = 'wicket_mship_grp_cfg_edit';

  private $group_config_cpt_slug = '';

  public function __construct() {
    $this->group_config_cpt_slug = Helper::get_membership_group_config_cpt_slug();

    add_action( 'admin_menu', [ $this, 'add_edit_page' ] );
    add_action( 'admin_init', [ $this, 'create_edit_page_redirects' ] );
    add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    add_action( 'manage_' . $this->group_config_cpt_slug . '_posts_columns', [ $this, 'table_head' ] );
    add_action( 'manage_' . $this->group_config_cpt_slug . '_posts_custom_column', [ $this, 'table_content' ], 10, 2 );

    add_filter( 'post_row_actions', [ $this, 'row_actions' ], 10, 2 );

    // Skip trash for group configs
    add_action( 'trashed_post', [ $this, 'directory_skip_trash' ] );

    // Prevent moving to trash if the config is in use by a membership group
    add_filter( 'pre_trash_post', [ $this, 'prevent_trash' ], 10, 2 );
  }

  /**
   * Prevent trashing a group config that has associated membership groups.
   */
  public function prevent_trash( $trash, $post ) {
    if ( $this->group_config_cpt_slug === $post->post_type ) {
      $linked_groups = get_posts( [
        'post_type'      => Helper::get_membership_group_cpt_slug(),
        'posts_per_page' => 1,
        'meta_key'       => 'membership_group_config_id',
        'meta_value'     => $post->ID,
        'fields'         => 'ids',
      ] );

      if ( ! empty( $linked_groups ) ) {
        wp_die( __( 'This group configuration is in use by one or more membership groups. It cannot be moved to trash.', 'wicket-memberships' ) );
      }
    }

    return $trash;
  }

  /**
   * Force-delete instead of trashing group config posts.
   */
  public function directory_skip_trash( $post_id ) {
    if ( get_post_type( $post_id ) === $this->group_config_cpt_slug ) {
      wp_delete_post( $post_id, true );
    }
  }

  /**
   * Remove "Quick Edit" from the row actions.
   */
  public function row_actions( $actions, $post ) {
    if ( $this->group_config_cpt_slug === $post->post_type ) {
      unset( $actions['inline hide-if-no-js'] );
    }
    return $actions;
  }

  /**
   * Register the hidden submenu page that hosts the React create/edit form.
   */
  public function add_edit_page() {
    add_submenu_page(
      NULL,
      __( 'Add New Membership Group Configuration', 'wicket-memberships' ),
      __( 'Add New Membership Group Configuration', 'wicket-memberships' ),
      'edit_posts',
      self::EDIT_PAGE_SLUG,
      [ $this, 'render_page' ]
    );
  }

  /**
   * Render the mount point for the React app.
   */
  public function render_page() {
    $group_config_list_page_url = admin_url( 'edit.php?post_type=' . $this->group_config_cpt_slug );

    $post_id = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : '';

    $language_codes                  = Helper::get_wp_languages_iso();
    $language_codes_comma_separated  = implode( ',', $language_codes );

    echo <<<HTML
      <div
        id="create_membership_group_config"
        data-group-config-cpt-slug="{$this->group_config_cpt_slug}"
        data-group-config-list-url="{$group_config_list_page_url}"
        data-language-codes="{$language_codes_comma_separated}"
        data-post-id="{$post_id}"></div>
    HTML;
  }

  /**
   * Redirect native WP new/edit screens to the React page.
   */
  public function create_edit_page_redirects() {
    global $pagenow;

    if ( $pagenow === 'post-new.php' && isset( $_GET['post_type'] ) && $_GET['post_type'] === $this->group_config_cpt_slug ) {
      wp_safe_redirect( admin_url( '/admin.php?page=' . self::EDIT_PAGE_SLUG ) );
      exit;
    }

    if (
      $pagenow === 'post.php' &&
      isset( $_GET['post'] ) &&
      isset( $_GET['action'] ) && $_GET['action'] === 'edit'
    ) {
      $post_id   = intval( $_GET['post'] );
      $post_type = get_post_type( $post_id );

      if ( $post_type === $this->group_config_cpt_slug ) {
        wp_safe_redirect( admin_url( '/admin.php?page=' . self::EDIT_PAGE_SLUG . '&post_id=' . $post_id ) );
        exit;
      }
    }
  }

  /**
   * Enqueue the React bundle only on the group config edit page.
   */
  public function enqueue_scripts() {
    $page = get_current_screen();

    $react_page_slugs = [
      'admin_page_' . self::EDIT_PAGE_SLUG,
    ];

    if ( ! in_array( $page->id, $react_page_slugs ) ) {
      return;
    }

    $asset_file = include( WICKET_MEMBERSHIP_PLUGIN_DIR . 'frontend/build/membership_group_config_create.asset.php' );

    wp_enqueue_script(
      WICKET_MEMBERSHIP_PLUGIN_SLUG . '_membership_group_config_create',
      WICKET_MEMBERSHIP_PLUGIN_URL . '/frontend/build/membership_group_config_create.js',
      $asset_file['dependencies'],
      $asset_file['version'],
      true
    );
  }

  /**
   * Add custom columns to the group config list table.
   */
  public function table_head( $columns ) {
    $columns['cycle_type']   = __( 'Cycle', 'wicket-memberships' );
    $columns['renewal_type'] = __( 'Renewal Type', 'wicket-memberships' );

    if ( ! empty( $_ENV['WICKET_MEMBERSHIPS_DEBUG_MODE'] ) ) {
      $columns['renewal_window_data']  = __( 'Renewal Window Data', 'wicket-memberships' );
      $columns['late_fee_window_data'] = __( 'Late Fee Window Data', 'wicket-memberships' );
      $columns['cycle_data']           = __( 'Cycle Data', 'wicket-memberships' );
      $columns['group_config_data']    = __( 'Group Config Data', 'wicket-memberships' );
    }

    unset( $columns['date'] );
    return $columns;
  }

  /**
   * Populate custom column cells in the group config list table.
   */
  public function table_content( $column_name, $post_id ) {
    $config = new Membership_Group_Config( $post_id );

    if ( $column_name === 'cycle_type' ) {
      echo ucfirst( (string) $config->get_cycle_type() );
    } elseif ( $column_name === 'renewal_type' ) {
      $renewal_type = $config->get_renewal_type();
      if ( $renewal_type === 'subscription' ) {
        echo __( 'Subscription', 'wicket-memberships' );
      } elseif ( $renewal_type === 'form_page' ) {
        echo __( 'Form Flow', 'wicket-memberships' );
      } else {
        echo '—';
      }
    }

    if ( empty( $_ENV['WICKET_MEMBERSHIPS_DEBUG_MODE'] ) ) {
      return;
    }

    if ( $column_name === 'renewal_window_data' ) {
      echo '<pre>';
      print_r( get_post_meta( $post_id, 'renewal_window_data', true ) );
      echo '</pre>';
    } elseif ( $column_name === 'late_fee_window_data' ) {
      echo '<pre>';
      print_r( get_post_meta( $post_id, 'late_fee_window_data', true ) );
      echo '</pre>';
    } elseif ( $column_name === 'cycle_data' ) {
      echo '<pre>';
      print_r( get_post_meta( $post_id, 'cycle_data', true ) );
      echo '</pre>';
    } elseif ( $column_name === 'group_config_data' ) {
      echo '<pre>';
      print_r( get_post_meta( $post_id, 'group_config_data', true ) );
      echo '</pre>';
    }
  }
}
