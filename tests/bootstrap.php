<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/antecedent/patchwork/Patchwork.php';

/**
 * PHPUnit bootstrap file.
 */
$_tests_dir = getenv('WP_TESTS_DIR');

if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv('WP_TESTS_PHPUNIT_POLYFILLS_PATH');
if (false !== $_phpunit_polyfills_path) {
    define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path);
}

if (!file_exists("{$_tests_dir}/includes/functions.php")) {
    echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit(1);
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load required plugins.
 */
function _manually_load_plugins() {
    require dirname(dirname(__FILE__)) . '/../woocommerce/woocommerce.php';
    require dirname(dirname(__FILE__)) . '/../woocommerce-subscriptions/woocommerce-subscriptions.php';
    require dirname(dirname(__FILE__)) . '/../wicket-wp-base-plugin/wicket.php';
    require dirname(dirname(__FILE__)) . '/wicket.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugins');

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";

// Include custom factories
require_once __DIR__ . '/factories/class-wp-unittest-factory-for-wicket-mship-tier.php';
require_once __DIR__ . '/factories/class-wp-unittest-factory-for-wicket-mship-config.php';
require_once __DIR__ . '/factories/class-wp-unittest-factory-for-wicket-mship-membership.php';
require_once __DIR__ . '/factories/class-wp-unittest-factory-for-product.php';


// Register custom factories after $factory is initialized by the test suite
tests_add_filter('set_up_before_class', function() {
    global $factory;
    if ($factory) {
        $factory->wicket_mship_tier = new WP_UnitTest_Factory_For_Wicket_Mship_Tier( $factory );
        $factory->wicket_mship_config = new WP_UnitTest_Factory_For_Wicket_Mship_Config( $factory );
        $factory->wicket_mship_membership = new WP_UnitTest_Factory_For_Wicket_Mship_Membership( $factory );
        $factory->wicket_mship_product = new WP_UnitTest_Factory_For_Product( $factory );
    }
});