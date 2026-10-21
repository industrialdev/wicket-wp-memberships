<?php

namespace Wicket_Memberships;

use Wicket_Memberships\Helper;
use Wicket_Memberships\Utilities;

/**
 * Represents a Membership Bundle CPT record
 * @package Wicket_Memberships
 */
class Membership_Bundle {

  /**
   * Renewal callout types — same strings as the individual-membership callout
   * types returned by Membership_Controller::get_membership_callouts(), so ACC
   * and the Membership Bundles List block share one vocabulary.
   */
  public const CALLOUT_EARLY_RENEWAL = 'early_renewal';
  public const CALLOUT_GRACE_PERIOD  = 'grace_period';

  /** Renewal flows a bundle can resolve to (see get_renewal_flow()). */
  public const RENEWAL_FLOW_SUBSCRIPTION = 'subscription';
  public const RENEWAL_FLOW_FORM_PAGE    = 'form_page';

  /**
   * Per-request cache for check_current_user_access(): the org-connection
   * lookup is a live MDP call, and the detail template + admin-post handler
   * may both ask for the same bundle in one request.
   *
   * @var array<string, true|\WP_Error>
   */
  private static array $access_cache = [];

  // Statuses an existing standalone membership may have to be converted into a bundle seat.
  private const EXISTING_MEMBER_STATUSES = [
    Wicket_Memberships::STATUS_PENDING,
    Wicket_Memberships::STATUS_ACTIVE,
    Wicket_Memberships::STATUS_DELAYED,
  ];

  public readonly int $post_id;
  public $meta_data;

  //don't create wicket connection - for testing locally
  public $bypass_wicket;

  public function __construct( $post_id ) {
    $this->bypass_wicket = ! empty( $_ENV['BYPASS_WICKET'] ) ?? false;

    // Ensure the post exists in the database before proceeding
    if ( ! get_post( $post_id ) ) {
      Wicket()->log()->error( 'Membership_Bundle: Invalid post ID', ['source' => 'wicket-memberships', 'post_id' => $post_id] );
      $this->post_id   = 0;
      $this->meta_data = [];
      return;
    }

    // Ensure the post is actually a membership bundle CPT, not some other post type
    if ( get_post_type( $post_id ) !== Helper::get_membership_bundle_cpt_slug() ) {
      Wicket()->log()->error( 'Membership_Bundle: Invalid post type', ['source' => 'wicket-memberships', 'post_id' => $post_id, 'post_type' => get_post_type( $post_id )] );
      $this->post_id   = 0;
      $this->meta_data = [];
      return;
    }

    $this->post_id   = $post_id;
    $this->meta_data = get_post_meta( $post_id );
  }

  /**
   * Create a new membership bundle post with all required data.
   *
   * Returns null and logs the reason on any failure; any partially-created post is deleted
   * before returning.
   *
   * @param string $name                       Post title for the bundle.
   * @param int    $membership_bundle_config_id Post ID of the linked Membership_Bundle_Config.
   * @param string $org_uuid                   MDP organisation UUID.
   * @param string $owner_uuid                 MDP person UUID of the bundle owner.
   * @param string $start_date                 Start date for the membership period: ISO 8601, or a bare Y-m-d read as an MDP calendar day. Stored as its MDP day start.
   * @param bool   $sync_to_mdp                Whether to create the bundle in MDP. Set false when
   *                                           importing a bundle that already exists in MDP, to avoid
   *                                           creating a duplicate record there.
   * @return static|null New Membership_Bundle instance on success, null on failure.
   */
  public static function create(
    string $name,
    int $membership_bundle_config_id,
    string $org_uuid,
    string $owner_uuid,
    string $start_date,
    bool $sync_to_mdp = true
  ): ?static {
    // Validate all inputs before touching the database.
    if ( empty( $name ) ) {
      Wicket()->log()->error( 'Membership_Bundle::create: name is empty', ['source' => 'wicket-memberships'] );
      throw new \RuntimeException( 'name is empty.' );
    }

    // Confirm the config post exists and is the correct CPT type.
    $config_check = new Membership_Bundle_Config( $membership_bundle_config_id );
    if ( $config_check->get_post_id() <= 0 ) {
      Wicket()->log()->error( 'Membership_Bundle::create: membership_bundle_config_id does not resolve to a valid config', ['source' => 'wicket-memberships', 'config_post_id' => $membership_bundle_config_id] );
      throw new \RuntimeException( 'membership_bundle_config_id does not resolve to a valid config.' );
    }

    // Confirm the org UUID is a well-formed UUID before attempting an MDP lookup later.
    if ( ! isValidUuid( $org_uuid ) ) {
      Wicket()->log()->error( 'Membership_Bundle::create: invalid org_uuid', ['source' => 'wicket-memberships', 'org_uuid' => $org_uuid] );
      throw new \RuntimeException( 'org_uuid is not a valid UUID.' );
    }

    // Confirm the owner UUID is well-formed; set_owner() will resolve/create the WP user.
    if ( ! isValidUuid( $owner_uuid ) ) {
      Wicket()->log()->error( 'Membership_Bundle::create: invalid owner_uuid', ['source' => 'wicket-memberships', 'owner_uuid' => $owner_uuid] );
      throw new \RuntimeException( 'owner_uuid is not a valid UUID.' );
    }

    // Validate start_date is parseable and normalize to its MDP day start (UTC ISO 8601).
    // A bare Y-m-d is an MDP calendar day; read as UTC midnight it is the previous day west of UTC.
    if ( empty( $start_date ) ) {
      Wicket()->log()->error( 'Membership_Bundle::create: start_date is empty', ['source' => 'wicket-memberships'] );
      throw new \RuntimeException( 'start_date is empty.' );
    }
    try {
      $start_dt   = Utilities::get_mdp_day_start( $start_date );
      $start_date = $start_dt->format( 'c' );
    } catch ( \Exception $e ) {
      Wicket()->log()->error( 'Membership_Bundle::create: start_date could not be parsed', ['source' => 'wicket-memberships', 'start_date' => $start_date] );
      throw new \RuntimeException( 'start_date is not a valid date string.' );
    }

    // Insert the CPT post shell. All meta is written via setters below.
    $post_id = wp_insert_post( [
      'post_type'   => Helper::get_membership_bundle_cpt_slug(),
      'post_title'  => $name,
      'post_status' => 'publish',
    ], true );

    if ( is_wp_error( $post_id ) ) {
      Wicket()->log()->error( 'Membership_Bundle::create: wp_insert_post failed', ['source' => 'wicket-memberships', 'error' => $post_id->get_error_message()] );
      return null;
    }

    if ( empty( $post_id ) ) {
      Wicket()->log()->error( 'Membership_Bundle::create: wp_insert_post returned empty ID', ['source' => 'wicket-memberships'] );
      return null;
    }

    // Wrap the new post in a Membership_Bundle instance so we can use its setters.
    $bundle = new static( $post_id );

    // Bundle memberships activate immediately on creation. Future start dates still use
    // delayed so the daily activation hook can promote them on schedule.
    if ( $start_dt->getTimestamp() > time() ) {
      $initial_status = Wicket_Memberships::STATUS_DELAYED;
    } else {
      $initial_status = Wicket_Memberships::STATUS_ACTIVE;
    }

    // Write status, owner, org, and config meta. Any failure rolls back the post entirely
    // so we never leave a partially-populated bundle in the database.
    if ( ! $bundle->set_membership_status( $initial_status )
      || ! $bundle->set_owner( $owner_uuid )
      || ! $bundle->set_organization( $org_uuid )
      || ! $bundle->set_config( $membership_bundle_config_id )
    ) {
      Wicket()->log()->error( 'Membership_Bundle::create: failed to set bundle data, rolling back post', ['source' => 'wicket-memberships', 'post_id' => $post_id] );
      wp_delete_post( $post_id, true );
      return null;
    }

    // Derive the membership date range from the config, anchored to the supplied start date.
    // expires_at and early_renew_at are only present when grace period / renewal window are configured.
    $config = $bundle->get_config();
    $dates  = $config->get_membership_dates( [ 'start_date' => $start_date ] );

    if ( ! $bundle->set_dates( [
      'starts_at'      => $dates['start_date'],
      'ends_at'        => $dates['end_date'],
      'expires_at'     => $dates['expires_at'] ?? null,
      'early_renew_at' => $dates['early_renew_at'] ?? null,
    ] ) ) {
      Wicket()->log()->error( 'Membership_Bundle::create: failed to set dates, rolling back post', ['source' => 'wicket-memberships', 'post_id' => $post_id] );
      wp_delete_post( $post_id, true );
      return null;
    }

    // Create a pending WC subscription linked to this bundle so billing and
    // renewal logic have a subscription record to work against from day one.
    $subscription_id = $bundle->create_bundle_subscription();
    if ( $subscription_id ) {
      update_post_meta( $post_id, 'membership_subscription_id', $subscription_id );

      // Bundles created directly active skip the pending→active transition, so
      // promote the subscription here instead.
      if ( $initial_status === Wicket_Memberships::STATUS_ACTIVE ) {
        $bundle->activate_subscription_for_dates(
          $dates['start_date'],
          $dates['end_date'],
          $dates['expires_at'] ?? $dates['end_date']
        );
      }
    } else {
      Wicket()->log()->error(
        'Membership_Bundle::create: could not create WC subscription — bundle created without one',
        [ 'source' => 'wicket-memberships', 'post_id' => $post_id ]
      );
      // Non-fatal: the bundle record is still usable without a subscription.
      // Consistent with individual membership precedent where subscription
      // creation is decoupled from record creation entirely.
    }

    // Generate a fresh group UUID anchored to org_uuid. Every new bundle starts its own
    // renewal series. renew_bundle() reuses the existing group UUID when creating a
    // renewal term — create() always generates a new one.
    // Must run after set_organization() so org_uuid is available for generation.
    $generated_uuid = $bundle->generate_bundle_group_uuid();
    if ( $generated_uuid ) {
      $bundle->set_bundle_group_uuid( $generated_uuid );
    } else {
      Wicket()->log()->error( 'Membership_Bundle::create: could not generate bundle_group_uuid — org_uuid missing', [
        'source'  => 'wicket-memberships',
        'post_id' => $post_id,
      ] );
    }

    // Reload meta so the returned instance reflects everything written above.
    $bundle->meta_data = get_post_meta( $post_id );

    // Schedule one-time AS jobs so AutomateWoo triggers fire at exact dates.
    $bundle->schedule_date_trigger_jobs();

    // Skip MDP creation when importing a bundle that already exists there — the caller
    // is responsible for seeding membership_bundle_mdp_uuid separately in that case.
    if ( $sync_to_mdp ) {
      $bundle->sync_mdp_create();
    }

    return $bundle;
  }

  /**
   * Create a new bundle post for a renewal term of this bundle.
   *
   * This is the renewal-specific factory. It differs from create() in three ways:
   * 1. Reuses the existing WC subscription (updates its dates to the new term) instead
   *    of creating a new one — WCS continues to fire renewal orders against the same sub.
   * 2. Carries the same membership_bundle_group_uuid so all renewal posts share a series link.
   * 3. Accepts pre-calculated $new_dates from Membership_Bundle_Config::get_membership_dates()
   *    rather than deriving them internally, since the caller (handle_bundle_renewal) has
   *    already anchored dates to the old bundle's ends_at.
   *
   * The old bundle and its subscription are NOT cancelled here — that is deferred to either
   * cancel_old_bundle_on_new_starts_at (early renewal, future start) or handle_bundle_renewal
   * (same-day renewal, immediate cancel).
   *
   * @param \WC_Subscription $subscription The existing WC subscription to reuse.
   * @param array            $new_dates    Output of Membership_Bundle_Config::get_membership_dates().
   * @return static|null New bundle instance on success, null on failure.
   */
  public function renew_bundle( \WC_Subscription $subscription, array $new_dates ): ?static {
    $config = $this->get_config();
    if ( ! $config ) {
      Wicket()->log()->error( 'Membership_Bundle::renew_bundle: old bundle has no config', [
        'source'  => 'wicket-memberships',
        'post_id' => $this->post_id,
      ] );
      return null;
    }

    // New term start date — drives initial status (delayed vs pending).
    $start_date = $new_dates['start_date'];

    // Insert new bundle post shell.
    $post_id = wp_insert_post( [
      'post_type'   => Helper::get_membership_bundle_cpt_slug(),
      'post_title'  => get_the_title( $this->post_id ),
      'post_status' => 'publish',
    ], true );

    if ( is_wp_error( $post_id ) || empty( $post_id ) ) {
      Wicket()->log()->error( 'Membership_Bundle::renew_bundle: wp_insert_post failed', [
        'source'      => 'wicket-memberships',
        'old_post_id' => $this->post_id,
      ] );
      return null;
    }

    $new_bundle = new static( $post_id );

    // Renewal bundles always start as delayed — this preserves the pre-calculated renewal
    // dates when transition_to(active) fires later. pending→active recalculates dates from
    // today (correct for new purchases), but renewals have dates anchored to the old term's
    // ends_at and must not be overwritten. delayed→active is date-preserving.
    $initial_status = Wicket_Memberships::STATUS_DELAYED;

    // Write status, owner, org, config — same as create(). Roll back on any failure.
    if ( ! $new_bundle->set_membership_status( $initial_status )
      || ! $new_bundle->set_owner( $this->get_owner_uuid() )
      || ! $new_bundle->set_organization( $this->get_org_uuid() )
      || ! $new_bundle->set_config( $config->get_post_id() )
    ) {
      Wicket()->log()->error( 'Membership_Bundle::renew_bundle: failed to set bundle data, rolling back', [
        'source'      => 'wicket-memberships',
        'old_post_id' => $this->post_id,
        'new_post_id' => $post_id,
      ] );
      wp_delete_post( $post_id, true );
      return null;
    }

    // Write dates from the pre-calculated new term.
    if ( ! $new_bundle->set_dates( [
      'starts_at'      => $new_dates['start_date'],
      'ends_at'        => $new_dates['end_date'],
      'expires_at'     => $new_dates['expires_at'] ?? null,
      'early_renew_at' => $new_dates['early_renew_at'] ?? null,
    ] ) ) {
      Wicket()->log()->error( 'Membership_Bundle::renew_bundle: failed to set dates, rolling back', [
        'source'      => 'wicket-memberships',
        'old_post_id' => $this->post_id,
        'new_post_id' => $post_id,
      ] );
      wp_delete_post( $post_id, true );
      return null;
    }

    // Reuse the existing subscription — link the new bundle post to it and update
    // the subscription's dates to the new term so WCS schedules the next renewal correctly.
    // membership_bundle_id is staged via update_meta_data() + save() below, not a
    // standalone update_post_meta() call — see create_bundle_subscription() for why.
    $sub_id = $subscription->get_id();
    update_post_meta( $post_id, 'membership_subscription_id', $sub_id );
    $subscription->update_meta_data( 'membership_bundle_id', $post_id );

    // Mirrors create_bundle_subscription()'s date logic and collision nudging.
    $end_target = ! empty( $new_dates['expires_at'] ) ? $new_dates['expires_at'] : $new_dates['end_date'];
    $sub_dates = [
      'end' => date( 'Y-m-d H:i:s', strtotime( $end_target ) ),
    ];
    // next_payment only applies to the 'subscription' renewal type — matches
    // activate_subscription_for_dates()'s rule. Every other renewal type
    // (form_page, ...) must not carry a next_payment date into the new term,
    // or WCS would auto-charge on its own schedule again.
    if ( $config->is_renewal_subscription() ) {
      $sub_dates['next_payment'] = date( 'Y-m-d H:i:s', strtotime( $new_dates['end_date'] ) );
      $sub_dates = Subscription_Manager::prepare_dates( $sub_dates, $subscription );
    }
    $subscription->update_dates( $sub_dates );
    if ( ! $config->is_renewal_subscription() ) {
      $subscription->delete_date( 'next_payment' );
    }
    $subscription->save();

    // Carry the group UUID forward — this post joins the existing renewal series.
    $new_bundle->set_bundle_group_uuid( $this->get_bundle_group_uuid() );

    // Reload meta so the returned instance is fully hydrated.
    $new_bundle->meta_data = get_post_meta( $post_id );

    // Schedule one-time AS date trigger jobs for the new term.
    $new_bundle->schedule_date_trigger_jobs();

    $new_bundle->sync_mdp_create();

    return $new_bundle;
  }

  // Membership bundle relations.

  /**
   * Get all individual memberships that have this bundle set as their FK
   *
   * @return array
   */
  public function get_individual_memberships( bool $active_only = true ) {
    $meta_query = [
      [
        'key'   => 'membership_bundle_id',
        'value' => $this->post_id,
      ],
    ];

    if ( $active_only ) {
      $meta_query[] = [
        'key'     => 'membership_status',
        'value'   => [ 'cancelled', 'expired' ],
        'compare' => 'NOT IN',
      ];
    }

    return get_posts( [
      'post_type'   => Helper::get_membership_cpt_slug(),
      'post_status' => 'any',
      'numberposts' => -1,
      'meta_query'  => $meta_query,
    ] );
  }

  /**
   * Find a WP user's standalone (not already in any bundle) active individual
   * memberships whose tier is eligible for this bundle's config — the
   * discovery step behind the bundle-side "Add Member" existing-membership
   * option (mirrors add_member()'s mode = 'existing' path, but read-only and
   * queried from the person's side rather than by a known membership post ID).
   *
   * @param int $user_id WP user ID (already resolved from an MDP person_uuid by the caller).
   * @return array Each entry: membership_post_id, tier_post_id, tier_name, starts_at, ends_at, status.
   */
  public function get_eligible_memberships_for_user( int $user_id ): array {
    $valid_statuses = self::EXISTING_MEMBER_STATUSES;

    $posts = get_posts( [
      'post_type'      => Helper::get_membership_cpt_slug(),
      'post_status'    => 'publish',
      'posts_per_page' => -1,
      'fields'         => 'ids',
      'meta_query'     => [
        'relation' => 'AND',
        [ 'key' => 'user_id',           'value' => $user_id ],
        [ 'key' => 'membership_type',   'value' => 'individual' ],
        [ 'key' => 'membership_status', 'value' => $valid_statuses, 'compare' => 'IN' ],
      ],
    ] );

    $memberships = [];
    foreach ( $posts as $membership_post_id ) {
      // Exclude memberships already linked to a bundle (this one or any
      // other) — a membership already in a bundle is moved via
      // move_individual_membership(), not re-offered here as "existing."
      // Selecting one through this flow would call add_member(existing),
      // which cancels it under its current bundle with no warning. Cast +
      // compare against 0, matching assert_individual_membership_in_bundle()'s
      // own convention, since the meta row may exist but be empty/'0'
      // rather than absent.
      $linked_bundle_id = (int) get_post_meta( $membership_post_id, 'membership_bundle_id', true );
      if ( $linked_bundle_id !== 0 ) {
        continue;
      }

      $tier_post_id = (int) get_post_meta( $membership_post_id, 'membership_tier_post_id', true );
      if ( ! Membership_Bundle_Config::is_tier_eligible_for_bundle( $this, $tier_post_id ) ) {
        continue;
      }

      $memberships[] = [
        'membership_post_id' => $membership_post_id,
        'tier_post_id'       => $tier_post_id,
        'tier_name'          => (string) get_post_meta( $membership_post_id, 'membership_tier_name', true ),
        'starts_at'          => (string) get_post_meta( $membership_post_id, 'membership_starts_at', true ),
        'ends_at'            => (string) get_post_meta( $membership_post_id, 'membership_ends_at', true ),
        'status'             => (string) get_post_meta( $membership_post_id, 'membership_status', true ),
      ];
    }

    return $memberships;
  }

  /**
   * Add an individual membership to this bundle.
   *
   * Single entry point for both the "new member" and "existing member" flows:
   *
   * - New member:      pass $user_id; omit $existing_membership_post_id.
   * - Existing member: pass $existing_membership_post_id; the existing membership is
   *                    cancelled first and its user_id is carried forward automatically.
   *                    $user_id is ignored when $existing_membership_post_id is provided.
   *
   * Bundle must be in pending, active, or delayed status. The new membership's start
   * date is calculated from today relative to the bundle date window (see
   * resolve_member_start_date()).
   *
   * @param int|null    $user_id                     WP user ID. Required when $existing_membership_post_id is null.
   * @param int         $tier_post_id                Post ID of the individual Membership_Tier CPT.
   * @param int|null    $product_id                  WC parent product ID. Auto-resolved from tier when null (fails if tier has >1 product).
   * @param int|null    $variation_id                WC variation ID. When provided, stored as membership_product_id instead of parent product_id, matching the subscription-driven membership flow.
   * @param int|null    $existing_membership_post_id Existing wicket_membership post ID to cancel before creating the new record.
   * @param bool        $is_renewal                  When true, skips MDP create call and subscription line item creation (renewal batch handler manages both).
   * @param string|null $start_date_override         ISO 8601 start date. When provided, bypasses resolve_member_start_date(). Used by renewal batch handler to anchor new memberships to the bundle's starts_at rather than the current timestamp.
   * @return int|\WP_Error New membership post ID on success, WP_Error on failure.
   */
  public function add_member(
    ?int $user_id,
    int $tier_post_id,
    ?int $product_id = null,
    ?int $variation_id = null,
    ?int $existing_membership_post_id = null,
    bool $is_renewal = false,
    ?string $start_date_override = null,
    bool $skip_status_guard = false
  ): int|\WP_Error {
    // $skip_status_guard is for the import path only: it lets historical members attach
    // to bundles imported as expired/cancelled/grace-period. Do not expose this bypass
    // to any other caller — it defeats assert_bundle_is_manageable() intentionally.
    if ( ! $skip_status_guard && ( $err = $this->assert_bundle_is_manageable() ) ) {
      return $err;
    }

    // Checks that can fail run before the existing-path cancel below, which has no rollback.
    if ( $existing_membership_post_id !== null && ( $err = $this->assert_existing_membership_is_convertible( $existing_membership_post_id ) ) ) {
      return $err;
    }

    if ( ! ( new Membership_Tier( $tier_post_id ) )->is_individual_tier() ) {
      Wicket()->log()->error( 'Membership_Bundle::add_member: tier_post_id does not resolve to an individual tier', [
        'source'       => 'wicket-memberships',
        'post_id'      => $this->post_id,
        'tier_post_id' => $tier_post_id,
      ] );
      return new \WP_Error( 'invalid_tier', __( 'The specified tier is not an individual membership tier.', 'wicket-memberships' ) );
    }

    // Renewals skip the MDP tier check: the batch handler owns MDP sync for them.
    if ( ! $is_renewal && ( $err = $this->assert_tier_resolves_in_mdp( $tier_post_id ) ) ) {
      return $err;
    }

    // Use explicit override when provided (renewal path — anchors to bundle starts_at).
    // Otherwise resolve dynamically relative to today vs bundle date window.
    if ( $start_date_override !== null ) {
      $start_date = $start_date_override;
    } else {
      $start_date = $this->resolve_member_start_date();
      if ( is_wp_error( $start_date ) ) {
        return $start_date;
      }
    }

    if ( $existing_membership_post_id !== null ) {
      $this->cancel_individual_membership( $existing_membership_post_id );
      $user_id = (int) get_post_meta( $existing_membership_post_id, 'user_id', true );
    }

    // New-member path: user_id must be supplied.
    if ( $user_id === null ) {
      return new \WP_Error( 'missing_user_id', __( 'user_id is required when not providing an existing membership.', 'wicket-memberships' ) );
    }

    return $this->provision_individual_membership_record( $user_id, $tier_post_id, $product_id, $variation_id, $start_date, $this->post_id, $is_renewal );
  }

