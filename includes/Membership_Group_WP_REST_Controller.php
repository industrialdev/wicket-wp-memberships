<?php

namespace Wicket_Memberships;

use Wicket_Memberships\Group_Admin_Controller;
use \WP_REST_Response;

/**
 * REST routes and methods for Membership Groups.
 *
 * Mirrors the shape of Membership_WP_REST_Controller but operates exclusively
 * on membership group (wicket_mship_group) posts.  All business logic is
 * delegated to Group_Admin_Controller and the Membership_Group model.
 *
 * Tier-management, individual-membership imports, MDP person merges, and org
 * browsing endpoints are intentionally absent — those concerns remain in
 * Membership_WP_REST_Controller or Membership_Group_Config_WP_REST_Controller.
 *
 * Routes with no backing business logic yet are registered as TODO stubs
 * and documented in TODO.md.
 *
 * @package Wicket_Memberships
 */
class Membership_Group_WP_REST_Controller extends \WP_REST_Controller {

  public function __construct() {
    add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    // Remove the native WP REST collection and single-item routes for the group
    // membership CPT so all create/read/update goes through our dedicated routes.
    add_filter( 'rest_endpoints', [ $this, 'remove_native_group_cpt_routes' ] );
    $this->namespace = 'wicket_member/v1';
  }

  /**
   * Drop the auto-registered WP REST routes for the group membership CPT.
   *
   * WordPress registers /wp/v2/{slug} (collection) and /wp/v2/{slug}/(?P<id>[\d]+)
   * (single item) for any CPT with show_in_rest => true. Those routes allow
   * arbitrary creation and mutation that bypasses our validation logic, so we
   * remove them here.
   *
   * @param array $endpoints
   * @return array
   */
  public function remove_native_group_cpt_routes( array $endpoints ): array {
    $slug = Helper::get_membership_group_cpt_slug();
    unset( $endpoints[ '/wp/v2/' . $slug ] );
    unset( $endpoints[ '/wp/v2/' . $slug . '/(?P<id>[\d]+)' ] );
    return $endpoints;
  }

