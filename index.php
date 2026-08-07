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
  /* Clean Light Slate Background */
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

  /* Animated Lock Circle */
  .lock-circle-container {
      width: 75px;
      height: 75px;
      margin: 0 auto 15px auto;
      background: rgba(168, 0, 0, 0.08);
      border: 2px solid #a80000;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 30px;
      color: #a80000;
      transition: all 0.4s ease;
  }

  .lock-circle-container.unlocked {
      background: rgba(34, 197, 94, 0.15);
      border-color: #16a34a;
      color: #16a34a;
      transform: scale(1.1) rotate(15deg);
  }

  /* Main Light Card */
  .login-page-card {
      position: relative;
      width: 100%;
      max-width: 410px;
      padding: 40px 35px;
      background: #ffffff;
      border: 1px solid #cbd5e1;
      border-radius: 20px;
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.06);
      color: #0f172a;
      box-sizing: border-box;
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
  }

  .btn-theme:hover {
      background: #8e0000;
  }
</style>

<div class="login-wrapper-fixed">
    <div class="login-page-card">
        <!-- 🔒 ANIMATED LOCK -->
        <div id="lockIconContainer" class="lock-circle-container">
            <i id="lockIcon" class="fa-solid fa-lock"></i>
        </div>

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

<!-- ✅ SCRIPT -->
<script>
window.addEventListener("DOMContentLoaded", function() {
    if (typeof(Storage) !== "undefined") {
        sessionStorage.clear();
    }

    // 🔓 LOCK UNLOCK ANIMATION
    var form = document.getElementById("loginForm");
    if (form) {
        form.addEventListener("submit", function(e) {
            var lockContainer = document.getElementById("lockIconContainer");
            var lockIcon = document.getElementById("lockIcon");
            var btn = document.getElementById("submitBtn");

            lockContainer.classList.add("unlocked");
            lockIcon.className = "fa-solid fa-unlock";
            btn.innerHTML = 'Authenticating... <i class="fa-solid fa-spinner fa-spin" style="margin-left:8px;"></i>';
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
