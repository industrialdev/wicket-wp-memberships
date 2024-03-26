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
}