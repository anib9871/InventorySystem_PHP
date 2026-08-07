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

// ✅ FRESH LOGIN FLAG CONSUMPTION
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

<!-- 🏬 3D REALISTIC STORE ROLLING SHUTTER CSS -->
<style>
.shutter-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    /* 3D Metallic Slats Gradient */
    background: linear-gradient(90deg, #1e293b 0%, transparent 5%, transparent 95%, #1e293b 100%),
                repeating-linear-gradient(
                    180deg,
                    #f1f5f9 0px,
                    #cbd5e1 5px,
                    #94a3b8 12px,
                    #64748b 18px,
                    #cbd5e1 24px
                );
    border-bottom: 25px solid #0f172a;
    box-shadow: inset 0 -35px 50px rgba(0, 0, 0, 0.4);
    z-index: 9999999;
    transition: transform 1.3s cubic-bezier(0.77, 0, 0.175, 1);
    transform: translateY(0%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
}

.shutter-overlay.shutter-hidden {
    display: none !important;
}

.shutter-overlay.shutter-open {
    transform: translateY(-100%);
}

/* Shop Bottom Lock Mechanism Bar */
.shutter-bottom-bar {
    width: 300px;
    height: 32px;
    background: linear-gradient(180deg, #e2e8f0, #475569);
    border-radius: 8px 8px 0 0;
    border: 2px solid #0f172a;
    box-shadow: 0 10px 25px rgba(0,0,0,0.4);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    color: #0f172a;
    font-weight: 800;
    font-size: 12px;
    letter-spacing: 2px;
}

.shutter-padlock {
    width: 28px;
    height: 28px;
    background: #a80000;
    color: #ffffff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    box-shadow: 0 0 10px rgba(168, 0, 0, 0.5);
    transition: all 0.4s ease;
}

.shutter-padlock.unlocked {
    background: #16a34a;
    box-shadow: 0 0 12px rgba(22, 163, 74, 0.6);
}
</style>

<!-- 🏬 ENHANCED MECHANICAL ROLLING SOUND & ANIMATION -->
<script>
function playMechanicalShutterSound() {
    try {
        var AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        var ctx = new AudioContext();

        var bufferSize = ctx.sampleRate * 1.3;
        var buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
        var data = buffer.getChannelData(0);

        for (var i = 0; i < bufferSize; i++) {
            data[i] = Math.random() * 2 - 1;
        }

        var noise = ctx.createBufferSource();
        noise.buffer = buffer;

        var filter = ctx.createBiquadFilter();
        filter.type = 'bandpass';
        filter.frequency.value = 520;
        filter.Q.value = 1.8;

        var gain = ctx.createGain();
        gain.gain.setValueAtTime(0.35, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 1.2);

        noise.connect(filter);
        filter.connect(gain);
        gain.connect(ctx.destination);

        noise.start();
    } catch (e) {}
}

window.addEventListener("DOMContentLoaded", function() {
    var shutter = document.getElementById("shopShutter");
    var lock = document.getElementById("shutterPadlock");
    var lockIcon = document.getElementById("shutterLockIcon");
    var isFreshLogin = <?php echo $is_fresh_login ? 'true' : 'false'; ?>;

    if (isFreshLogin && shutter) {
        shutter.classList.remove("shutter-hidden");
        
        // 🔓 UNLOCK TAALA FIRST, THEN ROLL SHUTTER
        setTimeout(function() {
            if (lock && lockIcon) {
                lock.classList.add("unlocked");
                lockIcon.className = "fa-solid fa-lock-open";
            }
        }, 100);

        setTimeout(function() {
            playMechanicalShutterSound();
            shutter.classList.add("shutter-open");
        }, 400);
    }
});

function animateLogout(e) {
    e.preventDefault();
    var shutter = document.getElementById("shopShutter");
    var lock = document.getElementById("shutterPadlock");
    var lockIcon = document.getElementById("shutterLockIcon");
    var targetUrl = e.currentTarget.href;

    if (shutter) {
        if (lock && lockIcon) {
            lock.classList.remove("unlocked");
            lockIcon.className = "fa-solid fa-lock";
        }

        shutter.classList.remove("shutter-hidden");
        shutter.classList.remove("shutter-open");
        playMechanicalShutterSound();

        setTimeout(function() {
            window.location.href = targetUrl;
        }, 1200);
    } else {
        window.location.href = targetUrl;
    }
}
</script>

<!-- ✅ REDEPLOYMENT & TAB GUARD -->
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

<!-- 🏬 3D REALISTIC STORE SHUTTER OVERLAY -->
<div id="shopShutter" class="shutter-overlay shutter-hidden">
    <div class="shutter-bottom-bar">
        <div id="shutterPadlock" class="shutter-padlock">
            <i id="shutterLockIcon" class="fa-solid fa-lock"></i>
        </div>
        <span>STORE SHUTTER</span>
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
