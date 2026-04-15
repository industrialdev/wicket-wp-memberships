<?php

namespace Wicket_Memberships;

use Wicket_Memberships\Utilities;
use Wicket_Memberships\Helper;

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
  // Group membership list
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
  public static function get_group_memberships_list(
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
      'user_email'        => 'user_email',
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
    $rows = array_map( [ self::class, 'build_group_memberships_row' ], $all_posts );
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
      return self::compare_group_membership_rows( $left, $right, $order_col, $sort_dir );
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
  // Group membership filters
  // ---------------------------------------------------------------------------

  /**
   * Return available filter options for the group membership list UI.
   *
   * Returns the set of membership_status values that exist on published
   * group posts, formatted as { name, value } pairs to match the shape
   * used by the individual-membership filters endpoint.
   *
   * @return array
   */
  public static function get_group_membership_filters(): array {
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
   * Return available status options for a group membership.
   *
   * If $group_post_id is supplied, returns only the valid transitions from the
   * group's current status.  Otherwise returns all status names.
   *
   * @param int|null $group_post_id
   * @return array
   */
  public static function get_admin_status_options( ?int $group_post_id = null ): array {
    if ( ! empty( $group_post_id ) ) {
      $current_status = get_post_meta( $group_post_id, 'membership_status', true );
      $transitions    = Helper::get_allowed_transition_status( $current_status );
      return is_array( $transitions ) ? $transitions : [];
    }
    return Helper::get_all_status_names();
  }

  /**
   * Transition a group membership to a new status.
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
    $tomorrow_iso  = ( new \DateTime( date( 'Y-m-d', strtotime( '+1 day' ) ), wp_timezone() ) )->format( 'c' );
    $yesterday_iso = ( new \DateTime( date( 'Y-m-d', strtotime( '-1 day' ) ), wp_timezone() ) )->format( 'c' );
    $now_iso       = ( new \DateTime( date( 'Y-m-d' ), wp_timezone() ) )->format( 'c' );

    if ( empty( $new_status ) ) {
      return new \WP_REST_Response( [ 'error' => 'Invalid status transition. Requested status was not received.' ], 400 );
    }

    $post = get_post( $group_post_id );
    if ( ! $post || get_post_type( $group_post_id ) !== Helper::get_membership_group_cpt_slug() ) {
      return new \WP_REST_Response( [ 'error' => 'Membership group not found.' ], 404 );
    }

    $current_status = get_post_meta( $group_post_id, 'membership_status', true );
    $group          = new Membership_Group( $group_post_id );
    $subscription_id = $group->get_subscription_id();

    $meta_data      = [];
    $response_array = [];
    $response_code  = 200;

    // --- pending → active -------------------------------------------------------
    if ( $current_status === Wicket_Memberships::STATUS_PENDING && $new_status === Wicket_Memberships::STATUS_ACTIVE ) {
      $config = $group->get_config();
      $dates  = $config ? $config->get_membership_dates() : [];

      $meta_data = [
        'membership_status'  => $new_status,
        'membership_starts_at'   => $dates['start_date']   ?? $now_iso,
        'membership_ends_at'     => $dates['end_date']     ?? $now_iso,
        'membership_expires_at'  => $dates['expires_at']   ?? ( $dates['end_date'] ?? $now_iso ),
        'membership_early_renew_at' => $dates['early_renew_at'] ?? ( $dates['end_date'] ?? $now_iso ),
      ];

      // Activate the WC subscription.
      if ( $subscription_id && function_exists( 'wcs_get_subscription' ) ) {
        $sub = wcs_get_subscription( $subscription_id );
        if ( ! empty( $sub ) ) {
          $sub->update_status( 'active' );

          $sub_dates = [ 'start_date' => substr( $meta_data['membership_starts_at'], 0, 10 ) . ' 00:00:00' ];
          if ( ! empty( $meta_data['membership_ends_at'] ) ) {
            $end = new \DateTime( substr( $meta_data['membership_ends_at'], 0, 10 ) . ' 23:59:59', wp_timezone() );
            $end->setTimezone( new \DateTimeZone( 'UTC' ) );
            $sub_dates['next_payment'] = $end->format( 'Y-m-d H:i:s' );
          }
          if ( ! empty( $meta_data['membership_expires_at'] ) ) {
            $exp = new \DateTime( substr( $meta_data['membership_expires_at'], 0, 10 ) . ' 23:59:59', wp_timezone() );
            $exp->setTimezone( new \DateTimeZone( 'UTC' ) );
            $sub_dates['end'] = $exp->format( 'Y-m-d H:i:s' );
          }
          $sub->update_dates( $sub_dates );
          $sub->save();
        }
      }

      $response_array['success'] = 'Pending group membership activated successfully.';

    // --- → cancelled ------------------------------------------------------------
    } elseif ( $new_status === Wicket_Memberships::STATUS_CANCELLED ) {
      if ( in_array( $current_status, [ Wicket_Memberships::STATUS_PENDING, Wicket_Memberships::STATUS_DELAYED ], true ) ) {
        $meta_data = [
          'membership_status'     => $new_status,
          'membership_starts_at'  => $yesterday_iso,
          'membership_ends_at'    => $now_iso,
          'membership_expires_at' => $now_iso,
        ];
      } elseif ( $current_status === Wicket_Memberships::STATUS_GRACE ) {
        $current_end = get_post_meta( $group_post_id, 'membership_ends_at', true );
        $meta_data   = [
          'membership_status'     => $new_status,
          'membership_ends_at'    => $current_end,
          'membership_expires_at' => $now_iso,
        ];
      } else {
        // active or any other cancellable state
        $meta_data = [
          'membership_status'     => $new_status,
          'membership_ends_at'    => $tomorrow_iso,
          'membership_expires_at' => $tomorrow_iso,
        ];
      }

      // TODO: Cancel the group WC subscription here once group subscription
      // management is implemented — see TODO.md.
      $response_array['success'] = 'Group membership cancelled successfully.';

    // --- → expired --------------------------------------------------------------
    } elseif ( $new_status === Wicket_Memberships::STATUS_EXPIRED ) {
      $meta_data = [
        'membership_status'     => $new_status,
        'membership_ends_at'    => $tomorrow_iso,
        'membership_expires_at' => $tomorrow_iso,
      ];

      // TODO: Cancel the group WC subscription here once group subscription
      // management is implemented — see TODO.md.
      $response_array['success'] = 'Group membership marked as expired.';

    } elseif ( empty( $_ENV['BYPASS_STATUS_CHANGE_LOCKOUT'] ) ) {
      $response_array['error'] = 'Invalid status transition. Request did not succeed.';
      Utilities::wc_log_mship_error( $response_array );
      return new \WP_REST_Response( $response_array, 400 );
    } else {
      // Bypass mode — force the status directly.
      update_post_meta( $group_post_id, 'membership_status', $new_status );
      $response_array['success'] = 'BYPASSED STATUS LOCKOUT — status set to ' . $new_status;
      Utilities::wc_log_mship_error( $response_array );
      return new \WP_REST_Response( $response_array, 200 );
    }

    // Persist meta changes.
    foreach ( $meta_data as $key => $value ) {
      update_post_meta( $group_post_id, $key, $value );
    }

    // Cascade status + dates to child individual memberships.
    $cascade_statuses  = [ 'expired', 'cancelled' ];
    $individual_skip   = [ 'expired', 'cancelled' ];
    $members           = $group->get_individual_memberships();
    foreach ( $members as $member_post ) {
      $member_status = get_post_meta( $member_post->ID, 'membership_status', true );
      if ( in_array( $member_status, $individual_skip, true ) ) {
        continue;
      }
      update_post_meta( $member_post->ID, 'membership_status', $new_status );
    }

    // TODO: cascade date changes to child individual memberships once
    // cascade_dates_to_members() is implemented — see TODO.md.

    $response_array['response'] = Helper::get_post_meta( $group_post_id );
    return new \WP_REST_Response( $response_array, $response_code );
  }

  // ---------------------------------------------------------------------------
  // Entity record retrieval
  // ---------------------------------------------------------------------------

  /**
   * Return the data needed to populate the group membership entity view.
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

    return [
      'ID'                 => $group_post_id,
      'title'              => get_the_title( $group_post_id ),
      'data'               => array_merge( $meta, [
        'membership_status'      => $statuses[ $status_slug ]['name'] ?? $status_slug,
        'membership_status_slug' => $status_slug,
        'membership_starts_at'   => ! empty( $meta['membership_starts_at'] )
          ? date( 'm/d/Y', strtotime( $meta['membership_starts_at'] ) ) : '',
        'membership_ends_at'     => ! empty( $meta['membership_ends_at'] )
          ? date( 'm/d/Y', strtotime( $meta['membership_ends_at'] ) ) : '',
        'membership_expires_at'  => ! empty( $meta['membership_expires_at'] )
          ? date( 'm/d/Y', strtotime( $meta['membership_expires_at'] ) ) : '',
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
  private static function build_group_memberships_row( \WP_Post $post ): array {
    $statuses        = Helper::get_all_status_names();
    $post_id         = (int) $post->ID;
    $status_slug     = (string) get_post_meta( $post_id, 'membership_status', true );
    $org_uuid        = (string) get_post_meta( $post_id, 'org_uuid', true );
    $wicket_settings = get_wicket_settings( $_ENV['WP_ENV'] ?? null );
    $wicket_admin    = $wicket_settings['wicket_admin'] ?? '';
    $mdp_link        = ( $org_uuid && $wicket_admin )
      ? $wicket_admin . '/organizations/' . $org_uuid
      : '';

    return [
      'id'            => $post_id,
      'group_name'    => self::get_group_membership_name( $post_id ),
      'org_name'      => (string) get_post_meta( $post_id, 'org_name', true ),
      'owner'         => [
        'name'  => (string) get_post_meta( $post_id, 'user_name', true ),
        'email' => (string) get_post_meta( $post_id, 'user_email', true ),
      ],
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
   * Compare two grouped rows for sorting.
   *
   * @param array  $left
   * @param array  $right
   * @param string $order_col
   * @param string $sort_dir
   * @return int
   */
  private static function compare_group_membership_rows( array $left, array $right, string $order_col, string $sort_dir ): int {
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
  private static function get_group_membership_name( int $post_id ): string {
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
   * Update editable fields on a group membership post.
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

    $normalized = self::normalize_group_edit_payload( $group_post_id, $data, $starts_at, $ends_at, $expires_at );

    foreach ( $normalized as $key => $value ) {
      update_post_meta( $group_post_id, $key, $value );
    }

    self::cascade_group_edit_to_members( $group_post_id, $normalized );

    return new \WP_REST_Response( [
      'success'  => 'Group membership updated successfully.',
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
  private static function normalize_group_edit_payload( int $group_post_id, array $data, int $starts_at, int $ends_at, int $expires_at ): array {
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
      'membership_starts_at'         => self::to_wp_timezone_iso( $starts_at ),
      'membership_ends_at'           => self::to_wp_timezone_iso( $ends_at ),
      'membership_expires_at'        => self::to_wp_timezone_iso( $expires_at ),
      'membership_early_renew_at'    => self::to_wp_timezone_iso( $early_renew_at ),
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

  /**
   * Apply locally supported group edit fields to child individual memberships.
   *
   * Subscription/order side effects are intentionally deferred; see TODO.md.
   *
   * @param int   $group_post_id
   * @param array $normalized
   * @return void
   */
  private static function cascade_group_edit_to_members( int $group_post_id, array $normalized ): void {
    $group = new Membership_Group( $group_post_id );
    $members = $group->get_individual_memberships();
    $cascade_keys = [
      'membership_starts_at',
      'membership_ends_at',
      'membership_expires_at',
      'membership_early_renew_at',
      'membership_grace_period_days',
      'membership_renewal_type',
      'membership_next_tier_id',
      'membership_next_tier_form_page_id',
      'membership_next_tier_subscription_renewal',
    ];

    foreach ( $members as $member_post ) {
      $status = (string) get_post_meta( $member_post->ID, 'membership_status', true );
      if ( in_array( $status, [ Wicket_Memberships::STATUS_CANCELLED, Wicket_Memberships::STATUS_EXPIRED ], true ) ) {
        continue;
      }

      foreach ( $cascade_keys as $key ) {
        if ( array_key_exists( $key, $normalized ) ) {
          update_post_meta( $member_post->ID, $key, $normalized[ $key ] );
        }
      }
    }
  }

  /**
   * Convert a timestamp into the same canonical local ISO representation used
   * by the individual membership edit flow.
   *
   * @param int $timestamp
   * @return string
   */
  private static function to_wp_timezone_iso( int $timestamp ): string {
    return ( new \DateTime( date( 'Y-m-d', $timestamp ), wp_timezone() ) )->format( 'c' );
  }

  // ---------------------------------------------------------------------------
  // Edit page info
  // ---------------------------------------------------------------------------

  /**
   * Return all data required to populate the group membership edit form.
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

    $owner_data = null;
    $owner_id   = $group->get_owner_id();
    if ( $owner_id ) {
      $owner_user = get_user_by( 'id', $owner_id );
      if ( $owner_user ) {
        $owner_uuid   = $group->get_owner_uuid() ?: $owner_user->user_login;
        $owner_person = null;

        if ( $owner_uuid && isValidUuid( $owner_uuid ) && function_exists( 'wicket_get_person_by_id' ) ) {
          try {
            $owner_person = wicket_get_person_by_id( $owner_uuid );
          } catch ( \Throwable $e ) {
            $owner_person = null;
          }
        }

        $owner_data = [
          'user_id'            => $owner_id,
          'uuid'               => $owner_uuid,
          'name'               => $owner_user->display_name,
          'email'              => $owner_user->user_email,
          'mdp_link'           => ( $owner_uuid && $wicket_admin )
            ? $wicket_admin . '/people/' . $owner_uuid
            : '',
          'identifying_number' => ( is_object( $owner_person ) && method_exists( $owner_person, 'getAttribute' ) )
            ? $owner_person->getAttribute( 'identifying_number' )
            : '',
        ];
      }
    }

    // Config data.
    $config      = $group->get_config();
    $config_data = $config ? Helper::get_post_meta( $config->get_post_id() ) : [];

    // Individual membership records belonging to this group.
    $statuses            = Helper::get_all_status_names();
    $individual_posts    = $group->get_individual_memberships();
    $membership_records  = array_map( function ( \WP_Post $member_post ) use ( $statuses ) {
      $member_meta = Helper::get_post_meta( $member_post->ID );
      $status_slug = $member_meta['membership_status'] ?? '';

      $group_id   = (int) ( $member_meta['membership_group_id'] ?? 0 );
      $group_name = $group_id ? (string) get_the_title( $group_id ) : '';

      $tier_post_id      = (int) ( $member_meta['membership_tier_post_id'] ?? 0 );
      $tier_renewal_type = '';
      if ( $tier_post_id ) {
        $tier              = new Membership_Tier( $tier_post_id );
        $tier_renewal_type = (string) $tier->get_tier_renewal_type();
      }

      return [
        'ID'                    => $member_post->ID,
        'name'                  => $group_name,
        'tier'                  => (string) ( $member_meta['membership_tier_name'] ?? '' ),
        'status'                => $statuses[ $status_slug ]['name'] ?? $status_slug,
        'starts_at'             => (string) ( $member_meta['membership_starts_at'] ?? '' ),
        'ends_at'               => (string) ( $member_meta['membership_ends_at'] ?? '' ),
        'expires_at'            => (string) ( $member_meta['membership_expires_at'] ?? '' ),
        'renewal_type'          => (string) ( $member_meta['membership_renewal_type'] ?? '' ),
        'tier_renewal_type'     => $tier_renewal_type,
        'next_tier_form_page_id' => (int) ( $member_meta['membership_next_tier_form_page_id'] ?? 0 ) ?: null,
        'next_tier_id'          => (int) ( $member_meta['membership_next_tier_id'] ?? 0 ) ?: null,
      ];
    }, $individual_posts );

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

    if ( empty( $new_owner_uuid ) ) {
      return new \WP_REST_Response( [ 'error' => 'new_owner_uuid is required.' ], 400 );
    }

    $group           = new Membership_Group( $group_post_id );
    $current_owner_id = $group->get_owner_id();
    $new_user        = get_user_by( 'login', $new_owner_uuid );

    if ( empty( $new_user ) ) {
      $new_user_id = wicket_create_wp_user_if_not_exist( $new_owner_uuid );
      $new_user    = get_user_by( 'id', $new_user_id );
    }

    if ( empty( $new_user ) ) {
      return new \WP_REST_Response( [ 'error' => 'Could not resolve new owner user.' ], 400 );
    }

    if ( $current_owner_id && $new_user->ID === $current_owner_id ) {
      return new \WP_REST_Response( [ 'error' => 'Please select a different user.' ], 400 );
    }

    if ( false === $group->set_owner( $new_user->ID ) ) {
      return new \WP_REST_Response( [ 'error' => 'Could not update group owner.' ], 500 );
    }

    $order_id = $group->get_parent_order_id();
    if ( $order_id ) {
      $order = wc_get_order( $order_id );
      if ( ! empty( $order ) ) {
        $order->set_customer_id( $new_user->ID );
        $order->save();
        $order->add_order_note(
          "Reassigning customer to {$new_user->user_email} on group membership ownership change."
        );
      }
    }

    // Reassign the WC subscription customer.
    $subscription_id = $group->get_subscription_id();
    if ( $subscription_id && function_exists( 'wcs_get_subscription' ) ) {
      $sub = wcs_get_subscription( $subscription_id );
      if ( ! empty( $sub ) ) {
        $sub->set_customer_id( $new_user->ID );
        $sub->save();
        $sub->add_order_note(
          "Reassigning customer to {$new_user->user_email} on group membership ownership change."
        );
      }
    }

    return new \WP_REST_Response( [
      'success'  => 'Group membership ownership updated successfully.',
      'response' => Helper::get_post_meta( $group_post_id ),
    ], 200 );
  }

  // ---------------------------------------------------------------------------
  // Renewal order
  // ---------------------------------------------------------------------------

  /**
   * Create a renewal order for a group membership.
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
