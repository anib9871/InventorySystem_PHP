
<?php
require_once('includes/load.php');

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($id <= 0){
    die("Invalid Quotation ID");
}

/* ================= FETCH QUOTATION ================= */

$qdata = find_by_sql("
SELECT *
FROM quotation_master
WHERE id = $id
");

if(!$qdata){
    die("Quotation ID Not Found : ".$id);
}

$quote = $qdata[0];

/* ================= FETCH ITEMS ================= */

$items = find_by_sql("
SELECT qi.*, p.name
FROM quotation_items qi
LEFT JOIN products p ON p.id = qi.product_id
WHERE qi.quotation_id = $id
");

/* ================= MASTER DATA ================= */

$customers = find_all('customer_master');
$products  = join_product_table();

$terms_templates = find_all('terms_conditions_master');

$gst_enabled = "Yes";

/* ================= UPDATE ================= */

if(isset($_POST['update_quotation'])){

global $db;

$db->query("START TRANSACTION");

try{

$cust = (int)$_POST['customer_id'];

$gst_type = $_POST['gst_type'] ?? 'exclusive';

$subtotal  = 0;
$net_total = 0;
$total_gst = 0;

/* ================= DELETE OLD ITEMS ================= */

$db->query("
DELETE FROM quotation_items
WHERE quotation_id = $id
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
INSERT INTO quotation_items
(
quotation_id,
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

}

/* ================= UPDATE MASTER ================= */

$db->query("
UPDATE quotation_master SET

customer_id = '$cust',
gst_type = '$gst_type',

subtotal = '$subtotal',
gst_total = '$total_gst',
net_total = '$net_total',

terms_conditions = '".$db->escape($_POST['terms_conditions'])."'

WHERE id = '$id'
");

$db->query("COMMIT");

echo "
<script>
window.location='quotation_list.php?print_id=".$id."';
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

/* =========================
   MAIN CARDS
========================= */

.card{
    border:none;
    border-radius:18px;
    background:#fff;
    box-shadow:0 4px 18px rgba(15,23,42,.06);
}

/* =========================
   FORM CONTROLS
========================= */

.form-control,
.form-control-sm{
    height:38px;
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

/* =========================
   GRID GAP FIX
========================= */

.row.align-items-stretch{
    --bs-gutter-x:10px !important;
}

.col-lg-3{
    padding-right:5px !important;
}

.col-lg-9{
    padding-left:5px !important;
}

/* =========================
   LEFT PANEL
========================= */

.left-panel{
    position:sticky;
    top:10px;
    height:100%;
}

/* =========================
   PRODUCT CARD
========================= */

.product-card{
    height:220px;
    overflow:hidden;
}

#productSearch{
    height:38px;
    margin-bottom:8px;
}

/* =========================
   PRODUCT SCROLL
========================= */

.product-scroll{
    height:160px;
    overflow-y:auto;
    overflow-x:hidden;
    border:1px solid #eef2f7;
    border-radius:10px;
}

.product-scroll::-webkit-scrollbar{
    width:6px;
}

.product-scroll::-webkit-scrollbar-thumb{
    background:#cbd5e1;
    border-radius:10px;
}

/* =========================
   PRODUCT TABLE
========================= */

.product-scroll table{
    width:100%;
    margin-bottom:0 !important;
    border-collapse:collapse;
}

#productList tr{
    cursor:pointer;
    transition:.2s;
}

#productList tr:hover{
    background:#eef4ff;
}

#productList th{
    background:#0f172a;
    color:#fff;
    position:sticky;
    top:0;
    z-index:10;
    padding:12px 10px !important;
    font-size:13px;
    border:none !important;
    white-space:nowrap;
}

#productList td{
    padding:12px 10px !important;
    font-size:13px;
    white-space:nowrap;
    border-color:#eef2f7 !important;
}

/* =========================
   TOP CARD
========================= */

.card.p-3.mb-3{
    padding:16px 18px !important;
}

.top-row-fix{
    min-height:58px;
}

.top-row-fix label{
    font-size:13px;
    font-weight:600;
    margin-bottom:6px;
}

.top-row-fix .form-control{
    height:40px;
}

.top-row-fix .btn{
    height:40px;
    margin-top:20px;
    border-radius:10px !important;
    font-weight:600;
}

/* =========================
   BILL GRID
========================= */

.bill-grid{
    background:#fff;
    border-radius:18px;
    min-height:220px;
    max-height:220px;
    overflow-y:auto;
    overflow-x:hidden;
    box-shadow:0 4px 18px rgba(15,23,42,.06);
    position:relative;
}

.bill-grid::-webkit-scrollbar{
    height:7px;
    width:7px;
}

.bill-grid::-webkit-scrollbar-thumb{
    background:#cbd5e1;
    border-radius:10px;
}

/* =========================
   ITEM TABLE
========================= */

#itemTable{
    width:100%;
    border-collapse:separate;
    border-spacing:0;
    table-layout:auto;
}

/* HEADER */

#itemTable thead th{
    position:sticky;
    top:0;
    background:#0f172a !important;
    color:#fff;
    border:none !important;
    padding:14px 10px;
    font-size:13px;
    white-space:nowrap;
    z-index:99;
}

/* BODY */

#itemTable tbody tr{
    transition:.2s;
}

#itemTable tbody tr:hover td{
    background:#eef4ff;
}

#itemTable td{
    background:#f8fafc;
    border:none !important;
    padding:12px 8px;
    vertical-align:middle;
    white-space:nowrap;
    height:72px;
}

