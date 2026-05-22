<?php
require_once('includes/load.php');
// page_require_level(2);

$products = find_all('products');
?>

<?php include_once('layouts/header.php'); ?>

<div class="panel panel-default">
<div class="panel-heading">
<strong>Stock Book Report</strong>
</div>

<div class="panel-body">

<?php

$stock_report = find_by_sql("

SELECT 
    p.id,
    p.name,
    p.reorder_level,

    SUM(
        CASE 
        WHEN t.transaction_type = 1 
        THEN t.quantity 
        ELSE 0 
        END
    ) as total_in,

    SUM(
        CASE 
        WHEN t.transaction_type = 2 
        THEN t.quantity 
        ELSE 0 
        END
    ) as total_out

FROM products p

LEFT JOIN transaction_master t
ON p.id = t.product_id

GROUP BY p.id

ORDER BY p.name ASC

");

?>

<table class="table table-bordered table-striped">

<thead>

<tr style="background:#f2f2f2;">

<th>#</th>
<th>Product Name</th>
<th>Current Stock</th>
<th>Reorder Level</th>
<th>Status</th>

</tr>

</thead>

<tbody>

<?php
$i = 1;

foreach($stock_report as $row):

$current_stock =
(float)$row['total_in']
-
(float)$row['total_out'];

$reorder = (float)$row['reorder_level'];

$status = "In Stock";
$color  = "#b6d7a8";

if($current_stock <= $reorder){

    $status = "Low Stock";
    $color  = "#f9cb9c";
}

if($current_stock <= ($reorder / 2)){

    $status = "Critical Low";
    $color  = "#f4cccc";
}
?>

<tr>

<td><?= $i++; ?></td>

<td><?= $row['name']; ?></td>

<td><?= $current_stock; ?></td>

<td><?= $reorder; ?></td>

<td style="
background:<?= $color ?>;
font-weight:bold;
text-align:center;
">

<?= $status ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>



</div>
</div>

<?php include_once('layouts/footer.php'); ?>