  /**
   * Move an individual membership from this bundle to a target bundle.
   *
   * Cancels the source individual membership and creates a new one linked to the
   * target bundle, inheriting the same user, tier, and product. Start date is
   * resolved against the target bundle's date window.
   *
   * If creation of the new membership fails after cancellation, a WP_Error is returned
   * with an explicit message. No rollback is attempted — the admin must manually
   * re-add the member if this occurs.
   *
   * @param int              $membership_post_id Post ID of the individual membership to move.
   * @param Membership_Bundle $target_bundle       The bundle to move the member into.
   * @return int|\WP_Error New membership post ID on success, WP_Error on failure.
   */
  public function move_individual_membership( int $membership_post_id, Membership_Bundle $target_bundle ): int|\WP_Error {
    if ( $err = $this->assert_bundle_is_manageable() ) {
      return $err;
    }

    if ( $err = $target_bundle->assert_bundle_is_manageable() ) {
      return $err;
    }

    if ( $err = $this->assert_individual_membership_in_bundle( $membership_post_id ) ) {
      return $err;
    }

    $meta = $this->extract_individual_membership_meta( $membership_post_id );
    if ( is_wp_error( $meta ) ) {
      return $meta;
    }

    $start_date = $target_bundle->resolve_member_start_date();
    if ( is_wp_error( $start_date ) ) {
      return $start_date;
    }

    // Cancellation has no rollback, so verify the member does not already hold a
    // non-cancelled membership with the same tier in the target bundle before
    // touching anything. A cancelled record is ignored — it represents a past
    // membership and must not block a legitimate move.
    $duplicate = get_posts( [
      'post_type'      => Helper::get_membership_cpt_slug(),
      'post_status'    => 'any',
      'posts_per_page' => 1,
      'fields'         => 'ids',
      'meta_query'     => [
        'relation' => 'AND',
        [ 'key' => 'membership_bundle_id',     'value' => $target_bundle->post_id ],
        [ 'key' => 'user_id',                 'value' => $meta['user_id'] ],
        [ 'key' => 'membership_tier_post_id', 'value' => $meta['tier_post_id'] ],
        [ 'key' => 'membership_status',       'value' => Wicket_Memberships::STATUS_CANCELLED, 'compare' => '!=' ],
      ],
    ] );

    if ( ! empty( $duplicate ) ) {
      return new \WP_Error(
        'duplicate_membership_in_target_bundle',
        __( 'This member already has an active membership with the same tier in the destination bundle.', 'wicket-memberships' )
      );
    }

    if ( $err = $target_bundle->assert_tier_resolves_in_mdp( (int) $meta['tier_post_id'] ) ) {
      return $err;
    }

    $this->cancel_individual_membership( $membership_post_id );

    $line_item_result = $this->remove_subscription_line_item( $membership_post_id );
    if ( is_wp_error( $line_item_result ) ) {
      Wicket()->log()->error( 'Membership_Bundle::move_individual_membership: could not remove subscription line item from source bundle', [
        'source'             => 'wicket-memberships',
        'source_bundle_id'    => $this->post_id,
        'membership_post_id' => $membership_post_id,
        'error'              => $line_item_result->get_error_message(),
      ] );
    }

    $result = $target_bundle->provision_individual_membership_record(
      $meta['user_id'],
      $meta['tier_post_id'],
      $meta['product_id'],
      $meta['variation_id'],
      $start_date,
      $target_bundle->post_id
    );

    if ( is_wp_error( $result ) ) {
      Wicket()->log()->error( 'Membership_Bundle::move_individual_membership: new membership creation failed after cancellation', [
        'source'              => 'wicket-memberships',
        'source_bundle_id'     => $this->post_id,
        'target_group_id'     => $target_bundle->post_id,
        'old_membership_post' => $membership_post_id,
        'error'               => $result->get_error_message(),
      ] );
      return new \WP_Error(
        $result->get_error_code(),
        __( 'The source membership was cancelled but the new membership could not be created. Please manually re-add the member to the target bundle.', 'wicket-memberships' )
      );
    }

    return $result;
  }

  // ---------------------------------------------------------------------------
  // Private helpers shared by add_member, remove_member, move_individual_membership
  // ---------------------------------------------------------------------------

  /**
   * Assert this bundle is in a status that allows member operations (pending/active/delayed),
   * and — outside bypass_wicket dev/test mode — that it has actually been synced to the MDP.
   *
   * @return \WP_Error|null WP_Error on failure, null on pass.
   */
  private function assert_bundle_is_manageable(): ?\WP_Error {
    $allowed = [ Wicket_Memberships::STATUS_PENDING, Wicket_Memberships::STATUS_ACTIVE, Wicket_Memberships::STATUS_DELAYED ];
    if ( ! \in_array( $this->get_membership_status(), $allowed, true ) ) {
      Wicket()->log()->error( 'Membership_Bundle::assert_bundle_is_manageable: bundle status does not allow member operations', [
        'source'  => 'wicket-memberships',
        'post_id' => $this->post_id,
        'status'  => $this->get_membership_status(),
      ] );
      return new \WP_Error( 'invalid_bundle_status', __( 'Member operations are only allowed on a pending, active, or delayed membership bundle.', 'wicket-memberships' ) );
    }

    // A bundle with no MDP-side counterpart (sync_mdp_create() never ran, or ran
    // and silently failed — see its error log) cannot accept members: MDP rejects
    // the person-to-bundle assignment with a "membership not found" error deep in
    // the request, which previously surfaced late as an opaque failure instead of
    // failing fast here. bypass_wicket dev/test environments never sync to MDP by
    // design, so this check does not apply to them.
    if ( empty( $this->bypass_wicket ) && empty( get_post_meta( $this->post_id, 'membership_bundle_mdp_uuid', true ) ) ) {
      Wicket()->log()->error( 'Membership_Bundle::assert_bundle_is_manageable: bundle has no membership_bundle_mdp_uuid — not synced to MDP', [
        'source'  => 'wicket-memberships',
        'post_id' => $this->post_id,
      ] );
      return new \WP_Error( 'bundle_not_synced_to_mdp', __( 'This membership bundle has not been synced to the MDP and cannot accept members. Contact support.', 'wicket-memberships' ) );
    }

    return null;
  }

  /**
   * Assert a membership can be converted into a seat via add_member()'s existing path:
   * a standalone individual membership with a user, in a pending/active/delayed status.
   * Mirrors the filter in get_eligible_memberships_for_user().
   *
   * @param int $membership_post_id Existing wicket_membership post ID.
   * @return \WP_Error|null WP_Error on failure, null on pass.
   */
  private function assert_existing_membership_is_convertible( int $membership_post_id ): ?\WP_Error {
    $log_context = [
      'source'             => 'wicket-memberships',
      'post_id'            => $this->post_id,
      'membership_post_id' => $membership_post_id,
    ];

    if ( ! get_post( $membership_post_id ) || get_post_type( $membership_post_id ) !== Helper::get_membership_cpt_slug() ) {
      Wicket()->log()->error( 'Membership_Bundle::add_member: existing_membership_post_id does not resolve to a valid membership', $log_context );
      return new \WP_Error( 'invalid_membership', __( 'The specified membership record does not exist.', 'wicket-memberships' ) );
    }

    if ( (int) get_post_meta( $membership_post_id, 'user_id', true ) <= 0 ) {
      Wicket()->log()->error( 'Membership_Bundle::add_member: existing membership has no user_id meta', $log_context );
      return new \WP_Error( 'missing_user_id', __( 'The existing membership record does not have a valid user_id.', 'wicket-memberships' ) );
    }

    if ( get_post_meta( $membership_post_id, 'membership_type', true ) !== 'individual' ) {
      Wicket()->log()->error( 'Membership_Bundle::add_member: existing membership is not an individual membership', $log_context );
      return new \WP_Error( 'invalid_membership_type', __( 'Only an individual membership can be added to a membership bundle.', 'wicket-memberships' ) );
    }

    // Cast + compare against 0: the meta row may exist but be empty/'0'.
    if ( (int) get_post_meta( $membership_post_id, 'membership_bundle_id', true ) !== 0 ) {
      Wicket()->log()->error( 'Membership_Bundle::add_member: existing membership is already in a bundle', $log_context );
      return new \WP_Error( 'membership_already_in_bundle', __( 'This membership already belongs to a membership bundle. Move it from that bundle instead.', 'wicket-memberships' ) );
    }

    $status = get_post_meta( $membership_post_id, 'membership_status', true );
    if ( ! \in_array( $status, self::EXISTING_MEMBER_STATUSES, true ) ) {
      Wicket()->log()->error( 'Membership_Bundle::add_member: existing membership status does not allow conversion', $log_context + [ 'status' => $status ] );
      return new \WP_Error( 'invalid_membership_status', __( 'Only a pending, active, or delayed membership can be added to a membership bundle.', 'wicket-memberships' ) );
    }

    return null;
  }

  /**
   * Assert a tier's stored MDP UUID resolves to a real "memberships" resource
   * in the MDP, so a stale UUID (e.g. from a reseeded MDP environment) fails
   * fast with a clear, tier-specific error instead of the opaque MDP error
   * that person_memberships assignment would otherwise return.
   *
   * Delegates to the base plugin's wicket_get_membership_by_uuid(), which
   * distinguishes a confirmed 404 from any other MDP failure — this method
   * maps that distinction onto its own tier-specific error codes.
   *
   * @param int $tier_post_id WP post ID of the individual Membership_Tier CPT.
   * @return \WP_Error|null WP_Error on failure (`tier_not_found_in_mdp` for a
   *   confirmed stale UUID, `mdp_unreachable` for a transient MDP failure),
   *   null on pass or when the check cannot run (bypass_wicket mode, helper
   *   unavailable, or the tier has no mdp_tier_uuid stored yet — an unrelated,
   *   pre-existing failure mode this method does not own).
   */
  private function assert_tier_resolves_in_mdp( int $tier_post_id ): ?\WP_Error {
    if ( ! empty( $this->bypass_wicket ) ) {
      return null;
    }

    $tier      = new Membership_Tier( $tier_post_id );
    $tier_uuid = $tier->get_mdp_tier_uuid();
    if ( empty( $tier_uuid ) || ! \function_exists( 'wicket_get_membership_by_uuid' ) ) {
      return null;
    }

    $result = wicket_get_membership_by_uuid( $tier_uuid );

    if ( ! is_wp_error( $result ) ) {
      return null;
    }

    // Only a confirmed 404 (wicket_membership_not_found) means the stored
    // UUID is actually stale. Anything else (client unavailable, timeout,
    // 5xx, rate limit — wicket_api_error) is a transient MDP problem, not a
    // bad tier reference — misreporting it as one blocks adds on a wrong fix.
    if ( 'wicket_membership_not_found' !== $result->get_error_code() ) {
      Wicket()->log()->error( 'Membership_Bundle::assert_tier_resolves_in_mdp: MDP request failed (not a stale tier)', [
        'source'       => 'wicket-memberships',
        'post_id'      => $this->post_id,
        'tier_post_id' => $tier_post_id,
        'tier_uuid'    => $tier_uuid,
        'error'        => $result->get_error_message(),
      ] );
      return new \WP_Error(
        'mdp_unreachable',
        __( 'The MDP could not be reached to verify this membership tier. Please try again.', 'wicket-memberships' )
      );
    }

    Wicket()->log()->error( 'Membership_Bundle::assert_tier_resolves_in_mdp: tier mdp_tier_uuid does not resolve in MDP', [
      'source'       => 'wicket-memberships',
      'post_id'      => $this->post_id,
      'tier_post_id' => $tier_post_id,
      'tier_uuid'    => $tier_uuid,
      'error'        => $result->get_error_message(),
    ] );
    return new \WP_Error(
      'tier_not_found_in_mdp',
      sprintf(
        /* translators: %s: membership tier name */
        __( 'The membership tier "%s" is not recognized by the MDP (its stored MDP reference no longer resolves). Contact support to re-sync this tier.', 'wicket-memberships' ),
        $tier->get_mdp_tier_name() ?: $tier_post_id
      )
    );
  }

