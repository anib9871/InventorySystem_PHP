<?php
require_once('includes/load.php');
//page_require_level(2);
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

$customers = find_all('customer_master');
$products  = find_by_sql("
    SELECT 
        p.id,
        p.name,
        p.sale_price,
        p.type,
        g.gst_percent,
        (
            COALESCE(SUM(
                CASE 
                    WHEN t.transaction_type IN (1,4) THEN t.quantity 
                    WHEN t.transaction_type IN (2,3,5,6) THEN -t.quantity 
                    ELSE 0 
                END
            ), 0)
            -
            COALESCE((
                SELECT SUM(d.qty) 
                FROM demo_item_detail d 
                WHERE d.product_id = p.id AND d.status = 1
            ), 0)
        ) AS current_stock
    FROM products p
    LEFT JOIN transaction_master t ON p.id = t.product_id
    LEFT JOIN gst_master g ON p.gst_id = g.id
    WHERE p.is_active = 1
    GROUP BY p.id, p.name, p.sale_price, p.type, g.gst_percent
");

/* TERMS & CONDITIONS */
$terms_templates = find_all('terms_conditions_master');
$gst_enabled = "Yes";

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
  $qno = $fy_name . "/" . $next;

  $cust = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
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

  $gst_type = ($gst_enabled == "Yes") ? ($_POST['gst_type'] ?? 'exclusive') : 'exclusive';

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

  /* ================= INSERT MASTER ================= */
  $insertMaster = $db->query("
    INSERT INTO quotation_master
    (quotation_no, quotation_date, customer_id, organization_id,
     subtotal, gst_total, net_total, remarks, terms_conditions, created_at)
    VALUES
    ('$qno','$qdate','$cust', '$org_id',
     0, 0, 0, '', '".$db->escape($_POST['terms_conditions'])."', NOW())
  ");

  if(!$insertMaster){
      die("Master Insert Error");
  }

  $qid = $db->insert_id();

  if(!$qid){
      die("Master insert failed");
  }

  if(!isset($_POST['product_id']) || count($_POST['product_id']) == 0){
      $db->query("DELETE FROM quotation_master WHERE id = $qid");
      die("No products selected");
  }

  /* ================= INSERT ITEMS ================= */
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
         $gst = isset($_POST['gst'][$i]) ? (float)$_POST['gst'][$i] : 0;
      }
      $disc = isset($_POST['discount'][$i]) ? (float)$_POST['discount'][$i] : 0;

      if($qty <= 0 || $base <= 0){
          continue;
      }

      $itemInserted = true;
      $line_base = $qty * $base;
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

  /* ================= UPDATE TOTALS ================= */
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
  exit;
}
?>

<?php include_once('layouts/header.php'); ?>

<style>
/* Enterprise SaaS POS Terminal */
:root {
  --navy-dark: #0f172a;
  --navy-light: #1e293b;
  --border-slate: #cbd5e1;
  --border-light: #e2e8f0;
  --blue-accent: #2563eb;
  --blue-hover: #1d4ed8;
  --emerald-accent: #059669;
  --emerald-hover: #047857;
  --danger-accent: #dc2626;
}

body {
  background-color: #f1f5f9;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Inter", sans-serif;
  color: #0f172a;
}

.quotation-terminal {
  padding: 8px 14px 14px;
}

.pos-card {
  background: #ffffff;
  border: 1px solid var(--border-slate);
  border-radius: 6px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  margin-bottom: 8px;
}

.pos-title-lbl {
  font-size: 10.5px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  margin-bottom: 2px;
  display: block;
}

/* Controls */
.form-control, .form-control-sm {
  height: 32px !important;
  border-radius: 4px !important;
  border: 1px solid var(--border-slate);
  font-size: 12px;
  font-weight: 600;
  color: #0f172a;
  box-shadow: none !important;
}

.form-control:focus {
  border-color: var(--blue-accent);
}

.btn-top-trigger {
  height: 32px;
  font-size: 12px;
  font-weight: 700;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  width: 100%;
}

/* Full Width Grid (Sweet Spot: 220px) */
.grid-container-full {
  height: 220px !important;
  max-height: 220px !important;
  overflow-y: auto;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

#itemTable {
  width: 100%;
  min-width: 720px;
  margin-bottom: 0;
  border-collapse: collapse;
}

