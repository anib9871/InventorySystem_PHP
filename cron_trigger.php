<?php
// Security Key taaki koi unauthorized user URL hit karke mail na bhej sake
$secret_key = "MySuperSecretCronKey123";

if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) {
    http_response_code(403);
    die("Unauthorized Access");
}

// Production Script Call
include_once __DIR__ . '/cron_daily_report.php';
?>
