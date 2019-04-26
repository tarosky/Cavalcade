<?php

namespace HM\Cavalcade\Plugin\Connector;

use HM\Cavalcade\Plugin as Cavalcade;
use HM\Cavalcade\Plugin\Job;

/**
 * Register hooks for WordPress.
 */
function bootstrap() {
	add_filter( 'pre_update_option_cron', __NAMESPACE__ . '\\update_cron_array', 10, 2 );
	add_filter( 'pre_option_cron',        __NAMESPACE__ . '\\get_cron_array' );

	// Filters introduced in WP 5.1.
	add_filter( 'pre_schedule_event', __NAMESPACE__ . '\\pre_schedule_event', 10, 2 );
	// @todo: pre_reschedule_event (we don't really use this at work)
	add_filter( 'pre_unschedule_event', __NAMESPACE__ . '\\pre_unschedule_event', 10, 4 );
	add_filter( 'pre_clear_scheduled_hook', __NAMESPACE__ . '\\pre_clear_scheduled_hook', 10, 3 );
}

/**
 * Schedule an event with Cavalcade.
 *
 * @param null|bool $pre   Value to return instead. Default null to continue adding the event.
 * @param stdClass  $event {
 *     An object containing an event's data.
 *
 *     @type string       $hook      Action hook to execute when the event is run.
 *     @type int          $timestamp Unix timestamp (UTC) for when to next run the event.
 *     @type string|false $schedule  How often the event should subsequently recur.
 *     @type array        $args      Array containing each separate argument to pass to the hook's callback function.
 *     @type int          $interval  The interval time in seconds for the schedule. Only present for recurring events.
 * }
 * @return null|bool True if event successfully scheduled. False for failure.
 */
function pre_schedule_event( $pre, $event ) {
	// First check if the job exists already.
	$job = Job::get_jobs_by_query(
		[
			'hook' => $event->hook,
			'timestamp' => $event->timestamp,
			'args' => $event->args,
		]
	);

	if ( empty( $job[0] ) ) {
		// The job does not exist.
		schedule_event( $event );

		return true;
	}

	// The job exists.
	$existing = $job[0];
	if (
		(
			Cavalcade\get_database_version() >= 2 &&
			$existing->schedule === $event->schedule
		) &&
		$existing->interval === null &&
		! isset( $event->interval )
	) {
		// Unchanged single event.
		return false;
	} elseif (
		(
			Cavalcade\get_database_version() >= 2 &&
			$existing->schedule === $event->schedule
		) &&
		$existing->interval === $event->interval
	) {
		// Unchanged recurring event.
		return false;
	} else {
		// Event has changed. Update it.
		if ( Cavalcade\get_database_version() >= 2 ) {
			$existing->schedule = $event->schedule;
		}
		if ( isset( $event->interval ) ) {
			$existing->interval = $event->interval;
		} else {
			$existing->interval = null;
		}
		$existing->save();
		return true;
	}
}

/**
 * Unschedule a previously scheduled event.
 *
 * The $timestamp and $hook parameters are required so that the event can be
 * identified.
 *
 * @param null|bool $pre       Value to return instead. Default null to continue unscheduling the event.
 * @param int       $timestamp Timestamp for when to run the event.
 * @param string    $hook      Action hook, the execution of which will be unscheduled.
 * @param array     $args      Arguments to pass to the hook's callback function.
 * @return null|bool True if event successfully scheduled. False for failure.
 */
function pre_unschedule_event( $pre, $timestamp, $hook, $args ) {
	// First check if the job exists already.
	$job = Job::get_jobs_by_query(
		[
			'hook' => $hook,
			'timestamp' => $timestamp,
			'args' => $args,
		]
	);

	if ( empty( $job[0] ) ) {
		// The job does not exist.
		return false;
	}

	// Delete it.
	$job[0]->delete();

	return true;
}

/**
 * Unschedules all events attached to the hook with the specified arguments.
 *
 * Warning: This function may return Boolean FALSE, but may also return a non-Boolean
 * value which evaluates to FALSE. For information about casting to booleans see the
 * {@link https://php.net/manual/en/language.types.boolean.php PHP documentation}. Use
 * the `===` operator for testing the return value of this function.
 *
 * @param null|array $pre  Value to return instead. Default null to continue unscheduling the event.
 * @param string     $hook Action hook, the execution of which will be unscheduled.
 * @param array      $args Arguments to pass to the hook's callback function.
 * @return bool|int  On success an integer indicating number of events unscheduled (0 indicates no
 *                   events were registered with the hook and arguments combination), false if
 *                   unscheduling one or more events fail.
*/
function pre_clear_scheduled_hook( $pre, $hook, $args ) {

	// First check if the job exists already.
	$jobs = Job::get_jobs_by_query(
		[
			'hook' => $hook,
			'args' => $args,
			'limit' => 100,
		]
	);

	if ( empty( $jobs ) ) {
		// No jobs to unschedule.
		return 0;
	}

	$ids = wp_list_pluck( $jobs, 'id' );

	global $wpdb;

	// Clear all scheduled events for this site
	$table = Job::get_table();

	$sql = "DELETE FROM `{$table}` WHERE site = %d";
	$sql_params[] = get_current_blog_id();

	$sql .= ' AND id IN(' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')';
	$sql_params = array_merge( $sql_params, $ids );

	$query = $wpdb->prepare( $sql, $sql_params );
	$results = $wpdb->query( $query );

	// Flush the caches.
	wp_cache_delete( 'jobs', 'cavalcade-jobs' );
	array_walk( $ids, function( $id ) {
		wp_cache_delete( "job::{$id}", 'cavalcade-jobs' );
	} );

	return $results;
}

