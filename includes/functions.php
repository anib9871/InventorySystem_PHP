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
/* HELPER: SMART DOMPDF LOADER FOR NESTED GITHUB PATH
/*--------------------------------------------------------------*/
function init_dompdf_autoloader() {
    if (class_exists('Dompdf\Dompdf')) {
        return true;
    }

    // 1. Try Standard Composer Autoloaders
    $possible_paths = [
        __DIR__ . '/libs/dompdf/vendor/autoload.php',
        __DIR__ . '/../libs/dompdf/vendor/autoload.php',
        $_SERVER['DOCUMENT_ROOT'] . '/libs/dompdf/vendor/autoload.php',
        $_SERVER['DOCUMENT_ROOT'] . '/InventorySystem_PHP/libs/dompdf/vendor/autoload.php',
        '/app/libs/dompdf/vendor/autoload.php'
    ];

    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            require_once($path);
            break;
        }
    }

    if (class_exists('Dompdf\Dompdf')) {
        return true;
    }

    // 2. Direct Auto-Fallback for your GitHub Nested Path: libs/dompdf/vendor/dompdf/dompdf/src
    $search_bases = [
        __DIR__ . '/libs/dompdf/vendor/dompdf/dompdf',
        __DIR__ . '/../libs/dompdf/vendor/dompdf/dompdf',
        $_SERVER['DOCUMENT_ROOT'] . '/libs/dompdf/vendor/dompdf/dompdf',
        $_SERVER['DOCUMENT_ROOT'] . '/InventorySystem_PHP/libs/dompdf/vendor/dompdf/dompdf',
        '/app/libs/dompdf/vendor/dompdf/dompdf'
    ];

    foreach ($search_bases as $base) {
        if (file_exists($base . '/src/Dompdf.php')) {
            spl_autoload_register(function ($class) use ($base) {
                $prefix = 'Dompdf\\';
                $len = strlen($prefix);
                if (strncmp($prefix, $class, $len) !== 0) return;
                $relative_class = substr($class, $len);
                $file = $base . '/src/' . str_replace('\\', '/', $relative_class) . '.php';
                if (file_exists($file)) {
                    require_once $file;
                }
            });

            // Also load FontLib & SvgLib if needed
            spl_autoload_register(function ($class) use ($base) {
                if (strpos($class, 'FontLib\\') === 0) {
                    $file = dirname($base) . '/phenx/php-font-lib/src/' . str_replace('\\', '/', $class) . '.php';
                    if (file_exists($file)) require_once $file;
                }
                if (strpos($class, 'Svg\\') === 0) {
                    $file = dirname($base) . '/phenx/php-svg-lib/src/' . str_replace('\\', '/', $class) . '.php';
                    if (file_exists($file)) require_once $file;
                }
            });

            return true;
        }
    }

    return false;
}

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
?>
