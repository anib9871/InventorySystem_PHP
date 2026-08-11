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

<!-- 🏬 SUPER PREMIUM 3D METALLIC SHUTTER STYLING -->
<style>
/* Shutter Container with Ultra-Realistic Metallic Slats & Dynamic Light Reflections */
.shutter-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: 
        /* Dynamic Metallic Light Sweep Reflection */
        linear-gradient(115deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.08) 45%, rgba(255,255,255,0.25) 50%, rgba(255,255,255,0.08) 55%, rgba(255,255,255,0) 100%),
        /* Side Vignette Shadows for 3D Depth */
        linear-gradient(90deg, rgba(0,0,0,0.6) 0%, rgba(0,0,0,0.1) 6%, transparent 12%, transparent 88%, rgba(0,0,0,0.1) 94%, rgba(0,0,0,0.6) 100%),
        /* Metallic Galvanized Steel Slats */
        repeating-linear-gradient(
            180deg,
            #e2e8f0 0px,
            #cbd5e1 3px,
            #94a3b8 8px,
            #475569 14px,
            #1e293b 18px,
            #0f172a 20px,
            #475569 22px,
            #cbd5e1 26px
        );
    background-size: 200% 100%, 100% 100%, 100% 100%;
    animation: metallicShine 4s infinite linear;
    border-bottom: 28px solid #0f172a;
    box-shadow: 0 25px 50px rgba(0,0,0,0.9), inset 0 -15px 30px rgba(0,0,0,0.8);
    z-index: 9999999;
    transition: transform 1.3s cubic-bezier(0.77, 0, 0.175, 1);
    transform: translateY(0%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
}

@keyframes metallicShine {
    0% { background-position: -200% 0, 0 0, 0 0; }
    100% { background-position: 200% 0, 0 0, 0 0; }
}

.shutter-overlay.shutter-hidden {
    display: none !important;
}

.shutter-overlay.shutter-open {
    transform: translateY(-100%);
}

