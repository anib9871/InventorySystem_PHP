<?php
$page_title = 'Ledger Report';
require_once('includes/load.php');

$type = isset($_GET['type']) ? $_GET['type'] : 'supplier';
$party_id = (isset($_GET['party_id']) && $_GET['party_id'] !== '') ? $_GET['party_id'] : 'all';

$from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-01');
$to   = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');

$formats = ['d/M/Y', 'd-m-Y', 'Y-m-d'];

foreach ($formats as $format) {
    $dt = DateTime::createFromFormat($format, $from);
    if ($dt instanceof DateTime) {
        $from = $dt->format('Y-m-d');
        break;
    }
}

foreach ($formats as $format) {
    $dt = DateTime::createFromFormat($format, $to);
    if ($dt instanceof DateTime) {
        $to = $dt->format('Y-m-d');
        break;
    }
}

$customers = find_all('customer_master');
$suppliers = find_all('supplier_master');

$rows = [];
$totalDebit = 0;
$totalCredit = 0;
$balance = 0;

if(!empty($party_id)){

if($type == 'supplier'){

    $where_party = ($party_id == 'all') 
        ? "WHERE bill_date BETWEEN '{$from}' AND '{$to}'" 
        : "WHERE supplier_id='{$party_id}' AND bill_date BETWEEN '{$from}' AND '{$to}'";

    $ledger = find_by_sql("
    SELECT
        ledger_id,
        bill_date,
        bill_no,
        bill_amount,
        paid_amount,
        balance_amount,
        entry_type
    FROM supplier_ledger
    {$where_party}
    ORDER BY bill_date ASC, ledger_id ASC
    ");

// supplier_ledger query ke baad loop me yeh condition lagayein:
    foreach($ledger as $l){

        // Advance Entry
        if($l['entry_type'] == 'ADVANCE'){

            // Agar advance pura adjust/clear ho chuka hai to skip karein
            if (isset($l['balance_amount']) && floatval($l['balance_amount']) <= 0) {
                continue; 
            }

            // Agar unadjusted advance bacha hai to sirf bacha hua amount dikhayein
            $adv_amount = (floatval($l['balance_amount']) > 0) ? $l['balance_amount'] : $l['paid_amount'];

            $rows[] = [
                'date'       => $l['bill_date'],
                'particular' => 'Advance (Unadjusted)',
                'type'       => 'Advance',
                'voucher'    => 'ADVANCE',
                'debit'      => $adv_amount,
                'credit'     => 0
            ];

        } else {
            // Purchase Entry & Payments...

// Purchase Entry (Round figure bill amount so it matches rounded payments)
            $bill_val = round((float)$l['bill_amount']);

            $rows[] = [
                'date'       => $l['bill_date'],
                'particular' => 'Purchase',
                'type'       => 'Purchase (GRN)',
                'voucher'    => $l['bill_no'],
                'debit'      => 0,
                'credit'     => $bill_val
            ];

            // Payments of this GRN
            $payments = find_by_sql("
            SELECT
                payment_id,
                payment_date,
                payment_amount
            FROM supplier_payment
            WHERE ledger_id='{$l['ledger_id']}'
            ORDER BY payment_date,payment_id
            ");

foreach($payments as $p){
    // Dynamic reference for Supplier GRN No.
    $ref_text = !empty($l['bill_no']) ? " (GRN: {$l['bill_no']})" : "";

    $rows[] = [
        'date'       => $p['payment_date'],
        'particular' => 'Payment' . $ref_text, // <-- Yahan update hua hai
        'type'       => 'Payment',
        'voucher'    => 'PAY-'.$p['payment_id'],
        'debit'      => $p['payment_amount'],
        'credit'     => 0
    ];
}

        }

    }

}
else{
    $where_sales = ($party_id == 'all') 
        ? "WHERE invoice_date BETWEEN '{$from}' AND '{$to}'" 
        : "WHERE customer_id='{$party_id}' AND invoice_date BETWEEN '{$from}' AND '{$to}'";

    $sales = find_by_sql("
    SELECT invoice_date txn_date,
           invoice_no,
           net_total
    FROM invoice
    {$where_sales}
    ");

    foreach($sales as $s){
        $rows[] = [
            'date'       => $s['txn_date'],
            'particular' => 'Sale',
            'type'       => 'Invoice',
            'voucher'    => $s['invoice_no'],
            'debit'      => $s['net_total'],
            'credit'     => 0
        ];
    }

$where_payments = ($party_id == 'all') 
        ? "WHERE DATE(p.payment_date) BETWEEN '{$from}' AND '{$to}'" 
        : "WHERE p.customer_id='{$party_id}' AND DATE(p.payment_date) BETWEEN '{$from}' AND '{$to}'";

    // LEFT JOIN add kiya hai invoice_no ke liye
    $payments = find_by_sql("
    SELECT p.payment_date txn_date,
           CONCAT('PAY-', p.id) voucher_no,
           p.amount,
           i.invoice_no
    FROM payments p
    LEFT JOIN invoice i ON p.invoice_id = i.id
    {$where_payments}
    ");

foreach($payments as $p){
    // Dynamic reference for Customer Invoice No.
    $ref_text = !empty($p['invoice_no']) ? " (INV: {$p['invoice_no']})" : "";

    $rows[] = [
        'date'       => $p['txn_date'],
        'particular' => 'Payment' . $ref_text, // <-- Yahan update hua hai
        'type'       => 'Payment',
        'voucher'    => $p['voucher_no'],
        'debit'      => 0,
        'credit'     => $p['amount']
    ];
}

}

}

usort($rows, function($a, $b){
    if($a['date'] == $b['date']){
        return strcmp($a['voucher'], $b['voucher']);
    }
    return strtotime($a['date']) <=> strtotime($b['date']);
});

include_once('layouts/header.php');
?>

<style>
/* --- SCREEN STYLES --- */
.panel-ledger {
    border: none;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    background: #fff;
    margin-bottom: 25px;
}
.panel-ledger .panel-heading {
    background: #1f2937 !important;
    color: #fff !important;
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
    padding: 14px 20px;
    font-size: 15px;
    font-weight: 700;
    letter-spacing: 0.5px;
}
.ledger-filter-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 16px;
    margin-bottom: 20px;
}
.ledger-filter-card label {
    font-size: 11px;
    text-transform: uppercase;
    color: #64748b;
    font-weight: 700;
    margin-bottom: 6px;
    display: block;
}
.ledger-filter-card .form-control {
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    height: 38px;
    box-shadow: none;
}
.filter-btn-group {
    display: flex;
    gap: 8px;
    align-items: flex-end;
}
.btn-ui {
    height: 38px;
    border-radius: 6px;
    font-weight: 600;
    font-size: 13px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 0 16px;
    transition: all 0.2s;
    border: none;
}
.btn-ui-primary { background-color: #3b82f6; color: #fff; }
.btn-ui-primary:hover { background-color: #2563eb; color: #fff; }
.btn-ui-success { background-color: #10b981; color: #fff; }
.btn-ui-success:hover { background-color: #059669; color: #fff; }

.custom-table {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
}
.custom-table thead th {
    background-color: #f1f5f9;
    color: #475569;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 11px;
    letter-spacing: 0.5px;
    padding: 12px;
    border-bottom: 2px solid #e2e8f0 !important;
}
.custom-table tbody tr:hover { background-color: #f8fafc; }
.custom-table td {
    padding: 11px 12px;
    vertical-align: middle !important;
    border-top: 1px solid #f1f5f9 !important;
    font-size: 13px;
    color: #1e293b;
}
.custom-table tfoot th {
    background-color: #f8fafc;
    padding: 14px 12px;
    font-size: 13px;
    border-top: 2px solid #cbd5e1 !important;
}
.badge-bal {
    font-size: 12px;
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: 700;
    display: inline-block;
}
.badge-cr { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.badge-dr { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
.badge-adv { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
.voucher-code {
    background: #f1f5f9;
    color: #ef4444;
    padding: 2px 6px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 12px;
    border: 1px solid #e2e8f0;
}

/* --- PRINT-ONLY OVERRIDES (Tax Invoice Theme) --- */
@media print {
    @page {
        size: A4 portrait;
        margin: 10mm;
    }

    /* Baki sab hide karega sirf printArea dikhayega */
    body * {
        visibility: hidden !important;
    }

    #printArea, #printArea * {
        visibility: visible !important;
    }

    #printArea {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        border-top: 4px solid #2563eb !important; /* Top Blue Bar */
        background: #fff !important;
    }

    .no-print, .ledger-filter-card, .panel-heading, form, nav, .sidebar, header, footer {
        display: none !important;
    }

    .panel-ledger, .panel-body {
        box-shadow: none !important;
        border: none !important;
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
    }

    #printHeader {
        display: block !important;
        margin-top: 15px;
        margin-bottom: 15px;
    }

    .print-header-top {
        display: table !important;
        width: 100% !important;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 12px;
        margin-bottom: 15px;
    }

    .print-org-col {
        display: table-cell !important;
        width: 60% !important;
        vertical-align: top;
    }

    .print-title-col {
        display: table-cell !important;
        width: 40% !important;
        vertical-align: top;
        text-align: right;
    }

    .org-title {
        font-size: 18px !important;
        font-weight: 800 !important;
        color: #0f172a !important;
        text-transform: uppercase;
    }

    .org-details {
        font-size: 10px !important;
        color: #64748b !important;
        margin-top: 4px;
        line-height: 1.4;
    }

    .report-title-badge {
        font-size: 18px !important;
        font-weight: 800 !important;
        color: #2563eb !important;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .report-period-text {
        font-size: 10px !important;
        color: #64748b !important;
        margin-top: 4px;
    }

    /* Light Blue Box (Invoice Style) */
    .party-info-card {
        background-color: #f0f7ff !important;
        border: 1px solid #bfdbfe !important;
        border-radius: 6px;
        padding: 8px 12px;
        margin-bottom: 15px;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .party-info-label {
        font-size: 9px !important;
        font-weight: 700 !important;
        color: #2563eb !important;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .party-info-name {
        font-size: 13px !important;
        font-weight: 700 !important;
        color: #1e293b !important;
        margin-top: 2px;
    }

    /* Invoice Table Styling */
    .custom-table {
        width: 100% !important;
        border-collapse: collapse !important;
        border: 1px solid #e2e8f0 !important;
    }

    .custom-table thead th {
        background-color: #f8fafc !important;
        color: #475569 !important;
        font-size: 9px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        padding: 7px 8px !important;
        border: 1px solid #e2e8f0 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .custom-table td {
        padding: 6px 8px !important;
        font-size: 10px !important;
        border: 1px solid #e2e8f0 !important;
        color: #1e293b !important;
    }

    .custom-table tfoot th {
        background-color: #eff6ff !important;
        color: #1e3a8a !important;
        border: 1px solid #bfdbfe !important;
        padding: 8px !important;
        font-size: 11px !important;
        font-weight: 800 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .voucher-code {
        background: transparent !important;
        border: none !important;
        color: #1e293b !important;
        font-weight: 600;
        padding: 0 !important;
    }

    .badge-bal {
        background: none !important;
        border: none !important;
        padding: 0 !important;
        font-size: 10px !important;
        font-weight: 700 !important;
    }
    .badge-cr { color: #dc2626 !important; }
    .badge-dr { color: #2563eb !important; }
    .badge-adv { color: #16a34a !important; }
}
</style>

<div id="printArea">

<?php
$org = find_by_sql("
SELECT *
FROM organization_master
WHERE id='{$_SESSION['org_id']}'
LIMIT 1
");

if($type == 'supplier'){
    if($party_id == 'all'){
        $party_name = "All Suppliers";
    } else {
        $party = find_by_sql("
        SELECT supplier_name
        FROM supplier_master
        WHERE id='{$party_id}'
        LIMIT 1
        ");
        $party_name = $party ? $party[0]['supplier_name'] : '';
    }

}else{
    if($party_id == 'all'){
        $party_name = "All Customers";
    } else {
        $party = find_by_sql("
        SELECT customer_name
        FROM customer_master
        WHERE id='{$party_id}'
        LIMIT 1
        ");
        $party_name = $party ? $party[0]['customer_name'] : '';
    }

}
?>

<!-- PRINT HEADER (Only shows when printing) -->
<div id="printHeader" style="display:none;">
    <div class="print-header-top">
        <div class="print-org-col">
            <div class="org-title"><?= $org[0]['org_name']; ?></div>
            <div class="org-details">
                <?= $org[0]['address']; ?><br>
                <strong>GSTIN:</strong> <?= $org[0]['gst_no']; ?>
            </div>
        </div>
        <div class="print-title-col">
            <div class="report-title-badge">LEDGER REPORT</div>
            <div class="report-period-text">
                <strong>Period:</strong> <?= date('d/M/Y', strtotime($from)); ?> To <?= date('d/M/Y', strtotime($to)); ?>
            </div>
        </div>
    </div>

    <!-- Billed To / Party Box -->
    <div class="party-info-card">
        <div class="party-info-label"><?= strtoupper($type); ?> DETAILS</div>
        <div class="party-info-name"><?= $party_name; ?></div>
    </div>
</div>

<div class="panel panel-default panel-ledger">
<div class="panel-heading no-print" id="screenHeader">
    <i class="fa fa-book"></i> LEDGER REPORT
</div>
<div class="panel-body">

<div class="ledger-filter-card no-print">
<form method="get" class="row">

<div class="col-md-2 col-sm-6">
<label>Ledger Type</label>
<select name="type" class="form-control" onchange="this.form.submit()">
<option value="supplier" <?= $type=='supplier'?'selected':''; ?>>Supplier</option>
<option value="customer" <?= $type=='customer'?'selected':''; ?>>Customer</option>
</select>
</div>

<div class="col-md-3 col-sm-6">
<label>Party</label>
<select name="party_id" class="form-control">
<option value="all" <?= ($party_id=='all')?'selected':''; ?>>All <?= ucfirst($type); ?>s</option>

<?php if($type=='supplier'): ?>
<?php foreach($suppliers as $s): ?>
<option value="<?= $s['id']; ?>" <?= $party_id==$s['id']?'selected':''; ?>>
<?= $s['supplier_name']; ?>
</option>
<?php endforeach; ?>
<?php else: ?>
<?php foreach($customers as $c): ?>
<option value="<?= $c['id']; ?>" <?= $party_id==$c['id']?'selected':''; ?>>
<?= $c['customer_name']; ?>
</option>
<?php endforeach; ?>
<?php endif; ?>

</select>
</div>

<div class="col-md-2 col-sm-6">
<label>From Date</label>
<input
type="text"
name="from"
class="form-control ledger-datepicker"
value="<?= date('d/M/Y', strtotime($from)); ?>"
autocomplete="off">
</div>

<div class="col-md-2 col-sm-6">
<label>To Date</label>
<input
type="text"
name="to"
class="form-control ledger-datepicker"
value="<?= date('d/M/Y', strtotime($to)); ?>"
autocomplete="off">
</div>

<div class="col-md-3 col-sm-12" style="margin-top: 22px;">
<div class="filter-btn-group">
    <button type="submit" class="btn-ui btn-ui-primary" style="flex: 1;">
        <i class="fa fa-filter"></i> Show Report
    </button>

    <button type="button" class="btn-ui btn-ui-success no-print" onclick="printLedger()" style="flex: 1;">
        <i class="fa fa-print"></i> Print / PDF
    </button>
</div>
</div>

</form>
</div>

<div class="table-responsive">
<table class="table custom-table">
<thead>
<tr>
<th width="12%">Date</th>
<th width="16%">Particular</th>
<th width="18%">Voucher Type</th>
<th width="14%">Voucher No</th>
<th width="13%" class="text-right">Debit (₹)</th>
<th width="13%" class="text-right">Credit (₹)</th>
<th width="14%" class="text-right">Balance</th>
</tr>
</thead>
<tbody>

<?php foreach($rows as $r):

$totalDebit += $r['debit'];
$totalCredit += $r['credit'];

if($type == 'supplier'){
    $balance += $r['credit'];
    $balance -= $r['debit'];
} else {
    $balance += $r['debit'];
    $balance -= $r['credit'];
}

// Agar minor round-off fark (0.50 paise) hai to balance ko 0 kar dein taaki aage carry na ho
if(abs(round($balance, 2)) <= 0.50){
    $balance = 0;
}
?>

<tr>
<td><?= date('d/M/Y', strtotime($r['date'])); ?></td>
<td><strong><?= $r['particular']; ?></strong></td>
<td><span class="text-muted"><?= $r['type']; ?></span></td>
<td><span class="voucher-code"><?= $r['voucher']; ?></span></td>
<td class="text-right"><?= $r['debit'] ? number_format($r['debit'], 2) : '-'; ?></td>
<td class="text-right"><?= $r['credit'] ? number_format($r['credit'], 2) : '-'; ?></td>
<td class="text-right">
<?php
if($balance == 0){
    echo "<span class='text-muted'>₹ 0.00</span>";
} else {
    if($type == 'supplier'){
        if($balance > 0){
            echo "<span class='badge-bal badge-cr'>₹ ".number_format($balance, 2)." Cr</span>";
        } else {
            echo "<span class='badge-bal badge-adv'>₹ ".number_format(abs($balance), 2)." Adv</span>";
        }
    } else {
        if($balance > 0){
            echo "<span class='badge-bal badge-dr'>₹ ".number_format($balance, 2)." Dr</span>";
        } else {
            echo "<span class='badge-bal badge-cr'>₹ ".number_format(abs($balance), 2)." Cr</span>";
        }
    }
}
?>
</td>
</tr>

<?php endforeach; ?>

</tbody>

<tfoot>
<tr>
<th colspan="4" class="text-right">Grand Total:</th>
<th class="text-right" style="color:#2563eb;"><?= number_format($totalDebit, 2); ?></th>
<th class="text-right" style="color:#dc2626;"><?= number_format($totalCredit, 2); ?></th>
<th class="text-right">
    ₹ <?= (abs(round($balance, 2)) <= 0.50) ? '0.00' : number_format(abs($balance), 2); ?>
</th>
</tr>
</tfoot>

</table>
</div>

</div>
</div>
</div>

<script>
function printLedger(){
    var originalTitle = document.title;
    
    // PHP se dynamically party name aur report name le lega
    var partyName = <?= json_encode(!empty($party_name) ? $party_name : ucfirst($type)); ?>;
    
    // PDF Save karte waqt name format: "[Party Name] - Ledger Report"
    document.title = partyName + " - Ledger Report";
    
    window.print();
    
    // Print window band hone ke baad wapas normal title kar dega
    setTimeout(function(){
        document.title = originalTitle;
    }, 1000);
}
</script>

<script>
flatpickr(".ledger-datepicker", {
    dateFormat: "d/M/Y",
    allowInput: false,
    disableMobile: true
});
</script>

<?php include_once('layouts/footer.php'); ?>
