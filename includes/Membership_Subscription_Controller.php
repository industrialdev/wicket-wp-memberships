<?php

namespace Wicket_Memberships;

use Wicket_Memberships\Helper;

/**
 * Main controller methods
 * @package Wicket_Memberships
 */
class Membership_Subscription_Controller {

  private $error_message = '';
  private $membership_cpt_slug = '';
  private $membership_config_cpt_slug = '';
  private $membership_tier_cpt_slug = '';

  public function __construct() {
    $this->membership_cpt_slug = Helper::get_membership_cpt_slug();
    $this->membership_config_cpt_slug = Helper::get_membership_config_cpt_slug();
    $this->membership_tier_cpt_slug = Helper::get_membership_tier_cpt_slug();  
  }

  public function create_subscriptions( $order, $user  ){
  
    if( ! function_exists( 'wc_create_order' ) || ! function_exists( 'wcs_create_subscription' ) || ! class_exists( 'WC_Subscriptions_Product' ) ){
      return false;
    }

    foreach($order->get_items() as $item ) {
      if( !empty( $item['variation_id'] ) ) {
        $product_id = $item['variation_id'];
        $variation = wc_get_product( $product_id );
        $parent_product_id = $variation->get_parent_id();        
      } else {
        $product_id = $parent_product_id = $item->get_product_id();
      }
      $product = wc_get_product( $product_id );
      $payment_term = $product->get_attribute('payment_terms');
      $payment_term = str_replace( "ly", "", $payment_term );

      if ( class_exists( 'WC_Subscriptions_Product' ) && \WC_Subscriptions_Product::is_subscription( $product ) ) {
        continue;
      }

      $membership_tiers = (new Membership_Controller())->get_tiers_from_product( $parent_product_id );

      if(! empty( $membership_tiers )) {
        $config = (new Membership_Config( $membership_tiers[0]->meta['config_id'][0] ));
        $dates = (new Membership_Controller)->get_membership_dates( $config );

          @$sub = wcs_create_subscription(array(
            'order_id' => $order->get_id(),
            'status' => 'pending',
            'billing_period' => $payment_term,
            'billing_interval' => 1,
            'start_date' => date( "Y-m-d H:i:s", strtotime( $dates['start_date'] ) ),
          ));

        if( is_wp_error( $sub ) ){      
          $_SESSION['wicket_membership_error'] = $sub->get_error_message();  
          wc_add_notice( $sub->get_error_message(), 'error' );
          $order->update_status( 'failed', $sub->get_error_message() );  
          return;
        }
        
        $address = $order->get_address( 'billing' );
        $sub->set_address( $address, 'billing' );
        $sub->add_product( $product, 1 );
        
        $payment_method = $order->get_payment_tokens();
        if( !empty( $payment_method ) ) {
          $sub->set_payment_method($payment_method->get_data()['token']);
        }        

        // Update Subscription End Date to expires at time
        $dates = array(
          'end' => date( "Y-m-d H:i:s", strtotime( $dates['expires_at'] ) ),
        );
        $sub->update_dates( $dates );
        $sub->calculate_totals();
        $note = ! empty( $note ) ? $note : __( 'Membership driven subscription created.', 'wicket-memberships' );
        $order->add_order_note( $note );
        $sub->add_order_note( $note );
        if( $order->get_status() == 'completed') {
          $note = ! empty( $note ) ? $note : __( 'Membership driven subscription activated.', 'wicket-memberships' );
          $order->add_order_note( $note );
          $sub->update_status( 'active', $note, true );
        }
      }
    }
  }
}