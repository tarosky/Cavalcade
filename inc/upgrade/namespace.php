<?php
/**
 * phpcs:ignoreFile WordPress.DB.PreparedSQL.NotPrepared
 */

namespace HM\Cavalcade\Plugin\Upgrade;

use const HM\Cavalcade\Plugin\DATABASE_VERSION;
use HM\Cavalcade\Plugin as Cavalcade;
use HM\Cavalcade\Plugin\Job;
use WP_CLI;

/**
 * Update the Cavalcade database version if required.
 *
 * Checks the Cavalcade database version and runs the
 * upgrade routines as required.
 *
 * @return bool False if upgrade not required, true if run.
 */
function upgrade_database() {
	$database_version = (int) get_site_option( 'cavalcade_db_version' );

	if ( $database_version === DATABASE_VERSION ) {
		// No upgrade required.
		return false;
	}

	if ( $database_version < 2 ) {
		upgrade_database_2();
	}

	if ( $database_version < 3 ) {
		upgrade_database_3();
	}

	if ( $database_version < 4 ) {
		upgrade_database_4();
	}

	if ( $database_version < 5 ) {
		upgrade_database_5();
	}

	if ( $database_version < 6 ) {
		upgrade_database_6();
	}

	if ( $database_version < 7 ) {
		upgrade_database_7();
	}

	if ( $database_version < 9 ) {
		upgrade_database_9();
	}

	if ( $database_version < 10 ) {
		upgrade_database_10();
	}

	update_site_option( 'cavalcade_db_version', DATABASE_VERSION );

	Job::flush_query_cache();

	// Upgrade successful.
	return true;
}

/**
 * Upgrade Cavalcade database tables to version 2.
 *
 * Add and populate the `schedule` column in the jobs table.
 */
function upgrade_database_2() {
	global $wpdb;

	$query = "ALTER TABLE `{$wpdb->base_prefix}cavalcade_jobs`
			  ADD `schedule` varchar(255) DEFAULT NULL";

	$res = $wpdb->query( $query );
	if ( $res === false ) {
		WP_CLI::error( "Error on 2: $wpdb->last_error" );
	}

	$schedules = Cavalcade\get_schedules_by_interval();

	foreach ( $schedules as $interval => $name ) {
		$query = "UPDATE `{$wpdb->base_prefix}cavalcade_jobs`
				  SET `schedule` = %s
				  WHERE `interval` = %d
				  AND `status` NOT IN ( 'failed', 'completed' )";

		$res = $wpdb->query(
			$wpdb->prepare( $query, $name, $interval )
		);
		if ( $res === false ) {
			WP_CLI::error( "Error on 2: $wpdb->last_error" );
		}
	}
}

/**
 * Upgrade Cavalcade database tables to version 3.
 *
 * Add indexes required for pre-flight filters.
 */
function upgrade_database_3() {
	global $wpdb;

	$query = "ALTER TABLE `{$wpdb->base_prefix}cavalcade_jobs`
			  ADD INDEX `site` (`site`),
			  ADD INDEX `hook` (`hook`)";

	$res = $wpdb->query( $query );
	if ( $res === false ) {
		WP_CLI::error( "Error on 3: $wpdb->last_error" );
	}
}

/**
 * Upgrade Cavalcade database tables to version 4.
 *
 * Remove nextrun index as it negatively affects performance.
 */
function upgrade_database_4() {
	global $wpdb;

	$query = "ALTER TABLE `{$wpdb->base_prefix}cavalcade_jobs`
			  DROP INDEX `nextrun`";

	$res = $wpdb->query( $query );
	if ( $res === false ) {
		WP_CLI::error( "Error on 4: $wpdb->last_error" );
	}
}

/**
 * Upgrade Cavalcade database tables to version 5.
 *
 * Add `deleted_at` column in the jobs table.
 */
function upgrade_database_5() {
	global $wpdb;

	$query = "ALTER TABLE `{$wpdb->base_prefix}cavalcade_jobs`
			  DROP INDEX `status`,
			  DROP INDEX `site`,
			  DROP INDEX `hook`,
			  ADD `deleted_at` datetime DEFAULT NULL,
			  ADD INDEX `status` (`status`, `deleted_at`),
			  ADD INDEX `site` (`site`, `deleted_at`),
			  ADD INDEX `hook` (`hook`, `deleted_at`)";

	$res = $wpdb->query( $query );
	if ( $res === false ) {
		WP_CLI::error( "Error on 5: $wpdb->last_error" );
	}
}

/**
 * Upgrade Cavalcade database tables to version 6.
 *
 * Add `finished_at` column in the jobs table.
 */
function upgrade_database_6() {
	global $wpdb;

	$query = "ALTER TABLE `{$wpdb->base_prefix}cavalcade_jobs`
			  ADD `finished_at` datetime DEFAULT NULL AFTER `schedule`,
			  ADD INDEX `status-finished_at` (`status`, `finished_at`)";

	$res = $wpdb->query( $query );
	if ( $res === false ) {
		WP_CLI::error( "Error on 6: $wpdb->last_error" );
	}
}

/**
 * Upgrade Cavalcade database tables to version 7.
 *
 * Add lifecycle timestamps.
 */
function upgrade_database_7() {
	global $wpdb;

	$query = "ALTER TABLE `{$wpdb->base_prefix}cavalcade_jobs`
			  DROP `start`,
			  ADD `started_at` datetime DEFAULT NULL AFTER `schedule`,
			  ADD `revised_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `schedule`,
			  ADD `registered_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `schedule`";

	$res = $wpdb->query( $query );
	if ( $res === false ) {
		WP_CLI::error( "Error on 7: $wpdb->last_error" );
	}
}

/**
 * Upgrade Cavalcade database tables to version 9.
 *
 * Drop unused table.
 */
function upgrade_database_9() {
	global $wpdb;

	$query = "DROP TABLE IF EXISTS `{$wpdb->base_prefix}cavalcade_logs`";

	$res = $wpdb->query( $query );
	if ( $res === false ) {
		WP_CLI::error( "Error on 9: $wpdb->last_error" );
	}
}

/**
 * Upgrade Cavalcade database tables to version 10.
 *
 * Delete old-formatted data.
 */
function upgrade_database_10() {
	global $wpdb;

	$query = "DELETE FROM `{$wpdb->base_prefix}cavalcade_jobs`
			  WHERE finished_at is NULL AND status IN ('completed', 'failed')";

	$res = $wpdb->query( $query );
	if ( $res === false ) {
		WP_CLI::error( "Error on 10-1: $wpdb->last_error" );
	}

	$query = "UPDATE `{$wpdb->base_prefix}cavalcade_jobs`
			  SET status = 'done'
			  WHERE status IN ('completed', 'failed')";

	$res = $wpdb->query( $query );
	if ( $res === false ) {
		WP_CLI::error( "Error on 10-2: $wpdb->last_error" );
	}

	$query = "ALTER TABLE `{$wpdb->base_prefix}cavalcade_jobs`
			  MODIFY status enum('waiting','running','done') NOT NULL DEFAULT 'waiting'";

	$res = $wpdb->query( $query );
	if ( $res === false ) {
		WP_CLI::error( "Error on 10-3: $wpdb->last_error" );
	}
}
