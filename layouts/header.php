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

// ✅ FRESH LOGIN FLAG CONSUMPTION FOR SHUTTER
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

<!-- 🏬 3D CARTOON CHARACTER & METALLIC SHUTTER STYLING -->
<style>
.shutter-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: linear-gradient(90deg, rgba(0,0,0,0.2) 0%, transparent 4%, transparent 96%, rgba(0,0,0,0.2) 100%),
                repeating-linear-gradient(
                    180deg,
                    #f8fafc 0px,
                    #cbd5e1 4px,
                    #94a3b8 10px,
                    #64748b 16px,
                    #cbd5e1 22px
                );
    border-bottom: 25px solid #1e293b;
    box-shadow: inset 0 -35px 50px rgba(0, 0, 0, 0.3);
    z-index: 9999999;
    transition: transform 1.3s cubic-bezier(0.25, 1, 0.5, 1);
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

/* 3D Character & Store Badge Overlay */
.character-3d-wrapper {
    position: absolute;
    bottom: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    z-index: 100;
}

.character-3d-badge {
    background: #ffffff;
    border: 2px solid #a80000;
    color: #a80000;
    font-weight: 800;
    font-size: 13px;
    padding: 6px 22px;
    border-radius: 20px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    margin-bottom: 12px;
    letter-spacing: 1.5px;
}

.character-3d-svg {
    width: 130px;
    height: 130px;
    filter: drop-shadow(0 15px 20px rgba(0,0,0,0.25));
    animation: characterMotion 1.2s infinite alternate ease-in-out;
}

@keyframes characterMotion {
    0% { transform: translateY(0px) scale(1); }
    100% { transform: translateY(-8px) scale(1.03); }
}

.shutter-handle-bar {
    width: 280px;
    height: 24px;
    background: linear-gradient(180deg, #ffffff, #94a3b8);
    border-radius: 8px;
    border: 2px solid #334155;
    box-shadow: 0 6px 15px rgba(0,0,0,0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: 2px;
    margin-top: -10px;
}
</style>

<!-- 🏬 SHUTTER SOUND & 3D ANIMATION CONTROLLER -->
<script>
function playLockClickSound() {
    try {
        var AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        var ctx = new AudioContext();
        var osc = ctx.createOscillator();
        var gain = ctx.createGain();

        osc.type = 'triangle';
        osc.frequency.setValueAtTime(1100, ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(300, ctx.currentTime + 0.08);

        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.08);

        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.09);
    } catch (e) {}
}

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
        filter.Q.value = 2.0;

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
    var statusText = document.getElementById("shutterStatusBadge");
    var isFreshLogin = <?php echo $is_fresh_login ? 'true' : 'false'; ?>;

    if (isFreshLogin && shutter) {
        if (statusText) statusText.innerText = "STORE OPENING...";
        shutter.classList.remove("shutter-hidden");
        
        setTimeout(function() {
            playLockClickSound();
        }, 100);

        setTimeout(function() {
            playRollingShutterSound();
            shutter.classList.add("shutter-open");
        }, 350);
    }
});

function animateLogout(e) {
    e.preventDefault();
    var shutter = document.getElementById("shopShutter");
    var statusText = document.getElementById("shutterStatusBadge");
    var targetUrl = e.currentTarget.href;

    if (shutter) {
        if (statusText) statusText.innerText = "STORE CLOSING...";
        shutter.classList.remove("shutter-hidden");
        shutter.classList.remove("shutter-open");
        playRollingShutterSound();

        setTimeout(function() {
            playLockClickSound();
        }, 850);

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

<!-- 🏬 3D CARTOON CHARACTER METALLIC SHUTTER OVERLAY -->
<div id="shopShutter" class="shutter-overlay shutter-hidden">
    <div class="character-3d-wrapper">
        <div id="shutterStatusBadge" class="character-3d-badge">STORE OPENING...</div>
        
        <!-- Modern 3D Business Cartoon Character Vector Overlay -->
        <svg class="character-3d-svg" viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="skinGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#ffedd5"/>
                    <stop offset="100%" stop-color="#fed7aa"/>
                </linearGradient>
                <linearGradient id="suitGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" stop-color="#1e293b"/>
                    <stop offset="100%" stop-color="#0f172a"/>
                </linearGradient>
                <linearGradient id="hairGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#475569"/>
                    <stop offset="100%" stop-color="#1e293b"/>
                </linearGradient>
            </defs>
            <!-- Hair -->
            <path d="M 35,40 Q 60,15 85,40 Q 90,25 60,18 Q 30,25 35,40 Z" fill="url(#hairGrad)" />
            <!-- Head -->
            <circle cx="60" cy="48" r="20" fill="url(#skinGrad)" />
            <!-- Glasses -->
            <rect x="42" y="42" width="14" height="10" rx="3" fill="none" stroke="#0f172a" stroke-width="2.5"/>
            <rect x="64" y="42" width="14" height="10" rx="3" fill="none" stroke="#0f172a" stroke-width="2.5"/>
            <line x1="56" y1="46" x2="64" y2="46" stroke="#0f172a" stroke-width="2.5"/>
            <!-- Smile -->
            <path d="M 52,58 Q 60,64 68,58" stroke="#0f172a" stroke-width="2.5" fill="none" stroke-linecap="round"/>
            <!-- Suit Body -->
            <path d="M 32,72 Q 60,65 88,72 L 95,115 L 25,115 Z" fill="url(#suitGrad)" />
            <!-- Shirt & Crimson Tie -->
            <polygon points="52,70 68,70 60,88" fill="#ffffff" />
            <polygon points="57,72 63,72 65,100 60,108 55,100" fill="#a80000" />
            <!-- Hands Lifting Handle Bar -->
            <path d="M 28,78 Q 12,50 25,32" stroke="url(#skinGrad)" stroke-width="8" stroke-linecap="round" fill="none"/>
            <path d="M 92,78 Q 108,50 95,32" stroke="url(#skinGrad)" stroke-width="8" stroke-linecap="round" fill="none"/>
            <circle cx="25" cy="30" r="6" fill="#a80000"/>
            <circle cx="95" cy="30" r="6" fill="#a80000"/>
        </svg>

        <div class="shutter-handle-bar">
            <i class="fa-solid fa-store" style="margin-right: 6px;"></i> STORE GATE
        </div>
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
