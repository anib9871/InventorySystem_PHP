<?php
require_once('includes/load.php');

$product_id = (int)$_GET['product_id'];

$data = find_by_sql("
SELECT 
r.rate,
r.gst_id,
g.gst_percent
FROM rate_master r
LEFT JOIN gst_master g ON g.id = r.gst_id
WHERE r.product_id = '{$product_id}'
ORDER BY r.id DESC
LIMIT 1
");

echo json_encode($data ? $data[0] : []);