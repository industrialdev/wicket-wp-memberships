<?php

namespace Wicket_Memberships;

defined( 'ABSPATH' ) || exit;

class Utilities {

  public function __construct() {
    if( $this->is_wicket_show_mship_order_org_search() ) {
      //org search metabox
      add_action( 'add_meta_boxes', [$this, 'wicket_sub_org_select_metabox'] );
      add_action('admin_enqueue_scripts', [$this, 'enqueue_suborg_scripts'] );
      add_action('wp_ajax_suborg_search', [$this, 'handle_suborg_search'] );
      add_action('save_post', [$this, 'set_subscription_postmeta_suborg_uuid']);
    }
    if(isset($_ENV['ALLOW_LOCAL_IMPORTS']) && $_ENV['ALLOW_LOCAL_IMPORTS']) {
      add_action('admin_enqueue_scripts', [$this, 'enqueue_suborg_scripts'] );
      //post_row_action tier uuid update
      add_action('wp_ajax_wicket_tier_uuid_update', [$this, 'handle_wicket_tier_uuid_update'] );
    }
  }

  function is_wicket_show_mship_order_org_search() {
    $options = get_option( 'wicket_membership_plugin_options' );
    if(isset($options['wicket_show_mship_order_org_search'])) {
      if(!empty($options['wicket_show_mship_order_org_search'])) {
        return true;
      }
    }
    return false;
  }

  function set_subscription_postmeta_suborg_uuid() {
    if(!empty($_REQUEST['wicket_sub_org_select_uuid'])) {
      wc_update_order_item_meta( $_REQUEST['wicket_update_suborg_item_id'], '_org_uuid', $_REQUEST['wicket_sub_org_select_uuid'] );
    }  
  }

  function wicket_sub_org_select_metabox() {
    global $post;
    $sub = wcs_get_subscription($post->ID);
    if(empty($sub)) {
      return;
    }
    $options = get_option( 'wicket_membership_plugin_options' );
    $categories_selected = $options['wicket_show_mship_order_org_search']['categorychoice'];
    $subscription_products = $sub->get_items();
    foreach( $subscription_products as $item ) {
      if(empty($product_id)) {
        $product_id = $item->get_product_id();
      }
      if ( ! has_term( $categories_selected, 'product_cat', $item->get_product_id() )
          && ! has_term( $categories_selected, 'product_cat', $item->get_variation_id() ) ) {
        continue;
      }
      if( $item->get_meta('_org_uuid' ,true)) {
        continue;
      }
      $org_uuid_missing = true;
    }
    if(empty($org_uuid_missing)) {
      return;
    }
        add_meta_box(
            'custom',
            __('Membership Order > SELECT Organization'),
            [$this, 'wicket_sub_org_select_callback'],
            'shop_subscription',
            'normal',
            'high'
        );
}

function wicket_sub_org_select_callback( $subscription ) {
      $org_uuid = '';
      $sub = wcs_get_subscription($subscription);
      if(empty($sub)) {
        return;
      }
      $subscription_products = $sub->get_items();
      foreach( $subscription_products as $item ) {
        $product_id = $item->get_variation_id();
        if(empty($product_id)) {
          $product_id = $item->get_product_id();
        }
        if ( ! has_term( 'Membership', 'product_cat', $product_id )) {
          continue;
        }
      }
      if(!empty($item->get_meta('_org_uuid'))){
        return;
      }
      wp_nonce_field('suborg_nonce', 'suborg_nonce_field');
    ?>
    <div class="search-container">
      <input type="hidden" id="suborg-search-id" name="wicket_sub_org_select_uuid" value="<?php echo $org_uuid;?>">
      <input type="hidden" id="suborg-search-item-id" name="wicket_update_suborg_item_id" value="<?php echo $item->get_id();?>">
      <input type="text" id="suborg-search" name="wicket_sub_org_select_name" class="woocommerce-input" value="<?php echo $org_uuid;?>" placeholder="Organization Search..." />
      <div id="suborg-results" class="woocommerce-results"></div>
    </div>
    <style>
      /* Container for the search input and results */
      .search-container {
          position: relative; /* For positioning the results */
      }

      /* Style the input field */
      .woocommerce-input {
          width: 100%; /* Full width */
          padding: 12px; /* Padding for comfortable click area */
          border: 1px solid #ccc; /* Border color */
          border-radius: 4px; /* Rounded corners */
          background-color: #fff; /* Background color */
          font-size: 16px; /* Font size */
          color: #333; /* Text color */
          transition: border-color 0.3s ease; /* Smooth border transition */
      }

      /* Input focus style */
      .woocommerce-input:focus {
          border-color: #0071a1; /* Change border color on focus */
          outline: none; /* Remove default outline */
      }

      /* Style for results container */
      .woocommerce-results {
          position: absolute; /* Position results below the input */
          top: 100%; /* Align to the bottom of the input */
          left: 0; /* Align to the left */
          right: 0; /* Stretch to the right */
          background-color: #fff; /* Background color */
          border: 1px solid #ccc; /* Border around results */
          border-radius: 4px; /* Rounded corners */
          z-index: 999; /* Ensure it appears above other elements */
          max-height: 200px; /* Limit height for scrolling */
          overflow-y: auto; /* Scroll if too many results */
          display: none; /* Initially hidden */
      }

      /* Individual result item */
      .woocommerce-results .result-item {
          padding: 10px; /* Padding for items */
          cursor: pointer; /* Pointer cursor on hover */
          color: #333; /* Text color */
      }

      /* Hover effect for result items */
      .woocommerce-results .result-item:hover {
          background-color: #f7f7f7; /* Change background on hover */
      }

      /* No results found message */
      .woocommerce-results .no-results {
          padding: 10px; /* Padding for no results */
          color: #999; /* Color for no results text */
          text-align: center; /* Center text */
      }
    </style>
    <?php
  }

  function enqueue_suborg_scripts() {
    wp_enqueue_script('jquery');
    wp_enqueue_script('custom-suborg', plugins_url('../assets/js/wicket_suborg.js', __FILE__), array('jquery'), null, true);
    wp_localize_script('custom-suborg', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
  }

  function handle_suborg_search() {
    check_ajax_referer('suborg_nonce', 'nonce');
    $search_term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';
    $search_json = json_encode(['searchTerm' => $search_term, 'autocomplete' => true]);  
    $request = new \WP_REST_Request('POST');
    $request->set_headers(['Content-Type' => 'application/json']);
    $request->set_body($search_json); // Set the body as the JSON string
    $results = wicket_internal_endpoint_search_orgs( $request);
    wp_reset_postdata();
    wp_send_json($results);
  }

    /**
   * Ipdate the tier uuid using Wicket Tier Page [post_row_action], necessary for migrating a tier data from staging to production
   * @return json
   */
  function handle_wicket_tier_uuid_update() {
    check_ajax_referer('tier_uuid_update_nonce', 'nonce');
    $new_tier_uuid = isset($_POST['tierUUID']) ? sanitize_text_field($_POST['tierUUID']) : '';
    $tier_post_id = isset($_POST['postID']) ? sanitize_text_field($_POST['postID']) : '';
    $tier_data = get_post_meta($tier_post_id, 'tier_data');
    $tier_data[0]['mdp_tier_uuid'] = $new_tier_uuid;
    try {
      update_post_meta(($tier_post_id), 'tier_data', $tier_data[0]);
      wp_send_json_success($tier_data[0]);
    } catch (\Exception $e) {
      wp_send_json_error($tier_data[0]);
    }
    wp_reset_postdata();
  }
}