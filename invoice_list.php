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
<style>
#invoiceTable thead th{
    background:#1f2d4d;
    color:#fff;
    font-weight:600;
    border-color:#1f2d4d;
}

#invoiceTable tbody td{
    vertical-align:middle;
}

#invoiceTable tbody tr:hover{
    background:#f7fbff;
}

#invoiceSearch{
    height:34px;
}

.btn-round{
    border-radius:50px;
    padding:8px 20px;
    font-weight:600;
}
</style>
<div class="panel panel-default">
<div class="panel-heading clearfix">

<strong class="pull-left" style="margin-top:6px;">
    Invoice List
</strong>

<a href="invoice_create.php"
   class="btn btn-success btn-sm pull-right btn-round">
    <i class="fa fa-plus"></i> Create Invoice
</a>

</div>
<div class="panel-body">
  <div class="row" style="margin-bottom:15px;">
    <div class="col-md-3">
        <div class="input-group">
            <span class="input-group-addon">
                <i class="fa fa-search"></i>
            </span>
            <input type="text"
                   id="invoiceSearch"
                   class="form-control"
                   placeholder="Search Invoice...">
        </div>
    </div>
</div>

<!-- 🔥 SCROLL WRAPPER START -->
<div class="table-responsive">

<table class="table table-bordered table-hover" id="invoiceTable">
<thead style="background:#1f2d4d;color:#fff;">
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
 <td><?php echo date('d/M/Y', strtotime($inv['invoice_date'])); ?></td>
  <td><?php echo $inv['customer_name']; ?></td>
  <td><?php echo number_format($inv['net_total'],2); ?></td>

<td>

<a href="invoice_print.php?id=<?php echo $inv['id']; ?>"
   target="_blank"
   class="btn btn-danger btn-xs"
   title="Print">
    <i class="fa fa-print"></i>
</a>

<a href="invoice_edit.php?id=<?php echo $inv['id']; ?>"
   class="btn btn-info btn-xs"
   title="Edit">
    <i class="fa fa-pencil"></i>
</a>

</td>
</tr>
<?php } ?>
</tbody>

</table>
</div>

</div>
</div>
<script>
$('#invoiceSearch').on('keyup', function () {

    var value = $(this).val().toLowerCase();

    $('#invoiceTable tbody tr').filter(function () {

        $(this).toggle(
            $(this).text().toLowerCase().indexOf(value) > -1
        );

    });

});
</script>

<?php include_once('layouts/footer.php'); ?>
