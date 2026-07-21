<?php

$page_title = 'Payment Report';
require_once('includes/load.php');

/* ================= DATE FILTER ================= */

$from = isset($_GET['from'])
    ? $_GET['from']
    : date('Y-m-01');

$to = isset($_GET['to'])
    ? $_GET['to']
    : date('Y-m-d');


$formats = ['d/M/Y', 'd-m-Y', 'Y-m-d'];

foreach ($formats as $format) {
    $dt = DateTime::createFromFormat($format, $from);
    if ($dt instanceof DateTime) {
        $from = $dt->format('Y-m-d');
        break;
    }
}

foreach ($formats as $format) {
    $dt = DateTime::createFromFormat($format, $to);
    if ($dt instanceof DateTime) {
        $to = $dt->format('Y-m-d');
        break;
    }
}

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
AND DATE(p.payment_date)
BETWEEN '{$from}' AND '{$to}'

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

<style>

body{
    background:#f4f7fb;
}

/* ---------- CARD ---------- */

.report-card{
    background:#fff;
    border-radius:16px;
    box-shadow:0 8px 20px rgba(0,0,0,.06);
    border:none;
    margin-bottom:20px;
}

.report-card .panel-heading{
    background:#fff !important;
    border:none !important;
    padding:18px 22px 0;
    font-size:18px;
    font-weight:600;
}

.report-card .panel-body{
    padding:12px 18px;
}

/* ---------- FILTER ---------- */

.filter-box{
    background:#fff;
    border-radius:16px;
    box-shadow:0 8px 20px rgba(0,0,0,.06);
    padding:20px;
    margin-bottom:20px;
}

.filter-box .form-control{
    height:42px;
    border-radius:10px;
    border:1px solid #dfe6ee;
    box-shadow:none;
}

.filter-box .btn{
    height:42px;
    border-radius:10px;
    font-weight:600;
}

/* ---------- SEARCH ---------- */

.search-box{
    height:42px;
    border-radius:10px;
    border:1px solid #dfe6ee;
    margin-bottom:15px;
}

/* ---------- TABLE ---------- */

.table{
    margin-bottom:0;
}

.table thead th{
    background:#111827 !important;
    color:#fff !important;
    border:none !important;
    padding:12px 10px !important;
    font-size:13px;
}

.table tbody td{
    padding:11px 10px;
    vertical-align:middle !important;
    font-size:13px;
}

.table tbody tr:hover{
    background:#f8fbff;
}

/* ---------- BADGES ---------- */

.label-success{
    background:#16a34a !important;
    border-radius:30px;
    padding:6px 12px;
}

.label-warning{
    background:#f59e0b !important;
    border-radius:30px;
    padding:6px 12px;
}

.label-danger{
    background:#dc2626 !important;
    border-radius:30px;
    padding:6px 12px;
}

.label-info{
    background:#0ea5e9 !important;
    border-radius:30px;
    padding:6px 12px;
}

/* ---------- TOTAL ---------- */

tfoot{
    background:#fafafa;
}

tfoot th{
    font-size:15px;
}

/* ---------- PANEL REMOVE ---------- */

.panel{
    border:none;
    box-shadow:none;
    background:transparent;
}

.table{
    border-collapse:separate;
    border-spacing:0;
}

.table tbody tr{
    transition:.2s;
}

.table tbody tr:hover{
    transform:scale(1.002);
}

.table td{
    border-color:#eef2f7 !important;
}

.table thead th:first-child{
    border-top-left-radius:10px;
}

.table thead th:last-child{
    border-top-right-radius:10px;
}

.panel-heading strong{
    font-size:18px;
    color:#111827;
}

.search-box:focus,
.form-control:focus{
    border-color:#2563eb;
    box-shadow:0 0 0 3px rgba(37,99,235,.12);
}

.report-card hr{
    margin:10px 0 12px !important;
}

.report-card label{
    margin-bottom:4px;
    font-size:12px;
    font-weight:600;
    color:#555;
}

.report-card .radio-inline{
    font-size:13px;
    font-weight:500;
    margin-bottom:0;
}

.report-card .form-control{
    height:34px;
    font-size:13px;
    border-radius:6px;
    padding:6px 10px;
}

