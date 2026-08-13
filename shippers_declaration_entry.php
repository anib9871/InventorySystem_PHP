<?php
// Errors logging enable for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once('includes/load.php');

// Global DB Connection access
global $db;

$invoice_id = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

if ($invoice_id <= 0) {
    die("Invalid Invoice ID! Please open from Invoice Print screen.");
}

/* FETCH INVOICE & CUSTOMER */
$invoice_data = find_by_sql("
    SELECT i.*, 
           c.customer_name, c.address AS cust_address, c.contact_no AS cust_phone, c.email AS customer_email
    FROM invoice i
    LEFT JOIN customer_master c ON c.id = i.customer_id
    WHERE i.id = '{$invoice_id}'
");

if (!$invoice_data) {
    die("Invoice Record #{$invoice_id} Not Found in Database!");
}
$inv = $invoice_data[0];

/* FETCH EXISTING SHIPPING ENTRY SAFELY */
$ship = null;
$existing_ship = find_by_sql("SELECT * FROM shipping_declaration WHERE invoice_id = '{$invoice_id}' LIMIT 1");
if ($existing_ship && count($existing_ship) > 0) {
    $ship = $existing_ship[0];
}

// Form Submit Handler
if (isset($_POST['save_and_preview'])) {
    
    // Helper function to safely escape string without crashing
    function safe_input($key, $default = '') {
        global $db;
        $val = isset($_POST[$key]) ? trim($_POST[$key]) : $default;
        if (is_object($db) && method_exists($db, 'escape')) {
            return $db->escape($val);
        }
        return addslashes($val);
    }

    $courier_name    = safe_input('courier_name', 'N.S. Enterprises');
    $tracking_no     = safe_input('tracking_no');
    $dispatch_date   = safe_input('dispatch_date', date('Y-m-d'));
    $declared_value  = isset($_POST['declared_value']) ? (float)$_POST['declared_value'] : (float)($inv['net_total'] ?? 0);
    $nature_of_goods = safe_input('nature_of_goods', 'Commercial Goods / Electronics');
    $no_of_packages  = safe_input('no_of_packages', '1 Box');
    $gross_weight    = safe_input('gross_weight', '1.5 Kg');
    $place           = safe_input('place', 'Delhi');
    $signatory_name  = safe_input('signatory_name', 'Authorized Signatory');
    $signatory_desig = safe_input('signatory_designation', 'Manager');

    $org_id_val  = (int)($inv['organization_id'] ?? 0);
    $cust_id_val = (int)($inv['customer_id'] ?? 0);

    if ($ship) {
        $query = "UPDATE shipping_declaration SET 
                    courier_name='{$courier_name}', 
                    tracking_no='{$tracking_no}', 
                    dispatch_date='{$dispatch_date}', 
                    declared_value='{$declared_value}', 
                    nature_of_goods='{$nature_of_goods}', 
                    no_of_packages='{$no_of_packages}', 
                    gross_weight='{$gross_weight}', 
                    place='{$place}', 
                    signatory_name='{$signatory_name}', 
                    signatory_designation='{$signatory_desig}' 
                  WHERE invoice_id='{$invoice_id}'";
    } else {
        $query = "INSERT INTO shipping_declaration 
                    (invoice_id, organization_id, customer_id, courier_name, tracking_no, dispatch_date, declared_value, nature_of_goods, no_of_packages, gross_weight, place, signatory_name, signatory_designation) 
                  VALUES 
                    ('{$invoice_id}', '{$org_id_val}', '{$cust_id_val}', '{$courier_name}', '{$tracking_no}', '{$dispatch_date}', '{$declared_value}', '{$nature_of_goods}', '{$no_of_packages}', '{$gross_weight}', '{$place}', '{$signatory_name}', '{$signatory_desig}')";
    }

    /* SAFE QUERY EXECUTION (Crash Proof) */
    if (is_object($db) && method_exists($db, 'query') && $db !== null) {
        $db->query($query);
    } else {
        find_by_sql($query); // Fallback execution
    }

    // Redirect to print page via JavaScript
    echo "<script>window.location.href='shippers_declaration_print.php?invoice_id={$invoice_id}';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Shipper's Declaration Entry - Invoice #<?= htmlspecialchars($inv['invoice_no']) ?></title>
<style>
    body { font-family: 'Segoe UI', Arial, sans-serif; background: #f1f5f9; padding: 20px; }
    .form-container { max-width: 680px; margin: 0 auto; background: #ffffff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
    .form-title { font-size: 18px; font-weight: bold; border-bottom: 2px solid #8b5cf6; padding-bottom: 8px; margin-bottom: 20px; color: #1e293b; }
    .row { display: flex; gap: 15px; margin-bottom: 15px; }
    .col { flex: 1; }
    label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px; color: #334155; }
    input[type="text"], input[type="number"], input[type="date"] { width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 5px; font-size: 13px; box-sizing: border-box; }
    .btn-submit { width: 100%; background: #8b5cf6; color: white; border: none; padding: 12px; font-size: 15px; font-weight: bold; border-radius: 6px; cursor: pointer; margin-top: 10px; }
    .btn-submit:hover { background: #7c3aed; }
    .info-bar { background: #f3e8ff; border: 1px solid #d8b4fe; padding: 10px; border-radius: 6px; font-size: 12px; margin-bottom: 20px; color: #6b21a8; }
</style>
</head>
<body>

<div class="form-container">
    <div class="form-title">📜 Shipper's Declaration Form</div>

    <div class="info-bar">
        <strong>Invoice No:</strong> <?= htmlspecialchars($inv['invoice_no']) ?> | 
        <strong>Customer:</strong> <?= htmlspecialchars($inv['customer_name']) ?>
    </div>

    <form method="post">
        <div class="row">
            <div class="col">
                <label>Courier / Transporter Name</label>
                <input type="text" name="courier_name" value="<?= htmlspecialchars($ship['courier_name'] ?? 'N.S. Enterprises') ?>" required>
            </div>
            <div class="col">
                <label>Consignment / Tracking No.</label>
                <input type="text" name="tracking_no" value="<?= htmlspecialchars($ship['tracking_no'] ?? '') ?>" placeholder="e.g. D12345678">
            </div>
        </div>

        <div class="row">
            <div class="col">
                <label>Dispatch Date</label>
                <input type="date" name="dispatch_date" value="<?= htmlspecialchars($ship['dispatch_date'] ?? date('Y-m-d')) ?>" required>
            </div>
            <div class="col">
                <label>Declared Value (₹)</label>
                <input type="number" step="0.01" name="declared_value" value="<?= htmlspecialchars($ship['declared_value'] ?? $inv['net_total']) ?>" required>
            </div>
        </div>

        <div class="row">
            <div class="col">
                <label>Nature of Goods</label>
                <input type="text" name="nature_of_goods" value="<?= htmlspecialchars($ship['nature_of_goods'] ?? 'Commercial Goods / Electronics') ?>" required>
            </div>
            <div class="col">
                <label>Total Packages</label>
                <input type="text" name="no_of_packages" value="<?= htmlspecialchars($ship['no_of_packages'] ?? '1 Box') ?>">
            </div>
        </div>

        <div class="row">
            <div class="col">
                <label>Gross Weight</label>
                <input type="text" name="gross_weight" value="<?= htmlspecialchars($ship['gross_weight'] ?? '1.5 Kg') ?>">
            </div>
            <div class="col">
                <label>Place of Issue</label>
                <input type="text" name="place" value="<?= htmlspecialchars($ship['place'] ?? 'Delhi') ?>">
            </div>
        </div>

        <div class="row">
            <div class="col">
                <label>Signatory Name</label>
                <input type="text" name="signatory_name" value="<?= htmlspecialchars($ship['signatory_name'] ?? 'Anindam Kumar Bagchi') ?>">
            </div>
            <div class="col">
                <label>Signatory Designation</label>
                <input type="text" name="signatory_designation" value="<?= htmlspecialchars($ship['signatory_designation'] ?? 'Technical Head') ?>">
            </div>
        </div>

        <button type="submit" name="save_and_preview" class="btn-submit">💾 Save & Preview Declaration 📄</button>
    </form>
</div>

</body>
</html>