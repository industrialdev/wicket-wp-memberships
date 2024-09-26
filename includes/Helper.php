<?php

namespace Wicket_Memberships;

defined( 'ABSPATH' ) || exit;

class Helper {

  public function __construct() {
    if( !empty( $_ENV['WICKET_SHOW_ORDER_DEBUG_DATA'] ) ) {
      // INJECT MEMBERSHIP META DATA into order and subscription and member pages -- org_id on checkout page
      add_action( 'woocommerce_admin_order_data_after_shipping_address', [$this, 'wps_select_checkout_field_display_admin_order_meta'], 10, 1 );
      add_action( 'wcs_subscription_details_table_before_dates', [$this, 'wps_select_checkout_field_display_admin_order_meta'], 10, 1 );
    }
    if( !empty( $_ENV['WICKET_MEMBERSHIPS_DEBUG_MODE'] ) ) {
      // INJECT MEMBERSHIP META DATA into membership pages
      add_action( 'init', [ $this, 'update_user_meta_from_post_data'] );
    }
  }

  

  function wps_select_checkout_field_display_admin_order_meta( $post ) {
    $post_meta = get_post_meta( $post->get_id() );
    foreach($post_meta as $key => $val) {
    if( str_starts_with( $key, '_wicket_membership_')) {
        echo '<br>'.$post->get_id().'<strong>'.$key.':</strong><pre>';var_dump( json_decode( maybe_unserialize( $val[0] ), true) ); echo '</pre>';
      }
    }
  }

  public static function get_wp_languages_iso() {
    // get WPML active languages if WPML is installed and active
    if ( has_filter( 'wpml_active_languages' ) ) {
      $languages = apply_filters( 'wpml_active_languages', NULL, 'orderby=id&order=desc' );
      $language_codes = array_map( function( $lang ) {
        return $lang['code'];
      }, $languages );
      array_unique( $language_codes );

      return $language_codes;
    }

    // If WPML is not installed or active, return the default WP language
    $locale = get_locale(); // Get the full locale (e.g., en_US)
    $iso_code = substr($locale, 0, 2); // Extract the first two characters
    return [ $iso_code ];
  }

  public static function get_membership_config_cpt_slug() {
    return 'wicket_mship_config';
  }

  public static function get_membership_cpt_slug() {
    return 'wicket_membership';
  }

  public static function get_membership_tier_cpt_slug() {
    return 'wicket_mship_tier';
  }

  public static function is_valid_membership_post( $membership_post_id ) {
    return ( !empty( get_post_status( $membership_post_id ) ) && get_post_status( $membership_post_id ) == 'publish' );
  }

  public static function get_all_status_names() {
    return [
      Wicket_Memberships::STATUS_ACTIVE => [
        'name' => __('Active', 'wicket-memberships'),
        'slug' => Wicket_Memberships::STATUS_ACTIVE
      ],
      Wicket_Memberships::STATUS_GRACE => [
        'name' => __('Grace Period', 'wicket-memberships'),
        'slug' => Wicket_Memberships::STATUS_GRACE
      ],
      Wicket_Memberships::STATUS_PENDING => [
        'name' => __('Pending Approval', 'wicket-memberships'),
        'slug' => Wicket_Memberships::STATUS_PENDING
      ],
      Wicket_Memberships::STATUS_DELAYED => [
        'name' => __('Delayed', 'wicket-memberships'),
        'slug' => Wicket_Memberships::STATUS_DELAYED
      ],
      Wicket_Memberships::STATUS_CANCELLED => [
        'name' => __('Cancelled', 'wicket-memberships'),
        'slug' => Wicket_Memberships::STATUS_CANCELLED
      ],
      Wicket_Memberships::STATUS_EXPIRED => [
        'name' => __('Expired', 'wicket-memberships'),
        'slug' => Wicket_Memberships::STATUS_EXPIRED
      ],
    ];
  }

