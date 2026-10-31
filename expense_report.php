<?php
$page_title = 'Expense Report';
require_once('includes/load.php');

/* ── ORG NAME ── */
$org_id   = $_SESSION['org_id'] ?? 0;
$org_data = find_by_sql("SELECT org_name FROM organization_master WHERE id = '{$org_id}'");
$org_name = !empty($org_data) ? $org_data[0]['org_name'] : 'Company Name';

/* ── EXPORT MODES ── */
$is_pdf   = isset($_GET['pdf']);
$is_excel = isset($_GET['excel']);

/* ── FILTERS ── */
$today = date('Y-m-d');
$from_raw = $_POST['from'] ?? $_GET['from'] ?? $today;
$to_raw   = $_POST['to']   ?? $_GET['to']   ?? $today;

$from = $today;
$to   = $today;
$formats = ['d/M/Y', 'd-m-Y', 'Y-m-d', 'd/m/Y'];

foreach ($formats as $format) {
    $dt = DateTime::createFromFormat($format, trim($from_raw));
    if ($dt instanceof DateTime) { $from = $dt->format('Y-m-d'); break; }
}
foreach ($formats as $format) {
    $dt = DateTime::createFromFormat($format, trim($to_raw));
    if ($dt instanceof DateTime) { $to = $dt->format('Y-m-d'); break; }
}

$category_filter     = $_POST['category_id'] ?? $_GET['category_id'] ?? '';
$payment_mode_filter = $_POST['payment_mode'] ?? $_GET['payment_mode'] ?? '';

/* ── FILE SAVE NAME ── */
$file_save_title = htmlspecialchars($org_name) . " - Expense Report (" . date('d-M-Y', strtotime($from)) . " to " . date('d-M-Y', strtotime($to)) . ")";

/* ── EXCEL HEADER EXPORT ── */
if ($is_excel) {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"{$file_save_title}.xls\"");
    header("Pragma: no-cache");
    header("Expires: 0");
}

/* ── SESSION & ROLES ── */
$role_id       = $_SESSION['role_id'] ?? 0;
$user_center   = $_SESSION['center_id'] ?? 0;

/* ══════════════════════════════════════════════════════════
   FETCH MASTER DATA FOR DROPDOWNS
══════════════════════════════════════════════════════════ */
$all_categories = find_by_sql("SELECT id, category_name FROM expense_master ORDER BY category_name ASC");
$all_payment_modes = find_by_sql("SELECT DISTINCT payment_mode FROM expenses WHERE payment_mode IS NOT NULL AND payment_mode != ''");

/* ══════════════════════════════════════════════════════════
   EXPENSE REPORT QUERY
══════════════════════════════════════════════════════════ */
$txn_cond = "";
if ($role_id == 3) {
    $txn_cond .= " AND e.center_id = '{$user_center}' ";
}
if (!empty($category_filter)) {
    $txn_cond .= " AND e.category_id = '{$category_filter}' ";
}
if (!empty($payment_mode_filter)) {
    $txn_cond .= " AND e.payment_mode = '{$payment_mode_filter}' ";
}

$sql = "
    SELECT 
        e.*, 
        em.category_name 
    FROM expenses e
    LEFT JOIN expense_master em ON em.id = e.category_id
    WHERE e.expense_date BETWEEN '{$from}' AND '{$to}'
    {$txn_cond}
    ORDER BY e.expense_date DESC, e.id DESC
";

$expense_data = find_by_sql($sql);

// Calculate Grand Total
$grand_total = 0;
if (!empty($expense_data)) {
    foreach ($expense_data as $row) {
        $grand_total += $row['amount'];
    }
}
?>

<?php if ($is_pdf): ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= $file_save_title ?></title>
<?php elseif (!$is_excel): ?>
<?php include_once('layouts/header.php'); ?>
<script>document.title = "<?= $file_save_title ?>";</script>
<?php endif; ?>

