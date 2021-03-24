<?php
namespace HM\Cavalcade\Tests;

use WP_UnitTestCase;
use const HM\Cavalcade\Plugin\DATE_FORMAT;


class Tests_Uniquenesss extends WP_UnitTestCase {
	protected $table;

	function setUp() {
		global $wpdb;

		parent::setUp();
		// Make sure the schedule is clear.
		_set_cron_array( [] );
		$this->table = "{$wpdb->base_prefix}cavalcade_jobs";
	}

	function tearDown() {
		// Make sure the schedule is clear.
		_set_cron_array( [] );
		parent::tearDown();
	}

	public function test_duplicate_events_not_scheduled() {
		global $wpdb;

		$hook     = __FUNCTION__;
		$args     = [ 'arg' ];
		$ts_time1  = strtotime( '+0 minutes' );
		$ts_time2  = strtotime( '+30 minutes' );
		$schedule = 'hourly';
		$interval = HOUR_IN_SECONDS;

		$expected1 = (object) [
			'hook'      => $hook,
			'timestamp' => $ts_time1,
			'schedule'  => $schedule,
			'args'      => $args,
			'interval'  => $interval,
		];

		wp_schedule_event( $ts_time1, $schedule, $hook, $args );
		wp_schedule_event( $ts_time2, $schedule, $hook, $args );

		$this->assertEquals( $expected1, wp_get_scheduled_event( $hook, $args ) );

		$res = $wpdb->get_results( "SELECT * FROM $this->table WHERE hook = '$hook'" );
		$this->assertCount(1, $res);
	}

	public function test_rescheduled_as_duplicate_event() {
		global $wpdb;

		$hook     = __FUNCTION__;
		$args    = [];
		$ts_time  = strtotime( '+0 minutes' );
		$time_str = date( DATE_FORMAT, $ts_time );

		$deleted_at = date( DATE_FORMAT );
		$wpdb->query( "INSERT INTO $this->table (`site`, `hook`, `args`, `args_digest`, `nextrun`, `interval`, `schedule`, `deleted_at`) VALUES (1, '$hook', 'a:0:{}', '35786c7117b4e38d0f169239752ce71158266ae2f6e4aa230fbbb87bd699c0e3', '$time_str', 86400, 'daily', '$deleted_at')" );
		$wpdb->query( "INSERT INTO $this->table (`site`, `hook`, `args`, `args_digest`, `nextrun`, `interval`, `schedule`) VALUES (1, '$hook', 'a:0:{}', '35786c7117b4e38d0f169239752ce71158266ae2f6e4aa230fbbb87bd699c0e3', '$time_str', 86400, 'daily')" );
		wp_unschedule_event($ts_time, $hook, $args);

		$res = $wpdb->get_results( "SELECT * FROM $this->table WHERE hook = '$hook'" );
		$this->assertCount(1, $res);
		$this->assertEquals($deleted_at, $res[0]->deleted_at);
	}
}
