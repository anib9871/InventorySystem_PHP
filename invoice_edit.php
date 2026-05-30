<?php
require_once('includes/load.php');
//page_require_level(2);

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($id <= 0){
    die("Invalid Invoice ID");
}


$old_payments = find_by_sql("
SELECT *
FROM payments
WHERE invoice_id = $id
");

/* ================= FETCH INVOICE ================= */

$qdata = find_by_sql("
SELECT *
FROM invoice
WHERE id = $id
");

if(!$qdata){
    die("Invoice not found");
}

$quote = $qdata[0];

/* ================= FETCH ITEMS ================= */

$items = find_by_sql("
SELECT ii.*, p.name
FROM invoice_items ii
LEFT JOIN products p ON p.id = ii.product_id
WHERE ii.invoice_id = $id
");

/* ================= MASTER DATA ================= */

$customers = find_all('customer_master');
$products  = join_product_table();

$payment_modes = find_by_sql("
SELECT id, mode_name
FROM payment_mode_master
WHERE is_active = 1
");

$terms_templates = find_all('terms_conditions_master');
$gst_enabled = "Yes";

/* ================= UPDATE ================= */

if(isset($_POST['update_invoice'])){

global $db;

$db->query("START TRANSACTION");

try{

$cust = (int)$_POST['customer_id'];

$gst_type = $_POST['gst_type'] ?? 'exclusive';

$subtotal  = 0;
$net_total = 0;
$total_gst = 0;

/* ================= OLD DATA DELETE ================= */

$db->query("DELETE FROM invoice_items WHERE invoice_id = $id");

$db->query("DELETE FROM payments WHERE invoice_id = $id");

$db->query("DELETE FROM ledger_entries WHERE invoice_id = $id");

$db->query("
DELETE FROM transaction_master
WHERE bill_indent_no = '".$quote['invoice_no']."'
");

/* ================= INSERT ITEMS AGAIN ================= */

foreach($_POST['product_id'] as $i => $pid){

$pid = (int)$pid;

if($pid <= 0){
    continue;
}

$qty  = (float)$_POST['qty'][$i];
$base = (float)$_POST['rate'][$i];

$gst = 0;

if($gst_enabled == "Yes"){
    $gst = (float)$_POST['gst'][$i];
}

$disc = (float)$_POST['discount'][$i];

$line_base = $qty * $base;

$discounted_base = $line_base - $disc;

if($gst_type == "exclusive"){

    $gst_amount = ($discounted_base * $gst) / 100;

    $cgst_amount = $gst_amount / 2;
    $sgst_amount = $gst_amount / 2;
    $igst_amount = 0;

    $rate_incl = $base + ($base * $gst / 100);

    $line_total = $discounted_base + $gst_amount;

}
elseif($gst_type == "inclusive"){

    $gst_amount = $discounted_base
                - ($discounted_base / (1 + $gst/100));

    $cgst_amount = $gst_amount / 2;
    $sgst_amount = $gst_amount / 2;
    $igst_amount = 0;

    $rate_incl = $base;

    $line_total = $discounted_base;

}
else{

    $gst_amount = 0;

    $cgst_amount = 0;
    $sgst_amount = 0;
    $igst_amount = 0;

    $rate_incl = $base;

    $line_total = $discounted_base;
}

$total_gst += $gst_amount;

$subtotal += $line_base;

$net_total += $line_total;

/* ================= INSERT ITEM ================= */

$db->query("
INSERT INTO invoice_items
(
invoice_id,
product_id,
qty,
rate_excl_gst,
discount_amount,
gst_percent,
rate_incl_gst,
cgst_amount,
sgst_amount,
igst_amount,
line_total
)
VALUES
(
$id,
$pid,
$qty,
$base,
$disc,
$gst,
$rate_incl,
$cgst_amount,
$sgst_amount,
$igst_amount,
$line_total
)
");

/* ================= TRANSACTION ================= */

$db->query("
INSERT INTO transaction_master
(
product_id,
gst_id,
bill_indent_no,
entry_date,
bill_indent_date,
quantity,
unit,
unit_price,
gst_amount,
discount_amount,
net_price,
sale_amount,
sale_gst,
sale_net,
transaction_type,
status,
comments,
created_at
)
VALUES
(
'$pid',
'$gst',
'".$quote['invoice_no']."',
NOW(),
NOW(),
'$qty',
'PCS',
'$base',
'$gst_amount',
'$disc',
'$line_total',
'$line_total',
'$gst_amount',
'$line_total',
2,
1,
'Sale Invoice Edit',
NOW()
)
");

}

/* ================= PAYMENT ================= */

$total_paid = 0;

if(isset($_POST['payment_amount'])){

foreach($_POST['payment_amount'] as $mode_id => $amt){

$amt = (float)$amt;

$total_paid += $amt;

if($amt > 0){

$pm = find_by_id('payment_mode_master', $mode_id);

$mode_name = $pm['mode_name'];

$utr = $_POST['utr_no'][$mode_id] ?? '';

$db->query("
INSERT INTO payments
(
invoice_id,
customer_id,
payment_mode,
amount,
reference_no
)
VALUES
(
$id,
$cust,
'$mode_name',
'$amt',
'".$db->escape($utr)."'
)
");

}
}
}

$due_amount = $net_total - $total_paid;

if($due_amount <= 0){

$payment_status = 'Paid';

$due_amount = 0;

}
elseif($total_paid > 0){

$payment_status = 'Partial';

}
else{

$payment_status = 'Unpaid';

}

/* ================= UPDATE MASTER ================= */

$db->query("
UPDATE invoice SET

customer_id = '$cust',
gst_type = '$gst_type',

subtotal = '$subtotal',
gst_total = '$total_gst',
net_total = '$net_total',

paid_amount = '$total_paid',
due_amount = '$due_amount',
payment_status = '$payment_status',

terms_conditions = '".$db->escape($_POST['terms_conditions'])."'

WHERE id = '$id'
");

/* ================= LEDGER ================= */

$db->query("
INSERT INTO ledger_entries
(invoice_id, customer_id, account, type, amount)
VALUES
($id, $cust, 'CUSTOMER', 'DEBIT', '$net_total')
");

$db->query("
INSERT INTO ledger_entries
(invoice_id, customer_id, account, type, amount)
VALUES
($id, $cust, 'SALES', 'CREDIT', '$net_total')
");

$db->query("COMMIT");

echo "
<script>
window.location='invoice_print.php?id=".$id."';
</script>
";

exit;

}catch(Exception $e){

$db->query("ROLLBACK");

echo "<pre>";
print_r($e);
echo "</pre>";

die();

}
}
?>

<?php include_once('layouts/header.php'); ?>

<style>

body{
    background:#f1f5f9;
    font-family:'Segoe UI',sans-serif;
}

/* ===== CARDS ===== */

.card{
    border:none;
    border-radius:18px;
    background:#fff;
    box-shadow:0 4px 18px rgba(15,23,42,.06);
}

/* ===== FORM ===== */

.form-control,
.form-control-sm{
    height:34px;
    border-radius:10px !important;
    border:1px solid #dbe2ea;
    font-size:14px;
    box-shadow:none !important;
}

.form-control:focus,
.form-control-sm:focus{
    border-color:#2563eb;
    box-shadow:0 0 0 0.15rem rgba(37,99,235,.15) !important;
}

/* ===== LEFT PANEL ===== */

.left-panel{
    position:sticky;
    top:10px;
}

/* ===== PRODUCT SEARCH ===== */

.product-card{
    margin-bottom:24px;
}


.product-scroll{
    height:180px;
    overflow-y:auto;
    border-radius:10px;
}

.product-scroll::-webkit-scrollbar{
    width:6px;
}

.product-scroll::-webkit-scrollbar-thumb{
    background:#cbd5e1;
    border-radius:10px;
}

#productSearch{
    height:38px;
    font-size:14px;
}

/* ===== PRODUCT LIST ===== */

#productList tr{
    transition:.2s;
    cursor:pointer;
}

#productList tr:hover{
    background:#eef4ff;
}

#productList td{
    padding:12px 10px !important;
    font-size:14px;
}

#productList th{
    background:#0f172a;
    color:#fff;
    position:sticky;
    top:0;
    z-index:10;
    padding:12px 10px !important;
    font-size:13px;
}

/* ===== BILL GRID ===== */

.bill-grid{
    background:#fff;
    border-radius:18px;
    height:220px;
    overflow-y:auto;
    overflow-x:hidden;
    box-shadow:0 4px 18px rgba(15,23,42,.06);
    position:relative;
}

.bill-grid::-webkit-scrollbar{
    width:6px;
}

.bill-grid::-webkit-scrollbar-thumb{
    background:#cbd5e1;
    border-radius:10px;
}


/* ===== TABLE ===== */

#itemTable{
    width:100%;
    table-layout:fixed;
    border-collapse:separate;
    border-spacing:0;
    margin-top:0;
}

/* HEADER */

#itemTable thead{
    position:sticky;
    top:0;
    z-index:999;
}

#itemTable thead th{
    position:sticky;
    top:0;
    background:#0f172a !important;
    color:#fff;
    border:none !important;
    padding:14px 8px;
    font-size:13px;
    white-space:nowrap;
    z-index:999;
    box-shadow:0 2px 4px rgba(0,0,0,.08);
}

/* ROWS */

#itemTable tbody tr{
    transition:.2s;
}

#itemTable tbody tr:hover td{
    background:#eef4ff;
}

/* CELLS */

#itemTable td{
    background:#f8fafc;
    border:none !important;
    padding:8px 6px;
    vertical-align:middle;
    height:72px;
}

