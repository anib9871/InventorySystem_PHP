<?php
  ob_start();
  require_once('includes/load.php');
  if($session->isUserLoggedIn(true)) { redirect('home.php', false);}
?>
<?php include_once('layouts/header.php'); ?>

<!-- FontAwesome Icons & SweetAlert2 -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
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

  .login-page-card {
      position: relative;
      width: 100%;
      max-width: 410px;
      padding: 42px 36px;
      background: #ffffff;
      border: 1px solid #cbd5e1;
      border-radius: 20px;
      box-shadow: 0 15px 35px rgba(15, 23, 42, 0.08);
      color: #0f172a;
      box-sizing: border-box;
      z-index: 2;
  }

  .login-page-card h1 { font-size: 26px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0; }
  .login-page-card h4 { color: #64748b; font-size: 13px; margin-top: 0; margin-bottom: 25px; }

  .form-group { margin-bottom: 20px; text-align: left; }
  .form-group label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #334155; margin-bottom: 8px; }
  .input-wrapper { position: relative; }
  .input-wrapper i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 15px; }

  .form-control {
      width: 100%;
      height: 48px;
      background: #f8fafc !important;
      border: 1.5px solid #cbd5e1 !important;
      border-radius: 10px !important;
      padding: 0 15px 0 48px !important;
      color: #0f172a !important;
      font-size: 14px;
      outline: none;
      box-sizing: border-box;
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
      box-shadow: 0 8px 18px rgba(168, 0, 0, 0.25);
      transition: all 0.3s ease;
  }

  /* 🏬 FULL COVER HEAVY METALLIC SHUTTER OVERLAY */
  .shutter-overlay {
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
      box-shadow: 0 25px 50px rgba(0,0,0,0.9), inset 0 -15px 30px rgba(0,0,0,0.8);
      z-index: 9999999;
      transform: translateY(0%); /* Full Close By Default */
      transition: transform 1.25s cubic-bezier(0.77, 0, 0.175, 1);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-end;
  }

  .shutter-overlay.shutter-open {
      transform: translateY(-100%) !important;
  }

  .character-3d-wrapper {
      position: absolute;
      bottom: 48px;
      display: flex;
      flex-direction: column;
      align-items: center;
      z-index: 100;
  }

  .character-3d-badge {
      background: linear-gradient(135deg, #1e1b4b 0%, #431407 100%);
      border: 2px solid #f59e0b;
      color: #fbbf24;
      font-weight: 900;
      font-size: 13px;
      padding: 8px 26px;
      border-radius: 30px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.5);
      margin-bottom: 12px;
      letter-spacing: 2px;
      text-transform: uppercase;
  }

  .character-3d-svg {
      width: 135px;
      height: 135px;
      filter: drop-shadow(0 20px 25px rgba(0,0,0,0.6));
  }

  .shutter-handle-bar {
      width: 310px;
      height: 32px;
      background: linear-gradient(180deg, #ffffff 0%, #cbd5e1 30%, #475569 70%, #0f172a 100%);
      border-radius: 12px;
      border: 2px solid #94a3b8;
      box-shadow: 0 12px 25px rgba(0,0,0,0.7);
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

<!-- 🏬 FULL SCREEN SHUTTER -->
<div id="loginShutter" class="shutter-overlay">
    <div class="character-3d-wrapper">
        <div id="shutterStatusBadge" class="character-3d-badge">⚡ AUTHENTICATING & OPENING...</div>
        
        <svg class="character-3d-svg" viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <radialGradient id="headGlow" cx="50%" cy="30%" r="70%">
                    <stop offset="0%" stop-color="#ffedd5"/>
                    <stop offset="100%" stop-color="#c2410c"/>
                </radialGradient>
            </defs>
            <circle cx="60" cy="48" r="22" fill="url(#headGlow)"/>
            <rect x="40" y="40" width="16" height="12" rx="4" fill="#0f172a" stroke="#fbbf24" stroke-width="2"/>
            <rect x="64" y="40" width="16" height="12" rx="4" fill="#0f172a" stroke="#fbbf24" stroke-width="2"/>
            <path d="M 28,74 Q 60,64 92,74 L 100,115 L 20,115 Z" fill="#1e1b4b" />
        </svg>

        <div class="shutter-handle-bar">
            <i class="fa-solid fa-store" style="margin-right: 8px; color: #d97706;"></i> STORE FRONT GATE
        </div>
    </div>
</div>

<div class="login-wrapper-fixed">
    <div class="login-page-card">
        <div class="text-center">
           <h1>Login Panel</h1>
           <h4>Inventory Management System</h4>
        </div>

        <?php echo display_msg($msg); ?>

        <form id="loginForm" method="post" action="auth_v2.php" class="clearfix">
            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-wrapper">
                    <input type="text" class="form-control" name="username" placeholder="Username" required autocomplete="off">
                    <i class="fa-solid fa-user"></i>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    <i class="fa-solid fa-lock"></i>
                </div>
            </div>

            <div class="text-center">
                <button type="submit" id="submitBtn" class="btn-theme">
                    Login <i class="fa-solid fa-arrow-right" style="margin-left: 8px;"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 🔊 HEAVY METALLIC SOUND & SYNC CONTROLLER -->
<script>
function playHeavyRollingSound() {
    try {
        var AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        var ctx = new AudioContext();

        // Heavy Bass Metal Rumble
        var osc = ctx.createOscillator();
        var gain = ctx.createGain();
        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(60, ctx.currentTime);
        osc.frequency.linearRampToValueAtTime(140, ctx.currentTime + 1.2);

        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 1.2);

        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 1.2);
    } catch (e) {}
}

window.addEventListener("DOMContentLoaded", function() {
    var form = document.getElementById("loginForm");
    var shutter = document.getElementById("loginShutter");

    // Default pe shutter hatado taaki username/password enter kar sake
    setTimeout(function(){
        if(shutter) shutter.classList.add("shutter-open");
    }, 100);

    if (form) {
        form.addEventListener("submit", function(e) {
            if (!form.checkValidity()) return;

            e.preventDefault();

            var btn = document.getElementById("submitBtn");
            btn.innerHTML = 'Authenticating... <i class="fa-solid fa-spinner fa-spin"></i>';

            if (shutter) {
                // Pehle Close then Open Effect WITH HEAVY AUDIO
                shutter.classList.remove("shutter-open");
                playHeavyRollingSound();

                setTimeout(function() {
                    shutter.classList.add("shutter-open");
                }, 400);

                setTimeout(function() {
                    form.submit();
                }, 1200);
            } else {
                form.submit();
            }
        });
    }
});
</script>

<?php include_once('layouts/footer.php'); ?>
