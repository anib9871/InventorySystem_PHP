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

<!-- 🏬 LIGHT METALLIC SHUTTER STYLING -->
<style>
.shutter-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: repeating-linear-gradient(
        180deg,
        #f1f5f9 0px,
        #cbd5e1 12px,
        #94a3b8 24px,
        #cbd5e1 36px
    );
    border-bottom: 20px solid #475569;
    box-shadow: inset 0 -30px 40px rgba(0, 0, 0, 0.2);
    z-index: 9999999;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    transition: transform 1.3s cubic-bezier(0.65, 0, 0.35, 1);
    transform: translateY(0%);
}

.shutter-overlay.shutter-hidden {
    display: none !important;
}

.shutter-overlay.shutter-open {
    transform: translateY(-100%);
}

.shutter-worker-box {
    text-align: center;
    background: #ffffff;
    padding: 20px 35px;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    border: 2px solid #a80000;
}

.shutter-worker-icon {
    font-size: 50px;
    color: #a80000;
    margin-bottom: 10px;
}

.shutter-text {
    font-size: 13px;
    font-weight: 800;
    letter-spacing: 2px;
    color: #0f172a;
}
</style>

<!-- 🏬 SHUTTER SCRIPT -->
<script>
function playShutterSound() {
    try {
        var AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        var ctx = new AudioContext();

        var bufferSize = ctx.sampleRate * 1.1;
        var buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
        var data = buffer.getChannelData(0);

        for (var i = 0; i < bufferSize; i++) {
            data[i] = Math.random() * 2 - 1;
        }

        var noise = ctx.createBufferSource();
        noise.buffer = buffer;

        var filter = ctx.createBiquadFilter();
        filter.type = 'bandpass';
        filter.frequency.value = 600;
        filter.Q.value = 2.5;

        var gain = ctx.createGain();
        gain.gain.setValueAtTime(0.25, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 1.0);

        noise.connect(filter);
        filter.connect(gain);
        gain.connect(ctx.destination);

        noise.start();
    } catch (e) {}
}

window.addEventListener("DOMContentLoaded", function() {
    var shutter = document.getElementById("shopShutter");
    var isFreshLogin = <?php echo $is_fresh_login ? 'true' : 'false'; ?>;

    // 🚀 SIRF LOGIN KE WAQT SHUTTER OPEN HOGA (BAKI RELOAD PAR NAHI)
    if (isFreshLogin && shutter) {
        shutter.classList.remove("shutter-hidden");
        setTimeout(function() {
            playShutterSound();
            shutter.classList.add("shutter-open");
        }, 300);
    }
});

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
        }, 1200);
    } else {
        window.location.href = targetUrl;
    }
}
</script>

<!-- ✅ REDEPLOYMENT CHECK -->
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

<!-- 🏬 LIGHT SHUTTER OVERLAY (Aadmi Lifting Animation Ke Saath) -->
<div id="shopShutter" class="shutter-overlay shutter-hidden">
    <div class="shutter-worker-box">
        <div class="shutter-worker-icon">
            <i class="fa-solid fa-person-running fa-bounce"></i>
        </div>
        <div class="shutter-text">OPENING...</div>
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