/* INPUTS */

#itemTable input{
    width:100%;
    min-width:60px;
    height:38px;
    border-radius:8px;
    border:1px solid #dbe2ea;
    background:#fff;
    font-size:13px;
}



/* ===== PAYMENT ===== */

.payment-box{
    min-height:235px;
    padding:12px !important;
}

.payment-header{
    font-size:14px;
    font-weight:600;
    margin-bottom:10px;
}

.payment-scroll{
    height:190px;
    overflow-y:auto;
}

.payment-scroll::-webkit-scrollbar{
    width:6px;
}

.payment-scroll::-webkit-scrollbar-thumb{
    background:#cbd5e1;
    border-radius:10px;
}

/* PAYMENT TABLE COMPACT */

.payment-box table td{
    padding:6px 4px !important;
    vertical-align:middle;
    font-size:13px;
}

.payment-box .form-control{
    height:34px !important;
    font-size:12px;
    padding:4px 8px;
    border-radius:8px !important;
}

.payment-box .payCheck{
    transform:scale(.9);
}

.payment-header{
    margin-bottom:6px !important;
    font-size:13px;
}

.payment-box hr{
    margin:6px 0;
}



/* ===== SUMMARY ===== */

.summary-card{
    background:#fff;
    border-radius:16px;
    padding:12px !important;
    margin-top:10px;
    box-shadow:0 3px 12px rgba(15,23,42,.05);
}

