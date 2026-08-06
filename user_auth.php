<?php

require_once('includes/load.php');

$username = trim($_POST['username']);
$password = trim($_POST['password']);
$org_name = trim($_POST['org_name']);

/* MASTER DB CONNECT */

$conn = mysqli_connect(
    getenv('MYSQLHOST'),
    getenv('MYSQLUSER'),
    getenv('MYSQLPASSWORD'),
    'master_inventory',
    getenv('MYSQLPORT')
);

if(!$conn){
die("Database connection failed");
}

/* GET ORGANIZATION DATABASE */

$org_sql = "SELECT db_name
FROM master_organization
WHERE org_name='{$org_name}'
LIMIT 1";

$org_result = mysqli_query($conn,$org_sql);

if(mysqli_num_rows($org_result) == 0){

$session->msg("d","Organization not found");
redirect('user_login.php');

}

$org = mysqli_fetch_assoc($org_result);

$_SESSION['db_name'] = $org['db_name'];

/* CONNECT TENANT DB */

$db->db_disconnect();
$db->db_connect();

/* USER LOGIN */

$username = $db->escape($username);
$password = $db->escape($password);

$sql = "SELECT *
FROM users
WHERE username='{$username}'
AND password='{$password}'
LIMIT 1";

$result = find_by_sql($sql);

if($result){

$user = $result[0];

$session->login($user['id']);

$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['user_level'] = $user['user_level'];

// ✅ ADDED THIS LINE TO PREVENT TAB LOOP:
$_SESSION['just_logged_in'] = true;

redirect('home.php', false);

}else{

$session->msg("d","Invalid Username or Password");
redirect('user_login.php', false);

}

?>