  /**
   * Assert an individual membership post exists, is the correct CPT, and belongs to this bundle.
   *
   * @param int $membership_post_id
   * @return \WP_Error|null WP_Error on failure, null on pass.
   */
  private function assert_individual_membership_in_bundle( int $membership_post_id ): ?\WP_Error {
    if ( ! get_post( $membership_post_id ) || get_post_type( $membership_post_id ) !== Helper::get_membership_cpt_slug() ) {
      return new \WP_Error( 'invalid_membership', __( 'The specified membership record does not exist.', 'wicket-memberships' ) );
    }

    $linked_bundle_id = (int) get_post_meta( $membership_post_id, 'membership_bundle_id', true );
    if ( $linked_bundle_id !== $this->post_id ) {
      return new \WP_Error( 'membership_not_in_bundle', __( 'The specified membership does not belong to this bundle.', 'wicket-memberships' ) );
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
   * Cancel a single individual membership that belongs to this bundle.
   *
   * Performs the full local cancellation sequence: date collapse per current status,
   * update_local_membership_record(), update_membership_status(), amend_membership_json(),
   * then cancels the membership's own WC subscription (if any).
   * Mirrors the logic in Admin_Controller::bundle_admin_manage_status() for cancelled status.
   *
   * $sync_mdp controls whether the cancellation is pushed to MDP:
   *
   * - Pass true  when cancelling a SINGLE member (remove_member, move_individual_membership,
   *   add_member with existing_membership_post_id). MDP does not know about these single-member
   *   operations from the bundle level — we must notify it directly.
   *
   * - Pass false when called from cascade_status_to_members() during a bundle-level cancel.
   *   MDP propagates the cancellation automatically from the bundle org record — a per-member
   *   call would be redundant and risk double-processing.
   *
   * When $sync_mdp is true, the MDP call itself branches on whether the membership being
   * cancelled has a `membership_bundle_id` — i.e. whether it's an MDP `person_memberships`
   * record created under a bundle assignment (via wicket_assign_person_to_bundle_membership(),
   * see Membership_Controller::create_mdp_record()) rather than a plain standalone one:
   *
   * - Bundle-linked: deleted outright via wicket_delete_person_membership(). A bundle seat has
   *   no meaning outside the bundle, so removing the member is an unassignment, not a dated
   *   cancellation — collapsing its dates via wicket_update_individual_membership_dates() (as
   *   used to happen here unconditionally) leaves a live `person_memberships` record in MDP
   *   that never gets removed, which is why members previously stayed visible there after
   *   being removed from a bundle.
   *
   * - Not bundle-linked (e.g. add_member's existing_membership_post_id path, which may cancel a
   *   standalone membership that was never in any bundle): the original date-collapse update
   *   behavior is preserved, matching how non-bundle membership cancellation already works.
   *
   * @param int  $membership_post_id
   * @param bool $sync_mdp Whether to push the cancellation to MDP. Default true.
   */
  private function cancel_individual_membership( int $membership_post_id, bool $sync_mdp = true ): void {
    $mc             = new Membership_Controller();
    $now            = Utilities::get_mdp_now()->format( 'c' );
    $yesterday      = Utilities::get_mdp_day_start( '-1 day' )->format( 'c' );
    $current_status = get_post_meta( $membership_post_id, 'membership_status', true );

    // Collapse dates per current status — mirrors Admin_Controller::bundle_admin_manage_status() rules.
    // pending/delayed: back-date starts_at so the record is unambiguously in the past.
    // grace-period: only collapse expires_at; ends_at has already passed.
    // all others (active, expired, etc.): collapse both ends_at and expires_at to now.
    if ( in_array( $current_status, [ Wicket_Memberships::STATUS_PENDING, Wicket_Memberships::STATUS_DELAYED ], true ) ) {
      $meta_data = [
        'membership_starts_at'         => $yesterday,
        'membership_ends_at'           => $now,
        'membership_expires_at'        => $now,
        'membership_grace_period_days' => 0,
      ];
    } elseif ( $current_status === Wicket_Memberships::STATUS_GRACE ) {
      $meta_data = [
        'membership_expires_at'        => $now,
        'membership_grace_period_days' => 0,
      ];
    } else {
      $meta_data = [
        'membership_ends_at'           => $now,
        'membership_expires_at'        => $now,
        'membership_grace_period_days' => 0,
      ];
    }

    // update_local_membership_record() recalculates status from dates — it would derive
    // 'expired' given collapsed dates. Call update_membership_status() after to stamp
    // 'cancelled' on top of whatever update_local_membership_record() wrote.
    $mc->update_local_membership_record( $membership_post_id, $meta_data );
    $mc->update_membership_status( $membership_post_id, Wicket_Memberships::STATUS_CANCELLED );
    $mc->amend_membership_json( $membership_post_id, array_merge( $meta_data, [ 'membership_status' => Wicket_Memberships::STATUS_CANCELLED ] ) );

    // Cancel the individual membership's own WC subscription — mirrors Admin_Controller::bundle_admin_manage_status().
    // Without this, the original personal subscription keeps renewing after the membership moves into the bundle.
    if ( function_exists( 'wcs_get_subscription' ) ) {
      $subscription_id = get_post_meta( $membership_post_id, 'membership_subscription_id', true );
      $sub             = ! empty( $subscription_id ) ? wcs_get_subscription( $subscription_id ) : false;
      if ( ! empty( $sub ) ) {
        $sub->update_status( 'cancelled' );
        if ( ! empty( $meta_data['membership_ends_at'] ) && $current_status !== Wicket_Memberships::STATUS_GRACE ) {
          // End of the membership's MDP end day, as UTC (what WCS expects).
          $subscription_end = Utilities::get_mdp_day_end( $meta_data['membership_ends_at'] );
          $sub->update_dates( [
            'end' => $subscription_end->format( 'Y-m-d H:i:s' ),
          ] );
        }
        $sub->update_dates( [
          'next_payment' => 0,
        ] );
        $sub->save();
      }
    }

    // Only sync to MDP for single-member operations. Bundle-level cancellations propagate
    // automatically from the bundle org record — per-member calls would be redundant.
    if ( $sync_mdp ) {
      $wicket_uuid = get_post_meta( $membership_post_id, 'membership_wicket_uuid', true );
      if ( ! empty( $wicket_uuid ) && empty( $this->bypass_wicket ) ) {
        // A bundle-linked seat's MDP record is a person_memberships assignment created via
        // wicket_assign_person_to_bundle_membership() (see create_mdp_record()) — it represents
        // membership in this bundle specifically, so removing the member means deleting that
        // assignment, not date-collapsing a record meant to be kept around. Check the meta on
        // membership_post_id itself, not $this->post_id: this method also cancels standalone
        // memberships that were never in any bundle (add_member's existing_membership_post_id
        // path), which must keep the original update-dates behavior instead.
        $linked_bundle_id = (int) get_post_meta( $membership_post_id, 'membership_bundle_id', true );

        if ( $linked_bundle_id > 0 ) {
          if ( function_exists( 'wicket_delete_person_membership' ) ) {
            $response = wicket_delete_person_membership( $wicket_uuid );
            if ( is_wp_error( $response ) ) {
              Wicket()->log()->error( 'Membership_Bundle::cancel_individual_membership: failed to delete bundle-linked person_membership from MDP', [
                'source'             => 'wicket-memberships',
                'bundle_post_id'     => $this->post_id,
                'membership_post_id' => $membership_post_id,
                'wicket_uuid'        => $wicket_uuid,
                'error'              => $response->get_error_message(),
              ] );
            }
          } else {
            Wicket()->log()->error( 'Membership_Bundle::cancel_individual_membership: wicket_delete_person_membership() not available, skipping MDP unassignment', [
              'source'             => 'wicket-memberships',
              'bundle_post_id'     => $this->post_id,
              'membership_post_id' => $membership_post_id,
            ] );
          }
        } else {
          $membership_data = [
            'membership_type'        => get_post_meta( $membership_post_id, 'membership_type', true ),
            'membership_wicket_uuid' => $wicket_uuid,
            'membership_starts_at'   => get_post_meta( $membership_post_id, 'membership_starts_at', true ),
            'org_seats'              => get_post_meta( $membership_post_id, 'org_seats', true ),
          ];
          $mc->update_mdp_record( $membership_data, $meta_data );
        }
      } elseif ( empty( $wicket_uuid ) ) {
        Wicket()->log()->error( 'Membership_Bundle::cancel_individual_membership: no membership_wicket_uuid, skipping MDP sync', [
          'source'             => 'wicket-memberships',
          'bundle_post_id'     => $this->post_id,
          'membership_post_id' => $membership_post_id,
        ] );
      }
    }
  }

  /**
   * Resolve the start date for a new member being added to this bundle.
   *
   * Rules per CURRENT_SCOPE.md:
   * - today is within bundle start→end  → today (UTC)
   * - today is before bundle start      → bundle start date
   * - today is after bundle end         → WP_Error (cannot add members after bundle ends)
   *
   * @return string|\WP_Error ISO 8601 UTC start date string on success, WP_Error on failure.
   */
  private function resolve_member_start_date(): string|\WP_Error {
    $dates = $this->get_dates();
    if ( empty( $dates['starts_at'] ) || empty( $dates['ends_at'] ) ) {
      Wicket()->log()->error( 'Membership_Bundle::resolve_member_start_date: bundle has no dates set', [
        'source'  => 'wicket-memberships',
        'post_id' => $this->post_id,
      ] );
      return new \WP_Error( 'bundle_no_dates', __( 'The membership bundle does not have dates configured.', 'wicket-memberships' ) );
    }

    $now       = new \DateTime( 'now', new \DateTimeZone( 'UTC' ) );
    $bundle_end = new \DateTime( $dates['ends_at'], new \DateTimeZone( 'UTC' ) );

    // Reject if today is past the bundle end date — no new members after the window closes.
    if ( $now > $bundle_end ) {
      Wicket()->log()->error( 'Membership_Bundle::resolve_member_start_date: today is after bundle end date', [
        'source'   => 'wicket-memberships',
        'post_id'  => $this->post_id,
        'today'    => $now->format( 'Y-m-d\TH:i:s\Z' ),
        'ends_at'  => $dates['ends_at'],
      ] );
      return new \WP_Error( 'bundle_ended', __( 'Individuals cannot be added to a membership bundle after the bundle end date.', 'wicket-memberships' ) );
    }

    $bundle_start = new \DateTime( $dates['starts_at'], new \DateTimeZone( 'UTC' ) );

    if ( $now < $bundle_start ) {
      return $dates['starts_at'];
    }

    return $now->format( 'Y-m-d\TH:i:s\Z' );
  }

  /**
   * Create an individual membership record linked to a bundle.
   *
   * Used when adding a member to a bundle. Creates only the MDP record and WP post —
   * no personal WC order or subscription, because the bundle's subscription covers billing.
   *
   * @param int      $user_id          WP user ID of the new member.
   * @param int      $tier_post_id     Post ID of the individual Membership_Tier CPT.
   * @param int|null $product_id       WC parent product ID. Auto-resolved from tier when null.
   * @param int|null $variation_id     WC variation ID. When set, stored as membership_product_id.
   * @param string   $start_date       ISO 8601 UTC start date string.
   * @param int      $link_to_bundle_id Bundle post ID to store in membership_bundle_id meta. Required.
   * @return int|\WP_Error New membership post ID on success, WP_Error on failure.
   */
  private function provision_individual_membership_record( int $user_id, int $tier_post_id, ?int $product_id, ?int $variation_id, string $start_date, int $link_to_bundle_id, bool $is_renewal = false ): int|\WP_Error {
    $user = get_user_by( 'id', $user_id );
    if ( ! $user ) {
      Wicket()->log()->error( 'Membership_Bundle::provision_individual_membership_record: invalid user_id', [
        'source'  => 'wicket-memberships',
        'post_id' => $this->post_id,
        'user_id' => $user_id,
      ] );
      return new \WP_Error( 'invalid_user', __( 'The specified user does not exist.', 'wicket-memberships' ) );
    }

    $tier = new Membership_Tier( $tier_post_id );
    if ( ! $tier->is_individual_tier() ) {
      Wicket()->log()->error( 'Membership_Bundle::provision_individual_membership_record: tier_post_id does not resolve to an individual tier', [
        'source'       => 'wicket-memberships',
        'post_id'      => $this->post_id,
        'tier_post_id' => $tier_post_id,
      ] );
      return new \WP_Error( 'invalid_tier', __( 'The specified tier is not an individual membership tier.', 'wicket-memberships' ) );
    }

    $tier_product_ids = array_map( 'intval', $tier->get_product_ids() );

    if ( $product_id === null ) {
      if ( \count( $tier_product_ids ) > 1 ) {
        Wicket()->log()->error( 'Membership_Bundle::provision_individual_membership_record: tier has multiple products; product_id must be specified explicitly', [
          'source'           => 'wicket-memberships',
          'post_id'          => $this->post_id,
          'tier_product_ids' => $tier_product_ids,
        ] );
        return new \WP_Error( 'ambiguous_product', __( 'The membership tier has multiple products. Specify a product_id explicitly.', 'wicket-memberships' ) );
      }
      if ( empty( $tier_product_ids ) ) {
        Wicket()->log()->error( 'Membership_Bundle::provision_individual_membership_record: tier has no products', [
          'source'  => 'wicket-memberships',
          'post_id' => $this->post_id,
        ] );
        return new \WP_Error( 'no_product', __( 'The membership tier has no products configured.', 'wicket-memberships' ) );
      }
      $product_id = $tier_product_ids[0];
    } elseif ( ! $is_renewal && ! \in_array( $product_id, $tier_product_ids, true ) ) {
      // Skipped on renewal: the renewal-order repricing phase already validated and
      // charged this exact product before payment (see
      // Membership_Bundle_Renewal_Order_Controller::reprice_bundle_renewal_line_item()) — that
      // decision must be trusted here, not re-checked against the tier's *current*
      // config, which may have changed between order creation and this batch running.
      // get_product_ids() returns parent IDs only, so a variation-configured tier fails
      // here unless the caller separately confirms $product_id via $variation_id — check
      // against the tier's own variation IDs before rejecting.
      $tier_variation_ids = array_map( 'intval', $tier->get_product_variation_ids() );
      if ( $variation_id === null || $product_id !== $variation_id || ! \in_array( $variation_id, $tier_variation_ids, true ) ) {
        Wicket()->log()->error( 'Membership_Bundle::provision_individual_membership_record: product_id is not associated with this tier', [
          'source'              => 'wicket-memberships',
          'post_id'             => $this->post_id,
          'product_id'          => $product_id,
          'variation_id'        => $variation_id,
          'tier_product_ids'    => $tier_product_ids,
          'tier_variation_ids'  => $tier_variation_ids,
        ] );
        return new \WP_Error( 'product_tier_mismatch', __( 'The specified product is not associated with this membership tier.', 'wicket-memberships' ) );
      }
    }

    $dates = $this->get_dates();

    // Seed the membership status from the bundle's own status so new members inherit
    // the bundle's lifecycle state. create_local_membership_record() will still
    // override this to 'delayed' when the start date is in the future. Tier-level
    // approval is skipped in two cases:
    //  - the bundle is already active — the bundle's own approval/activation already
    //    gated entry, so a per-tier approval step would silently strand new members
    //    in 'pending' under an active bundle.
    //  - this is a renewal — the new-term bundle always starts 'delayed' (see
    //    renew_bundle()), so the bundle-active check above never fires here, but a
    //    successfully renewing bundle is itself the re-approval event for members
    //    continuing into the new term. Without this, tiers with renew_approval_required
    //    would strand continuing members in 'pending' every single renewal cycle even
    //    though the bundle renewed cleanly.
    $skip_tier_approval = $is_renewal || $this->get_membership_status() === Wicket_Memberships::STATUS_ACTIVE;

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
      // Use bundle post ID as unique anchor — prevents check_local_membership_record_exists()
      // collision when multiple members share the same tier, starts_at, and ends_at in a
      // single batch (which would otherwise all match on parent_order_id=0).
      'membership_parent_order_id'                => $link_to_bundle_id,
      'membership_bundle_id'                      => $link_to_bundle_id,
      'membership_subscription_id'                => 0,
      'membership_grace_period_days'              => 0,
      'membership_wp_user_display_name'           => $user->display_name,
      'membership_wp_user_last_name'              => get_user_meta( $user_id, 'last_name', true ) ?: '',
      'membership_wp_user_email'                  => $user->user_email,
      'membership_user_uuid'                      => $user->user_login,
      // When the bundle has been synced to MDP, pass the bundle UUID so create_mdp_record()
      // links this person_membership to the bundle instead of creating a standalone record.
      'membership_bundle_mdp_uuid'                => (string) get_post_meta( $link_to_bundle_id, 'membership_bundle_mdp_uuid', true ),
    ], $is_renewal, false, $skip_tier_approval );

    // When the bundle is synced to MDP and the MDP member assignment failed,
    // surface the error immediately rather than silently creating an unlinked WP post.
    if ( ! empty( $result['mdp_error'] ) && ! empty( $result['membership_bundle_mdp_uuid'] ) ) {
      $mdp_post_id = (int) ( $result['membership_post_id'] ?? 0 );
      if ( $mdp_post_id > 0 ) {
        wp_delete_post( $mdp_post_id, true );
      }

      $raw_error = $result['mdp_error'];

      // Extract the MDP message field from the JSON body embedded in the Guzzle error string.
      // Falls back to a generic message when parsing fails.
      $clean_message = __( 'Failed to add member in MDP.', 'wicket-memberships' );
      if ( preg_match( '/\{.*\}/s', $raw_error, $matches ) ) {
        $body = json_decode( $matches[0], true );
        if ( ! empty( $body['message'] ) ) {
          $clean_message = sprintf(
            /* translators: %s: MDP error message */
            __( 'Failed to add member in MDP: %s', 'wicket-memberships' ),
            $body['message']
          );
        }
      }

      Wicket()->log()->error( 'Membership_Bundle::provision_individual_membership_record: MDP error', [
        'source'          => 'wicket-memberships',
        'post_id'         => $this->post_id,
        'mdp_error'       => $raw_error,
      ] );

      return new \WP_Error( 'mdp_create_failed', $clean_message, [ 'mdp_error' => $raw_error ] );
    }

    $result = apply_filters(
      'wicket_memberships_individual_membership_created_for_bundle',
      $result,
      $this,
      $user_id,
      $tier_post_id,
      $product_id
    );

    $membership_post_id = (int) ( $result['membership_post_id'] ?? 0 );

    if ( $membership_post_id <= 0 ) {
      Wicket()->log()->error( 'Membership_Bundle::provision_individual_membership_record: create_membership_record returned no post ID', [
        'source'  => 'wicket-memberships',
        'post_id' => $this->post_id,
        'user_id' => $user_id,
      ] );
      return new \WP_Error( 'create_failed', __( 'Failed to create the membership record.', 'wicket-memberships' ) );
    }

    update_post_meta( $membership_post_id, 'membership_bundle_id', $link_to_bundle_id );

    $stored_product_id = $variation_id ?? $product_id;

    // On renewal the batch handler (process_bundle_renewal_members) updates the existing
    // subscription line item in-place by swapping _membership_post_id to the new post ID.
    // Adding a new line item here would duplicate it — skip for renewals.
    if ( ! $is_renewal ) {
      $line_item_result = $this->add_subscription_line_item( $membership_post_id, $stored_product_id, $user_id );

      if ( is_wp_error( $line_item_result ) ) {
        // Non-fatal: membership record was created successfully. A missing line item is a
        // billing gap the admin can reconcile in WC admin, not a data-loss event.
        Wicket()->log()->error( 'Membership_Bundle::provision_individual_membership_record: could not add subscription line item', [
          'source'             => 'wicket-memberships',
          'post_id'            => $this->post_id,
          'membership_post_id' => $membership_post_id,
          'error'              => $line_item_result->get_error_message(),
        ] );
      }
    }

    return $membership_post_id;
  }

  /**
   * Create a fully-backed standalone individual membership for a member released from this bundle.
   *
   * Replicates the checkout-driven membership creation flow in code. Used only for the
   * keep_as_individual path in remove_member(). The resulting membership is identical to
   * what a checkout purchase produces: WC order, WC subscription, MDP record, WP post,
   * all post/order/subscription meta, and Action Scheduler lifecycle jobs.
   *
   * @param int    $user_id      WP user ID of the released member.
   * @param int    $tier_post_id Post ID of the individual Membership_Tier CPT.
   * @param int    $product_id   WC product ID (variation ID when present, else parent).
   * @param string $start_date   ISO 8601 UTC start date (today — resolved by remove_member()).
   * @param array  $bundle_dates  Date array from get_dates(): starts_at, ends_at, expires_at, early_renew_at.
   * @param string $admin_note   Note added to the WC order and subscription explaining why they were created.
   * @return int|\WP_Error New membership post ID on success, WP_Error on failure.
   */
  private function provision_standalone_individual_membership(
    int    $user_id,
    int    $tier_post_id,
    ?int   $product_id,
    string $start_date,
    array  $bundle_dates,
    string $admin_note = '',
    bool   $skip_tier_approval = false
  ): int|\WP_Error {
    if ( ! function_exists( 'wcs_create_subscription' ) ) {
      return new \WP_Error( 'wcs_unavailable', __( 'WooCommerce Subscriptions is not active.', 'wicket-memberships' ) );
    }

    $user = get_user_by( 'id', $user_id );
    if ( ! $user ) {
      return new \WP_Error( 'invalid_user', __( 'The specified user does not exist.', 'wicket-memberships' ) );
    }

    $tier = new Membership_Tier( $tier_post_id );

    // get_period_data() falls back to year/1 when config is absent, matching the
    // bundle subscription creation pattern in create_bundle_subscription().
    $config = $tier->get_config();
    $period = $config ? $config->get_period_data() : [ 'period_type' => 'year', 'period_count' => 1 ];

    // --- Step 1: Create WC order (pending) — only when inside the renewal window ---
    //
    // A pending renewal order should only be generated when the member is actually
    // inside the renewal window (at/after early_renew_at). Releasing someone from a
    // bundle mid-term is not a renewal event — provisioning an order in that case
    // would surface a spurious pending charge months before it's due. In that case
    // skip the order and create an order-less subscription instead — WC Subscriptions'
    // own core auto-renewal (wcs_create_renewal_order(), fired off the subscription's
    // own next_payment date) does not require parent_id to be set, and this plugin's
    // own bundle subscriptions (create_bundle_subscription()) are already created
    // order-less, so this is a supported, precedented shape.
    $now_ts            = current_datetime()->getTimestamp();
    $early_renew_ts    = ! empty( $bundle_dates['early_renew_at'] ) ? strtotime( $bundle_dates['early_renew_at'] ) : strtotime( $bundle_dates['ends_at'] );
    $in_renewal_window = $now_ts >= $early_renew_ts;

    $order = null;
    if ( $in_renewal_window ) {
      $order = wc_create_order( [ 'customer_id' => $user_id ] );
      if ( is_wp_error( $order ) ) {
        Wicket()->log()->error( 'Membership_Bundle::provision_standalone_individual_membership: wc_create_order failed', [
          'source'  => 'wicket-memberships',
          'post_id' => $this->post_id,
          'user_id' => $user_id,
          'error'   => $order->get_error_message(),
        ] );
        return new \WP_Error( 'order_create_failed', __( 'Failed to create the WooCommerce order.', 'wicket-memberships' ) );
      }
    }

    $wc_product = $product_id ? wc_get_product( $product_id ) : null;

    // wc_get_price_excluding_tax() is the correct source for line item pricing —
    // it returns the tax-exclusive unit price respecting WC tax settings, matching
    // what WC itself uses when building order items during checkout.
    $unit_price = $wc_product ? (float) wc_get_price_excluding_tax( $wc_product ) : 0.0;

    if ( $order && $wc_product ) {
      $item = new \WC_Order_Item_Product();
      $item->set_product( $wc_product );
      $item->set_quantity( 1 );
      // set_product() populates name/tax class/IDs but NOT price. Set subtotal and
      // total explicitly so calculate_totals() has values to sum.
      $item->set_subtotal( $unit_price );
      $item->set_total( $unit_price );
      $order->add_item( $item );
      $order->calculate_totals();
    }

    if ( $order ) {
      // Order stays pending — this is an admin-driven record, not a payment transaction.
      $order->save();

      if ( ! empty( $admin_note ) ) {
        $order->add_order_note( $admin_note );
      }
    }

    // --- Step 2: Create WC subscription explicitly ---
    //
    // WCS does NOT auto-create a subscription for programmatic orders — that only fires
    // during actual checkout via woocommerce_checkout_order_processed. We must call
    // wcs_create_subscription() explicitly. order_id is omitted (defaults to 0) when
    // there's no order — WCS core tolerates parent_id = 0 without issue.
    $sub_args = [
      'customer_id'      => $user_id,
      'status'           => 'pending',
      'billing_period'   => $period['period_type'],
      'billing_interval' => $period['period_count'],
      'start_date'       => date( 'Y-m-d H:i:s', strtotime( $bundle_dates['starts_at'] ) ),
    ];
    if ( $order ) {
      $sub_args['order_id'] = $order->get_id();
    }
    $sub = wcs_create_subscription( $sub_args );

    if ( is_wp_error( $sub ) ) {
      Wicket()->log()->error( 'Membership_Bundle::provision_standalone_individual_membership: wcs_create_subscription failed', [
        'source'   => 'wicket-memberships',
        'post_id'  => $this->post_id,
        'order_id' => $order ? $order->get_id() : 0,
        'error'    => $sub->get_error_message(),
      ] );
      return new \WP_Error( 'subscription_create_failed', __( 'Failed to create the WooCommerce subscription.', 'wicket-memberships' ) );
    }

    if ( $wc_product ) {
      $sub_item = new \WC_Order_Item_Product();
      $sub_item->set_product( $wc_product );
      $sub_item->set_quantity( 1 );
      $sub_item->set_subtotal( $unit_price );
      $sub_item->set_total( $unit_price );
      $sub->add_item( $sub_item );
      $sub->calculate_totals();
      // Persist items to DB before update_status() fires hooks that may trigger
      // an internal save and overwrite the in-memory items array.
      $sub->save();
    }

    // _requires_manual_renewal is inherited from the bundle subscription rather than
    // re-derived from user autopay preference. The released member's billing behaviour
    // should match what the bundle was configured with.
    // Guarded + global-namespace-prefixed to match the fix applied to every other
    // wcs_get_subscription() call site (avoids a fatal if WC Subscriptions is inactive).
    $bundle_sub     = function_exists( 'wcs_get_subscription' ) ? \wcs_get_subscription( $this->get_subscription_id() ) : null;
    $manual_renewal = $bundle_sub ? $bundle_sub->get_meta( '_requires_manual_renewal' ) : 'true';
    $sub->update_meta_data( '_requires_manual_renewal', $manual_renewal );

    // Mirror the _org_uuid and org_name meta that create_bundle_subscription() writes,
    // so admin screens and MDP sync can identify the organisation without a bundle post lookup.
    $org_uuid = $this->get_org_uuid();
    $org_name = get_post_meta( $this->post_id, 'org_name', true );
    if ( $org_uuid ) {
      $sub->update_meta_data( '_org_uuid', $org_uuid );
      update_post_meta( $sub->get_id(), '_org_uuid', $org_uuid );
    }
    if ( ! empty( $org_name ) ) {
      $sub->update_meta_data( 'org_name', $org_name );
      update_post_meta( $sub->get_id(), 'org_name', $org_name );
    }

    // Set subscription status to active — the member is being released from an active
    // bundle, so their standalone membership is active immediately.
    $sub->update_status( 'active' );
    if ( ! empty( $admin_note ) ) {
      $sub->add_order_note( $admin_note );
    }
    $sub->save();

    // --- Step 3: Build membership data array using bundle dates ---
    //
    // Bundle dates are used instead of $config->get_membership_dates() so the member
    // inherits the remaining bundle term rather than getting a fresh full-length term.
    // This mirrors exactly what get_memberships_data_from_subscription_products() builds
    // at lines 311–336 of Membership_Controller, substituting bundle dates in place of
    // dates recalculated from config.
    //
    // Pass the bundle's full ISO day boundaries: get_mdp_day_*() resolves an ISO instant
    // to its MDP-local day, so the member record and subscription match the bundle exactly.
    // schedule_force_set_next_payment_date() reuses the same string from $dates_to_update.
    $ends_date_only        = $bundle_dates['ends_at'];
    $expires_date_only     = ! empty( $bundle_dates['expires_at'] ) ? $bundle_dates['expires_at'] : $bundle_dates['ends_at'];
    $early_renew_date_only = ! empty( $bundle_dates['early_renew_at'] ) ? $bundle_dates['early_renew_at'] : $bundle_dates['ends_at'];

    // get_membership_array_from_order_and_product_id() just reads post meta off whatever
    // post ID it's given — it doesn't require that post to actually be a WC order. When
    // there's no order (outside the renewal window), the subscription post id is used
    // instead; the same _wicket_membership_{product_id} meta is written on both below.
    $membership = [
      'membership_parent_order_id'                 => $order ? $order->get_id() : $sub->get_id(),
      'membership_subscription_id'                 => $sub->get_id(),
      'membership_product_id'                      => $product_id,
      'membership_tier_post_id'                    => $tier_post_id,
      'membership_tier_name'                        => $tier->get_mdp_tier_name(),
      'membership_tier_uuid'                        => $tier->get_mdp_tier_uuid(),
      'membership_next_tier_id'                    => $tier->get_next_tier_id(),
      'membership_next_tier_form_page_id'          => $tier->get_next_tier_form_page_id(),
      'membership_next_tier_subscription_renewal'  => $tier->is_renewal_subscription(),
      'membership_type'                            => 'individual',
      'membership_starts_at'                       => $start_date,
      'membership_ends_at'                         => $ends_date_only,
      'membership_expires_at'                      => $expires_date_only,
      'membership_early_renew_at'                  => $early_renew_date_only,
      'membership_period'                          => $period['period_type'],
      'membership_interval'                        => $period['period_count'],
      'membership_subscription_period'             => $period['period_type'],
      'membership_subscription_interval'           => $period['period_count'],
      'membership_wp_user_id'                      => $user->ID,
      'membership_wp_user_display_name'            => $user->display_name,
      'membership_wp_user_last_name'               => get_user_meta( $user->ID, 'last_name', true ) ?: '',
      'membership_wp_user_email'                   => $user->user_email,
      'membership_user_uuid'                       => $user->user_login,
      'user_id'                                    => $user->ID,
      'person_uuid'                                => $user->user_login,
      'membership_grace_period_days'               => $config ? $config->get_late_fee_window_days() : 0,
    ];

    // Mirror the order/subscription post meta that catch_order_completed() writes at
    // lines 364–370 of Membership_Controller. The AS job callbacks and renewal triggers
    // look up membership data via this meta key on both the order and subscription.
    if ( $order ) {
      add_post_meta( $order->get_id(), '_wicket_membership_' . $product_id, wp_json_encode( $membership ), true );
    }
    add_post_meta( $sub->get_id(), '_wicket_membership_' . $product_id, wp_json_encode( $membership ), true );

    // --- Step 4: Fire wicket_member_create_record ---
    //
    // do_action is called directly instead of catch_order_completed() to bypass the
    // monthly subscription guard and use the already-resolved bundle dates. This triggers
    // Membership_Controller::create_membership_record() which handles:
    //   - MDP record creation via wicket_assign_individual_membership()
    //   - create_local_membership_record() → WP post + all post meta + user meta + wicket_update_membership_external_id()
    //   - scheduler_dates_for_expiry() → Action Scheduler lifecycle jobs
    //   - update_membership_subscription() → subscription end/next_payment via MDP timezone conversion
    //   - _membership_post_id_renew on subscription line item via wicket_update_subscription_meta_membership_post_id()
    //   - wicket_membership_created_mdp hook
    //
    // Tier-level approval is skipped only when $skip_tier_approval was set by the caller —
    // true when the member is being released from a bundle that was active at the moment of
    // release. The member was already vetted as part of an active bundle, so re-running tier
    // approval here would strand them in 'pending' despite coming from an active seat.
    do_action( 'wicket_member_create_record', $membership, false, false, $skip_tier_approval );

    // Re-apply subscription dates after do_action to correct the drift introduced by
    // update_membership_subscription() inside create_membership_record(). That method
    // passes the full UTC ISO string (e.g. "2025-12-31T23:59:59Z") to get_mdp_day_end(),
    // which parses the embedded Z timezone, shifts to MDP timezone, then pins to 23:59:59
    // — pushing the result one day forward in UTC when MDP timezone is behind UTC.
    // Fix: pass only the date portion (Y-m-d) so no embedded timezone can shift the day.
    // $ends_date_only and $expires_date_only already computed above (Step 3) as Y-m-d strings.
    //
    // next_payment only applies to the 'subscription' renewal type — matches
    // Helper::has_next_payment_date()/update_membership_subscription()'s rule that
    // current_tier/sequential_logic/form_flow tiers get no next_payment date at all.
    $sub->read_meta_data( true );
    $end_dt = Utilities::get_mdp_day_end( $expires_date_only );

    $dates_to_update = [];
    $dates_to_update['end'] = $end_dt->format( 'Y-m-d H:i:s' );
    if ( $tier->is_renewal_subscription() ) {
      $next_payment_dt = Utilities::get_mdp_day_end( $ends_date_only );
      $dates_to_update['next_payment'] = $next_payment_dt->format( 'Y-m-d H:i:s' );
      $dates_to_update = Subscription_Manager::prepare_dates( $dates_to_update, $sub );
    } else {
      $dates_to_update['next_payment'] = '';
    }

    try {
      $sub->update_dates( $dates_to_update );
      $sub->save();
    } catch ( \Exception $e ) {
      Wicket()->log()->error( 'Membership_Bundle::provision_standalone_individual_membership: could not correct subscription dates', [
        'source'          => 'wicket-memberships',
        'subscription_id' => $sub->get_id(),
        'error'           => $e->getMessage(),
      ] );
    }

    // Retrieve the post ID that create_membership_record() wrote via create_local_membership_record().
    // It is stored on the subscription as _membership_post_id_renew after the action fires.
    $membership_post_id = 0;
    foreach ( $sub->get_items() as $sub_item ) {
      $post_id_candidate = (int) $sub_item->get_meta( '_membership_post_id_renew' );
      if ( $post_id_candidate > 0 ) {
        $membership_post_id = $post_id_candidate;
        break;
      }
    }

    if ( $membership_post_id <= 0 ) {
      // Fall back to querying directly for the membership post created for this user/tier.
      $posts = get_posts( [
        'post_type'   => Helper::get_membership_cpt_slug(),
        'meta_query'  => [
          [ 'key' => 'user_id',                  'value' => $user_id ],
          [ 'key' => 'membership_subscription_id', 'value' => $sub->get_id() ],
        ],
        'numberposts' => 1,
        'fields'      => 'ids',
      ] );
      $membership_post_id = ! empty( $posts ) ? (int) $posts[0] : 0;
    }

    if ( $membership_post_id <= 0 ) {
      Wicket()->log()->error( 'Membership_Bundle::provision_standalone_individual_membership: could not resolve membership_post_id after create', [
        'source'          => 'wicket-memberships',
        'post_id'         => $this->post_id,
        'user_id'         => $user_id,
        'order_id'        => $order->get_id(),
        'subscription_id' => $sub->get_id(),
      ] );
      return new \WP_Error( 'membership_post_not_found', __( 'Standalone membership was provisioned but the resulting post ID could not be resolved.', 'wicket-memberships' ) );
    }

    return $membership_post_id;
  }

