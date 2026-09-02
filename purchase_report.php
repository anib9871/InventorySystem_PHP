<?php
$page_title = 'Purchase Report';
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

$formats = ['d/M/Y', 'd-m-Y', 'Y-m-d'];

foreach ($formats as $format) {
    $dt = DateTime::createFromFormat($format, $from);
    if ($dt instanceof DateTime) {
        $from = $dt->format('Y-m-d');
        break;
    }
}

foreach ($formats as $format) {
    $dt = DateTime::createFromFormat($format, $to);
    if ($dt instanceof DateTime) {
        $to = $dt->format('Y-m-d');
        break;
    }
}
/* ── PDF FILE SAVE AS NAME ── */
$pdf_save_title = htmlspecialchars($org_name) . " - Purchase Report (" . date('d-M-Y', strtotime($from)) . " to " . date('d-M-Y', strtotime($to)) . ")";
/* ── SESSION ── */
$role_id     = isset($_SESSION['role_id']) ? $_SESSION['role_id'] : (isset($_SESSION['user_level']) ? $_SESSION['user_level'] : 0);
$user_center = $_SESSION['center_id'] ?? 0;

/* ── CENTER FILTER (admin only) ── */
$filter_id = $_POST['filter_id'] ?? $_GET['filter_id'] ?? '';
$center_filter = $_POST['center_id'] ?? $_GET['center_id'] ?? '';

$report_type    = $_POST['report_type'] ?? $_GET['report_type'] ?? 'product';
$payment_status = $_POST['payment_status'] ?? $_GET['payment_status'] ?? '';
$show_report    = isset($_POST['generate_report']) || isset($_GET['pdf']);

/* ═══════════════════════════════════════
   1. TOTAL PURCHASE (Filtered by Status Support)
═══════════════════════════════════════ */
$sale_query = "
SELECT SUM(t.total_sale) AS total_sale
FROM (
    SELECT 
        tm.bill_indent_no,
        tm.center_id,
        tm.supplier_id,
        tm.product_id,
        tm.entry_date,
        (
            tm.net_price +
            CASE
                WHEN tm.transaction_id = (
                    SELECT MIN(sub_tm.transaction_id)
                    FROM transaction_master sub_tm
                    WHERE sub_tm.bill_indent_no = tm.bill_indent_no
                )
                THEN IFNULL(
                    (
                        SELECT SUM(s.total_amount)
                        FROM shipping s
                        WHERE s.bill_no = tm.bill_indent_no
                    ),0
                )
                ELSE 0
            END
        ) AS total_sale,
        (SELECT IFNULL(paid_amount, 0) FROM supplier_ledger sl WHERE sl.bill_no = tm.bill_indent_no LIMIT 1) AS paid_amount
    FROM transaction_master tm
    WHERE tm.transaction_type = 1
    AND DATE(tm.entry_date) BETWEEN '{$from}' AND '{$to}'
) t
WHERE 1=1
";

if ($role_id == 3) {
    $sale_query .= " AND t.center_id = '{$user_center}'";
} elseif ($role_id == 2 && !empty($center_filter)) {
    $sale_query .= " AND t.center_id = '{$center_filter}'";
}

if($report_type=='supplier' && !empty($filter_id)){
    $sale_query .= " AND t.supplier_id='{$filter_id}'";
}

if ($payment_status == 'paid') {
    $sale_query .= " AND (t.total_sale - t.paid_amount) <= 0";
} elseif ($payment_status == 'pending') {
    $sale_query .= " AND (t.total_sale - t.paid_amount) > 0";
}

$total_sale_row = find_by_sql($sale_query);
$total_sale     = $total_sale_row[0]['total_sale'] ?? 0;

/* ═══════════════════════════════════════
   2. PAYMENT MODE SUMMARY
═══════════════════════════════════════ */
$pay_q = "
SELECT
    sp.supplier_id,
    sm.supplier_name,
    sp.payment_mode,
    MAX(sp.payment_date) AS payment_date,
    SUM(sp.payment_amount) AS payment_amount
FROM supplier_payment sp
LEFT JOIN supplier_master sm
    ON sm.id = sp.supplier_id
WHERE DATE(sp.payment_date)
BETWEEN '{$from}' AND '{$to}'
AND (sp.reference_no IS NULL OR sp.reference_no <> 'Advance Adjusted')
";

