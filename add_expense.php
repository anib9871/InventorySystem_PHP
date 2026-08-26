<?php
$page_title = 'Manage Daily Expenses';
require_once('includes/load.php');

$center_id = $_SESSION['center_id'] ?? 0;
$user_id   = $_SESSION['user_id'] ?? 0; 
$role_id   = $_SESSION['role_id'] ?? 0;

// ==========================================
// 1. ADD NEW EXPENSE LOGIC
// ==========================================
if(isset($_POST['add_expense'])){
    $expense_date = $db->escape($_POST['expense_date']);
    $category_id  = (int)$_POST['category_id'];
    $description  = $db->escape($_POST['description']);
    $amount       = (float)$_POST['amount'];
    $payment_mode = $db->escape($_POST['payment_mode']);
    $reference_no = $db->escape($_POST['reference_no']);

    $sql = "INSERT INTO expenses (expense_date, category_id, description, amount, payment_mode, reference_no, center_id, created_by) 
            VALUES ('{$expense_date}', '{$category_id}', '{$description}', '{$amount}', '{$payment_mode}', '{$reference_no}', '{$center_id}', '{$user_id}')";

    if($db->query($sql)){
        $session->msg('s', "Expense added successfully.");
    } else {
        $session->msg('d', "Failed to add expense.");
    }
    redirect('add_expense.php', false);
}

// ==========================================
// 2. UPDATE EXISTING EXPENSE LOGIC
// ==========================================
if(isset($_POST['update_expense'])){
    $id           = (int)$_POST['expense_id'];
    $expense_date = $db->escape($_POST['expense_date']);
    $category_id  = (int)$_POST['category_id'];
    $description  = $db->escape($_POST['description']);
    $amount       = (float)$_POST['amount'];
    $payment_mode = $db->escape($_POST['payment_mode']);
    $reference_no = $db->escape($_POST['reference_no']);

    $sql = "UPDATE expenses SET 
            expense_date = '{$expense_date}', 
            category_id = '{$category_id}', 
            description = '{$description}', 
            amount = '{$amount}', 
            payment_mode = '{$payment_mode}', 
            reference_no = '{$reference_no}' 
            WHERE id = '{$id}' AND center_id = '{$center_id}'";

    if($db->query($sql)){
        $session->msg('s', "Expense updated successfully.");
        redirect('add_expense.php', false);
    } else {
        $session->msg('d', "Failed to update expense.");
        redirect("add_expense.php?edit={$id}", false);
    }
}

// ==========================================
// 3. FETCH DATA FOR EDITING (Agar Table se Edit dabaya ho)
// ==========================================
$edit_data = [];
if(isset($_GET['edit'])){
    $edit_id = (int)$_GET['edit'];
    $res = find_by_sql("SELECT * FROM expenses WHERE id = '{$edit_id}' LIMIT 1");
    if(!empty($res)){
        $edit_data = $res[0];
    }
}

// ==========================================
// 4. FETCH ALL MASTERS AND LIST DATA
// ==========================================
$master_categories = find_by_sql("SELECT id, category_name FROM expense_master WHERE status = 1 ORDER BY category_name ASC");
$payment_modes = find_by_sql("SELECT id, mode_name FROM payment_mode_master WHERE is_active = 1 ORDER BY mode_name ASC");

// Role filter for expenses list
$cond = "";
if($role_id == 3) {
    $cond = " WHERE e.center_id = '{$center_id}' ";
}

