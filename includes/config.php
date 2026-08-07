<?php

// 🚀 Change version to 1.0.2 to trigger the Toast alert:
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.0.2'); 
}
define('DB_HOST', getenv('MYSQLHOST'));
define('DB_USER', getenv('MYSQLUSER'));
define('DB_PASS', getenv('MYSQLPASSWORD'));
define('DB_NAME', getenv('MYSQLDATABASE'));
define('DB_PORT', getenv('MYSQLPORT'));

error_reporting(E_ALL);
ini_set('display_errors', 1);
