<?php
ob_start();

date_default_timezone_set('Asia/Kolkata');

define("URL_SEPARATOR", '/');
define("DS", DIRECTORY_SEPARATOR);

defined('SITE_ROOT')? null: define('SITE_ROOT', realpath(dirname(__FILE__)));
define("LIB_PATH_INC", SITE_ROOT.DS);

/* ================= 1. STRICT SESSION START (MUST BE BEFORE ANY OTHER INCLUDE) ================= */
if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    if (!headers_sent()) {
        session_set_cookie_params(0, '/');
    }
    session_start();
}

require_once(LIB_PATH_INC.'config.php');
require_once(LIB_PATH_INC.'functions.php');

// Version Tagging (Session Check se Pehle Set Karo)
if (defined('APP_VERSION')) {
    if (!isset($_SESSION['app_version'])) {
        $_SESSION['app_version'] = APP_VERSION;
    }
}

require_once(LIB_PATH_INC.'session.php');

/* ================= 2. GLOBAL AUTH & SESSION INTEGRITY CHECK ================= */
$current_script = basename($_SERVER['PHP_SELF']);
// Pages jahan login hona zaroori nahi hai
$public_scripts = ['index.php', 'login.php', 'login_v2.php', 'auth.php', 'auth_v2.php', 'forgot_password.php'];

if (!in_array($current_script, $public_scripts)) {
    
    // Check 1: Session Variables exist karte hain ya nahi?
    $has_valid_session = !empty($_SESSION['user_id']) 
                      && !empty($_SESSION['org_id']) 
                      && !empty($_SESSION['db_name']);

    // Check 2: App Version match kar raha hai ya nahi?
    $has_correct_version = !defined('APP_VERSION') 
                        || (isset($_SESSION['app_version']) && $_SESSION['app_version'] === APP_VERSION);

    // Agar session wipe ho gaya HO ya version match na kare -> INSTANT LOGOUT & REDIRECT
    if (!$has_valid_session || !$has_correct_version) {
        
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();

        if (!headers_sent()) {
            header("Location: index.php?msg=session_expired");
        } else {
            echo "<script>window.location.href='index.php?msg=session_expired';</script>";
        }
        exit;
    }
}

require_once(LIB_PATH_INC.'upload.php');
require_once(LIB_PATH_INC.'database.php');

/* ================= FORCE ORG DATABASE SELECTION ================= */
if (isset($_SESSION['db_name']) && !empty($_SESSION['db_name']) && isset($db->con)) {
    @mysqli_select_db($db->con, $_SESSION['db_name']);
}

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

        $expiry_required =
            $cfg2[0]['expiry_required'];

        $print_name =
            $cfg2[0]['print_name'];

        $print_css_width =
            $cfg2[0]['css_width'];
    }
}

/* ================= CURRENT FINANCIAL YEAR ================= */

$current_month = date('m');
$current_year = date('Y');

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
SELECT *
FROM financial_year_master
WHERE fy_name='{$current_fy}'
LIMIT 1
");

/* AUTO CREATE FY */

if(!$fy){

    $db->query("
    INSERT INTO financial_year_master
    (fy_name,fy_start_year,fy_end_year,is_active)

    VALUES

    ('{$current_fy}',
     '{$fy_start}',
     '{$fy_end}',
     1)
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

?>
