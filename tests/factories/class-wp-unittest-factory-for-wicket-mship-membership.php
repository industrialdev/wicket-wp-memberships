<?php
// tests/factories/class-wp-unittest-factory-for-wicket-mship-membership.php

class WP_UnitTest_Factory_For_Wicket_Mship_Membership extends WP_UnitTest_Factory_For_Post {
    public function __construct( $factory = null ) {
        parent::__construct( $factory, 'wicket_mship_membership' );
    }
}
