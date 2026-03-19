<?php
require_once('includes/load.php');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($id <= 0) die("Invalid Quotation ID");

/* Fetch Quotation */
$quotation_data = find_by_sql("
SELECT q.*, c.customer_name, c.address, c.gst_no, c.contact_no
FROM quotation_master q
LEFT JOIN customer_master c ON c.id = q.customer_id
WHERE q.id = $id
");

if(!$quotation_data) die("Quotation not found");
$quotation = $quotation_data[0];

/* Organization */
$org = find_by_sql("SELECT * FROM organization_master WHERE id=".$quotation['organization_id'])[0];

/* TAX MODE */
$org_state  = substr($org['gst_no'], 0, 2);
$cust_state = substr($quotation['gst_no'], 0, 2);
$tax_mode = ($org_state == $cust_state) ? 'CGST_SGST' : 'IGST';

/* Bank */
$bank_data = find_by_sql("SELECT * FROM bank_master WHERE organization_id=".$quotation['organization_id']);
$bank = $bank_data ? $bank_data[0] : null;

/* Items */
$items = find_by_sql("
SELECT qi.*, p.name , p.hsn_code
FROM quotation_items qi
LEFT JOIN products p ON p.id = qi.product_id
WHERE qi.quotation_id = $id
");

/* Number to Words */
function numberToWords($num){
    $ones = [0=>"",1=>"One",2=>"Two",3=>"Three",4=>"Four",5=>"Five",
        6=>"Six",7=>"Seven",8=>"Eight",9=>"Nine",10=>"Ten",
        11=>"Eleven",12=>"Twelve",13=>"Thirteen",14=>"Fourteen",
        15=>"Fifteen",16=>"Sixteen",17=>"Seventeen",
        18=>"Eighteen",19=>"Nineteen"];
    $tens = [0=>"",2=>"Twenty",3=>"Thirty",4=>"Forty",
        5=>"Fifty",6=>"Sixty",7=>"Seventy",8=>"Eighty",9=>"Ninety"];

    if($num==0) return "Zero";
    if($num<20) return $ones[$num];
    if($num<100) return $tens[intval($num/10)]." ".$ones[$num%10];
    if($num<1000) return $ones[intval($num/100)]." Hundred ".numberToWords($num%100);
    if($num<100000) return numberToWords(intval($num/1000))." Thousand ".numberToWords($num%1000);
    if($num<10000000) return numberToWords(intval($num/100000))." Lakh ".numberToWords($num%100000);
    return numberToWords(intval($num/10000000))." Crore ".numberToWords($num%10000000);
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Quotation</title>
<style>
*{box-sizing:border-box;}
body{font-family:Arial;font-size:13px;background:#f2f2f2;margin:0;padding:0;}
.no-print{max-width:900px;margin:20px auto 0;text-align:right;}
.wrapper{max-width:900px;margin:15px auto;background:#fff;border:2px solid #000;padding:20px;}
.section{border:1px solid #000;padding:10px;}
table{width:100%;border-collapse:collapse;margin-top:10px;}
th,td{border:1px solid #000;padding:7px;}
th{background:#eaeaea;text-align:center;}
.right{text-align:right;}
.center{text-align:center;}
@page{size:A4;margin:15mm;}
@media print{
body{background:#fff;}
.wrapper{border:none;margin:0;padding:0;}
.no-print{display:none;}
}
</style>
</head>
<body>

<div class="no-print">
<button onclick="window.print()">🖨 Print Quotation</button>
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
<b>PROFORMA QUOTATION</b><br><br>
Quotation No: <?= $quotation['quotation_no'] ?><br>
Date: <?= date("d-m-Y", strtotime($quotation['quotation_date'])) ?><br>
GST Type: <?= ucfirst($quotation['gst_type']) ?>
</td>
</tr>
</table>

<br>

<!-- CUSTOMER -->
<table>
<tr>
<td width="50%" class="section">
<b>Order From</b><br><br>
<?= $quotation['customer_name'] ?><br>
<?= $quotation['address'] ?><br>
GSTIN: <?= $quotation['gst_no'] ?><br>
Phone: <?= $quotation['contact_no'] ?>
</td>

<td width="50%" class="section">
<b>Ship To</b><br><br>
<?= $quotation['address'] ?>
</td>
</tr>
</table>

<br>

<!-- ITEMS -->
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
$i=1;
$total_cgst=0;$total_sgst=0;$total_igst=0;

foreach($items as $it):
$taxable = ($it['qty']*$it['rate_excl_gst']) - $it['discount_amount'];
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
<?php endforeach; ?>

<tr>
<td colspan="8" align="right"><b>Sub Total</b></td>
<td class="right"><?= number_format($quotation['subtotal'],2) ?></td>
</tr>

<?php if($tax_mode=='IGST'): ?>
<tr>
<td colspan="8" align="right"><b>Total IGST</b></td>
<td class="right"><?= number_format($total_igst,2) ?></td>
</tr>
<?php else: ?>
<tr>
<td colspan="8" align="right"><b>Total CGST</b></td>
<td class="right"><?= number_format($total_cgst,2) ?></td>
</tr>
<tr>
<td colspan="8" align="right"><b>Total SGST</b></td>
<td class="right"><?= number_format($total_sgst,2) ?></td>
</tr>
<?php endif; ?>

<tr>
<td colspan="8" align="right"><b>Total</b></td>
<td class="right"><b><?= number_format($quotation['net_total'],2) ?></b></td>
</tr>

<tr>
<td colspan="9">
<b>Amount in Words:</b>
<?= numberToWords(round($quotation['net_total'])) ?> Only
</td>
</tr>
</table>

<br>

<!-- BANK + TERMS -->
<table>
<tr>
<td width="33%" class="section">
<b>Bank Details</b><br><br>
<?php if($bank): ?>
Bank: <?= $bank['bank_name'] ?><br>
A/C Name: <?= $bank['account_name'] ?><br>
A/C No: <?= $bank['account_number'] ?><br>
IFSC: <?= $bank['ifsc_code'] ?><br>
<?php endif; ?>
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
