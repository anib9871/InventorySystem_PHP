<?php
$page_title = 'GRN';
require_once('includes/load.php');
//page_require_level(2);

$products   = find_all('products');
$suppliers  = find_all('supplier_master');

$gst_list = find_by_sql("
  SELECT id, gst_name, gst_percent
  FROM gst_master
  WHERE status = 1
  ORDER BY gst_percent
");

$payment_modes = find_by_sql("
  SELECT * FROM payment_mode_master
  WHERE is_active = 1
  ORDER BY mode_name
");

$edit_mode = false;
$edit_bill = '';

if(isset($_GET['edit'])){

    $edit_mode = true;

    $edit_bill = urldecode(
$db->escape($_GET['edit'])
);

}


/* ================= SAVE GRN ================= */

if (isset($_POST['save_grn']) || isset($_POST['update_grn'])) {

  if (empty($_POST['items_json'])) {
    $session->msg("d", "Add at least one item");
    redirect('grn.php');
  }

  $items = json_decode($_POST['items_json'], true);
  if (!is_array($items) || count($items) == 0) {
    $session->msg("d", "Invalid items");
    redirect('grn.php');
  }

  $supplier_id  = (int)$_POST['supplier_id'];
  $bill_no      = $_POST['bill_no'];
$bill_date = $_POST['bill_date'];

$formats = ['d/M/Y', 'd-m-Y', 'Y-m-d'];

foreach ($formats as $format) {
    $dt = DateTime::createFromFormat($format, $bill_date);
    if ($dt instanceof DateTime) {
        $bill_date = $dt->format('Y-m-d');
        break;
    }
}
  $payment_mode = $_POST['payment_mode'] ?? '';
  $payments = json_decode($_POST['payments_json'], true);
  $total_paid = 0;
  $used_advance =
(float)($_POST['used_advance'] ?? 0);
  $comments     = $_POST['comments'] ?? '';

  $entry_date  = date('Y-m-d H:i:s');
  $grand_total = 0;

  $db->query("START TRANSACTION");

  try {

  if(isset($_POST['update_grn'])){

$old_bill_no = $db->escape($_POST['old_bill_no']);

$old = find_by_sql("
SELECT * FROM transaction_master
WHERE bill_indent_no = '$old_bill_no'
");

foreach($old as $o){

update_product_qty(
-($o['quantity'] + $o['free_qty']),
$o['product_id']
);

}

$db->query("
DELETE FROM inventory
WHERE transaction_id IN
(
SELECT transaction_id FROM transaction_master
WHERE bill_indent_no = '$old_bill_no'
)
");

$db->query("
DELETE FROM transaction_master
WHERE bill_indent_no = '$old_bill_no'
");

$db->query("
DELETE FROM shipping
WHERE bill_no = '$old_bill_no'
");

$db->query("
DELETE FROM supplier_payment
WHERE ledger_id IN
(
SELECT ledger_id FROM supplier_ledger
WHERE bill_no = '$old_bill_no'
)
");

$db->query("
DELETE FROM supplier_ledger
WHERE bill_no = '$old_bill_no'
");
}

    foreach ($items as $it) {

      $qty   = (float)$it['qty'];
      $free  = (float)$it['free_qty'];
      $rate  = (float)$it['rate'];
      $gstp  = (float)$it['gst_percent'];
      $disc  = (float)$it['discount'];
      $misc  = (float)$it['misc'];
      $mrp   = (float)$it['mrp'];

$buy_type = $it['buy_type'];

if($buy_type == 'inclusive'){

    $total_amount = ($qty * $rate) - $disc + $misc;

    $base_amount = $total_amount / (1 + ($gstp / 100));
    $gst_amount  = $total_amount - $base_amount;

    $net_amount = $total_amount;

} else {

    $base_amount = ($qty * $rate) - $disc + $misc;

    $gst_amount = ($base_amount * $gstp) / 100;

    $net_amount = $base_amount + $gst_amount;
}

      $grand_total += $net_amount;

      /* ===== RATE MASTER ===== */

            $existing_rate = find_by_sql("
            SELECT id
            FROM rate_master
            WHERE product_id = '{$it['product_id']}'
            AND rate = '$rate'
            AND gst_id = '{$it['gst_id']}'
            AND mrp = '$mrp'
            LIMIT 1
            ");

if($existing_rate){

    $rate_id = $existing_rate[0]['id'];

} else {

    $db->query("
    INSERT INTO rate_master
    (
        product_id,
        rate,
        mrp,
        gst_id,
        price_date,
        is_active,
        created_at
    )
    VALUES
    (
        '{$it['product_id']}',
        '$rate',
        '$mrp',
        '{$it['gst_id']}',
        CURDATE(),
        1,
        NOW()
    )
    ");

    $rate_id = $db->insert_id();
}

      /* ===== TRANSACTION MASTER ===== */
      $db->query("
        INSERT INTO transaction_master
        (
          product_id, supplier_id, bill_indent_no,
          entry_date, bill_indent_date,
          quantity, free_qty, unit,
          rate_id, gst_id,
          unit_price, gst_amount,
          discount_amount, misc_amount,
          net_price, mrp,
          transaction_type, status,
          payment_status, payment_mode,
          amount_received, balance_amount,
          from_dept, to_dept, comments,
          created_at
        )
        VALUES
        (
          '{$it['product_id']}',
          '$supplier_id',
          '$bill_no',
          '$entry_date',
          '$bill_date',
          '$qty',
          '$free',
          'PCS',
          '$rate_id',
          '{$it['gst_id']}',
          '$rate',
          '$gst_amount',
          '$disc',
          '$misc',
          '$net_amount',
          '$mrp',
          1,
          1,
          0,
          '$payment_mode',
          0,
          '$net_amount',
          'SUPPLIER',
          'STORE',
          '$comments',
          NOW()
        )
      ");

      $transaction_id = $db->insert_id();

      /* ===== INVENTORY ===== */
      $db->query("
        INSERT INTO inventory
        (transaction_id, product_id, quantity, free_qty,
         fin_year, status, origin_dept, updated_at)
        VALUES
        ('$transaction_id', '{$it['product_id']}',
         '$qty', '$free', '2025-26', 1, 'SUPPLIER', NOW())
      ");

      update_product_qty($qty + $free, $it['product_id']);



        /* ===== UPDATE PRODUCT MASTER (LATEST BUY PRICE) ===== */

$db->query("
  UPDATE products
  SET 
    buy_price = '$rate',
    gst_id    = '{$it['gst_id']}',
    buy_type  = '{$buy_type}'
  WHERE id = '{$it['product_id']}'
");
    }

  /* ================= INSERT SHIPPING HERE ================= */

$charges = json_decode($_POST['charges_json'], true);

if (is_array($charges)) {
  foreach ($charges as $c) {

    $shipping_type_id = (int)$c['shipping_type_id'];
    $gst_id = (int)$c['gst_id'];
    $amount = (float)$c['amount'];
    $gst_percent = (float)$c['gst_percent'];
    $gst_type = $c['gst_type'];
    $gst_amount = (float)$c['gst_amount'];
    $total = (float)$c['total'];

    $db->query("
      INSERT INTO shipping
      (supplier_id, bill_no, shipping_type_id,
      gst_id,
      amount, gst_percent, gst_type,
      gst_amount, total_amount, created_at)
      VALUES
      ('$supplier_id', '$bill_no', '$shipping_type_id',
      '$gst_id',
      '$amount', '$gst_percent', '$gst_type',
      '$gst_amount', '$total', NOW())
    ");

    $grand_total += $total;
  }
}

$round_off = isset($_POST['round_off']) ? (int)$_POST['round_off'] : 0;


if ($round_off == 1) {
    $grand_total = round($grand_total);
} else {
    $grand_total = round($grand_total, 2);
}

if (is_array($payments)) {

if (is_array($payments)) {

foreach ($payments as $p){

$total_paid += (float)$p['amount'];

}

}

$total_paid += $used_advance;

}


    /* ===== SUPPLIER LEDGER ===== */
    $db->query("
      INSERT INTO supplier_ledger
      (supplier_id, bill_no, bill_date,
       bill_amount, paid_amount, balance_amount,
       payment_status, created_at)
      VALUES
      (
        '$supplier_id',
        '$bill_no',
        '$bill_date',
        '".round($grand_total,2)."',
        '$total_paid',
        '".max(0, round($grand_total - $total_paid,2))."',
        '".($total_paid >= $grand_total ? 1 : 0)."',
        NOW()
      )
    ");
    $ledger_id = $db->insert_id();

    /* ===== SUPPLIER PAYMENT ===== */
if (is_array($payments)) {

foreach ($payments as $p){

$mode = $p['mode'];
$amount = $p['amount'];

$utr = $p['utr'] ?? '';



$db->query("
INSERT INTO supplier_payment
(ledger_id, supplier_id, payment_date,
payment_amount, payment_mode, reference_no, created_at)

VALUES
('$ledger_id','$supplier_id',CURDATE(),
'$amount','$mode','$utr',NOW())
");

}

}

if($used_advance > 0){

$remaining = $used_advance;

$advances = find_by_sql("

SELECT ledger_id,balance_amount

FROM supplier_ledger

WHERE supplier_id = '$supplier_id'

AND balance_amount < 0

ORDER BY ledger_id ASC

");

foreach($advances as $adv){

if($remaining <= 0){
break;
}

$currentAdvance = abs($adv['balance_amount']);

if($remaining >= $currentAdvance){

$db->query("
UPDATE supplier_ledger
SET balance_amount = 0
WHERE ledger_id = '{$adv['ledger_id']}'
");

$remaining -= $currentAdvance;

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
    '$currentAdvance',
    'ADVANCE',
    'Advance Adjusted',
    NOW()
)");

}else{

$newBalance = -($currentAdvance - $remaining);

$db->query("
UPDATE supplier_ledger
SET balance_amount = '$newBalance'
WHERE ledger_id = '{$adv['ledger_id']}'
");

$adjustAmount = $remaining;

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
    '$adjustAmount',
    'ADVANCE',
    'Advance Adjusted',
    NOW()
)");

$remaining = 0;
}

}

}


$db->query("COMMIT");

if(isset($_POST['update_grn'])){

    redirect('grn.php?updated=1');

} else {

    redirect('grn.php?created=1');

}

} catch (Exception $e) {

    $db->query("ROLLBACK");

    die($e->getMessage());

}

  redirect('grn.php');
}

$edit_items = [];

$edit_charges = [];

if($edit_mode){

$edit_items = find_by_sql("

SELECT 
tm.*,
p.name,
p.hsn_code,
COALESCE(p.buy_type,'exclusive') as buy_type,
COALESCE(g.gst_percent,0) as gst_percent

FROM transaction_master tm

LEFT JOIN products p
ON p.id = tm.product_id

LEFT JOIN gst_master g
ON g.id = tm.gst_id

WHERE tm.bill_indent_no = '{$edit_bill}'

");

$edit_charges = find_by_sql("

SELECT
s.*,
stm.type_name

FROM shipping s

LEFT JOIN shipping_type_master stm
ON stm.id = s.shipping_type_id

WHERE s.bill_no = '{$edit_bill}'

");

$edit_payments = find_by_sql("

SELECT *
FROM supplier_payment sp

LEFT JOIN supplier_ledger sl
ON sl.ledger_id = sp.ledger_id

WHERE sl.bill_no = '{$edit_bill}'

");

$grn_info = [];

if($edit_mode){

$grn_info = find_by_sql("
SELECT *
FROM transaction_master
WHERE bill_indent_no = '{$edit_bill}'
LIMIT 1
");

$grn_info = $grn_info ? $grn_info[0] : [];

}
}

include_once('layouts/header.php');
?>

<style>

#itemEntryBox .form-control{
height:26px !important;
padding:2px 6px !important;
font-size:11px !important;
}

#itemEntryBox label{
font-size:11px !important;
margin-bottom:1px !important;
font-weight:600;
}

#itemEntryBox .row{
margin-bottom:2px !important;
}

.panel-heading{
padding:8px 12px !important;
font-size:14px !important;
}

#previousRateBox{
padding:5px !important;
font-size:11px !important;
margin-top:5px !important;
}

#itemBody tr{
height:42px;
}

.table thead th{
position:sticky;
top:0;
background:#f5f5f5;
z-index:99;
}

#grnItemsTable thead th{
    background:#0B1736 !important;
    color:#fff !important;
    border-color:#0B1736 !important;
}

#grnItemsTable td{
    font-size:10px !important;
    padding:2px 4px !important;
    vertical-align:middle;
}

#grnItemsTable input,
#grnItemsTable select{
    height:22px !important;
    font-size:10px !important;
    padding:1px 4px !important;
}

#grnItemsTable .btn{
    height:20px !important;
    padding:0 5px !important;
    font-size:9px !important;
    line-height:18px !important;
}

.panel-heading strong{
    font-size:13px;
}

.form-col-1{
    width:100px;
    display:inline-block;
    vertical-align:top;
    margin-right:8px;
}

.form-col-2{
    width:200px;
    display:inline-block;
    vertical-align:top;
    margin-right:8px;
}

.form-col-3{
    width:250px;
    display:inline-block;
    vertical-align:top;
    margin-right:8px;
}

.form-col-80{
    width:80px;
    display:inline-block;
    vertical-align:top;
    margin-right:8px;
}

.form-col-120{
    width:120px;
    display:inline-block;
    vertical-align:top;
    margin-right:8px;
}

.item-row{
    display:flex;
    flex-wrap:wrap;
    align-items:flex-end;
    gap:10px;
    padding-left:15px;
}

.form-col-supplier{width:210px;}
.form-col-bill{width:110px;}
.form-col-date{width:110px;}
.form-col-product{width:220px;}
.form-col-small{width:80px;}
.form-col-gst{width:90px;}
.form-col-gsttype{width:120px;}

</style>

<?php if(isset($_GET['created'])){ ?>


<script>
Swal.fire({
    icon: 'success',
    title: 'Success',
    text: 'GRN Created Successfully',
    showConfirmButton: false,
    timer: 2000
});
</script>
<?php } ?>

<?php if(isset($_GET['updated'])){ ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Success',
    text: 'GRN Updated Successfully',
    showConfirmButton: false,
    timer: 2000
});
</script>
<?php } ?>

<!-- ================= UI ================= -->

<div class="row">
  <div class="col-md-12"><?php echo display_msg($msg); ?></div>
</div>

<div class="row">

<!-- LEFT : ITEM ENTRY -->
<div class="col-md-12">
<div class="panel panel-default">
<div class="panel-heading"><strong>GRN</strong></div>
<div class="panel-body" id="itemEntryBox">

<input type="hidden" id="product">
<input type="hidden" id="hsn_code">
<input type="hidden" id="sac_code">

<!-- Row 1 -->
<div class="item-row" style="padding-left:20px;">

<!-- Supplier -->
<div class="form-col-supplier">
<label>Supplier</label>
<select
name="supplier_id"
id="supplier_id"
form="grnForm"
class="form-control"
required>

<option value="">Select Supplier</option>

<?php foreach ($suppliers as $s) { ?>

<option value="<?= $s['id']; ?>"
<?php
if($edit_mode && $grn_info['supplier_id'] == $s['id']){
echo 'selected';
}
?>>

<?= $s['supplier_name']; ?>

</option>

<?php } ?>

</select>
</div>


<!-- Bill No -->
<div class="form-col-bill">

<label>Bill No</label>

<input
type="text"
name="bill_no"
value="<?= $edit_mode ? $grn_info['bill_indent_no'] : ''; ?>"
class="form-control"
form="grnForm"
required>

</div>


<!-- Bill Date -->
<div class="form-col-date">

<label>Bill Date</label>

<input
type="text"
name="bill_date"
id="bill_date"
value="<?= $edit_mode
? date('d/M/Y', strtotime($grn_info['bill_indent_date']))
: date('d/M/Y'); ?>"
class="form-control"
form="grnForm"
required>

</div>

<div id="previousRateBox"
style="
display:none;
flex:1;
margin-left:15px;
padding:6px 10px;
background:#f8f9fa;
border-left:4px solid #2196F3;
border-radius:4px;
font-size:11px;
height:30px;
line-height:18px;
white-space:nowrap;
overflow:hidden;
text-overflow:ellipsis;
align-items:center;
">

</div>

</div>


<div class="item-row" style="margin-top:4px;padding-left:20px;">

<div class="form-col-product">

<label>Product</label>

<div class="input-group">

<input
type="text"
id="product_name"
class="form-control"
placeholder="Select Product"
readonly>

<span class="input-group-btn">

<button
type="button"
class="btn btn-primary"
onclick="openProductModal()"
style="
height:26px;
padding:2px 8px;
font-size:11px;
line-height:18px;
min-width:60px;
">
Choose
</button>
</span>

</div>

</div>

<div class="form-col-small">

<label>Qty</label>

<input
id="qty"
type="number"
class="form-control"
oninput="calculateDiscount()">

</div>

<div class="form-col-small">

<label>Free Qty</label>

<input
id="free_qty"
type="number"
class="form-control">

</div>

<div style="
display:flex;
align-items:flex-end;
gap:6px;
margin-left:10px;
flex-shrink:0;
">

<button
type="button"
onclick="addItem()"
class="btn btn-success"
style="height:30px;padding:3px 12px;font-size:12px;">
Add Item
</button>

<button
type="button"
onclick="cancelEditItem()"
class="btn btn-danger"
style="height:30px;padding:3px 12px;font-size:12px;">
Cancel
</button>

<button
type="button"
class="btn btn-primary btn-sm"
data-toggle="modal"
data-target="#shippingModal"
style="height:30px;padding:3px 10px;font-size:12px;">
<i class="fa fa-plus"></i> Shipping
</button>

<div
id="shippingSummary"
style="
display:none;
width:220px;
height:30px;
padding:5px 8px;
background:#f8f9fa;
border-left:3px solid #2196F3;
font-size:11px;
line-height:20px;
white-space:nowrap;
overflow:hidden;
text-overflow:ellipsis;
">
</div>

</div>

</div>




<!-- Row 3 -->
<div class="item-row" style="margin-top:10px;padding-left:20px;">

<!-- Rate -->
<div class="form-col-small">

<label>Rate</label>

<input
id="rate"
type="number"
step="any"
min="0"
class="form-control"
oninput="calculateDiscount()"
placeholder="Rate">

</div>

<!-- Disc % -->
<div class="form-col-small">

<label>Disc %</label>

<input
id="disc_percent"
type="number"
step="any"
class="form-control"
oninput="calculateDiscount()"
placeholder="%">

</div>

<!-- Disc Amt -->
<div class="form-col-small">

<label>Disc Amt</label>

<input
id="discount"
type="number"
step="any"
class="form-control"
oninput="calculateDiscountFromAmount()"
placeholder="Amount">

</div>

<!-- Misc -->
<div class="form-col-small">

<label>Misc</label>

<input
id="misc"
type="number"
step="any"
class="form-control"
placeholder="Misc">

</div>

<!-- MRP -->
<div class="form-col-small">

<label>MRP</label>

<input
id="mrp"
type="number"
step="any"
min="0"
class="form-control"
placeholder="MRP">

</div>

<!-- GST -->
<div class="form-col-gst">

<label>GST</label>

<select
id="gst"
class="form-control">

<option value="">Select GST</option>

<?php foreach ($gst_list as $g) { ?>

<option
value="<?= $g['id']; ?>"
data-gst="<?= $g['gst_percent']; ?>">

<?= $g['gst_name']; ?>
(<?= $g['gst_percent']; ?>%)

</option>

<?php } ?>

</select>

</div>

<!-- GST Type -->
<div class="form-col-gsttype">

<label>GST Type</label>

<select
id="buy_type"
class="form-control">

<option value="exclusive">
GST Exclusive
</option>

<option value="inclusive">
GST Inclusive
</option>

</select>

</div>

<!-- Net Amt -->
<div class="form-col-small">

<label>Net Amt</label>

<input
id="net_amount"
type="number"
class="form-control"
readonly>

</div>

</div>
<hr style="margin:8px 0;">

<!-- RIGHT SIDE -->
<!-- RIGHT : ITEM LIST + PAYMENT -->
<div class="col-md-12">
<form method="post" id="grnForm">

<input type="hidden" name="round_off" id="round_off" value="0">

<input type="hidden" name="charges_json" id="charges_json">

<?php if($edit_mode){ ?>

<input type="hidden"
name="old_bill_no"
value="<?= $edit_bill; ?>">

<?php } ?>

<input type="hidden" name="items_json" id="items_json">

<div class="panel panel-default" style="border-top:none;">

<div class="panel-body" style="padding-top:0;">

<div style="
max-height:170px;
overflow-y:auto;
overflow-x:auto;
border:1px solid #ddd;
">

<table id="grnItemsTable" class="table table-bordered table-striped" style="margin-bottom:0;">
<thead style="
position:sticky;
top:0;
background:#0B1736;
color:#fff;
z-index:10;
font-size:11px;
">

<tr>
<th style="padding:5px 4px;">#</th>
<th style="padding:5px 4px;">Product</th>
<th style="padding:5px 4px;">HSN</th>
<th style="padding:5px 4px;">Qty</th>
<th style="padding:5px 4px;">Rate</th>
<th style="padding:5px 4px;">Total Amt</th>
<th style="padding:5px 4px;">Disc %</th>
<th style="padding:5px 4px;">Disc Amt</th>
<th style="padding:5px 4px;">Net Amt</th>
<th style="padding:5px 4px;">GST %</th>
<th style="padding:5px 4px;">GST Amt</th>
<th style="padding:5px 4px;">Net Total</th>
<th style="padding:5px 4px;">Action</th>
</tr>

</thead>

<tbody id="itemBody"></tbody>

</table>

</div>

<hr>

<!-- Hidden Controls (JS ke liye) -->

<div style="display:none;">

<select id="charge_type" class="form-control">

<option value="">Select Type</option>

<?php
$shipping_types = find_by_sql("
SELECT * FROM shipping_type_master
WHERE is_active = 1
");

foreach($shipping_types as $st){ ?>
<option value="<?= $st['id']; ?>">
<?= $st['type_name']; ?>
</option>
<?php } ?>

</select>

<input
type="number"
id="charge_amount"
step="1"
min="0"
class="form-control">

<select id="charge_gst_type" class="form-control">
<option value="EXCLUSIVE">Exclusive</option>
<option value="INCLUSIVE">Inclusive</option>
</select>

<select id="charge_gst_id" class="form-control">

<option value="">Select GST</option>

<?php foreach ($gst_list as $g) { ?>

<option value="<?= $g['id']; ?>"
data-gst="<?= $g['gst_percent']; ?>">

<?= $g['gst_name']; ?>
(<?= $g['gst_percent']; ?>%)

</option>

<?php } ?>

</select>

<button
type="button"
onclick="addCharge()"
class="btn btn-warning">

Add

</button>

</div>

<div id="chargeBody"></div>

<?php

$advance_amount = 0;

?>

<div
class="alert alert-info"
id="advanceSection"
style="
display:none;
padding:8px 12px;
width:260px;
min-height:auto;
margin-bottom:8px;
font-size:12px;
">

<strong style="font-size:13px;">

Available Advance :
₹ <span id="advanceAmount">

<br>

<strong style="font-size:13px;">
Balance Advance :
₹ <span id="balanceAdvance">0.00</span>
</strong>

<?= number_format($advance_amount,2); ?>

</span>

</strong>

<br>

<div id="useAdvanceWrapper">

<label>
<input type="checkbox" id="useAdvance">
Use Advance
</label>

</div>

<input
type="number"
id="advanceInput"
class="form-control"
style="margin-top:8px;display:none;"
placeholder="Enter Advance Amount"
min="0"
step="1">

</div>

<div
style="
display:flex;
align-items:center;
justify-content:flex-start;
margin-top:12px;
margin-left:200px;
gap:20px;
">

</div>


</br>
</br>
</br>
<div>

<label style="
font-size:13px;
font-weight:600;
margin-bottom:5px;
display:block;
margin-top:-60px;
">
Comments
</label>

<textarea
name="comments"
class="form-control"
placeholder="Enter Remarks / Comments..."
rows="4"
style="
width:280px;
height:100px;
padding:12px;
font-size:13px;
border-radius:8px;
resize:none;
box-sizing:border-box;
"><?= $edit_mode ? $grn_info['comments'] : ''; ?></textarea>

</div>


<br>
<br>

<div class="row" style="margin-top:-150px;">

    <div class="col-md-6 col-md-offset-6">


<div style="
display:flex;
justify-content:space-between;
align-items:center;
width:340px;
margin-bottom:8px;
">

<div>
    <strong style="font-size:14px;">Payments</strong>
</div>

<div style="
display:flex;
align-items:center;
gap:8px;
">

<label style="
margin:0;
font-size:11px;
display:flex;
align-items:center;
gap:4px;
">

<input
type="checkbox"
id="roundOffToggle"
onchange="updateGrandTotal()">

Round Off

</label>

<div style="
font-size:13px;
font-weight:bold;
">

Total ₹
<span id="grandTotal">0.00</span>

</div>

<?php if($edit_mode){ ?>

<button
name="update_grn"
class="btn btn-primary btn-sm">
Update GRN
</button>

<?php } else { ?>

<button
name="save_grn"
class="btn btn-success btn-sm">
Create GRN
</button>

<?php } ?>

</div>

</div>

<div style="
width:340px;
border:1px solid #ddd;
border-radius:12px;
padding:8px;
background:#f8f8f8;
max-height:220px;
overflow:hidden;
">

<div class="row" style="
margin-left:-8px;
margin-right:-8px;
margin-top:10px;
max-height:140px;
overflow-y:auto;
overflow-x:hidden;
padding-right:5px;
">

<?php foreach($payment_modes as $pm){ ?>

<div class="col-md-12" style="
margin-bottom:8px;
padding-left:0;
padding-right:0;
">

<div style="
border-bottom:1px solid #eee;
height:30px;
padding:2px 6px;
background:none;
display:flex;
align-items:center;
gap:5px;
box-sizing:border-box;
">

<div style="
width:120px;
display:flex;
align-items:flex-start;
gap:6px;
padding-left:10px;
">

<input
type="checkbox"
class="pay-check"
onchange="togglePaymentInput(this)"
value="<?= $pm['mode_name']; ?>"
id="pay_<?= $pm['id']; ?>"
>

<label
for="pay_<?= $pm['id']; ?>"
style="
margin:0;
font-weight:600;
font-size:10px;
">

<?= strtoupper($pm['mode_name']); ?>

</label>

</div>

<input
type="number"
step="any"
min="0"
class="form-control pay-amount"
style="
display:none;
height:26px;
font-size:11px;
width:70px;
margin-left:5px;
margin-top:0;
"
data-mode="<?= $pm['mode_name']; ?>"
placeholder="Enter Amount"
value=""
>

<input
type="text"
class="form-control pay-utr"
style="
display:none;
height:26px;
font-size:11px;
width:90px;
margin-top:0;
margin-left:5px;
"
data-mode="<?= $pm['mode_name']; ?>"
placeholder="Enter UTR No"
>
</div>

</div>

<?php } ?>
</div>

</div>

<input type="hidden" name="payments_json" id="payments_json">
</div>

</div>

</div>

<input
type="hidden"
name="used_advance"
id="used_advance"
value="0">

<div style="
display:flex;
justify-content:space-between;
align-items:flex-end;
padding:15px 20px;
">





</div>

<div class="modal fade" id="shippingModal">

<div class="modal-dialog">

<div class="modal-content">

<div class="modal-header">

<button
type="button"
class="close"
data-dismiss="modal">

&times;

</button>

<h4 class="modal-title">
Add Shipping Charge
</h4>

</div>

<div class="modal-body">

<label>Charge Type</label>

<select
id="modal_charge_type"
class="form-control">

<option value="">Select Type</option>

<?php foreach($shipping_types as $st){ ?>
<option value="<?= $st['id']; ?>">
<?= $st['type_name']; ?>
</option>
<?php } ?>

</select>

<br>

<label>Amount</label>

<input
    type="text"
    inputmode="decimal"
    id="modal_charge_amount"
    class="form-control">

<br>

<label>GST Type</label>

<select
id="modal_charge_gst_type"
class="form-control">

<option value="EXCLUSIVE">
Exclusive
</option>

<option value="INCLUSIVE">
Inclusive
</option>

</select>

<br>

<label>GST</label>

<select
id="modal_charge_gst_id"
class="form-control">

<option value="">Select GST</option>

<?php foreach ($gst_list as $g) { ?>

<option value="<?= $g['id']; ?>"
data-gst="<?= $g['gst_percent']; ?>">

<?= $g['gst_name']; ?>
(<?= $g['gst_percent']; ?>%)

</option>

<?php } ?>

</select>

</div>

<div class="modal-footer">

<button
type="button"
class="btn btn-success"
onclick="saveShippingModal()">

Add Charge

</button>

</div>

</div>

</div>

</div>

</form>
</div>

<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>

<?php if($edit_mode){ ?>

items = <?= json_encode($edit_items); ?>;

items.forEach((it,index)=>{

it.sno = index + 1;

it.qty = parseFloat(it.quantity || 0);

it.free_qty = parseFloat(it.free_qty || 0);

it.rate = parseFloat(it.unit_price || 0);

it.discount = parseFloat(it.discount_amount || 0);

it.misc = parseFloat(it.misc_amount || 0);

it.mrp = parseFloat(it.mrp || 0);

it.gst_id = parseInt(it.gst_id || 0);

it.gst_percent =
parseFloat(it.gst_percent || 0);

it.gst_amount =
parseFloat(it.gst_amount || 0);

it.base_amount =
parseFloat(it.net_price || 0)
-
parseFloat(it.gst_amount || 0);

it.total =
parseFloat(it.net_price || 0);

});

charges = <?= json_encode($edit_charges); ?>;

charges.forEach(c => {

    c.amount = parseFloat(c.amount || 0);

    c.gst_percent =
    parseFloat(c.gst_percent || 0);

    c.gst_amount =
    parseFloat(c.gst_amount || 0);

    c.total =
    parseFloat(c.total_amount || 0);

});

let editPayments =
<?= json_encode($edit_payments); ?>;

document.addEventListener("DOMContentLoaded", function(){

renderItems();

renderCharges();

document.getElementById("items_json").value =
JSON.stringify(items);

document.getElementById("charges_json").value =
JSON.stringify(charges);



editPayments.forEach(p => {

let mode = p.payment_mode;

let checkbox = document.querySelector(
'.pay-check[value="' + mode + '"]'
);

let amountInput = document.querySelector(
'.pay-amount[data-mode="' + mode + '"]'
);

let utrInput = document.querySelector(
'.pay-utr[data-mode="' + mode + '"]'
);

if(checkbox){

checkbox.checked = true;

togglePaymentInput(checkbox);

}

if(amountInput){

amountInput.value =
parseFloat(p.payment_amount).toFixed(2);

}

if(
utrInput &&
p.utr_no &&
mode.toLowerCase() != 'cash'
){

utrInput.value = p.utr_no;

utrInput.style.display = 'block';

}

});

});

<?php } ?>

</script>
<!-- ================= SCRIPT ================= -->

<script>

if(typeof items === 'undefined'){
    var items = [];
}

if(typeof charges === 'undefined'){
    var charges = [];
}

let sno = items.length + 1;

let payments = [];

function collectPayments(){

payments = [];

document.querySelectorAll(".pay-check").forEach(check => {

if(check.checked){

let mode = check.value;

let amountInput = document.querySelector(
'.pay-amount[data-mode="' + mode + '"]'
);

let amount = parseFloat(amountInput.value) || 0;

let utrInput = document.querySelector(
'.pay-utr[data-mode="' + mode + '"]'
);

let utr = utrInput ? utrInput.value : '';

if(amount > 0){

payments.push({
mode: mode,
amount: amount,
utr: utr
});

}

}

});

document.getElementById("payments_json").value =
JSON.stringify(payments);

}

function calculateDiscount(){

let qty =
parseFloat(document.getElementById("qty").value) || 0;

let rate =
parseFloat(document.getElementById("rate").value) || 0;

let discPercent =
parseFloat(document.getElementById("disc_percent").value) || 0;

let totalAmt = qty * rate;

let discAmt =
(totalAmt * discPercent) / 100;

let netAmt =
totalAmt - discAmt;

document.getElementById("discount").value =
Math.round(discAmt);

document.getElementById("net_amount").value =
Math.round(netAmt);

}

function calculateDiscountFromAmount(){

let qty =
parseFloat(document.getElementById("qty").value) || 0;

let rate =
parseFloat(document.getElementById("rate").value) || 0;

let totalAmt = qty * rate;

let discAmt =
parseFloat(document.getElementById("discount").value) || 0;

let discPercent = 0;

if(totalAmt > 0){

discPercent =
(discAmt * 100) / totalAmt;

}

document.getElementById("disc_percent").value =
Math.round(discPercent);

let netAmt =
totalAmt - discAmt;

document.getElementById("net_amount").value =
Math.round(netAmt);

}

function addItem() {

  let pid = document.getElementById("product").value;
  let pname = document.getElementById("product_name").value;

  let qty   = parseFloat(document.getElementById("qty").value) || 0;
  let free  = parseFloat(document.getElementById("free_qty").value) || 0;
  let rate  = parseFloat(document.getElementById("rate").value) || 0;
  let disc_percent =parseFloat(document.getElementById("disc_percent").value) || 0;
  let disc  = parseFloat(document.getElementById("discount").value) || 0;
  let misc  = parseFloat(document.getElementById("misc").value) || 0;
  let mrp   = parseFloat(document.getElementById("mrp").value) || 0;

  let gstSel = document.getElementById("gst");
  let gst_id = gstSel.value;
  let gstp   = parseFloat(gstSel.options[gstSel.selectedIndex]?.dataset.gst || 0);

let errors = [];

// NEW VALIDATION
if(document.getElementById("supplier_id").value == ""){
    errors.push("Supplier");
}

if(document.querySelector('[name="bill_no"]').value.trim() == ""){
    errors.push("Bill / GRN No");
}

if(!pid){
    errors.push("Product");
}

if(qty <= 0){
    errors.push("Quantity");
}

if(rate < 0){
    errors.push("Rate");
}

if(!gst_id){
    errors.push("GST");
}

if(mrp < 0){
    errors.push("MRP");
}

if(errors.length > 0){

Swal.fire({
    icon: 'warning',
    title: 'Required Fields',
    html: errors.join("<br>")
});

    return;
}

let buyType = document.getElementById("buy_type").value;

let total_amt = qty * rate;

if(disc_percent > 0){

disc =
Math.round(
(total_amt * disc_percent) / 100
);

}

let base = 0;
let gst = 0;
let total = 0;

if (buyType === "inclusive") {

    total = (qty * rate) - disc + misc;

    base = total / (1 + (gstp / 100));
    gst = total - base;

} else {

    base = (qty * rate) - disc + misc;

    gst = (base * gstp) / 100;

    total = base + gst;
}

backupItem = null;

items.push({
  sno: sno++,
  product_id: pid,
  name: pname,
  hsn_code: document.getElementById("hsn_code").value,
  qty: qty,
  free_qty: free,
  rate: rate,
  gst_id: gst_id,
  gst_percent: gstp,
  buy_type: buyType,
  base_amount: base,
  gst_amount: gst,
  disc_percent: disc_percent,
  discount: disc,
  misc: misc,
  mrp: mrp,
  total: total
});

renderItems();

// CLEAR LEFT PANEL
document.getElementById("product").value = "";
document.getElementById("product_name").value = "";
document.getElementById("hsn_code").value = "";

document.getElementById("qty").value = "";
document.getElementById("free_qty").value = "";
document.getElementById("rate").value = "";
document.getElementById("discount").value = "";
document.getElementById("misc").value = "";
document.getElementById("mrp").value = "";

document.getElementById("gst").value = "";

document.getElementById("previousRateBox").style.display = "none";
document.getElementById("previousRateBox").innerHTML = "";
}

function renderItems() {

  let tb = document.getElementById("itemBody");
  tb.innerHTML = "";
  let grand = 0;

  items.forEach((it, i) => {

    it.sno = i + 1;
    grand += it.total;
    let base = it.base_amount;
let gst_amt = it.gst_amount;
let total = it.total;

tb.innerHTML += `
<tr>
<td>${it.sno}</td>
<td>${it.name}</td>
<td>${it.hsn_code || '-'}</td>
<td>${parseFloat(it.qty).toFixed(2)} (+${parseFloat(it.free_qty).toFixed(2)})</td>
<td>${it.rate}</td>

<td>${(it.qty * it.rate).toFixed(2)}</td>

<td>${it.disc_percent || 0}</td>

<td>${it.discount.toFixed(2)}</td>

<td>${it.base_amount.toFixed(2)}</td>

<td>${it.gst_percent}%</td>

<td>${it.gst_amount.toFixed(2)}</td>

<td>${it.total.toFixed(2)}</td>

<td>

<button type="button"
onclick="editItem(${i})"
class="btn btn-xs btn-info">

<i class="fa fa-pencil"></i>

</button>

<button type="button"
onclick="items.splice(${i},1);renderItems()"
class="btn btn-xs btn-danger">

<i class="fa fa-trash"></i>

</button>

</td>
</tr>`;
  });

    updateGrandTotal();
}

function updateGrandTotal() {

let itemTotal = 0;
let chargeTotal = 0;

items.forEach(it => itemTotal += it.total);

charges.forEach(c => chargeTotal += c.total);

let finalTotal = itemTotal + chargeTotal;

let roundChecked =
document.getElementById("roundOffToggle").checked;

document.getElementById("round_off").value = roundChecked ? 1 : 0;

if(roundChecked){

finalTotal = Math.round(finalTotal);

}

document.getElementById("grandTotal").innerText =
finalTotal.toFixed(2);

document.getElementById("items_json").value =
JSON.stringify(items);

document.getElementById("charges_json").value =
JSON.stringify(charges);

/* AUTO UPDATE PAYMENT INPUTS */

let checkedBoxes =
document.querySelectorAll(".pay-check:checked");

if(checkedBoxes.length == 1){

checkedBoxes.forEach(ch => {

let mode = ch.value;

let input = document.querySelector(
'.pay-amount[data-mode="' + mode + '"]'
);

if(input){

let advanceUsed = parseFloat(
document.getElementById("used_advance").value
) || 0;

let payable = finalTotal - advanceUsed;

if(payable < 0){
payable = 0;
}

if(roundChecked){

input.value = Math.round(payable);

}else{

input.value = payable;

}

}



});

}

}

function saveShippingModal(){

document.getElementById("charge_type").value =
document.getElementById("modal_charge_type").value;

document.getElementById("charge_amount").value =
document.getElementById("modal_charge_amount").value;

document.getElementById("charge_gst_type").value =
document.getElementById("modal_charge_gst_type").value;

document.getElementById("charge_gst_id").value =
document.getElementById("modal_charge_gst_id").value;

console.log("Amount =", document.getElementById("charge_amount").value);

addCharge();

$('#shippingModal').modal('hide');

}

function addCharge() {

  let select = document.getElementById("charge_type");
  let shipping_type_id = select.value;
  let type_name = select.options[select.selectedIndex].text;
  let amount = parseFloat(document.getElementById("charge_amount").value) || 0;
  let gstSelect = document.getElementById("charge_gst_id");
  let gst_id = gstSelect.value;
  let gst_percent = parseFloat(
    gstSelect.options[gstSelect.selectedIndex]?.dataset.gst || 0
  );
  let gst_type = document.getElementById("charge_gst_type").value;

if (amount <= 0) {

    Swal.fire({
        icon: 'warning',
        title: 'Invalid Amount',
        text: 'Please enter a valid amount.'
    });

    return;
}

  let taxable, gst_amount, total;

  if (gst_type === "EXCLUSIVE") {
    taxable = amount;
    gst_amount = taxable * gst_percent / 100;
    total = taxable + gst_amount;
  } else {
    taxable = amount / (1 + gst_percent / 100);
    gst_amount = amount - taxable;
    total = amount;
  }

  charges.push({
    shipping_type_id,
    type_name,
    gst_id,
    amount,
    gst_percent,
    gst_type,
    gst_amount,
    total
  });

  renderCharges();
document.getElementById("charge_type").value = "";
document.getElementById("charge_amount").value = "";
document.getElementById("charge_gst_id").value = "";
document.getElementById("charge_gst_type").value = "EXCLUSIVE";
  
}

function renderCharges() {

let body = document.getElementById("chargeBody");
body.innerHTML = "";

let summary = document.getElementById("shippingSummary");

if(charges.length == 0){

    summary.style.display = "none";
    updateGrandTotal();
    return;

}

summary.style.display = "block";

charges.forEach((c, i) => {

    summary.innerHTML += `
    <div style="
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:4px 0;
    border-bottom:1px solid #eee;
    ">

        <div style="font-size:11px;">

            <b>Total :</b> ₹ ${c.total.toFixed(2)}
            &nbsp; | &nbsp;
            <b>${c.gst_type == "EXCLUSIVE" ? "EXCL. GST" : "INCL."}</b>

        </div>

        <div>

            <button
            type="button"
            class="btn btn-xs btn-info"
            onclick="editCharge(${i})">

            <i class="fa fa-pencil"></i>

            </button>

            <button
            type="button"
            class="btn btn-xs btn-danger"
            onclick="charges.splice(${i},1);renderCharges()">

            <i class="fa fa-trash"></i>

            </button>

        </div>

    </div>
    `;

});

updateGrandTotal();

}


function selectProduct(id, name, hsn) {

  document.getElementById("product").value = id;
  document.getElementById("product_name").value = name;
  document.getElementById("hsn_code").value = hsn;

  $('#searchProduct').val('');
$("#productTable tr").show();

  $('#productModal').modal('hide');

  $('#productModal').one('hidden.bs.modal', function () {

    setTimeout(function () {

        document.getElementById("qty").focus();
        document.getElementById("qty").select();

    }, 50);

});

  

  fetch("get_product_rate.php?product_id=" + id)
    .then(res => res.json())
    .then(data => {

      if (!data || Object.keys(data).length === 0) return;

      if (data.rate !== undefined)
        document.getElementById("rate").value = data.rate;

      if (data.mrp !== undefined)
        document.getElementById("mrp").value = data.mrp;

      if (data.gst_id)
        document.getElementById("gst").value = data.gst_id;

      if (data.buy_type)
        document.getElementById("buy_type").value = data.buy_type;

if (data.last_rate !== undefined) {

    let box = document.getElementById("previousRateBox");
    box.style.display = "block";

    box.innerHTML = `
        <strong>Last Purchase :</strong>
        &nbsp; Rate (Inc GST): <b>₹ ${data.last_rate}</b>
        &nbsp; | &nbsp;
        GST: <b>${data.gst_percent}%</b>
        &nbsp; | &nbsp;
        MRP: <b>₹ ${data.mrp}</b>
        &nbsp; | &nbsp;
        Date: <b>${data.price_date}</b>
    `;
}


    })
    .catch(err => console.log("JSON Error:", err));
}

document.addEventListener("DOMContentLoaded", function () {

  let searchBox = document.getElementById("searchProduct");

  if (searchBox) {
    searchBox.addEventListener("keyup", function () {

      let value = this.value.toLowerCase();
      let rows = document.querySelectorAll("#productTable tr");

      rows.forEach(row => {
        row.style.display =
          row.innerText.toLowerCase().includes(value) ? "" : "none";
      });

    });
  }

});

document.getElementById("grnForm")
.addEventListener("submit", function(e){

    updateGrandTotal();

    document.getElementById("round_off").value =
        document.getElementById("roundOffToggle").checked ? 1 : 0;

    collectPayments();

/* ================= REQUIRED FIELD VALIDATION ================= */

let supplier =
document.querySelector('[name="supplier_id"]').value;

let billNo =
document.querySelector('[name="bill_no"]').value.trim();

let billDate =
document.querySelector('[name="bill_date"]').value;

let errors = [];

/* SUPPLIER */

if(supplier == ''){

errors.push("Supplier is required.");

}

/* BILL NO */

if(billNo == ''){

errors.push("Bill / GRN No is required.");

}

/* BILL DATE */

if(billDate == ''){

errors.push("Bill Date is required.");

}

/* ITEMS */

if(items.length == 0){

errors.push("Please add at least one item.");

}

/* SHOW REQUIRED FIELD ERRORS */

if(errors.length > 0){

e.preventDefault();

Swal.fire({
    icon: 'warning',
    title: 'Validation Error',
    html: errors.join("<br>")
});

return false;

}

/* ================= PAYMENT VALIDATION ================= */

let warningMessages = [];

let enteredPaymentTotal = 0;
document.querySelectorAll(".pay-check").forEach(check => {

if(check.checked){

let mode = check.value;

let amountInput = document.querySelector(
'.pay-amount[data-mode="' + mode + '"]'
);

let utrInput = document.querySelector(
'.pay-utr[data-mode="' + mode + '"]'
);

let amount = parseFloat(amountInput.value) || 0;

enteredPaymentTotal += amount;

let utr = utrInput ? utrInput.value.trim() : '';

/* PAYMENT EMPTY */

if(amount <= 0){

warningMessages.push(
mode + " selected but payment amount not entered."
);

}

/* UTR CHECK */

if(
mode.toLowerCase() != 'cash'
&& amount > 0
&& utr == ''
){

warningMessages.push(
mode + " payment selected but UTR No not entered."
);

}

}

});

let grandTotal = parseFloat(
document.getElementById("grandTotal").innerText
.replace(/,/g,'')
) || 0;

let roundedDown = Math.floor(grandTotal);
let roundedUp = Math.ceil(grandTotal);

if(enteredPaymentTotal > roundedUp){

e.preventDefault();

Swal.fire({
    icon: 'error',
    title: 'Invalid Payment',
    text: 'Payment amount cannot be greater than Grand Total.'
});

return false;

}
/* SHOW WARNING CONFIRMATION */

if(warningMessages.length > 0){

e.preventDefault();

let finalMsg =
warningMessages.join("\n\n") +
"\n\nDo you still want to continue?";

Swal.fire({
    title: 'Continue?',
    html: finalMsg.replace(/\n/g,'<br>'),
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, Continue',
    cancelButtonText: 'Cancel'
}).then((result) => {

if(result.isConfirmed){

    document.getElementById("grnForm")
            .removeEventListener("submit", arguments.callee);

    document.getElementById("grnForm").submit();

}

});

}

});


function togglePaymentInput(check){

let mode = check.value;

let input = document.querySelector(
'.pay-amount[data-mode="' + mode + '"]'
);

let utrInput = document.querySelector(
'.pay-utr[data-mode="' + mode + '"]'
);

let total = parseFloat(
document.getElementById("grandTotal").innerText
.replace(/,/g,'')
) || 0;

let checkedBoxes =
document.querySelectorAll(".pay-check:checked");

if(check.checked){

input.style.display = "block";

if(mode.toLowerCase() != 'cash'){

utrInput.disabled = false;

utrInput.style.display = "block";
utrInput.required = true;

}else{

utrInput.required = false;
utrInput.disabled = true;
utrInput.style.display = "none";
utrInput.value = '';

}

if(checkedBoxes.length == 1){

let advanceUsed = parseFloat(
document.getElementById("used_advance").value
) || 0;

let payable = total - advanceUsed;

if(payable < 0){
    payable = 0;
}

let roundChecked =
document.getElementById("roundOffToggle").checked;

if(roundChecked){

    input.value = Math.round(payable);

}else{

    input.value = payable.toFixed(2);

}

}else{

input.value = "";

}

input.focus();

}else{

input.style.display = "none";
input.value = "";

utrInput.style.display = "none";
utrInput.value = "";

}

/* MULTIPLE MODE HANDLE */

if(checkedBoxes.length > 1){

document.querySelectorAll(".pay-check:checked")
.forEach(ch => {

let m = ch.value;

let inp = document.querySelector(
'.pay-amount[data-mode="' + m + '"]'
);

if(
inp &&
(
parseFloat(inp.value) == total.toFixed(2)
||
parseFloat(inp.value) == Math.round(total)
)
){

inp.value = "";

}

});

}

}


function editItem(index){

let it = items[index];
backupItem = {...it};

document.getElementById("product").value = it.product_id;
document.getElementById("product_name").value = it.name;

document.getElementById("hsn_code").value = it.hsn_code;

document.getElementById("qty").value = it.qty;

document.getElementById("free_qty").value = it.free_qty;

document.getElementById("rate").value = it.rate;

document.getElementById("disc_percent").value = it.disc_percent || 0;

document.getElementById("discount").value = it.discount;

document.getElementById("misc").value = it.misc;

document.getElementById("mrp").value = it.mrp;

document.getElementById("gst").value = it.gst_id;

document.getElementById("buy_type").value = it.buy_type;

items.splice(index,1);

renderItems();
}

let backupItem = null;

function cancelEditItem(){

if(backupItem){

items.push(backupItem);

backupItem = null;

renderItems();

}

document.getElementById("product").value = "";
document.getElementById("product_name").value = "";
document.getElementById("hsn_code").value = "";

document.getElementById("qty").value = "";
document.getElementById("free_qty").value = "";
document.getElementById("rate").value = "";

document.getElementById("disc_percent").value = "";
document.getElementById("discount").value = "";

let netBox =
document.getElementById("net_amount");

if(netBox){
netBox.value = "";
}

document.getElementById("misc").value = "";
document.getElementById("mrp").value = "";
document.getElementById("gst").value = "";
document.getElementById("buy_type").value = "exclusive";

document.getElementById("previousRateBox").style.display = "none";
document.getElementById("previousRateBox").innerHTML = "";

}

function loadSupplierAdvance(){

let supplier =
document.getElementById("supplier_id");

if(!supplier) return;

let supplier_id = supplier.value;

if(!supplier_id){

document.getElementById("advanceAmount")
.innerText = "0.00";

document.getElementById("advanceSection")
.style.display = "block";

document.getElementById("useAdvanceWrapper")
.style.display = "none";

return;

}

fetch(
"get_supplier_advance.php?supplier_id="
+ supplier_id
)

.then(res => res.json())

.then(data => {

let adv = parseFloat(data.advance || 0);

document.getElementById("advanceAmount")
.innerText = adv.toFixed(2);


document.getElementById("advanceSection")
.style.display = "block";



if(adv > 0){

document.getElementById("useAdvanceWrapper")
.style.display = "block";

}else{

document.getElementById("useAdvanceWrapper")
.style.display = "none";

document.getElementById("useAdvance").checked = false;

document.getElementById("advanceInput").style.display = "none";

document.getElementById("advanceInput").value = "";

document.getElementById("used_advance").value = 0;

}

});

}

loadSupplierAdvance();

document.getElementById("supplier_id")
.addEventListener(
"change",
loadSupplierAdvance
);

let useAdvance =
document.getElementById("useAdvance");

if(useAdvance){

    document.getElementById("advanceInput")
    .addEventListener("input", function(){

        let available = parseFloat(
            document.getElementById("advanceAmount")
            .innerText.replace(/,/g,'')
        ) || 0;

        let total = parseFloat(
            document.getElementById("grandTotal")
            .innerText.replace(/,/g,'')
        ) || 0;

        let entered = parseFloat(this.value) || 0;

        if (entered > available) entered = available;
        if (entered > total) entered = total;

        this.value = entered.toFixed(2);

        document.getElementById("used_advance").value = entered;

        document.getElementById("balanceAdvance").innerText =
            (available - entered).toFixed(2);

        updateGrandTotal();
    });

    // 👇 YE CODE YAHI PASTE KARO
    useAdvance.addEventListener("change", function(){

        let available = parseFloat(
            document.getElementById("advanceAmount")
            .innerText.replace(/,/g,'')
        ) || 0;

        let total = parseFloat(
            document.getElementById("grandTotal")
            .innerText.replace(/,/g,'')
        ) || 0;

        if(this.checked){

            let amount = Math.min(available,total);

            document.getElementById("advanceInput").style.display = "block";
            document.getElementById("advanceInput").value = amount.toFixed(2);
            document.getElementById("used_advance").value = amount;

            document.getElementById("balanceAdvance").innerText =
                (available-amount).toFixed(2);

        }else{

            document.getElementById("advanceInput").style.display = "none";
            document.getElementById("advanceInput").value = "";
            document.getElementById("used_advance").value = 0;

            document.getElementById("balanceAdvance").innerText =
                available.toFixed(2);
        }

        updateGrandTotal();
    });

}

function refreshShippingTypes(){

    $.ajax({
        url: "get_shipping_types.php",
        type: "GET",
        dataType: "json",

        success: function(data){

            let html =
            '<option value="">Select Type</option>';

            data.forEach(function(row){

                html +=
                '<option value="'+row.id+'">'+
                row.type_name+
                '</option>';

            });

            $("#charge_type").html(html);

        },

        error: function(){

            Swal.fire({
    icon: 'error',
    title: 'Error',
    text: 'Unable to refresh shipping types.'
});

        }
    });

}

function editCharge(index){

    let c = charges[index];

    document.getElementById("modal_charge_type").value = c.shipping_type_id;
    document.getElementById("modal_charge_amount").value = c.amount;
    document.getElementById("modal_charge_gst_id").value = c.gst_id;
    document.getElementById("modal_charge_gst_type").value = c.gst_type;

    charges.splice(index,1);

    renderCharges();

    $('#shippingModal').modal('show');
}

$('#productModal').on('shown.bs.modal', function () {

    $('#searchProduct').val('');

    $("#productTable tr").show();

    setTimeout(function () {
        $('#searchProduct').focus();
        $('#searchProduct').select();
    }, 200);

});

function openProductModal(){

    $('#productModal').modal('show');

    setTimeout(function(){

        $('#searchProduct').val('');
        $("#productTable tr").show();

        document.getElementById("searchProduct").focus();
        document.getElementById("searchProduct").select();

    },300);

}

</script>




<!-- PRODUCT MODAL -->
<div class="modal fade" id="productModal">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4>Select Product</h4>
      </div>

      <div class="modal-body">

        <input type="text" id="searchProduct" class="form-control" placeholder="Search product..." autofocus>

        <div style="max-height:350px; overflow-y:auto;">
          <table class="table table-bordered table-hover">
            <thead>
             <tr>
    <th width="10%">#</th>
    <th width="70%">Product Name</th>
    <th width="20%">HSN Code</th>
</tr>
            </thead>
            <tbody id="productTable">
              <?php $i=1; foreach($products as $p){ ?>
              <tr onclick="selectProduct(
                '<?= $p['id']; ?>',
                '<?= $p['name']; ?>',
                '<?= $p['hsn_code']; ?>'
              )"
              style="cursor:pointer;">

              <td><?= $i++; ?></td>
              <td><?= $p['name']; ?></td>
              <td><?= $p['hsn_code']; ?></td>
            </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>

      </div>

    </div>
  </div>
</div>


<?php include_once('layouts/footer.php'); ?>
