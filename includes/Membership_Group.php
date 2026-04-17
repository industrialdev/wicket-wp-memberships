<?php

namespace Wicket_Memberships;

use Wicket_Memberships\Helper;
use Wicket_Memberships\Utilities;

/**
 * Represents a Membership Group CPT record
 * @package Wicket_Memberships
 */
class Membership_Group {

  public readonly int $post_id;
  public $meta_data;

  //don't create wicket connection - for testing locally
  public $bypass_wicket;

  public function __construct( $post_id ) {
    $this->bypass_wicket = ! empty( $_ENV['BYPASS_WICKET'] ) ?? false;

    // Ensure the post exists in the database before proceeding
    if ( ! get_post( $post_id ) ) {
      Wicket()->log()->error( 'Membership_Group: Invalid post ID', ['source' => 'wicket-memberships', 'post_id' => $post_id] );
      $this->post_id   = 0;
      $this->meta_data = [];
      return;
    }

    // Ensure the post is actually a membership group CPT, not some other post type
    if ( get_post_type( $post_id ) !== Helper::get_membership_group_cpt_slug() ) {
      Wicket()->log()->error( 'Membership_Group: Invalid post type', ['source' => 'wicket-memberships', 'post_id' => $post_id, 'post_type' => get_post_type( $post_id )] );
      $this->post_id   = 0;
      $this->meta_data = [];
      return;
    }

    $this->post_id   = $post_id;
    $this->meta_data = get_post_meta( $post_id );
  }

  /**
   * Create a new membership group post.
   *
   * @param array $args Post arguments (title, meta, etc.)
   * @return static|false Returns a new Membership_Group instance on success, false on failure
   * @todo Implement creation of a new membership group record
   */
  public static function create( $args = [] ) {
    // TODO: review this implementation before use in production https://app.asana.com/1/1138832104141584/project/1213403241762018/task/1213775558142167

    $post_id = wp_insert_post( [
      'post_type'   => Helper::get_membership_group_cpt_slug(),
      'post_title'  => $args['title'] ?? '',
      'post_status' => $args['status'] ?? 'publish',
    ], true );

    if ( is_wp_error( $post_id ) ) {
      Wicket()->log()->error( 'Membership_Group: Failed to create post', ['source' => 'wicket-memberships', 'error' => $post_id->get_error_message(), 'args' => $args] );
      return false;
    }

    if ( empty( $post_id ) ) {
      Wicket()->log()->error( 'Membership_Group: wp_insert_post returned empty ID', ['source' => 'wicket-memberships', 'args' => $args] );
      return false;
    }

    return new static( $post_id );
  }

  // Group membership relations.

  /**
   * Get all individual memberships that have this group set as their FK
   *
   * @return array
   */
  public function get_individual_memberships() {
    return get_posts( [
      'post_type'   => Helper::get_membership_cpt_slug(),
      'post_status' => 'any',
      'numberposts' => -1,
      'meta_query'  => [
        [
          'key'   => 'membership_group_id',
          'value' => $this->post_id,
        ],
      ],
    ] );
  }

  /**
   * Associate an individual membership post with this group.
   *
   * @param int $membership_post_id
   * @return void
   * @todo Review — sets membership_group_id meta on the individual membership to link it to this group
   */
  public function add_individual_membership( $membership_post_id ) {
    // TODO: review https://app.asana.com/1/1138832104141584/project/1213403241762018/task/1213781837058525
    update_post_meta( $membership_post_id, 'membership_group_id', $this->post_id );
  }

  // Owner management.

  /**
   * Set the single owner for this membership group.
   *
   * @param int $user_id WP user ID to set as the canonical owner
   * @return int|false Returns the saved owner ID on success, false on failure
   */
  public function set_owner( int $user_id ) {
    $user = get_user_by( 'id', $user_id );
    if ( ! $user ) {
      Wicket()->log()->error( 'Membership_Group: Invalid owner user ID', ['source' => 'wicket-memberships', 'post_id' => $this->post_id, 'user_id' => $user_id] );
      return false;
    }

    // Only the WP user ID is stored. Derived fields like display name, email, and
    // MDP UUID are intentionally omitted — they can change independently of the
    // membership record and would silently go stale. Retrieve them on demand via:
    //   $user = get_user_by( 'id', $owner_id );           // WP user object
    //   wicket_get_person_by_id( $user->user_login );      // MDP person record (UUID = user_login)
    if ( update_post_meta( $this->post_id, 'user_id', $user->ID ) === false ) {
      Wicket()->log()->error( 'Membership_Group: Failed to save owner user_id', ['source' => 'wicket-memberships', 'post_id' => $this->post_id, 'user_id' => $user_id] );
      return false;
    }

    wp_update_post( [
      'ID'          => $this->post_id,
      'post_author' => $user->ID,
    ] );

    $this->reassign_order_customer( $user->ID );
    $this->reassign_subscription_customer( $user->ID );

    $this->meta_data = get_post_meta( $this->post_id );

    return $user->ID;
  }

