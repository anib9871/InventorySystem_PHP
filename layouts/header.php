<?php
require_once('includes/load.php');

$user = [];

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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>

<?php if(isset($_SESSION['username'])): ?>

  <?php
$system = isset($_GET['system']) ? $_GET['system'] : 'inventory';
?>

<header id="header">

<!-- ✅ ROLE BASED LOGO -->
<?php if($user['role_id'] != 1): ?>
<div class="logo pull-left" id="menuToggle" 
     style="cursor:pointer; font-size:13px; white-space:nowrap; font-weight:600;">
<?php 
if(isset($_SESSION['billing_access']) && $_SESSION['billing_access'] == 1 && $_SESSION['inventory_access'] == 0){
    echo "RED ORANGES CONSULTING";
}
elseif(isset($_SESSION['inventory_access']) && $_SESSION['inventory_access'] == 1 && $_SESSION['billing_access'] == 0){
    echo "RED ORANGES CONSULTING";
}
elseif(isset($_SESSION['combined_mode']) && $_SESSION['combined_mode'] == 1){
echo "RED ORANGES CONSULTING";
}
else{
    echo "SYSTEM";
}
?>

<span style="font-size:12px;"></span>
</div>
<?php else: ?>
  <div class="logo pull-left">
    INVENTORY SYSTEM
  </div>
<?php endif; ?>

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

<?php if($_SESSION['role_id'] == 2): ?>

    <?php echo $_SESSION['org_name']; ?>

<?php else: ?>

    <?php echo ucfirst($user['name']); ?>

    <?php if(isset($_SESSION['org_name'])): ?>
        | <?php echo $_SESSION['org_name']; ?>
    <?php endif; ?>

    <?php if(isset($_SESSION['center_name'])): ?>
        | <?php echo $_SESSION['center_name']; ?>
    <?php endif; ?>

<?php endif; ?>

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
