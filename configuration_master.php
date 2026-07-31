<?php
$page_title = 'Configuration Master';
require_once('includes/load.php');
//page_require_level(2);

// FETCH ORGANIZATION LIST
$org_id = (int)$_SESSION['org_id'];

$org_data = find_by_sql("
SELECT id, org_name
FROM organization_master
WHERE id = '{$org_id}'
LIMIT 1
");

$current_org = $org_data ? $org_data[0] : [];
$print_types = find_all('print_type_master');

// -------------------- SAVE NEW --------------------
if(isset($_POST['save'])){
    $org_id = (int)$_POST['org_id'];
    $batch_required = $_POST['batch_required'];
    $gst_registered = $_POST['gst_registered'];
    $expiry_required = $_POST['expiry_required'];
    $print_type_id = (int)$_POST['print_type_id'];

    $sql = "INSERT INTO configuration_master
    (org_id, batch_required, gst_registered, expiry_required, print_type_id)
    VALUES
    ('{$org_id}','{$batch_required}','{$gst_registered}','{$expiry_required}','{$print_type_id}')";

    if($db->query($sql)){
        $session->msg("s","Configuration saved successfully!");
    }else{
        $session->msg("d","Failed to save configuration!");
    }

    redirect('configuration_master.php', false);
}

// -------------------- DELETE --------------------
if(isset($_GET['delete_id'])){
    $id = (int)$_GET['delete_id'];
    if($db->query("DELETE FROM configuration_master WHERE id = '{$id}'")){
        $session->msg("s","Configuration deleted successfully!");
    }else{
        $session->msg("d","Failed to delete configuration!");
    }
    redirect('configuration_master.php', false);
}

// -------------------- FETCH DATA FOR EDIT --------------------
$edit_data = null;
if(isset($_GET['edit_id'])){
    $id = (int)$_GET['edit_id'];
    $res = find_by_sql("SELECT * FROM configuration_master WHERE id='{$id}' LIMIT 1");
    if($res){
        $edit_data = $res[0];
    }
}

// -------------------- UPDATE --------------------
if(isset($_POST['update'])){
    $id = (int)$_POST['config_id'];
    $org_id = (int)$_POST['org_id'];
    $batch_required = $_POST['batch_required'];
    $gst_registered = $_POST['gst_registered'];
    $expiry_required = $_POST['expiry_required'];
    $print_type_id = (int)$_POST['print_type_id'];

    $sql = "UPDATE configuration_master 
            SET org_id='{$org_id}',
                batch_required='{$batch_required}',
                gst_registered='{$gst_registered}',
                expiry_required='{$expiry_required}',
                print_type_id='{$print_type_id}'
            WHERE id='{$id}'";

    if($db->query($sql)){
        $session->msg("s","Configuration updated successfully!");
    }else{
        $session->msg("d","Update failed!");
    }

    redirect('configuration_master.php', false);
}

// -------------------- LIST DATA --------------------
$config_list = find_by_sql("
SELECT c.*, o.org_name, p.print_name
FROM configuration_master c
LEFT JOIN organization_master o ON o.id = c.org_id
LEFT JOIN print_type_master p ON p.id = c.print_type_id
ORDER BY c.id DESC
");
?>

<?php include_once('layouts/header.php'); ?>

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

.form-control[readonly] {
    background-color: #f1f5f9;
    color: #64748b;
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
    padding: 8px 10px;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: #18233b;
}

.table tbody td {
    padding: 8px 10px;
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

.badge-yes {
    background: #e0f2fe;
    color: #0369a1;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
}

.badge-no {
    background: #fef2f2;
    color: #991b1b;
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

<!-- ADD / EDIT FORM -->
<div class="col-md-5">
    <div class="panel panel-default">
        <div class="panel-heading">
            <i class="glyphicon glyphicon-cog" style="color: #2b8cff; margin-right: 5px;"></i>
            <strong><?php echo $edit_data ? "EDIT CONFIGURATION" : "ADD CONFIGURATION"; ?></strong>
        </div>

        <div class="panel-body">
            <form method="post" id="configForm">

                <?php if($edit_data): ?>
                    <input type="hidden" name="config_id" value="<?php echo $edit_data['id']; ?>">
                <?php endif; ?>

                <div class="form-group form-group-compact">
                    <label>Organization</label>
                    <input type="hidden" name="org_id" value="<?php echo $current_org['id']; ?>">
                    <input type="text" class="form-control" value="<?php echo $current_org['org_name']; ?>" readonly>
                </div>

                <div class="form-group form-group-compact">
                    <label>Batch Required</label>
                    <select name="batch_required" class="form-control" required>
                        <option value="Yes" <?php if($edit_data && $edit_data['batch_required']=="Yes") echo "selected"; ?>>Yes</option>
                        <option value="No" <?php if($edit_data && $edit_data['batch_required']=="No") echo "selected"; ?>>No</option>
                    </select>
                </div>

                <div class="form-group form-group-compact">
                    <label>GST Registered</label>
                    <select name="gst_registered" class="form-control" required>
                        <option value="Yes" <?php if($edit_data && $edit_data['gst_registered']=="Yes") echo "selected"; ?>>Yes</option>
                        <option value="No" <?php if($edit_data && $edit_data['gst_registered']=="No") echo "selected"; ?>>No</option>
                    </select>
                </div>

                <div class="form-group form-group-compact">
                    <label>Expiry Required</label>
                    <select name="expiry_required" class="form-control" required>
                        <option value="Yes" <?php if($edit_data && $edit_data['expiry_required']=="Yes") echo "selected"; ?>>Yes</option>
                        <option value="No" <?php if($edit_data && $edit_data['expiry_required']=="No") echo "selected"; ?>>No</option>
                    </select>
                </div>

                <div class="form-group form-group-compact">
                    <label>Invoice Print Type</label>
                    <select name="print_type_id" class="form-control">
                        <?php foreach($print_types as $pt){ ?>
                            <option value="<?php echo $pt['id']; ?>" <?php if($edit_data && $edit_data['print_type_id']==$pt['id']) echo "selected"; ?>>
                                <?php echo $pt['print_name']; ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="btn-group-flex">
                    <?php if($edit_data): ?>
                        <a href="configuration_master.php" class="btn btn-clear btn-custom">
                            Cancel
                        </a>
                        <button type="submit" name="update" class="btn btn-primary-custom btn-custom">
                            Update Config
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-clear btn-custom" onclick="document.getElementById('configForm').reset();">
                            Clear
                        </button>
                        <button type="submit" name="save" class="btn btn-success-custom btn-custom">
                            Save Config
                        </button>
                    <?php endif; ?>
                </div>

            </form>
        </div>
    </div>
</div>

<!-- LIST + LIVE SEARCH -->
<div class="col-md-7">
    <div class="panel panel-default">
        <div class="panel-heading">
            <strong>CONFIGURATION LIST</strong>
        </div>

        <div class="panel-body">

            <div class="search-box">
                <i class="glyphicon glyphicon-search"></i>
                <input type="text" id="liveSearch" class="form-control" placeholder="Search by Organization / GST / Batch...">
            </div>

            <div class="table-scrollable">
                <table class="table table-bordered table-striped" id="configTable">
                    <thead>
                        <tr>
                            <th width="40" class="text-center">#</th>
                            <th>Organization</th>
                            <th class="text-center">Batch</th>
                            <th class="text-center">GST</th>
                            <th class="text-center">Expiry</th>
                            <th class="text-center">Print Type</th>
                            <th class="text-center" width="80">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($config_list)): ?>
                            <?php foreach($config_list as $i => $conf): ?>
                            <tr>
                                <td class="text-center"><?php echo $i + 1; ?></td>
                                <td><strong><?php echo $conf['org_name']; ?></strong></td>
                                <td class="text-center">
                                    <span class="<?php echo ($conf['batch_required']=='Yes') ? 'badge-yes' : 'badge-no'; ?>">
                                        <?php echo $conf['batch_required']; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="<?php echo ($conf['gst_registered']=='Yes') ? 'badge-yes' : 'badge-no'; ?>">
                                        <?php echo $conf['gst_registered']; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="<?php echo ($conf['expiry_required']=='Yes') ? 'badge-yes' : 'badge-no'; ?>">
                                        <?php echo $conf['expiry_required']; ?>
                                    </span>
                                </td>
                                <td class="text-center"><?php echo $conf['print_name']; ?></td>
                                <td class="action-td">
                                    <div class="action-cell">
                                        <a href="configuration_master.php?edit_id=<?php echo $conf['id']; ?>" class="btn btn-primary btn-xs equal-btn" title="Edit">
                                            <i class="glyphicon glyphicon-pencil"></i>
                                        </a>
                                        <button type="button" onclick="confirmDelete(<?php echo $conf['id']; ?>)" class="btn btn-danger btn-xs equal-btn" title="Delete">
                                            <i class="glyphicon glyphicon-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center" style="color: #94a3b8; padding: 15px;">No configurations found.</td>
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
/* Live JS Search */
document.getElementById("liveSearch").addEventListener("keyup", function(){
    let val = this.value.toLowerCase();
    document.querySelectorAll("#configTable tbody tr").forEach(function(row){
        row.style.display = row.innerText.toLowerCase().includes(val) ? "" : "none";
    });
});

/* SweetAlert Delete Confirmation */
function confirmDelete(id) {
    Swal.fire({
        title: 'Are You Sure?',
        text: "This configuration will be deleted!",
        icon: 'warning',
        showCancelButton: true,
        confirmColor: '#ef4444',
        cancelColor: '#6b7280',
        confirmButtonText: 'Yes, Delete It!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "configuration_master.php?delete_id=" + id;
        }
    });
}
</script>

<?php include_once('layouts/footer.php'); ?>
