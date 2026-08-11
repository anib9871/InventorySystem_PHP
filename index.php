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
  /* Fullscreen Fixed Light Slate Background */
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
      background: rgba(168, 0, 0, 0.05);
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
      background: rgba(19, 28, 42, 0.05);
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
      padding: 42px 36px;
      background: #ffffff;
      border: 1px solid #cbd5e1;
      border-radius: 20px;
      box-shadow: 0 15px 35px rgba(15, 23, 42, 0.08);
      color: #0f172a;
      box-sizing: border-box;
      z-index: 2;
  }

  .login-page-card h1 {
      font-size: 26px;
      font-weight: 800;
      color: #0f172a;
      margin: 0 0 4px 0;
  }

  .login-page-card h4 {
      color: #64748b;
      font-size: 13px;
      margin-top: 0;
      margin-bottom: 25px;
  }

  .form-group {
      margin-bottom: 20px;
      text-align: left;
  }

  .form-group label {
      display: block;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: #334155;
      margin-bottom: 8px;
  }

  .input-wrapper {
      position: relative;
  }

  .input-wrapper i {
      position: absolute;
      left: 16px;
      top: 50%;
      transform: translateY(-50%);
      color: #94a3b8;
      font-size: 15px;
  }

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

  .form-control:focus {
      border-color: #a80000 !important;
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
      box-shadow: 0 8px 18px rgba(168, 0, 0, 0.25);
      transition: all 0.3s ease;
  }

  .btn-theme:hover {
      background: #8e0000;
  }

  /* 🏬 ULTRA-PREMIUM METALLIC SHUTTER OVERLAY */
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
              #f1f5f9 0px,
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
      transform: translateY(0%);
      transition: transform 1.2s cubic-bezier(0.77, 0, 0.175, 1);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-end;
      pointer-events: none;
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
      box-shadow: 0 10px 25px rgba(0,0,0,0.5), 0 0 15px rgba(245, 158, 11, 0.4);
      margin-bottom: 12px;
      letter-spacing: 2px;
      text-transform: uppercase;
      text-shadow: 0 2px 4px rgba(0,0,0,0.8);
  }

  .character-3d-svg {
      width: 130px;
      height: 130px;
      filter: drop-shadow(0 20px 25px rgba(0,0,0,0.6));
      animation: characterMotion 1.4s infinite alternate ease-in-out;
  }

  @keyframes characterMotion {
      0% { transform: translateY(0px) scale(1); }
      100% { transform: translateY(-8px) scale(1.02); }
  }

  .shutter-handle-bar {
      width: 310px;
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
</style>

<!-- 🏬 SHUTTER OVERLAY -->
<div id="loginShutter" class="shutter-overlay">
    <div class="character-3d-wrapper">
        <div id="shutterStatusBadge" class="character-3d-badge">⚡ OPENING STORE...</div>
        
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
            <ellipse cx="60" cy="115" rx="35" ry="5" fill="rgba(0,0,0,0.4)" />
            <path d="M 32,42 Q 60,10 88,42 Q 92,20 60,12 Q 28,20 32,42 Z" fill="#0f172a" />
            <circle cx="60" cy="48" r="22" fill="url(#headGlow)" stroke="#ea580c" stroke-width="0.5"/>
            <rect x="40" y="40" width="16" height="12" rx="4" fill="rgba(15,23,42,0.85)" stroke="#fbbf24" stroke-width="2"/>
            <rect x="64" y="40" width="16" height="12" rx="4" fill="rgba(15,23,42,0.85)" stroke="#fbbf24" stroke-width="2"/>
            <line x1="56" y1="45" x2="64" y2="45" stroke="#fbbf24" stroke-width="2.5"/>
            <path d="M 50,58 Q 60,66 70,58" stroke="#7c2d12" stroke-width="3" fill="none" stroke-linecap="round"/>
            <path d="M 28,74 Q 60,64 92,74 L 100,115 L 20,115 Z" fill="url(#suitGrad)" />
            <polygon points="50,72 70,72 60,92" fill="#ffffff" />
            <polygon points="56,74 64,74 66,104 60,112 54,104" fill="url(#tieGrad)" />
            <path d="M 22,82 Q 8,50 22,30" stroke="url(#headGlow)" stroke-width="9" stroke-linecap="round" fill="none"/>
            <path d="M 98,82 Q 112,50 98,30" stroke="url(#headGlow)" stroke-width="9" stroke-linecap="round" fill="none"/>
            <circle cx="22" cy="28" r="7" fill="url(#chromeGrad)"/>
            <circle cx="98" cy="28" r="7" fill="url(#chromeGrad)"/>
        </svg>

        <div class="shutter-handle-bar">
            <i class="fa-solid fa-store" style="margin-right: 8px; color: #d97706;"></i> STORE FRONT GATE
        </div>
    </div>

    <div class="shutter-bottom-rail">
        <div class="shutter-lock-bolt"></div>
        <div class="shutter-lock-bolt"></div>
        <div class="shutter-lock-bolt"></div>
        <div class="shutter-lock-bolt"></div>
    </div>
</div>

<div class="login-wrapper-fixed">
    <div class="glow-circle-1"></div>
    <div class="glow-circle-2"></div>

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

        gain.gain.setValueAtTime(0.5, ctx.currentTime);
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

        var bufferSize = ctx.sampleRate * 1.2;
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
        filter.frequency.linearRampToValueAtTime(850, ctx.currentTime + 1.1);
        filter.Q.value = 3.0;

        var gain = ctx.createGain();
        gain.gain.setValueAtTime(0.4, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 1.2);

        noise.connect(filter);
        filter.connect(gain);
        gain.connect(ctx.destination);

        noise.start();
    } catch (e) {}
}

window.addEventListener("DOMContentLoaded", function() {
    if (typeof(Storage) !== "undefined") {
        sessionStorage.clear();
    }

    var form = document.getElementById("loginForm");
    if (form) {
        form.addEventListener("submit", function(e) {
            if (!form.checkValidity()) return;

            e.preventDefault();

            var btn = document.getElementById("submitBtn");
            var shutter = document.getElementById("loginShutter");

            btn.innerHTML = 'Authenticating... <i class="fa-solid fa-spinner fa-spin" style="margin-left:8px;"></i>';

            if (shutter) {
                playLockClickSound();

                setTimeout(function() {
                    playRollingShutterSound();
                    shutter.classList.add("shutter-open");
                }, 150);

                setTimeout(function() {
                    form.submit();
                }, 1100);
            } else {
                form.submit();
            }
        });
    }

    var urlParams = new URLSearchParams(window.location.search);
    var msg = urlParams.get('msg');

    if (msg === 'updated') {
        Swal.fire({
            icon: 'info',
            title: '🚀 System Updated!',
            html: 'Your session expired because a <b>new system update</b> was deployed.<br><br>Please log in again.',
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
