<?php
$page_title = 'Group Management';
require_once('includes/load.php');

/* =========================================================
   SAFE AUTO-FILL: DEFAULT ROLES (ADMIN & USER)
========================================================= */
$default_groups = [
    ['name' => 'Admin', 'level' => 1],
    ['name' => 'User',  'level' => 2]
];

foreach ($default_groups as $dg) {
    $check_name = $db->escape($dg['name']);
    $level = (int)$dg['level'];
    
    // Check if group level or name exists safely before inserting
    $exists = find_by_sql("SELECT id FROM user_groups WHERE group_name='{$check_name}' OR group_level={$level} LIMIT 1");
    if(!$exists){
        $db->query("INSERT INTO user_groups (group_name, group_level, group_status) VALUES ('{$check_name}', {$level}, '1')");
    }
}
/* ========================================================= */

$all_groups = find_all('user_groups');
$edit_group = null;

/* =========================
   DELETE GROUP
========================= */
if(isset($_GET['delete'])){

    $delete_id = delete_by_id('user_groups',(int)$_GET['delete']);

    if($delete_id){
        $session->msg("s","Group deleted successfully.");
    } else {
        $session->msg("d","Failed to delete group.");
    }

    redirect('group.php', false);
}

/* =========================
   EDIT FETCH
========================= */
if(isset($_GET['edit'])){

    $edit_group = find_by_id('user_groups',(int)$_GET['edit']);
}

