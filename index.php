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
  /* Modern Fullscreen Background */
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
      background: #0f172a;
      overflow: hidden;
      font-family: 'Segoe UI', Roboto, sans-serif;
  }

  /* Store Shutter Background Accent */
  .shutter-bg-pattern {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: repeating-linear-gradient(
          180deg,
          rgba(255,255,255,0.03) 0px,
          rgba(255,255,255,0.03) 10px,
          transparent 10px,
          transparent 20px
      );
      pointer-events: none;
  }

  /* Animated Lock Circle */
  .lock-circle-container {
      width: 80px;
      height: 80px;
      margin: 0 auto 15px auto;
      background: rgba(168, 0, 0, 0.15);
      border: 2px solid #a80000;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 32px;
      color: #f87171;
      box-shadow: 0 0 25px rgba(168, 0, 0, 0.4);
      transition: all 0.4s ease;
  }

  .lock-circle-container.unlocked {
      background: rgba(34, 197, 94, 0.2);
      border-color: #22c55e;
      color: #4ade80;
      box-shadow: 0 0 30px rgba(34, 197, 94, 0.6);
      transform: scale(1.1) rotate(15deg);
  }

  /* Modern Glass Card */
  .login-page-card {
      position: relative;
      width: 100%;
      max-width: 420px;
      padding: 40px 35px;
      background: #1e293b;
      border: 1px solid #334155;
      border-radius: 20px;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
      color: #f8fafc;
      box-sizing: border-box;
  }

  .login-page-card h1 {
      font-size: 26px;
      font-weight: 800;
      color: #ffffff;
      margin: 0 0 4px 0;
  }

  .login-page-card h4 {
      color: #94a3b8;
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
      height: 48px;
      background: #0f172a !important;
      border: 1.5px solid #334155 !important;
      border-radius: 10px !important;
      padding: 0 15px 0 48px !important;
      color: #ffffff !important;
      font-size: 14px;
      outline: none;
      box-sizing: border-box;
      transition: all 0.3s ease;
  }

  .form-control:focus {
      border-color: #a80000 !important;
      box-shadow: 0 0 0 4px rgba(168, 0, 0, 0.25) !important;
  }

  .btn-theme {
      width: 100%;
      height: 50px;
      margin-top: 10px;
      border: none;
      border-radius: 10px;
      background: #a80000;
      color: #ffffff;
      font-size: 14px;
      font-weight: 800;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      cursor: pointer;
      box-shadow: 0 8px 20px rgba(168, 0, 0, 0.4);
      transition: all 0.3s ease;
  }

  .btn-theme:hover {
      background: #c50000;
      transform: translateY(-2px);
  }
</style>

<div class="login-wrapper-fixed">
    <div class="shutter-bg-pattern"></div>

    <div class="login-page-card">
        <!-- 🔒 ANIMATED LOCK BADGE -->
        <div id="lockIconContainer" class="lock-circle-container">
            <i id="lockIcon" class="fa-solid fa-lock"></i>
        </div>

        <div class="text-center">
           <h1>STORE LOGIN</h1>
           <h4>Inventory Management System</h4>
        </div>

        <?php echo display_msg($msg); ?>

        <form id="loginForm" method="post" action="auth_v2.php" class="clearfix">

            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-wrapper">
                    <input type="text" class="form-control" name="username" placeholder="Username" required autocomplete="off">
                    <i class="fa-solid fa-user-tie"></i>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    <i class="fa-solid fa-key"></i>
                </div>
            </div>

            <div class="text-center">
                <button type="submit" id="submitBtn" class="btn-theme">
                    Unlock Store <i class="fa-solid fa-arrow-right" style="margin-left: 8px;"></i>
                </button>
            </div>

        </form>
    </div>
</div>

<!-- ✅ LOCK ANIMATION ON SUBMIT & SWEETALERT SCRIPT -->
<script>
window.addEventListener("DOMContentLoaded", function() {
    if (typeof(Storage) !== "undefined") {
        sessionStorage.clear();
    }

    // 🔓 UNLOCK ANIMATION ON FORM SUBMIT
    var form = document.getElementById("loginForm");
    if (form) {
        form.addEventListener("submit", function(e) {
            var lockContainer = document.getElementById("lockIconContainer");
            var lockIcon = document.getElementById("lockIcon");
            var btn = document.getElementById("submitBtn");

            lockContainer.classList.add("unlocked");
            lockIcon.className = "fa-solid fa-unlock";
            btn.innerHTML = 'Unlocking Store... <i class="fa-solid fa-spinner fa-spin" style="margin-left:8px;"></i>';
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
