<?php
/**
 * phpcs:ignoreFile WordPress.DB.PreparedSQL.NotPrepared
 */

namespace HM\Cavalcade\Plugin;

use WP_Error;

class Job {
	public $id;
	public $site;
	public $hook;
	public $hook_instance;
	public $args;
	public $nextrun;
	public $interval;
	public $schedule;
	public $status;

	public function __construct( $id = null ) {
		$this->id = $id;
	}

	/**
	 * Has this job been created yet?
	 *
	 * @return boolean
	 */
	public function is_created() {
		return (bool) $this->id;
	}

	/**
	 * Is this a recurring job?
	 *
	 * @return boolean
	 */
	public function is_recurring() {
		return ! empty( $this->interval );
	}

	private static function print_last_error() {
		global $wpdb;

		wp_load_translations_early();

		$error_str = sprintf(
			__( 'WordPress database error %1$s for query %2$s made by %3$s' ),
			$wpdb->last_error,
			$wpdb->last_query,
			$wpdb->get_caller() );
		error_log( $error_str );
	}

	public function save() {
		global $wpdb;

		$data = [
			'hook'    => $this->hook,
			'site'    => $this->site,
			'nextrun' => gmdate( DATE_FORMAT, $this->nextrun ),
			'args'    => serialize( $this->args ),
		];

		$data['args_digest'] = hash( 'sha256', $data['args'] );

		if ( $this->is_recurring() ) {
			$data['interval'] = $this->interval;
			$data['schedule'] = $this->schedule;
			$data['hook_instance'] = '';
		} else {
			$data['hook_instance'] = $data['nextrun'];
		}

		if ( $this->is_created() ) {
			$data['revised_at'] = date( DATE_FORMAT );
			$where = [
				'id' => $this->id,
			];
			$suppress_errors = $wpdb->suppress_errors();
			try {
				$wpdb->update( $this->get_table(), $data, $where, $this->row_format( $data ), $this->row_format( $where ) );
				switch ( mysqli_errno( $wpdb->getDbh() ) ) {
				case ER_DUP_ENTRY:
					$wpdb->delete( $this->get_table(), $where, $this->row_format( $where ) );
					if ( mysqli_errno( $wpdb->getDbh() ) !== 0 ) {
						self::print_last_error();
						return;
					}
					self::flush_query_cache();
					wp_cache_delete( "job::{$this->id}", 'cavalcade-jobs' );
					return;
				case ER_NO_SUCH_TABLE:
					self::show_no_table_error();
					self::print_last_error();
					return;
				case 0:
					self::flush_query_cache();
					wp_cache_set( "job::{$this->id}", $this, 'cavalcade-jobs' );
					return;
				default:
					self::print_last_error();
					return;
				}
			} finally {
				$wpdb->suppress_errors($suppress_errors);
			}
		} else {
			$suppress_errors = $wpdb->suppress_errors();
			try {
				$wpdb->insert( $this->get_table(), $data, $this->row_format( $data ) );
				switch ( mysqli_errno( $wpdb->getDbh() ) ) {
				case ER_DUP_ENTRY:
					return;
				case ER_NO_SUCH_TABLE:
					self::show_no_table_error();
					self::print_last_error();
					return;
				case 0:
					$this->id = $wpdb->insert_id;
					self::flush_query_cache();
					wp_cache_set( "job::{$this->id}", $this, 'cavalcade-jobs' );
					return;
				default:
					self::print_last_error();
					return;
				}
			} finally {
				$wpdb->suppress_errors($suppress_errors);
			}
		}
	}

	public function delete() {
		global $wpdb;
		$wpdb->show_errors();

		$data = [
			'deleted_at' => date( DATE_FORMAT ),
		];

		$where = [
			'id' => $this->id,
		];
		$suppress_errors = $wpdb->suppress_errors();
		try {
			$wpdb->update( $this->get_table(), $data, $where, $this->row_format( $data ), $this->row_format( $where ) );
			switch ( mysqli_errno( $wpdb->getDbh() ) ) {
			case ER_DUP_ENTRY:
				$wpdb->delete( $this->get_table(), $where, $this->row_format( $where ) );
				self::flush_query_cache();
				wp_cache_delete( "job::{$this->id}", 'cavalcade-jobs' );
				return true;
			case ER_NO_SUCH_TABLE:
				self::show_no_table_error();
				self::print_last_error();
				return;
			case 0:
				self::flush_query_cache();
				wp_cache_delete( "job::{$this->id}", 'cavalcade-jobs' );
				return true;
			default:
				self::print_last_error();
				return false;
			}
		} finally {
			$wpdb->suppress_errors($suppress_errors);
		}
	}