/* Bottom Lock Rail */
.shutter-bottom-rail {
    position: absolute;
    bottom: 0;
    width: 100%;
    height: 38px;
    background: linear-gradient(180deg, #334155 0%, #0f172a 50%, #020617 100%);
    border-top: 3px solid #64748b;
    border-bottom: 4px solid #020617;
    box-shadow: 0 -5px 15px rgba(0,0,0,0.5);
    display: flex;
    justify-content: space-around;
    align-items: center;
    padding: 0 10%;
}

.shutter-lock-bolt {
    width: 18px;
    height: 18px;
    background: radial-gradient(circle, #f8fafc 20%, #64748b 80%);
    border-radius: 50%;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.8), 0 0 6px rgba(255,255,255,0.4);
}

/* 3D Character & Store Badge Overlay */
.character-3d-wrapper {
    position: absolute;
    bottom: 48px;
    display: flex;
    flex-direction: column;
    align-items: center;
    z-index: 100;
}

/* Modern Glowing Badge */
.character-3d-badge {
    background: linear-gradient(135deg, #1e1b4b 0%, #431407 100%);
    border: 2px solid #f59e0b;
    color: #fbbf24;
    font-weight: 900;
    font-size: 13px;
    padding: 8px 26px;
    border-radius: 30px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.5), 0 0 15px rgba(245, 158, 11, 0.4);
    margin-bottom: 12px;
    letter-spacing: 2px;
    text-transform: uppercase;
    text-shadow: 0 2px 4px rgba(0,0,0,0.8);
}

/* SVG Character Animation */
.character-3d-svg {
    width: 140px;
    height: 140px;
    filter: drop-shadow(0 20px 25px rgba(0,0,0,0.6));
    animation: characterMotion 1.4s infinite alternate ease-in-out;
}

@keyframes characterMotion {
    0% { transform: translateY(0px) scale(1); }
    100% { transform: translateY(-10px) scale(1.02); }
}

/* Realistic Chrome Handle Bar */
.shutter-handle-bar {
    width: 320px;
    height: 32px;
    background: linear-gradient(180deg, #ffffff 0%, #cbd5e1 30%, #475569 70%, #0f172a 100%);
    border-radius: 12px;
    border: 2px solid #94a3b8;
    box-shadow: 0 12px 25px rgba(0,0,0,0.7), inset 0 2px 2px rgba(255,255,255,0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 900;
    color: #0f172a;
    letter-spacing: 2.5px;
    margin-top: -12px;
    text-shadow: 0 1px 0 rgba(255,255,255,0.8);
}
</style>

<!-- 🏬 REALISTIC SHUTTER SOUNDS & CONTROLLER -->
<script>
function playLockClickSound() {
    try {
        var AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        var ctx = new AudioContext();
        var osc = ctx.createOscillator();
        var gain = ctx.createGain();

        osc.type = 'triangle';
        osc.frequency.setValueAtTime(1200, ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(200, ctx.currentTime + 0.1);

        gain.gain.setValueAtTime(0.4, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.1);

        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.11);
    } catch (e) {}
}

function playRollingShutterSound() {
    try {
        var AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        var ctx = new AudioContext();

        var bufferSize = ctx.sampleRate * 1.3;
        var buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
        var data = buffer.getChannelData(0);

        for (var i = 0; i < bufferSize; i++) {
            data[i] = (Math.random() * 2 - 1) * 0.8;
        }

        var noise = ctx.createBufferSource();
        noise.buffer = buffer;

        var filter = ctx.createBiquadFilter();
        filter.type = 'bandpass';
        filter.frequency.setValueAtTime(350, ctx.currentTime);
        filter.frequency.linearRampToValueAtTime(850, ctx.currentTime + 1.2);
        filter.Q.value = 3.0;

        var gain = ctx.createGain();
        gain.gain.setValueAtTime(0.4, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 1.3);

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
        if (statusText) statusText.innerText = "⚡ STORE OPENING...";
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
        if (statusText) statusText.innerText = "🔒 STORE CLOSING...";
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

<!-- 🏬 SUPER PREMIUM 3D METALLIC SHUTTER OVERLAY -->
<div id="shopShutter" class="shutter-overlay shutter-hidden">
    <div class="character-3d-wrapper">
        <div id="shutterStatusBadge" class="character-3d-badge">⚡ STORE OPENING...</div>
        
        <!-- Modern High-Detail 3D Business Avatar SVG -->
        <svg class="character-3d-svg" viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <radialGradient id="headGlow" cx="50%" cy="30%" r="70%">
                    <stop offset="0%" stop-color="#ffedd5"/>
                    <stop offset="70%" stop-color="#fdba74"/>
                    <stop offset="100%" stop-color="#c2410c"/>
                </radialGradient>
                <linearGradient id="suitGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#1e1b4b"/>
                    <stop offset="50%" stop-color="#312e81"/>
                    <stop offset="100%" stop-color="#0f172a"/>
                </linearGradient>
                <linearGradient id="tieGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" stop-color="#ef4444"/>
                    <stop offset="100%" stop-color="#991b1b"/>
                </linearGradient>
                <linearGradient id="chromeGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#ffffff"/>
                    <stop offset="50%" stop-color="#94a3b8"/>
                    <stop offset="100%" stop-color="#334155"/>
                </linearGradient>
            </defs>
            <!-- Shadow -->
            <ellipse cx="60" cy="115" rx="35" ry="5" fill="rgba(0,0,0,0.4)" />
            <!-- Hair/Cap -->
            <path d="M 32,42 Q 60,10 88,42 Q 92,20 60,12 Q 28,20 32,42 Z" fill="#0f172a" />
            <!-- Head -->
            <circle cx="60" cy="48" r="22" fill="url(#headGlow)" stroke="#ea580c" stroke-width="0.5"/>
            <!-- Glasses -->
            <rect x="40" y="40" width="16" height="12" rx="4" fill="rgba(15,23,42,0.85)" stroke="#fbbf24" stroke-width="2"/>
            <rect x="64" y="40" width="16" height="12" rx="4" fill="rgba(15,23,42,0.85)" stroke="#fbbf24" stroke-width="2"/>
            <line x1="56" y1="45" x2="64" y2="45" stroke="#fbbf24" stroke-width="2.5"/>
            <!-- Smile -->
            <path d="M 50,58 Q 60,66 70,58" stroke="#7c2d12" stroke-width="3" fill="none" stroke-linecap="round"/>
            <!-- Suit Body -->
            <path d="M 28,74 Q 60,64 92,74 L 100,115 L 20,115 Z" fill="url(#suitGrad)" />
            <!-- Shirt & Gold/Red Tie -->
            <polygon points="50,72 70,72 60,92" fill="#ffffff" />
            <polygon points="56,74 64,74 66,104 60,112 54,104" fill="url(#tieGrad)" />
            <!-- Hands Opening Gate -->
            <path d="M 22,82 Q 8,50 22,30" stroke="url(#headGlow)" stroke-width="9" stroke-linecap="round" fill="none"/>
            <path d="M 98,82 Q 112,50 98,30" stroke="url(#headGlow)" stroke-width="9" stroke-linecap="round" fill="none"/>
            <circle cx="22" cy="28" r="7" fill="url(#chromeGrad)"/>
            <circle cx="98" cy="28" r="7" fill="url(#chromeGrad)"/>
        </svg>

        <div class="shutter-handle-bar">
            <i class="fa-solid fa-store" style="margin-right: 8px; color: #d97706;"></i> STORE FRONT GATE
        </div>
    </div>

    <!-- Bottom Lock Rail Bolts -->
    <div class="shutter-bottom-rail">
        <div class="shutter-lock-bolt"></div>
        <div class="shutter-lock-bolt"></div>
        <div class="shutter-lock-bolt"></div>
        <div class="shutter-lock-bolt"></div>
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