.summary-row{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:7px 2px;
    font-size:13px;
    border-bottom:1px dashed #e2e8f0;
}

.summary-row strong{
    font-weight:600;
    font-size:13px;
}

.summary-row:last-child{
    border-bottom:none;
}

/* RIGHT SIDE */

.summary-card label{
    font-size:12px;
    font-weight:600;
    margin-bottom:3px;
}

.summary-card .form-control{
    height:34px;
    font-size:13px;
    border-radius:8px !important;
}

#termsTemplate{
    margin-top:0;
    margin-bottom:8px;
}

#termsBox{
    margin-top:0;
    min-height:75px;
    resize:none;
    padding:8px;
    font-size:13px;
}

/* REMOVE EXTRA SPACE */

.summary-card .col-lg-4{
    padding-right:12px;
}

.summary-card .col-lg-8{
    padding-left:12px;
}
/* ===== BUTTON ===== */

.save-btn{
    width:220px;
    height:42px;
    border:none;
    border-radius:10px !important;
    font-size:14px;
    font-weight:600;
    background:#22c55e;
    transition:.2s;
    display:block;
    margin:18px auto 0;
}

.save-btn:hover{
    background:#16a34a;
}

/* ===== REMOVE BUTTON ===== */

.remove{
    border-radius:8px !important;
    width:34px;
    height:34px;
    padding:0;
}


