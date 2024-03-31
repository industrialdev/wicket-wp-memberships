<?php

namespace Wicket_Memberships;

/**
 * Class Admin_Controller
 * @package Wicket_Memberships
 */
class Admin_Controller {

  /**
   * Admin_Controller constructor.
   */
  public function __construct() {
    add_action( 'admin_menu', array( $this, 'init_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
  }

	/**
   * Initialize the admin menu
   */
	public function init_menu() {
    $menu_slug = WICKET_MEMBERSHIP_PLUGIN_SLUG;
		$capability = 'manage_options';

		add_menu_page(
			esc_attr__( 'Wicket Memberships', 'wicket-memberships' ),
			esc_attr__( 'Wicket Memberships', 'wicket-memberships' ),
			$capability,
			$menu_slug,
			'',
			'dashicons-list-view'
		);
	}

	/**
	 * Enqueue scripts and styles
	 */
	public function enqueue_scripts() {
		wp_enqueue_style(
      WICKET_MEMBERSHIP_PLUGIN_SLUG . '_styles',
      WICKET_MEMBERSHIP_PLUGIN_URL . '/assets/css/wicket-memberships-main.css',
      [ 'wp-components' ],
      false
    );
	}
}
