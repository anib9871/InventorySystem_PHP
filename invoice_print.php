<?php
require_once('includes/load.php');

if(strpos($print_name,'Thermal') !== false){
    include('invoice_print_thermal.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($id <= 0) die("Invalid Invoice ID");

/* Fetch Invoice */
$invoice_data = find_by_sql("
SELECT i.*, c.customer_name, c.address, c.gst_no, c.contact_no, c.email AS customer_email
FROM invoice i
LEFT JOIN customer_master c ON c.id = i.customer_id
WHERE i.id = $id
");

if(!$invoice_data) die("Invoice not found");
$invoice = $invoice_data[0];

/* PROFORMA VS TAX INVOICE TITLE LOGIC */
$doc_type = strtoupper($invoice['remarks'] ?? '');
$payment_status = strtolower(trim($invoice['payment_status'] ?? ''));

// Agar payment Paid ya Partial ho chuki hai (amount > 0), toh Tax Invoice dikhao
if ($payment_status === 'paid' || $invoice['paid_amount'] > 0) {
    $is_proforma = false;
} else {
    // Agar payment nahi hui hai, tabhi Proforma check karo
    $is_proforma = ($doc_type === 'PROFORMA' || ($invoice['paid_amount'] == 0 && $payment_status === 'unpaid'));
}

if ($is_proforma) {
    $title_text = 'PROFORMA INVOICE';
    $title_color = '#d97706'; 
} else {
    $title_text = 'TAX INVOICE';
    $title_color = '#2563eb'; 
}

/* FETCH PAYMENTS, UTR & DATE RECORDS */
$payment_records = find_by_sql("
SELECT payment_mode, amount, reference_no, payment_date 
FROM payments 
WHERE invoice_id = $id AND amount > 0
");

/* ================= MANUAL MULTIPLE EMAIL SEND HANDLER ================= */
$email_msg = "";
$email_status = "";

if (isset($_POST['send_email_btn'])) {
    $raw_emails = trim($_POST['target_email']);
    $client_name  = $invoice['customer_name'];

    if (!empty($raw_emails)) {
        // Comma se split karke multiple emails ki array banao
        $emails = array_map('trim', explode(',', $raw_emails));
        
        $sent_emails = [];
        $failed_emails = [];

        foreach ($emails as $client_email) {
            if (!empty($client_email) && filter_var($client_email, FILTER_VALIDATE_EMAIL)) {
                $sent = send_invoice_email($id, $client_email, $client_name);
                if ($sent === true || $sent == 1) {
                    $sent_emails[] = htmlspecialchars($client_email);
                } else {
                    $failed_emails[] = htmlspecialchars($client_email);
                }
            } else {
                $failed_emails[] = htmlspecialchars($client_email);
            }
        }

        // Status Message Create Karo
        if (!empty($sent_emails) && empty($failed_emails)) {
            $email_msg = "Document email successfully sent to: " . implode(', ', $sent_emails);
            $email_status = "success";
        } elseif (!empty($sent_emails) && !empty($failed_emails)) {
            $email_msg = "Sent to: " . implode(', ', $sent_emails) . " | Failed for: " . implode(', ', $failed_emails);
            $email_status = "error";
        } else {
            $email_msg = "Failed to send email to provided address(es).";
            $email_status = "error";
        }
    } else {
        $email_msg = "Please enter at least one valid email address!";
        $email_status = "error";
    }
}

/* MASTER ORG NAME */
$org_master = find_by_sql("
SELECT org_name 
FROM master_inventory.master_organization 
WHERE org_id = '{$invoice['organization_id']}'
LIMIT 1
");

$org_master = $org_master ? $org_master[0] : ['org_name' => ''];

/* Organization */
$org = find_by_sql("SELECT * FROM organization_master WHERE id=".$invoice['organization_id'])[0];

/* TAX MODE DETECT */
$org_state  = substr($org['gst_no'] ?? '', 0, 2);
$cust_state = substr($invoice['gst_no'] ?? '', 0, 2);

$tax_mode = ($org_state == $cust_state) ? 'CGST_SGST' : 'IGST';

/* Bank */
$bank_data = find_by_sql("SELECT * FROM bank_master WHERE organization_id=".$invoice['organization_id']);
$bank = $bank_data ? $bank_data[0] : null;

/* Items */
$items = find_by_sql("
SELECT ii.*, p.name , p.hsn_code
FROM invoice_items ii
LEFT JOIN products p ON p.id = ii.product_id
WHERE ii.invoice_id = $id
");

/* NUMBER TO WORDS FUNCTION */
function convertGroup($num) {
    $ones = array(
        0 => "", 1 => "One", 2 => "Two", 3 => "Three", 4 => "Four", 5 => "Five",
        6 => "Six", 7 => "Seven", 8 => "Eight", 9 => "Nine", 10 => "Ten",
        11 => "Eleven", 12 => "Twelve", 13 => "Thirteen", 14 => "Fourteen",
        15 => "Fifteen", 16 => "Sixteen", 17 => "Seventeen", 18 => "Eighteen", 19 => "Nineteen"
    );
    $tens = array(
        0 => "", 2 => "Twenty", 3 => "Thirty", 4 => "Forty",
        5 => "Fifty", 6 => "Sixty", 7 => "Seventy", 8 => "Eighty", 9 => "Ninety"
    );

    if ($num == 0) return "";
    if ($num < 20) return $ones[$num];
    if ($num < 100) return trim($tens[intval($num / 10)] . " " . $ones[$num % 10]);
    if ($num < 1000) return trim($ones[intval($num / 100)] . " Hundred " . convertGroup($num % 100));
    if ($num < 100000) return trim(convertGroup(intval($num / 1000)) . " Thousand " . convertGroup($num % 1000));
    if ($num < 10000000) return trim(convertGroup(intval($num / 100000)) . " Lakh " . convertGroup($num % 100000));
    return trim(convertGroup(intval($num / 10000000)) . " Crore " . convertGroup($num % 10000000));
}

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($invoice['customer_name']) ?> - <?= $is_proforma ? 'PROFORMA INVOICE' : 'TAX INVOICE' ?> (<?= htmlspecialchars(str_replace('/', '-', $invoice['invoice_no'])) ?>)</title>

<style>
:root {
  --primary-color: <?= $title_color ?>;
  --primary-dark: #1d4ed8;
  --primary-light: #eff6ff;
  --text-main: #1e293b;
  --text-muted: #64748b;
  --border-color: #e2e8f0;
  --table-header-bg: #f8fafc;
}

* { box-sizing: border-box; }

body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  font-size: 12px;
  line-height: 1.35;
  margin: 0;
  padding: 0;
  background: #f1f5f9;
  color: var(--text-main);
}

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
  padding: 6px 12px;
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

.btn:hover { background: #f8fafc; border-color: #cbd5e1; }
.btn-primary { background: var(--primary-color); color: #ffffff; border-color: var(--primary-color); }
.btn-primary:hover { opacity: 0.9; }

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

.wrapper::before {
  content: "";
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 5px;
  background: var(--primary-color);
}

.header-grid {
  display: flex;
  justify-content: space-between;
  margin-bottom: 12px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--border-color);
}

.org-brand { flex: 1; }
.org-title { font-size: 18px; font-weight: 700; color: var(--text-main); letter-spacing: -0.3px; margin: 0 0 4px 0; }
.invoice-badge-box { text-align: right; }
.invoice-title { font-size: 20px; font-weight: 800; color: <?= $title_color ?>; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 4px 0; }

.meta-info-table { width: auto; margin-left: auto; border-collapse: collapse; }
.meta-info-table td { padding: 1px 0 1px 10px; font-size: 11px; }
.meta-label { color: var(--text-muted); font-weight: 500; text-align: right; }
.meta-value { font-weight: 600; color: var(--text-main); text-align: right; }

.info-card { background: var(--primary-light); border: 1px solid #dbeafe; border-radius: 6px; padding: 8px 12px; margin-bottom: 12px; }
.card-title { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #2563eb; margin-bottom: 2px; }
.customer-name { font-size: 13px; font-weight: 700; color: var(--text-main); margin-bottom: 2px; }

table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
table.data-table th { background: var(--table-header-bg); color: var(--text-muted); font-weight: 600; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; padding: 6px 8px; border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); }
table.data-table td { padding: 5px 8px; border-bottom: 1px solid var(--border-color); vertical-align: middle; font-size: 11px; }
table.data-table tr:nth-child(even):not(.summary-row) { background-color: #fafafa; }

.right { text-align: right; }
.center { text-align: center; }
.bold { font-weight: 600; }

.summary-row td { border-bottom: none; padding: 3px 8px; }
.summary-row.grand-total td { border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); background: var(--primary-light); font-size: 12px; color: #1d4ed8; font-weight: bold; }

.amount-words-box { background: #f8fafc; border: 1px dashed var(--border-color); border-radius: 5px; padding: 6px 10px; margin-bottom: 10px; font-size: 11px; }

.utr-box {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 6px;
  padding: 8px 12px;
  margin-bottom: 10px;
}
.utr-title {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  color: #166534;
  margin-bottom: 4px;
}

.footer-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 10px; }
.footer-card { border: 1px solid var(--border-color); border-radius: 6px; padding: 8px 10px; }
.footer-card-title { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 4px; border-bottom: 1px solid var(--border-color); padding-bottom: 2px; }
.terms-box { white-space: pre-line; font-size: 10px; color: var(--text-muted); line-height: 1.3; }
.signature-space { height: 28px; }

/* COMPACT TOP-RIGHT ALERT BANNER STYLES */
.custom-top-toast {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 99999;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    animation: fadeInSlide 0.3s ease-in-out;
}

.toast-success {
    background-color: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.toast-error {
    background-color: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

.toast-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    font-size: 11px;
    font-weight: bold;
}

.toast-success .toast-icon {
    background-color: #10b981;
    color: #ffffff;
}

.toast-error .toast-icon {
    background-color: #ef4444;
    color: #ffffff;
}

@keyframes fadeInSlide {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@page { size: A4; margin: 6mm 8mm; }
@media print {
  html, body { height: 100%; background: #ffffff; color: #000000; }
  .no-print-bar, .custom-top-toast { display: none !important; }
  .wrapper { box-shadow: none; padding: 0; max-width: 100%; border-radius: 0; margin: 0; }
  table.data-table th { background: #f1f5f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .info-card, .summary-row.grand-total td { background: #f8fafc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .utr-box { background: #f0fdf4 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .wrapper::before { display: none; }
}
</style>
</head>

<body>

<!-- TOP RIGHT TOAST BANNER -->
<?php if (!empty($email_msg)): ?>
<div id="customToastAlert" class="custom-top-toast <?= $email_status === 'success' ? 'toast-success' : 'toast-error' ?>">
    <span class="toast-icon"><?= $email_status === 'success' ? '✔' : '✖' ?></span>
    <span class="toast-message"><?= $email_msg ?></span>
</div>
<?php endif; ?>

<!-- NAVIGATION BUTTONS BAR -->
<div class="no-print-bar">
    <div style="display: flex; gap: 6px;">
        <a href="invoice_create.php" class="btn">← Create Invoice</a>
        <a href="invoice_list.php" class="btn">← Invoice List</a>
        <a href="payment_report.php?action=generate&type=customer" class="btn" style="background: #f8fafc; border-color: #3b82f6; color: #1d4ed8; font-weight: 600;">← Back to Payment Report</a>
    </div>

    <div style="display: flex; gap: 8px; align-items: center;">
 <form method="post" style="display: flex; gap: 6px; align-items: center; margin: 0;">
            <input type="text" name="target_email" 
                   style="height: 31px; padding: 4px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; width: 200px;" 
                   value="<?= htmlspecialchars($invoice['customer_email'] ?? '') ?>" 
                   placeholder="Emails (comma separated)" title="Use comma to separate multiple emails" required>
            <button type="submit" name="send_email_btn" class="btn" style="background: #059669; color: #fff; border-color: #059669;">📧 Send Email</button>
        </form>

        <a href="shippers_declaration_entry.php?invoice_id=<?= $id ?>" 
           target="_blank" 
           class="btn" 
           style="background: #8b5cf6; color: #ffffff; border-color: #8b5cf6; font-weight: 600;">
           📜 Shipper's Declaration
        </a>

        <button onclick="window.print()" class="btn btn-primary">🖨 Print <?= $is_proforma ? 'Proforma' : 'Invoice' ?></button>
    </div>
</div>

<div class="wrapper">

    <!-- HEADER SECTION -->
    <div class="header-grid">
        <div class="org-brand">
            <h1 class="org-title"><?= strtoupper($org_master['org_name']) ?></h1>
            <div style="color: var(--text-muted); font-size: 11px; line-height: 1.3;">
                <?= $org['address'] ?><br>
                <?php if($gst_enabled == "Yes"): ?>
                    <b>GSTIN:</b> <?= $org['gst_no'] ?> | 
                <?php endif; ?>
                <b>Phone:</b> <?= $org['phone'] ?>
            </div>
        </div>

        <div class="invoice-badge-box">
            <div class="invoice-title"><?= $title_text ?></div>
            <table class="meta-info-table">
                <tr>
                    <td class="meta-label"><?= $is_proforma ? 'Proforma No:' : 'Invoice No:' ?></td>
                    <td class="meta-value"><?= $invoice['invoice_no'] ?></td>
                </tr>
                <tr>
                    <td class="meta-label">Date:</td>
                    <td class="meta-value"><?= date("d/M/Y", strtotime($invoice['invoice_date'])) ?></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- CUSTOMER DETAILS -->
    <div class="info-card">
        <div class="card-title">Billed To</div>
        <div class="customer-name"><?= strtoupper($invoice['customer_name']) ?></div>
        <div style="color: var(--text-main); font-size: 11px; line-height: 1.3;">
            <?= $invoice['address'] ?><br>
            <?php if($gst_enabled == "Yes"): ?>
                <b>GSTIN:</b> <?= $invoice['gst_no'] ?> | 
            <?php endif; ?>
            <?php if(!empty($invoice['contact_no'])): ?>
                <b>Phone:</b> <?= $invoice['contact_no'] ?>
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
                <td class="right bold"><?= number_format($invoice['net_total'],2) ?></td>
            </tr>

            <tr class="summary-row">
                <td colspan="<?= ($gst_enabled == 'Yes') ? '8' : '7' ?>" class="right">Advance / Paid</td>
                <td class="right"><?= number_format($invoice['paid_amount'] ?? $invoice['advance_paid'] ?? 0,2) ?></td>
            </tr>

            <tr class="summary-row">
                <td colspan="<?= ($gst_enabled == 'Yes') ? '8' : '7' ?>" class="right bold" style="color: var(--primary-dark);">Balance Due</td>
                <td class="right bold" style="color: var(--primary-dark);">
                    <?= number_format($invoice['due_amount'] ?? ($invoice['net_total'] - ($invoice['paid_amount'] ?? 0)),2) ?>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- PAYMENT DETAILS -->
    <?php if(!empty($payment_records)): ?>
    <div class="utr-box">
        <div class="utr-title">Payment Collection Details</div>
        <table style="width:100%; border-collapse:collapse; font-size:11px;">
            <thead>
                <tr style="border-bottom:1px solid #bbf7d0; color:#15803d; font-weight:bold;">
                    <td align="left" style="padding:2px 0;">Payment Date</td>
                    <td align="left" style="padding:2px 0;">Mode</td>
                    <td align="left" style="padding:2px 0;">UTR / Ref Number</td>
                    <td align="right" style="padding:2px 0;">Amount Paid</td>
                </tr>
            </thead>
            <tbody>
                <?php foreach($payment_records as $pr): 
                    $p_date = !empty($pr['payment_date']) ? date("d/M/Y", strtotime($pr['payment_date'])) : 'N/A';
                ?>
                <tr>
                    <td style="padding:2px 0; color:#15803d; font-weight:500;"><?= $p_date; ?></td>
                    <td style="padding:2px 0;"><b><?= strtoupper($pr['payment_mode']); ?></b></td>
                    <td style="padding:2px 0;"><?= !empty($pr['reference_no']) ? htmlspecialchars($pr['reference_no']) : 'N/A'; ?></td>
                    <td align="right" style="padding:2px 0; font-weight:bold; color:#166534;">₹ <?= number_format($pr['amount'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- AMOUNT IN WORDS -->
    <div class="amount-words-box">
        <span class="bold" style="color: var(--text-muted);">Amount in Words:</span> 
        <span class="bold"><?= numberToWords($invoice['net_total']) ?> Only</span>
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

   <!-- FOOTER INFO -->
<div class="footer-grid">
    <div class="footer-card">
        <div class="footer-card-title">Bank Details</div>
        <?php if($bank){ ?>
            <div style="font-size: 11px; line-height: 1.4;">
                <b>Bank:</b> <?= htmlspecialchars($bank['bank_name'] ?? '') ?><br>
                <?php if(!empty($bank['branch'])): ?>
                    <b>Branch:</b> <?= htmlspecialchars($bank['branch']) ?><br>
                <?php endif; ?>
                <b>A/C Name:</b> <?= htmlspecialchars($bank['account_name'] ?? '') ?><br>
                <b>A/C No:</b> <?= htmlspecialchars($bank['account_number'] ?? '') ?><br>
                <b>IFSC Code:</b> <?= htmlspecialchars($bank['ifsc_code'] ?? '') ?>
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
                Computer-generated document. No physical signature required.
            </div>
        </div>
    </div>

    <!-- TERMS & CONDITIONS -->
    <?php if(!empty(trim($invoice['terms_conditions']))): ?>
    <div class="footer-card" style="margin-top: 10px;">
        <div class="footer-card-title">Terms & Conditions</div>
        <div class="terms-box"><?= trim($invoice['terms_conditions']); ?></div>
    </div>
    <?php endif; ?>

</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // 3 seconds baad alert gayab ho jayega automatically
    setTimeout(function() {
        var alertBox = document.getElementById("customToastAlert");
        if (alertBox) {
            alertBox.style.transition = "opacity 0.4s ease, transform 0.4s ease";
            alertBox.style.opacity = "0";
            alertBox.style.transform = "translateY(-10px)";
            setTimeout(function() {
                alertBox.remove();
            }, 400);
        }
    }, 3000); 
});
</script>

</body>
</html>
