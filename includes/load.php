<?php
ob_start();
// ✅ Session config - SABSE PEHLE
if (session_status() === PHP_SESSION_NONE) {
    session_name('SECURE_APP_SESSION');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

date_default_timezone_set('Asia/Kolkata');
define("URL_SEPARATOR", '/');
define("DS", DIRECTORY_SEPARATOR);
defined('SITE_ROOT')? null: define('SITE_ROOT', realpath(dirname(__FILE__)));
define("LIB_PATH_INC", SITE_ROOT.DS);
require_once(LIB_PATH_INC.'config.php');
require_once(LIB_PATH_INC.'functions.php');
require_once(LIB_PATH_INC.'session.php');
require_once(LIB_PATH_INC.'upload.php');
require_once(LIB_PATH_INC.'database.php');
require_once(LIB_PATH_INC.'sql.php');

/* ================= GST CONFIG ================= */
$gst_enabled = "Yes";
if(isset($_SESSION['org_id'])){
    $org_id = (int)$_SESSION['org_id'];
    $cfg = find_by_sql("
        SELECT gst_registered
        FROM configuration_master
        WHERE org_id = '{$org_id}'
        LIMIT 1
    ");
    if($cfg){
        $gst_enabled = $cfg[0]['gst_registered'];
    }
}

/* ================= EXPIRY + PRINT CONFIG ================= */
$expiry_required = "Yes";
$print_name = "A4";
$print_css_width = "190mm";
if(isset($_SESSION['org_id'])){
    $org_id = (int)$_SESSION['org_id'];
    $cfg2 = find_by_sql("
        SELECT c.expiry_required,
               p.print_name,
               p.css_width
        FROM configuration_master c
        LEFT JOIN print_type_master p
        ON p.id = c.print_type_id
        WHERE c.org_id = '{$org_id}'
        LIMIT 1
    ");
    if($cfg2){
        $expiry_required = $cfg2[0]['expiry_required'];
        $print_name      = $cfg2[0]['print_name'];
        $print_css_width = $cfg2[0]['css_width'];
    }
}

/* ================= CURRENT FINANCIAL YEAR ================= */
$current_month = date('m');
$current_year  = date('Y');
if($current_month >= 4){
    $fy_start = $current_year;
    $fy_end   = $current_year + 1;
}else{
    $fy_start = $current_year - 1;
    $fy_end   = $current_year;
}
$current_fy = $fy_start . "-" . substr($fy_end,2,2);

/* CHECK FY */
$fy = find_by_sql("
    SELECT * FROM financial_year_master
    WHERE fy_name='{$current_fy}'
    LIMIT 1
");

/* AUTO CREATE FY */
if(!$fy){
    $db->query("
        INSERT INTO financial_year_master
        (fy_name,fy_start_year,fy_end_year,is_active)
        VALUES ('{$current_fy}','{$fy_start}','{$fy_end}',1)
    ");
    $db->query("
        UPDATE financial_year_master
        SET is_active = 0
        WHERE fy_name!='{$current_fy}'
    ");
}else{
    $db->query("
        UPDATE financial_year_master
        SET is_active = 1
        WHERE fy_name='{$current_fy}'
    ");
    $db->query("
        UPDATE financial_year_master
        SET is_active = 0
        WHERE fy_name!='{$current_fy}'
    ");
}
$_SESSION['financial_year'] = $current_fy;

/* ================= SESSION SECURITY CHECK ================= */
$open_pages = ['index.php','login_v2.php','auth_v2.php','user_login.php'];
$current_page = basename($_SERVER['PHP_SELF']);

if(!in_array($current_page, $open_pages)){
    if(!isset($_SESSION['user_id'])){
        redirect('index.php');
        exit;
    }
    if(isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 1800){
        session_unset();
        session_destroy();
        redirect('index.php');
        exit;
    }
    if(isset($_SESSION['user_agent']) &&
       $_SESSION['user_agent'] !== md5($_SERVER['HTTP_USER_AGENT'])){
        session_unset();
        session_destroy();
        redirect('index.php');
        exit;
    }
    $_SESSION['login_time'] = time();
}
?>
