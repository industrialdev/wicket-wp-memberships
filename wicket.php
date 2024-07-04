<?php
namespace Wicket_Memberships;

/**
 * Plugin Name: Wicket - Memberships
 * Plugin URI: http://wicket.io
 * Description: Wicket memberships addon to provide memberships functionality
 * Version: 0.0.1
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

if ( ! class_exists( 'Wicket_Memberships' ) ) {

	// Add vendor plugins with composer autoloader
	if (is_file(WICKET_MEMBERSHIP_PLUGIN_DIR . 'vendor/autoload.php')) {
		require_once WICKET_MEMBERSHIP_PLUGIN_DIR . 'vendor/autoload.php';
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

			register_activation_hook( WICKET_MEMBERSHIP_PLUGIN_FILE, array( $this, 'plugin_activate' ) );
			add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

      //Order wicket-membership subscription hooks
      //Hooks fired twice included in class contructor
      add_action( 'init', [$this, 'wicket_membership_init_session'] );
      //catch the order status change to complete
      add_action( 'woocommerce_order_status_changed', array ( __NAMESPACE__.'\\Membership_Controller' , 'catch_order_completed' ), 10, 1);
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

      //temporary admin notice response
      add_action( 'admin_notices', function() {
        if( !empty( $_SESSION['wicket_membership_error'] ) ) {
          echo '<div class="notice error is-dismissible" ><p><strong>Wicket Membership Error:</strong> '. $_SESSION['wicket_membership_error'] .'</p></div>';
        }
        unset( $_SESSION['wicket_membership_error'] );
      });
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
      if ( ! session_id() ) {
          session_start();
      }
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