  public static function get_allowed_transition_status( $status ) {
    if( !empty( $_ENV['BYPASS_STATUS_CHANGE_LOCKOUT'] ) ) {
      return self::get_all_status_names();
    }
    if( $status == Wicket_Memberships::STATUS_PENDING ) {
      return [
        Wicket_Memberships::STATUS_ACTIVE => [
          'name' => __('Active', 'wicket-memberships'),
          'slug' => Wicket_Memberships::STATUS_ACTIVE
        ],
        Wicket_Memberships::STATUS_CANCELLED => [
          'name' => __('Cancelled', 'wicket-memberships'),
          'slug' => Wicket_Memberships::STATUS_CANCELLED
        ],
      ];
    } else if( $status == Wicket_Memberships::STATUS_DELAYED ) {
      return [
        Wicket_Memberships::STATUS_CANCELLED => [
          'name' => __('Cancelled', 'wicket-memberships'),
          'slug' => Wicket_Memberships::STATUS_CANCELLED
        ],
      ];
    } else if( $status == Wicket_Memberships::STATUS_GRACE ) {
      return [
        Wicket_Memberships::STATUS_EXPIRED => [
          'name' => __('Expired', 'wicket-memberships'),
          'slug' => Wicket_Memberships::STATUS_EXPIRED
        ],
        Wicket_Memberships::STATUS_CANCELLED => [
          'name' => __('Cancelled', 'wicket-memberships'),
          'slug' => Wicket_Memberships::STATUS_CANCELLED
        ],
      ];
    } else if( $status == Wicket_Memberships::STATUS_ACTIVE ) {
      return [
        Wicket_Memberships::STATUS_CANCELLED => [
          'name' => __('Cancelled', 'wicket-memberships'),
          'slug' => Wicket_Memberships::STATUS_CANCELLED
        ],
      ];
    } else {
      return new \StdClass();
    }
  }

  /**
   * Convert json membership data to post_data
   *
   * @param string|array $membership_json json or array
   * @param boolean $json_encoded is this json encoded or array data
   * @return array
   */
  public static function get_membership_post_data_from_membership_json( $membership_json, $json_encoded = true ) {
    $membership_post_data = array();
    if( $json_encoded === true ) {
      $membership_array = json_decode( $membership_json, true);
    } else {
      $membership_array = $membership_json;
    }
    $mapping_keys = [
       'membership_wp_user_display_name' => 'user_name',
       'user_name' => 'membership_wp_user_display_name',
       'membership_wp_user_email' => 'user_email',
       'user_email' => 'membership_wp_user_email',
       'organization_name' => 'org_name',
       'org_name' => 'organization_name',
       'organization_location' => 'org_location',
       'org_location' => 'organization_location',
       'organization_uuid' => 'org_uuid',
       'org_uuid' => 'organization_uuid',
       'membership_seats' => 'org_seats',
       'org_seats' => 'membership_seats',
    ];
    array_walk(
      $membership_array,
      function(&$val, $key) use (&$membership_post_data, $mapping_keys)
      {
        $new_key = $mapping_keys[$key];
        if( empty($new_key) ) {
          $membership_post_data[$key] = $val;
        } else {
          $membership_post_data[$new_key] = $val;
        }
      }
    );
    return $membership_post_data;
  }


  /**
   * Convert post_data to json membership data
   *
   * @param array $membership_array  array
   * @param boolean $json_encode return json encoded or array data
   * @return string|array
   */
  public static function get_membership_json_from_membership_post_data( $membership_array, $json_encode = true ) {
    $membership_json_data = array();
    $mapping_keys = [
        'user_name' => 'membership_wp_user_display_name',
        'user_email' => 'membership_wp_user_email',
        'org_name' => 'organization_name',
        'org_location' => 'organization_location',
        'org_uuid' => 'organization_uuid',
        'org_seats' => 'membership_seats',
    ];
    array_walk(
      $membership_array,
      function(&$val, $key) use (&$membership_json_data, $mapping_keys)
      {
        $new_key = $mapping_keys[$key];
        if( empty($new_key) ) {
          $membership_json_data[$key] = $val;
        }
        $membership_json_data[$new_key] = $val;
      }
    );
    if( $json_encode === true ) {
      $membership_json = json_encode( $membership_json_data );
      return $membership_json;
    } else {
      return $membership_json_data;
    }
  }

