<?php
$page_title = 'Supplier Advance';
require_once('includes/load.php');

$suppliers = find_all('supplier_master');

$payment_modes = find_by_sql("
SELECT *
FROM payment_mode_master
WHERE is_active = 1
ORDER BY mode_name ASC
");

/* ================= SAVE ADVANCE ================= */

if(isset($_POST['save_advance'])){

$supplier_id = (int)$_POST['supplier_id'];

$amount =
(float)$_POST['amount'];

$mode =
$db->escape($_POST['payment_mode']);

$ref =
$db->escape($_POST['reference_no']);

$remarks =
$db->escape($_POST['remarks']);

if($supplier_id <= 0 || $amount <= 0){

$session->msg('d','Invalid Details');

redirect('supplier_advance.php');

}

/* ================= SUPPLIER LEDGER ================= */

$db->query("

INSERT INTO supplier_ledger
(
supplier_id,
bill_no,
bill_date,
bill_amount,
paid_amount,
balance_amount,
payment_status,
entry_type,
created_at
)

VALUES
(
'$supplier_id',
'ADVANCE',
CURDATE(),
0,
'$amount',
'-$amount',
1,
'ADVANCE',
NOW()
)

");

$ledger_id = $db->insert_id();

/* ================= SUPPLIER PAYMENT ================= */

$db->query("

INSERT INTO supplier_payment
(
ledger_id,
supplier_id,
payment_date,
payment_amount,
payment_mode,
reference_no,
created_at
)

VALUES
(
'$ledger_id',
'$supplier_id',
CURDATE(),
'$amount',
'$mode',
'$ref',
NOW()
)

");

$session->msg('s','Advance Added Successfully');

redirect('supplier_advance.php');

}

/* ================= ADVANCE LIST ================= */

$advances = find_by_sql("

SELECT

sl.*,
sm.supplier_name,

sp.payment_mode,
sp.reference_no

FROM supplier_ledger sl

LEFT JOIN supplier_master sm
ON sm.id = sl.supplier_id

LEFT JOIN supplier_payment sp
ON sp.ledger_id = sl.ledger_id

WHERE sl.entry_type='ADVANCE'

ORDER BY sl.ledger_id DESC

");

include_once('layouts/header.php');

?>

<div class="row">

<div class="col-md-12">

<?php echo display_msg($msg); ?>

</div>

</div>

<div class="row">

<!-- ================= LEFT ================= -->

<div class="col-md-4">

<div class="panel panel-default">

<div class="panel-heading">

<strong>

Add Supplier Advance

</strong>

</div>

<div class="panel-body">

<form method="post">

<div class="form-group">

<label>Supplier</label>

<select
name="supplier_id"
class="form-control"
required>

<option value="">

Select Supplier

</option>

<?php foreach($suppliers as $s): ?>

<option value="<?= $s['id']; ?>">

<?= $s['supplier_name']; ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="form-group">

<label>Advance Amount</label>

<input
type="number"
step="0.01"
min="0"
name="amount"
class="form-control"
required>

</div>

<div class="form-group">

<label>Payment Mode</label>

<select
name="payment_mode"
class="form-control"
required>

<option value="">

Select Mode

</option>

<?php foreach($payment_modes as $pm): ?>

<option value="<?= $pm['mode_name']; ?>">

<?= strtoupper($pm['mode_name']); ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="form-group">

<label>Reference No</label>

<input
type="text"
name="reference_no"
class="form-control">

</div>

<div class="form-group">

<label>Remarks</label>

<textarea
name="remarks"
class="form-control"
rows="3"></textarea>

</div>

<button
type="submit"
name="save_advance"
class="btn btn-success btn-block">

Save Advance

</button>

</form>

</div>

</div>

</div>

<!-- ================= RIGHT ================= -->

<div class="col-md-8">

<div class="panel panel-default">

<div class="panel-heading">

<strong>

Supplier Advance History

</strong>

</div>

<div class="panel-body">

<input
type="text"
id="searchAdvance"
class="form-control"
placeholder="Search Supplier">

<br>

<div class="table-responsive">

<table
class="table table-bordered table-striped">

<thead>

<tr>

<th>#</th>
<th>Supplier</th>
<th>Amount</th>
<th>Mode</th>
<th>Reference</th>
<th>Date</th>

</tr>

</thead>

<tbody id="advanceTable">

<?php foreach($advances as $i => $a): ?>

<tr>

<td><?= $i+1; ?></td>

<td>

<?= $a['supplier_name']; ?>

</td>

<td style="
color:green;
font-weight:bold;
">

₹ <?= number_format(
abs($a['balance_amount']),
2
); ?>

</td>

<td>

<?= strtoupper(
$a['payment_mode']
); ?>

</td>

<td>

<?= $a['reference_no']; ?>

</td>

<td>

<?= date(
'd-m-Y',
strtotime($a['created_at'])
); ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>

</div>

</div>

</div>

<script>

document.getElementById("searchAdvance")
.addEventListener("keyup", function(){

let value =
this.value.toLowerCase();

document.querySelectorAll(
"#advanceTable tr"
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

<?php include_once('layouts/footer.php'); ?>