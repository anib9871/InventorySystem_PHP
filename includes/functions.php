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
  $str = $str ?? ''; // null handle karne ke liye
  $str = nl2br($str);
  return htmlspecialchars(strip_tags($str));
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
/* DOMPDF SAFE INITIALIZER
/*--------------------------------------------------------------*/
function load_dompdf_framework() {
    if (class_exists('Dompdf\Dompdf') && class_exists('Dompdf\Cpdf')) {
        return true;
    }

    $possible_vendors = [
        __DIR__ . '/libs/dompdf/vendor',
        __DIR__ . '/../libs/dompdf/vendor',
        $_SERVER['DOCUMENT_ROOT'] . '/libs/dompdf/vendor',
        $_SERVER['DOCUMENT_ROOT'] . '/InventorySystem_PHP/libs/dompdf/vendor',
        '/app/libs/dompdf/vendor',
        '/app/vendor',
        __DIR__ . '/vendor'
    ];

    $v = null;
    foreach ($possible_vendors as $dir) {
        if (file_exists($dir . '/dompdf/dompdf/src/Dompdf.php')) {
            $v = $dir;
            break;
        }
    }

    if ($v) {
        if (file_exists($v . '/autoload.php')) {
            @include_once $v . '/autoload.php';
        }

        spl_autoload_register(function ($class) use ($v) {
            if ($class === 'Dompdf\Cpdf' || $class === 'Cpdf') {
                $cpdf_files = [
                    $v . '/dompdf/dompdf/lib/Cpdf.php',
                    $v . '/dompdf/dompdf/lib/cpdf.php',
                    $v . '/dompdf/dompdf/src/Cpdf.php',
                    $v . '/dompdf/dompdf/src/Adapter/CPDF.php'
                ];
                foreach ($cpdf_files as $cp) {
                    if (file_exists($cp)) {
                        require_once $cp;
                        if (!class_exists('Dompdf\Cpdf') && class_exists('Cpdf')) {
                            class_alias('Cpdf', 'Dompdf\Cpdf');
                        }
                        return;
                    }
                }
            }

            $map = [
                'Dompdf\Adapter\\' => $v . '/dompdf/dompdf/src/Adapter/',
                'Dompdf\\'         => $v . '/dompdf/dompdf/src/',
                'FontLib\\'        => $v . '/phenx/php-font-lib/src/FontLib/',
                'Svg\\'            => $v . '/phenx/php-svg-lib/src/Svg/',
                'Sabberworm\CSS\\' => $v . '/sabberworm/php-css-parser/src/'
            ];

            foreach ($map as $prefix => $base_dir) {
                $len = strlen($prefix);
                if (strncmp($prefix, $class, $len) === 0) {
                    $rel = substr($class, $len);
                    $file = $base_dir . str_replace('\\', '/', $rel) . '.php';
                    if (file_exists($file)) {
                        require_once $file;
                        return;
                    }
                }
            }
        }, true, true);

        if (!class_exists('Dompdf\Cpdf')) {
            if (class_exists('Dompdf\Adapter\CPDF')) {
                class_alias('Dompdf\Adapter\CPDF', 'Dompdf\Cpdf');
            } elseif (class_exists('Cpdf')) {
                class_alias('Cpdf', 'Dompdf\Cpdf');
            }
        }

        return true;
    }

    return false;
}

