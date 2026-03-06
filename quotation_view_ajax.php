<?php
require_once('includes/load.php');

if(!isset($_GET['id'])) exit("Invalid ID");

$id = (int)$_GET['id'];

$quote_data = find_by_sql("
 SELECT q.*,
        c.customer_name,
        c.address as cust_address,
        c.gst_no as cust_gst,
        c.contact_no as cust_phone
 FROM quotation_master q
 LEFT JOIN customer_master c ON c.id = q.customer_id
 WHERE q.id = {$id}
");

if(!$quote_data || count($quote_data) == 0){
   die("Quotation not found");
}

$quote = $quote_data[0];

$items = find_by_sql("
SELECT qi.*, p.name 
FROM quotation_items qi
LEFT JOIN products p ON p.id=qi.product_id
WHERE qi.quotation_id='{$id}'
");
?>

<b>Customer:</b> <?php echo $quote['customer_name']; ?><br>
<b>Address:</b> <?php echo $quote['cust_address']; ?><br>
<b>Date:</b> <?php echo $quote['quotation_date']; ?><br><br>

<table class="table table-bordered table-sm">
<thead class="table-dark">
<tr>
<th>#</th>
<th>Product</th>
<th>Qty</th>
<th>Rate (Excl GST)</th>
<th>GST%</th>
<th>GST Amt</th>
<th>Discount%</th>
<th>Discount</th>
<th>Total</th>
</tr>
</thead>

<tbody>
<?php 
$i=1; 
$gst_total=0;
$total_discount = 0; 
foreach($items as $it){

    $gst_amt = ($it['qty'] * $it['rate_excl_gst']) * $it['gst_percent'] / 100;
    $gst_total += $gst_amt;
    $total_discount += $it['discount_amount'];
?>
<tr>
<td><?php echo $i++; ?></td>
<td><?php echo $it['name']; ?></td>
<td><?php echo $it['qty']; ?></td>
<td><?php echo number_format($it['rate_excl_gst'],2); ?></td>
<td><?php echo $it['gst_percent']; ?>%</td>
<td><?php echo number_format($gst_amt,2); ?></td>
<td><?php echo number_format($it['discount_percent'],2); ?></td>
<td><?php echo number_format($it['discount_amount'],2); ?></td>
<td><?php echo number_format($it['line_total'],2); ?></td>
</tr>
<?php } ?>
</tbody>
</table>

<hr>

<div class="text-end">
<b>Subtotal:</b> ₹ <?php echo number_format($quote['subtotal'],2); ?><br>
<b>Total Discount:</b> ₹ <?php echo number_format($total_discount,2); ?><br>
<b>Total GST:</b> ₹ <?php echo number_format($gst_total,2); ?><br>
<b>Net Total:</b> ₹ <?php echo number_format($quote['net_total'],2); ?>
</div>
