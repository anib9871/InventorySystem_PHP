<?php

$page_title = 'Payment Report';
require_once('includes/load.php');

$reports = find_by_sql("

SELECT

p.id,
p.payment_date,
p.payment_mode,
p.amount,
p.reference_no AS ref_no,

i.invoice_no,

i.payment_status,

c.customer_name

FROM payments p

INNER JOIN invoice i
ON i.id = p.invoice_id

INNER JOIN customer_master c
ON c.id = p.customer_id

WHERE p.customer_id IS NOT NULL
AND p.customer_id > 0

ORDER BY p.id DESC

");

$is_inventory =
isset($_SESSION['inventory_access'])
&& $_SESSION['inventory_access'] == 1;

$is_billing =
isset($_SESSION['billing_access'])
&& $_SESSION['billing_access'] == 1;

$is_combined =
isset($_SESSION['combined_mode'])
&& $_SESSION['combined_mode'] == 1;

?>

<?php include_once('layouts/header.php'); ?>


<?php if($is_inventory || $is_combined): ?>

<div class="row">
<div class="col-md-12">

<div class="panel panel-default">
<div class="panel-body">

<label class="radio-inline">
<input type="radio"
name="reportType"
value="customer"
checked>

Customer Payments
</label>

<label class="radio-inline">
<input type="radio"
name="reportType"
value="supplier">

Supplier Payments
</label>

</div>
</div>

</div>
</div>

<?php endif; ?>

<div class="row">

<div class="col-md-12">

<?php if($is_billing || $is_inventory || $is_combined): ?>

<div
class="panel panel-default"
id="customerPanel"
>

<div class="panel-heading">

<strong>

Payment Collection Report

</strong>

</div>

<div class="panel-body">

<input type="text"
id="searchPayment"
class="form-control"
placeholder="Search Customer / Invoice / Mode">

<br>

<div class="table-responsive">

<table class="table table-bordered table-striped">

<thead>

<tr>

<th>#</th>
<th>Date</th>
<th>Invoice</th>
<th>Customer</th>
<th>Mode</th>
<th>Amount</th>
<th>Status</th>
<th style="min-width:180px;">Ref No</th>

</tr>

</thead>

<tbody id="paymentReportTable">

<?php

$total = 0;

foreach($reports as $i => $r):

$total += $r['amount'];

?>

<tr>

<td><?= $i+1; ?></td>

<td>

<?= date(
'd-m-Y',
strtotime($r['payment_date'])
); ?>

</td>

<td>

<?= $r['invoice_no']; ?>

</td>

<td>

<?= $r['customer_name']; ?>

</td>

<td>

<?= strtoupper($r['payment_mode']); ?>

</td>

<td style="color:green;font-weight:bold;">

₹ <?= number_format($r['amount'],2); ?>

</td>

<td>

<?php

$status = strtolower(trim($r['payment_status']));

if($status == 'paid'){
    echo '<span class="label label-success">Paid</span>';
}
elseif($status == 'partial'){
    echo '<span class="label label-warning">Partial</span>';
}
else{
    echo '<span class="label label-danger">Unpaid</span>';
}

?>

</td>

<td style="min-width:180px; white-space:nowrap; font-weight:bold; color:#333;">

<?= htmlspecialchars($r['ref_no'] ?? '-') ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

<tfoot>

<tr>

<th colspan="5"
class="text-right">

Total Collection

</th>

<th colspan="3"
style="color:green;">

₹ <?= number_format($total,2); ?>

</th>

</tr>

</tfoot>

</table>

</div>

</div>

</div>

</div>

</div>

<script>

document.getElementById("searchPayment")
.addEventListener("keyup", function(){

let value =
this.value.toLowerCase();

document.querySelectorAll(
"#paymentReportTable tr"
)

.forEach(function(row){

row.style.display =

row.innerText.toLowerCase()
.includes(value)

? ""

: "none";

});

});

</script>

<?php endif; ?>

<?php

if($is_inventory || $is_combined):

/* ================= SUPPLIER REPORT ================= */

$supplier_reports = find_by_sql("

SELECT

sp.payment_id,
sp.payment_date,
sp.payment_mode,
sp.payment_amount,
sp.reference_no,

sl.bill_no,
sl.bill_amount,
sl.paid_amount,
sl.balance_amount,

sl.payment_status,

sm.supplier_name

FROM supplier_payment sp

LEFT JOIN supplier_ledger sl
ON sl.ledger_id = sp.ledger_id

LEFT JOIN supplier_master sm
ON sm.id = sp.supplier_id

ORDER BY sp.payment_id DESC

");

?>

<br><br>

<div class="row">

<div class="col-md-12">

<div
class="panel panel-danger"
id="supplierPanel"
>

<div class="panel-heading">

<strong>

Supplier Payment Report

</strong>

</div>

<div class="panel-body">

<input type="text"
id="searchSupplier"
class="form-control"
placeholder="Search Supplier / Bill / Payment Mode">

<br>

<div class="table-responsive">

<table class="table table-bordered table-striped">

<thead>

<tr>

<th>#</th>
<th>Date</th>
<th>Bill No</th>
<th>Supplier</th>
<th>Bill Amount</th>
<th>Paid</th>
<th style="width:180px;">Balance Status</th>
<th>Mode</th>
<th>Status</th>
<th>Ref No</th>

</tr>

</thead>

<tbody id="supplierPaymentTable">

<?php

$supplier_total = 0;

foreach($supplier_reports as $i => $s):

$supplier_total += $s['payment_amount'];

?>

<tr>

<td><?= $i+1; ?></td>

<td>

<?= date(
'd-m-Y',
strtotime($s['payment_date'])
); ?>

</td>

<td>

<?= $s['bill_no']; ?>

</td>

<td>

<?= $s['supplier_name']; ?>

</td>

<td>

<?php if($s['bill_no'] == 'ADVANCE'){ ?>

₹ <?= number_format(
abs($s['paid_amount']) - abs($s['balance_amount']),
2
); ?>

<?php } else { ?>

₹ <?= number_format($s['bill_amount'],2); ?>

<?php } ?>

</td>

<td style="color:green;font-weight:bold;">

₹ <?= number_format($s['payment_amount'],2); ?>

</td>

<td style="font-weight:bold;width:180px;">

<?php if($s['balance_amount'] > 0){ ?>

<span style="color:red;">
Outstanding ₹ <?= number_format($s['balance_amount'],2); ?>
</span>

<?php } elseif($s['balance_amount'] < 0){ ?>

<span style="color:green;">
Advance ₹ <?= number_format(abs($s['balance_amount']),2); ?>
</span>

<?php } else { ?>

<span style="color:green;">
Settled
</span>

<?php } ?>

</td>

<td>

<?= strtoupper($s['payment_mode']); ?>

</td>

<td>

<?php if($s['payment_status']==1): ?>

<span class="label label-success">

Paid

</span>

<?php else: ?>

<span class="label label-warning">

Partial

</span>

<?php endif; ?>

</td>

<td style="min-width:180px; white-space:nowrap;">

<?= $s['reference_no'] ?: '-'; ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

<tfoot>

<tr>

<th colspan="7" class="text-right">

Total Supplier Payments

</th>

<th colspan="3"
style="color:green;">

₹ <?= number_format($supplier_total,2); ?>

</th>

</tr>

</tfoot>

</table>

</div>

</div>

</div>

</div>

</div>

<script>

document.getElementById("searchSupplier")
.addEventListener("keyup", function(){

let value =
this.value.toLowerCase();

document.querySelectorAll(
"#supplierPaymentTable tr"
)

.forEach(function(row){

row.style.display =

row.innerText.toLowerCase()
.includes(value)

? ""

: "none";

});

});

const radios =
document.querySelectorAll(
'input[name="reportType"]'
);

radios.forEach(radio => {

radio.addEventListener('change', function(){

if(this.value == "customer"){

document.getElementById(
'customerPanel'
).style.display = "block";

document.getElementById(
'supplierPanel'
).style.display = "none";

}else{

document.getElementById(
'customerPanel'
).style.display = "none";

document.getElementById(
'supplierPanel'
).style.display = "block";

}

});

});

/* DEFAULT */

<?php if($is_inventory || $is_combined): ?>

document.getElementById(
'supplierPanel'
).style.display = "none";

<?php endif; ?>


</script>

<?php endif; ?>



<?php include_once('layouts/footer.php'); ?>