  /**
   * Check if a given user ID is the owner.
   *
   * @param int $user_id
   * @return bool
   */
  public function is_owner( int $user_id ): bool {
    return $this->get_owner_id() === $user_id;
  }

  /**
   * Get the canonical owner user ID.
   *
   * @return int|false
   */
  public function get_owner_id() {
    $owner_id = (int) get_post_meta( $this->post_id, 'user_id', true );
    if ( $owner_id <= 0 ) {
      return false;
    }

    return get_user_by( 'id', $owner_id ) ? $owner_id : false;
  }

  /**
   * Get the MDP UUID of the group owner.
   *
   * The UUID is not stored as post meta — it is derived from the WP user's
   * user_login, which is the MDP person UUID. This avoids persisting a value
   * that can change independently of the membership record.
   *
   * @return string|false
   */
  public function get_owner_uuid() {
    $owner_id = $this->get_owner_id();
    if ( ! $owner_id ) {
      return false;
    }
    $user = get_user_by( 'id', $owner_id );
    return ( $user && ! empty( $user->user_login ) ) ? $user->user_login : false;
  }

  // Organization.

  /**
   * Set the organization for this membership group.
   * Stores org_uuid and org_name (from organization_legal_name) as post meta.
   *
   * @param string $org_uuid The UUID of the MDP organization
   * @return array|false Returns the organization data on success, false on failure
   */
  public function set_organization( string $org_uuid ) {
    if ( ! isValidUuid( $org_uuid ) ) {
      Wicket()->log()->error( 'Membership_Group: Invalid org UUID', ['source' => 'wicket-memberships', 'post_id' => $this->post_id, 'org_uuid' => $org_uuid] );
      return false;
    }

    $org_data = Helper::get_org_data( $org_uuid );

    if ( empty( $org_data['name'] ) ) {
      Wicket()->log()->error( 'Membership_Group: Could not retrieve organization data', ['source' => 'wicket-memberships', 'post_id' => $this->post_id, 'org_uuid' => $org_uuid] );
      return false;
    }

    $uuid_result = update_post_meta( $this->post_id, 'org_uuid', $org_uuid );
    $name_result = update_post_meta( $this->post_id, 'org_name', $org_data['name'] );

    if ( $uuid_result === false || $name_result === false ) {
      Wicket()->log()->error( 'Membership_Group: Failed to save organization meta', ['source' => 'wicket-memberships', 'post_id' => $this->post_id, 'org_uuid' => $org_uuid] );
      return false;
    }

    return $org_data;
  }

  /**
   * Get the org UUID stored on this membership group.
   *
   * @return string|false The org UUID, or false if not set
   */
  public function get_org_uuid() {
    $org_uuid = get_post_meta( $this->post_id, 'org_uuid', true );
    return ! empty( $org_uuid ) ? $org_uuid : false;
  }

  public function get_organization() {
    $org_uuid = $this->get_org_uuid();

    if ( ! $org_uuid || ! isValidUuid( $org_uuid ) ) {
      return false;
    }

    return Helper::get_org_data( $org_uuid );
  }

  // Configuration and commerce links.

  /**
   * Get the group config object linked to this group.
   *
   * @return Membership_Group_Config|false
   */
  public function get_config() {
    $config_id = (int) get_post_meta( $this->post_id, 'membership_group_config_id', true );
    if ( $config_id <= 0 ) {
      return false;
    }

    $config = new Membership_Group_Config( $config_id );
    return $config->get_post_id() > 0 ? $config : false;
  }

  /**
   * Get the linked parent order ID.
   *
   * @return int|false
   */
  public function get_parent_order_id() {
    $order_id = (int) get_post_meta( $this->post_id, 'membership_parent_order_id', true );
    return $order_id > 0 ? $order_id : false;
  }

  /**
   * Get the linked subscription ID.
   *
   * @return int|false
   */
  public function get_subscription_id() {
    $subscription_id = (int) get_post_meta( $this->post_id, 'membership_subscription_id', true );
    return $subscription_id > 0 ? $subscription_id : false;
  }

  // Status and dates.

  /**
   * Get the membership status for this group.
   *
   * @return string|false The status string, or false if not set
   */
  public function get_membership_status() {
    $status = get_post_meta( $this->post_id, 'membership_status', true );
    return ! empty( $status ) ? $status : false;
  }

