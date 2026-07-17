<?php
$page_title = 'Quotation List';
require_once('includes/load.php');
//page_require_level(2);

/* fetch quotation list */
$quotes = find_by_sql("
  SELECT q.id,
         q.quotation_no,
         q.quotation_date,
         c.customer_name,
         q.net_total
  FROM quotation_master q
  LEFT JOIN customer_master c ON c.id = q.customer_id
  ORDER BY q.id DESC
");

include_once('layouts/header.php');
?>

<div class="panel panel-default">
<div class="panel-heading">
<strong>Quotation List</strong>
</div>

<div class="panel-body">

<table class="table table-bordered table-striped">
<thead>
<tr>
  <th>#</th>
  <th>Quotation No</th>
  <th>Date</th>
  <th>Customer</th>
  <th>Net Amount</th>
  <th>Action</th>
</tr>
</thead>

<tbody>
<?php
$i = 1;
foreach($quotes as $q){
?>
<tr>
  <td><?php echo $i++; ?></td>
  <td><?php echo $q['quotation_no']; ?></td>
  <td><?php echo date('d/M/Y', strtotime($q['quotation_date'])); ?></td>
  <td><?php echo $q['customer_name']; ?></td>
  <td><?php echo number_format($q['net_total'],2); ?></td>

<td>
<!-- View / Print -->
<a href="quotation_print.php?id=<?php echo $q['id']; ?>"
   target="_blank"
   class="btn btn-primary btn-sm">
   View
</a>

  <!-- Edit -->
  <a href="quotation_edit.php?id=<?php echo $q['id']; ?>"
     class="btn btn-warning btn-sm">
     Edit
  </a>

  <!-- Convert to Invoice -->
<a href="convert_to_invoice.php?id=<?php echo $q['id']; ?>"
   class="btn btn-success btn-sm"
   title="Convert Quotation To Invoice"
   onclick="return convertQuotation(this.href)">
   Convert
  </a>
</td>
</tr>
<?php } ?>
</tbody>

</table>
</div>
</div>

<script>

function convertQuotation(url){

    fetch(url)

    .then(res => res.text())

    .then(data => {

        if(data.includes("Insufficient")){

            alert(data);

        }else{

            window.location.href = "invoice_list.php";

        }

    })

    .catch(err => {

        alert("Something went wrong");

    });

    return false;
}
</script>

<?php include_once('layouts/footer.php'); ?>
