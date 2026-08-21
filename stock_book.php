<?php
require_once('includes/load.php');
// page_require_level(2);

$products = find_all('products');

/* ===== FETCH ORGANIZATION DETAILS FOR PDF BRANDING ===== */
$org_id = $_SESSION['org_id'] ?? 1;

$org_data = find_by_sql("
    SELECT * FROM organization_master 
    WHERE id = '{$org_id}' 
    LIMIT 1
");
$org = !empty($org_data) ? $org_data[0] : [
    'org_name' => 'ORGANIZATION NAME',
    'address'  => 'Organization Address Details',
    'phone'    => '',
    'gst_no'   => ''
];

$master_org = find_by_sql("
    SELECT org_name 
    FROM master_inventory.master_organization 
    WHERE org_id = '{$org_id}' 
    LIMIT 1
");
$company_name = !empty($master_org) ? $master_org[0]['org_name'] : ($org['org_name'] ?? 'ORGANIZATION');

?>

<?php include_once('layouts/header.php'); ?>

<style>
/* BASE STYLES */
body {
    background: #f1f5f9;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.stock-card {
    border: none;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 4px 18px rgba(15,23,42,.06);
    padding: 24px;
    margin-bottom: 30px;
}

/* TABLE STYLING & SORT ARROWS FIX */
#stockTable th {
    background: #0f172a !important;
    color: #fff !important;
    cursor: pointer;
    position: relative;
    padding-right: 25px !important;
    font-size: 12px;
    white-space: nowrap;
    border: none !important;
    user-select: none;
}

/* Default Sort Arrow (Both Up & Down) */
#stockTable th.sortable:after {
    content: " ↕";
    position: absolute;
    right: 8px;
    color: #94a3b8;
    font-size: 11px;
    font-weight: bold;
}

/* Ascending Order Arrow (Up) */
#stockTable th.asc:after {
    content: " ▲";
    position: absolute;
    right: 8px;
    color: #38bdf8;
    font-size: 10px;
    font-weight: bold;
}

/* Descending Order Arrow (Down) */
#stockTable th.desc:after {
    content: " ▼";
    position: absolute;
    right: 8px;
    color: #38bdf8;
    font-size: 10px;
    font-weight: bold;
}

#stockTable td {
    font-size: 13px;
    padding: 10px 12px !important;
    vertical-align: middle;
    border-color: #f1f5f9;
}

.reorder-link {
    color: #2563eb;
    font-weight: bold;
    text-decoration: none !important;
    cursor: pointer;
}

.reorder-link:hover {
    color: #1d4ed8;
}

.web-link {
    padding: 3px 8px;
    border-radius: 4px;
    background: #e2e8f0;
    color: #0f172a;
    font-size: 11px;
    font-weight: 600;
    text-decoration: none !important;
}

/* HOVER BADGE & POPOVER STYLES */
.demo-hold-badge {
    color: #dc2626;
    font-weight: bold;
    cursor: pointer;
    border-bottom: 1px dashed #dc2626;
    display: inline-block;
    padding: 2px 6px;
    border-radius: 4px;
}

.demo-hold-badge:hover {
    background-color: #fee2e2;
}

.popover-header {
    background-color: #0f172a !important;
    color: #ffffff !important;
    font-size: 12px;
    font-weight: 600;
    border-top-left-radius: 6px;
    border-top-right-radius: 6px;
}

.popover-body {
    font-size: 12px;
    padding: 8px 12px;
    color: #334155;
    line-height: 1.5;
}

/* GREEN BUTTON MATCHED WITH ORGANIZATION MASTER */
.btn-generate-report {
    background-color: #00a65a !important;
    border-color: #00a65a !important;
    color: #ffffff !important;
    border-radius: 6px;
    font-weight: 700;
    font-size: 13px;
    padding: 8px 18px;
    transition: all 0.2s ease;
}

