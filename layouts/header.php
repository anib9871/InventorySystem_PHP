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

<!-- ✅ SMART TAB TOKEN & NEW TAB BRIDGE SCRIPT -->
<?php
$is_fresh_login = false;
if (!empty($_SESSION['just_logged_in'])) {
    $is_fresh_login = true;
    unset($_SESSION['just_logged_in']);
}
?>
<script>
(function() {
    var serverToken = "<?php echo $_SESSION['tab_token'] ?? ''; ?>";
    var isFresh = <?php echo $is_fresh_login ? 'true' : 'false'; ?>;
    
    if (serverToken !== '') {
        var clientToken = sessionStorage.getItem("app_tab_token");
        
        // 1. Fresh Login -> Same tab me session set karo
        if (isFresh) {
            sessionStorage.setItem("app_tab_token", serverToken);
        } 
        // 2. Agar new tab khola hai
        else if (!clientToken || clientToken !== serverToken) {
            
            // Check: Kya ye new tab app ke kisi link/toggle se khola gaya hai?
            var bridgeData = localStorage.getItem("app_tab_bridge");
            var isBridgeValid = false;
            
            if (bridgeData) {
                try {
                    var parsed = JSON.parse(bridgeData);
                    // Check if link was clicked within last 5 seconds
                    if (parsed.token === serverToken && (Date.now() - parsed.time) < 5000) {
                        isBridgeValid = true;
                    }
                } catch(e){}
            }
            
            if (isBridgeValid) {
                // Link se khola hai -> Naye tab ko allow karo
                sessionStorage.setItem("app_tab_token", serverToken);
            } else {
                // Direct URL paste / Tab closing case -> Force Logout
                window.location.href = "logout.php";
            }
        }
    }
})();

// App ke andar kisi bhi link (Open in new tab / Ctrl+Click) ko detect karne ka listener
document.addEventListener("mousedown", function(e) {
    var aTag = e.target.closest("a");
    if (aTag) {
        var serverToken = "<?php echo $_SESSION['tab_token'] ?? ''; ?>";
        if (serverToken !== '') {
            localStorage.setItem("app_tab_bridge", JSON.stringify({
                token: serverToken,
                time: Date.now()
            }));
        }
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
