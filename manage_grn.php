<?php
$page_title = 'Manage GRN';
require_once('includes/load.php');

$grn_list = find_by_sql("

SELECT
tm.bill_indent_no,
MAX(tm.bill_indent_date) as bill_indent_date,

(
SUM(tm.net_price)
+
IFNULL(
(
SELECT SUM(total_amount)
FROM shipping s
WHERE s.bill_no = tm.bill_indent_no
),0)
) as total,

MAX(s.supplier_name) as supplier_name

FROM transaction_master tm

LEFT JOIN supplier_master s
ON s.id = tm.supplier_id

WHERE tm.transaction_type = 1
AND tm.from_dept = 'SUPPLIER'

GROUP BY tm.bill_indent_no

ORDER BY MAX(tm.bill_indent_date) DESC

");

include_once('layouts/header.php');
?>

<div class="row">

<div class="col-md-12">

<div class="panel panel-default">

<div class="panel-heading">
<strong>Manage GRN</strong>
</div>

<div class="panel-body">

<table class="table table-bordered table-striped">

<thead>
<tr>
<th>#</th>
<th>GRN No</th>
<th>Supplier</th>
<th>Date</th>
<th>Total</th>
<th width="150">Action</th>
</tr>
</thead>

<tbody>

<?php if(empty($grn_list)){ ?>

<tr>
<td colspan="6" class="text-center">
No GRN Found
</td>
</tr>

<?php } ?>

<?php $i=1; foreach($grn_list as $g){ ?>

<tr>

<td><?= $i++; ?></td>

<td><?= $g['bill_indent_no']; ?></td>

<td><?= $g['supplier_name']; ?></td>

<td><?= date('d-m-Y', strtotime($g['bill_indent_date'])); ?></td>

<td><?= number_format($g['total'],2); ?></td>

<td>

<a href="grn.php?edit=<?= urlencode($g['bill_indent_no']); ?>"
class="btn btn-xs btn-primary">

Edit

</a>

<a href="print_grn.php?bill=<?= urlencode($g['bill_indent_no']); ?>"
class="btn btn-xs btn-success"
target="_blank">

Print

</a>

</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>

</div>

</div>

<?php include_once('layouts/footer.php'); ?>
