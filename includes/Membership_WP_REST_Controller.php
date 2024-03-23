<?php

namespace Wicket_Memberships;

/**
 * Rest routes and methods
 */
class Membership_WP_REST_Controller extends \WP_REST_Controller {

  public function __construct() {
      $this->namespace     = 'wicket_member/v1';
      $this->resource_name = 'membership';
  }

  /**
   * Register routes
   */
  public function register_routes() {
    register_rest_route( $this->namespace, '/user/(?P<user_id>[\d]+)/' . $this->resource_name, array(
      array(
        'methods'   => 'GET',
        'callback'  => array( $this, 'get_user_memberships' ),
        'permission_callback' => array( $this, 'permissions_check_read' ),
      ),
      'schema' => array( $this, 'get_user_memberships_schema' ),
    ) );

    register_rest_route( $this->namespace, '/' . $this->resource_name . '/' . '(?P<id>[\d]+)', array(
      array(
        'methods'   => 'GET',
        'callback'  => array( $this, 'get_membership' ),
        'permission_callback' => array( $this, 'permissions_check_read' ),
      ),
      'schema' => array( $this, 'get_membership_schema' ),
    ) );
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

  /**
   * Get membership by ID
   */
  public function get_membership( $request ) {
    $id = (int) $request['id'];
    $post = get_post( $id );
    if ( empty( $post ) ) {
      return rest_ensure_response( array() );
    }
    $response = $this->prepare_membership_for_response( $post, $request );
    return rest_ensure_response( $response );
  }

  /**
   * Get a users memberships by UserID
   */
  public function get_user_memberships( $request ) {
    $offset = 0;
    $page = (int) $request['page'];
    $per_page = (int) $request['per_page'];
    if($page > 1) {
      $offset = (($page - 1) * $per_page);
    }
    $user_id = (int) $request['user_id'];

    $args = array(
      'post_type' => 'wicket_member',
      'post_status' => 'publish',
      'posts_per_page' => $per_page,
      'offset' => $offset,
      'meta_query'     => array(
        array(
          'key'     => 'user_id',
          'value'   => $user_id,
          'compare' => '='
        )
      )
    );

    $posts = new \WP_Query( $args );
    $data = array();

    if ( empty( $posts ) ) {
        return rest_ensure_response( $data );
    }

    foreach ( $posts->posts as $post ) {
        $response = $this->prepare_membership_for_response( $post, $request );
        $data[] = $this->prepare_response_for_collection( $response );
    }
    $response = rest_ensure_response( $data );
    $response = $this->get_pagination_headers( $posts, $response );
    return $response;
  }

  /**
   * Get the pagination headers
   */
  public function get_pagination_headers( $query, $response ) {
    $total = $query->found_posts;
    $pages = $query->max_num_pages;
    $response->header( 'X-WP-Total', $total );
    $response->header( 'X-WP-TotalPages', $pages );
    return $response;
  }

  /**
   * Apply the schema
   */
  public function prepare_membership_for_response( $post, $request ) {
    $post_data = array();
    $schema = $this->get_membership_schema( $request );

    $post_data['id'] = $post->ID;
    foreach($schema['properties'] as $key => $property) {
      if( !empty( $post->{$key} ) ) {
        $post_data[$key] = $post->{$key};
        settype($post->{$key}, $property['type']);
      } else if( $key == 'wicket_uuid') {
        $post_data['wicket_uuid'] = 'no-sync';
      }
    }
    return $post_data;
  }

  /**
   * Get our user memberships collection schema
   */
  public function get_user_memberships_schema( $request ) {
    if ( $this->schema ) {
      return $this->schema;
    }


    $this->schema = array(
      '$schema'              => 'http://json-schema.org/draft-04/schema#',
      'title'                => 'post',
      'type'                 => 'object',
      'properties'           => array(
        array (
          'id' => array(
              'type'         => 'integer',
              'context'      => array( 'view', 'edit', 'embed' ),
              'readonly'     => true,
          ),
          'user_id' => array(
              'type'         => 'int',
              'context'      => array( 'view', 'edit', 'embed' ),
              'readonly'     => true,
          ),
          'wicket_uuid' => array(
              'type'         => 'string',
              'context'      => array( 'view', 'edit', 'embed' ),
              'readonly'     => true,
          ),
          'status' => array(
              'type'         => 'string',
              'context'      => array( 'view', 'edit', 'embed' ),
              'readonly'     => true,
          ),
          'start_date' => array(
              'type'         => 'string',
              'context'      => array( 'view', 'edit', 'embed' ),
              'readonly'     => true,
          ),
          'end_date' => array(
              'type'         => 'string',
              'context'      => array( 'view', 'edit', 'embed' ),
              'readonly'     => false,
          ),
          'expiry_date' => array(
              'type'         => 'string',
              'context'      => array( 'view', 'edit', 'embed' ),
              'readonly'     => false,
          ),
          'member_type' => array(
              'type'         => 'string',
              'context'      => array( 'view', 'edit', 'embed' ),
              'readonly'     => false,
          ),
          'membership_uuid' => array(
            'type'         => 'string',
            'context'      => array( 'view', 'edit', 'embed' ),
            'readonly'     => false,
          ),
        ),
      ),
    );
    return $this->schema;
  }

  /**
   * Get our membership item schema
   */
  public function get_membership_schema( $request ) {
    if ( $this->schema ) {
      return $this->schema;
    }

    $this->schema = array(
      '$schema'              => 'http://json-schema.org/draft-04/schema#',
      'title'                => 'post',
      'type'                 => 'object',
      'properties'           => array(
        'id' => array(
            'type'         => 'integer',
            'context'      => array( 'view', 'edit', 'embed' ),
            'readonly'     => true,
        ),
        'user_id' => array(
            'type'         => 'int',
            'context'      => array( 'view', 'edit', 'embed' ),
            'readonly'     => true,
        ),
        'wicket_uuid' => array(
            'type'         => 'string',
            'context'      => array( 'view', 'edit', 'embed' ),
            'readonly'     => true,
        ),
        'status' => array(
            'type'         => 'string',
            'context'      => array( 'view', 'edit', 'embed' ),
            'readonly'     => true,
        ),
        'start_date' => array(
            'type'         => 'string',
            'context'      => array( 'view', 'edit', 'embed' ),
            'readonly'     => true,
        ),
        'end_date' => array(
            'type'         => 'string',
            'context'      => array( 'view', 'edit', 'embed' ),
            'readonly'     => false,
        ),
        'expiry_date' => array(
            'type'         => 'string',
            'context'      => array( 'view', 'edit', 'embed' ),
            'readonly'     => false,
        ),
        'member_type' => array(
            'type'         => 'string',
            'context'      => array( 'view', 'edit', 'embed' ),
            'readonly'     => false,
        ),
        'membership_uuid' => array(
          'type'         => 'string',
          'context'      => array( 'view', 'edit', 'embed' ),
          'readonly'     => false,
        ),
      ),
    );
    return $this->schema;
  }

  public function authorization_status_code() {
    $status = 401;
    if ( is_user_logged_in() ) {
      $status = 403;
    }
    return $status;
  }
}

function wicket_member_register_my_rest_routes() {
  $controller = new WP_REST_Wicket_Member_Controller();
  $controller->register_routes();
}

add_action( 'rest_api_init', __NAMESPACE__ . '\wicket_member_register_my_rest_routes' );