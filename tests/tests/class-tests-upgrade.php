<?php

namespace HM\Cavalcade\Tests;

use WP_UnitTestCase;
use HM\Cavalcade\Plugin;
use HM\Cavalcade\Plugin\Upgrade;

/**
 * Test deleting events.
 */
class Tests_Upgrade extends WP_UnitTestCase
{
	protected $db;

	public function setUp()
	{
		global $wpdb;

		parent::setUp();
		Plugin\drop_tables();

		$charset_collate = $wpdb->get_charset_collate();
		$this->db = "{$wpdb->base_prefix}cavalcade_jobs";

		$v9schema = "CREATE TABLE `$this->db` (
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

		$wpdb->query( $v9schema );
	}

	private static function upgrade() {
		Upgrade\upgrade_database_10();
		Upgrade\upgrade_database_11();
	}

	public function tearDown()
	{
		Plugin\create_tables();
		parent::tearDown();
	}

	public function test_schema()
	{
		global $wpdb;

		$expected_indices = [
			[
				'Table' => $this->db,
				'Non_unique' => '0',
				'Key_name' => 'PRIMARY',
				'Seq_in_index' => '1',
				'Column_name' => 'id',
				'Collation' => 'A',
				'Cardinality' => '0',
				'Sub_part' => NULL,
				'Packed' => NULL,
				'Null' => '',
				'Index_type' => 'BTREE',
				'Comment' => '',
				'Index_comment' => '',
			],
			[
				'Table' => $this->db,
				'Non_unique' => '0',
				'Key_name' => 'uniqueness',
				'Seq_in_index' => '1',
				'Column_name' => 'site',
				'Collation' => 'A',
				'Cardinality' => '0',
				'Sub_part' => NULL,
				'Packed' => NULL,
				'Null' => '',
				'Index_type' => 'BTREE',
				'Comment' => '',
				'Index_comment' => '',
			],
			[
				'Table' => $this->db,
				'Non_unique' => '0',
				'Key_name' => 'uniqueness',
				'Seq_in_index' => '2',
				'Column_name' => 'hook',
				'Collation' => 'A',
				'Cardinality' => '0',
				'Sub_part' => NULL,
				'Packed' => NULL,
				'Null' => '',
				'Index_type' => 'BTREE',
				'Comment' => '',
				'Index_comment' => '',
			],
			[
				'Table' => $this->db,
				'Non_unique' => '0',
				'Key_name' => 'uniqueness',
				'Seq_in_index' => '3',
				'Column_name' => 'hook_instance',
				'Collation' => 'A',
				'Cardinality' => '0',
				'Sub_part' => NULL,
				'Packed' => NULL,
				'Null' => 'YES',
				'Index_type' => 'BTREE',
				'Comment' => '',
				'Index_comment' => '',
			],
			[
				'Table' => $this->db,
				'Non_unique' => '0',
				'Key_name' => 'uniqueness',
				'Seq_in_index' => '4',
				'Column_name' => 'args_digest',
				'Collation' => 'A',
				'Cardinality' => '0',
				'Sub_part' => NULL,
				'Packed' => NULL,
				'Null' => '',
				'Index_type' => 'BTREE',
				'Comment' => '',
				'Index_comment' => '',
			],
			[
				'Table' => $this->db,
				'Non_unique' => '1',
				'Key_name' => 'status',
				'Seq_in_index' => '1',
				'Column_name' => 'status',
				'Collation' => 'A',
				'Cardinality' => '0',
				'Sub_part' => NULL,
				'Packed' => NULL,
				'Null' => '',
				'Index_type' => 'BTREE',
				'Comment' => '',
				'Index_comment' => '',
			],
			[
				'Table' => $this->db,
				'Non_unique' => '1',
				'Key_name' => 'status',
				'Seq_in_index' => '2',
				'Column_name' => 'deleted_at',
				'Collation' => 'A',
				'Cardinality' => '0',
				'Sub_part' => NULL,
				'Packed' => NULL,
				'Null' => 'YES',
				'Index_type' => 'BTREE',
				'Comment' => '',
				'Index_comment' => '',
			],
			[
				'Table' => $this->db,
				'Non_unique' => '1',
				'Key_name' => 'status-finished_at',
				'Seq_in_index' => '1',
				'Column_name' => 'status',
				'Collation' => 'A',
				'Cardinality' => '0',
				'Sub_part' => NULL,
				'Packed' => NULL,
				'Null' => '',
				'Index_type' => 'BTREE',
				'Comment' => '',
				'Index_comment' => '',
			],
			[
				'Table' => $this->db,
				'Non_unique' => '1',
				'Key_name' => 'status-finished_at',
				'Seq_in_index' => '2',
				'Column_name' => 'finished_at',
				'Collation' => 'A',
				'Cardinality' => '0',
				'Sub_part' => NULL,
				'Packed' => NULL,
				'Null' => 'YES',
				'Index_type' => 'BTREE',
				'Comment' => '',
				'Index_comment' => '',
			],
			[
				'Table' => $this->db,
				'Non_unique' => '1',
				'Key_name' => 'site',
				'Seq_in_index' => '1',
				'Column_name' => 'site',
				'Collation' => 'A',
				'Cardinality' => '0',
				'Sub_part' => NULL,
				'Packed' => NULL,
				'Null' => '',
				'Index_type' => 'BTREE',
				'Comment' => '',
				'Index_comment' => '',
			],
			[
				'Table' => $this->db,
				'Non_unique' => '1',
				'Key_name' => 'site',
				'Seq_in_index' => '2',
				'Column_name' => 'deleted_at',
				'Collation' => 'A',
				'Cardinality' => '0',
				'Sub_part' => NULL,
				'Packed' => NULL,
				'Null' => 'YES',
				'Index_type' => 'BTREE',
				'Comment' => '',
				'Index_comment' => '',
			],
			[
				'Table' => $this->db,
				'Non_unique' => '1',
				'Key_name' => 'hook',
				'Seq_in_index' => '1',
				'Column_name' => 'hook',
				'Collation' => 'A',
				'Cardinality' => '0',
				'Sub_part' => NULL,
				'Packed' => NULL,
				'Null' => '',
				'Index_type' => 'BTREE',
				'Comment' => '',
				'Index_comment' => '',
			],
			[
				'Table' => $this->db,
				'Non_unique' => '1',
				'Key_name' => 'hook',
				'Seq_in_index' => '2',
				'Column_name' => 'deleted_at',
				'Collation' => 'A',
				'Cardinality' => '0',
				'Sub_part' => NULL,
				'Packed' => NULL,
				'Null' => 'YES',
				'Index_type' => 'BTREE',
				'Comment' => '',
				'Index_comment' => '',
			],
		];

		$expected_schema = [
			[
				'Field' => 'id',
				'Type' => 'bigint(20) unsigned',
				'Null' => 'NO',
				'Key' => 'PRI',
				'Default' => NULL,
				'Extra' => 'auto_increment',
			],
			[
				'Field' => 'site',
				'Type' => 'bigint(20) unsigned',
				'Null' => 'NO',
				'Key' => 'MUL',
				'Default' => NULL,
				'Extra' => '',
			],
			[
				'Field' => 'hook',
				'Type' => 'varchar(255)',
				'Null' => 'NO',
				'Key' => 'MUL',
				'Default' => NULL,
				'Extra' => '',
			],
			[
				'Field' => 'hook_instance',
				'Type' => 'varchar(255)',
				'Null' => 'YES',
				'Key' => '',
				'Default' => NULL,
				'Extra' => '',
			],
			[
				'Field' => 'args',
				'Type' => 'longtext',
				'Null' => 'NO',
				'Key' => '',
				'Default' => NULL,
				'Extra' => '',
			],
			[
				'Field' => 'args_digest',
				'Type' => 'char(64)',
				'Null' => 'NO',
				'Key' => '',
				'Default' => NULL,
				'Extra' => '',
			],
			[
				'Field' => 'nextrun',
				'Type' => 'datetime',
				'Null' => 'NO',
				'Key' => '',
				'Default' => NULL,
				'Extra' => '',
			],
			[
				'Field' => 'interval',
				'Type' => 'int(10) unsigned',
				'Null' => 'YES',
				'Key' => '',
				'Default' => NULL,
				'Extra' => '',
			],
			[
				'Field' => 'status',
				'Type' => 'enum(\'waiting\',\'running\',\'done\')',
				'Null' => 'NO',
				'Key' => 'MUL',
				'Default' => 'waiting',
				'Extra' => '',
			],
			[
				'Field' => 'schedule',
				'Type' => 'varchar(255)',
				'Null' => 'YES',
				'Key' => '',
				'Default' => NULL,
				'Extra' => '',
			],
			[
				'Field' => 'registered_at',
				'Type' => 'datetime',
				'Null' => 'NO',
				'Key' => '',
				'Default' => 'CURRENT_TIMESTAMP',
				'Extra' => '',
			],
			[
				'Field' => 'revised_at',
				'Type' => 'datetime',
				'Null' => 'NO',
				'Key' => '',
				'Default' => 'CURRENT_TIMESTAMP',
				'Extra' => '',
			],
			[
				'Field' => 'started_at',
				'Type' => 'datetime',
				'Null' => 'YES',
				'Key' => '',
				'Default' => NULL,
				'Extra' => '',
			],
			[
				'Field' => 'finished_at',
				'Type' => 'datetime',
				'Null' => 'YES',
				'Key' => '',
				'Default' => NULL,
				'Extra' => '',
			],
			[
				'Field' => 'deleted_at',
				'Type' => 'datetime',
				'Null' => 'YES',
				'Key' => '',
				'Default' => NULL,
				'Extra' => '',
			],
		];

		self::upgrade();

		$this->assertEquals( $expected_schema, $wpdb->get_results( "describe $this->db", ARRAY_A ) );
		$this->assertEquals( $expected_indices, $wpdb->get_results( "show index from $this->db", ARRAY_A ) );
	}

