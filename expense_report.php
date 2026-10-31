<?php
$page_title = 'Expense Report';
require_once('includes/load.php');

/* ── ORG NAME ── */
$org_id   = $_SESSION['org_id'] ?? 0;
$org_data = find_by_sql("SELECT org_name FROM organization_master WHERE id = '{$org_id}'");
$org_name = !empty($org_data) ? $org_data[0]['org_name'] : 'Company Name';

/* ── PDF & EXCEL MODE ── */
$is_pdf = isset($_GET['pdf']);
$is_excel = isset($_GET['excel']);

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
/* ── FILE SAVE AS NAME ── */
$file_save_title = htmlspecialchars($org_name) . " - Expense Report (" . date('d-M-Y', strtotime($from)) . " to " . date('d-M-Y', strtotime($to)) . ")";

if ($is_excel) {
    $excel_filename = "Expense_Report_" . date('d-M-Y', strtotime($from)) . "_to_" . date('d-M-Y', strtotime($to)) . ".xls";
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$excel_filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");
}

/* ── SESSION & ROLES ── */
$role_id       = $_SESSION['role_id'] ?? 0;
$user_center   = $_SESSION['center_id'] ?? 0;

/* ══════════════════════════════════════════════════════════
   EXPENSE REPORT QUERY
══════════════════════════════════════════════════════════ */
$txn_center_cond = "";
if ($role_id == 3) {
    $txn_center_cond = " AND e.center_id = '{$user_center}' ";
}

$sql = "
    SELECT 
        e.*, 
        em.category_name 
    FROM expenses e
    LEFT JOIN expense_master em ON em.id = e.category_id
    WHERE e.expense_date BETWEEN '{$from}' AND '{$to}'
    {$txn_center_cond}
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

