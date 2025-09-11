<?php
/**
 * PHPUnit bootstrap file
 *
 * @package BRAGBookGallery
 */

// Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Define testing environment
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', __DIR__ . '/../vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php' );

// WordPress test environment
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', false );
define( 'SCRIPT_DEBUG', true );
define( 'WP_TESTS_FORCE_KNOWN_BUGS', true );

// Plugin-specific constants
define( 'BRAG_BOOK_GALLERY_PLUGIN_DIR', dirname( __DIR__ ) );
define( 'BRAG_BOOK_GALLERY_PLUGIN_URL', 'http://localhost' );

// Set up WordPress test environment
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Forward compatible PHPUnit polyfills
if ( file_exists( WP_TESTS_PHPUNIT_POLYFILLS_PATH ) ) {
	require_once WP_TESTS_PHPUNIT_POLYFILLS_PATH;
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require BRAG_BOOK_GALLERY_PLUGIN_DIR . '/brag-book-gallery.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';