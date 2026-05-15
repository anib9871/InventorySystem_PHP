<?php
require_once('includes/load.php');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($id <= 0){
    die("Invalid Invoice ID");
}

/* ================= INVOICE ================= */

$invoice_data = find_by_sql("
SELECT i.*, c.customer_name, c.contact_no
FROM invoice i
LEFT JOIN customer_master c
ON c.id = i.customer_id
WHERE i.id = '{$id}'
");

if(!$invoice_data){
    die("Invoice not found");
}

$invoice = $invoice_data[0];

/* ================= ORGANIZATION ================= */

$org_data = find_by_sql("
SELECT *
FROM organization_master
WHERE id = '".$invoice['organization_id']."'
");

$org = $org_data ? $org_data[0] : [];

/* ================= ITEMS ================= */

$items = find_by_sql("
SELECT ii.*, p.name
FROM invoice_items ii
LEFT JOIN products p
ON p.id = ii.product_id
WHERE ii.invoice_id = '{$id}'
");

?>

<!DOCTYPE html>
<html>
<head>

<title>Thermal Invoice</title>

<style>

*{
    box-sizing:border-box;
}

/* ================= PAGE ================= */

html,
body{

    width: <?= $print_css_width ?>;
    margin:0;
    padding:0;

    background:#fff;
    color:#000;

    font-family:'Courier New', monospace;
    font-size:13px;

}

/* ================= MAIN BILL ================= */

.bill{

    width: <?= $print_css_width ?>;

    margin:0 auto;

    padding:3mm;

}

/* ================= HEADER ================= */

.shop-name{

    text-align:center;

    font-size:18px;
    font-weight:bold;

    letter-spacing:1px;
    text-transform:uppercase;

    margin-bottom:4px;
}

.shop-address{

    text-align:center;

    font-size:11px;

    line-height:17px;

    margin-bottom:6px;
}

/* ================= TITLE ================= */

.invoice-title{

    text-align:center;

    font-size:14px;
    font-weight:bold;

    border-top:1px dashed #000;
    border-bottom:1px dashed #000;

    padding:6px 0;

    margin:7px 0;
}

/* ================= COMMON ================= */

.center{
    text-align:center;
}

.right{
    text-align:right;
}

.bold{
    font-weight:bold;
}

/* ================= INFO ROW ================= */

.info-row{

    display:flex;

    justify-content:space-between;

    margin:3px 0;

    font-size:12px;
}

/* ================= CUSTOMER ================= */

.customer-box{

    font-size:12px;

    line-height:18px;

    margin-top:5px;
}

/* ================= TABLE ================= */

table{

    width:100%;

    border-collapse:collapse;

    margin-top:6px;
}

/* TABLE HEADER */

th{

    border-top:1px dashed #000;
    border-bottom:1px dashed #000;

    padding:6px 0;

    font-size:12px;

    text-align:left;
}

/* TABLE DATA */

td{

    padding:5px 0;

    font-size:12px;

    vertical-align:top;
}

/* ================= ITEM WIDTHS ================= */

.item-name{
    width:55%;
}

.item-qty{
    width:15%;
    text-align:center;
}

.item-amt{
    width:30%;
    text-align:right;
}

/* ================= TOTAL ================= */

.total-box{

    border-top:1px dashed #000;
    border-bottom:1px dashed #000;

    padding:7px 0;

    margin-top:7px;
}

.total-row{

    display:flex;

    justify-content:space-between;

    font-size:15px;

    font-weight:bold;
}

/* ================= FOOTER ================= */

.footer{

    text-align:center;

    margin-top:12px;

    font-size:11px;

    line-height:18px;
}

/* ================= HR ================= */

hr{

    border:none;

    border-top:1px dashed #000;

    margin:7px 0;
}

/* ================= PRINT ================= */

@media print{

    @page{

        size:auto;

        margin:0;
    }

    html,
    body{

        width: <?= $print_css_width ?>;

        margin:0;
        padding:0;
    }

    .bill{

        width: <?= $print_css_width ?>;

        margin:0;

        padding:2mm;
    }

}

</style>

</head>

<body>

<div class="bill">

    <!-- SHOP HEADER -->

    <div class="shop-name">
        <?= strtoupper($org['org_name'] ?? '') ?>
    </div>

    <div class="shop-address">

        <?= $org['address'] ?? '' ?><br>

        <?php if(!empty($org['contact_no'])): ?>
            Phone: <?= $org['contact_no'] ?><br>
        <?php endif; ?>

        <?php if($gst_enabled == "Yes" && !empty($org['gst_no'])): ?>
            GSTIN: <?= $org['gst_no'] ?><br>
        <?php endif; ?>

    </div>

    <div class="invoice-title">
        TAX INVOICE
    </div>

    <hr>

    <!-- INVOICE INFO -->

    <div class="info-row">
        <span>Invoice No</span>
        <span><?= $invoice['invoice_no'] ?></span>
    </div>

    <div class="info-row">
        <span>Date</span>
        <span>
            <?= date("d-m-Y", strtotime($invoice['invoice_date'])) ?>
        </span>
    </div>

    <hr>

    <!-- CUSTOMER -->

    <div style="font-size:12px;">

        <b>Customer:</b>
        <?= $invoice['customer_name'] ?><br>

        <?php if(!empty($invoice['contact_no'])): ?>
            <b>Phone:</b>
            <?= $invoice['contact_no'] ?>
        <?php endif; ?>

    </div>

    <hr>

    <!-- ITEMS -->

    <table>

        <tr>
            <th>Item</th>
            <th align="center">Qty</th>
            <th align="right">Amt</th>
        </tr>

        <?php foreach($items as $it){ ?>

        <tr>

            <td>
                <?= $it['name'] ?>
            </td>

            <td align="center">
                <?= $it['qty'] ?>
            </td>

            <td align="right">
                <?= number_format($it['line_total'],2) ?>
            </td>

        </tr>

        <?php } ?>

    </table>

    <!-- TOTAL -->

    <div class="total-box">

        <div class="total-row">

            <span>Total</span>

            <span>
                ₹ <?= number_format($invoice['net_total'],2) ?>
            </span>

        </div>

    </div>

    <!-- FOOTER -->

    <div class="footer">

        Thank You!<br>
        Visit Again 🙏

        <br><br>

        Powered By<br>
        RED ORANGES CONSULTING

    </div>

</div>

<script>

window.onload = function(){

    window.print();

    window.onafterprint = function(){

        window.location.href='invoice_create.php';

    };

};

</script>

</body>
</html>