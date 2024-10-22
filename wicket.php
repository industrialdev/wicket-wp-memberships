<?php
namespace Wicket_Memberships;

/**
 * Plugin Name: Wicket - Memberships
 * Plugin URI: http://wicket.io
 * Description: Wicket memberships addon to provide memberships functionality
 * Version: 1.0.49
 * Author: Wicket Inc.
 * Author URI: https://wicket.io/
 * Text Domain: wicket-memberships
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
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
          $options = get_option( 'wicket_membership_plugin_options' );
          
          if(isset($options['wicket_mship_subscription_renew'])) {
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
			add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

      //Order wicket-membership subscription hooks
      //Hooks fired twice included in class contructor
      add_action( 'init', [$this, 'wicket_membership_init_session'] );
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
      add_action( 'woocommerce_checkout_create_order_line_item', [  __NAMESPACE__.'\\Membership_Controller', 'validate_renewal_order_items'], 10, 4 );
      //
      add_action( 'template_redirect', [ $this, 'set_onboarding_posted_data_to_wc_session' ]);

      //plugin option settings & page including debug
      add_action( 'admin_menu', array ( __NAMESPACE__.'\\Settings' , 'wicket_membership_add_settings_page' ));
      add_action( 'admin_init', array( __NAMESPACE__.'\\Settings' , 'wicket_membership_register_settings' ));

      //temporary admin notice response
      add_action( 'admin_notices', function() {
        if( !empty( $_SESSION['wicket_membership_error'] ) ) {
          echo '<div class="notice error is-dismissible" ><p><strong>Wicket Membership Error:</strong> '. $_SESSION['wicket_membership_error'] .'</p></div>';
        }
        unset( $_SESSION['wicket_membership_error'] );
      });
      add_filter( 'automatewoo/triggers', array( $this, 'init_wicket_mship_end_date' ), 10, 1 );
      add_filter( 'automatewoo/triggers', array( $this, 'init_wicket_mship_grace_period' ), 10, 1 );
      add_filter( 'automatewoo/triggers', array( $this, 'init_wicket_mship_renew_early' ), 10, 1 );
    }

    public function set_onboarding_posted_data_to_wc_session() {
        if ( is_page( 'cart' ) || is_cart() ) {
            if ( isset($_REQUEST['org_uuid']) ) {
                if ( isset($_REQUEST['org_uuid']) && ! empty($_REQUEST['org_uuid']) ) {
                    $values['org_uuid'] = sanitize_text_field($_REQUEST['org_uuid']);
                }
                if ( ! empty($values)) {
                  foreach( $values as $key => $val ) {
                    WC()->session->set($key, $val );
                  }
                }
            }
        }
    }

    public function wicket_membership_init_session() {
      if ( ! session_id() && ! headers_sent()) {
          session_start();
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

    /**
		 * Plugin activation config
		 */
		public function plugin_activate() {
			// Default settings for plugin.
		}

		public function load_textdomain() {
			load_plugin_textdomain( 'wicket-memberships', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		}

	} // end Class Wicket_Memberships.
	new Wicket_Memberships();
}

