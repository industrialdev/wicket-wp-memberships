<?php
namespace WicketMember;

/**
 * Plugin Name: Wicket - Memberships
 * Plugin URI: http://wicket.io
 * Description: Wicket memberships addon to provide memberships functionality 
 * Version: 0.0.1
 * Author: Wicket Inc.
 * Author URI: https://wicket.io/
 * Text Domain: wicket
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.2
 *
 * @package Wicket
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Wicket_Memberships' ) ) {

	// Add vendor plugins with composer autoloader
	if (is_file(plugin_dir_path( __FILE__ ) . 'vendor/autoload.php')) {
		require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
	}

	/**
	 * The main Wicket Memberships class
	 */
	class Wicket_Memberships {

    public function __construct() {
			$this->wicket_global_constants_vars();
			include_once WICKET_MEMBER_PLUGIN_DIR . 'includes/wicket-member-controller.php';
			include_once WICKET_MEMBER_PLUGIN_DIR . 'includes/wicket-member-register-controller.php';
			include_once WICKET_MEMBER_PLUGIN_DIR . 'includes/wicket-member-wp-rest-controller.php';
			register_activation_hook( __FILE__, array( $this, 'wicket_member_activate' ) );
	
    }

		/**
		 * Plugin activation config
		 */
		public function wicket_member_activate() {
			// Default settings for plugin.
		}

		/**
		 * Define Global variables
		 */
		public function wicket_global_constants_vars() {
			if ( ! defined( 'WICKET_MEMBER_URL' ) ) {
				define( 'WICKET_MEMBER_URL', plugin_dir_url( __FILE__ ) );
			}
			if ( ! defined( 'WICKET_MEMBER_BASENAME' ) ) {
				define( 'WICKET_MEMBER_BASENAME', plugin_basename( __FILE__ ) );
			}
			if ( ! defined( 'WICKET_MEMBER_PLUGIN_DIR' ) ) {
				define( 'WICKET_MEMBER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
			}
		}

	} // end Class Wicket_Memberships.
	new Wicket_Memberships();
}