  /**
   * Return allowed admin status transitions for this group.
   *
   * @return array<string, array{name: string, slug: string}>
   */
  public function get_allowed_status_transitions(): array {
    if ( ! empty( $_ENV['BYPASS_STATUS_CHANGE_LOCKOUT'] ) ) {
      return Helper::get_all_status_names();
    }

    $current_status = $this->get_membership_status();
    if ( ! $current_status ) {
      return [];
    }

    $all_statuses  = Helper::get_all_status_names();
    $allowed_slugs = [];

    if ( $current_status === Wicket_Memberships::STATUS_PENDING ) {
      $allowed_slugs = [
        Wicket_Memberships::STATUS_ACTIVE,
        Wicket_Memberships::STATUS_CANCELLED,
      ];
    } elseif ( $current_status === Wicket_Memberships::STATUS_DELAYED ) {
      $allowed_slugs = [
        Wicket_Memberships::STATUS_CANCELLED,
      ];
    } elseif ( $current_status === Wicket_Memberships::STATUS_GRACE ) {
      $allowed_slugs = [
        Wicket_Memberships::STATUS_CANCELLED,
        Wicket_Memberships::STATUS_EXPIRED,
      ];
    } elseif ( $current_status === Wicket_Memberships::STATUS_ACTIVE ) {
      $allowed_slugs = [
        Wicket_Memberships::STATUS_CANCELLED,
        Wicket_Memberships::STATUS_EXPIRED,
      ];
    }

    $transitions = [];
    foreach ( $allowed_slugs as $slug ) {
      if ( isset( $all_statuses[ $slug ] ) ) {
        $transitions[ $slug ] = $all_statuses[ $slug ];
      }
    }

    return $transitions;
  }

  /**
   * Check whether the group can transition to the requested status.
   *
   * @param string $new_status
   * @return bool
   */
  public function can_transition_to( string $new_status ): bool {
    if ( ! empty( $_ENV['BYPASS_STATUS_CHANGE_LOCKOUT'] ) ) {
      return true;
    }

    return isset( $this->get_allowed_status_transitions()[ $new_status ] );
  }

  /**
   * Execute a group status transition and its side effects.
   *
   * @param string $new_status
   * @return array{success_message: string, bypassed: bool}|false
   */
  public function transition_to( string $new_status ) {
    if ( ! empty( $_ENV['BYPASS_STATUS_CHANGE_LOCKOUT'] ) ) {
      if ( ! $this->set_membership_status( $new_status ) ) {
        return false;
      }

      return [
        'success_message' => 'BYPASSED STATUS LOCKOUT — status set to ' . $new_status,
        'bypassed'        => true,
      ];
    }

    if ( ! $this->can_transition_to( $new_status ) ) {
      return false;
    }

    $transition_plan = $this->plan_status_transition( $new_status );
    if ( false === $transition_plan ) {
      return false;
    }

    if ( ! empty( $transition_plan['activate_subscription'] ) ) {
      $this->activate_subscription_for_dates(
        $transition_plan['transition_dates']['starts_at'],
        $transition_plan['transition_dates']['ends_at'],
        $transition_plan['transition_dates']['expires_at']
      );
    }

    if ( ! $this->apply_status_transition( $new_status, $transition_plan['transition_dates'] ) ) {
      return false;
    }

    $this->cascade_status_to_members( $new_status );

    return [
      'success_message' => $transition_plan['success_message'],
      'bypassed'        => false,
    ];
  }

  /**
   * Set the membership status for this group.
   *
   * This is intentionally kept public as a low-level developer escape hatch.
   * Normal application flows should use transition_to() so lifecycle rules,
   * date planning, and side effects are applied consistently.
   *
   * @param string $status One of the slugs returned by Helper::get_all_status_names()
   * @return bool True on success, false if the status is invalid or the update fails
   */
  public function set_membership_status( string $status ): bool {
    if ( ! in_array( $status, array_keys( Helper::get_all_status_names() ), true ) ) {
      Wicket()->log()->error( 'Membership_Group: Invalid membership status', ['source' => 'wicket-memberships', 'post_id' => $this->post_id, 'status' => $status] );
      return false;
    }

    $result = update_post_meta( $this->post_id, 'membership_status', $status );

    if ( $result === false ) {
      Wicket()->log()->error( 'Membership_Group: Failed to save membership status', ['source' => 'wicket-memberships', 'post_id' => $this->post_id, 'status' => $status] );
      return false;
    }

    return true;
  }

