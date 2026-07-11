<?php
$page_title = 'Business Report';
require_once('includes/load.php');

/* ── ORG NAME ── */
$org_id   = $_SESSION['org_id'] ?? 0;
$org_data = find_by_sql("SELECT org_name FROM organization_master WHERE id = '{$org_id}'");
$org_name = !empty($org_data) ? $org_data[0]['org_name'] : 'Company Name';

/* ── PDF MODE ── */
$is_pdf = isset($_GET['pdf']);

/* ── DATE FILTER ── */
$today = date('Y-m-d');

$from = $_POST['from'] ?? $_GET['from'] ?? $today;
$to   = $_POST['to']   ?? $_GET['to']   ?? $today;

$today = date('Y-m-d');

$from_input = $_POST['from'] ?? $_GET['from'] ?? date('d-m-Y');
$to_input   = $_POST['to']   ?? $_GET['to']   ?? date('d-m-Y');

$from = DateTime::createFromFormat('d/M/Y', $from_input);
$to   = DateTime::createFromFormat('d/M/Y', $to_input);

$from = $from ? $from->format('Y-m-d') : date('Y-m-d');
$to   = $to ? $to->format('Y-m-d') : date('Y-m-d');
/* ── SESSION ── */
$role_id     = $_SESSION['role_id'];
$user_center = $_SESSION['center_id'] ?? 0;

/* ── CENTER FILTER (admin only) ── */
$view_type = $_POST['view_type'] ?? $_GET['view_type'] ?? 'product';
$filter_id = $_POST['filter_id'] ?? $_GET['filter_id'] ?? '';


/* ═══════════════════════════════════════
   1. TOTAL SALE
═══════════════════════════════════════ */
$sale_query = "
SELECT SUM(t.sale_net) as total_sale
FROM transaction_master t

LEFT JOIN invoice i
ON i.invoice_no = t.bill_indent_no
WHERE t.transaction_type = 2
AND i.customer_id IS NOT NULL
AND t.sale_net > 0
AND t.bill_indent_no NOT LIKE 'MFG%'
AND DATE(t.entry_date)
BETWEEN '{$from}' AND '{$to}'
";

if ($role_id == 3)                               $sale_query .= " AND center_id = '{$user_center}'";
elseif ($role_id == 2 && !empty($center_filter)) $sale_query .= " AND center_id = '{$center_filter}'";

if($view_type=='product' && !empty($filter_id)){
    $sale_query .= " AND t.product_id='{$filter_id}'";
}

if($view_type=='customer' && !empty($filter_id)){
    $sale_query .= " AND i.customer_id='{$filter_id}'";
}

$total_sale_row = find_by_sql($sale_query);
$total_sale     = $total_sale_row[0]['total_sale'] ?? 0;

/* ═══════════════════════════════════════
   2. PAYMENT MODE SUMMARY
═══════════════════════════════════════ */
$pay_q = "
SELECT
p.payment_mode,
MAX(p.payment_date) AS payment_date,
SUM(p.amount) AS total_amount
FROM payments p

WHERE DATE(p.payment_date)
BETWEEN '{$from}' AND '{$to}'
";

if ($role_id == 3)
    $pay_q .= " AND p.center_id = '{$user_center}'";
elseif ($role_id == 2 && !empty($center_filter))
    $pay_q .= " AND p.center_id = '{$center_filter}'";

if($view_type=='customer' && !empty($filter_id)){
    $pay_q .= " AND p.customer_id='{$filter_id}'";
}

$pay_q .= " GROUP BY p.payment_mode";

$payments = find_by_sql($pay_q);

$total_collection = 0;
foreach ($payments as $pay){
    $total_collection += $pay['total_amount'];
}

