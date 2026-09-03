<?php

namespace Wicket_Memberships;

use Wicket_Memberships\Membership_Bundle_Admin_Controller;
use \WP_REST_Response;

/**
 * REST routes and methods for Membership Bundles.
 *
 * Mirrors the shape of Membership_WP_REST_Controller but operates exclusively
 * on membership bundle (wicket_mship_bundle) posts.  All business logic is
 * delegated to Membership_Bundle_Admin_Controller and the Membership_Bundle model.
 *
 * Tier-management, individual-membership imports, MDP person merges, and org
 * browsing endpoints are intentionally absent — those concerns remain in
 * Membership_WP_REST_Controller or Membership_Bundle_Config_WP_REST_Controller.
 *
 * @package Wicket_Memberships
 */
class Membership_Bundle_WP_REST_Controller extends \WP_REST_Controller {

  public function __construct() {
    add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    // Remove the native WP REST collection and single-item routes for the bundle
    // membership CPT so all create/read/update goes through our dedicated routes.
    add_filter( 'rest_endpoints', [ $this, 'remove_native_bundle_cpt_routes' ] );
    $this->namespace = 'wicket_member/v1';
  }

  /**
   * Drop the auto-registered WP REST routes for the membership bundle CPT.
   *
   * WordPress registers /wp/v2/{slug} (collection) and /wp/v2/{slug}/(?P<id>[\d]+)
   * (single item) for any CPT with show_in_rest => true. Those routes allow
   * arbitrary creation and mutation that bypasses our validation logic, so we
   * remove them here.
   *
   * @param array $endpoints
   * @return array
   */
  public function remove_native_bundle_cpt_routes( array $endpoints ): array {
    $slug = Helper::get_membership_bundle_cpt_slug();
    unset( $endpoints[ '/wp/v2/' . $slug ] );
    unset( $endpoints[ '/wp/v2/' . $slug . '/(?P<id>[\d]+)' ] );
    return $endpoints;
  }

