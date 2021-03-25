<?php

require_once __DIR__ . '/wp-core-cron.php';

/**
 * Test the cron scheduling functions
 *
 * @group cron
 */
class Tests_WpCron extends Tests_Cron {
	public function test_disallowed_event_returns_error_when_wp_error_is_set_to_true() {
		$this->markTestIncomplete(
			'Filter pre_reschedule_event in WordPress core does not accept ' .
			'$wp_error as of now.' );
	}

	public function test_invalid_recurrence_for_event_returns_error() {
		$this->markTestIncomplete(
			'Filter pre_reschedule_event in WordPress core does not accept ' .
			'$wp_error as of now.' );
	}

	public function test_clear_scheduled_hook_returns_default_pre_filter_error_when_wp_error_is_set_to_true() {
		$this->markTestSkipped( 'Incompatible with Cavalcade' );
	}

	public function test_clear_scheduled_hook_returns_custom_pre_filter_error_when_wp_error_is_set_to_true() {
		$this->markTestSkipped( 'Incompatible with Cavalcade' );
	}

	public function test_cron_array_error_is_returned_when_scheduling_single_event() {
		$this->markTestSkipped( 'Incompatible with Cavalcade' );
	}

	public function test_cron_array_error_is_returned_when_scheduling_event() {
		$this->markTestSkipped( 'Incompatible with Cavalcade' );
	}

	public function test_cron_array_error_is_returned_when_unscheduling_hook() {
		$this->markTestSkipped( 'Incompatible with Cavalcade' );
	}

	public function test_cron_array_error_is_returned_when_unscheduling_event() {
		$this->markTestSkipped( 'Incompatible with Cavalcade' );
	}

	public function test_get_scheduled_event_recurring() {
		$this->markTestSkipped( 'Incompatible with Cavalcade' );
	}
}
