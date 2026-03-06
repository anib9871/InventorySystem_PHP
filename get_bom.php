<?php
require_once('includes/load.php');

$product_id = (int)$_GET['product_id'];

$bom = find_by_sql("
  SELECT p.name, b.quantity
  FROM bom b
  JOIN products p ON p.id = b.raw_product_id
  WHERE b.product_id = $product_id
");

if(!$bom){
    echo "No BOM found";
    exit;
}

echo "<strong>Raw Materials:</strong><br><br>";

foreach($bom as $b){
    echo $b['name']." — Qty: ".$b['quantity']."<br>";
}