<?php
/**
 * Plugin Name: Cavalcade
 * Plugin URI: https://github.com/humanmade/Cavalcade
 * Description: A better wp-cron. Horizontally scalable, works perfectly with multisite.
 * Author: Human Made
 * Author URI: https://hmn.md/
 * Version: 2.0.0
 * License: GPLv2 or later
 */

namespace HM\Cavalcade\Plugin;

const DATE_FORMAT = 'Y-m-d H:i:s';
// Don't use '0000-00-00' since it has many pitfalls.
const EMPTY_DELETED_AT = '9999-12-31 23:59:59';
const ER_DUP_ENTRY = 1062;
const ER_NO_SUCH_TABLE = 1146;

require __DIR__ . '/inc/namespace.php';
require __DIR__ . '/inc/class-job.php';
require __DIR__ . '/inc/connector/namespace.php';

bootstrap();

// Register cache groups as early as possible, as some plugins may use cron functions before plugins_loaded
if ( function_exists( 'wp_cache_add_global_groups' ) ) {
	register_cache_groups();
}
