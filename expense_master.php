<?php
$page_title = 'Expense Master';
require_once('includes/load.php');

$user_id = $_SESSION['user_id'] ?? 0; // Login User ki ID

// ==========================================
// 1. ADD NEW CATEGORY LOGIC
// ==========================================
if(isset($_POST['add_category'])){
    $cat_name = $db->escape($_POST['category_name']);
    
    // Check if already exists
    $check = find_by_sql("SELECT id FROM expense_master WHERE category_name = '{$cat_name}' LIMIT 1");
    if(!empty($check)){
        $session->msg('w', "This category already exists!");
    } else {
        $sql = "INSERT INTO expense_master (category_name, status, created_by) VALUES ('{$cat_name}', 1, '{$user_id}')";
        if($db->query($sql)){
            $session->msg('s', "Category added successfully.");
        } else {
            $session->msg('d', "Failed to add category.");
        }
    }
    redirect('expense_master.php', false);
}

// ==========================================
// 2. UPDATE EXISTING CATEGORY LOGIC
// ==========================================
if(isset($_POST['update_category'])){
    $id = (int)$_POST['cat_id'];
    $cat_name = $db->escape($_POST['category_name']);
    $status = (int)$_POST['status'];
    
    $sql = "UPDATE expense_master SET category_name = '{$cat_name}', status = '{$status}' WHERE id = '{$id}'";
    if($db->query($sql)){
        $session->msg('s', "Category updated successfully.");
    } else {
        $session->msg('d', "Failed to update category.");
    }
    redirect('expense_master.php', false);
}

// ==========================================
// 3. FETCH DATA FOR EDITING (If Edit is clicked)
// ==========================================
$edit_data = [];
if(isset($_GET['edit'])){
    $edit_id = (int)$_GET['edit'];
    $res = find_by_sql("SELECT * FROM expense_master WHERE id = '{$edit_id}' LIMIT 1");
    if(!empty($res)){
        $edit_data = $res[0];
    }
}

// Fetch All Categories for Table
$categories = find_by_sql("SELECT * FROM expense_master ORDER BY category_name ASC");

include_once('layouts/header.php'); 
?>

<div class="row">
    <!-- FORM SECTION (Add / Edit) -->
    <div class="col-md-5">
        <div class="panel panel-default">
            <div class="panel-heading" style="background:#0f172a; color:#fff;">
                <strong>
                    <?php if(!empty($edit_data)): ?>
                        <i class="fa fa-pencil"></i> Edit Expense Category
                    <?php else: ?>
                        <i class="fa fa-plus"></i> Add New Expense Category
                    <?php endif; ?>
                </strong>
            </div>
            <div class="panel-body">
                <!-- Puraana display_msg($msg) HTML Alert hata diya hai taaki sirf SweetAlert aaye -->
                
                <?php if(!empty($edit_data)): ?>
                    <!-- EDIT FORM -->
                    <form method="post" action="expense_master.php">
                        <input type="hidden" name="cat_id" value="<?= $edit_data['id']; ?>">
                        
                        <div class="form-group">
                            <label>Category Name</label>
                            <input type="text" class="form-control" name="category_name" value="<?= htmlspecialchars($edit_data['category_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Status</label>
                            <select class="form-control" name="status">
                                <option value="1" <?= ($edit_data['status'] == 1) ? 'selected' : ''; ?>>Active</option>
                                <option value="0" <?= ($edit_data['status'] == 0) ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <button type="submit" name="update_category" class="btn btn-warning btn-block" style="font-weight:bold;">Update Category</button>
                        <div class="text-center" style="margin-top: 10px;">
                            <a href="expense_master.php" class="text-danger">Cancel Edit</a>
                        </div>
                    </form>
                <?php else: ?>
                    <!-- ADD FORM -->
                    <form method="post" action="expense_master.php">
                        <div class="form-group">
                            <label>Category Name</label>
                            <input type="text" class="form-control" name="category_name" placeholder="e.g. Marketing, Courier..." required>
                        </div>
                        <button type="submit" name="add_category" class="btn btn-primary btn-block" style="font-weight:bold;">Add Category</button>
                    </form>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- TABLE SECTION -->
    <div class="col-md-7">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Expense Categories List</strong></div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead style="background:#f1f5f9;">
                            <tr>
                                <th class="text-center" width="50">#</th>
                                <th>Category Name</th>
                                <th class="text-center" width="100">Status</th>
                                <th class="text-center" width="80">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i=1; foreach($categories as $cat): ?>
                            <tr>
                                <td class="text-center"><?= $i++; ?></td>
                                <td><b><?= htmlspecialchars($cat['category_name']); ?></b></td>
                                <td class="text-center">
                                    <?= ($cat['status'] == 1) ? '<span class="label label-success">Active</span>' : '<span class="label label-danger">Inactive</span>'; ?>
                                </td>
                                <td class="text-center">
                                    <!-- Edit Button -->
                                    <a href="expense_master.php?edit=<?= $cat['id']; ?>" class="btn btn-info btn-xs" title="Edit">
                                        <i class="fa fa-pencil"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once('layouts/footer.php'); ?>