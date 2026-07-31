<?php
$page_title = 'Center Master';
require_once('includes/load.php');

/* FETCH CENTERS (DESC) */
$org_id = $_SESSION['org_id'];

$centers = find_by_sql("
SELECT c.center_id,c.center_name
FROM master_center c
WHERE c.org_id='{$org_id}'
ORDER BY c.center_id DESC
");

/* ================= ADD CENTER ================= */
if(isset($_POST['add_center'])){

  $center_name = remove_junk($db->escape($_POST['center_name']));
  $org_id = $_SESSION['org_id'];

  if($center_name == "" || $org_id == 0){
    $session->msg('d',"Center name khali nahi ho sakta!");
    redirect('master_center.php', false);
  }

  $sql = "INSERT INTO master_center (center_name,org_id)
          VALUES('{$center_name}','{$org_id}')";

  if($db->query($sql)){
    $session->msg('s',"Center Added Successfully!");
  } else {
    $session->msg('d',"Failed to add center!");
  }

  redirect('master_center.php', false);
}

/* ================= EDIT FETCH ================= */
$edit_center = null;

if(isset($_GET['edit'])){
  $id = (int)$_GET['edit'];
  $org_id = $_SESSION['org_id'];

  $res = find_by_sql("
  SELECT * FROM master_center 
  WHERE center_id='{$id}' AND org_id='{$org_id}' 
  LIMIT 1
  ");

  if($res){
    $edit_center = $res[0];
  }
}

/* ================= UPDATE ================= */
if(isset($_POST['update_center'])){

  $id = (int)$_POST['center_id'];
  $center_name = remove_junk($db->escape($_POST['center_name']));
  $org_id = $_SESSION['org_id'];

  if(empty($center_name)){
    $session->msg('d',"Center name khali nahi ho sakta!");
    redirect('master_center.php', false);
  }

  $sql = "UPDATE master_center SET
          center_name='{$center_name}',
          org_id='{$org_id}'
          WHERE center_id='{$id}' AND org_id='{$org_id}'";

  if($db->query($sql)){
    $session->msg('s',"Center Updated Successfully!");
  } else {
    $session->msg('d',"Update Failed!");
  }

  redirect('master_center.php');
}

/* ================= DELETE ================= */
if(isset($_GET['delete'])){
  $id = (int)$_GET['delete'];
  $org_id = $_SESSION['org_id'];

  $sql = "DELETE FROM master_center 
          WHERE center_id='{$id}' AND org_id='{$org_id}'";

  if($db->query($sql)){
    $session->msg("s","Center Deleted Successfully!");
  } else {
    $session->msg("d","Failed to delete center!");
  }
  redirect('master_center.php');
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

/* Form Buttons Container Alignment Fix */
.btn-group-flex {
    display: flex !important;
    align-items: center !important;
    justify-content: flex-end !important;
    gap: 8px !important;
    margin-top: 10px;
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

/* Action Cell Styling (Icon Buttons) */
.action-td {
    width: 90px;
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

<!-- ================= FORM ================= -->
<div class="col-md-4">
    <div class="panel panel-default">
        <div class="panel-heading">
            <i class="glyphicon glyphicon-map-marker" style="color: #2b8cff; margin-right: 5px;"></i>
            <strong><?php echo $edit_center ? "EDIT CENTER" : "ADD CENTER"; ?></strong>
        </div>

        <div class="panel-body">
            <form method="post">
                <?php if($edit_center): ?>
                    <input type="hidden" name="center_id" value="<?php echo $edit_center['center_id']; ?>">
                <?php endif; ?>

                <!-- CENTER NAME -->
                <div class="form-group" style="margin-bottom: 10px;">
                    <label>Center Name</label>
                    <input type="text"
                           name="center_name"
                           class="form-control"
                           placeholder="Enter center name..."
                           value="<?php echo $edit_center ? $edit_center['center_name'] : ''; ?>"
                           required autofocus>
                </div>

                <!-- BUTTONS WRAPPER -->
                <div class="btn-group-flex">
                    <?php if($edit_center): ?>
                        <a href="master_center.php" class="btn btn-clear btn-custom">
                            Cancel
                        </a>
                        <button type="submit" name="update_center" class="btn btn-primary-custom btn-custom">
                            Update Center
                        </button>
                    <?php else: ?>
                        <button type="submit" name="add_center" class="btn btn-success-custom btn-custom">
                            Save Center
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= LIST ================= -->
<div class="col-md-8">
    <div class="panel panel-default">
        <div class="panel-heading">
            <strong>CENTER LIST</strong>
        </div>

        <div class="panel-body">
            <div class="search-box">
                <i class="glyphicon glyphicon-search"></i>
                <input type="text" id="centerSearch" class="form-control" placeholder="Search center...">
            </div>

            <div class="table-scrollable">
                <table class="table table-bordered table-striped" id="centerTable">
                    <thead>
                        <tr>
                            <th width="50" class="text-center">#</th>
                            <th>Center Name</th>
                            <th class="text-center" width="90">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($centers)): ?>
                            <?php foreach($centers as $i => $center): ?>
                            <tr>
                                <td class="text-center"><?php echo $i+1; ?></td>
                                <td><strong><?php echo $center['center_name']; ?></strong></td>
                                <td class="action-td">
                                    <div class="action-cell">
                                        <a href="?edit=<?php echo $center['center_id']; ?>" class="btn btn-primary btn-xs equal-btn" title="Edit">
                                            <i class="glyphicon glyphicon-pencil"></i>
                                        </a>
                                        <button type="button" onclick="confirmDelete(<?php echo $center['center_id']; ?>)" class="btn btn-danger btn-xs equal-btn" title="Delete">
                                            <i class="glyphicon glyphicon-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center" style="color: #94a3b8; padding: 15px;">No centers found.</td>
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
// Search Filter
document.getElementById("centerSearch").addEventListener("keyup", function(){
    let filter = this.value.toLowerCase();
    document.querySelectorAll("#centerTable tbody tr").forEach(function(row){
        row.style.display = row.innerText.toLowerCase().includes(filter) ? "" : "none";
    });
});

// Delete Confirmation Popup
function confirmDelete(id) {
    Swal.fire({
        title: 'Are You Sure?',
        text: "This center will be deleted!",
        icon: 'warning',
        showCancelButton: true,
        confirmColor: '#ef4444',
        cancelColor: '#6b7280',
        confirmButtonText: 'Yes, Delete It!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "master_center.php?delete=" + id;
        }
    });
}
</script>

<?php include_once('layouts/footer.php'); ?>
