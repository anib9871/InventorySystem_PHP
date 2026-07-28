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

$payment_date = $_POST['payment_date'];

$formats = ['d/M/Y', 'd-m-Y', 'Y-m-d'];

foreach ($formats as $format) {
    $dt = DateTime::createFromFormat($format, $payment_date);
    if ($dt instanceof DateTime) {
        $payment_date = $dt->format('Y-m-d');
        break;
    }
}

$payment_date = $db->escape($payment_date);

/* FETCH INVOICE */

if($type == 'customer'){

    $invoice = find_by_sql("
    SELECT *
    FROM invoice
    WHERE id='{$invoice_id}'
    LIMIT 1
    ");

}else{

    $invoice = find_by_sql("
    SELECT *
    FROM supplier_ledger
    WHERE ledger_id='{$invoice_id}'
    LIMIT 1
    ");

}

if(!$invoice){
    $session->msg('d','Record Not Found');
    redirect('payments.php?type='.$type);
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

if($type == 'customer'){
    $new_due = $invoice['net_total'] - $new_paid;
}else{
    $new_due = $invoice['bill_amount'] - $new_paid;
}

// Calculate Due
if($type == 'customer'){
    $new_due = round($invoice['net_total'] - $new_paid, 2);
}else{
    $new_due = round($invoice['bill_amount'] - $new_paid, 2);
}

// Round Off Adjustment
if(abs($new_due) <= 0.21){
    $new_due  = 0;
    if($type == 'customer'){
        $new_paid = $invoice['net_total'];
    } else {
        $new_paid = $invoice['bill_amount'];
    }
}

if($new_due <= 0){

    $new_due = 0;

    if($type == 'customer'){
        $status = 'Paid';
    }else{
        $status = 1;
    }

}else{

    if($type == 'customer'){
        $status = 'Partial';
    }else{
        $status = 0;
    }

}
foreach($amounts as $mode => $amt){

    $amt = round((float)$amt);

    if($amt <= 0){
        continue;
    }

    if($type == 'customer'){

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

    }else{

        $db->query("
        INSERT INTO supplier_payment
        (
            ledger_id,
            supplier_id,
            payment_date,
            payment_amount,
            payment_mode,
            reference_no,
            created_at,
            organization_id,
            center_id
        )
        VALUES
        (
            '{$invoice_id}',
            '{$invoice['supplier_id']}',
            '{$payment_date}',
            '{$amt}',
            '{$mode}',
            '{$remarks}',
            NOW(),
            '1',
            '1'
        )
        ");

    }

}

/* UPDATE INVOICE */

if($type == 'customer'){

    $db->query("
    UPDATE invoice
    SET
        paid_amount='{$new_paid}',
        due_amount='{$new_due}',
        payment_status='{$status}'
    WHERE id='{$invoice_id}'
    ");

}else{

    $db->query("
    UPDATE supplier_ledger
    SET
        paid_amount='{$new_paid}',
        balance_amount='{$new_due}',
        payment_status='{$status}'
    WHERE ledger_id='{$invoice_id}'
    ");

}

/* UPDATE CUSTOMER BALANCE */

if($type == 'customer'){

    $db->query("
    UPDATE customer_master
    SET balance = balance - {$total_amount}
    WHERE id='{$invoice['customer_id']}'
    ");

}else{

}

/* LEDGER ENTRY */

if($type == 'customer'){

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

}
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

<style>

body{
    background:#f4f7fb;
}

.report-card{
    background:#fff;
    border:none;
    border-radius:16px;
    box-shadow:0 8px 20px rgba(0,0,0,.06);
    margin-bottom:20px;
}

.report-card .panel-heading{
    background:#fff !important;
    border:none !important;
    padding:18px 22px 10px;
}

.report-card .panel-body{
    padding:18px;
}

.table{
    border-collapse:separate;
    border-spacing:0;
}

.table thead th{
    background:#111827 !important;
    color:#fff !important;
    border:none !important;
}

.table-responsive{
    border-radius:12px;
    overflow:hidden;
    border:1px solid #e5e7eb;
}

.search-box{
    height:40px;
    border-radius:10px;
}

/* Compact Modal Styling */
#paymentModal .modal-dialog {
    width: 100% !important;
    max-width: 580px; /* Width tight kar di */
    margin: 30px auto;
}

#paymentModal .modal-content {
    border-radius: 10px;
    border: none;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}

#paymentModal .modal-header {
    background: #111827;
    color: #fff;
    border-top-left-radius: 10px;
    border-top-right-radius: 10px;
    padding: 10px 15px;
}

#paymentModal .modal-header .close {
    color: #fff;
    opacity: 0.8;
}

.payment-modes-wrapper {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 8px 10px;
}

.payment-mode-item {
    display: flex;
    align-items: center;
    background: #fff;
    padding: 4px 8px;
    border: 1px solid #e5e7eb;
    border-radius: 5px;
    margin-bottom: 6px;
}

.payment-mode-item label {
    margin-bottom: 0;
    cursor: pointer;
    font-weight: 600;
    font-size: 12px;
    color: #374151;
    display: flex;
    align-items: center;
    gap: 6px;
}

</style>

<div class="row">

<div class="col-md-12">

</div>

</div>

<div class="row">

<div class="col-md-12">

<div class="report-card">

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

<div style="
background:#ffffff;
border:1px solid #e5e7eb;
border-left:5px solid #2563eb;
border-radius:10px;
padding:14px 18px;
margin-bottom:18px;
box-shadow:0 2px 8px rgba(0,0,0,.05);
">

<div style="
display:flex;
justify-content:space-between;
align-items:center;
">

<span style="
font-size:15px;
font-weight:600;
color:#374151;
">
Total Outstanding
</span>

<span style="
font-size:22px;
font-weight:700;
color:#2563eb;
">
₹ <?= number_format($total_due,2); ?>
</span>

</div>

</div>

<input type="text"
id="search"
class="form-control search-box"
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

<?= date('d/M/Y', strtotime($p['created_at'])); ?>

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
<div class="modal fade" id="paymentModal">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h5 class="modal-title" style="margin:0; font-weight:600; font-size:15px;">Add Payment</h5>
      </div>

      <form method="post">
        <div class="modal-body" style="padding: 12px 15px;">
          <input type="hidden" name="invoice_id" id="invoice_id">

          <!-- Top Details Row -->
          <div class="row" style="margin-left:-5px; margin-right:-5px;">
            <div class="col-xs-3" style="padding:0 5px;">
              <label style="font-size:11px; margin-bottom:2px;"><?= ($type=='customer') ? 'Invoice No' : 'GRN No' ?></label>
              <input type="text" id="invoice_no" class="form-control input-sm" style="font-size:12px; height:28px;" readonly>
            </div>

            <div class="col-xs-3" style="padding:0 5px;">
              <label style="font-size:11px; margin-bottom:2px;">Date</label>
              <input type="text" name="payment_date" class="form-control input-sm payment-datepicker" value="<?= date('d/M/Y'); ?>" style="font-size:12px; height:28px;" autocomplete="off" required>
            </div>

            <div class="col-xs-3" style="padding:0 5px;">
              <label style="font-size:11px; margin-bottom:2px;"><?= ($type=='customer') ? 'Customer' : 'Supplier' ?></label>
              <input type="text" id="customer_name" class="form-control input-sm" style="font-size:12px; height:28px;" readonly>
            </div>

            <div class="col-xs-3" style="padding:0 5px;">
              <label style="font-size:11px; margin-bottom:2px;">Due</label>
              <input type="text" id="due_amount" class="form-control input-sm" style="font-size:12px; height:28px; font-weight:bold; color:#dc2626;" readonly>
            </div>
          </div>

          <!-- Payment Modes Section -->
          <div class="form-group" style="margin-top:10px; margin-bottom:10px;">
            <label style="font-size:12px; margin-bottom:4px; font-weight:600;">Payment Mode(s)</label>
            <div class="payment-modes-wrapper">
              <div class="row" style="margin-left:-4px; margin-right:-4px;">
                <?php foreach($payment_modes as $mode): ?>
                <div class="col-xs-6" style="padding:0 4px;">
                  <div class="payment-mode-item">
                    <div style="flex: 1;">
                      <label>
                        <input type="checkbox" class="pay-check" data-target="mode_<?= $mode['id']; ?>" value="<?= $mode['mode_name']; ?>">
                        <?= strtoupper($mode['mode_name']); ?>
                      </label>
                    </div>
                    <div style="width: 85px;">
                      <input type="number" step="0.01" min="0" value="" name="amounts[<?= $mode['mode_name']; ?>]" id="mode_<?= $mode['id']; ?>" class="form-control input-sm" style="height:26px; padding:2px 6px; font-size:12px;" placeholder="0.00" disabled>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <!-- Bottom Remarks & Action Row -->
          <div class="row" style="margin-left:-5px; margin-right:-5px;">
            <div class="col-xs-8" style="padding:0 5px;">
              <input type="text" name="remarks" class="form-control input-sm" style="height:32px; font-size:12px;" placeholder="Remarks / Ref No">
            </div>

            <div class="col-xs-4" style="padding:0 5px;">
              <button type="submit" name="save_payment" class="btn btn-success btn-block btn-sm" style="font-weight:600; height:32px; line-height:1.2;">
                Save Payment
              </button>
            </div>
          </div>

        </div>
      </form>
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

    target.disabled = false;
    target.readOnly = false;
    target.value = due.toFixed(2);

    target.focus();
    target.select();

}else{

    target.disabled = true;
    target.value = "";

}

});

});

</script>


<?php include_once('layouts/footer.php'); ?>

<?php if($msg): ?>

<script>

document.addEventListener("DOMContentLoaded", function () {

<?php foreach($msg as $m): ?>

Swal.fire({

icon: "<?= ($m['type']=='s') ? 'success' : 'error'; ?>",

title: "<?= ($m['type']=='s') ? 'Success' : 'Error'; ?>",

text: "<?= addslashes($m['text']); ?>",

confirmButtonColor: "#28a745"

});

<?php endforeach; ?>

});

</script>
<?php endif; ?>

<script>
flatpickr(".payment-datepicker", {
    dateFormat: "d/M/Y",
    allowInput: false,
    disableMobile: true
});
</script>
