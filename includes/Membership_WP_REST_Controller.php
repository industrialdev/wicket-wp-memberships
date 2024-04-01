<?php

namespace Wicket_Memberships;

/**
 * Rest routes and methods
 */
class Membership_WP_REST_Controller extends \WP_REST_Controller {

  public function __construct() {
    add_action( 'rest_api_init', [ $this, 'register_routes' ]);
      $this->namespace     = 'wicket_member/v1';
  }

  /**
   * Register routes
   */
  public function register_routes() {
    register_rest_route( $this->namespace, '/membership_tiers', array(
      array(
        'methods'  => \WP_REST_Server::READABLE,
        'callback'  => array( $this, 'get_tiers_mdp' ),
        'permission_callback' => array( $this, 'permissions_check_read' ),
      ),
      'schema' => array( $this, '' ),
    ) 
    );
    register_rest_route( $this->namespace, '/membership_orgs', array(
      array(
        'methods'  => \WP_REST_Server::READABLE,
        'callback'  => array( $this, 'get_orgs_mdp' ),
        'permission_callback' => array( $this, 'permissions_check_read' ),
      ),
      'schema' => array( $this, '' ),
    ) 
    );
    register_rest_route( $this->namespace, '/product_tiers/(?P<id>\d+)', array(
      array(
        'methods'  => \WP_REST_Server::READABLE,
        'callback'  => array( $this, 'get_product_tiers' ),
        'permission_callback' => array( $this, 'permissions_check_read' ),
      ),
      'schema' => array( $this, '' ),
    ) 
    );
}

  public function get_product_tiers( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $mc = new Membership_Controller();
    $response = $mc->get_tiers_from_product( $params['id'] );
    return rest_ensure_response( $response );
  }

  public function get_orgs_mdp() {
    $organizations = wicket_get_organizations();
    return rest_ensure_response( $organizations );
  }

  public function get_tiers_mdp() {
    $categories = wicket_get_option( 'wicket_admin_settings_membership_categories' );
    $memberships = $this->get_memberships_table_data($categories);
    return rest_ensure_response( $memberships );
  }


	public function get_memberships_table_data($categories = null)
	{
		$memberships = [];
		$individual_memberships = get_individual_memberships();
		if($individual_memberships && isset($individual_memberships['data'])) {

			foreach ($individual_memberships['data'] as $key => $value) {
				$has_category = false;
        $membership_uuid = $value['id'];
				$membership_slug = ($value['attributes']['slug']) ?? $value['attributes']['slug'];

				if(($has_category && $categories) || (!$categories)){
					$memberships[$key]['status'] = (isset($value['attributes']['active']) && $value['attributes']['active'] == 1) ? 'Active' : 'Inactive';
					$memberships[$key]['type'] = ($value['attributes']['type']) ?? $value['attributes']['type'];
					$memberships[$key]['name'] = ($value['attributes']['name_en']) ?? $value['attributes']['name_en'];
					$memberships[$key]['slug'] = $membership_slug;
					$memberships[$key]['uuid'] = $membership_uuid;
				}
			}
		}
		return $memberships;
	}

  /**
   * Check permissions to read
   */
  public function permissions_check_read( $request ) {
    if ( 0 && ! current_user_can( 'read' ) ) {
      return new WP_Error( 'rest_forbidden', esc_html__( 'Permission Error.' ), array( 'status' => $this->authorization_error_status_code() ) );
    }
    return true;
  }


  public function authorization_status_code() {
    $status = 401;
    if ( is_user_logged_in() ) {
      $status = 403;
    }
    return $status;
  }
}