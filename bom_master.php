<?php
$page_title = 'BOM Master';
require_once('includes/load.php');
//page_require_level(2);

/* FETCH CURRENT ORGANIZATION DETAILS */
$org_id = isset($_SESSION['org_id']) ? $_SESSION['org_id'] : 0;
$current_org = find_by_sql("
    SELECT om.*, gsm.state_name 
    FROM organization_master om
    LEFT JOIN gst_state_master gsm ON gsm.id = om.state_id
    WHERE om.id = '{$org_id}' 
    LIMIT 1
");

$org_info = [
    'name' => 'My Organization',
    'address' => '',
    'gst' => '',
    'phone' => ''
];

if($current_org && count($current_org) > 0){
    $org_info['name']    = $current_org[0]['org_name'];
    $org_info['address'] = $current_org[0]['address'];
    $org_info['gst']     = $current_org[0]['gst_no'];
    $org_info['phone']   = $current_org[0]['phone'];
}

/* FETCH BOM PRODUCTS FOR CREATE DROPDOWN */
$bom_products_dropdown = find_by_sql("
    SELECT id, name
    FROM products
    WHERE is_bom = '1'
    ORDER BY name ASC
");

/* RAW MATERIAL & SUB-ASSEMBLY PRODUCTS */
$raw_products = find_by_sql("
    SELECT id, name, buy_price, gst_id, buy_type, is_bom
    FROM products
    WHERE type = 1 AND is_active = 1
    ORDER BY name ASC
");

/* ---------- SAVE / UPDATE BOM ---------- */
if(isset($_POST['save_bom'])){
    $product_id = (int)$_POST['product_id'];

    if($product_id == 0){
        $session->msg("d", "Please select a valid product!");
        redirect('bom_master.php', false);
    }

    /* Delete old BOM configuration */
    $db->query("DELETE FROM bom WHERE product_id='{$product_id}'");

    /* Insert new items */
    if(!empty($_POST['raw_product_id'])){
        foreach($_POST['raw_product_id'] as $i => $raw){
            $raw_id = (int)$raw;
            $qty = (float)$_POST['qty'][$i];

            if($raw_id > 0 && $qty > 0 && $raw_id != $product_id){
                $db->query("
                    INSERT INTO bom(product_id, raw_product_id, quantity)
                    VALUES('{$product_id}', '{$raw_id}', '{$qty}')
                ");
            }
        }
    }

    $session->msg("s", "BOM configuration saved successfully!");
    redirect('bom_master.php', false);
}

/* ---------- DELETE BOM ---------- */
if(isset($_GET['delete'])){
    $pid = (int)$_GET['delete'];
    if($db->query("DELETE FROM bom WHERE product_id='{$pid}'")){
        $session->msg("s", "BOM deleted successfully!");
    } else {
        $session->msg("d", "Failed to delete BOM!");
    }
    redirect('bom_master.php', false);
}

/* ---------- BOM MASTER PRODUCT LIST ---------- */
$bom_products = find_by_sql("
    SELECT DISTINCT b.product_id, p.name 
    FROM bom b
    JOIN products p ON p.id = b.product_id
    ORDER BY p.name ASC
");

include_once('layouts/header.php');
?>

<!-- DATALISTS -->
<datalist id="finished_products_list">
    <?php foreach($bom_products_dropdown as $p): ?>
        <option data-id="<?php echo $p['id']; ?>" value="<?php echo htmlspecialchars($p['name']); ?>"></option>
    <?php endforeach; ?>
</datalist>

<datalist id="raw_products_list">
    <?php foreach($raw_products as $p): ?>
        <option data-id="<?php echo $p['id']; ?>" value="<?php echo htmlspecialchars($p['name']); ?>">
            <?php echo ($p['is_bom'] == 1) ? ' [Sub-Assembly]' : ''; ?>
        </option>
    <?php endforeach; ?>
</datalist>

<div class="container-fluid py-3 px-3">

    <!-- ================= FULL WIDTH BOM LIST ================= -->
    <div class="panel-card">
        <div class="panel-card-header">
            <div>
                <i class="glyphicon glyphicon-th-list" style="color: #2563eb; margin-right: 5px;"></i>
                <strong>BOM LIST (BILL OF MATERIALS)</strong>
            </div>

            <div style="display: flex; gap: 8px; align-items: center;">
                <!-- Create BOM Button -->
                <button type="button" class="btn btn-success-custom btn-custom" data-toggle="modal" data-target="#createBomModal">
                    <i class="glyphicon glyphicon-plus" style="margin-right: 4px;"></i> Create BOM
                </button>

                <div class="btn-group">
                    <button type="button" class="btn btn-default dropdown-toggle btn-custom" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="font-size: 11px; padding: 0 10px;">
                        <i class="glyphicon glyphicon-export"></i> Export <span class="caret"></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-right" style="font-size: 12px; min-width: 120px;">
                        <li><a href="javascript:void(0)" onclick="exportTableToExcel('bomTable', 'BOM_List')"><i class="glyphicon glyphicon-file" style="color: #10b981; margin-right: 5px;"></i> Excel (.xlsx)</a></li>
                        <li><a href="javascript:void(0)" onclick="exportTableToPDF('bomTable', 'BOM_List')"><i class="glyphicon glyphicon-picture" style="color: #ef4444; margin-right: 5px;"></i> PDF (.pdf)</a></li>
                        <li><a href="javascript:void(0)" onclick="exportTableToImage('bomTable', 'BOM_List')"><i class="glyphicon glyphicon-camera" style="color: #2b8cff; margin-right: 5px;"></i> JPEG (.jpg)</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="panel-card-body">
            <div class="row align-items-center mb-3">
                <div class="col-md-4">
                    <div class="search-box">
                        <i class="glyphicon glyphicon-search"></i>
                        <input type="text" id="bomSearch" class="form-control" placeholder="Search BOM product...">
                    </div>
                </div>
            </div>

            <div class="table-responsive" style="border-radius: 6px; border: 1px solid #e2e8f0; overflow: hidden;">
                <table class="table table-bordered table-hover mb-0" id="bomTable">
                    <thead>
                        <tr>
                            <th width="45" class="text-center">#</th>
                            <th>Finished Product Name</th>
                            <th class="text-center" width="120">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($bom_products)): ?>
                            <?php $i = 1; foreach($bom_products as $r): ?>
                            <tr>
                                <td class="text-center" style="font-weight: 600; color: #64748b;"><?php echo $i++; ?></td>
                                <td style="font-weight: 600; color: #0f172a;"><?php echo htmlspecialchars($r['name']); ?></td>
                                <td class="text-center">
                                    <div class="action-cell">
                                        <!-- View Modal Trigger -->
                                        <button type="button" class="btn btn-success btn-xs equal-btn" data-toggle="modal" data-target="#viewModal_<?php echo $r['product_id']; ?>" title="View Details">
                                            <i class="glyphicon glyphicon-eye-open"></i>
                                        </button>
                                        <!-- Edit Modal Trigger -->
                                        <button type="button" class="btn btn-primary btn-xs equal-btn" data-toggle="modal" data-target="#editModal_<?php echo $r['product_id']; ?>" title="Edit BOM">
                                            <i class="glyphicon glyphicon-pencil"></i>
                                        </button>
                                        <!-- Delete Button -->
                                        <button type="button" onclick="confirmDelete(<?php echo $r['product_id']; ?>)" class="btn btn-danger btn-xs equal-btn" title="Delete">
                                            <i class="glyphicon glyphicon-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center" style="color: #94a3b8; padding: 20px;">No BOM configurations found. Click <b>"Create BOM"</b> to add one.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ================= CREATE BOM MODAL ================= -->
<div class="modal fade" id="createBomModal" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog modal-dialog-compact-form" role="document">
        <div class="modal-content" style="border-radius: 8px; overflow: hidden; border: none; box-shadow: 0 8px 24px rgba(0,0,0,0.18);">
            <div class="modal-header modal-header-compact">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">
                    <i class="glyphicon glyphicon-plus" style="color: #38bdf8; margin-right: 5px;"></i> CREATE NEW BOM
                </h4>
            </div>

            <form method="post" action="bom_master.php">
                <div class="modal-body modal-body-compact">
                    
                    <input type="hidden" name="product_id" class="selected_product_id" value="">

                    <div class="form-group" style="margin-bottom: 12px;">
                        <label>Select Finished Product <span class="text-danger">*</span></label>
                        <input type="text" list="finished_products_list" class="form-control finished-search" placeholder="Type to select product..." required autocomplete="off">
                    </div>

<div style="margin-top: 14px; margin-bottom: 8px; display:flex; justify-content:space-between; align-items:center;">
    <label style="color: #0f172a; font-size: 11px; margin:0; font-weight:700; text-transform:uppercase;">Raw Materials Required</label>
    <button type="button" class="btn btn-xs btn-primary-custom addRowBtn" style="font-size: 11px; padding: 3px 8px; border-radius: 4px;">
        <i class="glyphicon glyphicon-plus"></i> Add Item
    </button>
</div>

<div class="bom_rows_container">
    <div class="row bom_row" style="margin-bottom: 6px;">
        <div class="col-xs-7" style="padding-right: 3px;">
            <input type="hidden" name="raw_product_id[]" class="raw_id_hidden" value="">
            <input type="text" list="raw_products_list" class="form-control raw-search" placeholder="Search Raw Material..." required autocomplete="off">
        </div>
        <div class="col-xs-4" style="padding-left: 3px; padding-right: 3px;">
            <input type="number" step="0.01" name="qty[]" class="form-control" placeholder="Qty" required>
        </div>
        <div class="col-xs-1" style="padding-left: 0; padding-right: 15px; text-align: center;">
            <button type="button" class="btn btn-danger btn-xs removeRow" style="background:#ef4444; border-radius: 4px; height: 34px; width: 100%;" title="Remove">-</button>
        </div>
    </div>
</div>

                </div>
                <div class="modal-footer modal-footer-compact">
                    <button type="button" class="btn btn-clear btn-custom" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_bom" class="btn btn-success-custom btn-custom">Save BOM</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= EDIT & VIEW MODALS ================= -->
<?php if(!empty($bom_products)): ?>
    <?php foreach($bom_products as $bp): 
        $current_pid = (int)$bp['product_id'];
        $current_pname = $bp['name'];

        $items = find_by_sql("
            SELECT 
                b.raw_product_id,
                p.name AS raw_name,
                p.buy_price,
                p.buy_type,
                g.gst_percent,
                b.quantity
            FROM bom b
            JOIN products p ON p.id = b.raw_product_id
            LEFT JOIN gst_master g ON g.id = p.gst_id
            WHERE b.product_id = '{$current_pid}'
        ");
    ?>

    <!-- 1. VIEW MODAL -->
    <div class="modal fade" id="viewModal_<?php echo $current_pid; ?>" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-compact-view" role="document">
            <div class="modal-content" style="border-radius: 8px; overflow: hidden; border: none; box-shadow: 0 8px 24px rgba(0,0,0,0.18);">
                <div class="modal-header modal-header-compact" style="display: flex; align-items: center; justify-content: space-between;">
                    <h4 class="modal-title" style="margin: 0;">
                        <i class="glyphicon glyphicon-info-sign" style="color: #38bdf8; margin-right: 5px;"></i>
                        BOM DETAILS: <?php echo strtoupper(htmlspecialchars($current_pname)); ?>
                    </h4>

                    <div style="display: flex; gap: 6px; align-items: center;">
                        <div class="btn-group">
                            <button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="font-size: 10px; padding: 2px 8px; font-weight: 600;">
                                <i class="glyphicon glyphicon-export"></i> Export <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-right" style="font-size: 11px; min-width: 120px;">
                                <li><a href="javascript:void(0)" onclick="exportTableToExcel('bomDetailsTable_<?php echo $current_pid; ?>', 'BOM_Details_<?php echo addslashes($current_pname);?>')"><i class="glyphicon glyphicon-file" style="color: #10b981; margin-right: 4px;"></i> Excel (.xlsx)</a></li>
                                <li><a href="javascript:void(0)" onclick="exportTableToPDF('bomDetailsTable_<?php echo $current_pid; ?>', 'BOM_Details_<?php echo addslashes($current_pname);?>')"><i class="glyphicon glyphicon-picture" style="color: #ef4444; margin-right: 4px;"></i> PDF (.pdf)</a></li>
                                <li><a href="javascript:void(0)" onclick="exportTableToImage('bomDetailsTable_<?php echo $current_pid; ?>', 'BOM_Details_<?php echo addslashes($current_pname);?>')"><i class="glyphicon glyphicon-camera" style="color: #2b8cff; margin-right: 4px;"></i> JPEG (.jpg)</a></li>
                            </ul>
                        </div>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-left: 8px; color:#fff;"><span aria-hidden="true">&times;</span></button>
                    </div>
                </div>

                <div class="modal-body modal-body-compact">
                    <div class="table-responsive" style="border-radius: 6px; border: 1px solid #e2e8f0; overflow: hidden;">
                        <table class="table table-bordered table-striped" id="bomDetailsTable_<?php echo $current_pid; ?>">
                           <thead>
    <tr>
        <th width="35" class="text-center">#</th>
        <th>Raw Material</th>
        <th class="text-center">Qty</th>
        <th class="text-right">Unit Price</th>
        <th class="text-center">GST %</th>
        <th class="text-center">Current Stock</th>
        <th class="text-center">Status</th>
        <th class="text-right">Total Costing</th>
    </tr>
</thead>
                            <tbody>
                            <?php
                            $grand_total = 0;
                            $count = 1;

                            if(!empty($items)){
                                foreach($items as $x){
                                    $qty = (float)$x['quantity'];
                                    $gst = (float)$x['gst_percent'];
                                    $base_price = (float)$x['buy_price'];

                                    if($x['buy_type'] == "exclusive"){
                                        $unit_price_inclusive = $base_price * (1 + ($gst / 100));
                                    } else {
                                        $unit_price_inclusive = $base_price;
                                    }

                                    $line_total = $unit_price_inclusive * $qty;
                                    $grand_total += $line_total;

                                    $stock_data = find_by_sql("
                                        SELECT
                                            SUM(CASE WHEN transaction_type IN (1, 4) THEN quantity ELSE 0 END) AS total_in,
                                            SUM(CASE WHEN transaction_type IN (2, 3, 5, 6) THEN quantity ELSE 0 END) AS total_out,
                                            COALESCE((
                                                SELECT SUM(d.qty)
                                                FROM demo_item_detail d
                                                WHERE d.product_id = '{$x['raw_product_id']}'
                                                AND d.status = 1
                                            ), 0) AS demo_hold_qty
                                        FROM transaction_master
                                        WHERE product_id = '{$x['raw_product_id']}'
                                    ");

                                    $current_stock = 0;
                                    if(!empty($stock_data)){
                                        $physical_stock = (float)$stock_data[0]['total_in'] - (float)$stock_data[0]['total_out'];
                                        $demo_hold_qty  = (float)$stock_data[0]['demo_hold_qty'];
                                        $current_stock  = $physical_stock - $demo_hold_qty;
                                    }

                                    $product_info = find_by_id('products', $x['raw_product_id']);
                                    $reorder_level = isset($product_info['reorder_level']) ? (float)$product_info['reorder_level'] : 0;

                                    $badge_class = "badge-instock";
                                    $status = "In Stock";

                                    if($current_stock <= $reorder_level){
                                        $status = "Low Stock";
                                        $badge_class = "badge-lowstock";
                                    }

                                    if($current_stock <= ($reorder_level / 2)){
                                        $status = "Critical Low";
                                        $badge_class = "badge-critical";
                                    }
                            ?>
                                <tr>
                                    <td class="text-center"><?php echo $count++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($x['raw_name']); ?></strong></td>
                                    <td class="text-center"><?php echo $qty; ?></td>
                                    <td class="text-right">RS. <?php echo number_format($unit_price_inclusive, 2); ?></td>
                                    <td class="text-center"><?php echo number_format($gst, 2); ?>%</td>
                                    <td class="text-center font-weight-bold"><?php echo (int)round($current_stock); ?></td>
                                    <td class="text-center">
                                        <span class="<?php echo $badge_class; ?>"><?php echo $status; ?></span>
                                    </td>
                                    <td class="text-right"><strong>RS. <?php echo number_format($line_total, 2); ?></strong></td>
                                </tr>
                            <?php 
                                }
                            } 
                            ?>

                                <tr>
                                    <td colspan="7" class="text-right" style="background: #f8fafc; font-weight: bold; font-size: 11px;">
                                        Grand Total (Incl GST)
                                    </td>
                                    <td class="text-right" style="background: #e0f2fe; color: #0369a1; font-size: 12px;">
                                        <strong>RS. <?php echo number_format($grand_total, 2); ?></strong>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-footer modal-footer-compact">
                    <button type="button" class="btn btn-clear btn-custom" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. EDIT MODAL -->
    <div class="modal fade" id="editModal_<?php echo $current_pid; ?>" tabindex="-1" role="dialog" data-backdrop="static">
        <div class="modal-dialog modal-dialog-compact-form" role="document">
            <div class="modal-content" style="border-radius: 8px; overflow: hidden; border: none; box-shadow: 0 8px 24px rgba(0,0,0,0.18);">
                <div class="modal-header modal-header-compact">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">
                        <i class="glyphicon glyphicon-pencil" style="color: #38bdf8; margin-right: 5px;"></i> EDIT BOM: <?php echo htmlspecialchars($current_pname); ?>
                    </h4>
                </div>

                <form method="post" action="bom_master.php">
                    <div class="modal-body modal-body-compact">
                        
                        <input type="hidden" name="product_id" value="<?php echo $current_pid; ?>">

                        <div class="form-group" style="margin-bottom: 12px;">
                            <label>Finished Product</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($current_pname); ?>" readonly style="background:#f1f5f9; font-weight:600;">
                        </div>

                     <div style="margin-top: 14px; margin-bottom: 8px; display:flex; justify-content:space-between; align-items:center;">
    <label style="color: #0f172a; font-size: 11px; margin:0; font-weight:700; text-transform:uppercase;">Raw Materials Required</label>
    <button type="button" class="btn btn-xs btn-primary-custom addRowBtn" style="font-size: 11px; padding: 3px 8px; border-radius: 4px;">
        <i class="glyphicon glyphicon-plus"></i> Add Item
    </button>
</div>

<div class="bom_rows_container">
<?php 
    if(!empty($items)){
        foreach($items as $idx => $er){
?>
    <div class="row bom_row" style="margin-bottom: 6px;">
        <div class="col-xs-7" style="padding-right: 3px;">
            <input type="hidden" name="raw_product_id[]" class="raw_id_hidden" value="<?php echo $er['raw_product_id']; ?>">
            <input type="text" list="raw_products_list" class="form-control raw-search" placeholder="Search Raw Material..." value="<?php echo htmlspecialchars($er['raw_name']); ?>" required autocomplete="off">
        </div>
        <div class="col-xs-4" style="padding-left: 3px; padding-right: 3px;">
            <input type="number" step="0.01" name="qty[]" value="<?php echo $er['quantity']; ?>" class="form-control" placeholder="Qty" required>
        </div>
        <div class="col-xs-1" style="padding-left: 0; padding-right: 15px; text-align: center;">
            <button type="button" class="btn btn-danger btn-xs removeRow" style="background:#ef4444; border-radius: 4px; height: 34px; width: 100%;" title="Remove">-</button>
        </div>
    </div>
<?php 
        }
    } else { 
?>
    <div class="row bom_row" style="margin-bottom: 6px;">
        <div class="col-xs-7" style="padding-right: 3px;">
            <input type="hidden" name="raw_product_id[]" class="raw_id_hidden" value="">
            <input type="text" list="raw_products_list" class="form-control raw-search" placeholder="Search Raw Material..." required autocomplete="off">
        </div>
        <div class="col-xs-4" style="padding-left: 3px; padding-right: 3px;">
            <input type="number" step="0.01" name="qty[]" class="form-control" placeholder="Qty" required>
        </div>
        <div class="col-xs-1" style="padding-left: 0; padding-right: 15px; text-align: center;">
            <button type="button" class="btn btn-danger btn-xs removeRow" style="background:#ef4444; border-radius: 4px; height: 34px; width: 100%;" title="Remove">-</button>
        </div>
    </div>
<?php } ?>
</div>
                    </div>
                    <div class="modal-footer modal-footer-compact">
                        <button type="button" class="btn btn-clear btn-custom" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="save_bom" class="btn btn-primary-custom btn-custom">Update BOM</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php endforeach; ?>
<?php endif; ?>

<!-- ================= EXPORT LIBRARIES ================= -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
const ORG_DETAILS = {
    name: "<?php echo addslashes($org_info['name']); ?>",
    address: "<?php echo addslashes($org_info['address']); ?>",
    gst: "<?php echo addslashes($org_info['gst']); ?>",
    phone: "<?php echo addslashes($org_info['phone']); ?>"
};

// Map Selected Datalist text to real Product IDs
document.addEventListener("input", function(e) {
    if (e.target.classList.contains("finished-search")) {
        let val = e.target.value;
        let opts = document.getElementById("finished_products_list").options;
        let foundId = 0;
        for (let i = 0; i < opts.length; i++) {
            if (opts[i].value === val) {
                foundId = opts[i].getAttribute("data-id");
                break;
            }
        }
        let form = e.target.closest("form");
        if(form) {
            let hidden = form.querySelector(".selected_product_id");
            if(hidden) hidden.value = foundId;
        }
    }

    if (e.target.classList.contains("raw-search")) {
        let val = e.target.value;
        let opts = document.getElementById("raw_products_list").options;
        let hiddenInput = e.target.closest(".bom_row").querySelector(".raw_id_hidden");
        let foundId = 0;
        for (let i = 0; i < opts.length; i++) {
            if (opts[i].value === val) {
                foundId = opts[i].getAttribute("data-id");
                break;
            }
        }
        if(hiddenInput) hiddenInput.value = foundId;
    }
});

// Search Filter in Main Table
document.getElementById("bomSearch").addEventListener("keyup", function() {
    let value = this.value.toLowerCase();
    document.querySelectorAll("#bomTable tbody tr").forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(value) ? "" : "none";
    });
});

// Dynamic Add/Remove Rows inside any Modal
// Dynamic Add/Remove Rows via Top Button
document.addEventListener("click", function(e){
    // Top "+ Add Item" Button Click
    if(e.target.closest(".addRowBtn")){
        let modalBody = e.target.closest(".modal-body");
        let container = modalBody.querySelector(".bom_rows_container");
        
        let newRow = document.createElement("div");
        newRow.className = "row bom_row";
        newRow.style.marginBottom = "6px";
        newRow.innerHTML = `
            <div class="col-xs-7" style="padding-right: 3px;">
                <input type="hidden" name="raw_product_id[]" class="raw_id_hidden" value="">
                <input type="text" list="raw_products_list" class="form-control raw-search" placeholder="Search Raw Material..." required autocomplete="off">
            </div>
            <div class="col-xs-4" style="padding-left: 3px; padding-right: 3px;">
                <input type="number" step="0.01" name="qty[]" class="form-control" placeholder="Qty" required>
            </div>
            <div class="col-xs-1" style="padding-left: 0; padding-right: 15px; text-align: center;">
                <button type="button" class="btn btn-danger btn-xs removeRow" style="background:#ef4444; border-radius: 4px; height: 34px; width: 100%;" title="Remove">-</button>
            </div>
        `;
        container.appendChild(newRow);
    }

    // Row Delete Button Click
    if(e.target.classList.contains("removeRow")){
        let container = e.target.closest(".bom_rows_container");
        let rows = container.querySelectorAll(".bom_row");
        
        // Minimum 1 row form mein bani rahegi
        if(rows.length > 1){
            e.target.closest(".bom_row").remove();
        } else {
            let lastRow = rows[0];
            lastRow.querySelector(".raw_id_hidden").value = "";
            lastRow.querySelector(".raw-search").value = "";
            lastRow.querySelector("input[name='qty[]']").value = "";
        }
    }
});

// Confirm Delete
function confirmDelete(id) {
    if(typeof Swal !== 'undefined'){
        Swal.fire({
            title: 'Kya aap sure hain?',
            text: "Is pure BOM configuration ko delete kar diya jayega!",
            icon: 'warning',
            showCancelButton: true,
            confirmColor: '#ef4444',
            cancelColor: '#6b7280',
            confirmButtonText: 'Haan, Delete Karo!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "bom_master.php?delete=" + id;
            }
        });
    } else {
        if(confirm("Kya aap is BOM configuration ko delete karna chahte hain?")){
            window.location.href = "bom_master.php?delete=" + id;
        }
    }
}

/* EXPORT FUNCTIONS */
function exportTableToExcel(tableID, filename = ''){
    let tableSelect = document.getElementById(tableID);
    if(!tableSelect) return;

    let cloneTable = tableSelect.cloneNode(true);
    let rows = cloneTable.rows;

    if(tableID === 'bomTable'){
        for (let i = 0; i < rows.length; i++) {
            rows[i].deleteCell(-1); 
        }
    }

    let wb = XLSX.utils.table_to_book(cloneTable, {sheet: "BOM Data"});
    XLSX.writeFile(wb, (filename ? filename : 'Export') + '.xlsx');
}

function exportTableToPDF(tableID, filename = '') {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    doc.setFont("helvetica", "bold");
    doc.setFontSize(14);
    doc.setTextColor(15, 23, 42);
    doc.text(ORG_DETAILS.name || "COMPANY NAME", 14, 15);

    doc.setFont("helvetica", "normal");
    doc.setFontSize(8);
    doc.setTextColor(100);
    
    let currentY = 20;
    if(ORG_DETAILS.address) {
        doc.text(ORG_DETAILS.address, 14, currentY);
        currentY += 4;
    }
    
    let subDetails = [];
    if(ORG_DETAILS.gst) subDetails.push("GSTIN: " + ORG_DETAILS.gst);
    if(ORG_DETAILS.phone) subDetails.push("Phone: " + ORG_DETAILS.phone);
    
    if(subDetails.length > 0) {
        doc.text(subDetails.join(" | "), 14, currentY);
        currentY += 4;
    }

    doc.setDrawColor(200);
    doc.line(14, currentY, 196, currentY);
    currentY += 6;

    doc.setFont("helvetica", "bold");
    doc.setFontSize(11);
    doc.setTextColor(0);
    doc.text(tableID === 'bomTable' ? "BOM MASTER LIST" : "BOM DETAILED COSTING", 14, currentY);
    currentY += 4;

    let config = { 
        startY: currentY,
        theme: 'grid',
        headStyles: { fillColor: [15, 23, 42], textColor: [255, 255, 255], fontStyle: 'bold' },
        styles: { fontSize: 8, cellPadding: 3 }
    };

    if(tableID === 'bomTable'){
        config.html = '#' + tableID;
        config.columns = [
            { header: '#', dataKey: 0 },
            { header: 'Product Name', dataKey: 1 }
        ];
    } else {
        config.html = '#' + tableID;
    }

    doc.autoTable(config);
    doc.save((filename ? filename : 'Export') + '.pdf');
}

function exportTableToImage(tableID, filename = '') {
    let container = document.getElementById(tableID);
    if(!container) return;

    html2canvas(container).then(function(canvas) {
        let link = document.createElement('a');
        link.download = (filename ? filename : 'Export') + '.jpg';
        link.href = canvas.toDataURL('image/jpeg', 1.0);
        link.click();
    });
}
</script>

<?php include_once('layouts/footer.php'); ?>
