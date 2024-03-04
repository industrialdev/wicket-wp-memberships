<?php
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
		/**
		 * Constructor
		 */
		public function __construct() {
			
			
		}


	} // end Class Wicket_Memberships.
	new Wicket_Memberships();
}