#termsTemplate{
    margin-top:12px;
}

#termsBox{
    margin-top:10px;
    min-height:90px;
    resize:none;
}

.summary-card label{
    font-size:13px;
    font-weight:600;
    margin-bottom:5px;
}

.summary-card select{
    height:40px;
}

.summary-card textarea{
    margin-top:6px;
}

.left-panel .card{
    margin-bottom:22px;
}

.product-scroll{
    border-bottom:1px solid #e2e8f0;
    padding-bottom:8px;
}

.bill-grid::-webkit-scrollbar{
    display:none;
}

#itemTable tbody tr:first-child td{
    padding-top:18px;
}



.top-filter-card{
    border-radius:3px;
    padding:6px 7px !important;
    margin-bottom:5px !important;
}

.top-filter-card label{
    font-size:14px;
    font-weight:600;
    margin-bottom:6px;
}

.top-save-btn{
    height:38px;
    border-radius:8px !important;
    font-size:14px;
    font-weight:600;
    padding:0 18px;
    width:auto !important;
    min-width:150px;
}

.top-filter-card .row{
    margin-bottom:0 !important;
}



/* ===== RESPONSIVE ===== */

@media(max-width:991px){

    .left-panel{
        position:relative;
        top:0;
    }

    .bill-grid{
        height:auto;
    }

    .summary-card{
        margin-top:20px;
    }
}

/* EQUAL HEIGHT LAYOUT */

.left-panel{
    display:flex;
    flex-direction:column;
    height:100%;
}

.product-card{
    height:220px;
}

.payment-box{
    flex:1;
    min-height:248px;
}

.bill-grid{
    height:220px;
}

.summary-card{
    min-height:248px;
}

</style>

<div class="card-body">
<form method="post" onsubmit="return validateCustomer()">

<!-- CUSTOMER -->
<div class="card p-3 mb-3 top-filter-card">


 
<div class="row align-items-end g-2">


   <div class="col-md-4 d-flex flex-column justify-content-center">
      <label>Customer</label>
      <select name="customer_id" class="form-control">
        <option value="">Select Customer</option>
        <?php foreach($customers as $c): ?>
<option value="<?=$c['id'];?>"
<?=$c['id']==$quote['customer_id']?'selected':'';?>>

<?=$c['customer_name'];?>

</option>
        <?php endforeach; ?>
      </select>
    </div>

<?php if($system == 'billing'): ?>> <div class="col-md-4 d-flex flex-column justify-content-center">
  <label>Name</label>
  <input type="text"
  name="manual_name"
  class="form-control">
</div>

<div class="col-md-4">
  <label>Contact</label>
  <input type="text"
  name="manual_phone"
  class="form-control">
</div>

<?php endif; ?>

<?php if($_SESSION['role_id'] == 2 && $system != 'inventory'): ?>

<div class="col-md-4">
<label>Center</label>

<select name="center_id" class="form-control" required>

<?php
$centers = find_all('master_center');

foreach($centers as $c):
?>

<option value="<?= $c['center_id']; ?>">
<?= $c['center_name']; ?>
</option>

<?php endforeach; ?>

</select>
</div>

<?php endif; ?>



