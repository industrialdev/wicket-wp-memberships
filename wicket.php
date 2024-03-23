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
 * Requires PHP: 8.2
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

if ( ! defined( 'WICKET_MEMBER_URL' ) ) {
	define( 'WICKET_MEMBER_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'WICKET_MEMBER_BASENAME' ) ) {
	define( 'WICKET_MEMBER_BASENAME', plugin_basename( __FILE__ ) );
}

if ( ! defined( 'WICKET_MEMBER_PLUGIN_DIR' ) ) {
	define( 'WICKET_MEMBER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WICKET_MEMBER_PLUGIN_FILE' ) ) {
	define( 'WICKET_MEMBER_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'WICKET_MEMBER_PLUGIN_SLUG' ) ) {
	define( 'WICKET_MEMBER_PLUGIN_SLUG', 'wicket_member_wp' );
}

include_once 'includes/Admin_Controller.php';
include_once 'includes/Membership_Controller.php';
include_once 'includes/Membership_CPT_Hooks.php';
include_once 'includes/Membership_Post_Types.php';

use Wicket_Memberships\Admin_Controller;
use Wicket_Memberships\Membership_Controller;
use Wicket_Memberships\Membership_CPT_Hooks;
use Wicket_Memberships\Membership_Post_Types;

if ( ! class_exists( 'Wicket_Memberships' ) ) {

	// Add vendor plugins with composer autoloader
	if (is_file(WICKET_MEMBER_PLUGIN_DIR . 'vendor/autoload.php')) {
		require_once WICKET_MEMBER_PLUGIN_DIR . 'vendor/autoload.php';
	}

	/**
	 * The main Wicket Memberships class
	 */
	class Wicket_Memberships {

    public function __construct() {
			// Load the main plugin classes
 			new Admin_Controller;
			new Membership_Post_Types;
			new Membership_CPT_Hooks;
			new Membership_Controller;

			register_activation_hook( WICKET_MEMBER_PLUGIN_FILE, array( $this, 'plugin_activate' ) );
			add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
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