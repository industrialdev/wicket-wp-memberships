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
          $skip_approval = !empty( $record['skip_approval'] ) ? true : false;
          if( $record['Membership_Type'] == 'organization' ) {
            return new \WP_REST_Response(['success' => 'Skipping an Org Seat Membership.']);
          }
          $membership_post_mapping['membership_type'] = $record['Membership_Type'];
          $membership_tier_post_id = Membership_Tier::get_tier_id_by_wicket_uuid( $record['Membership_Tier_UUID'] );
          if( !empty( $membership_tier_post_id ) ) {
            $membership_tier_array = $this->get_tier_by_id( $membership_tier_post_id );
          } else {
            return new \WP_REST_Response(['error' => 'Missing Tier in Plugin.']);
          }


          $user = get_user_by('login', $record['Person_UUID']);
          if(empty($user)) {
            $user_id = wicket_create_wp_user_if_not_exist($record['Membership_Owner_UUID'], );
            $user = get_user_by('id', $user_id);
          }
          $membership_post_mapping['user_id'] = $user->ID;
          if( $user->display_name == $record['Person_UUID'] ) {
            $membership_post_mapping['membership_wp_user_display_name'] = $user->first_name . ' ' . $user->last_name;
          } else {
            $membership_post_mapping['membership_wp_user_display_name'] = $user->display_name;
          }
          $membership_post_mapping['membership_wp_user_display_name'] = $user->display_name;
    	    $membership_post_mapping['membership_wp_user_email'] = $user->user_email;
          
          $membership_post_mapping['pin'] = $record['Person_Identifying_Number'];
        	$membership_post_mapping['membership_tier_name'] = $record['Membership_Tier'];
          $membership_post_mapping['membership_tier_slug'] = $record['Membership_Tier_Slug'];
          $membership_post_mapping['membership_tier_cat'] = $record['Membership_Tier_Category'];
          $membership_post_mapping['membership_tier_post_id'] = $membership_tier_post_id;
          
          $membership_post_mapping['membership_starts_at'] = $record['Starts_At'];
          $membership_post_mapping['membership_ends_at'] = $record['Ends_At'];
          $membership_post_mapping['membership_expires_at'] = $record['Expires_At'];
          if(empty( $membership_post_mapping['membership_expires_at'] )) {
            $membership_post_mapping['membership_expires_at'] =
                  (new \DateTime( date('Y-m-d', strtotime($membership_post_mapping['membership_ends_at']. " + {$membership_tier_array['grace_period_days']} days")), wp_timezone() ))->format("c");
            $membership_post_mapping['membership_expires_at'] = str_replace( "00:00:00-", "", $membership_post_mapping['membership_expires_at']).':00Z';
          }
          $membership_post_mapping['membership_early_renew_at'] = 
                (new \DateTime( date('Y-m-d', strtotime($membership_post_mapping['membership_ends_at']. " - {$membership_tier_array['early_renew_days']} days")), wp_timezone() ))->format("c");
          $membership_post_mapping['membership_early_renew_at'] = str_replace( "00:00:00-", "", $membership_post_mapping['membership_early_renew_at']).':00Z';
          $membership_post_mapping['membership_seats'] = 1;
          $membership_post_mapping['membership_wicket_uuid'] = $record['Person_Membership_UUID'];
          $membership_post_mapping['membership_tier_uuid'] = $membership_tier_array['tier_uuid'];
          $membership_post_mapping['membership_next_tier_id'] = $membership_tier_array['next_tier'];
          $membership_post_mapping['membership_period'] = $membership_tier_array['period_type'];
          $membership_post_mapping['membership_interval'] = $membership_tier_array['period_count'];
          $membership_post_mapping['membership_status'] = $this->get_status( 
                                                            $membership_post_mapping['membership_starts_at'], 
                                                            $membership_post_mapping['membership_ends_at'], 
                                                            $membership_post_mapping['membership_expires_at'] );

          $response = (new Membership_Controller)->create_local_membership_record( $membership_post_mapping, $membership_post_mapping['membership_wicket_uuid'], $skip_approval );
          return new \WP_REST_Response(['success' => 'Individual Membership created: External_ID#'.$response ]);
        }

        public function create_organization_memberships( $record ) {
          $skip_approval = !empty( $record['skip_approval'] ) ? true : false;
          $membership_post_mapping['membership_type'] = $record['Membership_Type'];
          $membership_tier_post_id = Membership_Tier::get_tier_id_by_wicket_uuid( $record['Membership_Tier_UUID'] );
          if( !empty( $membership_tier_post_id ) ) {
            $membership_tier_array = $this->get_tier_by_id( $membership_tier_post_id );
          } else {
            return new \WP_REST_Response(['error' => 'Missing Tier in Plugin.']); 
          }

          $user = get_user_by('login', $record['Membership_Owner_UUID']);
          if(empty($user)) {
            $user_id = wicket_create_wp_user_if_not_exist($record['Membership_Owner_UUID'], );
            $user = get_user_by('id', $user_id);
          }
          $membership_post_mapping['user_id'] = $user->ID;
          $membership_post_mapping['membership_wp_user_display_name'] = $user->display_name;
    	    $membership_post_mapping['membership_wp_user_email'] = $user->user_email;
          $membership_post_mapping['pin'] = $record['Organization_Identifying_Number'];
        	
          $membership_post_mapping['membership_tier_name'] = $record['Membership_Tier'];
          $membership_post_mapping['membership_tier_slug'] = $record['Membership_Tier_Slug'];
          $membership_post_mapping['membership_tier_cat'] = $record['Membership_Tier_Category'];
          $membership_post_mapping['membership_tier_post_id'] = $membership_tier_post_id;

          $membership_post_mapping['membership_starts_at'] = $record['Starts_At'];
          $membership_post_mapping['membership_ends_at'] = $record['Ends_At'];
          $membership_post_mapping['membership_expires_at'] = $record['Expires_At'];
          if(empty( $membership_post_mapping['membership_expires_at'] )) {
            $membership_post_mapping['membership_expires_at'] =
                  (new \DateTime( date('Y-m-d', strtotime($membership_post_mapping['membership_ends_at']. " + {$membership_tier_array['grace_period_days']} days")), wp_timezone() ))->format("c");
            $membership_post_mapping['membership_expires_at'] = str_replace( "00:00:00-", "", $membership_post_mapping['membership_expires_at']).':00Z';
          }
          $membership_post_mapping['membership_early_renew_at'] = 
                (new \DateTime( date('Y-m-d', strtotime($membership_post_mapping['membership_ends_at']. " - {$membership_tier_array['early_renew_days']} days")), wp_timezone() ))->format("c");
          $membership_post_mapping['membership_early_renew_at'] = str_replace( "00:00:00-", "", $membership_post_mapping['membership_early_renew_at']).':00Z';
          $membership_post_mapping['org_name'] = $record['Organization'];
          $membership_post_mapping['organization_uuid'] = $record['Organization_UUID'];
          $membership_post_mapping['membership_seats'] = $record['Max_assignments'];
          $membership_post_mapping['membership_wicket_uuid'] = $record['Organization_Membership_UUID'];
          $membership_post_mapping['membership_tier_uuid'] = $membership_tier_array['tier_uuid'];
          $membership_post_mapping['membership_next_tier_id'] = $membership_tier_array['next_tier'];
          $membership_post_mapping['membership_period'] = $membership_tier_array['period_type'];
          $membership_post_mapping['membership_interval'] = $membership_tier_array['period_count'];
          $membership_post_mapping['membership_status'] = $this->get_status( 
                                                            $membership_post_mapping['membership_starts_at'], 
                                                            $membership_post_mapping['membership_ends_at'], 
                                                            $membership_post_mapping['membership_expires_at'] );

          $response = (new Membership_Controller)->create_local_membership_record( $membership_post_mapping, $membership_post_mapping['membership_wicket_uuid'], $skip_approval );
          return new \WP_REST_Response(['success' => 'Organization Membership created: External_ID#'.$response ]);
        }

        private function get_status( $membership_starts_at, $membership_ends_at, $membership_expires_at ) {
          $membership_starts_at = strtotime( $membership_starts_at );
          $membership_ends_at = strtotime( $membership_ends_at );
          $membership_expires_at = strtotime( $membership_expires_at );
          if( current_time( 'timestamp' ) >= $membership_starts_at && current_time( 'timestamp' ) < $membership_ends_at ) {
            $status = Wicket_Memberships::STATUS_ACTIVE;
          } else if (current_time( 'timestamp' ) >= $membership_ends_at && current_time( 'timestamp' ) < $membership_expires_at ) {
            $status = Wicket_Memberships::STATUS_GRACE;
          } else if( $membership_starts_at > current_time( 'timestamp' ) ) {
            $status = Wicket_Memberships::STATUS_DELAYED;
          } else if ( $membership_ends_at < current_time( 'timestamp' ) ) {
            $status = Wicket_Memberships::STATUS_EXPIRED;
          }
          return $status;
        }

  private function get_tier_by_id( $tier_id ) {
    $response['ID'] = $tier_id;
    $tier_data = maybe_unserialize( get_post_meta( $tier_id, 'tier_data', true ));
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
}