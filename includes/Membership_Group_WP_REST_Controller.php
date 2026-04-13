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
    $this->namespace = 'wicket_member/v1';
  }

  /**
   * Register all group membership REST routes.
   */
  public function register_routes() {

    // -------------------------------------------------------------------------
    // Group entity retrieval
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // TODO stubs — no backing business logic yet (see TODO.md)
    // -------------------------------------------------------------------------
    // TODO: POST /group/{id}/create_renewal_order — blocked on group ownership model
    //       and subscription line item structure being finalised.

    // TODO: GET  /group_memberships              — list/search/filter group memberships
    // TODO: GET  /group_membership_filters       — filter options for group membership list UI
    // TODO: GET  /get_group_membership_callouts  — group-level renewal/grace callouts
    // TODO: POST /group                          — create a new group membership
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
