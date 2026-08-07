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
  /* Metallic Rolling Shutter Background on Login Page */
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
      background: repeating-linear-gradient(
          180deg,
          #e2e8f0 0px,
          #cbd5e1 6px,
          #94a3b8 12px,
          #cbd5e1 18px,
          #f8fafc 24px
      );
      overflow: hidden;
      font-family: 'Segoe UI', Roboto, sans-serif;
  }

  /* Clean White Floating Login Card */
  .login-page-card {
      position: relative;
      width: 100%;
      max-width: 410px;
      padding: 40px 35px;
      background: #ffffff;
      border: 1px solid #cbd5e1;
      border-radius: 20px;
      box-shadow: 0 20px 40px rgba(15, 23, 42, 0.2);
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
  }

  .btn-theme:hover {
      background: #8e0000;
  }
</style>

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

<!-- ✅ SWEETALERT & SUBMIT CONTROLLER -->
<script>
window.addEventListener("DOMContentLoaded", function() {
    if (typeof(Storage) !== "undefined") {
        sessionStorage.clear();
    }

    var form = document.getElementById("loginForm");
    if (form) {
        form.addEventListener("submit", function(e) {
            var btn = document.getElementById("submitBtn");
            btn.innerHTML = 'Opening Shutter... <i class="fa-solid fa-spinner fa-spin" style="margin-left:8px;"></i>';
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