  /**
   * Add a WooCommerce subscription line item for an individual membership.
   *
   * Called after provision_individual_membership_record() succeeds. The line item
   * ties the bundle subscription to the individual membership record so billing and
   * renewal flows can identify which memberships are covered. Failure is non-fatal —
   * the caller logs and continues.
   *
   * $recalculate_totals controls whether calculate_totals() runs immediately after the line
   * item is added. Pass false when adding many members in a batch (e.g. bulk CSV import) to
   * avoid recalculating on every single add — the caller is responsible for calling
   * calculate_totals() + save() once after all members are added. Leave true (default) for
   * single-member adds so the subscription total is always persisted correctly.
   *
   * Additional line item meta (e.g. a client-specific member identifier) can be added via
   * the `wicket_mship_bundle_line_item_meta` filter.
   *
   * @param int  $membership_post_id   Post ID of the newly created individual membership.
   * @param int  $product_id           WC product ID to add (variation ID when present, else parent).
   * @param int  $user_id             WP user ID of the member (for _member_name meta).
   * @param bool $recalculate_totals   Whether to call calculate_totals() after adding the item.
   * @return int|\WP_Error             WC order item ID on success, WP_Error on failure.
   */
  private function add_subscription_line_item( int $membership_post_id, int $product_id, int $user_id, bool $recalculate_totals = true ): int|\WP_Error {
    if ( ! function_exists( 'wcs_get_subscription' ) ) {
      return new \WP_Error( 'wcs_unavailable', __( 'WooCommerce Subscriptions is not active.', 'wicket-memberships' ) );
    }

    $sub_id = $this->get_subscription_id();
    if ( ! $sub_id ) {
      return new \WP_Error( 'no_subscription', __( 'This bundle has no linked WooCommerce subscription.', 'wicket-memberships' ) );
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
      return new \WP_Error( 'add_product_failed', __( 'Failed to add product to the bundle subscription.', 'wicket-memberships' ) );
    }

    // _membership_post_id is written outside the filter so it can never be dropped or
    // overwritten — remove_subscription_line_item() and renewal flows rely on it to find this item.
    wc_add_order_item_meta( $item_id, '_membership_post_id', $membership_post_id );

    $meta = [];
    $user = get_user_by( 'id', $user_id );
    if ( $user ) {
      $meta['_member_name'] = $user->display_name;
    }

    // Environment-specific identifiers (e.g. OBA bar ID) are injected by the theme/site
    // plugin rather than hard-coded here, since the source field differs per client.
    $meta = apply_filters( 'wicket_mship_bundle_line_item_meta', $meta, [
      'item_id'            => $item_id,
      'membership_post_id' => $membership_post_id,
      'product_id'         => $product_id,
      'user_id'            => $user_id,
      'bundle'             => $this,
    ] );

    if ( is_array( $meta ) ) {
      foreach ( $meta as $key => $value ) {
        if ( ! is_string( $key ) || '' === $key || '_membership_post_id' === $key ) {
          continue;
        }
        if ( null === $value || '' === $value ) {
          continue;
        }
        wc_add_order_item_meta( $item_id, $key, $value );
      }
    }

    // Client-specific extension point: a child theme can inject additional line-item
    // meta (e.g. a client-specific ID sourced from user meta). Core has no knowledge of
    // what gets written here — empty array in, no-op, zero cost for callers not using it.
    $extra_meta = apply_filters(
      'wicket_mship_bundle_line_item_extra_meta',
      [],
      $item_id,
      $user,
      $membership_post_id,
      $product_id
    );
    foreach ( $extra_meta as $meta_key => $meta_value ) {
      wc_add_order_item_meta( $item_id, $meta_key, $meta_value );
    }

    if ( $recalculate_totals ) {
      $sub->calculate_totals();
    }
    $sub->save();

    return $item_id;
  }

  /**
   * Remove the WooCommerce subscription line item for an individual membership.
   *
   * Scans the bundle subscription for an item whose _membership_post_id meta matches
   * $membership_post_id and removes it. Failure is non-fatal — the membership
   * cancellation has already happened; a stale line item is a billing gap the admin
   * can reconcile, not a data-loss event.
   *
   * @param int $membership_post_id Post ID of the individual membership being removed.
   * @return true|\WP_Error true on success (including no-op when no item found), WP_Error on failure.
   */
  private function remove_subscription_line_item( int $membership_post_id ): true|\WP_Error {
    if ( ! function_exists( 'wcs_get_subscription' ) ) {
      return new \WP_Error( 'wcs_unavailable', __( 'WooCommerce Subscriptions is not active.', 'wicket-memberships' ) );
    }

    $sub_id = $this->get_subscription_id();
    if ( ! $sub_id ) {
      return new \WP_Error( 'no_subscription', __( 'This bundle has no linked WooCommerce subscription.', 'wicket-memberships' ) );
    }

    $sub = wcs_get_subscription( $sub_id );
    if ( ! $sub ) {
      return new \WP_Error( 'subscription_not_found', __( 'The linked WooCommerce subscription could not be loaded.', 'wicket-memberships' ) );
    }

    foreach ( $sub->get_items() as $item_id => $item ) {
      if ( (int) $item->get_meta( '_membership_post_id' ) === $membership_post_id ) {
        $sub->remove_item( $item_id );
        $sub->calculate_totals();
        $sub->save();
        return true;
      }
    }

    // No matching line item — not an error; item may never have been added (non-fatal path).
    return true;
  }

  /**
   * Remove an individual membership from this bundle.
   *
   * Two modes:
   * - 'cancel'            → cancel the bundle-linked membership immediately.
   * - 'keep_as_individual' → cancel the bundle-linked membership and create a new
   *                          standalone individual membership (start=now, end=bundle end date).
   *
   * @param int    $membership_post_id Post ID of the individual membership to remove.
   * @param string $mode               'cancel' or 'keep_as_individual'.
   * @return int|\WP_Error Affected membership post ID on success, WP_Error on failure.
   */
  public function remove_member( int $membership_post_id, string $mode ): int|\WP_Error {
    if ( $err = $this->assert_bundle_is_manageable() ) {
      return $err;
    }

    if ( $err = $this->assert_individual_membership_in_bundle( $membership_post_id ) ) {
      return $err;
    }

    // For keep_as_individual: capture all inputs before any state changes.
    // cancel_individual_membership() and remove_subscription_line_item() may mutate
    // post meta; reads must complete first so the extracted values are authoritative.
    // Not needed for cancel mode — nothing is read after cancellation in that path.
    if ( $mode === 'keep_as_individual' ) {
      $bundle_dates = $this->get_dates();
      $meta        = $this->extract_individual_membership_meta( $membership_post_id );
      if ( is_wp_error( $meta ) ) {
        return $meta;
      }
      // resolve_member_start_date() returns WP_Error when today > bundle ends_at, which
      // blocks releases from grace-period groups. For keep_as_individual the start date
      // is always today — the member is being given a fresh standalone record right now.
      $start_date = ( new \DateTime( 'now', new \DateTimeZone( 'UTC' ) ) )->format( 'Y-m-d\TH:i:s\Z' );
    }

    $this->cancel_individual_membership( $membership_post_id );

    $line_item_result = $this->remove_subscription_line_item( $membership_post_id );
    if ( is_wp_error( $line_item_result ) ) {
      Wicket()->log()->error( 'Membership_Bundle::remove_member: could not remove subscription line item', [
        'source'             => 'wicket-memberships',
        'post_id'            => $this->post_id,
        'membership_post_id' => $membership_post_id,
        'error'              => $line_item_result->get_error_message(),
      ] );
    }

    if ( $mode === 'cancel' ) {
      return $membership_post_id;
    }

    $bundle_name = $this->get_name();
    $admin_note = sprintf(
      /* translators: 1: membership bundle name, 2: bundle post ID */
      __( 'Created automatically when member was removed from membership bundle "%1$s" (ID: %2$d) with the keep-as-individual option.', 'wicket-memberships' ),
      $bundle_name,
      $this->post_id
    );

    // Member is being released from this bundle right now — skip tier-level approval when
    // the bundle is currently active, since the member was already vetted as part of an
    // active bundle. If the bundle is only pending/delayed, the member's own approval
    // status isn't established yet, so the tier gate still applies.
    $skip_tier_approval = $this->get_membership_status() === Wicket_Memberships::STATUS_ACTIVE;

    return $this->provision_standalone_individual_membership(
      $meta['user_id'],
      $meta['tier_post_id'],
      $meta['variation_id'] ?? $meta['product_id'],
      $start_date,
      $bundle_dates,
      $admin_note,
      $skip_tier_approval
    );
  }

  // Post-level accessors.

  /**
   * Returns the post title (bundle name), or an empty string if the post is not loaded.
   */
  public function get_name(): string {
    $post = get_post( $this->post_id );
    return $post ? $post->post_title : '';
  }

  // Owner management.

  /**
   * Set the single owner for this membership bundle by MDP UUID.
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
      Wicket()->log()->error( 'Membership_Bundle: Invalid owner UUID', ['source' => 'wicket-memberships', 'post_id' => $this->post_id, 'uuid' => $uuid] );
      return false;
    }

    // Resolve or create the WP user for this MDP person.
    // In bypass mode skip MDP and resolve against local WP only.
    if ( $this->bypass_wicket ) {
      $user = get_user_by( 'login', $uuid );
    } else {
      $user_id = wicket_create_wp_user_if_not_exist( $uuid );
      $user    = $user_id ? get_user_by( 'id', $user_id ) : false;
    }

    if ( ! $user ) {
      Wicket()->log()->error( 'Membership_Bundle: Could not resolve owner UUID to WP user', ['source' => 'wicket-memberships', 'post_id' => $this->post_id, 'uuid' => $uuid] );
      return false;
    }

    // Only the WP user ID is stored. Derived fields like display name, email, and
    // MDP UUID are intentionally omitted — they can change independently of the
    // membership record and would silently go stale. Retrieve them on demand via:
    //   $user = get_user_by( 'id', $owner_id );           // WP user object
    //   wicket_get_person_by_id( $user->user_login );      // MDP person record (UUID = user_login)
    if ( update_post_meta( $this->post_id, 'user_id', $user->ID ) === false ) {
      Wicket()->log()->error( 'Membership_Bundle: Failed to save owner user_id', ['source' => 'wicket-memberships', 'post_id' => $this->post_id, 'uuid' => $uuid] );
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

    $this->sync_mdp_update();

    $this->meta_data = get_post_meta( $this->post_id );

    return $user->ID;
  }

  /**
   * Check if a given MDP UUID is the owner of this bundle.
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
   * Get a structured snapshot of the bundle owner.
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
   * Get the MDP UUID of the bundle owner.
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
   * Set the organization for this membership bundle.
   * Stores org_uuid and org_name (from organization_legal_name) as post meta.
   *
   * @param string $org_uuid The UUID of the MDP organization
   * @return array|false Returns the organization data on success, false on failure
   */
  public function set_organization( string $org_uuid ) {
    if ( ! isValidUuid( $org_uuid ) ) {
      Wicket()->log()->error( 'Membership_Bundle: Invalid org UUID', ['source' => 'wicket-memberships', 'post_id' => $this->post_id, 'org_uuid' => $org_uuid] );
      return false;
    }

    $org_data = Helper::get_org_data( $org_uuid );

    if ( empty( $org_data['name'] ) ) {
      Wicket()->log()->error( 'Membership_Bundle: Could not retrieve organization data', ['source' => 'wicket-memberships', 'post_id' => $this->post_id, 'org_uuid' => $org_uuid] );
      return false;
    }

    // org_name is denormalised here so list/detail views can display the org name
    // without an extra MDP API call on every page load. It may drift if the org is
    // renamed in MDP, but that is an acceptable trade-off given the read frequency.
    $uuid_result = update_post_meta( $this->post_id, 'org_uuid', $org_uuid );
    $name_result = update_post_meta( $this->post_id, 'org_name', $org_data['name'] );

    if ( $uuid_result === false || $name_result === false ) {
      Wicket()->log()->error( 'Membership_Bundle: Failed to save organization meta', ['source' => 'wicket-memberships', 'post_id' => $this->post_id, 'org_uuid' => $org_uuid] );
      return false;
    }

    // Keep the linked subscription's org meta in sync so billing screens and MDP
    // sync can identify the correct organisation without following the bundle post link.
    $sub_id = $this->get_subscription_id();
    if ( $sub_id && function_exists( 'wcs_get_subscription' ) ) {
      $sub = wcs_get_subscription( $sub_id );
      if ( $sub ) {
        update_post_meta( $sub_id, '_org_uuid', $org_uuid );
        $sub->update_meta_data( '_org_uuid', $org_uuid );
        $sub->update_meta_data( 'org_name', $org_data['name'] );
        $sub->save();
      }
    }

    return $org_data;
  }

  /**
   * Get the org UUID stored on this membership bundle.
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
   * Get the bundle group UUID that links all posts in the same renewal series.
   *
   * @return string|false
   */
  public function get_bundle_group_uuid() {
    $uuid = get_post_meta( $this->post_id, 'membership_bundle_group_uuid', true );
    return ! empty( $uuid ) ? $uuid : false;
  }

  /**
   * Store a bundle group UUID on this post.
   *
   * @param string $uuid
   * @return bool
   */
  public function set_bundle_group_uuid( string $uuid ): bool {
    return update_post_meta( $this->post_id, 'membership_bundle_group_uuid', $uuid ) !== false;
  }

  /**
   * Generate a new bundle group UUID for this post.
   * Format: {org_uuid}_{uuidv4} — org-traceable prefix, random suffix.
   * Requires org_uuid to already be set on the post.
   *
   * @return string|false False if org_uuid is not yet set.
   */
  private function generate_bundle_group_uuid() {
    $org_uuid = $this->get_org_uuid();
    if ( ! $org_uuid ) {
      return false;
    }
    return $org_uuid . '_' . wp_generate_uuid4();
  }

  // Configuration and commerce links.

  /**
   * Get the bundle config object linked to this bundle.
   *
   * @return Membership_Bundle_Config|false
   */
  public function get_config() {
    $config_id = (int) get_post_meta( $this->post_id, 'membership_bundle_config_id', true );
    if ( $config_id <= 0 ) {
      return false;
    }

    $config = new Membership_Bundle_Config( $config_id );
    return $config->get_post_id() > 0 ? $config : false;
  }

  /**
   * Set the linked membership bundle config for this bundle.
   *
   * @param int $config_post_id Post ID of the Membership_Bundle_Config to link.
   * @return bool True on success, false on failure.
   */
  public function set_config( int $config_post_id ): bool {
    $config = new Membership_Bundle_Config( $config_post_id );
    if ( $config->get_post_id() <= 0 ) {
      Wicket()->log()->error( 'Membership_Bundle: Invalid config post ID', ['source' => 'wicket-memberships', 'post_id' => $this->post_id, 'config_post_id' => $config_post_id] );
      return false;
    }

    if ( update_post_meta( $this->post_id, 'membership_bundle_config_id', $config_post_id ) === false ) {
      Wicket()->log()->error( 'Membership_Bundle: Failed to save membership_bundle_config_id', ['source' => 'wicket-memberships', 'post_id' => $this->post_id, 'config_post_id' => $config_post_id] );
      return false;
    }

    $this->meta_data = get_post_meta( $this->post_id );

    return true;
  }

  /**
   * Set the date fields for this membership bundle.
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
    // Past ends_at/expires_at values silently break MDP record updates, so today is the
    // earliest allowed value. Status-transition/cancellation flows collapse dates to now
    // via apply_status_transition() instead of this method, so they are unaffected.
    $mdp_today = Utilities::get_mdp_day_start()->getTimestamp();
    foreach ( [ 'ends_at', 'expires_at' ] as $date_key ) {
      if ( empty( $dates[ $date_key ] ) ) {
        continue;
      }

      if ( strtotime( $dates[ $date_key ] ) < $mdp_today ) {
        Wicket()->log()->error( 'Membership_Bundle: Rejected past date value', [
          'source'   => 'wicket-memberships',
          'post_id'  => $this->post_id,
          'date_key' => $date_key,
          'value'    => $dates[ $date_key ],
        ] );
        return false;
      }
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

      // update_post_meta() returns false both on a genuine write failure and when the
      // new value is identical to the current one (WP core no-ops the write either way).
      // Only treat it as a failure when the value actually differs.
      if ( get_post_meta( $this->post_id, $meta_key, true ) === $dates[ $date_key ] ) {
        continue;
      }

      if ( update_post_meta( $this->post_id, $meta_key, $dates[ $date_key ] ) === false ) {
        Wicket()->log()->error( 'Membership_Bundle: Failed to persist date field', [
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
   * Get the membership status for this bundle.
   *
   * @return string|false The status string, or false if not set
   */
  public function get_membership_status() {
    $status = get_post_meta( $this->post_id, 'membership_status', true );
    return ! empty( $status ) ? $status : false;
  }

  /**
   * Return allowed admin status transitions for this bundle.
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

    $non_terminal = [
      Wicket_Memberships::STATUS_ACTIVE,
      Wicket_Memberships::STATUS_DELAYED,
      Wicket_Memberships::STATUS_GRACE,
    ];

    if ( $current_status === Wicket_Memberships::STATUS_PENDING ) {
      // New bundles activate immediately on creation and skip this state, but a bundle
      // can still land in pending via manual admin action or approval-gated config.
      // Admins can activate or cancel it from here.
      $allowed_slugs = [
        Wicket_Memberships::STATUS_ACTIVE,
        Wicket_Memberships::STATUS_CANCELLED,
      ];
    } elseif ( in_array( $current_status, $non_terminal, true ) ) {
      // Expiry is driven by daily_membership_expiry_hook. Manual admin action is cancel-only.
      $allowed_slugs = [ Wicket_Memberships::STATUS_CANCELLED ];
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
   * Check whether the bundle can transition to the requested status.
   *
   * This is the full programmatic lifecycle map used by transition_to(). It is wider
   * than get_allowed_status_transitions(), which is narrowed for admin UI use only:
   * pending→active is valid when driven by subscription payment; active/grace-period→expired
   * is valid when driven by daily_membership_expiry_hook. Neither is offered in the admin UI.
   *
   * @param string $new_status
   * @return bool
   */
  public function can_transition_to( string $new_status ): bool {
    if ( ! empty( $_ENV['BYPASS_STATUS_CHANGE_LOCKOUT'] ) ) {
      return true;
    }

    $current_status = $this->get_membership_status();
    if ( ! $current_status ) {
      return false;
    }

    $lifecycle_transitions = [
      Wicket_Memberships::STATUS_PENDING      => [
        Wicket_Memberships::STATUS_ACTIVE,
        Wicket_Memberships::STATUS_CANCELLED,
      ],
      Wicket_Memberships::STATUS_ACTIVE       => [
        Wicket_Memberships::STATUS_GRACE,
        Wicket_Memberships::STATUS_EXPIRED,
        Wicket_Memberships::STATUS_CANCELLED,
      ],
      Wicket_Memberships::STATUS_DELAYED      => [
        Wicket_Memberships::STATUS_ACTIVE,
        Wicket_Memberships::STATUS_CANCELLED,
      ],
      Wicket_Memberships::STATUS_GRACE        => [
        Wicket_Memberships::STATUS_EXPIRED,
        Wicket_Memberships::STATUS_CANCELLED,
      ],
    ];

    return in_array( $new_status, $lifecycle_transitions[ $current_status ] ?? [], true );
  }

