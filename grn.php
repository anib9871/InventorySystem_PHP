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
  $bill_date    = $_POST['bill_date'];
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

$db->query("

UPDATE supplier_ledger

SET balance_amount = 0

WHERE supplier_id = '$supplier_id'

AND balance_amount < 0

");

}


    $db->query("COMMIT");
    if(isset($_POST['update_grn'])){

$session->msg("s", "GRN Updated Successfully");

} else {

$session->msg("s", "GRN Created Successfully");

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

<!-- ================= UI ================= -->

<div class="row">
  <div class="col-md-12"><?php echo display_msg($msg); ?></div>
</div>

<div class="row">

<!-- LEFT : ITEM ENTRY -->
<div class="col-md-4">
<div class="panel panel-default">
<div class="panel-heading"><strong>Item Entry</strong></div>
<div class="panel-body">

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
?>
>
<?= $s['supplier_name']; ?>
</option>
<?php } ?>
</select><br>

<label>Bill / GRN No</label>
<input type="text"
name="bill_no"
value="<?= $edit_mode ? $grn_info['bill_indent_no'] : ''; ?>"
class="form-control"
form="grnForm"
required>
<br>

<label>Bill Date</label>
<input type="date"
name="bill_date"
value="<?= $edit_mode 
? date('Y-m-d', strtotime($grn_info['bill_indent_date'])) 
: date('Y-m-d'); ?>"
class="form-control"
form="grnForm"
required>
<br>

<input type="hidden" id="product">
<input type="hidden" id="hsn_code">
<input type="hidden" id="sac_code">

<div class="input-group">
  <input type="text" id="product_name" class="form-control" placeholder="Select Product" readonly>
  <span class="input-group-btn">
    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#productModal">
      Choose
    </button>
  </span>
</div>
<br>

<input
id="qty"
type="number"
step="0.01"
min="0"
class="form-control"
placeholder="Quantity">
<br>

<input
id="free_qty"
type="number"
step="0.01"
min="0"
class="form-control"
placeholder="Free Qty">
<br>

<input
id="rate"
type="number"
step="0.01"
min="0"
class="form-control"
placeholder="Rate"><br>

<input
id="discount"
type="number"
step="0.01"
min="0"
class="form-control"
placeholder="Discount"><br>

<input
id="misc"
type="number"
step="0.01"
min="0"
class="form-control"
placeholder="Misc"><br>

<input
id="mrp"
type="number"
step="0.01"
min="0"
class="form-control"
placeholder="MRP"><br>

<select id="gst" class="form-control">
<option value="">Select GST</option>
<?php foreach ($gst_list as $g) { ?>
<option value="<?= $g['id']; ?>" data-gst="<?= $g['gst_percent']; ?>">
<?= $g['gst_name']; ?> (<?= $g['gst_percent']; ?>%)
</option>
<?php } ?>
</select><br>

<select id="buy_type" class="form-control">
  <option value="exclusive">GST Exclusive</option>
  <option value="inclusive">GST Inclusive</option>
</select>
<br>

<div id="previousRateBox"
     style="display:none; margin-top:8px; padding:8px;
            background:#f5f5f5; border-left:4px solid #2196F3;
            font-size:12px;">
</div>


<br>

<div class="row">

<div class="col-md-6">

<button
type="button"
onclick="addItem()"
class="btn btn-info btn-block">

Add Item

</button>

</div>

<div class="col-md-6">

<button
type="button"
onclick="cancelEditItem()"
class="btn btn-danger btn-block">

Cancel

</button>

</div>

</div>

</div>
</div>
</div>

<!-- RIGHT SIDE -->
<!-- RIGHT : ITEM LIST + PAYMENT -->
<div class="col-md-8">
<form method="post" id="grnForm">

<input type="hidden" name="charges_json" id="charges_json">

<?php if($edit_mode){ ?>

<input type="hidden"
name="old_bill_no"
value="<?= $edit_bill; ?>">

<?php } ?>

<input type="hidden" name="items_json" id="items_json">

<div class="panel panel-default">
<div class="panel-heading">
<strong>GRN ITEMS</strong>
</div>

<div class="panel-body">

<table class="table table-bordered table-striped">
<thead>
<tr>
<th width="5%">#</th>
<th width="10%">Product</th>
<th width="10%">HSN Code</th>
<th width="10%">Qty</th>
<th width="10%">Rate</th>
<th width="12%">Amount</th>
<th width="10%">GST%</th>
<th width="10%">GST Amt</th>
<th width="15%">Total</th>
<th width="5%">X</th>
</tr>
</thead>
<tbody id="itemBody"></tbody>
</table>

