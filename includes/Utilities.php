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
    //add_filter( 'woocommerce_cart_item_remove_link', [$this, 'hide_cart_item_remove_link'], 10, 3);
    add_action( 'wp_trash_post', [$this, 'delete_wicket_membership_in_mdp' ], 10, 2);
    add_action( 'wp_trash_post', [$this, 'prevent_delete_linked_product' ], 10, 2);
    add_action( 'woocommerce_before_delete_product_variation', [$this, 'prevent_delete_linked_product' ], 10, 2);
    add_action('admin_notices', [$this, 'show_membership_product_delete_error'], 1);
    add_action('admin_notices', [$this, 'show_membership_delete_error'], 1);
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
      if ( has_term( $membership_categories, 'product_cat', $item['product_id']) ) {
        $product_remove = '';
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

  public static function autorenew_checkbox_toggle_switch() {
    if(!empty($_ENV['WICKET_MSHIP_AUTORENEW_TOGGLE'])) {
      add_action('wp', [__NAMESPACE__.'\\Utilities', 'wc_autorenew_toggle_filters']);
      add_action('wp_footer', [__NAMESPACE__.'\\Utilities', 'wicket_wc_enqueue_scripts_autorenew_toggle']);
      add_action('wp_ajax_auto_renew_enabled_for_user', [__NAMESPACE__.'\\Utilities', 'handle_user_auto_renew_toggle']);
      add_action('wp_ajax_nopriv_auto_renew_enabled_for_user', [__NAMESPACE__.'\\Utilities', 'handle_user_auto_renew_toggle']); // Allow guests if needed
      add_action('wp_enqueue_scripts', [__NAMESPACE__.'\\Utilities', 'enqueue_mship_ajax_script']);
    }
  }

  static function wc_autorenew_toggle_filters() {
    $self = new self();
    add_shortcode('wicket_autorenew_toggle_shortcode', [$self, 'wc_autorenew_toggle_shortcode']);
    add_filter('gform_field_value_wicket_autorenew_toggle_shortcode', function($value) {
      return do_shortcode('[wicket_autorenew_toggle_shortcode]');
    });
  }

  /**
   * Summary of wc_autorenew_toggle_shortcode
   * @param mixed $atts
   * @return void
   */
  function wc_autorenew_toggle_shortcode($atts) {
    if($_REQUEST['subscription_id']) {
      $subscription_id = !empty(intval($atts['subscription_id'])) ? intval($atts['subscription_id']) : intval($_REQUEST['subscription_id']);
    } else if($_REQUEST['membership_post_id_renew']) {
      $subscription_id = get_post_meta( intval($_REQUEST['membership_post_id_renew']), 'membership_subscription_id', true );
    }
    if(!empty($subscription_id)) {
      $sub = wcs_get_subscription( $subscription_id );
      if(!empty($sub)) {
        $is_autopay_enabled = !empty($sub->get_requires_manual_renewal()) ? false : true;
      }  
      $checked = !empty($is_autopay_enabled) ? 'checked' : '';
    } else {
      $user_autopay_enabled = get_user_meta( get_current_user_id(), 'subscription_autopay_enabled', true);
      $checked = !empty($user_autopay_enabled) && $user_autopay_enabled == 'yes' ? 'checked' : '';
    }
    ob_start();
    ?>
    <label class="wicket-wc-toggle">
      <input type="checkbox" id="wicket-wc-toggle" <?php echo $checked; ?>>
      <span class="slider"></span>
    </label>
    <?php
    return ob_get_clean();
  }

  /**
   *  Includes the styling and js for the autorenew toggle in the footer
   * 
   *  <label class="wicket-wc-toggle">
   *    <input type="checkbox" id="wicket-wc-toggle">
   *    <span class="slider"></span>
   *  </label>
   * @return void
   */
  public static function wicket_wc_enqueue_scripts_autorenew_toggle() {
    wp_localize_script('auto_renew_enabled_for_user', 
      'wicket_mship_ajax_object', 
      ['ajaxurl' => admin_url('admin-ajax.php'), 
      'user_id' => get_current_user_id()
      ]
    );
    ?>
    <style>
        .wicket-wc-toggle {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .wicket-wc-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .wicket-wc-toggle .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            border-radius: 12px;
            transition: 0.3s;
        }

        .wicket-wc-toggle .slider::before {
            content: "";
            position: absolute;
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }

        .wicket-wc-toggle input:checked + .slider {
            background-color: #2c6fbb;
        }

        .wicket-wc-toggle input:checked + .slider::before {
            transform: translateX(26px);
        }
    </style>

    <script>
      jQuery(document).ready(function($) {
          $('#wicket-wc-toggle').on('change', function() {
              var isChecked = $(this).is(':checked') ? 1 : 0;
              $.ajax({
                  url: wicket_mship_ajax_object.ajaxurl,
                  type: 'POST',
                  data: {
                      action: 'auto_renew_enabled_for_user',
                      user_id: wicket_mship_ajax_object.user_id,
                      <?php
                        if(!empty($_REQUEST['subscription_id'])) {
                          echo "subscription_id: '".$_REQUEST['subscription_id']."',\n";
                        }
                        if(!empty($_REQUEST['membership_post_id_renew'])) {
                          echo "membership_post_id_renew: '".$_REQUEST['membership_post_id_renew']."',\n";
                        }
                      ?>
                      enabled: isChecked
                  },
                  success: function(response) {
                      console.log('Success:', response);
                  },
                  error: function(error) {
                      console.log('Error:', error);
                  }
              });
          });
      });
    </script>
    <?php  
  }
  
  public static function handle_user_auto_renew_toggle() {
    if (!isset($_POST['user_id']) || !isset($_POST['enabled'])) {
        wp_send_json_error(['message' => 'Invalid request.']);
    }
    $user_id = intval($_POST['user_id']);
    $enabled = $_POST['enabled'] == 1 ? 'yes' : 'no';
    if( isset($_POST['subscription_id']) ) {
      $subscription_id = $_POST['subscription_id'];
    } else if( isset($_POST['membership_post_id_renew']) ) {
      $subscription_id = get_post_meta( intval($_REQUEST['membership_post_id_renew']), 'membership_subscription_id', true );
    }
    if(!empty($subscription_id)) {
      $subscription = wcs_get_subscription($subscription_id);
      $subscription_renewal_boolean = $enabled == 'yes' ? 'false' : 'true'; //reverse to set manual_renewal_enabeld;
      $subscription->update_meta_data('_requires_manual_renewal', $subscription_renewal_boolean);
      $subscription->save();
      $subscription->add_order_note('Customer turned '.($enabled == 'yes' ? 'on' : 'off').' automatic renewals via the Wicket Toggle. Manual renewal flag set to '.$subscription_renewal_boolean);
    }
    update_user_meta($user_id, 'subscription_autopay_enabled', $enabled);
    wp_send_json_success(['message' => 'Auto-renew status updated for '.$user_id, 'status' => $enabled]);
  }

  public static function enqueue_mship_ajax_script() {
    wp_enqueue_script('ajax-script', get_template_directory_uri() . '/js/custom-ajax.js', ['jquery'], null, true);
    wp_localize_script('ajax-script', 'wicket_mship_ajax_object', [
        'ajaxurl' => admin_url('admin-ajax.php'), 
        'user_id' => get_current_user_id()
    ]);
  }
}