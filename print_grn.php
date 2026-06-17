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
<html>
<head>

<title>
GRN_<?= $grn['bill_indent_no']; ?>
</title>

<style>

*{
    box-sizing:border-box;
}

body{
    font-family:Arial, Helvetica, sans-serif;
    font-size:13px;
    margin:0;
    padding:0;
    background:#f2f2f2;
}

.wrapper{
    width:100%;
    max-width:900px;
    margin:15px auto;
    background:#fff;
    border:2px solid #000;
    padding:20px;
}

table{
    width:100%;
    border-collapse:collapse;
}

th,td{
    border:1px solid #000;
    padding:7px;
    vertical-align:top;
}

th{
    background:#efefef;
    text-align:center;
}

.section{
    border:1px solid #000;
    padding:10px;
}

.center{
    text-align:center;
}

.right{
    text-align:right;
}

.no-print{
    width:900px;
    margin:10px auto;
    text-align:right;
}

.no-print button{
    padding:8px 18px;
    cursor:pointer;
}

@page{
    size:A4;
    margin:10mm;
}

@media print{

    body{
        background:#fff;
    }

    .no-print{
        display:none;
    }

    .wrapper{
        border:none;
        margin:0;
        padding:0;
        max-width:100%;
    }

}

</style>

</head>

<body>

<div class="no-print">

<button onclick="window.print()">
🖨 Print GRN
</button>

</div>

<div class="wrapper">

<!-- ================= HEADER ================= -->

<table>

<tr>

<td class="section center">

<h2 style="margin:0;">
GOODS RECEIPT NOTE
</h2>

<br>

<b>GRN No :</b>
<?= $grn['bill_indent_no']; ?>

&nbsp;&nbsp;&nbsp;

<b>Date :</b>
<?= date('d-m-Y', strtotime($grn['bill_indent_date'])); ?>

&nbsp;&nbsp;&nbsp;

<b>Payment Mode :</b>
<?= $grn['payment_mode']; ?>

</td>

</tr>

<tr>

<td class="section">

<b style="font-size:18px;">
<?= strtoupper($org_master['org_name']); ?>
</b>

<br>

<?= $org['address'] ?? ''; ?>

<?php if(!empty($org['gst_no'])){ ?>
<br>
GSTIN :
<?= $org['gst_no']; ?>
<?php } ?>

<?php if(!empty($org['phone'])){ ?>
<br>
Phone :
<?= $org['phone']; ?>
<?php } ?>

<?php if(!empty($org['email'])){ ?>
<br>
Email :
<?= $org['email']; ?>
<?php } ?>

</td>

</tr>

</table>

<br>

<!-- ================= SUPPLIER ================= -->

<table>

<tr>

<td class="section">

<b>Supplier Details</b>

<br><br>

<b>
<?= strtoupper(
$grn['supplier_name']
); ?>
</b>

<br>

<?= $grn['address']; ?>

<?php if(!empty($grn['gst_no'])){ ?>

<br>
GSTIN :
<?= $grn['gst_no']; ?>

<?php } ?>

<?php if(!empty($grn['phone'])){ ?>

<br>
Phone :
<?= $grn['phone']; ?>

<?php } ?>

<?php if(!empty($grn['email'])){ ?>

<br>
Email :
<?= $grn['email']; ?>

<?php } ?>

<?php if(!empty($grn['contact_person'])){ ?>

<br>
Contact Person :
<?= $grn['contact_person']; ?>

<?php } ?>

</td>

</tr>

</table>

<br>

<!-- ================= ITEMS ================= -->

<table>

<tr>

<th>#</th>
<th>Product</th>
<th>HSN</th>
<th>Qty</th>
<th>Free</th>
<th>Rate</th>
<th>GST%</th>
<th>GST Amt</th>
<th>Total</th>

</tr>

<?php
$i = 1;

foreach($items as $it):
?>

<tr>

<td class="center">
<?= $i++; ?>
</td>

<td>
<?= $it['name']; ?>
</td>

<td class="center">
<?= $it['hsn_code']; ?>
</td>

<td class="right">
<?= $it['quantity']; ?>
</td>

