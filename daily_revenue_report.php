<?php
$page_title = 'Daily Revenue Report';
require_once('includes/load.php');

/* ── ORG NAME ── */
$org_id   = $_SESSION['org_id'] ?? 0;
$org_data = find_by_sql("SELECT org_name FROM organization_master WHERE id = '{$org_id}'");
$org_name = !empty($org_data) ? $org_data[0]['org_name'] : 'Company Name';

/* ── PDF MODE ── */
$is_pdf = isset($_GET['pdf']);

/* ── DATE FILTER FIX ── */
$today = date('Y-m-d');

$from_raw = $_POST['from'] ?? $_GET['from'] ?? $today;
$to_raw   = $_POST['to']   ?? $_GET['to']   ?? $today;

$from = $today;
$to   = $today;

$formats = ['d/M/Y', 'd-m-Y', 'Y-m-d', 'd/m/Y'];

foreach ($formats as $format) {
    $dt = DateTime::createFromFormat($format, trim($from_raw));
    if ($dt instanceof DateTime) { 
        $from = $dt->format('Y-m-d'); 
        break; 
    }
}

foreach ($formats as $format) {
    $dt = DateTime::createFromFormat($format, trim($to_raw));
    if ($dt instanceof DateTime) { 
        $to = $dt->format('Y-m-d'); 
        break; 
    }
}

/* ── SESSION & ROLES ── */
$role_id       = $_SESSION['role_id'] ?? 0;
$user_center   = $_SESSION['center_id'] ?? 0;
$center_filter = $_POST['center_id'] ?? $_GET['center_id'] ?? '';

/* ══════════════════════════════════════════════════════════
   COMBINED REVENUE & DETAILED TRANSACTION QUERY
══════════════════════════════════════════════════════════ */
$txn_center_cond = "";
if ($role_id == 3) {
    $txn_center_cond = " AND t.center_id = '{$user_center}'";
} elseif ($role_id == 2 && !empty($center_filter)) {
    $txn_center_cond = " AND t.center_id = '{$center_filter}'";
}

$sql = "
SELECT 
    DATE(t.entry_date) AS txn_date,
    'Sale' AS txn_type,
    t.bill_indent_no AS ref_no,
    IFNULL(cm.customer_name, 'Unknown Customer') AS party_name,
    SUM(t.net_price + CASE WHEN t.transaction_id = (SELECT MIN(tm.transaction_id) FROM transaction_master tm WHERE tm.bill_indent_no = t.bill_indent_no) THEN IFNULL((SELECT SUM(s.total_amount) FROM shipping s WHERE s.bill_no = t.bill_indent_no), 0) ELSE 0 END) AS income_amount,
    0 AS expense_amount
FROM transaction_master t
LEFT JOIN invoice i ON i.invoice_no = t.bill_indent_no
LEFT JOIN customer_master cm ON cm.id = i.customer_id
WHERE t.transaction_type = 2 AND DATE(t.entry_date) BETWEEN '{$from}' AND '{$to}' {$txn_center_cond}
GROUP BY t.bill_indent_no, DATE(t.entry_date), cm.customer_name

UNION ALL

SELECT 
    DATE(t.entry_date) AS txn_date,
    'Purchase' AS txn_type,
    t.bill_indent_no AS ref_no,
    IFNULL(sm.supplier_name, 'Unknown Supplier') AS party_name,
    0 AS income_amount,
    SUM(t.net_price + CASE WHEN t.transaction_id = (SELECT MIN(tm.transaction_id) FROM transaction_master tm WHERE tm.bill_indent_no = t.bill_indent_no) THEN IFNULL((SELECT SUM(s.total_amount) FROM shipping s WHERE s.bill_no = t.bill_indent_no), 0) ELSE 0 END) AS expense_amount
FROM transaction_master t
LEFT JOIN supplier_master sm ON sm.id = t.supplier_id
WHERE t.transaction_type = 1 AND DATE(t.entry_date) BETWEEN '{$from}' AND '{$to}' {$txn_center_cond}
GROUP BY t.bill_indent_no, DATE(t.entry_date), sm.supplier_name

ORDER BY txn_date DESC, ref_no ASC
";

$detailed_data = find_by_sql($sql);

// Calculate Grand Totals Dynamically
$grand_income = 0;
$grand_expenditure = 0;

if (!empty($detailed_data)) {
    foreach ($detailed_data as $row) {
        $grand_income += $row['income_amount'];
        $grand_expenditure += $row['expense_amount'];
    }
}
$net_revenue = $grand_income - $grand_expenditure;

