<?php
require_once('includes/load.php');
page_require_level(2);
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

$customers = find_all('customer_master');
$products  = join_product_table();
$orgs = find_all('organization_master');

/* SAVE QUOTATION */
if(isset($_POST['save_quotation'])){
  global $db;
/* ===== GET NEXT QUOTATION NUMBER FROM SEQUENCE ===== */

$seq = find_by_sql("
SELECT last_no 
FROM sequence_master 
WHERE sequence_category='quotation'
");

$next = $seq[0]['last_no'] + 1;

/* Generate quotation number */
$qno = "Q".str_pad($next,5,"0",STR_PAD_LEFT);

/* Update sequence table */
$db->query("
UPDATE sequence_master 
SET last_no = $next
WHERE sequence_category='quotation'
");
  $cust  = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
  $org_id = isset($_POST['organization_id']) ? (int)$_POST['organization_id'] : 0;

if($org_id <= 0){
    die("Invalid Organization Selected");
}

  if($cust <= 0){
      die("Invalid Customer Selected");
  }

  $qdate    = date("Y-m-d");
  $gst_type = $_POST['gst_type'] ?? 'exclusive';

  $subtotal  = 0;
  $net_total = 0;

  /* ================= TAX MODE DETECT ================= */

$org_data  = find_by_sql("SELECT gst_no FROM organization_master WHERE id = $org_id");
$cust_data = find_by_sql("SELECT gst_no FROM customer_master WHERE id = $cust");

$org_gst  = $org_data[0]['gst_no'] ?? '';
$cust_gst = $cust_data[0]['gst_no'] ?? '';

$org_state  = substr($org_gst,0,2);
$cust_state = substr($cust_gst,0,2);

$tax_mode = ($org_state == $cust_state) ? 'CGST_SGST' : 'IGST';

  /* ===============================
     INSERT MASTER
  ================================*/
  $insertMaster = $db->query("
    INSERT INTO quotation_master
    (quotation_no, quotation_date, customer_id, organization_id,
     subtotal, gst_total, net_total, remarks, created_at)
    VALUES
    ('$qno','$qdate','$cust', '$org_id',
     0,0,0,'',NOW())
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
      $gst  = isset($_POST['gst'][$i]) ? (float)$_POST['gst'][$i] : 0;
      $disc = isset($_POST['discount'][$i]) ? (float)$_POST['discount'][$i] : 0;

      if($qty <= 0 || $base <= 0){
          continue;
      }

      $itemInserted = true;

$line_base = $qty * $base;

/* 🔥 Discount pehle minus hoga */
$discounted_base = $line_base - $disc;

if($gst_type == "exclusive"){

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

echo "<script>
window.location='quotation_list.php?print_id=".$qid."';
</script>";
}

?>

<?php include_once('layouts/header.php'); ?>

<div class="row">
<div class="col-md-12">
<div class="card shadow-sm">

<div class="card-header bg-white">
  <h4 class="mb-0">Create Quotation</h4>
</div>

<div class="card-body">
<form method="post">

<!-- ================= ORGANIZATION ================= -->
<div class="mb-3" style="max-width:480px;">
  <label class="form-label fw-bold">Organization</label>
  <select name="organization_id" class="form-control" required>
    <option value="">Select Organization</option>
    <?php foreach($orgs as $o){ ?>
      <option value="<?= $o['id']; ?>">
        <?= $o['org_name']; ?>
      </option>
    <?php } ?>
  </select>
</div>

<!-- ================= CUSTOMER ================= -->
<div class="mb-4" style="max-width:480px;">
  <label class="form-label fw-bold">Customer</label>
  <select name="customer_id" id="customer" class="form-control" required>
    <option value="">Select Customer</option>
    <?php foreach($customers as $c){ ?>
    <option value="<?=$c['id'];?>"
      data-name="<?=$c['customer_name'];?>"
      data-phone="<?=$c['contact_no'];?>"
      data-gst="<?=$c['gst_no'];?>"
      data-addr="<?=$c['address'];?>">
      <?=$c['customer_name'];?>
    </option>
    <?php } ?>
  </select>

  <div class="border rounded p-2 bg-light mt-2 small shadow-sm">
    <div><strong>Name:</strong> <span id="c_name">—</span></div>
    <div><strong>Contact:</strong> <span id="c_phone">—</span></div>
    <div><strong>GST:</strong> <span id="c_gst">—</span></div>
    <div><strong>Address:</strong> <span id="c_addr">—</span></div>
  </div>
</div>

<!-- ================= PRODUCTS ================= -->
<!-- GST TYPE TOGGLE -->
<div class="mb-3">
  <label class="fw-bold me-3">GST Type:</label>
  <label class="me-3">
    <input type="radio" name="gst_type" value="exclusive" checked> Exclusive
  </label>
  <label>
    <input type="radio" name="gst_type" value="inclusive"> Inclusive
  </label>
</div>

<div class="table-responsive">
<table class="table table-bordered table-sm align-middle" id="itemTable">
<thead class="bg-dark text-white text-center">
<tr>
  <th>Product</th>
  <th width="10%">Qty</th>
  <th width="10%">Base</th>
  <th width="8%">GST%</th>
  <th width="10%">GST Amt</th>
  <th width="10%">Disc ₹</th>
  <th width="8%">Disc %</th>
  <th width="12%">Total</th>
  <th width="5%"></th>
</tr>
</thead>

<tbody>
<tr class="rowItem">

<td>
<select name="product_id[]" class="form-control form-control-sm prod">
<option value="">Select Product</option>
<?php foreach($products as $p){
  $price = $p['sale_price'] ?? 0;
  $gst   = $p['gst_percent'] ?? 0;
?>
<option value="<?=$p['id'];?>"
  data-rate="<?=$price;?>"
  data-gst="<?=$gst;?>">
  <?=$p['name'];?>
</option>
<?php } ?>
</select>
</td>

<td><input type="number" name="qty[]" 
       class="form-control form-control-sm qty text-end" 
       style="min-width:80px;" 
       value="1"></td>

<td><input type="number" name="rate[]" class="form-control form-control-sm base text-end"></td>

<td><input type="number" name="gst[]" class="form-control form-control-sm gst bg-light text-end" readonly></td>

<td><input type="text" class="form-control form-control-sm gstAmt bg-light text-end" readonly></td>

<td><input type="number" name="discount[]" class="form-control form-control-sm discAmt text-end" value="0"></td>

<td><input type="number" class="form-control form-control-sm discPer text-end" value="0"></td>

<td><input type="text" class="form-control form-control-sm totalRow bg-light text-end" readonly></td>

<td class="text-center">
<button type="button" class="btn btn-sm btn-danger remove">×</button>
</td>

</tr>
</tbody>
</table>
</div>

<div class="mt-3 d-flex justify-content-between">
  <button type="button" id="addRow" class="btn btn-primary btn-sm">
    + Add Item
  </button>

  <h5>
    Grand Total : ₹ <span id="gTotal">0.00</span>
  </h5>
</div>

<br>



<div class="text-end">
  <button type="submit" name="save_quotation" class="btn btn-success">
    Save Quotation
  </button>
</div>
</form>
</div>
</div>
</div>
</div>

<?php include_once('layouts/footer.php'); ?>



<script>
/* CUSTOMER DETAILS */

document.addEventListener("DOMContentLoaded", function(){

  const customerSelect = document.getElementById("customer");
  const c_name  = document.getElementById("c_name");
  const c_phone = document.getElementById("c_phone");
  const c_gst   = document.getElementById("c_gst");
  const c_addr  = document.getElementById("c_addr");

  customerSelect.addEventListener("change", function(){
    let o = this.selectedOptions[0];

    c_name.innerText  = o.dataset.name  || '—';
    c_phone.innerText = o.dataset.phone || '—';
    c_gst.innerText   = o.dataset.gst   || '—';
    c_addr.innerText  = o.dataset.addr  || '—';
  });

});



/* PRODUCT SELECT */
document.addEventListener("change", e=>{
 if(e.target.classList.contains("prod")){
  let r = e.target.closest("tr");
  let o = e.target.selectedOptions[0];

  r.querySelector(".base").value = parseFloat(o.dataset.rate || 0).toFixed(2);
  r.querySelector(".gst").value  = parseFloat(o.dataset.gst || 0);

  calculate(r);
 }
});

/* INPUT EVENTS */
document.addEventListener("input", e=>{
 if(
   e.target.classList.contains("qty") ||
   e.target.classList.contains("base") ||
   e.target.classList.contains("discAmt") ||
   e.target.classList.contains("discPer")
 ){
   calculate(e.target.closest("tr"));
 }
});

/* GST TOGGLE CHANGE */
document.querySelectorAll("input[name='gst_type']").forEach(radio=>{
 radio.addEventListener("change", ()=>{
   document.querySelectorAll(".rowItem").forEach(r=>calculate(r));
 });
});

function calculate(r){

 let qty   = +r.querySelector(".qty").value || 0;
 let base  = +r.querySelector(".base").value || 0;
 let gst   = +r.querySelector(".gst").value || 0;
 let dAmt  = +r.querySelector(".discAmt").value || 0;
 let dPer  = +r.querySelector(".discPer").value || 0;

 let gstType = document.querySelector("input[name='gst_type']:checked").value;

 let lineBase = qty * base;

 // 🔥 Discount Logic
 if(dPer > 0){
    dAmt = (lineBase * dPer) / 100;
    r.querySelector(".discAmt").value = dAmt.toFixed(2);
 }
 else if(dAmt > 0){
    dPer = (dAmt / lineBase) * 100;
    r.querySelector(".discPer").value = dPer.toFixed(2);
 }

 let discountedBase = lineBase - dAmt;

 let gstAmount = 0;
 let final = 0;

 if(gstType === "exclusive"){
     gstAmount = discountedBase * gst / 100;
     final = discountedBase + gstAmount;
 } else {
     gstAmount = discountedBase - (discountedBase / (1 + gst/100));
     final = discountedBase;
 }

 r.querySelector(".gstAmt").value = gstAmount.toFixed(2);
 r.querySelector(".totalRow").value = final.toFixed(2);

 calculateGrand();
}


document.querySelector("form").addEventListener("submit", function(e){

    let valid = false;

    document.querySelectorAll(".prod").forEach(function(select){
        if(select.value){
            valid = true;
        }
    });

    if(!valid){
        alert("Please select at least one product");
        e.preventDefault();
    }
});

/* ADD NEW ROW */
document.getElementById("addRow").addEventListener("click", function(){

    let table = document.querySelector("#itemTable tbody");
    let firstRow = document.querySelector(".rowItem");

    let newRow = firstRow.cloneNode(true);

    /* Reset values */
    newRow.querySelectorAll("input").forEach(function(input){
        if(input.type !== "button"){
            input.value = 0;
        }
    });

    newRow.querySelector(".base").value = "";
    newRow.querySelector(".gst").value = "";
    newRow.querySelector(".gstAmt").value = "";
    newRow.querySelector(".totalRow").value = "";

    newRow.querySelector(".prod").value = "";

    table.appendChild(newRow);

});

/* REMOVE ROW */
document.addEventListener("click", function(e){

if(e.target.classList.contains("remove")){

    let row = e.target.closest("tr");

    if(document.querySelectorAll(".rowItem").length > 1){
        row.remove();
    } else {
        alert("At least one item required");
    }

    calculateGrand();
}

});
</script>