<td class="right">
<?= $it['free_qty']; ?>
</td>

<td class="right">
<?= number_format(
$it['unit_price'],
2
); ?>
</td>

<td class="center">
<?= $it['gst_percent']; ?>%
</td>

<td class="right">
<?= number_format(
$it['gst_amount'],
2
); ?>
</td>

<td class="right">
<?= number_format(
$it['net_price'],
2
); ?>
</td>

</tr>

<?php endforeach; ?>

<!-- ================= ITEM TOTAL ================= -->

<tr>
    <td colspan="8" align="right">
        <b>Items Total</b>
    </td>

    <td class="right">
        <b><?= number_format($item_total,2); ?></b>
    </td>
</tr>

</table>

<br>

<!-- ================= SHIPPING ================= -->

<?php if(!empty($shipping)){ ?>

<table>

<tr>
    <th colspan="6">
        Shipping / Additional Charges
    </th>
</tr>

<tr>
    <th>#</th>
    <th>Type</th>
    <th>Amount</th>
    <th>GST</th>
    <th>GST Amount</th>
    <th>Total</th>
</tr>

<?php
$sno = 1;

foreach($shipping as $s):
?>

<tr>

    <td class="center">
        <?= $sno++; ?>
    </td>

    <td>
        <?= $s['type_name']; ?>
    </td>

    <td class="right">
        <?= number_format($s['amount'],2); ?>
    </td>

    <td class="center">
        <?= $s['gst_percent']; ?>%
    </td>

    <td class="right">
        <?= number_format($s['gst_amount'],2); ?>
    </td>

    <td class="right">
        <?= number_format($s['total_amount'],2); ?>
    </td>

</tr>

<?php endforeach; ?>

<tr>
    <td colspan="5" align="right">
        <b>Shipping Total</b>
    </td>

    <td class="right">
        <b><?= number_format($shipping_total,2); ?></b>
    </td>
</tr>

</table>

<br>

<?php } ?>

<!-- ================= PAYMENTS ================= -->

<?php if(!empty($payments)){ ?>

<table>

<tr>
    <th colspan="4">
        Payment Details
    </th>
</tr>

<tr>
    <th>Date</th>
    <th>Mode</th>
    <th>Reference</th>
    <th>Amount</th>
</tr>

<?php foreach($payments as $p): ?>

<tr>

    <td class="center">
        <?= date(
            'd-m-Y',
            strtotime($p['payment_date'])
        ); ?>
    </td>

    <td>
        <?= $p['payment_mode']; ?>
    </td>

    <td>
        <?= $p['reference_no']; ?>
    </td>

    <td class="right">
        <?= number_format(
            $p['payment_amount'],
            2
        ); ?>
    </td>

</tr>

<?php endforeach; ?>

</table>

<br>

<?php } ?>

<!-- ================= TOTALS ================= -->

<table>

<tr>
    <td width="70%" class="section">

        <b>Amount in Words</b>

        <br><br>

        <?= numberToWords(
            round($grand_total)
        ); ?>

        Only

    </td>

    <td width="30%">

        <table>

        <tr>
            <td><b>Items Total</b></td>
            <td class="right">
                <?= number_format(
                    $item_total,
                    2
                ); ?>
            </td>
        </tr>

        <tr>
            <td><b>Shipping</b></td>
            <td class="right">
                <?= number_format(
                    $shipping_total,
                    2
                ); ?>
            </td>
        </tr>

        <tr>
            <td><b>Grand Total</b></td>
            <td class="right">
                <b>
                <?= number_format(
                    $grand_total,
                    2
                ); ?>
                </b>
            </td>
        </tr>

        </table>

    </td>

</tr>

</table>

<br>

<!-- ================= COMMENTS ================= -->

<?php if(!empty($grn['comments'])){ ?>

<table>

<tr>

<td class="section">

<b>Comments</b>

<br><br>

<?= nl2br(
    htmlspecialchars($grn['comments'])
); ?>

</td>

</tr>

</table>

<br>

<?php } ?>

</div>

<script>

window.onload = function(){

    window.print();

    window.onafterprint = function(){

        window.location.href =
        'manage_grn.php';

    };

};

</script>

</body>
</html>