#itemTable thead th {
  background: var(--navy-dark) !important;
  color: #ffffff;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  padding: 8px 8px;
  position: sticky;
  top: 0;
  z-index: 10;
  border: none;
  white-space: nowrap;
}

#itemTable tbody tr td {
  padding: 0 !important;
  vertical-align: middle;
  border-top: 1px solid var(--border-light);
  background: #ffffff;
}

#itemTable tbody tr:hover td {
  background-color: #f8fafc;
}

#itemTable input.grid-cell {
  width: 100%;
  height: 34px !important;
  border: none !important;
  background: transparent !important;
  font-size: 12px;
  font-weight: 600;
  color: #0f172a;
  padding: 2px 8px;
  box-shadow: none !important;
  outline: none !important;
}

#itemTable input.grid-cell:focus {
  background: #eff6ff !important;
  color: var(--blue-hover) !important;
}

#itemTable input.grid-cell[readonly] {
  color: #334155;
  cursor: default;
}

.btn-del-cell {
  width: 22px;
  height: 22px;
  border-radius: 3px;
  background: #fee2e2;
  color: var(--danger-accent);
  border: 1px solid #fca5a5;
  font-size: 13px;
  line-height: 1;
  font-weight: bold;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  padding: 0;
  margin: auto;
}

.btn-del-cell:hover {
  background: var(--danger-accent);
  color: #ffffff;
}

.empty-grid-msg {
  padding: 50px 0 !important;
  text-align: center;
  color: #94a3b8;
  font-size: 12.5px;
  font-weight: 600;
}

/* 50/50 Bottom Summary Layout */
.summary-container {
  padding: 10px 14px;
}

.summary-metric-card {
  background: #f8fafc;
  border: 1px solid var(--border-light);
  border-radius: 5px;
  padding: 12px 16px;
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: space-around;
}

.summary-metric-card .stat-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 6px 0;
  font-size: 12.5px;
  border-bottom: 1px dashed var(--border-slate);
}

.summary-metric-card .stat-row:last-child {
  border-bottom: none;
}

.stat-row.net-bold strong {
  color: var(--blue-accent);
  font-size: 15px;
}

.stock-tag-box {
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
  border-radius: 4px;
  font-size: 11.5px;
  font-weight: 700;
  color: #065f46;
  white-space: nowrap;
}

.btn-save-master {
  height: 36px;
  background: var(--emerald-accent);
  border: none;
  border-radius: 4px;
  font-size: 13px;
  font-weight: 700;
  color: #ffffff;
  width: 100%;
  transition: 0.15s ease;
}

.btn-save-master:hover {
  background: var(--emerald-hover);
  color: #ffffff;
}

/* Compact Modal Widths */
.modal-dialog-compact {
  max-width: 430px;
  margin: 35px auto;
}

/* Mobile & Tablet Responsiveness */
@media (max-width: 991px) {
  .quotation-terminal {
    padding: 6px;
  }
  .grid-container-full {
    height: auto !important;
    max-height: 220px !important;
  }
  .modal-dialog-compact {
    max-width: 92%;
    margin: 20px auto;
  }
  .summary-metric-card {
    margin-bottom: 10px;
  }
}

/* Custom Scrollbars */
::-webkit-scrollbar {
  width: 5px;
  height: 5px;
}
::-webkit-scrollbar-track {
  background: #f1f5f9;
}
::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 3px;
}
::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
</style>

