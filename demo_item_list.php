<?php
require_once('includes/load.php');
// page_require_level(2);

$customers = find_by_sql("SELECT * FROM customer_master ORDER BY customer_name ASC");
$products  = find_by_sql("SELECT * FROM products ORDER BY name ASC");

$swal_script = "";

/* ===== 1. SAVE DEMO ITEM ACTION ===== */
if (isset($_POST['save_demo_item'])) {
    global $db;
    
    $customer_id = (int)$_POST['customer_id'];
    $center_id   = (int)($_SESSION['center_id'] ?? 0);
    $org_id      = (int)($_SESSION['org_id'] ?? 0);
    $product_ids = $_POST['product_id'] ?? [];
    $qtys        = $_POST['qty'] ?? [];

    if ($customer_id > 0 && !empty($product_ids)) {
        $db->query("START TRANSACTION");
        try {
            foreach ($product_ids as $index => $pid) {
                $pid = (int)$pid;
                $qty = (float)$qtys[$index];

                if ($pid <= 0 || $qty <= 0) continue;

                $prod_data = find_by_id('products', $pid);
                $unit_price = (float)($prod_data['sale_price'] ?? 0);

                // Transaction Master Entry
                $insert_trans = $db->query("
                    INSERT INTO transaction_master 
                    (
                        product_id, supplier_id, customer_id, entry_date, bill_indent_date,
                        quantity, free_qty, unit, rate_id, gst_id, unit_price, gst_amount,
                        discount_amount, net_price, mrp, misc_amount, sale_amount, sale_gst,
                        sale_net, transaction_type, status, payment_status, amount_received,
                        balance_amount, from_dept, to_dept, comments, created_at, center_id,
                        organization_id, refund_required, credit_note_required, discarded
                    )
                    VALUES 
                    (
                        '$pid', NULL, '$customer_id', NOW(), NOW(), '$qty', 0, 'PCS', 0, 0, 
                        '$unit_price', 0, 0, '$unit_price', 0, 0, 0, 0, 0, 7, 1, 0, 0, 0, 
                        'STORE', 'CUSTOMER', 'Demo Item Dispatch', NOW(), '$center_id', 
                        '$org_id', 0, 0, 0
                    )
                ");

                if (!$insert_trans) {
                    throw new Exception("Transaction Master Error: " . $db->error);
                }

                // Demo Item Detail Entry
                $insert_demo = $db->query("
                    INSERT INTO demo_item_detail (customer_id, product_id, qty, status)
                    VALUES ('$customer_id', '$pid', '$qty', 1)
                ");

                if (!$insert_demo) {
                    throw new Exception("Demo Item Detail Error: " . $db->error);
                }
            }

            $db->query("COMMIT");
            $swal_script = "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Demo items dispatched successfully!',
                        confirmColor: '#2563eb'
                    }).then(() => {
                        window.location = 'demo_item_list.php';
                    });
                });
            </script>";
        } catch (Exception $e) {
            $db->query("ROLLBACK");
            $err_msg = addslashes($e->getMessage());
            $swal_script = "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Dispatch Failed',
                        text: '{$err_msg}',
                        confirmColor: '#ef4444'
                    });
                });
            </script>";
        }
    } else {
        $swal_script = "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'warning',
                    title: 'Warning',
                    text: 'Please select a valid customer and product!',
                    confirmColor: '#f59e0b'
                });
            });
        </script>";
    }
}

/* ===== 2. UPDATE DEMO ITEM ACTION (EDIT) ===== */
if (isset($_POST['update_demo_item'])) {
    global $db;

    $demo_id     = (int)$_POST['edit_demo_id'];
    $customer_id = (int)$_POST['edit_customer_id'];
    $product_id  = (int)$_POST['edit_product_id'];
    $qty         = (float)$_POST['edit_qty'];
    $status      = (int)$_POST['edit_status'];

    if ($demo_id > 0 && $customer_id > 0 && $product_id > 0 && $qty > 0) {
        $update = $db->query("
            UPDATE demo_item_detail 
            SET customer_id = '$customer_id', 
                product_id  = '$product_id', 
                qty         = '$qty', 
                status      = '$status' 
            WHERE id = '$demo_id'
        ");

        if ($update) {
            $swal_script = "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Updated!',
                        text: 'Demo record updated successfully!',
                        confirmColor: '#2563eb'
                    }).then(() => {
                        window.location = 'demo_item_list.php';
                    });
                });
            </script>";
        } else {
            $swal_script = "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Update Failed',
                        text: 'Unable to update record.',
                        confirmColor: '#ef4444'
                    });
                });
            </script>";
        }
    }
}

