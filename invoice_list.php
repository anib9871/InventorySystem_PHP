<?php
$page_title = 'Invoice List';
require_once('includes/load.php');
//page_require_level(2);

/* Fetch invoice list */
$invoices = find_by_sql("
  SELECT i.id,
         i.invoice_no,
         i.invoice_date,
         c.customer_name,
         i.net_total
  FROM invoice i
  LEFT JOIN customer_master c ON c.id = i.customer_id
  ORDER BY i.id DESC
");

include_once('layouts/header.php');
?>

<div class="panel panel-default">
<div class="panel-heading">
<strong>Invoice List</strong>
</div>

<div class="panel-body">

<table class="table table-bordered table-striped">
<thead>
<tr>
  <th>#</th>
  <th>Invoice No</th>
  <th>Date</th>
  <th>Customer</th>
  <th>Net Amount</th>
  <th>Action</th>
</tr>
</thead>

<tbody>
<?php
$i = 1;
foreach($invoices as $inv){
?>
<tr>
  <td><?php echo $i++; ?></td>
  <td><?php echo $inv['invoice_no']; ?></td>
  <td><?php echo $inv['invoice_date']; ?></td>
  <td><?php echo $inv['customer_name']; ?></td>
  <td><?php echo number_format($inv['net_total'],2); ?></td>

  <td>
    <!-- PRINT BUTTON -->
    <a href="invoice_print.php?id=<?php echo $inv['id']; ?>"
       target="_blank"
       class="btn btn-primary btn-sm">
       Print
    </a>

    <!-- EDIT BUTTON (optional) -->
    <a href="invoice_edit.php?id=<?php echo $inv['id']; ?>"
       class="btn btn-warning btn-sm">
       Edit
    </a>
  </td>
</tr>
<?php } ?>
</tbody>

</table>

</div>
</div>

<?php include_once('layouts/footer.php'); ?>
