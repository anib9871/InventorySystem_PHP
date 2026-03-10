<?php
$page_title = 'Edit User';
require_once('includes/load.php');
page_require_level(1);

$e_user = find_by_id('users',(int)$_GET['id']);
$groups  = find_all('user_groups');

if(!$e_user){
  $session->msg("d","Missing user id.");
  redirect('users.php');
}
?>

<?php
if(isset($_POST['update'])){

$req_fields = array('name','username','password','confirm_password','level');
validate_fields($req_fields);

if(empty($errors)){

$id = (int)$e_user['id'];

$name = remove_junk($db->escape($_POST['name']));
$username = remove_junk($db->escape($_POST['username']));
$password = remove_junk($db->escape($_POST['password']));
$confirm = remove_junk($db->escape($_POST['confirm_password']));
$level = (int)$db->escape($_POST['level']);

if($password != $confirm){
$session->msg('d',"Password not matched");
redirect('edit_user.php?id='.$id);
}

$sql = "UPDATE users SET 
name='{$name}',
username='{$username}',
password='{$password}',
user_level='{$level}'
WHERE id='{$id}'";

$result = $db->query($sql);

if($result && $db->affected_rows() === 1){
$session->msg('s',"User Updated Successfully");
redirect('users.php');
}else{
$session->msg('d',"Sorry Failed to Update User");
redirect('edit_user.php?id='.$id);
}

}else{
$session->msg("d",$errors);
redirect('edit_user.php?id='.$e_user['id']);
}
}
?>

<?php include_once('layouts/header.php'); ?>

<div class="row">

<div class="col-md-4">

<div class="panel panel-primary">

<div class="panel-heading">
<strong>
<span class="glyphicon glyphicon-th"></span>
Edit User
</strong>
</div>

<div class="panel-body">

<form method="post" action="edit_user.php?id=<?php echo (int)$e_user['id']; ?>">

<div class="form-group">
<label>Name</label>
<input type="text" class="form-control" name="name"
value="<?php echo remove_junk($e_user['name']); ?>">
</div>

<div class="form-group">
<label>Username</label>
<input type="text" class="form-control" name="username"
value="<?php echo remove_junk($e_user['username']); ?>">
</div>

<div class="form-group">
<label>Password</label>
<input type="password" class="form-control" name="password"
value="<?php echo $e_user['password']; ?>">
</div>

<div class="form-group">
<label>Confirm Password</label>
<input type="password" class="form-control" name="confirm_password"
value="<?php echo $e_user['password']; ?>">
</div>

<div class="form-group">
<label>User Role</label>

<select class="form-control" name="level">

<?php foreach ($groups as $group ): ?>

<option
<?php if($group['group_level'] == $e_user['user_level']) echo 'selected'; ?>
value="<?php echo $group['group_level']; ?>">

<?php echo ucwords($group['group_name']); ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="form-group clearfix">
<button type="submit" name="update" class="btn btn-warning">
Update User
</button>
</div>

</form>

</div>

</div>

</div>

</div>

<?php include_once('layouts/footer.php'); ?>
