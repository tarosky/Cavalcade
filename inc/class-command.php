<?php
/**
 * phpcs:ignoreFile WordPress.DB.PreparedSQL.NotPrepared
 */

namespace HM\Cavalcade\Plugin;

use WP_CLI;
use WP_CLI_Command;

class Command extends WP_CLI_Command {
	/**
	 * Run a job.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : ID of the job to run.
	 *
	 * @synopsis <id>
	 */
	public function run( $args, $assoc_args ) {
		$job = Job::get( $args[0] );
		if ( empty( $job ) ) {
			WP_CLI::error( 'Invalid job ID' );
		}
		// Make the current job id available for hooks run by this job
		define( 'CAVALCADE_JOB_ID', $job->id );

		// Handle SIGTERM calls as we don't want to kill a running job
		pcntl_signal( SIGTERM, SIG_IGN );

		// Set the wp-cron constant for plugin and theme interactions
		defined( 'DOING_CRON' ) or define( 'DOING_CRON', true );

		/**
		 * Fires scheduled events.
		 *
		 * @ignore
		 *
		 * @param string $hook Name of the hook that was scheduled to be fired.
		 * @param array  $args The arguments to be passed to the hook.
		 */
		do_action_ref_array( $job->hook, $job->args );
	}

	/**
	 * Show jobs.
	 *
	 * @synopsis [--format=<format>] [--id=<job-id>] [--site=<site-id>] [--hook=<hook>] [--status=<status>] [--deleted=<true|false>] [--limit=<limit>] [--page=<page>] [--order=<order>] [--orderby=<orderby>]
	 */
	public function jobs( $args, $assoc_args ) {

		global $wpdb;

		$assoc_args = wp_parse_args(
			$assoc_args,
			[
				'format'  => 'table',
				'fields'  => 'id,site,hook,hook_instance,nextrun,status,registered_at,revised_at,started_at,finished_at,deleted_at',
				'id'      => null,
				'site'    => null,
				'hook'    => null,
				'status'  => null,
				'deleted' => null,
				'limit'   => 20,
				'page'    => 1,
				'order'   => null,
				'orderby' => null,
			]
		);

		$where    = [];
		$data     = [];
		$_order   = [
			'ASC',
			'DESC',
		];
		$_orderby = [
			'id',
			'site',
			'hook',
			'hook_instance',
			'args',
			'nextrun',
			'interval',
			'status',
		];
		$order    = 'DESC';
		$orderby  = 'id';

		if ( $assoc_args['id'] ) {
			$where[] = 'id = %d';
			$data[]  = $assoc_args['id'];
		}

		if ( $assoc_args['site'] ) {
			$where[] = 'site = %d';
			$data[]  = $assoc_args['site'];
		}

		if ( $assoc_args['hook'] ) {
			$where[] = 'hook = %s';
			$data[]  = $assoc_args['hook'];
		}

		if ( $assoc_args['status'] ) {
			$where[] = 'status = %s';
			$data[]  = $assoc_args['status'];
		}

		if ( $assoc_args['deleted'] ) {
			$empty_deleted_at = EMPTY_DELETED_AT;
			if ( $assoc_args['deleted'] == 'true' ) {
				$where[] = "deleted_at != '$empty_deleted_at'";
			} elseif ( $assoc_args['deleted'] == 'false' ) {
				$where[] = "deleted_at = '$empty_deleted_at'";
			}
		}

		if ( $assoc_args['order'] && in_array( strtoupper( $assoc_args['order'] ), $_order, true ) ) {
			$order = strtoupper( $assoc_args['order'] );
		}

		if ( $assoc_args['orderby'] && in_array( $assoc_args['orderby'], $_orderby, true ) ) {
			$orderby = $assoc_args['orderby'];
		}

		$where = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$limit  = 'LIMIT %d';
		$data[] = absint( $assoc_args['limit'] );
		$offset = 'OFFSET %d';
		$data[] = absint( ( $assoc_args['page'] - 1 ) * $assoc_args['limit'] );

		$query = "SELECT * FROM {$wpdb->base_prefix}cavalcade_jobs $where ORDER BY $orderby $order $limit $offset";

		if ( $data ) {
			$query = $wpdb->prepare( $query, $data );
		}

		$jobs = $wpdb->get_results( $query );

		if ( empty( $jobs ) ) {
			\WP_CLI::error( 'No Cavalcade jobs found.' );
		} else {
			\WP_CLI\Utils\format_items( $assoc_args['format'], $jobs, explode( ',', $assoc_args['fields'] ) );
		}

	}

	/**
	 * Upgrade to the latest database schema.
	 */
	public function upgrade() {
		if ( Upgrade\upgrade_database() ) {
			WP_CLI::success( 'Database version upgraded.' );
			return;
		}

		WP_CLI::success( 'Database upgrade not required.' );
	}
}
