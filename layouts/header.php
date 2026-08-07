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

// ✅ FRESH LOGIN FLAG FOR SHUTTER
$is_fresh_login = false;
if (!empty($_SESSION['just_logged_in'])) {
    $is_fresh_login = true;
    unset($_SESSION['just_logged_in']);
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
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js"></script>

<!-- 🏬 REALISTIC ROLLING SHUTTER CSS (As seen in Green Screen Video) -->
<style>
.shutter-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    /* Slatted Rolling Metallic Metal Pattern */
    background: repeating-linear-gradient(
        180deg,
        #e2e8f0 0px,
        #cbd5e1 6px,
        #94a3b8 12px,
        #cbd5e1 18px,
        #f8fafc 24px
    );
    border-bottom: 22px solid #334155;
    box-shadow: inset 0 -20px 30px rgba(0, 0, 0, 0.25);
    z-index: 9999999;
    transition: transform 1.2s cubic-bezier(0.77, 0, 0.175, 1);
    transform: translateY(0%);
}

.shutter-overlay.shutter-hidden {
    display: none !important;
}

.shutter-overlay.shutter-open {
    transform: translateY(-100%);
}

.shutter-handle-bar {
    position: absolute;
    bottom: 12px;
    left: 50%;
    transform: translateX(-50%);
    width: 220px;
    height: 16px;
    background: linear-gradient(180deg, #ffffff, #94a3b8);
    border-radius: 6px;
    border: 2px solid #475569;
    box-shadow: 0 6px 12px rgba(0,0,0,0.2);
}
</style>

<!-- 🏬 REALISTIC SHUTTER SOUND & ANIMATION CONTROLLER -->
<script>
function playShutterSound() {
    try {
        var AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        var ctx = new AudioContext();

        var bufferSize = ctx.sampleRate * 1.2;
        var buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
        var data = buffer.getChannelData(0);

        for (var i = 0; i < bufferSize; i++) {
            data[i] = Math.random() * 2 - 1;
        }

        var noise = ctx.createBufferSource();
        noise.buffer = buffer;

        var filter = ctx.createBiquadFilter();
        filter.type = 'bandpass';
        filter.frequency.value = 550;
        filter.Q.value = 2.0;

        var gain = ctx.createGain();
        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 1.1);

        noise.connect(filter);
        filter.connect(gain);
        gain.connect(ctx.destination);

        noise.start();
    } catch (e) {}
}

window.addEventListener("DOMContentLoaded", function() {
    var shutter = document.getElementById("shopShutter");
    var isFreshLogin = <?php echo $is_fresh_login ? 'true' : 'false'; ?>;

    // 🚀 FRESH LOGIN: Dashboard khulte hi shutter rolling sound ke saath UP (Open) hoga
    if (isFreshLogin && shutter) {
        shutter.classList.remove("shutter-hidden");
        setTimeout(function() {
            playShutterSound();
            shutter.classList.add("shutter-open");
        }, 200);
    }
});

// 🔒 LOGOUT: Logout click karte hi shutter rolling sound ke saath DOWN (Close) hoga
function animateLogout(e) {
    e.preventDefault();
    var shutter = document.getElementById("shopShutter");
    var targetUrl = e.currentTarget.href;

    if (shutter) {
        shutter.classList.remove("shutter-hidden");
        shutter.classList.remove("shutter-open");
        playShutterSound();

        setTimeout(function() {
            window.location.href = targetUrl;
        }, 1100);
    } else {
        window.location.href = targetUrl;
    }
}
</script>

<!-- ✅ REDEPLOYMENT & SESSION GUARD -->
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

    var isFreshLogin = <?php echo $is_fresh_login ? 'true' : 'false'; ?>;

    if (isFreshLogin) {
        sessionStorage.setItem("app_tab_session", "active");
    } else {
        var isTabActive = sessionStorage.getItem("app_tab_session") === "active";
        var bridgeTime = localStorage.getItem("app_tab_bridge_time");
        var isBridgeValid = bridgeTime && (Date.now() - parseInt(bridgeTime)) < 5000;

        if (!isTabActive && !isBridgeValid) {
            window.location.href = "logout.php?msg=tab_closed";
            return;
        }

        sessionStorage.setItem("app_tab_session", "active");
    }
})();

document.addEventListener("mousedown", function(e) {
    if (e.target.closest("a")) {
        localStorage.setItem("app_tab_bridge_time", Date.now().toString());
    }
});
</script>

</head>
<body>

<!-- 🏬 ROLLING SHUTTER OVERLAY -->
<div id="shopShutter" class="shutter-overlay shutter-hidden">
    <div class="shutter-handle-bar"></div>
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
<a href="logout.php" onclick="animateLogout(event)">
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
