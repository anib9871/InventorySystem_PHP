<?php
// Strictly fetch secret key from Railway Environment Variable ONLY
$secret_key = getenv('CRON_SECRET_KEY');

// Agar Railway me CRON_SECRET_KEY variable set nahi hai ya key URL parameter se match nahi karti
if (empty($secret_key) || !isset($_GET['key']) || $_GET['key'] !== $secret_key) {
    http_response_code(403);
    die("Unauthorized Access");
}

// Environment Key Verified - Run Cron
include_once __DIR__ . '/cron_daily_report.php';
?>
