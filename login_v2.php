<?php
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

<!-- FontAwesome Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
      z-index: 9999;
      background: #f1f5f9; /* Clean Light Background */
      overflow: hidden;
      font-family: 'Segoe UI', Roboto, sans-serif;
  }

  /* Soft Color Lighting (Navy + Red Ambient Glow) */
  .glow-circle-1 {
      position: absolute;
      width: 380px;
      height: 380px;
      background: rgba(168, 0, 0, 0.08); /* Deep Red Glow */
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
      background: rgba(19, 28, 42, 0.08); /* Dark Navy Glow */
      border-radius: 50%;
      filter: blur(100px);
      bottom: 15%;
      right: 25%;
      pointer-events: none;
  }

  /* Main Card */
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
      animation: floatUp 0.4s ease-out;
  }

  @keyframes floatUp {
      from {
          opacity: 0;
          transform: translateY(15px);
      }
      to {
          opacity: 1;
          transform: translateY(0);
      }
  }

  /* Typography using Navy Blue (#131c2a) */
  .login-page-card h1 {
      font-size: 28px;
      font-weight: 800;
      letter-spacing: -0.5px;
      color: #131c2a; /* Navy Blue Accent */
      margin-bottom: 6px;
  }

  .login-page-card p {
      color: #64748b;
      font-size: 14px;
      margin-bottom: 32px;
  }

  /* Form Elements */
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
      color: #131c2a; /* Navy Blue Accent */
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
      background: #f8fafc !important;
      border: 1.5px solid #cbd5e1 !important;
      border-radius: 10px !important;
      padding: 0 15px 0 48px !important;
      color: #131c2a !important;
      font-size: 14px;
      outline: none;
      box-sizing: border-box;
      transition: all 0.3s ease;
  }

  .form-control:focus {
      border-color: #a80000 !important; /* Crimson Red Focus Border */
      background: #ffffff !important;
      box-shadow: 0 0 0 4px rgba(168, 0, 0, 0.12) !important;
  }

  .form-control:focus + i {
      color: #a80000;
  }

  /* Crimson Red Button (#a80000) */
  .btn-theme {
      width: 100%;
      height: 48px;
      margin-top: 10px;
      border: none;
      border-radius: 10px;
      background: #a80000; /* Deep Crimson Red */
      color: #ffffff;
      font-size: 14px;
      font-weight: 700;
      letter-spacing: 1px;
      text-transform: uppercase;
      cursor: pointer;
      box-shadow: 0 8px 18px rgba(168, 0, 0, 0.28);
      transition: all 0.3s ease;
  }

  .btn-theme:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 22px rgba(168, 0, 0, 0.38);
      background: #8e0000;
  }

  .btn-theme:active {
      transform: translateY(0);
  }
</style>

<div class="login-wrapper-fixed">
    <!-- Background Color Glows -->
    <div class="glow-circle-1"></div>
    <div class="glow-circle-2"></div>

    <div class="login-page-card">
        <div class="text-center">
           <h1>Welcome Back</h1>
           <p>Sign in to start your session</p>
        </div>

        <?php echo display_msg($msg); ?>

        <form method="post" action="auth_v2.php">

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
                <button type="submit" class="btn-theme">
                    Login <i class="fa-solid fa-arrow-right" style="margin-left: 8px;"></i>
                </button>
            </div>

        </form>
    </div>
</div>

<?php include_once('layouts/footer.php'); ?>
