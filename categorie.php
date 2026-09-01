<?php
$page_title = 'All Categories';
require_once('includes/load.php');
// page_require_level(1);

/* =========================================================
   SMART AUTO-FILL: DEFAULT CATEGORIES (IF MISSING)
========================================================= */
$default_categories = ['Product', 'Software'];

// 1. Check existing categories
$existing_cats_query = find_by_sql("SELECT name FROM categories");
$existing_cat_names = [];
if ($existing_cats_query) {
    foreach ($existing_cats_query as $ec) {
        $existing_cat_names[] = strtolower(trim($ec['name']));
    }
}

// 2. Find missing categories
$missing_inserts = [];
foreach ($default_categories as $dc) {
    $check_name = strtolower(trim($dc));
    if (!in_array($check_name, $existing_cat_names)) {
        $name_esc = $db->escape($dc);
        $missing_inserts[] = "('$name_esc')";
    }
}

// 3. Insert missing categories
if (!empty($missing_inserts)) {
    $insert_query = "INSERT INTO categories (name) VALUES " . implode(", ", $missing_inserts);
    $db->query($insert_query);
}
/* ========================================================= */

/* ================= FETCH ALL CATEGORIES ================= */
$all_categories = find_all('categories');

/* ================= EDIT FETCH ================= */
$edit_cat = null;
if(isset($_GET['edit'])){
    $edit_id = (int)$_GET['edit'];
    $edit_cat = find_by_id('categories', $edit_id);
}

/* ================= ADD / UPDATE CATEGORY ================= */
if(isset($_POST['save_cat'])){
    $req_field = array('categorie-name');
    validate_fields($req_field);
    
    $cat_name = remove_junk($db->escape($_POST['categorie-name']));
    
    if(empty($errors)){
        /* UPDATE */
        if(isset($_POST['cat_id']) && !empty($_POST['cat_id'])){
            $cat_id = (int)$_POST['cat_id'];
            $sql = "UPDATE categories SET name='{$cat_name}' WHERE id='{$cat_id}'";
            
            if($db->query($sql)){
                $session->msg("s", "Category updated successfully!");
            } else {
                $session->msg("d", "Failed to update category!");
            }
        } 
        /* INSERT */
        else {
            $sql = "INSERT INTO categories (name) VALUES ('{$cat_name}')";
            if($db->query($sql)){
                $session->msg("s", "Successfully added new category!");
            } else {
                $session->msg("d", "Sorry, failed to insert category!");
            }
        }
    } else {
        $session->msg("d", $errors);
    }
    redirect('categorie.php', false);
}

/* ================= DELETE CATEGORY ================= */
if(isset($_GET['delete'])){
    $delete_id = (int)$_GET['delete'];
    if(delete_by_id('categories', $delete_id)){
        $session->msg("s", "Category deleted successfully!");
    } else {
        $session->msg("d", "Category deletion failed!");
    }
    redirect('categorie.php', false);
}

include_once('layouts/header.php'); 
?>

<style>
body {
    background-color: #f4f7fb;
}

.panel {
    border: none;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    margin-bottom: 15px;
    background: #ffffff;
}

.panel-heading {
    padding: 10px 15px;
    font-size: 13px;
    font-weight: 700;
    border-bottom: 2px solid #2b8cff !important;
    background: #ffffff !important;
    color: #1d3557;
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.panel-body {
    padding: 15px;
}

label {
    font-size: 11px;
    margin-bottom: 4px;
    font-weight: 600;
    color: #4a5568;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.form-control {
    height: 32px;
    font-size: 12px;
    padding: 4px 10px;
    border-radius: 5px;
    border: 1px solid #cbd5e1;
    box-shadow: none;
    transition: all 0.2s ease-in-out;
}

.form-control:focus {
    border-color: #2b8cff;
    box-shadow: 0 0 0 2px rgba(43, 140, 255, 0.15);
}

/* Buttons Flex Alignment */
.btn-group-flex {
    display: flex !important;
    align-items: center !important;
    justify-content: flex-end !important;
    gap: 8px !important;
    margin-top: 12px;
}

.btn-custom {
    height: 32px;
    font-size: 11px;
    font-weight: 600;
    padding: 0 14px;
    border-radius: 5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: all 0.2s;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    text-decoration: none !important;
    white-space: nowrap !important;
    line-height: 1 !important;
}

.btn-success-custom {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #fff !important;
    border: none;
}

.btn-success-custom:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    color: #fff !important;
    box-shadow: 0 2px 6px rgba(16, 185, 129, 0.3);
}

