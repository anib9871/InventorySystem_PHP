<?php
require_once('includes/load.php');

$data = find_by_sql("
SELECT id, type_name
FROM shipping_type_master
WHERE is_active = 1
ORDER BY type_name
");

header('Content-Type: application/json');

echo json_encode($data);