/* =========================
   ADD / UPDATE GROUP
========================= */
if(isset($_POST['save_group'])){

    $req_fields = array('group-name','group-level');
    validate_fields($req_fields);

    if(empty($errors)){

        $name   = remove_junk($db->escape($_POST['group-name']));
        $level  = remove_junk($db->escape($_POST['group-level']));
        $status = remove_junk($db->escape($_POST['status']));

        /* ======================
           UPDATE
        ====================== */
        if(isset($_POST['group_id']) && !empty($_POST['group_id'])){

            $group_id = (int)$_POST['group_id'];

            $query  = "UPDATE user_groups SET ";
            $query .= "group_name='{$name}', ";
            $query .= "group_level='{$level}', ";
            $query .= "group_status='{$status}' ";
            $query .= "WHERE id='{$group_id}'";

            if($db->query($query)){
                $session->msg('s',"Group updated successfully.");
            } else {
                $session->msg('d',"Failed to update group.");
            }

        } else {

            /* ======================
               INSERT
            ====================== */
            $query  = "INSERT INTO user_groups (";
            $query .= "group_name, group_level, group_status";
            $query .= ") VALUES (";
            $query .= "'{$name}','{$level}','{$status}'";
            $query .= ")";

            if($db->query($query)){
                $session->msg('s',"Group added successfully.");
            } else {
                $session->msg('d',"Failed to add group.");
            }
        }

    } else {
        $session->msg("d", $errors);
    }

    redirect('group.php', false);
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

/* Form Buttons Container */
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

/* Table Scroll Properties */
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

.badge-deactive {
    background: #fef2f2;
    color: #991b1b;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
}

/* Action Buttons Flexbox Layout */
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

    <!-- LEFT SIDE FORM -->
    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-heading">
                <i class="glyphicon glyphicon-th-list" style="color: #2b8cff; margin-right: 5px;"></i>
                <strong><?php echo $edit_group ? "EDIT GROUP" : "ADD GROUP"; ?></strong>
            </div>

            <div class="panel-body">
                <form method="post" action="group.php">

                    <input type="hidden" name="group_id" value="<?php echo $edit_group ? (int)$edit_group['id'] : ''; ?>">

                    <div class="form-group form-group-compact">
                        <label>Group Name</label>
                        <input type="text"
                               class="form-control"
                               name="group-name"
                               placeholder="Enter group name..."
                               value="<?php echo $edit_group ? remove_junk($edit_group['group_name']) : ''; ?>"
                               required autofocus>
                    </div>

                    <div class="form-group form-group-compact">
                        <label>Group Level</label>
                        <input type="number"
                               class="form-control"
                               name="group-level"
                               placeholder="Enter level (e.g. 1)"
                               value="<?php echo $edit_group ? (int)$edit_group['group_level'] : ''; ?>"
                               required>
                    </div>

                    <div class="form-group form-group-compact">
                        <label>Status</label>
                        <select class="form-control" name="status">
                            <option value="1" <?php if($edit_group && $edit_group['group_status']=='1') echo 'selected'; ?>>
                                Active
                            </option>
                            <option value="0" <?php if($edit_group && $edit_group['group_status']=='0') echo 'selected'; ?>>
                                Deactive
                            </option>
                        </select>
                    </div>

                    <!-- BUTTONS WRAPPER -->
                    <div class="btn-group-flex">
                        <?php if($edit_group): ?>
                            <a href="group.php" class="btn btn-clear btn-custom">
                                Cancel
                            </a>
                            <button type="submit" name="save_group" class="btn btn-primary-custom btn-custom">
                                Update Group
                            </button>
                        <?php else: ?>
                            <button type="submit" name="save_group" class="btn btn-success-custom btn-custom">
                                Save Group
                            </button>
                        <?php endif; ?>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <!-- RIGHT SIDE TABLE -->
    <div class="col-md-8">
        <div class="panel panel-default">
            <div class="panel-heading">
                <strong>GROUPS LIST</strong>
            </div>

            <div class="panel-body">
                <div class="search-box">
                    <i class="glyphicon glyphicon-search"></i>
                    <input type="text" id="groupSearch" class="form-control" placeholder="Search group...">
                </div>

                <div class="table-scrollable">
                    <table class="table table-bordered table-striped" id="groupTable">
                        <thead>
                            <tr>
                                <th width="50" class="text-center">#</th>
                                <th>Group Name</th>
                                <th width="100" class="text-center">Level</th>
                                <th width="100" class="text-center">Status</th>
                                <th class="text-center" width="90">Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if(!empty($all_groups)): ?>
                                <?php foreach($all_groups as $i => $group): ?>
                                    <tr>
                                        <td class="text-center"><?php echo $i + 1; ?></td>

                                        <td>
                                            <strong><?php echo remove_junk(ucwords($group['group_name'])); ?></strong>
                                        </td>

                                        <td class="text-center">
                                            <?php echo (int)$group['group_level']; ?>
                                        </td>

                                        <td class="text-center">
                                            <?php if($group['group_status']=='1'): ?>
                                                <span class="badge-active">Active</span>
                                            <?php else: ?>
                                                <span class="badge-deactive">Deactive</span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="action-td">
                                            <div class="action-cell">
                                                <a href="group.php?edit=<?php echo (int)$group['id']; ?>"
                                                   class="btn btn-primary btn-xs equal-btn" title="Edit">
                                                    <i class="glyphicon glyphicon-pencil"></i>
                                                </a>

                                                <button type="button"
                                                        onclick="confirmDelete(<?php echo (int)$group['id']; ?>)"
                                                        class="btn btn-danger btn-xs equal-btn" title="Delete">
                                                    <i class="glyphicon glyphicon-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center" style="color: #94a3b8; padding: 15px;">No groups found.</td>
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
document.getElementById("groupSearch").addEventListener("keyup", function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll("#groupTable tbody tr");

    rows.forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(filter) ? "" : "none";
    });
});

// Delete Confirmation Modal
function confirmDelete(id) {
    Swal.fire({
        title: 'Kya aap sure hain?',
        text: "Is group ko delete kar diya jayega!",
        icon: 'warning',
        showCancelButton: true,
        confirmColor: '#ef4444',
        cancelColor: '#6b7280',
        confirmButtonText: 'Haan, Delete Karo!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "group.php?delete=" + id;
        }
    });
}
</script>

<?php include_once('layouts/footer.php'); ?>
