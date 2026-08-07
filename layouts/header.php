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

<!-- 🏬 ULTRA-REALISTIC 3D METALLIC SHUTTER STYLING -->
<style>
/* Fullscreen Shutter Overlay */
.shutter-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    /* Realistic Metallic Slats Texture */
    background: 
        linear-gradient(90deg, rgba(0,0,0,0.3) 0%, transparent 4%, transparent 96%, rgba(0,0,0,0.3) 100%),
        repeating-linear-gradient(
            180deg,
            #ffffff 0px,
            #cbd5e1 3px,
            #94a3b8 10px,
            #64748b 16px,
            #e2e8f0 22px
        );
    border-bottom: 28px solid #1e293b;
    box-shadow: inset 0 -40px 60px rgba(0, 0, 0, 0.35);
    z-index: 9999999;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    transition: transform 1.25s cubic-bezier(0.25, 1, 0.5, 1);
    transform: translateY(0%);
}

.shutter-overlay.shutter-hidden {
    display: none !important;
}

.shutter-overlay.shutter-open {
    transform: translateY(-100%);
}

/* Side Metallic Guide Channels */
.shutter-guide-rail-left,
.shutter-guide-rail-right {
    position: absolute;
    top: 0;
    width: 25px;
    height: 100%;
    background: linear-gradient(90deg, #334155, #64748b, #1e293b);
    box-shadow: 0 0 15px rgba(0,0,0,0.4);
    z-index: 10;
}
.shutter-guide-rail-left { left: 0; }
.shutter-guide-rail-right { right: 0; }

/* Heavy Bottom Handle Bar & Brass Lock */
.shutter-bottom-rail {
    width: 320px;
    height: 38px;
    background: linear-gradient(180deg, #f8fafc, #64748b, #334155);
    border-radius: 10px 10px 0 0;
    border: 2px solid #0f172a;
    box-shadow: 0 12px 28px rgba(0,0,0,0.4);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    color: #0f172a;
    font-weight: 800;
    font-size: 12px;
    letter-spacing: 2px;
    position: relative;
    z-index: 11;
}

.shutter-brass-lock {
    width: 32px;
    height: 32px;
    background: #a80000;
    color: #ffffff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    box-shadow: 0 0 12px rgba(168, 0, 0, 0.6);
    transition: all 0.35s ease;
}

.shutter-brass-lock.unlocked {
    background: #16a34a;
    box-shadow: 0 0 16px rgba(22, 163, 74, 0.8);
    transform: scale(1.15) rotate(12deg);
}
</style>

<!-- 🏬 DYNAMIC SYNTHESIZED MECHANICAL SOUNDS -->
<script>
// 1. Lock Unlock Click Sound
function playLockClickSound() {
    try {
        var AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        var ctx = new AudioContext();

        var osc = ctx.createOscillator();
        var gain = ctx.createGain();

        osc.type = 'triangle';
        osc.frequency.setValueAtTime(1200, ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(300, ctx.currentTime + 0.08);

        gain.gain.setValueAtTime(0.4, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.08);

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.start();
        osc.stop(ctx.currentTime + 0.09);
    } catch (e) {}
}

// 2. Rolling Shutter Heavy Metallic Noise Sound
function playRollingShutterSound() {
    try {
        var AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        var ctx = new AudioContext();

        var bufferSize = ctx.sampleRate * 1.25;
        var buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
        var data = buffer.getChannelData(0);

        for (var i = 0; i < bufferSize; i++) {
            data[i] = Math.random() * 2 - 1;
        }

        var noise = ctx.createBufferSource();
        noise.buffer = buffer;

        var filter = ctx.createBiquadFilter();
        filter.type = 'bandpass';
        filter.frequency.setValueAtTime(450, ctx.currentTime);
        filter.frequency.linearRampToValueAtTime(750, ctx.currentTime + 1.1);
        filter.Q.value = 2.2;

        var gain = ctx.createGain();
        gain.gain.setValueAtTime(0.35, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 1.2);

        noise.connect(filter);
        filter.connect(gain);
        gain.connect(ctx.destination);

        noise.start();
    } catch (e) {}
}

// 🚀 Fresh Login Shutter Transition
window.addEventListener("DOMContentLoaded", function() {
    var shutter = document.getElementById("shopShutter");
    var lock = document.getElementById("shutterPadlock");
    var lockIcon = document.getElementById("shutterLockIcon");
    var isFreshLogin = <?php echo $is_fresh_login ? 'true' : 'false'; ?>;

    if (isFreshLogin && shutter) {
        shutter.classList.remove("shutter-hidden");
        
        // Step 1: Unlock Lock First
        setTimeout(function() {
            playLockClickSound();
            if (lock && lockIcon) {
                lock.classList.add("unlocked");
                lockIcon.className = "fa-solid fa-lock-open";
            }
        }, 150);

        // Step 2: Roll Shutter Up
        setTimeout(function() {
            playRollingShutterSound();
            shutter.classList.add("shutter-open");
        }, 450);
    }
});

// 🔒 Logout Shutter Transition
function animateLogout(e) {
    e.preventDefault();
    var shutter = document.getElementById("shopShutter");
    var lock = document.getElementById("shutterPadlock");
    var lockIcon = document.getElementById("shutterLockIcon");
    var targetUrl = e.currentTarget.href;

    if (shutter) {
        shutter.classList.remove("shutter-hidden");
        shutter.classList.remove("shutter-open");
        playRollingShutterSound();

        setTimeout(function() {
            playLockClickSound();
            if (lock && lockIcon) {
                lock.classList.remove("unlocked");
                lockIcon.className = "fa-solid fa-lock";
            }
        }, 900);

        setTimeout(function() {
            window.location.href = targetUrl;
        }, 1250);
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

<!-- 🏬 3D REALISTIC ROLLING SHUTTER OVERLAY -->
<div id="shopShutter" class="shutter-overlay shutter-hidden">
    <div class="shutter-guide-rail-left"></div>
    <div class="shutter-guide-rail-right"></div>
    
    <div class="shutter-bottom-rail">
        <div id="shutterPadlock" class="shutter-brass-lock">
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
