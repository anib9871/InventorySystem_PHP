<?php
require_once('includes/load.php');

header('Content-Type: application/json');

$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;

if ($customer_id <= 0) {
    echo json_encode([]);
    exit;
}

// Fixed Query: Removed p.gst_percent to fix Fatal Error
$demo_items = find_by_sql("
    SELECT 
        d.id AS demo_id,
        d.product_id,
        d.qty,
        p.name AS product_name,
        p.sale_price
    FROM demo_item_detail d
    JOIN products p ON d.product_id = p.id
    WHERE d.customer_id = '$customer_id'
      AND d.status = 1
");

echo json_encode($demo_items);