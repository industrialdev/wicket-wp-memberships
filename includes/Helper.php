<?php

namespace Wicket_Memberships;

use Wicket_Memberships\Utilities;

defined( 'ABSPATH' ) || exit;

class Helper {

  public function __construct() {
    if( !empty( $_ENV['WICKET_SHOW_ORDER_DEBUG_DATA'] ) ) {
      // INJECT MEMBERSHIP META DATA into order and subscription and member pages -- org_id on checkout page
      add_action( 'woocommerce_admin_order_data_after_shipping_address', [$this, 'wps_select_checkout_field_display_admin_order_meta'], 10, 1 );
      add_action( 'wcs_subscription_details_table_before_dates', [$this, 'wps_select_checkout_field_display_admin_order_meta'], 10, 1 );
    }
    if( !empty( $_ENV['WICKET_MEMBERSHIPS_DEBUG_MODE'] ) ) {
      //SEARCH THE MEMBERSHIPS POSTS DEBUG LIST
      add_action( 'pre_get_posts', [$this, 'wicket_memberships_alter_query'] );
      add_action( 'restrict_manage_posts', [$this, 'wicket_memberships_admin_search_box'] );

      // INJECT MEMBERSHIP META DATA into membership pages
      add_action( 'add_meta_boxes', [$this, 'extra_info_add_meta_boxes'] );
      //add_action( 'add_meta_boxes', [$this, 'action_buttons_add_meta_boxes'] );
      add_action( 'admin_menu', function() {
          remove_meta_box( 'extra_info_data', self::get_membership_cpt_slug(), 'normal' );
      } );
      add_action('save_post_wicket_mship_tier', [$this, 'add_slug_on_mship_tier_create'], 10, 3);
    }
    add_action( 'woocommerce_subscription_renewal_payment_complete', [$this, 'adjust_next_payment_date_after_renewal'], 10, 2 );
  }

  /**
   * Subscription Next Payment Date Adjustment
   * When the subscription is renewed manually we want the next payment date change to follow the membership cycle
   * This overrides woocommerce default behavior to set the next payment date to the current date + 1 billing cycle
   * @param mixed $subscription
   * @param mixed $renewal_order
   * @return void
   */
  function adjust_next_payment_date_after_renewal( $subscription, $renewal_order ) {
      $old_next_payment = $subscription->get_time( 'next_payment' );
      //only update next payment date if it is set and the billing period is monthly
      if ( ! $old_next_payment || $subscription->get_billing_period() != 'month') {
          return;
      }
      $new_timestamp = strtotime( '+1 month', $old_next_payment );
      $subscription->update_dates( [
          'next_payment' => gmdate( 'Y-m-d H:i:s', $new_timestamp ),
      ] );
      $subscription->save();
      Utilities::wicket_logger( "Next payment for MONTHLY subscription #{$subscription->get_id()} manually set to " . gmdate( 'Y-m-d H:i:s', $new_timestamp ) );
  }

  function add_slug_on_mship_tier_create($post_ID, $post, $update) {
      if ($update) return;
      if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
      if ($post->post_type !== 'wicket_mship_tier') return;
      $tier_data = get_post_meta( $post_ID, 'tier_data' );
      $mdp_tier = get_individual_memberships( $tier_data[0]['mdp_tier_uuid'] );
      $slug = $mdp_tier['data']['attributes']['slug'];
      update_post_meta($post_ID, 'membership_tier_slug', $slug);
  }

