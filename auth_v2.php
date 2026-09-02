<?php
session_start();
require_once('includes/load.php');

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if(empty($username) || empty($password)){
    $session->msg("d","Please enter username and password");
    header("Location: login_v2.php");
    exit();
}

/* CONNECT MASTER DATABASE (Railway Environment Aware) */
$db_host = getenv('MYSQLHOST') ?: '127.0.0.1';
$db_user = getenv('MYSQLUSER') ?: 'root';
$db_pass = getenv('MYSQLPASSWORD') ?: 'Mysql123@';
$db_port = getenv('MYSQLPORT') ?: 3306;

$conn = mysqli_connect($db_host, $db_user, $db_pass, "master_inventory", (int)$db_port);

if(!$conn){
    die("Database connection failed");
}

$username_esc = mysqli_real_escape_string($conn, $username);
$password_esc = mysqli_real_escape_string($conn, $password);

/* FETCH USER + ORGANIZATION DATABASE */
$sql = "SELECT u.*, o.db_name, o.org_name
FROM master_inventory.user_credentials u
LEFT JOIN master_inventory.master_organization o
ON u.org_id = o.org_id
WHERE u.username='{$username_esc}'
AND u.password='{$password_esc}'
LIMIT 1";

$result = mysqli_query($conn, $sql);

