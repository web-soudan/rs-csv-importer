<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Really_Simple_CSV_Importer
 */

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

// wp-env provides the WordPress test library matching the installed core version.
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php. Run the tests with `npm run test:php`." . PHP_EOL; // phpcs:ignore
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function () {
		// The plugin bails out unless the importers are being loaded.
		if ( ! defined( 'WP_LOAD_IMPORTERS' ) ) {
			define( 'WP_LOAD_IMPORTERS', true );
		}

		require dirname( __DIR__, 2 ) . '/rs-csv-importer.php';
	}
);

require $_tests_dir . '/includes/bootstrap.php';

require __DIR__ . '/includes/class-importer-testcase.php';
