<?php

namespace Wicket_Memberships;

defined( 'ABSPATH' ) || exit;

class Import_Controller {

  private $error_message = '';
  private $membership_cpt_slug = '';
  private $membership_config_cpt_slug = '';
  private $membership_tier_cpt_slug = '';
  private $membership_search_term = '';

  public function __construct() {
    $this->membership_cpt_slug = Helper::get_membership_cpt_slug();
    $this->membership_config_cpt_slug = Helper::get_membership_config_cpt_slug();
    $this->membership_tier_cpt_slug = Helper::get_membership_tier_cpt_slug();
  }

  public function create_individual_memberships( $record ) {
          $membership_tier_array = $this->get_tier_by_name( $record['Membership_Tier'] );
          if(empty( $membership_tier_array )) {
            return new \WP_REST_Response(['error' => 'Missing Tier in Plugin.']);
          }

          $user = get_user_by('login', $record['Person_UUID']);
          $membership_post_mapping['user_id'] = $user->ID;
          $membership_post_mapping['membership_wp_user_display_name'] = $user->display_name;
    	    $membership_post_mapping['membership_wp_user_email'] = $user->user_email;
          
          $membership_post_mapping['pin'] = $record['Person_Identifying_Number'];
        	$membership_post_mapping['membership_tier_name'] = $record['Membership_Tier'];
          $membership_post_mapping['membership_tier_slug'] = $record['Membership_Tier_Slug'];
          $membership_post_mapping['membership_tier_cat'] = $record['Membership_Tier_Category'];
          $membership_post_mapping['membership_tier_post_id'] = $membership_tier_array['ID'];

          $membership_post_mapping['membership_type'] = $record['Membership_Type'];
          
          $membership_post_mapping['membership_starts_at'] = $record['Starts_At'];
          $membership_post_mapping['membership_ends_at'] = $record['Ends_At'];
          $membership_post_mapping['membership_expires_at'] = $record['Expires_At'];
          if(empty( $membership_post_mapping['membership_expires_at'] )) {
            $membership_post_mapping['membership_expires_at'] =
                  (new \DateTime( date('Y-m-d', strtotime($membership_post_mapping['membership_expires_at']. " + {$membership_tier_array['grace_period_days']} days")), wp_timezone() ))->format("c");
            $membership_post_mapping['membership_expires_at'] = str_replace( "00:00:00-", "", $membership_post_mapping['membership_expires_at']).':00Z';
          }
          $membership_post_mapping['membership_early_renew_at'] = 
                (new \DateTime( date('Y-m-d', strtotime($membership_post_mapping['membership_early_renew_at']. " - {$membership_tier_array['early_renew_days']} days")), wp_timezone() ))->format("c");
          $membership_post_mapping['membership_early_renew_at'] = str_replace( "00:00:00-", "", $membership_post_mapping['membership_early_renew_at']).':00Z';
          #$membership_post_mapping[''] = $record['Status'];
          #$membership_post_mapping[''] = $record['In_Grace'];
          #$membership_post_mapping[''] = $record['Organization_Membership_Starts_At'];
          #$membership_post_mapping[''] = $record['Organization_Membership_Ends_At'];
          #$membership_post_mapping[''] = $record['Organization_Membership_Expires_At'];
          #$membership_post_mapping[''] = $record['Organization_Membership_In_Grace'];
          $membership_post_mapping['org_name'] = $record['Organization'];
          #$membership_post_mapping[''] = $record['Organization_Identifying_Number'];
          $membership_post_mapping['organization_uuid'] = $record['Organization_UUID'];
          $membership_post_mapping['membership_seats'] = 1;
          #$membership_post_mapping[''] = $record['External_ID'];

          $membership_post_mapping['membership_wicket_uuid'] = $record['Person_Membership_UUID'];
          #$membership_post_mapping[''] = $record['Created_At'];
          #$membership_post_mapping[''] = $record['Updated_At'];

          $membership_post_mapping['membership_tier_uuid'] = $membership_tier_array['tier_uuid'];
          $membership_post_mapping['membership_next_tier_id'] = $membership_tier_array['next_tier'];
          $membership_post_mapping['membership_period'] = $membership_tier_array['period_type'];
          $membership_post_mapping['membership_interval'] = $membership_tier_array['period_count'];

          $response = (new Membership_Controller)->create_local_membership_record( $membership_post_mapping, $membership_post_mapping['membership_wicket_uuid'] );
          return new \WP_REST_Response(['success' => 'Membership created: ID#'.$response ]);
        }

  private function get_tier_by_name( $tier_name ) {
    $args = array(
      'post_type' => $this->membership_tier_cpt_slug,
      'post_status' => 'publish',
      'posts_per_page' => 1,
      'title' => $tier_name
    );
    $membership_tier = get_posts( $args );
    if( empty( $membership_tier ) ) {
      return;
    }

    $response['ID'] = $membership_tier[0]->ID;
    $tier_data = maybe_unserialize( get_post_meta( $membership_tier[0]->ID, 'tier_data', true ));
    $response['tier_uuid'] = $tier_data['mdp_tier_uuid'];
    $response['next_tier'] = $tier_data['next_tier_id'];
    $tier_config_id = $tier_data['config_id'];
    $config = new Membership_Config( $tier_config_id );
    $response['early_renew_days'] = $config->get_renewal_window_days();
    $response['grace_period_days'] = $config->get_late_fee_window_days();
    $period_data = $config->get_period_data();
    $response['period_type'] = $period_data['period_type'];
    $response['period_count'] = $period_data['period_count'];
    return $response;
  }

  public function create_organization_memberships( $record ) {

  }

  

}