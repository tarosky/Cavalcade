<?php

namespace HM\Cavalcade\Tests;

use WP_UnitTestCase;
use HM\Cavalcade\Plugin;
use HM\Cavalcade\Plugin\Upgrade;

/**
 * Test deleting events.
 */
class Tests_Upgrade extends WP_UnitTestCase {
	protected $table;
	protected $v9schema;

	public function setUp() {
		global $wpdb;

		parent::setUp();
		Plugin\drop_tables();

		$charset_collate = $wpdb->get_charset_collate();
		$this->table = "{$wpdb->base_prefix}cavalcade_jobs";

		$this->v9schema = "CREATE TABLE `$this->table` (
					`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					`site` bigint(20) unsigned NOT NULL,

					`hook` varchar(255) NOT NULL,
					`args` longtext NOT NULL,

					`nextrun` datetime NOT NULL,
					`interval` int unsigned DEFAULT NULL,
					`status` varchar(255) NOT NULL DEFAULT 'waiting',
					`schedule` varchar(255) DEFAULT NULL,
					`registered_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
					`revised_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
					`started_at` datetime DEFAULT NULL,
					`finished_at` datetime DEFAULT NULL,
					`deleted_at` datetime DEFAULT NULL,

					PRIMARY KEY (`id`),
					KEY `status` (`status`, `deleted_at`),
					KEY `status-finished_at` (`status`, `finished_at`),
					KEY `site` (`site`, `deleted_at`),
					KEY `hook` (`hook`, `deleted_at`)
					) ENGINE=InnoDB $charset_collate";

		$wpdb->query( $this->v9schema );
	}

	private static function upgrade() {
		update_site_option( 'cavalcade_db_version', 9 );
		Upgrade\upgrade_database();
	}

	public function tearDown() {
		Plugin\create_tables();
		parent::tearDown();
	}

	public function test_db_version() {
		global $wpdb;

		Plugin\drop_tables();
		$wpdb->query( $this->v9schema );
		self::upgrade();
		$this->assertEquals( 12, get_site_option( 'cavalcade_db_version' ) );
	}

	public function test_indices() {
		global $wpdb;

		$get_results = function () use ( $wpdb ) {
			$res = $wpdb->get_results( "show index from $this->table" );
			usort( $res, function ($v1, $v2) {
				if ($v1->Key_name != $v2->Key_name) {
					return $v1->Key_name <=> $v2->Key_name;
				}
				return $v1->Seq_in_index <=> $v2->Seq_in_index;
			} );
			return $res;
		};

		Plugin\drop_tables();
		Plugin\create_tables();
		$expected_indices = $get_results();
		Plugin\drop_tables();

		$wpdb->query( $this->v9schema );
		self::upgrade();

		$this->assertEquals( $expected_indices, $get_results() );
	}

	public function test_schema() {
		global $wpdb;

		$get_results = function () use ( $wpdb ) {
			$res = $wpdb->get_results( "describe $this->table" );
			usort( $res, function ($v1, $v2) {
				return $v1->Field <=> $v2->Field;
			} );
			return $res;
		};

		Plugin\drop_tables();
		Plugin\create_tables();
		$expected_indices = $get_results();
		Plugin\drop_tables();

		$wpdb->query( $this->v9schema );
		self::upgrade();

		$this->assertEquals( $expected_indices, $get_results() );
	}

	private function get_all() {
		global $wpdb;

		return $wpdb->get_results( "select * from $this->table" );
	}

	public function test_clean_old_completed() {
		global $wpdb;
		$wpdb->query( "INSERT INTO $this->table VALUES (10, 1, 'do_pings', 'a:0:{}', '2020-12-13 02:37:43', NULL, 'completed', NULL, '2021-03-01 04:56:05', '2021-03-01 04:56:05', NULL, NULL, NULL)" );
		self::upgrade();
		$this->assertCount( 0, $this->get_all() );
	}

	public function test_clean_old_failed() {
		global $wpdb;
		$wpdb->query( "INSERT INTO $this->table VALUES (13, 1, 'do_pings', 'a:0:{}', '2020-12-13 03:33:48', NULL, 'failed', NULL, '2021-03-01 04:56:05', '2021-03-01 04:56:05', NULL, NULL, NULL)" );
		self::upgrade();
		$this->assertCount( 0, $this->get_all() );
	}

	public function test_completed_to_done() {
		global $wpdb;
		$wpdb->query( "INSERT INTO $this->table VALUES (11, 1, 'do_pings', 'a:0:{}', '2020-12-13 02:57:43', NULL, 'completed', NULL, '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2020-12-13 02:57:43', '2020-12-13 02:57:43', NULL)" );
		self::upgrade();
		$this->assertEquals( 'done', $this->get_all()[0]->status );
	}

	public function test_failed_to_done() {
		global $wpdb;
		$wpdb->query( "INSERT INTO $this->table VALUES (12, 1, 'do_pings', 'a:0:{}', '2020-12-13 03:07:43', NULL, 'failed', NULL, '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2020-12-13 03:07:43', '2020-12-13 03:07:43', NULL)" );
		self::upgrade();
		$this->assertEquals( 'done', $this->get_all()[0]->status );
	}

	public function test_delete_now() {
		global $wpdb;
		$wpdb->query( "INSERT INTO $this->table VALUES (4, 1, 'wp_version_check', 'a:0:{}', '2021-03-19 01:21:00', 43200, 'waiting', 'twicedaily', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-18 13:21:01', '2021-03-18 13:21:08', '2021-03-18 13:21:08')" );
		self::upgrade();
		$this->assertCount( 0, $this->get_all() );
	}

	public function test_set_hook_instance_for_non_recurring() {
		global $wpdb;
		$wpdb->query( "INSERT INTO $this->table VALUES (61, 1, 'publish_future_post', 'a:1:{i:0;i:102;}', '2021-04-22 10:51:00', NULL, 'waiting', NULL, '2021-03-22 10:51:36', '2021-03-22 10:51:36', NULL, NULL, NULL)" );
		self::upgrade();
		$this->assertEquals( '2021-04-22 10:51:00', $this->get_all()[0]->hook_instance );
	}

	public function test_hook_instance_not_set_for_non_recurring() {
		global $wpdb;
		$wpdb->query( "INSERT INTO $this->table VALUES (47, 1, 'recovery_mode_clean_expired_keys', 'a:0:{}', '2021-03-19 04:56:02', 86400, 'waiting', 'daily', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-18 04:56:17', '2021-03-18 04:56:19', NULL)" );
		self::upgrade();
		$this->assertEquals( '', $this->get_all()[0]->hook_instance );
	}

	public function test_set_args_digest() {
		global $wpdb;
		$wpdb->query( "INSERT INTO $this->table VALUES (53, 1, 'recovery_mode_clean_expired_keys', 'a:0:{}', '2021-03-19 04:56:04', 86400, 'waiting', 'daily', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-18 04:56:22', '2021-03-18 04:56:24', NULL)" );
		$wpdb->query( "INSERT INTO $this->table VALUES (62, 1, 'publish_future_post', 'a:1:{i:0;i:102;}', '2021-05-22 10:51:00', NULL, 'waiting', NULL, '2021-03-22 10:51:36', '2021-03-22 10:51:36', NULL, NULL, NULL)" );
		self::upgrade();
		$result = $this->get_all();
		$this->assertEquals( hash( 'sha256', 'a:0:{}' ), $result[0]->args_digest );
		$this->assertEquals( hash( 'sha256', 'a:1:{i:0;i:102;}' ), $result[1]->args_digest );
	}

	public function test_unique() {
		global $wpdb;
		$wpdb->query( "INSERT INTO $this->table VALUES (1, 1, 'recovery_mode_clean_expired_keys', 'a:0:{}', '2021-03-19 01:21:00', 86400, 'waiting', 'daily', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-18 01:21:01', '2021-03-18 01:21:04', NULL)" );
		$wpdb->query( "INSERT INTO $this->table VALUES (8, 1, 'delete_expired_transients', 'a:0:{}', '2021-03-19 02:55:26', 86400, 'waiting', 'daily', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-18 02:55:27', '2021-03-18 02:55:29', NULL)" );
		$wpdb->query( "INSERT INTO $this->table VALUES (10, 1, 'do_pings', 'a:0:{}', '2020-12-12 02:57:43', NULL, 'completed', NULL, '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2020-12-13 02:57:43', '2020-12-13 02:57:43', NULL)" );
		$wpdb->query( "INSERT INTO $this->table VALUES (11, 1, 'do_pings', 'a:0:{}', '2020-12-13 02:57:43', NULL, 'completed', NULL, '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2020-12-13 02:57:43', '2020-12-13 02:57:43', NULL)" );
		$wpdb->query( "INSERT INTO $this->table VALUES (12, 1, 'do_pings', 'a:0:{}', '2020-12-13 02:57:43', NULL, 'completed', NULL, '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2020-12-13 02:57:43', '2020-12-13 02:57:43', NULL)" );
		$wpdb->query( "INSERT INTO $this->table VALUES (47, 1, 'recovery_mode_clean_expired_keys', 'a:0:{}', '2021-03-19 04:56:02', 86400, 'waiting', 'daily', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-18 04:56:17', '2021-03-18 04:56:19', NULL)" );
		$wpdb->query( "INSERT INTO $this->table VALUES (53, 1, 'recovery_mode_clean_expired_keys', 'a:0:{}', '2021-03-19 04:56:04', 86400, 'waiting', 'daily', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-18 04:56:22', '2021-03-18 04:56:24', NULL)" );
		$wpdb->query( "INSERT INTO $this->table VALUES (54, 1, 'recovery_mode_clean_expired_keys', 'a:1:{i:0;i:102;}', '2021-03-19 04:56:04', 86400, 'waiting', 'daily', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-18 04:56:22', '2021-03-18 04:56:24', NULL)" );
		self::upgrade();
		$result = $this->get_all();
		$this->assertCount( 5, $result );
		$this->assertEquals( 8, $result[0]->id );
		$this->assertEquals( 10, $result[1]->id );
		$this->assertEquals( 12, $result[2]->id );
		$this->assertEquals( 53, $result[3]->id );
		$this->assertEquals( 54, $result[4]->id );
	}

	public function test_all() {
		global $wpdb;

		$wpdb->query( "INSERT INTO $this->table VALUES (1, 1, 'recovery_mode_clean_expired_keys', 'a:0:{}', '2021-03-19 01:21:00', 86400, 'waiting', 'daily', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-18 01:21:01', '2021-03-18 01:21:04', NULL)" );
		$wpdb->query( "INSERT INTO $this->table VALUES (2, 1, 'wp_site_health_scheduled_check', 'a:0:{}', '2021-03-22 01:21:00', 604800, 'waiting', 'weekly', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-15 01:21:01', '2021-03-15 01:21:14', NULL)" );
		$wpdb->query( "INSERT INTO $this->table VALUES (4, 1, 'wp_version_check', 'a:0:{}', '2021-03-19 01:21:00', 43200, 'waiting', 'twicedaily', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-18 13:21:01', '2021-03-18 13:21:08', '2021-03-18 13:21:08')" );
		$wpdb->query( "INSERT INTO $this->table VALUES (7, 1, 'wp_scheduled_delete', 'a:0:{}', '2021-03-19 02:55:26', 86400, 'waiting', 'daily', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-18 02:55:27', '2021-03-18 02:55:29', NULL)" );
		$wpdb->query( "INSERT INTO $this->table VALUES (8, 1, 'delete_expired_transients', 'a:0:{}', '2021-03-19 02:55:26', 86400, 'waiting', 'daily', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-18 02:55:27', '2021-03-18 02:55:29', NULL)" );
		$wpdb->query( "INSERT INTO $this->table VALUES (10, 1, 'do_pings', 'a:0:{}', '2020-12-13 02:37:43', NULL, 'completed', NULL, '2021-03-01 04:56:05', '2021-03-01 04:56:05', NULL, NULL, NULL)" );
		$wpdb->query( "INSERT INTO $this->table VALUES (11, 1, 'do_pings', 'a:0:{}', '2020-12-13 02:57:43', NULL, 'completed', NULL, '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2020-12-13 02:57:43', '2020-12-13 02:57:43', NULL)" );
		$wpdb->query( "INSERT INTO $this->table VALUES (12, 1, 'do_pings', 'a:0:{}', '2020-12-13 03:07:43', NULL, 'failed', NULL, '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2020-12-13 03:07:43', '2020-12-13 03:07:43', NULL)" );
		$wpdb->query( "INSERT INTO $this->table VALUES (13, 1, 'do_pings', 'a:0:{}', '2020-12-13 03:23:48', NULL, 'completed', NULL, '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2020-12-13 03:23:48', '2020-12-13 03:23:48', NULL)" );
		$wpdb->query( "INSERT INTO $this->table VALUES (14, 1, 'do_pings', 'a:0:{}', '2020-12-13 03:33:48', NULL, 'failed', NULL, '2021-03-01 04:56:05', '2021-03-01 04:56:05', NULL, NULL, NULL)" );
		$wpdb->query( "INSERT INTO $this->table VALUES (47, 1, 'recovery_mode_clean_expired_keys', 'a:0:{}', '2021-03-19 04:56:02', 86400, 'waiting', 'daily', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-18 04:56:17', '2021-03-18 04:56:19', NULL)" );
		$wpdb->query( "INSERT INTO $this->table VALUES (53, 1, 'recovery_mode_clean_expired_keys', 'a:0:{}', '2021-03-19 04:56:04', 86400, 'waiting', 'daily', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-18 04:56:22', '2021-03-18 04:56:24', NULL)" );
		$wpdb->query( "INSERT INTO $this->table VALUES (61, 1, 'publish_future_post', 'a:1:{i:0;i:102;}', '2021-04-22 10:51:00', NULL, 'waiting', NULL, '2021-03-22 10:51:36', '2021-03-22 10:51:36', NULL, NULL, NULL)" );
		$wpdb->query( "INSERT INTO $this->table VALUES (62, 1, 'publish_future_post', 'a:1:{i:0;i:102;}', '2021-05-22 10:51:00', NULL, 'waiting', NULL, '2021-03-22 10:51:36', '2021-03-22 10:51:36', NULL, NULL, NULL)" );
		$wpdb->query( "INSERT INTO $this->table VALUES (63, 1, 'publish_future_post', 'a:1:{i:0;i:333;}', '2021-05-22 10:51:00', NULL, 'waiting', NULL, '2021-03-22 10:51:36', '2021-03-22 10:51:36', NULL, NULL, NULL)" );
		$wpdb->query( "INSERT INTO $this->table VALUES (64, 1, 'publish_future_post', 'a:1:{i:0;i:333;}', '2021-05-22 10:51:00', NULL, 'waiting', NULL, '2021-03-22 10:51:36', '2021-03-22 10:51:36', NULL, NULL, NULL)" );

		$expected_data = [
			[
				'id' => '2',
				'site' => '1',
				'hook' => 'wp_site_health_scheduled_check',
				'hook_instance' => NULL,
				'args' => 'a:0:{}',
				'args_digest' => '35786c7117b4e38d0f169239752ce71158266ae2f6e4aa230fbbb87bd699c0e3',
				'nextrun' => '2021-03-22 01:21:00',
				'interval' => '604800',
				'status' => 'waiting',
				'schedule' => 'weekly',
				'registered_at' => '2021-03-01 04:56:05',
				'revised_at' => '2021-03-01 04:56:05',
				'started_at' => '2021-03-15 01:21:01',
				'finished_at' => '2021-03-15 01:21:14',
				'deleted_at' => '9999-12-31 23:59:59',
			],
			[
				'id' => '7',
				'site' => '1',
				'hook' => 'wp_scheduled_delete',
				'hook_instance' => NULL,
				'args' => 'a:0:{}',
				'args_digest' => '35786c7117b4e38d0f169239752ce71158266ae2f6e4aa230fbbb87bd699c0e3',
				'nextrun' => '2021-03-19 02:55:26',
				'interval' => '86400',
				'status' => 'waiting',
				'schedule' => 'daily',
				'registered_at' => '2021-03-01 04:56:05',
				'revised_at' => '2021-03-01 04:56:05',
				'started_at' => '2021-03-18 02:55:27',
				'finished_at' => '2021-03-18 02:55:29',
				'deleted_at' => '9999-12-31 23:59:59',
			],
			[
				'id' => '8',
				'site' => '1',
				'hook' => 'delete_expired_transients',
				'hook_instance' => NULL,
				'args' => 'a:0:{}',
				'args_digest' => '35786c7117b4e38d0f169239752ce71158266ae2f6e4aa230fbbb87bd699c0e3',
				'nextrun' => '2021-03-19 02:55:26',
				'interval' => '86400',
				'status' => 'waiting',
				'schedule' => 'daily',
				'registered_at' => '2021-03-01 04:56:05',
				'revised_at' => '2021-03-01 04:56:05',
				'started_at' => '2021-03-18 02:55:27',
				'finished_at' => '2021-03-18 02:55:29',
				'deleted_at' => '9999-12-31 23:59:59',
			],
			[
				'id' => '11',
				'site' => '1',
				'hook' => 'do_pings',
				'hook_instance' => '2020-12-13 02:57:43',
				'args' => 'a:0:{}',
				'args_digest' => '35786c7117b4e38d0f169239752ce71158266ae2f6e4aa230fbbb87bd699c0e3',
				'nextrun' => '2020-12-13 02:57:43',
				'interval' => NULL,
				'status' => 'done',
				'schedule' => NULL,
				'registered_at' => '2021-03-01 04:56:05',
				'revised_at' => '2021-03-01 04:56:05',
				'started_at' => '2020-12-13 02:57:43',
				'finished_at' => '2020-12-13 02:57:43',
				'deleted_at' => '9999-12-31 23:59:59',
			],
			[
				'id' => '12',
				'site' => '1',
				'hook' => 'do_pings',
				'hook_instance' => '2020-12-13 03:07:43',
				'args' => 'a:0:{}',
				'args_digest' => '35786c7117b4e38d0f169239752ce71158266ae2f6e4aa230fbbb87bd699c0e3',
				'nextrun' => '2020-12-13 03:07:43',
				'interval' => NULL,
				'status' => 'done',
				'schedule' => NULL,
				'registered_at' => '2021-03-01 04:56:05',
				'revised_at' => '2021-03-01 04:56:05',
				'started_at' => '2020-12-13 03:07:43',
				'finished_at' => '2020-12-13 03:07:43',
				'deleted_at' => '9999-12-31 23:59:59',
			],
			[
				'id' => '13',
				'site' => '1',
				'hook' => 'do_pings',
				'hook_instance' => '2020-12-13 03:23:48',
				'args' => 'a:0:{}',
				'args_digest' => '35786c7117b4e38d0f169239752ce71158266ae2f6e4aa230fbbb87bd699c0e3',
				'nextrun' => '2020-12-13 03:23:48',
				'interval' => NULL,
				'status' => 'done',
				'schedule' => NULL,
				'registered_at' => '2021-03-01 04:56:05',
				'revised_at' => '2021-03-01 04:56:05',
				'started_at' => '2020-12-13 03:23:48',
				'finished_at' => '2020-12-13 03:23:48',
				'deleted_at' => '9999-12-31 23:59:59',
			],
			[
				'id' => '53',
				'site' => '1',
				'hook' => 'recovery_mode_clean_expired_keys',
				'hook_instance' => NULL,
				'args' => 'a:0:{}',
				'args_digest' => '35786c7117b4e38d0f169239752ce71158266ae2f6e4aa230fbbb87bd699c0e3',
				'nextrun' => '2021-03-19 04:56:04',
				'interval' => '86400',
				'status' => 'waiting',
				'schedule' => 'daily',
				'registered_at' => '2021-03-01 04:56:05',
				'revised_at' => '2021-03-01 04:56:05',
				'started_at' => '2021-03-18 04:56:22',
				'finished_at' => '2021-03-18 04:56:24',
				'deleted_at' => '9999-12-31 23:59:59',
			],
			[
				'id' => '61',
				'site' => '1',
				'hook' => 'publish_future_post',
				'hook_instance' => '2021-04-22 10:51:00',
				'args' => 'a:1:{i:0;i:102;}',
				'args_digest' => '838da5dbf3bafe8435799a51762d30a61263cf4c2b437a3d05e5026089d47571',
				'nextrun' => '2021-04-22 10:51:00',
				'interval' => NULL,
				'status' => 'waiting',
				'schedule' => NULL,
				'registered_at' => '2021-03-22 10:51:36',
				'revised_at' => '2021-03-22 10:51:36',
				'started_at' => NULL,
				'finished_at' => NULL,
				'deleted_at' => '9999-12-31 23:59:59',
			],
			[
				'id' => '62',
				'site' => '1',
				'hook' => 'publish_future_post',
				'hook_instance' => '2021-05-22 10:51:00',
				'args' => 'a:1:{i:0;i:102;}',
				'args_digest' => '838da5dbf3bafe8435799a51762d30a61263cf4c2b437a3d05e5026089d47571',
				'nextrun' => '2021-05-22 10:51:00',
				'interval' => NULL,
				'status' => 'waiting',
				'schedule' => NULL,
				'registered_at' => '2021-03-22 10:51:36',
				'revised_at' => '2021-03-22 10:51:36',
				'started_at' => NULL,
				'finished_at' => NULL,
				'deleted_at' => '9999-12-31 23:59:59',
			],
			[
				'id' => '64',
				'site' => '1',
				'hook' => 'publish_future_post',
				'hook_instance' => '2021-05-22 10:51:00',
				'args' => 'a:1:{i:0;i:333;}',
				'args_digest' => '905d1a16dbd57b33262f34e22e240965354f5951c45557bce072049140498ddb',
				'nextrun' => '2021-05-22 10:51:00',
				'interval' => NULL,
				'status' => 'waiting',
				'schedule' => NULL,
				'registered_at' => '2021-03-22 10:51:36',
				'revised_at' => '2021-03-22 10:51:36',
				'started_at' => NULL,
				'finished_at' => NULL,
				'deleted_at' => '9999-12-31 23:59:59',
			],
		];

		self::upgrade();

		$this->assertEquals( $expected_data, $wpdb->get_results( "select * from $this->table", ARRAY_A ) );
	}

	public function test_duplicate_entry() {
		global $wpdb;

		self::upgrade();

		$wpdb->query( "INSERT INTO $this->table VALUES (53, 1, 'recovery_mode_clean_expired_keys', '', 'a:0:{}', '35786c7117b4e38d0f169239752ce71158266ae2f6e4aa230fbbb87bd699c0e3', '2021-03-19 04:56:04', 86400, 'waiting', 'daily', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-18 04:56:22', '2021-03-18 04:56:24', '9999-12-31 23:59:59')" );
		$suppress = $wpdb->suppress_errors();
		try {
			$wpdb->query( "INSERT INTO $this->table (`site`, `hook`, `args`, `args_digest`, `nextrun`, `interval`, `schedule`) VALUES (1, 'recovery_mode_clean_expired_keys', 'a:0:{}', '35786c7117b4e38d0f169239752ce71158266ae2f6e4aa230fbbb87bd699c0e3', '2021-03-19 04:56:04', 86400, 'daily')" );
			$errno = mysqli_errno( $wpdb->getDbh() );
		} finally {
			$wpdb->suppress_errors( $suppress );
		}

		$this->assertEquals( 1062, $errno );
		$this->assertCount( 1, $this->get_all() );
	}
}
