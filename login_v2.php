<?php
  ob_start();
  require_once('includes/load.php');

  /* SUPERADMIN ALREADY LOGIN */
  if(isset($_SESSION['superadmin_login'])){
    header("Location: superadmin_dashboard.php");
    exit();
  }

  /* NORMAL USER ALREADY LOGIN */
  if($session->isUserLoggedIn(true)){
    redirect('home.php', false);
  }

  // Flag so header.php knows NOT to show duplicate shutter overlay here
  $is_login_page = true;
?>

<?php include_once('layouts/header.php'); ?>

<!-- FontAwesome Icons & SweetAlert2 -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
  /* Fullscreen Fixed Light Background */
  .login-wrapper-fixed {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1;
      background: #f1f5f9;
      overflow: hidden;
      font-family: 'Segoe UI', Roboto, sans-serif;
  }

  .glow-circle-1 {
      position: absolute;
      width: 380px;
      height: 380px;
      background: rgba(168, 0, 0, 0.08);
      border-radius: 50%;
      filter: blur(90px);
      top: 15%;
      left: 25%;
      pointer-events: none;
  }

  .glow-circle-2 {
      position: absolute;
      width: 420px;
      height: 420px;
      background: rgba(19, 28, 42, 0.08);
      border-radius: 50%;
      filter: blur(100px);
      bottom: 15%;
      right: 25%;
      pointer-events: none;
  }

  .login-page-card {
      position: relative;
      width: 100%;
      max-width: 410px;
      padding: 45px 36px;
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 18px;
      box-shadow: 0 20px 40px -15px rgba(19, 28, 42, 0.1);
      color: #131c2a;
      box-sizing: border-box;
      z-index: 2;
  }

  .login-page-card h1 { font-size: 28px; font-weight: 800; color: #131c2a; margin-bottom: 6px; }
  .login-page-card p { color: #64748b; font-size: 14px; margin-bottom: 32px; }

  .form-group { margin-bottom: 22px; text-align: left; }
  .form-group label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #131c2a; margin-bottom: 8px; }

  .input-wrapper { position: relative; }
  .input-wrapper i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 15px; }

  .form-control {
      width: 100%;
      height: 48px;
      background: #f8fafc !important;
      border: 1.5px solid #cbd5e1 !important;
      border-radius: 10px !important;
      padding: 0 15px 0 48px !important;
      color: #131c2a !important;
      font-size: 14px;
      outline: none;
      box-sizing: border-box;
  }

  .form-control:focus {
      border-color: #a80000 !important;
      background: #ffffff !important;
      box-shadow: 0 0 0 4px rgba(168, 0, 0, 0.12) !important;
  }

  .btn-theme {
      width: 100%;
      height: 48px;
      margin-top: 10px;
      border: none;
      border-radius: 10px;
      background: #a80000;
      color: #ffffff;
      font-size: 14px;
      font-weight: 700;
      letter-spacing: 1px;
      text-transform: uppercase;
      cursor: pointer;
      box-shadow: 0 8px 18px rgba(168, 0, 0, 0.28);
      transition: all 0.3s ease;
  }

  /* 🏬 SINGLE SHUTTER OVERLAY FOR LOGIN_V2 */
  .shutter-overlay-v2 {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background: 
          linear-gradient(115deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.08) 45%, rgba(255,255,255,0.25) 50%, rgba(255,255,255,0.08) 55%, rgba(255,255,255,0) 100%),
          linear-gradient(90deg, rgba(0,0,0,0.6) 0%, rgba(0,0,0,0.1) 6%, transparent 12%, transparent 88%, rgba(0,0,0,0.1) 94%, rgba(0,0,0,0.6) 100%),
          repeating-linear-gradient(
              180deg,
              #cbd5e1 0px,
              #94a3b8 4px,
              #475569 10px,
              #1e293b 16px,
              #0f172a 22px,
              #334155 26px
          );
      border-bottom: 28px solid #0f172a;
      box-shadow: 0 25px 50px rgba(0,0,0,0.9);
      z-index: 99999999;
      transform: translateY(-100%); /* Default Open so form is visible */
      transition: transform 1.2s cubic-bezier(0.77, 0, 0.175, 1);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-end;
      pointer-events: none;
  }

  .shutter-overlay-v2.shutter-closed {
      transform: translateY(0%) !important; /* Close on Login click */
      pointer-events: all;
  }

  .character-3d-wrapper {
      position: absolute;
      bottom: 48px;
      display: flex;
      flex-direction: column;
      align-items: center;
  }

  .character-3d-badge {
      background: linear-gradient(135deg, #1e1b4b 0%, #431407 100%);
      border: 2px solid #f59e0b;
      color: #fbbf24;
      font-weight: 900;
      font-size: 13px;
      padding: 8px 26px;
      border-radius: 30px;
      margin-bottom: 12px;
      letter-spacing: 2px;
      text-transform: uppercase;
  }

  .shutter-handle-bar {
      width: 310px;
      height: 32px;
      background: linear-gradient(180deg, #ffffff 0%, #cbd5e1 30%, #475569 70%, #0f172a 100%);
      border-radius: 12px;
      border: 2px solid #94a3b8;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: 900;
      color: #0f172a;
      letter-spacing: 2.5px;
      margin-top: -12px;
  }
</style>

<!-- 🏬 SHUTTER OVERLAY -->
<div id="v2Shutter" class="shutter-overlay-v2">
    <div class="character-3d-wrapper">
        <div id="shutterStatusBadge" class="character-3d-badge">⚡ AUTHENTICATING & OPENING STORE...</div>
        <div class="shutter-handle-bar">
            <i class="fa-solid fa-store" style="margin-right: 8px; color: #d97706;"></i> STORE FRONT GATE
        </div>
    </div>
</div>

<div class="login-wrapper-fixed">
    <div class="glow-circle-1"></div>
    <div class="glow-circle-2"></div>

    <div class="login-page-card">
        <div class="text-center">
           <h1>Welcome Back</h1>
           <p>Sign in to start your session</p>
        </div>

        <?php echo display_msg($msg); ?>

        <form id="loginFormV2" method="post" action="auth_v2.php">

            <div class="form-group">
                <label>Username</label>
                <div class="input-wrapper">
                    <input type="text" class="form-control" name="username" placeholder="Enter username" required autocomplete="off">
                    <i class="fa-solid fa-user"></i>
                </div>
            </div>

            <div class="form-group">
                <label>Password</label>
                <div class="input-wrapper">
                    <input type="password" class="form-control" name="password" placeholder="••••••••" required>
                    <i class="fa-solid fa-lock"></i>
                </div>
            </div>

            <div class="text-center">
                <button type="submit" id="submitBtnV2" class="btn-theme">
                    Login <i class="fa-solid fa-arrow-right" style="margin-left: 8px;"></i>
                </button>
            </div>

        </form>
    </div>
</div>

<!-- 🔊 HEAVY SOUND & ANIMATION SCRIPT -->
<script>
function playHeavyRollingSound() {
    try {
        var AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        var ctx = new AudioContext();

        var osc = ctx.createOscillator();
        var gain = ctx.createGain();
        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(60, ctx.currentTime);
        osc.frequency.linearRampToValueAtTime(140, ctx.currentTime + 1.2);

        gain.gain.setValueAtTime(0.35, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 1.2);

        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 1.2);
    } catch (e) {}
}

window.addEventListener("DOMContentLoaded", function() {
    var form = document.getElementById("loginFormV2");
    var shutter = document.getElementById("v2Shutter");

    if (form) {
        form.addEventListener("submit", function(e) {
            if (!form.checkValidity()) return;

            e.preventDefault();

            var btn = document.getElementById("submitBtnV2");
            btn.innerHTML = 'Authenticating... <i class="fa-solid fa-spinner fa-spin"></i>';

            if (shutter) {
                // Shutter rolls down with heavy audio, then submits
                shutter.classList.add("shutter-closed");
                playHeavyRollingSound();

                setTimeout(function() {
                    form.submit();
                }, 1100);
            } else {
                form.submit();
            }
        });
    }

    // SweetAlert handling
    var urlParams = new URLSearchParams(window.location.search);
    var msg = urlParams.get('msg');

    if (msg === 'updated') {
        Swal.fire({
            icon: 'info',
            title: '🚀 System Updated!',
            text: 'A new update was deployed. Please log in again.',
            confirmButtonText: 'OK, Login',
            confirmButtonColor: '#a80000'
        });
    } 
    else if (msg === 'session_expired' || msg === 'tab_closed') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'warning',
            title: 'Session Expired!',
            text: 'Please log in again to continue.',
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true
        });
    }
});
</script>

<?php include_once('layouts/footer.php'); ?>
