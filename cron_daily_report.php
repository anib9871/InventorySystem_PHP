<?php
date_default_timezone_set('Asia/Kolkata');

// 1. Session CLI Warning Fix
if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    session_start();
}

// 2. Project Core Includes
if (file_exists(__DIR__ . '/includes/load.php')) {
    @include_once __DIR__ . '/includes/load.php';
}

// 3. Dompdf Framework Initialize
if (!function_exists('load_dompdf_framework') || !load_dompdf_framework()) {
    if (file_exists(__DIR__ . '/libs/dompdf/autoload.inc.php')) {
        require_once __DIR__ . '/libs/dompdf/autoload.inc.php';
    }
}

use Dompdf\Dompdf;

// 4. RAILWAY PRODUCTION DATABASE CONNECTION
$master_host = getenv('MYSQLHOST') ?: '127.0.0.1';
$master_user = getenv('MYSQLUSER') ?: 'root';
$master_pass = getenv('MYSQLPASSWORD') ?: '';
$master_port = getenv('MYSQLPORT') ?: '3306';
$master_db   = 'master_inventory';

$conn_master = mysqli_connect($master_host, $master_user, $master_pass, $master_db, $master_port);

if (!$conn_master) {
    die("Master DB Connection Failed: " . mysqli_connect_error() . "\n");
}

$today = date('Y-m-d');
$today_formatted = date('d M Y');

// 5. Target Organizations (Jinki auto_report = 1 hai)
$org_query = "SELECT * FROM master_inventory.master_organization WHERE auto_report = 1";
$org_result = mysqli_query($conn_master, $org_query);

if (!$org_result || mysqli_num_rows($org_result) == 0) {
    echo "No organizations found with auto-report enabled.\n";
    exit;
}

echo "Found " . mysqli_num_rows($org_result) . " organization(s) for auto-report.\n";