  /**
   * Register all group membership REST routes.
   */
  public function register_routes() {

    // -------------------------------------------------------------------------
    // Group entity retrieval
    // -------------------------------------------------------------------------

    /**
     * List/search/filter group memberships grouped by organisation.
     *
     * GET /wicket_member/v1/group_memberships
     */
    register_rest_route( $this->namespace, '/group_memberships', [
      [
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => [ $this, 'get_group_memberships' ],
        'permission_callback' => [ $this, 'permissions_check_read' ],
        'args'                => [
          'page' => [
            'type'        => 'integer',
            'description' => 'Paginated results page.',
          ],
          'posts_per_page' => [
            'type'        => 'integer',
            'description' => 'Paginated results per page.',
          ],
          'status' => [
            'type'        => 'string',
            'description' => 'Membership group status filter.',
          ],
          'search' => [
            'type'        => 'string',
            'description' => 'Free-text search across grouped rows.',
          ],
          'order_col' => [
            'type'        => 'string',
            'description' => 'Order by column name.',
          ],
          'order_dir' => [
            'type'        => 'string',
            'description' => 'Order by direction.',
          ],
          'filter' => [
            'type'                 => 'object',
            'description'          => 'Optional exact-match filters (associative: key => value).',
            'additionalProperties' => [ 'type' => 'string' ],
          ],
        ],
      ],
    ] );

    /**
     * Get a group membership record by its post ID.
     *
     * GET /wicket_member/v1/group_membership_entity?group_post_id=123
     */
    register_rest_route( $this->namespace, '/group_membership_entity', [
      [
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => [ $this, 'get_group_entity' ],
        'permission_callback' => [ $this, 'permissions_check_read' ],
        'args'                => [
          'group_post_id' => [
            'required'    => true,
            'type'        => 'integer',
            'description' => 'The WP post ID of the membership group.',
          ],
        ],
      ],
    ] );

    /**
     * Update editable fields on a group membership post.
     *
     * POST /wicket_member/v1/group_membership_entity/{id}/update
     */
    register_rest_route( $this->namespace, '/group_membership_entity/(?P<group_post_id>\d+)/update', [
      [
        'methods'             => \WP_REST_Server::CREATABLE,
        'callback'            => [ $this, 'update_group_entity' ],
        'permission_callback' => [ $this, 'permissions_check_write' ],
      ],
    ] );

    // -------------------------------------------------------------------------
    // Group admin — status
    // -------------------------------------------------------------------------

    /**
     * Get available status options (all, or valid transitions from current).
     *
     * GET /wicket_member/v1/group/admin/status_options?group_post_id=123
     */
    register_rest_route( $this->namespace, '/group/admin/status_options', [
      [
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => [ $this, 'get_group_admin_status_options' ],
        'permission_callback' => [ $this, 'permissions_check_read' ],
      ],
    ] );

    /**
     * Transition a group membership to a new status.
     *
     * POST /wicket_member/v1/group/admin/manage_status
     * Body: { group_post_id, status }
     */
    register_rest_route( $this->namespace, '/group/admin/manage_status', [
      [
        'methods'             => \WP_REST_Server::CREATABLE,
        'callback'            => [ $this, 'group_admin_manage_status' ],
        'permission_callback' => [ $this, 'permissions_check_write' ],
      ],
    ] );

    // -------------------------------------------------------------------------
    // Group admin — edit page
    // -------------------------------------------------------------------------

    /**
     * Get all data needed to populate the group membership edit form.
     *
     * GET /wicket_member/v1/group/admin/get_edit_page_info?group_post_id=123
     */
    register_rest_route( $this->namespace, '/group/admin/get_edit_page_info', [
      [
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => [ $this, 'get_group_edit_page_info' ],
        'permission_callback' => [ $this, 'permissions_check_read' ],
        'args'                => [
          'group_post_id' => [
            'required'    => true,
            'type'        => 'integer',
            'description' => 'The WP post ID of the membership group.',
          ],
        ],
      ],
    ] );

    // -------------------------------------------------------------------------
    // Group ownership
    // -------------------------------------------------------------------------

    /**
     * Change the membership owner on a group post.
     *
     * POST /wicket_member/v1/group/{id}/change_owner
     * Body: { new_owner_uuid }
     */
    register_rest_route( $this->namespace, '/group/(?P<group_post_id>\d+)/change_owner', [
      [
        'methods'             => \WP_REST_Server::CREATABLE,
        'callback'            => [ $this, 'update_group_change_ownership' ],
        'permission_callback' => [ $this, 'permissions_check_write' ],
      ],
    ] );

    /**
     * Return available filter options for the group membership list UI.
     *
     * GET /wicket_member/v1/group_membership_filters
     */
    register_rest_route( $this->namespace, '/group_membership_filters', [
      [
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => [ $this, 'get_group_membership_filters' ],
        'permission_callback' => [ $this, 'permissions_check_read' ],
      ],
    ] );

    /**
     * Return total member count and per-tier breakdown for a group.
     *
     * GET /wicket_member/v1/group/{id}/members_by_tier
     */
    register_rest_route( $this->namespace, '/group/(?P<group_post_id>\d+)/members_by_tier', [
      [
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => [ $this, 'get_group_members_by_tier' ],
        'permission_callback' => [ $this, 'permissions_check_read' ],
        'args'                => [
          'group_post_id' => [
            'required'    => true,
            'type'        => 'integer',
            'description' => 'The WP post ID of the membership group.',
          ],
        ],
      ],
    ] );

    /**
     * Create a new group membership.
     *
     * POST /wicket_member/v1/group
     * Body: { name, membership_group_config_id, org_uuid, owner_uuid, start_date }
     */
    register_rest_route( $this->namespace, '/group', [
      [
        'methods'             => \WP_REST_Server::CREATABLE,
        'callback'            => [ $this, 'create_group_membership' ],
        'permission_callback' => [ $this, 'permissions_check_write' ],
        'args'                => [
          'name' => [
            'required'    => true,
            'type'        => 'string',
            'description' => 'Post title for the group.',
          ],
          'membership_group_config_id' => [
            'required'    => true,
            'type'        => 'integer',
            'description' => 'Post ID of the linked Membership_Group_Config.',
          ],
          'org_uuid' => [
            'required'    => true,
            'type'        => 'string',
            'description' => 'MDP organisation UUID.',
          ],
          'owner_uuid' => [
            'required'    => true,
            'type'        => 'string',
            'description' => 'MDP person UUID of the group owner.',
          ],
          'start_date' => [
            'required'    => true,
            'type'        => 'string',
            'description' => 'ISO 8601 date string. Normalized to UTC before use.',
          ],
        ],
      ],
    ] );

    // -------------------------------------------------------------------------
    // TODO stubs — no backing business logic yet (see TODO.md)
    // -------------------------------------------------------------------------
    // TODO: POST /group/{id}/create_renewal_order — blocked on group subscription
    //       line item structure being finalised.

    // TODO: GET  /get_group_membership_callouts  — group-level renewal/grace callouts
    // TODO: POST /group/{id}/add_member          — add individual to group
    // TODO: POST /group/{id}/remove_member       — remove individual from group (cancel or continue)
    // TODO: POST /group/{id}/move_member         — move individual to another group
    // TODO: POST /group/{id}/cancel              — cancel group with options (cancel all / continue as individual)
    // TODO: GET  /group/{id}/members             — list individual memberships in a group
    // TODO: POST /group/{id}/import_members      — bulk CSV import of members into a group
  }

