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
SELECT ii.*, p.name, p.sale_price, p.type
FROM invoice_items ii
LEFT JOIN products p ON p.id = ii.product_id
WHERE ii.invoice_id = $id
");

/* ================= MASTER DATA ================= */

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
$invoice_date = $_POST['invoice_date'];

$formats = ['d/M/Y', 'd-m-Y', 'Y-m-d'];

foreach ($formats as $format) {
    $dt = DateTime::createFromFormat($format, $invoice_date);
    if ($dt) {
        $invoice_date = $dt->format('Y-m-d');
        break;
    }
}
    
$cust = (int)$_POST['customer_id'];

/* ================= TAX MODE ================= */

$org_data = find_by_sql("
SELECT gst_no
FROM organization_master
WHERE id = '{$quote['organization_id']}'
LIMIT 1
");

$cust_data = find_by_sql("
SELECT gst_no
FROM customer_master
WHERE id = '$cust'
LIMIT 1
");

$org_gst  = $org_data[0]['gst_no'] ?? '';
$cust_gst = $cust_data[0]['gst_no'] ?? '';

$org_state  = substr($org_gst,0,2);
$cust_state = substr($cust_gst,0,2);

$tax_mode =
(trim($org_state) == trim($cust_state))
? 'CGST_SGST'
: 'IGST';

$gst_type = $_POST['gst_type'] ?? 'exclusive';

$subtotal  = 0;
$net_total = 0;
$total_gst = 0;

/* ================= OLD DATA DELETE ================= */

$db->query("DELETE FROM invoice_items WHERE invoice_id = $id");
$db->query("DELETE FROM payments WHERE invoice_id = $id");
$db->query("DELETE FROM ledger_entries WHERE invoice_id = $id");

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

    if($tax_mode == 'IGST'){
        $igst_amount = $gst_amount;
        $cgst_amount = 0;
        $sgst_amount = 0;
    }else{
        $cgst_amount = $gst_amount / 2;
        $sgst_amount = $gst_amount / 2;
        $igst_amount = 0;
    }

    $rate_incl = $base + ($base * $gst / 100);
    $line_total = $discounted_base + $gst_amount;

}
elseif($gst_type == "inclusive"){

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

    $rate_incl = $base;
    $line_total = $discounted_base;

}else{
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

/* ================= TRANSACTION UPDATE ================= */

$db->query("
UPDATE transaction_master
SET
product_id       = '$pid',
gst_id           = '$gst',
quantity         = '$qty',
unit_price       = '$base',
gst_amount       = '$gst_amount',
discount_amount  = '$disc',
net_price        = '$line_total',
sale_amount      = '$discounted_base',
sale_gst         = '$gst_amount',
sale_net         = '$line_total',
status           = 1,
comments         = 'Sale Invoice Edit'
WHERE bill_indent_no = '".$quote['invoice_no']."'
AND transaction_type = 2
LIMIT 1
");

/* ================= UPDATE DEMO ITEM QTY & STATUS ================= */
$demo_row = find_by_sql("
    SELECT id, qty FROM demo_item_detail 
    WHERE customer_id = '$cust' AND product_id = '$pid' AND status = 1 
    LIMIT 1
");

if(!empty($demo_row)){
    $demo_id = $demo_row[0]['id'];
    $old_qty = (float)$demo_row[0]['qty'];
    $new_qty = $old_qty - $qty;

    if($new_qty <= 0){
        $db->query("UPDATE demo_item_detail SET qty = 0, status = 0 WHERE id = '$demo_id'");
    } else {
        $db->query("UPDATE demo_item_detail SET qty = '$new_qty' WHERE id = '$demo_id'");
    }
}

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
invoice_date = '$invoice_date',

subtotal = '$subtotal',
gst_total = '$total_gst',
net_total = '$net_total',

paid_amount = '$total_paid',
due_amount = '$due_amount',
payment_status = '$payment_status',

is_revised = 1,

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
alert('Invoice Updated Successfully!');
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
/* Enterprise POS Terminal Pro */
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

.invoice-terminal {
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

/* Full Width Billing Grid (Sweet Spot: 220px) */
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
  padding: 6px 14px;
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: space-around;
}

.summary-metric-card .stat-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 3px 0;
  font-size: 12px;
  border-bottom: 1px dashed var(--border-slate);
}

.summary-metric-card .stat-row:last-child {
  border-bottom: none;
}

.stat-row.net-bold strong {
  color: var(--blue-accent);
  font-size: 14.5px;
}

.stat-row.due-bold strong,
.stat-row.due-bold span {
  color: var(--danger-accent);
  font-weight: 700;
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
  .invoice-terminal {
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

<div class="invoice-terminal">
<form method="post" onsubmit="return validateCustomer()">

  <!-- TOP HEADER ROW -->
  <div class="pos-card" style="padding: 8px 14px;">
    <div class="row align-items-end">
      
      <div class="col-xs-12 col-sm-6 col-md-2" style="margin-bottom: 4px;">
        <label class="pos-title-lbl">Invoice Date</label>
        <input type="text" name="invoice_date" id="invoice_date" class="form-control" value="<?= date('d/M/Y', strtotime($quote['invoice_date'])); ?>" autocomplete="off">
      </div>

      <div class="col-xs-12 col-sm-6 col-md-3" style="margin-bottom: 4px;">
        <label class="pos-title-lbl">Customer</label>
        <select name="customer_id" class="form-control">
          <option value="">Select Customer</option>
          <?php foreach($customers as $c): ?>
            <option value="<?=$c['id'];?>" <?=$c['id']==$quote['customer_id']?'selected':'';?>>
              <?=$c['customer_name'];?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-xs-12 col-sm-6 col-md-2" style="margin-bottom: 4px;">
        <label class="pos-title-lbl">GST Mode</label>
        <select name="gst_type" class="form-control">
          <option value="exclusive" <?= ($quote['gst_type'] == 'exclusive') ? 'selected' : ''; ?>>Exclusive GST</option>
          <option value="inclusive" <?= ($quote['gst_type'] == 'inclusive') ? 'selected' : ''; ?>>Inclusive GST</option>
          <option value="nogst" <?= ($quote['gst_type'] == 'nogst') ? 'selected' : ''; ?>>No GST</option>
        </select>
      </div>

      <div class="col-xs-6 col-sm-3 col-md-2" style="margin-bottom: 4px;">
        <label class="pos-title-lbl" style="visibility:hidden;">Products</label>
        <button type="button" class="btn btn-primary btn-top-trigger" data-toggle="modal" data-target="#productCatalogueModal">
          <i class="glyphicon glyphicon-search" style="font-size: 11px;"></i> Add Products
        </button>
      </div>

      <div class="col-xs-6 col-sm-3 col-md-3" style="margin-bottom: 4px;">
        <label class="pos-title-lbl" style="visibility:hidden;">Payment</label>
        <button type="button" class="btn btn-info btn-top-trigger" data-toggle="modal" data-target="#paymentModal" style="background:#0284c7; border-color:#0284c7; color:#fff;">
          <i class="glyphicon glyphicon-credit-card" style="font-size: 11px;"></i> Payment (<span id="btnPaidDisplay">₹<?= number_format((float)$quote['paid_amount'], 2); ?></span>)
        </button>
      </div>

      <?php if($_SESSION['role_id'] == 2 && $system != 'inventory'): ?>
      <div class="col-xs-12 col-sm-6 col-md-2" style="margin-bottom: 4px;">
        <label class="pos-title-lbl">Center</label>
        <select name="center_id" class="form-control" required>
          <?php foreach(find_all('master_center') as $c): ?>
            <option value="<?= $c['center_id']; ?>"><?= $c['center_name']; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

    </div>
  </div>

  <!-- FULL-WIDTH BILLING DATA GRID (PERFECT SWEET SPOT: 220px) -->
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
          <?php if(empty($items)): ?>
          <tr id="emptyRowMsg">
            <td colspan="<?= ($gst_enabled == 'Yes') ? 9 : 7; ?>" class="empty-grid-msg">
              No products in this invoice. Click <b>"Add Products"</b> above to insert items.
            </td>
          </tr>
          <?php else: ?>
            <?php foreach($items as $it): ?>
            <tr>
              <td class="productName" data-product-id="<?= $it['product_id']; ?>" style="font-size:12px; font-weight:700; padding-left: 12px !important;">
                <?= $it['name']; ?>
                <input type="hidden" name="product_id[]" value="<?= $it['product_id']; ?>">
              </td>
              <td><input type="number" name="qty[]" class="grid-cell qty text-center" value="<?= $it['qty']; ?>" min="1"></td>
              <td><input type="number" step="0.01" name="rate[]" class="grid-cell base text-right" value="<?= $it['rate_excl_gst']; ?>"></td>
              <?php if($gst_enabled == "Yes"): ?>
              <td><input type="number" name="gst[]" class="grid-cell gst text-center" value="<?= $it['gst_percent']; ?>"></td>
              <td><input type="text" class="grid-cell gstAmt text-right" readonly></td>
              <?php endif; ?>
              <td><input type="number" step="0.01" class="grid-cell discPer text-center" value="0"></td>
              <td><input type="number" step="0.01" name="discount[]" class="grid-cell discAmt text-right" value="<?= $it['discount_amount']; ?>"></td>
              <td><input type="text" class="grid-cell totalRow text-right font-weight-bold" style="color:var(--blue-accent);" readonly></td>
              <td class="text-center" style="padding-right: 6px !important;"><button type="button" class="btn-del-cell remove" title="Remove">&times;</button></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- EQUAL 50/50 BOTTOM SUMMARY, EXPANDED NOTES & UPDATE BUTTON -->
  <div class="pos-card summary-container">
    <div class="row">
      
      <!-- LEFT 50%: Financial Metrics -->
      <div class="col-xs-12 col-md-6" style="margin-bottom: 6px;">
        <div class="summary-metric-card">
          <div class="stat-row">
            <span>Gross Total</span>
            <strong>₹ <span id="gross"><?= number_format((float)$quote['subtotal'], 2); ?></span></strong>
          </div>
          <div class="stat-row net-bold">
            <span>Net Payable</span>
            <strong>₹ <span id="net"><?= number_format((float)$quote['net_total'], 2); ?></span></strong>
          </div>
          <div class="stat-row" style="color:var(--emerald-accent);">
            <span>Paid Amount</span>
            <strong>₹ <span id="paid"><?= number_format((float)$quote['paid_amount'], 2); ?></span></strong>
          </div>
          <div class="stat-row due-bold">
            <span>Balance Due</span>
            <strong>₹ <span id="balance"><?= number_format((float)$quote['due_amount'], 2); ?></span></strong>
          </div>
          <div class="stat-row">
            <span>Change / Return</span>
            <strong>₹ <span id="returnAmt">0.00</span></strong>
          </div>
        </div>
      </div>

      <!-- RIGHT 50%: Terms, Stock & Update Action Button -->
      <div class="col-xs-12 col-md-6" style="display:flex; flex-direction:column; justify-content:space-between;">
        <div>
          <div class="row" style="margin-bottom: 6px;">
            <div class="col-xs-8">
              <select id="termsTemplate" class="form-control" style="font-size:12px; height:30px !important;">
                <option value="">Select Terms Template</option>
                <?php foreach($terms_templates as $t): ?>
                  <option value="<?= htmlspecialchars($t['template']); ?>" <?= trim($quote['terms_conditions']) == trim($t['template']) ? 'selected' : ''; ?>>
                    <?= htmlspecialchars($t['template_name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-xs-4">
              <div id="stockInfo" class="stock-tag-box" title="Stock Info">Stock: —</div>
            </div>
          </div>
          
          <textarea name="terms_conditions" id="termsBox" class="form-control" style="height: 75px !important; resize: none; font-size:12px; margin-bottom: 6px;" placeholder="Add invoice terms, bank details or remarks..."><?= htmlspecialchars($quote['terms_conditions']); ?></textarea>
        </div>
        
        <button type="submit" name="update_invoice" class="btn btn-save-master">
          💾 Update & Print Invoice
        </button>
      </div>

    </div>
  </div>

  <!-- COMPACT PAYMENT BREAKDOWN MODAL -->
  <div class="modal fade" id="paymentModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-compact" role="document">
      <div class="modal-content" style="border-radius:6px; border:none; box-shadow:0 15px 35px rgba(0,0,0,0.25);">
        <div class="modal-header" style="background:#0284c7; color:#ffffff; border-top-left-radius:6px; border-top-right-radius:6px; padding:8px 14px;">
          <button type="button" class="close" data-dismiss="modal" style="color:#ffffff; opacity:0.9;">&times;</button>
          <h5 class="modal-title" style="font-weight:700; margin:0; font-size:13px;">Payment Modes</h5>
        </div>
        <div class="modal-body" style="padding:10px 14px;">
          <table class="table mb-0" style="margin-bottom:0;">
            <?php foreach($payment_modes as $pm): ?>
            <?php
              $checked = '';
              $old_amt = 0;
              $old_utr = '';
              foreach($old_payments as $op){
                if(strtolower($op['payment_mode']) == strtolower($pm['mode_name'])){
                  $checked = 'checked';
                  $old_amt = $op['amount'];
                  $old_utr = $op['reference_no'];
                }
              }
            ?>
            <tr>
              <td width="26" style="vertical-align:middle; padding: 4px 2px;">
                <input type="checkbox" class="payCheck" data-mode="<?=$pm['id'];?>" <?=$checked;?> style="margin:0; cursor:pointer;">
              </td>
              <td style="font-size:11.5px; font-weight:700; vertical-align:middle; padding: 4px 2px;">
                <?= strtoupper($pm['mode_name']); ?>
              </td>
              <td width="150" style="padding: 4px 2px;">
                <input type="number" step="0.01" min="0" name="payment_amount[<?=$pm['id'];?>]" class="form-control payAmt text-right" <?=$checked ? '' : 'disabled'; ?> data-mode="<?=$pm['id'];?>" value="<?=$old_amt;?>" style="height: 26px !important; font-size: 11.5px; margin-bottom: 2px;">
                <?php if(strtolower($pm['mode_name']) != 'cash'): ?>
                <input type="text" name="utr_no[<?=$pm['id'];?>]" class="form-control utrField" placeholder="Ref / UTR" <?=$checked ? '' : 'disabled'; ?> data-mode="<?=$pm['id'];?>" value="<?=$old_utr;?>" style="height: 24px !important; font-size: 11px;">
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </table>
        </div>
        <div class="modal-footer" style="padding:6px 14px; background:#f8fafc; border-bottom-left-radius:6px; border-bottom-right-radius:6px;">
          <button type="button" class="btn btn-primary btn-sm font-weight-bold" data-dismiss="modal" style="font-size: 11.5px; padding: 3px 12px;">Done</button>
        </div>
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

<!-- DEMO POP-UP MODAL -->
<div class="modal fade" id="demoModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-compact" role="document">
    <div class="modal-content" style="border-radius:6px; border:none; box-shadow:0 15px 35px rgba(0,0,0,0.25);">
      <div class="modal-header" style="background:var(--navy-dark); color:#ffffff; border-top-left-radius:6px; border-top-right-radius:6px; padding:8px 14px;">
        <button type="button" class="close" data-dismiss="modal" style="color:#ffffff; opacity:0.9;">&times;</button>
        <h5 class="modal-title" style="font-weight:700; margin:0; font-size:13px;">Active Demo Items</h5>
      </div>
      <div class="modal-body" style="padding:10px 14px;">
        <p class="text-muted" style="font-size:11px; margin-bottom:6px;">Select quantities to import:</p>
        <div class="table-responsive" style="border: 1px solid var(--border-slate); border-radius:4px;">
          <table class="table table-bordered table-striped" style="font-size:11.5px; margin-bottom:0;">
            <thead style="background:var(--navy-light); color:#ffffff;">
              <tr>
                <th width="30" class="text-center"><input type="checkbox" id="checkAllDemo" checked></th>
                <th>Product</th>
                <th width="70" class="text-center">Hold</th>
                <th width="90" class="text-center">Qty</th>
                <th width="70" class="text-right">Price</th>
              </tr>
            </thead>
            <tbody id="demoModalTableBody"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer" style="padding:6px 14px; background:#f8fafc; border-bottom-left-radius:6px; border-bottom-right-radius:6px;">
        <button type="button" class="btn btn-default btn-sm" data-dismiss="modal" style="font-size: 11.5px;">Skip</button>
        <button type="button" class="btn btn-success btn-sm font-weight-bold" id="importDemoBtn" style="font-size: 11.5px;">Import</button>
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
            No products in this invoice. Click <b>"Add Products"</b> above to insert items.
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

  updateSummary();
}

/* UPDATE TOTALS SUMMARY */
function updateSummary(){
  let gross = 0;
  document.querySelectorAll(".totalRow").forEach(t => {
    gross += parseFloat(t.value) || 0;
  });

  document.getElementById("gross").innerText = gross.toFixed(2);
  document.getElementById("net").innerText   = gross.toFixed(2);

  let paid = 0;
  document.querySelectorAll(".payAmt").forEach(i => {
    paid += parseFloat(i.value) || 0;
  });

  document.getElementById("paid").innerText = paid.toFixed(2);
  document.getElementById("btnPaidDisplay").innerText = `₹${paid.toFixed(2)}`;

  let balance = gross - paid;
  let returnAmt = 0;

  if(paid > gross){
    returnAmt = paid - gross;
    balance = 0;
  }

  document.getElementById("balance").innerText   = balance.toFixed(2);
  document.getElementById("returnAmt").innerText = returnAmt.toFixed(2);
}

/* PAYMENT HANDLERS */
document.querySelectorAll(".payCheck").forEach(chk => {
  chk.addEventListener("change", function(){
    let net = parseFloat(document.getElementById("net").innerText) || 0;
    let input = document.querySelector(`.payAmt[data-mode='${chk.dataset.mode}']`);
    let utr = document.querySelector(`.utrField[data-mode='${chk.dataset.mode}']`);

    if(chk.checked){
      input.disabled = false;
      if(utr) utr.disabled = false;

      let remaining = net;
      document.querySelectorAll(".payAmt").forEach(i => {
        if(i !== input) remaining -= parseFloat(i.value) || 0;
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

document.querySelectorAll(".payAmt").forEach(input => {
  input.addEventListener("input", function(){
    if(parseFloat(this.value) < 0) this.value = 0;
    updateSummary();
  });
});

function validateCustomer(){
  let cust = document.querySelector("select[name='customer_id']").value;
  if(cust == ""){
    alert("Please select customer");
    return false;
  }
  return true;
}

/* TEMPLATE HANDLER */
document.getElementById("termsTemplate")?.addEventListener("change", function(){
  document.getElementById("termsBox").value = this.value;
});

/* FLATPICKR INITIALIZATION & INITIAL ROW CALCULATION */
document.addEventListener("DOMContentLoaded", function () {
  if (typeof flatpickr !== 'undefined') {
    flatpickr("#invoice_date", {
      dateFormat: "d/M/Y",
      allowInput: false,
      disableMobile: true
    });
  }

  document.querySelectorAll("#billBody tr:not(#emptyRowMsg)").forEach(r => {
    calculate(r);
  });
});

/* DEMO POPUP LOGIC */
let currentDemoData = [];
function checkAndShowDemoModal(custId) {
  if (!custId || custId == "0") return;

  fetch(`get_demo_items.php?customer_id=${custId}`)
    .then(res => res.json())
    .then(data => {
      if (data && data.length > 0) {
        currentDemoData = data;
        let tbody = document.getElementById("demoModalTableBody");
        tbody.innerHTML = "";

        data.forEach((item, index) => {
          tbody.innerHTML += `
            <tr>
              <td class="text-center" style="vertical-align:middle;"><input type="checkbox" class="demoItemCheck" value="${index}" checked></td>
              <td style="vertical-align:middle; font-weight:700;">${item.product_name}</td>
              <td class="text-center" style="vertical-align:middle;"><span class="badge" style="background:#475569;">${item.qty} PCS</span></td>
              <td class="text-center" style="vertical-align:middle;">
                <input type="number" class="form-control text-center invoiceDemoQty" data-max="${item.qty}" value="${item.qty}" min="1" max="${item.qty}" style="height:24px !important; font-size:11px; font-weight:bold; color:var(--blue-accent);">
              </td>
              <td class="text-right" style="vertical-align:middle;">₹${item.sale_price}</td>
            </tr>
          `;
        });

        if (typeof $ !== 'undefined' && $.fn.modal) {
          $('#demoModal').modal('show');
        }
      }
    })
    .catch(err => console.error("Error fetching demo items:", err));
}

document.querySelector("select[name='customer_id']")?.addEventListener("change", function () {
  checkAndShowDemoModal(this.value);
});

document.getElementById("checkAllDemo")?.addEventListener("change", function() {
  document.querySelectorAll(".demoItemCheck").forEach(cb => cb.checked = this.checked);
});

document.addEventListener("input", function(e) {
  if(e.target.classList.contains("invoiceDemoQty")){
    let max = parseFloat(e.target.getAttribute("data-max")) || 0;
    let val = parseFloat(e.target.value) || 0;
    if(val > max){
      alert("Invoiced quantity cannot exceed the available demo quantity (" + max + " PCS)!");
      e.target.value = max;
    }
  }
});

document.getElementById("importDemoBtn")?.addEventListener("click", function () {
  let selectedBoxes = document.querySelectorAll(".demoItemCheck:checked");
  if(selectedBoxes.length === 0){
    alert("Please select at least one item to import!");
    return;
  }

  selectedBoxes.forEach(cb => {
    let index = cb.value;
    let item  = currentDemoData[index];
    let row   = cb.closest("tr");
    let customQty = parseFloat(row.querySelector(".invoiceDemoQty").value) || item.qty;

    let pObj = {
      id: item.product_id,
      name: item.product_name,
      sale_price: item.sale_price,
      gst_percent: item.gst_percent || 0,
      current_stock: 999
    };

    addProduct(pObj);

    let rows = document.querySelectorAll("#billBody tr:not(#emptyRowMsg)");
    let lastRow = rows[rows.length - 1];
    if (lastRow) {
      let qtyInput = lastRow.querySelector(".qty");
      if(qtyInput){
        qtyInput.value = customQty;
        calculate(lastRow);
      }
    }
  });

  $('#demoModal').modal('hide');
});
</script>