  /**
   * Cancel the bundle at its existing end date, leaving individual memberships active.
   *
   * Unlike transition_to('cancelled'), this path preserves the current ends_at so members
   * retain access until the paid period runs out. expires_at is collapsed to ends_at to
   * remove the grace period. Individual memberships are NOT cascaded — their status stays
   * active; only expires_at is updated so daily_membership_expiry_hook fires naturally.
   *
   * This method exists because plan_status_transition() always recalculates ends_at on
   * cancel (e.g. active → +1 day), making it impossible to preserve the original end date
   * through the standard transition_to() path.
   *
   * @return array{success_message: string}|false False when the bundle is not in a cancellable status.
   */
  public function transition_to_cancelled_at_end_date(): array|false {
    $cancellable = [
      Wicket_Memberships::STATUS_PENDING,
      Wicket_Memberships::STATUS_DELAYED,
      Wicket_Memberships::STATUS_ACTIVE,
      Wicket_Memberships::STATUS_GRACE,
    ];

    if ( ! in_array( $this->get_membership_status(), $cancellable, true ) ) {
      return false;
    }

    $current_ends_at = get_post_meta( $this->post_id, 'membership_ends_at', true );

    // Without an end date there is no meaningful point to defer cancellation to.
    // The controller guards this too, but enforce here so the method is self-contained.
    if ( $current_ends_at === '' || $current_ends_at === false ) {
      return false;
    }

    // Collapse expires_at to ends_at to remove the grace period. starts_at and
    // early_renew_at are preserved (null = no-op in apply_status_transition).
    $dates = [
      'starts_at'      => null,
      'ends_at'        => $current_ends_at,
      'expires_at'     => $current_ends_at,
      'early_renew_at' => null,
    ];

    if ( ! $this->apply_status_transition( Wicket_Memberships::STATUS_CANCELLED, $dates ) ) {
      return false;
    }

    $this->schedule_deferred_subscription_cancellation( $current_ends_at );

    // Collapse expires_at on each individual membership so daily_membership_expiry_hook
    // fires at ends_at. Status stays active — members keep access until that date.
    // Skip already-final members — collapsing expires_at on cancelled/expired records
    // is meaningless and could cause confusion in date-based queries.
    foreach ( $this->get_individual_memberships( false ) as $membership_post ) {
      $status = get_post_meta( $membership_post->ID, 'membership_status', true );
      if ( in_array( $status, [ Wicket_Memberships::STATUS_CANCELLED, Wicket_Memberships::STATUS_EXPIRED ], true ) ) {
        continue;
      }
      update_post_meta( $membership_post->ID, 'membership_expires_at', $current_ends_at );
    }

    $this->sync_mdp_update();

    return [ 'success_message' => 'Membership bundle cancelled. Members retain access until end date.' ];
  }

  /**
   * Cancel this bundle post as part of a renewal — marks it cancelled and cascades
   * cancelled status to all child individual memberships.
   *
   * Used exclusively by cancel_old_bundle_after_renewal() and cancel_old_bundle_on_new_starts_at().
   * Cascading cancelled to child memberships ensures past-term seats are unambiguously
   * terminal and do not appear in active-membership queries alongside the new term's seats.
   *
   * @param bool $preserve_end_date When true (early renewal path), keeps the current ends_at
   *                                 so the bundle record reflects the full term that was paid for.
   *                                 When false (same-day renewal), collapses ends_at to now.
   * @return bool True on success, false if status write failed.
   */
  public function cancel_for_renewal( bool $preserve_end_date = false ): bool {
    $now = Utilities::get_mdp_now()->format( 'c' );

    if ( $preserve_end_date ) {
      $ends_at = get_post_meta( $this->post_id, 'membership_ends_at', true ) ?: $now;
    } else {
      $ends_at = $now;
    }

    $dates = [
      'starts_at'      => null,
      'ends_at'        => $ends_at,
      'expires_at'     => $ends_at,
      'early_renew_at' => null,
    ];

    if ( ! $this->apply_status_transition( Wicket_Memberships::STATUS_CANCELLED, $dates ) ) {
      return false;
    }

    // Cascade cancelled to all child memberships so past-term seats are unambiguously
    // terminal and do not pollute active-membership queries after renewal.
    $this->cascade_status_to_members( Wicket_Memberships::STATUS_CANCELLED );

    $this->sync_mdp_update();

    return true;
  }

  /**
   * Cancel the bundle and convert every individual bundle membership to a standalone
   * individual membership, preserving the remaining bundle term for each member.
   *
   * This is Path C of the bundle cancellation flow. assert_bundle_is_manageable() blocks
   * once the bundle is cancelled, so all member data must be read before the bundle
   * transition fires. After cancellation, each existing bundle membership is cancelled and
   * a new standalone membership + WC subscription is provisioned via
   * provision_standalone_individual_membership(), which inherits the bundle dates so
   * members keep their current term rather than getting a fresh full-length period.
   *
   * Non-fatal per-member failures (missing user, WCS unavailable, order/subscription
   * creation failure) are collected in the returned `warnings` array so the caller can
   * report them without aborting the entire conversion.
   *
   * @return array{success_message: string, warnings?: string[]}|false
   *   False when the bundle transition itself fails (hard failure).
   */
  public function cancel_keep_as_individual(): array|false {
    // --- Phase 1: Read all member data before transitioning the bundle ---
    //
    // assert_bundle_is_manageable() in remove_member() and resolve_member_start_date()
    // both reject calls on a cancelled bundle, so reads must complete first.
    $individual_memberships = $this->get_individual_memberships( false );
    $bundle_dates           = $this->get_dates();

    // Captured before transition_to(STATUS_CANCELLED) below overwrites it — members are
    // being carried over from whatever this bundle's status was at the moment of
    // cancellation, so tier-level approval should skip for them under the same rule as
    // remove_member(): only when the bundle was active, not pending/delayed.
    $skip_tier_approval = $this->get_membership_status() === Wicket_Memberships::STATUS_ACTIVE;

    $members_meta = [];
    foreach ( $individual_memberships as $membership_post ) {
      $membership_post_id = $membership_post->ID;

      $user_id      = (int) get_post_meta( $membership_post_id, 'user_id', true );
      $tier_post_id = (int) get_post_meta( $membership_post_id, 'membership_tier_post_id', true );
      $product_id   = (int) get_post_meta( $membership_post_id, 'membership_product_id', true );

      // Resolve variation vs parent product — mirrors extract_individual_membership_meta().
      // provision_standalone_individual_membership() expects the variation ID when present
      // so the correct line item price and product data are used.
      $variation_id = null;
      if ( $product_id > 0 ) {
        $wc_product = wc_get_product( $product_id );
        if ( $wc_product instanceof \WC_Product_Variation ) {
          $variation_id = $product_id;
          $product_id   = (int) $wc_product->get_parent_id();
        }
      }

      $members_meta[] = [
        'membership_post_id' => $membership_post_id,
        'user_id'            => $user_id,
        'tier_post_id'       => $tier_post_id,
        'product_id'         => $product_id > 0 ? ( $variation_id ?? $product_id ) : null,
      ];
    }

    // --- Phase 2: Cancel the bundle (and its subscription) ---
    $transition_result = $this->transition_to( Wicket_Memberships::STATUS_CANCELLED );
    if ( false === $transition_result ) {
      return false;
    }

    // --- Phase 3: Per-member conversion ---
    $mc         = new Membership_Controller();
    $start_date = ( new \DateTime( 'now', new \DateTimeZone( 'UTC' ) ) )->format( 'Y-m-d\TH:i:s\Z' );
    $errors     = [];

    foreach ( $members_meta as $meta ) {
      $membership_post_id = $meta['membership_post_id'];

      // Cancel the existing bundle membership post so the member no longer appears as
      // an active bundle member. The bundle_id meta is preserved intentionally — it
      // records the origin bundle for audit purposes even after the record is cancelled.
      $mc->update_membership_status( $membership_post_id, Wicket_Memberships::STATUS_CANCELLED );

      $admin_note = sprintf(
        /* translators: 1: bundle post ID */
        __( 'Created automatically when membership bundle (ID: %d) was cancelled with the keep-as-individual option. Member continues as a standalone individual membership.', 'wicket-memberships' ),
        $this->post_id
      );

      $result = $this->provision_standalone_individual_membership(
        $meta['user_id'],
        $meta['tier_post_id'],
        $meta['product_id'],
        $start_date,
        $bundle_dates,
        $admin_note,
        $skip_tier_approval
      );

      if ( is_wp_error( $result ) ) {
        $errors[] = "membership {$membership_post_id}: " . $result->get_error_message();
      }
    }

    $response = [ 'success_message' => 'Membership bundle cancelled. Individual memberships converted to standalone.' ];
    if ( ! empty( $errors ) ) {
      $response['warnings'] = $errors;
    }
    return $response;
  }

  /**
   * Execute a bundle status transition and its side effects.
   *
   * @param string $new_status
   * @return array{success_message: string, bypassed: bool}|false
   */
  public function transition_to( string $new_status ) {
    $bypassed = ! empty( $_ENV['BYPASS_STATUS_CHANGE_LOCKOUT'] );

    // Bypass only skips the lifecycle-map guard below — it must NOT skip the
    // subscription activation / member cascade / MDP sync side effects that a
    // real transition performs, or bundles flip to active while their WC
    // subscription and children are silently left behind.
    if ( ! $bypassed && ! $this->can_transition_to( $new_status ) ) {
      return false;
    }

    // Compute the dates and side-effect flags for this specific transition path
    // before any writes, so a planning failure is still fully reversible.
    $transition_plan = $this->plan_status_transition( $new_status );
    if ( false === $transition_plan ) {
      // Under bypass, plan_status_transition() may not recognize the requested
      // transition (it only models the real lifecycle map). Fall back to a raw
      // status write so bypass still allows arbitrary dev/testing transitions.
      if ( $bypassed ) {
        if ( ! $this->set_membership_status( $new_status ) ) {
          return false;
        }

        return [
          'success_message' => 'BYPASSED STATUS LOCKOUT — status set to ' . $new_status,
          'bypassed'        => true,
        ];
      }

      return false;
    }

    // Activate or cancel the WC subscription first — if this fails the caller can
    // detect it without also having to undo a status write.
    if ( ! empty( $transition_plan['activate_subscription'] ) ) {
      $this->activate_subscription_for_dates(
        $transition_plan['transition_dates']['starts_at'],
        $transition_plan['transition_dates']['ends_at'],
        $transition_plan['transition_dates']['expires_at']
      );
    }

    if ( ! empty( $transition_plan['cancel_subscription'] ) ) {
      $this->cancel_subscription( $transition_plan['transition_dates']['ends_at'] );
    }

    // Write status and dates atomically via apply_status_transition.
    if ( ! $this->apply_status_transition( $new_status, $transition_plan['transition_dates'] ) ) {
      return false;
    }

    // Propagate the new status down to child memberships last, after the bundle
    // record is already in its final state so children see a consistent parent.
    $this->cascade_status_to_members( $new_status );

    $this->sync_mdp_update();

    return [
      'success_message' => $transition_plan['success_message'],
      'bypassed'        => $bypassed,
    ];
  }

  /**
   * Build the transition dates and side effects for a requested status change.
   *
   * @param string $new_status
   * @return array{transition_dates: array<string, string|null>, success_message: string, activate_subscription: bool}|false
   */
  private function plan_status_transition( string $new_status ) {
    $current_status = $this->get_membership_status();

    // pending → active: recalculate dates anchored to today so the membership period
    // runs from activation date, not from the original creation date.
    if ( $current_status === Wicket_Memberships::STATUS_PENDING && $new_status === Wicket_Memberships::STATUS_ACTIVE ) {
      $config     = $this->get_config();
      $today      = Utilities::get_mdp_day_start( 'now' )->format( 'c' );
      $dates      = $config ? $config->get_membership_dates( [ 'start_date' => $today ] ) : [];

      return [
        'transition_dates' => [
          'starts_at'      => $dates['start_date'] ?? Utilities::get_mdp_day_start( 'now' )->format( 'c' ),
          'ends_at'        => $dates['end_date'] ?? Utilities::get_mdp_day_end( 'now' )->format( 'c' ),
          // expires_at / early_renew_at fall back to end_date when no grace/window is configured.
          'expires_at'     => $dates['expires_at'] ?? ( $dates['end_date'] ?? Utilities::get_mdp_day_end( 'now' )->format( 'c' ) ),
          'early_renew_at' => $dates['early_renew_at'] ?? ( $dates['end_date'] ?? Utilities::get_mdp_day_end( 'now' )->format( 'c' ) ),
        ],
        'success_message'      => 'Pending membership bundle activated successfully.',
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
          'success_message'      => 'Membership bundle cancelled successfully.',
          'activate_subscription' => false,
          'cancel_subscription'   => true,
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
          'success_message'      => 'Membership bundle cancelled successfully.',
          'activate_subscription' => false,
          'cancel_subscription'   => true,
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
        'success_message'      => 'Membership bundle cancelled successfully.',
        'activate_subscription' => false,
        'cancel_subscription'   => true,
      ];
    }

    // delayed → active: status change only — dates were set at creation, preserve them.
    // Matches individual membership behaviour: daily_membership_activation_hook writes status only.
    if ( $current_status === Wicket_Memberships::STATUS_DELAYED && $new_status === Wicket_Memberships::STATUS_ACTIVE ) {
      return [
        'transition_dates' => [
          'starts_at'      => null,
          'ends_at'        => null,
          'expires_at'     => null,
          'early_renew_at' => null,
        ],
        'success_message'       => 'Delayed membership bundle activated successfully.',
        'activate_subscription' => false,
      ];
    }

    // active → grace-period: status change only — preserve all existing dates.
    // Matches individual membership behaviour: daily_membership_grace_period_hook writes status only.
    if ( $new_status === Wicket_Memberships::STATUS_GRACE ) {
      return [
        'transition_dates' => [
          'starts_at'      => null,
          'ends_at'        => null,
          'expires_at'     => null,
          'early_renew_at' => null,
        ],
        'success_message'       => 'Membership bundle moved to grace period.',
        'activate_subscription' => false,
      ];
    }

    // * → expired: status change only — preserve all existing dates.
    // Matches individual membership behaviour: daily_membership_expiry_hook writes status only.
    if ( $new_status === Wicket_Memberships::STATUS_EXPIRED ) {
      return [
        'transition_dates' => [
          'starts_at'      => null,
          'ends_at'        => null,
          'expires_at'     => null,
          'early_renew_at' => null,
        ],
        'success_message'      => 'Membership bundle marked as expired.',
        'activate_subscription' => false,
      ];
    }

    return false;
  }

  /**
   * Set the membership status for this bundle.
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
      Wicket()->log()->error( 'Membership_Bundle: Invalid membership status', ['source' => 'wicket-memberships', 'post_id' => $this->post_id, 'status' => $status] );
      return false;
    }

    // update_post_meta() returns false both on a genuine write failure and when the new
    // value is identical to the current one. Only treat it as a failure when it differs.
    if ( get_post_meta( $this->post_id, 'membership_status', true ) === $status ) {
      return true;
    }

    $result = update_post_meta( $this->post_id, 'membership_status', $status );

    if ( $result === false ) {
      Wicket()->log()->error( 'Membership_Bundle: Failed to save membership status', ['source' => 'wicket-memberships', 'post_id' => $this->post_id, 'status' => $status] );
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

        Wicket()->log()->error( 'Membership_Bundle: Failed to persist transition field', [
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
   * Cascade a status update to all non-cancelled child memberships.
   *
   * Performs the full local write sequence per member (date collapse where needed,
   * update_local_membership_record, update_membership_status, amend_membership_json).
   * No MDP calls — MDP handles bundle members at the org/bundle level.
   *
   * cancelled: routes through cancel_individual_membership() with sync_mdp = false so
   *   date collapse and the full local sequence run correctly without a per-member MDP call.
   *
   * expired: collapses ends_at and expires_at to tomorrow (mirrors bundle_admin_manage_status()
   *   date rules for expired status). grace_period_days zeroed.
   *
   * active, delayed, grace-period: no date mutation — provision-time dates are authoritative.
   *   Status stamped locally only.
   *
   * @param string $new_status
   * @return void
   */
  private function cascade_status_to_members( string $new_status ): void {
    $mc      = new Membership_Controller();
    $members = $this->get_individual_memberships( false );

    foreach ( $members as $membership_post ) {
      $membership_post_id = $membership_post->ID;
      $current            = get_post_meta( $membership_post_id, 'membership_status', true );

      // Cancelled is a final state — never overwrite it.
      if ( $current === Wicket_Memberships::STATUS_CANCELLED ) {
        continue;
      }

      if ( $new_status === Wicket_Memberships::STATUS_CANCELLED ) {
        // Route through cancel_individual_membership() for correct date collapse and local
        // write sequence. Pass sync_mdp = false — MDP propagates from the bundle org record.
        $this->cancel_individual_membership( $membership_post_id, false );
        continue;
      }

      // expired: collapse dates so the record is unambiguously past. All other statuses
      // (active, delayed, grace-period): no date mutation — keep provision-time dates.
      if ( $new_status === Wicket_Memberships::STATUS_EXPIRED ) {
        $tomorrow  = Utilities::get_mdp_day_end( '+1 day' )->format( 'c' );
        $meta_data = [
          'membership_ends_at'           => $tomorrow,
          'membership_expires_at'        => $tomorrow,
          'membership_grace_period_days' => 0,
        ];
      } else {
        $meta_data = [];
      }

      // Local writes only — MDP handles bundle members at the org/bundle level.
      $mc->update_local_membership_record( $membership_post_id, array_merge( $meta_data, [ 'membership_status' => $new_status ] ) );
      $mc->update_membership_status( $membership_post_id, $new_status );
      $mc->amend_membership_json( $membership_post_id, array_merge( $meta_data, [ 'membership_status' => $new_status ] ) );
    }
  }

  /**
   * Cascade normalized edit fields to eligible child memberships.
   *
   * Applies new bundle dates to child individual memberships, honouring per-field
   * exception rules that preserve custom dates on individual records:
   *   - starts_at: skip if member's current starts_at is after the bundle's pre-edit starts_at
   *   - ends_at:   skip if member's current ends_at is before the bundle's pre-edit ends_at
   *   - expires_at: skip if member's current expires_at is before the bundle's pre-edit expires_at
   * When ends_at is updated, early_renew_at is recalculated using the config renewal window.
   *
   * @param array<string, mixed> $normalized_fields Normalized fields from apply_edit_fields().
   * @param array<string, string> $pre_edit_dates   Bundle dates captured before meta was written; shape matches get_dates().
   * @return void
   */
  public function cascade_dates_to_members( array $normalized_fields, array $pre_edit_dates ): void {
    $new_starts_at  = $normalized_fields['membership_starts_at']  ?? '';
    $new_ends_at    = $normalized_fields['membership_ends_at']    ?? '';
    $new_expires_at = $normalized_fields['membership_expires_at'] ?? '';

    $baseline_starts_at  = $pre_edit_dates['starts_at']  ?? '';
    $baseline_ends_at    = $pre_edit_dates['ends_at']    ?? '';
    $baseline_expires_at = $pre_edit_dates['expires_at'] ?? '';

    $baseline_starts_ts  = $baseline_starts_at  ? strtotime( $baseline_starts_at )  : false;
    $baseline_ends_ts    = $baseline_ends_at    ? strtotime( $baseline_ends_at )    : false;
    $baseline_expires_ts = $baseline_expires_at ? strtotime( $baseline_expires_at ) : false;

    // Pre-compute early_renew_at offset once — same formula as build_normalized_bundle_edit_fields().
    $config              = $this->get_config();
    $renewal_window_days = $config ? $config->get_renewal_window_days() : false;

    $members = $this->get_individual_memberships( false );

    foreach ( $members as $member_post ) {
      $member_id = $member_post->ID;
      $status    = get_post_meta( $member_id, 'membership_status', true );

      if ( $status === Wicket_Memberships::STATUS_EXPIRED || $status === Wicket_Memberships::STATUS_CANCELLED ) {
        continue;
      }

      $member_starts_at  = (string) get_post_meta( $member_id, 'membership_starts_at', true );
      $member_ends_at    = (string) get_post_meta( $member_id, 'membership_ends_at', true );
      $member_expires_at = (string) get_post_meta( $member_id, 'membership_expires_at', true );

      $member_starts_ts  = $member_starts_at  ? strtotime( $member_starts_at )  : false;
      $member_ends_ts    = $member_ends_at    ? strtotime( $member_ends_at )    : false;
      $member_expires_ts = $member_expires_at ? strtotime( $member_expires_at ) : false;

      // starts_at: write unless member has a later custom start than the bundle had pre-edit.
      $write_starts = $new_starts_at && (
        $baseline_starts_ts === false || $member_starts_ts === false || $member_starts_ts <= $baseline_starts_ts
      );

      // ends_at: write unless member has a custom earlier end than the bundle had pre-edit.
      $write_ends = $new_ends_at && (
        $baseline_ends_ts === false || $member_ends_ts === false || $member_ends_ts >= $baseline_ends_ts
      );

      // expires_at: same rule as ends_at.
      $write_expires = $new_expires_at && (
        $baseline_expires_ts === false || $member_expires_ts === false || $member_expires_ts >= $baseline_expires_ts
      );

      if ( $write_starts ) {
        update_post_meta( $member_id, 'membership_starts_at', $new_starts_at );
      }

      if ( $write_ends ) {
        update_post_meta( $member_id, 'membership_ends_at', $new_ends_at );

        // Recalculate early_renew_at to stay consistent with the new ends_at. Day maths
        // in the MDP timezone: UTC truncation and DST crossings shift the day.
        $new_ends_ts        = strtotime( $new_ends_at );
        $early_renew_at_iso = '';
        if ( $new_ends_ts ) {
          $early_renew_dt = ( new \DateTime( '@' . $new_ends_ts ) )
            ->setTimezone( Utilities::get_mdp_timezone() );
          if ( $renewal_window_days !== false ) {
            $early_renew_dt->modify( '-' . absint( $renewal_window_days ) . ' days' );
          }
          $early_renew_at_iso = Utilities::get_mdp_day_start( $early_renew_dt->format( 'Y-m-d' ) )->format( 'c' );
        }
        if ( $early_renew_at_iso ) {
          update_post_meta( $member_id, 'membership_early_renew_at', $early_renew_at_iso );
        }
      }

      if ( $write_expires ) {
        update_post_meta( $member_id, 'membership_expires_at', $new_expires_at );
      }

      if ( $write_starts || $write_ends || $write_expires ) {
        $this->sync_member_mdp_dates( $member_id );
      }
    }
  }

