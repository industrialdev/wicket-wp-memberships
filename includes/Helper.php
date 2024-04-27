<?php

namespace Wicket_Memberships;

defined( 'ABSPATH' ) || exit;

class Helper {
  public static function get_membership_config_cpt_slug() {
    return 'wicket_mship_config';
  }

  public static function get_membership_cpt_slug() {
    return 'wicket_membership';
  }

  public static function get_membership_tier_cpt_slug() {
    return 'wicket_mship_tier';
  }

  public static function get_all_status_names() {
    return [
      Wicket_Memberships::STATUS_ACTIVE => [
        'name' => __('Active', 'wicket-memberships'),
        'slug' => Wicket_Memberships::STATUS_ACTIVE
      ],
      Wicket_Memberships::STATUS_GRACE => [
        'name' => __('Grace Period', 'wicket-memberships'),
        'slug' => Wicket_Memberships::STATUS_GRACE
      ],
      Wicket_Memberships::STATUS_PENDING => [
        'name' => __('Pending Approval', 'wicket-memberships'),
        'slug' => Wicket_Memberships::STATUS_PENDING
      ],
      Wicket_Memberships::STATUS_DELAYED => [
        'name' => __('Delayed', 'wicket-memberships'),
        'slug' => Wicket_Memberships::STATUS_DELAYED
      ],
      Wicket_Memberships::STATUS_CANCELLED => [
        'name' => __('Cancelled', 'wicket-memberships'),
        'slug' => Wicket_Memberships::STATUS_CANCELLED
      ],
      Wicket_Memberships::STATUS_EXPIRED => [
        'name' => __('Expired', 'wicket-memberships'),
        'slug' => Wicket_Memberships::STATUS_EXPIRED
      ],
    ];
  }

  public static function get_allowed_transition_status( $status ) {
    if( $status == Wicket_Memberships::STATUS_PENDING ) {
      return [
        Wicket_Memberships::STATUS_ACTIVE => [
          'name' => __('Active', 'wicket-memberships'),
          'slug' => Wicket_Memberships::STATUS_ACTIVE
        ],
        Wicket_Memberships::STATUS_CANCELLED => [
          'name' => __('Cancelled', 'wicket-memberships'),
          'slug' => Wicket_Memberships::STATUS_CANCELLED
        ],
      ];
    } else if( $status == Wicket_Memberships::STATUS_DELAYED ) {
      return [
        Wicket_Memberships::STATUS_CANCELLED => [
          'name' => __('Cancelled', 'wicket-memberships'),
          'slug' => Wicket_Memberships::STATUS_CANCELLED
        ],  
      ];
    } else if( $status == Wicket_Memberships::STATUS_GRACE ) {
      return [
        Wicket_Memberships::STATUS_EXPIRED => [
          'name' => __('Expired', 'wicket-memberships'),
          'slug' => Wicket_Memberships::STATUS_EXPIRED
        ],
        Wicket_Memberships::STATUS_CANCELLED => [
          'name' => __('Cancelled', 'wicket-memberships'),
          'slug' => Wicket_Memberships::STATUS_CANCELLED
        ],  
      ];
    } else if( $status == Wicket_Memberships::STATUS_ACTIVE ) {
      return [
        Wicket_Memberships::STATUS_CANCELLED => [
          'name' => __('Cancelled', 'wicket-memberships'),
          'slug' => Wicket_Memberships::STATUS_CANCELLED
        ],
      ];
    }
  }
  /*

                'membership_parent_order_id' => $order_id,
                'membership_subscription_id' => $subscription_id,
                'membership_product_id' => $product_id,
                'membership_tier_post_id' => $membership_tier->get_membership_tier_post_id(),
                'membership_tier_name' => $membership_tier->tier_data['mdp_tier_name'],
                'membership_tier_uuid' => $membership_tier->tier_data['mdp_tier_uuid'],
                'membership_type' => $membership_tier->tier_data['type'],
                'membership_starts_at' => $dates['start_date'],
                'membership_ends_at' =>  $dates['end_date'],
                'membership_expires_at' => !empty($dates['expires_at']) ? $dates['expires_at'] : $dates['end_date'],
                'membership_early_renew_at' => !empty($dates['early_renew_at']) ? $dates['early_renew_at'] : $dates['end_date'],
                'membership_period' => $period_data['period_type'],
                'membership_interval' => $period_data['period_count'],
                'membership_subscription_period' => get_post_meta( $subscription_id, '_billing_period')[0],
                'membership_subscription_interval' => get_post_meta( $subscription_id, '_billing_interval')[0],
                'membership_wp_user_id' => $user_object->ID,
                'membership_wp_user_display_name' => $user_object->display_name,
                'membership_wp_user_email' => $user_object->user_email
  */

  public static function get_membership_post_data_from_membership_json( $membership_json ) {
    $membership_post_data = array();
    $membership_array = json_decode( $membership_json, true);
    $mapping_keys = [
       'membership_type' => 'member_type',
       'membership_starts_at' => 'start_date',
       'membership_ends_at' => 'end_date',
       'membership_expires_at' => 'expiry_date',
       'membership_early_renew_at' => 'early_renew_date',
       'membership_wicket_uuid' => 'wicket_uuid',
       'membership_wp_user_display_name' => 'user_name',
       'membership_wp_user_email' => 'user_email',
       'membership_parent_order_id' => 'membership_order_id',
       'organization_name' => 'org_name',
       'organization_uuid' => 'org_uuid',
       'membership_seats' => 'org_seats',
    ];
    array_walk(
      $membership_array,
      function(&$val, $key) use (&$membership_post_data, $mapping_keys)
      {
        $new_key = $mapping_keys[$key];
        if( empty($new_key) ) {
          return;
        }
        $membership_post_data[$new_key] = $val;
      }
    );  
    return $membership_post_data;
  }
}