/* ═══════════════════════════════════════
   3. CENTER WISE SALES (admin only)
═══════════════════════════════════════ */
$center_sales = [];
if ($role_id == 2) {
    $cq = "SELECT x.center_name, x.center_id, SUM(x.mode_total) as total_sale,
             GROUP_CONCAT(CONCAT(x.payment_mode,' : Rs.',FORMAT(x.mode_total,2)) SEPARATOR ' | ') as payment_modes
           FROM (
SELECT mc.center_name, p.center_id, p.payment_mode, SUM(p.amount) as mode_total
FROM payments p

LEFT JOIN master_center mc 
ON mc.center_id = p.center_id

WHERE DATE(p.payment_date) BETWEEN '{$from}' AND '{$to}'";
    if (!empty($center_filter)) $cq .= " AND p.center_id = '{$center_filter}'";
    $cq .= " GROUP BY p.center_id, p.payment_mode) x GROUP BY x.center_id, x.center_name";
    $center_sales = find_by_sql($cq);
}

/* ═══════════════════════════════════════
   4. TRANSACTION LIST
═══════════════════════════════════════ */
$txn_q = "

SELECT

    t.entry_date AS sale_date,

    t.bill_indent_no AS invoice_no,

    cm.customer_name,

    p.name,

    t.quantity AS sold_qty,

    t.unit_price AS sell_price,

    t.discount_amount,

    t.gst_amount,

    t.sale_net AS total_sale

FROM transaction_master t

LEFT JOIN invoice i
    ON i.invoice_no = t.bill_indent_no

LEFT JOIN customer_master cm
    ON cm.id = i.customer_id

LEFT JOIN products p
    ON p.id = t.product_id

WHERE t.transaction_type = 2

AND i.customer_id IS NOT NULL

AND t.sale_net > 0

AND cm.customer_name IS NOT NULL

AND DATE(t.entry_date)
BETWEEN '{$from}' AND '{$to}'

";

if ($role_id == 3)
    $txn_q .= " AND t.center_id = '{$user_center}'";

elseif ($role_id == 2 && !empty($center_filter))
    $txn_q .= " AND t.center_id = '{$center_filter}'";

if($view_type=='product' && !empty($filter_id)){
    $txn_q .= " AND t.product_id='{$filter_id}'";
}

if($view_type=='customer' && !empty($filter_id)){
    $txn_q .= " AND i.customer_id='{$filter_id}'";
}

$txn_q .= " ORDER BY t.entry_date DESC";

$sales = find_by_sql($txn_q);

$grand = 0;

foreach ($sales as $s) {
    $grand += $s['total_sale'];
}

/* ═══════════════════════════════════════
   5. PRODUCT CHART DATA
═══════════════════════════════════════ */

$pq = "
SELECT

    p.id,
    p.name,

    SUM(ii.qty) AS qty,

    SUM(ii.line_total) AS total

FROM invoice_items ii

INNER JOIN invoice i
    ON i.id = ii.invoice_id

INNER JOIN products p
    ON p.id = ii.product_id

WHERE DATE(i.invoice_date)
BETWEEN '{$from}' AND '{$to}'
";

if ($role_id == 3){
    $pq .= " AND ii.center_id='{$user_center}'";
}
elseif ($role_id == 2 && !empty($center_filter)){
    $pq .= " AND ii.center_id='{$center_filter}'";
}

if($view_type=='product' && !empty($filter_id)){
    $pq .= " AND ii.product_id='{$filter_id}'";
}

if($view_type=='customer' && !empty($filter_id)){
    $pq .= " AND i.customer_id='{$filter_id}'";
}

$pq .= "
GROUP BY p.id,p.name
ORDER BY qty DESC
";

$product_data = find_by_sql($pq);

$product_labels = array_column($product_data,'name');

$product_qty = array_column($product_data,'qty');

$product_total = array_column($product_data,'total');


/* ═══════════════════════════════════════
   CUSTOMER CHART DATA
═══════════════════════════════════════ */

$cq = "
SELECT 
    cm.customer_name,
    SUM(t.sale_net) as total_sale

FROM transaction_master t

LEFT JOIN invoice i
    ON i.invoice_no = t.bill_indent_no

LEFT JOIN customer_master cm
    ON cm.id = i.customer_id

WHERE t.transaction_type = 2
AND t.sale_net > 0
AND DATE(t.entry_date)
BETWEEN '{$from}' AND '{$to}'
";

if($view_type=='product' && !empty($filter_id)){
    $cq .= " AND t.product_id='{$filter_id}'";
}

if($view_type=='customer' && !empty($filter_id)){
    $cq .= " AND i.customer_id='{$filter_id}'";
}

$cq .= "
GROUP BY cm.id, cm.customer_name
ORDER BY total_sale DESC
LIMIT 10
";

$customer_data = find_by_sql($cq);

$customer_labels = array_column($customer_data, 'customer_name');

$customer_sales = array_column($customer_data, 'total_sale');

$customer_data = find_by_sql($cq);

$customer_labels = array_column($customer_data, 'customer_name');

$customer_sales = array_column($customer_data, 'total_sale');

/* ═══════════════════════════════════════
   6. CENTER CHART DATA
═══════════════════════════════════════ */
$center_labels  = [];
$center_amounts = [];
$center_detail  = [];
foreach ($center_sales as $c) {
    $center_labels[]  = $c['center_name'];
    $center_amounts[] = (float)$c['total_sale'];
    $rows = [];
    if (!empty($c['payment_modes']))
        foreach (explode(' | ', $c['payment_modes']) as $part)
            $rows[] = array_map('trim', explode(' : ', trim($part)));
    $center_detail[] = $rows;
}

/* ── COLORS shared PHP + JS ── */
$pie_colors = ['#2563eb','#16a34a','#dc2626','#d97706','#7c3aed','#0891b2','#db2777','#65a30d','#ea580c','#475569'];

if (!$is_pdf) include_once('layouts/header.php');
?>
<style>
/* ════ BASE ════ */
.rpt * { box-sizing: border-box; }
.rpt   { font-size: 12px; color: #1e293b; }

/* ════ ORG HEADER ════ */
.rpt-header {
  text-align: center;
  border-bottom: 2px solid #0f172a;
  padding: 4px 0 7px;
  margin-bottom: 10px;
}
.rpt-header h2 {
  font-size: 16px; font-weight: 800; text-transform: uppercase;
  letter-spacing: .07em; color: #0f172a; margin: 0 0 2px;
}
.rpt-header p { font-size: 10px; color: #64748b; letter-spacing: .06em; text-transform: uppercase; margin: 0; }

/* ════ FILTER ════ */
.rpt-filter { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; margin-bottom: 10px; }
.rpt-filter .form-control { height: 27px; font-size: 11px; padding: 3px 7px; }
.rpt-filter .btn          { font-size: 11px; padding: 3px 10px; height: 27px; }
.rpt-pdf-btn {
  background: #dc3545; color: #fff !important; padding: 4px 11px;
  border-radius: 4px; font-size: 11px; text-decoration: none;
}

/* ════ TOP ROW (3 columns) ════ */
.rpt-top {
  display: flex;
  gap: 10px;
  align-items: stretch;
  margin-bottom: 10px;
}

/* ── Summary Card ── */
.rpt-summary {
  background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%);
  color: #fff;
  border-radius: 8px;
  padding: 10px 13px;
  flex: 0 0 210px;
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
  gap: 0;
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}
.rpt-summary .s-lbl { font-size: 9px; opacity: .6; text-transform: uppercase; letter-spacing: .05em; margin: 0 0 2px; }
.rpt-summary .s-big { font-size: 16px; font-weight: 800; margin: 0 0 7px; line-height: 1.1; }
.rpt-summary .s-div { border-top: 1px solid rgba(255,255,255,.18); margin: 5px 0 6px; }
.s-mode-tbl         { width: 100%; border-collapse: collapse; }
.s-mode-tbl td      { font-size: 10px; color: #fff; padding: 2px 2px; }
.s-mode-tbl td:last-child { text-align: right; }
.s-mode-tbl .grand td { border-top: 1px solid rgba(255,255,255,.22); padding-top: 4px; font-weight: 700; font-size: 10px; }

/* ── Chart Cards ── */
.rpt-card {
  background: #fff; border-radius: 8px; padding: 10px 12px;
  box-shadow: 0 1px 6px rgba(0,0,0,.07);
  display: flex; flex-direction: column;
}
.rpt-card-title {
  font-size: 10px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .04em; color: #475569; margin-bottom: 6px;
}
.rpt-chart-box { position: relative; flex: 1; min-height: 0; }

/* product chart */
.rpt-product { flex: 1; }

/* center pie + panel */
.rpt-center { flex: 1; }
.rpt-pie-row { display: flex; gap: 8px; flex: 1; min-height: 0; }
.rpt-pie-box { flex: 1; position: relative; min-width: 0; }
.rpt-pie-panel {
  width: 140px; flex-shrink: 0;
  background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;
  padding: 6px 8px; overflow-y: auto; font-size: 10px;
}
.rpt-ci { border-left: 3px solid #ccc; padding-left: 6px; margin-bottom: 8px; }
.rpt-ci-name  { font-weight: 700; font-size: 10px; color: #1e293b; line-height: 1.2; }
.rpt-ci-total { font-weight: 800; font-size: 11px; margin: 1px 0 3px; }
.rpt-ci-mode  {
  display: flex; justify-content: space-between;
  font-size: 9px; color: #64748b;
  border-bottom: 1px dashed #e2e8f0; padding: 1px 0;
}
.rpt-ci-mode span:last-child { font-weight: 700; color: #1e293b; }

/* ════ TRANSACTION TABLE ════ */
.rpt-tbl-wrap { background: #fff; border-radius: 8px; padding: 10px 12px; box-shadow: 0 1px 6px rgba(0,0,0,.07); }
.rpt-tbl { width: 100%; border-collapse: collapse; font-size: 11px; }
.rpt-tbl th, .rpt-tbl td { border: 1px solid #e2e8f0; padding: 4px 7px; }
.rpt-tbl th { background: #f1f5f9; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }

.center-group-heading{
  background:#0f172a !important;
  color:#fff !important;
  font-weight:700;
  font-size:16px !important;
  padding:8px 10px !important;
  text-transform:uppercase;
}

.rpt-tbl tbody tr:hover { background: #f8fafc; }
.rpt-tbl tfoot td { background: #f1f5f9; font-weight: 700; }

/* ════════════════ PRINT ════════════════ */
@media print {

  @page { size: A4 portrait; margin: 10mm; }

  * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }

body {
  margin: 0;
  padding: 0;
  font-size: 14px !important;
  line-height: 1.6 !important;
  background: #fff;
}


.pdf-period-box{
  display:block !important;
  background:#b30000 !important;
  color:#fff !important;
  padding:5px 9px !important;
  border-radius:3px !important;
  margin-bottom:6px !important;
  font-size:13px !important;
  font-weight:700 !important;
  line-height:1.2 !important;
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}

.pdf-total-box{
  display:block !important;
  background:#0f172a !important;
  color:#fff !important;
  padding:5px 9px !important;
  border-radius:3px !important;
  margin-bottom:6px !important;
  font-size:13px !important;
  font-weight:700 !important;
  line-height:1.2 !important;
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}

  /* hide everything except report */
  .no-print        { display: none !important; }
  .rpt-top         { display: none !important; }  /* hide charts in PDF */

  /* ── org header ── */
  .rpt-header { margin-bottom: 8px; padding-bottom: 6px; }
  .rpt-header h2 { font-size: 15px !important; }
  .rpt-header p  { font-size: 10px !important; }

  /* ── collection summary box ── */
.pdf-collection-box{
  display:block !important;
  background:linear-gradient(90deg,#a10805,#111827) !important;
  color:#fff !important;
  padding:6px 10px !important;
  border-radius:4px !important;
  margin-bottom:8px !important;
  width:100% !important;
  font-size:12px !important;
  line-height:1.2 !important;
}

  /* ── table ── */
  .rpt-tbl-wrap {
    padding: 0 !important;
    box-shadow: none !important;
    border: none !important;
  }

  .rpt {
  width: 96% !important;
  margin: 0 auto !important;
}

  .rpt-card-title  { font-size: 10px !important; margin-bottom: 6px !important; }
.rpt-tbl th,
.rpt-tbl td {
  font-size: 13px !important;
  padding: 8px 10px !important;
}
  .rpt-tbl thead   { display: table-header-group; }
  .rpt-tbl tfoot   { display: table-row-group; }
  tr               { page-break-inside: avoid; }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="rpt">

  <!-- ── ORG HEADER ── -->
<div class="rpt-header">
  <h2><?= htmlspecialchars($org_name) ?></h2>

  <h3 style="
    margin:4px 0 6px;
    font-size:15px;
    font-weight:700;
    color:#334155;
    letter-spacing:.04em;
    text-transform:uppercase;
  ">
    Inventory Sales Report
  </h3>

  <p>
    <?= date('d M Y', strtotime($from)) ?> &mdash; <?= date('d M Y', strtotime($to)) ?>
  </p>
</div>

  <!-- ── FILTER (screen only) ── -->
  <?php if (!$is_pdf): ?>
  <div class="rpt-filter no-print">
    <form id="filterform" method="post" class="rpt-filter" style="margin:0; width:100%;">
<input
type="text"
name="from"
value="<?= date('d/M/Y', strtotime($from)) ?>"
class="form-control datepicker"
style="width:135px;"
autocomplete="off"
required>

<input
type="text"
name="to"
value="<?= date('d/M/Y', strtotime($to)) ?>"
class="form-control datepicker"
style="width:135px;"
autocomplete="off"
required>

<select id="view_type" name="view_type" class="form-control" style="width:200px;">



    <option value="product"
        <?= ($view_type=='product') ? 'selected' : '' ?>>
        By Product Sales
    </option>

    <option value="customer"
        <?= ($view_type=='customer') ? 'selected' : '' ?>>
        By Customer Sales
    </option>

</select>

<?php
$product_list = find_by_sql("
SELECT DISTINCT
    p.id,
    p.name
FROM invoice_items ii

INNER JOIN products p
    ON p.id = ii.product_id

ORDER BY p.name
");
$customer_list = find_by_sql("SELECT id,customer_name FROM customer_master ORDER BY customer_name");
?>

<select name="filter_id" class="form-control" style="width:220px;">

<option value="">All</option>

<?php if($view_type=='product'){ ?>

<?php foreach($product_list as $p){ ?>

<option value="<?= $p['id'] ?>" <?= ($filter_id==$p['id'])?'selected':'' ?>>
<?= htmlspecialchars($p['name']) ?>
</option>

<?php } ?>

<?php } else { ?>

<?php foreach($customer_list as $c){ ?>

<option value="<?= $c['id'] ?>" <?= ($filter_id==$c['id'])?'selected':'' ?>>
<?= htmlspecialchars($c['customer_name']) ?>
</option>

<?php } ?>
  
<?php } ?>

</select>

     <button type="submit" class="btn btn-primary">
Generate Report
</button>
      <a href="?pdf=1&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?><?= ($role_id == 2 && $center_filter) ? '&center_id='.urlencode($center_filter) : '' ?>"
         target="_blank" class="rpt-pdf-btn">&#8659; Download PDF</a>
    </form>
  </div>
  <?php endif; ?>

  <!-- ── PDF COLLECTION BOX (print only) ── -->

  <div class="pdf-period-box" style="display:none;">
  <b>Period:</b>
  From <?= date('d-M-Y', strtotime($from)) ?>
  To <?= date('d-M-Y', strtotime($to)) ?>
</div>

<div class="pdf-total-box" style="display:none;">
  <b>Total Collection:</b>
  ₹ <?= number_format($grand, 2) ?>
</div>

  <div class="pdf-collection-box" style="display:none;">
    <h4 style="margin:0 0 8px; font-size:16px; font-weight:700; color:#fff;">
      Collection Summary (Mode-wise)
    </h4>
    <table style="width:100%; border-collapse:collapse; color:#fff; font-size:11px;">
      <tr style="font-weight:700; opacity:.7;">
        <td style="padding:2px 4px;">MODE</td>
        <td style="padding:2px 4px; text-align:right;">COLLECTION</td>
      </tr>
      <?php foreach ($payments as $pay): ?>
      <tr>
        <td style="padding:3px 4px;"><?= strtoupper(htmlspecialchars($pay['payment_mode'])) ?></td>
        <td style="padding:3px 4px; text-align:right;">&#8377; <?= number_format($pay['total_amount'], 2) ?></td>
      </tr>
      <?php endforeach; ?>
      <tr style="border-top:1px solid rgba(255,255,255,.3); font-weight:700;">
        <td style="padding:5px 4px 2px;">GRAND TOTAL</td>
        <td style="padding:5px 4px 2px; text-align:right;">&#8377; <?= number_format($total_collection, 2) ?></td>
      </tr>
    </table>
  </div>

  <!-- ══════════════════════════════════════
       TOP ROW
  ══════════════════════════════════════ -->
  <div class="rpt-top" style="height:185px;">

    <!-- 1. Summary Card -->
    <div class="rpt-summary">
      <div class="s-lbl">Total Sale</div>
      <div class="s-big">&#8377; <?= number_format($total_sale, 2) ?></div>
      <div class="s-div"></div>
<?php if($view_type=='customer'){ ?>

<div class="s-lbl">Collection &mdash; Mode Wise</div>

<table class="s-mode-tbl" style="margin-top:3px;">

<tr style="opacity:.5;">
<td style="font-size:8px;">Mode</td>
<td style="font-size:8px;">Date</td>
<td style="font-size:8px;text-align:right;">Amount</td>
</tr>

<?php foreach($payments as $pay){ ?>

<tr>

<td><?= strtoupper($pay['payment_mode']) ?></td>

<td><?= date('d/M/Y',strtotime($pay['payment_date'])) ?></td>

<td style="text-align:right;">
₹ <?= number_format($pay['total_amount'],2) ?>
</td>

</tr>

<?php } ?>

<tr class="grand">
<td colspan="2">Grand Total</td>
<td style="text-align:right;">
₹ <?= number_format($total_collection,2) ?>
</td>
</tr>

</table>

<?php } else { ?>

<div class="s-lbl">Product Wise Sale</div>

<div class="s-big">
₹ <?= number_format($grand,2) ?>
</div>

<?php } ?>
    </div>

    <!-- 2. Product Bar Chart -->
<div class="rpt-card rpt-product" style="
flex:0 0 36%;
padding:14px;
height:190px;
margin-bottom:25px;
">
      <div class="rpt-card-title">Product Wise Sale (Qty)</div>
      <div class="rpt-chart-box" style="height:155px;">
<div style="height:160px; padding:8px 0;">
   <canvas id="productChart"></canvas>
</div>
      </div>
    </div>

<div class="rpt-card rpt-product" style="
flex:0 0 36%;
padding:14px;
height:190px;
margin-bottom:25px;
overflow:hidden;
">

  <div class="rpt-card-title">
    Customer Wise Sale
  </div>

  <div class="rpt-chart-box" style="height:170px;">
    <canvas id="customerChart"></canvas>
  </div>

</div>



  </div><!-- /.rpt-top -->

  <!-- ══════════════════════════════════════
       TRANSACTION TABLE
  ══════════════════════════════════════ -->
  <div class="rpt-tbl-wrap" style="margin-top:40px;">
   <div class="rpt-card-title">

<?php
if(!empty($center_filter)){

  $center_data = find_by_sql("
    SELECT center_name 
    FROM master_center 
    WHERE center_id='{$center_filter}' 
    LIMIT 1
  ");

  $pdf_center_name = $center_data[0]['center_name'] ?? 'Center Sales Detail';

  echo strtoupper(htmlspecialchars($pdf_center_name)) . ' SALES DETAIL';

}else{

  echo 'PRODUCT WISE INVENTORY SALES';
}
?>

</div>

  <div class="rpt-table-card" style="
margin-top:25px;
width:100%;
clear:both;
background:#fff;
border-radius:12px;
padding:14px;
box-shadow:0 2px 8px rgba(0,0,0,.05);
">
  
    <?php if (empty($sales)): ?>
      <p style="color:#94a3b8;">No records found for selected period.</p>
    <?php else: ?>
    <div style="overflow-x:auto;">
<table class="rpt-tbl">

<thead>
<tr style="background:#f1f5f9;">

<th>Date</th>

<th>Invoice No</th>

<th>Customer</th>

<th>Product</th>

<th>Qty</th>

<th>Price</th>

<th>Discount</th>

<th>GST</th>

<th>Total</th>

</tr>
</thead>

<tbody>

<?php foreach ($sales as $index => $s): ?>

<tr>
<td>
    <?= date('d/M/Y', strtotime($s['sale_date'])) ?>
</td>

<td>
    <?= htmlspecialchars($s['invoice_no']) ?>
</td>


<td>
    <?= htmlspecialchars($s['customer_name'] ?? '-') ?>
</td>

<td>
    <?= htmlspecialchars($s['name']) ?>
</td>

<td style="text-align:center;">
    <?= $s['sold_qty'] ?>
</td>

<td>
    ₹ <?= number_format($s['sell_price'],2) ?>
</td>

<td>
    ₹ <?= number_format($s['discount_amount'],2) ?>
</td>

<td>
    ₹ <?= number_format($s['gst_amount'],2) ?>
</td>

<td>
    <b style="color:#2563eb;">
        ₹ <?= number_format($s['total_sale'],2) ?>
    </b>
</td>
</tr>

<?php endforeach; ?>




</tbody>
<tfoot>
  <tr style="background:#e2e8f0;font-weight:700;">
    <td colspan="7" style="text-align:right;">
      Grand Total
    </td>

    <td>
      <b>₹ <?= number_format($grand, 2) ?></b>
    </td>
  </tr>
</tfoot>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
</div><!-- /.rpt -->

<script>
/* ── Product Bar ── */
new Chart(document.getElementById('productChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($product_labels) ?>,
    datasets: [{
      label: 'Qty Sold',
      data:  <?= json_encode($product_qty) ?>,
      borderWidth: 1, borderRadius: 3,
      backgroundColor: 'rgba(37,99,235,.72)',
      borderColor: '#2563eb'
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
x: {
    grid: {
        display: false
    },
    ticks: {
        display: false
    }
},
y: {
    beginAtZero: true,
    grace: '5%',
    ticks: {
        stepSize: 1,
        precision: 0,
        font: {
            size: 9
        }
    },
    grid: {
        color: 'rgba(0,0,0,.04)'
    }
}
    }
  }
});

/* ── Customer Sales Bar ── */

const customerCanvas = document.getElementById('customerChart');

if(customerCanvas){

new Chart(customerCanvas, {

  type: 'bar',

  data: {

    labels: <?= json_encode($customer_labels) ?>,

    datasets: [{

      label: 'Customer Sales',

      data: <?= json_encode($customer_sales) ?>,

      borderWidth: 1,
      borderRadius: 4,

      backgroundColor: 'rgba(37,99,235,.72)',
      borderColor: '#2563eb'

    }]
  },

  options: {

    responsive: true,
    maintainAspectRatio: false,

    plugins: {
      legend: { display: false }
    },

    scales: {

x: {
    grid: {
        display: false
    },
    ticks: {
        display: false
    }
},

y: {
    beginAtZero: true,
    grace: '5%',
    ticks: {
        precision: 0
    }
}
    }
  }
});

}
</script>

<script>
document.getElementById('view_type').addEventListener('change', function () {
    document.getElementById('filterform').submit();
});

flatpickr(".datepicker", {
    dateFormat: "d/M/Y",
    allowInput: true,
    disableMobile: true
});

</script>

<?php if ($is_pdf): ?>
<script>
window.onload = function() { setTimeout(function() { window.print(); }, 1200); };
</script>
<?php endif; ?>

<?php if (!$is_pdf) include_once('layouts/footer.php'); ?>
