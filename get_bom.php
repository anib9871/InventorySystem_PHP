<?php
require_once('includes/load.php');

$product_id = (int)$_GET['product_id'];

$response = [
    'items' => [],
    'max'   => 0
];

if($product_id <= 0){
    echo json_encode($response);
    exit;
}

$bom = find_by_sql("
SELECT
    b.raw_product_id,
    b.quantity,
    p.name
FROM bom b
JOIN products p
ON p.id = b.raw_product_id
WHERE b.product_id = {$product_id}
");

if(!$bom){
    echo json_encode($response);
    exit;
}

$max = PHP_INT_MAX;

foreach($bom as $b){
    $raw_id = (int)$b['raw_product_id'];

    $stock = find_by_sql("
        SELECT
            IFNULL(SUM(CASE WHEN transaction_type IN (1, 4) THEN (quantity + COALESCE(free_qty, 0)) ELSE 0 END), 0)
            -
            IFNULL(SUM(CASE WHEN transaction_type IN (2, 3, 5, 6, 8) THEN (quantity + COALESCE(free_qty, 0)) ELSE 0 END), 0)
            -
            COALESCE((
                SELECT SUM(d.qty)
                FROM demo_item_detail d
                WHERE d.product_id = {$raw_id} AND d.status = 1
            ), 0)
            AS stock
        FROM transaction_master
        WHERE product_id = {$raw_id}
    ");

    $current_stock = max(0, (float)$stock[0]['stock']);

    $possible = 0;

    if($b['quantity'] > 0){
        $possible = floor($current_stock / $b['quantity']);
    }

    if($possible < $max){
        $max = $possible;
    }

    $response['items'][] = [
        'name'     => $b['name'],
        'quantity' => $b['quantity'],
        'stock'    => $current_stock
    ];
}

$response['max'] = ($max == PHP_INT_MAX) ? 0 : $max;

header('Content-Type: application/json');
echo json_encode($response);