/* COLUMN WIDTHS */

/* COLUMN WIDTHS */

#itemTable th:nth-child(1),
#itemTable td:nth-child(1){
    width:28%;
}

#itemTable th:nth-child(2),
#itemTable td:nth-child(2){
    width:9%;
}

#itemTable th:nth-child(3),
#itemTable td:nth-child(3){
    width:14%;
}

#itemTable th:nth-child(4),
#itemTable td:nth-child(4){
    width:10%;
}

#itemTable th:nth-child(5),
#itemTable td:nth-child(5){
    width:12%;
}

#itemTable th:nth-child(6),
#itemTable td:nth-child(6){
    width:10%;
}

#itemTable th:nth-child(7),
#itemTable td:nth-child(7){
    width:12%;
}

#itemTable th:nth-child(8),
#itemTable td:nth-child(8){
    width:15%;
}

#itemTable th:last-child,
#itemTable td:last-child{
    width:50px;
}

/* INPUTS */

#itemTable input{
    width:100%;
    min-width:70px;
    height:38px;
    border-radius:8px;
    border:1px solid #dbe2ea;
    background:#fff;
    font-size:13px;
}

/* REMOVE BUTTON */

.remove{
    width:34px;
    height:34px;
    border-radius:8px !important;
    padding:0;
}

/* =========================
   SUMMARY CARD
========================= */

.summary-card{
    background:#fff;
    border-radius:16px;
    padding:14px !important;
    margin-top:14px;
    min-height:250px;
    box-shadow:0 3px 12px rgba(15,23,42,.05);
}

.summary-row{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:8px 2px;
    border-bottom:1px dashed #e2e8f0;
    font-size:13px;
}

.summary-row:last-child{
    border-bottom:none;
}

.summary-row strong{
    font-size:13px;
    font-weight:600;
}

.summary-card label{
    font-size:13px;
    font-weight:600;
    margin-bottom:5px;
}

.summary-card .form-control{
    height:38px;
    font-size:13px;
}

/* =========================
   TERMS BOX
========================= */

#termsBox{
    min-height:90px;
    resize:none;
    padding:10px;
}

/* =========================
   RESPONSIVE
========================= */

@media(max-width:991px){

    .left-panel{
        position:relative;
        top:0;
    }

    .bill-grid{
        max-height:none;
    }

    .summary-card .col-lg-4{
        border-right:none !important;
        margin-bottom:20px;
    }
}

</style>

<div class="card-body">

<form method="post">

<div class="row align-items-stretch">

<!-- LEFT -->
<div class="col-lg-3 left-panel">

<div class="card p-3 product-card">

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

<!-- RIGHT -->
<div class="col-lg-9">

<!-- TOP -->
<div class="card p-3 mb-3">

<div class="row g-3 align-items-center top-row-fix">

<div class="col-md-5">

<label>Customer</label>

<select name="customer_id"
class="form-control">

<?php foreach($customers as $c): ?>

<option value="<?=$c['id'];?>"
<?=$c['id']==$quote['customer_id']?'selected':'';?>>

<?=$c['customer_name'];?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="col-md-3">

<label>GST Type</label>

<select name="gst_type" class="form-control">

<option value="exclusive"
<?=$quote['gst_type']=="exclusive"?'selected':'';?>>
Exclusive GST
</option>

<option value="inclusive"
<?=$quote['gst_type']=="inclusive"?'selected':'';?>>
Inclusive GST
</option>

<option value="nogst"
<?=$quote['gst_type']=="nogst"?'selected':'';?>>
No GST
</option>

</select>

</div>

<div class="col-md-3">

<button
type="submit"
name="update_quotation"
class="btn btn-success w-100">

💾 Update Quotation

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
class="form-control form-control-sm discPer"
value="0">
</td>

<td>
<input type="number"
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
<div class="card border-0 shadow-sm rounded-4 p-3 summary-card">

<div class="row">

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
value="<?= htmlspecialchars($t['template']); ?>"
<?= trim($quote['terms_conditions']) == trim($t['template']) ? 'selected' : ''; ?>>

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
class="form-control"><?= htmlspecialchars($quote['terms_conditions']); ?></textarea>
</div>

</div>

</div>

</div>

</div>

</form>

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
class="form-control form-control-sm discPer"
value="0">
</td>

<td>
<input type="number"
name="discount[]"
class="form-control form-control-sm discAmt"
value="0">
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
`;

 document.getElementById("billBody").appendChild(row);

 calculate(row);
}

/* EVENTS */

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

document.addEventListener("change",function(e){

 if(e.target.name=="gst_type"){

   document.querySelectorAll("#billBody tr").forEach(r=>{
      calculate(r);
   });

 }

});

document.addEventListener("click",function(e){

 if(e.target.classList.contains("remove")){

   e.target.closest("tr").remove();

   updateSummary();

 }

});

/* CALCULATION */

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

/* TERMS */

document.getElementById("termsTemplate")
.addEventListener("change",function(){

 document.getElementById("termsBox").value=this.value;

});

/* EXISTING ITEMS */

document.querySelectorAll("#billBody tr").forEach(r=>{
   calculate(r);
});

window.addEventListener("load", function(){

    let termsText =
    document.getElementById("termsBox").value.trim();

    let ddl =
    document.getElementById("termsTemplate");

    for(let i=0;i<ddl.options.length;i++){

        if(
            ddl.options[i].value.trim() === termsText
        ){
            ddl.selectedIndex = i;
            break;
        }
    }

});

</script>

<?php include_once('layouts/footer.php'); ?>