while ($org = mysqli_fetch_assoc($org_result)) {
    $org_id      = $org['org_id'];
    $org_name    = $org['org_name'];
    $db_name     = $org['db_name'];
    $admin_email = $org['report_email'] ?? '';

    echo "\n----------------------------------------\n";
    echo "Processing Organization: {$org_name} (DB: {$db_name})...\n";

    // Fallback logic for admin email (if report_email is NULL)
    if (empty($admin_email)) {
        $user_q = mysqli_query($conn_master, "SELECT username FROM master_inventory.user_credentials WHERE org_id = '{$org_id}' AND role_id = 2 LIMIT 1");
        if ($user_q && $user_r = mysqli_fetch_assoc($user_q)) {
            $admin_email = $user_r['username'];
        }
    }

    if (empty($admin_email)) {
        echo "Skipping {$org_name}: No report_email or Admin user found in master_inventory.\n";
        continue;
    }

    echo "Target Admin Email: {$admin_email}\n";

    // Connect Respective Organization Database
    $conn_org = @mysqli_connect($master_host, $master_user, $master_pass, $db_name, $master_port);

    if (!$conn_org) {
        echo "Failed to connect to Org DB: {$db_name}\n";
        continue;
    }

    /* ══════════════════════════════════════════════════════════
       1. SALES TRANSACTIONS DATA
    ══════════════════════════════════════════════════════════ */
    $sales_sql = "
        SELECT 
            t.transaction_id,
            t.bill_indent_no AS invoice_no,
            DATE(t.entry_date) AS sale_date,
            p.name AS product_name,
            cm.customer_name,
            t.quantity AS sold_qty,
            t.unit_price AS sell_price,
            t.discount_amount,
            t.gst_amount,
            t.sale_net AS total_sale
        FROM transaction_master t
        LEFT JOIN invoice i ON i.invoice_no = t.bill_indent_no
        LEFT JOIN customer_master cm ON cm.id = i.customer_id
        LEFT JOIN products p ON p.id = t.product_id
        WHERE t.transaction_type = 2 
        AND t.sale_net > 0 
        AND DATE(t.entry_date) = '{$today}'
        ORDER BY t.entry_date DESC
    ";

    $sales_res = mysqli_query($conn_org, $sales_sql);

    // ❌ Agar aaj koi sale nahi hui, to mail skip
    if (!$sales_res || mysqli_num_rows($sales_res) == 0) {
        echo "No sales today for {$org_name}. Email skipped.\n";
        mysqli_close($conn_org);
        continue;
    }

    echo "Sales records found! Calculating Revenue & Building Single Combined PDF...\n";

    $sales_data = [];
    $total_sales_amount = 0;
    while ($r = mysqli_fetch_assoc($sales_res)) {
        $sales_data[] = $r;
        $total_sales_amount += (float)$r['total_sale'];
    }

    /* ══════════════════════════════════════════════════════════
       2. REVENUE DATA (INCOME VS EXPENDITURE)
    ══════════════════════════════════════════════════════════ */
    $rev_sql = "
        SELECT 
            IFNULL(inc.total_income, 0) AS total_income,
            IFNULL(exp.total_expenditure, 0) AS total_expenditure
        FROM (SELECT '{$today}' AS report_date) dt
        LEFT JOIN (
            SELECT SUM(t.net_price) AS total_income
            FROM transaction_master t
            WHERE t.transaction_type = 2 AND DATE(t.entry_date) = '{$today}'
        ) inc ON 1=1
        LEFT JOIN (
            SELECT SUM(t.net_price) AS total_expenditure
            FROM transaction_master t
            WHERE t.transaction_type = 1 AND DATE(t.entry_date) = '{$today}'
        ) exp ON 1=1
    ";
    $rev_res = mysqli_query($conn_org, $rev_sql);
    $rev_row = mysqli_fetch_assoc($rev_res);

    $total_income      = (float)($rev_row['total_income'] ?? $total_sales_amount);
    $total_expenditure = (float)($rev_row['total_expenditure'] ?? 0);
    $net_revenue       = $total_income - $total_expenditure;
    $net_color         = $net_revenue >= 0 ? '#16a34a' : '#dc2626';

    /* ══════════════════════════════════════════════════════════
       3. COMBINED HTML LAYOUT BUILD
    ══════════════════════════════════════════════════════════ */
    $sales_rows = "";
    foreach ($sales_data as $s) {
        $sales_rows .= "
            <tr>
                <td style='padding:5px; border:1px solid #cbd5e1;'>" . date('d/M/Y', strtotime($s['sale_date'])) . "</td>
                <td style='padding:5px; border:1px solid #cbd5e1; font-weight:bold;'>" . htmlspecialchars($s['invoice_no']) . "</td>
                <td style='padding:5px; border:1px solid #cbd5e1;'>" . htmlspecialchars($s['customer_name'] ?? '-') . "</td>
                <td style='padding:5px; border:1px solid #cbd5e1;'>" . htmlspecialchars($s['product_name']) . "</td>
                <td style='padding:5px; border:1px solid #cbd5e1; text-align:center;'>" . $s['sold_qty'] . "</td>
                <td style='padding:5px; border:1px solid #cbd5e1; text-align:right;'>" . number_format($s['sell_price'], 2) . "</td>
                <td style='padding:5px; border:1px solid #cbd5e1; text-align:right;'>Rs. " . number_format($s['discount_amount'], 2) . "</td>
                <td style='padding:5px; border:1px solid #cbd5e1; text-align:right;'>Rs. " . number_format($s['gst_amount'], 2) . "</td>
                <td style='padding:5px; border:1px solid #cbd5e1; text-align:right; font-weight:bold; color:#2563eb;'>Rs. " . number_format($s['total_sale'], 2) . "</td>
            </tr>";
    }

    $combined_pdf_html = "
    <html>
    <head>
        <style>
            body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10px; color: #1e293b; margin: 0; padding: 0; }
            .rpt-header { text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 6px; margin-bottom: 12px; }
            .rpt-header h2 { font-size: 16px; font-weight: 800; text-transform: uppercase; margin: 0; color: #0f172a; }
            .rpt-header h3 { font-size: 11px; margin: 3px 0; color: #334155; text-transform: uppercase; letter-spacing: 0.5px; }
            .rpt-header p { font-size: 9px; color: #64748b; margin: 0; }
            
            .sec-title { font-size: 10px; font-weight: bold; color: #0f172a; text-transform: uppercase; margin-bottom: 6px; border-left: 3px solid #2563eb; padding-left: 6px; }

            .summary-table { width: 100%; margin-bottom: 15px; border-collapse: separate; border-spacing: 8px 0; }
            .card { background: #ffffff; border-radius: 6px; padding: 8px 10px; border-left: 4px solid #2563eb; border-top: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; }
            .card.exp { border-left-color: #dc2626; }
            .card.net { border-left-color: {$net_color}; }
            .card .title { font-size: 8px; text-transform: uppercase; color: #64748b; font-weight: bold; }
            .card .value { font-size: 14px; font-weight: 800; color: #0f172a; margin-top: 3px; }

            table.tbl { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
            table.tbl th { background-color: #f1f5f9; color: #334155; padding: 5px 6px; border: 1px solid #cbd5e1; font-size: 8.5px; text-transform: uppercase; text-align: left; }
            table.tbl td { padding: 5px 6px; border: 1px solid #cbd5e1; font-size: 8.5px; }
        </style>
    </head>
    <body>
        <!-- Header -->
        <div class='rpt-header'>
            <h2>" . htmlspecialchars($org_name) . "</h2>
            <h3>Daily Comprehensive Sales & Revenue Report</h3>
            <p>Date: {$today_formatted}</p>
        </div>

        <!-- Section 1: Revenue Widgets -->
        <div class='sec-title'>1. Daily Revenue Summary</div>
        <table class='summary-table'>
            <tr>
                <td width='33%'>
                    <div class='card'>
                        <div class='title'>Total Sales / Income</div>
                        <div class='value' style='color:#2563eb;'>Rs. " . number_format($total_income, 2) . "</div>
                    </div>
                </td>
                <td width='33%'>
                    <div class='card exp'>
                        <div class='title'>Total Expenditure</div>
                        <div class='value' style='color:#dc2626;'>Rs. " . number_format($total_expenditure, 2) . "</div>
                    </div>
                </td>
                <td width='33%'>
                    <div class='card net'>
                        <div class='title'>Net Flow (Profit / Loss)</div>
                        <div class='value' style='color:{$net_color};'>Rs. " . number_format($net_revenue, 2) . "</div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Section 2: Detailed Sales Transactions Table -->
        <div class='sec-title'>2. Daily Sales Transactions Breakdown</div>
        <table class='tbl'>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Invoice No</th>
                    <th>Customer Name</th>
                    <th>Product Name</th>
                    <th style='text-align:center;'>Qty</th>
                    <th style='text-align:right;'>Price</th>
                    <th style='text-align:right;'>Discount</th>
                    <th style='text-align:right;'>GST</th>
                    <th style='text-align:right;'>Total Net</th>
                </tr>
            </thead>
            <tbody>
                {$sales_rows}
            </tbody>
            <tfoot>
                <tr style='background:#e2e8f0; font-weight:bold;'>
                    <td colspan='8' style='text-align:right; padding:6px; border:1px solid #cbd5e1;'>GRAND TOTAL COLLECTION</td>
                    <td style='text-align:right; padding:6px; border:1px solid #cbd5e1; color:#2563eb;'>Rs. " . number_format($total_sales_amount, 2) . "</td>
                </tr>
            </tfoot>
        </table>
    </body>
    </html>";

    // Dompdf Render
    $dompdf = new Dompdf();
    $dompdf->loadHtml($combined_pdf_html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $pdf_base64 = base64_encode($dompdf->output());

    /* ══════════════════════════════════════════════════════════
       4. SEND VIA BREVO (STRICTLY FROM RAILWAY ENV VARIABLES)
    ══════════════════════════════════════════════════════════ */
    echo "Combined PDF generated! Sending email via Brevo REST API...\n";

    $apiKey      = getenv('BREVO_API_KEY');
    $senderEmail = getenv('BREVO_SENDER_EMAIL');

    if (empty($apiKey) || empty($senderEmail)) {
        echo "ERROR: BREVO_API_KEY or BREVO_SENDER_EMAIL is missing in Railway Environment Variables!\n";
        mysqli_close($conn_org);
        continue;
    }

    $date_str = date('d-M-Y');

    $subject = "Daily Sales & Revenue Report - " . $org_name . " (" . date('d/M/Y') . ")";
    $body = "Dear <b>Admin</b>,<br><br>Please find attached the daily comprehensive report for <b>" . htmlspecialchars($org_name) . "</b> for date <b>" . $date_str . "</b>.<br><br><b>Today's Total Collection:</b> <span style='color:green; font-weight:bold;'>Rs. " . number_format($total_sales_amount, 2) . "</span><br><br>Regards,<br><b>Inventory Master System</b>";

    $postData = [
        "sender" => ["name" => $org_name, "email" => $senderEmail],
        "to" => [["email" => $admin_email, "name" => "Admin"]],
        "subject" => $subject,
        "htmlContent" => $body,
        "attachment" => [
            [
                "content" => $pdf_base64,
                "name"    => "Daily_Sales_Revenue_Report_{$date_str}.pdf"
            ]
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json',
        'api-key: ' . $apiKey,
        'content-type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 201 || $httpCode == 200) {
        echo "SUCCESS: Combined Daily PDF Report sent successfully to Admin ({$admin_email}) for {$org_name}.\n";
    } else {
        echo "ERROR: Brevo API HTTP Code: {$httpCode} | Response: {$response}\n";
    }

    mysqli_close($conn_org);
}

mysqli_close($conn_master);
?>
