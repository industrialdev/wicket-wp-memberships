<?php

namespace Wicket_Memberships;

use Wicket_Memberships\Helper;
use Wicket_Memberships\Utilities;

/**
 * Admin operations for Membership Group posts.
 *
 * Mirrors the shape of Admin_Controller but operates exclusively on the
 * wicket_mship_group CPT.  Individual-membership concerns (MDP record sync,
 * tier/config lookups, user-meta JSON blobs) are intentionally absent here
 * because groups do not have their own MDP membership record — they are
 * containers that hold individual membership records.
 *
 * @package Wicket_Memberships
 */
class Group_Admin_Controller {

  private const MOCK_ORDER_PAYLOAD = [
    'id'            => 999999,
    'link'          => 'https://example.test/wp-admin/post.php?post=999999&action=edit',
    'total'         => '999.99',
    'status'        => 'mock-pending',
    'date_created'  => '2099-12-31T00:00:00+00:00',
    'date_completed'=> '2099-12-31T00:00:00+00:00',
  ];

  private const MOCK_ORDERS_PAYLOAD = [
    [
      'id'            => 999999,
      'link'          => 'https://example.test/wp-admin/post.php?post=999999&action=edit',
      'total'         => '999.99',
      'status'        => 'mock-pending',
      'date_created'  => '2099-12-31T00:00:00+00:00',
      'date_completed'=> '2099-12-31T00:00:00+00:00',
    ],
    [
      'id'            => 999999,
      'link'          => 'https://example.test/wp-admin/post.php?post=999999&action=edit',
      'total'         => '999.99',
      'status'        => 'mock-pending',
      'date_created'  => '2099-12-31T00:00:00+00:00',
      'date_completed'=> '2099-12-31T00:00:00+00:00',
    ],
    [
      'id'            => 999999,
      'link'          => 'https://example.test/wp-admin/post.php?post=999999&action=edit',
      'total'         => '999.99',
      'status'        => 'mock-pending',
      'date_created'  => '2099-12-31T00:00:00+00:00',
      'date_completed'=> '2099-12-31T00:00:00+00:00',
    ],
  ];

  private const MOCK_SUBSCRIPTION_PAYLOAD = [
    'id'                => 999999,
    'link'              => 'https://example.test/wp-admin/post.php?post=999999&action=edit',
    'status'            => 'mock-active',
    'next_payment_date' => '2099-12-31T00:00:00+00:00',
    'is_mocked'         => true,
  ];

  private string $group_cpt_slug;

  public function __construct() {
    $this->group_cpt_slug = Helper::get_membership_group_cpt_slug();
  }

  // ---------------------------------------------------------------------------
  // Membership group list
  // ---------------------------------------------------------------------------

  /**
   * Return grouped membership-group rows for the admin list UI.
   *
   * @param int|string      $page
   * @param int|string      $posts_per_page
   * @param string          $status
   * @param string          $search
   * @param array|string    $filter
   * @param string|null     $order_col
   * @param string|null     $order_dir
   * @return array
   */
  public static function get_membership_groups_list(
    $page = 1,
    $posts_per_page = 25,
    string $status = 'all',
    string $search = '',
    $filter = [],
    ?string $order_col = null,
    ?string $order_dir = null
  ): array {
    $page = max( 1, (int) $page );
    $posts_per_page = max( 1, (int) $posts_per_page );
    $status = sanitize_text_field( $status );
    $search = sanitize_text_field( $search );
    $order_col = ! empty( $order_col ) ? sanitize_key( $order_col ) : 'post_modified';
    $sort_dir = ( ! empty( $order_dir ) && strtoupper( $order_dir ) === 'ASC' ) ? 'ASC' : 'DESC';
    $filter = is_array( $filter ) ? $filter : [];

    $query_args = [
      'post_type'      => Helper::get_membership_group_cpt_slug(),
      'post_status'    => 'publish',
      'posts_per_page' => -1,
      'orderby'        => 'modified',
      'order'          => 'DESC',
      'meta_query'     => [],
    ];

    if ( $status !== '' && $status !== 'all' ) {
      $query_args['meta_query'][] = [
        'key'     => 'membership_status',
        'value'   => $status,
        'compare' => '=',
      ];
    }

    $supported_filters = [
      'org_uuid'          => 'org_uuid',
      'membership_status' => 'membership_status',
    ];
    foreach ( $filter as $key => $value ) {
      if ( ! isset( $supported_filters[ $key ] ) || $value === '' ) {
        continue;
      }

      $query_args['meta_query'][] = [
        'key'     => $supported_filters[ $key ],
        'value'   => sanitize_text_field( $value ),
        'compare' => '=',
      ];
    }

    if ( count( $query_args['meta_query'] ) === 1 && isset( $query_args['meta_query'][0]['relation'] ) ) {
      $query_args['meta_query'] = $query_args['meta_query'][0];
    } elseif ( count( $query_args['meta_query'] ) > 1 ) {
      $query_args['meta_query']['relation'] = 'AND';
    }

    if ( empty( $query_args['meta_query'] ) ) {
      unset( $query_args['meta_query'] );
    }

    $all_posts = get_posts( $query_args );
    $rows = array_map( [ self::class, 'build_membership_groups_row' ], $all_posts );
    if ( $search !== '' ) {
      $rows = array_values( array_filter( $rows, function ( array $row ) use ( $search ): bool {
        $haystacks = [
          $row['group_name'] ?? '',
          $row['org_name'] ?? '',
          $row['owner']['name'] ?? '',
          $row['owner']['email'] ?? '',
          $row['status']['label'] ?? '',
        ];

        foreach ( $haystacks as $haystack ) {
          if ( $haystack !== '' && stripos( (string) $haystack, $search ) !== false ) {
            return true;
          }
        }

        return false;
      } ) );
    }

    usort( $rows, function ( array $left, array $right ) use ( $order_col, $sort_dir ) {
      return self::compare_membership_group_rows( $left, $right, $order_col, $sort_dir );
    } );

    $count = count( $rows );
    $results = array_slice( $rows, ( $page - 1 ) * $posts_per_page, $posts_per_page );

    return [
      'results'        => array_values( $results ),
      'page'           => $page,
      'posts_per_page' => $posts_per_page,
      'count'          => $count,
    ];
  }

