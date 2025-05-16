<?php

namespace Wicket_Memberships;

use Wicket_Memberships\Helper;

defined( 'ABSPATH' ) || exit;

class Utilities {

  private $membership_cpt_slug = '';

  public function __construct() {
    if( $this->is_wicket_show_mship_order_org_search() ) {
      //org search metabox
      add_action( 'add_meta_boxes', [$this, 'wicket_sub_org_select_metabox'], 10, 2 );
      add_action('admin_enqueue_scripts', [$this, 'enqueue_suborg_scripts'] );
      add_action('wp_ajax_suborg_search', [$this, 'handle_suborg_search'] );
      add_action('woocommerce_process_shop_subscription_meta', [$this, 'set_subscription_postmeta_suborg_uuid']);
    }
    if(isset($_ENV['ALLOW_LOCAL_IMPORTS']) && $_ENV['ALLOW_LOCAL_IMPORTS']) {
      add_action('admin_enqueue_scripts', [$this, 'enqueue_suborg_scripts'] );
      //post_row_action tier uuid update
      add_action('wp_ajax_wicket_tier_uuid_update', [$this, 'handle_wicket_tier_uuid_update'] );
    }
    add_action('delete_user', [$this, 'handle_wp_delete_user'], 10, 3);
    $this->membership_cpt_slug = Helper::get_membership_cpt_slug();

    add_filter( 'woocommerce_cart_item_quantity', [$this, 'disable_cart_item_quantity'], 10, 3);
    add_filter( 'woocommerce_cart_item_remove_link', [$this, 'hide_cart_item_remove_link'], 10, 3);
    add_action( 'wp_trash_post', [$this, 'delete_wicket_membership_in_mdp' ], 10, 2);
    add_action( 'wp_trash_post', [$this, 'prevent_delete_linked_product' ], 10, 2);
    add_action( 'woocommerce_before_delete_product_variation', [$this, 'prevent_delete_linked_product' ], 10, 2);
    add_action('admin_notices', [$this, 'show_membership_product_delete_error'], 1);
    add_action('admin_notices', [$this, 'show_membership_delete_error'], 1);
    add_action('template_redirect', [$this, 'wicket_membership_clear_the_cart'], 10);
  }

  function wicket_membership_clear_the_cart() {
    if (is_cart() && isset($_GET['empty-cart']) && $_GET['empty-cart'] === 'true') {
        WC()->cart->empty_cart();
        wp_safe_redirect(wc_get_cart_url());
        exit;
    }
  }

  public static function wc_log_mship_error( $data, $level = 'error' ) {
    if (class_exists('WC_Logger')) {
      $logger = new \WC_Logger();
      if(is_array( $data )) {
        $data = wc_print_r( $data, true );
      }
      $logger->log($level, $data, ['source' => 'wicket-membership-plugin']);
    }
  }

  function delete_wicket_membership_in_mdp( $post_id ) {
    if(function_exists('wicket_delete_person_membership')) {
      $membership_wicket_uuid = get_post_meta( $post_id, 'membership_wicket_uuid', true);
      $membership_type = get_post_meta( $post_id, 'membership_type', true);
      if(!empty($membership_type) && !empty($membership_wicket_uuid)) {
        if($membership_type == 'individual') {
          $response = wicket_delete_person_membership( $membership_wicket_uuid );
        } else {
          $response = wicket_delete_organization_membership( $membership_wicket_uuid );
        }
        if( empty($response) || is_wp_error( $response ) ) {
          wp_redirect(get_admin_url() . "edit.php?post_type=wicket_membership&all_posts=1&show_membership_delete_error=1");
        }  
      }  
    }
  }

  function show_membership_delete_error() {
    if (isset($_GET['show_membership_delete_error'])) {
        echo '<div class="notice notice-error error is-dismissible" style="border-color: red; background-color: #ff000024;"><p><strong>WICKET MEMBERSHIP ERROR:</strong> THE MEMBERSHIP YOU ATTEMPTED TO DELETE WAS NOT SUCCESSFULLY REMOVED IN WICKET MDP OR MAY NOT EXIST. </p></div>';
    }
  }

  function show_membership_product_delete_error() {
    if (isset($_GET['show_membership_product_delete_error'])) {
        echo '<div class="notice notice-error error is-dismissible" style="border-color: red; background-color: #ff000024;"><p><strong>WICKET MEMBERSHIP ERROR:</strong> THE PRODUCT YOU ATTEMPTED TO DELETE IS ASSIGNED TO A MEMBERSHIP. It must first be removed from the Membership Tier or Config. You cannot trash or delete a product before removing your membership plugin assignment(s).</p></div>';
    }
  }

