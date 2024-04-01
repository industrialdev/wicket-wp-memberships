<?php
namespace Wicket_Memberships;

class Membership_Config {

  private $post_id;

  public function __construct( $post_id ) {
    $this->post_id = $post_id;
	}

  /**
   * Get the membership's renewal window days
   *
   * @return int|bool Integer, false otherwise
   */
  public function get_renewal_window_days() {
    $renewal_window_data = get_post_meta( $this->post_id, 'renewal_window_data', true );
    if ( isset( $renewal_window_data['days_count'] ) ) {
      return intval( $renewal_window_data['days_count'] );
    }

    return false;
  }
}