<?php if ($is_pdf || $is_excel): ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= $file_save_title ?></title>
<?php else: ?>
<?php include_once('layouts/header.php'); ?>
<script>document.title = "<?= $file_save_title ?>";</script>
<?php endif; ?>
<style>
.rpt * { box-sizing: border-box; }
.rpt { font-size: 12px; color: #1e293b; }
.rpt-header { text-align: center; border-bottom: 2px solid #0f172a; padding: 4px 0 7px; margin-bottom: 10px; }
.rpt-header h2 { font-size: 16px; font-weight: 800; text-transform: uppercase; margin: 0 0 2px; }
.rpt-filter { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; margin-bottom: 10px; }
.rpt-filter .form-control { height: 27px; font-size: 11px; padding: 3px 7px; }
.rpt-filter .btn { font-size: 11px; padding: 3px 10px; height: 27px; border:none; }
.rpt-pdf-btn { background: #dc3545; color: #fff !important; padding: 4px 11px; border-radius: 4px; font-size: 11px; text-decoration: none; }
.rpt-excel-btn { background: #107c41; color: #fff !important; padding: 4px 11px; border-radius: 4px; font-size: 11px; text-decoration: none; }
.rpt-tbl { width: 100%; border-collapse: collapse; font-size: 11px; }
.rpt-tbl th, .rpt-tbl td { border: 1px solid #e2e8f0; padding: 6px 10px; }
.rpt-tbl th { background: #f1f5f9; font-weight: 700; text-transform: uppercase; cursor: pointer; user-select: none; }
.rpt-tbl th:hover { background: #e2e8f0; }
.sort-icon { display: inline-block; margin-left: 5px; color: #94a3b8; font-size: 10px; }

/* Summary Cards */
.summary-container { display: flex; gap: 12px; margin-bottom: 15px; }
.summary-card { flex: 1; background: #fff; border-radius: 6px; padding: 12px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); border-left: 4px solid #dc2626; }
.summary-card .title { font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700; }
.summary-card .value { font-size: 20px; font-weight: 800; color: #dc2626; margin-top: 4px; }

/* ================= MOBILE RESPONSIVE FIXES ================= */
@media screen and (max-width: 768px) {
    .summary-container { flex-direction: column !important; }
    .summary-card { width: 100% !important; }
    .mobile-wrap-form { flex-wrap: wrap !important; }
    .mobile-wrap-form > input { flex: 1 1 calc(50% - 6px) !important; min-width: 130px; width: auto !important; }
    .mobile-wrap-form > button, .mobile-wrap-form > a { flex: 1 1 100% !important; justify-content: center; text-align: center; display: flex; align-items: center; }
    .rpt-tbl { min-width: 600px !important; }
}

@media print {
    @page { size: A4 portrait; margin: 10mm; }
    body { background: #fff; }
    .no-print { display: none !important; }
    .rpt-tbl-wrap { box-shadow: none !important; padding: 0 !important; }
}
</style>

<div class="rpt">
    <div class="rpt-header">
        <h2><?= htmlspecialchars($org_name) ?></h2>
        <h3 style="margin:4px 0 6px; font-size:15px; font-weight:700; color:#334155; text-transform:uppercase;">Daily Expense Report</h3>
        <p><?= date('d M Y', strtotime($from)) ?> &mdash; <?= date('d M Y', strtotime($to)) ?></p>
    </div>

    <!-- Filter Form -->
    <?php if (!$is_pdf && !$is_excel): ?>
    <div class="rpt-filter no-print">
        <form method="post" class="mobile-wrap-form" style="margin:0; width:100%; display:flex; align-items:center; gap:6px; flex-wrap:nowrap;">
            <input type="text" name="from" value="<?= date('d/M/Y', strtotime($from)) ?>" class="form-control purchase-datepicker" style="width:135px;" autocomplete="off" required>
            <input type="text" name="to" value="<?= date('d/M/Y', strtotime($to)) ?>" class="form-control purchase-datepicker" style="width:135px;" autocomplete="off" required>
            <button type="submit" name="generate_report" value="1" class="btn" style="background:#2563eb; color:#fff;"><i class="fa fa-file-text-o"></i> Generate</button>
            <a href="?pdf=1&from=<?= $from ?>&to=<?= $to ?>" target="_blank" class="rpt-pdf-btn">&#8659; PDF</a>
            <a href="?excel=1&from=<?= $from ?>&to=<?= $to ?>" target="_blank" class="rpt-excel-btn">&#8659; Excel</a>
            
            <!-- Global Search Box -->
            <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="🔍 Search records..." class="form-control" style="width:200px; margin-left:auto; outline:none; border:1px solid #cbd5e1;">
        </form>
    </div>
    <?php endif; ?>

    <!-- Summary Widgets -->
    <div class="summary-container">
        <div class="summary-card">
            <div class="title">Total Expenditure For Selected Period</div>
            <div class="value">₹ <?= number_format($grand_total, 2) ?></div>
        </div>
    </div>

    <!-- Detailed Expense Table -->
    <h4 style="font-size:13px; font-weight:700; color:#334155; text-transform:uppercase; margin-bottom:8px;">Expense Breakdown</h4>
    <div class="rpt-tbl-wrap" style="background:#fff; border-radius:8px; padding:10px; box-shadow:0 1px 6px rgba(0,0,0,.07);">
        <?php if (empty($expense_data)): ?>
            <p style="color:#94a3b8; text-align:center; padding: 20px;">No expenses found for the selected period.</p>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="rpt-tbl">
                    <thead>
                        <tr>
                            <th width="5%" onclick="sortTable(0)"># <span class="sort-icon">&#8693;</span></th>
                            <th width="10%" onclick="sortTable(1)">Date <span class="sort-icon">&#8693;</span></th>
                            <th width="15%" onclick="sortTable(2)">Category <span class="sort-icon">&#8693;</span></th>
                            <th width="15%" onclick="sortTable(3)">Ref No <span class="sort-icon">&#8693;</span></th>
                            <th width="25%" onclick="sortTable(4)">Description / Note <span class="sort-icon">&#8693;</span></th>
                            <th width="15%" style="text-align:center;" onclick="sortTable(5)">Payment Mode <span class="sort-icon">&#8693;</span></th>
                            <th width="15%" style="text-align:right;" onclick="sortTable(6)">Amount (₹) <span class="sort-icon">&#8693;</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($expense_data as $exp): ?>
                        <tr>
                            <td><?= $i++; ?></td>
                            <td><?= date('d/M/Y', strtotime($exp['expense_date'])) ?></td>
                            <td><b><?= htmlspecialchars($exp['category_name'] ?? 'Unknown Category') ?></b></td>
                            <td style="font-weight:600; color:#475569;">
                                <?= !empty($exp['reference_no']) ? htmlspecialchars($exp['reference_no']) : '-' ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($exp['description']) ?>
                            </td>
                            <td style="text-align:center;">
                                <span style="background:#e2e8f0; color:#475569; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:700;">
                                    <?= htmlspecialchars($exp['payment_mode']) ?>
                                </span>
                            </td>
                            <td style="text-align:right; color:#dc2626; font-weight:700;">
                                ₹ <?= number_format($exp['amount'], 2) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background:#e2e8f0; font-weight:700;">
                            <td colspan="6" style="text-align:right;">GRAND TOTAL</td>
                            <td style="text-align:right; color:#dc2626; font-size:13px;">
                                ₹ <?= number_format($grand_total, 2) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php if (!$is_pdf && !$is_excel): ?>
<script>
flatpickr(".purchase-datepicker", {
    dateFormat: "d/M/Y",
    allowInput: false,
    disableMobile: true
});

// Global Search Filter
function filterTable() {
    var input = document.getElementById("searchInput");
    var filter = input.value.toUpperCase();
    var table = document.querySelector(".rpt-tbl");
    var tr = table.getElementsByTagName("tr");

    for (var i = 1; i < tr.length - 1; i++) { // Skip header and footer
        tr[i].style.display = "none";
        var td = tr[i].getElementsByTagName("td");
        for (var j = 0; j < td.length; j++) {
            if (td[j]) {
                var txtValue = td[j].textContent || td[j].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                    break;
                }
            }
        }
    }
}

// Column Clicking Sort Logic
function sortTable(n) {
    var table, rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
    table = document.querySelector(".rpt-tbl");
    switching = true;
    dir = "asc"; 
    
    var icons = document.querySelectorAll('.sort-icon');
    icons.forEach(function(icon) { icon.innerHTML = '&#8693;'; });

    while (switching) {
        switching = false;
        rows = table.getElementsByTagName("tr");
        
        for (i = 1; i < (rows.length - 2); i++) { // Ignore header and footer rows
            shouldSwitch = false;
            x = rows[i].getElementsByTagName("td")[n];
            y = rows[i + 1].getElementsByTagName("td")[n];
            
            if (!x || !y) continue;
            
            var valX = x.innerText.trim();
            var valY = y.innerText.trim();
            
            var dateRegex = /^\d{2}\/[A-Za-z]{3}\/\d{4}$/;
            var numX, numY, isNumX, isNumY;
            
            if(dateRegex.test(valX) && dateRegex.test(valY)) {
                numX = new Date(valX.replace(/\//g, ' ')).getTime();
                numY = new Date(valY.replace(/\//g, ' ')).getTime();
                isNumX = !isNaN(numX);
                isNumY = !isNaN(numY);
            } else {
                numX = valX === '-' ? 0 : parseFloat(valX.replace(/[^0-9.-]+/g,""));
                numY = valY === '-' ? 0 : parseFloat(valY.replace(/[^0-9.-]+/g,""));
                isNumX = !isNaN(numX) && /[0-9]/.test(valX);
                isNumY = !isNaN(numY) && /[0-9]/.test(valY);
            }

            if (dir == "asc") {
                if (isNumX && isNumY) {
                    if (numX > numY) { shouldSwitch = true; break; }
                } else {
                    if (valX.toLowerCase() > valY.toLowerCase()) { shouldSwitch = true; break; }
                }
            } else if (dir == "desc") {
                if (isNumX && isNumY) {
                    if (numX < numY) { shouldSwitch = true; break; }
                } else {
                    if (valX.toLowerCase() < valY.toLowerCase()) { shouldSwitch = true; break; }
                }
            }
        }
        if (shouldSwitch) {
            rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
            switching = true;
            switchcount ++; 
        } else {
            if (switchcount == 0 && dir == "asc") {
                dir = "desc";
                switching = true;
            }
        }
    }
    
    // Set arrow for clicked column
    var th = table.getElementsByTagName("th")[n];
    if(th) {
        var currentIcon = th.querySelector('.sort-icon');
        if(currentIcon) {
            currentIcon.innerHTML = dir == "asc" ? '&#9650;' : '&#9660;';
        }
    }
}
</script>
<?php endif; ?>

<?php if ($is_pdf): ?>
<script>window.onload = function() { setTimeout(function() { window.print(); }, 1000); };</script>
<?php endif; ?>

<?php if (!$is_pdf && !$is_excel) include_once('layouts/footer.php'); ?>
