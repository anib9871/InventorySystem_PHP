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
  * { box-sizing: border-box; margin: 0; padding: 0; }

  body, html {
      width: 100%;
      height: 100%;
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
      background: #f1f5f9;
      overflow-x: hidden;
  }

  .login-container {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      position: relative;
  }

  /* Soft Ambient Background Lighting */
  .bg-glow-1 {
      position: absolute;
      width: 450px;
      height: 450px;
      background: rgba(37, 99, 235, 0.08);
      border-radius: 50%;
      filter: blur(100px);
      top: 10%;
      left: 15%;
      pointer-events: none;
  }

  .bg-glow-2 {
      position: absolute;
      width: 400px;
      height: 400px;
      background: rgba(168, 0, 0, 0.06);
      border-radius: 50%;
      filter: blur(100px);
      bottom: 10%;
      right: 15%;
      pointer-events: none;
  }

  /* Main Split Card Frame */
  .main-card-frame {
      position: relative;
      width: 100%;
      max-width: 920px;
      background: #ffffff;
      border-radius: 28px;
      box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.12);
      border: 1px solid rgba(226, 232, 240, 0.8);
      display: flex;
      overflow: hidden;
      z-index: 10;
  }

  /* Left Side: Form Section */
  .form-section {
      flex: 1;
      padding: 55px 45px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      z-index: 2;
  }

  .brand-header {
      margin-bottom: 35px;
  }

  .brand-header h2 {
      font-size: 28px;
      font-weight: 800;
      color: #0f172a;
      letter-spacing: -0.5px;
      display: flex;
      align-items: center;
      gap: 10px;
  }

  .brand-header p {
      font-size: 13px;
      color: #64748b;
      margin-top: 4px;
  }

  .form-group {
      margin-bottom: 22px;
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
      transition: color 0.3s ease;
  }

  .form-control {
      width: 100%;
      height: 48px;
      background: #f8fafc;
      border: 1.5px solid #cbd5e1;
      border-radius: 12px;
      padding: 0 15px 0 48px;
      color: #0f172a;
      font-size: 14px;
      outline: none;
      transition: all 0.3s ease;
  }

  .form-control:focus {
      border-color: #2563eb;
      background: #ffffff;
      box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
  }

  .form-control:focus + i {
      color: #2563eb;
  }

  .btn-submit {
      width: 100%;
      height: 50px;
      margin-top: 10px;
      border: none;
      border-radius: 12px;
      background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
      color: #ffffff;
      font-size: 14px;
      font-weight: 700;
      letter-spacing: 0.8px;
      text-transform: uppercase;
      cursor: pointer;
      box-shadow: 0 10px 20px -5px rgba(37, 99, 235, 0.4);
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
  }

  .btn-submit:hover {
      transform: translateY(-2px);
      box-shadow: 0 14px 24px -5px rgba(37, 99, 235, 0.5);
  }

  /* Right Side: Live Illustration Section */
  .illustration-section {
      flex: 1.1;
      background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
      position: relative;
      display: flex;
      align-items: flex-end;
      justify-content: center;
      padding: 30px;
      overflow: hidden;
  }

  .illustration-backdrop {
      position: absolute;
      width: 320px;
      height: 320px;
      background: #bfdbfe;
      border-radius: 50%;
      top: 15%;
      right: -40px;
      opacity: 0.6;
  }

  .pos-vector-art {
      width: 100%;
      max-width: 420px;
      height: auto;
      z-index: 5;
      filter: drop-shadow(0 15px 25px rgba(30, 58, 138, 0.15));
  }

  /* ------------------- LIVE ANIMATIONS ------------------- */
  
  /* Natural Breathing Movement */
  .anim-character-body {
      animation: characterBreathe 3.5s infinite ease-in-out;
      transform-origin: bottom center;
  }

  @keyframes characterBreathe {
      0%, 100% { transform: translateY(0px) rotate(0deg); }
      50% { transform: translateY(-4px) rotate(0.5deg); }
  }

  /* Eye Blinking Animation */
  .anim-eyes {
      animation: eyeBlink 4s infinite;
      transform-origin: center;
  }

  @keyframes eyeBlink {
      0%, 94%, 98%, 100% { transform: scaleY(1); }
      96% { transform: scaleY(0.1); }
  }

  /* Hand Typing Motion */
  .anim-hand {
      animation: handType 2s infinite alternate ease-in-out;
      transform-origin: 210px 170px;
  }

  @keyframes handType {
      0% { transform: rotate(0deg) translateY(0); }
      100% { transform: rotate(-3deg) translateY(-3px); }
  }

  /* POS Screen Glow & Line Scan */
  .anim-pos-screen {
      animation: screenPulse 2.5s infinite alternate ease-in-out;
  }

  @keyframes screenPulse {
      0% { fill: #ffffff; opacity: 0.9; }
      100% { fill: #f0f9ff; opacity: 1; filter: drop-shadow(0 0 8px rgba(56, 189, 248, 0.6)); }
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
      .main-card-frame { flex-direction: column-reverse; max-width: 440px; }
      .illustration-section { padding: 20px; min-height: 220px; }
      .pos-vector-art { max-width: 280px; }
      .form-section { padding: 35px 25px; }
  }
</style>

<div class="login-container">
    <div class="bg-glow-1"></div>
    <div class="bg-glow-2"></div>

    <div class="main-card-frame">

        <!-- LEFT COLUMN: LOGIN FORM -->
        <div class="form-section">
            <div class="brand-header">
                <h2><i class="fa-solid fa-store" style="color:#2563eb;"></i> Storly</h2>
                <p>Online Inventory & Store Management System</p>
            </div>

            <?php if(function_exists('display_msg')) { echo display_msg($msg); } ?>

            <form id="loginForm" method="post" action="auth_v2.php">

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

                <button type="submit" id="submitBtn" class="btn-submit">
                    Log In <i class="fa-solid fa-arrow-right"></i>
                </button>

            </form>
        </div>

        <!-- RIGHT COLUMN: LIVE ANIMATED POS CHARACTER VECTOR ART -->
        <div class="illustration-section">
            <div class="illustration-backdrop"></div>

            <svg class="pos-vector-art" viewBox="0 0 400 320" fill="none" xmlns="http://www.w3.org/2000/svg">
                
                <path d="M50 220 Q 20 180 60 140 Q 120 100 200 130 Q 280 90 350 150 Q 390 200 350 250 Z" fill="#e0f2fe" opacity="0.7"/>

                <!-- Live Character Group -->
                <g class="anim-character-body">
                    <!-- Body / Shirt -->
                    <path d="M 240 180 Q 280 150 320 180 L 340 300 L 220 300 Z" fill="#2563eb" />
                    <polygon points="270,180 290,180 280,210" fill="#ffffff" />
                    <polygon points="260,180 270,180 275,200" fill="#93c5fd" />
                    <polygon points="290,180 300,180 285,200" fill="#93c5fd" />

                    <!-- Neck & Head -->
                    <rect x="272" y="160" width="16" height="22" rx="4" fill="#fbcfe8" />
                    <path d="M 255 140 C 255 115, 305 115, 305 140 C 305 165, 255 165, 255 140 Z" fill="#fed7aa" />

                    <!-- Hair -->
                    <path d="M 250 135 Q 260 95 295 105 Q 315 115 310 135 Q 295 120 275 125 Z" fill="#1e293b" />

                    <!-- Eyes & Smile -->
                    <g class="anim-eyes">
                        <circle cx="270" cy="138" r="3" fill="#0f172a" />
                        <circle cx="288" cy="138" r="3" fill="#0f172a" />
                    </g>
                    <path d="M 272 150 Q 279 156 286 150" stroke="#0f172a" stroke-width="2" stroke-linecap="round" fill="none" />

                    <!-- Pointing Hand -->
                    <g class="anim-hand">
                        <path d="M 240 200 Q 200 195 175 200" stroke="#fed7aa" stroke-width="12" stroke-linecap="round" fill="none" />
                        <circle cx="172" cy="200" r="7" fill="#fed7aa" />
                    </g>
                </g>

                <!-- Counter Desk & POS Machine -->
                <rect x="180" y="240" width="200" height="70" rx="8" fill="#ea580c" />
                <rect x="175" y="235" width="210" height="12" rx="4" fill="#f97316" />
                <rect x="175" y="247" width="210" height="4" fill="#c2410c" />

                <rect x="200" y="210" width="55" height="26" rx="4" fill="#475569" />
                <rect x="205" y="200" width="45" height="12" rx="2" fill="#334155" />
                <rect x="222" y="185" width="10" height="18" fill="#64748b" />

                <!-- POS Screen (Live Glow) -->
                <g id="posScreenGroup">
                    <rect x="185" y="125" width="70" height="60" rx="6" fill="#1e293b" transform="rotate(-8 220 155)" />
                    <rect class="anim-pos-screen" x="190" y="130" width="60" height="50" rx="4" fill="#ffffff" transform="rotate(-8 220 155)" />
                    
                    <rect x="196" y="138" width="25" height="4" rx="2" fill="#2563eb" transform="rotate(-8 220 155)" />
                    <rect x="196" y="146" width="40" height="3" rx="1.5" fill="#94a3b8" transform="rotate(-8 220 155)" />
                    <rect x="196" y="152" width="32" height="3" rx="1.5" fill="#94a3b8" transform="rotate(-8 220 155)" />
                    <rect x="196" y="162" width="18" height="8" rx="2" fill="#22c55e" transform="rotate(-8 220 155)" />
                    
                    <line class="anim-pos-line" x1="190" y1="140" x2="250" y2="140" stroke="#38bdf8" stroke-width="2" transform="rotate(-8 220 155)" />
                </g>

            </svg>
        </div>

    </div>
</div>

<script>
window.addEventListener("DOMContentLoaded", function() {
    var form = document.getElementById("loginForm");
    var usernameInput = document.getElementById("usernameInput");
    var passwordInput = document.getElementById("passwordInput");
    var posScreen = document.getElementById("posScreenGroup");

    function highlightPosScreen() {
        if (posScreen) { posScreen.style.filter = "drop-shadow(0 0 12px #38bdf8)"; }
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
            var btn = document.getElementById("submitBtn");
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
            confirmButtonColor: '#2563eb'
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
