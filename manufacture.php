<?php
require_once('includes/load.php');
//page_require_level(2);
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

$products = find_by_sql("SELECT id, name FROM products WHERE is_bom = 1");

/* ===============================
   SAVE MANUFACTURE
================================ */
if(isset($_POST['save_manufacture'])){
    global $db;
    $product_id = (int)$_POST['product_id'];
    $qty        = (float)$_POST['qty'];

    if($product_id <= 0 || $qty <= 0){
        $_SESSION['mfg_error'] = "Please select device and enter valid quantity";
        redirect('manufacture.php', false);
        exit;
    }
    
    $ref_no = "MFG".time();
    $db->query("START TRANSACTION");

    /* ===== Fetch BOM ===== */
    $bom_items = find_by_sql("
        SELECT raw_product_id, quantity
        FROM bom
        WHERE product_id = {$product_id}
    ");

    if(!$bom_items){
        $_SESSION['mfg_error'] = "No BOM found for this product";
        $db->query("ROLLBACK");
        redirect('manufacture.php', false);
        exit;
    }

    foreach($bom_items as $b){
        $raw_id  = (int)$b['raw_product_id'];
        $per_unit = (float)$b['quantity'];
        $total_required = $per_unit * $qty;
        $product_data = find_by_id('products', $raw_id);
        $gst_id = (int)$product_data['gst_id'];

        /* ===== Transaction Based Stock Check ===== */
        $stock_data = find_by_sql("
            SELECT
            IFNULL(SUM(CASE WHEN transaction_type IN (1,4) THEN quantity ELSE 0 END),0)
            -
            IFNULL(SUM(CASE WHEN transaction_type IN (2, 3, 5, 6) THEN quantity ELSE 0 END),0)
            AS current_stock
            FROM transaction_master
            WHERE product_id = {$raw_id}
        ");

        $current_stock = (float)$stock_data[0]['current_stock'];

        if($current_stock < $total_required){
            $db->query("ROLLBACK");
            $raw_product = find_by_id('products', $raw_id);
            $_SESSION['mfg_error'] = "Insufficient stock for ".$raw_product['name'];
            redirect('manufacture.php', false);
            exit;
        }

        /* ===== Deduct Raw Material ===== */
        if(!$db->query("
            INSERT INTO transaction_master
            (product_id, bill_indent_no, entry_date, quantity, gst_id, transaction_type, comments, created_at)
            VALUES
            ({$raw_id}, '{$ref_no}', NOW(), {$total_required}, {$gst_id}, 6, 'Manufacture Raw Material', NOW())
        ")){
            $db->query("ROLLBACK");
            $_SESSION['mfg_error'] = "Raw material deduction failed";
            redirect('manufacture.php', false);
            exit;
        }
    }

    /* ===== Add Finished Goods ===== */
    $fg_data = find_by_id('products', $product_id);
    $fg_gst_id = (int)$fg_data['gst_id'];

    if(!$db->query("
        INSERT INTO transaction_master
        (product_id, bill_indent_no, entry_date, quantity, gst_id, transaction_type, comments, created_at)
        VALUES
        ({$product_id}, '{$ref_no}', NOW(), {$qty}, {$fg_gst_id}, 1, 'Manufactured Finished Goods', NOW())
    ")){
        $db->query("ROLLBACK");
        $_SESSION['mfg_error'] = "Finished goods insert failed";
        redirect('manufacture.php', false);
        exit;
    }

    $db->query("COMMIT");
    $_SESSION['mfg_success'] = "Manufacturing Successful";
    redirect('manufacture.php', false);
    exit;
}

/* ===============================
   EDIT HISTORY RECORD
================================ */
if(isset($_POST['edit_history'])){
    global $db;
    $edit_ref_no = $db->escape($_POST['edit_ref_no']);
    $edit_product_id = (int)$_POST['edit_product_id'];
    $new_qty = (float)$_POST['new_qty'];

    if($new_qty > 0){
        $db->query("START TRANSACTION");

        // 1. Update Finished Goods Quantity
        $db->query("UPDATE transaction_master SET quantity = {$new_qty} WHERE bill_indent_no = '{$edit_ref_no}' AND product_id = {$edit_product_id}");

        // 2. Fetch BOM and Update Raw Material Quantities accordingly
        $bom_items = find_by_sql("SELECT raw_product_id, quantity FROM bom WHERE product_id = {$edit_product_id}");
        
        if($bom_items){
            foreach($bom_items as $b){
                $raw_id   = (int)$b['raw_product_id'];
                $per_unit = (float)$b['quantity'];
                $total_rm = $per_unit * $new_qty;

                // Update the raw material entry linked to this same Reference Number
                $db->query("UPDATE transaction_master SET quantity = {$total_rm} WHERE bill_indent_no = '{$edit_ref_no}' AND product_id = {$raw_id}");
            }
        }
        
        $db->query("COMMIT");
        $_SESSION['mfg_success'] = "Record updated successfully. Stock adjusted automatically.";
    } else {
        $_SESSION['mfg_error'] = "Invalid quantity.";
    }
    
    redirect('manufacture.php', false);
    exit;
}

/* ===============================
   DELETE / UNDO HISTORY RECORD
================================ */
if(isset($_POST['delete_history'])){
    global $db;
    $delete_ref_no = $db->escape($_POST['delete_ref_no']);

    if($delete_ref_no){
        $db->query("START TRANSACTION");
        if($db->query("DELETE FROM transaction_master WHERE bill_indent_no = '{$delete_ref_no}'")){
            $db->query("COMMIT");
            $_SESSION['mfg_success'] = "Record {$delete_ref_no} deleted successfully. Stock reverted.";
        } else {
            $db->query("ROLLBACK");
            $_SESSION['mfg_error'] = "Failed to delete record.";
        }
    }
    
    redirect('manufacture.php', false);
    exit;
}

/* ===============================
   FETCH HISTORY (For Table Display)
================================ */
$history_data = find_by_sql("
    SELECT tm.entry_date, tm.bill_indent_no, p.id AS product_id, p.name AS product_name, tm.quantity, tm.transaction_type, tm.comments 
    FROM transaction_master tm
    JOIN products p ON tm.product_id = p.id
    WHERE p.is_bom = 1 AND tm.transaction_type IN (1, 5) 
    AND tm.bill_indent_no LIKE '%MFG%'
    ORDER BY tm.entry_date DESC 
    LIMIT 30
");
?>

<?php include_once('layouts/header.php'); ?>

<style>
#bom_container table thead{ background:#1f2d3d; }
#bom_container table thead th{ color:#fff !important; font-weight:600; border-color:#31445c; }
#bom_container table tbody td{ vertical-align:middle; font-size:13px; }
#bom_container .table{ margin-bottom:15px; }
#bom_container .alert-info{ margin-bottom:0; }
.btn-round{ border-radius:30px; padding:8px 24px; font-weight:600; }
.badge-mfg { background-color: #28a745; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; }
.badge-rev { background-color: #dc3545; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; }

/* Search Box Styling */
.history-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.history-search-box {
    max-width: 250px;
    display: inline-block;
    border-radius: 20px;
    padding-left: 15px;
}
</style>

<div class="row">

    <!-- LEFT SIDE -->
    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-heading">
                <strong>Manufacture Device</strong>
            </div>

            <div class="panel-body">
                <form method="post">
                    <div class="form-group">
                        <label>Select Device</label>
                        <select name="product_id" id="product_id" class="form-control" required>
                            <option value="">Select</option>
                            <?php foreach($products as $p){ ?>
                                <option value="<?= $p['id']; ?>">
                                    <?= $p['name']; ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Quantity</label>
                        <input type="number" name="qty" class="form-control" min="1" value="1" step="1" required>
                    </div>

                    <br>

                    <button type="submit" name="save_manufacture" class="btn btn-success btn-round">
                        Manufacture
                    </button>
                </form>
            </div>
        </div>
    </div>


    <!-- RIGHT SIDE (BOM Details) -->
    <div class="col-md-8">
        <div class="panel panel-default" id="bom_panel" style="display:none;">
            <div class="panel-heading">
                <strong>BOM Details</strong>
            </div>
            <div class="panel-body">
                <div id="bom_container" style="display:none;">
                    <table class="table table-bordered table-hover">
                        <thead style="background:#1f2d3d; color:#fff;">
                            <tr>
                                <th style="color:#fff;">Raw Material</th>
                                <th style="color:#fff;">Qty / Unit</th>
                                <th style="color:#fff;">Current Stock</th>
                            </tr>
                        </thead>
                        <tbody id="bom_body"></tbody>
                    </table>
                    <div class="alert alert-info">
                        Maximum Manufacturable :
                        <strong id="max_qty">0</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================
     HISTORY TABLE 
=============================================== -->
<div class="row">
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading history-header">
                <strong><i class="fa fa-history"></i> Recent Manufacturing History</strong>
                <!-- SEARCH BOX ADDED HERE -->
                <input type="text" id="historySearch" class="form-control history-search-box" placeholder="🔍 Search History...">
            </div>
            <div class="panel-body">
                <table class="table table-bordered table-striped table-hover">
                    <thead style="background:#1f2d3d; color:#fff;">
                        <tr>
                            <th style="color:#fff;">Date & Time</th>
                            <th style="color:#fff;">Ref. Number</th>
                            <th style="color:#fff;">Device Name</th>
                            <th style="color:#fff;">Action Taken</th>
                            <th style="color:#fff;">Quantity</th>
                            <th style="color:#fff;" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody">
                        <?php if(empty($history_data)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No manufacturing records found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($history_data as $row): ?>
                                <tr>
                                    <td><?= date("d-m-Y H:i", strtotime($row['entry_date'])); ?></td>
                                    <td><strong><?= htmlspecialchars($row['bill_indent_no']); ?></strong></td>
                                    <td><?= htmlspecialchars($row['product_name']); ?></td>
                                    <td>
                                        <?php if($row['transaction_type'] == 1): ?>
                                            <span class="badge-mfg">Manufactured (Added)</span>
                                        <?php elseif($row['transaction_type'] == 5): ?>
                                            <span class="badge-rev">Reversed (Deducted)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-weight:bold; font-size:14px;">
                                        <?= (int)$row['quantity']; ?> Pcs
                                    </td>
                                    <td class="text-center">
                                        <!-- EDIT BUTTON (Triggers Modal) -->
                                        <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#editModal<?= $row['bill_indent_no']; ?>" title="Edit Quantity">
                                            <i class="glyphicon glyphicon-pencil"></i>
                                        </button>

                                        <!-- DELETE BUTTON -->
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="delete_ref_no" value="<?= htmlspecialchars($row['bill_indent_no']); ?>">
                                            <button type="submit" name="delete_history" class="btn btn-danger btn-sm" onclick="return confirm('WARNING: Are you sure you want to completely DELETE this? All stock will be reverted.');" title="Delete & Revert">
                                                <i class="glyphicon glyphicon-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- EDIT MODAL FOR THIS ROW -->
                                <div class="modal fade" id="editModal<?= $row['bill_indent_no']; ?>" tabindex="-1" role="dialog">
                                  <div class="modal-dialog" role="document">
                                    <form method="post">
                                    <div class="modal-content">
                                      <div class="modal-header">
                                        <h4 class="modal-title">Edit Quantity - <?= htmlspecialchars($row['bill_indent_no']); ?></h4>
                                      </div>
                                      <div class="modal-body text-left">
                                        <p><strong>Device:</strong> <?= htmlspecialchars($row['product_name']); ?></p>
                                        
                                        <input type="hidden" name="edit_ref_no" value="<?= htmlspecialchars($row['bill_indent_no']); ?>">
                                        <input type="hidden" name="edit_product_id" value="<?= $row['product_id']; ?>">
                                        
                                        <div class="form-group">
                                            <label>Modify Quantity (Kam ya Zyada Karein)</label>
                                            <input type="number" name="new_qty" class="form-control" value="<?= (int)$row['quantity']; ?>" min="1" step="1" required>
                                        </div>
                                        <p class="text-muted"><small>Note: Yahan quantity change karne se Finished Goods aur Raw Materials dono ka stock automatically database mein update ho jayega.</small></p>
                                      </div>
                                      <div class="modal-footer">
                                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                                        <button type="submit" name="edit_history" class="btn btn-primary">Update Stock</button>
                                      </div>
                                    </div>
                                    </form>
                                  </div>
                                </div>
                                <!-- END MODAL -->

                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include_once('layouts/footer.php'); ?>

<script>
$(document).ready(function () {

    // SEARCH FILTER SCRIPT
    $("#historySearch").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("#historyTableBody tr").filter(function() {
            // Hum modal wale div ko filter nahi karenge, sirf table rows ko karenge
            if(!$(this).hasClass('modal')){
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            }
        });
    });

    $('#product_id').on('change', function () {
        var product_id = $(this).val();

        if(product_id==''){
            $('#bom_container').hide();
            $('#bom_panel').hide();
            $('#bom_body').html('');
            $('#max_qty').text('0');
            return;
        }

        $.ajax({
            url:'get_bom.php',
            type:'GET',
            data:{product_id:product_id},
            dataType:'json',
            success:function(response){
                var html='';
                $.each(response.items, function(i, item){
                    var stock = Number(item.stock);
                    var rowStyle = (stock <= 0) ? 'style="background-color: #f8d7da; color: #721c24;"' : '';
                
                    html += '<tr ' + rowStyle + '>';
                    html += '<td>'+item.name+'</td>';
                    html += '<td>'+item.quantity+'</td>';
                    html += '<td>'+stock.toFixed(2)+'</td>';
                    html += '</tr>';
                });

                $('#bom_body').html(html);
                $('#max_qty').text(response.max);
                $('#bom_container').show();
                $('#bom_panel').show();
            },
            error:function(xhr){
                console.log(xhr.responseText);
            }
        });
    });
});
</script>

<?php if(isset($_SESSION['mfg_error'])){ ?>
<script>
Swal.fire({
    icon: 'error',
    title: 'Action Failed',
    text: '<?= addslashes($_SESSION['mfg_error']); ?>',
    confirmButtonColor: '#d33'
});
</script>
<?php unset($_SESSION['mfg_error']); } ?>

<?php if(isset($_SESSION['mfg_success'])){ ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Success',
    text: '<?= addslashes($_SESSION['mfg_success']); ?>',
    timer: 2000,
    showConfirmButton: false
});
</script>
<?php unset($_SESSION['mfg_success']); } ?>
