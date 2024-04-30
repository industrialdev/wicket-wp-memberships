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
       'organization_location' => 'org_location',
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

  public static function get_membership_json_from_membership_post_data( $membership_array ) {
    $membership_json_data = array();
    $mapping_keys = [
        'member_type' => 'membership_type',
        'start_date' => 'membership_starts_at',
        'end_date' => 'membership_ends_at',
        'expiry_date' => 'membership_expires_at',
        'early_renew_date' => 'membership_early_renew_at',
        'wicket_uuid' => 'membership_wicket_uuid',
        'user_name' => 'membership_wp_user_display_name',
        'user_email' => 'membership_wp_user_email',
        'membership_order_id' => 'membership_parent_order_id',
        'org_name' => 'organization_name',
        'org_location' => 'organization_location',
        'org_uuid' => 'organization_uuid',
        'org_seats' => 'membership_seats',
    ];
    array_walk(
      $membership_array,
      function(&$val, $key) use (&$membership_json_data, $mapping_keys)
      {
        $new_key = $mapping_keys[$key];
        if( empty($new_key) ) {
          return;
        }
        $membership_json_data[$new_key] = $val;
      }
    );  
    $membership_json = json_encode( $membership_json_data );
    return $membership_json;
  }

  public static function get_org_data( $org_uuid ) {
    $org_data = json_decode( get_option( 'org_data_'. $org_uuid ), true);
    $data['location'] = $org_data['included'][0]['attributes']['city'] . ', ';
    $data['location'] .= $org_data['included'][0]['attributes']['state_name'] . ', ';
    $data['location'] .= $org_data['included'][0]['attributes']['country_code'];
    $data['name'] = $org_data['data']['attributes']['alternate_name'];
    return $data;
  }
}