	public static function get_table() {
		global $wpdb;
		return $wpdb->base_prefix . 'cavalcade_jobs';
	}

	/**
	 * Convert row data to Job instance
	 *
	 * @param stdClass $row Raw job data from the database.
	 * @return Job
	 */
	protected static function to_instance( $row ) {
		$job = new Job( $row->id );

		// Populate the object with row values
		$job->site          = $row->site;
		$job->hook          = $row->hook;
		$job->hook_instance = $row->hook_instance;
		$job->args          = unserialize( $row->args );
		$job->nextrun       = mysql2date( 'G', $row->nextrun );
		$job->interval      = $row->interval;
		$job->status        = $row->status;

		if ( ! $row->interval ) {
			// One off event.
			$job->schedule = false;
		} elseif ( ! empty( $row->schedule ) ) {
			$job->schedule = $row->schedule;
		} else {
			$job->schedule = get_schedule_by_interval( $row->interval );
		}

		wp_cache_set( "job::{$job->id}", $job, 'cavalcade-jobs' );
		return $job;
	}

	/**
	 * Convert list of data to Job instances
	 *
	 * @param stdClass[] $rows Raw mapping rows
	 * @return Job[]
	 */
	protected static function to_instances( $rows ) {
		return array_map( [ get_called_class(), 'to_instance' ], $rows );
	}