/**
 * Schedule an event with Cavalcade
 *
 * Note on return value: Although `false` can be returned to shortcircuit the
 * filter, this causes the calling function to return false. Plugins checking
 * this return value will hence think that the function has failed. Instead, we
 * hijack the save event in {@see update_cron} to simply skip saving to the DB.
 *
 * @param stdClass $event {
 *     @param string $hook Hook to fire
 *     @param int $timestamp
 *     @param array $args
 *     @param string|bool $schedule How often the event should occur (key from {@see wp_get_schedules})
 *     @param int|null $interval Time in seconds between events (derived from `$schedule` value)
 * }
 * @return stdClass Event object passed in (as we aren't hijacking it)
 */
function schedule_event( $event ) {
	global $wpdb;

	if ( ! empty( $event->schedule ) ) {
		return schedule_recurring_event( $event );
	}

	$job = new Job();
	$job->hook = $event->hook;
	$job->site = get_current_blog_id();
	$job->start = $job->nextrun = $event->timestamp;
	$job->args = $event->args;

	$job->save();
}

function schedule_recurring_event( $event ) {
	global $wpdb;

	$schedules = wp_get_schedules();
	$schedule = $event->schedule;

	$job = new Job();
	$job->hook = $event->hook;
	$job->site = get_current_blog_id();
	$job->start = $job->nextrun = $event->timestamp;
	$job->interval = $event->interval;
	$job->args = $event->args;

	if ( Cavalcade\get_database_version() >= 2 ) {
		$job->schedule = $event->schedule;
	}

	$job->save();
}

/**
 * Hijack option update call for cron
 *
 * We force this to not save to the database by always returning the old value.
 *
 * @param array $value Cron array to save
 * @param array $old_value Existing value (actually hijacked via {@see get_cron})
 * @return array Existing value, to shortcircuit saving
 */
function update_cron_array( $value, $old_value ) {
	// Ignore the version
	$stored = $old_value;
	unset( $stored['version'] );
	unset( $value['version'] );

	// Massage so we can compare
	$massager = function ( $crons ) {
		$new = [];

		foreach ( $crons as $timestamp => $hooks ) {
			foreach ( $hooks as $hook => $groups ) {
				foreach ( $groups as $key => $item ) {
					// Workaround for https://core.trac.wordpress.org/ticket/33423
					if ( $timestamp === 'wp_batch_split_terms' ) {
						$timestamp = $hook;
						$hook = 'wp_batch_split_terms';
					}

					$real_key = $timestamp . $hook . $key;

					if ( isset( $item['interval'] ) ) {
						$real_key .= (string) $item['interval'];
					}

					$real_key = sha1( $real_key );
					$new[ $real_key ] = [
						'timestamp' => $timestamp,
						'hook' => $hook,
						'key' => $key,
						'value' => $item,
					];
				}
			}
		}

		return $new;
	};

	$original = $massager( $stored );
	$new = $massager( $value );

	// Any new or changed?
	$added = array_diff_key( $new, $original );
	foreach ( $added as $key => $item ) {
		// Skip new ones, as these are handled in schedule_event/schedule_recurring_event
		if ( isset( $original[ $key ] ) ) {
			// Skip existing events, we handle them below
			continue;
		}

		// Added new event
		$event = (object) [
			'hook'      => $item['hook'],
			'timestamp' => $item['timestamp'],
			'args'      => $item['value']['args'],
		];
		if ( ! empty( $item['value']['schedule'] ) ) {
			$event->schedule = $item['value']['schedule'];
			$event->interval = $item['value']['interval'];
		}

		schedule_event( $event );
	}

	// Any removed?
	$removed = array_diff_key( $original, $new );
	foreach ( $removed as $key => $item ) {
		$job = $item['value']['_job'];

		if ( isset( $new[ $key ] ) ) {
			// Changed events: the only way to change an event without changing
			// its key is to change the schedule or interval
			$job->interval = $new['value']['interval'];
			$job->save();

			continue;
		}

		// Remaining keys are removed values only
		$job->delete();
	}

	// Cancel the DB update
	return $old_value;
}

/**
 * Get cron array.
 *
 * This is constructed based on our database values, rather than being actually
 * stored like this.
 *
 * @param array|boolean $value Value to override with. False by default, truthy if another plugin has already overridden.
 * @return array Overridden cron array.
 */
function get_cron_array( $value ) {
	if ( ! empty( $value ) ) {
		// Something else is trying to filter the value, let it
		return $value;
	}

	// Massage into the correct format
	$crons = [];
	$results = Cavalcade\get_jobs();
	foreach ( $results as $result ) {
		$timestamp = $result->nextrun;
		$hook = $result->hook;
		$key = md5( serialize( $result->args ) );
		$value = [
			'schedule' => $result->schedule,
			'args'     => $result->args,
			'_job'     => $result,
		];

		if ( isset( $result->interval ) ) {
			$value['interval'] = $result->interval;
		}

		// Build the array up, urgh
		if ( ! isset( $crons[ $timestamp ] ) ) {
			$crons[ $timestamp ] = [];
		}
		if ( ! isset( $crons[ $timestamp ][ $hook ] ) ) {
			$crons[ $timestamp ][ $hook ] = [];
		}
		$crons[ $timestamp ][ $hook ][ $key ] = $value;
	}

	ksort( $crons, SORT_NUMERIC );

	// Set the version too
	$crons['version'] = 2;

	return $crons;
}
