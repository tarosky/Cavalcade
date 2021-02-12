<?php
namespace HM\Cavalcade\Tests;

use WP_UnitTestCase;

/**
 * Test deleting events.
 */
class Tests_Deletion extends WP_UnitTestCase {

	function setUp() {
		parent::setUp();
		// Make sure the schedule is clear.
		_set_cron_array( array() );
	}

	function tearDown() {
		// Make sure the schedule is clear.
		_set_cron_array( array() );
		parent::tearDown();
	}

	function test_unschedule_event() {
		global $wpdb;

		// Schedule an event and make sure it's returned by wp_next_scheduled().
		$hook      = __FUNCTION__;
		$timestamp = strtotime( '+1 hour' );

		wp_schedule_single_event( $timestamp, $hook );
		$before = time();
		sleep( 1 );
		wp_unschedule_event( $timestamp, $hook );
		sleep( 1 );
		$after = time();

		$sql          = "SELECT * FROM {$wpdb->base_prefix}cavalcade_jobs WHERE hook = %s";
		$sql_params[] = $hook;

		$query   = $wpdb->prepare( $sql, $sql_params );
		$results = $wpdb->get_results( $query );
		$t       = mysql2date( 'G', $results[0]->deleted_at );

		$this->assertGreaterThan( $before, $t );
		$this->assertLessThan( $after, $t );
	}

	function test_clear_schedule() {
		global $wpdb;

		$hook = __FUNCTION__;
		$args = array( 'arg1' );

		// Schedule several events with and without arguments.
		wp_schedule_single_event( strtotime( '+1 hour' ), $hook );
		wp_schedule_single_event( strtotime( '+2 hour' ), $hook );
		wp_schedule_single_event( strtotime( '+3 hour' ), $hook, $args );
		wp_schedule_single_event( strtotime( '+4 hour' ), $hook, $args );

		$before = time();
		sleep( 1 );

		// Clear the schedule for the no args events and make sure it's gone.
		$hook_unscheduled = wp_clear_scheduled_hook( $hook );

		sleep( 1 );
		$after = time();

		$sql          = "SELECT * FROM {$wpdb->base_prefix}cavalcade_jobs WHERE hook = %s AND deleted_at IS NOT NULL";
		$sql_params[] = $hook;

		$query   = $wpdb->prepare( $sql, $sql_params );
		$results = $wpdb->get_results( $query );

		$this->assertCount( 2, $results );

		$t1 = mysql2date( 'G', $results[0]->deleted_at );
		$t2 = mysql2date( 'G', $results[1]->deleted_at );

		$this->assertGreaterThan( $before, $t1 );
		$this->assertGreaterThan( $before, $t2 );
		$this->assertLessThan( $after, $t1 );
		$this->assertLessThan( $after, $t2 );

		// Clear the schedule for the args events and make sure they're gone too.
		// Note: wp_clear_scheduled_hook() expects args passed directly, rather than as an array.
		wp_clear_scheduled_hook( $hook, $args );
		$this->assertFalse( wp_next_scheduled( $hook, $args ) );

		$this->assertCount( 4, $wpdb->get_results( $query ) );
	}

}
