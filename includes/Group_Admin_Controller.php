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
      $slug = ( new Membership_Group( $post_id ) )->get_membership_status();
      if ( $slug !== false && $slug !== '' && ! in_array( $slug, $used_slugs, true ) ) {
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

    $dates = $group->get_dates();

    $response_array['success']  = $transition_result['success_message'];
    $response_array['response'] = [
      'membership_status'         => $group->get_membership_status(),
      'membership_starts_at'      => $dates['starts_at'],
      'membership_ends_at'        => $dates['ends_at'],
      'membership_expires_at'     => $dates['expires_at'],
      'membership_early_renew_at' => $dates['early_renew_at'],
    ];
    return new \WP_REST_Response( $response_array, 200 );
  }

  // ---------------------------------------------------------------------------
  // Entity record retrieval
  // ---------------------------------------------------------------------------

  /**
   * Return the data needed to populate the membership group entity view.
   *
   * @param int $group_post_id
   * @return array|\WP_REST_Response
   */
  public static function get_group_entity_records( int $group_post_id ) {
    $post = get_post( $group_post_id );
    if ( ! $post || get_post_type( $group_post_id ) !== Helper::get_membership_group_cpt_slug() ) {
      return new \WP_REST_Response( [ 'error' => 'Membership group not found.' ], 404 );
    }

    $group    = new Membership_Group( $group_post_id );
    $statuses = Helper::get_all_status_names();
    $meta     = Helper::get_post_meta( $group_post_id );

    $status_slug = $group->get_membership_status() ?: '';
    $dates       = $group->get_dates();

    // Remove date keys from meta so the mangled Y-m-d values from Helper::get_post_meta
    // cannot leak through; dates are always sourced from the getter below.
    unset( $meta['membership_starts_at'], $meta['membership_ends_at'], $meta['membership_expires_at'], $meta['membership_early_renew_at'] );

    return [
      'ID'                 => $group_post_id,
      'title'              => $group->get_name(),
      'data'               => array_merge( $meta, [
        'membership_status'         => $statuses[ $status_slug ]['name'] ?? $status_slug,
        'membership_status_slug'    => $status_slug,
        'membership_starts_at'      => $dates['starts_at'],
        'membership_ends_at'        => $dates['ends_at'],
        'membership_expires_at'     => $dates['expires_at'],
        'membership_early_renew_at' => $dates['early_renew_at'],
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
    $group           = new Membership_Group( $post_id );
    $status_slug     = $group->get_membership_status() ?: '';
    $org_uuid        = $group->get_org_uuid() ?: '';
    $wicket_settings = get_wicket_settings( $_ENV['WP_ENV'] ?? null );
    $wicket_admin    = $wicket_settings['wicket_admin'] ?? '';
    $mdp_link        = ( $org_uuid && $wicket_admin )
      ? $wicket_admin . '/organizations/' . $org_uuid
      : '';

    return [
      'id'            => $post_id,
      'group_name'    => $group->get_name(),
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

    $group = new Membership_Group( $group_post_id );
    if ( $group->get_membership_status() === Wicket_Memberships::STATUS_CANCELLED ) {
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

    $dates = $group->get_dates();

    return new \WP_REST_Response( [
      'success'  => 'Membership group updated successfully.',
      'response' => [
        'membership_status'         => $group->get_membership_status(),
        'membership_starts_at'      => $dates['starts_at'],
        'membership_ends_at'        => $dates['ends_at'],
        'membership_expires_at'     => $dates['expires_at'],
        'membership_early_renew_at' => $dates['early_renew_at'],
      ],
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
      'membership_starts_at'         => Utilities::get_mdp_day_start( $data['membership_starts_at'] )->format( 'c' ),
      'membership_ends_at'           => Utilities::get_mdp_day_end( $data['membership_ends_at'] )->format( 'c' ),
      'membership_expires_at'        => Utilities::get_mdp_day_end( $data['membership_expires_at'] )->format( 'c' ),
      'membership_early_renew_at'    => Utilities::get_mdp_day_start( gmdate( 'Y-m-d', $early_renew_at ) )->format( 'c' ),
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
    $status_slug        = $group->get_membership_status() ?: '';
    $group_dates        = $group->get_dates();
    $membership_records = [
      [
        'ID'                     => $group_post_id,
        'name'                   => $group->get_name(),
        'status'                 => $statuses[ $status_slug ]['name'] ?? $status_slug,
        'starts_at'              => $group_dates['starts_at'],
        'ends_at'                => $group_dates['ends_at'],
        'expires_at'             => $group_dates['expires_at'],
        'early_renew_at'         => $group_dates['early_renew_at'],
        'renewal_type'           => (string) ( $meta['membership_renewal_type'] ?? '' ),
        'next_tier_form_page_id' => (int) ( $meta['membership_next_tier_form_page_id'] ?? 0 ) ?: null,
        'next_tier_id'           => (int) ( $meta['membership_next_tier_id'] ?? 0 ) ?: null,
      ],
    ];

    $sub_id = $group->get_subscription_id();
    $sub    = $sub_id ? wcs_get_subscription( $sub_id ) : null;

    $subscription_payload = $sub ? [
      'id'                => $sub->get_id(),
      'link'              => admin_url( 'post.php?post=' . $sub->get_id() . '&action=edit' ),
      'status'            => $sub->get_status(),
      'next_payment_date' => $sub->get_time( 'next_payment' )
        ? ( new \DateTime( '@' . $sub->get_time( 'next_payment' ) ) )->format( 'c' )
        : null,
    ] : null;

    $order_payload = null;
    if ( $sub ) {
      $parent_ids = $sub->get_related_orders( 'all', 'parent' );
      $parent_id  = reset( $parent_ids );
      if ( $parent_id ) {
        $o = wc_get_order( $parent_id );
        if ( $o ) {
          $order_payload = [
            'id'             => $o->get_id(),
            'status'         => $o->get_status(),
            'link'           => admin_url( 'post.php?post=' . $o->get_id() . '&action=edit' ),
            'total'          => $o->get_total(),
            'date_created'   => $o->get_date_created() ? $o->get_date_created()->format( 'c' ) : null,
            'date_completed' => $o->get_date_completed() ? $o->get_date_completed()->format( 'c' ) : null,
          ];
        }
      }
    }

    $orders_payload = [];
    if ( $sub ) {
      foreach ( $sub->get_related_orders( 'all' ) as $order_id ) {
        $o = wc_get_order( $order_id );
        if ( ! $o ) {
          continue;
        }
        $orders_payload[] = [
          'id'             => $o->get_id(),
          'status'         => $o->get_status(),
          'link'           => admin_url( 'post.php?post=' . $o->get_id() . '&action=edit' ),
          'total'          => $o->get_total(),
          'date_created'   => $o->get_date_created() ? $o->get_date_created()->format( 'c' ) : null,
          'date_completed' => $o->get_date_completed() ? $o->get_date_completed()->format( 'c' ) : null,
          'type'           => wcs_order_contains_renewal( $o ) ? 'renewal' : ( wcs_order_contains_subscription( $o ) ? 'parent' : 'other' ),
        ];
      }
    }

    return [
      'ID'                  => $group_post_id,
      'title'               => $group->get_name(),
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
      'subscription'        => $subscription_payload,
      'order'               => $order_payload,
      'orders'              => $orders_payload,
      'dates'               => $group_dates,
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

    $dates = $group->get_dates();

    return new \WP_REST_Response( [
      'success'  => 'Membership group ownership updated successfully.',
      'response' => [
        'membership_status'         => $group->get_membership_status(),
        'membership_starts_at'      => $dates['starts_at'],
        'membership_ends_at'        => $dates['ends_at'],
        'membership_expires_at'     => $dates['expires_at'],
        'membership_early_renew_at' => $dates['early_renew_at'],
      ],
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

  // Cancel group
  // ---------------------------------------------------------------------------

  /**
   * Cancel a membership group with configurable handling for individual memberships.
   *
   * Three paths, selected by $member_handling + $timing:
   *
   * Path A — cancel_all + immediately:
   *   Transitions the group to cancelled (collapses dates to now via plan_status_transition,
   *   cancels the group subscription via transition_to's internal cascade). Cancels every
   *   individual membership immediately. No replacement memberships created.
   *
   * Path B — cancel_all + at_end_date:
   *   Calls Membership_Group::transition_to_cancelled_at_end_date(), which sets group status
   *   to cancelled while preserving ends_at, collapses expires_at to ends_at (removes grace
   *   period), and updates individual membership expires_at without touching their active
   *   status. Members retain access until the original end date. daily_membership_expiry_hook
   *   handles individual expiry naturally on that date — no per-member jobs needed.
   *   Group subscription set to pending-cancel. One AS job scheduled at ends_at to
   *   hard-cancel it. No replacement memberships created.
   *
   * Path C — keep_as_individual:
   *   Collects all individual membership meta before cancelling (assert_group_is_manageable
   *   and resolve_member_start_date both block after cancellation). Transitions the group to
   *   cancelled. For each collected membership: cancels it, then creates a new standalone
   *   individual membership record + WooCommerce subscription with start=today, inheriting
   *   the group's tier, product, and end/expires dates. membership_group_id meta is preserved
   *   on the cancelled record for historical reference.
   *
   * @param int    $group_post_id   Post ID of the membership group to cancel.
   * @param string $member_handling 'cancel_all' or 'keep_as_individual'.
   * @param string $timing          'immediately' or 'at_end_date'. Ignored when $member_handling
   *                                is 'keep_as_individual'.
   * @return \WP_REST_Response
   */
  public static function cancel_group( int $group_post_id, string $member_handling, string $timing ): \WP_REST_Response {
    $post = get_post( $group_post_id );
    if ( ! $post || get_post_type( $group_post_id ) !== Helper::get_membership_group_cpt_slug() ) {
      return new \WP_REST_Response( [ 'error' => 'Membership group not found.' ], 404 );
    }

    $member_handling = sanitize_text_field( $member_handling );
    $timing          = sanitize_text_field( $timing );

    $allowed_handling = [ 'cancel_all', 'keep_as_individual' ];
    $allowed_timing   = [ 'immediately', 'at_end_date' ];

    if ( ! in_array( $member_handling, $allowed_handling, true ) ) {
      return new \WP_REST_Response( [ 'error' => 'Invalid member_handling value.' ], 400 );
    }

    if ( $member_handling === 'cancel_all' && ! in_array( $timing, $allowed_timing, true ) ) {
      return new \WP_REST_Response( [ 'error' => 'Invalid timing value.' ], 400 );
    }

    $group = new Membership_Group( $group_post_id );

    // -------------------------------------------------------------------------
    // Path C — keep_as_individual
    // -------------------------------------------------------------------------
    if ( $member_handling === 'keep_as_individual' ) {
      $result = $group->cancel_keep_as_individual();
      if ( false === $result ) {
        return new \WP_REST_Response( [ 'error' => 'Could not cancel membership group. Transition failed.' ], 400 );
      }

      $response = [ 'success' => $result['success_message'] ];
      if ( ! empty( $result['warnings'] ) ) {
        $response['warnings'] = $result['warnings'];
      }
      return new \WP_REST_Response( $response, 200 );
    }

    // -------------------------------------------------------------------------
    // Path A — cancel_all + immediately
    // -------------------------------------------------------------------------
    if ( $timing === 'immediately' ) {
      // transition_to('cancelled') collapses dates to now, cancels the group subscription
      // via plan_status_transition, and cascades cancelled status to all child memberships
      // via cascade_status_to_members(). No per-member loop needed.
      $transition_result = $group->transition_to( Wicket_Memberships::STATUS_CANCELLED );
      if ( false === $transition_result ) {
        return new \WP_REST_Response( [ 'error' => 'Could not cancel membership group. Transition failed.' ], 400 );
      }

      self::cancel_group_subscription( $group->get_subscription_id(), array_merge( $group->get_dates(), [ 'group_post_id' => $group_post_id ] ) );

      return new \WP_REST_Response( [ 'success' => 'Membership group and all individual memberships cancelled immediately.' ], 200 );
    }

    // -------------------------------------------------------------------------
    // Path B — cancel_all + at_end_date
    // -------------------------------------------------------------------------

    $group_dates = $group->get_dates();
    if ( empty( $group_dates['ends_at'] ) ) {
      return new \WP_REST_Response( [ 'error' => 'Membership group has no end date. Cannot schedule deferred cancellation.' ], 400 );
    }

    // Delegates to transition_to_cancelled_at_end_date() which: sets group status to
    // cancelled preserving ends_at, collapses group expires_at to ends_at, and updates
    // individual membership expires_at without touching their active status.
    $transition_result = $group->transition_to_cancelled_at_end_date();
    if ( false === $transition_result ) {
      return new \WP_REST_Response( [ 'error' => 'Could not cancel membership group at end date. Transition failed.' ], 400 );
    }

    // Set group subscription to pending-cancel so it stops renewing immediately.
    $sub_id = $group->get_subscription_id();
    if ( $sub_id && function_exists( 'wcs_get_subscription' ) ) {
      $sub = wcs_get_subscription( $sub_id );
      if ( $sub ) {
        $sub->add_order_note(
          sprintf(
            /* translators: 1: membership group post ID */
            __( 'Set to pending-cancel by admin — membership group (ID: %d) cancelled at end date. Subscription will be cancelled when the group end date is reached.', 'wicket-memberships' ),
            $group_post_id
          )
        );
        try {
          $sub->update_status( 'pending-cancel' );
        } catch ( \Exception $e ) {
          // Subscription may already be in a terminal status (cancelled, expired).
          // Log and continue — the AS job will still fire at ends_at.
          Utilities::wc_log_mship_error( [
            'Group_Admin_Controller::cancel_group (path B): could not set subscription to pending-cancel',
            'subscription_id' => $sub->get_id(),
            'error'           => $e->getMessage(),
          ] );
        }
        $sub->save();
      }
    }

    // Schedule one AS job at ends_at to hard-cancel the subscription.
    // The hook handler in wicket.php calls $subscription->update_status('cancelled').
    $ends_at_ts = strtotime( $group_dates['ends_at'] );
    if ( $ends_at_ts && ! as_next_scheduled_action( 'wicket_group_cancel_subscription', [ $group_post_id ] ) ) {
      as_schedule_single_action( $ends_at_ts, 'wicket_group_cancel_subscription', [ $group_post_id ] );
    }

    return new \WP_REST_Response( [ 'success' => 'Membership group set to cancel at end date. Subscription set to pending-cancel.' ], 200 );
  }

  /**
   * Cancel the WC subscription linked to a membership group.
   *
   * Updates the subscription end date from $meta_data['ends_at'], clears next_payment,
   * and sets status to cancelled. No-op when the subscription does not exist or
   * WooCommerce Subscriptions is not active.
   *
   * @param int|false $subscription_id
   * @param array     $meta_data  Group date meta — expects key 'ends_at'.
   */
  private static function cancel_group_subscription( $subscription_id, array $meta_data ): void {
    if ( ! $subscription_id || ! function_exists( 'wcs_get_subscription' ) ) {
      return;
    }

    $sub = wcs_get_subscription( $subscription_id );
    if ( ! $sub ) {
      return;
    }

    $date_updates = [];
    if ( ! empty( $meta_data['ends_at'] ) ) {
      $date_updates['end'] = date( 'Y-m-d H:i:s', strtotime( $meta_data['ends_at'] ) );
    }

    try {
      if ( ! empty( $date_updates ) ) {
        $sub->update_dates( $date_updates );
      }
    } catch ( \Exception $e ) {
      Utilities::wc_log_mship_error( [
        'Group_Admin_Controller::cancel_group_subscription: could not update subscription dates',
        'subscription_id' => $subscription_id,
        'error'           => $e->getMessage(),
      ] );
    }

    $sub->add_order_note(
      sprintf(
        /* translators: 1: membership group post ID */
        __( 'Subscription cancelled immediately by admin via membership group cancellation (group ID: %d).', 'wicket-memberships' ),
        $meta_data['group_post_id'] ?? 0
      )
    );
    $sub->update_status( 'cancelled' );
    $sub->save();
  }

}