  /**
   * Push a cascaded date change to the member's own MDP person_membership record.
   * Local post meta is already the source of truth by the time this runs — read it
   * back rather than threading values through the cascade loop.
   */
  private function sync_member_mdp_dates( int $member_id ): void {
    $membership_wicket_uuid = get_post_meta( $member_id, 'membership_wicket_uuid', true );
    if ( empty( $membership_wicket_uuid ) ) {
      return;
    }

    $membership = [
      'membership_type'        => get_post_meta( $member_id, 'membership_type', true ),
      'membership_wicket_uuid' => $membership_wicket_uuid,
      'org_seats'              => get_post_meta( $member_id, 'org_seats', true ),
    ];

    $meta_data = [
      'membership_starts_at'         => get_post_meta( $member_id, 'membership_starts_at', true ),
      'membership_ends_at'           => get_post_meta( $member_id, 'membership_ends_at', true ),
      'membership_grace_period_days' => get_post_meta( $member_id, 'membership_grace_period_days', true ),
    ];

    $result = ( new Membership_Controller() )->update_mdp_record( $membership, $meta_data );

    if ( ! empty( $result['error'] ) ) {
      Wicket()->log()->error( 'Membership_Bundle::sync_member_mdp_dates: MDP date sync failed', [
        'source'    => 'wicket-memberships',
        'bundle_id' => $this->post_id,
        'member_id' => $member_id,
        'error'     => $result['error'],
      ] );
    }
  }

  /**
   * Apply normalized bundle edit fields to this bundle.
   *
   *@param array<string, mixed> $normalized_fields
   * @return bool
   */
  public function apply_edit_fields( array $normalized_fields ): bool {
    // Capture before any writes so cascade_dates_to_members() has the correct comparison baseline.
    $pre_edit_dates = $this->get_dates();

    foreach ( $normalized_fields as $key => $value ) {
      $update_result = update_post_meta( $this->post_id, $key, $value );

      if ( false === $update_result ) {
        $persisted_value = get_post_meta( $this->post_id, $key, true );

        // WordPress returns false when the stored meta already matches the
        // submitted value, which should be treated as a successful no-op.
        if ( (string) $persisted_value === (string) $value ) {
          continue;
        }

        Wicket()->log()->error( 'Membership_Bundle: Failed to persist edit field', [
          'source'   => 'wicket-memberships',
          'post_id'  => $this->post_id,
          'meta_key' => $key,
        ] );
        return false;
      }
    }

    $this->meta_data = get_post_meta( $this->post_id );

    $this->sync_subscription_dates();
    $this->cascade_dates_to_members( $normalized_fields, $pre_edit_dates );

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

    $subscription_dates = [
      'start_date' => Utilities::get_mdp_day_start( $starts_at_utc )->format( 'Y-m-d H:i:s' ),
      'end'        => $end->format( 'Y-m-d H:i:s' ),
    ];

    // next_payment only applies to the 'subscription' renewal type — matches the
    // individual-membership precedent (Helper::has_next_payment_date()) and
    // Membership_Bundle_Admin_Controller::maybe_sync_renewal_type_next_payment()'s
    // rule for a later renewal-type change: subscription auto-charges via WCS's own
    // schedule; every other renewal type (form_page, ...) must not have a
    // next_payment date or WCS would trigger an unwanted renewal payment.
    $config = $this->get_config();
    if ( $config && $config->is_renewal_subscription() ) {
      $subscription_dates['next_payment'] = $next_payment->format( 'Y-m-d H:i:s' );
      // Nudges next_payment earlier instead of bumping end when no grace period is configured.
      $subscription_dates = Subscription_Manager::prepare_dates( $subscription_dates, $subscription );
    }

    $subscription->update_dates( $subscription_dates );
    if ( ! $config || ! $config->is_renewal_subscription() ) {
      $subscription->delete_date( 'next_payment' );
    }
    $subscription->save();
  }

  /**
   * Hard-cancel the linked WC subscription for this bundle.
   *
   * @param string|null $ends_at_utc Optional end-date override, e.g. the transition
   *                                 plan's collapsed ends_at. Skipped when empty.
   * @return void
   */
  private function cancel_subscription( ?string $ends_at_utc = null ): void {
    $subscription_id = $this->get_subscription_id();
    if ( ! $subscription_id || ! function_exists( 'wcs_get_subscription' ) ) {
      return;
    }

    $sub = wcs_get_subscription( $subscription_id );
    if ( ! $sub ) {
      return;
    }

    if ( ! empty( $ends_at_utc ) ) {
      try {
        $sub->update_dates( [ 'end' => date( 'Y-m-d H:i:s', strtotime( $ends_at_utc ) ) ] );
      } catch ( \Exception $e ) {
        Wicket()->log()->error( 'Membership_Bundle: could not update subscription end date before cancel', [
          'source'          => 'wicket-memberships',
          'post_id'         => $this->post_id,
          'subscription_id' => $subscription_id,
          'error'           => $e->getMessage(),
        ] );
      }
    }

    $sub->add_order_note(
      sprintf(
        /* translators: 1: membership bundle post ID */
        __( 'Subscription cancelled — membership bundle (ID: %d) cancelled.', 'wicket-memberships' ),
        $this->post_id
      )
    );

    try {
      $sub->update_status( 'cancelled' );
    } catch ( \Exception $e ) {
      Wicket()->log()->error( 'Membership_Bundle: could not cancel subscription', [
        'source'          => 'wicket-memberships',
        'post_id'         => $this->post_id,
        'subscription_id' => $subscription_id,
        'error'           => $e->getMessage(),
      ] );
      return;
    }

    $sub->save();
  }

  /**
   * Set the linked WC subscription to pending-cancel and schedule an Action
   * Scheduler job to hard-cancel it when the bundle's end date is reached.
   *
   * @param string $ends_at_utc
   * @return void
   */
  private function schedule_deferred_subscription_cancellation( string $ends_at_utc ): void {
    $subscription_id = $this->get_subscription_id();
    if ( $subscription_id && function_exists( 'wcs_get_subscription' ) ) {
      $sub = wcs_get_subscription( $subscription_id );
      if ( $sub ) {
        $sub->add_order_note(
          sprintf(
            /* translators: 1: membership bundle post ID */
            __( 'Set to pending-cancel — membership bundle (ID: %d) cancelled at end date. Subscription will be cancelled when the bundle end date is reached.', 'wicket-memberships' ),
            $this->post_id
          )
        );

        try {
          $sub->update_status( 'pending-cancel' );
        } catch ( \Exception $e ) {
          // Subscription may already be in a terminal status (cancelled, expired).
          // Log and continue — the AS job will still fire at ends_at.
          Wicket()->log()->error( 'Membership_Bundle: could not set subscription to pending-cancel', [
            'source'          => 'wicket-memberships',
            'post_id'         => $this->post_id,
            'subscription_id' => $subscription_id,
            'error'           => $e->getMessage(),
          ] );
        }

        $sub->save();
      }
    }

    $ends_at_ts = strtotime( $ends_at_utc );
    if ( $ends_at_ts && ! as_next_scheduled_action( 'wicket_bundle_cancel_subscription', [ $this->post_id ] ) ) {
      as_schedule_single_action( $ends_at_ts, 'wicket_bundle_cancel_subscription', [ $this->post_id ] );
    }
  }

  /**
   * Sync the linked WC subscription's next_payment and end dates to the bundle's
   * current ends_at / expires_at values.
   *
   * Called after an admin date edit succeeds. Skipped only for cancelled subscriptions,
   * mirroring the individual membership date-edit behaviour in Admin_Controller.
   *
   * @return void
   */
  public function sync_subscription_dates(): void {
    if ( ! function_exists( 'wcs_get_subscription' ) ) {
      return;
    }

    $sub_id = $this->get_subscription_id();
    if ( ! $sub_id ) {
      return;
    }

    $sub = wcs_get_subscription( $sub_id );
    if ( ! $sub || $sub->get_status() === 'cancelled' ) {
      return;
    }

    $dates = $this->get_dates();
    if ( empty( $dates['ends_at'] ) ) {
      return;
    }

    $config = $this->get_config();
    $is_renewal_subscription = $config && $config->is_renewal_subscription();
    $end_source = ! empty( $dates['expires_at'] ) ? $dates['expires_at'] : $dates['ends_at'];

    $next_payment = Utilities::get_mdp_day_end( $dates['ends_at'] );
    $end          = Utilities::get_mdp_day_end( $end_source );

    $date_updates = [ 'end' => $end->format( 'Y-m-d H:i:s' ) ];
    if ( $is_renewal_subscription ) {
      $date_updates['next_payment'] = $next_payment->format( 'Y-m-d H:i:s' );
      $date_updates = Subscription_Manager::prepare_dates( $date_updates, $sub );
    }

    try {
      $sub->update_dates( $date_updates );
      if ( ! $is_renewal_subscription ) {
        $sub->delete_date( 'next_payment' );
      }

      $sub->add_order_note( sprintf(
        'Membership bundle (ID: %d) date edit synced subscription dates. Next Payment: %s End: %s',
        $this->post_id,
        $is_renewal_subscription ? Utilities::format_mdp_date( 'Y-m-d', strtotime( $dates['ends_at'] ) ) : 'none',
        Utilities::format_mdp_date( 'Y-m-d', strtotime( $end_source ) )
      ) );
    } catch ( \Exception $e ) {
      $sub->add_order_note( sprintf(
        'Membership bundle (ID: %d) attempted to sync subscription dates but failed. Next Payment: %s End: %s Error: %s',
        $this->post_id,
        $is_renewal_subscription ? Utilities::format_mdp_date( 'Y-m-d', strtotime( $dates['ends_at'] ) ) : 'none',
        Utilities::format_mdp_date( 'Y-m-d', strtotime( $end_source ) ),
        $e->getMessage()
      ) );
    }

    $sub->save();
  }

  /**
   * Build renewal/grace/pending callout arrays for all membership bundles owned by a user.
   *
   * Mirrors the shape of Membership_Controller::get_membership_callouts() so the ACC plugin
   * can consume both with the same renderer. Called from
   * Membership_Controller::get_membership_callouts(), which merges the result into the
   * individual-membership callout feed ACC's ac-callout block reads.
   *
   * Three callout types are evaluated per bundle:
   * - pending_approval: status === 'pending' (no is_approval_required() gate — mirrors individual behavior)
   * - early_renewal / grace_period: delegated to get_renewal_callout(), the same rules the
   *   Membership Bundles List block's detail view uses, so both surfaces always agree.
   *
   * Renewal entries carry a `subscription_renewal.permalink` pointing at the configured
   * "Manage Membership Bundles" page for that bundle, so ACC renders a button that lands
   * the owner on the detail view — where the renewal summary modal / form-page link lives
   * — instead of starting a renewal (and creating orders) from the ACC dashboard itself.
   *
   * @param int $user_id WP user ID of the bundle owner.
   * @return array{early_renewal: array, grace_period: array, pending_approval: array, group_owner: array, debug: array}
   */
  public static function get_owner_callouts( int $user_id ): array {
    $early_renewal    = [];
    $grace_period     = [];
    $pending_approval = [];
    $debug            = [];

    $iso_code = self::get_callout_language();

    // Find all bundles this user owns that are in a relevant status.
    $bundle_posts = get_posts( [
      'post_type'   => Helper::get_membership_bundle_cpt_slug(),
      'post_status' => 'publish',
      'numberposts' => -1,
      'meta_query'  => [
        'relation' => 'AND',
        [
          'key'   => 'user_id',
          'value' => $user_id,
        ],
        [
          'key'     => 'membership_status',
          'value'   => [
            Wicket_Memberships::STATUS_ACTIVE,
            Wicket_Memberships::STATUS_DELAYED,
            Wicket_Memberships::STATUS_GRACE,
            Wicket_Memberships::STATUS_PENDING,
          ],
          'compare' => 'IN',
        ],
      ],
    ] );

    $current_time    = self::get_renewal_reference_time();
    $manage_page_id  = Helper::get_membership_bundles_manage_page_id();
    $manage_page_url = ( $manage_page_id > 0 && get_post_status( $manage_page_id ) === 'publish' )
      ? get_permalink( $manage_page_id )
      : '';

    foreach ( $bundle_posts as $post ) {
      $bundle = new self( $post->ID );
      $config = $bundle->get_config();
      if ( ! $config ) {
        continue;
      }

      $dates  = $bundle->get_dates();
      $status = $bundle->get_membership_status();

      // Shape membership_data to match Membership_Controller individual entry format so
      // ACC's ac-callout block can render bundle owner callouts without modification.
      $ends_at_ts = ! empty( $dates['ends_at'] ) ? strtotime( $dates['ends_at'] ) : 0;
      $membership_data = [
        'ID'              => $post->ID,
        'ends_in_days'    => $ends_at_ts ? (int) ceil( ( $ends_at_ts - $current_time ) / 86400 ) : 0,
        'next_tier'       => false,
        'form_page'       => false,
        'subscription_renewal' => false,
        'multi_tier_renewal'   => false,
        'meta'            => [
          'membership_status'         => $status,
          'membership_tier_name'      => 'Bundle: ' . $bundle->get_name(),
          'membership_starts_at'      => $dates['starts_at'] ?? '',
          'membership_ends_at'        => $dates['ends_at'] ?? '',
          'membership_expires_at'     => $dates['expires_at'] ?? '',
          'membership_early_renew_at' => $dates['early_renew_at'] ?? '',
          'membership_parent_order_id' => '',
          'org_uuid'                  => $bundle->get_org_uuid() ?: '',
        ],
      ];

      // Pending: fires on status alone — no is_approval_required() gate.
      // Note: ACC's renewal block skips pending status (handled by become_member block).
      // Group owner pending callouts are surfaced here but may not render in all ACC configs.
      if ( $status === Wicket_Memberships::STATUS_PENDING ) {
        $callout = [
          'type'         => 'pending_approval',
          'header'       => $config->get_approval_callout_header( $iso_code ),
          'content'      => $config->get_approval_callout_content( $iso_code ),
          'button_label' => $config->get_approval_callout_button_label( $iso_code ),
          'email'        => $config->get_approval_email() . '?subject=' . __( 'Re: Pending Membership Request', 'wicket-memberships' ),
        ];
        $pending_approval[] = [ 'membership' => $membership_data, 'callout' => $callout ];
        continue;
      }

      $renewal_callout = $bundle->get_renewal_callout( $iso_code, $current_time );
      if ( ! $renewal_callout ) {
        continue;
      }

      // Grace period: skip if expires_at is not set.
      if ( ! empty( $dates['expires_at'] ) ) {
        $ends_at    = $ends_at_ts;
        $expires_at = strtotime( $dates['expires_at'] );
        if ( $current_time > $ends_at && $current_time <= $expires_at ) {
          $callout = [
            'type'         => 'grace_period',
            'header'       => $config->get_late_fee_window_callout_header( $iso_code ),
            'content'      => $config->get_late_fee_window_callout_content( $iso_code ),
            'button_label' => $config->get_late_fee_window_callout_button_label( $iso_code ),
          ];
          // late_fee_product_id is informational only — no bundle renewal path charges
          // it. See Membership_Bundle_Config::get_late_fee_window_product_id().
          $grace_period[] = [
            'membership'          => $membership_data,
            'callout'             => $callout,
            'late_fee_product_id' => $config->get_late_fee_window_product_id(),
          ];
        }
      }
    }

    return [
      'early_renewal'    => $early_renewal,
      'grace_period'     => $grace_period,
      'pending_approval' => $pending_approval,
      'group_owner'      => [],  // reserved for future nested bundle-within-bundle callouts
      'debug'            => $debug,
    ];
  }

  // Coverage callout (member-facing).

  /**
   * Find the membership bundles currently paying for a user's own membership.
   *
   * A user is "covered" by a bundle when they hold a membership record linked to it
   * (membership_bundle_id meta) whose status is active, delayed or grace_period, and the
   * bundle itself is in one of those statuses. Pending and ended records are left out:
   * the callout is about coverage the member has right now, not coverage being set up
   * or already lapsed.
   *
   * Bundles the user owns are skipped — the owner manages the bundle through the
   * Membership Bundles List block, and "contact the bundle owner" would point them at
   * themselves.
   *
   * @param int $user_id WP user ID of the member.
   * @return int[] Unique bundle post IDs, oldest membership first.
   */
  public static function get_covering_bundle_ids_for_user( int $user_id ): array {
    if ( $user_id <= 0 ) {
      return [];
    }

    $covered_statuses = [
      Wicket_Memberships::STATUS_ACTIVE,
      Wicket_Memberships::STATUS_DELAYED,
      Wicket_Memberships::STATUS_GRACE,
    ];

    $membership_ids = get_posts( [
      'post_type'   => Helper::get_membership_cpt_slug(),
      'post_status' => 'publish',
      'numberposts' => -1,
      'fields'      => 'ids',
      'orderby'     => 'ID',
      'order'       => 'ASC',
      'meta_query'  => [
        'relation' => 'AND',
        [
          'key'   => 'user_id',
          'value' => $user_id,
        ],
        [
          'key'     => 'membership_bundle_id',
          'value'   => 0,
          'compare' => '>',
          'type'    => 'NUMERIC',
        ],
        [
          'key'     => 'membership_status',
          'value'   => $covered_statuses,
          'compare' => 'IN',
        ],
      ],
    ] );

    // A member can hold several tiers in the same bundle — one callout per bundle.
    $bundle_ids = [];
    foreach ( $membership_ids as $membership_id ) {
      $bundle_id = (int) get_post_meta( $membership_id, 'membership_bundle_id', true );
      if ( $bundle_id > 0 ) {
        $bundle_ids[ $bundle_id ] = $bundle_id;
      }
    }

    $covering = [];
    foreach ( $bundle_ids as $bundle_id ) {
      $bundle = new self( $bundle_id );
      if ( ! $bundle->post_id ) {
        continue;
      }

      if ( ! in_array( $bundle->get_membership_status(), $covered_statuses, true ) ) {
        continue;
      }
      if ( $bundle->get_owner_id() === $user_id ) {
        continue;
      }
      $covering[] = $bundle_id;
    }

    return $covering;
  }

  /**
   * Values for the coverage callout placeholders, for this bundle.
   *
   * {organization_name} and {bundle_owner_name} come from the bundle, so they differ per
   * member. {site_name} and {site_name_contact_link} come from the block's settings.
   *
   * Values are plain text (not escaped), except {site_name_contact_link}, which is not
   * returned here: it is HTML and is built by get_coverage_callout().
   * Missing data falls back to generic wording rather than an empty string, so the
   * sentence still reads correctly (e.g. "Your organization is currently paying…").
   *
   * @param string $site_name Site name from the block; empty = the WordPress site title.
   * @return array<string, string> Placeholder token (with braces) => value.
   */
  public function get_coverage_callout_placeholders( string $site_name = '' ): array {
    // org_name is denormalised onto the bundle at set_organization(); only fall back to
    // the org lookup (cached option, else a live MDP call) when it is missing.
    $org_name = (string) get_post_meta( $this->post_id, 'org_name', true );
    if ( $org_name === '' ) {
      $org      = $this->get_organization();
      $org_name = is_array( $org ) && ! empty( $org['name'] ) ? (string) $org['name'] : '';
    }

    $owner     = $this->get_owner();
    $site_name = trim( $site_name );

    $placeholders = [
      '{organization_name}' => $org_name !== '' ? $org_name : __( 'Your organization', 'wicket-memberships' ),
      '{bundle_owner_name}' => ! empty( $owner['name'] ) ? $owner['name'] : __( 'your membership bundle administrator', 'wicket-memberships' ),
      '{site_name}'         => $site_name !== '' ? $site_name : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
    ];

    /**
     * Filter the coverage callout placeholder values for a bundle.
     *
     * Add a key to support a new placeholder, or change a value. Values must be plain
     * text; they are escaped on output.
     *
     * @param array<string, string> $placeholders Placeholder token => value.
     * @param Membership_Bundle     $bundle       The covering bundle.
     */
    return (array) apply_filters( 'wicket_mship_bundle_coverage_callout_placeholders', $placeholders, $this );
  }

  /**
   * Default coverage callout wording — the block's heading/message attribute defaults.
   *
   * @return array{heading: string, message: string}
   */
  public static function get_default_coverage_callout_copy(): array {
    return [
      'heading' => __( '{organization_name} is currently paying for your membership.', 'wicket-memberships' ),
      'message' => __( 'If you no longer work here, please contact {site_name_contact_link} or {bundle_owner_name} to update this.', 'wicket-memberships' ),
    ];
  }