  /**
   * Register all membership bundle REST routes.
   */
  public function register_routes() {

    // -------------------------------------------------------------------------
    // Group entity retrieval
    // -------------------------------------------------------------------------

    /**
     * List/search/filter membership bundles grouped by organisation.
     *
     * GET /wicket_member/v1/membership_bundles
     */
    register_rest_route( $this->namespace, '/membership_bundles', [
      [
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => [ $this, 'get_membership_bundles' ],
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
            'description' => 'Membership bundle status filter.',
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
     * Get a membership bundle record by its post ID.
     *
     * GET /wicket_member/v1/membership_bundle_entity?bundle_post_id=123
     */
    register_rest_route( $this->namespace, '/membership_bundle_entity', [
      [
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => [ $this, 'get_bundle_entity' ],
        'permission_callback' => [ $this, 'permissions_check_read' ],
        'args'                => [
          'bundle_post_id' => [
            'required'    => true,
            'type'        => 'integer',
            'description' => 'The WP post ID of the membership bundle.',
          ],
        ],
      ],
    ] );

    /**
     * Update editable fields on a membership bundle post.
     *
     * POST /wicket_member/v1/membership_bundle_entity/{id}/update
     */
    register_rest_route( $this->namespace, '/membership_bundle_entity/(?P<bundle_post_id>\d+)/update', [
      [
        'methods'             => \WP_REST_Server::CREATABLE,
        'callback'            => [ $this, 'update_bundle_entity' ],
        'permission_callback' => [ $this, 'permissions_check_write' ],
      ],
    ] );

    // -------------------------------------------------------------------------
    // Group admin — status
    // -------------------------------------------------------------------------

    /**
     * Get available status options (all, or valid transitions from current).
     *
     * GET /wicket_member/v1/bundle/admin/status_options?bundle_post_id=123
     */
    register_rest_route( $this->namespace, '/bundle/admin/status_options', [
      [
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => [ $this, 'get_bundle_admin_status_options' ],
        'permission_callback' => [ $this, 'permissions_check_read' ],
      ],
    ] );

    /**
     * Transition a membership bundle to a new status.
     *
     * POST /wicket_member/v1/bundle/admin/manage_status
     * Body: { bundle_post_id, status }
     */
    register_rest_route( $this->namespace, '/bundle/admin/manage_status', [
      [
        'methods'             => \WP_REST_Server::CREATABLE,
        'callback'            => [ $this, 'bundle_bundle_admin_manage_status' ],
        'permission_callback' => [ $this, 'permissions_check_write' ],
      ],
    ] );

    // -------------------------------------------------------------------------
    // Group admin — edit page
    // -------------------------------------------------------------------------

    /**
     * Get all data needed to populate the membership bundle edit form.
     *
     * GET /wicket_member/v1/bundle/admin/get_edit_page_info?bundle_group_uuid=...
     */
    register_rest_route( $this->namespace, '/bundle/admin/get_edit_page_info', [
      [
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => [ $this, 'get_bundle_edit_page_info' ],
        'permission_callback' => [ $this, 'permissions_check_read' ],
        'args'                => [
          'bundle_group_uuid' => [
            'required'          => true,
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'description'       => 'The membership_bundle_group_uuid shared by all posts in the series.',
          ],
        ],
      ],
    ] );

    // -------------------------------------------------------------------------
    // Group ownership
    // -------------------------------------------------------------------------

    /**
     * Change the membership owner on a bundle post.
     *
     * POST /wicket_member/v1/bundle/{id}/change_owner
     * Body: { new_owner_uuid }
     */
    register_rest_route( $this->namespace, '/bundle/(?P<bundle_post_id>\d+)/change_owner', [
      [
        'methods'             => \WP_REST_Server::CREATABLE,
        'callback'            => [ $this, 'update_bundle_change_ownership' ],
        'permission_callback' => [ $this, 'permissions_check_write' ],
      ],
    ] );

    /**
     * Return available filter options for the membership bundle list UI.
     *
     * GET /wicket_member/v1/membership_bundle_filters
     */
    register_rest_route( $this->namespace, '/membership_bundle_filters', [
      [
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => [ $this, 'get_membership_bundle_filters' ],
        'permission_callback' => [ $this, 'permissions_check_read' ],
      ],
    ] );

    /**
     * Return total member count and per-tier breakdown for a bundle.
     *
     * GET /wicket_member/v1/bundle/{id}/members_by_tier
     */
    register_rest_route( $this->namespace, '/bundle/(?P<bundle_post_id>\d+)/members_by_tier', [
      [
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => [ $this, 'get_bundle_members_by_tier' ],
        'permission_callback' => [ $this, 'permissions_check_read' ],
        'args'                => [
          'bundle_post_id' => [
            'required'    => true,
            'type'        => 'integer',
            'description' => 'The WP post ID of the membership bundle.',
          ],
        ],
      ],
    ] );

    /**
     * Create a new membership bundle.
     *
     * POST /wicket_member/v1/bundle
     * Body: { name, membership_bundle_config_id, org_uuid, owner_uuid, start_date }
     */
    register_rest_route( $this->namespace, '/bundle', [
      [
        'methods'             => \WP_REST_Server::CREATABLE,
        'callback'            => [ $this, 'create_membership_bundle' ],
        'permission_callback' => [ $this, 'permissions_check_write' ],
        'args'                => [
          'name' => [
            'required'    => true,
            'type'        => 'string',
            'description' => 'Post title for the bundle.',
          ],
          'membership_bundle_config_id' => [
            'required'    => true,
            'type'        => 'integer',
            'description' => 'Post ID of the linked Membership_Bundle_Config.',
          ],
          'org_uuid' => [
            'required'    => true,
            'type'        => 'string',
            'description' => 'MDP organisation UUID.',
          ],
          'owner_uuid' => [
            'required'    => true,
            'type'        => 'string',
            'description' => 'MDP person UUID of the bundle owner.',
          ],
          'start_date' => [
            'required'    => true,
            'type'        => 'string',
            'description' => 'ISO 8601 date string. Normalized to UTC before use.',
          ],
        ],
      ],
    ] );

    /**
     * Add an individual membership to a bundle (new or existing member).
     *
     * POST /wicket_member/v1/bundle/{bundle_post_id}/add_member
     * Body: { mode, tier_post_id, person_uuid|existing_membership_post_id, product_id? }
     */
    register_rest_route( $this->namespace, '/bundle/(?P<bundle_post_id>\d+)/add_member', [
      [
        'methods'             => \WP_REST_Server::CREATABLE,
        'callback'            => [ $this, 'add_member_to_bundle' ],
        'permission_callback' => [ $this, 'permissions_check_write' ],
        'args'                => [
          'bundle_post_id' => [
            'required'    => true,
            'type'        => 'integer',
            'description' => 'Post ID of the membership bundle.',
          ],
          'mode' => [
            'type'        => 'string',
            'description' => '"new" to create a fresh membership, "existing" to cancel an existing membership and create a new one.',
          ],
          'tier_post_id' => [
            'required'    => true,
            'type'        => 'integer',
            'description' => 'Post ID of the individual Membership_Tier CPT.',
          ],
          'person_uuid' => [
            'type'        => 'string',
            'description' => 'MDP person UUID. Required when mode = "new".',
          ],
          'existing_membership_post_id' => [
            'type'        => 'integer',
            'description' => 'Existing wicket_membership post ID to cancel. Required when mode = "existing".',
          ],
          'product_id' => [
            'type'        => 'integer',
            'description' => 'WC parent product ID. Auto-resolved from tier when omitted.',
          ],
          'variation_id' => [
            'type'        => 'integer',
            'description' => 'WC variation ID. When provided, stored as membership_product_id instead of parent product_id.',
          ],
        ],
      ],
    ] );

    /**
     * Remove an individual membership from a bundle (cancel or keep as individual).
     *
     * POST /wicket_member/v1/bundle/{bundle_post_id}/remove_member
     * Body: { membership_post_id, mode }
     */
    register_rest_route( $this->namespace, '/bundle/(?P<bundle_post_id>\d+)/remove_member', [
      [
        'methods'             => \WP_REST_Server::CREATABLE,
        'callback'            => [ $this, 'remove_member_from_bundle' ],
        'permission_callback' => [ $this, 'permissions_check_write' ],
        'args'                => [
          'bundle_post_id' => [
            'required'    => true,
            'type'        => 'integer',
            'description' => 'Post ID of the membership bundle.',
          ],
          'membership_post_id' => [
            'required'    => true,
            'type'        => 'integer',
            'description' => 'Post ID of the individual membership to remove.',
          ],
          'mode' => [
            'type'        => 'string',
            'description' => '"cancel" to end the membership immediately, "keep_as_individual" to convert to a standalone individual membership.',
          ],
        ],
      ],
    ] );

    /**
     * Move an individual membership from one bundle to another.
     *
     * POST /wicket_member/v1/bundle/{bundle_post_id}/move_individual_membership
     * Body: { membership_post_id, target_bundle_post_id }
     */
    register_rest_route( $this->namespace, '/bundle/(?P<bundle_post_id>\d+)/move_individual_membership', [
      [
        'methods'             => \WP_REST_Server::CREATABLE,
        'callback'            => [ $this, 'move_individual_membership' ],
        'permission_callback' => [ $this, 'permissions_check_write' ],
        'args'                => [
          'bundle_post_id' => [
            'required'    => true,
            'type'        => 'integer',
            'description' => 'Post ID of the source membership bundle.',
          ],
          'membership_post_id' => [
            'required'    => true,
            'type'        => 'integer',
            'description' => 'Post ID of the individual membership to move.',
          ],
          'target_bundle_post_id' => [
            'required'    => true,
            'type'        => 'integer',
            'description' => 'Post ID of the target membership bundle.',
          ],
        ],
      ],
    ] );

    /**
     * Cancel a membership bundle with configurable member handling.
     *
     * POST /wicket_member/v1/bundle/{bundle_post_id}/cancel
     * Body: { member_handling, timing? }
     */
    register_rest_route( $this->namespace, '/bundle/(?P<bundle_post_id>\d+)/cancel', [
      [
        'methods'             => \WP_REST_Server::CREATABLE,
        'callback'            => [ $this, 'cancel_bundle' ],
        'permission_callback' => [ $this, 'permissions_check_write' ],
        'args'                => [
          'bundle_post_id' => [
            'required'    => true,
            'type'        => 'integer',
            'description' => 'Post ID of the membership bundle to cancel.',
          ],
          'member_handling' => [
            'required'    => true,
            'type'        => 'string',
            'enum'        => [ 'cancel_all', 'keep_as_individual' ],
            'description' => '"cancel_all" to cancel all individual memberships, "keep_as_individual" to convert each to a standalone membership.',
          ],
          'timing' => [
            'required'    => false,
            'type'        => 'string',
            'enum'        => [ 'immediately', 'at_end_date' ],
            'description' => 'When to cancel. Required when member_handling is "cancel_all". "immediately" cancels now; "at_end_date" cancels at the bundle end date.',
          ],
        ],
      ],
    ] );

    /**
     * POST /wicket_member/v1/bundle/{bundle_post_id}/create_renewal_order
     */
    register_rest_route( $this->namespace, '/bundle/(?P<bundle_post_id>\d+)/create_renewal_order', [
      [
        'methods'             => \WP_REST_Server::CREATABLE,
        'callback'            => [ $this, 'create_bundle_renewal_order' ],
        'permission_callback' => [ $this, 'permissions_check_write' ],
        'args'                => [
          'bundle_post_id' => [
            'required'    => true,
            'type'        => 'integer',
            'description' => 'Post ID of the membership bundle.',
          ],
        ],
      ],
    ] );

    /**
     * POST /wicket_member/v1/bundle/{bundle_post_id}/confirm_renewal
     *
     * Member-facing confirm action for confirmation_renewal bundles. Distinct from
     * create_renewal_order above: this endpoint is gated to the bundle's actual owner
     * (not just the admin capability), and only works during the confirm window on a
     * bundle actually configured with renewal_type === 'confirmation_renewal'.
     */
    register_rest_route( $this->namespace, '/bundle/(?P<bundle_post_id>\d+)/confirm_renewal', [
      [
        'methods'             => \WP_REST_Server::CREATABLE,
        'callback'            => [ $this, 'confirm_bundle_renewal' ],
        'permission_callback' => [ $this, 'permissions_check_confirm_renewal' ],
        'args'                => [
          'bundle_post_id' => [
            'required'    => true,
            'type'        => 'integer',
            'description' => 'Post ID of the membership bundle.',
          ],
        ],
      ],
    ] );

  }

  // ---------------------------------------------------------------------------
  // Handlers — implemented
  // ---------------------------------------------------------------------------

  /**
   * GET /membership_bundles
   */
  public function get_membership_bundles( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $response = Membership_Bundle_Admin_Controller::get_membership_bundles_list(
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
   * GET /membership_bundle_entity
   */
  public function get_bundle_entity( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $response = Membership_Bundle_Admin_Controller::get_bundle_entity_records( (int) $params['bundle_post_id'] );
    return rest_ensure_response( $response );
  }

  /**
   * POST /membership_bundle_entity/{bundle_post_id}/update
   */
  public function update_bundle_entity( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $response = Membership_Bundle_Admin_Controller::update_bundle_entity_record( $params );
    return rest_ensure_response( $response );
  }

  /**
   * GET /bundle/admin/status_options
   */
  public function get_bundle_admin_status_options( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $bundle_post_id = ! empty( $params['bundle_post_id'] ) ? (int) $params['bundle_post_id'] : null;
    $response = Membership_Bundle_Admin_Controller::get_admin_status_options( $bundle_post_id );
    return rest_ensure_response( $response );
  }

  /**
   * POST /bundle/admin/manage_status
   */
  public function bundle_bundle_admin_manage_status( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $response = Membership_Bundle_Admin_Controller::bundle_admin_manage_status(
      (int) $params['bundle_post_id'],
      (string) $params['status']
    );
    return rest_ensure_response( $response );
  }

  /**
   * GET /bundle/admin/get_edit_page_info
   */
  public function get_bundle_edit_page_info( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $response = Membership_Bundle_Admin_Controller::get_bundle_edit_page_info( (string) $params['bundle_group_uuid'] );
    return rest_ensure_response( $response );
  }

  /**
   * GET /membership_bundle_filters
   */
  public function get_membership_bundle_filters( \WP_REST_Request $request ) {
    $response = Membership_Bundle_Admin_Controller::get_membership_bundle_filters();
    return rest_ensure_response( $response );
  }

  /**
   * GET /bundle/{bundle_post_id}/members_by_tier
   */
  public function get_bundle_members_by_tier( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $response = Membership_Bundle_Admin_Controller::get_bundle_members_by_tier( (int) $params['bundle_post_id'] );
    return rest_ensure_response( $response );
  }

  /**
   * POST /bundle
   */
  public function create_membership_bundle( \WP_REST_Request $request ) {
    $params = $request->get_params();

    try {
      $bundle = Membership_Bundle::create(
        sanitize_text_field( $params['name'] ?? '' ),
        (int) ( $params['membership_bundle_config_id'] ?? 0 ),
        sanitize_text_field( $params['org_uuid'] ?? '' ),
        sanitize_text_field( $params['owner_uuid'] ?? '' ),
        sanitize_text_field( $params['start_date'] ?? '' )
      );
    } catch ( \RuntimeException $e ) {
      return new WP_REST_Response( [ 'error' => $e->getMessage() ], 400 );
    }

    if ( null === $bundle ) {
      return new WP_REST_Response( [ 'error' => 'Failed to create membership bundle. Check server logs for details.' ], 500 );
    }

    return new WP_REST_Response( [
      'success'  => 'Membership bundle created.',
      'response' => Membership_Bundle_Admin_Controller::get_bundle_entity_records( $bundle->post_id ),
    ], 200 );
  }

  /**
   * POST /bundle/{bundle_post_id}/change_owner
   */
  public function update_bundle_change_ownership( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $response = Membership_Bundle_Admin_Controller::update_bundle_change_ownership( $params );
    return rest_ensure_response( $response );
  }

  /**
   * POST /bundle/{bundle_post_id}/add_member
   */
  public function add_member_to_bundle( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $mode   = sanitize_text_field( $params['mode'] ?? '' );

    if ( ! \in_array( $mode, [ 'new', 'existing' ], true ) ) {
      return new WP_REST_Response( [ 'error' => 'mode must be "new" or "existing".' ], 400 );
    }

    if ( $mode === 'new' && empty( $params['person_uuid'] ) ) {
      return new WP_REST_Response( [ 'error' => 'person_uuid is required when mode is "new".' ], 400 );
    }

    if ( $mode === 'existing' && empty( $params['existing_membership_post_id'] ) ) {
      return new WP_REST_Response( [ 'error' => 'existing_membership_post_id is required when mode is "existing".' ], 400 );
    }

    $result = Membership_Bundle_Admin_Controller::add_member( $params );

    if ( isset( $result['error'] ) ) {
      return new WP_REST_Response( [ 'error' => $result['error'], 'code' => $result['code'] ?? '' ], 400 );
    }

    return new WP_REST_Response( $result, 200 );
  }

  /**
   * POST /bundle/{bundle_post_id}/remove_member
   */
  public function remove_member_from_bundle( \WP_REST_Request $request ): \WP_REST_Response {
    $params = $request->get_params();
    $mode   = sanitize_text_field( $params['mode'] ?? '' );

    if ( ! \in_array( $mode, [ 'cancel', 'keep_as_individual' ], true ) ) {
      return new \WP_REST_Response( [ 'error' => 'mode must be "cancel" or "keep_as_individual".' ], 400 );
    }

    $result = Membership_Bundle_Admin_Controller::remove_member( $params );

    if ( isset( $result['error'] ) ) {
      return new \WP_REST_Response( [ 'error' => $result['error'] ], 400 );
    }

    return new \WP_REST_Response( $result, 200 );
  }

  /**
   * POST /bundle/{bundle_post_id}/cancel
   */
  public function cancel_bundle( \WP_REST_Request $request ): \WP_REST_Response {
    $params          = $request->get_params();
    $bundle_post_id   = (int) ( $params['bundle_post_id'] ?? 0 );
    $member_handling = sanitize_text_field( $params['member_handling'] ?? '' );
    $timing          = sanitize_text_field( $params['timing'] ?? '' );

    if ( ! \in_array( $member_handling, [ 'cancel_all', 'keep_as_individual' ], true ) ) {
      return new \WP_REST_Response( [ 'error' => 'member_handling must be "cancel_all" or "keep_as_individual".' ], 400 );
    }

    if ( $member_handling === 'cancel_all' && ! \in_array( $timing, [ 'immediately', 'at_end_date' ], true ) ) {
      return new \WP_REST_Response( [ 'error' => 'timing must be "immediately" or "at_end_date" when member_handling is "cancel_all".' ], 400 );
    }

    return Membership_Bundle_Admin_Controller::cancel_bundle( $bundle_post_id, $member_handling, $timing );
  }

  /**
   * POST /bundle/{bundle_post_id}/create_renewal_order
   *
   * Creates a WooCommerce renewal order off the bundle's existing subscription
   * using wcs_create_renewal_order(). Does not create a new subscription —
   * the bundle subscription carries all member line items and must remain the
   * parent so billing history stays intact.
   */
  public function create_bundle_renewal_order( \WP_REST_Request $request ): \WP_REST_Response {
    $bundle_post_id = (int) ( $request->get_param( 'bundle_post_id' ) ?? 0 );

    $validated = self::validate_bundle_and_subscription( $bundle_post_id );
    if ( is_wp_error( $validated ) ) {
      return new \WP_REST_Response( [ 'error' => $validated->get_error_message() ], (int) $validated->get_error_data()['status'] );
    }
    [ 'bundle' => $bundle, 'subscription' => $subscription ] = $validated;

    $renewal_order = wcs_create_renewal_order( $subscription );
    if ( is_wp_error( $renewal_order ) ) {
      return new \WP_REST_Response( [ 'error' => $renewal_order->get_error_message() ], 500 );
    }

    $order_url = admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $renewal_order->get_id(), 'https' );

    return new \WP_REST_Response( [
      'success'   => __( 'Renewal order created successfully.', 'wicket-memberships' ),
      'order_url' => $order_url,
      'order_id'  => $renewal_order->get_id(),
    ], 200 );
  }

  /**
   * POST /bundle/{bundle_post_id}/confirm_renewal
   *
   * Member-facing confirm action for a bundle configured with
   * renewal_type === 'confirmation_renewal'. Calls the same wcs_create_renewal_order()
   * the admin create_renewal_order endpoint uses — this plugin does not create a
   * second code path for the actual order creation, only for the permission/
   * validation/response-shape layer around it (see design decision in the
   * client-extensibility plan, Milestone 5).
   *
   * Deliberately a separate endpoint from create_bundle_renewal_order rather than a
   * reuse with a loosened permission check: the two callers have different trust
   * models. The admin tool is an unconditional manual override (capability check
   * only, no timing/config gating). This endpoint only works during the confirm
   * window, only for the bundle's actual owner, and only for bundles actually
   * configured with confirmation_renewal — coupling both into one method would mean
   * the admin override's permission callback grows an is_admin() OR is_bundle_owner()
   * branch, and the response shape would need to be conditional (the admin response's
   * order_url is a wp-admin URL meaningless to a non-admin caller).
   */
  public function confirm_bundle_renewal( \WP_REST_Request $request ): \WP_REST_Response {
    $bundle_post_id = (int) ( $request->get_param( 'bundle_post_id' ) ?? 0 );

    $validated = self::validate_bundle_and_subscription( $bundle_post_id );
    if ( is_wp_error( $validated ) ) {
      return new \WP_REST_Response( [ 'error' => $validated->get_error_message() ], (int) $validated->get_error_data()['status'] );
    }
    [ 'bundle' => $bundle, 'subscription' => $subscription ] = $validated;

    // Owner check — distinct from the admin capability check permissions_check_write()
    // uses. Only the bundle's own owner may confirm their own renewal.
    $current_user_id = get_current_user_id();
    if ( ! $current_user_id || $bundle->get_owner_id() !== $current_user_id ) {
      return new \WP_REST_Response( [ 'error' => 'You are not the owner of this membership bundle.' ], 403 );
    }

    $config = $bundle->get_config();
    if ( ! $config || ! $config->is_renewal_confirmation() ) {
      return new \WP_REST_Response( [ 'error' => 'This membership bundle is not configured for confirmation renewal.' ], 400 );
    }

    // Confirm window: same early_renew_at -> ends_at window Membership_Bundle::get_owner_callouts()
    // already uses to surface the early_renewal callout to this same bundle owner.
    $dates          = $bundle->get_dates();
    $early_renew_at = ! empty( $dates['early_renew_at'] ) ? strtotime( $dates['early_renew_at'] ) : null;
    $ends_at        = ! empty( $dates['ends_at'] ) ? strtotime( $dates['ends_at'] ) : null;
    $now            = current_time( 'timestamp' );

    if ( ! $early_renew_at || ! $ends_at || $now < $early_renew_at || $now >= $ends_at ) {
      return new \WP_REST_Response( [ 'error' => 'The renewal confirmation window is not currently open for this membership bundle.' ], 400 );
    }

    // Idempotency: reject a second confirm (double-click, duplicate/race request) if a
    // renewal order already exists for this cycle. A renewal order created before the
    // current window opened belongs to a prior cycle, not this one — only one created
    // since early_renew_at counts as "already renewed" for this cycle.
    foreach ( $subscription->get_related_orders( 'ids', 'renewal' ) as $existing_order_id ) {
      $existing_order = wc_get_order( $existing_order_id );
      if ( $existing_order && $existing_order->get_date_created() && $existing_order->get_date_created()->getTimestamp() >= $early_renew_at ) {
        return new \WP_REST_Response( [
          'error'    => 'This membership bundle has already been renewed for the current cycle.',
          'order_id' => $existing_order_id,
        ], 409 );
      }
    }

    $renewal_order = wcs_create_renewal_order( $subscription );
    if ( is_wp_error( $renewal_order ) ) {
      return new \WP_REST_Response( [ 'error' => $renewal_order->get_error_message() ], 500 );
    }

    // Member-appropriate response shape — no wp-admin order_url, unlike
    // create_bundle_renewal_order's response: a wp-admin URL is meaningless to a
    // non-admin caller. The created order flows through the existing
    // catch_order_completed() -> handle_bundle_renewal() pipeline unchanged.
    return new \WP_REST_Response( [
      'success'  => __( 'Renewal confirmed successfully.', 'wicket-memberships' ),
      'order_id' => $renewal_order->get_id(),
    ], 200 );
  }

  /**
   * Shared defensive validation for any endpoint that needs to act on a bundle's
   * linked WooCommerce subscription: bundle_post_id is valid, WCS is active, the
   * post resolves to a membership bundle, it has a linked subscription, and that
   * subscription loads. Purely defensive — no business/permission logic. Used by
   * both the admin create_renewal_order endpoint and the member-facing
   * confirm_renewal endpoint so the two share identical checks.
   *
   * @param int $bundle_post_id
   * @return array{bundle: Membership_Bundle, subscription: \WC_Subscription}|\WP_Error
   *         WP_Error's data carries ['status' => int] for the caller's REST response code.
   */
  private static function validate_bundle_and_subscription( int $bundle_post_id ) {
    if ( ! $bundle_post_id ) {
      return new \WP_Error( 'invalid_bundle_post_id', 'Invalid bundle_post_id.', [ 'status' => 400 ] );
    }

    if ( ! function_exists( 'wcs_get_subscription' ) || ! function_exists( 'wcs_create_renewal_order' ) ) {
      return new \WP_Error( 'wcs_unavailable', 'WooCommerce Subscriptions is not active.', [ 'status' => 500 ] );
    }

    if ( get_post_type( $bundle_post_id ) !== Helper::get_membership_bundle_cpt_slug() ) {
      return new \WP_Error( 'bundle_not_found', 'Membership bundle not found.', [ 'status' => 404 ] );
    }

    $bundle = new Membership_Bundle( $bundle_post_id );

    $subscription_id = $bundle->get_subscription_id();
    if ( ! $subscription_id ) {
      return new \WP_Error( 'no_subscription', 'This membership bundle has no linked WooCommerce subscription.', [ 'status' => 400 ] );
    }

    $subscription = wcs_get_subscription( $subscription_id );
    if ( ! $subscription ) {
      return new \WP_Error( 'subscription_not_found', 'The linked WooCommerce subscription could not be loaded.', [ 'status' => 400 ] );
    }

    return [ 'bundle' => $bundle, 'subscription' => $subscription ];
  }

  /**
   * POST /bundle/{bundle_post_id}/move_individual_membership
   */
  public function move_individual_membership( \WP_REST_Request $request ): \WP_REST_Response {
    $params = $request->get_params();

    $result = Membership_Bundle_Admin_Controller::move_individual_membership( [
      'source_bundle_post_id' => (int) ( $params['bundle_post_id'] ?? 0 ),
      'membership_post_id'   => (int) ( $params['membership_post_id'] ?? 0 ),
      'target_bundle_post_id' => (int) ( $params['target_bundle_post_id'] ?? 0 ),
    ] );

    if ( isset( $result['error'] ) ) {
      return new \WP_REST_Response( [ 'error' => $result['error'] ], 400 );
    }

    return new \WP_REST_Response( $result, 200 );
  }

  // ---------------------------------------------------------------------------
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

  /**
   * Check permissions for the member-facing confirm_renewal endpoint.
   *
   * Distinct from permissions_check_write: this is not gated on the admin
   * WICKET_MEMBERSHIPS_CAPABILITY. Any logged-in user may call this endpoint —
   * the handler itself rejects with 'not_bundle_owner' if the caller is not the
   * bundle's actual owner, since bundle_post_id is only known once the route
   * resolves and this callback runs before the handler.
   */
  public function permissions_check_confirm_renewal( $request ) {
    if ( ! is_user_logged_in() ) {
      return new WP_REST_Response( [ 'error' => 'Authentication required.' ], 401 );
    }
    return true;
  }
}
