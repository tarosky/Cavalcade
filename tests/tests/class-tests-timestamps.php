<?php
namespace HM\Cavalcade\Tests;

use WP_UnitTestCase;
use const HM\Cavalcade\Plugin\EMPTY_DELETED_AT;


/**
 * Test timestamps of lifecycle.
 */
class Tests_Timestamps extends WP_UnitTestCase {

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

	function assertBetweenTime( $before, $after, $time ) {
		$this->assertGreaterThan( $before, $time );
		$this->assertLessThan( $after, $time );
	}

	function getSingleResultByHook( $hook ) {
		global $wpdb;

		$sql          = "SELECT * FROM {$wpdb->base_prefix}cavalcade_jobs WHERE hook = %s";
		$sql_params[] = $hook;

		$query = $wpdb->prepare( $sql, $sql_params );
		return $wpdb->get_results( $query )[0];
	}

	function asEpoch( $sqlDateTime ) {
		return mysql2date( 'G', $sqlDateTime );
	}

	function test_schedule_event_single() {
		$hook = __FUNCTION__;

		$before = time();
		sleep( 1 );
		wp_schedule_single_event( strtotime( '+1 hour' ), $hook );
		sleep( 1 );
		$after = time();

		$result = $this->getSingleResultByHook( $hook );

		$this->assertBetweenTime( $before, $after, $this->asEpoch( $result->registered_at ) );
		$this->assertBetweenTime( $before, $after, $this->asEpoch( $result->revised_at ) );
		$this->assertNull( $result->started_at );
		$this->assertNull( $result->finished_at );
		$this->assertEquals( EMPTY_DELETED_AT, $result->deleted_at );
	}

	function test_schedule_event() {
		$hook = __FUNCTION__;

		$before = time();
		sleep( 1 );
		wp_schedule_event( strtotime( '+1 hour' ), 'hourly', $hook );
		sleep( 1 );
		$after = time();

		$result = $this->getSingleResultByHook( $hook );

		$this->assertBetweenTime( $before, $after, $this->asEpoch( $result->registered_at ) );
		$this->assertBetweenTime( $before, $after, $this->asEpoch( $result->revised_at ) );
		$this->assertNull( $result->started_at );
		$this->assertNull( $result->finished_at );
		$this->assertEquals( EMPTY_DELETED_AT, $result->deleted_at );
	}

	function test_unschedule_event() {
		// Schedule an event and make sure it's returned by wp_next_scheduled().
		$hook      = __FUNCTION__;
		$timestamp = strtotime( '+1 hour' );

		$before_reg = time();
		sleep( 1 );
		wp_schedule_single_event( $timestamp, $hook );
		sleep( 1 );
		$after_reg = time();

		$before_del = time();
		sleep( 1 );
		wp_unschedule_event( $timestamp, $hook );
		sleep( 1 );
		$after_del = time();

		$result = $this->getSingleResultByHook( $hook );

		$this->assertBetweenTime( $before_reg, $after_reg, $this->asEpoch( $result->registered_at ) );
		$this->assertBetweenTime( $before_reg, $after_reg, $this->asEpoch( $result->revised_at ) );
		$this->assertNull( $result->started_at );
		$this->assertNull( $result->finished_at );
		$this->assertBetweenTime( $before_del, $after_del, $this->asEpoch( $result->deleted_at ) );
	}

	function test_pre_reschedule_event_filter() {
		$hook      = __FUNCTION__;
		$timestamp = strtotime( '+30 minutes' );

		$before_reg = time();
		sleep( 1 );
		wp_schedule_event( $timestamp, 'hourly', $hook );
		sleep( 1 );
		$after_reg = time();

		$before_rev = time();
		sleep( 1 );
		wp_reschedule_event( $timestamp, 'daily', $hook );
		sleep( 1 );
		$after_rev = time();

		$result = $this->getSingleResultByHook( $hook );

		$this->assertBetweenTime( $before_reg, $after_reg, $this->asEpoch( $result->registered_at ) );
		$this->assertBetweenTime( $before_rev, $after_rev, $this->asEpoch( $result->revised_at ) );
		$this->assertNull( $result->started_at );
		$this->assertNull( $result->finished_at );
		$this->assertEquals( EMPTY_DELETED_AT, $result->deleted_at );
	}
}
