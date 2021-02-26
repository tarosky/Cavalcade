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
		$timestamp = strtotime( '+1 hour' );

		wp_schedule_single_event( $timestamp, __FUNCTION__ );
		$before = time();
		sleep( 1 );
		wp_unschedule_event( $timestamp, __FUNCTION__ );
		sleep( 1 );
		$after = time();

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->base_prefix}cavalcade_jobs WHERE hook = %s",
			[ __FUNCTION__ ] ) );
		$t = mysql2date( 'G', $results[0]->deleted_at );

		$this->assertGreaterThan( $before, $t );
		$this->assertLessThan( $after, $t );
	}

	function test_unschedule_event_while_running() {
		global $wpdb;

		// Schedule an event and make sure it's returned by wp_next_scheduled().
		$timestamp = strtotime( '+1 hour' );

		wp_schedule_single_event( $timestamp, __FUNCTION__ );
		$before = time();
		sleep( 1 );
		// status transition: waiting -> running 
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->base_prefix}cavalcade_jobs SET status = 'running' WHERE hook = %s",
			[ __FUNCTION__ ] ) );
		
		wp_unschedule_event( $timestamp, __FUNCTION__ );
		sleep( 1 );
		$after = time();

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->base_prefix}cavalcade_jobs WHERE hook = %s",
			[ __FUNCTION__ ] ) );
		$t = mysql2date( 'G', $results[0]->deleted_at );

		$this->assertGreaterThan( $before, $t );
		$this->assertLessThan( $after, $t );
	}

	function test_clear_schedule() {
		global $wpdb;

		// Schedule several events with and without arguments.
		wp_schedule_single_event( strtotime( '+1 hour' ), __FUNCTION__ );
		wp_schedule_single_event( strtotime( '+2 hour' ), __FUNCTION__ );
		wp_schedule_single_event( strtotime( '+3 hour' ), __FUNCTION__, array( 'arg1' ) );
		wp_schedule_single_event( strtotime( '+4 hour' ), __FUNCTION__, array( 'arg1' ) );

		$before = time();
		sleep( 1 );

		// Clear the schedule for the no args events and make sure it's gone.
		$hook_unscheduled = wp_clear_scheduled_hook( __FUNCTION__ );

		sleep( 1 );
		$after = time();

		$query = $wpdb->prepare(
			"SELECT * FROM {$wpdb->base_prefix}cavalcade_jobs WHERE hook = %s AND deleted_at IS NOT NULL",
			[ __FUNCTION__ ] );

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
		wp_clear_scheduled_hook( __FUNCTION__, array( 'arg1' ) );
		$this->assertFalse( wp_next_scheduled( __FUNCTION__, array( 'arg1' ) ) );

		$this->assertCount( 4, $wpdb->get_results( $query ) );
	}

}
