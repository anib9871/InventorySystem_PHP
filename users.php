<?php
  $page_title = 'All User';
  require_once('includes/load.php');

  // Check What level user has permission to view this page
  // page_require_level(1);
  
  $all_users = find_all_user();
  $edit_user = null;

  if(isset($_GET['edit'])){
    $edit_user = find_by_id('users',(int)$_GET['edit']);
  }
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
    font-size: 12px;
    border-radius: 5px;
    border: 1px solid #cbd5e1;
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

/* Action Buttons Alignment */
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

<!-- LEFT SIDE : CREATE USER FORM -->
<div class="col-md-4">
    <?php include_once('add_user.php'); ?>
</div>

<!-- RIGHT SIDE : USERS LIST -->
<div class="col-md-8">
    <div class="panel panel-default">
        <div class="panel-heading">
            <i class="glyphicon glyphicon-user" style="color: #2b8cff; margin-right: 5px;"></i>
            <strong>USERS LIST</strong>
        </div>

        <div class="panel-body">
            <!-- SEARCH BAR -->
            <div class="search-box">
                <i class="glyphicon glyphicon-search"></i>
                <input type="text" id="userSearch" class="form-control" placeholder="Search user...">
            </div>

            <div class="table-scrollable">
                <table class="table table-bordered table-striped" id="usersTable">
                    <thead>
                        <tr>
                            <th class="text-center" width="40">#</th>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Center</th>
                            <th class="text-center">User Role</th>
                            <th class="text-center">Status</th>
                            <th>Last Login</th>
                            <th class="text-center" width="80">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($all_users)): ?>
                            <?php foreach($all_users as $i => $a_user): ?>
                                <tr>
                                    <td class="text-center"><?php echo $i + 1;?></td>
                                    <td><strong><?php echo remove_junk(ucwords($a_user['name']))?></strong></td>
                                    <td><?php echo remove_junk($a_user['username'])?></td>
                                    <td><?php echo remove_junk($a_user['center_name'] ?? ''); ?></td>
                                    <td class="text-center"><?php echo remove_junk(ucwords($a_user['group_name']))?></td>
                                    <td class="text-center">
                                        <?php if($a_user['status'] === '1'): ?>
                                            <span class="badge-active">Active</span>
                                        <?php else: ?>
                                            <span class="badge-deactive">Deactive</span>
                                        <?php endif;?>
                                    </td>
                                    <td><small><?php echo read_date($a_user['last_login'])?></small></td>
                                    <td class="action-td">
                                        <div class="action-cell">
                                            <a href="users.php?edit=<?php echo (int)$a_user['id'];?>" class="btn btn-primary btn-xs equal-btn" title="Edit">
                                                <i class="glyphicon glyphicon-pencil"></i>
                                            </a>
                                            <button type="button" onclick="confirmDelete(<?php echo (int)$a_user['id'];?>)" class="btn btn-danger btn-xs equal-btn" title="Remove">
                                                <i class="glyphicon glyphicon-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach;?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center" style="color: #94a3b8; padding: 15px;">No users found.</td>
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
document.getElementById("userSearch").addEventListener("keyup", function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll("#usersTable tbody tr");

    rows.forEach(function(row){
        let text = row.textContent.toLowerCase();
        row.style.display = (text.indexOf(filter) > -1) ? "" : "none";
    });
});

// Delete Confirmation
function confirmDelete(id) {
    Swal.fire({
        title: 'Are You Sure?',
        text: "This User Will Be Removed!",
        icon: 'warning',
        showCancelButton: true,
        confirmColor: '#ef4444',
        cancelColor: '#6b7280',
        confirmButtonText: 'Yes, Delete It!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "delete_user.php?id=" + id;
        }
    });
}
</script>

<?php include_once('layouts/footer.php'); ?>
