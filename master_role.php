<?php
$page_title = 'Role Master';
require_once('includes/load.php');

/* ================= FETCH ROLES (DESC) ================= */
$roles = find_by_sql("SELECT * FROM master_inventory.master_role ORDER BY role_id DESC");

/* ================= ADD ROLE ================= */
if(isset($_POST['add_role'])){

  $role_name = remove_junk($db->escape($_POST['role_name']));

  if($role_name == ""){
    $session->msg('d',"Role name required");
    redirect('master_role.php');
  }

  $sql = "INSERT INTO master_inventory.master_role (role_name)
          VALUES('{$role_name}')";

  if($db->query($sql)){
    $session->msg('s',"Role Added Successfully");
  } else {
    $session->msg('d',"Failed to add role");
  }

  redirect('master_role.php');
}

/* ================= EDIT FETCH ================= */
$edit_role = null;

if(isset($_GET['edit'])){
  $id = (int)$_GET['edit'];

  $res = find_by_sql("SELECT * FROM master_inventory.master_role WHERE role_id='{$id}' LIMIT 1");

  if($res){
    $edit_role = $res[0];
  }
}

/* ================= UPDATE ================= */
if(isset($_POST['update_role'])){

  $id = (int)$_POST['role_id'];
  $role_name = remove_junk($db->escape($_POST['role_name']));

  $sql = "UPDATE master_inventory.master_role SET
          role_name='{$role_name}'
          WHERE role_id='{$id}'";

  if($db->query($sql)){
    $session->msg('s',"Role Updated Successfully");
  } else {
    $session->msg('d',"Update Failed");
  }

  redirect('master_role.php');
}

/* ================= DELETE ================= */
if(isset($_GET['delete'])){
  $id = (int)$_GET['delete'];

  $db->query("DELETE FROM master_inventory.master_role WHERE role_id='{$id}'");

  $session->msg("s","Role Deleted");
  redirect('master_role.php');
}

include_once('layouts/header.php');
?>

<div class="row">

<!-- ================= FORM ================= -->
<div class="col-md-4">
<div class="panel panel-default">

<div class="panel-heading">
<strong><?php echo $edit_role ? "EDIT ROLE" : "ADD ROLE"; ?></strong>
</div>

<div class="panel-body">

<form method="post">

<?php if($edit_role): ?>
<input type="hidden" name="role_id" value="<?php echo $edit_role['role_id']; ?>">
<?php endif; ?>

<div class="form-group">
<input type="text"
name="role_name"
class="form-control"
placeholder="Role Name"
value="<?php echo $edit_role ? $edit_role['role_name'] : ''; ?>"
required autofocus>
</div>

<?php if($edit_role): ?>

<button type="submit" name="update_role" class="btn btn-primary">
Update Role
</button>

<a href="master_role.php" class="btn btn-default">Cancel</a>

<?php else: ?>

<button type="submit" name="add_role" class="btn btn-danger">
Save Role
</button>

<?php endif; ?>

</form>

</div>
</div>
</div>

<!-- ================= LIST ================= -->
<div class="col-md-8">
<div class="panel panel-default">

<div class="panel-heading">
<strong>ROLE LIST</strong>
</div>

<div class="panel-body">

<input type="text" id="roleSearch" class="form-control" placeholder="Search role..."><br>

<table class="table table-bordered" id="roleTable">

<thead>
<tr>
<th>#</th>
<th>Role Name</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php foreach($roles as $i => $role): ?>

<tr>
<td><?php echo $i+1; ?></td>
<td><?php echo $role['role_name']; ?></td>

<td>
<a href="?edit=<?php echo $role['role_id']; ?>" class="btn btn-info btn-xs">Edit</a>

<a href="?delete=<?php echo $role['role_id']; ?>"
class="btn btn-danger btn-xs"
onclick="return confirm('Delete this role?')">
Delete
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

<script>
document.getElementById("roleSearch").addEventListener("keyup", function(){
  let val = this.value.toLowerCase();

  document.querySelectorAll("#roleTable tbody tr").forEach(row=>{
    row.style.display = row.innerText.toLowerCase().includes(val) ? "" : "none";
  });
});
</script>

<?php include_once('layouts/footer.php'); ?>