  /**
   * Build the member-facing coverage callout for this bundle.
   *
   * Wording comes from the Membership Bundle Coverage Callout block's settings; empty
   * heading/message fall back to get_default_coverage_callout_copy(). Placeholders:
   * {organization_name}, {bundle_owner_name}, {site_name}, {site_name_contact_link}
   * (the site name linked to site_contact_link; plain site name when no link is set).
   *
   * Does not check who is viewing — callers decide that (see
   * get_covering_bundle_ids_for_user()).
   *
   * @param array $settings {
   *   @type string $heading           Heading text with placeholders.
   *   @type string $message           Message text with placeholders (basic HTML allowed).
   *   @type string $site_name         Value for {site_name}; empty = site title.
   *   @type string $site_contact_link URL or mailto: for {site_name_contact_link}.
   * }
   * @return array{bundle_post_id: int, header: string, content: string}|null
   *   header is plain text (escape with esc_html); content is sanitized HTML
   *   (wp_kses_post, paragraphs added). Null when the filter hides it.
   */
  public function get_coverage_callout( array $settings = [] ): ?array {
    if ( ! $this->post_id ) {
      return null;
    }

    $defaults = self::get_default_coverage_callout_copy();
    $heading  = trim( (string) ( $settings['heading'] ?? '' ) );
    $message  = trim( (string) ( $settings['message'] ?? '' ) );
    $heading  = $heading !== '' ? $heading : $defaults['heading'];
    $message  = $message !== '' ? $message : $defaults['message'];

    $placeholders = $this->get_coverage_callout_placeholders( (string) ( $settings['site_name'] ?? '' ) );
    $contact_url  = esc_url( trim( (string) ( $settings['site_contact_link'] ?? '' ) ) );

    // Heading is plain text: a link can't render there, so the link placeholder becomes
    // the plain site name. Escaped later by the template.
    $heading = wp_strip_all_tags( strtr( $heading, $placeholders + [
      '{site_name_contact_link}' => $placeholders['{site_name}'],
    ] ) );

    // Message may contain editor HTML: escape the data values first so e.g. an org name
    // from MDP can never inject markup, then sanitize the whole string.
    $escaped_placeholders = array_map( 'esc_html', $placeholders );
    $escaped_placeholders['{site_name_contact_link}'] = $contact_url !== ''
      ? '<a href="' . $contact_url . '">' . $escaped_placeholders['{site_name}'] . '</a>'
      : $escaped_placeholders['{site_name}'];

    $content = wpautop( wp_kses_post( strtr( $message, $escaped_placeholders ) ) );

    $callout = [
      'bundle_post_id' => $this->post_id,
      'header'         => $heading,
      'content'        => $content,
    ];

    /**
     * Filter the membership bundle coverage callout before it is rendered.
     *
     * Return null to hide the callout for this bundle.
     *
     * @param array|null        $callout  Callout data (see get_coverage_callout()).
     * @param Membership_Bundle $bundle   The covering bundle.
     * @param array             $settings Block settings passed to get_coverage_callout().
     */
    $callout = apply_filters( 'wicket_mship_bundle_coverage_callout', $callout, $this, $settings );

    return is_array( $callout ) ? $callout : null;
  }

  // Renewal reminder (owner-facing, account centre landing page).

  /**
   * Bundles owned by a user that are currently due for renewal, for the
   * Membership Bundle Renewal Reminder block and the account-menu badge.
   *
   * "Due" means get_renewal_callout() returns a callout, so this list always
   * matches the bundles that show a "Renew" prompt on the detail view and a badge on
   * the list view. Already-renewed terms, autopay early renewals, bundles with no usable
   * renewal flow and the global "Disable Renewal Callouts" setting are all excluded
   * there, so they are excluded here too.
   *
   * Memoised per request ($user_id + reference time): the account centre page renders
   * both the nav badge (wp_head) and the reminder block, and each would otherwise walk
   * every owned bundle's renewal orders again.
   *
   * @param int      $user_id WP user ID of the bundle owner.
   * @param int|null $now     UTC timestamp; defaults to get_renewal_reference_time().
   * @return array<int, array{
   *   bundle_post_id: int,
   *   type: string,
   *   bundle_name: string,
   *   org_name: string,
   *   ends_at: string,
   *   expires_at: string
   * }> Soonest ends_at first. Names are plain text (escape on output); dates are the
   *    raw ISO 8601 strings stored on the bundle.
   */
  public static function get_owner_renewal_due_bundles( int $user_id, ?int $now = null ): array {
    static $cache = [];

    if ( $user_id <= 0 ) {
      return [];
    }

    $now       = $now ?? self::get_renewal_reference_time();
    $cache_key = $user_id . ':' . $now;
    if ( isset( $cache[ $cache_key ] ) ) {
      return $cache[ $cache_key ];
    }

    // Only statuses get_renewal_state() can match — keeps the per-bundle order lookups
    // in get_renewal_callout() to the few bundles that can actually qualify.
    $bundle_ids = get_posts( [
      'post_type'   => Helper::get_membership_bundle_cpt_slug(),
      'post_status' => 'publish',
      'numberposts' => -1,
      'fields'      => 'ids',
      'meta_query'  => [
        'relation' => 'AND',
        [
          'key'   => 'user_id',
          'value' => $user_id,
        ],
        [
          'key'     => 'membership_status',
          'value'   => [
            Wicket_Memberships::STATUS_ACTIVE,
            Wicket_Memberships::STATUS_DELAYED,
            Wicket_Memberships::STATUS_GRACE,
          ],
          'compare' => 'IN',
        ],
      ],
    ] );

    $iso_code = self::get_callout_language();
    $due      = [];

    foreach ( $bundle_ids as $bundle_id ) {
      $bundle = new self( (int) $bundle_id );
      if ( ! $bundle->post_id ) {
        continue;
      }

      $callout = $bundle->get_renewal_callout( $iso_code, $now );
      if ( ! $callout ) {
        continue;
      }

      $dates = $bundle->get_dates();
      $due[] = [
        'bundle_post_id' => $bundle->post_id,
        'type'           => (string) $callout['type'],
        'bundle_name'    => wp_specialchars_decode( $bundle->get_name(), ENT_QUOTES ),
        // Denormalised at set_organization(); deliberately no live MDP fallback here —
        // this runs on every account centre page load.
        'org_name'       => wp_specialchars_decode( (string) get_post_meta( $bundle->post_id, 'org_name', true ), ENT_QUOTES ),
        'ends_at'        => $dates['ends_at'] ?? '',
        'expires_at'     => $dates['expires_at'] ?? '',
      ];
    }

    // Most urgent first, so the bundle the owner should deal with next leads the list.
    usort( $due, static function ( array $a, array $b ): int {
      return (int) strtotime( $a['ends_at'] ) <=> (int) strtotime( $b['ends_at'] );
    } );

    $cache[ $cache_key ] = $due;
    return $due;
  }

  /**
   * Default renewal reminder wording — used when the block's heading/message/button
   * label are empty. Pluralised on $count and switched to "overdue" wording when any of
   * the bundles is already past its end date (grace period).
   *
   * @param int  $count  Number of bundles due.
   * @param bool $lapsed Whether at least one bundle is in its grace period.
   * @return array{heading: string, message: string, button_label: string}
   */
  public static function get_default_renewal_reminder_copy( int $count = 1, bool $lapsed = false ): array {
    if ( $lapsed ) {
      return [
        'heading'      => _n(
          'Your membership bundle renewal is overdue.',
          'You have {bundle_count} membership bundles to renew, and at least one is overdue.',
          $count,
          'wicket-memberships'
        ),
        'message'      => __( 'Review and renew each bundle before its grace period ends to keep your members covered.', 'wicket-memberships' ),
        'button_label' => _n( 'Review bundle', 'Review bundles', $count, 'wicket-memberships' ),
      ];
    }

    return [
      'heading'      => _n(
        'Your membership bundle is due for renewal.',
        'You have {bundle_count} membership bundles due for renewal.',
        $count,
        'wicket-memberships'
      ),
      'message'      => __( 'Review the members in each bundle and renew it from the Manage Group Membership page.', 'wicket-memberships' ),
      'button_label' => _n( 'Review bundle', 'Review bundles', $count, 'wicket-memberships' ),
    ];
  }

