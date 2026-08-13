<?php
// Error display for safety
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once('includes/load.php');

$invoice_id = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

if ($invoice_id <= 0) {
    die("Invalid Invoice ID!");
}

/* 1. FETCH INVOICE & CUSTOMER DETAILS */
$invoice_data = find_by_sql("
    SELECT i.*, 
           c.customer_name, c.address AS cust_address, c.contact_no AS cust_phone, c.email AS cust_email
    FROM invoice i
    LEFT JOIN customer_master c ON c.id = i.customer_id
    WHERE i.id = '{$invoice_id}' LIMIT 1
");

if (!$invoice_data) {
    die("Invoice Record #{$invoice_id} Not Found!");
}
$inv = $invoice_data[0];

/* 2. FETCH SAVED SHIPPER DECLARATION DATA */
$ship_data = find_by_sql("SELECT * FROM shipping_declaration WHERE invoice_id = '{$invoice_id}' LIMIT 1");
$ship = ($ship_data && count($ship_data) > 0) ? $ship_data[0] : null;

/* 3. DYNAMIC ORGANIZATION FETCH LOGIC (Form Selected Org Priority) */
$org_id = 0;
if (!empty($ship['organization_id'])) {
    $org_id = (int)$ship['organization_id'];
} elseif (!empty($inv['organization_id'])) {
    $org_id = (int)$inv['organization_id'];
} elseif (!empty($_SESSION['org_id'])) {
    $org_id = (int)$_SESSION['org_id'];
}

$org = null;
if ($org_id > 0) {
    $org_query = find_by_sql("SELECT * FROM organization_master WHERE id = '{$org_id}' LIMIT 1");
    if ($org_query && count($org_query) > 0) {
        $org = $org_query[0];
    }
}

// Fully Dynamic Org Mapping
$org_name    = !empty($org['org_name']) ? $org['org_name'] : 'Company Name';
$org_address = !empty($org['address']) ? $org['address'] : '';
$org_email   = !empty($org['email']) ? $org['email'] : '';
$org_phone   = !empty($org['phone']) ? $org['phone'] : '';

// Fallback Values mapping for Shipping
$courier_name = $ship['courier_name'] ?? 'N.S. Enterprises';
$tracking_no  = !empty($ship['tracking_no']) ? $ship['tracking_no'] : '____________________';
$dispatch_date= !empty($ship['dispatch_date']) ? date('d-m-Y', strtotime($ship['dispatch_date'])) : date('d-m-Y');
$declared_val = !empty($ship['declared_value']) ? number_format($ship['declared_value'], 2) : number_format($inv['net_total'], 2);
$nature_goods = $ship['nature_of_goods'] ?? 'Commercial Goods / Electronics';
$packages     = $ship['no_of_packages'] ?? '1 Box';
$weight       = $ship['gross_weight'] ?? '1.5 Kg';
$place        = $ship['place'] ?? 'Delhi';
$sig_name     = $ship['signatory_name'] ?? 'Authorized Signatory';
$sig_desig    = $ship['signatory_designation'] ?? 'Manager';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Shipper's Declaration - Invoice #<?= htmlspecialchars($inv['invoice_no']) ?></title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: 'Times New Roman', Times, serif, Arial, sans-serif;
        font-size: 14px;
        line-height: 1.6;
        color: #000;
        background: #f1f5f9;
        padding: 20px;
    }

    .no-print-bar {
        max-width: 800px;
        margin: 0 auto 15px auto;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .btn {
        padding: 8px 16px;
        font-size: 13px;
        font-weight: bold;
        border-radius: 5px;
        cursor: pointer;
        text-decoration: none;
        border: none;
    }
    .btn-print { background: #10b981; color: #fff; }
    .btn-back { background: #64748b; color: #fff; }

    /* A4 Document Box */
    .declaration-page {
        width: 210mm;
        min-height: 297mm;
        background: #ffffff;
        margin: 0 auto;
        padding: 20mm 20mm 15mm 20mm;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    /* Top Brand Header */
    .header-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 2px solid #8b5cf6;
        padding-bottom: 12px;
        margin-bottom: 25px;
    }
    .brand-name {
        font-size: 26px;
        font-weight: bold;
        color: #7c3aed;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .brand-contact {
        text-align: right;
        font-size: 12px;
        line-height: 1.4;
        color: #334155;
    }

    /* Document Title */
    .doc-title {
        text-align: center;
        font-weight: bold;
        font-size: 18px;
        text-transform: uppercase;
        margin-bottom: 4px;
        letter-spacing: 1px;
    }
    .doc-subtitle {
        text-align: center;
        font-size: 12px;
        font-weight: bold;
        text-decoration: underline;
        margin-bottom: 25px;
    }

    /* Paragraph Text */
    .content-p {
        text-align: justify;
        margin-bottom: 15px;
        font-size: 14px;
    }

    /* Details List */
    .section-title {
        font-weight: bold;
        margin-top: 15px;
        margin-bottom: 5px;
    }
    .details-list {
        margin-left: 20px;
        margin-bottom: 20px;
    }
    .details-list li {
        margin-bottom: 4px;
    }

    /* Declaration Points */
    .declaration-ol {
        margin-left: 20px;
        margin-bottom: 30px;
    }
    .declaration-ol li {
        margin-bottom: 6px;
        text-align: justify;
    }

    /* Signature Section */
    .signature-container {
        margin-top: 20px;
    }

    /* Bottom Footer Banner */
    .footer-banner {
        background: #a855f7;
        color: #ffffff;
        text-align: center;
        padding: 10px;
        font-weight: bold;
        font-size: 13px;
        border-radius: 4px;
        margin-top: 30px;
    }

    @media print {
        body { background: #fff; padding: 0; }
        .no-print-bar { display: none !important; }
        .declaration-page {
            box-shadow: none;
            width: 100%;
            height: auto;
            padding: 10mm 15mm;
        }
    }
</style>
</head>
<body>

<div class="no-print-bar">
    <button onclick="window.history.back()" class="btn btn-back">← Edit Details</button>
    <button onclick="window.print()" class="btn btn-print">🖨️ Print Declaration</button>
</div>

<div class="declaration-page">
    <div>
        <div class="header-top">
            <div>
                <div class="brand-name"><?= htmlspecialchars($org_name) ?></div>
            </div>
            <div class="brand-contact">
                <?php if(!empty($org_email)): ?>📧 <?= htmlspecialchars($org_email) ?><br><?php endif; ?>
                <?php if(!empty($org_phone)): ?>📞 <?= htmlspecialchars($org_phone) ?><?php endif; ?>
            </div>
        </div>

        <div class="doc-title">SHIPPER’S DECLARATION</div>
        <div class="doc-subtitle">TO WHOMSOEVER IT MAY CONCERN</div>

        <div class="content-p">
            This is to verify and confirm that the consignment booked by us, <strong><?= htmlspecialchars($org_name) ?></strong>, is accurately described and prepared in accordance with all applicable laws and regulations.
        </div>

        <div class="content-p">
            The consignment was booked through <strong>M/S <?= htmlspecialchars($courier_name) ?></strong> under COURIER CONSIGNMENT NO. <strong><?= htmlspecialchars($tracking_no) ?></strong> Dated <strong><?= htmlspecialchars($dispatch_date) ?></strong>, with a total Declared Value of <strong>₹ <?= htmlspecialchars($declared_val) ?></strong>.
        </div>

        <div class="section-title">Consignment Details:</div>
        <ul class="details-list">
            <li><strong>Nature of Goods:</strong> <?= htmlspecialchars($nature_goods) ?></li>
            <li><strong>Total Number of Packages:</strong> <?= htmlspecialchars($packages) ?></li>
            <li><strong>Total Gross Weight:</strong> <?= htmlspecialchars($weight) ?></li>
            <li><strong>Shipper/Origin Address:</strong> <?= htmlspecialchars($org_address) ?></li>
            <li><strong>Consignee/Destination Address:</strong> <?= htmlspecialchars($inv['customer_name'] ?? '') ?>, <?= htmlspecialchars($inv['cust_address'] ?? '') ?> (Ph: <?= htmlspecialchars($inv['cust_phone'] ?? '') ?>)</li>
        </ul>

        <div class="section-title">Declaration and Certification:</div>
        <div style="margin-bottom: 5px;">We hereby certify that:</div>
        <ol class="declaration-ol">
            <li>The information provided above is complete and accurate.</li>
            <li>The goods do <strong>not</strong> contain any items classified as <strong>Dangerous Goods</strong> (e.g., explosives, flammable liquids/solids, corrosive materials, or compressed gases).</li>
            <li>The consignment does <strong>not</strong> contain any restricted items, contraband, or illegal material.</li>
        </ol>

        <hr style="border: 0; border-top: 1px solid #000; margin-bottom: 25px;">

        <div class="signature-container">
            <div><strong>Place:</strong> <?= htmlspecialchars($place) ?></div>
            <div><strong>Date:</strong> <?= date('d-m-Y') ?></div>
            <div style="margin-top: 15px;"><strong>For <?= htmlspecialchars($org_name) ?></strong></div>
            <div style="margin-top: 40px;"><strong>Authorized Signatory:</strong> ______________________</div>
            <div><strong>Name:</strong> <?= htmlspecialchars($sig_name) ?></div>
            <div><strong>Designation:</strong> <?= htmlspecialchars($sig_desig) ?></div>
        </div>
    </div>

    <div class="footer-banner">
        <?= htmlspecialchars($org_name) ?><?= !empty($org_address) ? ', ' . htmlspecialchars($org_address) : '' ?>
    </div>
</div>

</body>
</html>