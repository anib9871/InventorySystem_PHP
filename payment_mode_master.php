<?php
$page_title = 'Payment Mode Master';
require_once('includes/load.php');
//page_require_level(1);

/* ================= ADD ================= */

if(isset($_POST['add_mode'])){

  $mode_name = remove_junk($db->escape($_POST['mode_name']));
  $created_by = $_SESSION['user_id'];

  if($mode_name != ""){

  $check = find_by_sql("
  SELECT id FROM payment_mode_master
  WHERE mode_name = '$mode_name'
  AND is_active = 1
");

if(count($check) > 0){
  $session->msg("d", "Payment Mode Already Exists!");
  redirect('payment_mode_master.php');
}

    $db->query("
      INSERT INTO payment_mode_master
      (mode_name, is_active, created_by, created_at)
      VALUES
      ('$mode_name', 1, '$created_by', NOW())
    ");

    $session->msg("s", "Payment Mode Added Successfully!");
  } else {
    $session->msg("d", "Mode Name Required!");
  }

  redirect('payment_mode_master.php');
}


/* ================= DELETE ================= */

if(isset($_GET['delete'])){
  $id = (int)$_GET['delete'];

  $db->query("
    UPDATE payment_mode_master
    SET is_active = 0
    WHERE id = '$id'
  ");

  $session->msg("s", "Payment Mode Deleted Successfully!");
  redirect('payment_mode_master.php');
}

/* ================= EDIT FETCH ================= */

$edit_mode = null;

if(isset($_GET['edit'])){
  $edit_id = (int)$_GET['edit'];

  $edit_data = find_by_sql("
    SELECT * FROM payment_mode_master
    WHERE id = '$edit_id'
    LIMIT 1
  ");

  if(count($edit_data) > 0){
    $edit_mode = $edit_data[0];
  }
}

/* ================= UPDATE ================= */

if(isset($_POST['update_mode'])){

  $id = (int)$_POST['mode_id'];
  $mode_name = remove_junk($db->escape($_POST['mode_name']));
  $updated_by = $_SESSION['user_id'];

  if($mode_name != ""){

    $db->query("
      UPDATE payment_mode_master
      SET mode_name = '$mode_name',
          updated_at = NOW(),
          updated_by = '$updated_by'
      WHERE id = '$id'
    ");

    $session->msg("s", "Payment Mode Updated Successfully!");
  } else {
    $session->msg("d", "Mode Name Required!");
  }

  redirect('payment_mode_master.php');
}

/* ================= FETCH ================= */

$modes = find_by_sql("
  SELECT * FROM payment_mode_master
  WHERE is_active = 1
  ORDER BY id DESC
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

/* Form Buttons Container Alignment */
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

.badge-active {
    background: #e0f2fe;
    color: #0369a1;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
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

<!-- LEFT : ADD/EDIT FORM -->
<div class="col-md-4">
<div class="panel panel-default">
<div class="panel-heading">
    <i class="glyphicon glyphicon-credit-card" style="color: #2b8cff; margin-right: 5px;"></i>
    <strong><?= $edit_mode ? "EDIT PAYMENT MODE" : "ADD PAYMENT MODE"; ?></strong>
</div>
<div class="panel-body">

<form method="post">
<input type="hidden" name="mode_id" value="<?= $edit_mode['id'] ?? ''; ?>">

<div class="form-group" style="margin-bottom: 10px;">
    <label>Mode Name</label>
    <input type="text"
           name="mode_name"
           class="form-control"
           placeholder="Enter mode name..."
           value="<?= $edit_mode['mode_name'] ?? ''; ?>"
           autofocus
           required>
</div>

<div class="btn-group-flex">
<?php if($edit_mode){ ?>
    <a href="payment_mode_master.php" class="btn btn-clear btn-custom">
        Cancel
    </a>
    <button name="update_mode" class="btn btn-primary-custom btn-custom">
        Update Mode
    </button>
<?php } else { ?>
    <button name="add_mode" class="btn btn-success-custom btn-custom">
        Save Mode
    </button>
<?php } ?>
</div>

</form>

</div>
</div>
</div>


<!-- RIGHT : LIST -->
<div class="col-md-8">
<div class="panel panel-default">
<div class="panel-heading">
    <strong>PAYMENT MODES LIST</strong>
</div>
<div class="panel-body">

<div class="search-box">
    <i class="glyphicon glyphicon-search"></i>
    <input type="text"
           id="searchMode"
           class="form-control"
           placeholder="Search payment mode...">
</div>

<div class="table-scrollable">
<table class="table table-bordered table-striped" id="modeTable">
<thead>
<tr>
<th width="50" class="text-center">#</th>
<th>Mode Name</th>
<th width="100" class="text-center">Status</th>
<th class="text-center" width="90">Action</th>
</tr>
</thead>

<tbody>

<?php if(!empty($modes)): ?>
    <?php foreach($modes as $i => $mode){ ?>
    <tr>
    <td class="text-center"><?= $i+1; ?></td>
    <td><strong><?= $mode['mode_name']; ?></strong></td>
    <td class="text-center"><span class="badge-active"><?= $mode['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
    <td class="action-td">
    <?php if($mode['is_active']){ ?>
        <div class="action-cell">
            <a href="?edit=<?= $mode['id']; ?>"
               class="btn btn-primary btn-xs equal-btn" title="Edit">
                <i class="glyphicon glyphicon-pencil"></i>
            </a>

            <button type="button"
                    onclick="confirmDelete(<?= $mode['id']; ?>)"
                    class="btn btn-danger btn-xs equal-btn" title="Delete">
                <i class="glyphicon glyphicon-trash"></i>
            </button>
        </div>
    <?php } ?>
    </td>
    </tr>
    <?php } ?>
<?php else: ?>
    <tr>
        <td colspan="4" class="text-center" style="color: #94a3b8; padding: 15px;">No payment modes found.</td>
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
document.getElementById("searchMode").addEventListener("keyup", function() {
    let value = this.value.toLowerCase();
    let rows = document.querySelectorAll("#modeTable tbody tr");

    rows.forEach(row => {
        row.style.display =
            row.innerText.toLowerCase().includes(value)
            ? ""
            : "none";
    });
});

// Delete Confirmation
function confirmDelete(id) {
    Swal.fire({
        title: 'Kya aap sure hain?',
        text: "Is payment mode ko delete kar diya jayega!",
        icon: 'warning',
        showCancelButton: true,
        confirmColor: '#ef4444',
        cancelColor: '#6b7280',
        confirmButtonText: 'Haan, Delete Karo!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "payment_mode_master.php?delete=" + id;
        }
    });
}
</script>
<?php include_once('layouts/footer.php'); ?>