<div class="quotation-terminal">
<form method="post" onsubmit="return validateQuotation()">

  <!-- TOP HEADER ROW -->
  <div class="pos-card" style="padding: 8px 14px;">
    <div class="row align-items-end">
      
      <div class="col-xs-12 col-sm-6 col-md-3" style="margin-bottom: 4px;">
        <label class="pos-title-lbl">Quotation Date</label>
        <input type="text" name="quotation_date" id="quotation_date" class="form-control" value="<?= date('d/M/Y'); ?>" autocomplete="off">
      </div>

      <div class="col-xs-12 col-sm-6 col-md-4" style="margin-bottom: 4px;">
        <label class="pos-title-lbl">Customer</label>
        <select name="customer_id" class="form-control" required>
          <option value="">Select Customer</option>
          <?php foreach($customers as $c): ?>
            <option value="<?=$c['id'];?>"><?=$c['customer_name'];?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-xs-12 col-sm-6 col-md-3" style="margin-bottom: 4px;">
        <label class="pos-title-lbl">GST Mode</label>
        <select name="gst_type" class="form-control">
          <option value="exclusive" selected>Exclusive GST</option>
          <option value="inclusive">Inclusive GST</option>
          <option value="nogst">No GST</option>
        </select>
      </div>

      <div class="col-xs-12 col-sm-6 col-md-2" style="margin-bottom: 4px;">
        <label class="pos-title-lbl" style="visibility:hidden;">Products</label>
        <button type="button" class="btn btn-primary btn-top-trigger" data-toggle="modal" data-target="#productCatalogueModal">
          <i class="glyphicon glyphicon-search" style="font-size: 11px;"></i> Add Products
        </button>
      </div>

    </div>
  </div>

  <!-- FULL-WIDTH BILLING DATA GRID -->
  <div class="pos-card" style="padding:0; overflow:hidden;">
    <div class="grid-container-full">
      <table class="table" id="itemTable">
        <thead>
          <tr>
            <th style="width: 32%; padding-left: 12px;">Product Description</th>
            <th style="width: 8%;" class="text-center">Qty</th>
            <th style="width: 12%;" class="text-right">Price (₹)</th>
            <?php if($gst_enabled == "Yes"): ?>
            <th style="width: 8%;" class="text-center">GST %</th>
            <th style="width: 10%;" class="text-right">GST (₹)</th>
            <?php endif; ?>
            <th style="width: 8%;" class="text-center">Disc %</th>
            <th style="width: 10%;" class="text-right">Disc (₹)</th>
            <th style="width: 10%;" class="text-right">Line Total (₹)</th>
            <th style="width: 2%; text-align:center;"></th>
          </tr>
        </thead>
        <tbody id="billBody">
          <tr id="emptyRowMsg">
            <td colspan="<?= ($gst_enabled == 'Yes') ? 9 : 7; ?>" class="empty-grid-msg">
              No products added to quotation. Click <b>"Add Products"</b> above to insert items.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- EQUAL 50/50 BOTTOM SUMMARY, EXPANDED NOTES & SAVE -->
  <div class="pos-card summary-container">
    <div class="row">
      
      <!-- LEFT 50%: Financial Metrics -->
      <div class="col-xs-12 col-md-6" style="margin-bottom: 6px;">
        <div class="summary-metric-card">
          <div class="stat-row">
            <span>Gross Total</span>
            <strong>₹ <span id="gross">0.00</span></strong>
          </div>
          <div class="stat-row">
            <span>GST Amount</span>
            <strong>₹ <span id="gstTotal">0.00</span></strong>
          </div>
          <div class="stat-row net-bold">
            <span>Net Amount</span>
            <strong>₹ <span id="net">0.00</span></strong>
          </div>
        </div>
      </div>

      <!-- RIGHT 50%: Terms, Stock & Save Action Button -->
      <div class="col-xs-12 col-md-6" style="display:flex; flex-direction:column; justify-content:space-between;">
        <div>
          <div class="row" style="margin-bottom: 6px;">
            <div class="col-xs-8">
              <select id="termsTemplate" class="form-control" style="font-size:12px; height:30px !important;">
                <option value="">Select Terms Template</option>
                <?php foreach($terms_templates as $t): ?>
                  <option value="<?= htmlspecialchars($t['template']); ?>"><?= htmlspecialchars($t['template_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-xs-4">
              <div id="stockInfo" class="stock-tag-box" title="Stock Info">Stock: —</div>
            </div>
          </div>
          
          <textarea name="terms_conditions" id="termsBox" class="form-control" style="height: 75px !important; resize: none; font-size:12px; margin-bottom: 6px;" placeholder="Add quotation terms, validity or remarks..."></textarea>
        </div>
        
        <button type="submit" name="save_quotation" class="btn btn-save-master">
          💾 Save & Print Quotation
        </button>
      </div>

    </div>
  </div>

</form>
</div>

<!-- COMPACT PRODUCT CATALOGUE MODAL -->
<div class="modal fade" id="productCatalogueModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-compact" role="document">
    <div class="modal-content" style="border-radius:6px; border:none; box-shadow:0 15px 35px rgba(0,0,0,0.25);">
      <div class="modal-header" style="background:var(--navy-dark); color:#ffffff; border-top-left-radius:6px; border-top-right-radius:6px; padding:8px 14px;">
        <button type="button" class="close" data-dismiss="modal" style="color:#ffffff; opacity:0.9;">&times;</button>
        <h5 class="modal-title" style="font-weight:700; margin:0; font-size:13px;">Product Catalogue</h5>
      </div>
      <div class="modal-body" style="padding:10px 14px;">
        <input type="text" id="productSearch" class="form-control mb-2" placeholder="Search product..." style="height: 28px !important; font-size: 11.5px;">
        <div style="max-height: 220px; overflow-y: auto; border: 1px solid var(--border-light); border-radius: 4px;">
          <table class="table table-hover mb-0">
            <tbody id="productList"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer" style="padding:6px 14px; background:#f8fafc; border-bottom-left-radius:6px; border-bottom-right-radius:6px;">
        <button type="button" class="btn btn-default btn-sm" data-dismiss="modal" style="font-size: 11.5px; padding: 3px 12px;">Close</button>
      </div>
    </div>
  </div>
</div>

<?php include_once('layouts/footer.php'); ?>

<script>
const products = <?= json_encode($products); ?>;

/* RENDER PRODUCTS IN MODAL */
function renderProducts(filter=""){
  let list = document.getElementById("productList");
  list.innerHTML = "";

  products
  .filter(p => p.name.toLowerCase().includes(filter.toLowerCase()))
  .forEach(p => {
    let tr = document.createElement("tr");
    tr.style.cursor = "pointer";
    tr.innerHTML = `
      <td style="font-size:11.5px; font-weight:600; padding: 6px 8px;">${p.name}</td>
      <td class="text-right" style="font-size:11.5px; font-weight:700; color:#475569; padding: 6px 8px;">₹${parseFloat(p.sale_price).toFixed(2)}</td>
    `;
    tr.addEventListener("click", () => {
      addProduct(p);
      $('#productCatalogueModal').modal('hide');
    });
    list.appendChild(tr);
  });
}
renderProducts();

document.getElementById("productSearch").addEventListener("input", e => renderProducts(e.target.value));

/* ADD PRODUCT TO BILLING GRID */
function addProduct(p){
  document.getElementById("stockInfo").innerHTML = `Stock: ${Math.floor(Number(p.current_stock || 0))} PCS`;

  let emptyMsg = document.getElementById("emptyRowMsg");
  if(emptyMsg) emptyMsg.remove();

  let rows = document.querySelectorAll("#billBody tr:not(#emptyRowMsg)");
  for(let r of rows){
    let pId = r.querySelector(".productName").dataset.productId;
    if(pId == p.id){
      let qtyInput = r.querySelector(".qty");
      qtyInput.value = parseInt(qtyInput.value || 0) + 1;
      calculate(r);
      return;
    }
  }

  let row = document.createElement("tr");
  row.innerHTML = `
    <td class="productName" data-product-id="${p.id}" style="font-size:12px; font-weight:700; padding-left: 12px !important;">
      ${p.name}
      <input type="hidden" name="product_id[]" value="${p.id}">
    </td>
    <td><input type="number" name="qty[]" class="grid-cell qty text-center" value="1" min="1"></td>
    <td><input type="number" step="0.01" name="rate[]" class="grid-cell base text-right" value="${p.sale_price}"></td>
    <?php if($gst_enabled == "Yes"): ?>
    <td><input type="number" name="gst[]" class="grid-cell gst text-center" value="${p.gst_percent}"></td>
    <td><input type="text" class="grid-cell gstAmt text-right" readonly></td>
    <?php endif; ?>
    <td><input type="number" step="0.01" class="grid-cell discPer text-center" value="0"></td>
    <td><input type="number" step="0.01" name="discount[]" class="grid-cell discAmt text-right" value="0"></td>
    <td><input type="text" class="grid-cell totalRow text-right font-weight-bold" style="color:var(--blue-accent);" readonly></td>
    <td class="text-center" style="padding-right: 6px !important;"><button type="button" class="btn-del-cell remove" title="Remove">&times;</button></td>
  `;

  document.getElementById("billBody").appendChild(row);
  calculate(row);
}

/* EVENT DELEGATION */
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
    document.querySelectorAll("#billBody tr:not(#emptyRowMsg)").forEach(r => calculate(r));
  }
});

