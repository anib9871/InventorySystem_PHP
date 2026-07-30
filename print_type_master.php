<?php
$page_title = 'Print Type Master';
require_once('includes/load.php');
//page_require_level(1);

/* AUTO DEFAULT PRINT TYPES */
$check_print = find_by_sql("
SELECT id
FROM print_type_master
LIMIT 1
");

if(!$check_print){
  $db->query("
  INSERT INTO print_type_master
  (print_name,paper_width,css_width,status)
  VALUES
  ('A4','210mm','190mm','Active'),
  ('58MM Thermal','58mm','54mm','Active'),
  ('80MM Thermal','80mm','78mm','Active'),
  ('112MM Thermal','112mm','108mm','Active')
  ");
}

/* FETCH LIST */
$print_types = find_by_sql("
SELECT *
FROM print_type_master
ORDER BY id ASC
");

/* ADD */
if(isset($_POST['add_print'])){

    $print_name  = remove_junk($db->escape($_POST['print_name']));
    $paper_width = remove_junk($db->escape($_POST['paper_width'])) . "mm";
    $css_width   = remove_junk($db->escape($_POST['css_width'])) . "mm";
    $status      = remove_junk($db->escape($_POST['status']));

    $sql = "INSERT INTO print_type_master (print_name,paper_width,css_width,status)
            VALUES ('{$print_name}','{$paper_width}','{$css_width}','{$status}')";

    if($db->query($sql)){
        $session->msg("s","Print Type Added Successfully!");
    } else {
        $session->msg("d","Failed to Add Print Type!");
    }

    redirect('print_type_master.php',false);
}

/* EDIT FETCH */
$edit = false;

if(isset($_GET['edit'])){
    $id = (int)$_GET['edit'];
    $edit = find_by_id('print_type_master', $id);
}

/* UPDATE */
if(isset($_POST['update_print'])){

    $id = (int)$_POST['id'];

    $print_name  = remove_junk($db->escape($_POST['print_name']));
    $paper_width = remove_junk($db->escape($_POST['paper_width'])) . "mm";
    $css_width   = remove_junk($db->escape($_POST['css_width'])) . "mm";
    $status      = remove_junk($db->escape($_POST['status']));

    $sql = "UPDATE print_type_master SET
            print_name='{$print_name}',
            paper_width='{$paper_width}',
            css_width='{$css_width}',
            status='{$status}'
            WHERE id='{$id}'";

    if($db->query($sql)){
        $session->msg("s","Updated Successfully!");
    } else {
        $session->msg("d","Update Failed!");
    }

    redirect('print_type_master.php',false);
}

/* DELETE */
if(isset($_GET['del'])){

    $id = (int)$_GET['del'];

    if($db->query("DELETE FROM print_type_master WHERE id='{$id}'")){
        $session->msg("s","Deleted Successfully!");
    } else {
        $session->msg("d","Deletion Failed!");
    }

    redirect('print_type_master.php',false);
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

.form-group-compact {
    margin-bottom: 10px;
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
    margin-top: 15px;
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

/* Scrollable Table Container */
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

.badge-active {
    background: #e0f2fe;
    color: #0369a1;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
}

.badge-inactive {
    background: #f1f5f9;
    color: #64748b;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
}

/* Action Cell Styling */
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

<!-- LEFT FORM -->
<div class="col-md-4">
    <div class="panel panel-default">
        <div class="panel-heading">
            <i class="glyphicon glyphicon-print" style="color: #2b8cff; margin-right: 5px;"></i>
            <strong><?php echo $edit ? 'EDIT PRINT TYPE' : 'ADD PRINT TYPE'; ?></strong>
        </div>

        <div class="panel-body">
            <form method="post" id="printForm">

                <?php if($edit): ?>
                    <input type="hidden" name="id" value="<?php echo $edit['id']; ?>">
                <?php endif; ?>

                <div class="form-group form-group-compact">
                    <label>Print Name</label>
                    <input type="text"
                           id="print_name"
                           name="print_name"
                           class="form-control"
                           value="<?php echo $edit ? $edit['print_name'] : ''; ?>"
                           placeholder="e.g. 80MM Thermal, A4"
                           required autofocus>
                </div>

                <div class="form-group form-group-compact">
                    <label>Paper Width (mm)</label>
                    <input type="number"
                           name="paper_width"
                           min="1"
                           class="form-control"
                           value="<?php echo $edit ? str_replace('mm','',$edit['paper_width']) : ''; ?>"
                           placeholder="e.g. 80"
                           required>
                </div>

                <div class="form-group form-group-compact">
                    <label>CSS Width (mm)</label>
                    <input type="number"
                           name="css_width"
                           min="1"
                           class="form-control"
                           value="<?php echo $edit ? str_replace('mm','',$edit['css_width']) : ''; ?>"
                           placeholder="e.g. 78"
                           required>
                </div>

                <div class="form-group form-group-compact">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="Active" <?php if($edit && $edit['status']=="Active") echo "selected"; ?>>
                            Active
                        </option>
                        <option value="Inactive" <?php if($edit && $edit['status']=="Inactive") echo "selected"; ?>>
                            Inactive
                        </option>
                    </select>
                </div>

                <div class="btn-group-flex">
                    <?php if($edit): ?>
                        <a href="print_type_master.php" class="btn btn-clear btn-custom">
                            Cancel
                        </a>
                        <button type="submit" name="update_print" class="btn btn-primary-custom btn-custom">
                            Update Print Type
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-clear btn-custom" onclick="clearForm()">
                            Clear
                        </button>
                        <button type="submit" name="add_print" class="btn btn-success-custom btn-custom">
                            Save Print Type
                        </button>
                    <?php endif; ?>
                </div>

            </form>
        </div>
    </div>
</div>

<!-- RIGHT LIST -->
<div class="col-md-8">
    <div class="panel panel-default">
        <div class="panel-heading">
            <strong>PRINT TYPE LIST</strong>
        </div>

        <div class="panel-body">

            <div class="search-box">
                <i class="glyphicon glyphicon-search"></i>
                <input type="text" id="search" class="form-control" placeholder="Search print type...">
            </div>

            <div class="table-scrollable">
                <table class="table table-bordered table-striped" id="printTable">
                    <thead>
                        <tr>
                            <th width="40" class="text-center">#</th>
                            <th>Print Name</th>
                            <th class="text-center">Paper Width</th>
                            <th class="text-center">CSS Width</th>
                            <th class="text-center" width="90">Status</th>
                            <th class="text-center" width="80">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if(!empty($print_types)): ?>
                            <?php foreach($print_types as $i => $p): ?>
                                <tr>
                                    <td class="text-center"><?php echo $i+1; ?></td>
                                    <td><strong><?php echo $p['print_name']; ?></strong></td>
                                    <td class="text-center"><?php echo $p['paper_width']; ?></td>
                                    <td class="text-center"><?php echo $p['css_width']; ?></td>
                                    <td class="text-center">
                                        <span class="<?php echo ($p['status']=='Active') ? 'badge-active' : 'badge-inactive'; ?>">
                                            <?php echo $p['status']; ?>
                                        </span>
                                    </td>
                                    <td class="action-td">
                                        <div class="action-cell">
                                            <a href="print_type_master.php?edit=<?php echo $p['id']; ?>" class="btn btn-primary btn-xs equal-btn" title="Edit">
                                                <i class="glyphicon glyphicon-pencil"></i>
                                            </a>
                                            <button type="button" onclick="confirmDelete(<?php echo $p['id']; ?>)" class="btn btn-danger btn-xs equal-btn" title="Delete">
                                                <i class="glyphicon glyphicon-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center" style="color: #94a3b8; padding: 15px;">No print types found.</td>
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
window.onload = function () {
    let input = document.getElementById("print_name");
    if(input) input.focus();
};

function clearForm(){
    document.getElementById("printForm").reset();
    let input = document.getElementById("print_name");
    if(input) input.focus();
}

// Search Filter
document.getElementById("search").addEventListener("keyup", function(){
    let value = this.value.toLowerCase();
    document.querySelectorAll("#printTable tbody tr").forEach(function(row){
        row.style.display = row.textContent.toLowerCase().includes(value) ? "" : "none";
    });
});

/* Delete confirmation modal */
function confirmDelete(id) {
    Swal.fire({
        title: 'Are You Sure?',
        text: "This Supplier Will Be Deleted!",
        icon: 'warning',
        showCancelButton: true,
        confirmColor: '#ef4444',
        cancelColor: '#6b7280',
        confirmButtonText: 'Yes, Delete It!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "supplier_master.php?del=" + id;
        }
    });
}
</script>

<?php include_once('layouts/footer.php'); ?>
