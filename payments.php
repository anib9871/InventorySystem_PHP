<?php

$page_title = 'Outstanding Payments';
require_once('includes/load.php');

$type = $_GET['type'] ?? 'customer';
/* ================= SAVE PAYMENT ================= */

if(isset($_POST['save_payment'])){

$invoice_id = (int)$_POST['invoice_id'];

$amounts = $_POST['amounts'];

$total_amount = 0;

$remarks =
$db->escape($_POST['remarks']);

$payment_date =
$db->escape($_POST['payment_date']);

/* FETCH INVOICE */

$invoice = find_by_sql("

SELECT *

FROM invoice

WHERE id='{$invoice_id}'

LIMIT 1

");

if(!$invoice){

$session->msg('d','Invoice Not Found');
redirect('payments.php');

}

$invoice = $invoice[0];

/* VALIDATION */

foreach($amounts as $amt){

$total_amount += round((float)$amt);

}

if($total_amount <= 0){

$session->msg('d','Invalid Amount');
redirect('payments.php');

}

/* CALCULATE */

$new_paid =
$invoice['paid_amount'] + $total_amount;

$new_due =
$invoice['net_total'] - $new_paid;

if($new_due <= 0){

$new_due = 0;
$status = 'Paid';

}else{

$status = 'Partial';

}

foreach($amounts as $mode => $amt){

$amt = round((float)$amt);

if($amt > 0){

$db->query("

INSERT INTO payments
(
invoice_id,
customer_id,
payment_mode,
amount,
reference_no,
payment_date,
center_id,
created_at
)

VALUES
(
'{$invoice_id}',
'{$invoice['customer_id']}',
'{$mode}',
'{$amt}',
'{$remarks}',
'{$payment_date}',
'1',
NOW()
)

");

}

}

/* UPDATE INVOICE */

$db->query("

UPDATE invoice SET

paid_amount = '{$new_paid}',
due_amount = '{$new_due}',
payment_status = '{$status}'

WHERE id='{$invoice_id}'

");

/* UPDATE CUSTOMER BALANCE */

$db->query("

UPDATE customer_master SET

balance = balance - {$total_amount}

WHERE id='{$invoice['customer_id']}'

");

/* LEDGER ENTRY */

$db->query("

INSERT INTO ledger_entries
(
invoice_id,
customer_id,
account,
type,
amount,
entry_date
)

VALUES
(
'{$invoice_id}',
'{$invoice['customer_id']}',
'PAYMENT RECEIVED',
'CREDIT',
'{$total_amount}',
NOW()
)

");

$session->msg('s','Payment Added Successfully');

redirect('payments.php');

}

/* ================= FETCH INVOICES ================= */

if($type == 'customer'){

$payments = find_by_sql("

SELECT

i.id,
i.invoice_no,

tm.sale_amount,
tm.sale_gst,
tm.sale_net,

(
SELECT COALESCE(SUM(discount_amount),0)
FROM invoice_items ii
WHERE ii.invoice_id = i.id
) as discount_total,

i.subtotal,
i.gst_total,
i.net_total,
i.paid_amount,
i.due_amount,
i.payment_status,
i.created_at,

c.customer_name as party_name

FROM invoice i

LEFT JOIN customer_master c
ON c.id = i.customer_id

LEFT JOIN transaction_master tm
ON tm.bill_indent_no = i.invoice_no

WHERE i.due_amount > 0

ORDER BY i.id DESC

");

}else{

$payments = find_by_sql("

SELECT

sl.ledger_id as id,

sl.bill_no as invoice_no,

sl.bill_amount as net_total,

sl.paid_amount,

sl.balance_amount as due_amount,

sl.payment_status,

sl.created_at,

sm.supplier_name as party_name

FROM supplier_ledger sl

LEFT JOIN supplier_master sm
ON sm.id = sl.supplier_id

WHERE sl.balance_amount > 0

ORDER BY sl.ledger_id DESC

");

}

/* ================= PAYMENT MODES ================= */

$payment_modes = find_by_sql("

SELECT *

FROM payment_mode_master

WHERE is_active = 1

ORDER BY mode_name ASC

");

include_once('layouts/header.php');

?>

<div class="row">

<div class="col-md-12">

<?php echo display_msg($msg); ?>

</div>

</div>

<div class="row">

<div class="col-md-12">

<div class="panel panel-default">

<div class="panel-heading">

<strong>

Outstanding Payments

</strong>

</div>

<div class="panel-body">

<div style="margin-bottom:15px;">

<label style="margin-right:20px;">

<input type="radio"
name="outstanding_type"
value="customer"

<?= ($type=='customer')?'checked':'' ?>

onclick="window.location='payments.php?type=customer'">

 Customer Outstanding

</label>

<label>

<input type="radio"
name="outstanding_type"
value="supplier"

<?= ($type=='supplier')?'checked':'' ?>

onclick="window.location='payments.php?type=supplier'">

 Supplier Outstanding

</label>

</div>

<?php

$total_due = 0;

foreach($payments as $d){

$total_due += $d['due_amount'];

}

?>

<div class="alert alert-danger">

<strong>

Total Outstanding :
₹ <?= number_format($total_due,2); ?>

</strong>

</div>

<input type="text"
id="search"
class="form-control"
placeholder="Search Customer / Invoice">

<br>

<div class="table-responsive">

<table class="table table-bordered table-striped">

<thead>

<tr>

<th>#</th>
<th>

<?= ($type=='customer') ? 'Invoice No' : 'GRN No' ?>

</th>
<th>
<?= ($type=='customer') ? 'Customer' : 'Supplier' ?>
</th>
<?php if($type == 'customer'){ ?>
<th>Amount</th>
<th>GST</th>
<th>Paid</th>
<th>Due</th>

<?php } else { ?>

<th>Bill Amount</th>
<th>Paid</th>
<th>Due</th>

<?php } ?>
<th>Status</th>
<th>Date</th>
<th>Action</th>

</tr>

</thead>

<tbody id="paymentTable">

<?php foreach($payments as $i => $p): ?>

<tr>

<td><?= $i+1; ?></td>

<td><?= $p['invoice_no']; ?></td>

<td>
<?= htmlspecialchars($p['party_name']); ?>
</td>

<?php if($type == 'customer'){ ?>

<td>
₹ <?= number_format($p['sale_amount'],2); ?>
</td>

<td>
₹ <?= number_format($p['sale_gst'],2); ?>
</td>

<td>
₹ <?= number_format($p['paid_amount'],2); ?>
</td>

<td>
<span style="color:red;font-weight:bold;">
₹ <?= number_format($p['due_amount'],2); ?>
</span>
</td>

<?php } else { ?>

<td>
₹ <?= number_format($p['net_total'],2); ?>
</td>

<td>
₹ <?= number_format($p['paid_amount'],2); ?>
</td>

<td>
<span style="color:red;font-weight:bold;">
₹ <?= number_format($p['due_amount'],2); ?>
</span>
</td>

<?php } ?>

<td>

<?php if($p['payment_status']=="Paid"): ?>

<span class="label label-success">

Paid

</span>

<?php elseif($p['payment_status']=="Partial"): ?>

<span class="label label-warning">

Partial

</span>

<?php else: ?>

<span class="label label-danger">

Unpaid

</span>

<?php endif; ?>

</td>

<td>

<?= date('d-m-Y',
strtotime($p['created_at'])); ?>

</td>

<td>

<button
class="btn btn-success btn-xs"

data-toggle="modal"
data-target="#paymentModal"

onclick="setPaymentData(

'<?= $p['id']; ?>',
'<?= $p['invoice_no']; ?>',
'<?= $p['party_name']; ?>',
'<?= $p['due_amount']; ?>'

)">

Add Payment

</button>

<button
class="btn btn-info btn-xs"

data-toggle="modal"
data-target="#historyModal"

onclick="loadHistory(<?= $p['id']; ?>)">

History

</button>

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

<!-- ================= ADD PAYMENT MODAL ================= -->

<div class="modal fade"
id="paymentModal">

<div class="modal-dialog">

<div class="modal-content">

<div class="modal-header">

<button type="button"
class="close"
data-dismiss="modal">

&times;

</button>

<h4 class="modal-title">

Add Payment

</h4>

</div>

<form method="post">

<div class="modal-body">

<input type="hidden"
name="invoice_id"
id="invoice_id">

<div class="form-group">

<label>

<?= ($type=='customer') ? 'Invoice No' : 'GRN No' ?>

</label>

<input type="text"
id="invoice_no"
class="form-control"
readonly>

</div>

<div class="form-group">

<label>Payment Date</label>

<input type="date"
name="payment_date"
class="form-control"
value="<?= date('Y-m-d'); ?>"
required>

</div>

<div class="form-group">

<label>
<?= ($type=='customer') ? 'Customer' : 'Supplier' ?>
</label>

<input type="text"
id="customer_name"
class="form-control"
readonly>

</div>

<div class="form-group">

<label>Remaining Due</label>

<input type="text"
id="due_amount"
class="form-control"
readonly>

</div>

<div class="form-group">

<label>

Payment

</label>

<div style="
max-height:250px;
overflow-y:auto;
border:1px solid #ddd;
padding:10px;
border-radius:5px;
">

<?php foreach($payment_modes as $mode): ?>

<div class="row"
style="margin-bottom:10px;">

<div class="col-md-5">

<label style="font-weight:normal;">

<input type="checkbox"

class="pay-check"

data-target="mode_<?= $mode['id']; ?>"

value="<?= $mode['mode_name']; ?>">

&nbsp;

<?= strtoupper($mode['mode_name']); ?>

</label>

</div>

<div class="col-md-7">

<input type="number"

step="0.01"

min="0"

value="0"

name="amounts[<?= $mode['mode_name']; ?>]"

id="mode_<?= $mode['id']; ?>"

class="form-control"

readonly>

</div>

</div>

<?php endforeach; ?>

</div>

</div>



<div class="form-group">

<label>Remarks / Ref No</label>

<input type="text"
name="remarks"
class="form-control">

</div>

</div>

<div class="modal-footer">

<button type="submit"
name="save_payment"
class="btn btn-success">

Save Payment

</button>

</div>

</form>

</div>

</div>

</div>

<!-- ================= HISTORY MODAL ================= -->

<div class="modal fade"
id="historyModal">

<div class="modal-dialog modal-lg">

<div class="modal-content">

<div class="modal-header">

<button type="button"
class="close"
data-dismiss="modal">

&times;

</button>

<h4 class="modal-title">

Payment History

</h4>

</div>

<div class="modal-body"
id="historyContent">

Loading...

</div>

</div>

</div>

</div>

<script>

/* SEARCH */

document.getElementById("search")
.addEventListener("keyup", function(){

let value = this.value.toLowerCase();

document.querySelectorAll("#paymentTable tr")
.forEach(function(row){

row.style.display =

row.textContent.toLowerCase()
.includes(value)

? ""

: "none";

});

});

/* SET PAYMENT DATA */

function setPaymentData(
id,
invoice,
customer,
due
){

document.getElementById('invoice_id')
.value = id;

document.getElementById('invoice_no')
.value = invoice;

document.getElementById('customer_name')
.value = customer;

document.getElementById('due_amount')
.value = due;

}

/* LOAD HISTORY */

function loadHistory(invoice_id){

fetch('payment_history_ajax.php?invoice_id='
+ invoice_id)

.then(response => response.text())

.then(data => {

document.getElementById('historyContent')
.innerHTML = data;

});

}


document.querySelectorAll('.pay-check')
.forEach(function(check){

check.addEventListener('change', function(){

let target =
document.getElementById(
this.dataset.target
);

if(this.checked){

let due = parseFloat(
document.getElementById('due_amount').value
) || 0;

target.style.display = "block";

target.value = due.toFixed(2);

}else{

target.value = "";

target.style.display = "none";

}

});

});

</script>

<?php include_once('layouts/footer.php'); ?>
