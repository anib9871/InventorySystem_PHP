<?php
$errors = array();

/*--------------------------------------------------------------*/
/* Helper Functions
/*--------------------------------------------------------------*/
function real_escape($str){
  global $con;
  $escape = mysqli_real_escape_string($con,$str);
  return $escape;
}

function remove_junk($str){
  $str = nl2br($str);
  $str = htmlspecialchars(strip_tags($str, ENT_QUOTES));
  return $str;
}

function first_character($str){
  $val = str_replace('-'," ",$str);
  $val = ucfirst($val);
  return $val;
}

function validate_fields($var){
  global $errors;
  foreach ($var as $field) {
    $val = remove_junk($_POST[$field]);
    if(isset($val) && $val==''){
      $errors = $field ." can't be blank.";
      return $errors;
    }
  }
}

function display_msg($msg =''){
   $output = array();
   if(!empty($msg)) {
      foreach ($msg as $key => $value) {
         $output  = "<div class=\"alert alert-{$key}\">";
         $output .= "<a href=\"#\" class=\"close\" data-dismiss=\"alert\">&times;</a>";
         $output .= remove_junk(first_character($value));
         $output .= "</div>";
      }
      return $output;
   } else {
      return "" ;
   }
}

function redirect($url, $permanent = false)
{
    if (headers_sent() === false)
    {
      header('Location: ' . $url, true, ($permanent === true) ? 301 : 302);
    }
    exit();
}

function total_price($totals){
   $sum = 0;
   $sub = 0;
   foreach($totals as $total ){
     $sum += $total['total_saleing_price'];
     $sum += $total['total_buying_price'];
     $profit = $sum - $sub;
   }
   return array($sum,$profit);
}

function read_date($str){
     if($str)
      return date('F j, Y, g:i:s a', strtotime($str));
     else
      return null;
}

function make_date(){
  return strftime("%Y-%m-%d %H:%M:%S", time());
}

function count_id(){
  static $count = 1;
  return $count++;
}

function randString($length = 5)
{
  $str='';
  $cha = "0123456789abcdefghijklmnopqrstuvwxyz";

  for($x=0; $x<$length; $x++)
   $str .= $cha[mt_rand(0,strlen($cha))];
  return $str;
}

function getSystemMode(){
  if(isset($_SESSION['combined_mode']) && $_SESSION['combined_mode'] == 1){
    return 'combined';
  }
  if(isset($_SESSION['billing_access']) && $_SESSION['billing_access'] == 1){
    return 'billing';
  }
  if(isset($_SESSION['inventory_access']) && $_SESSION['inventory_access'] == 1){
    return 'inventory';
  }
  return 'inventory';
}

/* HELPER FUNCTIONS FOR NUMBER TO WORDS */
if (!function_exists('convertGroup')) {
    function convertGroup($num) {
        $ones = array(0 => "", 1 => "One", 2 => "Two", 3 => "Three", 4 => "Four", 5 => "Five", 6 => "Six", 7 => "Seven", 8 => "Eight", 9 => "Nine", 10 => "Ten", 11 => "Eleven", 12 => "Twelve", 13 => "Thirteen", 14 => "Fourteen", 15 => "Fifteen", 16 => "Sixteen", 17 => "Seventeen", 18 => "Eighteen", 19 => "Nineteen");
        $tens = array(0 => "", 2 => "Twenty", 3 => "Thirty", 4 => "Forty", 5 => "Fifty", 6 => "Sixty", 7 => "Seventy", 8 => "Eighty", 9 => "Ninety");
        if ($num == 0) return "";
        if ($num < 20) return $ones[$num];
        if ($num < 100) return trim($tens[intval($num / 10)] . " " . $ones[$num % 10]);
        if ($num < 1000) return trim($ones[intval($num / 100)] . " Hundred " . convertGroup($num % 100));
        if ($num < 100000) return trim(convertGroup(intval($num / 1000)) . " Thousand " . convertGroup($num % 1000));
        if ($num < 10000000) return trim(convertGroup(intval($num / 100000)) . " Lakh " . convertGroup($num % 100000));
        return trim(convertGroup(intval($num / 10000000)) . " Crore " . convertGroup($num % 10000000));
    }
}

