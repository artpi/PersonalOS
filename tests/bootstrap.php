<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Starter_Plugin
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

define( 'DOING_TEST', true );

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

require 'vendor/autoload.php';

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	if ( file_exists( WP_PLUGIN_DIR . '/abilities-api/abilities-api.php' ) ) {
		require WP_PLUGIN_DIR . '/abilities-api/abilities-api.php';
	} else {
		// We still want the test run to highlight missing dependencies.
		fwrite( STDERR, "Abilities API plugin not found at " . WP_PLUGIN_DIR . '/abilities-api/abilities-api.php' . "\n" );
	}
	require dirname( dirname( __FILE__ ) ) . '/personalos.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";