  // ---------------------------------------------------------------------------
  // Membership group filters
  // ---------------------------------------------------------------------------

  /**
   * Return available filter options for the membership group list UI.
   *
   * Returns the set of membership_status values that exist on published
   * group posts, formatted as { name, value } pairs to match the shape
   * used by the individual-membership filters endpoint.
   *
   * @return array
   */
  public static function get_membership_group_filters(): array {
    $statuses = Helper::get_all_status_names();

    $existing_slugs = get_posts( [
      'post_type'      => Helper::get_membership_group_cpt_slug(),
      'post_status'    => 'publish',
      'posts_per_page' => -1,
      'fields'         => 'ids',
    ] );

    $used_slugs = [];
    foreach ( $existing_slugs as $post_id ) {
      $slug = get_post_meta( $post_id, 'membership_status', true );
      if ( $slug !== '' && ! in_array( $slug, $used_slugs, true ) ) {
        $used_slugs[] = $slug;
      }
    }

    $membership_status = [];
    foreach ( $statuses as $slug => $info ) {
      if ( in_array( $slug, $used_slugs, true ) ) {
        $membership_status[] = [
          'name'  => $slug,
          'value' => $info['name'],
        ];
      }
    }

    return [
      'membership_status' => $membership_status,
    ];
  }

  // ---------------------------------------------------------------------------
  // Status management
  // ---------------------------------------------------------------------------

  /**
   * Return available status options for a membership group.
   *
   * If $group_post_id is supplied, returns only the valid transitions from the
   * group's current status.  Otherwise returns all status names.
   *
   * @param int|null $group_post_id
   * @return array
   */
  public static function get_admin_status_options( ?int $group_post_id = null ): array {
    if ( ! empty( $group_post_id ) ) {
      $group = new Membership_Group( $group_post_id );
      return $group->get_allowed_status_transitions();
    }
    return Helper::get_all_status_names();
  }

  /**
   * Transition a membership group to a new status.
   *
   * Supported transitions:
   *   pending  → active     Activates the group and its WC subscription.
   *   pending  → cancelled  Cancels immediately; sets end/expires to now.
   *   delayed  → cancelled  Same as pending → cancelled.
   *   active   → cancelled  Sets end/expires to tomorrow; cancels subscription.
   *   active   → expired    Sets end/expires to tomorrow; cancels subscription.
   *   grace-period → cancelled  Preserves existing end date; expires now.
   *
   * The method also cascades the new status to all non-expired/non-cancelled
   * individual memberships that belong to the group.
   *
   * @param int    $group_post_id
   * @param string $new_status
   * @return \WP_REST_Response
   */
  public static function admin_manage_status( int $group_post_id, string $new_status ): \WP_REST_Response {
    if ( empty( $new_status ) ) {
      return new \WP_REST_Response( [ 'error' => 'Invalid status transition. Requested status was not received.' ], 400 );
    }

    $post = get_post( $group_post_id );
    if ( ! $post || get_post_type( $group_post_id ) !== Helper::get_membership_group_cpt_slug() ) {
      return new \WP_REST_Response( [ 'error' => 'Membership group not found.' ], 404 );
    }

    $group          = new Membership_Group( $group_post_id );
    $response_array = [];
    $transition_result = $group->transition_to( $new_status );

    if ( false === $transition_result ) {
      $response_array['error'] = 'Invalid status transition. Request did not succeed.';
      Utilities::wc_log_mship_error( $response_array );
      return new \WP_REST_Response( $response_array, 400 );
    }

    $response_array['success']  = $transition_result['success_message'];
    $response_array['response'] = Helper::get_post_meta( $group_post_id );
    return new \WP_REST_Response( $response_array, 200 );
  }

  // ---------------------------------------------------------------------------
  // Entity record retrieval
  // ---------------------------------------------------------------------------