	private function get_all() {
		global $wpdb;

		return $wpdb->get_results( "select * from $this->db" );
	}

	public function test_clean_old_completed() {
		global $wpdb;
		$wpdb->query( "INSERT INTO $this->db VALUES (10, 1, 'do_pings', 'a:0:{}', '2020-12-13 02:37:43', NULL, 'completed', NULL, '2021-03-01 04:56:05', '2021-03-01 04:56:05', NULL, NULL, NULL)" );
		self::upgrade();
		$this->assertCount( 0, $this->get_all() );
	}

	public function test_clean_old_failed() {
		global $wpdb;
		$wpdb->query( "INSERT INTO $this->db VALUES (13, 1, 'do_pings', 'a:0:{}', '2020-12-13 03:33:48', NULL, 'failed', NULL, '2021-03-01 04:56:05', '2021-03-01 04:56:05', NULL, NULL, NULL)" );
		self::upgrade();
		$this->assertCount( 0, $this->get_all() );
	}

	public function test_completed_to_done() {
		global $wpdb;
		$wpdb->query( "INSERT INTO $this->db VALUES (11, 1, 'do_pings', 'a:0:{}', '2020-12-13 02:57:43', NULL, 'completed', NULL, '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2020-12-13 02:57:43', '2020-12-13 02:57:43', NULL)" );
		self::upgrade();
		$this->assertEquals( 'done', $this->get_all()[0]->status );
	}