<?php if($system != 'billing'): ?>

<div class="col-md-3 d-flex flex-column justify-content-center">
  <label>GST Type</label>

<select name="gst_type" class="form-control">

<option value="exclusive" selected>
Exclusive GST
</option>

<option value="inclusive">
Inclusive GST
</option>

<option value="nogst">
No GST
</option>

</select>

</div>

<!-- SAVE BUTTON -->
<div class="col-md-2 d-flex flex-column justify-content-end">
<label style="visibility:hidden;">Save</label>
<button
type="submit"
name="update_invoice"
class="btn btn-success top-save-btn">

💾 Save Invoice

</button>

</div>

<?php endif; ?>


  </div>
</div>
<br>


<div class="row align-items-stretch">

<div class="container-fluid px-4 py-3">

<div class="row">

  <!-- LEFT SIDE -->
  <div class="col-lg-3 left-panel">

    <!-- PRODUCT SEARCH -->
    <div class="card border-0 shadow-sm rounded-4 p-3 mb-3 product-card">

      <input type="text"
      id="productSearch"
      class="form-control form-control-sm mb-2"
      placeholder="Search Product...">

      <div class="product-scroll">

        <table class="table table-sm table-bordered mb-0">

          <thead>
            <tr>
              <th>Product</th>
              <th width="90">₹ Rate</th>
            </tr>
          </thead>

          <tbody id="productList"></tbody>

        </table>

      </div>

    </div>

    <!-- PAYMENT -->
    <div class="card border-0 shadow-sm p-3 payment-box">

      <div class="payment-header mb-2">
        <b>Payment</b>
      </div>

      <div class="payment-scroll">

        <table class="table table-sm mb-0">

<?php foreach($payment_modes as $pm): ?>

<?php

$checked = '';
$old_amt = 0;
$old_utr = '';

foreach($old_payments as $op){

   if(
      strtolower($op['payment_mode']) ==
      strtolower($pm['mode_name'])
   ){

      $checked = 'checked';

      $old_amt = $op['amount'];

      $old_utr = $op['reference_no'];
   }
}

?>

<tr>

<td width="30">

<input
type="checkbox"
class="payCheck"
data-mode="<?=$pm['id'];?>"
<?=$checked;?>>

</td>

<td>
<?= strtoupper($pm['mode_name']); ?>
</td>

<td>

<input
type="number"
name="payment_amount[<?=$pm['id'];?>]"
class="form-control form-control-sm payAmt"
data-mode="<?=$pm['id'];?>"
value="<?=$old_amt;?>"
<?=$checked ? '' : 'disabled'; ?>>

<?php if(strtolower($pm['mode_name']) != 'cash'): ?>

<input
type="text"
name="utr_no[<?=$pm['id'];?>]"
class="form-control form-control-sm mt-1 utrField"
placeholder="Enter UTR No"
data-mode="<?=$pm['id'];?>"
value="<?=$old_utr;?>"
<?=$checked ? '' : 'disabled'; ?>>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

        </table>

      </div>

    </div>

  </div>

  <!-- RIGHT SIDE -->
  <div class="col-lg-9 ps-lg-3">

    <!-- BILL GRID -->
    <div class="bill-grid mb-3">

      <table class="table table-bordered mb-0" id="itemTable">

        <thead>

          <tr>

            <th>Product</th>
            <th>Qty</th>
            <th>Price</th>

            <?php if($gst_enabled == "Yes"): ?>
            <th>GST%</th>
            <th>GST</th>
            <?php endif; ?>

            <th>Disc %</th>
            <th>Disc ₹</th>
            <th>Total</th>
            <th width="50"></th>

          </tr>

        </thead>

        <tbody id="billBody">

          <?php foreach($items as $it): ?>

<tr>

<td>
<?= $it['name']; ?>
<input type="hidden"
name="product_id[]"
value="<?= $it['product_id']; ?>">
</td>

