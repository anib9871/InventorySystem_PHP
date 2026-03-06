<?php
require_once('includes/load.php');

header('Content-Type: application/json');

if (!isset($_GET['product_id'])) {
    echo json_encode([]);
    exit;
}

$product_id = (int)$_GET['product_id'];

$sql = "
    SELECT 
        rm.rate,
        rm.mrp,
        rm.gst_id,
        rm.price_date,
        gm.gst_percent
    FROM rate_master rm
    LEFT JOIN gst_master gm ON gm.id = rm.gst_id
    WHERE rm.product_id = '$product_id'
      AND rm.is_active = 1
    ORDER BY rm.price_date DESC, rm.id DESC
    LIMIT 1
";

$result = $db->query($sql);

if ($db->num_rows($result) > 0) {

    $row = $db->fetch_assoc($result);

$inclusive_rate = (float)$row['rate'];
$gst_percent = (float)$row['gst_percent'];


    echo json_encode([
    "rate" => $inclusive_rate,   // already inclusive
    "mrp" => $row['mrp'],
    "gst_id" => $row['gst_id'],
    "last_rate" => $inclusive_rate,
    "gst_percent" => $gst_percent,
    "price_date" => $row['price_date']
]);


} else {
    echo json_encode([]);
}
