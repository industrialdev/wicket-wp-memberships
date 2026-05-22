<?php

namespace Wicket_Memberships;

use \WP_REST_Response;

/**
 * REST routes and methods for Membership Bundle Config
 */
class Membership_Bundle_Config_WP_REST_Controller extends \WP_REST_Controller {

  public function __construct() {
    add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    $this->namespace = 'wicket_member/v1';
  }

  /**
   * Register routes
   */
  public function register_routes() {
    /**
     * Get membership dates calculated from a Membership Bundle Config
     */
    register_rest_route( $this->namespace, '/bundle_config/(?P<id>\d+)/membership_dates', array(
      array(
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => array( $this, 'get_bundle_config_membership_dates' ),
        'permission_callback' => array( $this, 'permissions_check_read' ),
        'args'                => array(
          'id' => array(
            'required'    => true,
            'type'        => 'integer',
            'description' => 'The post ID of the membership bundle config.',
          ),
        ),
      ),
    ) );
  }

  /**
   * Get calculated membership dates from a Membership Bundle Config.
   * Accepts an optional 'membership' param (array) for renewal date calculation on existing membership bundles.
   */
  public function get_bundle_config_membership_dates( \WP_REST_Request $request ) {
    $params  = $request->get_params();
    $post_id = intval( $params['id'] );
    $post    = get_post( $post_id );

    if ( ! $post || get_post_type( $post_id ) !== Helper::get_membership_bundle_config_cpt_slug() ) {
      return new WP_REST_Response( [ 'error' => 'Group config not found.' ], 404 );
    }

    $config     = new Membership_Bundle_Config( $post_id );
    $membership = ! empty( $params['membership'] ) ? $params['membership'] : [];
    $response   = $config->get_membership_dates( $membership );

    return rest_ensure_response( $response );
  }

  /**
   * Check permissions to read
   */
  public function permissions_check_read( $request ) {
    if ( ! empty( $_ENV['ALLOW_LOCAL_IMPORTS'] ) ) {
      return true;
    }
    if ( ! current_user_can( Wicket_Memberships::WICKET_MEMBERSHIPS_CAPABILITY ) ) {
      return new WP_REST_Response( array( 'error' => 'Authentication required.' ), 401 );
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
