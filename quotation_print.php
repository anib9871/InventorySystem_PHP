<?php
require_once('includes/load.php');

if(strpos($print_name,'Thermal') !== false){
    include('invoice_print_thermal.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($id <= 0) die("Invalid Quotation ID");

/* Fetch Quotation */
$invoice_data = find_by_sql("
SELECT i.*, c.customer_name, c.address, c.gst_no, c.contact_no, c.email AS customer_email
FROM quotation_master i
LEFT JOIN customer_master c ON c.id = i.customer_id
WHERE i.id = $id
");

if(!$invoice_data) die("Quotation not found");
$quotation = $invoice_data[0];
/* ================= MANUAL EMAIL SEND HANDLER ================= */
$email_msg = "";
$email_status = "";

if (isset($_POST['send_email_btn'])) {
    $raw_emails = trim($_POST['target_email']);
    $client_name  = $quotation['customer_name'];

    if (!empty($raw_emails)) {
        $emails = array_map('trim', explode(',', $raw_emails));
        $sent_emails = [];
        $failed_emails = [];
        $invalid_emails = [];

        foreach ($emails as $client_email) {
            if (!empty($client_email)) {
                // Email format check
                if (filter_var($client_email, FILTER_VALIDATE_EMAIL)) {
                    $sent = send_quotation_email($id, $client_email, $client_name);
                    if ($sent === true || $sent == 1) {
                        $sent_emails[] = htmlspecialchars($client_email);
                    } else {
                        $failed_emails[] = htmlspecialchars($client_email);
                    }
                } else {
                    $invalid_emails[] = htmlspecialchars($client_email); // Galat email pakad liya
                }
            }
        }

        // Alert Message Generate
        $msg_parts = [];
        if (!empty($sent_emails)) $msg_parts[] = "✔ Sent: " . implode(', ', $sent_emails);
        if (!empty($invalid_emails)) $msg_parts[] = "✖ Invalid (Ignored): " . implode(', ', $invalid_emails);
        if (!empty($failed_emails)) $msg_parts[] = "⚠ Failed: " . implode(', ', $failed_emails);

        $email_msg = implode(" | ", $msg_parts);
        $email_status = (!empty($sent_emails) && empty($failed_emails)) ? "success" : "error";
    } else {
        $email_msg = "Please enter at least one email address!";
        $email_status = "error";
    }
}


/* MASTER ORG NAME */
$org_master = find_by_sql("
SELECT org_name 
FROM master_inventory.master_organization 
WHERE org_id = '{$quotation['organization_id']}'
LIMIT 1
");

$org_master = $org_master ? $org_master[0] : ['org_name' => ''];

/* Organization */
$org = find_by_sql("SELECT * FROM organization_master WHERE id=".$quotation['organization_id'])[0];

// JSON Address ko plain text me badalne ka naya logic
$raw_org_address = $org['address'];
$decoded_org_addr = json_decode($raw_org_address, true);
$final_org_address = $raw_org_address; // Default fallback

if (is_array($decoded_org_addr)) {
    if (isset($decoded_org_addr[0]['text'])) {
        $final_org_address = $decoded_org_addr[0]['text'];
    } elseif (isset($decoded_org_addr[0]) && is_string($decoded_org_addr[0])) {
        $final_org_address = $decoded_org_addr[0];
    }
}

/* ================= TAX MODE DETECT ================= */

$org_state  = substr($org['gst_no'], 0, 2);
$cust_state = substr($quotation['gst_no'], 0, 2);

$tax_mode = ($org_state == $cust_state) ? 'CGST_SGST' : 'IGST';

/* Bank */
$bank_data = find_by_sql("SELECT * FROM bank_master WHERE organization_id=".$quotation['organization_id']);
$bank = $bank_data ? $bank_data[0] : null;

/* Items */
$items = find_by_sql("
SELECT ii.*, p.name , p.hsn_code
FROM quotation_items ii
LEFT JOIN products p ON p.id = ii.product_id
WHERE ii.quotation_id = $id
");

/* ================= NUMBER TO WORDS ================= */

function numberToWords($num){
    $ones = array(
        0=>"",1=>"One",2=>"Two",3=>"Three",4=>"Four",5=>"Five",
        6=>"Six",7=>"Seven",8=>"Eight",9=>"Nine",10=>"Ten",
        11=>"Eleven",12=>"Twelve",13=>"Thirteen",14=>"Fourteen",
        15=>"Fifteen",16=>"Sixteen",17=>"Seventeen",
        18=>"Eighteen",19=>"Nineteen"
    );
    $tens = array(
        0=>"",2=>"Twenty",3=>"Thirty",4=>"Forty",
        5=>"Fifty",6=>"Sixty",7=>"Seventy",
        8=>"Eighty",9=>"Ninety"
    );

    if($num==0) return "Zero";

    if($num<20) return $ones[$num];

    if($num<100){
        return $tens[intval($num/10)]." ".$ones[$num%10];
    }

    if($num<1000){
        return $ones[intval($num/100)]." Hundred ".numberToWords($num%100);
    }

    if($num<100000){
        return numberToWords(intval($num/1000))." Thousand ".numberToWords($num%1000);
    }

    if($num<10000000){
        return numberToWords(intval($num/100000))." Lakh ".numberToWords($num%100000);
    }

    return numberToWords(intval($num/10000000))." Crore ".numberToWords($num%10000000);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($quotation['customer_name']) ?>_Quotation_<?= htmlspecialchars($quotation['quotation_no']) ?></title>
<style>
/* ===== GLOBAL RESET & VARIABLES ===== */
:root {
  --primary-color: #2563eb;
  --primary-dark: #1d4ed8;
  --primary-light: #eff6ff;
  --text-main: #1e293b;
  --text-muted: #64748b;
  --border-color: #e2e8f0;
  --table-header-bg: #f8fafc;
}

* {
  box-sizing: border-box;
}

body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  font-size: 12px;
  line-height: 1.35;
  margin: 0;
  padding: 0;
  background: #f1f5f9;
  color: var(--text-main);
}

/* ===== ACTION BAR (NO PRINT) ===== */
.no-print-bar {
  max-width: 850px;
  margin: 15px auto;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  font-size: 12px;
  font-weight: 500;
  text-decoration: none;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s ease;
  border: 1px solid var(--border-color);
  background: #ffffff;
  color: var(--text-main);
  box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.btn:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
}

.btn-primary {
  background: var(--primary-color);
  color: #ffffff;
  border-color: var(--primary-color);
}

.btn-primary:hover {
  background: var(--primary-dark);
  border-color: var(--primary-dark);
}

/* ===== MAIN WRAPPER ===== */
.wrapper {
  width: 100%;
  max-width: 850px;
  margin: 0 auto 20px auto;
  background: #ffffff;
  border-radius: 8px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  padding: 20px 24px;
  position: relative;
  overflow: hidden;
}

/* Decorative Header Bar */
.wrapper::before {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 5px;
  background: var(--primary-color);
}

/* ===== HEADER & META ===== */
.header-grid {
  display: flex;
  justify-content: space-between;
  margin-bottom: 12px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--border-color);
}

.org-brand {
  flex: 1;
}

.org-title {
  font-size: 18px;
  font-weight: 700;
  color: var(--text-main);
  letter-spacing: -0.3px;
  margin: 0 0 4px 0;
}

.invoice-badge-box {
  text-align: right;
}

.invoice-title {
  font-size: 20px;
  font-weight: 800;
  color: var(--primary-color);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin: 0 0 4px 0;
}

.meta-info-table {
  width: auto;
  margin-left: auto;
  border-collapse: collapse;
}

.meta-info-table td {
  padding: 1px 0 1px 10px;
  font-size: 11px;
}

.meta-label {
  color: var(--text-muted);
  font-weight: 500;
  text-align: right;
}

.meta-value {
  font-weight: 600;
  color: var(--text-main);
  text-align: right;
}

/* ===== CUSTOMER CARD ===== */
.info-card {
  background: var(--primary-light);
  border: 1px solid #dbeafe;
  border-radius: 6px;
  padding: 8px 12px;
  margin-bottom: 12px;
}

.card-title {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--primary-color);
  margin-bottom: 2px;
}

.customer-name {
  font-size: 13px;
  font-weight: 700;
  color: var(--text-main);
  margin-bottom: 2px;
}

/* ===== TABLES ===== */
table.data-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 10px;
}

table.data-table th {
  background: var(--table-header-bg);
  color: var(--text-muted);
  font-weight: 600;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  padding: 6px 8px;
  border-top: 1px solid var(--border-color);
  border-bottom: 1px solid var(--border-color);
}

table.data-table td {
  padding: 5px 8px;
  border-bottom: 1px solid var(--border-color);
  vertical-align: middle;
  font-size: 11px;
}

table.data-table tr:nth-child(even):not(.summary-row) {
  background-color: #fafafa;
}

.right { text-align: right; }
.center { text-align: center; }
.bold { font-weight: 600; }

/* Table Summary Section */
.summary-row td {
  border-bottom: none;
  padding: 3px 8px;
}

.summary-row.grand-total td {
  border-top: 1px solid var(--border-color);
  border-bottom: 1px solid var(--border-color);
  background: var(--primary-light);
  font-size: 12px;
  color: var(--primary-dark);
}

/* Amount in words bar */
.amount-words-box {
  background: #f8fafc;
  border: 1px dashed var(--border-color);
  border-radius: 5px;
  padding: 6px 10px;
  margin-bottom: 10px;
  font-size: 11px;
}

/* ===== DUAL FOOTER SECTION ===== */
.footer-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-top: 10px;
}

.footer-card {
  border: 1px solid var(--border-color);
  border-radius: 6px;
  padding: 8px 10px;
}

.footer-card-title {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--text-muted);
  margin-bottom: 4px;
  border-bottom: 1px solid var(--border-color);
  padding-bottom: 2px;
}

