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

echo "<table class='table table-sm table-bordered'>";
echo "<tr><th>Raw Material</th><th>Qty</th></tr>";

foreach($bom as $b){
    echo "<tr>
            <td>".$b['name']."</td>
            <td>".$b['quantity']."</td>
          </tr>";
}

echo "</table>";
