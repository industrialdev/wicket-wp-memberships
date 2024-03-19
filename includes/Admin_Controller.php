<?php

namespace Wicket_Memberships;

/**
 * Main controller methods
 */
class Admin_Controller {

  public function __construct() {
    add_action( 'admin_menu', array( $this, 'init_menu' ) );
  }

	/**
	 * Initializes the menu.
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