  /**
   * Return the data needed to populate the membership group entity view.
   *
   * Returns group-level post meta only.
   * Subscription + order detail enrichment is tracked as a TODO.
   *
   * @param int $group_post_id
   * @return array|\WP_REST_Response
   * @todo Enrich response with WC subscription and order data — see TODO.md
   */
  public static function get_group_entity_records( int $group_post_id ) {
    $post = get_post( $group_post_id );
    if ( ! $post || get_post_type( $group_post_id ) !== Helper::get_membership_group_cpt_slug() ) {
      return new \WP_REST_Response( [ 'error' => 'Membership group not found.' ], 404 );
    }

    $group    = new Membership_Group( $group_post_id );
    $statuses = Helper::get_all_status_names();
    $meta     = Helper::get_post_meta( $group_post_id );

    $status_slug = $meta['membership_status'] ?? '';

    // Parse stored date strings into DateTime objects so legacy non-ISO values
    // are normalised before being re-emitted as ISO 8601.
    $starts_at   = ! empty( $meta['membership_starts_at'] )    ? new \DateTime( $meta['membership_starts_at'] )    : null;
    $ends_at     = ! empty( $meta['membership_ends_at'] )      ? new \DateTime( $meta['membership_ends_at'] )      : null;
    $expires_at  = ! empty( $meta['membership_expires_at'] )   ? new \DateTime( $meta['membership_expires_at'] )   : null;
    $early_renew = ! empty( $meta['membership_early_renew_at'] ) ? new \DateTime( $meta['membership_early_renew_at'] ) : null;

    return [
      'ID'                 => $group_post_id,
      'title'              => get_the_title( $group_post_id ),
      'data'               => array_merge( $meta, [
        'membership_status'         => $statuses[ $status_slug ]['name'] ?? $status_slug,
        'membership_status_slug'    => $status_slug,
        'membership_starts_at'      => $starts_at   ? $starts_at->format( 'c' )   : '',
        'membership_ends_at'        => $ends_at     ? $ends_at->format( 'c' )     : '',
        'membership_expires_at'     => $expires_at  ? $expires_at->format( 'c' )  : '',
        'membership_early_renew_at' => $early_renew ? $early_renew->format( 'c' ) : '',
      ] ),
      'individual_members' => array_map( fn( $p ) => $p->ID, $group->get_individual_memberships() ),
    ];
  }

  /**
   * Build one list row per membership-group post.
   *
   * @param \WP_Post $post
   * @return array
   */
  private static function build_membership_groups_row( \WP_Post $post ): array {
    $statuses        = Helper::get_all_status_names();
    $post_id         = (int) $post->ID;
    $status_slug     = (string) get_post_meta( $post_id, 'membership_status', true );
    $org_uuid        = (string) get_post_meta( $post_id, 'org_uuid', true );
    $group           = new Membership_Group( $post_id );
    $wicket_settings = get_wicket_settings( $_ENV['WP_ENV'] ?? null );
    $wicket_admin    = $wicket_settings['wicket_admin'] ?? '';
    $mdp_link        = ( $org_uuid && $wicket_admin )
      ? $wicket_admin . '/organizations/' . $org_uuid
      : '';

    return [
      'id'            => $post_id,
      'group_name'    => self::get_membership_group_name( $post_id ),
      'org_name'      => (string) get_post_meta( $post_id, 'org_name', true ),
      'owner'         => self::build_owner_field( $group ),
      'status'        => [
        'slug'  => $status_slug,
        'label' => $statuses[ $status_slug ]['name'] ?? $status_slug,
      ],
      'last_updated'  => (string) $post->post_modified,
      'post_modified' => (string) $post->post_modified,
      'org_uuid'      => $org_uuid,
      'mdp_link'      => $mdp_link,
    ];
  }

  /**
   * Build the owner payload used in admin list responses.
   *
   * @param Membership_Group $group
   * @return array{name: string, email: string}
   */
  private static function build_owner_field( Membership_Group $group ): array {
    $owner = self::resolve_owner_data( $group );
    if ( ! $owner ) {
      return [ 'name' => '', 'email' => '' ];
    }
    return [
      'name'  => $owner['name'],
      'email' => $owner['email'],
    ];
  }

  /**
   * Resolve canonical owner data for group admin responses.
   *
   * Prefers the MDP full_name when available. Falls back to the WP user's
   * first/last name, then to display_name only when it is not just the UUID.
   *
   * @param Membership_Group $group
   * @return array|null
   */
  private static function resolve_owner_data( Membership_Group $group ): ?array {
    $owner = $group->get_owner();
    if ( ! $owner ) {
      return null;
    }

    $owner_person = null;
    if ( isValidUuid( $owner['uuid'] ) && function_exists( 'wicket_get_person_by_id' ) ) {
      try {
        $owner_person = wicket_get_person_by_id( $owner['uuid'] );
      } catch ( \Throwable $e ) {
        $owner_person = null;
      }
    }

    $mdp_full_name = ( is_object( $owner_person ) && method_exists( $owner_person, 'getAttribute' ) )
      ? trim( (string) $owner_person->getAttribute( 'full_name' ) )
      : '';

    $user = get_user_by( 'id', (int) $owner['user_id'] );
    $wp_full_name = '';
    if ( $user ) {
      $wp_full_name = trim( implode( ' ', array_filter( [
        (string) $user->first_name,
        (string) $user->last_name,
      ] ) ) );
    }

    $display_name = trim( (string) $owner['name'] );
    $resolved_name = $mdp_full_name !== ''
      ? $mdp_full_name
      : ( $wp_full_name !== ''
        ? $wp_full_name
        : ( $display_name !== '' && $display_name !== $owner['uuid'] ? $display_name : '' ) );

    return array_merge( $owner, [
      'name'               => $resolved_name,
      'identifying_number' => ( is_object( $owner_person ) && method_exists( $owner_person, 'getAttribute' ) )
        ? $owner_person->getAttribute( 'identifying_number' )
        : '',
    ] );
  }