  /**
   * URL of the configured "Manage Membership Bundles" page (Settings > Wicket
   * Memberships > Membership Bundles), or '' when none is set or it is not published.
   *
   * Carries ?wicket_wp_membership_debug_days through when the QA date override is
   * active, so a tester following the link sees the same simulated date there.
   *
   * @return string Unescaped URL.
   */
  public static function get_manage_bundles_page_url(): string {
    $page_id = Helper::get_membership_bundles_manage_page_id();
    if ( $page_id <= 0 || get_post_status( $page_id ) !== 'publish' ) {
      return '';
    }

    $url = (string) get_permalink( $page_id );

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- debug-only read, gated by env flag.
    if ( $url !== '' && ! empty( $_ENV['WICKET_MEMBERSHIPS_DEBUG_RENEW'] ) && ! empty( $_GET['wicket_wp_membership_debug_days'] ) ) {
      $url = add_query_arg( 'wicket_wp_membership_debug_days', (int) $_GET['wicket_wp_membership_debug_days'], $url ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }

    return $url;
  }

  /**
   * Build the owner-facing renewal reminder: one notice covering every bundle the user
   * owns that is due for renewal.
   *
   * Deliberately does not start a renewal or create orders — an owner can have several
   * bundles due in the same period, each renewed separately from its detail view. The
   * reminder only says renewals are due and links to the Manage Membership Bundles page.
   *
   * @param int   $user_id  WP user ID of the bundle owner.
   * @param array $settings {
   *   Block attributes. Empty strings fall back to get_default_renewal_reminder_copy().
   *
   *   @type string $heading      Heading text; supports {bundle_count}.
   *   @type string $message      Message text; supports {bundle_count}; basic HTML allowed.
   *   @type string $button_label Button label.
   * }
   * @return array{
   *   type: string,
   *   header: string,
   *   content: string,
   *   button_label: string,
   *   manage_url: string,
   *   bundles: array
   * }|null
   *   type is grace_period when any bundle is lapsed, else early_renewal. header and
   *   button_label are plain text (escape on output); content is sanitized HTML;
   *   manage_url is unescaped ('' when no manage page is configured); bundles is
   *   get_owner_renewal_due_bundles(). Null when nothing is due or the filter hides it.
   */
  public static function get_renewal_reminder( int $user_id, array $settings = [] ): ?array {
    $bundles = self::get_owner_renewal_due_bundles( $user_id );
    if ( empty( $bundles ) ) {
      return null;
    }

    $count  = count( $bundles );
    $lapsed = in_array( self::CALLOUT_GRACE_PERIOD, array_column( $bundles, 'type' ), true );

    // Per-field fallback, so an editor can override just the button label, say.
    $defaults = self::get_default_renewal_reminder_copy( $count, $lapsed );
    $heading  = trim( (string) ( $settings['heading'] ?? '' ) );
    $message  = trim( (string) ( $settings['message'] ?? '' ) );
    $button   = trim( (string) ( $settings['button_label'] ?? '' ) );
    $heading  = $heading !== '' ? $heading : $defaults['heading'];
    $message  = $message !== '' ? $message : $defaults['message'];
    $button   = $button !== '' ? $button : $defaults['button_label'];

    $placeholders = [
      '{bundle_count}' => number_format_i18n( $count ),
    ];

    $reminder = [
      'type'         => $lapsed ? self::CALLOUT_GRACE_PERIOD : self::CALLOUT_EARLY_RENEWAL,
      'header'       => wp_strip_all_tags( strtr( $heading, $placeholders ) ),
      'content'      => wpautop( wp_kses_post( strtr( $message, $placeholders ) ) ),
      'button_label' => wp_strip_all_tags( strtr( $button, $placeholders ) ),
      'manage_url'   => self::get_manage_bundles_page_url(),
      'bundles'      => $bundles,
    ];

    /**
     * Filter the owner-facing membership bundle renewal reminder before it is rendered.
     *
     * Return null to hide it. To hide a single bundle from the reminder and from the
     * other renewal prompts alike, filter wicket_mship_bundle_renewal_callout instead.
     *
     * @param array|null $reminder Reminder data (see get_renewal_reminder()).
     * @param int        $user_id  The bundle owner.
     * @param array      $settings Block settings passed to get_renewal_reminder().
     */
    $reminder = apply_filters( 'wicket_mship_bundle_renewal_reminder', $reminder, $user_id, $settings );

    return is_array( $reminder ) ? $reminder : null;
  }

  // Renewal callouts (member-facing).

  /**
   * Resolve the language code used to pick localized callout copy from the bundle config.
   *
   * Same resolution as Membership_Controller::get_membership_callouts(): WPML's current
   * language when available, otherwise the two-letter prefix of the site locale.
   *
   * @return string
   */
  public static function get_callout_language(): string {
    $iso_code = apply_filters( 'wpml_current_language', null );
    if ( empty( $iso_code ) ) {
      $iso_code = substr( get_locale(), 0, 2 );
    }
    return (string) $iso_code;
  }

  /**
   * "Now" as a UTC timestamp for renewal-window checks.
   *
   * Honors the existing QA override used by individual-membership callouts: when
   * WICKET_MEMBERSHIPS_DEBUG_RENEW is enabled, `?wicket_wp_membership_debug_days=N`
   * (GET or POST) shifts "now" by N days so the renewal window / grace period can be
   * exercised without editing dates.
   *
   * Uses time() rather than current_time( 'timestamp' ): bundle dates are stored as
   * ISO 8601 strings with an offset, so strtotime() on them already yields a true UTC
   * timestamp, and current_time( 'timestamp' ) would skew the comparison by the site's
   * GMT offset.
   *
   * @return int
   */
  public static function get_renewal_reference_time(): int {
    $now = time();

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- debug-only read, gated by env flag.
    if ( ! empty( $_ENV['WICKET_MEMBERSHIPS_DEBUG_RENEW'] ) && ! empty( $_REQUEST['wicket_wp_membership_debug_days'] ) ) {
      $days = (int) $_REQUEST['wicket_wp_membership_debug_days']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
      $now  = (int) strtotime( sprintf( '%+d days', $days ), $now );
    }

    return $now;
  }

  /**
   * Determine which renewal callout (if any) applies to this bundle right now.
   *
   * Both states are derived from dates, the same way individual-membership callouts are
   * (Membership_Controller::get_membership_callouts()):
   * - early_renewal: early_renew_at <= now < ends_at
   * - grace_period:  ends_at <= now <= expires_at
   *
   * membership_status is only a gate (active, delayed or grace_period). It is not what
   * picks the state: the daily cron moves a bundle to grace_period up to a day after
   * ends_at (later if WP-Cron is late), and keying off status left that stretch with no
   * callout at all.
   *
   * @param int|null $now UTC timestamp; defaults to get_renewal_reference_time().
   * @return string|null self::CALLOUT_EARLY_RENEWAL, self::CALLOUT_GRACE_PERIOD, or null.
   */
  public function get_renewal_state( ?int $now = null ): ?string {
    if ( ! $this->post_id ) {
      return null;
    }

    // Status gate: admin-set expired/cancelled (and pending) bundles never get a renewal
    // callout, whatever their dates say.
    $status = $this->get_membership_status();
    $eligible_statuses = [
      Wicket_Memberships::STATUS_ACTIVE,
      Wicket_Memberships::STATUS_DELAYED,
      Wicket_Memberships::STATUS_GRACE,
    ];
    if ( ! in_array( $status, $eligible_statuses, true ) ) {
      return null;
    }

    $dates          = $this->get_dates();
    $ends_at        = ! empty( $dates['ends_at'] ) ? strtotime( $dates['ends_at'] ) : false;
    $expires_at     = ! empty( $dates['expires_at'] ) ? strtotime( $dates['expires_at'] ) : false;
    $early_renew_at = ! empty( $dates['early_renew_at'] ) ? strtotime( $dates['early_renew_at'] ) : false;

    // Every window is measured against ends_at; without it there is nothing to compare.
    if ( ! $ends_at ) {
      return null;
    }

    $now = $now ?? self::get_renewal_reference_time();

    // Grace period is checked first so a bundle past ends_at is never reported as
    // early_renewal. Needs a configured grace period (expires_at after ends_at).
    if ( $expires_at && $expires_at > $ends_at && $now >= $ends_at && $now <= $expires_at ) {
      return self::CALLOUT_GRACE_PERIOD;
    }

    // Renewal window: only when one is configured.
    if ( $early_renew_at && $now >= $early_renew_at && $now < $ends_at ) {
      return self::CALLOUT_EARLY_RENEWAL;
    }

    return null;
  }

  /**
   * Build the renewal callout for this bundle, or null when none should be shown.
   *
   * Single source of truth for both the Membership Bundles List block's detail view
   * (templates/account-membership-bundles/renewal-callout.php) and the ACC feed
   * (get_owner_callouts()). Does not check who is viewing — callers are responsible for
   * restricting it to the bundle owner.
   *
   * @param string   $iso_code Language for config callout copy; defaults to get_callout_language().
   * @param int|null $now      UTC timestamp; defaults to get_renewal_reference_time().
   * @return array{
   *   type: string,
   *   header: string,
   *   content: string,
   *   button_label: string,
   *   flow: string,
   *   form_url: string,
   *   late_fee_product_id: int
   * }|null
   */
  public function get_renewal_callout( string $iso_code = '', ?int $now = null ): ?array {
    // Same global kill switch as individual-membership callouts
    // (Settings > "Disable Renewal Callouts").
    if ( ! empty( $_ENV['WICKET_MSHIP_DISABLE_RENEWALS'] ) ) {
      return null;
    }

    $config = $this->get_config();
    if ( ! $config ) {
      return null;
    }

    $state = $this->get_renewal_state( $now );
    if ( ! $state ) {
      return null;
    }

    // An order has already been paid for this term (or the renewal batch is running) —
    // prompting again would invite a second payment for the same period.
    if ( $this->is_renewal_underway() ) {
      return null;
    }

    $flow = $this->get_renewal_flow();
    if ( ! $flow ) {
      return null;
    }

    // Autopay will renew the bundle on its own at ends_at; an early-renewal prompt would
    // only duplicate that. Grace period is still shown — past ends_at with no paid
    // renewal (is_renewal_underway() above) means autopay did not complete. Form-page renewals can't be completed by autopay at all, so
    // they are never suppressed here (same rule as individual Form Flow renewals).
    if ( $state === self::CALLOUT_EARLY_RENEWAL && $flow === self::RENEWAL_FLOW_SUBSCRIPTION && $this->is_autopay_active() ) {
      return null;
    }

    $iso_code = $iso_code !== '' ? $iso_code : self::get_callout_language();

    if ( $state === self::CALLOUT_GRACE_PERIOD ) {
      $header       = $config->get_late_fee_window_callout_header( $iso_code );
      $content      = $config->get_late_fee_window_callout_content( $iso_code );
      $button_label = $config->get_late_fee_window_callout_button_label( $iso_code );
      $default_head = __( 'Lapsed - Renew your Bundle Membership', 'wicket-memberships' );
    } else {
      $header       = $config->get_renewal_window_callout_header( $iso_code );
      $content      = $config->get_renewal_window_callout_content( $iso_code );
      $button_label = $config->get_renewal_window_callout_button_label( $iso_code );
      $default_head = __( 'Renew your Bundle Membership', 'wicket-memberships' );
    }

    $late_fee_product_id = $state === self::CALLOUT_GRACE_PERIOD
      ? (int) $config->get_late_fee_window_product_id()
      : 0;

    $callout = [
      'type'                => $state,
      'header'              => trim( (string) $header ) !== '' ? (string) $header : $default_head,
      'content'             => (string) ( $content ?: '' ),
      'button_label'        => trim( (string) $button_label ) !== '' ? (string) $button_label : __( 'Renew your membership', 'wicket-memberships' ),
      'flow'                => $flow,
      'form_url'            => $flow === self::RENEWAL_FLOW_FORM_PAGE ? $this->get_renewal_form_url( $late_fee_product_id ) : '',
      'late_fee_product_id' => $late_fee_product_id,
    ];

    /**
     * Filter the membership bundle renewal callout before it is rendered.
     *
     * Return null to hide the callout for this bundle.
     *
     * @param array|null        $callout Callout data (see get_renewal_callout() return shape).
     * @param Membership_Bundle $bundle  The bundle being evaluated.
     */
    $callout = apply_filters( 'wicket_mship_bundle_renewal_callout', $callout, $this );

    return is_array( $callout ) ? $callout : null;
  }

  /**
   * Resolve how this bundle is renewed: via a renewal order on its subscription, or via a form page.
   *
   * A bundle-level renewal type (set on the admin edit screen) overrides the config;
   * 'inherited' (or no value) defers to the config's renewal_type.
   *
   * @return string self::RENEWAL_FLOW_FORM_PAGE, self::RENEWAL_FLOW_SUBSCRIPTION, or '' when neither is usable.
   */
  public function get_renewal_flow(): string {
    if ( $this->get_renewal_form_page_id() > 0 ) {
      return self::RENEWAL_FLOW_FORM_PAGE;
    }

    return $this->get_subscription_id() ? self::RENEWAL_FLOW_SUBSCRIPTION : '';
  }

  /**
   * Get the published form page used to renew this bundle, if the bundle renews via form page.
   *
   * @return int Page ID, or 0 when the bundle does not renew via a (published) form page.
   */
  public function get_renewal_form_page_id(): int {
    $renewal_type = (string) get_post_meta( $this->post_id, 'membership_renewal_type', true );

    if ( $renewal_type !== '' && $renewal_type !== 'inherited' ) {
      // Bundle-level override. build_normalized_bundle_edit_fields() keeps these two
      // meta values mutually exclusive, so the form page wins only when subscription
      // renewal is not flagged.
      $page_id = ! empty( get_post_meta( $this->post_id, 'membership_next_tier_subscription_renewal', true ) )
        ? 0
        : (int) get_post_meta( $this->post_id, 'membership_next_tier_form_page_id', true );
    } else {
      $config  = $this->get_config();
      $page_id = ( $config && $config->get_renewal_type() === 'form_page' )
        ? (int) $config->get_renewal_form_page_id()
        : 0;
    }

    return ( $page_id > 0 && get_post_status( $page_id ) === 'publish' ) ? $page_id : 0;
  }

  /**
   * Build the renewal form page URL for this bundle.
   *
   * Query args follow the individual Form Flow renewal link built by ACC
   * (org_uuid, late_fee_product_id), with bundle_post_id_renew identifying the bundle.
   *
   * @param int $late_fee_product_id Appended only when > 0 (grace period with a late fee product).
   * @return string Empty string when the bundle has no form page.
   */
  public function get_renewal_form_url( int $late_fee_product_id = 0 ): string {
    $page_id = $this->get_renewal_form_page_id();

    if ( ! $page_id ) {
      return '';
    }

    $args = [ 'bundle_post_id_renew' => $this->post_id ];

    $org_uuid = $this->get_org_uuid();
    if ( $org_uuid ) {
      $args['org_uuid'] = $org_uuid;
    }
    if ( $late_fee_product_id > 0 ) {
      $args['late_fee_product_id'] = $late_fee_product_id;
    }

    return add_query_arg( $args, get_permalink( $page_id ) );
  }

  /**
   * Whether this bundle's current term has already been renewed (or a renewal payment is in flight).
   *
   * True when either:
   * - handle_bundle_renewal() has stamped membership_renewal_processing with this bundle as the
   *   old term (the meta is written to both old and new posts; only the old one is "renewed"), or
   * - a renewal order on the bundle subscription, created inside the current renewal window, is
   *   already paid or awaiting payment confirmation (processing / on-hold / completed).
   *
   * Orders created before the window opened belong to a previous term and are ignored — the
   * subscription is reused across terms, so its renewal history spans every year of the bundle.
   *
   * @return bool
   */
  public function is_renewal_underway(): bool {
    $processing = json_decode( (string) get_post_meta( $this->post_id, 'membership_renewal_processing', true ), true );
    if ( is_array( $processing ) && (int) ( $processing['old_bundle_post_id'] ?? 0 ) === $this->post_id ) {
      return true;
    }

    foreach ( $this->get_current_term_renewal_orders() as $order ) {
      if ( $order->has_status( [ 'processing', 'on-hold', 'completed' ] ) ) {
        return true;
      }
    }

    return false;
  }

  /**
   * Whether the bundle subscription will renew itself automatically at ends_at.
   *
   * Same checks as the individual-membership autopay skip in
   * Membership_Controller::get_membership_callouts(): automatic (not manual) renewal, an
   * upcoming next_payment date, and a subscription status that WCS will actually process.
   *
   * @return bool
   */
  public function is_autopay_active(): bool {
    $subscription = $this->get_subscription();
    if ( ! $subscription ) {
      return false;
    }

    if ( $subscription->get_requires_manual_renewal() ) {
      return false;
    }

    if ( ! $subscription->has_status( 'active' ) ) {
      return false;
    }

    $next_payment = (int) $subscription->get_time( 'next_payment' );
    return $next_payment > time();
  }

  /**
   * Load the bundle's WooCommerce subscription.
   *
   * @return \WC_Subscription|null
   */
  public function get_subscription(): ?\WC_Subscription {
    $subscription_id = $this->get_subscription_id();
    if ( ! $subscription_id || ! function_exists( 'wcs_get_subscription' ) ) {
      return null;
    }

    $subscription = wcs_get_subscription( $subscription_id );
    return $subscription instanceof \WC_Subscription ? $subscription : null;
  }

  /**
   * Renewal orders on the bundle subscription created since this term's renewal window opened.
   *
   * @return \WC_Order[]
   */
  private function get_current_term_renewal_orders(): array {
    $subscription = $this->get_subscription();
    if ( ! $subscription ) {
      return [];
    }

    // The term's renewal window opens at the earlier of early_renew_at and ends_at. Taking
    // the minimum guards against an early_renew_at that was left pointing past ends_at
    // (e.g. after a date edit), which would otherwise hide every order for this term.
    $dates      = $this->get_dates();
    $candidates = array_filter( [
      ! empty( $dates['early_renew_at'] ) ? (int) strtotime( $dates['early_renew_at'] ) : 0,
      ! empty( $dates['ends_at'] ) ? (int) strtotime( $dates['ends_at'] ) : 0,
    ] );
    if ( ! $candidates ) {
      return [];
    }
    $window_ts = min( $candidates );

    $orders = [];
    foreach ( $subscription->get_related_orders( 'ids', 'renewal' ) as $order_id ) {
      $order = wc_get_order( $order_id );
      if ( ! $order || ! $order->get_date_created() ) {
        continue;
      }
      if ( $order->get_date_created()->getTimestamp() < $window_ts ) {
        continue;
      }
      $orders[] = $order;
    }

    return $orders;
  }

  /**
   * Summarize the memberships a renewal order would bill, grouped by tier.
   *
   * Counts the bundle subscription's line items that carry _membership_post_id — the same
   * set handle_bundle_renewal() provisions from — rather than get_individual_memberships(),
   * so the number shown in the renewal modal is exactly what the order will contain.
   *
   * @return array{total: int, tiers: array<int, array{name: string, count: int}>}
   */
  public function get_renewal_summary(): array {
    $summary      = [ 'total' => 0, 'tiers' => [] ];
    $subscription = $this->get_subscription();
    if ( ! $subscription ) {
      return $summary;
    }

    $counts = [];
    foreach ( $subscription->get_items() as $item ) {
      $membership_post_id = (int) $item->get_meta( '_membership_post_id', true );
      if ( $membership_post_id <= 0 ) {
        continue;
      }

      $tier_name = (string) get_post_meta( $membership_post_id, 'membership_tier_name', true );
      if ( $tier_name === '' ) {
        $tier_name = $item->get_name();
      }

      $counts[ $tier_name ] = ( $counts[ $tier_name ] ?? 0 ) + 1;
      $summary['total']++;
    }

    ksort( $counts, SORT_NATURAL | SORT_FLAG_CASE );
    foreach ( $counts as $name => $count ) {
      $summary['tiers'][] = [ 'name' => (string) $name, 'count' => (int) $count ];
    }

    return $summary;
  }

  /**
   * Get a payable renewal order for this bundle, creating one only when none is pending.
   *
   * Member-facing counterpart of POST /bundle/{id}/create_renewal_order (admin), which always
   * creates a new order. Here a pending/failed renewal order from the current renewal window is
   * reused, so clicking "Generate Order" more than once (or abandoning checkout and coming back)
   * never stacks up duplicate invoices for the same term.
   *
   * Validation is repeated server-side rather than trusting that the callout was showing: the
   * window may have closed, or the bundle been renewed elsewhere, since the page was rendered.
   *
   * @return \WC_Order|\WP_Error
   */
  public function get_or_create_renewal_order() {
    if ( ! function_exists( 'wcs_create_renewal_order' ) ) {
      return new \WP_Error( 'wcs_inactive', 'WooCommerce Subscriptions is not active.' );
    }

    // Callout rules are the gate: if the owner would not see a callout, they cannot order.
    $callout = $this->get_renewal_callout();
    if ( ! $callout ) {
      return new \WP_Error( 'not_renewable', 'This membership bundle is not currently open for renewal.' );
    }

    if ( $callout['flow'] !== self::RENEWAL_FLOW_SUBSCRIPTION ) {
      return new \WP_Error( 'form_page_flow', 'This membership bundle renews through a form.' );
    }

    $subscription = $this->get_subscription();
    if ( ! $subscription ) {
      return new \WP_Error( 'no_subscription', 'This membership bundle has no linked WooCommerce subscription.' );
    }

    if ( $this->get_renewal_summary()['total'] === 0 ) {
      return new \WP_Error( 'no_members', 'This membership bundle has no memberships to renew.' );
    }

    // Reuse an unpaid order from this term before creating another.
    $order = null;
    foreach ( $this->get_current_term_renewal_orders() as $candidate ) {
      if ( $candidate->has_status( [ 'pending', 'failed' ] ) ) {
        $order = $candidate;
        break;
      }
    }

    if ( ! $order ) {
      $order = wcs_create_renewal_order( $subscription );
      if ( is_wp_error( $order ) ) {
        return $order;
      }
      if ( ! $order instanceof \WC_Order ) {
        return new \WP_Error( 'order_failed', 'The renewal order could not be created.' );
      }

      $order->add_order_note( sprintf(
        'Membership bundle renewal order generated by the bundle owner from the member portal (bundle ID: %d).',
        $this->post_id
      ) );
    }

    // Late fee applies only in grace period; added once, also to a reused order that was
    // generated before the bundle crossed from the renewal window into grace.
    if ( $callout['type'] === self::CALLOUT_GRACE_PERIOD && $callout['late_fee_product_id'] > 0 ) {
      $this->maybe_add_late_fee_item( $order, (int) $callout['late_fee_product_id'] );
    }

    return $order;
  }

  /**
   * Add the config's late fee product to a renewal order, unless it is already on it.
   *
   * @param \WC_Order $order
   * @param int       $product_id
   * @return void
   */
  private function maybe_add_late_fee_item( \WC_Order $order, int $product_id ): void {
    foreach ( $order->get_items() as $item ) {
      if ( (int) $item->get_product_id() === $product_id || (int) $item->get_variation_id() === $product_id ) {
        return;
      }
    }

    $product = wc_get_product( $product_id );
    if ( ! $product ) {
      Utilities::wc_log_mship_error( [ 'Membership_Bundle: late fee product not found', [
        'bundle_post_id' => $this->post_id,
        'product_id'     => $product_id,
      ] ] );
      return;
    }

    $order->add_product( $product, 1 );
    $order->calculate_totals();
    $order->add_order_note( sprintf( 'Grace period late fee added to bundle renewal order (product ID: %d).', $product_id ) );
    $order->save();
  }

  // Member-facing access.

  /**
   * Whether the current logged-in user is this bundle's owner (billing contact).
   *
   * Renewal is owner-only: the renewal order belongs to the subscription customer, and
   * WooCommerce only lets that customer pay it.
   *
   * @return bool
   */
  public function is_current_user_owner(): bool {
    $owner_id = $this->get_owner_id();
    return $owner_id && is_user_logged_in() && (int) $owner_id === get_current_user_id();
  }

  /**
   * Check whether the current user may manage a bundle from the member portal.
   *
   * Allows any logged-in member with an active MDP connection to the bundle's organisation
   * (e.g. an org delegate who did not personally purchase the bundle). MDP is queried live
   * rather than trusting locally cached relationship data. Shared by the member-scoped REST
   * routes (Membership_Bundle_WP_REST_Controller::permissions_check_bundle_org_member()), the
   * detail template, and the renewal order handler, so every entry point applies the same rule.
   *
   * @param int $bundle_post_id
   * @return true|\WP_Error WP_Error data carries the HTTP status to return.
   */
  public static function check_current_user_access( int $bundle_post_id ) {
    if ( ! is_user_logged_in() ) {
      return new \WP_Error( 'not_logged_in', 'Authentication required.', [ 'status' => 401 ] );
    }

    $cache_key = get_current_user_id() . ':' . $bundle_post_id;
    if ( isset( self::$access_cache[ $cache_key ] ) ) {
      return self::$access_cache[ $cache_key ];
    }

    $result = self::resolve_current_user_access( $bundle_post_id );

    self::$access_cache[ $cache_key ] = $result;

    return $result;
  }

  /**
   * Uncached body of check_current_user_access().
   *
   * @param int $bundle_post_id
   * @return true|\WP_Error
   */
  private static function resolve_current_user_access( int $bundle_post_id ) {
    $bundle = new self( $bundle_post_id );

    // Reject unknown/wrong-CPT IDs here rather than letting a handler's own
    // 404 fire after the request has already been treated as authorized.
    if ( ! $bundle->post_id ) {
      return new \WP_Error( 'not_found', 'Membership bundle not found.', [ 'status' => 404 ] );
    }

    $org_uuid = $bundle->get_org_uuid();

    if ( ! $org_uuid ) {
      // A bundle with no linked org has no member to authorize against.
      return new \WP_Error( 'forbidden', 'You do not have access to this membership bundle.', [ 'status' => 403 ] );
    }

    $person_uuid = function_exists( 'wicket_current_person_uuid' ) ? wicket_current_person_uuid() : '';
    if ( empty( $person_uuid ) ) {
      return new \WP_Error( 'no_person', 'Unable to resolve current member.', [ 'status' => 403 ] );
    }

    $connections = function_exists( 'wicket_get_active_person_org_connections' )
      ? wicket_get_active_person_org_connections( $person_uuid, $org_uuid )
      : [];

    if ( is_wp_error( $connections ) || empty( $connections ) ) {
      return new \WP_Error( 'forbidden', 'You do not have access to this membership bundle.', [ 'status' => 403 ] );
    }

    return true;
  }

  /**
   * Get the stored membership date fields for this bundle.
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
      "Reassigning customer to {$user->user_email} on membership bundle ownership change."
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
      "Reassigning customer to {$user->user_email} on membership bundle ownership change."
    );
  }

  /**
   * Create a pending WC subscription for this bundle.
   *
   * Called only from create() after dates are written. No product line items are
   * added here — those are attached when individual members are added to the bundle.
   * Always created 'pending' regardless of the bundle's own initial status — create()
   * promotes it to 'active' afterward via activate_subscription_for_dates() when the
   * bundle itself starts active, reusing the same mechanism as the pending→active
   * admin transition rather than duplicating status/date logic here.
   *
   * @return int|false Subscription post ID on success, false on any failure.
   */
  private function create_bundle_subscription(): int|false {
    // WooCommerce Subscriptions may not be active in all environments.
    if ( ! function_exists( 'wcs_create_subscription' ) ) {
      Wicket()->log()->error( 'Membership_Bundle::create_bundle_subscription: wcs_create_subscription not available', [
        'source'  => 'wicket-memberships',
        'post_id' => $this->post_id,
      ] );
      return false;
    }

    // Subscription must be assigned to a real WP customer.
    $owner_id = $this->get_owner_id();
    if ( ! $owner_id ) {
      Wicket()->log()->error( 'Membership_Bundle::create_bundle_subscription: could not resolve owner_id', [
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
      Wicket()->log()->error( 'Membership_Bundle::create_bundle_subscription: could not resolve config', [
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
      Wicket()->log()->error( 'Membership_Bundle::create_bundle_subscription: wcs_create_subscription failed', [
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

    // WCS validates end > next_payment (strictly); set both explicitly, then let
    // Subscription_Manager nudge next_payment if they'd collide (no grace period).
    $end = date( 'Y-m-d H:i:s', strtotime( $end_target ) );
    $next_payment = date( 'Y-m-d H:i:s', strtotime( $dates['ends_at'] ) );

    $sub->update_dates( Subscription_Manager::prepare_dates( [
      'end'          => $end,
      'next_payment' => $next_payment,
    ], $sub ) );

    // Link the subscription back to the bundle and forward org identity so admin
    // screens and MDP sync can identify which organisation owns this subscription
    // without an extra bundle lookup. Mirrors the _org_uuid pattern on individual
    // org membership subscriptions (Membership_Controller.php lines 83–84).
    // org_name is stored alongside _org_uuid so the subscription is human-readable
    // in WC admin without following the bundle post link.
    //
    // Staged via update_meta_data() + save(), not a standalone update_post_meta() call —
    // $sub->save() re-syncs meta from its own in-memory store and can drop a key that
    // bypassed it.
    $org_uuid = $this->get_org_uuid();
    $org_name = get_post_meta( $this->post_id, 'org_name', true );
    $sub->update_meta_data( 'membership_bundle_id', $this->post_id );
    $sub->update_meta_data( '_org_uuid', $org_uuid );
    if ( ! empty( $org_name ) ) {
      $sub->update_meta_data( 'org_name', $org_name );
    }

    $sub->save();

    return $sub->get_id();
  }

  // -------------------------------------------------------------------------
  // Action Scheduler date trigger jobs
  // -------------------------------------------------------------------------

  /**
   * Schedule one-time AS jobs for each date milestone on this bundle.
   *
   * Jobs fire do_action hooks consumed by AutomateWoo triggers. They do not
   * write status — that is the daily cron's responsibility. Safe to call on
   * create and on every date edit; existing jobs are cancelled before rescheduling.
   */
  public function schedule_date_trigger_jobs(): void {
    $dates = $this->get_dates();

    $early_renew_at = ! empty( $dates['early_renew_at'] ) ? strtotime( $dates['early_renew_at'] ) : null;
    $ends_at        = ! empty( $dates['ends_at'] )        ? strtotime( $dates['ends_at'] )        : null;
    $expires_at     = ! empty( $dates['expires_at'] )     ? strtotime( $dates['expires_at'] )     : null;

    $args  = [ 'bundle_post_id' => $this->post_id ];
    $bundle = 'wicket-memberships';

    // Cancel existing jobs before rescheduling so date edits don't leave stale jobs.
    as_unschedule_action( 'wicket_bundle_early_renew_at', $args, $bundle );
    as_unschedule_action( 'wicket_bundle_ends_at',        $args, $bundle );
    as_unschedule_action( 'wicket_bundle_expires_at',     $args, $bundle );

    if ( $early_renew_at ) {
      as_schedule_single_action( $early_renew_at, 'wicket_bundle_early_renew_at', $args, $bundle, false );
    }
    if ( $ends_at ) {
      as_schedule_single_action( $ends_at,    'wicket_bundle_ends_at',    $args, $bundle, false );
    }
    if ( $expires_at ) {
      as_schedule_single_action( $expires_at, 'wicket_bundle_expires_at', $args, $bundle, false );
    }
  }

  // -------------------------------------------------------------------------
  // MDP sync stubs — no-ops until MDP bundle API is available.
  // All three methods guard on bypass_wicket so dev/test environments are safe.
  // -------------------------------------------------------------------------

  /**
   * Sync a newly created bundle to the MDP.
   * Called at the end of create() on the returned instance.
   */
  public function sync_mdp_create(): void {
    if ( ! empty( $this->bypass_wicket ) ) {
      return;
    }

    if ( ! function_exists( 'wicket_create_bundle_membership' ) ) {
      Wicket()->log()->error( 'Membership_Bundle::sync_mdp_create: wicket_create_bundle_membership() not available', [
        'source'  => 'wicket-memberships',
        'post_id' => $this->post_id,
      ] );
      return;
    }

    $owner_uuid = $this->get_owner_uuid();
    $org_uuid   = $this->get_org_uuid();
    $name       = $this->get_name();
    $dates      = $this->get_dates();

    if ( empty( $owner_uuid ) || empty( $org_uuid ) || empty( $name ) ) {
      Wicket()->log()->error( 'Membership_Bundle::sync_mdp_create: missing required fields', [
        'source'     => 'wicket-memberships',
        'post_id'    => $this->post_id,
        'owner_uuid' => $owner_uuid,
        'org_uuid'   => $org_uuid,
        'name'       => $name,
      ] );
      return;
    }

    $config            = $this->get_config();
    $grace_period_days = $config ? (int) $config->get_late_fee_window_days() : 0;

    $response = wicket_create_bundle_membership(
      $owner_uuid,
      $org_uuid,
      $name,
      $dates['starts_at'] ?? '',
      $dates['ends_at'] ?? '',
      $grace_period_days,
      '',
      (string) $this->post_id
    );

    if ( is_wp_error( $response ) ) {
      Wicket()->log()->error( 'Membership_Bundle::sync_mdp_create: MDP error', [
        'source'  => 'wicket-memberships',
        'post_id' => $this->post_id,
        'error'   => $response->get_error_message(),
      ] );
      return;
    }

    $bundle_mdp_uuid = $response['data']['id'] ?? '';
    if ( ! empty( $bundle_mdp_uuid ) ) {
      update_post_meta( $this->post_id, 'membership_bundle_mdp_uuid', $bundle_mdp_uuid );
    }
  }

  /**
   * Sync current bundle state to the MDP after any local write.
   * Covers status changes, date edits, owner changes, and cancellation (end-dating).
   * Callers commit all changes to post meta before calling this — inference is safe.
   */
  public function sync_mdp_update(): void {
    if ( ! empty( $this->bypass_wicket ) ) {
      return;
    }

    $bundle_mdp_uuid = get_post_meta( $this->post_id, 'membership_bundle_mdp_uuid', true );
    if ( empty( $bundle_mdp_uuid ) ) {
      return;
    }

    if ( ! function_exists( 'wicket_update_bundle_membership' ) || ! function_exists( 'wicket_update_bundle_membership_owner' ) ) {
      Wicket()->log()->error( 'Membership_Bundle::sync_mdp_update: base-plugin bundle helpers not available', [
        'source'  => 'wicket-memberships',
        'post_id' => $this->post_id,
      ] );
      return;
    }

    $dates  = $this->get_dates();
    $config = $this->get_config();
    $grace  = $config ? (int) $config->get_late_fee_window_days() : false;

    $response = wicket_update_bundle_membership(
      $bundle_mdp_uuid,
      $dates['starts_at'] ?? '',
      $dates['ends_at'] ?? '',
      $grace,
      $this->get_name()
    );

    if ( is_wp_error( $response ) ) {
      Wicket()->log()->error( 'Membership_Bundle::sync_mdp_update: MDP date/name update error', [
        'source'          => 'wicket-memberships',
        'post_id'         => $this->post_id,
        'bundle_mdp_uuid' => $bundle_mdp_uuid,
        'error'           => $response->get_error_message(),
      ] );
    }

    $owner_uuid = $this->get_owner_uuid();
    if ( ! empty( $owner_uuid ) ) {
      $owner_response = wicket_update_bundle_membership_owner( $bundle_mdp_uuid, $owner_uuid );
      if ( is_wp_error( $owner_response ) ) {
        Wicket()->log()->error( 'Membership_Bundle::sync_mdp_update: MDP owner update error', [
          'source'          => 'wicket-memberships',
          'post_id'         => $this->post_id,
          'bundle_mdp_uuid' => $bundle_mdp_uuid,
          'error'           => $owner_response->get_error_message(),
        ] );
      }
    }
  }

  /**
   * Delete the bundle's MDP record when the WP post is hard-deleted (wp_trash_post).
   * NOT called on cancellation — cancellation end-dates via sync_mdp_update().
   */
  public function sync_mdp_delete(): void {
    if ( ! empty( $this->bypass_wicket ) ) {
      return;
    }

    $bundle_mdp_uuid = get_post_meta( $this->post_id, 'membership_bundle_mdp_uuid', true );
    if ( empty( $bundle_mdp_uuid ) ) {
      return;
    }

    if ( ! function_exists( 'wicket_delete_bundle_membership' ) ) {
      Wicket()->log()->error( 'Membership_Bundle::sync_mdp_delete: wicket_delete_bundle_membership() not available', [
        'source'  => 'wicket-memberships',
        'post_id' => $this->post_id,
      ] );
      return;
    }

    $response = wicket_delete_bundle_membership( $bundle_mdp_uuid );

    if ( is_wp_error( $response ) ) {
      Wicket()->log()->error( 'Membership_Bundle::sync_mdp_delete: MDP error', [
        'source'          => 'wicket-memberships',
        'post_id'         => $this->post_id,
        'bundle_mdp_uuid' => $bundle_mdp_uuid,
        'error'           => $response->get_error_message(),
      ] );
    }
  }
}
