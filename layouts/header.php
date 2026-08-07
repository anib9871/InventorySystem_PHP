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

<!-- 🏬 CARTOON WORKER & REALISTIC SHUTTER STYLING -->
<style>
.shutter-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: repeating-linear-gradient(
        180deg,
        #cbd5e1 0px,
        #94a3b8 10px,
        #cbd5e1 20px,
        #64748b 30px
    );
    border-bottom: 25px solid #334155;
    box-shadow: inset 0 -30px 50px rgba(15, 23, 42, 0.3);
    z-index: 9999999;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    transition: transform 1.3s cubic-bezier(0.65, 0, 0.35, 1);
    transform: translateY(0%);
}

.shutter-overlay.shutter-hidden {
    display: none !important;
}

.shutter-overlay.shutter-open {
    transform: translateY(-100%);
}

/* Dukaan Board & Handle Section */
.shop-board-container {
    position: absolute;
    bottom: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    z-index: 2;
}

.shop-handle-bar {
    width: 260px;
    height: 20px;
    background: linear-gradient(180deg, #f8fafc, #94a3b8);
    border-radius: 8px;
    border: 2px solid #475569;
    box-shadow: 0 8px 20px rgba(0,0,0,0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: 2px;
}

/* Cartoon Worker Character Container */
.worker-character-box {
    position: absolute;
    bottom: -15px;
    display: flex;
    flex-direction: column;
    align-items: center;
    animation: workerLifting 0.6s infinite alternate ease-in-out;
}

@keyframes workerLifting {
    0% { transform: translateY(0px); }
    100% { transform: translateY(-8px); }
}

.worker-svg {
    width: 110px;
    height: 110px;
    filter: drop-shadow(0px 8px 12px rgba(0,0,0,0.3));
}

.status-badge {
    background: #ffffff;
    color: #a80000;
    font-weight: 800;
    font-size: 13px;
    padding: 6px 20px;
    border-radius: 20px;
    border: 2px solid #a80000;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    margin-bottom: 5px;
    letter-spacing: 1.5px;
}
</style>

<!-- 🏬 SHUTTER SOUND & CARTOON WORKER SCRIPT -->
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
        filter.frequency.value = 600;
        filter.Q.value = 2.5;

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

    // 🚀 LOGIN KE WAQT: Cartoon Worker Shutter Upar Uthayega
    if (isFreshLogin && shutter) {
        document.getElementById("shutterStatusText").innerText = "OPENING STORE...";
        shutter.classList.remove("shutter-hidden");
        
        setTimeout(function() {
            playShutterSound();
            shutter.classList.add("shutter-open");
        }, 300);
    }
});

// 🔒 LOGOUT KE WAQT: Cartoon Worker Shutter Niche Kheenchein
function animateLogout(e) {
    e.preventDefault();
    var shutter = document.getElementById("shopShutter");
    var targetUrl = e.currentTarget.href;

    if (shutter) {
        document.getElementById("shutterStatusText").innerText = "CLOSING STORE...";
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

<!-- ✅ REDEPLOYMENT & TAB ISOLATION SCRIPT -->
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

<!-- 🏬 CARTOON WORKER METALLIC SHUTTER OVERLAY -->
<div id="shopShutter" class="shutter-overlay shutter-hidden">
    <div class="shop-board-container">
        <div id="shutterStatusText" class="status-badge">OPENING STORE...</div>
        
        <!-- Animated Cartoon Man Lifting Shutter -->
        <div class="worker-character-box">
            <svg class="worker-svg" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                <!-- Cap / Safety Hat -->
                <path d="M 25,35 Q 50,15 75,35 Z" fill="#a80000" />
                <rect x="20" y="34" width="60" height="5" rx="2" fill="#8e0000" />
                <!-- Head -->
                <circle cx="50" cy="45" r="15" fill="#fbcfe8" />
                <!-- Eyes -->
                <circle cx="43" cy="43" r="2" fill="#0f172a" />
                <circle cx="57" cy="43" r="2" fill="#0f172a" />
                <!-- Smile -->
                <path d="M 43,52 Q 50,58 57,52" stroke="#0f172a" stroke-width="2" fill="none" />
                <!-- Uniform Body -->
                <path d="M 30,62 L 70,62 L 75,95 L 25,95 Z" fill="#0284c7" />
                <rect x="46" y="62" width="8" height="33" fill="#e0f2fe" />
                <!-- Arms Lifting Up -->
                <path d="M 25,65 Q 10,40 20,25" stroke="#fbcfe8" stroke-width="6" stroke-linecap="round" fill="none" />
                <path d="M 75,65 Q 90,40 80,25" stroke="#fbcfe8" stroke-width="6" stroke-linecap="round" fill="none" />
                <!-- Gloves grabbing handle bar -->
                <circle cx="20" cy="24" r="5" fill="#a80000" />
                <circle cx="80" cy="24" r="5" fill="#a80000" />
            </svg>
        </div>

        <div class="shop-handle-bar">
            <i class="fa-solid fa-lock" style="margin-right: 6px;"></i> STORE SHUTTER
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