  /**
   * Compare two grouped rows for sorting.
   *
   * @param array  $left
   * @param array  $right
   * @param string $order_col
   * @param string $sort_dir
   * @return int
   */
  private static function compare_membership_group_rows( array $left, array $right, string $order_col, string $sort_dir ): int {
    if ( $order_col === 'group_name' ) {
      $result = strcasecmp( (string) $left['group_name'], (string) $right['group_name'] );
    } elseif ( $order_col === 'org_name' ) {
      $result = strcasecmp( (string) $left['org_name'], (string) $right['org_name'] );
    } elseif ( $order_col === 'user_name' ) {
      $result = strcasecmp( (string) $left['owner']['name'], (string) $right['owner']['name'] );
    } elseif ( $order_col === 'membership_status' ) {
      $result = strcasecmp( (string) $left['status']['label'], (string) $right['status']['label'] );
    } else {
      $left_ts = strtotime( (string) $left['last_updated'] ) ?: 0;
      $right_ts = strtotime( (string) $right['last_updated'] ) ?: 0;
      $result = $left_ts <=> $right_ts;
    }

    if ( $result === 0 ) {
      $result = strcasecmp( (string) $left['group_name'], (string) $right['group_name'] );
    }

    return $sort_dir === 'ASC' ? $result : $result * -1;
  }

  /**
   * Resolve the preferred group name for list-table display.
   *
   * @param int $post_id
   * @return string
   */
  private static function get_membership_group_name( int $post_id ): string {
    $stored_name = (string) get_post_meta( $post_id, 'membership_group_name', true );
    if ( $stored_name !== '' ) {
      return $stored_name;
    }

    return get_the_title( $post_id );
  }

  // ---------------------------------------------------------------------------
  // Entity record update
  // ---------------------------------------------------------------------------

  /**
   * Update editable fields on a membership group post.
   *
   * Validates date ordering (start < end < expires) and cascades date changes
   * to child individual memberships.
   *
   * @param array $data  Expects keys: group_post_id, membership_starts_at,
   *                     membership_ends_at, membership_expires_at.
   *                     Optional: membership_renewal_type, membership_next_tier_form_page_id,
   *                     membership_next_tier_id.
   * @return \WP_REST_Response
   * @todo Wire in subscription date updates when renewal type changes — see TODO.md
   */
  public static function update_group_entity_record( array $data ): \WP_REST_Response {
    foreach ( $data as $key => $value ) {
      if ( is_scalar( $value ) ) {
        $data[ $key ] = sanitize_text_field( (string) $value );
      }
    }

    $group_post_id = (int) ( $data['group_post_id'] ?? 0 );

    if ( ! $group_post_id || get_post_type( $group_post_id ) !== Helper::get_membership_group_cpt_slug() ) {
      return new \WP_REST_Response( [ 'error' => 'Membership group not found.' ], 404 );
    }

    $group_meta = Helper::get_post_meta( $group_post_id );
    if ( ( $group_meta['membership_status'] ?? '' ) === Wicket_Memberships::STATUS_CANCELLED ) {
      return new \WP_REST_Response( [ 'error' => 'Cannot update a cancelled membership record. Membership update failed.' ], 400 );
    }

    if (
      ! array_key_exists( 'membership_starts_at', $data )
      || ! array_key_exists( 'membership_ends_at', $data )
      || ! array_key_exists( 'membership_expires_at', $data )
    ) {
      return new \WP_REST_Response( [ 'error' => 'All dates required.' ], 400 );
    }

    $starts_at  = strtotime( $data['membership_starts_at'] );
    $ends_at    = strtotime( $data['membership_ends_at'] );
    $expires_at = strtotime( $data['membership_expires_at'] );

    if ( false === $starts_at || false === $ends_at || false === $expires_at ) {
      return new \WP_REST_Response( [ 'error' => 'Invalid date value received.' ], 400 );
    }

    if ( $starts_at && $ends_at && $starts_at >= $ends_at ) {
      return new \WP_REST_Response( [ 'error' => 'Start date must be before end date.' ], 400 );
    }
    if ( $ends_at && $expires_at && $ends_at > $expires_at ) {
      return new \WP_REST_Response( [ 'error' => 'End date must not be after expiration date.' ], 400 );
    }

    $group             = new Membership_Group( $group_post_id );
    $normalized_fields = self::build_normalized_group_edit_fields( $group_post_id, $data, $starts_at, $ends_at, $expires_at );

    if ( ! $group->apply_edit_fields( $normalized_fields ) ) {
      return new \WP_REST_Response( [ 'error' => 'Could not update membership group record.' ], 500 );
    }

    $group->cascade_dates_to_members( $normalized_fields );

    return new \WP_REST_Response( [
      'success'  => 'Membership group updated successfully.',
      'response' => Helper::get_post_meta( $group_post_id ),
    ], 200 );
  }

