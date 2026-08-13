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
   * Delete all memberships for a person_uuid from the MDP
   */
    register_rest_route( $this->namespace, '/person/(?P<person_uuid>[a-zA-Z0-9-]+)/delete_all_memberships', array(
      array(
        'methods'  => \WP_REST_Server::CREATABLE,
        'callback'  => array( $this, 'delete_all_person_memberships' ),
        'permission_callback' => array( $this, 'permissions_check_write' ),
        'args' => array(
          'person_uuid' => array(
            'required' => true,
            'type' => 'string',
            'description' => 'The person_uuid whose memberships will be deleted from the MDP.'
          ),
        ),
      ),
    ) );

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
    * Get Group Data WP
    * Filter by bundle post IDs
    */
    register_rest_route( $this->namespace, '/membership_bundle_info', array(
      array(
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => array( $this, 'get_bundle_info' ),
        'permission_callback' => array( $this, 'permissions_check_read' ),
      ),
    )
    );

    /**
    * Get all published WP pages, ignoring any visibility restrictions from third-party
    * plugins (e.g. WP Private Content Plus), for use in tier renewal form page selectors.
    */
    register_rest_route( $this->namespace, '/wp_pages_all', array(
      array(
        'methods'  => \WP_REST_Server::READABLE,
        'callback'  => array( $this, 'get_all_wp_pages' ),
        'permission_callback' => array( $this, 'permissions_check_read' ),
      ),
    )
    );

    /**
    * Get published WooCommerce products for the plugin's admin product pickers, ignoring any
    * visibility restrictions from third-party plugins (e.g. WP Private Content Plus).
    */
    register_rest_route( $this->namespace, '/wc_products_all', array(
      array(
        'methods'  => \WP_REST_Server::READABLE,
        'callback'  => array( $this, 'get_all_wc_products' ),
        'permission_callback' => array( $this, 'permissions_check_read' ),
      ),
    )
    );

    /**
    * Get published variations of a single WooCommerce product, ignoring any visibility
    * restrictions from third-party plugins (e.g. WP Private Content Plus).
    */
    register_rest_route( $this->namespace, '/wc_product_variations/(?P<id>\d+)', array(
      array(
        'methods'  => \WP_REST_Server::READABLE,
        'callback'  => array( $this, 'get_wc_product_variations' ),
        'permission_callback' => array( $this, 'permissions_check_read' ),
      ),
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
        'callback'  => array( $this, 'bundle_admin_manage_status' ),
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

  if( ! empty( $_ENV['ALLOW_LOCAL_IMPORTS'] )) {
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
    register_rest_route( $this->namespace, '/import/membership_bundle', array(
      array(
        'methods'  => \WP_REST_Server::CREATABLE,
        'callback'  => array( $this, 'import_membership_bundle' ),
        'permission_callback' => array( $this, 'permissions_check_write' ),
      ),
    )
    );
  }
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

    /**
     * Create a renewal order for a membership
     */
    register_rest_route( $this->namespace, '/membership/(?P<membership_post_id>\d+)/create_renewal_order', array(
      array(
        'methods'  => \WP_REST_Server::CREATABLE,
        'callback'  => array( $this, 'create_renewal_order' ),
        'permission_callback' => array( $this, 'permissions_check_write' ),
      ),
      //'schema' => array( $this, '' ),
    ) );    

    /**
     * Bulk Merge webhook consumer from MDP
     */
    register_rest_route( $this->namespace, '/membership/merge', array(
      array(
        'methods'  => \WP_REST_Server::CREATABLE,
        'callback'  => array( $this, 'memberships_merge_webhook_consumer'),
        'permission_callback' => '__return_true',
      ),
      //'schema' => array( $this, '' ),
    ) );

    /**
     * Resolve WooCommerce product and variation names by ID.
     *
     * GET /wicket_member/v1/membership_products?ids[]=123&ids[]=456
     *
     * Accepts a mixed list of product IDs and variation IDs. Each ID is resolved
     * via wc_get_product() which handles both types transparently. Returns a flat
     * array of objects with id, name, type, product_id, and variation_id fields.
     */
    register_rest_route( $this->namespace, '/membership_products', [
      [
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => [ $this, 'get_membership_products' ],
        'permission_callback' => [ $this, 'permissions_check_read' ],
        'args'                => [
          'ids' => [
            'required'    => false,
            'type'        => 'array',
            'items'       => [ 'type' => 'integer' ],
            'description' => 'List of WC product or variation IDs to resolve.',
          ],
        ],
      ],
    ] );

    /**
     * Transfer Membership
     */
    register_rest_route( $this->namespace, '/membership/(?P<membership_post_id>\d+)/transfer_membership', array(
      array(
        'methods'  => \WP_REST_Server::CREATABLE,
        'callback'  => array( $this, 'transfer_membership' ),
        'permission_callback' => array( $this, 'permissions_check_write' ),
      ),
    ) );

    /**
     * Awitch Membership
     */
    register_rest_route( $this->namespace, '/membership/(?P<membership_post_id>\d+)/switch_membership', array(
      array(
        'methods'  => \WP_REST_Server::CREATABLE,
        'callback'  => array( $this, 'switch_membership' ),
        'permission_callback' => array( $this, 'permissions_check_write' ),
      ),
    ) );
  }

  /**
   * GET /membership_products
   *
   * Accepts a mixed list of WC product IDs and variation IDs via ?ids[]=.
   * wc_get_product() resolves both transparently — no need for the caller
   * to distinguish between them.
   *
   * Each entry in the response includes:
   *   id           — the ID that was passed in
   *   name         — display name (variation name when variation, product name otherwise)
   *   type         — WC product type slug (e.g. "subscription", "variable-subscription", "variation")
   *   product_id   — parent product ID (same as id for non-variations)
   *   variation_id — variation ID, or null for non-variations
   */
  public function get_membership_products( \WP_REST_Request $request ) {
    $ids = array_map( 'intval', (array) $request->get_param( 'ids' ) );
    $ids = array_filter( $ids );

    $results = [];

    foreach ( $ids as $id ) {
      $product = wc_get_product( $id );
      if ( ! $product ) {
        continue;
      }

      $is_variation = $product instanceof \WC_Product_Variation;

      $results[] = [
        'id'           => $id,
        'name'         => $product->get_name(),
        'type'         => $product->get_type(),
        'product_id'   => $is_variation ? $product->get_parent_id() : $id,
        'variation_id' => $is_variation ? $id : null,
      ];
    }

    return rest_ensure_response( $results );
  }

  public function memberships_merge_webhook_consumer( \WP_REST_Request $request ) {
    $header = 'X-WicketEvents-Signature';
    $signature = $request->get_header( $header );
    $response = new WP_REST_Response(['error' => 'Authentication error.'], 401);
    if(!empty($signature)) { 
      $mship_options = get_option( 'wicket_membership_plugin_options' );
      $key = $mship_options['wicket_mship_membership_merge_key'];
      $payload = file_get_contents('php://input');
      $error = 'Authentication error: '.$signature;
      if(wicket_get_option('wicket_admin_settings_environment') != 'prod') {
        $debug = true;
        $error .= ' | '. hash_hmac('sha256', $payload, $key).' | '.$payload;
      }
      $response = new WP_REST_Response( ['error' => $error], 401);
      $test_request = json_decode($payload);
      if(!empty($test_request->test)) {
        $response = new WP_REST_Response(['success' => 'Caught a test request'], 200);
      } else if( !empty($payload) && $signature == hash_hmac('sha256', $payload, $key) ) {
        $merge_data = json_decode($payload, true);
        $merge_data = $merge_data['events'][0];
        $merge_from = $merge_data['relationships']['source_person']['data']['id'];
        if(empty($merge_from)) {
          $merge_from = $merge_data['relationships']['other_person']['data']['id'];
        }
        $merge_to = $merge_data['attributes']['uuid'];
        $user = get_user_by('login', $merge_from);
        if(empty($user)) {
          $response = new WP_REST_Response(['error' => 'Merge from user not found with uuid'], 400);
        } else {
          $response = Admin_Controller::update_memberships_owner( $user->ID, $merge_to);
        }  
      }  
    }
    if(!empty($debug)) {
      Utilities::wc_log_mship_error( ['Merge Webhook Run' => [
        'signature' => $signature, 
        'payload' => $payload, 
        'merge_data' => $merge_data, 
        'merge_from' => $merge_from,
        'merge_to' => $merge_to,
        'response' => $response->get_data()]
      ] );
    } else {
      Utilities::wc_log_mship_error( ['Merge Webhook Run' => [
        'merge_from' => $merge_from,
        'merge_to' => $merge_to,
        'response' => $response->get_data()]
      ] );
    }
    return rest_ensure_response( $response );      
  }

  public function delete_all_person_memberships( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $person_uuid = $params['person_uuid'] ?? null;
    if ( empty( $person_uuid ) ) {
      return new \WP_REST_Response(['success' => false, 'error' => 'Missing person_uuid'], 400);
    }
    $result = Utilities::delete_all_person_memberships_from_mdp( $person_uuid );
    return new \WP_REST_Response(['success' => true, 'result' => $result], 200);
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

  public function import_membership_bundle( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $response = (new Bundle_Import_Controller() )->create_bundle( $params );
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

  public function bundle_admin_manage_status( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $response = Admin_Controller::bundle_admin_manage_status( $params['post_id'], $params['status']);
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

  public function get_bundle_info( \WP_REST_Request $request ) {
    $params    = $request->get_params();
    $bundle_ids = isset( $params['filter']['bundle_id'] ) ? (array) $params['filter']['bundle_id'] : [];
    return rest_ensure_response( Membership_Controller::get_bundle_info( $bundle_ids ) );
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

  public function create_renewal_order( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $response = Admin_Controller::create_renewal_order( $params );
    return rest_ensure_response( $response );
  }

  /**
   * Get every published WP page for admin page-picker UIs (e.g. the tier renewal
   * form page selector). Queried directly via get_posts() instead of the core
   * wp/v2/pages REST endpoint because third-party visibility plugins (e.g. WP
   * Private Content Plus) hook rest_prepare_page and silently drop restricted
   * pages from that endpoint's response, hiding valid pages from the picker.
   *
   * WP Private Content Plus also hooks pre_get_posts on every REST request
   * (its `isset( $query->query_vars['s'] )` check is always true under
   * REST_REQUEST, since WP_Query always sets an 's' var, even empty) and
   * excludes restricted posts there too. We bypass that specific filter for
   * this query only via its own 'disable_restriction_checks' escape hatch.
   *
   * @param  \WP_REST_Request  $request  The REST request (no parameters used).
   *
   * @return \WP_REST_Response  List of pages as [{ id, title: { rendered } }, ...].
   */
  public function get_all_wp_pages( \WP_REST_Request $request ) {
    // Bypass WP Private Content Plus (WPCP) restriction checks for this query only.
    // WPCP's pre_get_posts handler excludes member/role/users-restricted pages any
    // time isset($query->query_vars['s']) is true under REST_REQUEST — and WP_Query
    // always sets 's' (even as ''), so it fires on every REST query, not just real
    // searches. Renewal-form page pickers are staff-only admin screens and must list
    // every page regardless of front-end visibility restrictions, so we disable the
    // check rather than let WPCP silently drop restricted pages from the results.
    add_filter( 'disable_restriction_checks', '__return_true' );

    $pages = get_posts( array(
      'post_type'   => 'page',
      'post_status' => 'publish',
      'numberposts' => -1,
      'orderby'     => 'title',
      'order'       => 'ASC',
    ) );

    // Re-enable WP Private Content Plus (WPCP) restriction checks now that our query is done.
    remove_filter( 'disable_restriction_checks', '__return_true' );

    $response = array_map( function( $page ) {
      return array(
        'id'    => $page->ID,
        'title' => array( 'rendered' => get_the_title( $page ) ),
      );
    }, $pages );

    return rest_ensure_response( $response );
  }

  /**
   * Normalise a REST `exclude` parameter into a list of post IDs.
   *
   * The React pickers pass their "already in use" lists either as a comma-separated string
   * (product IDs) or as an array (variation IDs), so both shapes are accepted here.
   *
   * @param  mixed  $raw  Raw parameter value: comma-separated string, array, or empty.
   *
   * @return array<int, int>  Positive integer IDs, empty when nothing was supplied.
   */
  private function parse_exclude_ids( $raw ) {
    if ( empty( $raw ) ) {
      return array();
    }

    $ids = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
    $ids = array_map( 'absint', $ids );

    // Drop zeros so a stray empty segment (e.g. a trailing comma) can't become ID 0.
    return array_values( array_filter( $ids ) );
  }

  /**
   * Normalise a REST `per_page` parameter into a wc_get_products() `limit`.
   *
   * Mirrors the /wc/v3 contract the product pickers were built against so replacing that route
   * does not change how many rows they receive. WooCommerce treats -1 as "no limit"; any other
   * non-positive value is meaningless, so fall back to /wc/v3's default of 10 rather than
   * returning an empty list and making the picker look broken.
   *
   * @param  mixed  $raw  Raw parameter value; null or empty when the caller omitted it.
   *
   * @return int  Row limit suitable for wc_get_products()'s `limit` argument.
   */
  private function parse_per_page_param( $raw ) {
    $per_page = (int) $raw;

    return ( -1 === $per_page || $per_page > 0 ) ? $per_page : 10;
  }

  /**
   * Normalise a REST `status` parameter for wc_get_products().
   *
   * Defaults to `publish` so an omitted or empty parameter can never widen the result set to
   * drafts or private products — the pickers only ever ask for published rows.
   *
   * @param  mixed  $raw  Raw parameter value; null or empty when the caller omitted it.
   *
   * @return string  A single post status slug.
   */
  private function parse_status_param( $raw ) {
    return ! empty( $raw ) ? sanitize_text_field( (string) $raw ) : 'publish';
  }

  /**
   * Get published WooCommerce products for the plugin's admin product pickers.
   *
   * Queried here rather than through WooCommerce's own /wc/v3/products endpoint because
   * third-party visibility plugins filter that route for every caller. WP Private Content
   * Plus hooks pre_get_posts and, under REST_REQUEST, excludes any post whose
   * `_wppcp_post_page_visibility` is member/role/users. Two things go wrong on the pickers
   * as a result:
   *
   *   1. Restricted products disappear from the list entirely.
   *   2. WPCP calls $query->set( 'post__not_in', ... ), overwriting rather than merging.
   *      WooCommerce maps its REST `exclude` param onto that same key, so a single restricted
   *      post of ANY type anywhere on the site discards the picker's "already in use" list and
   *      products assigned to other tiers reappear as selectable.
   *
   * Keeping this on the plugin's own namespace means the WPCP bypass applies only to requests
   * this plugin's admin screens make — /wc/v3/products keeps its normal filtered behaviour for
   * WooCommerce itself and every other consumer on the site.
   *
   * @see    get_all_wp_pages()  Same workaround applied to the page pickers.
   *
   * Deliberately honours `status` and `per_page` rather than hardcoding them. This endpoint
   * exists only to sidestep WPCP, so it stays a drop-in replacement for /wc/v3/products and
   * leaves how much data the pickers pull exactly as it was. Changing pagination here would be
   * an unrelated behaviour change smuggled into a bug fix.
   *
   * @see    get_all_wp_pages()  Same workaround applied to the page pickers.
   *
   * @param  \WP_REST_Request  $request  Accepts `type` (single product type slug; omit for all),
   *                                     `exclude` (comma-separated or array of product IDs),
   *                                     `status` (post status; defaults to publish) and
   *                                     `per_page` (result cap; -1 for all, defaults to 10 as
   *                                     /wc/v3 does). Pagination beyond the first page is not
   *                                     supported — no caller needs it.
   *
   * @return \WP_REST_Response  List of products as [{ id, name, type }, ...].
   */
  public function get_all_wc_products( \WP_REST_Request $request ) {
    // Bail cleanly when WooCommerce is unavailable rather than fataling on wc_get_products().
    if ( ! function_exists( 'wc_get_products' ) ) {
      return rest_ensure_response( array() );
    }

    $args = array(
      'status'  => $this->parse_status_param( $request->get_param( 'status' ) ),
      'limit'   => $this->parse_per_page_param( $request->get_param( 'per_page' ) ),
      'orderby' => 'title',
      'order'   => 'ASC',
      'return'  => 'objects',
    );

    $type = $request->get_param( 'type' );
    if ( ! empty( $type ) ) {
      $args['type'] = sanitize_text_field( $type );
    }

    $exclude = $this->parse_exclude_ids( $request->get_param( 'exclude' ) );
    if ( ! empty( $exclude ) ) {
      $args['exclude'] = $exclude;
    }

    // Bypass WP Private Content Plus restriction checks for this query only, via its own
    // 'disable_restriction_checks' escape hatch. These pickers are staff-only admin screens
    // and must list every product regardless of front-end visibility restrictions.
    add_filter( 'disable_restriction_checks', '__return_true' );

    $products = wc_get_products( $args );

    // Re-enable WP Private Content Plus restriction checks now that our query is done.
    remove_filter( 'disable_restriction_checks', '__return_true' );

    $response = array_map( function( $product ) {
      return array(
        'id'   => $product->get_id(),
        'name' => $product->get_name(),
        'type' => $product->get_type(),
      );
    }, $products );

    return rest_ensure_response( $response );
  }

  /**
   * Get the published variations of a single WooCommerce product for the admin pickers.
   *
   * Companion to get_all_wc_products() — WooCommerce's own variations route sits under
   * /wc/v3/products/{id}/variations and is filtered by the same WP Private Content Plus
   * pre_get_posts handler, which also clobbers the `exclude` list of variations already
   * assigned to other tiers.
   *
   * @see    get_all_wc_products()  Parent-product equivalent, with the full explanation.
   *
   * @param  \WP_REST_Request  $request  Requires `id` (parent product ID) from the route;
   *                                     accepts `exclude` (comma-separated or array of
   *                                     variation IDs), plus `status` and `per_page`, honoured
   *                                     for the same drop-in reasons as get_all_wc_products().
   *
   * @return \WP_REST_Response  List of variations as [{ id, name }, ...], matching the
   *                            `id` and `name` fields of the /wc/v3 variations response.
   */
  public function get_wc_product_variations( \WP_REST_Request $request ) {
    if ( ! function_exists( 'wc_get_products' ) ) {
      return rest_ensure_response( array() );
    }

    $parent_id = absint( $request->get_param( 'id' ) );
    if ( ! $parent_id ) {
      return rest_ensure_response( array() );
    }

    $args = array(
      'type'   => 'variation',
      'parent' => $parent_id,
      'status' => $this->parse_status_param( $request->get_param( 'status' ) ),
      'limit'  => $this->parse_per_page_param( $request->get_param( 'per_page' ) ),
      'return' => 'objects',
    );

    $exclude = $this->parse_exclude_ids( $request->get_param( 'exclude' ) );
    if ( ! empty( $exclude ) ) {
      $args['exclude'] = $exclude;
    }

    // Same WPCP bypass as get_all_wc_products(), scoped to this single query.
    add_filter( 'disable_restriction_checks', '__return_true' );

    $variations = wc_get_products( $args );

    remove_filter( 'disable_restriction_checks', '__return_true' );

    // The switch-membership and create-renewal-order pickers label options with the variation
    // name, so `name` has to be present here. wc_get_formatted_variation() is called with the
    // same arguments WooCommerce's own /wc/v3 variations controller uses (flat, values only),
    // so those labels read identically to before this endpoint replaced that route.
    $response = array_map( function( $variation ) {
      return array(
        'id'   => $variation->get_id(),
        'name' => wc_get_formatted_variation( $variation, true, false, false ),
      );
    }, $variations );

    return rest_ensure_response( $response );
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
      return new \WP_Error('Membership API read permission denied', 'You do not have permission to perform this action.', array( 'status' => $this->authorization_status_code() ) );
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
      return new \WP_Error('Membership API write permission denied', 'You do not have permission to perform this action.', array( 'status' => $this->authorization_status_code() ) );
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

   /**
   * Transfer Membership handler
   */
  public function transfer_membership( \WP_REST_Request $request ) {
    $params = $request->get_params();
    if ( empty( $params['membership_post_id'] ) || empty( $params['new_owner_uuid'] ) ) {
      return new \WP_REST_Response( [ 'success' => false, 'error' => ['Missing required parameters.', $params] ], 400 );
    }
    $result = Admin_Controller::transfer_membership( $params['membership_post_id'], $params['new_owner_uuid'] );
    return new \WP_REST_Response( [ 'success' => $result ], 200 );
  }

  /**
   * Switch Membership handler
   */
  public function switch_membership( \WP_REST_Request $request ) {
    $params = $request->get_params();
    if ( empty( $params['membership_post_id'] ) || empty( $params['switch_post_id'] ) || empty( $params['switch_type'] ) ) {
      return new \WP_REST_Response( [ 'success' => false, 'error' => ['Missing required parameters.', $params] ], 400 );
    }
    return Admin_Controller::switch_membership_request( $params );
  }
}
