<?php
ob_start();

date_default_timezone_set('Asia/Kolkata');

define("URL_SEPARATOR", '/');
define("DS", DIRECTORY_SEPARATOR);

defined('SITE_ROOT')? null: define('SITE_ROOT', realpath(dirname(__FILE__)));
define("LIB_PATH_INC", SITE_ROOT.DS);

/* ================= 💡 APP VERSION ================= */
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.0.1'); // 🚀 Jab naya code deploy karo, version badal do (e.g. 1.0.2)
}

/* ================= 1. STRICT SESSION START ================= */
if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    if (!headers_sent()) {
        session_set_cookie_params(0, '/');
    }
    session_start();
}

require_once(LIB_PATH_INC.'config.php');
require_once(LIB_PATH_INC.'functions.php');
require_once(LIB_PATH_INC.'session.php');

/* ================= 2. GLOBAL AUTH & REDEPLOYMENT CHECK ================= */
$current_script = basename($_SERVER['PHP_SELF']);
$public_scripts = ['index.php', 'login.php', 'login_v2.php', 'auth.php', 'auth_v2.php', 'forgot_password.php', 'logout.php'];

if (!in_array($current_script, $public_scripts)) {
    
    // Check 1: User Logged In hai ya nahi?
    $is_logged_in = !empty($_SESSION['user_id']);

    // Check 2: Browser Cookie me saved version aur Current Code Version mismatch
    $browser_version = $_COOKIE['app_deploy_version'] ?? '';
    $is_redeployed = ($browser_version !== '' && $browser_version !== APP_VERSION);

    // --- CASE A: REDEPLOYMENT DETECTED (ALERT FIRST -> LOGOUT ONLY AFTER OK) ---
    if ($is_redeployed) {
        
        // Browser Cookie ko new version par update kar do
        setcookie('app_deploy_version', APP_VERSION, time() + (86400 * 30), "/");

        // Browser me alert dikhao. Jab tak user OK nahi dabaayega, JS rukega.
        // OK dabaate hi 'logout.php' par bhejega jo session destroy karega.
        echo "<script>
            alert('⚠️ System Updated / Redeployed! Please click OK to login again.');
            window.location.href = 'logout.php?msg=redeployed';
        </script>";
        exit;
    }

    // --- CASE B: NOT LOGGED IN / SESSION EXPIRED ---
    if (!$is_logged_in) {
        if (!headers_sent()) {
            header("Location: index.php?msg=session_expired");
        } else {
            echo "<script>window.location.href='index.php?msg=session_expired';</script>";
        }
        exit;
    }

    // Current version cookie refresh karo for active user
    setcookie('app_deploy_version', APP_VERSION, time() + (86400 * 30), "/");
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
