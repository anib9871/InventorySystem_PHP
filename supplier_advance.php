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
$amount = (float)$_POST['amount'];
$mode = $db->escape($_POST['payment_mode']);
$ref = $db->escape($_POST['reference_no']);
$payment_date = $_POST['payment_date'];

$formats = ['d/M/Y','d-m-Y','Y-m-d'];

foreach($formats as $format){
    $dt = DateTime::createFromFormat($format,$payment_date);
    if($dt){
        $payment_date = $dt->format('Y-m-d');
        break;
    }
}

$payment_date = $db->escape($payment_date);

if($supplier_id <= 0 || $amount <= 0){

$_SESSION['swal_error'] = 'Invalid Details';
redirect('supplier_advance.php');

}

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
'$payment_date',
0,
'$amount',
'-$amount',
1,
'ADVANCE',
NOW()
)

");

$ledger_id = $db->insert_id();

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
'$payment_date',
'$amount',
'$mode',
'$ref',
NOW()
)

");

$_SESSION['swal_success'] = 'Advance Added Successfully';
redirect('supplier_advance.php');

}

/* ================= UPDATE ADVANCE ================= */

if(isset($_POST['update_advance'])){

$ledger_id = (int)$_POST['ledger_id'];
$supplier_id = (int)$_POST['supplier_id'];
$amount = (float)$_POST['amount'];
$mode = $db->escape($_POST['payment_mode']);
$ref = $db->escape($_POST['reference_no']);
$payment_date = $_POST['payment_date'];

$formats = ['d/M/Y','d-m-Y','Y-m-d'];

foreach($formats as $format){
    $dt = DateTime::createFromFormat($format,$payment_date);
    if($dt){
        $payment_date = $dt->format('Y-m-d');
        break;
    }
}

$payment_date = $db->escape($payment_date);
$remarks = $db->escape($_POST['remarks']);

$db->query("
UPDATE supplier_ledger
SET
supplier_id='{$supplier_id}',
bill_date='{$payment_date}',
paid_amount='{$amount}',
balance_amount='-{$amount}'
WHERE ledger_id='{$ledger_id}'
");

$db->query("
UPDATE supplier_payment
SET
supplier_id='{$supplier_id}',
payment_date='{$payment_date}',
payment_amount='{$amount}',
payment_mode='{$mode}',
reference_no='{$ref}'
WHERE ledger_id='{$ledger_id}'
");

$_SESSION['swal_success'] = 'Advance Updated Successfully';
redirect('supplier_advance.php');

}

/* ================= EDIT FETCH ================= */

$edit_data = null;

if(isset($_GET['edit'])){

$ledger_id = (int)$_GET['edit'];

$edit = find_by_sql("

SELECT
sl.*,
sp.payment_mode,
sp.reference_no

FROM supplier_ledger sl

LEFT JOIN supplier_payment sp
ON sp.ledger_id = sl.ledger_id

WHERE sl.ledger_id = '{$ledger_id}'

LIMIT 1

");

if($edit){

$edit_data = $edit[0];

}

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

WHERE
    sl.entry_type='ADVANCE'
    AND sl.balance_amount < 0

ORDER BY sl.ledger_id DESC

");

    include_once('layouts/header.php');

    ?>

    <div class="row">

    <div class="col-md-12">

    </div>

    </div>

<div class="row">

<div class="col-md-12">

<div class="panel panel-default">

<div class="panel-heading text-center">

<strong>

<i class="fa fa-money text-primary"></i>

SUPPLIER ADVANCE

</strong>

</div>

<div class="panel-body">

<form method="post">

<div class="row">

<input type="hidden"
name="ledger_id"
value="<?= $edit_data['ledger_id'] ?? ''; ?>">

<div class="col-md-4">

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

<option value="<?= $s['id']; ?>"
<?= ($edit_data && $edit_data['supplier_id']==$s['id']) ? 'selected' : ''; ?>>

<?= $s['supplier_name']; ?>

</option>

<?php endforeach; ?>

</select>

</div>
</div>

<div class="col-md-4">

<div class="form-group">

<label>Payment Date</label>

<input
type="text"
name="payment_date"
id="payment_date"
class="form-control"
value="<?= isset($edit_data['bill_date']) ? date('d/M/Y', strtotime($edit_data['bill_date'])) : date('d/M/Y'); ?>"
autocomplete="off"
required>

</div>
</div>

<div class="col-md-4">

<div class="form-group">

<label>Advance Amount</label>

<input
type="number"
step="0.01"
min="0"
name="amount"
class="form-control"
value="<?= $edit_data['paid_amount'] ?? ''; ?>"
required>

</div>
</div>

<div class="col-md-4">

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

<option value="<?= $pm['mode_name']; ?>"
<?= ($edit_data && $edit_data['payment_mode']==$pm['mode_name']) ? 'selected' : ''; ?>>

<?= strtoupper($pm['mode_name']); ?>

</option>

<?php endforeach; ?>

</select>

</div>

</div>

<div class="col-md-4">

<div class="form-group">

<label>Reference No</label>

<input
type="text"
name="reference_no"
class="form-control"
value="<?= $edit_data['reference_no'] ?? ''; ?>">

</div>

</div>

<div class="col-md-12">

<div class="form-group">

<label>Remarks</label>

<textarea
name="remarks"
class="form-control"
rows="3"><?= $edit_data['remarks'] ?? ''; ?></textarea>

</div>
</div>

<div class="col-md-12 text-right">

<button
type="submit"
name="<?= $edit_data ? 'update_advance' : 'save_advance'; ?>"
class="btn btn-success"
style="min-width:180px;">

<?= $edit_data ? 'Update Advance' : 'Save Advance'; ?>

</button>

</div> <!-- button column -->

</div> <!-- row -->

</form>

</div>

</div>

</div>

<div class="row">

<div class="col-md-12">

<div class="panel panel-default">

<div class="panel-heading">

<strong style="font-size:22px;">

SUPPLIER ADVANCE HISTORY

</strong>

</div>

<div class="panel-body">

<div class="col-md-4" style="padding-left:0;">
    <div class="input-group">
        <span class="input-group-addon">
            <i class="fa fa-search"></i>
        </span>
        <input
            type="text"
            id="searchAdvance"
            class="form-control"
            placeholder="Search Supplier...">
    </div>
</div>

<div class="clearfix"></div>
<br>

<br>

<div class="table-responsive">

<table class="table table-bordered table-hover" style="width:100%; margin-bottom:0;">

<thead style="background:#1f2a44;color:#fff;">

<tr>

<th>#</th>
<th>Supplier</th>
<th>Amount</th>
<th>Mode</th>
<th>Reference</th>
<th>Date</th>
<th>Edit</th>

</tr>

</thead>

<tbody id="advanceTable">

<?php foreach($advances as $i => $a): ?>

<tr>

<td><?= $i+1; ?></td>

<td><?= $a['supplier_name']; ?></td>

<td>
    <span style="color:#008000;font-weight:600;">
        ₹ <?= number_format(abs($a['balance_amount']),2); ?>
    </span>
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
'd/M/Y',
strtotime($a['bill_date'])
); ?>

</td>

<td>

<a href="supplier_advance.php?edit=<?= $a['ledger_id']; ?>"
class="btn btn-warning btn-xs"
style="font-weight:600;">

Edit

</a>

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

<script>
document.addEventListener("DOMContentLoaded", function () {

    flatpickr("#payment_date", {
        dateFormat: "d/M/Y",
        allowInput: false,
        disableMobile: true
    });

});

<?php if(isset($_SESSION['swal_success'])): ?>

Swal.fire({
    icon: 'success',
    title: '<?php echo $_SESSION['swal_success']; ?>',
    showConfirmButton: false,
    timer: 2000,
    timerProgressBar: true
});

<?php unset($_SESSION['swal_success']); ?>
<?php endif; ?>


<?php if(isset($_SESSION['swal_error'])): ?>

Swal.fire({
    icon: 'error',
    title: 'Error',
    text: '<?php echo $_SESSION['swal_error']; ?>',
    confirmButtonText: 'OK',
    confirmButtonColor: '#d33'
});

<?php unset($_SESSION['swal_error']); ?>
<?php endif; ?>
</script>

<?php include_once('layouts/footer.php'); ?>
