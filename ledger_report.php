<?php
$page_title = 'Ledger Report';
require_once('includes/load.php');



$type = isset($_GET['type']) ? $_GET['type'] : 'supplier';
$party_id = isset($_GET['party_id']) ? (int)$_GET['party_id'] : 0;
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

if($party_id > 0){

if($type == 'supplier'){

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
    WHERE supplier_id='{$party_id}'
      AND bill_date BETWEEN '{$from}' AND '{$to}'
    ORDER BY bill_date ASC, ledger_id ASC
    ");

    foreach($ledger as $l){

        // Advance Entry
        if($l['entry_type'] == 'ADVANCE'){

            $rows[] = [
                'date'       => $l['bill_date'],
                'particular' => 'Advance',
                'type'       => 'Advance',
                'voucher'    => 'ADVANCE',
                'debit'      => $l['paid_amount'],
                'credit'     => 0
            ];

        }else{

            // Purchase Entry
            $rows[] = [
                'date'       => $l['bill_date'],
                'particular' => 'Purchase',
                'type'       => 'Purchase (GRN)',
                'voucher'    => $l['bill_no'],
                'debit'      => 0,
                'credit'     => $l['bill_amount']
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

                $rows[] = [
                    'date'       => $p['payment_date'],
                    'particular' => 'Payment',
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
        $sales = find_by_sql("
        SELECT invoice_date txn_date,
               invoice_no,
               net_total
        FROM invoice
        WHERE customer_id='{$party_id}'
          AND invoice_date BETWEEN '{$from}' AND '{$to}'
        ");

        foreach($sales as $s){
            $rows[] = [
                'date'=>$s['txn_date'],
                'particular'=>'Sale',
                'type'=>'Invoice',
                'voucher'=>$s['invoice_no'],
                'debit'=>$s['net_total'],
                'credit'=>0
            ];
        }

$payments = find_by_sql("
SELECT payment_date txn_date,
       CONCAT('PAY-',id) voucher_no,
       amount
FROM payments
WHERE customer_id='{$party_id}'
  AND DATE(payment_date) BETWEEN '{$from}' AND '{$to}'
");

foreach($payments as $p){
    $rows[] = [
        'date'       => $p['txn_date'],
        'particular' => 'Payment',
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
@media print{

body{
    margin:0;
    padding:0;
}

.no-print,
#screenHeader,
.panel-heading,
form,
hr{
    display:none !important;
}

.panel,
.panel-body{
    border:none !important;
    box-shadow:none !important;
    padding:0 !important;
    margin:0 !important;
}

#printHeader{
    display:block !important;
}

table{
    width:100%;
    border-collapse:collapse;
}

table th,
table td{
    border:1px solid #000 !important;
    padding:5px !important;
}

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

    $party = find_by_sql("
    SELECT supplier_name
    FROM supplier_master
    WHERE id='{$party_id}'
    LIMIT 1
    ");

    $party_name = $party ? $party[0]['supplier_name'] : '';

}else{

    $party = find_by_sql("
    SELECT customer_name
    FROM customer_master
    WHERE id='{$party_id}'
    LIMIT 1
    ");

    $party_name = $party ? $party[0]['customer_name'] : '';

}

?>

<div id="printHeader" style="display:none;text-align:center;margin-bottom:20px;">

<h2><?= $org[0]['org_name']; ?></h2>

<p>

<?= $org[0]['address']; ?><br>

GST : <?= $org[0]['gst_no']; ?>

</p>

<h3>LEDGER REPORT</h3>


<hr>

<table class="table table-bordered" style="margin-bottom:20px;">
<tr>
<td><strong>Ledger Type :</strong> <?= ucfirst($type); ?></td>
<td><strong><?= ucfirst($type); ?> :</strong> <?= $party_name; ?></td>
<td><strong>Period :</strong>
<?= date('d/M/Y',strtotime($from)); ?>
To
<?= date('d/M/Y',strtotime($to)); ?>
</td>
</tr>
</table>

</div>

<div class="panel panel-default">
<div class="panel-heading no-print" id="screenHeader">
    <strong>Ledger Report</strong>
</div>
<div class="panel-body">

<form method="get" class="row no-print">

<div class="col-md-2">
<label>Ledger Type</label>
<select name="type" class="form-control" onchange="this.form.submit()">
<option value="supplier" <?= $type=='supplier'?'selected':''; ?>>Supplier</option>
<option value="customer" <?= $type=='customer'?'selected':''; ?>>Customer</option>
</select>
</div>

<div class="col-md-2">
<label>Party</label>
<select name="party_id" class="form-control">
<option value="">Select</option>

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

<div class="col-md-2">
<label>From</label>
<input
type="text"
name="from"
class="form-control ledger-datepicker"
value="<?= date('d/M/Y', strtotime($from)); ?>"
autocomplete="off">
</div>

<div class="col-md-2">
<label>To</label>
<input
type="text"
name="to"
class="form-control ledger-datepicker"
value="<?= date('d/M/Y', strtotime($to)); ?>"
autocomplete="off">
</div>

<div class="col-md-3">

<label>&nbsp;</label><br>

<button type="submit"
class="btn btn-primary">

Show Report

</button>

<button
type="button"
class="btn btn-success no-print"
onclick="printLedger()">

<i class="fa fa-print"></i> Print / PDF

</button>

</div>

</form>

<hr class="no-print">

<table class="table table-bordered table-striped">
<thead>
<tr>
<th>Date</th>
<th>Particular</th>
<th>Voucher Type</th>
<th>Voucher No</th>
<th>Debit</th>
<th>Credit</th>
<th>Balance</th>
</tr>
</thead>
<tbody>

<?php foreach($rows as $r):

$totalDebit += $r['debit'];
$totalCredit += $r['credit'];

if($type=='supplier'){
    $balance += $r['credit'];
    $balance -= $r['debit'];
}else{
    $balance += $r['debit'];
    $balance -= $r['credit'];
}
?>

<tr>
<td><?= date('d/M/Y', strtotime($r['date'])); ?></td>
<td><?= $r['particular']; ?></td>
<td><?= $r['type']; ?></td>
<td><?= $r['voucher']; ?></td>
<td><?= $r['debit']?number_format($r['debit'],2):''; ?></td>
<td><?= $r['credit']?number_format($r['credit'],2):''; ?></td>
<td>
<?php

if($type=='supplier'){

    if($balance > 0){

        echo "<strong style='color:red;'>₹ ".number_format($balance,2)." Cr</strong>";

    }elseif($balance < 0){

        echo "<strong style='color:green;'>₹ ".number_format(abs($balance),2)." Adv</strong>";

    }else{

        echo "<strong>₹ 0.00</strong>";

    }

}else{

    if($balance > 0){

        echo "<strong style='color:red;'>₹ ".number_format($balance,2)." Dr</strong>";

    }elseif($balance < 0){

        echo "<strong style='color:green;'>₹ ".number_format(abs($balance),2)." Cr</strong>";

    }else{

        echo "<strong>₹ 0.00</strong>";

    }

}

?>
</td>
</tr>

<?php endforeach; ?>

</tbody>

<tfoot>
<tr>
<th colspan="4" class="text-right">Total</th>
<th><?= number_format($totalDebit,2); ?></th>
<th><?= number_format($totalCredit,2); ?></th>
<th style="color:<?= ($balance > 0) ? 'red' : 'black'; ?>;font-weight:bold;">
    <?= number_format($balance,2); ?>
</th>
</tr>
</tfoot>

</table>

</div>
</div>
</div>

<?php
?>

<script>

function printLedger(){

    document.getElementById("printHeader").style.display = "block";

    document.querySelector("form").style.display = "none";
    document.querySelector(".panel-heading").style.display = "none";
    document.querySelector("hr.no-print").style.display = "none";

    var printContents =
    document.getElementById("printArea").innerHTML;

    var originalContents =
    document.body.innerHTML;

    document.body.innerHTML =
    printContents;

    window.print();

    document.body.innerHTML =
    originalContents;

    location.reload();

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
