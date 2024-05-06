<?php

if ( ! defined( 'ABSPATH' ) ){
	exit;
}

class Wicket_Mship_Renew_Early extends AutomateWoo\Trigger{

/**
	 * Define which data items are set by this trigger, this determines which rules and actions will be available
	 *
	 * @var array
	 */
	public $supplied_data_items = array( 'order', 'subscription', 'customer' );

	/**
	 * Set up the trigger
	 */
	public function init() {
		$this->title = __( 'Membership Renewal Period Opens', 'wicket-memberships' );
		$this->group = __( 'Wicket Memberships', 'wicket-memberships' );
	}

	/**
	 * Add any fields to the trigger (optional)
	 */
	public function load_fields() {}

	/**
	 * Defines when the trigger is run
	 */
	public function register_hooks() {
		add_action( 'wicket_memberships_renewal_period_open', array( $this, 'catch_hooks' ) );
	}

	/**
	 * Catches the action and calls the maybe_run() method.
	 *
	 * @param array $membership
	 */
	public function catch_hooks( $membership ) {

		$user_id = $membership['membership_wp_user_id'];
    $order_id = $membership['membership_parent_order_id'];
    $subscription_id = $membership['membership_subscription_id'];

		// get/create objects from ids
		$customer = AutomateWoo\Customer_Factory::get_by_user_id( absint( $user_id ) );
    if( ! empty( $customer ) ) {
      $maybe_run['customer'] = $customer;
    }

    $order = wc_get_order( $order_id );
    if( ! empty( $order ) ) {
      $maybe_run['order'] = $order;
    }

    $subscription = wcs_get_subscription( $subscription_id );
    if( ! empty( $subscription ) ) {
      $maybe_run['subscription'] = $subscription;
    }

    if( ! empty( $maybe_run ) ) {
      $this->maybe_run( $maybe_run );
    } 
	}

	/**
	 * Performs any validation if required. If this method returns true the trigger will fire.
	 *
	 * @param $workflow AutomateWoo\Workflow
	 * @return bool
	 */
	public function validate_workflow( $workflow ) {
		$customer = $workflow->data_layer()->get_customer();
		$order = $workflow->data_layer()->get_order();
		$customer = $workflow->data_layer()->get_subscription();
		return true;
	}
}