	public function test_failed_to_done() {
		global $wpdb;
		$wpdb->query( "INSERT INTO $this->db VALUES (12, 1, 'do_pings', 'a:0:{}', '2020-12-13 03:07:43', NULL, 'failed', NULL, '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2020-12-13 03:07:43', '2020-12-13 03:07:43', NULL)" );
		self::upgrade();
		$this->assertEquals( 'done', $this->get_all()[0]->status );
	}

	public function test_delete_now() {
		global $wpdb;
		$wpdb->query( "INSERT INTO $this->db VALUES (4, 1, 'wp_version_check', 'a:0:{}', '2021-03-19 01:21:00', 43200, 'waiting', 'twicedaily', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-18 13:21:01', '2021-03-18 13:21:08', '2021-03-18 13:21:08')" );
		self::upgrade();
		$this->assertCount( 0, $this->get_all() );
	}

	public function test_set_hook_instance_for_non_recurring() {
		global $wpdb;
		$wpdb->query( "INSERT INTO $this->db VALUES (61, 1, 'publish_future_post', 'a:1:{i:0;i:102;}', '2021-04-22 10:51:00', NULL, 'waiting', NULL, '2021-03-22 10:51:36', '2021-03-22 10:51:36', NULL, NULL, NULL)" );
		self::upgrade();
		$this->assertEquals( '2021-04-22 10:51:00', $this->get_all()[0]->hook_instance );
	}

	public function test_hook_instance_not_set_for_non_recurring() {
		global $wpdb;
		$wpdb->query( "INSERT INTO $this->db VALUES (47, 1, 'recovery_mode_clean_expired_keys', 'a:0:{}', '2021-03-19 04:56:02', 86400, 'waiting', 'daily', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-18 04:56:17', '2021-03-18 04:56:19', NULL)" );
		self::upgrade();
		$this->assertNull( $this->get_all()[0]->hook_instance );
	}