/* REMOVE ROW */
document.addEventListener("click", function(e){
  if(e.target.classList.contains("remove")){
    let row = e.target.closest("tr");
    row.remove();
    updateSummary();

    let rows = document.querySelectorAll("#billBody tr:not(#emptyRowMsg)");
    if(rows.length === 0){
      document.getElementById("stockInfo").innerHTML = "Stock: —";
      document.getElementById("billBody").innerHTML = `
        <tr id="emptyRowMsg">
          <td colspan="<?= ($gst_enabled == 'Yes') ? 9 : 7; ?>" class="empty-grid-msg">
            No products added to quotation. Click <b>"Add Products"</b> above to insert items.
          </td>
        </tr>`;
    }else{
      let lastRow = rows[rows.length - 1];
      let productId = lastRow.querySelector(".productName").dataset.productId;
      let productObj = products.find(p => p.id == productId);
      if(productObj){
        document.getElementById("stockInfo").innerHTML = `Stock: ${Math.floor(Number(productObj.current_stock || 0))} PCS`;
      }
    }
  }
});

/* CALCULATION ENGINE */
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

  let active = document.activeElement;
  if(active && active.classList.contains("discPer")){
    dAmt = (total * dPer) / 100;
    r.querySelector(".discAmt").value = dAmt.toFixed(2);
  } else if(active && active.classList.contains("discAmt")){
    dPer = total > 0 ? (dAmt / total) * 100 : 0;
    r.querySelector(".discPer").value = dPer.toFixed(2);
  }

  let afterDisc = total - dAmt;

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
  } else if(gstType == "exclusive"){
    gstAmt = (afterDisc * gst) / 100;
    final = afterDisc + gstAmt;
  } else {
    gstAmt = afterDisc - (afterDisc * 100 / (100 + gst));
    final = afterDisc;
  }

  let gstAmtField = r.querySelector(".gstAmt");
  if(gstAmtField){
    gstAmtField.value = gstAmt.toFixed(2);
  }
  r.querySelector(".totalRow").value = final.toFixed(2);
  r.dataset.gstAmount = gstAmt;
  r.dataset.lineBase = afterDisc;

  updateSummary();
}

