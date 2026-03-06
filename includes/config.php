<?php

define('DB_HOST', getenv('MYSQLHOST') ?: 'localhost');
define('DB_USER', getenv('MYSQLUSER') ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: 'Mysql123@');
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'inventory_system');
define('DB_PORT', getenv('MYSQLPORT') ?: '3306');

?>
