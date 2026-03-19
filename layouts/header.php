<?php
require_once('includes/load.php');

$user = [];

/* GET SESSION USER DATA */

if(isset($_SESSION['username'])){
  $user['name'] = $_SESSION['username'];
}

if(isset($_SESSION['role_id'])){
  $user['role_id'] = $_SESSION['role_id'];
}else{
  $user['role_id'] = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">

<title>
<?php
if (!empty($page_title)){
echo remove_junk($page_title);
}
elseif(!empty($user) && isset($user['name'])){
echo ucfirst($user['name']);
}
else{
echo "Inventory Management System";
}
?>
</title>

<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css"/>
<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.3.0/css/datepicker3.min.css"/>
<link rel="stylesheet" href="libs/css/main.css"/>

</head>

<body>

<?php if(isset($_SESSION['username'])): ?>

<header id="header">

<div class="logo pull-left">
Inventory System
</div>

<div class="header-content">

<div class="header-date pull-left">
<strong><?php echo date("F j, Y, g:i a");?></strong>
</div>

<div class="pull-right clearfix">

<ul class="info-menu list-inline list-unstyled">

<li class="profile">

<a href="#" data-toggle="dropdown" class="toggle">

<img src="uploads/users/no_image.png" class="img-circle img-inline">

<span>
<?php echo ucfirst($user['name']); ?>
<i class="caret"></i>
</span>

</a>

<ul class="dropdown-menu">

<li>
<a href="logout.php">
<i class="glyphicon glyphicon-off"></i>
Logout
</a>
</li>

</ul>

</li>

</ul>

</div>

</div>

</header>

<!-- SIDEBAR -->

<div class="sidebar">

<?php if($user['role_id'] == 1): ?>

<?php include_once('superadmin_menu.php');?>

<?php elseif($user['role_id'] == 2): ?>

<?php include_once('admin_menu.php');?>

<?php elseif($user['role_id'] == 3): ?>

<?php include_once('user_menu.php');?>

<?php endif; ?>

</div>

<?php endif; ?>

<div class="page">
<div class="container-fluid">
