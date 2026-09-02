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
<link rel="stylesheet" href="libs/css/main.css"/>

<!-- JS Libraries -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js"></script>

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

<style>
.swal2-toast.pastel-success-toast {
    background-color: #d1fae5 !important;
    border: 1px solid #10b981 !important;
    color: #065f46 !important;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15) !important;
}
.swal2-toast.pastel-success-toast .swal2-title {
    color: #065f46 !important;
    font-size: 13px !important;
    font-weight: 600 !important;
}
</style>

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

<div class="header-date pull-left hidden-xs">
  <strong><?php echo date("F j, Y, g:i a");?></strong>
</div>

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
/* ORGANIZATION USERS & STAFF (UNIFIED DYNAMIC ADMIN MENU) */
else{
    include_once('admin_menu.php');
}
?>
</div>

<?php endif; ?>

<div class="page">
<div class="container-fluid" style="padding-top: 15px;">

<?php 
$global_msg = $session->msg();
if(!empty($global_msg) && is_array($global_msg)):
    $msg_type = key($global_msg);
    $msg_text = current($global_msg);
    $is_error = in_array($msg_type, ['danger', 'd', 'error', 'warning', 'w']);
?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    <?php if($is_error): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: '<?php echo strip_tags($msg_text); ?>',
            confirmColor: '#ef4444',
            confirmButtonText: 'OK'
        });
    <?php else: ?>
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: '<?php echo strip_tags($msg_text); ?>',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            customClass: {
                popup: 'pastel-success-toast'
            }
        });
    <?php endif; ?>
});
</script>
<?php endif; ?>
