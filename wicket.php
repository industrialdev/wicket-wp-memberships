<?php
namespace Wicket_Memberships;

/**
 * Plugin Name: Wicket Memberships
 * Plugin URI: http://wicket.io
 * Description: Wicket memberships addon to provide memberships functionality
 * Version: 1.0.103
 * Author: Wicket Inc.
 * Author URI: https://wicket.io/
 * Text Domain: wicket-memberships
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Requires Plugins: wicket-wp-base-plugin, woocommerce, woocommerce-subscriptions
 * @package Wicket
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! defined( 'WICKET_MEMBERSHIP_PLUGIN_URL' ) ) {
	define( 'WICKET_MEMBERSHIP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'WICKET_MEMBERSHIP_BASENAME' ) ) {
	define( 'WICKET_MEMBERSHIP_BASENAME', plugin_basename( __FILE__ ) );
}

if ( ! defined( 'WICKET_MEMBERSHIP_PLUGIN_DIR' ) ) {
	define( 'WICKET_MEMBERSHIP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WICKET_MEMBERSHIP_PLUGIN_FILE' ) ) {
	define( 'WICKET_MEMBERSHIP_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'WICKET_MEMBERSHIP_PLUGIN_SLUG' ) ) {
	define( 'WICKET_MEMBERSHIP_PLUGIN_SLUG', 'wicket_member_wp' );
}

use Wicket_Memberships\Admin_Controller;
use Wicket_Memberships\Membership_Controller;
use Wicket_Memberships\Membership_CPT_Hooks;
use Wicket_Memberships\Membership_Post_Types;
use Wicket_Memberships\Membership_Config_CPT_Hooks;
use Wicket_Memberships\Membership_Tier;
use Wicket_Memberships\Membership_Tier_CPT_Hooks;
use Wicket_Memberships\Membership_WP_REST_Controller;
use Wicket_Memberships\Membership_Subscription_Controller;
use Wicket_Memberships\Import_Controller;
use Wicket_Memberships\Settings;
use Wicket_Memberships\Utilities;

if ( ! class_exists( 'Wicket_Memberships' ) ) {

        // Add vendor plugins with composer autoloader
        if (is_file(WICKET_MEMBERSHIP_PLUGIN_DIR . 'vendor/autoload.php')) {
          require_once WICKET_MEMBERSHIP_PLUGIN_DIR . 'vendor/autoload.php';
          if (!class_exists('\Wicket\Client')) {
            if(is_file(WP_PLUGIN_DIR . '/wicket-wp-base-plugin/vendor/autoload.php')) {
              require_once( WP_PLUGIN_DIR . '/wicket-wp-base-plugin/vendor/autoload.php' );
            }

            // Including alternate plugin name in case of manual .zip installs
            if(is_file(WP_PLUGIN_DIR . '/wicket-wordpressplugin-php-master/vendor/autoload.php')) {
              require_once( WP_PLUGIN_DIR . '/wicket-wordpressplugin-php-master/vendor/autoload.php' );
            }
          }

          // https://developer.wordpress.org/reference/functions/get_plugin_data/#comment-content-5864
          if ( ! function_exists('get_plugin_data') ){
            require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
          }
          $plugin_data = \get_plugin_data( WP_PLUGIN_DIR . '/wicket-wp-base-plugin/wicket.php' );
          $_ENV['WICKET_BASE_PLUGIN_VERSION'] = $plugin_data['Version'];
          $options = get_option( 'wicket_membership_plugin_options' );

    if (isset($options['wicket_mship_subscription_renew'])) {
            if($options['wicket_mship_subscription_renew']) {
              $_ENV['WICKET_MSHIP_SUBSCRIPTION_RENEW']=true;
            }
          }
          if(isset($options['bypass_wicket'])) {
            if($options['bypass_wicket']) {
              $_ENV['BYPASS_WICKET']=true;
            }
          }
          if(isset($options['wicket_membership_debug_mode'])) {
            if($options['wicket_membership_debug_mode']) {
              $_ENV['WICKET_MEMBERSHIPS_DEBUG_MODE']=true;
            }
          }
          if(isset($options['bypass_status_change_lockout'])) {
            if($options['bypass_status_change_lockout']) {
              $_ENV['BYPASS_STATUS_CHANGE_LOCKOUT']=true;
            }
          }
          if(isset($options['wicket_show_order_debug_data'])) {
            if($options['wicket_show_order_debug_data']) {
              $_ENV['WICKET_SHOW_ORDER_DEBUG_DATA']=true;
            }
          }
          if(isset($options['allow_local_imports'])) {
            if($options['allow_local_imports']) {
              $_ENV['ALLOW_LOCAL_IMPORTS']=true;
            }
          }
          if(isset($options['wicket_memberships_debug_cart_ids'])) {
            if($options['wicket_memberships_debug_cart_ids']) {
              $_ENV['WICKET_MEMBERSHIPS_DEBUG_CART_IDS']=true;
            }
          }
          if(isset($options['wicket_memberships_debug_renew'])) {
            if($options['wicket_memberships_debug_renew']) {
              $_ENV['WICKET_MEMBERSHIPS_DEBUG_RENEW']=true;
            }
          }
          if(isset($options['wicket_memberships_debug_acc'])) {
            if($options['wicket_memberships_debug_acc']) {
              $_ENV['WICKET_MEMBERSHIPS_DEBUG_ACC']=true;
            }
          }
          if(isset($options['wicket_show_mship_order_org_search'])) {
            if($options['wicket_show_mship_order_org_search']) {
              $_ENV['WICKET_SHOW_MSHIP_ORDER_ORG_SEARCH']=true;
            }
          }
          if(isset($options['wicket_mship_disable_renewal'])) {
            if($options['wicket_mship_disable_renewal']) {
              $_ENV['WICKET_MSHIP_DISABLE_RENEWALS']=true;
            }
          }
        }

	/**
	 * The main Wicket Memberships class
	 */
	class Wicket_Memberships {

    const STATUS_PENDING    = 'pending';
    const STATUS_GRACE      = 'grace_period';
    const STATUS_ACTIVE     = 'active';
    const STATUS_DELAYED    = 'delayed';
    const STATUS_EXPIRED    = 'expired';
    const STATUS_CANCELLED  = 'cancelled';
    const WICKET_MEMBERSHIPS_CAPABILITY = 'manage_options';

    public function __construct() {
			// Load the main plugin classes
 			new Admin_Controller;
			new Membership_Post_Types;
			new Membership_CPT_Hooks;
			new Membership_Controller;
			new Membership_Config_CPT_Hooks;
			new Membership_Tier_CPT_Hooks;
      new Membership_WP_REST_Controller;
      new Membership_Subscription_Controller;
      new Helper;
      new Settings;
      new Utilities;

			register_activation_hook( WICKET_MEMBERSHIP_PLUGIN_FILE, array( $this, 'plugin_activate' ) );
      add_action('init', array($this, 'load_textdomain'));
      add_action('init', array($this, 'register_automatewoo_triggers'));

      //Order wicket-membership subscription hooks
      //Hooks fired twice included in class contructor

      //catch the order status change to processing
      add_action( 'woocommerce_order_status_processing', array ( __NAMESPACE__.'\\Membership_Controller' , 'catch_order_completed' ), 10, 1);
      //create the membership record
      add_action( 'wicket_member_create_record', array( __NAMESPACE__.'\\Membership_Controller', 'create_membership_record'), 10, 3 );

      //Advanced Scheduler action hooks to run our `do_action(..)` triggers on the renewal transition dates
      add_action( 'add_membership_early_renew_at', array ( __NAMESPACE__.'\\Membership_Controller', 'catch_membership_early_renew_at' ), 10, 2 );
      add_action( 'add_membership_ends_at', array ( __NAMESPACE__.'\\Membership_Controller', 'catch_membership_ends_at' ), 10, 2 );
      add_action( 'add_membership_expires_at', array ( __NAMESPACE__.'\\Membership_Controller', 'catch_membership_expires_at' ), 10, 2 );

      //expire current membership when new one starts
      add_action( 'expire_old_membership_on_new_starts_at', array ( __NAMESPACE__.'\\Membership_Controller', 'catch_expire_current_membership' ), 10, 2 );

      //check items in cart for valid renewal dates or return error
      //TODO: currently disabled need validate and remove, replaced with 'memberships_verify_cart' on checkout hooks
      //add_action( 'woocommerce_checkout_create_order_line_item', [  __NAMESPACE__.'\\Membership_Controller', 'validate_renewal_order_items'], 10, 4 );

      //
      add_action( 'template_redirect', [ $this, 'set_onboarding_posted_data_to_wc_session' ]);

      //plugin option settings & page including debug
      add_action( 'admin_menu', array ( __NAMESPACE__.'\\Settings' , 'wicket_membership_add_settings_page' ));
      add_action( 'admin_init', array( __NAMESPACE__.'\\Settings' , 'wicket_membership_register_settings' ));

      //check order items before and at checkout process

      //TODO: Confirm where date could gte miscalculated
      //add_action( 'woocommerce_cart_contents', array( $this, 'memberships_verify_cart' ) );
      //add_action( 'woocommerce_add_to_cart', array( $this, 'memberships_verify_cart' ) );
      //add_action( 'woocommerce_checkout_process', array( $this, 'memberships_verify_cart' ) );
      //add_action( 'woocommerce_before_checkout_form', array( $this, 'memberships_verify_cart' ) );

      //these will expire memberships that have not been renewed at end of grace period
      add_action('wp', array( $this, 'schedule_daily_membership_expiry'), 10, 2);
      add_action('schedule_daily_membership_expiry_hook', array( __NAMESPACE__.'\\Membership_Controller', 'daily_membership_expiry_hook'), 10, 2);
      //these will set to garce_period memberships that have not been renewed at membership_ends_at date
      add_action('wp', array($this, 'schedule_daily_membership_grace_period'), 10, 2);
    }

    public static function schedule_daily_membership_expiry() {
      if (!as_next_scheduled_action('schedule_daily_membership_expiry_hook')) {
        $timezone = wp_timezone();
        $next_run_time = new \DateTime('tomorrow 3:00', $timezone);
        $next_run_time->setTimezone(new \DateTimeZone('UTC'));
        as_schedule_recurring_action($next_run_time->getTimestamp(), DAY_IN_SECONDS, 'schedule_daily_membership_expiry_hook');
      }
    }

    public static function schedule_daily_membership_grace_period() {
      if (!as_next_scheduled_action('schedule_daily_membership_grace_period_hook')) {
        $timezone = wp_timezone();
        $next_run_time = new \DateTime('tomorrow 3:30', $timezone);
        $next_run_time->setTimezone(new \DateTimeZone('UTC'));
        as_schedule_recurring_action($next_run_time->getTimestamp(), DAY_IN_SECONDS, 'schedule_daily_membership_grace_period_hook');
      }
    }

    public function memberships_verify_cart( $checkout_order ) {
      if( !empty( $_ENV['check_renewals_orders_in_cart'] )) {
        $error_message = '';
        $user = wp_get_current_user();
        //get all the users subscription renewal orders pending a payment
        $orders  = get_posts( array(
          'meta_query' => [
            [
              'key'    => '_customer_user',
              'value'  => $user->ID,
            ],
            [
              'key'    => '_subscription_renewal',
              'compare' => 'EXISTS'
            ],
          ],
        'post_type'   => 'shop_order',
        'post_status' => 'wc-pending',
        'numberposts' => -1
        ));

        if(count($orders) > 0) {
          $error_message .= __('You have subscription renewal order(s) currently pending payment.', 'wicket-memberships');
          foreach($orders as $order) {
            $the_order = wc_get_order($order->ID);
            $subscriptions = wcs_get_subscriptions_for_order( $order->ID, ['order_type' => 'any'] );
            //check if the subscription(s) in a renewable state in case the order exists in error
            foreach($subscriptions as $subscription) {
              if( $subscription->has_status( array( 'on-hold', 'pending', 'pending-cancel' ) ) ) {
                $cannot_process[] = $order->ID;
              }
            }
            //get the checkout url for the order by ID
            $url[$order->ID] = $the_order->get_checkout_payment_url();
          }
          if( !empty($url)) {
            foreach($url as $key => $val) {
              //if the subscription each order is in a renewable status provide a link to checkout with it
              if( !in_array($key, $cannot_process )) {
                $error_message_links[] = ' <a href="'.$val.'">'.sprintf(__("Click Here to checkout with Order ID# %s",'wicket-memberships'), $key).'</a> ';
              }
            }
            //combine multiple checkout order links if they exist and error with links in notice
            if(!empty($error_message_links)) {
              wc_add_notice($error_message . '<br />'.implode("<br />", $error_message_links), 'error');
              return;
            }
          }
        }
      }

      //error check any membership products or renewals in the cart
      foreach( WC()->cart->get_cart() as $cart_item ) {
        $product_id = $cart_item['product_id'];
        $membership_tier_obj = Membership_Tier::get_tier_by_product_id( $product_id );
        //check we have org_uuid set for org memberships products or error on checkout
        if(!empty($membership_tier_obj) && $membership_tier_obj->tier_data['type'] == 'organization' && empty($cart_item['org_uuid']) && empty($cart_item['membership_post_id_renew'])) {
          wc_add_notice('<strong>'.__('Please contact support.', 'wicket-memberships').'</strong> '.__('Membership product missing important information.', 'wicket-memberships').' [org_uuid]', 'error');
          return;
        }

        if( !empty( $cart_item['membership_post_id_renew'] ) ) {
          $membership_current = (new Membership_Controller)->get_membership_array_from_user_meta_by_post_id( $cart_item['membership_post_id_renew'] );
          if( !empty($membership_current) ) {
            $config = new Membership_Config( $membership_tier_obj->tier_data['config_id'] );
            $early_renewal_date = $config->is_valid_renewal_date( $membership_current );
            //if we are trying to renew a membership check that we are in the renewal period
            if( !empty( $early_renewal_date ) && empty( $_ENV['WICKET_MEMBERSHIPS_DEBUG_RENEW'] )) {
              wc_add_notice(sprintf(__("Your membership is not due for renewal yet. You can renew starting %s.", "wicket-memberships" ), date("l jS \of F Y", strtotime($early_renewal_date))), 'error');
              return;
            }
          }
        }
      }
    }

    public function set_onboarding_posted_data_to_wc_session() {
        if ( is_page( 'cart' ) || is_cart() ) {
            if ( isset($_REQUEST['org_uuid']) ) {
                if ( isset($_REQUEST['org_uuid']) && ! empty($_REQUEST['org_uuid']) ) {
                    $values['org_uuid'] = sanitize_text_field($_REQUEST['org_uuid']);
                }
                if ( isset($_REQUEST['membership_post_id_renew']) && ! empty($_REQUEST['membership_post_id_renew']) ) {
                  $values['membership_post_id_renew'] = sanitize_text_field($_REQUEST['membership_post_id_renew']);
                }
                if ( ! empty($values)) {
                  foreach( $values as $key => $val ) {
                    WC()->session->set($key, $val );
                  }
                }
            }
        }
    }

    public static function init_wicket_mship_end_date( $triggers ) {
      require_once( WICKET_MEMBERSHIP_PLUGIN_DIR . '/automate-woo/triggers/wicket_mship_end_date.php' );
      $triggers['wicket_mship_end_date'] = 'Wicket_Mship_End_Date';
      return $triggers;
    }

    public static function init_wicket_mship_grace_period( $triggers ) {
      require_once( WICKET_MEMBERSHIP_PLUGIN_DIR . '/automate-woo/triggers/wicket_mship_grace_period.php' );
      $triggers['wicket_mship_grace_period'] = 'Wicket_Mship_Grace_Period';
      return $triggers;
    }

    public static function init_wicket_mship_renew_early( $triggers ) {
      require_once( WICKET_MEMBERSHIP_PLUGIN_DIR . '/automate-woo/triggers/wicket_mship_renew_early.php' );
      $triggers['wicket_mship_renew_early'] = 'Wicket_Mship_Renew_Early';
      return $triggers;
    }

    public function register_automatewoo_triggers()
    {
      add_filter('automatewoo/triggers', array($this, 'init_wicket_mship_end_date'), 10, 1);
      add_filter('automatewoo/triggers', array($this, 'init_wicket_mship_grace_period'), 10, 1);
      add_filter('automatewoo/triggers', array($this, 'init_wicket_mship_renew_early'), 10, 1);
    }

    /**
     * Plugin activation config
     */
		public function plugin_activate() {
			// Minimum versions for base plugin.
      $base_version_minimum_not_met = version_compare( $_ENV['WICKET_BASE_PLUGIN_VERSION'], '2.0', '<' );
      if ( !empty($base_version_minimum_not_met) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( 'Wicket Membership plugin requires "Wicket Base" version 2.0 or higher. You have version ' . $_ENV['WICKET_BASE_PLUGIN_VERSION'] . '.' );
      }
		}

		public function load_textdomain() {
			load_plugin_textdomain( 'wicket-memberships', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		}

	} // end Class Wicket_Memberships.
	new Wicket_Memberships();
}

