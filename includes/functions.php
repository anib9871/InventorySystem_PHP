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
/* NATIVE PHP PDF GENERATOR (NO LIBRARIES REQUIRED)
/*--------------------------------------------------------------*/
function generate_native_pdf_base64($title, $inv_no, $inv_date, $cust_name, $items, $net_total) {
    $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n";
    $pdf .= "2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj\n";
    $pdf .= "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Resources<</Font<*/F1 4 0 R*>>>/Contents 5 0 R>>endobj\n";
    $pdf .= "4 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\n";

    $stream = "BT /F1 16 Tf 50 780 Td (" . strtoupper($title) . " #" . $inv_no . ") Tj ET\n";
    $stream .= "BT /F1 10 Tf 50 760 Td (Date: " . $inv_date . ") Tj ET\n";
    $stream .= "BT /F1 11 Tf 50 730 Td (Customer: " . $cust_name . ") Tj ET\n";
    $stream .= "BT /F1 10 Tf 50 700 Td (--------------------------------------------------------------------------------) Tj ET\n";

    $y = 680;
    foreach ($items as $it) {
        $line = $it['name'] . "  x" . $it['qty'] . "  Rs." . number_format($it['rate_excl_gst'], 2);
        $stream .= "BT /F1 10 Tf 50 " . $y . " Td (" . $line . ") Tj ET\n";
        $y -= 20;
    }

    $stream .= "BT /F1 10 Tf 50 " . ($y - 10) . " Td (--------------------------------------------------------------------------------) Tj ET\n";
    $stream .= "BT /F1 12 Tf 50 " . ($y - 30) . " Td (Grand Total: Rs." . number_format($net_total, 2) . ") Tj ET\n";

    $pdf .= "5 0 obj<</Length " . strlen($stream) . ">>stream\n" . $stream . "\nendstream\nendobj\n";
    $pdf .= "xref\n0 6\n0000000000 65535 f \n0000000009 00000 n \n0000000052 00000 n \n0000000101 00000 n \n0000000213 00000 n \n0000000282 00000 n \ntrailer<</Size 6/Root 1 0 R>>\nstartxref\n" . (strlen($pdf) - 50) . "\n%%EOF";

    return base64_encode($pdf);
}

/*--------------------------------------------------------------*/
/* 1. SEND INVOICE PDF VIA BREVO
/*--------------------------------------------------------------*/
function send_invoice_email($invoice_id, $to_email, $customer_name) {
    global $db;

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

    $org_master = find_by_sql("
        SELECT org_name 
        FROM master_inventory.master_organization 
        WHERE org_id = '{$invoice['organization_id']}' LIMIT 1
    ");
    $org_master = $org_master ? $org_master[0] : ['org_name' => 'ORGANIZATION'];

    $items = find_by_sql("
        SELECT ii.*, p.name
        FROM invoice_items ii
        LEFT JOIN products p ON p.id = ii.product_id
        WHERE ii.invoice_id = '{$invoice_id}'
    ");

    $pdf_base64 = generate_native_pdf_base64(
        $title_text, 
        $invoice['invoice_no'], 
        date("d/M/Y", strtotime($invoice['invoice_date'])), 
        $customer_name, 
        $items, 
        $invoice['net_total']
    );

    $apiKey      = $_ENV['BREVO_API_KEY'] ?? $_SERVER['BREVO_API_KEY'] ?? getenv('BREVO_API_KEY');
    $senderEmail = $_ENV['BREVO_SENDER_EMAIL'] ?? $_SERVER['BREVO_SENDER_EMAIL'] ?? getenv('BREVO_SENDER_EMAIL');

    if (empty($apiKey)) return "BREVO_API_KEY is EMPTY in Railway Environment Variables!";
    if (empty($senderEmail)) return "BREVO_SENDER_EMAIL is EMPTY in Railway Environment Variables!";

    $subject = ($is_revised ? "[REVISED] " : "") . "Invoice #" . $invoice['invoice_no'] . " from " . strtoupper($org_master['org_name']);
    $body = "Dear <b>" . htmlspecialchars($customer_name) . "</b>,<br><br>Please find attached your invoice PDF.<br><br>Regards,<br><b>Team " . htmlspecialchars($org_master['org_name']) . "</b>";

    $data = [
        "sender" => ["name" => strtoupper($org_master['org_name']), "email" => $senderEmail],
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
    return send_invoice_email($quotation_id, $to_email, $customer_name);
}
?>
