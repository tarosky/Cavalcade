<?php
/**
 * Bootstrap the plugin unit testing environment.
 *
 * phpcs:disable PSR1.Files.SideEffects
 *
 * @package WordPress
*/
use const HM\Cavalcade\Plugin\EMPTY_DELETED_AT;

if ( '1' === getenv( 'WP_MULTISITE' ) ) {
	define( 'MULTISITE', true );
	define( 'WP_TESTS_MULTISITE', true );
}

require '/wp-tests/includes/functions.php';

function drop_cavalcade_table() {
	global $wpdb;

	$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->base_prefix}cavalcade_jobs`" );
}

function create_cavalcade_table() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	$empty_deleted_at = EMPTY_DELETED_AT;
	$query = "CREATE TABLE IF NOT EXISTS `{$wpdb->base_prefix}cavalcade_jobs` (
		`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,

		`site` bigint(20) unsigned NOT NULL,
		`hook` varchar(255) NOT NULL,
		`hook_instance` varchar(255) NOT NULL DEFAULT '',
		`args` longtext NOT NULL,
		`args_digest` char(64) NOT NULL,

		`nextrun` datetime NOT NULL,
		`interval` int unsigned DEFAULT NULL,
		`status` enum('waiting','running','done') NOT NULL DEFAULT 'waiting',
		`schedule` varchar(255) DEFAULT NULL,
		`registered_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		`revised_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		`started_at` datetime DEFAULT NULL,
		`finished_at` datetime DEFAULT NULL,
		`deleted_at` datetime NOT NULL DEFAULT '$empty_deleted_at',

		PRIMARY KEY (`id`),
		UNIQUE KEY `uniqueness` (`site`, `hook`, `hook_instance`, `args_digest`, `deleted_at`),
		KEY `status` (`status`, `deleted_at`),
		KEY `status-finished_at` (`status`, `finished_at`),
		KEY `site` (`site`, `deleted_at`),
		KEY `hook` (`hook`, `deleted_at`)
	) ENGINE=InnoDB {$charset_collate};\n";

	$wpdb->query( $query );
}

tests_add_filter( 'muplugins_loaded', function () {
	require_once dirname( __DIR__ ) . '/plugin.php';
	// Call create tables before each run.
	drop_cavalcade_table();
	create_cavalcade_table();
});

require '/wp-tests/includes/bootstrap.php';
