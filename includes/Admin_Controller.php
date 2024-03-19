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
  }

	/**
   * Initialize the admin menu
   */
	public function init_menu() {
    $menu_slug = WICKET_MEMBER_PLUGIN_SLUG;
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
}
