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

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css"/>

<link rel="stylesheet" href="libs/css/main.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

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

<?php echo $_SESSION['org_name']; ?>

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

<?php if(isset($_SESSION['role_id']) && $_SESSION['role_id'] == 2): ?>

    Logout

<?php else: ?>

    <?php echo $_SESSION['username']; ?>

    <?php if(!empty($_SESSION['center_name'])): ?>
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

<?php

/* SUPER ADMIN */

if(isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1){

    include_once('superadmin_menu.php');

}

/* ORGANIZATION USERS */

else{

    if(isset($_SESSION['user_level']) && $_SESSION['user_level'] == 1){

        include_once('admin_menu.php');

    }else{

        include_once('user_menu.php');

    }

}

?>

</div>

<?php endif; ?>

<div class="page">
<div class="container-fluid">