if($report_type=='supplier' && !empty($filter_id)){
    $pay_q .= " AND sp.supplier_id='{$filter_id}'";
}

if ($role_id == 3)                                    $pay_q .= " AND center_id = '{$user_center}'";
elseif ($role_id == 2 && !empty($center_filter)) $pay_q .= " AND center_id = '{$center_filter}'";
$pay_q .= "
GROUP BY sp.supplier_id, sp.payment_mode
ORDER BY sm.supplier_name ASC
";
$payments = find_by_sql($pay_q);

$total_collection = 0;
$mode_summary = [];

foreach ($payments as $pay){
    $total_collection += $pay['payment_amount'];
    
    $mode_name = strtoupper(trim($pay['payment_mode']));
    if (!isset($mode_summary[$mode_name])) {
        $mode_summary[$mode_name] = 0;
    }
    $mode_summary[$mode_name] += (float)$pay['payment_amount'];
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
             LEFT JOIN master_center mc ON mc.center_id = p.center_id
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

    t.bill_indent_no AS grn_no,

    DATE(t.entry_date) AS sale_date,

    mc.center_name,

    p.name,

    sm.supplier_name ,

    t.quantity AS sold_qty,

    t.unit_price AS purchase_price,

t.gst_amount,

(
    t.net_price +
    CASE
        WHEN t.transaction_id = (
            SELECT MIN(tm.transaction_id)
            FROM transaction_master tm
            WHERE tm.bill_indent_no = t.bill_indent_no
        )
        THEN IFNULL(
            (
                SELECT SUM(s2.total_amount)
                FROM shipping s2
                WHERE s2.bill_no = t.bill_indent_no
            ),0
        )
        ELSE 0
    END
) AS total_sale,

(
        (t.unit_price - p.buy_price) * t.quantity
    ) AS profit,

(SELECT IFNULL(paid_amount, 0) FROM supplier_ledger sl WHERE sl.bill_no = t.bill_indent_no LIMIT 1) AS paid_amount

FROM transaction_master t

LEFT JOIN products p
    ON p.id = t.product_id

LEFT JOIN supplier_master sm
    ON sm.id = t.supplier_id

LEFT JOIN master_center mc
    ON mc.center_id = t.center_id

WHERE t.transaction_type = 1
AND t.supplier_id IS NOT NULL
AND t.supplier_id != 0
AND DATE(t.entry_date)
BETWEEN '{$from}' AND '{$to}'
";

if ($role_id == 3)
    $txn_q .= " AND t.center_id = '{$user_center}'";
elseif ($role_id == 2 && !empty($center_filter))
    $txn_q .= " AND t.center_id = '{$center_filter}'";

if($report_type=='product' && !empty($filter_id)){
    $txn_q .= " AND t.product_id='{$filter_id}'";
}

if($report_type=='supplier' && !empty($filter_id)){
    $txn_q .= " AND t.supplier_id='{$filter_id}'";
}

$txn_q .= "
GROUP BY t.transaction_id
ORDER BY t.entry_date DESC
";

$sales = find_by_sql($txn_q);

if ($payment_status == 'paid') {
    $sales = array_filter($sales, function($s) {
        $paid = round((float)($s['paid_amount'] ?? 0));
        $total = round((float)$s['total_sale']);
        return ($total - $paid) <= 0;
    });
} elseif ($payment_status == 'pending') {
    $sales = array_filter($sales, function($s) {
        $paid = round((float)($s['paid_amount'] ?? 0));
        $total = round((float)$s['total_sale']);
        return ($total - $paid) > 0;
    });
}

$sales = array_values($sales);

$grand = 0;
$grand_qty = 0;

foreach ($sales as $s) {
    $grand += $s['total_sale'];
    $grand_qty += $s['sold_qty'];
}

$grand_round = round($grand);
$round_off   = $grand_round - $grand;

/* ═══════════════════════════════════════
   5. PRODUCT CHART DATA
═══════════════════════════════════════ */

$pq = "
SELECT
p.name,
SUM(t.quantity) AS qty,

SUM(
    t.net_price +
    CASE
        WHEN t.transaction_id = (
            SELECT MIN(tm.transaction_id)
            FROM transaction_master tm
            WHERE tm.bill_indent_no = t.bill_indent_no
        )
        THEN IFNULL(
            (
                SELECT SUM(s.total_amount)
                FROM shipping s
                WHERE s.bill_no = t.bill_indent_no
            ),0
        )
        ELSE 0
    END
) AS price
FROM transaction_master t

LEFT JOIN products p
ON p.id = t.product_id

WHERE t.transaction_type = 1
AND t.supplier_id IS NOT NULL
AND t.supplier_id != 0
AND p.type = 1
AND DATE(t.entry_date)
BETWEEN '{$from}' AND '{$to}'
";

if ($role_id == 3)
    $pq .= " AND t.center_id = '{$user_center}'";
elseif ($role_id == 2 && !empty($center_filter))
    $pq .= " AND t.center_id = '{$center_filter}'";

if($report_type=='product' && !empty($filter_id)){
    $pq .= " AND t.product_id='{$filter_id}'";
}

if($report_type=='supplier' && !empty($filter_id)){
    $pq .= " AND t.supplier_id='{$filter_id}'";
}

$pq .= " GROUP BY t.product_id";

$product_data   = find_by_sql($pq);
$product_labels = array_column($product_data, 'name');
$product_qty    = array_column($product_data, 'qty');
$product_price = array_column($product_data,'price');

/* ═══════════════════════════════════════
   6. SUPPLIER CHART DATA
═══════════════════════════════════════ */

$sq = "
SELECT

sm.supplier_name,

SUM(
    t.net_price +
    CASE
        WHEN t.transaction_id = (
            SELECT MIN(tm.transaction_id)
            FROM transaction_master tm
            WHERE tm.bill_indent_no = t.bill_indent_no
        )
        THEN IFNULL(
            (
                SELECT SUM(s.total_amount)
                FROM shipping s
                WHERE s.bill_no = t.bill_indent_no
            ),0
        )
        ELSE 0
    END
) AS price

FROM transaction_master t

LEFT JOIN supplier_master sm
ON sm.id = t.supplier_id

WHERE t.transaction_type = 1
AND t.supplier_id IS NOT NULL
AND t.supplier_id != 0
AND DATE(t.entry_date)
BETWEEN '{$from}' AND '{$to}'
";

if($report_type=='supplier' && !empty($filter_id)){
    $sq .= " AND t.supplier_id='{$filter_id}'";
}

if($report_type=='product' && !empty($filter_id)){
    $sq .= " AND t.product_id='{$filter_id}'";
}

$sq .= " GROUP BY t.supplier_id";

$supplier_data   = find_by_sql($sq);
$supplier_labels = array_column($supplier_data,'supplier_name');
$supplier_price = array_column($supplier_data,'price');

$pie_colors = ['#2563eb','#16a34a','#dc2626','#d97706','#7c3aed','#0891b2','#db2777','#65a30d','#ea580c','#475569'];
?>

<?php if ($is_pdf): ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= $pdf_save_title ?></title>
<?php else: ?>
<?php include_once('layouts/header.php'); ?>
<script>document.title = "<?= $pdf_save_title ?>";</script>
<?php endif; ?>
<style>
.rpt * { box-sizing: border-box; }
.rpt   { font-size: 12px; color: #1e293b; }

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

.rpt-filter { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; margin-bottom: 10px; }
.rpt-filter .form-control { height: 27px; font-size: 11px; padding: 3px 7px; }
.rpt-filter .btn          { font-size: 11px; padding: 3px 10px; height: 27px; }
.rpt-pdf-btn {
  background: #dc3545; color: #fff !important; padding: 4px 11px;
  border-radius: 4px; font-size: 11px; text-decoration: none;
}

.rpt-top { display: flex; gap: 10px; align-items: stretch; margin-bottom: 10px; }

.rpt-summary {
  background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%);
  color: #fff; border-radius: 8px; padding: 10px 13px; flex: 0 0 210px;
  display: flex; flex-direction: column; justify-content: flex-start; gap: 0;
  -webkit-print-color-adjust: exact; print-color-adjust: exact;
}
.rpt-summary .s-lbl { font-size: 9px; opacity: .6; text-transform: uppercase; letter-spacing: .05em; margin: 0 0 2px; }
.rpt-summary .s-big { font-size: 16px; font-weight: 800; margin: 0 0 7px; line-height: 1.1; }
.rpt-summary .s-div { border-top: 1px solid rgba(255,255,255,.18); margin: 5px 0 6px; }
.s-mode-tbl         { width: 100%; border-collapse: collapse; }
.s-mode-tbl td      { font-size: 10px; color: #fff; padding: 2px 2px; }
.s-mode-tbl td:last-child { text-align: right; }
.s-mode-tbl .grand td { border-top: 1px solid rgba(255,255,255,.22); padding-top: 4px; font-weight: 700; font-size: 10px; }

.payment-scroll{
    max-height:95px; overflow-y:auto; margin-top:3px; padding-right:4px;
    scrollbar-width:thin; scrollbar-color:rgba(255,255,255,.35) transparent;
}
.payment-scroll::-webkit-scrollbar{ width:4px; }
.payment-scroll::-webkit-scrollbar-track{ background:transparent; }
.payment-scroll::-webkit-scrollbar-thumb{ background:rgba(255,255,255,.30); border-radius:20px; }
.payment-scroll::-webkit-scrollbar-thumb:hover{ background:rgba(255,255,255,.55); }

.rpt-card {
  background: #fff; border-radius: 8px; padding: 10px 12px;
  box-shadow: 0 1px 6px rgba(0,0,0,.07); display: flex; flex-direction: column;
}
.rpt-card-title {
  font-size: 10px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .04em; color: #475569; margin-bottom: 6px;
}
.rpt-chart-box { position: relative; flex: 1; min-height: 0; }
.rpt-product { flex: 1; }

.rpt-tbl-wrap { background: #fff; border-radius: 8px; padding: 10px 12px; box-shadow: 0 1px 6px rgba(0,0,0,.07); }
.rpt-tbl { width: 100%; border-collapse: collapse; font-size: 11px; }
.rpt-tbl th, .rpt-tbl td { border: 1px solid #e2e8f0; padding: 4px 7px; }
.rpt-tbl th { background: #f1f5f9; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
.rpt-tbl tbody tr:hover { background: #f8fafc; }
.rpt-tbl tfoot td { background: #f1f5f9; font-weight: 700; }

@media screen and (max-width: 768px) {
  .rpt-top { flex-direction: column !important; height: auto !important; gap: 15px !important; }
  .rpt-summary, .rpt-product { flex: 1 1 100% !important; width: 100% !important; }
  .rpt-chart-box { height: 200px !important; }
  .mobile-wrap-form { flex-wrap: wrap !important; }
  .mobile-wrap-form > input, .mobile-wrap-form > select { flex: 1 1 calc(50% - 6px) !important; min-width: 130px !important; }
  .mobile-wrap-form > button, .mobile-wrap-form > a { flex: 1 1 100% !important; justify-content: center; }
  .payment-scroll { max-height: 150px; }
  .rpt-tbl { min-width: 750px !important; }
  .rpt-tbl th, .rpt-tbl td { padding: 6px 4px !important; word-break: break-word; }
}

@media print {
  @page { size: A4 portrait; margin: 10mm; }
  * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }

  body { margin: 0; padding: 0; font-size: 13px !important; line-height: 1.5 !important; background: #fff; color: #1e293b !important; }
  .no-print, .rpt-top { display: none !important; }

  .rpt-header { border-bottom: 2px solid #2563eb !important; margin-bottom: 8px; padding-bottom: 6px; }
  .rpt-header h2 { color: #111827 !important; font-size: 16px !important; }

  .pdf-period-box {
    display: block !important;
    background: #eff6ff !important;
    color: #1e40af !important;
    border: 1px solid #bfdbfe !important;
    padding: 6px 10px !important;
    border-radius: 6px !important;
    margin-bottom: 6px !important;
    font-size: 12px !important;
    font-weight: 700 !important;
  }
  .pdf-period-box table, .pdf-period-box b { color: #1e40af !important; }

  .pdf-total-box {
    display: block !important;
    background: #f8fafc !important;
    color: #0f172a !important;
    border: 1px solid #cbd5e1 !important;
    padding: 8px 10px !important;
    border-radius: 6px !important;
    margin-bottom: 6px !important;
    font-size: 12px !important;
    font-weight: 700 !important;
  }

  .pdf-collection-box {
    display: block !important;
    background: #f0fdf4 !important;
    color: #1e3a8a !important;
    border: 1px solid #93c5fd !important;
    padding: 8px 10px !important;
    border-radius: 6px !important;
    margin-bottom: 8px !important;
    width: 100% !important;
    font-size: 12px !important;
  }
  .pdf-collection-box h4, .pdf-collection-box table td { color: #1e3a8a !important; }

  .rpt-tbl-wrap { padding: 0 !important; box-shadow: none !important; border: none !important; }
  .rpt { width: 100% !important; margin: 0 auto !important; }
  
  .rpt-tbl { width: 100% !important; table-layout: fixed !important; border-collapse: collapse !important; }
  .rpt-tbl th {
    background: #2563eb !important;
    color: #fff !important;
    font-size: 10px !important;
    padding: 5px !important;
  }
  .rpt-tbl td {
    font-size: 10px !important;
    padding: 5px !important;
    word-break: break-word !important;
    color: #1e293b !important;
  }
  .rpt-tbl thead { display: table-header-group; }
  .rpt-tbl tfoot { display: table-row-group; }
  tr { page-break-inside: avoid; }
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
    Purchase Report
  </h3>

  <p>
    <?= date('d M Y', strtotime($from)) ?> &mdash; <?= date('d M Y', strtotime($to)) ?>
  </p>
</div>

  <!-- ── FILTER (screen only) ── -->
  <?php if (!$is_pdf): ?>
  <div class="no-print mb-2" style="margin-bottom:10px;">
    <form method="post" class="mobile-wrap-form" style="display:flex; align-items:center; gap:6px; flex-wrap:nowrap; width:100%;">
      <input type="text" name="from" value="<?= date('d/M/Y', strtotime($from)) ?>" class="form-control purchase-datepicker" style="height:32px; font-size:12px; flex:0 0 110px;" autocomplete="off" required>
      <input type="text" name="to" value="<?= date('d/M/Y', strtotime($to)) ?>" class="form-control purchase-datepicker" style="height:32px; font-size:12px; flex:0 0 110px;" autocomplete="off" required>

      <?php if ($role_id == 2): ?>
      <select name="report_type" id="report_type" class="form-control" style="height:32px; font-size:12px; flex:0 0 115px;">
        <option value="product" <?= ($report_type=='product')?'selected':'' ?>>By Product</option>
        <option value="supplier" <?= ($report_type=='supplier')?'selected':'' ?>>By Supplier</option>
      </select>

      <!-- PAID / PENDING STATUS DROPDOWN -->
      <select name="payment_status" class="form-control" style="height:32px; font-size:12px; flex:0 0 110px;">
          <option value="">All Status</option>
          <option value="paid" <?= ($payment_status == 'paid') ? 'selected' : '' ?>>Paid</option>
          <option value="pending" <?= ($payment_status == 'pending') ? 'selected' : '' ?>>Pending</option>
      </select>

      <?php
      $product_list = find_by_sql("SELECT id, name FROM products WHERE type = 1 ORDER BY name");
      $supplier_list = find_by_sql("SELECT id, supplier_name FROM supplier_master ORDER BY supplier_name");
      ?>

      <select name="filter_id" class="form-control" style="height:32px; font-size:12px; flex:1 1 auto; min-width:130px;">
        <option value="">All</option>
        <?php if($report_type=='product'): ?>
            <?php foreach($product_list as $p): ?>
                <option value="<?= $p['id'] ?>" <?= ($filter_id==$p['id'])?'selected':'' ?>><?= htmlspecialchars($p['name']) ?></option>
            <?php endforeach; ?>
        <?php else: ?>
            <?php foreach($supplier_list as $s): ?>
                <option value="<?= $s['id'] ?>" <?= ($filter_id==$s['id'])?'selected':'' ?>><?= htmlspecialchars($s['supplier_name']) ?></option>
            <?php endforeach; ?>
        <?php endif; ?>
      </select>
      <?php endif; ?>

      <button type="submit" name="generate_report" value="1" class="btn btn-primary" style="height:32px; font-size:12px; white-space:nowrap; padding:0 12px;">
        <i class="fa fa-file-text-o"></i> Generate Report
      </button>

      <?php
      $pdf_params = [
          'pdf'            => 1,
          'from'           => $from,
          'to'             => $to,
          'report_type'    => $report_type,
          'payment_status' => $payment_status,
          'filter_id'      => $filter_id,
      ];
      if ($role_id == 2 && !empty($center_filter)) {
          $pdf_params['center_id'] = $center_filter;
      }
      ?>
      <a href="?<?= http_build_query($pdf_params) ?>" target="_blank" class="rpt-pdf-btn" style="height:32px; font-size:12px; display:inline-flex; align-items:center; white-space:nowrap; background:#dc2626; color:#fff; border-radius:4px; padding:0 12px; text-decoration:none;">
        &#8659; Download PDF
      </a>

      <input type="hidden" name="change_type" id="change_type" value="0">
    </form>
  </div>
  <?php endif; ?>
<script>
document.getElementById('report_type').addEventListener('change', function () {
    document.getElementById('change_type').value = "1";
    this.form.submit();
});
</script>

<?php if($show_report){ ?>

<!-- ── PDF COLLECTION BOX (print only) ── -->

<?php
$selected_supplier = '';

if($report_type=='supplier' && !empty($filter_id)){
    $sup = find_by_sql("
        SELECT supplier_name
        FROM supplier_master
        WHERE id='{$filter_id}'
        LIMIT 1
    ");

    $selected_supplier = $sup[0]['supplier_name'] ?? '';
}
?>

<div class="pdf-period-box" style="display:none;">

    <table style="width:100%; border-collapse:collapse; color:#fff;">
        <tr>

            <td style="text-align:left;">
                <?php if(!empty($selected_supplier)){ ?>
                    <b>Supplier :</b> <?= htmlspecialchars($selected_supplier) ?>
                <?php } ?>
            </td>

            <td style="text-align:right;">
                <b>Period :</b>
                <?= date('d/M/Y', strtotime($from)) ?>
                To
                <?= date('d/M/Y', strtotime($to)) ?>
            </td>

        </tr>
    </table>

</div>

<div class="pdf-total-box" style="display:none;">
  <b>Total Purchase Amount :</b><br>

  ₹ <?= number_format($grand,2) ?><br>

  <?php if(abs($round_off) > 0.001){ ?>
      Round Off :
      <?= ($round_off >= 0 ? '+' : '') . number_format($round_off,2) ?><br>

      <b>Net Total :
      ₹ <?= number_format($grand_round,2) ?></b>
  <?php } ?>
</div>

<?php if ($payment_status != 'pending'): ?>
<div class="pdf-collection-box" style="display:none;">
    <h4 style="margin:0 0 8px; font-size:16px; font-weight:700; color:#fff;">
      Supplier Payment Summary (Mode-wise)
    </h4>
    <table style="width:100%; border-collapse:collapse; color:#fff; font-size:11px;">
      <tr style="font-weight:700; opacity:.7;">
        <td style="padding:2px 4px;">MODE</td>
        <td style="padding:2px 4px; text-align:right;">AMOUNT</td>
      </tr>
      
      <?php foreach ($mode_summary as $mode => $amt): ?>
      <tr>
        <td style="padding:3px 4px;"><?= htmlspecialchars($mode) ?></td>
        <td style="padding:3px 4px;text-align:right;">₹ <?= number_format($amt, 2) ?></td>
      </tr>
      <?php endforeach; ?>
      
      <tr style="border-top:1px solid rgba(255,255,255,.3); font-weight:700;">
        <td style="padding-top:4px;">GRAND TOTAL</td>
        <td style="text-align:right; padding-top:4px;">₹ <?= number_format($total_collection, 2) ?></td>
      </tr>
    </table>
  </div>
<?php endif; ?>

  <!-- ══════════════════════════════════════
        TOP ROW
  ══════════════════════════════════════ -->
  <div class="rpt-top" style="height:185px;">

    <!-- 1. Summary Card -->
    <div class="rpt-summary">
      <div class="s-lbl">Total Purchase</div>
      <div class="s-big">&#8377; <?= number_format($total_sale, 2) ?></div>
      <div class="s-div"></div>

<?php if(abs($round_off) > 0.001){ ?>
<div style="font-size:9px;line-height:14px;margin-bottom:6px;">
    Round Off : <?= ($round_off >= 0 ? '+' : '') . number_format($round_off,2) ?><br>
    <span style="font-size:10px;font-weight:700;">
        Net Total : ₹ <?= number_format($grand_round,2) ?>
    </span>
</div>
<?php } ?>

<?php if($report_type=='supplier'){ ?>

<div class="s-lbl">Payment &mdash; Mode Wise</div>

<div class="payment-scroll">

<table class="s-mode-tbl">
<tr style="opacity:.5;">
    <td style="font-size:8px;text-transform:uppercase;">Mode</td>
    <td style="font-size:8px;text-transform:uppercase;">Date</td>
    <td style="font-size:8px;text-transform:uppercase;text-align:right;">Amount</td>
</tr>

<?php foreach ($payments as $pay): ?>
<tr>

    <td>
        <?= strtoupper(htmlspecialchars($pay['payment_mode'])) ?>
    </td>

    <td>
        <?= date('d/M/Y', strtotime($pay['payment_date'])) ?>
    </td>

    <td style="text-align:right;">
        &#8377; <?= number_format($pay['payment_amount'],2) ?>
    </td>

</tr>
<?php endforeach; ?>

<tr class="grand">
    <td colspan="2">Grand Total</td>

    <td style="text-align:right;">
        &#8377; <?= number_format($total_collection,2) ?>
    </td>
</tr>
</table>

</div>

<?php } else { ?>

<?php if(!empty($filter_id)){ ?>
    <div class="s-lbl">Product Wise Purchase</div>
    <div class="s-big">
        &#8377; <?= number_format($grand,2) ?>
    </div>
<?php } ?>

<?php } ?>
    </div>

<!-- Product Chart -->
<div class="rpt-card rpt-product">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
    <div class="rpt-card-title" style="margin-bottom: 0;">Product Wise Purchase (Qty)</div>
    <div style="font-size: 10px; font-weight: 600; color: #0369a1; background: #f0f9ff; padding: 3px 7px; border-radius: 4px; border: 1px solid #bae6fd;">
      &#9432; Hover on bars for names
    </div>
  </div>
  <div class="rpt-chart-box" style="height:135px;">
    <canvas id="productChart"></canvas>
  </div>
</div>

<!-- Supplier Chart -->
<div class="rpt-card rpt-product">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
    <div class="rpt-card-title" style="margin-bottom: 0;">Supplier Wise Purchase (Qty)</div>
    <div style="font-size: 10px; font-weight: 600; color: #0369a1; background: #f0f9ff; padding: 3px 7px; border-radius: 4px; border: 1px solid #bae6fd;">
      &#9432; Hover on bars for names
    </div>
  </div>
  <div class="rpt-chart-box" style="height:135px;">
    <canvas id="supplierChart"></canvas>
  </div>
</div>

  </div><!-- /.rpt-top -->

  <!-- ══════════════════════════════════════
        TRANSACTION TABLE
  ══════════════════════════════════════ -->
  <div class="rpt-tbl-wrap">
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

    if($report_type == 'supplier'){
        echo 'SUPPLIER WISE PURCHASE REPORT';
    }else{
        echo 'PRODUCT WISE PURCHASE REPORT';
    }

}
?>

</div>

    <?php if (empty($sales)): ?>
      <p style="color:#94a3b8;">No records found for selected period.</p>
    <?php else: ?>
    <div style="overflow-x:auto;">
<table class="rpt-tbl">

<thead>
<tr style="background:#f1f5f9;">
    <th style="width: 10%;">Date</th>
    <th style="width: 12%;">GRN No</th>
    <th style="width: 25%;">Product</th>
    <th style="width: 20%;">Supplier</th>
    <th style="width: 5%; text-align:center;">Qty</th>
    <th style="width: 10%;">Purchase Price</th>
    <th style="width: 8%;">GST</th>
    <th style="width: 10%; text-align:right;">Total</th>
</tr>
</thead>

<tbody>

<?php foreach ($sales as $index => $s): ?>

<tr>
<td>
    <?= date('d/M/Y', strtotime($s['sale_date'])) ?>
</td>

<td>
    <?= htmlspecialchars($s['grn_no']) ?>
</td>

<td>
    <?= htmlspecialchars($s['name']) ?>
</td>

<td>
    <?= htmlspecialchars($s['supplier_name']) ?>
</td>

<td style="text-align:center;">
    <?= $s['sold_qty'] ?>
</td>

<td>
    <?= number_format($s['purchase_price'],2) ?>
</td>

<td>
    ₹ <?= number_format($s['gst_amount'],2) ?>
</td>

<?php  
    $paid_amt = round((float)($s['paid_amount'] ?? 0));
    $total_amt = round((float)$s['total_sale']);
    
    $pending_amt = $total_amt - $paid_amt;
    
    $status_color = ($pending_amt <= 0) ? '#2563eb' : '#dc2626'; 
?>
<td style="text-align:right;">
    <b style="color:<?= $status_color ?>;">
        ₹ <?= number_format($s['total_sale'],2) ?>
    </b>
</td>
</tr>

<?php endforeach; ?>

</tbody>

<?php if(abs($round_off) > 0.001){ ?>
<tfoot>
<tr style="background:#e2e8f0;font-weight:700;">
    <td colspan="4" style="text-align:right;">Grand Total</td>
    <td style="text-align:center;"><?= $grand_qty ?></td>
    <td colspan="2" style="text-align:right;"></td>
    <td style="text-align:right;">₹ <?= number_format($grand,2) ?></td>
</tr>
<tr style="background:#e2e8f0;font-weight:700;">
    <td colspan="7" style="text-align:right;">Round Off</td>
    <td style="text-align:right;"><?= ($round_off >= 0 ? '+' : '') . number_format($round_off,2) ?></td>
</tr>
<tr style="background:#e2e8f0;font-weight:700;">
    <td colspan="7" style="text-align:right;">Net Total</td>
    <td style="text-align:right;"><b>₹ <?= number_format($grand_round,2) ?></b></td>
</tr>
</tfoot>
<?php } else { ?>
<tfoot>
<tr style="background:#e2e8f0;font-weight:700;">
    <td colspan="4" style="text-align:right;">Grand Total</td>
    <td style="text-align:center;"><?= $grand_qty ?></td>
    <td colspan="2" style="text-align:right;"></td>
    <td style="text-align:right;">
        <b>₹ <?= number_format($grand,2) ?></b>
    </td>
</tr>
</tfoot>
<?php } ?>
  </table>
    </div>
    
    <div style="text-align:right; margin-top:8px; font-size:11px;">
        <b>Note:</b>
        <span style="color:#2563eb; font-weight:700; margin-left:8px;">&#9632; Paid = Blue</span> | 
        <span style="color:#dc2626; font-weight:700;">&#9632; Pending = Red</span>
    </div>

    <?php endif; ?>
  </div>

</div><!-- /.rpt -->

<?php } ?>

<script>
new Chart(document.getElementById('productChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($product_labels) ?>,
    datasets: [{
      label: 'Purchase Qty',
      data: <?= json_encode($product_qty) ?>,
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
      legend: { display:false },
      tooltip: {
        callbacks: {
          title: function(context){
            return context[0].label;
          },
        label: function(context){
            var price = <?= json_encode($product_price) ?>[context.dataIndex];
            return [
                'Qty : ' + context.raw,
                'Price : ₹ ' + Number(price).toLocaleString('en-IN',{
                    minimumFractionDigits:2,
                    maximumFractionDigits:2
                })
            ];
        }
        }
      }
    },
    scales: {
      x: {
        grid: { display:false },
        ticks: { display:false }
      },
      y: {
        beginAtZero: true,
        grace: '5%',
        ticks: {
            stepSize: 1,
            precision: 0,
            font: { size: 9 }
        },
        grid: { color: 'rgba(0,0,0,.04)' }
      }
    }
  }
});

new Chart(document.getElementById('supplierChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($supplier_labels) ?>,
    datasets: [{
      label: 'Purchase Qty',
      data: <?= json_encode($supplier_price) ?>,
      borderWidth: 1,
      borderRadius: 4,
      backgroundColor: 'rgba(22,163,74,.72)',
      borderColor: '#16a34a'
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display:false },
      tooltip: {
        callbacks: {
          title: function(context){
            return context[0].label;
          },
        label: function(context){
            return 'Price : ₹ ' +
            Number(context.raw).toLocaleString('en-IN',{
                minimumFractionDigits:2,
                maximumFractionDigits:2
            });
        }
        }
      }
    },
    scales: {
      x: {
        grid: { display:false },
        ticks: { display: false }
      },
      y: {
        beginAtZero: true,
        grace: '5%',
        ticks: { precision: 0 }
      }
    }
  }
});
</script>

<script>
flatpickr(".purchase-datepicker", {
    dateFormat: "d/M/Y",
    allowInput: false,
    disableMobile: true
});
</script>

<?php if ($is_pdf): ?>
<script>
window.onload = function() { setTimeout(function() { window.print(); }, 1200); };
</script>
<?php endif; ?>

<?php if (!$is_pdf) include_once('layouts/footer.php'); ?>
