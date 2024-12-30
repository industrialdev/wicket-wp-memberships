<?php

namespace Wicket_Memberships;

use Wicket_Memberships\Membership_Controller;
use Wicket_Memberships\Admin_Controller;

use \WP_REST_Response;

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
   /**
    * Get All Tiers MDP
    */
    register_rest_route( $this->namespace, '/membership_tiers', array(
      array(
        'methods'  => \WP_REST_Server::READABLE,
        'callback'  => array( $this, 'get_tiers_mdp' ),
        'permission_callback' => array( $this, 'permissions_check_read' ),
      ),
      //'schema' => array( $this, '' ),
    )
    );
    /**
    * Get All Orgs MDP
    */
    register_rest_route( $this->namespace, '/membership_orgs', array(
      array(
        'methods'  => \WP_REST_Server::READABLE,
        'callback'  => array( $this, 'get_orgs_mdp' ),
        'permission_callback' => array( $this, 'permissions_check_read' ),
      ),
      //'schema' => array( $this, '' ),
    )
    );
    /**
    * Get Tier Data WP
    * Can filter by UUID and add properties like: count
    */
    register_rest_route( $this->namespace, '/membership_tier_info', array(
      array(
        'methods'  => \WP_REST_Server::READABLE,
        'callback'  => array( $this, 'get_tier_info' ),
        'permission_callback' => array( $this, 'permissions_check_read' ),
      ),
      //'schema' => array( $this, '' ),
    )
    );

    /**
    * Get Org Data WP
    * Can filter by UUID and add properties like: count
    */
    register_rest_route( $this->namespace, '/membership_org_info', array(
      array(
        'methods'  => \WP_REST_Server::READABLE,
        'callback'  => array( $this, 'get_org_info' ),
        'permission_callback' => array( $this, 'permissions_check_read' ),
      ),
      //'schema' => array( $this, '' ),
    )
    );
    /**
     * Get Tier by Product_ID
     */
    register_rest_route( $this->namespace, '/product_tiers/(?P<id>\d+)', array(
      array(
        'methods'  => \WP_REST_Server::READABLE,
        'callback'  => array( $this, 'get_product_tiers' ),
        'permission_callback' => array( $this, 'permissions_check_read' ),
      ),
      //'schema' => array( $this, '' ),
    )
    );
    /**
     * Get Memberships by Org or User
     */
    register_rest_route( $this->namespace, '/membership_entity', array(
      array(
        'methods'  => \WP_REST_Server::READABLE,
        'callback'  => array( $this, 'get_membership_entity' ),
        'permission_callback' => array( $this, 'permissions_check_read' ),
      ),
      //'schema' => array( $this, '' ),
    )
    );
    /**
     * Write to a Membership
     */
    register_rest_route( $this->namespace, '/membership_entity/(?P<membership_post_id>\d+)/update', array(
      array(
        'methods'  => \WP_REST_Server::CREATABLE,
        'callback'  => array( $this, 'update_membership_entity' ),
        'permission_callback' => array( $this, 'permissions_check_write' ),
      ),
      //'schema' => array( $this, '' ),
    )
    );
        // available status options for change status drop-down
        register_rest_route( $this->namespace, '/membership/(?P<membership_post_id>\d+)/change_owner', array(
          array(
            'methods'  => \WP_REST_Server::CREATABLE,
            'callback'  => array( $this, 'update_membership_change_ownership' ),
            'permission_callback' => array( $this, 'permissions_check_write' ),
          ),
          //'schema' => array( $this, '' ),
        ) );    
  /**
   * Get membership filters by Membership Type
   */
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
      //'schema' => array( $this, '' ),
    )
    );
    /**
     * Main Search and FIlter Memberships Endpoint
     */
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
            'description' => 'membership status',
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
      //'schema' => array( $this, '' ),
    )
    );
    /**
     * Get Membership Dates with Config_ID
     */
    register_rest_route( $this->namespace, '/config/(?P<id>\d+)/membership_dates', array(
      array(
        'methods'  => \WP_REST_Server::READABLE,
        'callback'  => array( $this, 'get_membership_dates' ),
        'permission_callback' => array( $this, 'permissions_check_read' ),
      ),
      //'schema' => array( $this, '' ),
    ) );
    /**
     * Get memberships early renewal and grace periods by user_id
     */
    // params = user_id
    register_rest_route( $this->namespace, '/get_membership_callouts(?:/(?P<user_id>\d+))?', array(
      array(
        'methods'  => \WP_REST_Server::READABLE,
        'callback'  => array( $this, 'get_membership_callouts' ),
        'permission_callback' => array( $this, 'permissions_check_read' ),
      ),
      //'schema' => array( $this, '' ),
    ) );

    // change status on membership
    register_rest_route( $this->namespace, '/admin/manage_status', array(
      array(
        'methods'  => \WP_REST_Server::CREATABLE,
        'callback'  => array( $this, 'admin_manage_status' ),
        'permission_callback' => array( $this, 'permissions_check_write' ),
      ),
      //'schema' => array( $this, '' ),
    ) );

    // available status options for change status drop-down
    register_rest_route( $this->namespace, '/admin/status_options', array(
      array(
        'methods'  => \WP_REST_Server::READABLE,
        'callback'  => array( $this, 'get_admin_status_options' ),
        'permission_callback' => array( $this, 'permissions_check_read' ),
      ),
      //'schema' => array( $this, '' ),
    ) );

    // available status options for change status drop-down
    register_rest_route( $this->namespace, '/admin/get_edit_page_info', array(
      array(
        'methods'  => \WP_REST_Server::READABLE,
        'callback'  => array( $this, 'get_edit_page_info' ),
        'permission_callback' => array( $this, 'permissions_check_read' ),
      ),
      //'schema' => array( $this, '' ),
    ) );

    // test endpoint
    register_rest_route( $this->namespace, '/subscription/(?P<id>\d+)/modify', array(
      array(
        'methods'  => \WP_REST_Server::CREATABLE,
        'callback'  => array( $this, 'modify_subscription' ),
        'permission_callback' => array( $this, 'permissions_check_write' ),
      ),
      //'schema' => array( $this, '' ),
    ) );
    //DEBUG
    register_rest_route( $this->namespace, '/org_data', array(
      array(
        'methods'  => \WP_REST_Server::READABLE,
        'callback'  => array( $this, 'get_org_data' ),
        'permission_callback' => array( $this, 'permissions_check_read' ),
      ),
      //'schema' => array( $this, '' ),
    )
    );
    //DEBUG
    register_rest_route( $this->namespace, '/import/person_memberships', array(
      array(
        'methods'  => \WP_REST_Server::CREATABLE,
        'callback'  => array( $this, 'import_person_memberships' ),
        'permission_callback' => array( $this, 'permissions_check_write' ),
      ),
      //'schema' => array( $this, '' ),
    )
    );
    //DEBUG
    register_rest_route( $this->namespace, '/import/membership_organizations', array(
      array(
        'methods'  => \WP_REST_Server::CREATABLE,
        'callback'  => array( $this, 'import_membership_organizations' ),
        'permission_callback' => array( $this, 'permissions_check_write' ),
      ),
      //'schema' => array( $this, '' ),
    )
    );

    //lookahead person name search
    register_rest_route( $this->namespace, '/mdp_person/search', array(
      array(
        'methods'  => \WP_REST_Server::CREATABLE,
        'callback'  => array( $this, 'mdp_person_lookup' ),
        'permission_callback' => array( $this, 'permissions_check_read' ),
      ),
      //'schema' => array( $this, '' ),
    )
    );
  }

  public function mdp_person_lookup( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $response = wicket_search_person($params['term']);
    return rest_ensure_response( $response );
  }

  public function update_membership_change_ownership( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $response = (new Admin_Controller() )->update_membership_change_ownership( $params );
    return rest_ensure_response( $response );
  }

  public function import_membership_organizations( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $response = (new Import_Controller() )->create_organization_memberships( $params );
    return rest_ensure_response( $response );
  }

  public function import_person_memberships( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $response = (new Import_Controller() )->create_individual_memberships( $params );
    return rest_ensure_response( $response );
  }

  public function get_membership_entity( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $response = Admin_Controller::get_membership_entity_records( $params['entity_id'] );
    return rest_ensure_response( $response );
  }

  public function get_edit_page_info( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $response = Admin_Controller::get_edit_page_info( $params['entity_id'] );
    return rest_ensure_response( $response );
  }

  public function update_membership_entity( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $response = Admin_Controller::update_membership_entity_record( $params );
    return rest_ensure_response( $response );
  }

  public function admin_manage_status( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $response = Admin_Controller::admin_manage_status( $params['post_id'], $params['status']);
    return rest_ensure_response( $response );
  }

  public function get_admin_status_options( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $response = Admin_Controller::get_admin_status_options( $params['post_id']);
    return rest_ensure_response( $response );
  }

  public function get_membership_callouts( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $user_id = null;
    if( !empty( $params['user_id'] )) {
      $user_id = $params['user_id'];
    }
    $mc = new Membership_Controller();
    $response = $mc->get_membership_callouts( $user_id );
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

  public function get_org_info(  \WP_REST_Request $request  ) {
    $params = $request->get_params();
    $org_info = Membership_Controller::get_org_info( $params['filter']['org_uuid'], $params['properties'] );
    return rest_ensure_response( $org_info );
  }

  public function get_org_data(  \WP_REST_Request $request  ) {
    $params = $request->get_params();
    $org_data = Helper::get_org_data( $params['org_uuid'], true );
    //$org_data = get_option( 'org_data_' . $params['org_uuid'] );
    return rest_ensure_response( $org_data );
  }

  public function get_tiers_mdp( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $categories = wicket_get_option( 'wicket_admin_settings_membership_categories' );
    $memberships = $this->get_memberships_table_data($categories, $params['filters']);
    return rest_ensure_response( $memberships );
  }

  public function get_tier_info(  \WP_REST_Request $request  ) {
    $params = $request->get_params();
    $tier_info = Membership_Controller::get_tier_info( $params['filter']['tier_uuid'], $params['properties'] );
    return rest_ensure_response( $tier_info );
  }

	public function get_memberships_table_data($categories = null, $filters = [])
	{
		$memberships = [];
		$individual_memberships = get_individual_memberships();
		if($individual_memberships && isset($individual_memberships['data'])) {
			foreach ($individual_memberships['data'] as $key => $value) {
        if( !empty( $filters['id'] ) && ! in_array( $value['id'], $filters['id'] ) ) {
          continue;
        }
        $has_category = true;
        $membership_uuid = $value['id'];
				$membership_slug = ($value['attributes']['slug']) ?? $value['attributes']['slug'];

				if(($has_category && $categories) || (!$categories)){
					$membership['status'] = (isset($value['attributes']['active']) && $value['attributes']['active'] == 1) ? 'Active' : 'Inactive';
					$membership['type'] = ($value['attributes']['type']) ?? $value['attributes']['type'];
					$membership['name'] = ($value['attributes']['name_en']) ?? $value['attributes']['name_en'];
					$membership['slug'] = $membership_slug;
					$membership['uuid'] = $membership_uuid;
					$membership['category'] = ($value['attributes']['category']) ?? $value['attributes']['category'];
					$membership['unlimited_assignments'] = ($value['attributes']['unlimited_assignments']) ?? $value['attributes']['unlimited_assignments'];
					$membership['max_assignments'] = ($value['attributes']['max_assignments']) ?? $value['attributes']['max_assignments'];
					$membership['tags'] = ($value['attributes']['tags']) ?? $value['attributes']['tags'];
          $memberships[] = $membership;
				}
			}
		}
		return  $memberships;
	}

  /**
   * Check permissions to read
   */
  public function permissions_check_read( $request ) {
    if( ! empty( $_ENV['ALLOW_LOCAL_IMPORTS'] )) {
      return true;
    }
    if ( ! current_user_can( Wicket_Memberships::WICKET_MEMBERSHIPS_CAPABILITY ) ) {
      return new \WP_REST_Response(array('error' => 'Authentication required.'), 401);
    }
    return true;
  }

  /**
   * Check permissions to write
   */
  public function permissions_check_write( $request ) {
    if( ! empty( $_ENV['ALLOW_LOCAL_IMPORTS'] )) {
      return true;
    }
    if ( ! current_user_can( Wicket_Memberships::WICKET_MEMBERSHIPS_CAPABILITY ) ) {
      return new \WP_REST_Response(array('error' => 'Authentication required.'), 401);
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