<td>
<input type="number"
name="qty[]"
class="form-control form-control-sm qty"
value="<?= $it['qty']; ?>">
</td>

<td>
<input type="number"
name="rate[]"
class="form-control form-control-sm base"
value="<?= $it['rate_excl_gst']; ?>">
</td>

<?php if($gst_enabled == "Yes"): ?>

<td>
<input type="number"
name="gst[]"
class="form-control form-control-sm gst"
value="<?= $it['gst_percent']; ?>">
</td>

<td>
<input type="text"
class="form-control form-control-sm gstAmt"
readonly>
</td>

<?php endif; ?>

<td>
<input type="number"
step="0.0001"
class="form-control form-control-sm discPer"
value="0">
</td>

<td>
<input type="number"
step="0.0001"
name="discount[]"
class="form-control form-control-sm discAmt"
value="<?= $it['discount_amount']; ?>">
</td>

<td>
<input type="text"
class="form-control form-control-sm totalRow"
readonly>
</td>

<td>
<button type="button"
class="btn btn-danger btn-sm remove">
×
</button>
</td>

</tr>

<?php endforeach; ?>

        </tbody>

      </table>

    </div>

    <!-- SUMMARY -->
<div class="card border-0 shadow-sm rounded-4 p-3 mt-3 summary-card">

<div class="row">

    <!-- LEFT : TOTALS -->
    <div class="col-lg-4 border-end">

      <div class="summary-row">
        <span>Gross</span>
        <strong>₹ <span id="gross">0</span></strong>
      </div>

      <div class="summary-row">
        <span>Net</span>
        <strong class="text-primary">
          ₹ <span id="net">0</span>
        </strong>
      </div>

      <div class="summary-row">
        <span>Paid</span>
        <strong class="text-success">
          ₹ <span id="paid">0</span>
        </strong>
      </div>

      <div class="summary-row text-danger">
        <span>Balance</span>
        <strong>
          ₹ <span id="balance">0</span>
        </strong>
      </div>

      <div class="summary-row text-success">
        <span>Return</span>
        <strong>
          ₹ <span id="returnAmt">0</span>
        </strong>
      </div>

    </div>

    <!-- RIGHT : TERMS -->
    <div class="col-lg-8">

      <label class="mb-1">
        Terms & Conditions Template
      </label>

      <select
      id="termsTemplate"
      class="form-control mb-2">

        <option value="">
          Select Template
        </option>

        <?php foreach($terms_templates as $t): ?>

        <option
        value="<?= htmlspecialchars($t['template']); ?>">

          Template <?= $t['tc_id']; ?>

        </option>

        <?php endforeach; ?>

      </select>

      <label class="mb-1">
        Terms & Conditions
      </label>

      <textarea
      name="terms_conditions"
      id="termsBox"
      rows="5"
      class="form-control"
      placeholder="Terms & Conditions..."></textarea>

    </div>

</div>
</div>





<script>
const products = <?= json_encode($products); ?>;

/* PRODUCT LIST */
function renderProducts(filter=""){
 let list = document.getElementById("productList");
 list.innerHTML="";

 products
 .filter(p=>p.name.toLowerCase().includes(filter.toLowerCase()))
 .forEach(p=>{

  let tr=document.createElement("tr");
  tr.style.cursor="pointer";

  tr.innerHTML=`
    <td>${p.name}</td>
    <td>₹${p.sale_price}</td>
  `;

  tr.addEventListener("click",()=>addProduct(p)); // 🔥 inline onclick remove

  list.appendChild(tr);
 });
}
renderProducts();

document.getElementById("productSearch")
.addEventListener("input",e=>renderProducts(e.target.value));