	public function test_set_args_digest() {
		global $wpdb;
		$wpdb->query( "INSERT INTO $this->db VALUES (53, 1, 'recovery_mode_clean_expired_keys', 'a:0:{}', '2021-03-19 04:56:04', 86400, 'waiting', 'daily', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-18 04:56:22', '2021-03-18 04:56:24', NULL)" );
		$wpdb->query( "INSERT INTO $this->db VALUES (62, 1, 'publish_future_post', 'a:1:{i:0;i:102;}', '2021-05-22 10:51:00', NULL, 'waiting', NULL, '2021-03-22 10:51:36', '2021-03-22 10:51:36', NULL, NULL, NULL)" );
		self::upgrade();
		$result = $this->get_all();
		$this->assertEquals( hash( 'sha256', 'a:0:{}' ), $result[0]->args_digest );
		$this->assertEquals( hash( 'sha256', 'a:1:{i:0;i:102;}' ), $result[1]->args_digest );
	}

	public function test_unique() {
		global $wpdb;
		$wpdb->query( "INSERT INTO $this->db VALUES (1, 1, 'recovery_mode_clean_expired_keys', 'a:0:{}', '2021-03-19 01:21:00', 86400, 'waiting', 'daily', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-18 01:21:01', '2021-03-18 01:21:04', NULL)" );
		$wpdb->query( "INSERT INTO $this->db VALUES (8, 1, 'delete_expired_transients', 'a:0:{}', '2021-03-19 02:55:26', 86400, 'waiting', 'daily', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-18 02:55:27', '2021-03-18 02:55:29', NULL)" );
		$wpdb->query( "INSERT INTO $this->db VALUES (10, 1, 'do_pings', 'a:0:{}', '2020-12-12 02:57:43', NULL, 'completed', NULL, '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2020-12-13 02:57:43', '2020-12-13 02:57:43', NULL)" );
		$wpdb->query( "INSERT INTO $this->db VALUES (11, 1, 'do_pings', 'a:0:{}', '2020-12-13 02:57:43', NULL, 'completed', NULL, '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2020-12-13 02:57:43', '2020-12-13 02:57:43', NULL)" );
		$wpdb->query( "INSERT INTO $this->db VALUES (12, 1, 'do_pings', 'a:0:{}', '2020-12-13 02:57:43', NULL, 'completed', NULL, '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2020-12-13 02:57:43', '2020-12-13 02:57:43', NULL)" );
		$wpdb->query( "INSERT INTO $this->db VALUES (47, 1, 'recovery_mode_clean_expired_keys', 'a:0:{}', '2021-03-19 04:56:02', 86400, 'waiting', 'daily', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-18 04:56:17', '2021-03-18 04:56:19', NULL)" );
		$wpdb->query( "INSERT INTO $this->db VALUES (53, 1, 'recovery_mode_clean_expired_keys', 'a:0:{}', '2021-03-19 04:56:04', 86400, 'waiting', 'daily', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-18 04:56:22', '2021-03-18 04:56:24', NULL)" );
		$wpdb->query( "INSERT INTO $this->db VALUES (54, 1, 'recovery_mode_clean_expired_keys', 'a:1:{i:0;i:102;}', '2021-03-19 04:56:04', 86400, 'waiting', 'daily', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-18 04:56:22', '2021-03-18 04:56:24', NULL)" );
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

		$wpdb->query( "INSERT INTO $this->db VALUES (1, 1, 'recovery_mode_clean_expired_keys', 'a:0:{}', '2021-03-19 01:21:00', 86400, 'waiting', 'daily', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-18 01:21:01', '2021-03-18 01:21:04', NULL)" );
		$wpdb->query( "INSERT INTO $this->db VALUES (2, 1, 'wp_site_health_scheduled_check', 'a:0:{}', '2021-03-22 01:21:00', 604800, 'waiting', 'weekly', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-15 01:21:01', '2021-03-15 01:21:14', NULL)" );
		$wpdb->query( "INSERT INTO $this->db VALUES (4, 1, 'wp_version_check', 'a:0:{}', '2021-03-19 01:21:00', 43200, 'waiting', 'twicedaily', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-18 13:21:01', '2021-03-18 13:21:08', '2021-03-18 13:21:08')" );
		$wpdb->query( "INSERT INTO $this->db VALUES (7, 1, 'wp_scheduled_delete', 'a:0:{}', '2021-03-19 02:55:26', 86400, 'waiting', 'daily', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-18 02:55:27', '2021-03-18 02:55:29', NULL)" );
		$wpdb->query( "INSERT INTO $this->db VALUES (8, 1, 'delete_expired_transients', 'a:0:{}', '2021-03-19 02:55:26', 86400, 'waiting', 'daily', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-18 02:55:27', '2021-03-18 02:55:29', NULL)" );
		$wpdb->query( "INSERT INTO $this->db VALUES (10, 1, 'do_pings', 'a:0:{}', '2020-12-13 02:37:43', NULL, 'completed', NULL, '2021-03-01 04:56:05', '2021-03-01 04:56:05', NULL, NULL, NULL)" );
		$wpdb->query( "INSERT INTO $this->db VALUES (11, 1, 'do_pings', 'a:0:{}', '2020-12-13 02:57:43', NULL, 'completed', NULL, '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2020-12-13 02:57:43', '2020-12-13 02:57:43', NULL)" );
		$wpdb->query( "INSERT INTO $this->db VALUES (12, 1, 'do_pings', 'a:0:{}', '2020-12-13 03:07:43', NULL, 'failed', NULL, '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2020-12-13 03:07:43', '2020-12-13 03:07:43', NULL)" );
		$wpdb->query( "INSERT INTO $this->db VALUES (13, 1, 'do_pings', 'a:0:{}', '2020-12-13 03:23:48', NULL, 'completed', NULL, '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2020-12-13 03:23:48', '2020-12-13 03:23:48', NULL)" );
		$wpdb->query( "INSERT INTO $this->db VALUES (14, 1, 'do_pings', 'a:0:{}', '2020-12-13 03:33:48', NULL, 'failed', NULL, '2021-03-01 04:56:05', '2021-03-01 04:56:05', NULL, NULL, NULL)" );
		$wpdb->query( "INSERT INTO $this->db VALUES (47, 1, 'recovery_mode_clean_expired_keys', 'a:0:{}', '2021-03-19 04:56:02', 86400, 'waiting', 'daily', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-18 04:56:17', '2021-03-18 04:56:19', NULL)" );
		$wpdb->query( "INSERT INTO $this->db VALUES (53, 1, 'recovery_mode_clean_expired_keys', 'a:0:{}', '2021-03-19 04:56:04', 86400, 'waiting', 'daily', '2021-03-01 04:56:05', '2021-03-01 04:56:05', '2021-03-18 04:56:22', '2021-03-18 04:56:24', NULL)" );
		$wpdb->query( "INSERT INTO $this->db VALUES (61, 1, 'publish_future_post', 'a:1:{i:0;i:102;}', '2021-04-22 10:51:00', NULL, 'waiting', NULL, '2021-03-22 10:51:36', '2021-03-22 10:51:36', NULL, NULL, NULL)" );
		$wpdb->query( "INSERT INTO $this->db VALUES (62, 1, 'publish_future_post', 'a:1:{i:0;i:102;}', '2021-05-22 10:51:00', NULL, 'waiting', NULL, '2021-03-22 10:51:36', '2021-03-22 10:51:36', NULL, NULL, NULL)" );
		$wpdb->query( "INSERT INTO $this->db VALUES (63, 1, 'publish_future_post', 'a:1:{i:0;i:333;}', '2021-05-22 10:51:00', NULL, 'waiting', NULL, '2021-03-22 10:51:36', '2021-03-22 10:51:36', NULL, NULL, NULL)" );
		$wpdb->query( "INSERT INTO $this->db VALUES (64, 1, 'publish_future_post', 'a:1:{i:0;i:333;}', '2021-05-22 10:51:00', NULL, 'waiting', NULL, '2021-03-22 10:51:36', '2021-03-22 10:51:36', NULL, NULL, NULL)" );

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
				'deleted_at' => NULL,
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
				'deleted_at' => NULL,
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
				'deleted_at' => NULL,
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
				'deleted_at' => NULL,
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
				'deleted_at' => NULL,
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
				'deleted_at' => NULL,
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
				'deleted_at' => NULL,
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
				'deleted_at' => NULL,
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
				'deleted_at' => NULL,
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
				'deleted_at' => NULL,
			],
		];

		self::upgrade();

		$this->assertEquals( $expected_data, $wpdb->get_results( "select * from $this->db", ARRAY_A ) );
	}
}
