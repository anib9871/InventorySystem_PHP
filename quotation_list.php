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
<style>

#quotationTable thead th{
    background:#1f2d4d;
    color:#fff;
    font-weight:600;
    border-color:#1f2d4d;
}

#quotationTable tbody td{
    vertical-align:middle;
}

#quotationTable tbody tr:hover{
    background:#f7fbff;
}

#quoteSearch{
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
        Quotation List
    </strong>

  <a href="create_quotation.php"
   class="btn btn-success btn-sm pull-right btn-round">
        <i class="fa fa-plus"></i> Create Quotation
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
                   id="quoteSearch"
                   class="form-control"
                   placeholder="Search Quotation...">
        </div>
    </div>
</div>

<div class="table-responsive">

<table class="table table-bordered table-hover" id="quotationTable">
<thead style="background:#1f2d4d;color:#fff;">
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

<a href="quotation_print.php?id=<?php echo $q['id']; ?>"
   target="_blank"
   class="btn btn-primary btn-xs"
   title="View">
   <i class="fa fa-eye"></i>
</a>

<a href="quotation_edit.php?id=<?php echo $q['id']; ?>"
   class="btn btn-info btn-xs"
   title="Edit">
   <i class="fa fa-pencil"></i>
</a>

<a href="convert_to_invoice.php?id=<?php echo $q['id']; ?>"
   class="btn btn-success btn-xs"
   title="Convert To Invoice"
   onclick="return convertQuotation(this.href)">
   <i class="fa fa-exchange"></i>
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

$('#quoteSearch').on('keyup', function () {

    var value = $(this).val().toLowerCase();

    $('#quotationTable tbody tr').filter(function () {

        $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);

    });

});


</script>



<?php include_once('layouts/footer.php'); ?>
