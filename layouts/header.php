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

<!-- ✅ REDEPLOYMENT POPUP STYLING & SCRIPT -->
<style>
  #deployModalOverlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(6px);
    z-index: 999999;
    display: none;
    align-items: center;
    justify-content: center;
  }
  .deploy-modal-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 28px 32px;
    max-width: 420px;
    width: 90%;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
    text-align: center;
    animation: popupAnim 0.25s ease-out;
  }
  @keyframes popupAnim {
    from { transform: scale(0.85); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
  }
  .deploy-icon {
    font-size: 48px;
    margin-bottom: 12px;
  }
  .deploy-btn {
    background: #2563eb;
    color: #fff;
    border: none;
    padding: 12px 24px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 15px;
    margin-top: 20px;
    cursor: pointer;
    width: 100%;
    transition: background 0.2s;
  }
  .deploy-btn:hover {
    background: #1d4ed8;
  }
</style>

<script>
(function() {
    var CURRENT_VERSION = "<?php echo defined('APP_VERSION') ? APP_VERSION : '1.0.1'; ?>"; 
    var savedVersion = localStorage.getItem("app_deploy_version");

    // 1. Redeploy Check
    if (!savedVersion) {
        localStorage.setItem("app_deploy_version", CURRENT_VERSION);
    } else if (savedVersion !== CURRENT_VERSION) {
        window.addEventListener("DOMContentLoaded", function() {
            var modal = document.getElementById("deployModalOverlay");
            if (modal) {
                modal.style.display = "flex";
            }
        });
    }

    // 2. Safe Tab Close Check (Without Session Destruction Loop)
    var isFreshLogin = <?php echo !empty($_SESSION['just_logged_in']) ? 'true' : 'false'; ?>;
    
    if (isFreshLogin) {
        sessionStorage.setItem("tab_is_open", "true");
    } else {
        // Tab Close hone par jab naya Tab khulega toh direct Login Screen par bhejega
        if (!sessionStorage.getItem("tab_is_open")) {
            window.location.href = "login_v2.php";
            return;
        }
    }
})();

function confirmRedeployLogout() {
    var CURRENT_VERSION = "<?php echo defined('APP_VERSION') ? APP_VERSION : '1.0.1'; ?>";
    localStorage.setItem("app_deploy_version", CURRENT_VERSION);
    window.location.href = "logout.php?msg=updated";
}

// Same app ke andar kisi link ko naye tab me kholne par tab state maintain rakhna
document.addEventListener("mousedown", function(e) {
    if (e.target.closest("a")) {
        sessionStorage.setItem("tab_is_open", "true");
    }
});
</script>

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
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js"></script>

</head>
<body>

<!-- ✅ REDEPLOYMENT POPUP OVERLAY HTML -->
<div id="deployModalOverlay">
  <div class="deploy-modal-card">
    <div class="deploy-icon">🚀</div>
    <h3 style="margin: 0 0 8px 0; font-weight: 700; color: #0f172a;">System Updated</h3>
    <p style="margin: 0; font-size: 14px; color: #64748b; line-height: 1.5;">
      A new update has been deployed. Please click below to refresh and log in again.
    </p>
    <button class="deploy-btn" onclick="confirmRedeployLogout()">OK, Login Again</button>
  </div>
</div>

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
