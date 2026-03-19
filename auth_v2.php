<?php
session_start();
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
}

/* LOGIN SESSION */
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role_id'] = $user['role_id'];
$_SESSION['org_id'] = $user['org_id'];
$_SESSION['center_id'] = $user['center_id'];
$_SESSION['db_name'] = $user['db_name'];

/* 🔥 SWITCH DATABASE */
$db->db_disconnect();
$db->db_connect();

/* ROLE BASED LOGIN */
if($user['role_id'] == 1){

    $_SESSION['superadmin_login'] = true;
    redirect('superadmin_dashboard.php');

}elseif($user['role_id'] == 2){

    redirect('admin.php');

}elseif($user['role_id'] == 3){

    redirect('home.php');

}

}else{

$session->msg("d","Invalid Username or Password");
redirect('login_v2.php');

}
?>