  public function update_user_meta_from_post_data( $post_id ) {
    if(! $_REQUEST['wicket_update_order_meta_from_mship_post']) {
      return;
    }
    $post_meta = get_post_meta( $_REQUEST['wicket_post_id'] );
    $new_meta = [];
    array_walk(
      $post_meta,
      function(&$val, $key) use ( &$new_meta )
      {
        if( str_starts_with( $key, '_' ) ) {
          return;
        }
        if( str_ends_with( $key, '_at' ) ) {
          return $new_meta[$key] = (new \DateTime( $val[0], wp_timezone() ))->format('c'); //= date( "Y-m-d", strtotime( $val[0] ));
        }
        return $new_meta[$key] = $val[0];
      }
    );
    $new_meta['membership_post_id'] = $_REQUEST['wicket_post_id'];

    $mapping_keys = [
      'membership_wp_user_display_name' => 'user_name',
      'user_name' => 'membership_wp_user_display_name',
      'membership_wp_user_email' => 'user_email',
      'user_email' => 'membership_wp_user_email',
      'organization_name' => 'org_name',
      'org_name' => 'organization_name',
      'organization_location' => 'org_location',
      'org_location' => 'organization_location',
      'organization_uuid' => 'org_uuid',
      'org_uuid' => 'organization_uuid',
      'membership_seats' => 'org_seats',
      'org_seats' => 'membership_seats',
   ];

   $membership_post_data = [];
   array_walk(
     $new_meta,
     function(&$val, $key) use (&$membership_post_data, $mapping_keys)
     {
       $new_key = $mapping_keys[$key];
       if( empty($new_key) ) {
         $membership_post_data[$key] = $val;
       } else {
         $membership_post_data[$new_key] = $val;
       }
     }
   );

    //echo '<pre>'; var_dump($membership_post_data);exit;
    update_user_meta($new_meta['user_id'], '_wicket_membership_'.$new_meta['membership_post_id'], json_encode( $new_meta) );
    update_post_meta($new_meta['membership_parent_order_id'], '_wicket_membership_'.$new_meta['membership_product_id'], json_encode( $membership_post_data) );
    //update_post_meta($new_meta['membership_subscription_id'], '_wicket_membership_'.$new_meta['membership_product_id'], json_encode( $membership_post_data) );
  }

  public static function get_org_data( $org_uuid, $bypass_lookup = false, $force_lookup = false ) {
    $org_data = json_decode( get_option( 'org_data_'. $org_uuid ), true);
    if(empty( $org_data ) && $bypass_lookup ) {
      return ['name' => '', 'location' => ''];
    }

    //var_dump($org_data);exit;
    if( empty( $org_data['data']['attributes']['alternate_name'] ) || $force_lookup) {
      $org_data = self::store_an_organizations_data_in_options_table($org_uuid, $force_lookup);
    }

    if( ! empty( $org_data['included'][0]['attributes']['city'] ) ) {
      $data['location'] = $org_data['included'][0]['attributes']['city'] . ', ';
      $data['location'] .= $org_data['included'][0]['attributes']['state_name'] . ', ';
      $data['location'] .= $org_data['included'][0]['attributes']['country_code'];
    } else {
      $data['location'] = '';
    }
    $data['name'] = $org_data['data']['attributes']['alternate_name'];
    return $data;
  }

  public static function store_an_organizations_data_in_options_table($org_uuid, $force_update = false ) {
    if( !($org_data = get_option('org_data_'.  $org_uuid)) || $force_update) {
      $org_data = wicket_get_organization($org_uuid, 'addresses' );
      add_option('org_data_'.$org_uuid, json_encode( $org_data) );
    }
    return $org_data;
  }


  public static function get_post_meta( $post_id ) {
    $post_meta = get_post_meta( $post_id );
    $new_meta = [];
    array_walk(
      $post_meta,
      function(&$val, $key) use ( &$new_meta )
      {
        if( str_starts_with( $key, '_' ) ) {
          return;
        }
        if( str_ends_with( $key, '_at' ) ) {
          return $new_meta[$key] = date( "Y-m-d", strtotime( $val[0] ));
        }
        return $new_meta[$key] = $val[0];
      }
    );
    return $new_meta;
  }

