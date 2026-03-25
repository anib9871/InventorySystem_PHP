<?php
//session_start();
require_once('includes/load.php');

$username = $db->escape(trim($_POST['username']));
$password = trim($_POST['password']);

/* FETCH USER + ORGANIZATION DATABASE */

$sql = "SELECT u.*, o.db_name
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
/* ROLE BASED LOGIN */

// 🔥 SUPERADMIN (NO DB SWITCH)
// if($user['role_id'] == 1){

//     $_SESSION['superadmin_login'] = true;

//     // ❌ DB SWITCH MAT KAR
//     redirect('superadmin_dashboard.php');

// }

// 🔥 ADMIN
elseif($user['role_id'] == 2){

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
