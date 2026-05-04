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
   * Create a new membership group post with all required data.
   *
   * Returns null and logs the reason on any failure; any partially-created post is deleted
   * before returning.
   *
   * @param string $name                       Post title for the group.
   * @param int    $membership_group_config_id Post ID of the linked Membership_Group_Config.
   * @param string $org_uuid                   MDP organisation UUID.
   * @param string $owner_uuid                 MDP person UUID of the group owner.
   * @param string $start_date                 ISO 8601 start date for the membership period.
   * @return static|null New Membership_Group instance on success, null on failure.
   */
  public static function create(
    string $name,
    int $membership_group_config_id,
    string $org_uuid,
    string $owner_uuid,
    string $start_date
  ): ?static {
    // Validate all inputs before touching the database.
    if ( empty( $name ) ) {
      Wicket()->log()->error( 'Membership_Group::create: name is empty', ['source' => 'wicket-memberships'] );
      throw new \RuntimeException( 'name is empty.' );
    }

    // Confirm the config post exists and is the correct CPT type.
    $config_check = new Membership_Group_Config( $membership_group_config_id );
    if ( $config_check->get_post_id() <= 0 ) {
      Wicket()->log()->error( 'Membership_Group::create: membership_group_config_id does not resolve to a valid config', ['source' => 'wicket-memberships', 'config_post_id' => $membership_group_config_id] );
      throw new \RuntimeException( 'membership_group_config_id does not resolve to a valid config.' );
    }

    // Confirm the org UUID is a well-formed UUID before attempting an MDP lookup later.
    if ( ! isValidUuid( $org_uuid ) ) {
      Wicket()->log()->error( 'Membership_Group::create: invalid org_uuid', ['source' => 'wicket-memberships', 'org_uuid' => $org_uuid] );
      throw new \RuntimeException( 'org_uuid is not a valid UUID.' );
    }

    // Confirm the owner UUID is well-formed; set_owner() will resolve/create the WP user.
    if ( ! isValidUuid( $owner_uuid ) ) {
      Wicket()->log()->error( 'Membership_Group::create: invalid owner_uuid', ['source' => 'wicket-memberships', 'owner_uuid' => $owner_uuid] );
      throw new \RuntimeException( 'owner_uuid is not a valid UUID.' );
    }

    // Validate start_date is parseable and normalize to UTC ISO 8601.
    if ( empty( $start_date ) ) {
      Wicket()->log()->error( 'Membership_Group::create: start_date is empty', ['source' => 'wicket-memberships'] );
      throw new \RuntimeException( 'start_date is empty.' );
    }
    try {
      $start_dt = new \DateTime( $start_date );
      $start_dt->setTimezone( new \DateTimeZone( 'UTC' ) );
      $start_date = $start_dt->format( 'Y-m-d\TH:i:s\Z' );
    } catch ( \Exception $e ) {
      Wicket()->log()->error( 'Membership_Group::create: start_date could not be parsed', ['source' => 'wicket-memberships', 'start_date' => $start_date] );
      throw new \RuntimeException( 'start_date is not a valid date string.' );
    }

    // Insert the CPT post shell. All meta is written via setters below.
    $post_id = wp_insert_post( [
      'post_type'   => Helper::get_membership_group_cpt_slug(),
      'post_title'  => $name,
      'post_status' => 'publish',
    ], true );

    if ( is_wp_error( $post_id ) ) {
      Wicket()->log()->error( 'Membership_Group::create: wp_insert_post failed', ['source' => 'wicket-memberships', 'error' => $post_id->get_error_message()] );
      return null;
    }

    if ( empty( $post_id ) ) {
      Wicket()->log()->error( 'Membership_Group::create: wp_insert_post returned empty ID', ['source' => 'wicket-memberships'] );
      return null;
    }

    // Wrap the new post in a Membership_Group instance so we can use its setters.
    $group = new static( $post_id );

    // Write status, owner, org, and config meta. Any failure rolls back the post entirely
    // so we never leave a partially-populated group in the database.
    // Status starts as pending; approval check below may leave it there intentionally.
    if ( ! $group->set_membership_status( Wicket_Memberships::STATUS_PENDING )
      || ! $group->set_owner( $owner_uuid )
      || ! $group->set_organization( $org_uuid )
      || ! $group->set_config( $membership_group_config_id )
    ) {
      Wicket()->log()->error( 'Membership_Group::create: failed to set group data, rolling back post', ['source' => 'wicket-memberships', 'post_id' => $post_id] );
      wp_delete_post( $post_id, true );
      return null;
    }

    // Derive the membership date range from the config, anchored to the supplied start date.
    // expires_at and early_renew_at are only present when grace period / renewal window are configured.
    $config = $group->get_config();
    $dates  = $config->get_membership_dates( [ 'start_date' => $start_date ] );

    if ( ! $group->set_dates( [
      'starts_at'      => $dates['start_date'],
      'ends_at'        => $dates['end_date'],
      'expires_at'     => $dates['expires_at'] ?? null,
      'early_renew_at' => $dates['early_renew_at'] ?? null,
    ] ) ) {
      Wicket()->log()->error( 'Membership_Group::create: failed to set dates, rolling back post', ['source' => 'wicket-memberships', 'post_id' => $post_id] );
      wp_delete_post( $post_id, true );
      return null;
    }

    // Create a pending WC subscription linked to this group so billing and
    // renewal logic have a subscription record to work against from day one.
    $subscription_id = $group->create_group_subscription();
    if ( $subscription_id ) {
      update_post_meta( $post_id, 'membership_subscription_id', $subscription_id );
    } else {
      Wicket()->log()->error(
        'Membership_Group::create: could not create WC subscription — group created without one',
        [ 'source' => 'wicket-memberships', 'post_id' => $post_id ]
      );
      // Non-fatal: the group record is still usable without a subscription.
      // Consistent with individual membership precedent where subscription
      // creation is decoupled from record creation entirely.
    }

    // If the config requires approval, the group stays in STATUS_PENDING (set above).
    // TODO: Implement the full group approval workflow — send approval email, link admin
    //       to the org edit page, handle pending→active transition, show callout in member
    //       portal while pending. Mirror the individual/org tier approval in
    //       Membership_Controller::create_membership_record() lines 764–781 and
    //       Admin_Controller::admin_manage_status(). Also determine whether group-level
    //       approval should block individual memberships from being added until approved.
    if ( $config->is_approval_required() ) {
      Wicket()->log()->info( 'Membership_Group::create: approval required — group remains pending', [
        'source'  => 'wicket-memberships',
        'post_id' => $post_id,
      ] );
    }

    // Reload meta so the returned instance reflects everything written above.
    $group->meta_data = get_post_meta( $post_id );

    return $group;
  }

  // Membership group relations.

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
   * Add an individual membership to this group.
   *
   * Single entry point for both the "new member" and "existing member" flows:
   *
   * - New member:      pass $user_id; omit $existing_membership_post_id.
   * - Existing member: pass $existing_membership_post_id; the existing membership is
   *                    cancelled first and its user_id is carried forward automatically.
   *                    $user_id is ignored when $existing_membership_post_id is provided.
   *
   * Group must be in pending, active, or delayed status. The new membership's start
   * date is calculated from today relative to the group date window (see
   * resolve_member_start_date()).
   *
   * @param int|null $user_id                     WP user ID. Required when $existing_membership_post_id is null.
   * @param int      $tier_post_id                Post ID of the individual Membership_Tier CPT.
   * @param int|null $product_id                  WC parent product ID. Auto-resolved from tier when null (fails if tier has >1 product).
   * @param int|null $variation_id                WC variation ID. When provided, stored as membership_product_id instead of parent product_id, matching the subscription-driven membership flow.
   * @param int|null $existing_membership_post_id Existing wicket_membership post ID to cancel before creating the new record.
   * @return int|\WP_Error New membership post ID on success, WP_Error on failure.
   *
   * TODO: Link membership_subscription_id and membership_parent_order_id to the
   *       group's WooCommerce subscription once group subscription management exists.
   *       Also add a tier line item to the group subscription on each add.
   */
  public function add_member(
    ?int $user_id,
    int $tier_post_id,
    ?int $product_id = null,
    ?int $variation_id = null,
    ?int $existing_membership_post_id = null
  ): int|\WP_Error {
    if ( $err = $this->assert_group_is_manageable() ) {
      return $err;
    }

    // Existing-member path: cancel old membership, resolve user_id from it.
    if ( $existing_membership_post_id !== null ) {
      // Only check post existence here — group ownership is not required for add_member's
      // existing path (the membership may be standalone, not yet in any group).
      if ( ! get_post( $existing_membership_post_id ) || get_post_type( $existing_membership_post_id ) !== Helper::get_membership_cpt_slug() ) {
        Wicket()->log()->error( 'Membership_Group::add_member: existing_membership_post_id does not resolve to a valid membership', [
          'source'  => 'wicket-memberships',
          'post_id' => $this->post_id,
          'target'  => $existing_membership_post_id,
        ] );
        return new \WP_Error( 'invalid_membership', __( 'The specified membership record does not exist.', 'wicket-memberships' ) );
      }

      $resolved_user_id = (int) get_post_meta( $existing_membership_post_id, 'user_id', true );
      if ( $resolved_user_id <= 0 ) {
        Wicket()->log()->error( 'Membership_Group::add_member: existing membership has no user_id meta', [
          'source'             => 'wicket-memberships',
          'post_id'            => $this->post_id,
          'membership_post_id' => $existing_membership_post_id,
        ] );
        return new \WP_Error( 'missing_user_id', __( 'The existing membership record does not have a valid user_id.', 'wicket-memberships' ) );
      }

      $this->cancel_individual_membership( $existing_membership_post_id );
      $user_id = $resolved_user_id;
    }

    // New-member path: user_id must be supplied.
    if ( $user_id === null ) {
      return new \WP_Error( 'missing_user_id', __( 'user_id is required when not providing an existing membership.', 'wicket-memberships' ) );
    }

    $start_date = $this->resolve_member_start_date();
    if ( is_wp_error( $start_date ) ) {
      return $start_date;
    }

    return $this->create_individual_membership_for_group( $user_id, $tier_post_id, $product_id, $variation_id, $start_date, $this->post_id );
  }

  /**
   * Move an individual membership from this group to a target group.
   *
   * Cancels the source individual membership and creates a new one linked to the
   * target group, inheriting the same user, tier, and product. Start date is
   * resolved against the target group's date window.
   *
   * If creation of the new membership fails after cancellation, a WP_Error is returned
   * with an explicit message. No rollback is attempted — the admin must manually
   * re-add the member if this occurs.
   *
   * @param int              $membership_post_id Post ID of the individual membership to move.
   * @param Membership_Group $target_group       The group to move the member into.
   * @return int|\WP_Error New membership post ID on success, WP_Error on failure.
   */
  public function move_individual_membership( int $membership_post_id, Membership_Group $target_group ): int|\WP_Error {
    if ( $err = $this->assert_group_is_manageable() ) {
      return $err;
    }

    if ( $err = $target_group->assert_group_is_manageable() ) {
      return $err;
    }

    if ( $err = $this->assert_individual_membership_in_group( $membership_post_id ) ) {
      return $err;
    }

    $meta = $this->extract_individual_membership_meta( $membership_post_id );
    if ( is_wp_error( $meta ) ) {
      return $meta;
    }

    $start_date = $target_group->resolve_member_start_date();
    if ( is_wp_error( $start_date ) ) {
      return $start_date;
    }

    $this->cancel_individual_membership( $membership_post_id );

    $result = $target_group->create_individual_membership_for_group(
      $meta['user_id'],
      $meta['tier_post_id'],
      $meta['product_id'],
      $meta['variation_id'],
      $start_date,
      $target_group->post_id
    );

    if ( is_wp_error( $result ) ) {
      Wicket()->log()->error( 'Membership_Group::move_individual_membership: new membership creation failed after cancellation', [
        'source'              => 'wicket-memberships',
        'source_group_id'     => $this->post_id,
        'target_group_id'     => $target_group->post_id,
        'old_membership_post' => $membership_post_id,
        'error'               => $result->get_error_message(),
      ] );
      return new \WP_Error(
        $result->get_error_code(),
        __( 'The source membership was cancelled but the new membership could not be created. Please manually re-add the member to the target group.', 'wicket-memberships' )
      );
    }

    return $result;
  }

  // ---------------------------------------------------------------------------
  // Private helpers shared by add_member, remove_member, move_individual_membership
  // ---------------------------------------------------------------------------

  /**
   * Assert this group is in a status that allows member operations (pending/active/delayed).
   *
   * @return \WP_Error|null WP_Error on failure, null on pass.
   */
  private function assert_group_is_manageable(): ?\WP_Error {
    $allowed = [ Wicket_Memberships::STATUS_PENDING, Wicket_Memberships::STATUS_ACTIVE, Wicket_Memberships::STATUS_DELAYED ];
    if ( ! \in_array( $this->get_membership_status(), $allowed, true ) ) {
      Wicket()->log()->error( 'Membership_Group::assert_group_is_manageable: group status does not allow member operations', [
        'source'  => 'wicket-memberships',
        'post_id' => $this->post_id,
        'status'  => $this->get_membership_status(),
      ] );
      return new \WP_Error( 'invalid_group_status', __( 'Member operations are only allowed on a pending, active, or delayed membership group.', 'wicket-memberships' ) );
    }
    return null;
  }

  /**
   * Assert an individual membership post exists, is the correct CPT, and belongs to this group.
   *
   * @param int $membership_post_id
   * @return \WP_Error|null WP_Error on failure, null on pass.
   */
  private function assert_individual_membership_in_group( int $membership_post_id ): ?\WP_Error {
    if ( ! get_post( $membership_post_id ) || get_post_type( $membership_post_id ) !== Helper::get_membership_cpt_slug() ) {
      return new \WP_Error( 'invalid_membership', __( 'The specified membership record does not exist.', 'wicket-memberships' ) );
    }

    $linked_group_id = (int) get_post_meta( $membership_post_id, 'membership_group_id', true );
    if ( $linked_group_id !== $this->post_id ) {
      return new \WP_Error( 'membership_not_in_group', __( 'The specified membership does not belong to this group.', 'wicket-memberships' ) );
    }

    return null;
  }

  /**
   * Read user, tier, and product meta from an individual membership post.
   *
   * Resolves whether the stored product ID is a WC variation or a parent product.
   *
   * @param int $membership_post_id
   * @return array{user_id: int, tier_post_id: int, product_id: int|null, variation_id: int|null}|\WP_Error
   */
  private function extract_individual_membership_meta( int $membership_post_id ): array|\WP_Error {
    $user_id      = (int) get_post_meta( $membership_post_id, 'user_id', true );
    $tier_post_id = (int) get_post_meta( $membership_post_id, 'membership_tier_post_id', true );
    $product_id   = (int) get_post_meta( $membership_post_id, 'membership_product_id', true );

    $user = get_user_by( 'id', $user_id );
    if ( ! $user ) {
      return new \WP_Error( 'invalid_user', __( 'Could not resolve the user for this membership.', 'wicket-memberships' ) );
    }

    // Determine whether stored product_id is a variation or a parent product.
    $variation_id = null;
    if ( $product_id > 0 ) {
      $wc_product = wc_get_product( $product_id );
      if ( $wc_product instanceof \WC_Product_Variation ) {
        $variation_id = $product_id;
        $product_id   = (int) $wc_product->get_parent_id();
      }
    }

    return [
      'user_id'      => $user_id,
      'tier_post_id' => $tier_post_id,
      'product_id'   => $product_id > 0 ? $product_id : null,
      'variation_id' => $variation_id,
    ];
  }

  /**
   * Cancel an individual membership by setting its status to cancelled.
   *
   * Uses Membership_Controller::update_membership_status() directly — avoids
   * Admin_Controller::admin_manage_status() which depends on WC order JSON that
   * group-path memberships never have.
   *
   * @param int $membership_post_id
   */
  private function cancel_individual_membership( int $membership_post_id ): void {
    $mc = new Membership_Controller();
    $mc->update_membership_status( $membership_post_id, Wicket_Memberships::STATUS_CANCELLED );
  }

  /**
   * Resolve the start date for a new member being added to this group.
   *
   * Rules per CURRENT_SCOPE.md:
   * - today is within group start→end  → today (UTC)
   * - today is before group start      → group start date
   * - today is after group end         → WP_Error (cannot add members after group ends)
   *
   * @return string|\WP_Error ISO 8601 UTC start date string on success, WP_Error on failure.
   */
  private function resolve_member_start_date(): string|\WP_Error {
    $dates = $this->get_dates();
    if ( empty( $dates['starts_at'] ) || empty( $dates['ends_at'] ) ) {
      Wicket()->log()->error( 'Membership_Group::resolve_member_start_date: group has no dates set', [
        'source'  => 'wicket-memberships',
        'post_id' => $this->post_id,
      ] );
      return new \WP_Error( 'group_no_dates', __( 'The membership group does not have dates configured.', 'wicket-memberships' ) );
    }

    $now       = new \DateTime( 'now', new \DateTimeZone( 'UTC' ) );
    $group_end = new \DateTime( $dates['ends_at'], new \DateTimeZone( 'UTC' ) );

    // Reject if today is past the group end date — no new members after the window closes.
    if ( $now > $group_end ) {
      Wicket()->log()->error( 'Membership_Group::resolve_member_start_date: today is after group end date', [
        'source'   => 'wicket-memberships',
        'post_id'  => $this->post_id,
        'today'    => $now->format( 'Y-m-d\TH:i:s\Z' ),
        'ends_at'  => $dates['ends_at'],
      ] );
      return new \WP_Error( 'group_ended', __( 'Individuals cannot be added to a membership group after the group end date.', 'wicket-memberships' ) );
    }

    $group_start = new \DateTime( $dates['starts_at'], new \DateTimeZone( 'UTC' ) );

    if ( $now < $group_start ) {
      return $dates['starts_at'];
    }

    return $now->format( 'Y-m-d\TH:i:s\Z' );
  }

  /**
   * Create an individual membership record and optionally link it to a group.
   *
   * Handles tier/product validation and the full create_membership_record() field set.
   * Dates are sourced from $this group — when called for move_individual_membership,
   * $this is the target group.
   *
   * @param int      $user_id          WP user ID of the new member.
   * @param int      $tier_post_id     Post ID of the individual Membership_Tier CPT.
   * @param int|null $product_id       WC parent product ID. Auto-resolved from tier when null.
   * @param int|null $variation_id     WC variation ID. When set, stored as membership_product_id.
   * @param string   $start_date       ISO 8601 UTC start date string.
   * @param int|null $link_to_group_id Group post ID to store in membership_group_id meta.
   *                                   Pass null for standalone (keep_as_individual) memberships.
   * @return int|\WP_Error New membership post ID on success, WP_Error on failure.
   */
  private function create_individual_membership_for_group( int $user_id, int $tier_post_id, ?int $product_id, ?int $variation_id, string $start_date, ?int $link_to_group_id ): int|\WP_Error {
    $user = get_user_by( 'id', $user_id );
    if ( ! $user ) {
      Wicket()->log()->error( 'Membership_Group::create_individual_membership_for_group: invalid user_id', [
        'source'  => 'wicket-memberships',
        'post_id' => $this->post_id,
        'user_id' => $user_id,
      ] );
      return new \WP_Error( 'invalid_user', __( 'The specified user does not exist.', 'wicket-memberships' ) );
    }

    $tier = new Membership_Tier( $tier_post_id );
    if ( ! $tier->is_individual_tier() ) {
      Wicket()->log()->error( 'Membership_Group::create_individual_membership_for_group: tier_post_id does not resolve to an individual tier', [
        'source'       => 'wicket-memberships',
        'post_id'      => $this->post_id,
        'tier_post_id' => $tier_post_id,
      ] );
      return new \WP_Error( 'invalid_tier', __( 'The specified tier is not an individual membership tier.', 'wicket-memberships' ) );
    }

    $tier_product_ids = array_map( 'intval', $tier->get_product_ids() );

    if ( $product_id === null ) {
      if ( \count( $tier_product_ids ) > 1 ) {
        Wicket()->log()->error( 'Membership_Group::create_individual_membership_for_group: tier has multiple products; product_id must be specified explicitly', [
          'source'           => 'wicket-memberships',
          'post_id'          => $this->post_id,
          'tier_product_ids' => $tier_product_ids,
        ] );
        return new \WP_Error( 'ambiguous_product', __( 'The membership tier has multiple products. Specify a product_id explicitly.', 'wicket-memberships' ) );
      }
      if ( empty( $tier_product_ids ) ) {
        Wicket()->log()->error( 'Membership_Group::create_individual_membership_for_group: tier has no products', [
          'source'  => 'wicket-memberships',
          'post_id' => $this->post_id,
        ] );
        return new \WP_Error( 'no_product', __( 'The membership tier has no products configured.', 'wicket-memberships' ) );
      }
      $product_id = $tier_product_ids[0];
    } elseif ( ! \in_array( $product_id, $tier_product_ids, true ) ) {
      Wicket()->log()->error( 'Membership_Group::create_individual_membership_for_group: product_id is not associated with this tier', [
        'source'           => 'wicket-memberships',
        'post_id'          => $this->post_id,
        'product_id'       => $product_id,
        'tier_product_ids' => $tier_product_ids,
      ] );
      return new \WP_Error( 'product_tier_mismatch', __( 'The specified product is not associated with this membership tier.', 'wicket-memberships' ) );
    }

    $dates = $this->get_dates();

    // Seed the membership status from the group's own status so new members inherit
    // the group's lifecycle state. create_local_membership_record() will still
    // override this to 'pending' when the tier requires approval, or 'delayed' when
    // the start date is in the future — those rules take precedence.
    $result = Membership_Controller::create_membership_record( [
      'membership_type'                           => 'individual',
      'user_id'                                   => $user_id,
      'person_uuid'                               => $user->user_login,
      'membership_status'                         => $this->get_membership_status(),
      'membership_starts_at'                      => $start_date,
      'membership_ends_at'                        => $dates['ends_at'],
      'membership_expires_at'                     => $dates['expires_at'] ?? '',
      'membership_early_renew_at'                 => $dates['early_renew_at'] ?? '',
      'membership_tier_post_id'                   => $tier_post_id,
      'membership_tier_uuid'                      => $tier->get_mdp_tier_uuid(),
      'membership_tier_name'                      => $tier->get_mdp_tier_name(),
      'membership_next_tier_id'                   => $tier->get_next_tier_id(),
      'membership_next_tier_form_page_id'         => $tier->get_next_tier_form_page_id(),
      'membership_next_tier_subscription_renewal' => '',
      // Store variation_id as membership_product_id when present — matches the
      // subscription-driven flow where variation_id takes precedence over parent product_id.
      'membership_product_id'                     => $variation_id ?? $product_id,
      'membership_parent_order_id'                => 0,
      'membership_subscription_id'                => 0,
      'membership_grace_period_days'              => 0,
      'membership_wp_user_display_name'           => $user->display_name,
      'membership_wp_user_last_name'              => get_user_meta( $user_id, 'last_name', true ) ?: '',
      'membership_wp_user_email'                  => $user->user_email,
      'membership_user_uuid'                      => $user->user_login,
    ] );

    $result = apply_filters(
      'wicket_memberships_individual_membership_created_for_group',
      $result,
      $this,
      $user_id,
      $tier_post_id,
      $product_id
    );

    $membership_post_id = (int) ( $result['membership_post_id'] ?? 0 );

    if ( $membership_post_id <= 0 ) {
      Wicket()->log()->error( 'Membership_Group::create_individual_membership_for_group: create_membership_record returned no post ID', [
        'source'  => 'wicket-memberships',
        'post_id' => $this->post_id,
        'user_id' => $user_id,
      ] );
      return new \WP_Error( 'create_failed', __( 'Failed to create the membership record.', 'wicket-memberships' ) );
    }

    if ( $link_to_group_id !== null ) {
      update_post_meta( $membership_post_id, 'membership_group_id', $link_to_group_id );
    }

    // Add a line item to the group subscription for this membership.
    // Variation ID takes precedence over parent product ID — same rule used when storing
    // membership_product_id above — so the line item price matches the variation price.
    $stored_product_id  = $variation_id ?? $product_id;
    $line_item_result   = $this->add_subscription_line_item( $membership_post_id, $stored_product_id, $user_id );

    if ( is_wp_error( $line_item_result ) ) {
      // Non-fatal: membership record was created successfully. A missing line item is a
      // billing gap the admin can reconcile in WC admin, not a data-loss event.
      Wicket()->log()->error( 'Membership_Group::create_individual_membership_for_group: could not add subscription line item', [
        'source'             => 'wicket-memberships',
        'post_id'            => $this->post_id,
        'membership_post_id' => $membership_post_id,
        'error'              => $line_item_result->get_error_message(),
      ] );
    }

    return $membership_post_id;
  }

  /**
   * Add a WooCommerce subscription line item for an individual membership.
   *
   * Called after create_individual_membership_for_group() succeeds. The line item
   * ties the group subscription to the individual membership record so billing and
   * renewal flows can identify which memberships are covered. Failure is non-fatal —
   * the caller logs and continues.
   *
   * @param int $membership_post_id  Post ID of the newly created individual membership.
   * @param int $product_id          WC product ID to add (variation ID when present, else parent).
   * @param int $user_id             WP user ID of the member (for _member_name meta).
   * @return int|\WP_Error           WC order item ID on success, WP_Error on failure.
   */
  private function add_subscription_line_item( int $membership_post_id, int $product_id, int $user_id ): int|\WP_Error {
    if ( ! function_exists( 'wcs_get_subscription' ) ) {
      return new \WP_Error( 'wcs_unavailable', __( 'WooCommerce Subscriptions is not active.', 'wicket-memberships' ) );
    }

    $sub_id = $this->get_subscription_id();
    if ( ! $sub_id ) {
      return new \WP_Error( 'no_subscription', __( 'This group has no linked WooCommerce subscription.', 'wicket-memberships' ) );
    }

    $sub = wcs_get_subscription( $sub_id );
    if ( ! $sub ) {
      return new \WP_Error( 'subscription_not_found', __( 'The linked WooCommerce subscription could not be loaded.', 'wicket-memberships' ) );
    }

    $product = wc_get_product( $product_id );
    if ( ! $product ) {
      return new \WP_Error( 'product_not_found', __( 'The membership product could not be loaded.', 'wicket-memberships' ) );
    }

    $item_id = $sub->add_product( $product, 1 );
    if ( ! $item_id ) {
      global $wpdb;
      fwrite( STDERR, "\n[DEBUG add_subscription_line_item] add_product returned falsy. sub_id={$sub_id} product_id={$product_id} wpdb_last_error=" . $wpdb->last_error . "\n" );
      return new \WP_Error( 'add_product_failed', __( 'Failed to add product to the group subscription.', 'wicket-memberships' ) );
    }

    wc_add_order_item_meta( $item_id, '_membership_post_id', $membership_post_id );

    $user = get_user_by( 'id', $user_id );
    if ( $user ) {
      wc_add_order_item_meta( $item_id, '_member_name', $user->display_name );
    }

    $sub->calculate_totals();
    $sub->save();

    return $item_id;
  }

  // TODO: Implement remove_subscription_line_item() to remove the group subscription
  //       line item (matched by _membership_post_id meta) when a member is removed
  //       or moved out of the group. Call from remove_member() and the source-group
  //       side of move_individual_membership(). See plan-group-subscription-line-items.md.

  // TODO: Add subscription line items for bulk CSV import path.
  //       Currently each add_member() call fires calculate_totals() individually.
  //       For large imports, investigate batching totals recalculation.
  //       See plan-group-subscription-line-items.md.

  // TODO: Implement group subscription status transitions.
  //       Individual memberships go pending → active when parent order completes
  //       (Membership_Subscription_Controller::create_subscriptions() lines 84–87).
  //       Group subscriptions have no parent order — need an explicit activation
  //       path, likely triggered when the group status transitions to 'active'.
  //       See plan-group-subscription-line-items.md.

  /**
   * Remove an individual membership from this group.
   *
   * Two modes:
   * - 'cancel'            → cancel the group-linked membership immediately.
   * - 'keep_as_individual' → cancel the group-linked membership and create a new
   *                          standalone individual membership (start=now, end=group end date).
   *
   * @param int    $membership_post_id Post ID of the individual membership to remove.
   * @param string $mode               'cancel' or 'keep_as_individual'.
   * @return int|\WP_Error Affected membership post ID on success, WP_Error on failure.
   */
  public function remove_member( int $membership_post_id, string $mode ): int|\WP_Error {
    if ( $err = $this->assert_group_is_manageable() ) {
      return $err;
    }

    if ( $err = $this->assert_individual_membership_in_group( $membership_post_id ) ) {
      return $err;
    }

    $this->cancel_individual_membership( $membership_post_id );

    if ( $mode === 'cancel' ) {
      return $membership_post_id;
    }

    // keep_as_individual: create a new standalone membership inheriting group dates.
    $meta = $this->extract_individual_membership_meta( $membership_post_id );
    if ( is_wp_error( $meta ) ) {
      return $meta;
    }

    $start_date = $this->resolve_member_start_date();
    if ( is_wp_error( $start_date ) ) {
      return $start_date;
    }

    // link_to_group_id = null → standalone, no membership_group_id meta written.
    return $this->create_individual_membership_for_group(
      $meta['user_id'],
      $meta['tier_post_id'],
      $meta['product_id'],
      $meta['variation_id'],
      $start_date,
      null
    );
  }

  // Post-level accessors.

  /**
   * Returns the post title (group name), or an empty string if the post is not loaded.
   */
  public function get_name(): string {
    $post = get_post( $this->post_id );
    return $post ? $post->post_title : '';
  }

  // Owner management.

  /**
   * Set the single owner for this membership group by MDP UUID.
   *
   * Resolves or creates the WP user via wicket_create_wp_user_if_not_exist before
   * writing meta, so callers never need to pre-resolve a UUID to a user ID.
   *
   * @param string $uuid MDP person UUID (stored as WP user_login)
   * @return int|false Returns the saved WP user ID on success, false on failure
   */
  public function set_owner( string $uuid ): int|false {
    // Cheap format guard before any DB or MDP calls.
    if ( ! isValidUuid( $uuid ) ) {
      Wicket()->log()->error( 'Membership_Group: Invalid owner UUID', ['source' => 'wicket-memberships', 'post_id' => $this->post_id, 'uuid' => $uuid] );
      return false;
    }

    // Resolve or create the WP user for this MDP person.
    $user_id = wicket_create_wp_user_if_not_exist( $uuid );
    $user    = $user_id ? get_user_by( 'id', $user_id ) : false;

    if ( ! $user ) {
      Wicket()->log()->error( 'Membership_Group: Could not resolve owner UUID to WP user', ['source' => 'wicket-memberships', 'post_id' => $this->post_id, 'uuid' => $uuid] );
      return false;
    }

    // Only the WP user ID is stored. Derived fields like display name, email, and
    // MDP UUID are intentionally omitted — they can change independently of the
    // membership record and would silently go stale. Retrieve them on demand via:
    //   $user = get_user_by( 'id', $owner_id );           // WP user object
    //   wicket_get_person_by_id( $user->user_login );      // MDP person record (UUID = user_login)
    if ( update_post_meta( $this->post_id, 'user_id', $user->ID ) === false ) {
      Wicket()->log()->error( 'Membership_Group: Failed to save owner user_id', ['source' => 'wicket-memberships', 'post_id' => $this->post_id, 'uuid' => $uuid] );
      return false;
    }

    // Keep post_author in sync with user_id meta so WordPress-level queries and
    // capabilities that rely on post_author continue to resolve to the correct user.
    wp_update_post( [
      'ID'          => $this->post_id,
      'post_author' => $user->ID,
    ] );

    // Reassign the WC order and subscription so billing and payment processing
    // continue to target the new owner's account rather than the previous one.
    $this->reassign_order_customer( $user->ID );
    $this->reassign_subscription_customer( $user->ID );

    $this->meta_data = get_post_meta( $this->post_id );

    return $user->ID;
  }

  /**
   * Check if a given MDP UUID is the owner of this group.
   *
   * @param string $uuid MDP person UUID
   * @return bool
   */
  public function is_owner( string $uuid ): bool {
    $user = get_user_by( 'login', $uuid );
    if ( ! $user ) {
      return false;
    }
    return $this->get_owner_id() === $user->ID;
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
   * Get a structured snapshot of the group owner.
   *
   * Returns the four fields that callers most commonly need together. The UUID
   * is derived from user_login rather than stored as meta so it stays in sync
   * with the MDP person record automatically.
   *
   * @return array{user_id: int, uuid: string, name: string, email: string}|false
   */
  public function get_owner(): array|false {
    $owner_id = $this->get_owner_id();
    if ( ! $owner_id ) {
      return false;
    }
    $user = get_user_by( 'id', $owner_id );
    if ( ! $user ) {
      return false;
    }
    return [
      'user_id' => $owner_id,
      'uuid'    => $user->user_login,
      'name'    => $user->display_name,
      'email'   => $user->user_email,
    ];
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

    // org_name is denormalised here so list/detail views can display the org name
    // without an extra MDP API call on every page load. It may drift if the org is
    // renamed in MDP, but that is an acceptable trade-off given the read frequency.
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
   * Set the linked membership group config for this group.
   *
   * @param int $config_post_id Post ID of the Membership_Group_Config to link.
   * @return bool True on success, false on failure.
   */
  public function set_config( int $config_post_id ): bool {
    $config = new Membership_Group_Config( $config_post_id );
    if ( $config->get_post_id() <= 0 ) {
      Wicket()->log()->error( 'Membership_Group: Invalid config post ID', ['source' => 'wicket-memberships', 'post_id' => $this->post_id, 'config_post_id' => $config_post_id] );
      return false;
    }

    if ( update_post_meta( $this->post_id, 'membership_group_config_id', $config_post_id ) === false ) {
      Wicket()->log()->error( 'Membership_Group: Failed to save membership_group_config_id', ['source' => 'wicket-memberships', 'post_id' => $this->post_id, 'config_post_id' => $config_post_id] );
      return false;
    }

    $this->meta_data = get_post_meta( $this->post_id );

    return true;
  }

  /**
   * Set the date fields for this membership group.
   *
   * Accepts the same keys returned by get_dates(). Optional keys are skipped when null.
   *
   * @param array<string, string|null> $dates {
   *   @type string      $starts_at      Required. ISO 8601 membership start date.
   *   @type string      $ends_at        Required. ISO 8601 membership end date.
   *   @type string|null $expires_at     Optional. ISO 8601 expiration date (end + grace period).
   *   @type string|null $early_renew_at Optional. ISO 8601 early-renewal window start.
   * }
   * @return bool True on success, false on failure.
   */
  public function set_dates( array $dates ): bool {
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
        Wicket()->log()->error( 'Membership_Group: Failed to persist date field', [
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
    // Bypass path skips lifecycle rules entirely — for dev/testing only.
    if ( ! empty( $_ENV['BYPASS_STATUS_CHANGE_LOCKOUT'] ) ) {
      if ( ! $this->set_membership_status( $new_status ) ) {
        return false;
      }

      return [
        'success_message' => 'BYPASSED STATUS LOCKOUT — status set to ' . $new_status,
        'bypassed'        => true,
      ];
    }

    // Guard against illegal transitions before touching any state.
    if ( ! $this->can_transition_to( $new_status ) ) {
      return false;
    }

    // Compute the dates and side-effect flags for this specific transition path
    // before any writes, so a planning failure is still fully reversible.
    $transition_plan = $this->plan_status_transition( $new_status );
    if ( false === $transition_plan ) {
      return false;
    }

    // Activate the WC subscription first — if this fails the caller can detect
    // it without also having to undo a status write.
    if ( ! empty( $transition_plan['activate_subscription'] ) ) {
      $this->activate_subscription_for_dates(
        $transition_plan['transition_dates']['starts_at'],
        $transition_plan['transition_dates']['ends_at'],
        $transition_plan['transition_dates']['expires_at']
      );
    }

    // Write status and dates atomically via apply_status_transition.
    if ( ! $this->apply_status_transition( $new_status, $transition_plan['transition_dates'] ) ) {
      return false;
    }

    // Propagate the new status down to child memberships last, after the group
    // record is already in its final state so children see a consistent parent.
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

      $update_result = update_post_meta( $this->post_id, $meta_key, $dates[ $date_key ] );

      if ( false === $update_result ) {
        // WordPress returns false when the stored value already matches — treat as no-op.
        if ( (string) get_post_meta( $this->post_id, $meta_key, true ) === (string) $dates[ $date_key ] ) {
          continue;
        }

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
    $memberships = $this->get_individual_memberships();

    foreach ( $memberships as $membership_post ) {
      $current = get_post_meta( $membership_post->ID, 'membership_status', true );

      // Cancelled is a final state — never overwrite it.
      if ( $current === Wicket_Memberships::STATUS_CANCELLED ) {
        continue;
      }

      $result = update_post_meta( $membership_post->ID, 'membership_status', $new_status );

      if ( false === $result && (string) get_post_meta( $membership_post->ID, 'membership_status', true ) !== (string) $new_status ) {
        Wicket()->log()->error( 'Membership_Group::cascade_status_to_members: failed to update membership status', [
          'source'             => 'wicket-memberships',
          'group_post_id'      => $this->post_id,
          'membership_post_id' => $membership_post->ID,
          'new_status'         => $new_status,
        ] );
      }
    }
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
      $update_result = update_post_meta( $this->post_id, $key, $value );

      if ( false === $update_result ) {
        $persisted_value = get_post_meta( $this->post_id, $key, true );

        // WordPress returns false when the stored meta already matches the
        // submitted value, which should be treated as a successful no-op.
        if ( (string) $persisted_value === (string) $value ) {
          continue;
        }

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

    $next_payment = Utilities::get_mdp_day_end( $ends_at_utc );
    $end          = Utilities::get_mdp_day_end( $expires_at_utc );

    // WC Subscriptions requires end > next_payment (strict). When there is no
    // grace period expires_at equals ends_at, producing identical timestamps.
    // Bump end by one second so the constraint is satisfied.
    if ( $end <= $next_payment ) {
      $end->modify( '+1 second' );
    }

    $subscription_dates = [
      'start_date'   => Utilities::get_mdp_day_start( $starts_at_utc )->format( 'Y-m-d H:i:s' ),
      'next_payment' => $next_payment->format( 'Y-m-d H:i:s' ),
      'end'          => $end->format( 'Y-m-d H:i:s' ),
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
      "Reassigning customer to {$user->user_email} on membership group ownership change."
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
      "Reassigning customer to {$user->user_email} on membership group ownership change."
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

    // pending → active: derive fresh dates from the config so the subscription
    // period reflects the current cycle, not whatever was stored at creation time.
    if ( $current_status === Wicket_Memberships::STATUS_PENDING && $new_status === Wicket_Memberships::STATUS_ACTIVE ) {
      $config = $this->get_config();
      $dates  = $config ? $config->get_membership_dates() : [];

      return [
        'transition_dates' => [
          'starts_at'      => $dates['start_date'] ?? Utilities::get_mdp_day_start( 'now' )->format( 'c' ),
          'ends_at'        => $dates['end_date'] ?? Utilities::get_mdp_day_end( 'now' )->format( 'c' ),
          // expires_at / early_renew_at fall back to end_date when no grace/window is configured.
          'expires_at'     => $dates['expires_at'] ?? ( $dates['end_date'] ?? Utilities::get_mdp_day_end( 'now' )->format( 'c' ) ),
          'early_renew_at' => $dates['early_renew_at'] ?? ( $dates['end_date'] ?? Utilities::get_mdp_day_end( 'now' )->format( 'c' ) ),
        ],
        'success_message'      => 'Pending membership group activated successfully.',
        'activate_subscription' => true,
      ];
    }

    if ( $new_status === Wicket_Memberships::STATUS_CANCELLED ) {
      // Cancelling before any active period: backdate starts_at by one day so the
      // membership record reflects a zero-length period in MDP rather than appearing
      // to have been active today.
      if ( in_array( $current_status, [ Wicket_Memberships::STATUS_PENDING, Wicket_Memberships::STATUS_DELAYED ], true ) ) {
        return [
          'transition_dates' => [
            'starts_at'      => Utilities::get_mdp_day_start( '-1 day' )->format( 'c' ),
            'ends_at'        => Utilities::get_mdp_day_end( 'now' )->format( 'c' ),
            'expires_at'     => Utilities::get_mdp_day_end( 'now' )->format( 'c' ),
            'early_renew_at' => null,
          ],
          'success_message'      => 'Membership group cancelled successfully.',
          'activate_subscription' => false,
        ];
      }

      // Cancelling during grace period: preserve the original ends_at so the member's
      // paid period is not retroactively shortened; collapse expires_at to now.
      if ( $current_status === Wicket_Memberships::STATUS_GRACE ) {
        $current_end = get_post_meta( $this->post_id, 'membership_ends_at', true );

        return [
          'transition_dates' => [
            'starts_at'      => null,
            'ends_at'        => $current_end !== '' ? $current_end : null,
            'expires_at'     => Utilities::get_mdp_day_end( 'now' )->format( 'c' ),
            'early_renew_at' => null,
          ],
          'success_message'      => 'Membership group cancelled successfully.',
          'activate_subscription' => false,
        ];
      }

      // Cancelling from active or any other status: give a one-day notice window
      // so downstream systems processing the webhook have time to react before the
      // record is fully closed.
      return [
        'transition_dates' => [
          'starts_at'      => null,
          'ends_at'        => Utilities::get_mdp_day_end( '+1 day' )->format( 'c' ),
          'expires_at'     => Utilities::get_mdp_day_end( '+1 day' )->format( 'c' ),
          'early_renew_at' => null,
        ],
        'success_message'      => 'Membership group cancelled successfully.',
        'activate_subscription' => false,
      ];
    }

    // Expiring: same one-day notice window as cancellation for the same reason.
    if ( $new_status === Wicket_Memberships::STATUS_EXPIRED ) {
      return [
        'transition_dates' => [
          'starts_at'      => null,
          'ends_at'        => Utilities::get_mdp_day_end( '+1 day' )->format( 'c' ),
          'expires_at'     => Utilities::get_mdp_day_end( '+1 day' )->format( 'c' ),
          'early_renew_at' => null,
        ],
        'success_message'      => 'Membership group marked as expired.',
        'activate_subscription' => false,
      ];
    }

    return false;
  }

  /**
   * Create a pending WC subscription for this group.
   *
   * Called only from create() after dates are written. No product line items are
   * added here — those are attached when individual members are added to the group.
   *
   * @return int|false Subscription post ID on success, false on any failure.
   */
  private function create_group_subscription(): int|false {
    // WooCommerce Subscriptions may not be active in all environments.
    if ( ! function_exists( 'wcs_create_subscription' ) ) {
      Wicket()->log()->error( 'Membership_Group::create_group_subscription: wcs_create_subscription not available', [
        'source'  => 'wicket-memberships',
        'post_id' => $this->post_id,
      ] );
      return false;
    }

    // Subscription must be assigned to a real WP customer.
    $owner_id = $this->get_owner_id();
    if ( ! $owner_id ) {
      Wicket()->log()->error( 'Membership_Group::create_group_subscription: could not resolve owner_id', [
        'source'  => 'wicket-memberships',
        'post_id' => $this->post_id,
      ] );
      return false;
    }

    // Dates are guaranteed present — set_dates() succeeded before this call.
    $dates = $this->get_dates();

    // Config is also guaranteed present — validated in create() before set_dates().
    // Resolve once and reuse for both billing period and renewal-type check below.
    $config = $this->get_config();
    if ( ! $config ) {
      Wicket()->log()->error( 'Membership_Group::create_group_subscription: could not resolve config', [
        'source'  => 'wicket-memberships',
        'post_id' => $this->post_id,
      ] );
      return false;
    }

    // billing_period / billing_interval are required by wcs_create_subscription even
    // for date-driven subscriptions. Source from the config so the values are
    // semantically correct (anniversary configs get the right period; calendar configs
    // fall back to year/1 via get_period_data()'s internal default).
    $period = $config->get_period_data();

    $sub = wcs_create_subscription( [
      'customer_id'      => $owner_id,
      'status'           => 'pending',
      'billing_period'   => $period['period_type'],
      'billing_interval' => $period['period_count'],
      'start_date'       => date( 'Y-m-d H:i:s', strtotime( $dates['starts_at'] ) ),
    ] );

    if ( is_wp_error( $sub ) ) {
      Wicket()->log()->error( 'Membership_Group::create_group_subscription: wcs_create_subscription failed', [
        'source'  => 'wicket-memberships',
        'post_id' => $this->post_id,
        'error'   => $sub->get_error_message(),
      ] );
      return false;
    }

    // end maps to expires_at (grace-period end), mirroring Membership_Subscription_Controller::create_subscriptions().
    // When no grace period is configured expires_at is empty; fall back to ends_at so
    // the subscription always has an explicit end date.
    $end_target = ! empty( $dates['expires_at'] ) ? $dates['expires_at'] : $dates['ends_at'];

    // WCS validates end > next_payment (strictly). When billing_period is set, WCS
    // auto-computes next_payment as start + 1 period, which can violate the constraint
    // for date-driven subscriptions. Set both explicitly in all cases.
    //
    // Subscription renewal: next_payment = ends_at so WCS triggers renewal at period end;
    //   end = end_target (expires_at or ends_at) which is >= ends_at.
    // All other renewal types: next_payment = ends_at, end = end_target + 1 second so
    //   the strict end > next_payment constraint is always satisfied without scheduling
    //   an automatic charge (WCS only charges on next_payment for active subscriptions).
    $ends_at_ts   = strtotime( $dates['ends_at'] );
    $end_ts       = strtotime( $end_target );
    $next_payment = date( 'Y-m-d H:i:s', $ends_at_ts );
    // Guarantee end > next_payment — add one second when they would be equal (no grace period).
    $end = date( 'Y-m-d H:i:s', $end_ts > $ends_at_ts ? $end_ts : $ends_at_ts + 1 );

    $sub->update_dates( [
      'end'          => $end,
      'next_payment' => $next_payment,
    ] );

    // Link the subscription back to the group and forward org identity so admin
    // screens and MDP sync can identify which organisation owns this subscription
    // without an extra group lookup. Mirrors the _org_uuid pattern on individual
    // org membership subscriptions (Membership_Controller.php lines 83–84).
    // org_name is stored alongside _org_uuid so the subscription is human-readable
    // in WC admin without following the group post link.
    $org_uuid = $this->get_org_uuid();
    $org_name = get_post_meta( $this->post_id, 'org_name', true );
    update_post_meta( $sub->get_id(), 'membership_group_id', $this->post_id );
    update_post_meta( $sub->get_id(), '_org_uuid', $org_uuid );
    $sub->update_meta_data( '_org_uuid', $org_uuid );
    if ( ! empty( $org_name ) ) {
      $sub->update_meta_data( 'org_name', $org_name );
    }

    $sub->save();

    return $sub->get_id();
  }
}
