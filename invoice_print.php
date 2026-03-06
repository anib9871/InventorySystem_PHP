<?php
require_once('includes/load.php');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($id <= 0) die("Invalid Invoice ID");

/* Fetch Invoice */
$invoice_data = find_by_sql("
SELECT i.*, c.customer_name, c.address, c.gst_no, c.contact_no
FROM invoice i
LEFT JOIN customer_master c ON c.id = i.customer_id
WHERE i.id = $id
");

if(!$invoice_data) die("Invoice not found");
$invoice = $invoice_data[0];

/* Organization */
$org = find_by_sql("SELECT * FROM organization_master WHERE id=".$invoice['organization_id'])[0];

/* ================= TAX MODE DETECT ================= */

$org_state  = substr($org['gst_no'], 0, 2);
$cust_state = substr($invoice['gst_no'], 0, 2);

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
<html>
<head>
<title>Invoice</title>
<style>
/* ===== RESET ===== */
*{
  box-sizing:border-box;
}

body{
  font-family: Arial, Helvetica, sans-serif;
  font-size:13px;
  margin:0;
  padding:0;
  background:#f2f2f2;
  color:#000;
}

/* ===== PRINT BUTTON ===== */
.no-print{
  max-width:900px;
  margin:20px auto 0;
  text-align:right;
}

.no-print button{
  padding:8px 18px;
  font-size:13px;
  cursor:pointer;
  border:1px solid #000;
  background:#fff;
}

/* ===== MAIN WRAPPER ===== */
.wrapper{
  width:100%;
  max-width:900px;      /* A4 safe width */
  margin:15px auto;
  background:#fff;
  border:2px solid #000;
  padding:20px;
}

/* ===== SECTION BOXES ===== */
.section{
  border:1px solid #000;
  padding:10px;
}

/* ===== TABLES ===== */
table{
  width:100%;
  border-collapse:collapse;
  margin-top:10px;
}

th, td{
  border:1px solid #000;
  padding:7px;
  vertical-align:top;
}

th{
  background:#eaeaea;
  font-weight:600;
  text-align:center;
}

.right{
  text-align:right;
}

.center{
  text-align:center;
}

/* ===== HEADINGS ===== */
h1, h2, h3{
  margin:0 0 5px 0;
}

/* ===== TOTAL ROWS ===== */
.total-row td{
  font-weight:600;
}

/* ===== A4 PRINT SETTINGS ===== */
@page{
  size:A4;
  margin:15mm;
}

@media print{

  body{
    background:#fff;
  }

  .wrapper{
    border:none;
    margin:0;
    max-width:100%;
    padding:0;
  }

  .no-print{
    display:none;
  }

  th{
    background:#f5f5f5 !important;
  }

}
</style>
</head>

<body>

<div class="no-print" style="width:900px;margin:auto;">
    <button onclick="window.print()" 
        style="padding:6px 15px;cursor:pointer;">
        🖨 Print Invoice
    </button>
</div>

<div class="wrapper">

<!-- HEADER -->
<table>
<tr>
<td width="55%" class="section">
<b style="font-size:16px;"><?= strtoupper($org['org_name']) ?></b><br>
<?= $org['address'] ?><br>
GSTIN: <?= $org['gst_no'] ?><br>
Phone: <?= $org['phone'] ?>
</td>

<td width="45%" class="section">
<b>PROFORMA INVOICE</b><br><br>
Invoice No: <?= $invoice['invoice_no'] ?><br>
Date: <?= date("d-m-Y", strtotime($invoice['invoice_date'])) ?><br>
GST Type: <?= ucfirst($invoice['gst_type']) ?>
</td>
</tr>
</table>

<br>

<!-- BILL TO -->
<table>
<tr>
<td width="50%" class="section">
<b>Order From</b><br><br>
<?= $invoice['customer_name'] ?><br>
<?= $invoice['address'] ?><br>
GSTIN: <?= $invoice['gst_no'] ?><br>
Phone: <?= $invoice['contact_no'] ?>
</td>

<td width="50%" class="section">
<b>Ship To</b><br><br>
<?= $invoice['address'] ?>
</td>
</tr>
</table>

<br>

<!-- ITEMS TABLE -->
<table>
<tr>
<th>#</th>
<th>Item Name</th>
<th>HSN/SAC</th>
<th>Qty</th>
<th>Unit</th>
<th>Price/Unit</th>
<th>Discount</th>
<th>GST %</th>
<th>Amount</th>
</tr>

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
<td><?= $it['name'] ?></td>
<td><?= $it['hsn_code'] ?></td>
<td class="right"><?= $it['qty'] ?></td>
<td class="center">Nos</td>
<td class="right"><?= number_format($it['rate_excl_gst'],2) ?></td>
<td class="right"><?= number_format($it['discount_amount'],2) ?></td>
<td class="right"><?= $it['gst_percent'] ?>%</td>
<td class="right"><?= number_format($it['line_total'],2) ?></td>
</tr>

<?php } ?>

