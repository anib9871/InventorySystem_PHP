<?php
$page_title = 'GST Master';
require_once('includes/load.php');
//page_require_level(2);

if(isset($gst_enabled) && $gst_enabled == "No"){
   $session->msg("d","GST disabled from configuration");
   redirect('home.php');
}

/* ---------- FETCH ALL ---------- */
$all_gst = find_by_sql("SELECT * FROM gst_master ORDER BY gst_percent ASC");

/* ---------- ADD GST ---------- */
if(isset($_POST['add_gst'])){

  $name = remove_junk($db->escape($_POST['gst_name']));
  $percent = (float)$_POST['gst_percent'];

  if($name == '' || $_POST['gst_percent'] == ''){
     $session->msg("d","Fields cannot be empty!");
     redirect('gst_master.php',false);
  }

  /* Prevent duplicate */
  $chk = find_by_sql("SELECT id FROM gst_master WHERE gst_name='$name'");
  if(!empty($chk)){
     $session->msg("d","GST already exists!");
     redirect('gst_master.php',false);
  }

  $query = "INSERT INTO gst_master(gst_name,gst_percent)
            VALUES('$name','$percent')";

  if($db->query($query)){
    $session->msg("s","GST added successfully!");
  } else {
    $session->msg("d","Failed to add GST!");
  }

  redirect('gst_master.php',false);
}

/* ---------- DELETE GST ---------- */
if(isset($_GET['del'])){
   $id = (int)$_GET['del'];
   if($db->query("DELETE FROM gst_master WHERE id='{$id}'")){
       $session->msg("s","GST deleted successfully!");
   } else {
       $session->msg("d","Failed to delete GST!");
   }
   redirect('gst_master.php',false);
}

/* ---------- LOAD FOR EDIT ---------- */
$edit_data = null;
if(isset($_GET['edit'])){
  $id = (int)$_GET['edit'];
  $res = find_by_sql("SELECT * FROM gst_master WHERE id='{$id}' LIMIT 1");
  if($res){
    $edit_data = $res[0];
  }
}

/* ---------- UPDATE GST ---------- */
if(isset($_POST['update_gst'])){

  $id = (int)$_POST['id'];
  $name = remove_junk($db->escape($_POST['gst_name']));
  $percent = (float)$_POST['gst_percent'];

  if($name == '' || $_POST['gst_percent'] == ''){
     $session->msg("d","Fields cannot be empty!");
     redirect('gst_master.php',false);
  }

  $query = "UPDATE gst_master 
            SET gst_name='$name', gst_percent='$percent'
            WHERE id='$id'";

  if($db->query($query)){
    $session->msg("s","GST updated successfully!");
  } else {
    $session->msg("d","Update failed!");
  }

  redirect('gst_master.php',false);
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

.badge-percent {
    background: #e0f2fe;
    color: #0369a1;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
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

<!-- ADD / EDIT FORM -->
<div class="col-md-4">
    <div class="panel panel-default">
        <div class="panel-heading">
            <i class="glyphicon glyphicon-list-alt" style="color: #2b8cff; margin-right: 5px;"></i>
            <strong><?php echo $edit_data ? 'EDIT GST' : 'ADD GST'; ?></strong>
        </div>

        <div class="panel-body">
            <form method="post" id="gstForm">

                <?php if($edit_data): ?>
                    <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
                <?php endif; ?>

                <div class="form-group form-group-compact">
                    <label>GST Name</label>
                    <input type="text" id="gst_name" name="gst_name" class="form-control"
                           placeholder="e.g. GST-5, IGST-12"
                           value="<?php echo $edit_data['gst_name'] ?? ''; ?>" required autofocus>
                </div>

                <div class="form-group form-group-compact">
                    <label>GST Percentage (%)</label>
                    <input type="number" step="0.01" name="gst_percent" class="form-control"
                           placeholder="e.g. 18.00"
                           value="<?php echo $edit_data['gst_percent'] ?? ''; ?>" required>
                </div>

                <div class="btn-group-flex">
                    <?php if($edit_data): ?>
                        <a href="gst_master.php" class="btn btn-clear btn-custom">
                            Cancel
                        </a>
                        <button name="update_gst" class="btn btn-primary-custom btn-custom">
                            Update GST
                        </button>
                    <?php else: ?>
                        <button type="button" onclick="clearForm()" class="btn btn-clear btn-custom">
                            Clear
                        </button>
                        <button name="add_gst" class="btn btn-success-custom btn-custom">
                            Save GST
                        </button>
                    <?php endif; ?>
                </div>

            </form>
        </div>
    </div>
</div>

<!-- GST LIST -->
<div class="col-md-8">
    <div class="panel panel-default">
        <div class="panel-heading">
            <strong>GST LIST</strong>
        </div>

        <div class="panel-body">

            <div class="search-box">
                <i class="glyphicon glyphicon-search"></i>
                <input type="text" id="search" class="form-control" placeholder="Search GST...">
            </div>

            <div class="table-scrollable">
                <table class="table table-bordered table-striped" id="gstTable">
                    <thead>
                        <tr>
                            <th width="50" class="text-center">#</th>
                            <th>GST Name</th>
                            <th class="text-center" width="120">GST %</th>
                            <th class="text-center" width="80">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($all_gst)): ?>
                            <?php foreach($all_gst as $i=>$g): ?>
                            <tr>
                                <td class="text-center"><?php echo $i+1; ?></td>
                                <td><strong><?php echo $g['gst_name']; ?></strong></td>
                                <td class="text-center"><span class="badge-percent"><?php echo number_format($g['gst_percent'], 2); ?>%</span></td>
                                <td class="action-td">
                                    <div class="action-cell">
                                        <a href="gst_master.php?edit=<?php echo $g['id']; ?>" class="btn btn-primary btn-xs equal-btn" title="Edit">
                                            <i class="glyphicon glyphicon-pencil"></i>
                                        </a>
                                        <button type="button" onclick="confirmDelete(<?php echo $g['id']; ?>)" class="btn btn-danger btn-xs equal-btn" title="Delete">
                                            <i class="glyphicon glyphicon-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center" style="color: #94a3b8; padding: 15px;">No GST records found.</td>
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
    let input = document.getElementById("gst_name");
    if(input) input.focus();
};

function clearForm(){
    document.getElementById("gstForm").reset();
    document.getElementById("gst_name").focus();
}

/* Search filter */
document.getElementById("search").addEventListener("keyup", function(){
    let value = this.value.toLowerCase();
    document.querySelectorAll("#gstTable tbody tr").forEach(function(row){
        row.style.display = row.textContent.toLowerCase().includes(value) ? "" : "none";
    });
});

/* Delete confirmation */
function confirmDelete(id) {
    Swal.fire({
        title: 'Kya aap sure hain?',
        text: "Is GST record ko delete kar diya jayega!",
        icon: 'warning',
        showCancelButton: true,
        confirmColor: '#ef4444',
        cancelColor: '#6b7280',
        confirmButtonText: 'Haan, Delete Karo!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "gst_master.php?del=" + id;
        }
    });
}
</script>

<?php include_once('layouts/footer.php'); ?>