/*--------------------------------------------------------------*/
/* 1. SEND INVOICE / PROFORMA VIA BREVO
/*--------------------------------------------------------------*/
function send_invoice_email($invoice_id, $to_email, $customer_name) {
    global $db;

    if (!load_dompdf_framework()) {
        return "Dompdf load error: Vendor path not found on server.";
    }

    $invoice_data = find_by_sql("
        SELECT i.*, c.customer_name, c.address, c.gst_no, c.contact_no
        FROM invoice i
        LEFT JOIN customer_master c ON c.id = i.customer_id
        WHERE i.id = '{$invoice_id}' LIMIT 1
    ");

    if (empty($invoice_data)) return "Invoice ID {$invoice_id} not found!";
    $invoice = $invoice_data[0];

/* 1. PEHLE PAYMENTS FETCH KARO */
    $payment_records = find_by_sql("
        SELECT payment_mode, amount, reference_no, payment_date 
        FROM payments 
        WHERE invoice_id = '{$invoice_id}' AND amount > 0
    ");

    $total_paid_dynamically = 0;
    if(!empty($payment_records)){
        foreach($payment_records as $pr){
            $total_paid_dynamically += (float)$pr['amount'];
        }
    }

    /* 2. PROFORMA VS TAX INVOICE TITLE LOGIC FOR EMAIL */
    $doc_type = strtoupper($invoice['remarks'] ?? '');
    $payment_status = strtolower(trim($invoice['payment_status'] ?? ''));
    $db_paid_amount = (float)($invoice['paid_amount'] ?? 0);

    // Agar payment DB me update ho gayi hai YA actual payment table me entry hai
    if ($payment_status === 'paid' || $payment_status === 'partial' || $db_paid_amount > 0 || $total_paid_dynamically > 0) {
        $is_proforma = false; // Payment aa gayi, toh TAX INVOICE banega
    } else {
        // Payment bilkul zero hai, toh check karo remarks me PROFORMA likha hai kya
        $is_proforma = ($doc_type === 'PROFORMA' || ($db_paid_amount == 0 && $total_paid_dynamically == 0));
    }

    if ($is_proforma) {
        $title_text = 'PROFORMA INVOICE';
        $title_color = '#d97706'; // Amber / Orange
    } else {
        $title_text = 'TAX INVOICE';
        $title_color = '#2563eb'; // Blue
    }
    $org_master = find_by_sql("
        SELECT org_name 
        FROM master_inventory.master_organization 
        WHERE org_id = '{$invoice['organization_id']}' LIMIT 1
    ");
    $org_master = $org_master ? $org_master[0] : ['org_name' => ''];

  $org_data = find_by_sql("SELECT * FROM organization_master WHERE id = '{$invoice['organization_id']}' LIMIT 1");
    $org = $org_data ? $org_data[0] : ['address' => '', 'gst_no' => '', 'phone' => ''];

    // JSON Address decode logic for Email PDF
    $raw_org_address = $org['address'] ?? '';
    $decoded_org_addr = json_decode($raw_org_address, true);
    $final_org_address = $raw_org_address; 

    if (is_array($decoded_org_addr)) {
        if (isset($decoded_org_addr[0]['text'])) {
            $final_org_address = $decoded_org_addr[0]['text'];
        } elseif (isset($decoded_org_addr[0]) && is_string($decoded_org_addr[0])) {
            $final_org_address = $decoded_org_addr[0];
        }
    }

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

    // /* FETCH PAYMENTS */
    // $payment_records = find_by_sql("
    //     SELECT payment_mode, amount, reference_no, payment_date 
    //     FROM payments 
    //     WHERE invoice_id = '{$invoice_id}' AND amount > 0
    // ");

    $org_name_upper = strtoupper($org_master['org_name']);
    $cust_name_upper = strtoupper($invoice['customer_name']);
    $inv_date_formatted = date("d/M/Y", strtotime($invoice['invoice_date']));
    $net_total_words = numberToWords($invoice['net_total']);

    $html = '
    <html>
    <head>
    <style>
        @page { margin: 12px 18px; }
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 9.5px; color: #1e293b; margin: 0; padding: 0; line-height: 1.15; }
        .wrapper { width: 100%; }
        .header-table { width: 100%; border-bottom: 1px solid #e2e8f0; margin-bottom: 5px; padding-bottom: 3px; }
        .org-title { font-size: 13px; font-weight: bold; color: #1e293b; margin-bottom: 2px; }
        .inv-title { font-size: 15px; font-weight: bold; color: ' . $title_color . '; text-align: right; text-transform: uppercase; }
        .meta-table { font-size: 8.5px; width: 100%; text-align: right; }
        .info-card { background: #eff6ff; border: 1px solid #dbeafe; padding: 4px 6px; margin-bottom: 5px; border-radius: 4px; }
        .card-title { font-size: 8px; font-weight: bold; color: #2563eb; text-transform: uppercase; margin-bottom: 1px; }
        .customer-name { font-size: 10.5px; font-weight: bold; color: #1e293b; }
        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
        table.data-table th { background: #f8fafc; color: #64748b; font-size: 8px; text-transform: uppercase; padding: 3px; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; }
        table.data-table td { padding: 3px; border-bottom: 1px solid #e2e8f0; font-size: 8.5px; }
        .right { text-align: right; } .center { text-align: center; } .bold { font-weight: bold; }
        .summary-row td { border-bottom: none; padding: 2px 3px; }
        .grand-total td { border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; background: #eff6ff; font-size: 9.5px; color: #1d4ed8; font-weight: bold; }
        .amount-words-box { background: #f8fafc; border: 1px dashed #e2e8f0; padding: 3px 5px; margin-bottom: 5px; font-size: 8.5px; }
        .footer-table { width: 100%; margin-top: 4px; }
        .footer-card { border: 1px solid #e2e8f0; padding: 4px; border-radius: 4px; font-size: 8.5px; vertical-align: top; }
        .footer-title { font-size: 7.5px; font-weight: bold; color: #64748b; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; margin-bottom: 2px; padding-bottom: 1px; }
        .terms-box { border: 1px solid #e2e8f0; padding: 3px 5px; border-radius: 4px; font-size: 7px; line-height: 1.1; color: #475569; margin-top: 4px; }
        .pay-box-green { background: #f0fdf4; border: 1px solid #bbf7d0; padding: 4px 6px; border-radius: 4px; margin-bottom: 5px; }
        .pay-box-orange { background: #fffbebfb; border: 1px solid #fef3c7; padding: 4px 6px; border-radius: 4px; margin-bottom: 5px; color: #b45309; font-size: 8px; }
    </style>
    </head>
    <body>
    <div class="wrapper">
        <table class="header-table">
            <tr>
                <td width="60%">
                    <div class="org-title">' . $org_name_upper . '</div>
                    <div style="color: #64748b; font-size: 8.5px;">
    ' . nl2br(htmlspecialchars($final_org_address)) . '<br>
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
            <div style="font-size: 8.5px; line-height: 1.15;">
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

            $paid = $invoice['paid_amount'] ?? 0;
            $balance = $invoice['due_amount'] ?? ($invoice['net_total'] - $paid);

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
                <td colspan="8" class="right" style="color:#16a34a; font-weight:bold;">Total Paid / Received</td>
                <td class="right" style="color:#16a34a; font-weight:bold;">' . number_format($paid, 2) . '</td>
            </tr>
            <tr class="summary-row">
                <td colspan="8" class="right bold" style="color: #dc2626;">Balance Due</td>
                <td class="right bold" style="color: #dc2626;">' . number_format($balance, 2) . '</td>
            </tr>
            </tbody>
        </table>';

    if (!empty($payment_records)) {
        $html .= '
        <div class="pay-box-green">
            <div style="font-size:8px; font-weight:bold; color:#166534; text-transform:uppercase; margin-bottom:2px;">
                Payment Breakdown (' . strtoupper($invoice['payment_status']) . ')
            </div>
            <table style="width:100%; border-collapse:collapse; font-size:8px;">
                <thead>
                    <tr style="border-bottom:1px solid #bbf7d0; color:#15803d;">
                        <th align="left" style="padding:1px 0;">Mode</th>
                        <th align="left" style="padding:1px 0;">UTR / Ref No.</th>
                        <th align="right" style="padding:1px 0;">Amount Received</th>
                    </tr>
                </thead>
                <tbody>';
                foreach ($payment_records as $pr) {
                    $ref = !empty($pr['reference_no']) ? htmlspecialchars($pr['reference_no']) : 'N/A';
                    $html .= '
                    <tr>
                        <td style="padding:1px 0;"><b>' . strtoupper($pr['payment_mode']) . '</b></td>
                        <td style="padding:1px 0;">' . $ref . '</td>
                        <td align="right" style="padding:1px 0; font-weight:bold; color:#166534;">₹ ' . number_format($pr['amount'], 2) . '</td>
                    </tr>';
                }
        $html .= '
                </tbody>
            </table>
        </div>';
    } else {
        $html .= '
        <div class="pay-box-orange">
            <b>PROFORMA INVOICE / ESTIMATE</b> | Pending Balance: <b>₹ ' . number_format($balance, 2) . '</b>
        </div>';
    }

    $html .= '
        <div class="amount-words-box">
            <span class="bold" style="color: #64748b;">Amount in Words:</span>
            <span class="bold">' . $net_total_words . ' Only</span>
        </div>

        <div style="font-size: 7.5px; font-weight: bold; color: #64748b; text-transform: uppercase; margin-bottom: 2px;">GST Tax Breakdown</div>
        <table class="data-table" style="font-size: 7.5px;">
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
                        $html .= '<b>Bank:</b> ' . htmlspecialchars($bank['bank_name']) . '<br>';
                        
                        // Branch print karne ka logic
                        if (!empty($bank['branch'])) {
                            $html .= '<b>Branch:</b> ' . htmlspecialchars($bank['branch']) . '<br>';
                        }

                        $html .= '<b>A/C Name:</b> ' . htmlspecialchars($bank['account_name']) . '<br>
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
                    <div style="height: 16px;"></div>
                    <div style="font-size: 7px; color: #64748b;">Computer-generated document. No physical signature required.</div>
                </td>
            </tr>
        </table>';

        if (!empty(trim($invoice['terms_conditions'] ?? ''))) {
            $html .= '
            <div class="terms-box">
                <div class="footer-title">Terms & Conditions</div>
                <div style="white-space: pre-line;">' . htmlspecialchars(trim($invoice['terms_conditions'])) . '</div>
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

    // =========================================================
    // BREVO API CREDENTIALS (टेस्टिंग के लिए यहाँ सीधे Key डालें)
    // =========================================================
    $apiKey      = $_ENV['BREVO_API_KEY'] ?? $_SERVER['BREVO_API_KEY'] ?? getenv('BREVO_API_KEY') ?? 'YOUR_BREVO_API_KEY_HERE';
    $senderEmail = $_ENV['BREVO_SENDER_EMAIL'] ?? $_SERVER['BREVO_SENDER_EMAIL'] ?? getenv('BREVO_SENDER_EMAIL') ?? 'your-email@domain.com';
  
    if (empty($apiKey) || $apiKey === 'YOUR_BREVO_API_KEY_HERE') {
        return "BREVO_API_KEY is missing/not set properly!";
    }
    if (empty($senderEmail) || $senderEmail === 'your-email@domain.com') {
        return "BREVO_SENDER_EMAIL is missing/not set properly!";
    }

    $subject = ($is_proforma ? "Proforma Invoice #" : "Tax Invoice #") . $invoice['invoice_no'] . " from " . $org_name_upper;
    $body = "Dear <b>" . htmlspecialchars($customer_name) . "</b>,<br><br>Please find attached your " . ($is_proforma ? "Proforma Invoice" : "Tax Invoice") . ".<br><br>Regards,<br><b>Team " . htmlspecialchars($org_name_upper) . "</b>";

    $data = [
        "sender" => ["name" => $org_name_upper, "email" => $senderEmail],
        "to" => [["email" => $to_email, "name" => $customer_name]],
        "subject" => $subject,
        "htmlContent" => $body,
        "attachment" => [
            [
                "content" => $pdf_base64,
                "name"    => ($is_proforma ? "Proforma_" : "Invoice_") . str_replace('/', '_', $invoice['invoice_no']) . ".pdf"
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
    curl_close($ch);

    if ($httpCode == 201 || $httpCode == 200) {
        return true;
    } else {
        return "HTTP Code: {$httpCode} | Brevo Msg: {$response}";
    }
}

/*--------------------------------------------------------------*/
/* 2. SEND QUOTATION VIA BREVO
/*--------------------------------------------------------------*/
function send_quotation_email($quotation_id, $to_email, $customer_name) {
    global $db;

    if (!load_dompdf_framework()) {
        return "Dompdf load error: Vendor path not found on server.";
    }

    $quotation_data = find_by_sql("
        SELECT q.*, c.customer_name, c.address, c.gst_no, c.contact_no
        FROM quotation_master q
        LEFT JOIN customer_master c ON c.id = q.customer_id
        WHERE q.id = '{$quotation_id}' LIMIT 1
    ");

    if (empty($quotation_data)) return "Quotation ID {$quotation_id} not found!";
    $quotation = $quotation_data[0];

    $title_text = 'QUOTATION';
    $title_color = '#2563eb'; 

    $org_master = find_by_sql("
        SELECT org_name 
        FROM master_inventory.master_organization 
        WHERE org_id = '{$quotation['organization_id']}' LIMIT 1
    ");
    $org_master = $org_master ? $org_master[0] : ['org_name' => ''];

    $org_data = find_by_sql("SELECT * FROM organization_master WHERE id = '{$quotation['organization_id']}' LIMIT 1");
    $org = $org_data ? $org_data[0] : ['address' => '', 'gst_no' => '', 'phone' => ''];

    // JSON Address decode logic for Quotation Email PDF
    $raw_org_address = $org['address'] ?? '';
    $decoded_org_addr = json_decode($raw_org_address, true);
    $final_org_address = $raw_org_address; 

    if (is_array($decoded_org_addr)) {
        if (isset($decoded_org_addr[0]['text'])) {
            $final_org_address = $decoded_org_addr[0]['text'];
        } elseif (isset($decoded_org_addr[0]) && is_string($decoded_org_addr[0])) {
            $final_org_address = $decoded_org_addr[0];
        }
    }

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
    $quot_date_formatted = date("d/M/Y", strtotime($quotation['quotation_date']));
    $net_total_words = numberToWords($quotation['net_total']);

    $html = '
    <html>
    <head>
    <style>
        @page { margin: 12px 18px; }
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 9.5px; color: #1e293b; margin: 0; padding: 0; line-height: 1.15; }
        .wrapper { width: 100%; }
        .header-table { width: 100%; border-bottom: 1px solid #e2e8f0; margin-bottom: 5px; padding-bottom: 3px; }
        .org-title { font-size: 13px; font-weight: bold; color: #1e293b; margin-bottom: 2px; }
        .inv-title { font-size: 15px; font-weight: bold; color: ' . $title_color . '; text-align: right; text-transform: uppercase; }
        .meta-table { font-size: 8.5px; width: 100%; text-align: right; }
        .info-card { background: #eff6ff; border: 1px solid #dbeafe; padding: 4px 6px; margin-bottom: 5px; border-radius: 4px; }
        .card-title { font-size: 8px; font-weight: bold; color: #2563eb; text-transform: uppercase; margin-bottom: 1px; }
        .customer-name { font-size: 10.5px; font-weight: bold; color: #1e293b; }
        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
        table.data-table th { background: #f8fafc; color: #64748b; font-size: 8px; text-transform: uppercase; padding: 3px; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; }
        table.data-table td { padding: 3px; border-bottom: 1px solid #e2e8f0; font-size: 8.5px; }
        .right { text-align: right; } .center { text-align: center; } .bold { font-weight: bold; }
        .summary-row td { border-bottom: none; padding: 2px 3px; }
        .grand-total td { border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; background: #eff6ff; font-size: 9.5px; color: #1d4ed8; font-weight: bold; }
        .amount-words-box { background: #f8fafc; border: 1px dashed #e2e8f0; padding: 3px 5px; margin-bottom: 5px; font-size: 8.5px; }
        .footer-table { width: 100%; margin-top: 4px; }
        .footer-card { border: 1px solid #e2e8f0; padding: 4px; border-radius: 4px; font-size: 8.5px; vertical-align: top; }
        .footer-title { font-size: 7.5px; font-weight: bold; color: #64748b; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; margin-bottom: 2px; padding-bottom: 1px; }
        .terms-box { border: 1px solid #e2e8f0; padding: 3px 5px; border-radius: 4px; font-size: 7px; line-height: 1.1; color: #475569; margin-top: 4px; }
    </style>
    </head>
    <body>
    <div class="wrapper">
        <table class="header-table">
            <tr>
                <td width="60%">
                    <div class="org-title">' . $org_name_upper . '</div>
                    <div style="color: #64748b; font-size: 8.5px;">
                        ' . nl2br(htmlspecialchars($final_org_address)) . '<br>
                        <b>GSTIN:</b> ' . htmlspecialchars($org['gst_no'] ?? '') . ' | <b>Phone:</b> ' . htmlspecialchars($org['phone'] ?? '') . '
                    </div>
                </td>
                <td width="40%" style="vertical-align: top;">
                    <div class="inv-title">' . $title_text . '</div>
                    <table class="meta-table">
                        <tr><td style="color:#64748b;">Quotation No:</td><td class="bold">' . htmlspecialchars($quotation['quotation_no']) . '</td></tr>
                        <tr><td style="color:#64748b;">Date:</td><td class="bold">' . $quot_date_formatted . '</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="info-card">
            <div class="card-title">Quotation For</div>
            <div class="customer-name">' . $cust_name_upper . '</div>
            <div style="font-size: 8.5px; line-height: 1.15;">
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

        <div style="font-size: 7.5px; font-weight: bold; color: #64748b; text-transform: uppercase; margin-bottom: 2px;">GST Tax Breakdown</div>
        <table class="data-table" style="font-size: 7.5px;">
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
                        $html .= '<b>Bank:</b> ' . htmlspecialchars($bank['bank_name']) . '<br>';
                        if (!empty($bank['branch'])) {
                            $html .= '<b>Branch:</b> ' . htmlspecialchars($bank['branch']) . '<br>';
                        }
                        $html .= '<b>A/C Name:</b> ' . htmlspecialchars($bank['account_name']) . '<br>
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
                    <div style="height: 16px;"></div>
                    <div style="font-size: 7px; color: #64748b;">Computer-generated document. No physical signature required.</div>
                </td>
            </tr>
        </table>';

        if (!empty(trim($quotation['terms_conditions'] ?? ''))) {
            $html .= '
            <div class="terms-box">
                <div class="footer-title">Terms & Conditions</div>
                <div style="white-space: pre-line;">' . htmlspecialchars(trim($quotation['terms_conditions'])) . '</div>
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

    $apiKey      = $_ENV['BREVO_API_KEY'] ?? $_SERVER['BREVO_API_KEY'] ?? getenv('BREVO_API_KEY') ?? 'YOUR_BREVO_API_KEY_HERE';
    $senderEmail = $_ENV['BREVO_SENDER_EMAIL'] ?? $_SERVER['BREVO_SENDER_EMAIL'] ?? getenv('BREVO_SENDER_EMAIL') ?? 'your-email@domain.com';
  
    if (empty($apiKey) || $apiKey === 'YOUR_BREVO_API_KEY_HERE') {
        return "BREVO_API_KEY is missing/not set properly!";
    }
    if (empty($senderEmail) || $senderEmail === 'your-email@domain.com') {
        return "BREVO_SENDER_EMAIL is missing/not set properly!";
    }

    $subject = "Quotation #" . $quotation['quotation_no'] . " from " . $org_name_upper;
    $body = "Dear <b>" . htmlspecialchars($customer_name) . "</b>,<br><br>Please find attached your Quotation.<br><br>Regards,<br><b>Team " . htmlspecialchars($org_name_upper) . "</b>";

    $data = [
        "sender" => ["name" => $org_name_upper, "email" => $senderEmail],
        "to" => [["email" => $to_email, "name" => $customer_name]],
        "subject" => $subject,
        "htmlContent" => $body,
        "attachment" => [
            [
                "content" => $pdf_base64,
                "name"    => "Quotation_" . str_replace('/', '_', $quotation['quotation_no']) . ".pdf"
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
    curl_close($ch);

    if ($httpCode == 201 || $httpCode == 200) {
        return true;
    } else {
        return "HTTP Code: {$httpCode} | Brevo Msg: {$response}";
    }
}