  /**
   * Normalize incoming group edit data to the same local shape used by the
   * individual membership edit flow.
   *
   * Order/subscription side effects are intentionally deferred; see TODO.md.
   *
   * @param int   $group_post_id
   * @param array $data
   * @param int   $starts_at
   * @param int   $ends_at
   * @param int   $expires_at
   * @return array<string, mixed>
   */
  private static function build_normalized_group_edit_fields( int $group_post_id, array $data, int $starts_at, int $ends_at, int $expires_at ): array {
    $group = new Membership_Group( $group_post_id );
    $config = $group->get_config();

    $renewal_type = $data['membership_renewal_type'] ?? $data['renewal_type'] ?? get_post_meta( $group_post_id, 'membership_renewal_type', true );
    $renewal_type = $renewal_type !== '' ? $renewal_type : 'inherited';

    $renewal_window_days = $config ? $config->get_renewal_window_days() : false;
    $early_renew_at = $starts_at;
    if ( $renewal_window_days !== false ) {
      $early_renew_at = strtotime( '-' . absint( $renewal_window_days ) . ' days', $ends_at );
    }

    $grace_period_days = abs( (int) round( ( $expires_at - $ends_at ) / DAY_IN_SECONDS ) );

    $resolved = [
      'membership_starts_at'         => Utilities::get_utc_datetime( date( 'Y-m-d', $starts_at ) )->format( 'c' ),
      'membership_ends_at'           => Utilities::get_utc_datetime( date( 'Y-m-d', $ends_at ) )->format( 'c' ),
      'membership_expires_at'        => Utilities::get_utc_datetime( date( 'Y-m-d', $expires_at ) )->format( 'c' ),
      'membership_early_renew_at'    => Utilities::get_utc_datetime( date( 'Y-m-d', $early_renew_at ) )->format( 'c' ),
      'membership_grace_period_days' => $grace_period_days,
      'membership_renewal_type'      => $renewal_type,
    ];

    $current_next_tier_id = get_post_meta( $group_post_id, 'membership_next_tier_id', true );
    $current_next_form_id = get_post_meta( $group_post_id, 'membership_next_tier_form_page_id', true );
    $current_next_sub_renew = get_post_meta( $group_post_id, 'membership_next_tier_subscription_renewal', true );

    $resolved['membership_next_tier_id'] = $current_next_tier_id !== '' ? (int) $current_next_tier_id : '';
    $resolved['membership_next_tier_form_page_id'] = $current_next_form_id !== '' ? (int) $current_next_form_id : '';
    $resolved['membership_next_tier_subscription_renewal'] = $current_next_sub_renew !== '' ? (int) $current_next_sub_renew : 0;

    $requested_next_tier_id = isset( $data['membership_next_tier_id'] ) ? (int) $data['membership_next_tier_id'] : ( isset( $data['next_tier_id'] ) ? (int) $data['next_tier_id'] : 0 );
    $requested_next_form_id = isset( $data['membership_next_tier_form_page_id'] ) ? (int) $data['membership_next_tier_form_page_id'] : ( isset( $data['next_tier_form_page_id'] ) ? (int) $data['next_tier_form_page_id'] : 0 );

    if ( $renewal_type === 'subscription' ) {
      $resolved['membership_next_tier_id'] = '';
      $resolved['membership_next_tier_form_page_id'] = '';
      $resolved['membership_next_tier_subscription_renewal'] = 1;
    } elseif ( $renewal_type === 'current_tier' ) {
      $resolved['membership_next_tier_id'] = '';
      $resolved['membership_next_tier_form_page_id'] = '';
      $resolved['membership_next_tier_subscription_renewal'] = 0;
    } elseif ( $requested_next_form_id > 0 ) {
      $resolved['membership_next_tier_id'] = '';
      $resolved['membership_next_tier_form_page_id'] = $requested_next_form_id;
      $resolved['membership_next_tier_subscription_renewal'] = 0;
    } elseif ( $renewal_type === 'sequential_logic' || $requested_next_tier_id > 0 ) {
      $resolved['membership_next_tier_id'] = $requested_next_tier_id;
      $resolved['membership_next_tier_form_page_id'] = '';
      $resolved['membership_next_tier_subscription_renewal'] = 0;
    } elseif ( $renewal_type === 'inherited' ) {
      if ( $config && $config->is_renewal_subscription() ) {
        $resolved['membership_next_tier_id'] = '';
        $resolved['membership_next_tier_form_page_id'] = '';
        $resolved['membership_next_tier_subscription_renewal'] = 1;
      } elseif ( $config && $config->is_renewal_form_page() ) {
        $resolved['membership_next_tier_id'] = '';
        $resolved['membership_next_tier_form_page_id'] = (int) $config->get_renewal_form_page_id();
        $resolved['membership_next_tier_subscription_renewal'] = 0;
      } else {
        $resolved['membership_next_tier_id'] = '';
        $resolved['membership_next_tier_form_page_id'] = '';
        $resolved['membership_next_tier_subscription_renewal'] = 0;
      }
    }

    return $resolved;
  }

