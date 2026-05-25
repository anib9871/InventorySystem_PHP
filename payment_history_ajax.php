<?php

require_once('includes/load.php');

$invoice_id = (int)$_GET['invoice_id'];

/* FETCH HISTORY */

$history = find_by_sql("

SELECT *

FROM payments

WHERE invoice_id='{$invoice_id}'

ORDER BY id DESC

");

if(!$history){

echo "

<div class='alert alert-danger'>

No Payment History Found

</div>

";

exit;

}

?>

<div class="table-responsive">

<table class="table table-bordered table-striped">

<thead>

<tr>

<th>#</th>
<th>Date</th>
<th>Payment Mode</th>
<th>Amount</th>
<th>Reference / Remarks</th>

</tr>

</thead>

<tbody>

<?php

$total = 0;

foreach($history as $i => $h):

$total += $h['amount'];

?>

<tr>

<td><?= $i+1; ?></td>

<td>

<?= date(
'd-m-Y',
strtotime($h['payment_date'])
); ?>

</td>

<td>

<?= strtoupper($h['payment_mode']); ?>

</td>

<td style="color:green;font-weight:bold;">

₹ <?= number_format($h['amount'],2); ?>

</td>

<td>

<?= $h['reference_no']; ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

<tfoot>

<tr>

<th colspan="3"
class="text-right">

Total Received

</th>

<th colspan="2"
style="color:green;">

₹ <?= number_format($total,2); ?>

</th>

</tr>

</tfoot>

</table>

</div>