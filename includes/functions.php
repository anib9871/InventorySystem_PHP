/*--------------------------------------------------------------*/
/* 1. SEND INVOICE EMAIL VIA BREVO API (PURE HTML & LINK FIX)
/*--------------------------------------------------------------*/
function send_invoice_email($invoice_id, $to_email, $customer_name) {
    global $db;

    try {
        $apiKey      = $_ENV['BREVO_API_KEY'] ?? $_SERVER['BREVO_API_KEY'] ?? getenv('BREVO_API_KEY');
        $senderEmail = $_ENV['BREVO_SENDER_EMAIL'] ?? $_SERVER['BREVO_SENDER_EMAIL'] ?? getenv('BREVO_SENDER_EMAIL');

        if (empty($apiKey)) return "BREVO_API_KEY is EMPTY in Railway Environment Variables!";
        if (empty($senderEmail)) return "BREVO_SENDER_EMAIL is EMPTY in Railway Environment Variables!";

        $invoice_data = find_by_sql("
            SELECT i.*, c.customer_name, c.address, c.gst_no, c.contact_no
            FROM invoice i
            LEFT JOIN customer_master c ON c.id = i.customer_id
            WHERE i.id = '{$invoice_id}' LIMIT 1
        ");

        if (empty($invoice_data)) return "Invoice ID {$invoice_id} not found in DB!";
        $invoice = $invoice_data[0];

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $print_url = $protocol . $host . "/invoice_print.php?id=" . $invoice_id;

        $subject = "Invoice #" . $invoice['invoice_no'] . " from Billing Team";
        $body = "
        <html>
        <body style='font-family: Arial, sans-serif; font-size: 14px; color: #1e293b; line-height: 1.6; padding: 20px;'>
            <div style='max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; padding: 24px; border-radius: 8px;'>
                <h2 style='color: #2563eb; margin-top: 0;'>Dear " . htmlspecialchars($customer_name) . ",</h2>
                <p>Your invoice <b>#" . htmlspecialchars($invoice['invoice_no']) . "</b> has been generated.</p>
                <p><b>Total Amount:</b> ₹" . number_format($invoice['net_total'], 2) . "</p>
                <p><b>Date:</b> " . date("d/M/Y", strtotime($invoice['invoice_date'])) . "</p>
                <br>
                <div style='text-align: center; margin: 20px 0;'>
                    <a href='" . $print_url . "' target='_blank' style='background-color: #2563eb; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;'>View & Print Invoice</a>
                </div>
                <br>
                <p style='color: #64748b; font-size: 12px; margin-bottom: 0;'>Regards,<br><b>Billing Team</b></p>
            </div>
        </body>
        </html>
        ";

        $data = [
            "sender" => ["name" => "Invoice Billing", "email" => $senderEmail],
            "to" => [["email" => $to_email, "name" => $customer_name]],
            "subject" => $subject,
            "htmlContent" => $body
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'api-key: ' . $apiKey,
            'content-type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($httpCode == 201 || $httpCode == 200) {
            return true;
        } else {
            return "HTTP Code: {$httpCode} | cURL Err: {$curlErr} | Brevo Msg: {$response}";
        }
    } catch (Exception $e) {
        return "PHP Exception: " . $e->getMessage();
    }
}

/*--------------------------------------------------------------*/
/* 2. SEND QUOTATION EMAIL VIA BREVO API
/*--------------------------------------------------------------*/
function send_quotation_email($quotation_id, $to_email, $customer_name) {
    global $db;

    try {
        $apiKey      = $_ENV['BREVO_API_KEY'] ?? $_SERVER['BREVO_API_KEY'] ?? getenv('BREVO_API_KEY');
        $senderEmail = $_ENV['BREVO_SENDER_EMAIL'] ?? $_SERVER['BREVO_SENDER_EMAIL'] ?? getenv('BREVO_SENDER_EMAIL');

        if (empty($apiKey)) return "BREVO_API_KEY is EMPTY in Railway Environment Variables!";
        if (empty($senderEmail)) return "BREVO_SENDER_EMAIL is EMPTY in Railway Environment Variables!";

        $q_data = find_by_sql("
            SELECT q.*, c.customer_name, c.address, c.gst_no, c.contact_no
            FROM quotation_master q
            LEFT JOIN customer_master c ON c.id = q.customer_id
            WHERE q.id = '{$quotation_id}' LIMIT 1
        ");

        if (empty($q_data)) return "Quotation ID {$quotation_id} not found in DB!";
        $quotation = $q_data[0];

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $print_url = $protocol . $host . "/quotation_print.php?id=" . $quotation_id;

        $subject = "Quotation #" . $quotation['quotation_no'] . " from Billing Team";
        $body = "
        <html>
        <body style='font-family: Arial, sans-serif; font-size: 14px; color: #1e293b; line-height: 1.6; padding: 20px;'>
            <div style='max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; padding: 24px; border-radius: 8px;'>
                <h2 style='color: #2563eb; margin-top: 0;'>Dear " . htmlspecialchars($customer_name) . ",</h2>
                <p>Please find details for your requested Quotation <b>#" . htmlspecialchars($quotation['quotation_no']) . "</b>.</p>
                <p><b>Total Amount:</b> ₹" . number_format($quotation['net_total'], 2) . "</p>
                <br>
                <div style='text-align: center; margin: 20px 0;'>
                    <a href='" . $print_url . "' target='_blank' style='background-color: #2563eb; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;'>View & Print Quotation</a>
                </div>
                <br>
                <p style='color: #64748b; font-size: 12px; margin-bottom: 0;'>Regards,<br><b>Billing Team</b></p>
            </div>
        </body>
        </html>
        ";

        $data = [
            "sender" => ["name" => "Quotation Billing", "email" => $senderEmail],
            "to" => [["email" => $to_email, "name" => $customer_name]],
            "subject" => $subject,
            "htmlContent" => $body
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'api-key: ' . $apiKey,
            'content-type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($httpCode == 201 || $httpCode == 200) {
            return true;
        } else {
            return "HTTP Code: {$httpCode} | cURL Err: {$curlErr} | Brevo Msg: {$response}";
        }
    } catch (Exception $e) {
        return "PHP Exception: " . $e->getMessage();
    }
}