  // ---------------------------------------------------------------------------
  // Handlers — implemented
  // ---------------------------------------------------------------------------

  /**
   * GET /group_memberships
   */
  public function get_group_memberships( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $response = Group_Admin_Controller::get_group_memberships_list(
      $params['page'] ?? 1,
      $params['posts_per_page'] ?? 25,
      $params['status'] ?? 'all',
      $params['search'] ?? '',
      $params['filter'] ?? [],
      $params['order_col'] ?? 'post_modified',
      $params['order_dir'] ?? 'desc'
    );
    return rest_ensure_response( $response );
  }

  /**
   * GET /group_membership_entity
   */
  public function get_group_entity( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $response = Group_Admin_Controller::get_group_entity_records( (int) $params['group_post_id'] );
    return rest_ensure_response( $response );
  }

  /**
   * POST /group_membership_entity/{group_post_id}/update
   */
  public function update_group_entity( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $response = Group_Admin_Controller::update_group_entity_record( $params );
    return rest_ensure_response( $response );
  }

  /**
   * GET /group/admin/status_options
   */
  public function get_group_admin_status_options( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $group_post_id = ! empty( $params['group_post_id'] ) ? (int) $params['group_post_id'] : null;
    $response = Group_Admin_Controller::get_admin_status_options( $group_post_id );
    return rest_ensure_response( $response );
  }

  /**
   * POST /group/admin/manage_status
   */
  public function group_admin_manage_status( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $response = Group_Admin_Controller::admin_manage_status(
      (int) $params['group_post_id'],
      (string) $params['status']
    );
    return rest_ensure_response( $response );
  }

  /**
   * GET /group/admin/get_edit_page_info
   */
  public function get_group_edit_page_info( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $response = Group_Admin_Controller::get_group_edit_page_info( (int) $params['group_post_id'] );
    return rest_ensure_response( $response );
  }

  /**
   * GET /group_membership_filters
   */
  public function get_group_membership_filters( \WP_REST_Request $request ) {
    $response = Group_Admin_Controller::get_group_membership_filters();
    return rest_ensure_response( $response );
  }

  /**
   * GET /group/{group_post_id}/members_by_tier
   */
  public function get_group_members_by_tier( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $response = Group_Admin_Controller::get_group_members_by_tier( (int) $params['group_post_id'] );
    return rest_ensure_response( $response );
  }

  /**
   * POST /group
   */
  public function create_group_membership( \WP_REST_Request $request ) {
    $params = $request->get_params();

    try {
      $group = Membership_Group::create(
        sanitize_text_field( $params['name'] ?? '' ),
        (int) ( $params['membership_group_config_id'] ?? 0 ),
        sanitize_text_field( $params['org_uuid'] ?? '' ),
        sanitize_text_field( $params['owner_uuid'] ?? '' ),
        sanitize_text_field( $params['start_date'] ?? '' )
      );
    } catch ( \RuntimeException $e ) {
      return new WP_REST_Response( [ 'error' => $e->getMessage() ], 400 );
    }

    if ( null === $group ) {
      return new WP_REST_Response( [ 'error' => 'Failed to create group membership. Check server logs for details.' ], 500 );
    }

    return new WP_REST_Response( [
      'success'  => 'Group membership created.',
      'response' => Group_Admin_Controller::get_group_entity_records( $group->post_id ),
    ], 200 );
  }

  /**
   * POST /group/{group_post_id}/change_owner
   */
  public function update_group_change_ownership( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $response = Group_Admin_Controller::update_group_change_ownership( $params );
    return rest_ensure_response( $response );
  }

  // ---------------------------------------------------------------------------
  // Permissions
  // ---------------------------------------------------------------------------

  /**
   * Check permissions to read.
   */
  public function permissions_check_read( $request ) {
    if ( ! empty( $_ENV['ALLOW_LOCAL_IMPORTS'] ) ) {
      return true;
    }
    if ( ! current_user_can( Wicket_Memberships::WICKET_MEMBERSHIPS_CAPABILITY ) ) {
      return new WP_REST_Response( [ 'error' => 'Authentication required.' ], 401 );
    }
    return true;
  }

  /**
   * Check permissions to write.
   */
  public function permissions_check_write( $request ) {
    if ( ! empty( $_ENV['ALLOW_LOCAL_IMPORTS'] ) ) {
      return true;
    }
    if ( ! current_user_can( Wicket_Memberships::WICKET_MEMBERSHIPS_CAPABILITY ) ) {
      return new WP_REST_Response( [ 'error' => 'Authentication required.' ], 401 );
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
