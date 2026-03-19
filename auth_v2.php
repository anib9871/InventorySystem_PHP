<?php
session_start();
require_once('includes/load.php');

$username = trim($_POST['username']);
$password = trim($_POST['password']);

/* CONNECT MASTER DATABASE */

// $conn = mysqli_connect("127.0.0.1","root","Mysql123@","master_inventory",3306);

if(!$conn){
die("Database connection failed");
}

/* FETCH USER + ORGANIZATION DATABASE */

$sql = "SELECT u.*, o.db_name
FROM master_inventory.user_credentials u
LEFT JOIN master_inventory.master_organization o
ON u.org_id = o.org_id
WHERE u.username='$username'
AND u.password='$password'
LIMIT 1";

$result = mysqli_query($conn,$sql);

if(mysqli_num_rows($result) == 1){

$user = mysqli_fetch_assoc($result);

/* LOGIN SESSION */

$session->login($user['id']);

$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role_id'] = $user['role_id'];
$_SESSION['org_id'] = $user['org_id'];
$_SESSION['center_id'] = $user['center_id'];
$_SESSION['db_name'] = $user['db_name'];

/* 🔥 MOST IMPORTANT — TENANT DATABASE */

$_SESSION['db_name'] = $user['db_name'];

/* RECONNECT DATABASE WITH TENANT DB */

$db->db_disconnect();
$db->db_connect();

/* ROLE BASED LOGIN */

if($user['role_id'] == 1){

$_SESSION['superadmin_login'] = true;

header("Location: superadmin_dashboard.php");
exit();

}

elseif($user['role_id'] == 2){

header("Location: admin.php");
exit();

}

elseif($user['role_id'] == 3){

header("Location: home.php");
exit();

}

}else{

$session->msg("d","Invalid Username or Password");
header("Location: login_v2.php");
exit();

}
?>
