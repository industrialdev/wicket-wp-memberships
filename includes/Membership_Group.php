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
    $this->bypass_wicket = !empty( $_ENV['BYPASS_WICKET'] ) ?? false;

    // Ensure the post exists in the database before proceeding
    if ( ! get_post( $post_id ) ) {
      Wicket()->log()->error( 'Membership_Group: Invalid post ID', [
        'source'  => 'wicket-memberships',
        'post_id' => $post_id,
      ] );
      $this->post_id   = 0;
      $this->meta_data = [];
      return;
    }

    // Ensure the post is actually a membership group CPT, not some other post type
    if ( get_post_type( $post_id ) !== Helper::get_membership_group_cpt_slug() ) {
      Wicket()->log()->error( 'Membership_Group: Invalid post type', [
        'source'    => 'wicket-memberships',
        'post_id'   => $post_id,
        'post_type' => get_post_type( $post_id ),
      ] );
      $this->post_id   = 0;
      $this->meta_data = [];
      return;
    }

    $this->post_id    = $post_id;
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
      Wicket()->log()->error( 'Membership_Group: Failed to create post', [
        'source' => 'wicket-memberships',
        'error'  => $post_id->get_error_message(),
        'args'   => $args,
      ] );
      return false;
    }

    if ( empty( $post_id ) ) {
      Wicket()->log()->error( 'Membership_Group: wp_insert_post returned empty ID', [
        'source' => 'wicket-memberships',
        'args'   => $args,
      ] );
      return false;
    }

    return new static( $post_id );
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

  /**
   * Set the group owners for this membership group.
   *
   * @param array $user_ids Array of WP user IDs to set as group owners
   * @return int[]|false Returns the saved owner IDs on success, false on failure
   */
  public function set_group_owners( array $user_ids ) {
    $owners = array_values( array_map( 'intval', $user_ids ) );
    if ( update_post_meta( $this->post_id, 'group_owner_ids', $owners ) === false ) {
      return false;
    }
    return $this->get_group_owners();
  }

  /**
   * Check if a given user ID is a group owner.
   *
   * @param int $user_id
   * @return bool
   */
  public function is_group_owner( int $user_id ): bool {
    return in_array( $user_id, $this->get_group_owners(), true );
  }

  /**
   * Get the array of group owner user IDs.
   *
   * @return int[]
   */
  public function get_group_owners(): array {
    $owners = get_post_meta( $this->post_id, 'group_owner_ids', true );
    return is_array( $owners ) ? $owners : [];
  }

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
      Wicket()->log()->error( 'Membership_Group: Invalid org UUID', [
        'source'   => 'wicket-memberships',
        'post_id'  => $this->post_id,
        'org_uuid' => $org_uuid,
      ] );
      return false;
    }

    $org_data = Helper::get_org_data( $org_uuid );

    if ( empty( $org_data['name'] ) ) {
      Wicket()->log()->error( 'Membership_Group: Could not retrieve organization data', [
        'source'   => 'wicket-memberships',
        'post_id'  => $this->post_id,
        'org_uuid' => $org_uuid,
      ] );
      return false;
    }

    $uuid_result = update_post_meta( $this->post_id, 'org_uuid', $org_uuid );
    $name_result = update_post_meta( $this->post_id, 'org_name', $org_data['name'] );

    if ( $uuid_result === false || $name_result === false ) {
      Wicket()->log()->error( 'Membership_Group: Failed to save organization meta', [
        'source'   => 'wicket-memberships',
        'post_id'  => $this->post_id,
        'org_uuid' => $org_uuid,
      ] );
      return false;
    }

    return $org_data;
  }

  /**
   * Get the organization linked to this membership group.
   * Eventually will call wicket_get_organization() to fetch from the MDP.
   *
   * @return string|false Returns the org UUID string, or false if not set
   */
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

}