.report-card .btn{
    height:34px;
    font-size:13px;
    border-radius:6px;
    padding:6px 14px;
}

</style>


<?php if($is_inventory || $is_combined): ?>

<div class="row">
<div class="col-md-12">

<div class="report-card">
<div class="panel-body">

<div class="row" style="display:flex;align-items:center;min-height:40px;">

<div class="col-md-2" style="display:flex;align-items:center;height:40px;">
    <label class="radio-inline" style="margin:0;">
        <input type="radio" name="reportType" value="customer" checked>
        Customer
    </label>
</div>

<div class="col-md-2" style="display:flex;align-items:center;height:40px;">
    <label class="radio-inline" style="margin:0;">
        <input type="radio" name="reportType" value="supplier">
        Supplier
    </label>
</div>

<div class="col-md-3" style="display:flex;align-items:center;">
    <label style="margin:0 8px 0 0;white-space:nowrap;">
        From Date
    </label>

    <input
        type="text"
        id="fromDate"
        class="form-control"
        value="<?= date('d/M/Y', strtotime($from)); ?>"
        autocomplete="off">
</div>

<div class="col-md-2" style="display:flex;align-items:center;">
    <label style="margin:0 8px 0 0;white-space:nowrap;">
        To Date
    </label>

    <input
        type="text"
        id="toDate"
        class="form-control"
        value="<?= date('d/M/Y', strtotime($to)); ?>"
        autocomplete="off">
</div>

<div class="col-md-4">
    <button
        type="button"
        class="btn btn-primary btn-block"
        onclick="filterReport()">
        <i class="fa fa-search"></i> Generate Report
    </button>
</div>

</div>
</div>

</div>
</div>

<?php endif; ?>

<div class="row">

<div class="col-md-12">

<?php if($is_billing || $is_inventory || $is_combined): ?>

<div
class="report-card"
id="customerPanel"
>

<div class="panel-heading">

<strong>

Payment Collection Report

</strong>

</div>

<div class="panel-body">

<input
type="text"
id="searchPayment"
class="form-control search-box"
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
'd/M/Y',
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

WHERE DATE(sp.payment_date)
BETWEEN '{$from}' AND '{$to}'

ORDER BY sp.payment_id DESC

");

?>

<div class="row" style="margin-top:10px;">

<div class="col-md-12">

<div
class="report-card"
id="supplierPanel"
>

<div class="panel-heading">

<strong>

Supplier Payment Report

</strong>

</div>

<div class="panel-body">

<input
type="text"
id="searchSupplier"
class="form-control search-box"
placeholder="Search Supplier / Bill / Payment Mode">

<br>

<div
class="table-responsive"
style="
border-radius:12px;
overflow:hidden;
border:1px solid #e5e7eb;
">

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
'd/M/Y',
strtotime($s['payment_date'])
); ?>

</td>

<td>

<?php
if($s['bill_no'] == 'ADVANCE'){
    echo '<span class="label label-info">Advance Paid</span>';
}else{
    echo htmlspecialchars($s['bill_no']);
}
?>

</td>

<td>

<?= $s['supplier_name']; ?>

</td>

<td>

<?php if($s['bill_no'] == 'ADVANCE'){ ?>

₹ <?= number_format(abs($s['paid_amount']),2); ?>

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

<?php
if($s['bill_no'] == 'ADVANCE'){
?>

<span style="color:green;">
Remaining Advance ₹ <?= number_format(abs($s['balance_amount']),2); ?>
</span>

<?php
}else{
?>

<span style="color:green;">
Advance Used
</span>

<?php } ?>

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

<script>

function filterReport(){

    let from = document.getElementById("fromDate").value;
    let to   = document.getElementById("toDate").value;

    window.location =
        "?from=" + from + "&to=" + to;

}

</script>


<script>
document.addEventListener("DOMContentLoaded", function () {

    flatpickr("#fromDate", {
        dateFormat: "d/M/Y",
        allowInput: false,
        disableMobile: true
    });

    flatpickr("#toDate", {
        dateFormat: "d/M/Y",
        allowInput: false,
        disableMobile: true
    });

});
</script>


<?php include_once('layouts/footer.php'); ?>
