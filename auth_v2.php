<?php
//session_start();
require_once('includes/load.php');

$username = $db->escape(trim($_POST['username']));
$password = trim($_POST['password']);

/* FETCH USER + ORGANIZATION DATABASE */

$sql = "SELECT u.*, o.db_name, o.org_name
FROM master_inventory.user_credentials u
LEFT JOIN master_inventory.master_organization o
ON u.org_id = o.org_id
WHERE u.username='{$username}'
LIMIT 1";

$result = $db->query($sql);

if($db->num_rows($result) == 1){

$user = $db->fetch_assoc($result);

/* PASSWORD CHECK */
if($password !== $user['password']){
    $session->msg("d","Invalid Password");
    redirect('login_v2.php');
    exit;
}
/* 🔥 SUPERADMIN BYPASS */
if($user['role_id'] == 1){

    $_SESSION['superadmin_login'] = true;

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role_id'] = $user['role_id'];

    redirect('superadmin_dashboard.php');
    exit;
}
/* 🔥 SUBSCRIPTION CHECK */

$org_id = $user['org_id'];

$sub = $db->query("
SELECT * FROM master_inventory.organization_subscriptions
WHERE org_id='$org_id' AND status=1
ORDER BY sub_id DESC LIMIT 1
");

if($db->num_rows($sub) == 0){

    $session->msg("d","No active subscription");
    redirect('login_v2.php');
    exit;
}

$row = $db->fetch_assoc($sub);

$today = date('Y-m-d');

if($row['end_date'] < $today){

    $session->msg("d","Subscription expired!");
    redirect('login_v2.php');
    exit;
}
/* LOGIN SESSION */
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role_id'] = $user['role_id'];
$_SESSION['org_id'] = $user['org_id'];
$_SESSION['center_id'] = $user['center_id'];
$_SESSION['db_name'] = $user['db_name'];
$_SESSION['user_level'] = 1;
$_SESSION['org_name'] = $user['org_name'];

/* GET PLAN TYPE */

$sub = $db->query("
SELECT os.*, sp.plan_type
FROM master_inventory.organization_subscriptions os
JOIN master_inventory.subscription_plans sp
ON os.plan_id = sp.plan_id
WHERE os.org_id='$org_id' AND os.status=1
ORDER BY os.sub_id DESC LIMIT 1
");

$row = $db->fetch_assoc($sub);

$plan = strtolower(trim($row['plan_type']));

/* RESET ACCESS */

$_SESSION['inventory_access'] = 0;
$_SESSION['billing_access'] = 0;
$_SESSION['combined_mode'] = 0;

/* PLAN ACCESS */

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
    
/* ROLE BASED LOGIN */

// 🔥 SUPERADMIN (NO DB SWITCH)
// if($user['role_id'] == 1){

//     $_SESSION['superadmin_login'] = true;

//     // ❌ DB SWITCH MAT KAR
//     redirect('superadmin_dashboard.php');

// }

// 🔥 ADMIN
if($user['role_id'] == 2){

    $_SESSION['db_name'] = $user['db_name'];

    $db->db_disconnect();
    $db->db_connect();


    redirect('admin.php');
    exit;
}

// 🔥 USER
elseif($user['role_id'] == 3){

    $_SESSION['db_name'] = $user['db_name'];

    $db->db_disconnect();
    $db->db_connect();
   
    redirect('home.php');
    exit;
}

}else{

$session->msg("d","Invalid Username or Password");
redirect('login_v2.php');
exit;

}
?>
