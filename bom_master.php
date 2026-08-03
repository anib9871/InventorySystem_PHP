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

/* FETCH PRODUCTS WITH PRICE + GST + BUY TYPE */
/* BOM PRODUCTS */
$bom_products_dropdown = find_by_sql("
SELECT id,name
FROM products
WHERE is_bom='1'
ORDER BY name ASC
");

$edit_pid = 0;

if(isset($_GET['edit'])){
    $edit_pid = (int)$_GET['edit'];
}

/* RAW MATERIAL PRODUCTS */
$raw_products = find_by_sql("
SELECT id,name,buy_price,gst_id,buy_type
FROM products
WHERE (is_bom = 0 AND type = 1)
   OR id = '{$edit_pid}'
ORDER BY name ASC
");

/* ---------- SAVE BOM ---------- */
if(isset($_POST['save_bom'])){

    $product_id = (int)$_POST['product_id'];

    if($product_id == 0){
        $session->msg("d","Please select product!");
        redirect('bom_master.php',false);
    }

    /* delete old bom first */
    $db->query("DELETE FROM bom WHERE product_id='{$product_id}'");

    /* insert new rows */
    if(!empty($_POST['raw_product_id'])){
        foreach($_POST['raw_product_id'] as $i=>$raw){

            $raw_id = (int)$raw;
            $qty = (float)$_POST['qty'][$i];

            if($raw_id>0 && $qty>0){
                $db->query("
                    INSERT INTO bom(product_id,raw_product_id,quantity)
                    VALUES('{$product_id}','{$raw_id}','{$qty}')
                ");
            }
        }
    }

    $session->msg("s","BOM saved successfully!");
    redirect('bom_master.php',false);
}

/* ---------- DELETE BOM ---------- */
if(isset($_GET['delete'])){
    $pid = (int)$_GET['delete'];
    if($db->query("DELETE FROM bom WHERE product_id='{$pid}'")){
        $session->msg("s","BOM deleted successfully!");
    } else {
        $session->msg("d","Failed to delete BOM!");
    }
    redirect('bom_master.php',false);
}

/* ---------- LOAD FOR EDIT ---------- */
$edit_rows = [];

if(isset($_GET['edit'])){
    $edit_pid = (int)$_GET['edit'];

    $edit_rows = $db->query("
        SELECT raw_product_id,quantity
        FROM bom
        WHERE product_id='{$edit_pid}'
    ");
}

/* ---------- VIEW BOM ---------- */
$view_items = [];
$selected_product_name = "";
if(isset($_GET['pid'])){
    $pid = (int)$_GET['pid'];

    $p_info = find_by_id('products', $pid);
    if($p_info) { $selected_product_name = $p_info['name']; }

    $view_items = $db->query("
        SELECT 
            b.raw_product_id,
            p.name AS raw_name,
            p.buy_price,
            p.buy_type,
            g.gst_percent,
            b.quantity
        FROM bom b
        JOIN products p ON p.id=b.raw_product_id
        LEFT JOIN gst_master g ON g.id=p.gst_id
        WHERE b.product_id='{$pid}'
    ");
}

/* ---------- BOM PRODUCT LIST ---------- */
$bom_products = $db->query("
    SELECT DISTINCT b.product_id,p.name 
    FROM bom b
    JOIN products p ON p.id=b.product_id
    ORDER BY p.name ASC
");

include_once('layouts/header.php');
?>

<style>
body { background-color: #f4f7fb; }
.panel { border: none; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); margin-bottom: 15px; background: #ffffff; }
.panel-heading { padding: 10px 15px; font-size: 13px; font-weight: 700; border-bottom: 2px solid #2b8cff !important; background: #ffffff !important; color: #1d3557; border-top-left-radius: 8px; border-top-right-radius: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
.panel-body { padding: 15px; }
label { font-size: 11px; margin-bottom: 4px; font-weight: 600; color: #4a5568; text-transform: uppercase; letter-spacing: 0.3px; }
.form-group-compact { margin-bottom: 10px; }
.form-control { height: 32px; font-size: 12px; padding: 4px 10px; border-radius: 5px; border: 1px solid #cbd5e1; box-shadow: none; transition: all 0.2s ease-in-out; }
.form-control:focus { border-color: #2b8cff; box-shadow: 0 0 0 2px rgba(43, 140, 255, 0.15); }
.btn-group-flex { display: flex !important; align-items: center !important; justify-content: flex-end !important; gap: 8px !important; margin-top: 15px; }
.btn-custom { height: 32px; font-size: 11px; font-weight: 600; padding: 0 14px; border-radius: 5px; text-transform: uppercase; letter-spacing: 0.5px; transition: all 0.2s; display: inline-flex !important; align-items: center !important; justify-content: center !important; text-decoration: none !important; white-space: nowrap !important; line-height: 1 !important; }
.btn-success-custom { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff !important; border: none; }
.btn-primary-custom { background: linear-gradient(135deg, #2b8cff 0%, #1d72db 100%); color: #fff !important; border: none; }
.btn-clear { background: #f1f5f9; color: #64748b !important; border: 1px solid #cbd5e1; }
.table-scrollable { max-height: 320px; overflow-y: auto; overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 6px; }
.table { margin-bottom: 0; font-size: 12px; width: 100%; }
.table thead { position: sticky; top: 0; background: #18233b; color: #ffffff; z-index: 10; }
.table thead th { border: none !important; padding: 8px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; background: #18233b; }
.table tbody td { padding: 8px 12px; vertical-align: middle !important; border-color: #f1f5f9; }
.search-box { position: relative; width: 100%; margin-bottom: 12px; }
.search-box i { position: absolute; left: 10px; top: 9px; color: #94a3b8; font-size: 12px; }
.search-box input { padding-left: 30px; height: 32px !important; }
.action-td { width: 100px; text-align: center; white-space: nowrap !important; }
.action-cell { display: flex !important; flex-direction: row !important; align-items: center !important; justify-content: center !important; gap: 5px !important; }
.equal-btn { width: 28px !important; height: 26px !important; padding: 0 !important; line-height: 26px !important; font-size: 11px !important; border-radius: 4px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; margin: 0 !important; }
.badge-instock { background: #d1fae5; color: #065f46; padding: 3px 8px; border-radius: 12px; font-size: 10px; font-weight: 700; }
.badge-lowstock { background: #ffedd5; color: #9a3412; padding: 3px 8px; border-radius: 12px; font-size: 10px; font-weight: 700; }
.badge-critical { background: #fee2e2; color: #991b1b; padding: 3px 8px; border-radius: 12px; font-size: 10px; font-weight: 700; }
</style>

<div class="row">

<!-- ================= ADD / EDIT FORM ================= -->
<div class="col-md-6">
    <div class="panel panel-default">
        <div class="panel-heading">
            <i class="glyphicon glyphicon-list-alt" style="color: #2b8cff; margin-right: 5px;"></i>
            <strong><?php echo $edit_pid ? 'EDIT BOM' : 'CREATE / UPDATE BOM'; ?></strong>
        </div>

        <div class="panel-body">
            <form method="post" id="bomForm">
                <div class="form-group form-group-compact">
                    <label>Select Finished Product</label>
                    <select name="product_id" class="form-control" required>
                        <option value="">-- Select Product --</option>
                        <?php foreach($bom_products_dropdown as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php if($edit_pid==$p['id']) echo "selected"; ?>>
                                <?php echo $p['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-top: 15px; margin-bottom: 8px;">
                    <label style="color: #1d3557; font-size: 12px;">Raw Materials Required</label>
                </div>

                <div id="bom_rows">
                <?php 
                if($edit_pid && $edit_rows->num_rows>0){
                    while($er=$edit_rows->fetch_assoc()){
                ?>
                <div class="row bom_row" style="margin-bottom:8px">
                    <div class="col-xs-6" style="padding-right: 4px;">
                        <select name="raw_product_id[]" class="form-control" required>
                            <option value="">Select Raw Material</option>
                            <?php foreach($raw_products as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php if($p['id']==$er['raw_product_id']) echo "selected"; ?>>
                                    <?php echo $p['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-xs-4" style="padding-left: 4px; padding-right: 4px;">
                        <input type="number" step="0.01" name="qty[]" value="<?php echo $er['quantity']; ?>" class="form-control" placeholder="Qty" required>
                    </div>
                    <div class="col-xs-2" style="padding-left: 4px;">
                        <button type="button" class="btn btn-success-custom addRow" style="height: 32px; width: 100%; font-weight: bold; border-radius: 5px;">+</button>
                    </div>
                </div>
                <?php } } else { ?>
                <div class="row bom_row" style="margin-bottom:8px">
                    <div class="col-xs-6" style="padding-right: 4px;">
                        <select name="raw_product_id[]" class="form-control" required>
                            <option value="">Select Raw Material</option>
                            <?php foreach($raw_products as $p): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo $p['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-xs-4" style="padding-left: 4px; padding-right: 4px;">
                        <input type="number" step="0.01" name="qty[]" class="form-control" placeholder="Qty" required>
                    </div>
                    <div class="col-xs-2" style="padding-left: 4px;">
                        <button type="button" class="btn btn-success-custom addRow" style="height: 32px; width: 100%; font-weight: bold; border-radius: 5px;">+</button>
                    </div>
                </div>
                <?php } ?>
                </div>

                <div class="btn-group-flex">
                    <?php if($edit_pid): ?>
                        <a href="bom_master.php" class="btn btn-clear btn-custom">Cancel</a>
                        <button name="save_bom" class="btn btn-primary-custom btn-custom">Update BOM</button>
                    <?php else: ?>
                        <button type="button" class="btn btn-clear btn-custom" onclick="document.getElementById('bomForm').reset();">Clear</button>
                        <button name="save_bom" class="btn btn-success-custom btn-custom">Save BOM</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= BOM LIST ================= -->
<div class="col-md-6">
    <div class="panel panel-default">
        <div class="panel-heading" style="display: flex; justify-content: space-between; align-items: center;">
            <strong>BOM LIST</strong>

            <div class="btn-group">
                <button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="font-size: 11px; padding: 2px 8px; font-weight: 600;">
                    <i class="glyphicon glyphicon-export"></i> Export <span class="caret"></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-right" style="font-size: 12px; min-width: 120px;">
                    <li><a href="javascript:void(0)" onclick="exportTableToExcel('bomTable', 'BOM_List')"><i class="glyphicon glyphicon-file" style="color: #10b981; margin-right: 5px;"></i> Excel (.xlsx)</a></li>
                    <li><a href="javascript:void(0)" onclick="exportTableToPDF('bomTable', 'BOM_List')"><i class="glyphicon glyphicon-picture" style="color: #ef4444; margin-right: 5px;"></i> PDF (.pdf)</a></li>
                    <li><a href="javascript:void(0)" onclick="exportTableToImage('bomTable', 'BOM_List')"><i class="glyphicon glyphicon-camera" style="color: #2b8cff; margin-right: 5px;"></i> JPEG (.jpg)</a></li>
                </ul>
            </div>
        </div>

        <div class="panel-body">
            <div class="search-box">
                <i class="glyphicon glyphicon-search"></i>
                <input type="text" id="bomSearch" class="form-control" placeholder="Search BOM product...">
            </div>

            <div class="table-scrollable">
                <table class="table table-bordered table-striped" id="bomTable">
                    <thead>
                        <tr>
                            <th width="40" class="text-center">#</th>
                            <th>Product Name</th>
                            <th class="text-center" width="100">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($bom_products && $bom_products->num_rows > 0): ?>
                            <?php $i=1; while($r=$bom_products->fetch_assoc()): ?>
                            <tr>
                                <td class="text-center"><?php echo $i++; ?></td>
                                <td><strong><?php echo $r['name']; ?></strong></td>
                                <td class="action-td">
                                    <div class="action-cell">
                                        <a href="bom_master.php?pid=<?php echo $r['product_id']; ?>" class="btn btn-success btn-xs equal-btn" title="View Details">
                                            <i class="glyphicon glyphicon-eye-open"></i>
                                        </a>
                                        <a href="bom_master.php?edit=<?php echo $r['product_id']; ?>" class="btn btn-primary btn-xs equal-btn" title="Edit">
                                            <i class="glyphicon glyphicon-pencil"></i>
                                        </a>
                                        <button type="button" onclick="confirmDelete(<?php echo $r['product_id']; ?>)" class="btn btn-danger btn-xs equal-btn" title="Delete">
                                            <i class="glyphicon glyphicon-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center" style="color: #94a3b8; padding: 15px;">No BOM records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</div>

<!-- ================= VIEW BOM DETAILS ================= -->
<?php if(isset($_GET['pid'])): ?>
<div class="row">
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading" style="display: flex; align-items: center; justify-content: space-between;">
                <span><i class="glyphicon glyphicon-info-sign" style="color: #2b8cff; margin-right: 5px;"></i> <strong>BOM DETAILS: <?php echo strtoupper($selected_product_name); ?></strong></span>
                
                <div style="display: flex; gap: 8px; align-items: center;">
                    <div class="btn-group">
                        <button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="font-size: 10px; padding: 2px 8px; font-weight: 600;">
                            <i class="glyphicon glyphicon-export"></i> Export Details <span class="caret"></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-right" style="font-size: 12px; min-width: 120px;">
                            <li><a href="javascript:void(0)" onclick="exportTableToExcel('bomDetailsTable', 'BOM_Details_<?php echo $selected_product_name;?>')"><i class="glyphicon glyphicon-file" style="color: #10b981; margin-right: 5px;"></i> Excel (.xlsx)</a></li>
                            <li><a href="javascript:void(0)" onclick="exportTableToPDF('bomDetailsTable', 'BOM_Details_<?php echo $selected_product_name;?>')"><i class="glyphicon glyphicon-picture" style="color: #ef4444; margin-right: 5px;"></i> PDF (.pdf)</a></li>
                            <li><a href="javascript:void(0)" onclick="exportTableToImage('bomDetailsTable', 'BOM_Details_<?php echo $selected_product_name;?>')"><i class="glyphicon glyphicon-camera" style="color: #2b8cff; margin-right: 5px;"></i> JPEG (.jpg)</a></li>
                        </ul>
                    </div>

                    <a href="bom_master.php" class="btn btn-clear btn-custom" style="height: 24px; padding: 0 10px; font-size: 10px;">Close</a>
                </div>
            </div>

            <div class="panel-body">
                <div class="table-scrollable">
                    <table class="table table-bordered table-striped" id="bomDetailsTable">
                        <thead>
                            <tr>
                                <th width="40" class="text-center">#</th>
                                <th>Raw Material</th>
                                <th class="text-center">Qty</th>
                                <th class="text-right">Unit Price (Incl GST)</th>
                                <th class="text-center">GST %</th>
                                <th class="text-center">Current Stock</th>
                                <th class="text-center">Status</th>
                                <th class="text-right">Total Costing</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $grand_total = 0;
                        $i=1;

                        while($x=$view_items->fetch_assoc()):
                        $qty = (float)$x['quantity'];
                        $gst = (float)$x['gst_percent'];
                        $base_price = (float)$x['buy_price'];

                        if($x['buy_type'] == "exclusive"){
                            $unit_price_inclusive = $base_price * (1 + ($gst/100));
                        } else {
                            $unit_price_inclusive = $base_price;
                        }

                        $line_total = $unit_price_inclusive * $qty;
                        $grand_total += $line_total;

                        $stock_data = find_by_sql("
                        SELECT
                        SUM(CASE WHEN transaction_type=1 THEN quantity ELSE 0 END) as total_in,
                        SUM(CASE WHEN transaction_type=2 THEN quantity ELSE 0 END) as total_out
                        FROM transaction_master
                        WHERE product_id='{$x['raw_product_id']}'
                        ");

                        $current_stock = 0;
                        if($stock_data){
                            $current_stock = (float)$stock_data[0]['total_in'] - (float)$stock_data[0]['total_out'];
                        }

                        $product_info = find_by_id('products',$x['raw_product_id']);
                        $reorder_level = isset($product_info['reorder_level']) ? $product_info['reorder_level'] : 0;

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
                            <td class="text-center"><?php echo $i++; ?></td>
                            <td><strong><?php echo $x['raw_name']; ?></strong></td>
                            <td class="text-center"><?php echo $qty; ?></td>
                            <td class="text-right">RS. <?php echo number_format($unit_price_inclusive,2); ?></td>
                            <td class="text-center"><?php echo number_format($gst, 2); ?>%</td>
                            <td class="text-center"><?php echo number_format($current_stock, 2); ?></td>
                            <td class="text-center">
                                <span class="<?php echo $badge_class; ?>"><?php echo $status; ?></span>
                            </td>
                            <td class="text-right"><strong>RS. <?php echo number_format($line_total,2); ?></strong></td>
                        </tr>

                        <?php endwhile; ?>

                        <tr>
                            <td colspan="7" class="text-right" style="background: #f8fafc;">
                                <strong>Grand Total (Incl GST)</strong>
                            </td>
                            <td class="text-right" style="background: #e0f2fe; color: #0369a1;">
                                <strong>RS. <?php echo number_format($grand_total,2); ?></strong>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ================= EXPORT LIBRARIES ================= -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
/* Pass PHP fetched Org details into JS Object */
const ORG_DETAILS = {
    name: "<?php echo addslashes($org_info['name']); ?>",
    address: "<?php echo addslashes($org_info['address']); ?>",
    gst: "<?php echo addslashes($org_info['gst']); ?>",
    phone: "<?php echo addslashes($org_info['phone']); ?>"
};

document.getElementById("bomSearch").addEventListener("keyup", function() {
    let value = this.value.toLowerCase();
    document.querySelectorAll("#bomTable tbody tr").forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(value) ? "" : "none";
    });
});

document.addEventListener("click", function(e){
    if(e.target.classList.contains("addRow")){
        let r = document.querySelector(".bom_row").cloneNode(true);
        r.querySelectorAll("input,select").forEach(x => x.value = "");
        let btn = r.querySelector(".addRow");
        btn.classList.remove("addRow", "btn-success-custom");
        btn.classList.add("removeRow", "btn-danger");
        btn.innerText = "-";
        btn.style.background = "#ef4444";
        btn.style.border = "none";
        document.getElementById("bom_rows").appendChild(r);
    }
    if(e.target.classList.contains("removeRow")){
        e.target.closest(".bom_row").remove();
    }
});

function confirmDelete(id) {
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
}

/* EXPORT FUNCTIONS WITH ORG HEADER */

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

    // 1. HEADER - Organization Info
    doc.setFont("helvetica", "bold");
    doc.setFontSize(16);
    doc.setTextColor(29, 53, 87);
    doc.text(ORG_DETAILS.name || "COMPANY NAME", 14, 15);

    doc.setFont("helvetica", "normal");
    doc.setFontSize(9);
    doc.setTextColor(100);
    
    let currentY = 20;
    if(ORG_DETAILS.address) {
        doc.text(ORG_DETAILS.address, 14, currentY);
        currentY += 5;
    }
    
    let subDetails = [];
    if(ORG_DETAILS.gst) subDetails.push("GSTIN: " + ORG_DETAILS.gst);
    if(ORG_DETAILS.phone) subDetails.push("Phone: " + ORG_DETAILS.phone);
    
    if(subDetails.length > 0) {
        doc.text(subDetails.join(" | "), 14, currentY);
        currentY += 5;
    }

    // Line Divider
    doc.setDrawColor(200);
    doc.line(14, currentY, 196, currentY);
    currentY += 7;

    // 2. DOCUMENT TITLE
    doc.setFont("helvetica", "bold");
    doc.setFontSize(12);
    doc.setTextColor(0);
    doc.text(tableID === 'bomTable' ? "BOM MASTER LIST" : "BOM DETAILED COSTING", 14, currentY);
    currentY += 5;

    // 3. TABLE AUTO GENERATION
    let config = { 
        startY: currentY,
        theme: 'grid',
        headStyles: { fillColor: [24, 35, 59], textColor: [255, 255, 255], fontStyle: 'bold' },
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