  /**
   * Persist a status transition using explicit date fields.
   *
   * @param string $new_status
   * @param array<string, string|null> $dates
   * @return bool
   */
  private function apply_status_transition( string $new_status, array $dates ): bool {
    if ( ! $this->set_membership_status( $new_status ) ) {
      return false;
    }

    $field_map = [
      'starts_at'      => 'membership_starts_at',
      'ends_at'        => 'membership_ends_at',
      'expires_at'     => 'membership_expires_at',
      'early_renew_at' => 'membership_early_renew_at',
    ];

    foreach ( $field_map as $date_key => $meta_key ) {
      if ( ! array_key_exists( $date_key, $dates ) || null === $dates[ $date_key ] ) {
        continue;
      }

      if ( update_post_meta( $this->post_id, $meta_key, $dates[ $date_key ] ) === false ) {
        Wicket()->log()->error( 'Membership_Group: Failed to persist transition field', [
          'source'   => 'wicket-memberships',
          'post_id'  => $this->post_id,
          'meta_key' => $meta_key,
        ] );
        return false;
      }
    }

    $this->meta_data = get_post_meta( $this->post_id );

    return true;
  }

  /**
   * Cascade a status update to non-final child memberships.
   *
   * @param string $new_status
   * @return void
   */
  private function cascade_status_to_members( string $new_status ): void {
    // TODO: Re-enable child membership status cascading once the intended
    // group/member lifecycle rules are finalized.
  }

  /**
   * Cascade normalized edit fields to eligible child memberships.
   *
   * @param array<string, mixed> $normalized_fields
   * @return void
   */
  public function cascade_dates_to_members( array $normalized_fields ): void {
    // TODO: Re-enable child membership date cascading once the intended
    // group/member edit propagation rules are finalized.
  }

  /**
   * Apply normalized group edit fields to this group.
   *
   * TODO: Review and consider replacing with typed getters/setters per field. The current
   * array<string, mixed> signature is wide open — any meta key can be written without
   * validation. Given that we have strict meta field requirements, per-field setters with
   * type enforcement would be safer.
   *
   * @param array<string, mixed> $normalized_fields
   * @return bool
   */
  public function apply_edit_fields( array $normalized_fields ): bool {
    foreach ( $normalized_fields as $key => $value ) {
      if ( update_post_meta( $this->post_id, $key, $value ) === false ) {
        Wicket()->log()->error( 'Membership_Group: Failed to persist edit field', [
          'source'   => 'wicket-memberships',
          'post_id'  => $this->post_id,
          'meta_key' => $key,
        ] );
        return false;
      }
    }

    $this->meta_data = get_post_meta( $this->post_id );

    return true;
  }

  /**
   * Activate the linked WC subscription for the supplied UTC date range.
   *
   * @param string $starts_at_utc
   * @param string $ends_at_utc
   * @param string $expires_at_utc
   * @return void
   */
  private function activate_subscription_for_dates( string $starts_at_utc, string $ends_at_utc, string $expires_at_utc ): void {
    $subscription_id = $this->get_subscription_id();
    if ( ! $subscription_id || ! function_exists( 'wcs_get_subscription' ) ) {
      return;
    }

    $subscription = wcs_get_subscription( $subscription_id );
    if ( empty( $subscription ) ) {
      return;
    }

    $subscription->update_status( 'active' );

    $subscription_dates = [
      'start_date'   => Utilities::get_mdp_day_start( $starts_at_utc )->format( 'Y-m-d H:i:s' ),
      'next_payment' => Utilities::get_mdp_day_end( $ends_at_utc )->format( 'Y-m-d H:i:s' ),
      'end'          => Utilities::get_mdp_day_end( $expires_at_utc )->format( 'Y-m-d H:i:s' ),
    ];

    $subscription->update_dates( $subscription_dates );
    $subscription->save();
  }

  /**
   * Get the stored membership date fields for this group.
   *
   * @return array<string, string>
   */
  public function get_dates(): array {
    return [
      'starts_at'      => (string) get_post_meta( $this->post_id, 'membership_starts_at', true ),
      'ends_at'        => (string) get_post_meta( $this->post_id, 'membership_ends_at', true ),
      'expires_at'     => (string) get_post_meta( $this->post_id, 'membership_expires_at', true ),
      'early_renew_at' => (string) get_post_meta( $this->post_id, 'membership_early_renew_at', true ),
    ];
  }

  /**
   * Reassign the linked WC order customer after an ownership change.
   *
   * @param int $user_id
   * @return void
   */
  private function reassign_order_customer( int $user_id ): void {
    $order_id = $this->get_parent_order_id();
    if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
      return;
    }

