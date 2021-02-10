<?php
/**
 * Bootstrap the plugin unit testing environment.
 *
 * phpcs:disable PSR1.Files.SideEffects
 *
 * @package WordPress
*/


if ( '1' === getenv( 'WP_MULTISITE' ) ) {
	define( 'MULTISITE', true );
	define( 'WP_TESTS_MULTISITE', true );
}

require '/wp-tests/includes/functions.php';

tests_add_filter( 'muplugins_loaded', function () {
	require_once dirname( __DIR__ ) . '/plugin.php';
	// Call create tables before each run.
	HM\Cavalcade\Plugin\drop_tables();
	HM\Cavalcade\Plugin\create_tables();
});

require '/wp-tests/includes/bootstrap.php';
