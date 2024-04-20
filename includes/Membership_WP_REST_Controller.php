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

    register_rest_route( $this->namespace, '/membership_filters', array(
      array(
        'methods'  => \WP_REST_Server::READABLE,
        'callback'  => array( $this, 'get_membership_filters' ),
        'permission_callback' => array( $this, 'permissions_check_read' ),        
        'args' => array(
          'type' => array(
            'required' => true,
            'type' => 'string',
            'description' => 'membership filter values type: individual | organization',
          ),
        ),
      ),
      'schema' => array( $this, '' ),
    ) 
    );

    register_rest_route( $this->namespace, '/memberships', array(
      array(
        'methods'  => \WP_REST_Server::READABLE,
        'callback'  => array( $this, 'get_membership_lists' ),
        'permission_callback' => array( $this, 'permissions_check_read' ),
        'args' => array(
          'type' => array(
            'required' => true,
            'type' => 'string',
            'description' => 'membership type: individual | organization',
          ),
          'page' => array(
            'type' => 'integer',
            'description' => 'paginated results page',
          ),
          'posts_per_page' => array(
            'type' => 'integer',
            'description' => 'paginated results per page',
          ),
          'status' => array(
            'type' => 'string',
            'description' => 'membership status: active | expired',
          ),  
          'order_col' => array(
            'type' => 'string',
            'description' => 'order by column name',
          ),  
          'order_dir' => array(
            'type' => 'string',
            'description' => 'order by direction',
          ),       
          'filter[]' => array(
            'type' => 'string',
            'description' => 'list filters',
          ),          
        )
      ),
      'schema' => array( $this, '' ),
    ) 
    );

    register_rest_route( $this->namespace, '/config/(?P<id>\d+)/membership_dates', array(
      array(
        'methods'  => \WP_REST_Server::READABLE,
        'callback'  => array( $this, 'get_membership_dates' ),
        'permission_callback' => array( $this, 'permissions_check_read' ),
      ),
      'schema' => array( $this, '' ),
    ) );

    // params = user_id
    register_rest_route( $this->namespace, '/memberships_expiring(?:/(?P<user_id>\d+))?', array(
      array(
        'methods'  => \WP_REST_Server::READABLE,
        'callback'  => array( $this, 'get_memberships_expiring' ),
        'permission_callback' => array( $this, 'permissions_check_read' ),
      ),
      'schema' => array( $this, '' ),
    ) );

    // test endpoint
    register_rest_route( $this->namespace, '/subscription/(?P<id>\d+)/modify', array(
      array(
        'methods'  => \WP_REST_Server::CREATABLE,
        'callback'  => array( $this, 'modify_subscription' ),
        'permission_callback' => array( $this, 'permissions_check_read' ),
      ),
      'schema' => array( $this, '' ),
    ) );
  }

  public function get_memberships_expiring( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $user_id = null;
    if( !empty( $params['user_id'] )) {
      $user_id = $params['user_id'];
    }
    $mc = new Membership_Controller();
    $response = $mc->get_my_early_renewals( $user_id );
    return rest_ensure_response( $response );
  }

  public function get_membership_filters( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $mc = new Membership_Controller();
    $response = $mc->get_members_filters( $params['type'] );
    return rest_ensure_response( $response );
  }

  public function get_membership_lists( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $mc = new Membership_Controller();
    $response = $mc->get_members_list( $params['type'], $params['page'], $params['posts_per_page'], $params['status'], $params['search'], $params['filter'], $params['order_col'], $params['order_dir'] );
    return rest_ensure_response( $response );
  }
  
  public function modify_subscription( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $mc = new Membership_Subscription_Controller();
    $response = $mc->modify_subscription( $params['id'] );
    return rest_ensure_response( $response );

  }

public function get_membership_dates( \WP_REST_Request $request ) {
  $params = $request->get_params();
  $mc = new Membership_Controller();
  $response = $mc->get_membership_dates( $params['id'] );
  return rest_ensure_response( $response );

}

  public function get_product_tiers( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $response = Membership_Tier::get_tier_by_product_id( $params['id'] ); 
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