  function prevent_delete_linked_product($post_id, $post_status){
    $late_fee_product = false;
    $membership_categories = wicket_get_option('wicket_admin_settings_membership_categories');
    if (get_post_type($post_id) === 'product_variation') {
      $post_id = wp_get_post_parent_id($post_id);
    }

    if ( has_term( $membership_categories, 'product_cat', $post_id) ) {
      $membership_tier = Membership_Tier::get_tier_by_product_id( $post_id );
      $config_posts = get_posts(['post_type' => Helper::get_membership_config_cpt_slug(), 'numberposts' => -1]);
      foreach($config_posts as $config) {
        if( ( new Membership_Config($config->ID) )->get_late_fee_window_product_id() == $post_id) {
          $late_fee_product = true;
        }
      }
      if( $late_fee_product || !empty($membership_tier)) {
        wp_redirect(get_admin_url() . "/edit.php?post_type=product&all_posts=1&show_membership_product_delete_error=1");
        exit;
      }
    }
  }

  /**
 * Disable remove item on cart page when in Membership category.
 *
 * @param string $product_remove The remove html.
 * @param string $cart_item_key The cart item key.
 * @param array $cart_item The cart item object.
 * @return string The modified product quantity html (with hidden input).
 */
  function hide_cart_item_remove_link($product_remove, $cart_item_key)
  {
    if (is_cart()) {
      $item = WC()->cart->get_cart_item( $cart_item_key );
      $membership_categories = wicket_get_option('wicket_admin_settings_membership_categories');
      $category = get_term_by( 'slug', 'membership_late_fee', 'product_cat' );
      $cat_id = $category->term_id;
      $membership_categories[] = $cat_id;
      if ( has_term( $membership_categories, 'product_cat', $item['product_id']) ) {
        $product_remove = '<a href="'.esc_url( add_query_arg( 'empty-cart', 'true', wc_get_cart_url() ) ).'" class="remove" aria-label="Remove" onclick="event.stopImmediatePropagation(); return confirm(\''.__("This will empty the cart of all items.", 'wicket-memberships').'\');">×</a>';
      }
    }
    return $product_remove;
  }


  /**
 * Disable quantity input on cart page when in Membership category.
 *
 * @param string $product_quantity The current product quantity html.
 * @param string $cart_item_key The cart item key.
 * @param array $cart_item The cart item object.
 * @return string The modified product quantity html (with hidden input).
 */
  function disable_cart_item_quantity($product_quantity, $cart_item_key, $cart_item)
  {
    if (is_cart()) {
      $membership_categories = wicket_get_option('wicket_admin_settings_membership_categories');
      $category = get_term_by( 'slug', 'membership_late_fee', 'product_cat' );
      $cat_id = $category->term_id;
      $membership_categories[] = $cat_id;
      if ( has_term( $membership_categories, 'product_cat', $cart_item['product_id']) ) {
        $product_quantity = sprintf('<strong>%s</strong><input type="hidden" name="cart[%s][qty]" value="%s" />', $cart_item['quantity'], $cart_item_key, $cart_item['quantity']);
      }
    }
    return $product_quantity;
  }


  function handle_wp_delete_user( $user_id, $reassign = false, $user = false) {
    $args = array(
      'post_type' => $this->membership_cpt_slug,
      'post_status' => 'publish',
      'posts_per_page' => -1,
      'meta_query' => array(
        array(
          'key'     => 'user_id',
          'value'   => $user_id,
          'compare' => '='
        )
      )
    );
    $memberships = new \WP_Query( $args );
    foreach($memberships->posts as $mship) {
      if(get_post_meta( $mship->ID, 'user_email', true) == $user->user_email) {
        //clear the meta on the user for this membership
        $user_meta_removed = delete_user_meta( $user_id, '_wicket_membership_'.$mship->ID );
        if( $order_id = get_post_meta( $mship->ID, 'membership_parent_order_id', true)) {
          if(!empty($order_id)) {
            //clear the meta on the order for this membership
            $order_meta_removed = delete_post_meta( $order_id, '_wicket_membership_'.$mship->membership_product_id );
            $order = wc_get_order( $order_id );
            if(!empty($order)) {
              $order->add_order_note( "Membership ID: {$mship->ID} deleted when user deleted.");
              $order->add_order_note( " Order meta removed: ".$order_id . '_wicket_membership_'.$mship->membership_product_id);
              $order->add_order_note( " User meta removed: " . $user_id . '_wicket_membership_'.$mship->ID);
            }
          }
        }
        wp_delete_post($mship->ID);
      }
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

  function wicket_sub_org_select_metabox( $post_type, $post ) {
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

    $screen =  wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled( 'shop_subscription' ) ? 'woocommerce_page_wc-orders--shop_subscription' : 'shop_subscription';

        add_meta_box(
            'custom',
            __('Membership Order > SELECT Organization'),
            [$this, 'wicket_sub_org_select_callback'],
            $screen,
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