<?php if (!$is_excel): ?>
<style>
.rpt * { box-sizing: border-box; }
.rpt { font-size: 12px; color: #1e293b; }
.rpt-header { text-align: center; border-bottom: 2px solid #0f172a; padding: 4px 0 7px; margin-bottom: 10px; }
.rpt-header h2 { font-size: 16px; font-weight: 800; text-transform: uppercase; margin: 0 0 2px; }
.rpt-filter { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; margin-bottom: 10px; }
.rpt-filter .form-control { height: 27px; font-size: 11px; padding: 3px 7px; }
.rpt-filter .btn { font-size: 11px; padding: 3px 10px; height: 27px; }
.rpt-pdf-btn { background: #dc3545; color: #fff !important; padding: 4px 11px; border-radius: 4px; font-size: 11px; text-decoration: none; display: inline-flex; align-items: center; }
.rpt-excel-btn { background: #16a34a; color: #fff !important; padding: 4px 11px; border-radius: 4px; font-size: 11px; text-decoration: none; display: inline-flex; align-items: center; }
.rpt-tbl { width: 100%; border-collapse: collapse; font-size: 11px; }
.rpt-tbl th, .rpt-tbl td { border: 1px solid #e2e8f0; padding: 6px 10px; }
.rpt-tbl th { background: #f1f5f9; font-weight: 700; text-transform: uppercase; }

/* Summary Cards */
.summary-container { display: flex; gap: 12px; margin-bottom: 15px; }
.summary-card { flex: 1; background: #fff; border-radius: 6px; padding: 12px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); border-left: 4px solid #dc2626; }
.summary-card .title { font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700; }
.summary-card .value { font-size: 20px; font-weight: 800; color: #dc2626; margin-top: 4px; }

/* Mobile Responsive */
@media screen and (max-width: 768px) {
    .summary-container { flex-direction: column !important; }
    .summary-card { width: 100% !important; }
    .mobile-wrap-form { flex-wrap: wrap !important; }
    .mobile-wrap-form > input, .mobile-wrap-form > select { flex: 1 1 calc(50% - 6px) !important; min-width: 130px; width: auto !important; }
    .mobile-wrap-form > button, .mobile-wrap-form > a { flex: 1 1 100% !important; justify-content: center; text-align: center; }
    .rpt-tbl { min-width: 600px !important; }
}

@media print {
    @page { size: A4 portrait; margin: 10mm; }
    body { background: #fff; }
    .no-print { display: none !important; }
    .rpt-tbl-wrap { box-shadow: none !important; padding: 0 !important; }
}
</style>
<?php endif; ?>

<div class="rpt">
    <?php if (!$is_excel): ?>
    <div class="rpt-header">
        <h2><?= htmlspecialchars($org_name) ?></h2>
        <h3 style="margin:4px 0 6px; font-size:15px; font-weight:700; color:#334155; text-transform:uppercase;">Expense Report</h3>
        <p><?= date('d M Y', strtotime($from)) ?> &mdash; <?= date('d M Y', strtotime($to)) ?></p>
    </div>
    <?php endif; ?>

    <!-- Filter Form -->
    <?php if (!$is_pdf && !$is_excel): ?>
    <div class="rpt-filter no-print">
        <form method="post" class="mobile-wrap-form" style="margin:0; width:100%; display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
            
            <input type="text" name="from" value="<?= date('d/M/Y', strtotime($from)) ?>" class="form-control purchase-datepicker" style="width:110px;" autocomplete="off" required>
            <input type="text" name="to" value="<?= date('d/M/Y', strtotime($to)) ?>" class="form-control purchase-datepicker" style="width:110px;" autocomplete="off" required>
            
            <!-- Category Filter -->
            <select name="category_id" class="form-control" style="width:140px;">
                <option value="">All Categories</option>
                <?php foreach($all_categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($category_filter == $cat['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['category_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Payment Mode Filter -->
            <select name="payment_mode" class="form-control" style="width:130px;">
                <option value="">All Modes</option>
                <?php foreach($all_payment_modes as $pm): ?>
                    <option value="<?= htmlspecialchars($pm['payment_mode']) ?>" <?= ($payment_mode_filter == $pm['payment_mode']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($pm['payment_mode']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" name="generate_report" value="1" class="btn btn-primary"><i class="fa fa-filter"></i> Filter</button>
            
            <div style="margin-left:auto; display:flex; gap:6px;">
                <a href="?excel=1&from=<?= $from ?>&to=<?= $to ?>&category_id=<?= $category_filter ?>&payment_mode=<?= urlencode($payment_mode_filter) ?>" target="_blank" class="rpt-excel-btn"><i class="fa fa-file-excel-o"></i> &nbsp;Excel</a>
                <a href="?pdf=1&from=<?= $from ?>&to=<?= $to ?>&category_id=<?= $category_filter ?>&payment_mode=<?= urlencode($payment_mode_filter) ?>" target="_blank" class="rpt-pdf-btn"><i class="fa fa-file-pdf-o"></i> &nbsp;PDF</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <?php if (!$is_excel): ?>
    <!-- Summary Widgets -->
    <div class="summary-container">
        <div class="summary-card">
            <div class="title">Total Expenditure For Selected Period</div>
            <div class="value">₹ <?= number_format($grand_total, 2) ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Detailed Expense Table -->
    <?php if (!$is_excel): ?>
    <h4 style="font-size:13px; font-weight:700; color:#334155; text-transform:uppercase; margin-bottom:8px;">Expense Breakdown</h4>
    <div class="rpt-tbl-wrap" style="background:#fff; border-radius:8px; padding:10px; box-shadow:0 1px 6px rgba(0,0,0,.07);">
    <?php endif; ?>
    
        <?php if (empty($expense_data) && !$is_excel): ?>
            <p style="color:#94a3b8; text-align:center; padding: 20px;">No expenses found for the selected criteria.</p>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="rpt-tbl" border="<?= $is_excel ? '1' : '0' ?>">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="12%">Date</th>
                            <th width="20%">Category</th>
                            <th width="35%">Description / Note</th>
                            <th width="13%" style="text-align:center;">Payment Mode</th>
                            <th width="15%" style="text-align:right;">Amount (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($expense_data as $exp): ?>
                        <tr>
                            <td><?= $i++; ?></td>
                            <td><?= date('d/M/Y', strtotime($exp['expense_date'])) ?></td>
                            <td><b><?= htmlspecialchars($exp['category_name'] ?? 'Unknown Category') ?></b></td>
                            <td>
                                <?= htmlspecialchars($exp['description']) ?>
                                <?php if(!empty($exp['reference_no'])): ?>
                                    <?= $is_excel ? " (Ref: " . htmlspecialchars($exp['reference_no']) . ")" : "<br><span style='color:#64748b; font-size:10px;'>Ref: " . htmlspecialchars($exp['reference_no']) . "</span>" ?>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <?= htmlspecialchars($exp['payment_mode']) ?>
                            </td>
                            <td style="text-align:right; font-weight:700;">
                                <?= $is_excel ? $exp['amount'] : "₹ " . number_format($exp['amount'], 2) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background:#e2e8f0; font-weight:700;">
                            <td colspan="5" style="text-align:right;">GRAND TOTAL</td>
                            <td style="text-align:right; color:#dc2626; font-size:13px;">
                                <?= $is_excel ? $grand_total : "₹ " . number_format($grand_total, 2) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
        
    <?php if (!$is_excel): ?>
    </div>
    <?php endif; ?>
</div>

<?php if (!$is_excel && !$is_pdf): ?>
<script>
flatpickr(".purchase-datepicker", {
    dateFormat: "d/M/Y",
    allowInput: false,
    disableMobile: true
});
</script>
<?php endif; ?>

<?php if ($is_pdf): ?>
<script>window.onload = function() { setTimeout(function() { window.print(); }, 1000); };</script>
<?php endif; ?>

<?php if (!$is_pdf && !$is_excel) include_once('layouts/footer.php'); ?>