  // ---------------------------------------------------------------------------
  // Edit page info
  // ---------------------------------------------------------------------------

  /**
   * Return all data required to populate the membership group edit form.
   *
   * @param int $group_post_id
   * @return array|\WP_REST_Response
   */
  public static function get_group_edit_page_info( int $group_post_id ) {
    $post = get_post( $group_post_id );
    if ( ! $post || get_post_type( $group_post_id ) !== Helper::get_membership_group_cpt_slug() ) {
      return new \WP_REST_Response( [ 'error' => 'Membership group not found.' ], 404 );
    }

    $wicket_settings = get_wicket_settings( $_ENV['WP_ENV'] ?? null );
    $wicket_admin    = $wicket_settings['wicket_admin'] ?? '';
    $group           = new Membership_Group( $group_post_id );
    $meta            = Helper::get_post_meta( $group_post_id );

    // Organisation data from MDP.
    $org_uuid = $group->get_org_uuid();
    $org_data = $org_uuid ? Helper::get_org_data( $org_uuid ) : [];
    $mdp_org_link = ( $org_uuid && $wicket_admin )
      ? $wicket_admin . '/organizations/' . $org_uuid
      : '';

    $owner_data = self::resolve_owner_data( $group );
    if ( $owner_data ) {
      $owner_data['mdp_link']      = $wicket_admin ? $wicket_admin . '/people/' . $owner_data['uuid'] : '';
      $owner_data['switch_to_url'] = Helper::get_user_switch_to_url( $owner_data['user_id'] );
    }

    // Config data.
    $config      = $group->get_config();
    $config_data = $config ? Helper::get_post_meta( $config->get_post_id() ) : [];

    // The "Membership Records" table on the group detail page should show the
    // membership group record itself. Child individual memberships are managed
    // separately via the Group Members section.
    $statuses           = Helper::get_all_status_names();
    $status_slug        = (string) ( $meta['membership_status'] ?? '' );
    $membership_records = [
      [
        'ID'                     => $group_post_id,
        'name'                   => self::get_membership_group_name( $group_post_id ),
        'status'                 => $statuses[ $status_slug ]['name'] ?? $status_slug,
        'starts_at'              => (string) ( $meta['membership_starts_at'] ?? '' ),
        'ends_at'                => (string) ( $meta['membership_ends_at'] ?? '' ),
        'expires_at'             => (string) ( $meta['membership_expires_at'] ?? '' ),
        'early_renew_at'         => (string) ( $meta['membership_early_renew_at'] ?? '' ),
        'renewal_type'           => (string) ( $meta['membership_renewal_type'] ?? '' ),
        'next_tier_form_page_id' => (int) ( $meta['membership_next_tier_form_page_id'] ?? 0 ) ?: null,
        'next_tier_id'           => (int) ( $meta['membership_next_tier_id'] ?? 0 ) ?: null,
      ],
    ];

    // Order/subscription enrichment is intentionally mocked until the group
    // commerce implementation exists; see TODO.md.
    return [
      'ID'                  => $group_post_id,
      'title'               => get_the_title( $group_post_id ),
      'meta'                => $meta,
      'org'                 => [
        'uuid'     => $org_uuid,
        'name'     => $org_data['name']     ?? '',
        'location' => $org_data['location'] ?? '',
        'mdp_link' => $mdp_org_link,
      ],
      'owner'               => $owner_data,
      'config'              => $config_data,
      'config_id'           => $config ? $config->get_post_id() : null,
      'config_title'        => $config ? get_the_title( $config->get_post_id() ) : '',
      'config_renewal_type' => $config ? $config->get_renewal_type() : '',
      'subscription_id'     => $group->get_subscription_id(),
      'subscription'        => self::MOCK_SUBSCRIPTION_PAYLOAD,
      'order'               => self::MOCK_ORDER_PAYLOAD,
      'orders'              => self::MOCK_ORDERS_PAYLOAD,
      'dates'               => $group->get_dates(),
      'statuses'            => $statuses,
      'allowed_transitions' => Helper::get_allowed_transition_status( $meta['membership_status'] ?? '' ),
      'membership_records'  => $membership_records,
    ];
  }

  // ---------------------------------------------------------------------------
  // Members by tier
  // ---------------------------------------------------------------------------

