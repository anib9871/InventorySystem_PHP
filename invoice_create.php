<?php
require_once('includes/load.php');
$system = $_GET['system'] ?? 'billing';
//page_require_level(2);
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

$center_id = $_SESSION['center_id'] ?? 0;

if($_SESSION['role_id'] == 3){



$customers = find_by_sql("
SELECT * FROM customer_master
WHERE center_id = '$center_id'
");

}else{

$customers = find_all('customer_master');

}
$products = array_values(array_filter(join_product_table(), function($p){
    return $p['is_active'] == 1;
}));
$payment_modes = find_by_sql("SELECT id, mode_name FROM payment_mode_master WHERE is_active = 1");

/* TERMS & CONDITIONS */

$terms_templates = find_all('terms_conditions_master');

$rate_map = [];

    $rates = find_by_sql("
        SELECT r.product_id, r.rate, g.gst_percent
        FROM rate_master r
        LEFT JOIN gst_master g ON g.id = r.gst_id
        
    ");

    foreach($rates as $r){
        $rate_map[$r['product_id']] = $r;
    }



/* SAVE INVOICE */
if(isset($_POST['save_invoice'])){

if($_SESSION['role_id'] == 2){

   $center_id = (int)$_POST['center_id'];

}else{

   $center_id = (int)$_SESSION['center_id'];
}
  global $db;


  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$db->query("START TRANSACTION");

  $debug = [];

$debug[] = "========================";
$debug[] = "INVOICE CREATE DEBUG";
$debug[] = date("Y-m-d H:i:s");
$debug[] = "System : ".$system;
$debug[] = "POST : ".json_encode($_POST);

try {

/* ===== GET NEXT INVOICE NUMBER FROM SEQUENCE ===== */

/* ===== SAFE INVOICE NUMBER (MULTI-USER FIX) ===== */



/* 🔒 LOCK sequence row */
/* 🔒 LOCK sequence row */

$fy = find_by_sql("
SELECT fy_id, fy_name
FROM financial_year_master
LIMIT 1
");

$fy_id = $fy[0]['fy_id'];

$seq = find_by_sql("
SELECT *
FROM sequence_master
WHERE sequence_category='invoice'
AND fy_id='$fy_id'
LIMIT 1
");

if($seq){

    $seq = $seq[0];

    $next = $seq['last_no'] + 1;

$db->query("
UPDATE sequence_master
SET last_no = '$next'
WHERE sequence_category = 'invoice'
AND fy_id = '$fy_id'
");

}else{

    $next = 1;

    $db->query("
    INSERT INTO sequence_master
    (
        sequence_category,
        fy_id,
        last_no
    )
    VALUES
    (
        'invoice',
        '$fy_id',
        1
    )
    ");
}

$fy_name = substr($fy[0]['fy_name'], 2);

$inv_no = $fy_name . "/" . $next;


$cust  = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;

if($system != 'inventory'){

if($cust <= 0){

    $name  = remove_junk($db->escape($_POST['manual_name']));
    $phone = remove_junk($db->escape($_POST['manual_phone']));
    // $gst   = remove_junk($db->escape($_POST['manual_gst']));
    // $addr  = remove_junk($db->escape($_POST['manual_address']));

    if($name == ""){
        echo "<script>
alert('Please enter customer name');
window.location='invoice_create.php';
</script>";
exit;
    }

    /* 🔥 SMART LOGIC START */

    if($phone != ""){

        // ✅ contact se match
        $check = find_by_sql("SELECT id FROM customer_master WHERE contact_no='$phone' LIMIT 1");

        if($check){
            $cust = $check[0]['id'];
        }else{
            // new create
            $db->query("INSERT INTO customer_master
            (customer_name, contact_no, address, gst_no, center_id)
            VALUES
            (
            '$name',
            '$phone',
            '$addr',
            '$gst',
            '$center_id'
            )");

            $cust = $db->insert_id();
        }

    }else{

        // ❌ phone nahi → direct new customer
          $db->query("INSERT INTO customer_master
          (customer_name, contact_no, address, gst_no, center_id)
          VALUES
          (
          '$name',
           '',
          '$addr',
          '$gst',
          '$center_id'
          )");

        $cust = $db->insert_id();
    }

    /* 🔥 SMART LOGIC END */
}

}else{

    if($cust <= 0){
        echo "<script>
alert('Please select customer');
window.location='invoice_create.php';
</script>";
exit;
    }

}
  if(!isset($_SESSION['org_id'])){
    echo "<script>
    alert('Session expired. Please login again');
    window.location='index.php';
    </script>";
    exit;
}

$org_id = $_SESSION['org_id'];

  /* ===============================
   STATE CODE CHECK
================================ */

$org_data = find_by_sql("
SELECT gst_no 
FROM organization_master 
WHERE id = '{$org_id}'
");
$cust_data = find_by_sql("SELECT gst_no FROM customer_master WHERE id = $cust");

$cust_gst = '';

if(!empty($cust_data)){
    $cust_gst = $cust_data[0]['gst_no'] ?? '';
}

$org_gst = '';

if(!empty($org_data)){
    $org_gst = $org_data[0]['gst_no'] ?? '';
}

//$org_gst = $org_data[0]['gst_no'] ?? '';
// $cust_gst = $cust_data[0]['gst_no'] ?? '';

$org_state_code = substr($org_gst, 0, 2);
$cust_state_code = substr($cust_gst, 0, 2);

/* Determine GST Mode */
$tax_mode = 'CGST_SGST'; // default for services

if(isset($_POST['product_id']) && count($_POST['product_id']) > 0){

foreach($_POST['product_id'] as $pid){

  $product = find_by_id('products', $pid);

if(in_array($product['type'], [1,2])){// product

if(trim((string)$org_state_code) !== trim((string)$cust_state_code)){

    $tax_mode = 'IGST';

}else{

    $tax_mode = 'CGST_SGST';
}

break;
}
}
}

if (!empty($_POST['invoice_date'])) {

    $qdate = $_POST['invoice_date'];

    $formats = ['d/M/Y', 'd-m-Y', 'Y-m-d'];

    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $qdate);
        if ($dt instanceof DateTime) {
            $qdate = $dt->format('Y-m-d');
            break;
        }
    }

} else {

    $qdate = date('Y-m-d');

}

$gst_type = isset($_POST['gst_type'])
? $_POST['gst_type']
: 'inclusive';

  $subtotal  = 0;
  $net_total = 0;
  $total_gst = 0;

  /* ===============================
     INSERT MASTER
  ================================*/
$insertMaster = $db->query("
INSERT INTO invoice
(
invoice_no,
invoice_date,
customer_id,
organization_id,
quotation_id,
subtotal,
gst_total,
net_total,
paid_amount,
due_amount,
payment_status,
gst_type,
remarks,
terms_conditions,
created_at
)
VALUES
(
'$inv_no',
'$qdate',
'$cust',
'$org_id',
NULL,
0,
0,
0,
0,
0,
'Unpaid',
'$gst_type',
'',
'".$db->escape($_POST['terms_conditions'])."',
NOW()
)
");

  if(!$insertMaster){
      echo "<script>alert('Master Insert Error');window.location='invoice_create.php';</script>";
exit;
  }

  $qid = $db->insert_id();   // ✅ correct method

  $debug[] = "Invoice ID : ".$qid;

  if(!$qid){
      echo "<script>
alert('Something went wrong while saving invoice');
window.location='invoice_create.php';
</script>";
exit;
  }

  if(!isset($_POST['product_id']) || count($_POST['product_id']) == 0){
      $db->query("DELETE FROM invoice WHERE id = $qid");
      echo "<script>
alert('Something went wrong while saving invoice');
window.location='invoice_create.php';
</script>";
exit;
  }

  /* ===============================
     INSERT ITEMS
  ================================*/
  $itemInserted = false;

foreach($_POST['product_id'] as $i => $pid){

$pid = (int)$pid;

if($pid <= 0){
    continue;
}

$qty  = isset($_POST['qty'][$i]) ? (float)$_POST['qty'][$i] : 0;
$base = isset($_POST['rate'][$i]) ? (float)$_POST['rate'][$i] : 0;
$gst = 0;

if($gst_enabled == "Yes"){
   $gst = isset($_POST['gst'][$i])
          ? (float)$_POST['gst'][$i]
          : 0;
}
$disc = isset($_POST['discount'][$i]) ? (float)$_POST['discount'][$i] : 0;

/* ===== CHECK AVAILABLE STOCK ===== */
// | type | meaning         |
// | ---- | --------------- |
// | 1    | GRN             |
// | 2    | SALE            |
// | 3    | PURCHASE RETURN |
// | 4    | SALE RETURN     |
$product = find_by_id('products',$pid);

/* ===== ONLY PRODUCT ME STOCK CHECK ===== */
if($product['type'] == 1){

$stock_row = find_by_sql("
SELECT 
COALESCE(SUM(
CASE
WHEN transaction_type = 1 THEN quantity
WHEN transaction_type = 2 THEN -quantity
WHEN transaction_type = 3 THEN -quantity
WHEN transaction_type = 4 THEN quantity
END
),0) AS stock
FROM transaction_master
WHERE product_id = {$pid}
");

$current_stock = $stock_row[0]['stock'] ?? 0;

if($qty > $current_stock){

$db->query("DELETE FROM invoice WHERE id = $qid");

echo "<script>
alert('Stock not available. Available stock: ".$current_stock."');
window.history.back();
</script>";

exit;
}

} // 🔥 IMPORTANT: yahi close karo

/* ===== YE SAB SABKE LIYE CHALEGA (product + service) ===== */

if($qty <= 0 || $base <= 0){
    continue;
}

$itemInserted = true;

$line_base = $qty * $base;

/* 🔥 Discount */
$discounted_base = $line_base - $disc;

if($gst_type == "exclusive"){

    if($tax_mode == 'IGST'){
        $igst_amount = $discounted_base * $gst / 100;
        $cgst_amount = 0;
        $sgst_amount = 0;
        $gst_amount  = $igst_amount;
    }else{
        $cgst_amount = ($discounted_base * $gst / 100) / 2;
        $sgst_amount = ($discounted_base * $gst / 100) / 2;
        $igst_amount = 0;
        $gst_amount  = $cgst_amount + $sgst_amount;
    }

    $rate_incl  = $base + ($base * $gst / 100);
    $line_total = $discounted_base + $gst_amount;

}else{

    $gst_amount = $discounted_base - ($discounted_base / (1 + $gst/100));

    if($tax_mode == 'IGST'){
        $igst_amount = $gst_amount;
        $cgst_amount = 0;
        $sgst_amount = 0;
    }else{
        $cgst_amount = $gst_amount / 2;
        $sgst_amount = $gst_amount / 2;
        $igst_amount = 0;
    }

    $rate_incl  = $base;
    $line_total = $discounted_base;
}

$total_gst += $gst_amount;

      $subtotal  += $line_base;
      $net_total += $line_total;
      $insertItem = $db->query("
      INSERT INTO invoice_items
      (invoice_id, product_id, qty, rate_excl_gst,
      discount_amount, gst_percent, rate_incl_gst, 
      cgst_amount, sgst_amount, igst_amount, line_total)
      VALUES
      ($qid, $pid, $qty, $base,
      $disc, $gst, $rate_incl,
      $cgst_amount, $sgst_amount, $igst_amount, $line_total)
      ");

      if(!$insertItem){
          $db->query("DELETE FROM invoice WHERE id = $qid");
          echo "<script>
alert('Item Insert Error');
window.location='invoice_create.php';
</script>";
exit;
      }

      /* ================= TRANSACTION MASTER ENTRY ================= */

$trans = $db->query("
INSERT INTO transaction_master
(
product_id,
supplier_id,
bill_indent_no,
entry_date,
bill_indent_date,
quantity,
free_qty,
unit,
rate_id,
gst_id,
unit_price,
gst_amount,
discount_amount,
net_price,
mrp,
misc_amount,
sale_amount,
sale_gst,
sale_net,
transaction_type,
status,
payment_status,
payment_mode,
amount_received,
balance_amount,
from_dept,
to_dept,
comments,
created_at,
center_id
)
VALUES
(
'$pid',
NULL,
'$inv_no',
NOW(),
NOW(),
'$qty',
0,
'PCS',
0,
0,
'$base',
'$gst_amount',
'$disc',
'$line_total',
0,
0,
'$discounted_base',
'$gst_amount',
'$line_total',
2,
1,
0,
NULL,
0,
0,
'STORE',
'CUSTOMER',
'Sale Invoice',
NOW(),
'$center_id'
)
");

if(!$trans){
    echo "<script>alert('Transaction Error');window.location='invoice_create.php';</script>";
exit;
}
}


  if(!$itemInserted){
      $db->query("DELETE FROM invoice WHERE id = $qid");
      echo "<script>
alert('Please select at least one valid product');
window.location='invoice_create.php';
</script>";
exit;
  }

  /* ===============================
     UPDATE TOTALS
  ================================*/


$total_paid = 0;

/* Payment Amount Calculate */
if(isset($_POST['payment_amount']) && is_array($_POST['payment_amount'])){

    foreach($_POST['payment_amount'] as $amt){

        $total_paid += (float)$amt;

    }

}

/* Due Amount */
$due_amount = round($net_total - $total_paid,2);

if($due_amount <= 0){

    $due_amount = 0;
    $payment_status = "Paid";

}elseif($total_paid > 0){

    $payment_status = "Partial";

}else{

    $payment_status = "Unpaid";

}

$debug[] = "Net Total : ".$net_total;
$debug[] = "Total Paid : ".$total_paid;
$debug[] = "Due Amount : ".$due_amount;
$debug[] = "Payment Status : ".$payment_status;

$gst_total = $total_gst;



$update = $db->query("
UPDATE invoice SET

subtotal = '$subtotal',
gst_total = '$gst_total',
net_total = '$net_total',

paid_amount = '$total_paid',
due_amount = '$due_amount',
payment_status = '$payment_status'

WHERE id = '$qid'
");

/* 👇 YE CODE YAHI INSERT KARO */
if(!$update){
    throw new Exception("Invoice Update Failed : ".$db->error);
}

if($system == 'billing' && isset($_POST['payment_amount'])){

  foreach($_POST['payment_amount'] as $mode_id => $amt){

    $amt = (float)$amt;

    if($amt > 0){

      // 🔥 get payment mode name
      $pm = find_by_id('payment_mode_master', $mode_id);
      $mode_name = $pm['mode_name'];

// 🔥 insert into NEW payments table

$utr = $_POST['utr_no'][$mode_id] ?? '';

$db->query("
INSERT INTO payments
(
invoice_id,
customer_id,
payment_mode,
amount,
reference_no,
center_id
)
VALUES
(
$qid,
$cust,
'$mode_name',
'$amt',
'".$db->escape($utr)."',
'$center_id'
)
");

    }
  }
}

// ================= LEDGER ENTRY =================

// 🔹 1. CUSTOMER DEBIT (invoice total)
$db->query("
INSERT INTO ledger_entries (invoice_id, customer_id, account, type, amount)
VALUES ($qid, $cust, 'CUSTOMER', 'DEBIT', '$net_total')
");

// 🔥 UPDATE CUSTOMER BALANCE (INVOICE)
$db->query("
UPDATE customer_master 
SET balance = balance + $net_total
WHERE id = $cust
");

// 🔹 2. SALES CREDIT
$db->query("
INSERT INTO ledger_entries (invoice_id, customer_id, account, type, amount)
VALUES ($qid, $cust, 'SALES', 'CREDIT', '$net_total')
");


// 🔹 3. PAYMENT ENTRIES
if($system == 'billing' && isset($_POST['payment_amount'])){

  foreach($_POST['payment_amount'] as $mode_id => $amt){

    $amt = (float)$amt;

    if($amt > 0){

      $pm = find_by_id('payment_mode_master', $mode_id);
      $mode_name = strtoupper($pm['mode_name']);

      // CASH / UPI DEBIT
      $db->query("
      INSERT INTO ledger_entries (invoice_id, customer_id, account, type, amount)
      VALUES ($qid, $cust, '$mode_name', 'DEBIT', '$amt')
      ");

      // CUSTOMER CREDIT
      $db->query("
      INSERT INTO ledger_entries (invoice_id, customer_id, account, type, amount)
      VALUES ($qid, $cust, 'CUSTOMER', 'CREDIT', '$amt')
      ");

      // 🔥 CUSTOMER BALANCE REDUCE (PAYMENT)
      $db->query("
      UPDATE customer_master 
      SET balance = balance - $amt
      WHERE id = $cust
      ");

    }
  }
}

$db->query("COMMIT");  // 🔥 FINAL COMMIT

file_put_contents(
    __DIR__.'/invoice_debug.log',
    implode(PHP_EOL,$debug).PHP_EOL.PHP_EOL,
    FILE_APPEND
);

// Direct print page par redirect karo (bina mail bheje)
echo "<script>
window.location='invoice_print.php?id=".$qid."';
</script>";
exit;

} catch(Exception $e){

  $db->query("ROLLBACK");

  $debug[] = "EXCEPTION : ".$e->getMessage();

file_put_contents(
    __DIR__.'/invoice_debug.log',
    implode(PHP_EOL,$debug).PHP_EOL.PHP_EOL,
    FILE_APPEND
);

  echo "<script>
alert(" . json_encode($e->getMessage()) . ");
window.location='invoice_create.php';
</script>";

exit;
}

} // POST['save_invoice'] condition close

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
<form method="post" target="_blank" onsubmit="return validateCustomer()">

<!-- CUSTOMER -->
<div class="card p-3 mb-3 top-filter-card">

<div class="col-md-1 d-flex flex-column justify-content-center" style="min-width:180px;">
    <label>Invoice Date</label>

<input
    type="text"
    name="invoice_date"
    id="invoice_date"
    class="form-control"
    value="<?= date('d/M/Y'); ?>"
    autocomplete="off">
</div>

 
<div class="row align-items-end g-2">


   <div class="col-md-3 d-flex flex-column justify-content-center">
      <label>Customer</label>
      <select name="customer_id" class="form-control">
        <option value="">Select Customer</option>
        <?php foreach($customers as $c): ?>
<option value="<?=$c['id'];?>">
<?=$c['customer_name'];?>
</option>
        <?php endforeach; ?>
      </select>
    </div>

<?php if($system == 'billing'): ?> <div class="col-md-4 d-flex flex-column justify-content-center">
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

<div class="col-md-2 d-flex flex-column justify-content-center">
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
name="save_invoice"
class="btn btn-success top-save-btn">

💾 Save Invoice

</button>

</div>

<div class="col-md-2 d-flex flex-column justify-content-end">

<label style="visibility:hidden;">Stock</label>

<div id="stockInfo"
style="
height:38px;
display:flex;
align-items:center;
justify-content:center;
background:#fff;
border:1px solid #dbe2ea;
border-radius:8px;
font-weight:600;
padding:0 10px;
white-space:nowrap;
overflow:hidden;
text-overflow:ellipsis;
">
No Product Selected
</div>

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

          <tr>

            <td width="30">
              <input type="checkbox"
              class="payCheck"
              data-mode="<?=$pm['id'];?>">
            </td>

            <td>
              <?= strtoupper($pm['mode_name']); ?>
            </td>

            <td>

             <input type="number"
                step="0.01"
                min="0"
                name="payment_amount[<?=$pm['id'];?>]"
                class="form-control form-control-sm payAmt"
                disabled
                data-mode="<?=$pm['id'];?>"
                value="0">

              <?php if(strtolower($pm['mode_name']) != 'cash'): ?>

              <input type="text"
              name="utr_no[<?=$pm['id'];?>]"
              class="form-control form-control-sm mt-1 utrField"
              placeholder="Enter UTR No"
              disabled
              data-mode="<?=$pm['id'];?>">

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

          <!-- EXISTING ITEMS -->

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

          <?= htmlspecialchars($t['template_name']); ?>

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


<?php include_once('layouts/footer.php'); ?>


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

let shortName = p.name.length > 20
? p.name.substring(0,20) + "..."
: p.name;

document.getElementById("stockInfo").innerHTML =
`Available Stock: ${Math.floor(Number(p.current_stock || 0))} PCS`;

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
 <td class="productName" data-product-id="${p.id}">
${p.name}
<input type="hidden" name="product_id[]" value="${p.id}">
</td>

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

 }

});


/* REMOVE FIX */
document.addEventListener("click", function(e){

   if(e.target.classList.contains("remove")){

      let row = e.target.closest("tr");
      row.remove();

      updateSummary();

      let rows = document.querySelectorAll("#billBody tr");

      if(rows.length === 0){

         document.getElementById("stockInfo").innerHTML =
         "No Product Selected";

      }else{

         let lastRow = rows[rows.length - 1];

let productId =
lastRow.querySelector(".productName").dataset.productId;

let productObj =
products.find(p => p.id == productId);

if(productObj){

   document.getElementById("stockInfo").innerHTML =
   `Available Stock: ${Math.floor(Number(productObj.current_stock || 0))} PCS`;
}
      }
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

 let afterDisc = total - dAmt;

/* GST TYPE */
let gstType = "exclusive";

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

    let utr = document.querySelector(`.utrField[data-mode='${chk.dataset.mode}']`);

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

document.addEventListener("DOMContentLoaded", function () {

    flatpickr("#invoice_date", {
        dateFormat: "d/M/Y",
        allowInput: false,
        disableMobile: true
    });

});

</script>