  function wps_select_checkout_field_display_admin_order_meta( $post ) {    
    if ( ! is_admin() ) {
      return $post;
    }
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
        Wicket_Memberships::STATUS_GRACE => [
          'name' => __('Grace Period', 'wicket-memberships'),
          'slug' => Wicket_Memberships::STATUS_GRACE
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
   * @param string $dir 'post' | 'order' - key mapping 
   * @return array
   */
  public static function get_membership_post_data_from_membership_json( $membership_json, $json_encoded = true, $dir = 'post' ) {
    $membership_post_data = array();
    if( $json_encoded === true ) {
      $membership_array = json_decode( $membership_json, true);
    } else {
      $membership_array = $membership_json;
    }
    if($dir = 'post') {
      $mapping_keys = [
        'membership_wp_user_display_name' => 'user_name',
        'membership_wp_user_email' => 'user_email',
        'organization_name' => 'org_name',
        'organization_location' => 'org_location',
        'organization_uuid' => 'org_uuid',
        'membership_seats' => 'org_seats',
     ]; 
    } else {
      $mapping_keys = [
        'user_name' => 'membership_wp_user_display_name',
        'user_email' => 'membership_wp_user_email',
        'org_name' => 'organization_name',
        'org_location' => 'organization_location',
        'org_uuid' => 'organization_uuid',
        'org_seats' => 'membership_seats',
     ]; 
    }
    array_walk(
      $membership_array,
      function(&$val, $key) use (&$membership_post_data, $mapping_keys)
      {
        $new_key = $mapping_keys[$key];
        if(empty($val)) {
          if( empty($new_key) ) {
            $membership_post_data[$key] = $val;
          } else {
            $membership_post_data[$new_key] = $val;
          }  
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

  public static function get_org_data( $org_uuid, $bypass_lookup = false, $force_lookup = false ) {
    $org_data = json_decode( get_option( 'org_data_'. $org_uuid ), true);
    if(empty( $org_data ) && $bypass_lookup ) {
      return ['name' => '', 'location' => ''];
    }

    //var_dump($org_data);exit;
    if( empty( $org_data['data']['attributes']['legal_name'] ) || $force_lookup) {
      $org_data = self::store_an_organizations_data_in_options_table($org_uuid, $force_lookup);
    }

    if(!is_array($org_data)) {
      $org_data = json_decode($org_data, true);
    }

    if( ! empty( $org_data['included'][0]['attributes']['city'] ) ) {
      $data['location'] = $org_data['included'][0]['attributes']['city'] . ', ';
      $data['location'] .= $org_data['included'][0]['attributes']['state_name'] . ', ';
      $data['location'] .= $org_data['included'][0]['attributes']['country_code'];
    } else {
      $data['location'] = '';
    }

    if(! is_array($org_data) || empty($org_data['data']['attributes']['legal_name']) ) {
      $data['name'] = '';
    } else {
      $data['name'] = $org_data['data']['attributes']['legal_name'];
    }
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

  public function wicket_memberships_alter_query( $query ) {
      if ( !is_admin() || 'wicket_membership' != $query->query['post_type'] ) {
        return;
      }

      if ( !empty($_REQUEST['wicket_membership_search']) ) {
        $s = sanitize_text_field($_REQUEST['wicket_membership_search']);

        $meta_query = array(
        'relation' => 'OR',
        array(
            'key'     => 'user_email',
            'value'   => $s,
            'compare' => 'LIKE',
        ),
        array(
            'key'     => 'org_name',
            'value'   => $s,
            'compare' => 'LIKE',
        ),
        array(
            'key'     => 'membership_uuid',
            'value'   => $s,
            'compare' => 'LIKE',
        ),
        array(
          'key'     => 'membership_subscription_id',
          'value'   => $s,
          'compare' => 'LIKE',
        ),
      );
      $query->set( 'meta_query', $meta_query );
    }
    return $query;
  }

  public function wicket_memberships_admin_search_box() {
      global $typenow;
      if ( $typenow == 'wicket_membership' ) {
          ?>
          <div style="float:right;">
            <input type="text" name="wicket_membership_search" id="wicket_membership_search" placeholder="Membership Posts Search..." value="<?php echo isset( $_GET['wicket_membership_search'] ) ? esc_attr( $_GET['wicket_membership_search'] ) : ''; ?>" />
           <input type="submit" class="button" value="Search Memberships" />
          </div>
          <?php
      }
  }

  /**
   * Summary of change_order_address_match_customer
   * Used for merge membership / owner change flow
   * @param mixed $order
   * @return void
   */
  public static function change_order_address_match_customer($order) {
    $user_id = $order->get_user_id();

    if (!$user_id) {
        return;
    }

    $customer = new \WC_Customer($user_id);

    $order->set_billing_first_name($customer->get_billing_first_name());
    $order->set_billing_last_name($customer->get_billing_last_name());
    $order->set_billing_company($customer->get_billing_company());
    $order->set_billing_address_1($customer->get_billing_address_1());
    $order->set_billing_address_2($customer->get_billing_address_2());
    $order->set_billing_city($customer->get_billing_city());
    $order->set_billing_postcode($customer->get_billing_postcode());
    $order->set_billing_country($customer->get_billing_country());
    $order->set_billing_state($customer->get_billing_state());
    $order->set_billing_phone($customer->get_billing_phone());
    $order->set_billing_email($customer->get_billing_email());

    $order->set_shipping_first_name($customer->get_shipping_first_name());
    $order->set_shipping_last_name($customer->get_shipping_last_name());
    $order->set_shipping_company($customer->get_shipping_company());
    $order->set_shipping_address_1($customer->get_shipping_address_1());
    $order->set_shipping_address_2($customer->get_shipping_address_2());
    $order->set_shipping_city($customer->get_shipping_city());
    $order->set_shipping_postcode($customer->get_shipping_postcode());
    $order->set_shipping_country($customer->get_shipping_country());
    $order->set_shipping_state($customer->get_shipping_state());

    $order->save();
  }

  /**
   * Summary of assign_preferred_payment_method_to_order
   * Used for merge membership / owner change flow
   * @param mixed $order
   * @return void
   */
  public static function assign_preferred_payment_method_to_order($order) {
    $order_id = $order->get_id();
    $user_id = $order->get_user_id();

    if (!$user_id || !$order_id) return;

    $tokens = \WC_Payment_Tokens::get_customer_tokens($user_id);

    if (!empty($tokens)) {
        $token = array_values($tokens)[0];
        $order->set_payment_method($token->get_gateway_id());
        $order->set_payment_method_title('Stored Token Based Payment');
        $order->set_payment_token($token->get_id());

        $order->save();
        return;
    }

    $customer_orders = wc_get_orders([
        'customer_id' => $user_id,
        'limit'       => 1,
        'orderby'     => 'date',
        'order'       => 'DESC',
        'exclude'     => [$order_id],
    ]);

    if (!empty($customer_orders)) {
        $last_order = $customer_orders[0];
        $method_id = $last_order->get_payment_method();
        $method_title = $last_order->get_payment_method_title();

        $order->set_payment_method($method_id ?: 'cheque');
        $order->set_payment_method_title($method_title ?: 'Manual Payment');
        $order->save();
        return;
    }

    $order->set_payment_method('cheque');
    $order->set_payment_method_title('Manual Payment');
    $order->save();
  }

  /**
   * Do we be updating next payment date on this membership
   * @param mixed $membership
   * @param bool $processing_renewal
   * @return mixed
   */

  public static function has_next_payment_date( $membership ) {
    $has_next_payment_date = false;
    if(!empty($membership['membership_subscription_id'])) {
      $subscription = wcs_get_subscription( $membership['membership_subscription_id'] );
    }

    if( empty($subscription) ) {
      return 'clear';
    }

    $is_autopay_enabled = $subscription->get_requires_manual_renewal() ? false : true;

    if( !empty( $membership['membership_next_tier_subscription_renewal'] ) || $is_autopay_enabled ) {
          /**
           * https://app.asana.com/0/1206866539294627/1208600763040824/f
           * 
           * This is only time we want next_payment_date set to allow subscriptions to generate a renewal order automatically.
           * The date is always set to the Membership End Date (membership_ends_at) to coincide with renewal date.
           * 
           */
      $has_next_payment_date = true;
    } else {
        $subscription = wcs_get_subscription( $membership['membership_subscription_id'] );
        $subscription_period = $subscription->get_billing_period(); // Get the billing period (e.g., 'month', 'year')
        $Membership_Tier = new Membership_Tier( $membership['membership_tier_post_id'] );
        $Membership_Config = $Membership_Tier->get_config();
        $membership_period_data = $Membership_Config->get_period_data();
        $membership_period = $membership_period_data['period_type'];
        Utilities::wicket_logger( 'inside has_next_payment_date', [$membership_period == $subscription_period, $membership_period_data, $membership_period, $subscription_period]);
        if( $membership_period == $subscription_period) {
          /**
           * https://app.asana.com/0/1206866539294627/1208600763040824/f
           * For non-subscription style renewal we need to be careful with the next payment date
           * - If subscription interval matches membership config (ex: 1 year), then no next payment date on subscription
           * 
           * NOTE: Returning this indicates we should not have any date in next_payment on subscription
           * and makes sure we clear any date that may currently exist in the field
           */
          $has_next_payment_date = 'clear';
        }
          /**
           * https://app.asana.com/0/1206866539294627/1208600763040824/f
           * If subscription interval does not match membership config (ex: subscription = monthly, membership = annual) then next payment date is not updated based on membership config
           * - end date = membership end date
           * - subscription renewal does not renew subscription
           * 
           * NOTE: Here return false used to skip processing order in the renewals order validation 
           * in `Membership_Controller->get_memberships_data_from_subscription_products()`
           * 
           */
    }    
    return $has_next_payment_date;
  }
}