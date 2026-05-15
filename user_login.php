<?php
require_once('includes/load.php');

/* ================= ALREADY LOGIN ================= */

if($session->isUserLoggedIn(true)){
redirect('home.php', false);
}

/* ================= LOGIN ================= */

if(isset($_POST['login'])){

/* ================= MASTER DB CONNECT ================= */

$conn = mysqli_connect(
"127.0.0.1",
"root",
"Mysql123@",
"master_inventory",
3306
);

if(!$conn){
die("Database connection failed");
}

/* ================= GET FORM DATA ================= */

$username = mysqli_real_escape_string($conn,$_POST['username']);
$password = mysqli_real_escape_string($conn,$_POST['password']);

/* ===================================================== */
/* STEP 1 : FIND USER FROM ALL ORGANIZATION DATABASES */
/* ===================================================== */

$get_orgs = mysqli_query($conn,"
SELECT org_id, db_name
FROM master_organization
");

$login_success = false;

while($org = mysqli_fetch_assoc($get_orgs)){

$db_name = $org['db_name'];
$org_id  = $org['org_id'];

/* ================= CONNECT ORG DB ================= */

$tenant_conn = mysqli_connect(
"127.0.0.1",
"root",
"Mysql123@",
$db_name,
3306
);

if(!$tenant_conn){
continue;
}

/* ================= CHECK USER ================= */

$check_user = mysqli_query($tenant_conn,"
SELECT *
FROM users
WHERE username='{$username}'
AND password='{$password}'
LIMIT 1
");

if(mysqli_num_rows($check_user) == 1){

$user = mysqli_fetch_assoc($check_user);

/* ================= SET SESSION ================= */

$_SESSION['db_name'] = $db_name;

$db->db_disconnect();
$db->db_connect();

$session->login($user['id']);

$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['user_level'] = $user['user_level'];
$_SESSION['role_id'] = 3;
$_SESSION['org_id'] = $org_id;
$_SESSION['center_id'] = $user['center_id'];

$org_query = mysqli_query($conn,"
SELECT org_name
FROM master_organization
WHERE org_id='{$org_id}'
LIMIT 1
");

$org_data = mysqli_fetch_assoc($org_query);

$_SESSION['org_name'] = $org_data['org_name'];
$center_query = mysqli_query($tenant_conn,"
SELECT center_name
FROM master_center
WHERE center_id='{$user['center_id']}'
LIMIT 1
");

$center_data = mysqli_fetch_assoc($center_query);

$_SESSION['center_name'] = $center_data['center_name'];

/* ================= SUBSCRIPTION CHECK ================= */

$sub = mysqli_query($conn,"
SELECT os.*, sp.plan_type
FROM organization_subscriptions os
JOIN subscription_plans sp
ON os.plan_id = sp.plan_id
WHERE os.org_id='{$org_id}'
AND os.status=1
ORDER BY os.sub_id DESC
LIMIT 1
");

if(mysqli_num_rows($sub) > 0){

$row = mysqli_fetch_assoc($sub);

$plan = $row['plan_type'];

/* RESET */
$_SESSION['inventory_access'] = 0;
$_SESSION['billing_access'] = 0;
$_SESSION['combined_mode'] = 0;

if($plan == 'inventory'){

$_SESSION['inventory_access'] = 1;

}
elseif($plan == 'billing'){

$_SESSION['billing_access'] = 1;

}
elseif($plan == 'combined'){

$_SESSION['inventory_access'] = 1;
$_SESSION['billing_access'] = 1;
$_SESSION['combined_mode'] = 1;

}

}


mysqli_query($tenant_conn,"
UPDATE users
SET last_login = NOW()
WHERE id='{$user['id']}'
");

/* ================= SUCCESS ================= */

$session->msg("s","Welcome ".$user['username']);

$login_success = true;

redirect('home.php', false);

exit();

}

}

/* ================= INVALID ================= */

if(!$login_success){

$session->msg("d","Invalid Username or Password");

redirect('user_login.php', false);

exit();

}

}
?>

<?php include_once('layouts/header.php'); ?>

<div class="login-page">

<div class="text-center">
<h1>User Login</h1>
<p>Login for Employees / Staff</p>
</div>

<?php echo display_msg($msg); ?>

<form method="post">

<div class="form-group">

<label>Username</label>

<input type="text"
class="form-control"
name="username"
required>

</div>

<div class="form-group">

<label>Password</label>

<input type="password"
class="form-control"
name="password"
required>

</div>

<div class="form-group">

<button type="submit"
name="login"
class="btn btn-info pull-right">

Login

</button>

</div>

</form>

<hr>

<div class="text-center">

<a href="login_v2.php" class="btn btn-default">

Admin Login

</a>

</div>

</div>

<?php include_once('layouts/footer.php'); ?>