/* UPDATE TOTALS SUMMARY */
function updateSummary(){
  let gross = 0;
  let totalGst = 0;
  let net = 0;

  document.querySelectorAll("#billBody tr:not(#emptyRowMsg)").forEach(t => {
    let lineTotal = parseFloat(t.querySelector(".totalRow").value) || 0;
    let gstAmount = parseFloat(t.dataset.gstAmount) || 0;
    let lineBase = parseFloat(t.dataset.lineBase) || 0;

    net += lineTotal;
    totalGst += gstAmount;
    gross += lineBase;
  });

  document.getElementById("gross").innerText = gross.toFixed(2);
  document.getElementById("gstTotal").innerText = totalGst.toFixed(2);
  document.getElementById("net").innerText = net.toFixed(2);
}

function validateQuotation(){
  let cust = document.querySelector("select[name='customer_id']").value;
  let rows = document.querySelectorAll("#billBody tr:not(#emptyRowMsg)");

  if(cust == ""){
    alert("Please select a customer");
    return false;
  }

  if(rows.length <= 0){
    alert("Please add at least one product");
    return false;
  }
  return true;
}

/* TEMPLATE HANDLER */
document.getElementById("termsTemplate")?.addEventListener("change", function(){
  document.getElementById("termsBox").value = this.value;
});

/* FLATPICKR INITIALIZATION */
document.addEventListener("DOMContentLoaded", function () {
  if (typeof flatpickr !== 'undefined') {
    flatpickr("#quotation_date", {
      dateFormat: "d/M/Y",
      allowInput: false,
      disableMobile: true
    });
  }
});
</script>