/* ADD PRODUCT */
function addProduct(p){

 let rows = document.querySelectorAll("#billBody tr");

 for(let r of rows){
   let name = r.querySelector("td").innerText.trim();

   if(name === p.name){
     let qtyInput = r.querySelector(".qty");
     qtyInput.value = parseInt(qtyInput.value || 0) + 1;
     calculate(r);
     return;
   }
 }

 let row=document.createElement("tr");

 row.innerHTML=`
  <td>${p.name}<input type="hidden" name="product_id[]" value="${p.id}"></td>

  <td><input type="number" name="qty[]" class="form-control form-control-sm qty" value="1"></td>

  <td><input type="number" name="rate[]" class="form-control form-control-sm base" value="${p.sale_price}"></td>

  <?php if($gst_enabled == "Yes"): ?>

<td>
<input type="number" name="gst[]"
class="form-control form-control-sm gst"
value="${p.gst_percent}">
</td>

<td>
<input type="text"
class="form-control form-control-sm gstAmt"
readonly>
</td>

<?php endif; ?>

  <td><input
type="number"
step="0.0001"
class="form-control form-control-sm discPer"
value="0"></td>

  <td><input
type="number"
step="0.0001"
name="discount[]"
class="form-control form-control-sm discAmt"
value="0"></td>

  <td><input type="text" class="form-control form-control-sm totalRow" readonly></td>

  <td><button type="button" class="btn btn-danger btn-sm remove">×</button></td>
 `;

 document.getElementById("billBody").appendChild(row);

 calculate(row);
}


/* 🔥 INPUT FIX (VERY IMPORTANT) */
document.addEventListener("input", function(e){

 if(
   e.target.classList.contains("qty") ||
   e.target.classList.contains("base") ||
   e.target.classList.contains("gst") ||
   e.target.classList.contains("discPer") ||
   e.target.classList.contains("discAmt")
 ){
   calculate(e.target.closest("tr"));
 }

});

document.addEventListener("change", function(e){

 if(e.target.name == "gst_type"){

   document.querySelectorAll("#billBody tr").forEach(r=>{
      calculate(r);
   });

   updateSummary();
 }

});

/* REMOVE FIX */
document.addEventListener("click", function(e){

 if(e.target.classList.contains("remove")){
   let row = e.target.closest("tr");
   row.remove();
   updateSummary();
 }

});


/* CALC */
function calculate(r){

 let qty  = parseFloat(r.querySelector(".qty").value) || 0;
 let base = parseFloat(r.querySelector(".base").value) || 0;
let gst = 0;

let gstField = r.querySelector(".gst");

if(gstField){
   gst = parseFloat(gstField.value) || 0;
}

 let dPer = parseFloat(r.querySelector(".discPer").value) || 0;
 let dAmt = parseFloat(r.querySelector(".discAmt").value) || 0;

 let total = qty * base;

 // 🔥 FIX: prevent divide by zero
 if(total === 0){
   r.querySelector(".discAmt").value = 0;
   r.querySelector(".discPer").value = 0;
 }

// discount sync

let active = document.activeElement;

if(!active){
   active = {};
}

if(active && active.classList.contains("discPer")){

   dAmt = (total * dPer) / 100;

   r.querySelector(".discAmt").value =
   dAmt.toFixed(2);

}
else if(active && active.classList.contains("discAmt")){

   dPer = total > 0
   ? (dAmt / total) * 100
   : 0;

   r.querySelector(".discPer").value =
   dPer.toFixed(2);
}
else{

   dAmt = parseFloat(
      r.querySelector(".discAmt").value
   ) || 0;

   dPer = total > 0
   ? (dAmt / total) * 100
   : 0;

   r.querySelector(".discPer").value =
   dPer.toFixed(2);
}

 let afterDisc = total - dAmt;

/* GST TYPE */
let gstType = document.querySelector("select[name='gst_type']")
? document.querySelector("select[name='gst_type']").value
: "inclusive";

let gstSelect = document.querySelector("select[name='gst_type']");

if(gstSelect){
   gstType = gstSelect.value;
}

let gstAmt = 0;
let final = 0;

if(gstType == "nogst"){

    gstAmt = 0;
    final = afterDisc;

}
else if(gstType == "exclusive"){

    gstAmt = (afterDisc * gst) / 100;

    final = afterDisc + gstAmt;

}
else{

    gstAmt = afterDisc - (afterDisc * 100 / (100 + gst));

    final = afterDisc;
}

let gstAmtField = r.querySelector(".gstAmt");

if(gstAmtField){
   gstAmtField.value = gstAmt.toFixed(2);
}
 r.querySelector(".totalRow").value = final.toFixed(2);

 updateSummary();
}