.btn-primary-custom {
    background: linear-gradient(135deg, #2b8cff 0%, #1d72db 100%);
    color: #fff !important;
    border: none;
}

.btn-primary-custom:hover {
    background: linear-gradient(135deg, #1d72db 0%, #155bc4 100%);
    color: #fff !important;
}

.btn-clear {
    background: #f1f5f9;
    color: #64748b !important;
    border: 1px solid #cbd5e1;
}

.btn-clear:hover {
    background: #e2e8f0;
    color: #334155 !important;
}

/* Scrollable Table */
.table-scrollable {
    max-height: 320px;
    overflow-y: auto;
    overflow-x: auto;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
}

.table {
    margin-bottom: 0;
    font-size: 12px;
    width: 100%;
}

.table thead {
    position: sticky;
    top: 0;
    background: #18233b;
    color: #ffffff;
    z-index: 10;
}

.table thead th {
    border: none !important;
    padding: 8px 12px;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: #18233b;
}

.table tbody td {
    padding: 8px 12px;
    vertical-align: middle !important;
    border-color: #f1f5f9;
}

.table tbody tr:hover {
    background: #f8fafc;
}

.search-box {
    position: relative;
    width: 100%;
    margin-bottom: 12px;
}

.search-box i {
    position: absolute;
    left: 10px;
    top: 9px;
    color: #94a3b8;
    font-size: 12px;
}

.search-box input {
    padding-left: 30px;
    height: 32px !important;
}

.action-td {
    width: 80px;
    text-align: center;
    white-space: nowrap !important;
}

.action-cell {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px !important;
}

.equal-btn {
    width: 28px !important;
    height: 26px !important;
    padding: 0 !important;
    line-height: 26px !important;
    font-size: 11px !important;
    border-radius: 4px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    margin: 0 !important;
}
</style>

<div class="row">

    <!-- LEFT SIDE : ADD/EDIT FORM -->
    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-heading">
                <i class="glyphicon glyphicon-tags" style="color: #2b8cff; margin-right: 5px;"></i>
                <strong><?php echo $edit_cat ? "EDIT CATEGORY" : "ADD NEW CATEGORY"; ?></strong>
            </div>
            <div class="panel-body">
                <form method="post" action="categorie.php">
                    <input type="hidden" name="cat_id" value="<?php echo $edit_cat ? (int)$edit_cat['id'] : ''; ?>">

                    <div class="form-group" style="margin-bottom: 10px;">
                        <label>Category Name</label>
                        <input type="text" 
                               class="form-control" 
                               name="categorie-name" 
                               placeholder="Enter category name..." 
                               value="<?php echo $edit_cat ? remove_junk(ucfirst($edit_cat['name'])) : ''; ?>"
                               required autofocus>
                    </div>

                    <div class="btn-group-flex">
                        <?php if($edit_cat): ?>
                            <a href="categorie.php" class="btn btn-clear btn-custom">
                                Cancel
                            </a>
                            <button type="submit" name="save_cat" class="btn btn-primary-custom btn-custom">
                                Update Category
                            </button>
                        <?php else: ?>
                            <button type="submit" name="save_cat" class="btn btn-success-custom btn-custom">
                                Add Category
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- RIGHT SIDE : LIST -->
    <div class="col-md-8">
        <div class="panel panel-default">
            <div class="panel-heading">
                <strong>ALL CATEGORIES</strong>
            </div>
            <div class="panel-body">

                <div class="search-box">
                    <i class="glyphicon glyphicon-search"></i>
                    <input type="text" id="catSearch" class="form-control" placeholder="Search category...">
                </div>

                <div class="table-scrollable">
                    <table class="table table-bordered table-striped" id="catTable">
                        <thead>
                            <tr>
                                <th width="50" class="text-center">#</th>
                                <th>Category Name</th>
                                <th class="text-center" width="80">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($all_categories)): ?>
                                <?php foreach ($all_categories as $i => $cat): ?>
                                    <tr>
                                        <td class="text-center"><?php echo $i + 1; ?></td>
                                        <td><strong><?php echo remove_junk(ucfirst($cat['name'])); ?></strong></td>
                                        <td class="action-td">
                                            <div class="action-cell">
                                                <a href="categorie.php?edit=<?php echo (int)$cat['id'];?>" class="btn btn-primary btn-xs equal-btn" title="Edit">
                                                    <i class="glyphicon glyphicon-pencil"></i>
                                                </a>
                                                <button type="button" onclick="confirmDelete(<?php echo (int)$cat['id'];?>)" class="btn btn-danger btn-xs equal-btn" title="Delete">
                                                    <i class="glyphicon glyphicon-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center" style="color: #94a3b8; padding: 15px;">No categories found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

</div>

<script>
// Filter Search
document.getElementById("catSearch").addEventListener("keyup", function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll("#catTable tbody tr");

    rows.forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(filter) ? "" : "none";
    });
});

// Delete Confirmation Modal
function confirmDelete(id) {
    Swal.fire({
        title: 'Are You Sure?',
        text: "This category will be deleted!",
        icon: 'warning',
        showCancelButton: true,
        confirmColor: '#ef4444',
        cancelColor: '#6b7280',
        confirmButtonText: 'Yes, Delete It!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "categorie.php?delete=" + id;
        }
    });
}
</script>

<?php include_once('layouts/footer.php'); ?>
