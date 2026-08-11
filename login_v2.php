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
?>
<?php include_once('layouts/header.php'); ?>

<!-- FontAwesome Icons & SweetAlert2 -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }

  /* 🚫 Lock Screen Page - Absolute No Scroll */
  html, body {
      width: 100vw;
      height: 100vh;
      overflow: hidden !important;
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
      background: #0f172a;
  }

  .login-container {
      width: 100vw;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      position: fixed;
      top: 0;
      left: 0;
      z-index: 99999;
      background: radial-gradient(circle at 80% 20%, #1e1b4b 0%, #0f172a 60%, #020617 100%);
  }

  /* Ambient Lights */
  .bg-glow-1 {
      position: absolute;
      width: 500px;
      height: 500px;
      background: rgba(99, 102, 241, 0.15);
      border-radius: 50%;
      filter: blur(120px);
      top: -10%;
      right: 10%;
      pointer-events: none;
  }

  .bg-glow-2 {
      position: absolute;
      width: 450px;
      height: 450px;
      background: rgba(168, 85, 247, 0.12);
      border-radius: 50%;
      filter: blur(120px);
      bottom: -10%;
      left: 10%;
      pointer-events: none;
  }

  /* Glassmorphism Frame (Fixed Height = No Scroll) */
  .main-card-frame {
      position: relative;
      width: 100%;
      max-width: 900px;
      height: 500px;
      background: rgba(30, 41, 59, 0.7);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border-radius: 24px;
      box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.6), inset 0 1px 1px rgba(255, 255, 255, 0.1);
      border: 1px solid rgba(255, 255, 255, 0.08);
      display: flex;
      overflow: hidden;
      z-index: 10;
  }

  .form-section {
      flex: 1;
      padding: 40px 45px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      z-index: 2;
  }

  .brand-header {
      margin-bottom: 25px;
  }

  .brand-header h2 {
      font-size: 28px;
      font-weight: 800;
      color: #f8fafc;
      letter-spacing: -0.5px;
      display: flex;
      align-items: center;
      gap: 12px;
  }

  .brand-header p {
      font-size: 13px;
      color: #94a3b8;
      margin-top: 4px;
  }

  .form-group {
      margin-bottom: 18px;
      text-align: left;
  }

  .form-group label {
      display: block;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: #cbd5e1;
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
      color: #64748b;
      font-size: 15px;
      transition: color 0.3s ease;
  }

  .form-control {
      width: 100%;
      height: 46px;
      background: rgba(15, 23, 42, 0.6) !important;
      border: 1.5px solid rgba(51, 65, 85, 0.8) !important;
      border-radius: 12px !important;
      padding: 0 15px 0 48px !important;
      color: #f8fafc !important;
      font-size: 14px;
      outline: none;
      transition: all 0.3s ease;
  }

  .form-control:focus {
      border-color: #6366f1 !important;
      background: rgba(15, 23, 42, 0.9) !important;
      box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.2) !important;
  }

  .form-control:focus + i {
      color: #818cf8;
  }

  .btn-submit {
      width: 100%;
      height: 48px;
      margin-top: 8px;
      border: none;
      border-radius: 12px;
      background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
      color: #ffffff;
      font-size: 14px;
      font-weight: 700;
      letter-spacing: 0.8px;
      text-transform: uppercase;
      cursor: pointer;
      box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.5);
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
  }

  .btn-submit:hover {
      transform: translateY(-2px);
      box-shadow: 0 14px 28px -5px rgba(99, 102, 241, 0.6);
      background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
  }

  .illustration-section {
      flex: 1.1;
      background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
      position: relative;
      display: flex;
      align-items: flex-end;
      justify-content: center;
      padding: 20px;
      overflow: hidden;
  }

  .illustration-backdrop {
      position: absolute;
      width: 320px;
      height: 320px;
      background: rgba(99, 102, 241, 0.25);
      border-radius: 50%;
      top: 10%;
      right: -30px;
      filter: blur(40px);
  }

  .pos-vector-art {
      width: 100%;
      max-width: 380px;
      height: auto;
      z-index: 5;
      filter: drop-shadow(0 15px 30px rgba(0, 0, 0, 0.4));
  }

  .anim-character-body {
      animation: characterBreathe 3.5s infinite ease-in-out;
      transform-origin: bottom center;
  }

  @keyframes characterBreathe {
      0%, 100% { transform: translateY(0px) rotate(0deg); }
      50% { transform: translateY(-4px) rotate(0.5deg); }
  }

  .anim-eyes {
      animation: eyeBlink 4s infinite;
      transform-origin: center;
  }

  @keyframes eyeBlink {
      0%, 94%, 98%, 100% { transform: scaleY(1); }
      96% { transform: scaleY(0.1); }
  }

  .anim-hand {
      animation: handType 2s infinite alternate ease-in-out;
      transform-origin: 210px 170px;
  }

  @keyframes handType {
      0% { transform: rotate(0deg) translateY(0); }
      100% { transform: rotate(-3deg) translateY(-3px); }
  }

  .anim-pos-screen {
      animation: screenPulse 2.5s infinite alternate ease-in-out;
  }

  @keyframes screenPulse {
      0% { fill: #0f172a; opacity: 0.95; }
      100% { fill: #1e1b4b; opacity: 1; filter: drop-shadow(0 0 10px rgba(129, 140, 248, 0.8)); }
  }

  .anim-pos-line {
      animation: lineScan 2s infinite linear;
  }

  @keyframes lineScan {
      0% { transform: translateY(-10px); opacity: 0; }
      50% { opacity: 0.8; }
      100% { transform: translateY(20px); opacity: 0; }
  }

  @media (max-width: 768px) {
      .main-card-frame { flex-direction: column-reverse; height: auto; max-height: 90vh; }
      .illustration-section { padding: 15px; min-height: 180px; }
      .pos-vector-art { max-width: 240px; }
      .form-section { padding: 25px 20px; }
  }
</style>

<div class="login-container">
    <div class="bg-glow-1"></div>
    <div class="bg-glow-2"></div>

    <div class="main-card-frame">

        <!-- LEFT COLUMN: LOGIN FORM -->
        <div class="form-section">
            <div class="brand-header">
                <h2><i class="fa-solid fa-store" style="color:#818cf8;"></i> Storly</h2>
                <p>Online Inventory & Store Management System</p>
            </div>

            <?php if(function_exists('display_msg')) { echo display_msg($msg); } ?>

            <form id="loginFormV2" method="post" action="auth_v2.php">

                <div class="form-group">
                    <label for="username">Username / Email</label>
                    <div class="input-wrapper">
                        <input type="text" class="form-control" id="usernameInput" name="username" placeholder="Enter username" required autocomplete="off">
                        <i class="fa-solid fa-user"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <input type="password" class="form-control" id="passwordInput" name="password" placeholder="••••••••" required>
                        <i class="fa-solid fa-lock"></i>
                    </div>
                </div>

                <button type="submit" id="submitBtnV2" class="btn-submit">
                    Log In <i class="fa-solid fa-arrow-right"></i>
                </button>

            </form>
        </div>

        <!-- RIGHT COLUMN: LIVE ANIMATED VECTOR ART -->
        <div class="illustration-section">
            <div class="illustration-backdrop"></div>

            <svg class="pos-vector-art" viewBox="0 0 400 320" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M50 220 Q 20 180 60 140 Q 120 100 200 130 Q 280 90 350 150 Q 390 200 350 250 Z" fill="#312e81" opacity="0.6"/>

                <g class="anim-character-body">
                    <path d="M 240 180 Q 280 150 320 180 L 340 300 L 220 300 Z" fill="#4f46e5" />
                    <polygon points="270,180 290,180 280,210" fill="#ffffff" />
                    <polygon points="260,180 270,180 275,200" fill="#818cf8" />
                    <polygon points="290,180 300,180 285,200" fill="#818cf8" />

                    <rect x="272" y="160" width="16" height="22" rx="4" fill="#fed7aa" />
                    <path d="M 255 140 C 255 115, 305 115, 305 140 C 305 165, 255 165, 255 140 Z" fill="#fed7aa" />

                    <path d="M 250 135 Q 260 95 295 105 Q 315 115 310 135 Q 295 120 275 125 Z" fill="#0f172a" />

                    <g class="anim-eyes">
                        <circle cx="270" cy="138" r="3" fill="#0f172a" />
                        <circle cx="288" cy="138" r="3" fill="#0f172a" />
                    </g>
                    <path d="M 272 150 Q 279 156 286 150" stroke="#0f172a" stroke-width="2" stroke-linecap="round" fill="none" />

                    <g class="anim-hand">
                        <path d="M 240 200 Q 200 195 175 200" stroke="#fed7aa" stroke-width="12" stroke-linecap="round" fill="none" />
                        <circle cx="172" cy="200" r="7" fill="#fed7aa" />
                    </g>
                </g>

                <rect x="180" y="240" width="200" height="70" rx="8" fill="#334155" />
                <rect x="175" y="235" width="210" height="12" rx="4" fill="#475569" />
                <rect x="175" y="247" width="210" height="4" fill="#1e293b" />

                <rect x="200" y="210" width="55" height="26" rx="4" fill="#1e293b" />
                <rect x="205" y="200" width="45" height="12" rx="2" fill="#0f172a" />
                <rect x="222" y="185" width="10" height="18" fill="#475569" />

                <g id="posScreenGroup">
                    <rect x="185" y="125" width="70" height="60" rx="6" fill="#0f172a" transform="rotate(-8 220 155)" />
                    <rect class="anim-pos-screen" x="190" y="130" width="60" height="50" rx="4" fill="#1e1b4b" stroke="#6366f1" stroke-width="1.5" transform="rotate(-8 220 155)" />
                    
                    <rect x="196" y="138" width="25" height="4" rx="2" fill="#818cf8" transform="rotate(-8 220 155)" />
                    <rect x="196" y="146" width="40" height="3" rx="1.5" fill="#475569" transform="rotate(-8 220 155)" />
                    <rect x="196" y="152" width="32" height="3" rx="1.5" fill="#475569" transform="rotate(-8 220 155)" />
                    <rect x="196" y="162" width="18" height="8" rx="2" fill="#10b981" transform="rotate(-8 220 155)" />
                    
                    <line class="anim-pos-line" x1="190" y1="140" x2="250" y2="140" stroke="#818cf8" stroke-width="2" transform="rotate(-8 220 155)" />
                </g>
            </svg>
        </div>

    </div>
</div>

<script>
window.addEventListener("DOMContentLoaded", function() {
    var form = document.getElementById("loginFormV2");
    var usernameInput = document.getElementById("usernameInput");
    var passwordInput = document.getElementById("passwordInput");
    var posScreen = document.getElementById("posScreenGroup");

    function highlightPosScreen() {
        if (posScreen) { posScreen.style.filter = "drop-shadow(0 0 14px #818cf8)"; }
    }

    function resetPosScreen() {
        if (posScreen) { posScreen.style.filter = "none"; }
    }

    if(usernameInput) {
        usernameInput.addEventListener("focus", highlightPosScreen);
        usernameInput.addEventListener("blur", resetPosScreen);
    }

    if(passwordInput) {
        passwordInput.addEventListener("focus", highlightPosScreen);
        passwordInput.addEventListener("blur", resetPosScreen);
    }

    if (form) {
        form.addEventListener("submit", function() {
            var btn = document.getElementById("submitBtnV2");
            btn.innerHTML = 'Authenticating... <i class="fa-solid fa-spinner fa-spin"></i>';
        });
    }

    var urlParams = new URLSearchParams(window.location.search);
    var msg = urlParams.get('msg');

    if (msg === 'updated') {
        Swal.fire({
            icon: 'info',
            title: '🚀 System Updated!',
            text: 'A new update was deployed. Please log in again.',
            confirmButtonText: 'OK, Login',
            confirmButtonColor: '#6366f1'
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
