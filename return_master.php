<?php
$page_title = 'Return Master';
require_once('includes/load.php');

// Fetch master dropdown data
$products  = find_all('products');
$suppliers = find_all('supplier_master');
$customers = find_all('customer_master');

// Edit Mode Handler
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_return = null;

if ($edit_id > 0) {
    $edit_data = find_by_sql("
        SELECT *
        FROM transaction_master
        WHERE transaction_id='{$edit_id}'
        AND transaction_type IN (3,4,5)
        LIMIT 1
    ");

    if (!empty($edit_data)) {
        $edit_return = $edit_data[0];
    }
}

// View Mode Handler
$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$view_return = null;

if ($view_id > 0) {
    $view_data = find_by_sql("
        SELECT tm.*, p.name AS product_name
        FROM transaction_master tm
        LEFT JOIN products p ON p.id = tm.product_id
        WHERE tm.transaction_id='{$view_id}'
        LIMIT 1
    ");

    if (!empty($view_data)) {
        $view_return = $view_data[0];
    }
}

/* =========================================================================
   1. SAVE RETURN ENTRY
   ========================================================================= */
if (isset($_POST['save_return'])) {
    global $db;

    $return_type          = (int)$_POST['return_type'];
    $supplier_id          = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 0;
    $customer_id          = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $product_id           = (int)$_POST['product_id'];
    $qty                  = (float)$_POST['qty'];
    $remarks              = remove_junk($db->escape($_POST['remarks']));
    $refund_required      = isset($_POST['refund_required']) ? 1 : 0;
    $credit_note_required = isset($_POST['credit_note_required']) ? 1 : 0;

    // Basic Validations
    if ($product_id <= 0 || $qty <= 0) {
        $session->msg('d', 'Invalid product selection or quantity.');
        redirect('return_master.php', false);
    }

    if ($return_type <= 0 || $return_type > 3) {
        $session->msg('d', 'Please select a valid return type.');
        redirect('return_master.php', false);
    }

    $product = find_by_id('products', $product_id);
    if (!$product) {
        $session->msg('d', 'Selected product was not found.');
        redirect('return_master.php', false);
    }

    $gst_id = (int)$product['gst_id'];

    /* --- Case 1: Return To Supplier --- */
    if ($return_type == 1) {
        if ($supplier_id <= 0) {
            $session->msg('d', 'Please select a supplier.');
            redirect('return_master.php', false);
        }

        $transaction_type = 3;
        $from_dept        = 'STORE';
        $to_dept          = 'SUPPLIER';
        $credit_amount    = (float)$product['buy_price'] * $qty;

        $stock = find_by_sql("
            SELECT quantity FROM inventory
            WHERE product_id = '{$product_id}'
            ORDER BY inventory_id DESC LIMIT 1
        ");

        if (empty($stock) || $stock[0]['quantity'] < $qty) {
            $session->msg('d', 'Insufficient stock available in store.');
            redirect('return_master.php', false);
        }

        $db->query("
            INSERT INTO transaction_master (
                product_id, supplier_id, quantity, gst_id, transaction_type,
                from_dept, to_dept, comments, refund_required, credit_note_required,
                discarded, entry_date
            ) VALUES (
                '{$product_id}', '{$supplier_id}', '{$qty}', '{$gst_id}', '{$transaction_type}',
                '{$from_dept}', '{$to_dept}', '{$remarks}', '{$refund_required}', '{$credit_note_required}',
                0, NOW()
            )
        ");
        $transaction_id = $db->insert_id();

        // Stock reduce
        $db->query("
            UPDATE inventory
            SET quantity = quantity - {$qty}
            WHERE product_id = '{$product_id}'
            ORDER BY inventory_id DESC LIMIT 1
        ");
    }

    /* --- Case 2: Return From Customer --- */
    elseif ($return_type == 2) {
        if ($customer_id <= 0) {
            $session->msg('d', 'Please select a customer.');
            redirect('return_master.php', false);
        }

        $transaction_type = 4;
        $from_dept        = 'CUSTOMER';
        $to_dept          = 'STORE';
        $credit_amount    = (float)$product['sale_price'] * $qty;

        $db->query("
            INSERT INTO transaction_master (
                product_id, customer_id, quantity, gst_id, transaction_type,
                from_dept, to_dept, comments, refund_required, credit_note_required,
                discarded, entry_date
            ) VALUES (
                '{$product_id}', '{$customer_id}', '{$qty}', '{$gst_id}', '{$transaction_type}',
                '{$from_dept}', '{$to_dept}', '{$remarks}', '{$refund_required}', '{$credit_note_required}',
                0, NOW()
            )
        ");
        $transaction_id = $db->insert_id();

        // Add back to inventory
        $db->query("
            INSERT INTO inventory (
                transaction_id, product_id, quantity, origin_dept, status, updated_at
            ) VALUES (
                '{$transaction_id}', '{$product_id}', '{$qty}', 'CUSTOMER', 1, NOW()
            )
        ");
    }

    /* --- Case 3: Discard Product --- */
    elseif ($return_type == 3) {
        $transaction_type = 5;
        $from_dept        = 'STORE';
        $to_dept          = 'DISCARD';
        $credit_amount    = 0;

        $stock = find_by_sql("
            SELECT quantity FROM inventory
            WHERE product_id = '{$product_id}'
            ORDER BY inventory_id DESC LIMIT 1
        ");

        if (empty($stock) || $stock[0]['quantity'] < $qty) {
            $session->msg('d', 'Insufficient stock to discard.');
            redirect('return_master.php', false);
        }

        $db->query("
            INSERT INTO transaction_master (
                product_id, quantity, gst_id, transaction_type,
                from_dept, to_dept, comments, refund_required, credit_note_required,
                discarded, entry_date
            ) VALUES (
                '{$product_id}', '{$qty}', '{$gst_id}', '{$transaction_type}',
                '{$from_dept}', '{$to_dept}', '{$remarks}', 0, 0,
                1, NOW()
            )
        ");
        $transaction_id = $db->insert_id();

        // Reduce inventory
        $db->query("
            UPDATE inventory
            SET quantity = quantity - {$qty}
            WHERE product_id = '{$product_id}'
            ORDER BY inventory_id DESC LIMIT 1
        ");
    }

    /* --- Credit Note Generation --- */
    if ($credit_note_required == 1 && $return_type != 3) {
        $party_type = ($return_type == 1) ? 'SUPPLIER' : 'CUSTOMER';
        $party_id   = ($return_type == 1) ? $supplier_id : $customer_id;
        $credit_no  = 'CN' . date('YmdHis');

        $db->query("
            INSERT INTO credit_notes (
                transaction_id, party_type, party_id, amount, credit_note_no, remarks, created_at
            ) VALUES (
                '{$transaction_id}', '{$party_type}', '{$party_id}', '{$credit_amount}', '{$credit_no}', '{$remarks}', NOW()
            )
        ");
    }

    $session->msg('s', 'Return entry recorded successfully.');
    redirect('return_master.php', false);
}

/* =========================================================================
   2. UPDATE RETURN ENTRY
   ========================================================================= */
if (isset($_POST['update_return'])) {
    global $db;

    $transaction_id       = (int)$_POST['transaction_id'];
    $qty                  = (float)$_POST['qty'];
    $remarks              = remove_junk($db->escape($_POST['remarks']));
    $refund_required      = isset($_POST['refund_required']) ? 1 : 0;
    $credit_note_required = isset($_POST['credit_note_required']) ? 1 : 0;
    $discarded            = ($edit_return && $edit_return['transaction_type'] == 5) ? 1 : 0;

    $db->query("
        UPDATE transaction_master
        SET quantity='{$qty}',
            comments='{$remarks}',
            refund_required='{$refund_required}',
            credit_note_required='{$credit_note_required}',
            discarded='{$discarded}',
            updated_at=NOW()
        WHERE transaction_id='{$transaction_id}'
    ");

    $session->msg('s', 'Return record updated successfully.');
    redirect('return_master.php', false);
}

include_once('layouts/header.php');
?>

<!-- UI Styling matching BOM Master Layout -->
<style>
    .card-master {
        background: #ffffff;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        border: 1px solid #e2e8f0;
        margin-bottom: 25px;
        overflow: hidden;
    }
    .card-master-header {
        background: #ffffff;
        padding: 16px 20px;
        border-bottom: 3px solid #0284c7;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .card-master-header h5 {
        margin: 0;
        font-weight: 700;
        font-size: 13px;
        color: #1e293b;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .card-master-body {
        padding: 20px;
    }
    .form-label-custom {
        font-size: 11px;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        margin-bottom: 6px;
        display: block;
        letter-spacing: 0.5px;
    }
    .form-control-custom {
        border-radius: 6px;
        border: 1px solid #cbd5e1;
        padding: 8px 12px;
        height: auto;
        font-size: 13px;
        color: #1e293b;
        box-shadow: none;
        transition: all 0.2s ease;
    }
    .form-control-custom:focus {
        border-color: #0284c7;
        box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
    }
    .search-input-wrapper {
        position: relative;
        margin-bottom: 15px;
    }
    .search-input-wrapper i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 12px;
    }
    .search-input-wrapper input {
        padding-left: 32px;
    }
    .table-custom {
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
    }
    .table-custom thead th {
        background-color: #1a233a !important;
        color: #ffffff !important;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 12px 10px;
        border: none !important;
    }
    .table-custom tbody td {
        font-size: 13px;
        color: #334155;
        padding: 10px;
        vertical-align: middle !important;
        border-bottom: 1px solid #f1f5f9;
    }
    .btn-action {
        width: 28px;
        height: 28px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        font-size: 11px;
        margin: 0 2px;
        border: none;
    }
    .btn-action-view { background-color: #10b981; color: #fff; }
    .btn-action-view:hover { background-color: #059669; color: #fff; }
    .btn-action-edit { background-color: #0284c7; color: #fff; }
    .btn-action-edit:hover { background-color: #0369a1; color: #fff; }
    
    .btn-custom-save {
        background-color: #10b981;
        color: #ffffff;
        font-weight: 700;
        font-size: 12px;
        padding: 9px 20px;
        border-radius: 4px;
        text-transform: uppercase;
        border: none;
        letter-spacing: 0.5px;
    }
    .btn-custom-save:hover {
        background-color: #059669;
        color: #ffffff;
    }
    .btn-custom-clear {
        background-color: #f1f5f9;
        color: #475569;
        font-weight: 700;
        font-size: 12px;
        padding: 9px 20px;
        border-radius: 4px;
        text-transform: uppercase;
        border: 1px solid #cbd5e1;
        letter-spacing: 0.5px;
        margin-right: 8px;
    }
    .btn-custom-clear:hover {
        background-color: #e2e8f0;
        color: #334155;
    }
    .checkbox-custom {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 6px;
        font-size: 12px;
        color: #334155;
        font-weight: 600;
    }
</style>

<div class="row">
    <div class="col-md-12">
        <?php echo display_msg($msg); ?>
    </div>
</div>

<div class="row">
    <!-- LEFT CARD: CREATE / UPDATE RETURN -->
    <div class="col-md-5">
        <div class="card-master">
            <div class="card-master-header">
                <h5>
                    <span class="glyphicon glyphicon-list-alt" style="color: #0284c7;"></span>
                    <?php echo $edit_return ? 'UPDATE RETURN' : 'CREATE RETURN'; ?>
                </h5>
            </div>
            <div class="card-master-body">
                <form method="post" action="return_master.php<?php echo $edit_return ? '?edit=' . $edit_return['transaction_id'] : ''; ?>">
                    
                    <?php if ($edit_return): ?>
                        <input type="hidden" name="transaction_id" value="<?php echo $edit_return['transaction_id']; ?>">
                    <?php endif; ?>

                    <!-- Return Type -->
                    <div class="form-group">
                        <label class="form-label-custom">SELECT RETURN TYPE <span class="text-danger">*</span></label>
                        <select name="return_type" id="return_type" class="form-control form-control-custom" required <?php echo $edit_return ? 'disabled' : ''; ?>>
                            <option value="">-- Select Type --</option>
                            <option value="1" <?php if ($edit_return && $edit_return['transaction_type'] == 3) echo 'selected'; ?>>Return To Supplier</option>
                            <option value="2" <?php if ($edit_return && $edit_return['transaction_type'] == 4) echo 'selected'; ?>>Return From Customer</option>
                            <option value="3" <?php if ($edit_return && $edit_return['transaction_type'] == 5) echo 'selected'; ?>>Discard Item</option>
                        </select>
                        <?php if ($edit_return): ?>
                            <input type="hidden" name="return_type" value="<?php 
                                if ($edit_return['transaction_type'] == 3) echo '1';
                                elseif ($edit_return['transaction_type'] == 4) echo '2';
                                else echo '3';
                            ?>">
                        <?php endif; ?>
                    </div>

                    <!-- Supplier Field -->
                    <div class="form-group" id="supplier_div" style="display:none;">
                        <label class="form-label-custom">SELECT SUPPLIER <span class="text-danger">*</span></label>
                        <select name="supplier_id" id="supplier_id" class="form-control form-control-custom">
                            <option value="">-- Select Supplier --</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?php echo $s['id']; ?>" <?php if ($edit_return && $edit_return['supplier_id'] == $s['id']) echo 'selected'; ?>>
                                    <?php echo remove_junk($s['supplier_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Customer Field -->
                    <div class="form-group" id="customer_div" style="display:none;">
                        <label class="form-label-custom">SELECT CUSTOMER <span class="text-danger">*</span></label>
                        <select name="customer_id" id="customer_id" class="form-control form-control-custom">
                            <option value="">-- Select Customer --</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php if ($edit_return && $edit_return['customer_id'] == $c['id']) echo 'selected'; ?>>
                                    <?php echo remove_junk($c['customer_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Product Selection -->
                    <div class="form-group">
                        <label class="form-label-custom">SELECT PRODUCT <span class="text-danger">*</span></label>
                        <select name="product_id" id="product_id" class="form-control form-control-custom" required <?php echo $edit_return ? 'disabled' : ''; ?>>
                            <option value="">-- Select Product --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php if ($edit_return && $edit_return['product_id'] == $p['id']) echo 'selected'; ?>>
                                    <?php echo remove_junk($p['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Quantity -->
                    <div class="form-group">
                        <label class="form-label-custom">QUANTITY <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="qty" id="qty" class="form-control form-control-custom" placeholder="Qty" required value="<?php echo $edit_return ? $edit_return['quantity'] : ''; ?>">
                    </div>

                    <!-- Checkboxes -->
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label class="checkbox-custom">
                            <input type="checkbox" name="refund_required" value="1" <?php if ($edit_return && $edit_return['refund_required'] == 1) echo 'checked'; ?>>
                            REFUND REQUIRED
                        </label>
                        <label class="checkbox-custom">
                            <input type="checkbox" name="credit_note_required" value="1" <?php if ($edit_return && $edit_return['credit_note_required'] == 1) echo 'checked'; ?>>
                            CREDIT NOTE REQUIRED
                        </label>
                    </div>

                    <!-- Remarks -->
                    <div class="form-group">
                        <label class="form-label-custom">REMARKS</label>
                        <textarea name="remarks" id="remarks" class="form-control form-control-custom" rows="2" placeholder="Remarks..."><?php echo $edit_return ? remove_junk($edit_return['comments']) : ''; ?></textarea>
                    </div>

                    <!-- Form Action Buttons -->
                    <div class="text-right" style="margin-top: 20px;">
                        <?php if ($edit_return): ?>
                            <a href="return_master.php" class="btn btn-custom-clear">CLEAR</a>
                            <button type="submit" name="update_return" class="btn btn-custom-save" style="background-color: #0284c7;">UPDATE RETURN</button>
                        <?php else: ?>
                            <a href="return_master.php" class="btn btn-custom-clear">CLEAR</a>
                            <button type="submit" name="save_return" class="btn btn-custom-save">SAVE RETURN</button>
                        <?php endif; ?>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <!-- RIGHT CARD: RETURN LIST -->
    <div class="col-md-7">
        <div class="card-master">
            <div class="card-master-header">
                <h5>RETURN LIST</h5>
            </div>
            <div class="card-master-body">
                
                <!-- Search Field -->
                <div class="search-input-wrapper">
                    <i class="glyphicon glyphicon-search"></i>
                    <input type="text" id="returnSearchInput" class="form-control form-control-custom" placeholder="Search Return product...">
                </div>

                <?php
                $returns = find_by_sql("
                    SELECT tm.*, p.name AS product_name
                    FROM transaction_master tm
                    LEFT JOIN products p ON p.id = tm.product_id
                    WHERE tm.transaction_type IN (3,4,5)
                    ORDER BY tm.transaction_id DESC
                ");
                ?>
                <div class="table-responsive">
                    <table class="table table-custom" id="returnsTable">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 40px;">#</th>
                                <th>PRODUCT NAME</th>
                                <th class="text-center">QTY</th>
                                <th class="text-center">TYPE</th>
                                <th class="text-center" style="width: 90px;">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($returns)): ?>
                                <?php $i = 1; foreach ($returns as $r): ?>
                                    <tr>
                                        <td class="text-center"><?php echo $i++; ?></td>
                                        <td><strong><?php echo remove_junk($r['product_name']); ?></strong></td>
                                        <td class="text-center"><?php echo (float)$r['quantity']; ?></td>
                                        <td class="text-center">
                                            <?php
                                            if ($r['transaction_type'] == 3) {
                                                echo '<span class="label label-warning">Supplier</span>';
                                            } elseif ($r['transaction_type'] == 4) {
                                                echo '<span class="label label-info">Customer</span>';
                                            } else {
                                                echo '<span class="label label-danger">Discard</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="return_master.php?view=<?php echo $r['transaction_id']; ?>" class="btn-action btn-action-view" title="View">
                                                <i class="glyphicon glyphicon-eye-open"></i>
                                            </a>
                                            <a href="return_master.php?edit=<?php echo $r['transaction_id']; ?>" class="btn-action btn-action-edit" title="Edit">
                                                <i class="glyphicon glyphicon-pencil"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted" style="padding: 20px;">No return records found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- DETAILED VIEW MODAL / CARD -->
<?php if ($view_return): ?>
<div class="row">
    <div class="col-md-12">
        <div class="card-master" style="border-top: 3px solid #0284c7;">
            <div class="card-master-header" style="justify-content: space-between;">
                <h5>
                    <span class="glyphicon glyphicon-info-sign" style="color: #0284c7;"></span>
                    RETURN DETAILS (#<?php echo $view_return['transaction_id']; ?>)
                </h5>
                <a href="return_master.php" class="btn btn-xs btn-default"><i class="glyphicon glyphicon-remove"></i> CLOSE</a>
            </div>
            <div class="card-master-body">
                <table class="table table-bordered" style="font-size: 13px; margin: 0;">
                    <tr>
                        <th style="width: 20%; background: #f8fafc;">PRODUCT NAME</th>
                        <td><?php echo remove_junk($view_return['product_name']); ?></td>
                        <th style="width: 20%; background: #f8fafc;">QUANTITY</th>
                        <td><?php echo (float)$view_return['quantity']; ?></td>
                    </tr>
                    <tr>
                        <th style="background: #f8fafc;">RETURN TYPE</th>
                        <td>
                            <?php
                            if ($view_return['transaction_type'] == 3) echo 'Supplier Return';
                            elseif ($view_return['transaction_type'] == 4) echo 'Customer Return';
                            else echo 'Discard';
                            ?>
                        </td>
                        <th style="background: #f8fafc;">ENTRY DATE</th>
                        <td><?php echo date('d/M/Y h:i A', strtotime($view_return['entry_date'])); ?></td>
                    </tr>
                    <tr>
                        <th style="background: #f8fafc;">REFUND REQUIRED</th>
                        <td><?php echo $view_return['refund_required'] ? 'Yes' : 'No'; ?></td>
                        <th style="background: #f8fafc;">CREDIT NOTE REQUIRED</th>
                        <td><?php echo $view_return['credit_note_required'] ? 'Yes' : 'No'; ?></td>
                    </tr>
                    <tr>
                        <th style="background: #f8fafc;">DISCARD STATUS</th>
                        <td><?php echo $view_return['discarded'] ? 'Discarded' : 'Active'; ?></td>
                        <th style="background: #f8fafc;">REMARKS</th>
                        <td><?php echo !empty($view_return['comments']) ? remove_junk($view_return['comments']) : '-'; ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Dynamic Fields JS & Live Search -->
<script>
function handlePartyVisibility() {
    var typeVal = document.getElementById('return_type').value;
    var supplierDiv = document.getElementById('supplier_div');
    var customerDiv = document.getElementById('customer_div');

    supplierDiv.style.display = (typeVal === '1') ? 'block' : 'none';
    customerDiv.style.display = (typeVal === '2') ? 'block' : 'none';
}

document.getElementById('return_type').addEventListener('change', handlePartyVisibility);
window.addEventListener('DOMContentLoaded', handlePartyVisibility);

// Live search for Return List table
document.getElementById('returnSearchInput').addEventListener('keyup', function() {
    var filter = this.value.toLowerCase();
    var rows = document.querySelectorAll('#returnsTable tbody tr');

    rows.forEach(function(row) {
        var text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>

<?php include_once('layouts/footer.php'); ?>