  /**
   * Return the total member count and per-tier breakdown for a group.
   *
   * @param int $group_post_id
   * @return array|\WP_REST_Response
   */
  public static function get_group_members_by_tier( int $group_post_id ) {
    $post = get_post( $group_post_id );
    if ( ! $post || get_post_type( $group_post_id ) !== Helper::get_membership_group_cpt_slug() ) {
      return new \WP_REST_Response( [ 'error' => 'Membership group not found.' ], 404 );
    }

    $group   = new Membership_Group( $group_post_id );
    $members = $group->get_individual_memberships();
    $counts  = [];

    $counts = [];
    foreach ( $members as $member ) {
      $tier_uuid = (string) get_post_meta( $member->ID, 'membership_tier_uuid', true );
      $tier_name = (string) get_post_meta( $member->ID, 'membership_tier_name', true );
      if ( $tier_uuid === '' ) {
        continue;
      }
      if ( ! isset( $counts[ $tier_uuid ] ) ) {
        $counts[ $tier_uuid ] = [ 'tier_name' => $tier_name, 'count' => 0 ];
      }
      $counts[ $tier_uuid ]['count']++;
    }

    $tiers = [];
    foreach ( $counts as $tier_uuid => $data ) {
      $tiers[] = [
        'tier_uuid'    => $tier_uuid,
        'tier_name'    => $data['tier_name'],
        'member_count' => $data['count'],
      ];
    }

    usort( $tiers, fn( $a, $b ) => strcasecmp( $a['tier_name'], $b['tier_name'] ) );

    return [
      'total_members' => count( $members ),
      'tiers'         => $tiers,
    ];
  }

  // ---------------------------------------------------------------------------
  // Ownership change
  // ---------------------------------------------------------------------------

  /**
   * Change the membership owner on a group post and update the WC subscription.
   *
   * @param array $params Expects: group_post_id, new_owner_uuid
   * @return \WP_REST_Response
   */
  public static function update_group_change_ownership( array $params ): \WP_REST_Response {
    $group_post_id = (int) ( $params['group_post_id'] ?? 0 );
    $new_owner_uuid = $params['new_owner_uuid'] ?? '';

    if ( ! $group_post_id || get_post_type( $group_post_id ) !== Helper::get_membership_group_cpt_slug() ) {
      return new \WP_REST_Response( [ 'error' => 'Membership group not found.' ], 404 );
    }

    $new_owner_uuid = is_string( $new_owner_uuid ) ? trim( $new_owner_uuid ) : '';

    if ( '' === $new_owner_uuid ) {
      return new \WP_REST_Response( [ 'error' => 'new_owner_uuid is required.' ], 400 );
    }

    $group            = new Membership_Group( $group_post_id );
    $current_owner_uuid = $group->get_owner_uuid();

    if ( $current_owner_uuid && $current_owner_uuid === $new_owner_uuid ) {
      return new \WP_REST_Response( [], 204 );
    }

    if ( false === $group->set_owner( $new_owner_uuid ) ) {
      return new \WP_REST_Response( [ 'error' => 'Could not update group owner.' ], 500 );
    }

    return new \WP_REST_Response( [
      'success'  => 'Membership group ownership updated successfully.',
      'response' => Helper::get_post_meta( $group_post_id ),
    ], 200 );
  }

  // ---------------------------------------------------------------------------
  // Member management
  // ---------------------------------------------------------------------------

  /**
   * Add an individual membership to a group.
   *
   * Dispatches to Membership_Group::add_member() based on the 'mode' key in $params:
   * - 'new'      → resolve WP user from person_uuid, create a new membership and link it.
   * - 'existing' → cancel the existing membership, create a new one with group dates and link it.
   *
   * @param array $params {
   *   @type int    $group_post_id               Post ID of the Membership_Group.
   *   @type string $mode                        'new' or 'existing'.
   *   @type int    $tier_post_id                Post ID of the individual Membership_Tier.
   *   @type string $person_uuid                 MDP person UUID. Required when mode = 'new'.
   *   @type int    $existing_membership_post_id Existing membership post ID to cancel. Required when mode = 'existing'.
   *   @type int    $product_id                  Optional WC product ID. Auto-resolved from tier when omitted.
   * }
   * @return array{success: string, membership_post_id: int}|array{error: string, code: string}
   */
  public static function add_member( array $params ): array {
    $group_post_id               = (int) ( $params['group_post_id'] ?? 0 );
    $mode                        = sanitize_text_field( $params['mode'] ?? '' );
    $tier_post_id                = (int) ( $params['tier_post_id'] ?? 0 );
    $product_id                  = ! empty( $params['product_id'] ) ? (int) $params['product_id'] : null;
    $variation_id                = ! empty( $params['variation_id'] ) ? (int) $params['variation_id'] : null;
    $existing_membership_post_id = ! empty( $params['existing_membership_post_id'] ) ? (int) $params['existing_membership_post_id'] : null;

    $group = new Membership_Group( $group_post_id );
    if ( $group->post_id <= 0 ) {
      return [ 'error' => 'Membership group not found.', 'code' => 'group_not_found' ];
    }

    // For existing mode, derive product/variation from the existing membership
    // when the caller doesn't supply them — avoids requiring the frontend to
    // know whether membership_product_id is a parent or variation ID.
    if ( $mode === 'existing' && $existing_membership_post_id && $product_id === null && $variation_id === null ) {
      $stored_product_id = (int) get_post_meta( $existing_membership_post_id, 'membership_product_id', true );
      if ( $stored_product_id > 0 ) {
        $wc_product = wc_get_product( $stored_product_id );
        if ( $wc_product instanceof \WC_Product_Variation ) {
          $variation_id = $stored_product_id;
          $product_id   = (int) $wc_product->get_parent_id();
        } else {
          $product_id = $stored_product_id;
        }
      }
    }

    $user_id = null;

    if ( $mode === 'new' ) {
      $person_uuid = sanitize_text_field( $params['person_uuid'] ?? '' );
      // Resolve or create the WP user from the MDP person UUID.
      $user_id = (int) wicket_create_wp_user_if_not_exist( $person_uuid );
      if ( $user_id <= 0 ) {
        return [ 'error' => 'Could not resolve or create a WordPress user for the provided person_uuid.', 'code' => 'user_resolve_failed' ];
      }
    }

    $result = $group->add_member( $user_id, $tier_post_id, $product_id, $variation_id, $existing_membership_post_id );

    if ( is_wp_error( $result ) ) {
      return [ 'error' => $result->get_error_message(), 'code' => $result->get_error_code() ];
    }

    return [ 'success' => 'Member added to group.', 'membership_post_id' => $result ];
  }

