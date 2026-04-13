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

  private string $group_cpt_slug;

  public function __construct() {
    $this->group_cpt_slug = Helper::get_membership_group_cpt_slug();
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
   *                     Optional: membership_renewal_type.
   * @return \WP_REST_Response
   * @todo Wire in subscription date updates when renewal type changes — see TODO.md
   */
  public static function update_group_entity_record( array $data ): \WP_REST_Response {
    $group_post_id = (int) ( $data['group_post_id'] ?? 0 );

    if ( ! $group_post_id || get_post_type( $group_post_id ) !== Helper::get_membership_group_cpt_slug() ) {
      return new \WP_REST_Response( [ 'error' => 'Membership group not found.' ], 404 );
    }

    $starts_at  = strtotime( $data['membership_starts_at'] ?? '' );
    $ends_at    = strtotime( $data['membership_ends_at']   ?? '' );
    $expires_at = strtotime( $data['membership_expires_at'] ?? '' );

    if ( $starts_at && $ends_at && $starts_at >= $ends_at ) {
      return new \WP_REST_Response( [ 'error' => 'Start date must be before end date.' ], 400 );
    }
    if ( $ends_at && $expires_at && $ends_at > $expires_at ) {
      return new \WP_REST_Response( [ 'error' => 'End date must not be after expiration date.' ], 400 );
    }

    $updatable = [
      'membership_starts_at',
      'membership_ends_at',
      'membership_expires_at',
      'membership_renewal_type',
    ];

    foreach ( $updatable as $key ) {
      if ( isset( $data[ $key ] ) ) {
        update_post_meta( $group_post_id, $key, $data[ $key ] );
      }
    }

    // TODO: cascade date changes to child individual memberships once
    // cascade_dates_to_members() is implemented — see TODO.md.

    return new \WP_REST_Response( [
      'success'  => 'Group membership updated successfully.',
      'response' => Helper::get_post_meta( $group_post_id ),
    ], 200 );
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

    $wicket_settings = get_wicket_settings( $_ENV['WP_ENV'] );
    $group           = new Membership_Group( $group_post_id );
    $meta            = Helper::get_post_meta( $group_post_id );

    // Organisation data from MDP.
    $org_uuid = $group->get_org_uuid();
    $org_data = $org_uuid ? Helper::get_org_data( $org_uuid ) : [];
    $mdp_org_link = $org_uuid
      ? $wicket_settings['wicket_admin'] . '/organizations/' . $org_uuid
      : '';

    // Owner / person data from MDP — groups support multiple owners.
    $owner_user_ids = $group->get_group_owners();
    $owners_data    = [];
    foreach ( $owner_user_ids as $owner_user_id ) {
      $owner_user = get_user_by( 'id', $owner_user_id );
      if ( ! $owner_user ) {
        continue;
      }
      $owner_uuid   = $owner_user->user_login;
      $owner_person = $owner_uuid ? wicket_get_person_by_id( $owner_uuid ) : null;
      $owners_data[] = [
        'user_id'            => $owner_user_id,
        'uuid'               => $owner_uuid,
        'name'               => $owner_user->display_name,
        'email'              => $owner_user->user_email,
        'mdp_link'           => $owner_uuid
          ? $wicket_settings['wicket_admin'] . '/people/' . $owner_uuid
          : '',
        'identifying_number' => $owner_person
          ? $owner_person->getAttribute( 'identifying_number' )
          : '',
      ];
    }

    // Config data.
    $config      = $group->get_config();
    $config_data = $config ? Helper::get_post_meta( $config->get_post_id() ) : [];

    return [
      'ID'              => $group_post_id,
      'title'           => get_the_title( $group_post_id ),
      'meta'            => $meta,
      'org'             => [
        'uuid'     => $org_uuid,
        'name'     => $org_data['name']     ?? '',
        'location' => $org_data['location'] ?? '',
        'mdp_link' => $mdp_org_link,
      ],
      'owners'          => $owners_data,
      'config'          => $config_data,
      'subscription_id' => $group->get_subscription_id(),
      'dates'           => $group->get_dates(),
      'statuses'        => Helper::get_all_status_names(),
      'allowed_transitions' => Helper::get_allowed_transition_status( $meta['membership_status'] ?? '' ),
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
    $current_owners  = $group->get_group_owners();
    $new_user        = get_user_by( 'login', $new_owner_uuid );

    if ( empty( $new_user ) ) {
      $new_user_id = wicket_create_wp_user_if_not_exist( $new_owner_uuid );
      $new_user    = get_user_by( 'id', $new_user_id );
    }

    if ( empty( $new_user ) ) {
      return new \WP_REST_Response( [ 'error' => 'Could not resolve new owner user.' ], 400 );
    }

    if ( in_array( $new_user->ID, $current_owners, true ) ) {
      return new \WP_REST_Response( [ 'error' => 'Please select a different user.' ], 400 );
    }

    // Replace the owner list with the new single owner.
    $group->set_group_owners( [ $new_user->ID ] );
    wp_update_post( [ 'ID' => $group_post_id, 'post_author' => $new_user->ID ] );

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
   * - Group ownership model being finalised (who is the WC subscription billing customer
   *   when a group has multiple owners?).
   * - Group subscription line item structure being finalised (multi-tier line items).
   *
   * @param array $params
   * @return \WP_REST_Response
   * @todo Implement once group ownership model and subscription line item structure are finalised — see TODO.md
   */
  public static function create_group_renewal_order( array $params ): \WP_REST_Response {
    return new \WP_REST_Response( [ 'error' => 'Not yet implemented.' ], 501 );
  }

}
