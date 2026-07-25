<?php
require_once('includes/load.php');

$bill = isset($_GET['bill']) ? $db->escape($_GET['bill']) : '';

if(empty($bill)){
    die("Invalid GRN Number");
}

/* ================= GRN HEADER ================= */

$grn_data = find_by_sql("
SELECT
    tm.*,
    sm.supplier_name,
    sm.address,
    sm.phone,
    sm.email,
    sm.contact_person,
    sm.gst_no

FROM transaction_master tm

LEFT JOIN supplier_master sm
ON sm.id = tm.supplier_id

WHERE tm.bill_indent_no = '$bill'

LIMIT 1
");

if(!$grn_data){
    die("GRN Not Found");
}

$grn = $grn_data[0];


/* ================= ORGANIZATION ================= */

$org_master = find_by_sql("
SELECT org_name
FROM master_inventory.master_organization
WHERE org_id = '{$grn['organization_id']}'
LIMIT 1
");

$org_master = $org_master
? $org_master[0]
: ['org_name' => ''];


$org_data = find_by_sql("
SELECT *
FROM organization_master
WHERE id = '{$grn['organization_id']}'
LIMIT 1
");

$org = $org_data
? $org_data[0]
: [];

/* ================= ITEMS ================= */

$items = find_by_sql("
SELECT
    tm.*,
    p.name,
    p.hsn_code,
    gm.gst_name,
    gm.gst_percent

FROM transaction_master tm

LEFT JOIN products p
ON p.id = tm.product_id

LEFT JOIN gst_master gm
ON gm.id = tm.gst_id

WHERE tm.bill_indent_no = '$bill'

ORDER BY tm.transaction_id
");

/* ================= SHIPPING ================= */

$shipping = find_by_sql("
SELECT
    s.*,
    stm.type_name,
    gm.gst_name

FROM shipping s

LEFT JOIN shipping_type_master stm
ON stm.id = s.shipping_type_id

LEFT JOIN gst_master gm
ON gm.id = s.gst_id

WHERE s.bill_no = '$bill'
");


/* ================= PAYMENTS ================= */

$payments = [];

/* ================= LEDGER ================= */

$ledger = find_by_sql("
SELECT *
FROM supplier_ledger
WHERE bill_no = '$bill'
LIMIT 1
");

$ledger = $ledger ? $ledger[0] : [];


/* ================= TOTALS ================= */

$item_total = 0;
$gst_total  = 0;

foreach($items as $it){
    $item_total += $it['net_price'];
    $gst_total  += $it['gst_amount'];
}

$shipping_total = 0;

foreach($shipping as $s){
    $shipping_total += $s['total_amount'];
}

$grand_total = $item_total + $shipping_total;


/* ================= NUMBER TO WORDS ================= */

function numberToWords($num){
    $ones = [
        0=>"Zero",1=>"One",2=>"Two",3=>"Three",
        4=>"Four",5=>"Five",6=>"Six",
        7=>"Seven",8=>"Eight",9=>"Nine",
        10=>"Ten",11=>"Eleven",12=>"Twelve",
        13=>"Thirteen",14=>"Fourteen",
        15=>"Fifteen",16=>"Sixteen",
        17=>"Seventeen",18=>"Eighteen",
        19=>"Nineteen"
    ];

    $tens = [
        2=>"Twenty",
        3=>"Thirty",
        4=>"Forty",
        5=>"Fifty",
        6=>"Sixty",
        7=>"Seventy",
        8=>"Eighty",
        9=>"Ninety"
    ];

    if($num < 20){
        return $ones[$num];
    }

    if($num < 100){
        return $tens[intval($num/10)]
            ." ".
            ($ones[$num%10] ?? '');
    }

    if($num < 1000){
        return $ones[intval($num/100)]
            ." Hundred ".
            numberToWords($num%100);
    }

    if($num < 100000){
        return numberToWords(intval($num/1000))
            ." Thousand ".
            numberToWords($num%1000);
    }

    if($num < 10000000){
        return numberToWords(intval($num/100000))
            ." Lakh ".
            numberToWords($num%100000);
    }

    return numberToWords(intval($num/10000000))
        ." Crore ".
        numberToWords($num%10000000);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>GRN_<?= htmlspecialchars($grn['bill_indent_no']); ?></title>
<style>
/* ===== GLOBAL RESET & VARIABLES ===== */
:root {
  --primary-color: #2563eb;
  --primary-dark: #1d4ed8;
  --primary-light: #eff6ff;
  --text-main: #1e293b;
  --text-muted: #64748b;
  --border-color: #cbd5e1;
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
  border-color: #94a3b8;
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

/* ===== MAIN INVOICE WRAPPER ===== */
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

.grn-badge-box {
  text-align: right;
}

.grn-title {
  font-size: 18px;
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

/* ===== SUPPLIER CARD ===== */
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

.supplier-name {
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
  border: 1px solid var(--border-color);
}

table.data-table td {
  padding: 6px 8px;
  border: 1px solid var(--border-color);
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
  padding: 5px 8px;
  background: #ffffff;
}

/* Totals Grid */
.totals-grid {
  display: grid;
  grid-template-columns: 1.6fr 1fr;
  gap: 12px;
  margin-top: 8px;
  margin-bottom: 10px;
}

.amount-words-box {
  background: #f8fafc;
  border: 1px dashed var(--border-color);
  border-radius: 5px;
  padding: 8px 12px;
  font-size: 11px;
}

.totals-summary-box {
  border: 1px solid var(--border-color);
  border-radius: 5px;
  padding: 6px 10px;
  background: #ffffff;
}

.totals-summary-table {
  width: 100%;
  border-collapse: collapse;
}

.totals-summary-table td {
  padding: 3px 0;
  font-size: 11px;
}

.section-label {
  font-size: 10px;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
  margin-bottom: 4px;
}

.footer-card {
  border: 1px solid var(--border-color);
  border-radius: 6px;
  padding: 8px 10px;
  margin-top: 10px;
}

/* ===== PRINT STYLES ===== */
@page {
  size: A4;
  margin: 6mm 8mm;
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

  .info-card {
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

<!-- PRINT / NAVIGATION BUTTONS -->
<div class="no-print-bar">
    <div>
        <a href="manage_grn.php" class="btn">← Back to Manage GRN</a>
    </div>
    <button onclick="window.print()" class="btn btn-primary">🖨 Print GRN</button>
</div>

<div class="wrapper">

    <!-- HEADER SECTION -->
    <div class="header-grid">
        <div class="org-brand">
            <h1 class="org-title"><?= strtoupper($org_master['org_name']); ?></h1>
            <div style="color: var(--text-muted); font-size: 11px; line-height: 1.3;">
                <?= $org['address'] ?? ''; ?><br>
                <?php if(!empty($org['gst_no'])){ ?>
                    <b>GSTIN:</b> <?= $org['gst_no']; ?> | 
                <?php } ?>
                <?php if(!empty($org['phone'])){ ?>
                    <b>Phone:</b> <?= $org['phone']; ?>
                <?php } ?>
                <?php if(!empty($org['email'])){ ?>
                    | <b>Email:</b> <?= $org['email']; ?>
                <?php } ?>
            </div>
        </div>

        <div class="grn-badge-box">
            <div class="grn-title">Goods Receipt Note</div>
            <table class="meta-info-table">
                <tr>
                    <td class="meta-label">GRN No:</td>
                    <td class="meta-value"><?= $grn['bill_indent_no']; ?></td>
                </tr>
                <tr>
                    <td class="meta-label">Date:</td>
                    <td class="meta-value"><?= date('d-m-Y', strtotime($grn['bill_indent_date'])); ?></td>
                </tr>
                <tr>
                    <td class="meta-label">Payment Mode:</td>
                    <td class="meta-value"><?= !empty($grn['payment_mode']) ? $grn['payment_mode'] : 'N/A'; ?></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- SUPPLIER DETAILS -->
    <div class="info-card">
        <div class="card-title">Supplier Details</div>
        <div class="supplier-name"><?= strtoupper($grn['supplier_name']); ?></div>
        <div style="color: var(--text-main); font-size: 11px; line-height: 1.3;">
            <?= $grn['address']; ?><br>
            <?php if(!empty($grn['gst_no'])){ ?>
                <b>GSTIN:</b> <?= $grn['gst_no']; ?> | 
            <?php } ?>
            <?php if(!empty($grn['phone'])){ ?>
                <b>Phone:</b> <?= $grn['phone']; ?>
            <?php } ?>
            <?php if(!empty($grn['email'])){ ?>
                | <b>Email:</b> <?= $grn['email']; ?>
            <?php } ?>
            <?php if(!empty($grn['contact_person'])){ ?>
                <br><b>Contact Person:</b> <?= $grn['contact_person']; ?>
            <?php } ?>
        </div>
    </div>

    <!-- ITEMS TABLE -->
    <table class="data-table">
        <thead>
            <tr>
                <th class="center" width="4%">#</th>
                <th width="32%">Product</th>
                <th class="center" width="10%">HSN</th>
                <th class="right" width="8%">Qty</th>
                <th class="right" width="8%">Free</th>
                <th class="right" width="11%">Rate</th>
                <th class="center" width="7%">GST%</th>
                <th class="right" width="10%">GST Amt</th>
                <th class="right" width="10%">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $i = 1;
            foreach($items as $it):
            ?>
            <tr>
                <td class="center"><?= $i++; ?></td>
                <td class="bold"><?= $it['name']; ?></td>
                <td class="center"><?= $it['hsn_code']; ?></td>
                <td class="right"><?= $it['quantity']; ?></td>
                <td class="right"><?= $it['free_qty']; ?></td>
                <td class="right"><?= number_format($it['unit_price'], 2); ?></td>
                <td class="center"><?= $it['gst_percent']; ?>%</td>
                <td class="right"><?= number_format($it['gst_amount'], 2); ?></td>
                <td class="right bold"><?= number_format($it['net_price'], 2); ?></td>
            </tr>
            <?php endforeach; ?>

            <tr class="summary-row">
                <td colspan="8" class="right bold">Items Total</td>
                <td class="right bold"><?= number_format($item_total, 2); ?></td>
            </tr>
        </tbody>
    </table>

    <!-- SHIPPING / ADDITIONAL CHARGES -->
    <?php if(!empty($shipping)){ ?>
    <div style="margin-top: 8px;">
        <div class="section-label">Shipping / Additional Charges</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="center" width="5%">#</th>
                    <th width="35%">Type</th>
                    <th class="right" width="15%">Amount</th>
                    <th class="center" width="10%">GST</th>
                    <th class="right" width="15%">GST Amt</th>
                    <th class="right" width="20%">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sno = 1;
                foreach($shipping as $s):
                ?>
                <tr>
                    <td class="center"><?= $sno++; ?></td>
                    <td><?= $s['type_name']; ?></td>
                    <td class="right"><?= number_format($s['amount'], 2); ?></td>
                    <td class="center"><?= $s['gst_percent']; ?>%</td>
                    <td class="right"><?= number_format($s['gst_amount'], 2); ?></td>
                    <td class="right bold"><?= number_format($s['total_amount'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="summary-row">
                    <td colspan="5" class="right bold">Shipping Total</td>
                    <td class="right bold"><?= number_format($shipping_total, 2); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php } ?>

    <!-- PAYMENTS TABLE -->
    <?php if(!empty($payments)){ ?>
    <div style="margin-top: 8px;">
        <div class="section-label">Payment Details</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="center">Date</th>
                    <th>Mode</th>
                    <th>Reference</th>
                    <th class="right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($payments as $p): ?>
                <tr>
                    <td class="center"><?= date('d-m-Y', strtotime($p['payment_date'])); ?></td>
                    <td><?= $p['payment_mode']; ?></td>
                    <td><?= $p['reference_no']; ?></td>
                    <td class="right bold"><?= number_format($p['payment_amount'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php } ?>

    <!-- TOTALS & WORDS GRID -->
    <div class="totals-grid">
        <div class="amount-words-box">
            <span class="bold" style="color: var(--text-muted);">Amount in Words:</span><br>
            <span class="bold" style="font-size: 12px; color: var(--text-main);"><?= numberToWords(round($grand_total)); ?> Only</span>
        </div>

        <div class="totals-summary-box">
            <table class="totals-summary-table">
                <tr>
                    <td>Items Total:</td>
                    <td class="right bold"><?= number_format($item_total, 2); ?></td>
                </tr>
                <tr>
                    <td>Shipping:</td>
                    <td class="right bold"><?= number_format($shipping_total, 2); ?></td>
                </tr>
                <tr style="border-top: 1px solid var(--border-color); font-size: 12px;">
                    <td class="bold" style="color: var(--primary-dark); padding-top: 4px;">Grand Total:</td>
                    <td class="right bold" style="color: var(--primary-dark); padding-top: 4px;">
                        <?= number_format($grand_total, 2); ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- COMMENTS SECTION -->
    <?php if(!empty($grn['comments'])){ ?>
    <div class="footer-card">
        <div class="section-label" style="border-bottom: 1px solid var(--border-color); padding-bottom: 2px;">Comments</div>
        <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
            <?= nl2br(htmlspecialchars($grn['comments'])); ?>
        </div>
    </div>
    <?php } ?>

</div>

</body>
</html>
