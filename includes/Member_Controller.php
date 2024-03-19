<?php

namespace Wicket_Memberships;

/**
 * Main controller methods
 */
class Member_Controller {

  public function __construct() {
    add_action( 'wicket_member_create_record', array( $this, 'create_membership_record'), 10, 1 );
  }

  public function create_membership_record( $data ) {
    $wicket = $this->create_mdp_record( $data );
    return $this->create_local_membership_record(  $wicket['id'], $data);
  }

  private function create_mdp_record( $data ) {
    return wicket_assign_individual_membership( $data['membership_uuid'], $data['person_uuid']);
  }

  private function create_local_membership_record( $wicket_uuid, $data ) {
    return wp_insert_post( array (
      'post_type' => 'wicket_member',
      'post_status' => 'publish',
      'meta_input'  => [
        'status' => 'active',
        'member_type' => 'person',
        'user_id' => $data['user_id'],
        'start_date' => $data['start_date'],
        'end_date' => $data['end_date'],
        'grace_period' => $data['grace_period'],
        'wicket_uuid' => $wicket_uuid,
      ]
    ));
  }
}