.btn-generate-report:hover {
    background-color: #008d4c !important;
    border-color: #008d4c !important;
    color: #ffffff !important;
}

/* ================= MOBILE RESPONSIVE FIXES ================= */
@media (max-width: 768px) {
    .container-fluid {
        padding-left: 10px !important;
        padding-right: 10px !important;
    }
    .stock-card {
        padding: 14px !important;
        border-radius: 8px;
    }
    .btn-generate-report {
        width: 100%;
        margin-top: 10px;
        text-align: center;
    }
    .table-responsive {
        border: 1px solid #e2e8f0;
        -webkit-overflow-scrolling: touch;
    }
    #stockTable {
        min-width: 680px; /* Mobile par clean scroll layout */
    }
}

/* ================= PRINT / PDF EXPORT SPECIFIC STYLES ================= */
.print-org-header {
    display: none;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 8mm 10mm;
    }

    body * {
        visibility: hidden;
    }

    .printable-area, .printable-area * {
        visibility: visible;
    }

    .printable-area {
        position: absolute;
        left: 0;
        top: 0;
        width: 100% !important;
        background: #ffffff !important;
        padding: 0 !important;
        box-shadow: none !important;
    }

    .no-print, 
    .btn, 
    #stockSearch, 
    .reorder-pencil, 
    .web-link,
    .popover,
    .dataTables_filter {
        display: none !important;
    }

    #stockTable th:after {
        content: "" !important;
        display: none !important;
    }

    a[href]:after {
        content: none !important;
    }

    .print-org-header {
        display: flex !important;
        justify-content: space-between;
        align-items: flex-start;
        border-bottom: 2px solid #2563eb;
        padding-bottom: 12px;
        margin-bottom: 15px;
    }

    .print-org-brand {
        flex: 1;
    }

    .print-org-title {
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
        margin: 0 0 4px 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .print-org-sub {
        font-size: 11px;
        color: #475569;
        line-height: 1.4;
    }

    .print-doc-badge {
        text-align: right;
    }

    .print-doc-title {
        font-size: 18px;
        font-weight: 800;
        color: #2563eb;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin: 0 0 4px 0;
    }

    .print-doc-date {
        font-size: 11px;
        color: #64748b;
        font-weight: 600;
    }

    table#stockTable {
        width: 100% !important;
        border-collapse: collapse !important;
        margin-top: 10px !important;
    }

    table#stockTable th {
        background-color: #0f172a !important;
        color: #ffffff !important;
        font-size: 11px !important;
        padding: 8px 6px !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    table#stockTable td {
        border: 1px solid #cbd5e1 !important;
        padding: 6px 8px !important;
        font-size: 11px !important;
    }

    .status-cell {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .reorder-link {
        text-decoration: none !important;
        color: #0f172a !important;
    }
}
</style>

