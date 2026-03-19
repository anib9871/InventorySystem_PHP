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

<div class="login-page">
    <div class="text-center">
       <h1>Welcome</h1>
       <p>Sign in to start your session</p>
    </div>

    <?php echo display_msg($msg); ?>

    <form method="post" action="auth_v2.php">

        <div class="form-group">
            <label>Username</label>
            <input type="text" class="form-control" name="username" required>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" class="form-control" name="password" required>
        </div>

        <div class="form-group">
            <button type="submit" class="btn btn-info pull-right">Login</button>
        </div>

    </form>
</div>

<?php include_once('layouts/footer.php'); ?>
