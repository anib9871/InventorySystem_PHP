<?php

session_start();
require_once('includes/load.php');

/*
|--------------------------------------------------------------------------
| LOGIN CHECK
|--------------------------------------------------------------------------
*/
if (!$session->isUserLoggedIn(true)) {
    http_response_code(401);
    echo "SESSION_EXPIRED";
    exit();
}

/*
|--------------------------------------------------------------------------
| ONLY ORGANIZATION ADMIN
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['user_level']) || (int)$_SESSION['user_level'] !== 1) {
    http_response_code(403);
    echo "ACCESS_DENIED";
    exit();
}

/*
|--------------------------------------------------------------------------
| CURRENT ORGANIZATION DATABASE
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['db_name'])) {
    http_response_code(400);
    echo "DATABASE_NOT_FOUND";
    exit();
}

$db_name = $_SESSION['db_name'];

/*
|--------------------------------------------------------------------------
| MASTER DATABASE PROTECTION
|--------------------------------------------------------------------------
*/
$master_db = getenv('MYSQLDATABASE');

if (empty($master_db)) {
    $master_db = 'master_inventory';
}

if ($db_name === $master_db || $db_name === 'master_inventory') {
    http_response_code(403);
    echo "MASTER_DATABASE_BLOCKED";
    exit();
}

/*
|--------------------------------------------------------------------------
| RAILWAY DATABASE VARIABLES
|--------------------------------------------------------------------------
*/
$host = getenv('MYSQLHOST');
$user = getenv('MYSQLUSER');
$pass = getenv('MYSQLPASSWORD');
$port = (int)getenv('MYSQLPORT');

if (empty($host) || empty($user) || empty($db_name)) {
    http_response_code(500);
    echo "DATABASE_CONFIG_ERROR";
    exit();
}

/*
|--------------------------------------------------------------------------
| CONNECT
|--------------------------------------------------------------------------
*/
$conn = mysqli_connect(
    $host,
    $user,
    $pass,
    $db_name,
    $port
);

if (!$conn) {
    http_response_code(500);
    echo "DATABASE_CONNECTION_FAILED";
    exit();
}

mysqli_set_charset($conn, 'utf8mb4');

/*
|--------------------------------------------------------------------------
| BACKUP FILENAME
|--------------------------------------------------------------------------
*/
$safe_db_name = preg_replace(
    '/[^a-zA-Z0-9_-]/',
    '_',
    $db_name
);

$filename =
    'storely_' .
    $safe_db_name .
    '_backup_' .
    date('Y-m-d_H-i-s') .
    '.sql';

/*
|--------------------------------------------------------------------------
| STREAM RESPONSE
|--------------------------------------------------------------------------
*/
header('Content-Type: application/octet-stream');

header(
    'Content-Disposition: attachment; filename="' .
    $filename .
    '"'
);

header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

/*
|--------------------------------------------------------------------------
| SQL HEADER
|--------------------------------------------------------------------------
*/
echo "-- Storely Database Backup\n";
echo "-- Organization: " .
     ($_SESSION['org_name'] ?? '') .
     "\n";

echo "-- Database: " .
     $db_name .
     "\n";

echo "-- Generated: " .
     date('Y-m-d H:i:s') .
     "\n";

echo "-- --------------------------------------------------\n\n";

echo "SET FOREIGN_KEY_CHECKS=0;\n";
echo "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
echo "SET NAMES utf8mb4;\n\n";

flush();

/*
|--------------------------------------------------------------------------
| GET TABLES
|--------------------------------------------------------------------------
*/
$tables_result = mysqli_query(
    $conn,
    "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"
);

if (!$tables_result) {

    mysqli_close($conn);

    http_response_code(500);

    exit();
}

/*
|--------------------------------------------------------------------------
| PROCESS TABLES
|--------------------------------------------------------------------------
*/
while ($table_row = mysqli_fetch_row($tables_result)) {

    $table = $table_row[0];

    $safe_table =
        str_replace('`', '``', $table);

    /*
    |--------------------------------------------------------------------------
    | TABLE STRUCTURE
    |--------------------------------------------------------------------------
    */
    $create_result = mysqli_query(
        $conn,
        "SHOW CREATE TABLE `{$safe_table}`"
    );

    if ($create_result) {

        $create_row =
            mysqli_fetch_assoc($create_result);

        $create_sql =
            $create_row['Create Table'];

        echo "\n";
        echo "-- --------------------------------------------------\n";
        echo "-- Table: `{$table}`\n";
        echo "-- --------------------------------------------------\n\n";

        echo "DROP TABLE IF EXISTS `{$safe_table}`;\n";

        echo $create_sql . ";\n\n";

        flush();
    }

    /*
    |--------------------------------------------------------------------------
    | TABLE DATA
    |--------------------------------------------------------------------------
    */
    $data_result = mysqli_query(
        $conn,
        "SELECT * FROM `{$safe_table}`"
    );

    if (
        $data_result &&
        mysqli_num_rows($data_result) > 0
    ) {

        echo "-- Data for `{$table}`\n";

        while ($row = mysqli_fetch_assoc($data_result)) {

            $columns = [];
            $values  = [];

            foreach ($row as $column => $value) {

                $columns[] =
                    "`" .
                    str_replace(
                        '`',
                        '``',
                        $column
                    ) .
                    "`";

                if ($value === null) {

                    $values[] = "NULL";

                } else {

                    $values[] =
                        "'" .
                        mysqli_real_escape_string(
                            $conn,
                            $value
                        ) .
                        "'";
                }
            }

            echo
                "INSERT INTO `{$safe_table}` (" .
                implode(', ', $columns) .
                ") VALUES (" .
                implode(', ', $values) .
                ");\n";

            flush();
        }

        echo "\n";
    }
}

/*
|--------------------------------------------------------------------------
| FINISH
|--------------------------------------------------------------------------
*/
echo "\nSET FOREIGN_KEY_CHECKS=1;\n";

flush();

mysqli_close($conn);

exit();
?>