if(mysqli_num_rows($result) == 1){

    $user = mysqli_fetch_assoc($result);

    /* 🔥 PASSWORD CHECK FOR MASTER CREDENTIALS */
    if($password !== $user['password']){
        $session->msg("d","Invalid Password");
        header("Location: login_v2.php");
        exit();
    }

    /* 🔥 SUPERADMIN BYPASS */
    if(isset($user['role_id']) && $user['role_id'] == 1){
        $_SESSION['superadmin_login'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['tab_token'] = bin2hex(random_bytes(16));
        $_SESSION['just_logged_in'] = true;

        header("Location: superadmin_dashboard.php");
        exit();
    }

    /* 🔥 SUBSCRIPTION CHECK */
    $org_id = $user['org_id'];

    $sub = mysqli_query($conn,"
    SELECT os.*, sp.plan_type
    FROM master_inventory.organization_subscriptions os
    JOIN master_inventory.subscription_plans sp
    ON os.plan_id = sp.plan_id
    WHERE os.org_id='{$org_id}' AND os.status=1 
    ORDER BY os.sub_id DESC LIMIT 1
    ");

    if(mysqli_num_rows($sub) == 0){
        $session->msg("d","No active subscription found");
        header("Location: login_v2.php");
        exit();
    }

    $row = mysqli_fetch_assoc($sub);
    $plan = isset($row['plan_type']) ? strtolower(trim($row['plan_type'])) : '';
    $today = date('Y-m-d');

    if(isset($row['end_date']) && $row['end_date'] < $today){
        $session->msg("d","Subscription expired! Contact admin.");
        header("Location: login_v2.php");
        exit();
    }

    /* ✅ LOGIN SESSION (MASTER ADMIN / ORG ADMIN) */
    $session->login($user['id']);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role_id'] = $user['role_id'];
    $_SESSION['org_id'] = $user['org_id'];
    $_SESSION['center_id'] = $user['center_id'];
    $_SESSION['db_name'] = $user['db_name'];
    $_SESSION['user_level'] = 1;
    $_SESSION['org_name'] = $user['org_name'];
    $_SESSION['tab_token'] = bin2hex(random_bytes(16));
    $_SESSION['just_logged_in'] = true;

    $center_id_esc = (int)$user['center_id'];
    $center = $db->query("SELECT center_name FROM master_center WHERE center_id = {$center_id_esc} LIMIT 1");
    if($db->num_rows($center) > 0){
        $center_row = $db->fetch_assoc($center);
        $_SESSION['center_name'] = $center_row['center_name'];
    }

    // 🔥 MASTER ADMIN FULL ACCESS OVERRIDE
    $_SESSION['menu_masters']     = 1;
    $_SESSION['menu_transaction'] = 1;
    $_SESSION['menu_payments']    = 1;
    $_SESSION['menu_reports']     = 1;
    $_SESSION['sub_permissions']  = ['org_master', 'centers', 'paymode', 'supplier_master', 'customer_master', 'products', 'bom_master', 'user_role', 'users', 'categories', 'gst_master', 'gst_state', 'shipping_type', 'config_master', 'rate_master', 'fin_year', 'sequence_master', 'bank_master', 'print_type', 'terms_cond', 'expense_master', 'grn', 'manage_grn', 'quotation', 'quotation_list', 'demo_item', 'demo_item_list', 'invoice', 'invoice_create', 'invoice_list', 'manufacture', 'return', 'return_master', 'direct_billing', 'duplicate_print', 'supp_advance', 'pay_pendency', 'expense', 'add_expense', 'manage_payments', 'payment_report', 'payments', 'stock_report', 'stock_book', 'sales_report', 'inventory_report', 'purchase_report', 'ledger_report', 'revenue_report', 'daily_revenue_report', 'expense_report', 'business_report', 'shippers_declaration', 'shippers_declaration_entry'];

    // RESET ACCESS MODES
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

    $db->db_disconnect();
    $db->db_connect();

    if(isset($user['role_id']) && $user['role_id'] == 2){
        header("Location: admin.php");
        exit();
    }elseif(isset($user['role_id']) && $user['role_id'] == 3){
        header("Location: home.php");
        exit();
    }else{
        header("Location: login_v2.php");
        exit();
    }

}else{

    /* CHECK TENANT USERS (STAFF / SUB-USERS FROM USERS TABLE) */
    $get_orgs = mysqli_query($conn,"
    SELECT org_id, db_name, org_name
    FROM master_inventory.master_organization
    ");

    while($org = mysqli_fetch_assoc($get_orgs)){

        $tenant_conn = mysqli_connect(
            $db_host,
            $db_user,
            $db_pass,
            $org['db_name'],
            (int)$db_port
        );

        if(!$tenant_conn){
            continue;
        }

        $username_safe = mysqli_real_escape_string($tenant_conn, $username);

        $check_user = mysqli_query($tenant_conn,"
        SELECT *
        FROM users
        WHERE username='{$username_safe}'
        LIMIT 1
        ");

        if(mysqli_num_rows($check_user) == 1){

            $user = mysqli_fetch_assoc($check_user);

            if($password !== $user['password']){
                mysqli_close($tenant_conn);
                continue;
            }

            $_SESSION['db_name']    = $org['db_name'];
            $_SESSION['org_id']     = $org['org_id'];
            $_SESSION['org_name']   = $org['org_name'];
            $_SESSION['center_id']  = $user['center_id'];
            $_SESSION['user_level'] = (int)$user['user_level'];
            $_SESSION['username']   = $user['username'];
            $_SESSION['user_id']    = (int)$user['id'];
            $_SESSION['tab_token']  = bin2hex(random_bytes(16));
            $_SESSION['just_logged_in'] = true;

            $t_center_id = (int)$user['center_id'];
            $center = mysqli_query($tenant_conn,"
            SELECT center_name
            FROM master_center
            WHERE center_id = {$t_center_id}
            LIMIT 1
            ");

            if(mysqli_num_rows($center) > 0){
                $center_row = mysqli_fetch_assoc($center);
                $_SESSION['center_name'] = $center_row['center_name'];
            }

            mysqli_close($tenant_conn);

            // 🔥 TENANT USER PERMISSIONS FROM DATABASE
            $_SESSION['menu_masters']     = isset($user['menu_masters']) ? (int)$user['menu_masters'] : 0;
            $_SESSION['menu_transaction'] = isset($user['menu_transaction']) ? (int)$user['menu_transaction'] : 0;
            $_SESSION['menu_payments']    = isset($user['menu_payments']) ? (int)$user['menu_payments'] : 0;
            $_SESSION['menu_reports']     = isset($user['menu_reports']) ? (int)$user['menu_reports'] : 0;
            $_SESSION['sub_permissions']  = !empty($user['sub_permissions']) ? array_map('trim', explode(',', $user['sub_permissions'])) : [];

            $org_id_str = mysqli_real_escape_string($conn, $org['org_id']);
            $sub = mysqli_query($conn,"
            SELECT os.*, sp.plan_type
            FROM master_inventory.organization_subscriptions os
            JOIN master_inventory.subscription_plans sp
            ON os.plan_id = sp.plan_id
            WHERE os.org_id='{$org_id_str}'
            AND os.status=1
            ORDER BY os.sub_id DESC
            LIMIT 1
            ");

            $_SESSION['inventory_access'] = 0;
            $_SESSION['billing_access'] = 0;
            $_SESSION['combined_mode'] = 0;

            if(mysqli_num_rows($sub) > 0){
                $plan_row = mysqli_fetch_assoc($sub);
                $p_type = strtolower(trim($plan_row['plan_type']));

                if($p_type == 'inventory'){
                    $_SESSION['inventory_access'] = 1;
                }
                elseif($p_type == 'billing'){
                    $_SESSION['billing_access'] = 1;
                }
                elseif($p_type == 'combined'){
                    $_SESSION['inventory_access'] = 1;
                    $_SESSION['billing_access'] = 1;
                    $_SESSION['combined_mode'] = 1;
                }
            }

            $session->login($user['id']);

            $db->db_disconnect();
            $db->db_connect();

            if($_SESSION['user_level'] == 1){
                header("Location: admin.php");
                exit();
            }else{
                header("Location: home.php");
                exit();
            }
        }
        mysqli_close($tenant_conn);
    }

    /* INVALID LOGIN */
    $session->msg("d","Invalid Username or Password");
    header("Location: login_v2.php");
    exit();
}
?>