    $order = wc_get_order( $order_id );
    $user  = get_user_by( 'id', $user_id );
    if ( empty( $order ) || ! $user ) {
      return;
    }

    $order->set_customer_id( $user_id );
    $order->save();
    $order->add_order_note(
      "Reassigning customer to {$user->user_email} on group membership ownership change."
    );
  }

  /**
   * Reassign the linked WC subscription customer after an ownership change.
   *
   * @param int $user_id
   * @return void
   */
  private function reassign_subscription_customer( int $user_id ): void {
    $subscription_id = $this->get_subscription_id();
    if ( ! $subscription_id || ! function_exists( 'wcs_get_subscription' ) ) {
      return;
    }

    $subscription = wcs_get_subscription( $subscription_id );
    $user         = get_user_by( 'id', $user_id );
    if ( empty( $subscription ) || ! $user ) {
      return;
    }

    $subscription->set_customer_id( $user_id );
    $subscription->save();
    $subscription->add_order_note(
      "Reassigning customer to {$user->user_email} on group membership ownership change."
    );
  }

  /**
   * Build the transition dates and side effects for a requested status change.
   *
   * @param string $new_status
   * @return array{transition_dates: array<string, string|null>, success_message: string, activate_subscription: bool}|false
   */
  private function plan_status_transition( string $new_status ) {
    $current_status = $this->get_membership_status();

    if ( $current_status === Wicket_Memberships::STATUS_PENDING && $new_status === Wicket_Memberships::STATUS_ACTIVE ) {
      $config = $this->get_config();
      $dates  = $config ? $config->get_membership_dates() : [];

      return [
        'transition_dates' => [
          'starts_at'      => $dates['start_date'] ?? Utilities::get_mdp_day_start( 'now' )->format( 'c' ),
          'ends_at'        => $dates['end_date'] ?? Utilities::get_mdp_day_end( 'now' )->format( 'c' ),
          'expires_at'     => $dates['expires_at'] ?? ( $dates['end_date'] ?? Utilities::get_mdp_day_end( 'now' )->format( 'c' ) ),
          'early_renew_at' => $dates['early_renew_at'] ?? ( $dates['end_date'] ?? Utilities::get_mdp_day_end( 'now' )->format( 'c' ) ),
        ],
        'success_message'      => 'Pending group membership activated successfully.',
        'activate_subscription' => true,
      ];
    }

    if ( $new_status === Wicket_Memberships::STATUS_CANCELLED ) {
      if ( in_array( $current_status, [ Wicket_Memberships::STATUS_PENDING, Wicket_Memberships::STATUS_DELAYED ], true ) ) {
        return [
          'transition_dates' => [
            'starts_at'      => Utilities::get_mdp_day_start( '-1 day' )->format( 'c' ),
            'ends_at'        => Utilities::get_mdp_day_end( 'now' )->format( 'c' ),
            'expires_at'     => Utilities::get_mdp_day_end( 'now' )->format( 'c' ),
            'early_renew_at' => null,
          ],
          'success_message'      => 'Group membership cancelled successfully.',
          'activate_subscription' => false,
        ];
      }

      if ( $current_status === Wicket_Memberships::STATUS_GRACE ) {
        $current_end = get_post_meta( $this->post_id, 'membership_ends_at', true );

        return [
          'transition_dates' => [
            'starts_at'      => null,
            'ends_at'        => $current_end !== '' ? $current_end : null,
            'expires_at'     => Utilities::get_mdp_day_end( 'now' )->format( 'c' ),
            'early_renew_at' => null,
          ],
          'success_message'      => 'Group membership cancelled successfully.',
          'activate_subscription' => false,
        ];
      }

      return [
        'transition_dates' => [
          'starts_at'      => null,
          'ends_at'        => Utilities::get_mdp_day_end( '+1 day' )->format( 'c' ),
          'expires_at'     => Utilities::get_mdp_day_end( '+1 day' )->format( 'c' ),
          'early_renew_at' => null,
        ],
        'success_message'      => 'Group membership cancelled successfully.',
        'activate_subscription' => false,
      ];
    }

    if ( $new_status === Wicket_Memberships::STATUS_EXPIRED ) {
      return [
        'transition_dates' => [
          'starts_at'      => null,
          'ends_at'        => Utilities::get_mdp_day_end( '+1 day' )->format( 'c' ),
          'expires_at'     => Utilities::get_mdp_day_end( '+1 day' )->format( 'c' ),
          'early_renew_at' => null,
        ],
        'success_message'      => 'Group membership marked as expired.',
        'activate_subscription' => false,
      ];
    }

    return false;
  }

}
