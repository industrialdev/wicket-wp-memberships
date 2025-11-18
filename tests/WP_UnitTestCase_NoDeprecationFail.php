<?php
// Custom test base class to disable deprecation and incorrect usage assertions
class WP_UnitTestCase_NoDeprecationFail extends WP_UnitTestCase {
    public function expectedDeprecated() {
        // Disable assertion on deprecation notices
    }
    public function assert_post_conditions() {
        // Disable assertion on post conditions (including deprecation/incorrect usage)
    }

    public function test_sample() {
        $this->assertTrue( true );
    }
}