.terms-box {
  white-space: pre-line;
  font-size: 10px;
  color: var(--text-muted);
  line-height: 1.3;
}

.signature-space {
  height: 28px;
}

/* ===== PRINT STYLES ===== */
@page {
  size: A4;
  margin: 6mm 8mm; /* Compact margins to fit exactly 1 page */
}

@media print {
  html, body {
    height: 100%;
    background: #ffffff;
    color: #000000;
  }

  .no-print-bar {
    display: none !important;
  }

  .wrapper {
    box-shadow: none;
    padding: 0;
    max-width: 100%;
    border-radius: 0;
    margin: 0;
  }

  table.data-table th {
    background: #f1f5f9 !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .info-card, .summary-row.grand-total td {
    background: #f8fafc !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .wrapper::before {
    display: none;
  }
}
</style>
</head>

<body>

<!-- PRINT / EMAIL / NAVIGATION BUTTONS -->
<div class="no-print-bar">
    <div>
        <a href="create_quotation.php" class="btn">← Back to Create Quotation</a>
        <a href="quotation_list.php" class="btn">← Back to Quotation List</a>
    </div>

    <div style="display: flex; gap: 8px; align-items: center;">
        <!-- EMAIL FORM -->
        <form method="post" style="display: flex; gap: 6px; align-items: center; margin: 0;">
            <input type="email" name="target_email" 
                   style="height: 31px; padding: 4px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; width: 200px;" 
                   value="<?= htmlspecialchars($quotation['customer_email'] ?? '') ?>" 
                   placeholder="Enter Customer Email" required>
            <button type="submit" name="send_email_btn" class="btn" style="background: #059669; color: #fff; border-color: #059669;">📧 Send Email</button>
        </form>

        <button onclick="window.print()" class="btn btn-primary">🖨 Print Quotation</button>
    </div>
