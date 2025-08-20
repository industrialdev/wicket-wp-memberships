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
    add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_list_page_scripts' ] );
    add_action('manage_'.$this->membership_tier_cpt_slug.'_posts_columns', [ $this, 'table_head'] );
    add_action('manage_'.$this->membership_tier_cpt_slug.'_posts_custom_column', [ $this, 'table_content'], 10, 2 );

    // Manipulate post data after saving if needed
    add_action( 'rest_after_insert_' . $this->membership_tier_cpt_slug, [ $this, 'rest_save_post_page' ], 10, 1);

    // Remove quick edit from row actions
    add_filter( 'post_row_actions', [ $this, 'row_actions' ], 10, 2 );

    // Skip trash for membership tiers
    add_action('trashed_post', [ $this, 'directory_skip_trash' ]);

    // Prevent moving post to trash if it has associated memberships
    add_filter( 'pre_trash_post', [ $this, 'prevent_trash' ], 10, 2 );

    //post row actions link for changing tier uuid
    add_filter('post_row_actions', [$this, 'tier_id_post_link'], 10, 2);
  }

  function prevent_trash( $trash, $post ) {
    if ( $this->membership_tier_cpt_slug === $post->post_type ) {
      $tier = new Membership_Tier( $post->ID );
      $member_count = count( $tier->get_membership_posts() );

      if ( $member_count > 0 ) {
        wp_die('This tier has associated memberships. It cannot be moved to trash.');
      }
    }

    return $trash;
  }

  function row_actions( $actions, $post ) {
    if ( $this->membership_tier_cpt_slug === $post->post_type ) {
      // Removes the "Quick Edit" action.
      unset( $actions['inline hide-if-no-js'] );
    }
    return $actions;
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

    $next_tier_form_post_exists = get_post_status( $tier->get_next_tier_form_page_id() ) === false ? false : true;
    $next_tier_post_exists = get_post_status( $tier->get_next_tier_id() ) === false ? false : true;

    if ( ! $next_tier_post_exists && ! $next_tier_form_post_exists ) {
      $tier_data = $tier->tier_data;

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
    $individual_member_list_page_url = admin_url( 'admin.php?page=individual_member_list' );
    $org_member_list_page_url = admin_url( 'admin.php?page=org_member_list' );

    $post_id = isset( $_GET['post_id'] ) ? $_GET['post_id'] : '';

    $all_tier_product_ids = Membership_Tier::get_all_tier_product_ids();
    $all_tier_product_variation_ids = Membership_Tier::get_all_tier_product_variation_ids();

    // Exclude variable and variable-subscription product IDs from the list
    $all_tier_product_ids = array_filter( $all_tier_product_ids, function( $product_id ) {
      return ! wc_get_product( $product_id )->is_type( [ 'variable', 'variable-subscription' ] );
    });

    /**
    * If we are editing a tier,
    * then we need to exclude the current tier product IDs from the list
    * because we won't be able to show them in the frontend dropdown
    */
    if ( $post_id ) {
      $tier = new Membership_Tier( $post_id );
      $tier_product_ids = $tier->get_product_ids();

      $all_tier_product_ids = array_diff( $all_tier_product_ids, $tier_product_ids );
      $all_tier_product_variation_ids = array_diff( $all_tier_product_variation_ids, $tier->get_product_variation_ids() );
    }

    $all_tier_product_ids_comma_separated = implode( ',', $all_tier_product_ids );
    $all_tier_product_variation_ids_comma_separated = implode( ',', $all_tier_product_variation_ids );

    $language_codes = Helper::get_wp_languages_iso();
    $language_codes_comma_separated = implode( ',', $language_codes );

    echo <<<HTML
      <div
        id="create_membership_tier"
        data-products-in-use="{$all_tier_product_ids_comma_separated}"
        data-product-variations-in-use="{$all_tier_product_variation_ids_comma_separated}"
        data-tier-cpt-slug="{$this->membership_tier_cpt_slug}"
        data-config-cpt-slug="{$this->membership_config_cpt_slug}"
        data-tier-list-url="{$tier_list_page}"
        data-individual-list-url="{$individual_member_list_page_url}"
        data-org-list-url="{$org_member_list_page_url}"
        data-language-codes="{$language_codes_comma_separated}"
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

  function enqueue_list_page_scripts() {
    $page = get_current_screen();

    if ( $page->id !== 'edit-' . $this->membership_tier_cpt_slug ) {
      return;
    }

    $asset_file = include( WICKET_MEMBERSHIP_PLUGIN_DIR . 'frontend/build/tier_member_count.asset.php' );

    wp_enqueue_script(
      WICKET_MEMBERSHIP_PLUGIN_SLUG . '_tier_member_count',
      WICKET_MEMBERSHIP_PLUGIN_URL . '/frontend/build/tier_member_count.js',
      $asset_file['dependencies'],
      $asset_file['version'],
      true
    );

    $asset_file = include( WICKET_MEMBERSHIP_PLUGIN_DIR . 'frontend/build/wicket_memberships_tier_cell_info.asset.php' );

    wp_enqueue_script(
      WICKET_MEMBERSHIP_PLUGIN_SLUG . '_tier_cell_info',
      WICKET_MEMBERSHIP_PLUGIN_URL . '/frontend/build/wicket_memberships_tier_cell_info.js',
      $asset_file['dependencies'],
      $asset_file['version'],
      true
    );
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

    $columns['status'] = __( 'Status', 'wicket-memberships' );
    $columns['category'] = __( 'Category', 'wicket-memberships' );
    $columns['member_count'] = __( '# Members', 'wicket-memberships' );
    $columns['config_name'] = __( 'Config', 'wicket-memberships' );
    $columns['slug'] = __( 'MDP Tier Slug', 'wicket-memberships' );

    if ( ! empty( $_ENV['WICKET_MEMBERSHIPS_DEBUG_MODE'] ) ) {
      $columns['tier_data'] = __( 'Tier Data', 'wicket-memberships' );
    }

    unset($columns['date']);
    return $columns;
  }

  /**
   * Customize Membership Tier List Page Contents
   */
  public function table_content( $column_name, $post_id ) {
    $tier = new Membership_Tier( $post_id );

    if ( $column_name === 'status' ) {
      $tier_uuid = $tier->get_mdp_tier_uuid();

      echo <<<HTML
      <div
        class="wicket_memberships_tier_cell_info"
        data-tier-field="status"
        data-tier-uuid="{$tier_uuid}"></div>
      HTML;
    }

    if ( $column_name === 'category' ) {
      $tier_uuid = $tier->get_mdp_tier_uuid();

      echo <<<HTML
      <div
        class="wicket_memberships_tier_cell_info"
        data-tier-field="category"
        data-tier-uuid="{$tier_uuid}"></div>
      HTML;
    }

    if ( $column_name === 'member_count' ) {
      $tier_uuid = $tier->get_mdp_tier_uuid();

      echo <<<HTML
      <div
        class="wicket_memberships_tier_cell_member_count"
        data-tier-uuid="{$tier_uuid}"></div>
      HTML;
    }

    if ( $column_name === 'config_name' ) {
      echo "<span style='white-space: nowrap;'>";
      $config = $tier->get_config();
      echo $config->get_title();
      if( $_ENV['WICKET_MSHIP_MULTI_TIER_RENEWALS'] && $config->is_multitier_renewal() ) {
        echo ' (Multi-Tier)';
      }
      echo '</span>';
    }

    if ( $column_name === 'slug' ) {
      $tier_slug = get_post_meta( $post_id, 'membership_tier_slug', true);

      echo <<<HTML
      <div>$tier_slug</div>
      HTML;
    }

    if ( empty( $_ENV['WICKET_MEMBERSHIPS_DEBUG_MODE'] ) ) {
      return;
    }

    if ( $column_name === 'tier_data' ) {
      $tier_data = get_post_meta( $post_id, 'tier_data', true );
      $tier_slug = get_post_meta( $post_id, 'membership_tier_slug', true );
      echo '<pre>';
      print_r($tier_data);
      echo "MDP Tier Slug:";
      print_r($tier_slug);
      echo '</pre>';
    }
  }
  
  public function tier_id_post_link($actions, $post)
  {
      if ($post->post_type == $this->membership_tier_cpt_slug && ! empty( $_ENV['ALLOW_LOCAL_IMPORTS'] ))
      {
        $tier_uuid = get_post_meta($post->ID);
        $tier_uuid = unserialize($tier_uuid['tier_data'][0])['mdp_tier_uuid'];
        $individual_memberships = get_individual_memberships();
        foreach($individual_memberships['data'] as $tier) {
          $selected = $tier_uuid == $tier['id'] ? 'selected' : '';
          $options[] = '<option '.$selected.' value="'.$tier['id'].'">'.$tier['attributes']['name_en'].'</option>';
        }
        $select = '<select style="width:175px;" id="tier_post_'.$post->ID.'" name="tier_uuid_changed">'.implode("\n",$options).'</select>';
        $nonce = wp_create_nonce('tier_uuid_update_nonce');
        $actions['tier_uuid_change'] = '
          <a href="javascript:void(0)" class="show_change_tier_uuid" wicket-tier-id="'.$post->ID.'">Update UUID</a>
          <div id="tier_div_'.$post->ID.'" class="" style="display:none">'.$select.'
            <button id="tier_button_'.$post->ID.'" class="wicket_update_tier_uuid button" wicket-tier-post-id="'.$post->ID.'" data-nonce="'.$nonce.'">Set</button>
          </div>
        ';
      }
      return $actions;
  }

}