<hr>

<h4><strong>Shipping / Additional Charges</strong></h4>

<div class="row">

<div class="col-md-3">
<select id="charge_type" class="form-control">
<option value="">Select Type</option>
<?php
$shipping_types = find_by_sql("SELECT * FROM shipping_type_master WHERE is_active = 1");
foreach($shipping_types as $st){ ?>
  <option value="<?= $st['id']; ?>">
    <?= $st['type_name']; ?>
  </option>
<?php } ?>
</select>
</div>

<div class="col-md-2">
<input
type="number"
id="charge_amount"
step="0.01"
min="0"
class="form-control"
placeholder="Amount">
</div>

<div class="col-md-2">
<select id="charge_gst_type" class="form-control">
<option value="EXCLUSIVE">Exclusive</option>
<option value="INCLUSIVE">Inclusive</option>
</select>
</div>

<div class="col-md-2">
<select id="charge_gst_id" class="form-control">
  <option value="">Select GST</option>
  <?php foreach ($gst_list as $g) { ?>
    <option value="<?= $g['id']; ?>" 
            data-gst="<?= $g['gst_percent']; ?>">
      <?= $g['gst_name']; ?> (<?= $g['gst_percent']; ?>%)
    </option>
  <?php } ?>
</select>
</div>

<div class="col-md-2">
<button type="button" onclick="addCharge()" class="btn btn-warning">
Add
</button>
</div>

</div>

<br>

<table class="table table-bordered">
<thead>
<tr>
<th>Type</th>
<th>Amount</th>
<th>GST %</th>
<th>GST Amt</th>
<th>Total</th>
<th>X</th>
</tr>
</thead>
<tbody id="chargeBody"></tbody>
</table>


<div
style="
display:flex;
justify-content:flex-end;
align-items:center;
gap:15px;
margin-top:10px;
">

<label style="margin:0; font-weight:normal;">

<input
type="checkbox"
id="roundOffToggle"
onchange="updateGrandTotal()"
>

Round Off

</label>

<h4 style="margin:0;">
Grand Total ₹
<span id="grandTotal">0.00</span>
</h4>

</div>

<hr>

<?php

$advance_amount = 0;

?>

<div class="alert alert-info">

<strong>

Available Advance :
₹ <span id="advanceAmount">

<?= number_format($advance_amount,2); ?>

</span>

</strong>

<br>

<label style="margin-top:8px;">

<input type="checkbox"
id="useAdvance">

Use Advance

</label>

</div>

<h4><strong>Payments</strong></h4>

<div class="row" style="
margin-left:-8px;
margin-right:-8px;
margin-top:10px;
">

<?php foreach($payment_modes as $pm){ ?>

<div class="col-md-4" style="
margin-bottom:15px;
padding-left:8px;
padding-right:8px;
">

<div style="
border:1px solid #ddd;
padding:6px 8px;
border-radius:5px;
background:#fafafa;
">

<div style="
display:flex;
align-items:center;
gap:8px;
margin-bottom:6px;
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
font-size:12px;
">

<?= strtoupper($pm['mode_name']); ?>

</label>

</div>

<input
type="number"
step="0.01"
min="0"
class="form-control pay-amount"
style="
display:none;
height:28px;
font-size:11px;
padding:2px 6px;
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
height:28px;
font-size:11px;
padding:2px 6px;
margin-top:5px;
"
data-mode="<?= $pm['mode_name']; ?>"
placeholder="Enter UTR No"
>
</div>

</div>

<?php } ?>

</div>


<input type="hidden" name="payments_json" id="payments_json">

<input
type="hidden"
name="used_advance"
id="used_advance"
value="0">

<br>

<label><strong>Comments</strong></label>
<textarea name="comments"
class="form-control"
rows="3"><?= $edit_mode ? $grn_info['comments'] : ''; ?></textarea>
<br>

<?php if($edit_mode){ ?>

<button name="update_grn"
class="btn btn-primary pull-right">

Update GRN

</button>

<?php } else { ?>

<button name="save_grn"
class="btn btn-success pull-right">

Create GRN

</button>

<?php } ?>

</div>
</div>

</form>
</div>

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

c.total = parseFloat(c.total_amount);

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