	/**
	 * Get job by job ID
	 *
	 * @param int|Job $job Job ID or instance
	 * @return Job|WP_Error|null Job on success, WP_Error if error occurred, or null if no job found
	 */
	public static function get( $job ) {
		global $wpdb;

		if ( $job instanceof Job ) {
			return $job;
		}

		$job = absint( $job );

		$cached_job = wp_cache_get( "job::{$job}", 'cavalcade-jobs' );
		if ( $cached_job ) {
			return $cached_job;
		}

		$suppress = $wpdb->suppress_errors();
		try {
			$job = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . static::get_table() . ' WHERE id = %d', $job ) );
			switch ( mysqli_errno( $wpdb->getDbh() ) ) {
			case ER_NO_SUCH_TABLE:
				self::show_no_table_error();
				self::print_last_error();
				return null;
			case 0:
				break;
			default:
				self::print_last_error();
				return null;
			}
		} finally {
			$wpdb->suppress_errors( $suppress );
		}

		if ( ! $job ) {
			return null;
		}

		return static::to_instance( $job );
	}

	/**
	 * Get jobs by site ID
	 *
	 * @param int|stdClass $site Site ID, or site object from {@see get_blog_details}
	 * @return Job[]|WP_Error Jobs on success, error otherwise.
	 */
	public static function get_by_site( $site ) {

		// Allow passing a site object in
		if ( is_object( $site ) && isset( $site->blog_id ) ) {
			$site = $site->blog_id;
		}

		if ( ! is_numeric( $site ) ) {
			return new WP_Error( 'cavalcade.job.invalid_site_id' );
		}

		$args = [
			'site' => $site,
			'args' => null,
			'statuses' => [ 'waiting', 'running' ],
			'limit' => 0,
			'__raw' => true,
		];

		$results = static::get_jobs_by_query( $args );

		if ( empty( $results ) ) {
			return [];
		}

		return static::to_instances( $results );
	}

	/**
	 * Query jobs database.
	 *
	 * Returns an array of Job instances for the current site based
	 * on the paramaters.
	 *
	 * @todo: allow searching within time window for duplicate events.
	 *
	 * @param array|\stdClass $args {
	 *     @param string          $hook      Jobs hook to return. Optional.
	 *     @param string          $hook_instance Hook instance. Optional.
	 *     @param int|string|null $timestamp Timestamp to search for. Optional.
	 *                                       String shortcuts `future`: > NOW(); `past`: <= NOW()
	 *     @param array           $args      Cron job arguments.
	 *     @param int|object      $site      Site to query. Default current site.
	 *     @param array           $statuses  Job statuses to query. Default to waiting and running.
	 *     @param int             $limit     Max number of jobs to return. Default 1.
	 *     @param string          $order     ASC or DESC. Default ASC.
	 * }
	 * @return Job[]|WP_Error Jobs on success, error otherwise.
	 */
	public static function get_jobs_by_query( $args = [] ) {
		global $wpdb;
		$args = (array) $args;
		$results = [];

		$defaults = [
			'timestamp' => null,
			'hook' => null,
			'hook_instance' => null,
			'args' => [],
			'site' => get_current_blog_id(),
			'statuses' => [ 'waiting', 'running' ],
			'limit' => 1,
			'order' => 'ASC',
			'__raw' => false,
		];

		$args = wp_parse_args( $args, $defaults );

		/**
		 * Filters the get_jobs_by_query() arguments.
		 *
		 * An example use case would be to enforce limits on the number of results
		 * returned if you run into performance problems.
		 *
		 * @param array $args {
		 *     @param string           $hook      Jobs hook to return. Optional.
		 *     @param string           $hook_instance Hook instance. Optional.
		 *     @param int|string|array $timestamp Timestamp to search for. Optional.
		 *                                        String shortcuts `future`: > NOW(); `past`: <= NOW()
		 *                                        Array of 2 time stamps will search between those dates.
		 *     @param array            $args      Cron job arguments.
		 *     @param int|object       $site      Site to query. Default current site.
		 *     @param array            $statuses  Job statuses to query. Default to waiting and running.
		 *                                        Possible values are 'waiting', 'running', and 'done'.
		 *     @param int              $limit     Max number of jobs to return. Default 1.
		 *     @param string           $order     ASC or DESC. Default ASC.
		 *     @param bool             $__raw     If true return the raw array of data rather than Job objects.
		 * }
		 */
		$args = apply_filters( 'cavalcade.get_jobs_by_query.args', $args );

		// Allow passing a site object in
		if ( is_object( $args['site'] ) && isset( $args['site']->blog_id ) ) {
			$args['site'] = $args['site']->blog_id;
		}

		if ( ! is_numeric( $args['site'] ) ) {
			return new WP_Error( 'cavalcade.job.invalid_site_id' );
		}

		if ( ! empty( $args['hook'] ) && ! is_string( $args['hook'] ) ) {
			return new WP_Error( 'cavalcade.job.invalid_hook_name' );
		}

		if ( ! is_array( $args['args'] ) && ! is_null( $args['args'] ) ) {
			return new WP_Error( 'cavalcade.job.invalid_event_arguments' );
		}

		if ( ! is_numeric( $args['limit'] ) ) {
			return new WP_Error( 'cavalcade.job.invalid_limit' );
		}

		$args['limit'] = absint( $args['limit'] );

		// Find all scheduled events for this site
		$table = static::get_table();

		$sql = "SELECT * FROM `{$table}` WHERE site = %d";
		$sql_params[] = $args['site'];

		if ( is_string( $args['hook'] ) ) {
			$sql .= ' AND hook = %s';
			$sql_params[] = $args['hook'];
		}

		if ( ! is_null( $args['hook_instance'] ) ) {
			$sql .= ' AND hook_instance = %s';
			$sql_params[] = $args['hook_instance'];
		}

		if ( ! is_null( $args['args'] ) ) {
			$sql .= ' AND args = %s';
			$sql_params[] = serialize( $args['args'] );
		}

		// Timestamp 'future' shortcut.
		if ( $args['timestamp'] === 'future' ) {
			$sql .= " AND nextrun > %s";
			$sql_params[] = date( DATE_FORMAT );
		}

		// Timestamp past shortcut.
		if ( $args['timestamp'] === 'past' ) {
			$sql .= " AND nextrun <= %s";
			$sql_params[] = date( DATE_FORMAT );
		}

		// Timestamp array range.
		if ( is_array( $args['timestamp'] ) && count( $args['timestamp'] ) === 2 ) {
			$sql .= ' AND nextrun BETWEEN %s AND %s';
			$sql_params[] = date( DATE_FORMAT, (int) $args['timestamp'][0] );
			$sql_params[] = date( DATE_FORMAT, (int) $args['timestamp'][1] );
		}

		// Default integer timestamp.
		if ( is_int( $args['timestamp'] ) ) {
			$sql .= ' AND nextrun = %s';
			$sql_params[] = date( DATE_FORMAT, (int) $args['timestamp'] );
		}

		$sql .= ' AND status IN(' . implode( ',', array_fill( 0, count( $args['statuses'] ), '%s' ) ) . ')';
		$sql_params = array_merge( $sql_params, $args['statuses'] );

		$empty_deleted_at = EMPTY_DELETED_AT;
		$sql .= " AND deleted_at = '$empty_deleted_at'";

		$sql .= ' ORDER BY nextrun';
		if ( $args['order'] === 'DESC' ) {
			$sql .= ' DESC';
		} else {
			$sql .= ' ASC';
		}

		if ( $args['limit'] > 0 ) {
			$sql .= ' LIMIT %d';
			$sql_params[] = $args['limit'];
		}

		// Cache results.
		$last_changed = wp_cache_get_last_changed( 'cavalcade-jobs' );
		$query_hash = sha1( serialize( [ $sql, $sql_params ] ) ) . "::{$last_changed}";
		$results = wp_cache_get( "jobs::{$query_hash}", 'cavalcade-jobs' );

		if ( false === $results ) {
			$query = $wpdb->prepare( $sql, $sql_params );
			$suppress = $wpdb->suppress_errors();
			try {
				$results = $wpdb->get_results( $query );
				switch ( $errno = mysqli_errno( $wpdb->getDbh() ) ) {
				case ER_NO_SUCH_TABLE:
					self::show_no_table_error();
					self::print_last_error();
					return new WP_Error( 'cavalcade.no_table' );
				case 0:
					break;
				default:
					self::print_last_error();
					return new WP_Error( 'cavalcade.database' );
				}
			} finally {
				$wpdb->suppress_errors( $suppress );
			}

			wp_cache_set( "jobs::{$query_hash}", $results, 'cavalcade-jobs' );
		}

		if ( $args['__raw'] === true ) {
			return $results;
		}

		return static::to_instances( $results );
	}

	/**
	 * Invalidates existing query cache keys by updating last changed time.
	 */
	public static function flush_query_cache() {
		wp_cache_set( 'last_changed', microtime(), 'cavalcade-jobs' );
	}

	/**
	 * Get the (printf-style) format for a given column.
	 *
	 * @param string $column Column to retrieve format for.
	 * @return string Format specifier. Defaults to '%s'
	 */
	protected static function column_format( $column ) {
		$columns = [
			'id'   => '%d',
			'site' => '%d',
			'hook' => '%s',
			'hook_instance' => '%s',
			'args' => '%s',
			'args_digest' => '%s',
			'nextrun' => '%s',
			'interval' => '%d',
			'schedule' => '%s',
			'status' => '%s',
			'registered_at' => '%s',
			'revised_at' => '%s',
			'started_at' => '%s',
			'finished_at' => '%s',
			'deleted_at' => '%s',
		];

		if ( isset( $columns[ $column ] ) ) {
			return $columns[ $column ];
		}

		return '%s';
	}

	/**
	 * Get the (printf-style) formats for an entire row.
	 *
	 * @param array $row Map of field to value.
	 * @return array List of formats for fields in the row. Order matches the input order.
	 */
	protected static function row_format( $row ) {
		$format = [];
		foreach ( $row as $field => $value ) {
			$format[] = static::column_format( $field );
		}
		return $format;
	}

	private static function show_no_table_error() {
		error_log( '[Error] No Cavalcade database table exists. (Re)start cavalcade-runner service to create.' );
	}
}
