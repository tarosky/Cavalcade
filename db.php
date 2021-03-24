<?php

namespace HM\Cavalcade;

class wpdb extends \wpdb {

	public function getDbh() {
		return $this->dbh;
	}
}

$dbuser     = defined( 'DB_USER' ) ? DB_USER : '';
$dbpassword = defined( 'DB_PASSWORD' ) ? DB_PASSWORD : '';
$dbname     = defined( 'DB_NAME' ) ? DB_NAME : '';
$dbhost     = defined( 'DB_HOST' ) ? DB_HOST : '';

$GLOBALS['wpdb'] = new wpdb($dbuser, $dbpassword, $dbname, $dbhost);
