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
     * List/search/filter membership bundles owned by the current logged-in member.
     *
     * GET /wicket_member/v1/membership_bundles/mine
     *
     * Member-scoped counterpart to /membership_bundles: restricted to bundles the
     * requesting user owns (via the bundle's user_id meta), gated by
     * permissions_check_member_read instead of the staff-only capability check.
     */
    register_rest_route( $this->namespace, '/membership_bundles/mine', [
      [
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => [ $this, 'get_my_membership_bundles' ],
        'permission_callback' => [ $this, 'permissions_check_member_read' ],
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
          'order_col' => [
            'type'        => 'string',
            'description' => 'Order by column name.',
          ],
          'order_dir' => [
            'type'        => 'string',
            'description' => 'Order by direction.',
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
     * Get a membership bundle record by its post ID (member-scoped).
     *
     * GET /wicket_member/v1/membership_bundle_entity/mine?bundle_post_id=123
     *
     * Member-scoped counterpart to /membership_bundle_entity: restricted to
     * bundles linked to an MDP organisation the requesting member belongs to
     * (see permissions_check_bundle_org_member), rather than staff members
     * holding the plugin's admin capability. Reuses get_bundle_entity() as-is
     * since the response shape is identical — only the permission layer differs.
     */
    register_rest_route( $this->namespace, '/membership_bundle_entity/mine', [
      [
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => [ $this, 'get_bundle_entity' ],
        'permission_callback' => [ $this, 'permissions_check_bundle_org_member' ],
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
     * Return total member count and per-tier breakdown for a bundle (member-scoped).
     *
     * GET /wicket_member/v1/bundle/{id}/members_by_tier/mine
     *
     * Member-scoped counterpart to /bundle/{id}/members_by_tier, gated by
     * permissions_check_bundle_org_member instead of the staff-only capability
     * check. Reuses get_bundle_members_by_tier() as-is — only the permission
     * layer differs.
     */
    register_rest_route( $this->namespace, '/bundle/(?P<bundle_post_id>\d+)/members_by_tier/mine', [
      [
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => [ $this, 'get_bundle_members_by_tier' ],
        'permission_callback' => [ $this, 'permissions_check_bundle_org_member' ],
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
     * List individual member seats within a bundle, for the bundle-detail
     * members table (member-scoped).
     *
     * GET /wicket_member/v1/bundle/{bundle_post_id}/members/mine
     *
     * Gated by permissions_check_bundle_org_member. Unlike the other /mine
     * routes, this does NOT reuse an existing staff handler directly:
     * Membership_Controller::get_members_list() (which backs the staff
     * /memberships route) accepts a client-supplied `filter[membership_bundle_id]`
     * with no ownership check, and its row shape includes staff-only fields
     * (mdp_link) and cross-bundle data (all_membership_tiers,
     * all_membership_bundles) that must not reach a member. get_bundle_members()
     * forces membership_bundle_id server-side from the already-authorized
     * bundle_post_id and returns a minimal, explicit row shape instead of
     * passing the staff response through — see that method for details.
     */
    register_rest_route( $this->namespace, '/bundle/(?P<bundle_post_id>\d+)/members/mine', [
      [
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => [ $this, 'get_bundle_members' ],
        'permission_callback' => [ $this, 'permissions_check_bundle_org_member' ],
        'args'                => [
          'bundle_post_id' => [
            'required'    => true,
            'type'        => 'integer',
            'description' => 'The WP post ID of the membership bundle.',
          ],
          'page' => [
            'type'        => 'integer',
            'description' => 'Paginated results page.',
          ],
          'posts_per_page' => [
            'type'        => 'integer',
            'description' => 'Paginated results per page.',
          ],
          'tier_uuid' => [
            'type'        => 'string',
            'description' => 'Restrict results to one tier (matches membership_tier_uuid).',
          ],
          'order_col' => [
            'type'        => 'string',
            'description' => 'Order by column name.',
          ],
          'order_dir' => [
            'type'        => 'string',
            'description' => 'Order by direction.',
          ],
        ],
      ],
    ] );

    /**
     * Search MDP people by name or email for the add-member flow (member-scoped).
     *
     * POST /wicket_member/v1/bundle/{bundle_post_id}/search_eligible_members
     * Body: { term }
     *
     * Member-scoped counterpart to the staff-only /mdp_person/search route
     * (Membership_WP_REST_Controller::mdp_person_lookup): gated by
     * permissions_check_bundle_org_member, so only members of the bundle's
     * owning org can search. Returns plain MDP person matches only — no
     * eligibility computation. Tier eligibility is still enforced by
     * add_member at submit time (see Membership_Bundle_Config::is_tier_eligible_for_bundle()).
     */
    register_rest_route( $this->namespace, '/bundle/(?P<bundle_post_id>\d+)/search_eligible_members', [
      [
        'methods'             => \WP_REST_Server::CREATABLE,
        'callback'            => [ $this, 'search_eligible_members' ],
        'permission_callback' => [ $this, 'permissions_check_bundle_org_member' ],
        'args'                => [
          'bundle_post_id' => [
            'required'    => true,
            'type'        => 'integer',
            'description' => 'Post ID of the membership bundle to search within.',
          ],
          'term' => [
            'required'    => true,
            'type'        => 'string',
            'description' => 'Free-text search term matched against MDP person full name or email.',
          ],
        ],
      ],
    ] );

    /**
     * List individual tiers eligible for a bundle's add-member flow, with
     * resolved WooCommerce product/variation options (member-scoped).
     *
     * GET /wicket_member/v1/bundle/{bundle_post_id}/eligible_tiers/mine
     *
     * Member-scoped: the wicket_mship_tier CPT is registered with
     * `public => false`, so a member cannot read it via the native
     * /wp/v2/{slug} REST route, and the staff-only /membership_products
     * route can't be reused either. Filters to the bundle config's
     * eligible_tier_ids (empty means all active individual tiers, per
     * Membership_Bundle_Config::get_eligible_tier_ids()'s own fallback rule)
     * and resolves each product/variation's name and price server-side.
     *
     * When person_uuid is supplied, each tier is additionally annotated with
     * eligibility_status ('eligible' | 'in_bundle' | 'not_eligible') plus
     * membership_status/starts_at/ends_at for that person's matching
     * membership record — see
     * Membership_Bundle_Admin_Controller::get_eligible_tiers_for_bundle().
     */
    register_rest_route( $this->namespace, '/bundle/(?P<bundle_post_id>\d+)/eligible_tiers/mine', [
      [
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => [ $this, 'get_bundle_eligible_tiers' ],
        'permission_callback' => [ $this, 'permissions_check_bundle_org_member' ],
        'args'                => [
          'bundle_post_id' => [
            'required'    => true,
            'type'        => 'integer',
            'description' => 'Post ID of the membership bundle.',
          ],
          'person_uuid' => [
            'required'    => false,
            'type'        => 'string',
            'description' => 'MDP person UUID to compute per-tier eligibility/status/dates for. Omit for the plain tier list with no eligibility annotation.',
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
     * Add an individual membership to a bundle (member-scoped).
     *
     * POST /wicket_member/v1/bundle/{bundle_post_id}/add_member/mine
     * Body: { mode, tier_post_id, person_uuid|existing_membership_post_id, product_id? }
     *
     * Member-scoped counterpart to /bundle/{bundle_post_id}/add_member: gated
     * by permissions_check_bundle_org_member instead of the staff-only
     * capability check. Reuses add_member_to_bundle() and
     * Membership_Bundle_Admin_Controller::add_member() as-is — no new
     * business logic, only the permission layer differs.
     */
    register_rest_route( $this->namespace, '/bundle/(?P<bundle_post_id>\d+)/add_member/mine', [
      [
        'methods'             => \WP_REST_Server::CREATABLE,
        'callback'            => [ $this, 'add_member_to_bundle' ],
        'permission_callback' => [ $this, 'permissions_check_bundle_org_member' ],
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
     * Remove an individual membership from a bundle (member-scoped).
     *
     * POST /wicket_member/v1/bundle/{bundle_post_id}/remove_member/mine
     * Body: { membership_post_id, mode }
     *
     * Member-scoped counterpart to /bundle/{bundle_post_id}/remove_member:
     * gated by permissions_check_bundle_org_member instead of the staff-only
     * capability check. Reuses remove_member_from_bundle() and
     * Membership_Bundle_Admin_Controller::remove_member() as-is — no new
     * business logic, only the permission layer differs.
     */
    register_rest_route( $this->namespace, '/bundle/(?P<bundle_post_id>\d+)/remove_member/mine', [
      [
        'methods'             => \WP_REST_Server::CREATABLE,
        'callback'            => [ $this, 'remove_member_from_bundle' ],
        'permission_callback' => [ $this, 'permissions_check_bundle_org_member' ],
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
   * GET /membership_bundles/mine
   *
   * Owner-only: returns bundles where get_current_user_id() matches the
   * bundle's user_id meta. Does not include bundles where the member merely
   * holds an individual seat as a non-owner.
   */
  public function get_my_membership_bundles( \WP_REST_Request $request ) {
    $params = $request->get_params();
    $response = Membership_Bundle_Admin_Controller::get_membership_bundles_list(
      $params['page'] ?? 1,
      $params['posts_per_page'] ?? 25,
      $params['status'] ?? 'all',
      '',
      [],
      $params['order_col'] ?? 'post_modified',
      $params['order_dir'] ?? 'desc',
      get_current_user_id()
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
   * GET /bundle/{bundle_post_id}/members/mine
   *
   * Queries the wicket_membership CPT directly rather than delegating to
   * Membership_Controller::get_members_list() — that method was written for
   * rosters where one row per person is the goal (it deduplicates its result
   * set by user_id, folding a person's other memberships into a nested
   * all_membership_tiers array instead of separate rows; see its own comment
   * at "deduplicate per user/org in PHP"). A bundle can legitimately hold
   * more than one concurrent individual membership for the same person (one
   * per tier — see the add-member modal's multi-tier selection), and that
   * dedup silently drops every row past the first one for that person, so a
   * member added with two tiers would only ever show one of them here. This
   * bundle-scoped table needs one row per membership record, not per person.
   *
   * Also avoids delegating to Membership_WP_REST_Controller's /memberships
   * handler for the reason already noted below: that route trusts the
   * caller's own `filter` input for membership_bundle_id, which would let an
   * authorized-for-this-bundle member also request another bundle's members
   * by changing the filter. bundle_post_id is instead read from the
   * already-validated route param and used as the ONLY source of the
   * membership_bundle_id filter.
   */
  public function get_bundle_members( \WP_REST_Request $request ) {
    $params         = $request->get_params();
    $bundle_post_id = (int) $params['bundle_post_id'];
    $page           = max( 1, (int) ( $params['page'] ?? 1 ) );
    $posts_per_page = max( 1, (int) ( $params['posts_per_page'] ?? 25 ) );
    $order_col      = sanitize_text_field( $params['order_col'] ?? '' );
    $order_dir      = sanitize_text_field( $params['order_dir'] ?? '' );

    $meta_query = [
      [ 'key' => 'membership_type', 'value' => 'individual', 'compare' => '=' ],
      [ 'key' => 'membership_bundle_id', 'value' => $bundle_post_id, 'compare' => '=' ],
      // Mirrors Membership_Bundle::get_individual_memberships()'s $active_only=true
      // default (used by the tier summary endpoint) — without this, a membership
      // removed via remove_member/mine (mode "cancel", or the old bundle seat left
      // behind by "keep_as_individual") keeps its membership_bundle_id meta and
      // would otherwise still match this query and show up as a live row here.
      [ 'key' => 'membership_status', 'value' => [ 'cancelled', 'expired' ], 'compare' => 'NOT IN' ],
    ];

    if ( ! empty( $params['tier_uuid'] ) ) {
      $meta_query[] = [
        'key'     => 'membership_tier_uuid',
        'value'   => sanitize_text_field( $params['tier_uuid'] ),
        'compare' => '=',
      ];
    }

    $query = new \WP_Query( [
      'post_type'      => Helper::get_membership_cpt_slug(),
      'post_status'    => 'publish',
      'posts_per_page' => -1,
      'meta_query'     => $meta_query,
    ] );

    // Reshape into an explicit, minimal row rather than a raw, unaudited
    // post-meta dump — a staff-only MDP admin link and cross-bundle/cross-org
    // membership data have no place in a member-facing response.
    $rows = array_map( [ $this, 'shape_member_row_for_member' ], $query->posts );
    $rows = $this->sort_bundle_member_rows( $rows, $order_col, $order_dir );

    $total     = count( $rows );
    $page_rows = array_slice( $rows, ( $page - 1 ) * $posts_per_page, $posts_per_page );

    return rest_ensure_response( [
      'results'        => $page_rows,
      'page'           => $page,
      'posts_per_page' => $posts_per_page,
      'count'          => $total,
    ] );
  }

  /**
   * Reduce a wicket_membership post down to the fields safe to expose to a
   * member viewing their own org's bundle.
   */
  private function shape_member_row_for_member( \WP_Post $post ): array {
    $meta        = get_post_meta( $post->ID );
    $user_id     = (int) ( $meta['user_id'][0] ?? 0 );
    $user        = $user_id > 0 ? get_userdata( $user_id ) : false;
    $status_slug = $meta['membership_status'][0] ?? '';
    $statuses    = Helper::get_all_status_names();

    return [
      'ID'                     => $post->ID,
      'first_name'             => $user ? $user->first_name : '',
      'last_name'              => $user ? $user->last_name : '',
      'email'                  => $user ? $user->user_email : '',
      'membership_status'      => $statuses[ $status_slug ]['name'] ?? $status_slug,
      'membership_status_slug' => $status_slug,
      'membership_starts_at'   => $meta['membership_starts_at'][0] ?? '',
      'membership_ends_at'     => $meta['membership_ends_at'][0] ?? '',
      'membership_expires_at'  => $meta['membership_expires_at'][0] ?? '',
      'tier_uuid'              => $meta['membership_tier_uuid'][0] ?? '',
    ];
  }

  /**
   * Sort shape_member_row_for_member() rows in PHP (there's no SQL query left
   * to attach an ORDER BY to once rows have been fetched and reshaped).
   * Mirrors the order_col values detail.php's sortMembersBy() already sends
   * (see that template's orderColMap): 'user_name' (first name — the closest
   * available equivalent now that there's no concatenated user_name meta to
   * sort by), 'user_last_name', 'membership_tier_uuid', and 'start_date'.
   * Falls back to newest-start-date-first, roughly matching
   * get_members_list()'s own 'modified' DESC default.
   */
  private function sort_bundle_member_rows( array $rows, string $order_col, string $order_dir ): array {
    $sort_key_map = [
      'user_name'            => 'first_name',
      'user_last_name'       => 'last_name',
      'membership_tier_uuid' => 'tier_uuid',
      'start_date'           => 'membership_starts_at',
    ];

    $sort_key = $sort_key_map[ $order_col ] ?? 'membership_starts_at';
    $dir      = strtolower( $order_dir ) === 'desc' ? 'desc' : ( isset( $sort_key_map[ $order_col ] ) ? 'asc' : 'desc' );

    usort( $rows, function ( $a, $b ) use ( $sort_key ) {
      return strnatcasecmp( (string) $a[ $sort_key ], (string) $b[ $sort_key ] );
    } );

    if ( $dir === 'desc' ) {
      $rows = array_reverse( $rows );
    }

    return $rows;
  }

  /**
   * POST /bundle/{bundle_post_id}/search_eligible_members
   *
   * bundle_post_id is only used by the permission_callback (to resolve the
   * bundle's org for the org-membership check) — the search itself is a
   * plain MDP person lookup, not scoped to the bundle's existing members.
   */
  public function search_eligible_members( \WP_REST_Request $request ) {
    $term = sanitize_text_field( (string) $request->get_param( 'term' ) );

    if ( '' === $term ) {
      return new WP_REST_Response( [ 'error' => 'term is required.' ], 400 );
    }

    $response = wicket_search_person( $term );

    if ( false === $response ) {
      return new WP_REST_Response( [ 'error' => 'Person search failed.' ], 500 );
    }

    return rest_ensure_response( $response );
  }

  /**
   * GET /bundle/{bundle_post_id}/eligible_tiers/mine
   */
  public function get_bundle_eligible_tiers( \WP_REST_Request $request ) {
    $bundle_post_id = (int) $request->get_param( 'bundle_post_id' );
    $person_uuid    = sanitize_text_field( (string) $request->get_param( 'person_uuid' ) );
    $response = Membership_Bundle_Admin_Controller::get_eligible_tiers_for_bundle( $bundle_post_id, $person_uuid );
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
      $error_response = [ 'error' => $result['error'], 'code' => $result['code'] ?? '' ];

      if ( isset( $result['eligible_tier_names'] ) ) {
        $error_response['eligible_tier_names'] = $result['eligible_tier_names'];
      }

      return new WP_REST_Response( $error_response, 400 );
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

    if ( ! $bundle_post_id ) {
      return new \WP_REST_Response( [ 'error' => 'Invalid bundle_post_id.' ], 400 );
    }

    if ( ! function_exists( 'wcs_get_subscription' ) || ! function_exists( 'wcs_create_renewal_order' ) ) {
      return new \WP_REST_Response( [ 'error' => 'WooCommerce Subscriptions is not active.' ], 500 );
    }

    if ( get_post_type( $bundle_post_id ) !== Helper::get_membership_bundle_cpt_slug() ) {
      return new \WP_REST_Response( [ 'error' => 'Membership bundle not found.' ], 404 );
    }

    $bundle = new Membership_Bundle( $bundle_post_id );

    $subscription_id = $bundle->get_subscription_id();
    if ( ! $subscription_id ) {
      return new \WP_REST_Response( [ 'error' => 'This membership bundle has no linked WooCommerce subscription.' ], 400 );
    }

    $subscription = wcs_get_subscription( $subscription_id );
    if ( ! $subscription ) {
      return new \WP_REST_Response( [ 'error' => 'The linked WooCommerce subscription could not be loaded.' ], 400 );
    }

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
   * Check permissions for member-scoped read routes.
   *
   * Unlike permissions_check_read (staff-only, gated by
   * WICKET_MEMBERSHIPS_CAPABILITY), this only requires the requester to be a
   * logged-in WP user — member-scoped routes filter results to that user's own
   * data (see get_membership_bundles_list()'s $owner_user_id param), so no
   * elevated capability is needed.
   *
   * Deliberately does not honor ALLOW_LOCAL_IMPORTS: that flag exists for CSV
   * import automation, not member-facing browsing, so it should not bypass
   * authentication here.
   */
  public function permissions_check_member_read( $request ) {
    if ( ! is_user_logged_in() ) {
      return new WP_REST_Response( [ 'error' => 'Authentication required.' ], 401 );
    }
    return true;
  }

  /**
   * Check permissions for member-scoped routes restricted to bundles the
   * requesting member's MDP organisation owns.
   *
   * Unlike permissions_check_member_read (owner-only, matched against the WP
   * user_id meta), this allows any member belonging to the bundle's linked
   * org_uuid to read — e.g. an org delegate who did not personally purchase
   * the bundle but administers it on the org's behalf. Ownership is resolved
   * via MDP (the source of truth for org membership), not cached locally, so
   * this always reflects the member's current connections.
   *
   * Deliberately does not honor ALLOW_LOCAL_IMPORTS: that flag exists for CSV
   * import automation, not member-facing browsing.
   */
  public function permissions_check_bundle_org_member( \WP_REST_Request $request ) {
    if ( ! is_user_logged_in() ) {
      return new WP_REST_Response( [ 'error' => 'Authentication required.' ], 401 );
    }

    $bundle_post_id = (int) $request->get_param( 'bundle_post_id' );
    $bundle = new Membership_Bundle( $bundle_post_id );

    // Reject unknown/wrong-CPT IDs here rather than letting the handler's own
    // 404 fire after we've already treated the request as authorized.
    if ( ! $bundle->post_id ) {
      return new WP_REST_Response( [ 'error' => 'Membership bundle not found.' ], 404 );
    }

    $org_uuid = $bundle->get_org_uuid();
    if ( ! $org_uuid ) {
      // A bundle with no linked org has no member to authorize against.
      return new WP_REST_Response( [ 'error' => 'You do not have access to this membership bundle.' ], 403 );
    }

    $person_uuid = wicket_current_person_uuid();
    if ( empty( $person_uuid ) ) {
      return new WP_REST_Response( [ 'error' => 'Unable to resolve current member.' ], 403 );
    }

    // MDP is the source of truth for org membership — query live rather than
    // trusting any locally cached role/relationship data.
    $connections = wicket_get_active_person_org_connections( $person_uuid, $org_uuid );

    if ( is_wp_error( $connections ) || empty( $connections ) ) {
      return new WP_REST_Response( [ 'error' => 'You do not have access to this membership bundle.' ], 403 );
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
