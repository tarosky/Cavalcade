<?php
/**
 * phpcs:ignoreFile WordPress.DB.PreparedSQL.NotPrepared
 */

namespace HM\Cavalcade\Plugin;

use WP_CLI;

/**
 * Bootstrap the plugin and get it started!
 */
function bootstrap() {
	register_cache_groups();
	register_cli_commands();
	maybe_populate_site_option();
	Connector\bootstrap();
}

/**
 * Register the cache groups
 */
function register_cache_groups() {
	wp_cache_add_global_groups( [ 'cavalcade' ] );
	wp_cache_add_non_persistent_groups( [ 'cavalcade-jobs' ] );
}

/**
 * Register the WP-CLI command
 */
function register_cli_commands() {
	if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
		return;
	}

	require __DIR__ . '/class-command.php';
	WP_CLI::add_command( 'cavalcade', __NAMESPACE__ . '\\Command' );
}

/**
 * Populate the Cavalcade db version when upgrading to multisite.
 *
 * This ensures the database option is copied from the options table
 * accross to the sitemeta table when WordPress is upgraded from
 * a single site install to a multisite install.
 */
function maybe_populate_site_option() {
	if ( is_multisite() ) {
		return;
	}

	$set_site_meta = function ( $site_meta ) {
		$site_meta['cavalcade_db_version'] = get_option( 'cavalcade_db_version' );
		return $site_meta;
	};

	add_filter( 'populate_network_meta', $set_site_meta );
}

/**
 * Get jobs for the specified site.
 *
 * @param int|stdClass $site Site ID or object (from {@see get_blog_details}) to get jobs for. Null for current site.
 * @return Job[] List of jobs on the site.
 */
function get_jobs( $site = null ) {
	if ( empty( $site ) ) {
		$site = get_current_blog_id();
	}

	return Job::get_by_site( $site );
}

/**
 * Get the WP Cron schedule names by interval.
 *
 * This is used as a fallback when Cavalcade does not have the
 * schedule name stored in the database to make a best guest as
 * the schedules name.
 *
 * Interval collisions caused by two plugins registering the same
 * interval with different names are unified into a single name.
 *
 * @return array Cron Schedules indexed by interval.
 */
function get_schedules_by_interval() {
	$schedules = [];

	foreach ( wp_get_schedules() as $name => $schedule ) {
		$schedules[ (int) $schedule['interval'] ] = $name;
	}

	return $schedules;
}

/**
 * Helper function to get a schedule name from a specific interval.
 *
 * @param int $interval Cron schedule interval.
 * @return string Cron schedule name.
 */
function get_schedule_by_interval( $interval = null ) {
	if ( empty( $interval ) ) {
		return '__fake_schedule';
	}

	$schedules = get_schedules_by_interval();

	if ( ! empty ( $schedules[ (int) $interval ] ) ) {
		return $schedules[ (int) $interval ];
	}

	return '__fake_schedule';
}
