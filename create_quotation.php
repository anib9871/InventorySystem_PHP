<?php
require_once('includes/load.php');
//page_require_level(2);
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

$customers = find_all('customer_master');
$products  = join_product_table();

/* TERMS & CONDITIONS */

$terms_templates = find_all('terms_conditions_master');
// $orgs = find_all('organization_master');

/* SAVE QUOTATION */
if(isset($_POST['save_quotation'])){
  global $db;

  $db->query("START TRANSACTION");
/* ===== GET NEXT QUOTATION NUMBER FROM SEQUENCE ===== */

$fy = find_by_sql("
SELECT fy_id, fy_name
FROM financial_year_master
WHERE is_active = 1
LIMIT 1
");

$fy_id = $fy[0]['fy_id'];

$seq = find_by_sql("
SELECT last_no, fy_id
FROM sequence_master
WHERE sequence_category='quotation'
AND fy_id = '$fy_id'
LIMIT 1
FOR UPDATE
");

if($seq){

    $seq = $seq[0];

    $fy_id = $seq['fy_id'];

    $next = $seq['last_no'] + 1;

    $db->query("
        UPDATE sequence_master
        SET last_no = '$next'
        WHERE sequence_category='quotation'
        AND fy_id = '$fy_id'
    ");

}else{

    // sequence nahi mili to auto create karo
    $fy = find_by_sql("
        SELECT fy_id, fy_name
        FROM financial_year_master
        LIMIT 1
    ");

    $fy_id = $fy[0]['fy_id'];

    $db->query("
        INSERT INTO sequence_master
        (
            sequence_category,
            fy_id,
            last_no
        )
        VALUES
        (
            'quotation',
            '$fy_id',
            1
        )
    ");

    $next = 1;
}

/* FY SHORT FORMAT */
$fy = find_by_sql("
SELECT fy_name
FROM financial_year_master
WHERE fy_id = '$fy_id'
LIMIT 1
");

$fy_name = substr($fy[0]['fy_name'], 2);

/* Generate quotation number */
$qno = $fy_name . "/" . $next;
  $cust  = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
 $org_id = $_SESSION['org_id'];

if(!isset($_SESSION['org_id'])){
    die("Invalid Session Organization");
}

  if($cust <= 0){
      die("Invalid Customer Selected");
  }

  $qdate = $_POST['quotation_date'] ?? date('d/M/Y');

$formats = ['d/M/Y','d-m-Y','Y-m-d'];

foreach($formats as $format){
    $dt = DateTime::createFromFormat($format, $qdate);
    if($dt){
        $qdate = $dt->format('Y-m-d');
        break;
    }
}
  $gst_type = ($gst_enabled == "Yes")
            ? ($_POST['gst_type'] ?? 'exclusive')
            : 'exclusive';

  $subtotal  = 0;
  $net_total = 0;

  /* ================= TAX MODE DETECT ================= */
$org_data  = find_by_sql("SELECT gst_no FROM organization_master WHERE id = $org_id");
$cust_data = find_by_sql("SELECT gst_no FROM customer_master WHERE id = $cust");

$org_gst  = $org_data ? $org_data[0]['gst_no'] : '';
$cust_gst = $cust_data ? $cust_data[0]['gst_no'] : '';

$org_state  = substr($org_gst,0,2);
$cust_state = substr($cust_gst,0,2);

$tax_mode = ($org_state == $cust_state) ? 'CGST_SGST' : 'IGST';

  /* ===============================
     INSERT MASTER
  ================================*/
  $insertMaster = $db->query("
    INSERT INTO quotation_master
    (quotation_no, quotation_date, customer_id, organization_id,
     subtotal,
gst_total,
net_total,
remarks,
terms_conditions,
created_at)
    VALUES
    ('$qno','$qdate','$cust', '$org_id',
     0,
0,
0,
'',
'".$db->escape($_POST['terms_conditions'])."',
NOW())
  ");

  if(!$insertMaster){
      die("Master Insert Error");
  }

  $qid = $db->insert_id();   // ✅ correct method

  if(!$qid){
      die("Master insert failed");
  }

  if(!isset($_POST['product_id']) || count($_POST['product_id']) == 0){
      $db->query("DELETE FROM quotation_master WHERE id = $qid");
      die("No products selected");
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

      if($qty <= 0 || $base <= 0){
          continue;
      }

      $itemInserted = true;

$line_base = $qty * $base;

/* 🔥 Discount pehle minus hoga */
$discounted_base = $line_base - $disc;

if($gst_enabled == "No"){

    $gst_amount = 0;
    $cgst_amount = 0;
    $sgst_amount = 0;
    $igst_amount = 0;

    $rate_incl  = $base;
    $line_total = $discounted_base;

}
else if($gst_type == "exclusive"){

    $gst_amount = $discounted_base * $gst / 100;

    if($tax_mode == 'IGST'){
        $igst_amount = $gst_amount;
        $cgst_amount = 0;
        $sgst_amount = 0;
    } else {
        $cgst_amount = $gst_amount / 2;
        $sgst_amount = $gst_amount / 2;
        $igst_amount = 0;
    }

    $rate_incl  = $base + ($base * $gst / 100);
    $line_total = $discounted_base + $gst_amount;

} else {

    $gst_amount = $discounted_base - ($discounted_base / (1 + $gst/100));

    if($tax_mode == 'IGST'){
        $igst_amount = $gst_amount;
        $cgst_amount = 0;
        $sgst_amount = 0;
    } else {
        $cgst_amount = $gst_amount / 2;
        $sgst_amount = $gst_amount / 2;
        $igst_amount = 0;
    }

    $rate_incl  = $base;
    $line_total = $discounted_base;
}
      $subtotal  += $line_base;
      $net_total += $line_total;

$insertItem = $db->query("
INSERT INTO quotation_items
(quotation_id, product_id, qty, rate_excl_gst,
 discount_amount, gst_percent, rate_incl_gst, line_total,
 cgst_amount, sgst_amount, igst_amount)
VALUES
($qid, $pid, $qty, $base,
 $disc, $gst, $rate_incl, $line_total,
 $cgst_amount, $sgst_amount, $igst_amount)
");

if(!$insertItem){
    $db->query("DELETE FROM quotation_master WHERE id = $qid");
    die("Item Insert Error");
}
  }

  if(!$itemInserted){
      $db->query("DELETE FROM quotation_master WHERE id = $qid");
      die("Please select at least one valid product");
  }

  $gst_total = $net_total - $subtotal;

  /* ===============================
     UPDATE TOTALS
  ================================*/
  $db->query("
    UPDATE quotation_master SET
    subtotal = '$subtotal',
    gst_total = '$gst_total',
    net_total = '$net_total'
    WHERE id = '$qid'
  ");

  $db->query("COMMIT");

echo "<script>
window.location='quotation_list.php?print_id=".$qid."';
</script>";
}

?>

<?php include_once('layouts/header.php'); ?>

<form method="post" onsubmit="return validateQuotation();">




<!-- LEFT -->

<div class="row align-items-stretch">

<!-- LEFT -->
<div class="col-lg-3 mb-3">

<div class="left-panel">

<!-- PRODUCT -->
<div class="card p-3 product-card mb-3">

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

</div>

</div>

<!-- RIGHT -->
<div class="col-lg-9">

<!-- TOP CARD -->
<div class="card p-3 mb-3">

<div class="row g-3 align-items-center top-row-fix">

<div class="col-md-2">

<label>Quotation Date</label>

<input
type="text"
id="quotation_date"
name="quotation_date"
class="form-control"
value="<?= date('d/M/Y'); ?>"
autocomplete="off">

</div>

<div class="col-md-3">

<label>Customer</label>

<select name="customer_id"
class="form-control"
required>

<option value="">Select Customer</option>

<?php foreach($customers as $c): ?>

<option value="<?=$c['id'];?>">

<?=$c['customer_name'];?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="col-md-3">

<label>GST Type</label>

<select name="gst_type" class="form-control">

<option value="exclusive">
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

<div class="col-md-2">

<button
type="submit"
name="save_quotation"
class="btn btn-success w-100">

💾 Save Quotation

</button>

</div>

</div>

</div>

<br>

<!-- BILL GRID -->
<div class="bill-grid mb-3">

<table class="table table-bordered mb-0" id="itemTable">

<thead>

<tr>

<th>Product</th>
<th width="90">Qty</th>
<th width="120">Price</th>

<?php if($gst_enabled == "Yes"): ?>

<th width="90">GST%</th>
<th width="110">GST</th>

<?php endif; ?>

<th width="90">Disc%</th>
<th width="110">Disc ₹</th>
<th width="130">Total</th>
<th width="60"></th>

</tr>

</thead>

<tbody id="billBody"></tbody>

</table>

</div>

<!-- SUMMARY -->
<div class="card border-0 shadow-sm rounded-4 p-3 mt-3 summary-card">

<div class="row">

<!-- LEFT -->
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

</div>

<!-- RIGHT -->
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

  tr.innerHTML=`
   <td>${p.name}</td>
   <td>₹${p.sale_price}</td>
  `;

  tr.onclick=()=>addProduct(p);

  list.appendChild(tr);

 });

}

renderProducts();

document.getElementById("productSearch")
.addEventListener("input",e=>renderProducts(e.target.value));

/* ADD PRODUCT */

function addProduct(p){

 let rows=document.querySelectorAll("#billBody tr");

 for(let r of rows){

   let name=r.querySelector("td").innerText.trim();

   if(name===p.name){

      let qty=r.querySelector(".qty");

      qty.value=parseInt(qty.value||0)+1;

      calculate(r);

      return;
   }
 }

 let row=document.createElement("tr");

 row.innerHTML=`

<td>
${p.name}
<input type="hidden" name="product_id[]" value="${p.id}">
</td>

<td>
<input type="number"
name="qty[]"
class="form-control form-control-sm qty"
value="1">
</td>

<td>
<input type="number"
name="rate[]"
class="form-control form-control-sm base"
value="${p.sale_price}">
</td>

<?php if($gst_enabled == "Yes"): ?>

<td>

<input type="number"
name="gst[]"
class="form-control form-control-sm gst"
value="${p.gst_percent}">

</td>

<td>

<input type="text"
class="form-control form-control-sm gstAmt"
readonly>

</td>

<?php endif; ?>

<td>

<input type="number"
step="0.01"
min="0"
class="form-control form-control-sm discPer"
value="0.00">

</td>

<td>

<input type="number"
step="0.01"
min="0"
name="discount[]"
class="form-control form-control-sm discAmt"
value="0.00">

</td>

<td>

<input type="text"
class="form-control form-control-sm totalRow"
readonly>

</td>

<td>

<button
type="button"
class="btn btn-danger btn-sm remove">

×

</button>

</td>
`;





 document.getElementById("billBody").appendChild(row);

 calculate(row);
}

/* INPUT */

document.addEventListener("input",function(e){

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

/* GST TYPE */

document.addEventListener("change",function(e){

 if(e.target.name=="gst_type"){

   document.querySelectorAll("#billBody tr").forEach(r=>{
      calculate(r);
   });

 }

});

/* REMOVE */

document.addEventListener("click",function(e){

 if(e.target.classList.contains("remove")){

   e.target.closest("tr").remove();

   updateSummary();

 }

});

/* CALCULATE */

function calculate(r){

 let qty=parseFloat(r.querySelector(".qty").value)||0;

 let base=parseFloat(r.querySelector(".base").value)||0;

 let gst=0;

 let gstField=r.querySelector(".gst");

 if(gstField){
    gst=parseFloat(gstField.value)||0;
 }

 let dPer=parseFloat(r.querySelector(".discPer").value)||0;

 let dAmt=parseFloat(r.querySelector(".discAmt").value)||0;

 let total=qty*base;

 let active=document.activeElement;

if(active && active.classList.contains("discPer")){

    dAmt=(total*dPer)/100;

    r.querySelector(".discAmt").value=dAmt.toFixed(2);

 }
 else if(active && active.classList.contains("discAmt")){

    dPer=total>0?(dAmt/total)*100:0;

    r.querySelector(".discPer").value=dPer.toFixed(2);

 }

 let afterDisc=total-dAmt;

 let gstType=document.querySelector("select[name='gst_type']").value;

 let gstAmt=0;

 let final=0;

 if(gstType=="nogst"){

    gstAmt=0;
    final=afterDisc;

 }
 else if(gstType=="exclusive"){

    gstAmt=(afterDisc*gst)/100;

    final=afterDisc+gstAmt;

 }
 else{

    gstAmt=afterDisc-(afterDisc*100/(100+gst));

    final=afterDisc;

 }

 let gstAmtField=r.querySelector(".gstAmt");

 if(gstAmtField){
    gstAmtField.value=gstAmt.toFixed(2);
 }

 r.querySelector(".totalRow").value=final.toFixed(2);

 updateSummary();

}

/* SUMMARY */

function updateSummary(){

 let gross=0;

 document.querySelectorAll(".totalRow").forEach(t=>{
   gross+=parseFloat(t.value)||0;
 });

 document.getElementById("gross").innerText=gross.toFixed(2);

 document.getElementById("net").innerText=gross.toFixed(2);

}

/* VALIDATION */

function validateQuotation(){

 let rows=document.querySelectorAll("#billBody tr");

 if(rows.length<=0){

   alert("Please add at least one product");

   return false;
 }

 return true;

}

/* TERMS */

document.getElementById("termsTemplate")
.addEventListener("change",function(){

 document.getElementById("termsBox").value=this.value;

});

</script>

</form>

<script>
document.addEventListener("DOMContentLoaded", function () {

    flatpickr("#quotation_date", {
        dateFormat: "d/M/Y",
        allowInput: false,
        disableMobile: true
    });

});
</script>

<?php include_once('layouts/footer.php'); ?>
