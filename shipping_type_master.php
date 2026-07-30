<?php
$page_title = 'Shipping Type Master';
require_once('includes/load.php');
//page_require_level(1);

$gst_list = find_all('gst_master');

/* ================= DELETE ================= */
if(isset($_GET['delete'])){
  $id = (int)$_GET['delete'];

  if($db->query("DELETE FROM shipping_type_master WHERE id = '$id'")){
    $session->msg("s","Shipping type deleted successfully!");
  } else {
    $session->msg("d","Failed to delete shipping type!");
  }
  redirect('shipping_type_master.php');
}

/* ================= ADD ================= */
if(isset($_POST['add_type'])){

  $type_name = remove_junk($db->escape($_POST['type_name']));
  $default_gst_id = (int)$_POST['default_gst_id'];
  $is_gst_applicable = (int)$_POST['is_gst_applicable'];
  $created_by = $_SESSION['user_id'];
  $sac_code = remove_junk($db->escape($_POST['sac_code'] ?? ''));

  if(empty($type_name)){
    $session->msg("d","Type Name field is required!");
    redirect('shipping_type_master.php');
  }

  $db->query("INSERT INTO shipping_type_master
            (type_name, sac_code, default_gst_id, is_gst_applicable, created_by, created_at)
              VALUES
              ('$type_name', '$sac_code', '$default_gst_id', '$is_gst_applicable', '$created_by', NOW())");

  $session->msg("s","Shipping type added successfully!");
  redirect('shipping_type_master.php');
}

/* ================= UPDATE ================= */
if(isset($_POST['update_type'])){

  $id = (int)$_POST['id'];
  $type_name = remove_junk($db->escape($_POST['type_name']));
  $default_gst_id = (int)$_POST['default_gst_id'];
  $is_gst_applicable = (int)$_POST['is_gst_applicable'];
  $sac_code = remove_junk($db->escape($_POST['sac_code'] ?? ''));

  if(empty($type_name)){
    $session->msg("d","Type Name field is required!");
    redirect('shipping_type_master.php');
  }

  $db->query("UPDATE shipping_type_master SET
            type_name='$type_name',
            sac_code='$sac_code',
            default_gst_id='$default_gst_id',
            is_gst_applicable='$is_gst_applicable',
            updated_at=NOW()
              WHERE id='$id'");

  $session->msg("s","Shipping type updated successfully!");
  redirect('shipping_type_master.php');
}

/* ================= EDIT FETCH ================= */
$edit = null;
if(isset($_GET['edit'])){
  $id = (int)$_GET['edit'];
  $edit = find_by_id('shipping_type_master',$id);
}

/* ================= FETCH LIST ================= */
$types = find_by_sql("
  SELECT stm.*, gm.gst_percent
  FROM shipping_type_master stm
  LEFT JOIN gst_master gm ON gm.id = stm.default_gst_id
  ORDER BY stm.id DESC
");

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

.badge-yes {
    background: #e0f2fe;
    color: #0369a1;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
}

.badge-no {
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
            <i class="glyphicon glyphicon-send" style="color: #2b8cff; margin-right: 5px;"></i>
            <strong><?= $edit ? 'EDIT SHIPPING TYPE' : 'ADD SHIPPING TYPE'; ?></strong>
        </div>
        <div class="panel-body">

            <form method="post" id="shippingForm">

                <?php if($edit){ ?>
                    <input type="hidden" name="id" value="<?= $edit['id']; ?>">
                <?php } ?>

                <div class="form-group form-group-compact">
                    <label>Type Name</label>
                    <input type="text"
                           id="typeName"
                           name="type_name"
                           value="<?= $edit ? $edit['type_name'] : ''; ?>"
                           class="form-control"
                           placeholder="e.g. Courier, Local Delivery"
                           required autofocus>
                </div>

                <div class="form-group form-group-compact">
                    <label>SAC Code (Optional)</label>
                    <input type="text"
                           name="sac_code"
                           value="<?= $edit ? $edit['sac_code'] : ''; ?>"
                           class="form-control"
                           placeholder="Enter SAC Code (if applicable)">
                </div>

                <div class="form-group form-group-compact">
                    <label>GST Applicable</label>
                    <select name="is_gst_applicable" class="form-control">
                        <option value="1" <?= ($edit && $edit['is_gst_applicable']==1)?'selected':''; ?>>
                            Yes
                        </option>
                        <option value="0" <?= ($edit && $edit['is_gst_applicable']==0)?'selected':''; ?>>
                            No
                        </option>
                    </select>
                </div>

                <div class="form-group form-group-compact">
                    <label>Default GST</label>
                    <select name="default_gst_id" class="form-control">
                        <option value="0">-- None --</option>
                        <?php foreach($gst_list as $gst){ ?>
                            <option value="<?= $gst['id']; ?>"
                                <?= ($edit && $edit['default_gst_id']==$gst['id'])?'selected':''; ?>>
                                <?= $gst['gst_name']; ?> (<?= $gst['gst_percent']; ?>%)
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="btn-group-flex">
                    <?php if($edit){ ?>
                        <a href="shipping_type_master.php" class="btn btn-clear btn-custom">
                            Cancel
                        </a>
                        <button name="update_type" class="btn btn-primary-custom btn-custom">
                            Update Type
                        </button>
                    <?php } else { ?>
                        <button type="button" class="btn btn-clear btn-custom" onclick="clearForm()">
                            Clear
                        </button>
                        <button name="add_type" class="btn btn-success-custom btn-custom">
                            Save Type
                        </button>
                    <?php } ?>
                </div>

            </form>

        </div>
    </div>
</div>

<!-- RIGHT LIST -->
<div class="col-md-8">
    <div class="panel panel-default">
        <div class="panel-heading">
            <strong>SHIPPING TYPES LIST</strong>
        </div>
        <div class="panel-body">

            <div class="search-box">
                <i class="glyphicon glyphicon-search"></i>
                <input type="text"
                       id="searchShipping"
                       class="form-control"
                       placeholder="Search shipping type...">
            </div>

            <div class="table-scrollable">
                <table class="table table-bordered table-striped" id="shippingTable">
                    <thead>
                        <tr>
                            <th width="40" class="text-center">#</th>
                            <th>Type Name</th>
                            <th class="text-center">GST Applicable</th>
                            <th class="text-center">Default GST</th>
                            <th class="text-center">SAC</th>
                            <th class="text-center" width="80">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($types)): ?>
                            <?php foreach($types as $i => $type){ ?>
                            <tr>
                                <td class="text-center"><?= $i+1; ?></td>
                                <td><strong><?= $type['type_name']; ?></strong></td>
                                <td class="text-center">
                                    <span class="<?= $type['is_gst_applicable'] ? 'badge-yes' : 'badge-no'; ?>">
                                        <?= $type['is_gst_applicable'] ? 'Yes' : 'No'; ?>
                                    </span>
                                </td>
                                <td class="text-center"><?= $type['gst_percent'] ? number_format($type['gst_percent'], 2).'%' : '<span style="color:#94a3b8;">N/A</span>'; ?></td>
                                <td class="text-center"><?= $type['sac_code'] ?: '<span style="color:#94a3b8;">N/A</span>'; ?></td>

                                <td class="action-td">
                                    <div class="action-cell">
                                        <a href="shipping_type_master.php?edit=<?= $type['id']; ?>"
                                           class="btn btn-primary btn-xs equal-btn" title="Edit">
                                            <i class="glyphicon glyphicon-pencil"></i>
                                        </a>

                                        <button type="button"
                                                onclick="confirmDelete(<?= $type['id']; ?>)"
                                                class="btn btn-danger btn-xs equal-btn" title="Delete">
                                            <i class="glyphicon glyphicon-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php } ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center" style="color: #94a3b8; padding: 15px;">No shipping types found.</td>
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
function clearForm(){
    document.getElementById("shippingForm").reset();
    document.getElementById("typeName").focus();
}

document.getElementById("searchShipping").addEventListener("keyup", function() {
    let value = this.value.toLowerCase();
    document.querySelectorAll("#shippingTable tbody tr").forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(value) ? "" : "none";
    });
});

document.addEventListener("DOMContentLoaded", function () {
    const input = document.getElementById("typeName");
    if(input){
        input.focus();
        input.select();
    }
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
