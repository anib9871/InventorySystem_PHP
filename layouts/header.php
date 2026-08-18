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
<!-- Mobile responsiveness viewport -->
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta http-equiv="X-UA-Compatible" content="IE=edge">

<title>
<?php
if (!empty($page_title)){
  echo remove_junk($page_title);
}
elseif(!empty($user) && isset($user['name'])){
  echo ucfirst($user['name']);
}
else{
  echo "Storely - Inventory Management System";
}
?>
</title>

<!-- Bootstrap & Plugins CSS -->
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css"/>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- Central Main CSS Location -->
<link rel="stylesheet" href="libs/css/main.css"/>

<!-- JS Libraries -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js"></script>

<!-- REDEPLOYMENT & TAB GUARD -->
<script>
(function() {
    var CURRENT_VERSION = "<?php echo defined('APP_VERSION') ? APP_VERSION : '1.0.1'; ?>"; 
    var savedVersion = localStorage.getItem("app_deploy_version");

    if (!savedVersion) {
        localStorage.setItem("app_deploy_version", CURRENT_VERSION);
    } else if (savedVersion !== CURRENT_VERSION) {
        localStorage.setItem("app_deploy_version", CURRENT_VERSION);
        window.location.href = "logout.php?msg=updated";
        return;
    }

    var isLoggedIn = <?php echo !empty($_SESSION['user_id']) ? 'true' : 'false'; ?>;
    if (!isLoggedIn) return;
})();
</script>

</head>
<body>

<?php if(isset($_SESSION['username'])): ?>

  <?php
  $system = isset($_GET['system']) ? $_GET['system'] : 'inventory';
  ?>

<header id="header">

<!-- ROLE BASED RED LOGO / SIDEBAR TOGGLE -->
<?php if($user['role_id'] != 1): ?>
<div class="logo pull-left" id="menuToggle" style="cursor:pointer;">
  <i class="fa-solid fa-bars visible-xs-inline-block" style="margin-right: 6px;"></i>
  <span><?php echo isset($_SESSION['org_name']) ? $_SESSION['org_name'] : 'STORELY'; ?></span>
</div>
<?php else: ?>
  <div class="logo pull-left" id="menuToggle" style="cursor:pointer;">
    <i class="fa-solid fa-bars visible-xs-inline-block" style="margin-right: 6px;"></i>
    <span>STORELY INVENTORY</span>
  </div>
<?php endif; ?>

<div class="header-content">

<!-- Header Date -->
<div class="header-date pull-left hidden-xs">
  <strong><?php echo date("F j, Y, g:i a");?></strong>
</div>

<!-- Logout Menu Section -->
<div class="pull-right">
<ul class="info-menu list-inline list-unstyled">
  <li class="profile dropdown">
    <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
      <i class="fa-solid fa-circle-user" style="font-size: 22px; color: #0284c7;"></i>
      <span>
        <?php if(isset($_SESSION['role_id']) && $_SESSION['role_id'] == 2): ?>
            Logout
        <?php else: ?>
            <?php echo $_SESSION['username']; ?>
            <?php if(!empty($_SESSION['center_name'])): ?>
                <span class="hidden-xs">| <?php echo $_SESSION['center_name']; ?></span>
            <?php endif; ?>
        <?php endif; ?>
      </span>
      <i class="caret"></i>
    </a>
    <ul class="dropdown-menu dropdown-menu-right">
      <li>
        <a href="logout.php">
          <i class="fa-solid fa-power-off" style="color: #ef4444;"></i> Logout
        </a>
      </li>
    </ul>
  </li>
</ul>
</div>

</div>
</header>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebarNav">
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
<div class="container-fluid" style="padding-top: 15px;">