</div>

<!-- ALERT NOTIFICATION -->
<?php if (!empty($email_msg)): ?>
    <div style="max-width: 850px; margin: 10px auto; padding: 10px 14px; border-radius: 6px; font-size: 12px; font-weight: 500; <?= $email_status == 'success' ? 'background: #dcfce7; color: #15803d;' : 'background: #fee2e2; color: #b91c1c;' ?>">
        <?= $email_msg ?>
    </div>
<?php endif; ?>
<div class="wrapper">

<!-- HEADER SECTION -->
    <div class="header-grid">
        <div class="org-brand">
            <h1 class="org-title"><?= strtoupper($org_master['org_name']) ?></h1>
            <div style="color: var(--text-muted); font-size: 11px; line-height: 1.3;">
                <?= htmlspecialchars($final_org_address) ?><br>
                <?php if($gst_enabled == "Yes"): ?>
                    <b>GSTIN:</b> <?= $org['gst_no'] ?> | 
                <?php endif; ?>
                <b>Phone:</b> <?= $org['phone'] ?>
            </div>
        </div>

        <div class="invoice-badge-box">
            <div class="invoice-title">Quotation</div>
            <table class="meta-info-table">
                <tr>
                    <td class="meta-label">Quotation No:</td>
                    <td class="meta-value"><?= $quotation['quotation_no'] ?></td>
                </tr>
                <tr>
                    <td class="meta-label">Date:</td>
                    <td class="meta-value"><?= date("d/M/Y", strtotime($quotation['quotation_date'])) ?></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- CUSTOMER DETAILS -->
    <div class="info-card">
        <div class="card-title">Customer Details</div>
        <div class="customer-name"><?= strtoupper($quotation['customer_name']) ?></div>
        <div style="color: var(--text-main); font-size: 11px; line-height: 1.3;">
            <?= $quotation['address'] ?><br>
            <?php if($gst_enabled == "Yes"): ?>
                <b>GSTIN:</b> <?= $quotation['gst_no'] ?> | 
            <?php endif; ?>
            <?php if(!empty($quotation['contact_no'])): ?>
                <b>Phone:</b> <?= $quotation['contact_no'] ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ITEMS TABLE -->
    <table class="data-table">
        <thead>
            <tr>
                <th class="center" width="5%">#</th>
                <th width="32%">Item Name</th>
                <th class="center" width="10%">HSN/SAC</th>
                <th class="right" width="8%">Qty</th>
                <th class="center" width="8%">Unit</th>
                <th class="right" width="12%">Price/Unit</th>
                <th class="right" width="10%">Discount</th>
                <?php if($gst_enabled == "Yes"): ?>
                    <th class="center" width="5%">GST</th>
                <?php endif; ?>
                <th class="right" width="10%">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $i = 1;
            $total_taxable = 0;
            $total_cgst = 0;
            $total_sgst = 0;
            $total_igst = 0;

            foreach($items as $it){
                $taxable = ($it['qty'] * $it['rate_excl_gst']) - $it['discount_amount'];
                $total_taxable += $taxable;
                $total_cgst += $it['cgst_amount'];
                $total_sgst += $it['sgst_amount'];
                $total_igst += $it['igst_amount'];
            ?>
            <tr>
                <td class="center"><?= $i++ ?></td>
                <td class="bold"><?= $it['name'] ?></td>
                <td class="center"><?= $it['hsn_code'] ?></td>
                <td class="right"><?= $it['qty'] ?></td>
                <td class="center">Nos</td>
                <td class="right"><?= number_format($it['rate_excl_gst'],2) ?></td>
                <td class="right"><?= number_format($it['discount_amount'],2) ?></td>
                <?php if($gst_enabled == "Yes"): ?>
                    <td class="center"><?= $it['gst_percent'] ?>%</td>
                <?php endif; ?>
                <td class="right bold"><?= number_format($taxable,2) ?></td>
            </tr>
            <?php } ?>

            <!-- SUMMARY ROWS -->
            <tr class="summary-row">
                <td colspan="<?= ($gst_enabled == 'Yes') ? '8' : '7' ?>" class="right bold">Sub Total</td>
                <td class="right bold"><?= number_format($total_taxable,2) ?></td>
            </tr>

            <?php if($gst_enabled == "Yes"){ ?>
                <?php if($tax_mode == 'IGST'){ ?>
                <tr class="summary-row">
                    <td colspan="8" class="right">Total IGST</td>
                    <td class="right"><?= number_format($total_igst,2) ?></td>
                </tr>
                <?php } else { ?>
                <tr class="summary-row">
                    <td colspan="8" class="right">Total CGST</td>
                    <td class="right"><?= number_format($total_cgst,2) ?></td>
                </tr>
                <tr class="summary-row">
                    <td colspan="8" class="right">Total SGST</td>
                    <td class="right"><?= number_format($total_sgst,2) ?></td>
                </tr>
                <?php } ?>
            <?php } ?>

            <tr class="summary-row grand-total">
                <td colspan="<?= ($gst_enabled == 'Yes') ? '8' : '7' ?>" class="right bold">Total Amount</td>
                <td class="right bold"><?= number_format($quotation['net_total'],2) ?></td>
            </tr>
        </tbody>
    </table>

    <!-- AMOUNT IN WORDS -->
    <div class="amount-words-box">
        <span class="bold" style="color: var(--text-muted);">Amount in Words:</span> 
        <span class="bold"><?= numberToWords(round($quotation['net_total'])) ?> Only</span>
    </div>

    <?php if($gst_enabled == "Yes"): ?>
    <!-- GST BREAKDOWN TABLE -->
    <div style="margin-top: 10px;">
        <div style="font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px;">GST Tax Breakdown</div>
        <table class="data-table" style="font-size: 10px; margin-bottom: 8px;">
            <thead>
                <tr>
                    <th rowspan="2" class="center">HSN / SAC</th>
                    <th rowspan="2" class="right">Taxable Amount</th>
                    <th rowspan="2" class="center">Rate</th>
                    <?php if($tax_mode == 'IGST'){ ?>
                        <th colspan="2" class="center">IGST</th>
                    <?php } else { ?>
                        <th colspan="2" class="center">CGST</th>
                        <th colspan="2" class="center">SGST</th>
                    <?php } ?>
                    <th rowspan="2" class="right">Total Tax</th>
                </tr>
                <tr>
                    <?php if($tax_mode == 'IGST'){ ?>
                        <th class="center">Rate</th>
                        <th class="right">Amount</th>
                    <?php } else { ?>
                        <th class="center">Rate</th>
                        <th class="right">Amount</th>
                        <th class="center">Rate</th>
                        <th class="right">Amount</th>
                    <?php } ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $gst_summary = [];

                foreach($items as $it){
                    $hsn  = $it['hsn_code'] ?? 'NA';
                    $rate = $it['gst_percent'];
                    $key = $hsn . '_' . $rate;

                    if(!isset($gst_summary[$key])){
                        $gst_summary[$key] = [
                            'hsn'      => $hsn,
                            'rate'     => $rate,
                            'taxable'  => 0,
                            'cgst'     => 0,
                            'sgst'     => 0,
                            'igst'     => 0
                        ];
                    }

                    $taxable = ($it['qty'] * $it['rate_excl_gst']) - $it['discount_amount'];
                    $gst_summary[$key]['taxable'] += $taxable;
                    $gst_summary[$key]['cgst']    += $it['cgst_amount'];
                    $gst_summary[$key]['sgst']    += $it['sgst_amount'];
                    $gst_summary[$key]['igst']    += $it['igst_amount'];
                }

                $grand_taxable = 0;
                $grand_cgst = 0;
                $grand_sgst = 0;
                $grand_igst = 0;

                foreach($gst_summary as $key => $data):
                    $grand_taxable += $data['taxable'];
                    $grand_cgst += $data['cgst'];
                    $grand_sgst += $data['sgst'];
                    $grand_igst += $data['igst'];
                    $total_tax = $data['cgst'] + $data['sgst'] + $data['igst'];
                ?>
                <tr>
                    <td class="center"><?= $data['hsn'] ?></td>
                    <td class="right"><?= number_format($data['taxable'],2) ?></td>
                    <td class="center"><?= $data['rate'] ?>%</td>

                    <?php if($tax_mode == 'IGST'){ ?>
                        <td class="center"><?= $data['rate'] ?>%</td>
                        <td class="right"><?= number_format($data['igst'],2) ?></td>
                    <?php } else { ?>
                        <td class="center"><?= $data['rate']/2 ?>%</td>
                        <td class="right"><?= number_format($data['cgst'],2) ?></td>
                        <td class="center"><?= $data['rate']/2 ?>%</td>
                        <td class="right"><?= number_format($data['sgst'],2) ?></td>
                    <?php } ?>

                    <td class="right bold"><?= number_format($total_tax,2) ?></td>
                </tr>
                <?php endforeach; ?>

                <tr class="bold" style="background: var(--table-header-bg);">
                    <td class="center">Total</td>
                    <td class="right"><?= number_format($grand_taxable,2) ?></td>
                    <td></td>

                    <?php if($tax_mode == 'IGST'){ ?>
                        <td></td>
                        <td class="right"><?= number_format($grand_igst,2) ?></td>
                    <?php } else { ?>
                        <td></td>
                        <td class="right"><?= number_format($grand_cgst,2) ?></td>
                        <td></td>
                        <td class="right"><?= number_format($grand_sgst,2) ?></td>
                    <?php } ?>

                    <td class="right"><?= number_format($grand_cgst + $grand_sgst + $grand_igst,2) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- FOOTER INFO: BANK DETAILS & AUTHORIZATION -->
    <div class="footer-grid">
        <div class="footer-card">
            <div class="footer-card-title">Bank Details</div>
            <?php if($bank){ ?>
                <div style="font-size: 11px; line-height: 1.4;">
                    <b>Bank:</b> <?= $bank['bank_name'] ?><br>
                    <b>A/C Name:</b> <?= $bank['account_name'] ?><br>
                    <b>A/C No:</b> <?= $bank['account_number'] ?><br>
                    <b>IFSC Code:</b> <?= $bank['ifsc_code'] ?>
                </div>
            <?php } else { ?>
                <div style="color: var(--text-muted); font-size: 11px;">No bank details available.</div>
            <?php } ?>
        </div>

        <div class="footer-card center" style="display: flex; flex-direction: column; justify-content: space-between;">
            <div class="footer-card-title">Authorized Signatory</div>
            <div class="bold"><?= $org_master['org_name'] ?></div>
            <div class="signature-space"></div>
            <div style="font-size: 9px; color: var(--text-muted);">
                Computer-generated quotation. No physical signature required.
            </div>
        </div>
    </div>

    <!-- TERMS & CONDITIONS -->
    <?php if(!empty(trim($quotation['terms_conditions']))): ?>
    <div class="footer-card" style="margin-top: 10px;">
        <div class="footer-card-title">Terms & Conditions</div>
        <div class="terms-box"><?= trim($quotation['terms_conditions']); ?></div>
    </div>
    <?php endif; ?>

</div>

</body>
</html>