<!-- TOTAL ROWS -->
<tr>
<td colspan="8" align="right"><b>Sub Total</b></td>
<td class="right"><?= number_format($invoice['subtotal'],2) ?></td>
</tr>

<?php if($tax_mode == 'IGST'){ ?>
<tr>
<td colspan="8" align="right"><b>Total IGST</b></td>
<td class="right"><?= number_format($total_igst,2) ?></td>
</tr>
<?php } else { ?>
<tr>
<td colspan="8" align="right"><b>Total CGST</b></td>
<td class="right"><?= number_format($total_cgst,2) ?></td>
</tr>
<tr>
<td colspan="8" align="right"><b>Total SGST</b></td>
<td class="right"><?= number_format($total_sgst,2) ?></td>
</tr>
<?php } ?>

<tr>
<td colspan="8" align="right"><b>Total</b></td>
<td class="right"><b><?= number_format($invoice['net_total'],2) ?></b></td>
</tr>

<tr>
<td colspan="8" align="right"><b>Advance</b></td>
<td class="right"><?= number_format($invoice['advance_paid'] ?? 0,2) ?></td>
</tr>

<tr>
<td colspan="8" align="right"><b>Balance</b></td>
<td class="right">
<b><?= number_format($invoice['net_total'] - ($invoice['advance_paid'] ?? 0),2) ?></b>
</td>
</tr>

<tr>
<td colspan="9">
<b>Amount in Words:</b>
<?= numberToWords(round($invoice['net_total'])) ?> Only
</td>
</tr>

</table>

<br>

<!-- GST SUMMARY -->
<!-- ================= GST SUMMARY ================= -->

<table>
<tr>
<th rowspan="2">HSN / SAC</th>
<th rowspan="2">Taxable Amount</th>
<th rowspan="2">Rate</th>

<?php if($tax_mode == 'IGST'){ ?>
    <th colspan="2">IGST</th>
<?php } else { ?>
    <th colspan="2">CGST</th>
    <th colspan="2">SGST</th>
<?php } ?>

<th rowspan="2">Total Tax</th>
</tr>

<tr>
<?php if($tax_mode == 'IGST'){ ?>
    <th>Rate</th>
    <th>Amount</th>
<?php } else { ?>
    <th>Rate</th>
    <th>Amount</th>
    <th>Rate</th>
    <th>Amount</th>
<?php } ?>
</tr>

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
<td class="center"><?= $hsn ?></td>
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

<td class="right"><?= number_format($total_tax,2) ?></td>
</tr>

<?php endforeach; ?>

<tr>
<td class="center"><b>Total</b></td>
<td class="right"><b><?= number_format($grand_taxable,2) ?></b></td>
<td></td>

<?php if($tax_mode == 'IGST'){ ?>
    <td></td>
    <td class="right"><b><?= number_format($grand_igst,2) ?></b></td>
<?php } else { ?>
    <td></td>
    <td class="right"><b><?= number_format($grand_cgst,2) ?></b></td>
    <td></td>
    <td class="right"><b><?= number_format($grand_sgst,2) ?></b></td>
<?php } ?>

<td class="right">
<b><?= number_format($grand_cgst + $grand_sgst + $grand_igst,2) ?></b>
</td>
</tr>

</table>

<br>

<!-- BANK + TERMS + SIGN -->
<table>
<tr>

<td width="33%" class="section">
<b>Bank Details</b><br><br>
<?php if($bank){ ?>
Bank: <?= $bank['bank_name'] ?><br>
A/C Name: <?= $bank['account_name'] ?><br>
A/C No: <?= $bank['account_number'] ?><br>
IFSC: <?= $bank['ifsc_code'] ?><br>
<?php } ?>
</td>

<td width="33%" class="section">
<b>Terms & Conditions</b><br><br>
1. 50% Advance.<br>
2. Delay attracts 2% per week.<br>
3. Goods once sold will not be returned.
</td>

<td width="33%" class="section center">
For: <?= $org['org_name'] ?><br><br><br><br>
Authorized Signatory
</td>

</tr>
</table>

</div>
</body>
</html>