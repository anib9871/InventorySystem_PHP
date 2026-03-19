<?php
$page_title = 'User Credentials';
require_once('includes/load.php');

/* ================= ORGANIZATIONS (DESC) ================= */
$orgs = find_by_sql("SELECT * FROM master_inventory.master_organization ORDER BY org_id DESC");

/* ================= USERS LIST (DESC) ================= */
$users = find_by_sql("
SELECT u.id,u.username,u.password,u.org_id,u.center_id,u.role_id,
r.role_name,
o.org_name,
c.center_name
FROM master_inventory.user_credentials u
LEFT JOIN master_inventory.master_role r ON r.role_id = u.role_id
LEFT JOIN master_inventory.master_organization o ON o.org_id=u.org_id
LEFT JOIN master_inventory.master_center c ON c.center_id=u.center_id
ORDER BY u.id DESC
");

/* ================= ADD USER ================= */
if(isset($_POST['add_user'])){

  $username = remove_junk($db->escape($_POST['username']));
  $password = remove_junk($db->escape($_POST['password']));
  $org_id   = (int)$_POST['org_id'];
  $center_id= (int)$_POST['center_id'];
  $role_id  = (int)$_POST['role_id'];

  if($username=="" || $password=="" || $org_id==0 || $center_id==0){
    $session->msg('d',"All fields required");
    redirect('user_credentials.php');
  }

  $sql = "INSERT INTO master_inventory.user_credentials
  (username,password,org_id,center_id,role_id)
  VALUES('$username','$password','$org_id','$center_id','$role_id')";

  if($db->query($sql)){
    $session->msg('s',"User Added Successfully");
  } else {
    $session->msg('d',"Failed to add user");
  }

  redirect('user_credentials.php');
}

/* ================= EDIT FETCH ================= */
$edit_user = null;

if(isset($_GET['edit'])){
  $id=(int)$_GET['edit'];

  $res = find_by_sql("SELECT * FROM master_inventory.user_credentials WHERE id='{$id}' LIMIT 1");

  if($res){
    $edit_user = $res[0];
  }
}

/* ================= UPDATE ================= */
if(isset($_POST['update_user'])){

  $id=(int)$_POST['id'];

  $username = remove_junk($db->escape($_POST['username']));
  $password = remove_junk($db->escape($_POST['password']));
  $org_id   = (int)$_POST['org_id'];
  $center_id= (int)$_POST['center_id'];
  $role_id  = (int)$_POST['role_id'];

  $sql="UPDATE master_inventory.user_credentials SET
  username='{$username}',
  password='{$password}',
  org_id='{$org_id}',
  center_id='{$center_id}',
  role_id='{$role_id}'
  WHERE id='{$id}'";

  if($db->query($sql)){
    $session->msg("s","User Updated Successfully");
  } else {
    $session->msg("d","Update Failed");
  }

  redirect('user_credentials.php');
}

/* ================= DELETE ================= */
if(isset($_GET['delete'])){
  $id=(int)$_GET['delete'];

  $db->query("DELETE FROM master_inventory.user_credentials WHERE id='{$id}'");

  $session->msg("s","User Deleted");
  redirect('user_credentials.php');
}

include_once('layouts/header.php');
?>

<div class="row">

<!-- ================= FORM ================= -->
<div class="col-md-4">
<div class="panel panel-default">

<div class="panel-heading">
<strong><?php echo $edit_user ? "EDIT USER" : "ADD USER"; ?></strong>
</div>

<div class="panel-body">

<form method="post">

<?php if($edit_user): ?>
<input type="hidden" name="id" value="<?php echo $edit_user['id']; ?>">
<?php endif; ?>

<!-- USERNAME -->
<div class="form-group">
<input type="text" name="username" class="form-control"
placeholder="Username"
value="<?php echo $edit_user ? $edit_user['username'] : ''; ?>"
required>
</div>

<!-- PASSWORD -->
<div class="form-group">
<input type="text" name="password" class="form-control"
placeholder="Password"
value="<?php echo $edit_user ? $edit_user['password'] : ''; ?>"
required>
</div>

<!-- ORGANIZATION -->
<div class="form-group">
<select name="org_id" id="orgSelect" class="form-control" required>
<option value="">Select Organization</option>

<?php foreach($orgs as $org): ?>
<option value="<?php echo $org['org_id']; ?>"
<?php if($edit_user && $edit_user['org_id']==$org['org_id']) echo "selected"; ?>>
<?php echo $org['org_name']; ?>
</option>
<?php endforeach; ?>

</select>
</div>

<!-- CENTER -->
<div class="form-group">
<select name="center_id" id="centerSelect" class="form-control" required>
<option value="">Select Center</option>

<?php
if($edit_user){
$centers = find_by_sql("
SELECT * FROM master_inventory.master_center
WHERE org_id='{$edit_user['org_id']}'
ORDER BY center_id DESC
");

foreach($centers as $c){
?>
<option value="<?php echo $c['center_id']; ?>"
<?php if($edit_user['center_id']==$c['center_id']) echo "selected"; ?>>
<?php echo $c['center_name']; ?>
</option>
<?php } } ?>
</select>
</div>

<!-- ROLE -->
<div class="form-group">
<select name="role_id" class="form-control" required>
<option value="">Select Role</option>

<option value="1" <?php if($edit_user && $edit_user['role_id']==1) echo "selected"; ?>>Super Admin</option>
<option value="2" <?php if($edit_user && $edit_user['role_id']==2) echo "selected"; ?>>Admin</option>
<option value="3" <?php if($edit_user && $edit_user['role_id']==3) echo "selected"; ?>>User</option>

</select>
</div>

<!-- BUTTONS -->
<?php if($edit_user): ?>
<button type="submit" name="update_user" class="btn btn-primary">Update</button>
<a href="user_credentials.php" class="btn btn-default">Cancel</a>
<?php else: ?>
<button type="submit" name="add_user" class="btn btn-danger">Save User</button>
<?php endif; ?>

</form>

</div>
</div>
</div>

<!-- ================= LIST ================= -->
<div class="col-md-8">
<div class="panel panel-default">

<div class="panel-heading"><strong>USER LIST</strong></div>

<div class="panel-body">

<input type="text" id="userSearch" class="form-control" placeholder="Search user..."><br>

<table class="table table-bordered" id="userTable">
<thead>
<tr>
<th>#</th>
<th>Username</th>
<th>Password</th>
<th>Organization</th>
<th>Center</th>
<th>Role</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php foreach($users as $i=>$u): ?>
<tr>
<td><?php echo $i+1; ?></td>
<td><?php echo $u['username']; ?></td>
<td><?php echo $u['password']; ?></td>
<td><?php echo $u['org_name']; ?></td>
<td><?php echo $u['center_name']; ?></td>
<td><?php echo $u['role_name']; ?></td>

<td>
<a href="?edit=<?php echo $u['id']; ?>" class="btn btn-info btn-xs">Edit</a>
<a href="?delete=<?php echo $u['id']; ?>" class="btn btn-danger btn-xs"
onclick="return confirm('Delete this user?')">Delete</a>
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
document.getElementById("userSearch").addEventListener("keyup", function(){
  let val = this.value.toLowerCase();
  document.querySelectorAll("#userTable tbody tr").forEach(row=>{
    row.style.display = row.innerText.toLowerCase().includes(val) ? "" : "none";
  });
});

/* DYNAMIC CENTER LOAD */
document.getElementById("orgSelect").addEventListener("change", function(){
  let org_id = this.value;

  fetch("get_centers.php?org_id="+org_id)
  .then(res=>res.text())
  .then(data=>{
    document.getElementById("centerSelect").innerHTML = data;
  });
});
</script>

<?php include_once('layouts/footer.php'); ?>