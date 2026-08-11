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

<title>Inventory Management System</title>

<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css"/>
<link rel="stylesheet" href="libs/css/main.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
.shutter-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: repeating-linear-gradient(180deg, #cbd5e1 0px, #475569 10px, #0f172a 20px);
    border-bottom: 28px solid #0f172a;
    z-index: 9999999;
    transition: transform 1.2s cubic-bezier(0.77, 0, 0.175, 1);
    transform: translateY(-100%);
    display: flex;
    align-items: flex-end;
    justify-content: center;
}

.shutter-overlay.shutter-close {
    transform: translateY(0%) !important;
}

.shutter-handle-bar {
    width: 310px;
    height: 32px;
    background: #ffffff;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 900;
    margin-bottom: 30px;
}
</style>

<script>
function playHeavyRollingSound() {
    try {
        var AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        var ctx = new AudioContext();

        var osc = ctx.createOscillator();
        var gain = ctx.createGain();
        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(140, ctx.currentTime);
        osc.frequency.linearRampToValueAtTime(50, ctx.currentTime + 1.2);

        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 1.2);

        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 1.2);
    } catch (e) {}
}

function animateLogout(e) {
    e.preventDefault();
    var shutter = document.getElementById("shopShutter");
    var targetUrl = e.currentTarget.href;

    if (shutter) {
        shutter.classList.add("shutter-close");
        playHeavyRollingSound();

        setTimeout(function() {
            window.location.href = targetUrl;
        }, 1100);
    } else {
        window.location.href = targetUrl;
    }
}
</script>
</head>
<body>

<div id="shopShutter" class="shutter-overlay">
    <div class="shutter-handle-bar">🔒 STORE CLOSING...</div>
</div>

<?php if(isset($_SESSION['username'])): ?>
<header id="header">
  <div class="logo pull-left">INVENTORY SYSTEM</div>
  <div class="pull-right">
      <a href="logout.php" onclick="animateLogout(event)" class="btn btn-danger" style="margin:10px;">Logout</a>
  </div>
</header>
<?php endif; ?>

<div class="page">
<div class="container-fluid">
