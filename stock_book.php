<?php
require_once('includes/load.php');
// page_require_level(2);

$products = find_all('products');
?>

<?php include_once('layouts/header.php'); ?>

<style>
@media print {
    @page {
        size: A4 portrait;
        margin: 10mm;
    }

    body * {
        visibility: hidden;
    }

    .panel, .panel * {
        visibility: visible;
    }

    .panel {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        box-shadow: none !important;
        border: 1px solid #000 !important;
    }

    body {
        font-size: 12px;
    }

    .btn {
        display: none !important;
    }

    table {
        width: 100% !important;
        border-collapse: collapse !important;
    }

    th, td {
        border: 1px solid #000 !important;
        padding: 6px !important;
        text-align: center;
    }

    .panel-heading {
        font-size: 20px;
        font-weight: bold;
        text-align: center;
        margin-bottom: 15px;
    }
}

#stockTable th {
    cursor: pointer;
    position: relative;
    padding-right: 20px;
    font-size: 12px;
    white-space: nowrap;
}

#stockTable td {
    font-size: 13px;
    padding: 8px 10px !important;
    vertical-align: middle;
}

#stockTable th:after {
    font-family: FontAwesome;
    content: "\f0dc";   /* sort */
    position: absolute;
    right: 6px;
    color: #c5c5c5;
}

#stockTable th.asc:after {
    content: "\f0de";   /* sort-up */
    color: #007bff;
}

#stockTable th.desc:after {
    content: "\f0dd";   /* sort-down */
    color: #007bff;
}

.reorder-link {
    color: #2563eb;
    font-weight: bold;
    text-decoration: underline;
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

.web-link:hover {
    background: #cbd5e1;
}
</style>

<div class="panel panel-default">
    <div class="panel-heading">
        <strong>Stock Book Report</strong>
    </div>

    <div class="panel-body">

        <div class="row" style="margin-bottom:15px;">
            <div class="col-md-4 pull-right">
                <input
                    type="text"
                    id="stockSearch"
                    class="form-control"
                    placeholder="Search Product Name..."
                >
            </div>
        </div>

        <div style="margin-bottom:15px; text-align:right;">
            <button
                type="button"
                onclick="window.print();"
                class="btn btn-danger">
                <i class="fa fa-file-pdf-o"></i> Export PDF
            </button>
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
                    WHEN t.transaction_type IN (2,3,5)
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
            ), 0) AS demo_hold_qty

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

        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle" id="stockTable">
                <thead>
                    <tr style="background:#f2f2f2;">
                        <th width="40">#</th>
                        <th>Product Name</th>
                        <th width="110" class="text-center">On Demo / Hold</th>
                        <th width="120" class="text-center">Available Stock</th>
                        <th width="110" class="text-center">Reorder Level</th>
                        <th width="90" class="text-center">Website</th>
                        <th width="120" class="text-center">Status</th>
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

                    // Formatted numbers without trailing zeros
                    // Decimals ko round karke clean integer (Whole Quantity) banane ke liye
$display_available = (int)round($available_stock);
$display_demo      = (int)round($demo_hold_qty);

                    $reorder = (float)$row['reorder_level'];

                    $status = "In Stock";
                    $color  = "#b6d7a8";

                    if($available_stock <= $reorder){
                        $status = "Low Stock";
                        $color  = "#f9cb9c";
                    }

                    if($available_stock <= ($reorder / 2)){
                        $status = "Critically Low";
                        $color  = "#f4cccc";
                    }
                    ?>

                    <tr>
                        <td><?= $i++; ?></td>

                        <td style="text-align:left; font-weight:600;"><?= htmlspecialchars($row['name']); ?></td>

                        <!-- Demo Stock with Clients -->
                        <td style="color:#d9534f; font-weight:bold;" class="text-center">
                            <?= $display_demo > 0 ? $display_demo : '0'; ?>
                        </td>

                        <!-- Free Available Stock -->
                        <td style="font-weight:bold; color:#2e7d32;" class="text-center">
                            <?= $display_available; ?>
                        </td>

                        <!-- Clickable Reorder Level -> Direct Edit in product.php -->
                        <td class="text-center">
                            <a href="product.php?edit=<?= $row['id']; ?>" class="reorder-link" title="Click to edit Reorder Level in Product Master">
                                <?= (int)$reorder; ?> <i class="fa fa-pencil" style="font-size:10px; margin-left:2px;"></i>
                            </a>
                        </td>

                        <!-- Website Link -->
                        <td class="text-center">
                            <?php if(!empty($row['website_link'])): ?>
                                <a href="<?= htmlspecialchars($row['website_link']); ?>" target="_blank" class="web-link">
                                    <i class="fa fa-globe"></i> Visit
                                </a>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>

                        <td style="background:<?= $color ?>; font-weight:bold; text-align:center;">
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

const table = document.getElementById("stockTable");
const headers = table.querySelectorAll("th");

headers.forEach((header, index) => {
    let asc = true;

    header.addEventListener("click", function () {
        headers.forEach(h=>{
            if(h!==header){
                h.classList.remove("asc","desc");
            }
        });

        const tbody = table.querySelector("tbody");
        const rows = Array.from(tbody.querySelectorAll("tr"));

        rows.sort(function(a,b){
            let x = a.cells[index].innerText.trim();
            let y = b.cells[index].innerText.trim();

            if(!isNaN(parseFloat(x)) && !isNaN(parseFloat(y))){
                return asc
                    ? parseFloat(x)-parseFloat(y)
                    : parseFloat(y)-parseFloat(x);
            }

            return asc
                ? x.localeCompare(y)
                : y.localeCompare(x);
        });

        rows.forEach(row=>tbody.appendChild(row));

        if(asc){
            header.classList.remove("desc");
            header.classList.add("asc");
        }else{
            header.classList.remove("asc");
            header.classList.add("desc");
        }

        asc=!asc;
    });
});
</script>

<?php include_once('layouts/footer.php'); ?>