$expenses_list = find_by_sql("
    SELECT e.*, em.category_name 
    FROM expenses e
    LEFT JOIN expense_master em ON em.id = e.category_id
    {$cond}
    ORDER BY e.expense_date DESC, e.id DESC
");

include_once('layouts/header.php'); 
?>

<style>
#expenseTable thead th{ background:#0f172a; color:#fff; font-weight:600; border-color:#0f172a; font-size:12px; }
#expenseTable tbody td{ vertical-align:middle; font-size: 12px; }
#expenseTable tbody tr:hover{ background:#f7fbff; }
</style>

<div class="row">
    <!-- LEFT SIDE: FORM (Add/Edit) -->
    <div class="col-md-4">
        <div class="panel panel-default" style="border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
            <div class="panel-heading" style="background:#0f172a; color:#fff; border-radius: 8px 8px 0 0;">
                <strong>
                    <?php if(!empty($edit_data)): ?>
                        <i class="fa fa-pencil"></i> Edit Daily Expense
                    <?php else: ?>
                        <i class="fa fa-money"></i> Add Daily Expense
                    <?php endif; ?>
                </strong>
            </div>
            <div class="panel-body">
                
                <form method="post" action="add_expense.php" class="clearfix">
                    <?php if(!empty($edit_data)): ?>
                        <input type="hidden" name="expense_id" value="<?= $edit_data['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Expense Date *</label>
                        <input type="date" class="form-control" name="expense_date" value="<?= !empty($edit_data) ? $edit_data['expense_date'] : date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Expense Category *</label>
                        <select class="form-control" name="category_id" required>
                            <option value="">-- Select Category --</option>
                            <?php foreach($master_categories as $mc): ?>
                                <option value="<?= $mc['id'] ?>" <?= (!empty($edit_data) && $edit_data['category_id'] == $mc['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($mc['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Description / Note</label>
                        <input type="text" class="form-control" name="description" value="<?= !empty($edit_data) ? htmlspecialchars($edit_data['description']) : '' ?>" placeholder="Note...">
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Amount (₹) *</label>
                                <input type="number" step="any" class="form-control" name="amount" value="<?= !empty($edit_data) ? $edit_data['amount'] : '' ?>" placeholder="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Pay Mode *</label>
                                <select class="form-control" name="payment_mode" required>
                                    <option value="">-- Select --</option>
                                    <?php foreach($payment_modes as $pm): ?>
                                        <option value="<?= htmlspecialchars($pm['mode_name']) ?>" <?= (!empty($edit_data) && $edit_data['payment_mode'] == $pm['mode_name']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($pm['mode_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Reference No.</label>
                        <input type="text" class="form-control" name="reference_no" value="<?= !empty($edit_data) ? htmlspecialchars($edit_data['reference_no']) : '' ?>" placeholder="Ref...">
                    </div>

                    <div class="form-group" style="margin-top: 20px;">
                        <?php if(!empty($edit_data)): ?>
                            <button type="submit" name="update_expense" class="btn btn-warning" style="width: 100%; font-weight: bold;">Update Expense</button>
                            <div class="text-center" style="margin-top: 10px;">
                                <a href="add_expense.php" class="text-danger">Cancel Edit</a>
                            </div>
                        <?php else: ?>
                            <button type="submit" name="add_expense" class="btn btn-primary" style="width: 100%; font-weight: bold;">Save Expense</button>
                        <?php endif; ?>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <!-- RIGHT SIDE: EXPENSE LIST (Table) -->
    <div class="col-md-8">
        <div class="panel panel-default" style="border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.06);">
            <div class="panel-heading clearfix" style="border-radius: 8px 8px 0 0;">
                <strong class="pull-left" style="margin-top:6px; font-size: 15px; text-transform:uppercase;">Expense List</strong>
                <input type="text" id="expenseSearch" class="form-control pull-right" placeholder="Search..." style="width:200px; height:30px;">
            </div>
            
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="expenseTable">
                        <thead>
                            <tr>
                                <th width="10%">Date</th>
                                <th width="20%">Category</th>
                                <th width="35%">Description</th>
                                <th width="15%" class="text-right">Amount (₹)</th>
                                <th width="10%" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($expenses_list)): ?>
                                <tr><td colspan="5" class="text-center text-muted">No expenses recorded yet.</td></tr>
                            <?php else: ?>
                                <?php $total = 0; foreach($expenses_list as $exp): $total += $exp['amount']; ?>
                                <tr>
                                    <td><?= date('d/M/Y', strtotime($exp['expense_date'])); ?></td>
                                    <td>
                                        <b style="color: #475569;"><?= htmlspecialchars($exp['category_name'] ?? 'Unknown'); ?></b><br>
                                        <span class="label label-info" style="font-size:9px;"><?= htmlspecialchars($exp['payment_mode']); ?></span>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($exp['description']); ?>
                                        <?php if(!empty($exp['reference_no'])): ?>
                                            <br><small class="text-muted">Ref: <?= htmlspecialchars($exp['reference_no']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right"><b style="color: #dc2626;">₹ <?= number_format($exp['amount'], 2); ?></b></td>
                                    <td class="text-center">
                                        <!-- Edit Button (Redirects to same page with edit id) -->
                                        <a href="add_expense.php?edit=<?= $exp['id']; ?>" class="btn btn-info btn-xs" title="Edit">
                                            <i class="fa fa-pencil"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <?php if(!empty($expenses_list)): ?>
                        <tfoot>
                            <tr style="background:#e2e8f0;">
                                <td colspan="3" class="text-right" style="font-weight: 800;">Total:</td>
                                <td class="text-right" style="font-weight: 800; color: #dc2626;">₹ <?= number_format($total, 2); ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
// Search script for the table
$('#expenseSearch').on('keyup', function () {
    var value = $(this).val().toLowerCase();
    $('#expenseTable tbody tr').filter(function () {
        $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
    });
});
</script>

<?php include_once('layouts/footer.php'); ?>