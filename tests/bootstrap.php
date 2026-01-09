<?php
/**
 * PHPUnit bootstrap file - Supports both WordPress and Brain Monkey modes
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

// Check if WordPress test environment is available
$wp_tests_available = file_exists("{$_tests_dir}/includes/functions.php");

if ($wp_tests_available) {
    // ==========================================
    // FULL WORDPRESS TEST MODE
    // ==========================================

    // Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file
    $_phpunit_polyfills_path = getenv('WP_TESTS_PHPUNIT_POLYFILLS_PATH');
    if (false !== $_phpunit_polyfills_path) {
        define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path);
    }

    // Give access to tests_add_filter() function
    require_once "{$_tests_dir}/includes/functions.php";

    /**
     * Manually load required plugins
     */
    function _manually_load_plugins() {
        $base = dirname(dirname(__FILE__));

        // Load WooCommerce if available
        if (file_exists($base . '/../woocommerce/woocommerce.php')) {
            require $base . '/../woocommerce/woocommerce.php';
        }

        // Load WooCommerce Subscriptions if available
        if (file_exists($base . '/../woocommerce-subscriptions/woocommerce-subscriptions.php')) {
            require $base . '/../woocommerce-subscriptions/woocommerce-subscriptions.php';
        }

        // Load Wicket base plugin if available
        if (file_exists($base . '/../wicket-wp-base-plugin/wicket.php')) {
            require $base . '/../wicket-wp-base-plugin/wicket.php';
        }

        // Load this plugin
        require $base . '/wicket.php';
    }
    tests_add_filter('muplugins_loaded', '_manually_load_plugins');

    // Start up the WP testing environment
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

    define('WP_TESTS_MODE', true);

} else {
    // ==========================================
    // BRAIN MONKEY MODE (WordPress not available)
    // ==========================================

    echo "WordPress test environment not found - running in Brain Monkey mode (unit tests only)\n";

    // Define essential WordPress constants
    if (!defined('ABSPATH')) {
        define('ABSPATH', dirname(__DIR__) . '/');
    }

    if (!defined('DAY_IN_SECONDS')) {
        define('DAY_IN_SECONDS', 86400);
    }

    if (!defined('HOUR_IN_SECONDS')) {
        define('HOUR_IN_SECONDS', 3600);
    }

    if (!defined('MINUTE_IN_SECONDS')) {
        define('MINUTE_IN_SECONDS', 60);
    }

    if (!defined('WEEK_IN_SECONDS')) {
        define('WEEK_IN_SECONDS', 604800);
    }

    if (!defined('MONTH_IN_SECONDS')) {
        define('MONTH_IN_SECONDS', 2592000);
    }

    if (!defined('YEAR_IN_SECONDS')) {
        define('YEAR_IN_SECONDS', 31536000);
    }

    // Mock WordPress classes that might be needed
    if (!class_exists('WP_Widget')) {
        class WP_Widget {
            public function __construct($id_base = '', $name = '', $widget_options = [], $control_options = []) {}
        }
    }

    // Mock WordPress factory base classes
    if (!class_exists('WP_UnitTest_Factory_For_Post')) {
        class WP_UnitTest_Factory_For_Post {
            protected $factory;
            protected $default_generation_definitions = [];
            protected $post_type = 'post';

            public function __construct($factory = null, $post_type = 'post') {
                $this->factory = $factory;
                $this->post_type = $post_type;
            }

            public function create($args = []) {
                return rand(1, 9999);
            }

            public function create_and_get($args = []) {
                return (object) ['ID' => $this->create($args)];
            }
        }
    }

    if (!class_exists('WP_UnitTest_Factory_For_Thing')) {
        class WP_UnitTest_Factory_For_Thing {
            protected $factory;

            public function __construct($factory = null) {
                $this->factory = $factory;
            }

            public function create($args = []) {
                return rand(1, 9999);
            }

            public function create_and_get($args = []) {
                return (object) ['ID' => $this->create($args)];
            }
        }
    }

    // Create a mock WP_UnitTestCase that tests can extend
    if (!class_exists('WP_UnitTestCase')) {
        class WP_UnitTestCase extends \PHPUnit\Framework\TestCase {
            protected $factory;

            /**
             * Skip test if full WordPress environment is not available
             */
            protected function requireWordPress(): void {
                if (!defined('WP_TESTS_MODE') || WP_TESTS_MODE !== true) {
                    $this->markTestSkipped('This test requires a full WordPress test environment. Run bin/install-wp-tests.sh to set it up.');
                }
            }

            protected function setUp(): void {
                parent::setUp();
                \Brain\Monkey\setUp();

                // Set up a mock factory object
                $this->factory = (object) [
                    'post' => new WP_UnitTest_Factory_For_Post(null, 'post'),
                ];

                // Mock essential WordPress functions
                \Brain\Monkey\Functions\stubs([
                    'get_post' => function($id) { return (object) ['ID' => $id, 'post_type' => 'post']; },
                    'get_post_meta' => [],
                    'update_post_meta' => true,
                    'delete_post_meta' => true,
                    'get_posts' => [],
                    'get_option' => false,
                    'update_option' => true,
                    'delete_option' => true,
                    'wp_insert_post' => function() { return rand(1, 9999); },
                    'wp_delete_post' => true,
                    'add_action' => null,
                    'add_filter' => null,
                    'do_action' => null,
                    'apply_filters' => function($tag, $value) { return $value; },
                    '__' => function($text) { return $text; },
                    'esc_html__' => function($text) { return $text; },
                    'esc_attr__' => function($text) { return $text; },
                    'esc_html' => function($text) { return $text; },
                    'esc_attr' => function($text) { return $text; },
                ]);
            }

            protected function tearDown(): void {
                \Brain\Monkey\tearDown();
                parent::tearDown();
            }
        }
    }

    // Include custom factories in Brain Monkey mode (they'll use mocked base classes)
    require_once __DIR__ . '/factories/class-wp-unittest-factory-for-product.php';
    require_once __DIR__ . '/factories/class-wp-unittest-factory-for-wicket-mship-tier.php';
    require_once __DIR__ . '/factories/class-wp-unittest-factory-for-wicket-mship-config.php';
    require_once __DIR__ . '/factories/class-wp-unittest-factory-for-wicket-mship-membership.php';

    define('WP_TESTS_MODE', false);
}
