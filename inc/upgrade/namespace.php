<?php
/**
 * phpcs:ignoreFile WordPress.DB.PreparedSQL.NotPrepared
 */

namespace HM\Cavalcade\Plugin\Upgrade;

use const HM\Cavalcade\Plugin\DATABASE_VERSION;
use const HM\Cavalcade\Plugin\EMPTY_DELETED_AT;
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

	lock_table();
	try {
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

		if ( $database_version < 11 ) {
			upgrade_database_11();
		}

		if ( $database_version < 12 ) {
			upgrade_database_12();
		}
	} finally {
		unlock_table();
	}

	update_site_option( 'cavalcade_db_version', DATABASE_VERSION );

	Job::flush_query_cache();

	// Upgrade successful.
	return true;
}

function lock_table() {
	global $wpdb;

	$wpdb->query( "LOCK TABLES `{$wpdb->base_prefix}cavalcade_jobs` WRITE" );
	if ( $wpdb->last_error !== '' ) {
		WP_CLI::error( "Error on table lock: $wpdb->last_error" );
	}
}

function unlock_table() {
	global $wpdb;

	$wpdb->query( 'UNLOCK TABLES' );
	if ( $wpdb->last_error !== '' ) {
		WP_CLI::error( "Error on table unlock: $wpdb->last_error" );
	}
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

	$wpdb->query( $query );
	if ( $wpdb->last_error !== '' ) {
		WP_CLI::error( "Error on 2: $wpdb->last_error" );
	}

	$schedules = Cavalcade\get_schedules_by_interval();

	foreach ( $schedules as $interval => $name ) {
		$query = "UPDATE `{$wpdb->base_prefix}cavalcade_jobs`
				  SET `schedule` = %s
				  WHERE `interval` = %d
				  AND `status` NOT IN ( 'failed', 'completed' )";

		$wpdb->query(
			$wpdb->prepare( $query, $name, $interval )
		);
		if ( $wpdb->last_error !== '' ) {
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

	$wpdb->query( $query );
	if ( $wpdb->last_error !== '' ) {
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

	$wpdb->query( $query );
	if ( $wpdb->last_error !== '' ) {
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

	$wpdb->query( $query );
	if ( $wpdb->last_error !== '' ) {
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

	$wpdb->query( $query );
	if ( $wpdb->last_error !== '' ) {
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

	$wpdb->query( $query );
	if ( $wpdb->last_error !== '' ) {
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

	$wpdb->query( $query );
	if ( $wpdb->last_error !== '' ) {
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
			  WHERE `finished_at` is NULL AND status IN ('completed', 'failed')";

	$wpdb->query( $query );
	if ( $wpdb->last_error !== '' ) {
		WP_CLI::error( "Error on 10-1: $wpdb->last_error" );
	}

	$query = "UPDATE `{$wpdb->base_prefix}cavalcade_jobs`
			  SET `status` = 'done'
			  WHERE `status` IN ('completed', 'failed')";

	$wpdb->query( $query );
	if ( $wpdb->last_error !== '' ) {
		WP_CLI::error( "Error on 10-2: $wpdb->last_error" );
	}

	$query = "ALTER TABLE `{$wpdb->base_prefix}cavalcade_jobs`
			  MODIFY `status` enum('waiting','running','done') NOT NULL DEFAULT 'waiting'";

	$wpdb->query( $query );
	if ( $wpdb->last_error !== '' ) {
		WP_CLI::error( "Error on 10-3: $wpdb->last_error" );
	}
}

/**
 * Upgrade Cavalcade database tables to version 11.
 *
 * Apply unique constraint.
 */
function upgrade_database_11() {
	global $wpdb;

	$query = "DELETE FROM `{$wpdb->base_prefix}cavalcade_jobs`
			  WHERE `deleted_at` IS NOT NULL";

	$wpdb->query( $query );
	if ( $wpdb->last_error !== '' ) {
		WP_CLI::error( "Error on 11-1: $wpdb->last_error" );
	}

	$query = "ALTER TABLE `{$wpdb->base_prefix}cavalcade_jobs`
			  ADD `hook_instance` varchar(255) DEFAULT NULL AFTER `hook`,
			  ADD `args_digest` char(64) AFTER `args`";

	$wpdb->query( $query );
	if ( $wpdb->last_error !== '' ) {
		WP_CLI::error( "Error on 11-2: $wpdb->last_error" );
	}

	$query = "UPDATE `{$wpdb->base_prefix}cavalcade_jobs`
			  SET `hook_instance` = `nextrun`
			  WHERE `interval` IS NULL";

	$wpdb->query( $query );
	if ( $wpdb->last_error !== '' ) {
		WP_CLI::error( "Error on 11-3: $wpdb->last_error" );
	}

	$query = "SELECT `id`, `args` FROM `{$wpdb->base_prefix}cavalcade_jobs`";

	foreach ( $wpdb->get_results( $query ) as $row ) {
		$wpdb->update(
			$wpdb->base_prefix . 'cavalcade_jobs',
			[ 'args_digest' => hash( 'sha256', $row->args ) ],
			[ 'id' => $row->id ],
			[ '%s' ],
			[ '%d' ] );
	}
	if ( $wpdb->last_error !== '' ) {
		WP_CLI::error( "Error on 11-4: $wpdb->last_error" );
	}

	$query = "SELECT MAX(`id`) as `maxid` FROM `{$wpdb->base_prefix}cavalcade_jobs`
			  GROUP BY `site`, `hook`, `hook_instance`, `args_digest`";
	$results = $wpdb->get_results( $query );
	$ids = '(-1' . implode( '', array_map( function( $r ) { return ',' . $r->maxid; }, $results ) ) . ')';

	if ( $wpdb->last_error !== '' ) {
		WP_CLI::error( "Error on 11-5: $wpdb->last_error" );
	}

	$query = "DELETE FROM `{$wpdb->base_prefix}cavalcade_jobs`
			  WHERE `id` NOT IN $ids";

	$wpdb->query( $query );
	if ( $wpdb->last_error !== '' ) {
		WP_CLI::error( "Error on 11-5: $wpdb->last_error" );
	}

	$query = "ALTER TABLE `{$wpdb->base_prefix}cavalcade_jobs`
			  MODIFY `args_digest` char(64) NOT NULL";

	$wpdb->query( $query );
	if ( $wpdb->last_error !== '' ) {
		WP_CLI::error( "Error on 11-6: $wpdb->last_error" );
	}

	$query = "ALTER TABLE `{$wpdb->base_prefix}cavalcade_jobs`
			  ADD UNIQUE KEY `uniqueness` (`site`, `hook`, `hook_instance`, `args_digest`)";

	$wpdb->query( $query );
	if ( $wpdb->last_error !== '' ) {
		WP_CLI::error( "Error on 11-7: $wpdb->last_error" );
	}
}

/**
 * Upgrade Cavalcade database tables to version 12.
 *
 * Fix incorrect unique constraint.
 */
function upgrade_database_12() {
	global $wpdb;

	$query = "UPDATE `{$wpdb->base_prefix}cavalcade_jobs`
			  SET `hook_instance` = ''
			  WHERE `hook_instance` IS NULL";

	$wpdb->query( $query );
	if ( $wpdb->last_error !== '' ) {
		WP_CLI::error( "Error on 12-1: $wpdb->last_error" );
	}

	$query = "ALTER TABLE `{$wpdb->base_prefix}cavalcade_jobs`
			  MODIFY `hook_instance` varchar(255) NOT NULL DEFAULT ''";

	$wpdb->query( $query );
	if ( $wpdb->last_error !== '' ) {
		WP_CLI::error( "Error on 12-2: $wpdb->last_error" );
	}

	$empty_deleted_at = EMPTY_DELETED_AT;
	$query = "UPDATE `{$wpdb->base_prefix}cavalcade_jobs`
			  SET `deleted_at` = '$empty_deleted_at'
			  WHERE `deleted_at` IS NULL";

	$wpdb->query( $query );
	if ( $wpdb->last_error !== '' ) {
		WP_CLI::error( "Error on 12-1: $wpdb->last_error" );
	}

	$query = "ALTER TABLE `{$wpdb->base_prefix}cavalcade_jobs`
			  MODIFY `deleted_at` datetime NOT NULL DEFAULT '$empty_deleted_at',
			  DROP INDEX `uniqueness`,
			  ADD UNIQUE KEY `uniqueness` (`site`, `hook`, `hook_instance`, `args_digest`, `deleted_at`)";

	$wpdb->query( $query );
	if ( $wpdb->last_error !== '' ) {
		WP_CLI::error( "Error on 12-3: $wpdb->last_error" );
	}
}