<div class="container-fluid py-4 px-4">
    <div class="stock-card printable-area">
        
        <!-- 🔥 PDF / PRINT EXPORT HEADER 🔥 -->
        <div class="print-org-header">
            <div class="print-org-brand">
                <h1 class="print-org-title"><?= strtoupper($company_name); ?></h1>
                <div class="print-org-sub">
                    <?= htmlspecialchars($org['address'] ?? ''); ?><br>
                    <?php if(!empty($org['gst_no'])): ?>
                        <b>GSTIN:</b> <?= htmlspecialchars($org['gst_no']); ?> | 
                    <?php endif; ?>
                    <?php if(!empty($org['phone'])): ?>
                        <b>Phone:</b> <?= htmlspecialchars($org['phone']); ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="print-doc-badge">
                <div class="print-doc-title">STOCK REPORT</div>
                <div class="print-doc-date">Generated on: <?= date("d/M/Y h:i A"); ?></div>
            </div>
        </div>

        <!-- SCREEN HEADER -->
        <div class="mb-4 pb-2 border-bottom no-print">
            <h4 class="m-0 font-weight-bold text-dark" style="font-size: 20px;">Stock Report</h4>
            <p class="text-muted m-0" style="font-size: 13px;"><?= htmlspecialchars($company_name); ?> - Inventory & Reorder Level Tracking</p>
        </div>

        <!-- SEARCH BAR & GENERATE REPORT BUTTON -->
        <div class="row align-items-center mb-3 no-print">
            <div class="col-md-5">
                <input type="text" id="stockSearch" class="form-control" placeholder="🔍 Search Product Name..." style="border-radius: 8px;">
            </div>
            <div class="col-md-7 text-right">
                <button type="button" onclick="window.print();" class="btn btn-generate-report">
                    <i class="fa fa-file-pdf-o mr-1"></i> Generate Report
                </button>
            </div>
        </div>

        <?php
        $stock_report = find_by_sql("
            SELECT 
                p.id,
                p.name,
                p.reorder_level,
                p.website_link,

            SUM(
                CASE
                    WHEN t.transaction_type IN (1,4)
                    THEN t.quantity
                    ELSE 0
                END
            ) AS total_in,

            SUM(
                CASE
                    WHEN t.transaction_type IN (2,3,5,6)
                    THEN t.quantity
                    ELSE 0
                END
            ) AS total_out,

            -- Active Demo Hold Stock Count (status = 1)
            COALESCE((
                SELECT SUM(d.qty)
                FROM demo_item_detail d
                WHERE d.product_id = p.id
                AND d.status = 1
            ), 0) AS demo_hold_qty,

            -- Group Concatenated Client Details for Hover View
            (
                SELECT GROUP_CONCAT(CONCAT('• ', c.customer_name, ' (', d.qty, ' PCS)') SEPARATOR '<br>')
                FROM demo_item_detail d
                LEFT JOIN customer_master c ON d.customer_id = c.id
                WHERE d.product_id = p.id
                AND d.status = 1
            ) AS demo_client_details
            
            FROM products p

            LEFT JOIN transaction_master t
            ON p.id = t.product_id

            WHERE p.type = 1
            AND p.is_active = 1

            GROUP BY
                p.id,
                p.name,
                p.reorder_level,
                p.website_link

            ORDER BY p.name ASC
        ");
        ?>
<br>
        <!-- STOCK REPORT TABLE WITH SORTABLE HEADERS -->
        <div class="table-responsive" style="border-radius: 10px; overflow: hidden; border: 1px solid #e2e8f0;">
            <table class="table table-bordered align-middle mb-0" id="stockTable">
                <thead>
                    <tr>
                        <th width="40" class="text-center">#</th>
                        <th class="sortable">Product Name</th>
                        <th width="140" class="text-center sortable">On Demo / Hold</th>
                        <th width="140" class="text-center sortable">Available Stock</th>
                        <th width="130" class="text-center sortable">Reorder Level</th>
                        <th width="100" class="text-center no-print">Website</th>
                        <th width="130" class="text-center sortable">Status</th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    $i = 1;

                    foreach($stock_report as $row):

                    // Physical Stock in Godown
                    $physical_stock = (float)$row['total_in'] - (float)$row['total_out'];

                    // Active Demo Stock with Clients
                    $demo_hold_qty = (float)$row['demo_hold_qty'];

                    // Net Available Stock for sale
                    $available_stock = $physical_stock - $demo_hold_qty;

                    // Clean integer formatting
                    $display_available = (int)round($available_stock);
                    $display_demo      = (int)round($demo_hold_qty);

                    $reorder = (float)$row['reorder_level'];

                    $status = "In Stock";
                    $color  = "#b6d7a8"; // Light Green

                    if($available_stock <= $reorder){
                        $status = "Low Stock";
                        $color  = "#f9cb9c"; // Light Orange
                    }

                    if($available_stock <= ($reorder / 2)){
                        $status = "Critically Low";
                        $color  = "#f4cccc"; // Light Red
                    }
                    ?>

                    <tr>
                        <td class="text-center"><?= $i++; ?></td>

                        <td style="text-align:left; font-weight:600; color:#0f172a;">
                            <?= htmlspecialchars($row['name']); ?>
                        </td>

                        <!-- Demo Stock with Clients (Hover Tooltip) -->
                        <td class="text-center">
                            <?php if ($display_demo > 0): ?>
                                <span class="demo-hold-badge" 
                                      data-toggle="popover" 
                                      data-trigger="hover" 
                                      data-placement="top" 
                                      data-html="true" 
                                      title="<b>Demo Dispatched To:</b>" 
                                      data-content="<?= htmlspecialchars($row['demo_client_details'] ?? 'Details not found'); ?>">
                                    <?= $display_demo; ?>
                                </span>
                            <?php else: ?>
                                <span style="color:#64748b; font-weight:bold;">0</span>
                            <?php endif; ?>
                        </td>

                        <!-- Free Available Stock -->
                        <td style="font-weight:bold; color:#2e7d32;" class="text-center">
                            <?= $display_available; ?>
                        </td>

                        <!-- Clickable Reorder Level -> Direct Edit in product.php -->
                        <td class="text-center">
                            <a href="product.php?edit=<?= $row['id']; ?>" class="reorder-link" title="Click to edit Reorder Level in Product Master">
                                <?= (int)$reorder; ?> 
                                <i class="fa fa-pencil reorder-pencil" style="font-size:11px; margin-left:2px;"></i>
                            </a>
                        </td>

                        <!-- Website Link (Hidden in PDF) -->
                        <td class="text-center no-print">
                            <?php if(!empty($row['website_link'])): ?>
                                <a href="<?= htmlspecialchars($row['website_link']); ?>" target="_blank" class="web-link">
                                    <i class="fa fa-globe"></i> Visit
                                </a>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>

                        <td class="status-cell" style="background:<?= $color ?>; font-weight:bold; text-align:center;">
                            <?= $status ?>
                        </td>
                    </tr>

                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<script>
// Search Filter
document.getElementById("stockSearch").addEventListener("keyup", function () {
    var value = this.value.toLowerCase();
    var rows = document.querySelectorAll("#stockTable tbody tr");

    rows.forEach(function(row){
        var text = row.innerText.toLowerCase();
        if(text.indexOf(value) > -1){
            row.style.display = "";
        }else{
            row.style.display = "none";
        }
    });
});

// Table Header Sorting Logic with Visual Arrow Updates
const table = document.getElementById("stockTable");
const headers = table.querySelectorAll("th.sortable");

headers.forEach((header) => {
    let asc = true;

    header.addEventListener("click", function () {
        const index = Array.from(header.parentNode.children).indexOf(header);

        // Reset all sortable headers
        headers.forEach(h => {
            if (h !== header) {
                h.classList.remove("asc", "desc");
            }
        });

        const tbody = table.querySelector("tbody");
        const rows = Array.from(tbody.querySelectorAll("tr"));

        rows.sort(function (a, b) {
            let x = a.cells[index].innerText.trim();
            let y = b.cells[index].innerText.trim();

            if (!isNaN(parseFloat(x)) && !isNaN(parseFloat(y))) {
                return asc
                    ? parseFloat(x) - parseFloat(y)
                    : parseFloat(y) - parseFloat(x);
            }

            return asc
                ? x.localeCompare(y)
                : y.localeCompare(x);
        });

        rows.forEach(row => tbody.appendChild(row));

        if (asc) {
            header.classList.remove("desc");
            header.classList.add("asc");
        } else {
            header.classList.remove("asc");
            header.classList.add("desc");
        }

        asc = !asc;
    });
});

// Initialize Bootstrap Popovers for Hover Details
$(document).ready(function(){
    $('[data-toggle="popover"]').popover({
        html: true,
        trigger: 'hover'
    });
});
</script>

<?php include_once('layouts/footer.php'); ?>
