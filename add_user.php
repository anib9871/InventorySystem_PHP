<?php

$page_title = 'Add User';
require_once('includes/load.php');

page_require_level(1);

$groups = find_all('user_groups');

?>

<?php
/* ================= ADD USER ================= */

if(isset($_POST['add_user'])){

$req_fields = array('full-name','username','password','confirm_password','level');
validate_fields($req_fields);

if(empty($errors)){

$name = remove_junk($db->escape($_POST['full-name']));
$username = remove_junk($db->escape($_POST['username']));
$password = $_POST['password'];
$confirm = $_POST['confirm_password'];
$user_level = (int)$db->escape($_POST['level']);

$check = find_by_sql("SELECT username FROM users WHERE username='{$username}'");

if($check){
$session->msg('d',"Username already exists");
redirect('users.php');
}

if($password != $confirm){
$session->msg('d',"Password not matched");
redirect('users.php');
}

$query = "INSERT INTO users (name,username,password,user_level,status)
VALUES ('{$name}','{$username}','{$password}','{$user_level}','1')";

if($db->query($query)){
$session->msg('s',"User account created");
}else{
$session->msg('d',"Failed to create user");
}

redirect('users.php');

}

}
?>

<?php
/* ================= UPDATE USER ================= */

if(isset($_POST['update_user'])){

$id = (int)$_GET['edit'];

$name = remove_junk($db->escape($_POST['full-name']));
$username = remove_junk($db->escape($_POST['username']));
$level = (int)$db->escape($_POST['level']);

$password = $_POST['password'];
$confirm = $_POST['confirm_password'];

$sql = "UPDATE users SET 
name='{$name}',
username='{$username}',
user_level='{$level}'";

if(!empty($password)){

if($password != $confirm){
$session->msg('d',"Password not matched");
redirect('users.php?edit='.$id);
}

$sql .= ", password='{$password}'";
}

$sql .= " WHERE id='{$id}'";

if($db->query($sql)){
$session->msg('s',"User updated successfully");
}else{
$session->msg('d',"User update failed");
}

redirect('users.php');

}
?>

<div class="panel panel-primary">

<div class="panel-heading">
<strong>
<span class="glyphicon glyphicon-th"></span>
<?php echo isset($edit_user) ? 'Edit User' : 'Create User'; ?>
</strong>
</div>

<div class="panel-body">

<form method="post" action="users.php<?php echo isset($_GET['edit']) ? '?edit='.$_GET['edit'] : ''; ?>">

<div class="form-group">
<label>Name</label>
<input type="text" class="form-control" name="full-name"
value="<?php echo isset($edit_user['name']) ? $edit_user['name'] : ''; ?>">
</div>

<div class="form-group">
<label>Username</label>
<input type="text" class="form-control" name="username"
value="<?php echo isset($edit_user['username']) ? $edit_user['username'] : ''; ?>">
</div>

<div class="form-group">
<label>Password</label>

<div class="input-group">
<input type="password" class="form-control" name="password" id="password"
value="<?php echo isset($edit_user['password']) ? $edit_user['password'] : ''; ?>"
placeholder="Leave blank if not changing">
<span class="input-group-btn">
<button type="button" class="btn btn-default" onclick="togglePassword()">👁</button>
</span>
</div>

</div>

<div class="form-group">
<label>Confirm Password</label>

<div class="input-group">
<input type="password" class="form-control" name="confirm_password" id="confirm_password"
value="<?php echo isset($edit_user['password']) ? $edit_user['password'] : ''; ?>">
<span class="input-group-btn">
<button type="button" class="btn btn-default" onclick="toggleConfirmPassword()">👁</button>
</span>
</div>

</div>

<div class="form-group">
<label>User Role</label>

<select class="form-control" name="level">

<?php foreach ($groups as $group ): ?>

<option value="<?php echo $group['group_level']; ?>"
<?php if(isset($edit_user) && $group['group_level']==$edit_user['user_level']) echo 'selected'; ?>>

<?php echo ucwords($group['group_name']); ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="form-group clearfix">

<?php if(isset($edit_user)): ?>

<button type="submit" name="update_user" class="btn btn-warning">
Update User
</button>

<?php else: ?>

<button type="submit" name="add_user" class="btn btn-primary">
Add User
</button>

<?php endif; ?>

</div>

</form>

</div>

</div>

<script>

function togglePassword(){

let pass=document.getElementById("password");

pass.type = pass.type === "password" ? "text":"password";

}

function toggleConfirmPassword(){

let pass=document.getElementById("confirm_password");

pass.type = pass.type === "password" ? "text":"password";

}

</script>
