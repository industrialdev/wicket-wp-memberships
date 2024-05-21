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
    if( !empty( $_ENV['WICKET_SHOW_MEMBERSHIP_DEBUG_DATA'] ) ) {
      // INJECT MEMBERSHIP META DATA into membership pages
      add_action( 'add_meta_boxes', [$this, 'extra_info_add_meta_boxes'] );
      //add_action( 'add_meta_boxes', [$this, 'action_buttons_add_meta_boxes'] );
      add_action( 'admin_menu', function() {
          remove_meta_box( 'extra_info_data', self::get_membership_cpt_slug(), 'normal' );
      } );
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
  
  // TEMPORARILY INJECT MEMBERSHIP META DATA into membership pages
  function action_buttons_add_meta_boxes() {
    global $post;
    add_meta_box( 'action_buttons_add_meta_boxes', __('[do_action] Buttons','your_text_domain'), [$this, 'display_action_buttons'], self::get_membership_cpt_slug(), 'side', 'core' );
  }

  function display_action_buttons() {
    global $post;
    $order_id = get_post_meta( $post->ID, 'membership_parent_order_id', true );
    $product_id = get_post_meta( $post->ID, 'membership_product_id', true );
    ?>
      <input type="submit" name="wicket_do_action_early_renew_at" value="Early Renew"><br>
      <input type="submit" name="wicket_do_action_ends_at" value="Ends At"><br>
      <input type="submit" name="wicket_do_action_expires_at" value="Grace Period"><br>
      membership_parent_order_id<br>
      <input type="text" name="wicket_order_id" value="<?php echo $order_id; ?>"><br>
      membership_product_id<br>
      <input type="text" name="wicket_product_id" value="<?php echo $product_id; ?>">
    <?php
  }

  function extra_info_add_meta_boxes()
  {
    global $post;
    add_meta_box( 'extra_info_data_content', __('Extra Info','your_text_domain'), [$this, 'extra_info_data_contents'], self::get_membership_cpt_slug(), 'normal', 'core' );
  }
  
  // TEMPORARILY INJECT MEMBERSHIP META DATA into membership pages
  function extra_info_data_contents()
  {
    global $post;
    $post_meta = get_post_meta( $post->ID );
    $new_meta = [];
    array_walk(
      $post_meta,
      function(&$val, $key) use ( &$new_meta )
      {
        if( str_starts_with( $key, '_' ) ) {
          return;
        }
        $new_meta[$key] = $val[0];
      }
    );
    $mship_product_id = get_post_meta( $post->ID, 'membership_product_id', true );
    echo '<table><tr><td valign="top"><h3>Post Data</h3><pre>';
    var_dump( $new_meta );
    echo '</pre></td>';
    echo '<td valign="top"><h3>Customer Data</h3>( _wicket_membership_';echo $post->ID.' )<br><pre>';
    $customer_meta = Membership_Controller::get_membership_array_from_user_meta_by_post_id( $post->ID, $new_meta['user_id'] );
    var_dump( $customer_meta );
    echo '</pre></td>"';
    echo '<td valign="top"><h3>Order Data</h3>( _wicket_membership_';echo $mship_product_id.' )<br><pre>';
    var_dump( Membership_Controller::get_membership_array_from_post_id( $post->ID ) );
    echo '</pre></td></tr></table>"';
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
       'membership_wp_user_email' => 'user_email',
       'organization_name' => 'org_name',
       'organization_location' => 'org_location',
       'organization_uuid' => 'org_uuid',
       'membership_seats' => 'org_seats',
    ];
    array_walk(
      $membership_array,
      function(&$val, $key) use (&$membership_post_data, $mapping_keys)
      {
        $new_key = $mapping_keys[$key];
        if( empty($new_key) ) {
          return;
        }
        $membership_post_data[$new_key] = $val;
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
          return;
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

  public static function get_org_data( $org_uuid ) {
    $org_data = json_decode( get_option( 'org_data_'. $org_uuid ), true);
    if( empty( $org_data['data']['attributes']['alternate_name'] )) {
      self::store_an_organizations_data_in_options_table($org_uuid);
    }
    $data['location'] = $org_data['included'][0]['attributes']['city'] . ', ';
    $data['location'] .= $org_data['included'][0]['attributes']['state_name'] . ', ';
    $data['location'] .= $org_data['included'][0]['attributes']['country_code'];
    $data['name'] = $org_data['data']['attributes']['alternate_name'];
    return $data;
  }

  public static function store_an_organizations_data_in_options_table($org_uuid) {
    if( !($org_data = get_option('org_data_'.  $org_uuid)) ) {
      $org_data = wicket_get_organization($org_uuid, 'addresses' );
      add_option('org_data_'.$org_uuid, json_encode( $org_data) );
    }
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
}