/* ===== FETCH DEMO RECORDS ===== */
$demo_records = find_by_sql("
    SELECT 
        d.id,
        d.customer_id,
        d.product_id,
        d.qty,
        d.status,
        d.created_at,
        c.customer_name,
        c.contact_no,
        p.name AS product_name
    FROM demo_item_detail d
    LEFT JOIN customer_master c ON d.customer_id = c.id
    LEFT JOIN products p ON d.product_id = p.id
    ORDER BY d.id DESC
");
?>

<?php include_once('layouts/header.php'); ?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
.select2-container .select2-selection--single { height: 38px !important; border: 1px solid #ced4da; border-radius: 6px; padding: 5px; }
.select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 26px; }
.select2-dropdown { z-index: 99999 !important; }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?= $swal_script; ?>

<div class="container-fluid py-4 px-4">
    <div class="card-master">
        <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
            <div>
                <h4 class="m-0 font-weight-bold text-dark" style="font-size: 20px;">Demo Items Management</h4>
                <p class="text-muted m-0" style="font-size: 13px;">View details and status of demo items dispatched to clients</p>
            </div>
            <button type="button" class="btn btn-dispatch" data-toggle="modal" data-target="#addDemoModal">
                <i class="fa fa-plus-circle mr-1"></i> New Demo Dispatch
            </button>
        </div>
        <br>
        <div class="row align-items-center mb-4 g-2">
            <div class="col-md-4">
                <input type="text" id="demoSearch" class="form-control" placeholder="🔍 Search Client or Product Name...">
            </div>
            <div class="col-md-8 text-right">
                <div class="btn-group" role="group">
                    <button class="btn btn-outline-secondary filter-btn active" onclick="filterStatus('ALL')">All</button>
                    <button class="btn btn-outline-warning filter-btn" onclick="filterStatus('1')">🟡 Active (On Demo)</button>
                    <button class="btn btn-outline-success filter-btn" onclick="filterStatus('0')">🟢 Completed (Invoiced)</button>
                </div>
            </div>
        </div>
        <br>
        <div class="table-responsive" style="border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0;">
            <table class="table master-table mb-0" id="demoTable">
                <thead>
                    <tr>
                        <th width="50">#</th>
                        <th>Client Name</th>
                        <th>Contact No</th>
                        <th>Product Name</th>
                        <th width="100" class="text-center">Qty</th>
                        <th>Dispatch Date</th>
                        <th width="150" class="text-center">Status</th>
                        <th width="80" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($demo_records)): ?>
                        <?php $i = 1; foreach ($demo_records as $row): ?>
                            <tr data-status="<?= $row['status']; ?>">
                                <td><?= $i++; ?></td>
                                <td><b class="text-dark"><?= htmlspecialchars($row['customer_name'] ?? 'N/A'); ?></b></td>
                                <td><?= htmlspecialchars($row['contact_no'] ?? '-'); ?></td>
                                <td><?= htmlspecialchars($row['product_name'] ?? 'N/A'); ?></td>
                                <td class="text-center">
                                    <span class="badge badge-light border font-weight-bold" style="font-size:12px; padding: 4px 8px; border-radius: 6px;">
                                        <?= $row['qty']; ?> PCS
                                    </span>
                                </td>
                                <td><?= date('d-M-Y h:i A', strtotime($row['created_at'])); ?></td>
                                <td class="text-center">
                                    <?php if ($row['status'] == 1): ?>
                                        <span class="badge-demo-active">🟡 Active (On Demo)</span>
                                    <?php else: ?>
                                        <span class="badge-demo-completed">🟢 Completed</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-primary btn-edit" 
                                            style="border-radius:6px; padding:2px 8px;"
                                            data-id="<?= $row['id']; ?>"
                                            data-customer_id="<?= $row['customer_id']; ?>"
                                            data-product_id="<?= $row['product_id']; ?>"
                                            data-qty="<?= $row['qty']; ?>"
                                            data-status="<?= $row['status']; ?>">
                                        ✏️ Edit
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No demo item records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ================= ADD DEMO DISPATCH MODAL ================= -->
<div class="modal fade" id="addDemoModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered" role="document">
        <form method="post">
            <div class="modal-content modal-content-compact">
                <div class="modal-header modal-header-compact">
                    <h5 class="modal-title">New Demo Item Dispatch</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body p-4">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold mb-1" style="font-size:12px;">Select Customer</label>
                        <select name="customer_id" class="form-control select2-search" style="width:100%;" required>
                            <option value="">-- Select Customer --</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id']; ?>"><?= htmlspecialchars($c['customer_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <label class="font-weight-bold mb-1" style="font-size:12px;">Demo Products</label>
                    <table class="table table-sm table-bordered mb-2" id="demoInputTable" style="border-radius:8px; overflow:hidden;">
                        <thead>
                            <tr class="bg-light" style="font-size:12px;">
                                <th>Product</th>
                                <th width="100">Qty</th>
                                <th width="40"></th>
                            </tr>
                        </thead>
                        <tbody id="demoInputBody">
                            <tr>
                                <td>
                                   <select name="product_id[]" class="form-control form-control-sm select2-search" style="width:100%;" required>
                                        <option value="">-- Select Product --</option>
                                        <?php foreach ($products as $p): ?>
                                            <option value="<?= $p['id']; ?>"><?= htmlspecialchars($p['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="qty[]" class="form-control form-control-sm text-center" value="1" min="1" required>
                                </td>
                                <td class="text-center align-middle">
                                    <button type="button" class="btn btn-danger btn-sm removeRow" style="padding: 2px 8px; border-radius: 6px;">×</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-sm btn-outline-primary font-weight-bold" id="addRowBtn" style="border-radius:8px; font-size:12px;">
                        ➕ Add More Product
                    </button>
                </div>
                <div class="modal-footer bg-light" style="border-bottom-left-radius: 16px; border-bottom-right-radius: 16px; padding: 12px 20px;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal" style="border-radius:8px;">Cancel</button>
                    <button type="submit" name="save_demo_item" class="btn btn-success btn-sm font-weight-bold" style="border-radius:8px; padding: 6px 18px;">
                        Dispatch Demo
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ================= EDIT DEMO MODAL ================= -->
<div class="modal fade" id="editDemoModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered" role="document">
        <form method="post">
            <input type="hidden" name="edit_demo_id" id="edit_demo_id">
            <div class="modal-content modal-content-compact">
                <div class="modal-header modal-header-compact">
                    <h5 class="modal-title">✏️ Edit Demo Item</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body p-4">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold mb-1" style="font-size:12px;">Customer</label>
                        <select name="edit_customer_id" id="edit_customer_id" class="form-control select2-search" style="width:100%;" required>
                            <option value="">-- Select Customer --</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id']; ?>"><?= htmlspecialchars($c['customer_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold mb-1" style="font-size:12px;">Product</label>
                        <select name="edit_product_id" id="edit_product_id" class="form-control select2-search" style="width:100%;" required>
                            <option value="">-- Select Product --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id']; ?>"><?= htmlspecialchars($p['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-bold mb-1" style="font-size:12px;">Quantity</label>
                            <input type="number" name="edit_qty" id="edit_qty" class="form-control text-center" min="1" required>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label class="font-weight-bold mb-1" style="font-size:12px;">Status</label>
                            <select name="edit_status" id="edit_status" class="form-control" required>
                                <option value="1">🟡 Active (On Demo)</option>
                                <option value="0">🟢 Completed</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light" style="border-bottom-left-radius: 16px; border-bottom-right-radius: 16px; padding: 12px 20px;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal" style="border-radius:8px;">Cancel</button>
                    <button type="submit" name="update_demo_item" class="btn btn-primary btn-sm font-weight-bold" style="border-radius:8px; padding: 6px 18px;">
                        Update Record
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// Search Filter
document.getElementById("demoSearch").addEventListener("keyup", function() {
    let value = this.value.toLowerCase();
    let rows  = document.querySelectorAll("#demoTable tbody tr");

    rows.forEach(row => {
        let text = row.innerText.toLowerCase();
        row.style.display = text.indexOf(value) > -1 ? "" : "none";
    });
});

// Status Filter Tabs
function filterStatus(status) {
    let rows = document.querySelectorAll("#demoTable tbody tr");
    rows.forEach(row => {
        let rowStatus = row.getAttribute("data-status");
        if (status === 'ALL' || rowStatus === status) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
}

// Select2 Initialization
function initSelect2() {
    $('.select2-search').each(function() {
        var modal = $(this).closest('.modal');
        $(this).select2({
            dropdownParent: modal.length ? modal : $(document.body),
            width: '100%'
        });
    });
}

// Add Dynamic Product Row
document.getElementById("addRowBtn").addEventListener("click", function() {
    let tbody = document.getElementById("demoInputBody");
    let firstRow = tbody.querySelector("tr").cloneNode(true);
    firstRow.querySelector("input").value = 1;
    
    // Reset select2 in cloned row
    let select = firstRow.querySelector("select");
    $(select).val('').removeClass("select2-hidden-accessible").next(".select2-container").remove();

    tbody.appendChild(firstRow);
    setTimeout(initSelect2, 50);
});

// Remove Row
document.addEventListener("click", function(e) {
    if (e.target.classList.contains("removeRow")) {
        let rows = document.querySelectorAll("#demoInputBody tr");
        if (rows.length > 1) {
            e.target.closest("tr").remove();
        } else {
            Swal.fire({
                icon: 'warning',
                title: 'Note',
                text: 'At least one product is required!',
                confirmColor: '#2563eb'
            });
        }
    }
});

// Edit Button Click - Open Modal with Data
document.addEventListener("click", function(e) {
    if (e.target.classList.contains("btn-edit")) {
        let btn = e.target;
        document.getElementById("edit_demo_id").value = btn.getAttribute("data-id");
        document.getElementById("edit_qty").value = btn.getAttribute("data-qty");
        document.getElementById("edit_status").value = btn.getAttribute("data-status");

        $('#edit_customer_id').val(btn.getAttribute("data-customer_id")).trigger('change');
        $('#edit_product_id').val(btn.getAttribute("data-product_id")).trigger('change');

        $('#editDemoModal').modal('show');
    }
});

$(document).ready(function() {
    initSelect2();

    $('#addDemoModal, #editDemoModal').on('shown.bs.modal', function () {
        initSelect2();
    });
});
</script>

<?php include_once('layouts/footer.php'); ?>