  public static function force_renewal_for_subscription($subscription_id) {
    if (!class_exists('WC_Subscriptions')) {
        return false; // WooCommerce Subscriptions plugin is not active
    }

    // Get the subscription object
    $subscription = wcs_get_subscription($subscription_id);
    if (!$subscription) {
        return false; // Subscription not found
    }

    // Force renewal
    $order = wcs_create_renewal_order($subscription_id);

    if ($order) {
        // Optional: Update the order status if needed
        $order->update_status('pending'); // or 'processing', 'completed', etc.

        // Optional: You can add custom data to the order if needed
        // Example: Add a note
        $order->add_order_note('This renewal order was created programmatically.');

        // Save the order
        $order->save();

        echo 'Renewal order created successfully.';
    } else {
        echo 'Failed to create renewal order.';
    }
}

  public static function wicket_duplicate_order( $original_order_id ) {

    // Load Original Order
    $original_order = wc_get_order( $original_order_id );
    $user_id = $original_order->get_user_id();
  
    // Setup Cart
    WC()->frontend_includes();
    WC()->session = new WC_Session_Handler();
    WC()->session->init();
    WC()->customer = new WC_Customer( $user_id, true );
    WC()->cart = new WC_Cart();
  
    // Setup New Order
    $checkout = WC()->checkout();
    WC()->cart->calculate_totals();
    $order_id = $checkout->create_order( [ ] );
    $order = wc_get_order( $order_id );
    $order->update_meta_data( '_customer_user', $original_order->get_meta( '_order_shipping' ) );
  
    // Header
    $order->update_meta_data( '_order_shipping', $original_order->get_meta( '_order_shipping' ) );
    $order->update_meta_data( '_order_discount', $original_order->get_meta( '_order_discount' ) );
    $order->update_meta_data( '_cart_discount', $original_order->get_meta( '_cart_discount' ) );
    $order->update_meta_data( '_order_tax', $original_order->get_meta( '_order_tax' ) );
    $order->update_meta_data( '_order_shipping_tax', $original_order->get_meta( '_order_shipping_tax' ) );
    $order->update_meta_data( '_order_total', $original_order->get_meta( '_order_total' ) );
    $order->update_meta_data( '_order_key', 'wc_' . apply_filters( 'woocommerce_generate_order_key', uniqid( 'order_' ) ) );
    $order->update_meta_data( '_customer_user', $original_order->get_meta( '_customer_user' ) );
    $order->update_meta_data( '_order_currency', $original_order->get_meta( '_order_currency' ) );
    $order->update_meta_data( '_prices_include_tax', $original_order->get_meta( '_prices_include_tax' ) );
    $order->update_meta_data( '_customer_ip_address', $original_order->get_meta( '_customer_ip_address' ) );
    $order->update_meta_data( '_customer_user_agent', $original_order->get_meta( '_customer_user_agent' ) );
  
    // Billing
    $order->update_meta_data( '_billing_city', $original_order->get_meta( '_billing_city' ) );
    $order->update_meta_data( '_billing_state', $original_order->get_meta( '_billing_state' ) );
    $order->update_meta_data( '_billing_postcode', $original_order->get_meta( '_billing_postcode' ) );
    $order->update_meta_data( '_billing_email', $original_order->get_meta( '_billing_email' ) );
    $order->update_meta_data( '_billing_phone', $original_order->get_meta( '_billing_phone' ) );
    $order->update_meta_data( '_billing_address_1', $original_order->get_meta( '_billing_address_1' ) );
    $order->update_meta_data( '_billing_address_2', $original_order->get_meta( '_billing_address_2' ) );
    $order->update_meta_data( '_billing_country', $original_order->get_meta( '_billing_country' ) );
    $order->update_meta_data( '_billing_first_name', $original_order->get_meta( '_billing_first_name' ) );
    $order->update_meta_data( '_billing_last_name', $original_order->get_meta( '_billing_last_name' ) );
    $order->update_meta_data( '_billing_company', $original_order->get_meta( '_billing_company' ) );
  
    // Shipping
    $order->update_meta_data( '_shipping_country', $original_order->get_meta( '_shipping_country' ) );
    $order->update_meta_data( '_shipping_first_name', $original_order->get_meta( '_shipping_first_name' ) );
    $order->update_meta_data( '_shipping_last_name', $original_order->get_meta( '_shipping_last_name' ) );
    $order->update_meta_data( '_shipping_company', $original_order->get_meta( '_shipping_company' ) );
    $order->update_meta_data( '_shipping_address_1', $original_order->get_meta( '_shipping_address_1' ) );
    $order->update_meta_data( '_shipping_address_2', $original_order->get_meta( '_shipping_address_2' ) );
    $order->update_meta_data( '_shipping_city', $original_order->get_meta( '_shipping_city' ) );
    $order->update_meta_data( '_shipping_state', $original_order->get_meta( '_shipping_state' ) );
    $order->update_meta_data( '_shipping_postcode', $original_order->get_meta( '_shipping_postcode' ) );
  
    // Shipping Items
    $original_order_shipping_items = $original_order->get_items( 'shipping' );
    foreach( $original_order_shipping_items as $original_order_shipping_item ) {
      $item_id = wc_add_order_item( $order_id, array(
        'order_item_name' => $original_order_shipping_item['name'],
        'order_item_type' => 'shipping'
      ) );
      if( $item_id ) {
        wc_add_order_item_meta( $item_id, 'method_id', $original_order_shipping_item['method_id'] );
        wc_add_order_item_meta( $item_id, 'cost', wc_format_decimal( $original_order_shipping_item['cost'] ) );
      }
    }
  
    // Coupons
    $original_order_coupons = $original_order->get_items( 'coupon' );
    foreach( $original_order_coupons as $original_order_coupon ) {
      $item_id = wc_add_order_item( $order_id, array(
        'order_item_name' => $original_order_coupon['name'],
        'order_item_type' => 'coupon'
      ) );
      if ( $item_id ) {
        wc_add_order_item_meta( $item_id, 'discount_amount', $original_order_coupon['discount_amount'] );
      }
    }
  
    // Payment
    $order->update_meta_data( '_payment_method', $original_order->get_meta( '_payment_method' ) );
    $order->update_meta_data( '_payment_method_title', $original_order->get_meta( '_payment_method_title' ) );
    $order->update_meta_data( 'Transaction ID', $original_order->get_meta( 'Transaction ID' ) );
  
    // Line Items
    foreach( $original_order->get_items() as $originalOrderItem ) {
      $itemName = $originalOrderItem['name'];
      $qty = $originalOrderItem['qty'];
      $lineTotal = $originalOrderItem['line_total'];
      $lineTax = $originalOrderItem['line_tax'];
      $productID = $originalOrderItem['product_id'];
      $item_id = wc_add_order_item( $order_id, [
          'order_item_name' => $itemName,
          'order_item_type' => 'line_item'
      ] );
      wc_add_order_item_meta( $item_id, '_qty', $qty );
      wc_add_order_item_meta( $item_id, '_tax_class', $originalOrderItem['tax_class'] );
      wc_add_order_item_meta( $item_id, '_product_id', $productID );
      wc_add_order_item_meta( $item_id, '_variation_id', $originalOrderItem['variation_id'] );
      wc_add_order_item_meta( $item_id, '_line_subtotal', wc_format_decimal( $lineTotal ) );
      wc_add_order_item_meta( $item_id, '_line_total', wc_format_decimal( $lineTotal ) );
      wc_add_order_item_meta( $item_id, '_line_tax', wc_format_decimal( $lineTax ) );
      wc_add_order_item_meta( $item_id, '_line_subtotal_tax', wc_format_decimal( $originalOrderItem['line_subtotal_tax'] ) );
    }
  
    // Close New Order
    $order->save();
    $order->calculate_totals();
    //$order->payment_complete();
    //$order->update_status( 'processing' );
  
    // Note
    $message = sprintf(
      'This order was duplicated from order %d.',
      $original_order_id
    );
    $order->add_order_note( $message );
  
    // Return
    return $order_id;
  
  }
}