  /**
   * Remove an individual membership from a group.
   *
   * Dispatches to Membership_Group::remove_member() based on the 'mode' key in $params:
   * - 'cancel'             → cancel the membership immediately.
   * - 'keep_as_individual' → cancel the membership and create a new standalone individual
   *                          membership with start=now and end=group end date.
   *
   * @param array $params {
   *   @type int    $group_post_id       Post ID of the Membership_Group.
   *   @type int    $membership_post_id  Post ID of the individual membership to remove.
   *   @type string $mode                'cancel' or 'keep_as_individual'.
   * }
   * @return array{success: string, membership_post_id: int}|array{error: string, code: string}
   */
  public static function remove_member( array $params ): array {
    $group_post_id      = (int) ( $params['group_post_id'] ?? 0 );
    $membership_post_id = (int) ( $params['membership_post_id'] ?? 0 );
    $mode               = sanitize_text_field( $params['mode'] ?? '' );

    $group = new Membership_Group( $group_post_id );
    if ( $group->post_id <= 0 ) {
      return [ 'error' => 'Membership group not found.', 'code' => 'group_not_found' ];
    }

    $result = $group->remove_member( $membership_post_id, $mode );

    if ( is_wp_error( $result ) ) {
      return [ 'error' => $result->get_error_message(), 'code' => $result->get_error_code() ];
    }

    return [ 'success' => 'Member removed from group.', 'membership_post_id' => $result ];
  }

  // Move member
  // ---------------------------------------------------------------------------

  /**
   * Move an individual membership from one group to another.
   *
   * Cancels the source individual membership and creates a new one linked to the
   * target group. On failure after cancellation, returns an error — no rollback.
   *
   * @param array $params {
   *   @type int $source_group_post_id  Post ID of the source Membership_Group.
   *   @type int $membership_post_id    Post ID of the individual membership to move.
   *   @type int $target_group_post_id  Post ID of the target Membership_Group.
   * }
   * @return array{success: string, membership_post_id: int}|array{error: string, code: string}
   */
  public static function move_individual_membership( array $params ): array {
    $source_group_post_id = (int) ( $params['source_group_post_id'] ?? 0 );
    $membership_post_id   = (int) ( $params['membership_post_id'] ?? 0 );
    $target_group_post_id = (int) ( $params['target_group_post_id'] ?? 0 );

    $source_group = new Membership_Group( $source_group_post_id );
    if ( $source_group->post_id <= 0 ) {
      return [ 'error' => 'Source membership group not found.', 'code' => 'group_not_found' ];
    }

    $target_group = new Membership_Group( $target_group_post_id );
    if ( $target_group->post_id <= 0 ) {
      return [ 'error' => 'Target membership group not found.', 'code' => 'target_group_not_found' ];
    }

    $result = $source_group->move_individual_membership( $membership_post_id, $target_group );

    if ( is_wp_error( $result ) ) {
      return [ 'error' => $result->get_error_message(), 'code' => $result->get_error_code() ];
    }

    return [ 'success' => 'Member moved to new group.', 'membership_post_id' => $result ];
  }

  // Renewal order
  // ---------------------------------------------------------------------------

  /**
   * Create a renewal order for a membership group.
   *
   * Not yet implemented. Blocked on:
   * - Group subscription line item structure being finalised (multi-tier line items).
   *
   * @param array $params
   * @return \WP_REST_Response
   * @todo Implement once the group subscription line item structure is finalised — see TODO.md
   */
  public static function create_group_renewal_order( array $params ): \WP_REST_Response {
    return new \WP_REST_Response( [ 'error' => 'Not yet implemented.' ], 501 );
  }

}
