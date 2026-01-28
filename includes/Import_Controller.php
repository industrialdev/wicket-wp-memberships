<?php

namespace Wicket_Memberships;

use Wicket_Memberships\Utilities;

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
          if(empty($record)) {
            return 'File line read error.';
          }
          if($this->local_membership_exists($record['Person_Membership_UUID'])) {
            return new \WP_REST_Response(['error' => 'Already exists: Wicket UUID#'.$record['Person_Membership_UUID'] ]);
          }
          $skip_approval = !empty( $record['skip_approval'] ) ? true : false;
          if( $record['Membership_Type'] == 'organization' ) {
            return new \WP_REST_Response(['success' => 'Skipping an Org Seat Membership.']);
          }
          $membership_post_mapping['membership_type'] = $record['Membership_Type'];
          $membership_tier_post_id = Membership_Tier::get_tier_id_by_wicket_uuid( $record['Membership_Tier_UUID'] );
          if( !empty( $membership_tier_post_id ) ) {
            $membership_tier_array = $this->get_tier_by_id( $membership_tier_post_id );
            if(empty($membership_tier_array)) {
              return new \WP_REST_Response(['error' => 'Missing tier type designation in Tier Data.']);
            }
          } else {
            return new \WP_REST_Response(['error' => 'Missing Tier in Plugin.']);
          }

          $user = get_user_by('login', $record['Person_UUID']);
          if(empty($user)) {
            $user_id = wicket_create_wp_user_if_not_exist($record['Person_UUID'], );
            $user = get_user_by('id', $user_id);
          }
          $membership_post_mapping['user_id'] = $user->ID;
          $membership_post_mapping['membership_user_uuid'] = $record['Person_UUID'];
          if( $user->display_name == $record['Person_UUID'] ) {
            $membership_post_mapping['membership_wp_user_display_name'] = $user->first_name . ' ' . $user->last_name;
          } else {
            $membership_post_mapping['membership_wp_user_display_name'] = $user->display_name;
          }
          $membership_post_mapping['user_name'] = $membership_post_mapping['membership_wp_user_display_name'];
    	    $membership_post_mapping['membership_wp_user_email'] = $user->user_email;
          $membership_post_mapping['user_email'] = $membership_post_mapping['membership_wp_user_email'];
          
          $membership_post_mapping['pin'] = $record['Person_Identifying_Number'];
        	$membership_post_mapping['membership_tier_name'] = $record['Membership_Tier'];
          $membership_post_mapping['membership_tier_slug'] = $record['Membership_Tier_Slug'];
          $membership_post_mapping['membership_tier_cat'] = $record['Membership_Tier_Category'];
          $membership_post_mapping['membership_tier_post_id'] = $membership_tier_post_id;
          
          // Localize timestamps to MDP timezone with appropriate start/end of day
          $membership_post_mapping['membership_starts_at'] = Utilities::get_mdp_day_start( $record['Starts_At'] )->format('c');
          $membership_post_mapping['membership_ends_at'] = Utilities::get_mdp_day_end( $record['Ends_At'] )->format('c');
          
          if( empty( $record['Expires_At'] ) ) {
            // Calculate expires_at from ends_at + grace period, set to end of day
            $expires_date = date('Y-m-d', strtotime( $record['Ends_At'] . " + {$membership_tier_array['grace_period_days']} days" ));
            $membership_post_mapping['membership_expires_at'] = Utilities::get_mdp_day_end( $expires_date )->format('c');
          } else {
            $membership_post_mapping['membership_expires_at'] = Utilities::get_mdp_day_end( $record['Expires_At'] )->format('c');
          }
          
          // Calculate early_renew_at from ends_at - early renew days, set to start of day
          $early_renew_date = date('Y-m-d', strtotime( $record['Ends_At'] . " - {$membership_tier_array['early_renew_days']} days" ));
          $membership_post_mapping['membership_early_renew_at'] = Utilities::get_mdp_day_start( $early_renew_date )->format('c');
          
          $membership_post_mapping['membership_seats'] = 1;
          $membership_post_mapping['membership_wicket_uuid'] = $record['Person_Membership_UUID'];
          $membership_post_mapping['membership_tier_uuid'] = $membership_tier_array['tier_uuid'];

          $membership_post_mapping['membership_next_tier_id'] = $membership_tier_array['next_tier'];
          $membership_post_mapping['membership_next_tier_form_page_id'] = $membership_tier_array['next_tier_form_page_id'];
          $membership_post_mapping['membership_next_tier_subscription_renewal'] = $membership_tier_array['next_tier_subscription_renewal'];

          $membership_post_mapping['membership_period'] = $membership_tier_array['period_type'];
          $membership_post_mapping['membership_interval'] = $membership_tier_array['period_count'];
          $membership_post_mapping['membership_status'] = $this->get_status( 
                                                            $membership_post_mapping['membership_starts_at'], 
                                                            $membership_post_mapping['membership_ends_at'], 
                                                            $membership_post_mapping['membership_expires_at'] );
          if( !empty($record['Previous_External_ID'])) {
            $delta = true;
            $membership_post_mapping['previous_membership_post_id'] = $record['Previous_External_ID'];
          }

          $response = (new Membership_Controller)->create_local_membership_record( $membership_post_mapping, $membership_post_mapping['membership_wicket_uuid'], $skip_approval );
          $membership_id = $response;

          if(!empty($membership_id) && $membership_post_mapping['membership_status'] == Wicket_Memberships::STATUS_DELAYED) {
            $membership_starts_at = strtotime( $membership_post_mapping['membership_starts_at'] );
            as_schedule_single_action( $membership_starts_at, 'expire_old_membership_on_new_starts_at', [ 'previous_membership_post_id' => 0, 'new_membership_post_id' => $membership_id ], 'wicket-membership-plugin', false );
          }

          //if this is a delta import (Sheet has Previous_External_ID column value set)
          if( !empty($delta) ) {
            $membership_post_mapping['membership_post_id'] = $membership_id;
            //we are performing or scheduling the status changeover from current to next membership
            (new Membership_Controller)->scheduler_dates_for_expiry( $membership_post_mapping );
            $response .= ' Delta Membership ID#'.$membership_post_mapping['previous_membership_post_id'];
          } 
          
          //if this is a subscription renewal tier import we need to create the placeholder subscription for reference when renewing
          if( $membership_tier_array['renewal_type'] == 'subscription' ) {
            $subscription_id = $this->createSubscriptionForRenewal( $membership_post_mapping, $membership_id );
            $response .= ' Subscription created ID# '.$subscription_id;
          }

          return new \WP_REST_Response(['success' => 'Individual Membership created: External_ID#'.$response ]);
        }

        public function create_organization_memberships( $record ) {
          if(empty($record)) {
            return 'File line read error.';
          }
          if($this->local_membership_exists($record['Organization_Membership_UUID'])) {
            return new \WP_REST_Response(['error' => 'Already exists: Wicket_UUID#'.$record['Organization_Membership_UUID'] ]);
          }
          $skip_approval = !empty( $record['skip_approval'] ) ? true : false;
          $membership_post_mapping['membership_type'] = $record['Membership_Type'];
          $membership_tier_post_id = Membership_Tier::get_tier_id_by_wicket_uuid( $record['Membership_Tier_UUID'] );
          if( !empty( $membership_tier_post_id ) ) {
            $membership_tier_array = $this->get_tier_by_id( $membership_tier_post_id );
            if(empty($membership_tier_array)) {
              return new \WP_REST_Response(['error' => 'Missing tier type designation in Tier Data.']);
            }
          } else {
            return new \WP_REST_Response(['error' => 'Missing Tier in Plugin.']); 
          }

          $user = get_user_by('login', $record['Membership_Owner_UUID']);
          if(empty($user)) {
            $user_id = wicket_create_wp_user_if_not_exist($record['Membership_Owner_UUID'], );
            $user = get_user_by('id', $user_id);
          }
          $membership_post_mapping['user_id'] = $user->ID;
          $membership_post_mapping['membership_user_uuid'] = $record['Membership_Owner_UUID'];
          if( $user->display_name == $record['Membership_Owner_UUID'] ) {
            $membership_post_mapping['membership_wp_user_display_name'] = $user->first_name . ' ' . $user->last_name;
          } else {
            $membership_post_mapping['membership_wp_user_display_name'] = $user->display_name;
          }
          $membership_post_mapping['user_name'] = $membership_post_mapping['membership_wp_user_display_name'];
    	    $membership_post_mapping['membership_wp_user_email'] = $user->user_email;
          $membership_post_mapping['user_email'] = $membership_post_mapping['membership_wp_user_email'];

          $membership_post_mapping['pin'] = $record['Organization_Identifying_Number'];
        	
          $membership_post_mapping['membership_tier_name'] = $record['Membership_Tier'];
          $membership_post_mapping['membership_tier_slug'] = $record['Membership_Tier_Slug'];
          $membership_post_mapping['membership_tier_cat'] = $record['Membership_Tier_Category'];
          $membership_post_mapping['membership_tier_post_id'] = $membership_tier_post_id;

          // Localize timestamps to MDP timezone with appropriate start/end of day
          $membership_post_mapping['membership_starts_at'] = Utilities::get_mdp_day_start( $record['Starts_At'] )->format('c');
          $membership_post_mapping['membership_ends_at'] = Utilities::get_mdp_day_end( $record['Ends_At'] )->format('c');
          
          if( empty( $record['Expires_At'] ) ) {
            // Calculate expires_at from ends_at + grace period, set to end of day
            $expires_date = date('Y-m-d', strtotime( $record['Ends_At'] . " + {$membership_tier_array['grace_period_days']} days" ));
            $membership_post_mapping['membership_expires_at'] = Utilities::get_mdp_day_end( $expires_date )->format('c');
          } else {
            $membership_post_mapping['membership_expires_at'] = Utilities::get_mdp_day_end( $record['Expires_At'] )->format('c');
          }
          
          // Calculate early_renew_at from ends_at - early renew days, set to start of day
          $early_renew_date = date('Y-m-d', strtotime( $record['Ends_At'] . " - {$membership_tier_array['early_renew_days']} days" ));
          $membership_post_mapping['membership_early_renew_at'] = Utilities::get_mdp_day_start( $early_renew_date )->format('c');
          
          $membership_post_mapping['org_name'] = $record['Organization'];
          $membership_post_mapping['organization_uuid'] = $record['Organization_UUID'];
          $membership_post_mapping['membership_seats'] = $record['Max_assignments'];
          $membership_post_mapping['membership_wicket_uuid'] = $record['Organization_Membership_UUID'];
          $membership_post_mapping['membership_tier_uuid'] = $membership_tier_array['tier_uuid'];

          $membership_post_mapping['membership_next_tier_id'] = $membership_tier_array['next_tier'];
          $membership_post_mapping['membership_next_tier_form_page_id'] = $membership_tier_array['next_tier_form_page_id'];
          $membership_post_mapping['membership_next_tier_subscription_renewal'] = $membership_tier_array['next_tier_subscription_renewal'];

          $membership_post_mapping['membership_period'] = $membership_tier_array['period_type'];
          $membership_post_mapping['membership_interval'] = $membership_tier_array['period_count'];
          $membership_post_mapping['membership_status'] = $this->get_status( 
                                                            $membership_post_mapping['membership_starts_at'], 
                                                            $membership_post_mapping['membership_ends_at'], 
                                                            $membership_post_mapping['membership_expires_at'] );

          if( !empty($record['Previous_External_ID'])) {
            $delta = true;
            $membership_post_mapping['previous_membership_post_id'] = $record['Previous_External_ID'];
          }

          $response = (new Membership_Controller)->create_local_membership_record( $membership_post_mapping, $membership_post_mapping['membership_wicket_uuid'], $skip_approval );
          $membership_id = $response;

          if(!empty($membership_id) && $membership_post_mapping['membership_status'] == Wicket_Memberships::STATUS_DELAYED) {
            $membership_starts_at = strtotime( $membership_post_mapping['membership_starts_at'] );
            as_schedule_single_action( $membership_starts_at, 'expire_old_membership_on_new_starts_at', [ 'previous_membership_post_id' => 0, 'new_membership_post_id' => $membership_id ], 'wicket-membership-plugin', false );
          }

          //if this is a delta import (Sheet has Previous_External_ID column value set)
          if( !empty($delta) ) {
            $membership_post_mapping['membership_post_id'] = $membership_id;
            //we are performing or scheduling the status changeover from current to next membership
            (new Membership_Controller)->scheduler_dates_for_expiry( $membership_post_mapping );
            $response .= ' Delta Membership ID#'.$membership_post_mapping['previous_membership_post_id'];
          } 
          
          //if this is a subscription renewal tier import we need to create the placeholder subscription for reference when renewing
          if( $membership_tier_array['renewal_type'] == 'subscription' ) {
            $subscription_id = $this->createSubscriptionForRenewal( $membership_post_mapping, $membership_id );
            $response .= ' Subscription created ID# '.$subscription_id;
          }

          return new \WP_REST_Response(['success' => 'Organization Membership created: External_ID#'.$response ]);
        }

        private function get_status( $membership_starts_at, $membership_ends_at, $membership_expires_at ) {
          $membership_starts_at = strtotime( $membership_starts_at );
          $membership_ends_at = strtotime( $membership_ends_at );
          $membership_expires_at = strtotime( $membership_expires_at );
          if( current_time( 'timestamp' ) >= $membership_starts_at && current_time( 'timestamp' ) < $membership_ends_at ) {
            $status = Wicket_Memberships::STATUS_ACTIVE;
          } else if (current_time( 'timestamp' ) >= $membership_ends_at && current_time( 'timestamp' ) < $membership_expires_at ) {
            $status = Wicket_Memberships::STATUS_ACTIVE;
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

    $response['renewal_type'] = $tier_data['renewal_type'];

    //set the default values
    $response['next_tier'] = 0;
    $response['next_tier_form_page_id'] = 0;
    $response['next_tier_subscription_renewal'] = false;

    if($response['renewal_type'] == 'current_tier' || $response['renewal_type'] == 'sequential_logic' ) {
      $response['next_tier'] = $tier_data['next_tier_id'];
    } else if( $response['renewal_type'] == 'form_flow' ) {
      $response['next_tier_form_page_id'] = $tier_data['next_tier_form_page_id'];
    } else if ( $response['renewal_type'] == 'subscription' ) {
      $response['next_tier_subscription_renewal'] = true;
      $response['next_tier'] = $tier_id; //this is for self reference only on all subscription renewal tiers
    } else {
      return '';
    }

    $tier_config_id = $tier_data['config_id'];
    $config = new Membership_Config( $tier_config_id );
    $response['early_renew_days'] = $config->get_renewal_window_days();
    $response['grace_period_days'] = $config->get_late_fee_window_days();
    $period_data = $config->get_period_data();
    $response['period_type'] = $period_data['period_type'];
    $response['period_count'] = $period_data['period_count'];
    return $response;
  }

  /**
   * When a membership_renewal_type = subscription] we will need this subscription to start the next renewal from
   * 
   * @param mixed $membership
   * @param mixed $membership_id
   * @return int
   */
  private function createSubscriptionForRenewal( $membership, $membership_id ) {
    $Membership_Tier = new Membership_Tier( $membership['membership_tier_post_id'] );
    if( $Membership_Tier->is_renewal_subscription() ) {
      $Membership_Config = $Membership_Tier->get_config();
      $products = $Membership_Tier->get_products_data();
      $product_id = !empty( $products[0]['variation_id'] ) ? $products[0]['variation_id'] : $products[0]['product_id'];
      $product_id = apply_filters( 'wicket_subscription_renewal_product_tier_filter', $product_id );
      $wc_product = wc_get_product( $product_id );
      $membership_period_data = $Membership_Config->get_period_data();
      $start_date_mysqltime = date ('Y-m-d H:i:s', strtotime( $membership['membership_starts_at']));
      $next_payment_date_mysqltime = date ('Y-m-d H:i:s', strtotime( $membership['membership_ends_at']));
      $end_date_mysqltime = date ('Y-m-d H:i:s', strtotime( $membership['membership_expires_at']));

      // We do not need an order as there will be no payment for the initial membership imported
      $subscription = wcs_create_subscription( array(
        'customer_id' => $membership['user_id'],
        'billing_period' => $membership_period_data['period_type'],
        'billing_interval' => 1,
        'start_date' => $start_date_mysqltime,
      ) );
      //make sure this happens before setting dates, woo subscriptions makes changes based on existing dates when its status changes
      $subscription->update_status('active');

      $dates['next_payment'] = $next_payment_date_mysqltime;
      $dates['end'] = $end_date_mysqltime;
      if($dates['next_payment'] == $dates['end']) {
        //add 1 second to end date so it is always after next payment date
        $dates['end'] = date ('Y-m-d H:i:s', strtotime( $dates['end']. " + 1 second"));
      }
      $subscription->update_dates($dates);

      $subscription->add_product( $wc_product );
      $subscription->calculate_totals();
      $subscription->save();

      //add the org uuid if this is an organization membership
      if(!empty($membership['organization_uuid'])) {
        $subscription_products = $subscription->get_items();
        foreach( $subscription_products as $item ) {
          wc_add_order_item_meta( $item->get_id(), '_org_uuid', $membership['organization_uuid'] );
        }  
      }

      $subscription_id = $subscription->get_id();
      ( new Utilities() )->wicket_assign_subscription_to_membership( $subscription_id, $membership_id  );
      $subscription->add_order_note('This subscription was created on import. No order was created as no payment was received. Will be used for subsequent renewals.');
      return $subscription_id;
    }
  }

  public function local_membership_exists($wicket_uuid) {
    $args = [
      'post_type' => $this->membership_cpt_slug,
      'meta_query' => [
        [
          'key' => 'membership_wicket_uuid',
          'value' => $wicket_uuid,
          'compare' => '='
        ]
      ],
      'posts_per_page' => 1,
      'fields' => 'ids'
    ];
    $query = new \WP_Query( $args );
    if( $query->have_posts() ) {
      return true;
    } else {
      return false;
    }
  }

}