if (!function_exists('numberToWords')) {
    function numberToWords($amount) {
        $amount = round($amount, 2);
        $rupees = intval($amount);
        $paise = intval(round(($amount - $rupees) * 100));
        if ($rupees == 0 && $paise == 0) return "Zero";
        $rupeesInWords = ($rupees > 0) ? convertGroup($rupees) : "Zero";
        $result = $rupeesInWords;
        if ($paise > 0) {
            $result .= " and " . convertGroup($paise) . " Paise";
        }
        return trim($result);
    }
}

/*--------------------------------------------------------------*/
/* UNIVERSAL AUTOLOADER FOR DOMPDF (COMPOSER ONLY)
/*--------------------------------------------------------------*/
function load_dompdf_framework() {
    if (class_exists('Dompdf\Dompdf')) {
        return true;
    }

    $possible_autoloads = [
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/vendor/autoload.php',
        $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php',
        '/app/vendor/autoload.php'
    ];

    foreach ($possible_autoloads as $file) {
        if (file_exists($file)) {
            require_once($file);
            if (class_exists('Dompdf\Dompdf')) {
                return true;
            }
        }
    }

    return false;
}

/*--------------------------------------------------------------*/
/* 1. SEND INVOICE PDF VIA BREVO
/*--------------------------------------------------------------*/
function send_invoice_email($invoice_id, $to_email, $customer_name) {
    global $db;

    if (!load_dompdf_framework()) {
        return "Dompdf autoload error! Clean vendor folder not found.";
    }

    $invoice_data = find_by_sql("
        SELECT i.*, c.customer_name, c.address, c.gst_no, c.contact_no
        FROM invoice i
        LEFT JOIN customer_master c ON c.id = i.customer_id
        WHERE i.id = '{$invoice_id}' LIMIT 1
    ");

    if (empty($invoice_data)) return "Invoice ID {$invoice_id} not found in DB!";
    $invoice = $invoice_data[0];

    $is_revised = isset($invoice['is_revised']) && $invoice['is_revised'] == 1;
    $title_text = $is_revised ? 'REVISED INVOICE' : 'INVOICE';
    $title_color = $is_revised ? '#dc2626' : '#2563eb';

    $org_master = find_by_sql("
        SELECT org_name 
        FROM master_inventory.master_organization 
        WHERE org_id = '{$invoice['organization_id']}' LIMIT 1
    ");
    $org_master = $org_master ? $org_master[0] : ['org_name' => ''];

    $org_data = find_by_sql("SELECT * FROM organization_master WHERE id = '{$invoice['organization_id']}' LIMIT 1");
    $org = $org_data ? $org_data[0] : ['address' => '', 'gst_no' => '', 'phone' => ''];

    $org_state  = substr($org['gst_no'] ?? '', 0, 2);
    $cust_state = substr($invoice['gst_no'] ?? '', 0, 2);
    $tax_mode   = ($org_state == $cust_state) ? 'CGST_SGST' : 'IGST';

    $bank_data = find_by_sql("SELECT * FROM bank_master WHERE organization_id = '{$invoice['organization_id']}' LIMIT 1");
    $bank = $bank_data ? $bank_data[0] : null;

    $items = find_by_sql("
        SELECT ii.*, p.name, p.hsn_code
        FROM invoice_items ii
        LEFT JOIN products p ON p.id = ii.product_id
        WHERE ii.invoice_id = '{$invoice_id}'
    ");

    $org_name_upper = strtoupper($org_master['org_name']);
    $cust_name_upper = strtoupper($invoice['customer_name']);
    $inv_date_formatted = date("d/M/Y", strtotime($invoice['invoice_date']));
    $net_total_words = numberToWords($invoice['net_total']);

    $html = '
    <html>
    <head>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 11px; color: #1e293b; margin: 0; padding: 0; }
        .wrapper { width: 100%; }
        .header-table { width: 100%; border-bottom: 1px solid #e2e8f0; margin-bottom: 10px; padding-bottom: 8px; }
        .org-title { font-size: 16px; font-weight: bold; color: #1e293b; margin-bottom: 3px; }
        .inv-title { font-size: 18px; font-weight: bold; color: ' . $title_color . '; text-align: right; text-transform: uppercase; }
        .meta-table { font-size: 10px; width: 100%; text-align: right; }
        .info-card { background: #eff6ff; border: 1px solid #dbeafe; padding: 8px; margin-bottom: 10px; border-radius: 4px; }
        .card-title { font-size: 9px; font-weight: bold; color: #2563eb; text-transform: uppercase; margin-bottom: 2px; }
        .customer-name { font-size: 12px; font-weight: bold; color: #1e293b; }
        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.data-table th { background: #f8fafc; color: #64748b; font-size: 9px; text-transform: uppercase; padding: 5px; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; }
        table.data-table td { padding: 5px; border-bottom: 1px solid #e2e8f0; font-size: 10px; }
        .right { text-align: right; } .center { text-align: center; } .bold { font-weight: bold; }
        .summary-row td { border-bottom: none; padding: 3px 5px; }
        .grand-total td { border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; background: #eff6ff; font-size: 11px; color: #1d4ed8; font-weight: bold; }
        .amount-words-box { background: #f8fafc; border: 1px dashed #e2e8f0; padding: 6px; margin-bottom: 10px; font-size: 10px; }
        .footer-table { width: 100%; margin-top: 10px; }
        .footer-card { border: 1px solid #e2e8f0; padding: 6px; border-radius: 4px; font-size: 10px; vertical-align: top; }
        .footer-title { font-size: 9px; font-weight: bold; color: #64748b; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; margin-bottom: 4px; padding-bottom: 2px; }
    </style>
    </head>
    <body>
    <div class="wrapper">
        <table class="header-table">
            <tr>
                <td width="60%">
                    <div class="org-title">' . $org_name_upper . '</div>
                    <div style="color: #64748b; font-size: 10px;">
                        ' . nl2br(htmlspecialchars($org['address'] ?? '')) . '<br>
                        <b>GSTIN:</b> ' . htmlspecialchars($org['gst_no'] ?? '') . ' | <b>Phone:</b> ' . htmlspecialchars($org['phone'] ?? '') . '
                    </div>
                </td>
                <td width="40%" style="vertical-align: top;">
                    <div class="inv-title">' . $title_text . '</div>
                    <table class="meta-table">
                        <tr><td style="color:#64748b;">Invoice No:</td><td class="bold">' . htmlspecialchars($invoice['invoice_no']) . '</td></tr>
                        <tr><td style="color:#64748b;">Date:</td><td class="bold">' . $inv_date_formatted . '</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="info-card">
            <div class="card-title">Billed To</div>
            <div class="customer-name">' . $cust_name_upper . '</div>
            <div style="font-size: 10px; line-height: 1.3;">
                ' . nl2br(htmlspecialchars($invoice['address'] ?? '')) . '<br>
                <b>GSTIN:</b> ' . htmlspecialchars($invoice['gst_no'] ?? '') . ' | <b>Phone:</b> ' . htmlspecialchars($invoice['contact_no'] ?? '') . '
            </div>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th class="center" width="5%">#</th>
                    <th width="35%">Item Name</th>
                    <th class="center" width="10%">HSN/SAC</th>
                    <th class="right" width="8%">Qty</th>
                    <th class="center" width="8%">Unit</th>
                    <th class="right" width="12%">Price/Unit</th>
                    <th class="right" width="10%">Discount</th>
                    <th class="center" width="8%">GST</th>
                    <th class="right" width="12%">Amount</th>
                </tr>
            </thead>
            <tbody>';

            $i = 1; $total_taxable = 0; $total_cgst = 0; $total_sgst = 0; $total_igst = 0; $gst_summary = [];

            foreach($items as $it) {
                $taxable = ($it['qty'] * $it['rate_excl_gst']) - $it['discount_amount'];
                $total_taxable += $taxable;
                $total_cgst += $it['cgst_amount'];
                $total_sgst += $it['sgst_amount'];
                $total_igst += $it['igst_amount'];

                $hsn = $it['hsn_code'] ?? 'NA';
                $rate = $it['gst_percent'];
                $key = $hsn . '_' . $rate;

                if (!isset($gst_summary[$key])) {
                    $gst_summary[$key] = ['hsn' => $hsn, 'rate' => $rate, 'taxable' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0];
                }
                $gst_summary[$key]['taxable'] += $taxable;
                $gst_summary[$key]['cgst'] += $it['cgst_amount'];
                $gst_summary[$key]['sgst'] += $it['sgst_amount'];
                $gst_summary[$key]['igst'] += $it['igst_amount'];

                $html .= '<tr>
                    <td class="center">' . $i++ . '</td>
                    <td class="bold">' . htmlspecialchars($it['name']) . '</td>
                    <td class="center">' . htmlspecialchars($hsn) . '</td>
                    <td class="right">' . $it['qty'] . '</td>
                    <td class="center">Nos</td>
                    <td class="right">' . number_format($it['rate_excl_gst'], 2) . '</td>
                    <td class="right">' . number_format($it['discount_amount'], 2) . '</td>
                    <td class="center">' . $it['gst_percent'] . '%</td>
                    <td class="right bold">' . number_format($taxable, 2) . '</td>
                </tr>';
            }

            $advance = $invoice['advance_paid'] ?? 0;
            $balance = $invoice['net_total'] - $advance;

    $html .= '
            <tr class="summary-row">
                <td colspan="8" class="right bold">Sub Total</td>
                <td class="right bold">' . number_format($total_taxable, 2) . '</td>
            </tr>';

            if ($tax_mode == 'IGST') {
                $html .= '<tr class="summary-row"><td colspan="8" class="right">Total IGST</td><td class="right">' . number_format($total_igst, 2) . '</td></tr>';
            } else {
                $html .= '<tr class="summary-row"><td colspan="8" class="right">Total CGST</td><td class="right">' . number_format($total_cgst, 2) . '</td></tr>';
                $html .= '<tr class="summary-row"><td colspan="8" class="right">Total SGST</td><td class="right">' . number_format($total_sgst, 2) . '</td></tr>';
            }

    $html .= '
            <tr class="summary-row grand-total">
                <td colspan="8" class="right bold">Total Amount</td>
                <td class="right bold">' . number_format($invoice['net_total'], 2) . '</td>
            </tr>
            <tr class="summary-row">
                <td colspan="8" class="right">Advance Paid</td>
                <td class="right">' . number_format($advance, 2) . '</td>
            </tr>
            <tr class="summary-row">
                <td colspan="8" class="right bold" style="color: #1d4ed8;">Balance Due</td>
                <td class="right bold" style="color: #1d4ed8;">' . number_format($balance, 2) . '</td>
            </tr>
            </tbody>
        </table>

        <div class="amount-words-box">
            <span class="bold" style="color: #64748b;">Amount in Words:</span>
            <span class="bold">' . $net_total_words . ' Only</span>
        </div>

        <div style="font-size: 9px; font-weight: bold; color: #64748b; text-transform: uppercase; margin-bottom: 3px;">GST Tax Breakdown</div>
        <table class="data-table" style="font-size: 9px;">
            <thead>
                <tr>
                    <th class="center">HSN / SAC</th>
                    <th class="right">Taxable Amount</th>
                    <th class="center">Rate</th>';
                    if ($tax_mode == 'IGST') {
                        $html .= '<th class="right">IGST Amount</th>';
                    } else {
                        $html .= '<th class="right">CGST Amount</th><th class="right">SGST Amount</th>';
                    }
                    $html .= '<th class="right">Total Tax</th>
                </tr>
            </thead>
            <tbody>';
                foreach($gst_summary as $data) {
                    $tot_tax = $data['cgst'] + $data['sgst'] + $data['igst'];
                    $html .= '<tr>
                        <td class="center">' . $data['hsn'] . '</td>
                        <td class="right">' . number_format($data['taxable'], 2) . '</td>
                        <td class="center">' . $data['rate'] . '%</td>';
                        if ($tax_mode == 'IGST') {
                            $html .= '<td class="right">' . number_format($data['igst'], 2) . '</td>';
                        } else {
                            $html .= '<td class="right">' . number_format($data['cgst'], 2) . '</td><td class="right">' . number_format($data['sgst'], 2) . '</td>';
                        }
                    $html .= '<td class="right bold">' . number_format($tot_tax, 2) . '</td></tr>';
                }
    $html .= '
            </tbody>
        </table>

        <table class="footer-table">
            <tr>
                <td width="50%" class="footer-card">
                    <div class="footer-title">Bank Details</div>';
                    if ($bank) {
                        $html .= '<b>Bank:</b> ' . htmlspecialchars($bank['bank_name']) . '<br>
                                  <b>A/C Name:</b> ' . htmlspecialchars($bank['account_name']) . '<br>
                                  <b>A/C No:</b> ' . htmlspecialchars($bank['account_number']) . '<br>
                                  <b>IFSC Code:</b> ' . htmlspecialchars($bank['ifsc_code']);
                    } else {
                        $html .= '<span style="color:#64748b;">No bank details available.</span>';
                    }
    $html .= '
                </td>
                <td width="50%" class="footer-card center" style="text-align: center;">
                    <div class="footer-title">Authorized Signatory</div>
                    <div class="bold">' . $org_name_upper . '</div>
                    <div style="height: 25px;"></div>
                    <div style="font-size: 8px; color: #64748b;">Computer-generated invoice. No physical signature required.</div>
                </td>
            </tr>
        </table>';

        if (!empty(trim($invoice['terms_conditions'] ?? ''))) {
            $html .= '
            <div class="footer-card" style="margin-top: 8px;">
                <div class="footer-title">Terms & Conditions</div>
                <div style="font-size: 9px; color: #64748b; white-space: pre-line;">' . htmlspecialchars(trim($invoice['terms_conditions'])) . '</div>
            </div>';
        }

    $html .= '
    </div>
    </body>
    </html>';

    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $pdf_base64 = base64_encode($dompdf->output());

    $apiKey      = $_ENV['BREVO_API_KEY'] ?? $_SERVER['BREVO_API_KEY'] ?? getenv('BREVO_API_KEY');
    $senderEmail = $_ENV['BREVO_SENDER_EMAIL'] ?? $_SERVER['BREVO_SENDER_EMAIL'] ?? getenv('BREVO_SENDER_EMAIL');

    if (empty($apiKey)) return "BREVO_API_KEY is EMPTY in Railway Environment Variables!";
    if (empty($senderEmail)) return "BREVO_SENDER_EMAIL is EMPTY in Railway Environment Variables!";

    $subject = ($is_revised ? "[REVISED] " : "") . "Invoice #" . $invoice['invoice_no'] . " from " . $org_name_upper;
    $body = $is_revised 
        ? "Dear <b>" . htmlspecialchars($customer_name) . "</b>,<br><br>Please find attached the <b>REVISED/UPDATED</b> copy of your Invoice.<br><br>Regards,<br><b>Team " . htmlspecialchars($org_name_upper) . "</b>"
        : "Dear <b>" . htmlspecialchars($customer_name) . "</b>,<br><br>Please find attached your invoice.<br><br>Regards,<br><b>Team " . htmlspecialchars($org_name_upper) . "</b>";

    $data = [
        "sender" => ["name" => $org_name_upper, "email" => $senderEmail],
        "to" => [["email" => $to_email, "name" => $customer_name]],
        "subject" => $subject,
        "htmlContent" => $body,
        "attachment" => [
            [
                "content" => $pdf_base64,
                "name"    => ($is_revised ? "Revised_" : "") . "Invoice_" . str_replace('/', '_', $invoice['invoice_no']) . ".pdf"
            ]
        ]
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
}

/*--------------------------------------------------------------*/
/* 2. SEND QUOTATION PDF VIA BREVO
/*--------------------------------------------------------------*/
function send_quotation_email($quotation_id, $to_email, $customer_name) {
    global $db;

    if (!load_dompdf_framework()) {
        return "Dompdf autoload error! Clean vendor folder not found.";
    }

    $q_data = find_by_sql("
        SELECT q.*, c.customer_name, c.address, c.gst_no, c.contact_no
        FROM quotation_master q
        LEFT JOIN customer_master c ON c.id = q.customer_id
        WHERE q.id = '{$quotation_id}' LIMIT 1
    ");

    if (empty($q_data)) return "Quotation ID {$quotation_id} not found!";
    $quotation = $q_data[0];

    $is_revised = isset($quotation['is_revised']) && $quotation['is_revised'] == 1;
    $title_text = $is_revised ? 'REVISED QUOTATION' : 'QUOTATION';
    $title_color = $is_revised ? '#dc2626' : '#2563eb';

    $org_master = find_by_sql("
        SELECT org_name 
        FROM master_inventory.master_organization 
        WHERE org_id = '{$quotation['organization_id']}' LIMIT 1
    ");
    $org_master = $org_master ? $org_master[0] : ['org_name' => ''];

    $org_data = find_by_sql("SELECT * FROM organization_master WHERE id = '{$quotation['organization_id']}' LIMIT 1");
    $org = $org_data ? $org_data[0] : ['address' => '', 'gst_no' => '', 'phone' => ''];

    $org_state  = substr($org['gst_no'] ?? '', 0, 2);
    $cust_state = substr($quotation['gst_no'] ?? '', 0, 2);
    $tax_mode   = ($org_state == $cust_state) ? 'CGST_SGST' : 'IGST';

    $bank_data = find_by_sql("SELECT * FROM bank_master WHERE organization_id = '{$quotation['organization_id']}' LIMIT 1");
    $bank = $bank_data ? $bank_data[0] : null;

    $items = find_by_sql("
        SELECT qi.*, p.name, p.hsn_code
        FROM quotation_items qi
        LEFT JOIN products p ON p.id = qi.product_id
        WHERE qi.quotation_id = '{$quotation_id}'
    ");

    $org_name_upper = strtoupper($org_master['org_name']);
    $cust_name_upper = strtoupper($quotation['customer_name']);
    $q_date_formatted = date("d/M/Y", strtotime($quotation['quotation_date']));
    $net_total_words = numberToWords($quotation['net_total']);

    $html = '
    <html>
    <head>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 11px; color: #1e293b; margin: 0; padding: 0; }
        .wrapper { width: 100%; }
        .header-table { width: 100%; border-bottom: 1px solid #e2e8f0; margin-bottom: 10px; padding-bottom: 8px; }
        .org-title { font-size: 16px; font-weight: bold; color: #1e293b; margin-bottom: 3px; }
        .q-title { font-size: 18px; font-weight: bold; color: ' . $title_color . '; text-align: right; text-transform: uppercase; }
        .meta-table { font-size: 10px; width: 100%; text-align: right; }
        .info-card { background: #eff6ff; border: 1px solid #dbeafe; padding: 8px; margin-bottom: 10px; border-radius: 4px; }
        .card-title { font-size: 9px; font-weight: bold; color: #2563eb; text-transform: uppercase; margin-bottom: 2px; }
        .customer-name { font-size: 12px; font-weight: bold; color: #1e293b; }
        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.data-table th { background: #f8fafc; color: #64748b; font-size: 9px; text-transform: uppercase; padding: 5px; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; }
        table.data-table td { padding: 5px; border-bottom: 1px solid #e2e8f0; font-size: 10px; }
        .right { text-align: right; } .center { text-align: center; } .bold { font-weight: bold; }
        .summary-row td { border-bottom: none; padding: 3px 5px; }
        .grand-total td { border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; background: #eff6ff; font-size: 11px; color: #1d4ed8; font-weight: bold; }
        .amount-words-box { background: #f8fafc; border: 1px dashed #e2e8f0; padding: 6px; margin-bottom: 10px; font-size: 10px; }
        .footer-table { width: 100%; margin-top: 10px; }
        .footer-card { border: 1px solid #e2e8f0; padding: 6px; border-radius: 4px; font-size: 10px; vertical-align: top; }
        .footer-title { font-size: 9px; font-weight: bold; color: #64748b; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; margin-bottom: 4px; padding-bottom: 2px; }
    </style>
    </head>
    <body>
    <div class="wrapper">
        <table class="header-table">
            <tr>
                <td width="60%">
                    <div class="org-title">' . $org_name_upper . '</div>
                    <div style="color: #64748b; font-size: 10px;">
                        ' . nl2br(htmlspecialchars($org['address'] ?? '')) . '<br>
                        <b>GSTIN:</b> ' . htmlspecialchars($org['gst_no'] ?? '') . ' | <b>Phone:</b> ' . htmlspecialchars($org['phone'] ?? '') . '
                    </div>
                </td>
                <td width="40%" style="vertical-align: top;">
                    <div class="q-title">' . $title_text . '</div>
                    <table class="meta-table">
                        <tr><td style="color:#64748b;">Quotation No:</td><td class="bold">' . htmlspecialchars($quotation['quotation_no']) . '</td></tr>
                        <tr><td style="color:#64748b;">Date:</td><td class="bold">' . $q_date_formatted . '</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="info-card">
            <div class="card-title">Customer Details</div>
            <div class="customer-name">' . $cust_name_upper . '</div>
            <div style="font-size: 10px; line-height: 1.3;">
                ' . nl2br(htmlspecialchars($quotation['address'] ?? '')) . '<br>
                <b>GSTIN:</b> ' . htmlspecialchars($quotation['gst_no'] ?? '') . ' | <b>Phone:</b> ' . htmlspecialchars($quotation['contact_no'] ?? '') . '
            </div>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th class="center" width="5%">#</th>
                    <th width="35%">Item Name</th>
                    <th class="center" width="10%">HSN/SAC</th>
                    <th class="right" width="8%">Qty</th>
                    <th class="center" width="8%">Unit</th>
                    <th class="right" width="12%">Price/Unit</th>
                    <th class="right" width="10%">Discount</th>
                    <th class="center" width="8%">GST</th>
                    <th class="right" width="12%">Amount</th>
                </tr>
            </thead>
            <tbody>';

            $i = 1; $total_taxable = 0; $total_cgst = 0; $total_sgst = 0; $total_igst = 0; $gst_summary = [];

            foreach($items as $it) {
                $taxable = ($it['qty'] * $it['rate_excl_gst']) - $it['discount_amount'];
                $total_taxable += $taxable;
                $total_cgst += $it['cgst_amount'];
                $total_sgst += $it['sgst_amount'];
                $total_igst += $it['igst_amount'];

                $hsn = $it['hsn_code'] ?? 'NA';
                $rate = $it['gst_percent'];
                $key = $hsn . '_' . $rate;

                if (!isset($gst_summary[$key])) {
                    $gst_summary[$key] = ['hsn' => $hsn, 'rate' => $rate, 'taxable' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0];
                }
                $gst_summary[$key]['taxable'] += $taxable;
                $gst_summary[$key]['cgst'] += $it['cgst_amount'];
                $gst_summary[$key]['sgst'] += $it['sgst_amount'];
                $gst_summary[$key]['igst'] += $it['igst_amount'];

                $html .= '<tr>
                    <td class="center">' . $i++ . '</td>
                    <td class="bold">' . htmlspecialchars($it['name']) . '</td>
                    <td class="center">' . htmlspecialchars($hsn) . '</td>
                    <td class="right">' . $it['qty'] . '</td>
                    <td class="center">Nos</td>
                    <td class="right">' . number_format($it['rate_excl_gst'], 2) . '</td>
                    <td class="right">' . number_format($it['discount_amount'], 2) . '</td>
                    <td class="center">' . $it['gst_percent'] . '%</td>
                    <td class="right bold">' . number_format($taxable, 2) . '</td>
                </tr>';
            }

    $html .= '
            <tr class="summary-row">
                <td colspan="8" class="right bold">Sub Total</td>
                <td class="right bold">' . number_format($total_taxable, 2) . '</td>
            </tr>';

            if ($tax_mode == 'IGST') {
                $html .= '<tr class="summary-row"><td colspan="8" class="right">Total IGST</td><td class="right">' . number_format($total_igst, 2) . '</td></tr>';
            } else {
                $html .= '<tr class="summary-row"><td colspan="8" class="right">Total CGST</td><td class="right">' . number_format($total_cgst, 2) . '</td></tr>';
                $html .= '<tr class="summary-row"><td colspan="8" class="right">Total SGST</td><td class="right">' . number_format($total_sgst, 2) . '</td></tr>';
            }

    $html .= '
            <tr class="summary-row grand-total">
                <td colspan="8" class="right bold">Total Amount</td>
                <td class="right bold">' . number_format($quotation['net_total'], 2) . '</td>
            </tr>
            </tbody>
        </table>

        <div class="amount-words-box">
            <span class="bold" style="color: #64748b;">Amount in Words:</span>
            <span class="bold">' . $net_total_words . ' Only</span>
        </div>

        <div style="font-size: 9px; font-weight: bold; color: #64748b; text-transform: uppercase; margin-bottom: 3px;">GST Tax Breakdown</div>
        <table class="data-table" style="font-size: 9px;">
            <thead>
                <tr>
                    <th class="center">HSN / SAC</th>
                    <th class="right">Taxable Amount</th>
                    <th class="center">Rate</th>';
                    if ($tax_mode == 'IGST') {
                        $html .= '<th class="right">IGST Amount</th>';
                    } else {
                        $html .= '<th class="right">CGST Amount</th><th class="right">SGST Amount</th>';
                    }
                    $html .= '<th class="right">Total Tax</th>
                </tr>
            </thead>
            <tbody>';
                foreach($gst_summary as $data) {
                    $tot_tax = $data['cgst'] + $data['sgst'] + $data['igst'];
                    $html .= '<tr>
                        <td class="center">' . $data['hsn'] . '</td>
                        <td class="right">' . number_format($data['taxable'], 2) . '</td>
                        <td class="center">' . $data['rate'] . '%</td>';
                        if ($tax_mode == 'IGST') {
                            $html .= '<td class="right">' . number_format($data['igst'], 2) . '</td>';
                        } else {
                            $html .= '<td class="right">' . number_format($data['cgst'], 2) . '</td><td class="right">' . number_format($data['sgst'], 2) . '</td>';
                        }
                    $html .= '<td class="right bold">' . number_format($tot_tax, 2) . '</td></tr>';
                }
    $html .= '
            </tbody>
        </table>

        <table class="footer-table">
            <tr>
                <td width="50%" class="footer-card">
                    <div class="footer-title">Bank Details</div>';
                    if ($bank) {
                        $html .= '<b>Bank:</b> ' . htmlspecialchars($bank['bank_name']) . '<br>
                                  <b>A/C Name:</b> ' . htmlspecialchars($bank['account_name']) . '<br>
                                  <b>A/C No:</b> ' . htmlspecialchars($bank['account_number']) . '<br>
                                  <b>IFSC Code:</b> ' . htmlspecialchars($bank['ifsc_code']);
                    } else {
                        $html .= '<span style="color:#64748b;">No bank details available.</span>';
                    }
    $html .= '
                </td>
                <td width="50%" class="footer-card center" style="text-align: center;">
                    <div class="footer-title">Authorized Signatory</div>
                    <div class="bold">' . $org_name_upper . '</div>
                    <div style="height: 25px;"></div>
                    <div style="font-size: 8px; color: #64748b;">Computer-generated quotation. No physical signature required.</div>
                </td>
            </tr>
        </table>';

        if (!empty(trim($quotation['terms_conditions'] ?? ''))) {
            $html .= '
            <div class="footer-card" style="margin-top: 8px;">
                <div class="footer-title">Terms & Conditions</div>
                <div style="font-size: 9px; color: #64748b; white-space: pre-line;">' . htmlspecialchars(trim($quotation['terms_conditions'])) . '</div>
            </div>';
        }

    $html .= '
    </div>
    </body>
    </html>';

    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $pdf_base64 = base64_encode($dompdf->output());

    $apiKey      = $_ENV['BREVO_API_KEY'] ?? $_SERVER['BREVO_API_KEY'] ?? getenv('BREVO_API_KEY');
    $senderEmail = $_ENV['BREVO_SENDER_EMAIL'] ?? $_SERVER['BREVO_SENDER_EMAIL'] ?? getenv('BREVO_SENDER_EMAIL');

    if (empty($apiKey)) return "BREVO_API_KEY is EMPTY in Railway Environment Variables!";
    if (empty($senderEmail)) return "BREVO_SENDER_EMAIL is EMPTY in Railway Environment Variables!";

    $subject = ($is_revised ? "[REVISED] " : "") . "Quotation #" . $quotation['quotation_no'] . " from " . $org_name_upper;
    $body = $is_revised 
        ? "Dear <b>" . htmlspecialchars($customer_name) . "</b>,<br><br>Please find attached the <b>REVISED/UPDATED</b> copy of your Quotation.<br><br>Regards,<br><b>Team " . htmlspecialchars($org_name_upper) . "</b>"
        : "Dear <b>" . htmlspecialchars($customer_name) . "</b>,<br><br>Please find attached your requested Quotation.<br><br>Regards,<br><b>Team " . htmlspecialchars($org_name_upper) . "</b>";

    $data = [
        "sender" => ["name" => $org_name_upper, "email" => $senderEmail],
        "to" => [["email" => $to_email, "name" => $customer_name]],
        "subject" => $subject,
        "htmlContent" => $body,
        "attachment" => [
            [
                "content" => $pdf_base64,
                "name"    => ($is_revised ? "Revised_" : "") . "Quotation_" . str_replace('/', '_', $quotation['quotation_no']) . ".pdf"
            ]
        ]
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
}
?>
