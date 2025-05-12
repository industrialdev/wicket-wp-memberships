<?php
use Wicket_Memberships\Helper;

class Helper_Test extends WP_UnitTestCase {
  /**
   * Test get_example method.
   */
  public function test_get_membership_config_cpt_slug() {

    $value = Helper::get_membership_config_cpt_slug();

    $this->assertEquals('wicket_mship_config', $value);
  }
}