/* SUMMARY */
function updateSummary(){

 let gross=0;

 document.querySelectorAll(".totalRow").forEach(t=>{
  gross += parseFloat(t.value) || 0;
 });

 document.getElementById("gross").innerText = gross.toFixed(2);
 document.getElementById("net").innerText   = gross.toFixed(2);

 let paid=0;

 document.querySelectorAll(".payAmt").forEach(i=>{
  paid += parseFloat(i.value) || 0;
 });

 document.getElementById("paid").innerText = paid.toFixed(2);

 let balance = gross - paid;
 let returnAmt = 0;

 if(paid > gross){

 alert("Payment is greater than bill amount");
  returnAmt = paid - gross;
  balance = 0;
 }

 document.getElementById("balance").innerText   = balance.toFixed(2);
 document.getElementById("returnAmt").innerText = returnAmt.toFixed(2);

/* 🔥 ADD THIS */
highlightSummary();
}



/* PAYMENT */
document.querySelectorAll(".payCheck").forEach(chk=>{
 chk.addEventListener("change",function(){

  let net = parseFloat(document.getElementById("net").innerText) || 0;

  let input = document.querySelector(`.payAmt[data-mode='${chk.dataset.mode}']`);

  if(chk.checked){

    input.disabled = false;

    let utr = document.querySelector(
`.utrField[data-mode='${chk.dataset.mode}']`
);

if(utr){
   utr.disabled = false;
}

    let remaining = net;

    document.querySelectorAll(".payAmt").forEach(i=>{
      if(i !== input){
        remaining -= parseFloat(i.value) || 0;
      }
    });

    input.value = remaining > 0 ? remaining.toFixed(2) : 0;

  } else {
    input.value = 0;
    input.disabled = true;

    if(utr){
   utr.disabled = true;
   utr.value = '';
}
  }

  updateSummary();
 });
});

document.querySelectorAll(".payAmt").forEach(input=>{
  input.addEventListener("input", function(){

  if(parseFloat(this.value) < 0){
   this.value = 0;
}
    updateSummary(); // 🔥 ye missing tha
  });
});

function highlightSummary(){

 let balance = parseFloat(document.getElementById("balance").innerText) || 0;
 let ret = parseFloat(document.getElementById("returnAmt").innerText) || 0;

 let balEl = document.getElementById("balance").parentElement;
 let retEl = document.getElementById("returnAmt").parentElement;

 balEl.style.color = balance > 0 ? "red" : "#333";
 retEl.style.color = ret > 0 ? "green" : "#333";
}

highlightSummary();

function validateCustomer(){

  let cust   = document.querySelector("select[name='customer_id']").value;
  let name   = document.querySelector("input[name='manual_name']").value.trim();
  let phone  = document.querySelector("input[name='manual_phone']").value.trim();

  if(cust == "" && name == "" && phone == ""){
    alert("⚠️ Please select customer OR enter name & contact");
    return false;
  }

  return true;
}

/* TERMS TEMPLATE */

document.getElementById("termsTemplate")

.addEventListener("change", function(){

document.getElementById("termsBox").value =
this.value;

});

/* EXISTING ITEMS CALCULATE */

document.querySelectorAll("#billBody tr").forEach(r=>{
   calculate(r);
});

document.querySelectorAll(
"#billBody input"
).forEach(inp=>{

   inp.removeAttribute("readonly");
   inp.removeAttribute("disabled");

});

</script>
<?php include_once('layouts/footer.php'); ?>