if (!$is_pdf) include_once('layouts/header.php');
?>
<style>
.rpt * { box-sizing: border-box; }
.rpt { font-size: 12px; color: #1e293b; }
.rpt-header { text-align: center; border-bottom: 2px solid #0f172a; padding: 4px 0 7px; margin-bottom: 10px; }
.rpt-header h2 { font-size: 16px; font-weight: 800; text-transform: uppercase; margin: 0 0 2px; }
.rpt-filter { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; margin-bottom: 10px; }
.rpt-filter .form-control { height: 27px; font-size: 11px; padding: 3px 7px; }
.rpt-filter .btn { font-size: 11px; padding: 3px 10px; height: 27px; }
.rpt-pdf-btn { background: #dc3545; color: #fff !important; padding: 4px 11px; border-radius: 4px; font-size: 11px; text-decoration: none; }
.rpt-tbl { width: 100%; border-collapse: collapse; font-size: 11px; }
.rpt-tbl th, .rpt-tbl td { border: 1px solid #e2e8f0; padding: 6px 10px; }
.rpt-tbl th { background: #f1f5f9; font-weight: 700; text-transform: uppercase; }

/* Summary Cards */
.summary-container { display: flex; gap: 12px; margin-bottom: 15px; }
.summary-card { flex: 1; background: #fff; border-radius: 6px; padding: 12px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); border-left: 4px solid #2563eb; }
.summary-card.exp { border-left-color: #dc2626; }
.summary-card.net { border-left-color: #16a34a; }
.summary-card .title { font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 700; }
.summary-card .value { font-size: 18px; font-weight: 800; color: #0f172a; margin-top: 4px; }

/* ================= MOBILE RESPONSIVE FIXES ================= */
@media screen and (max-width: 768px) {
    .summary-container { flex-direction: column !important; }
    .summary-card { width: 100% !important; }
    .mobile-wrap-form { flex-wrap: wrap !important; }
    .mobile-wrap-form > input { flex: 1 1 calc(50% - 6px) !important; min-width: 130px; width: auto !important; }
    .mobile-wrap-form > button, .mobile-wrap-form > a { flex: 1 1 100% !important; justify-content: center; text-align: center; display: flex; align-items: center; }
    .rpt-tbl { min-width: 800px !important; }
}

@media print {
    @page { size: A4 portrait; margin: 10mm; }
    body { background: #fff; }
    .no-print { display: none !important; }
    .rpt-tbl-wrap { box-shadow: none !important; padding: 0 !important; }
    a { text-decoration: none !important; color: #1e293b !important; }
}
</style>

<div class="rpt">
    <div class="rpt-header">
        <h2><?= htmlspecialchars($org_name) ?></h2>
        <h3 style="margin:4px 0 6px; font-size:15px; font-weight:700; color:#334155; text-transform:uppercase;">Daily Revenue & Transaction Report</h3>
        <p><?= date('d M Y', strtotime($from)) ?> &mdash; <?= date('d M Y', strtotime($to)) ?></p>
    </div>

    <!-- Filter Form -->
    <?php if (!$is_pdf): ?>
    <div class="rpt-filter no-print">
        <form method="post" class="mobile-wrap-form" style="margin:0; width:100%; display:flex; align-items:center; gap:6px; flex-wrap:nowrap;">
            <input type="text" name="from" value="<?= date('d/M/Y', strtotime($from)) ?>" class="form-control purchase-datepicker" style="width:135px;" autocomplete="off" required>
            <input type="text" name="to" value="<?= date('d/M/Y', strtotime($to)) ?>" class="form-control purchase-datepicker" style="width:135px;" autocomplete="off" required>
            <button type="submit" name="generate_report" value="1" class="btn btn-primary"><i class="fa fa-file-text-o"></i> Generate Report</button>
            <a href="?pdf=1&from=<?= $from ?>&to=<?= $to ?>" target="_blank" class="rpt-pdf-btn">&#8659; Download PDF</a>
        </form>
    </div>
    <?php endif; ?>

    <!-- Summary Widgets -->
    <div class="summary-container">
        <div class="summary-card">
            <div class="title">Total Income (Sales + Shipping)</div>
            <div class="value" style="color:#2563eb;">₹ <?= number_format($grand_income, 2) ?></div>
        </div>
        <div class="summary-card exp">
            <div class="title">Total Expenditure (Purchase + Courier)</div>
            <div class="value" style="color:#dc2626;">₹ <?= number_format($grand_expenditure, 2) ?></div>
        </div>
        <div class="summary-card net">
            <div class="title">Net Revenue (Profit / Loss)</div>
            <div class="value" style="color:<?= $net_revenue >= 0 ? '#16a34a' : '#dc2626' ?>;">
                ₹ <?= number_format($net_revenue, 2) ?>
            </div>
        </div>
    </div>

    <!-- 1. Combined Unified Table -->
    <h4 style="font-size:13px; font-weight:700; color:#334155; text-transform:uppercase; margin-bottom:8px;">Transaction Breakdown</h4>
    <div class="rpt-tbl-wrap" style="background:#fff; border-radius:8px; padding:10px; box-shadow:0 1px 6px rgba(0,0,0,.07);">
        <?php if (empty($detailed_data)): ?>
            <p style="color:#94a3b8; text-align:center; padding: 20px;">No transactions found for the selected period.</p>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="rpt-tbl">
                    <thead>
                        <tr>
                            <th width="10%">Date</th>
                            <th width="8%" style="text-align:center;">Type</th>
                            <th width="15%">Ref No. (Inv/GRN)</th>
                            <th width="25%">Party Name</th>
                            <th width="14%" style="text-align:right;">Income / Sales (₹)</th>
                            <th width="14%" style="text-align:right;">Expenditure / Purchase (₹)</th>
                            <th width="14%" style="text-align:right;">Net Flow (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detailed_data as $dt): 
                            $row_net = $dt['income_amount'] - $dt['expense_amount'];
                        ?>
                        <tr>
                            <td><?= date('d/M/Y', strtotime($dt['txn_date'])) ?></td>
                            <td style="text-align:center;">
                                <?php if($dt['txn_type'] == 'Sale'): ?>
                                    <span style="background:#dbeafe; color:#1d4ed8; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:700;">SALE</span>
                                <?php else: ?>
                                    <span style="background:#fee2e2; color:#b91c1c; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:700;">PURCHASE</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <!-- Yahan apne file ka sahi URL naam daal lena agar zarurat ho -->
                                <?php if($dt['txn_type'] == 'Sale'): ?>
                                    <a href="view_invoice.php?invoice_no=<?= urlencode($dt['ref_no']) ?>" target="_blank" style="color:#2563eb; text-decoration:underline; font-weight:bold;">
                                        <?= htmlspecialchars($dt['ref_no']) ?>
                                    </a>
                                <?php else: ?>
                                    <a href="purchase_view.php?grn_no=<?= urlencode($dt['ref_no']) ?>" target="_blank" style="color:#dc2626; text-decoration:underline; font-weight:bold;">
                                        <?= htmlspecialchars($dt['ref_no']) ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($dt['party_name']) ?></td>
                            <td style="text-align:right; color:#2563eb; font-weight:600;">
                                <?= $dt['income_amount'] > 0 ? '₹ ' . number_format($dt['income_amount'], 2) : '-' ?>
                            </td>
                            <td style="text-align:right; color:#dc2626; font-weight:600;">
                                <?= $dt['expense_amount'] > 0 ? '₹ ' . number_format($dt['expense_amount'], 2) : '-' ?>
                            </td>
                            <td style="text-align:right; font-weight:700; color:<?= $row_net >= 0 ? '#16a34a' : '#dc2626' ?>;">
                                <?= $row_net >= 0 ? '₹ ' . number_format($row_net, 2) : '₹ ' . number_format($row_net, 2) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background:#e2e8f0; font-weight:700;">
                            <td colspan="4" style="text-align:right;">GRAND TOTAL</td>
                            <td style="text-align:right; color:#2563eb;">₹ <?= number_format($grand_income, 2) ?></td>
                            <td style="text-align:right; color:#dc2626;">₹ <?= number_format($grand_expenditure, 2) ?></td>
                            <td style="text-align:right; color:<?= $net_revenue >= 0 ? '#16a34a' : '#dc2626' ?>;">
                                ₹ <?= number_format($net_revenue, 2) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
flatpickr(".purchase-datepicker", {
    dateFormat: "d/M/Y",
    allowInput: false,
    disableMobile: true
});
</script>

<?php if ($is_pdf): ?>
<script>window.onload = function() { setTimeout(function() { window.print(); }, 1000); };</script>
<?php endif; ?>

<?php if (!$is_pdf) include_once('layouts/footer.php'); ?>