function addItem() {

  let pid = document.getElementById("product").value;
  let pname = document.getElementById("product_name").value;

  let qty   = parseFloat(document.getElementById("qty").value) || 0;
  let free  = parseFloat(document.getElementById("free_qty").value) || 0;
  let rate  = parseFloat(document.getElementById("rate").value) || 0;
  let disc  = parseFloat(document.getElementById("discount").value) || 0;
  let misc  = parseFloat(document.getElementById("misc").value) || 0;
  let mrp   = parseFloat(document.getElementById("mrp").value) || 0;

  let gstSel = document.getElementById("gst");
  let gst_id = gstSel.value;
  let gstp   = parseFloat(gstSel.options[gstSel.selectedIndex]?.dataset.gst || 0);

  if (!pid || qty <= 0 || rate <= 0 || !gst_id) {
    alert("Please fill all item fields");
    return;
  }

let buyType = document.getElementById("buy_type").value;

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
<td>${it.base_amount.toFixed(2)}</td>
<td>${it.gst_percent}%</td>
<td>${it.gst_amount.toFixed(2)}</td>
<td>${it.total.toFixed(2)}</td>
<td>
<button type="button"
onclick="editItem(${i})"
class="btn btn-xs btn-info">

Edit

</button>

<button type="button"
onclick="items.splice(${i},1);renderItems()"
class="btn btn-xs btn-danger">

X

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

let payable =
finalTotal - advanceUsed;

if(payable < 0){
payable = 0;
}

if(roundChecked){

input.value = Math.round(payable);

}else{

input.value = payable.toFixed(2);

}

}



});

}

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
    alert("Enter valid amount");
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

  
}

function renderCharges() {

  let body = document.getElementById("chargeBody");
  body.innerHTML = "";

  charges.forEach((c, i) => {
    body.innerHTML += `
      <tr>
        <td>${c.type_name}</td>
        <td>${c.amount}</td>
        <td>${c.gst_percent}%</td>
        <td>${c.gst_amount.toFixed(2)}</td>
        <td>${c.total.toFixed(2)}</td>
        <td>
          <button type="button"
          onclick="charges.splice(${i},1);renderCharges()">X</button>
        </td>
      </tr>
    `;
  });

  updateGrandTotal();
}


function selectProduct(id, name, hsn) {

  document.getElementById("product").value = id;
  document.getElementById("product_name").value = name;
  document.getElementById("hsn_code").value = hsn;
  $('#productModal').modal('hide');

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
    <strong>Last Purchase Details:</strong><br>
    Rate (Inclusive GST): ₹ ${data.last_rate}<br>
    GST Applied: ${data.gst_percent}%<br>
    MRP: ₹ ${data.mrp}<br>
    Price Date: ${data.price_date}
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

alert(errors.join("\n"));

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

alert(
"Payment amount cannot be greater than Grand Total."
);

return false;

}
/* SHOW WARNING CONFIRMATION */

if(warningMessages.length > 0){

e.preventDefault();

let finalMsg =
warningMessages.join("\n\n") +
"\n\nDo you still want to continue?";

if(confirm(finalMsg)){

document.getElementById("grnForm").submit();

}else{

return false;

}

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

let roundChecked =
document.getElementById("roundOffToggle").checked;

if(roundChecked){

input.value = Math.round(total);

}else{

input.value = total.toFixed(2);

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

document.getElementById("discount").value = "";

document.getElementById("misc").value = "";

document.getElementById("mrp").value = "";

document.getElementById("gst").value = "";

document.getElementById("buy_type").value = "exclusive";

}

function loadSupplierAdvance(){

let supplier =
document.getElementById("supplier_id");

if(!supplier) return;

let supplier_id = supplier.value;

if(!supplier_id){

document.getElementById("advanceAmount")
.innerText = "0.00";

return;

}

fetch(
"get_supplier_advance.php?supplier_id="
+ supplier_id
)

.then(res => res.json())

.then(data => {

document.getElementById("advanceAmount")
.innerText = parseFloat(
data.advance || 0
).toFixed(2);

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

useAdvance.onchange = function(){

let advance = parseFloat(
document.getElementById("advanceAmount")
.innerText.replace(/,/g,'')
) || 0;

let total = parseFloat(
document.getElementById("grandTotal")
.innerText.replace(/,/g,'')
) || 0;

let used = 0;

if(this.checked){

used = Math.min(advance,total);

}

document.getElementById("used_advance")
.value = used;

updateGrandTotal();
updatePaymentDistribution();

};

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

        <input type="text" id="searchProduct" class="form-control" placeholder="Search product..."><br>

        <div style="max-height:350px; overflow-y:auto;">
          <table class="table table-bordered table-hover">
            <thead>
              <tr>
                <th>#</th>
                <th>Product Name</th>
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
