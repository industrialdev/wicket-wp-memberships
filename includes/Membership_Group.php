<?php

namespace Wicket_Memberships;

use Wicket_Memberships\Helper;

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
   * @param ?int $user_id WP user ID to set as the canonical owner, or null to clear it
   * @return int|false Returns the saved owner ID on success, false on failure
   */
  public function set_owner( ?int $user_id ) {
    if ( null === $user_id ) {
      return $this->clear_owner();
    }

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

    $this->meta_data = get_post_meta( $this->post_id );

    return $user->ID;
  }

  /**
   * Clear the canonical owner fields for this group.
   *
   * @return int
   */
  private function clear_owner(): int {
    delete_post_meta( $this->post_id, 'user_id' );

    wp_update_post( [
      'ID'          => $this->post_id,
      'post_author' => 0,
    ] );

    $this->meta_data = get_post_meta( $this->post_id );

    return 0;
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
   * Pass null to remove the organization from this group.
   *
   * @param string|null $org_uuid The UUID of the MDP organization, or null to remove
   * @return array|true|false Returns the organization data on success, true when cleared, false on failure
   */
  public function set_organization( ?string $org_uuid ) {
    if ( $org_uuid === null ) {
      delete_post_meta( $this->post_id, 'org_uuid' );
      delete_post_meta( $this->post_id, 'org_name' );
      return true;
    }

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
   * Set the membership status for this group.
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

}
