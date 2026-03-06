<?php
$page_title = 'Quotation List';
require_once('includes/load.php');
page_require_level(2);

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

<?php if(isset($_GET['print_id'])){ ?>
<script>
window.onload = function(){
    document.getElementById("quoteFrame").src =
        "quotation_print.php?id=<?php echo $_GET['print_id']; ?>";
    $("#quoteModal").modal("show");
}
</script>
<?php } ?>

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
  <td><?php echo $q['quotation_date']; ?></td>
  <td><?php echo $q['customer_name']; ?></td>
  <td><?php echo number_format($q['net_total'],2); ?></td>

<td>
  <!-- View / Print -->
  <button class="btn btn-primary btn-sm openQuote"
          data-id="<?php echo $q['id']; ?>">
    View / Print
  </button>

  <!-- Edit -->
  <a href="quotation_edit.php?id=<?php echo $q['id']; ?>"
     class="btn btn-warning btn-sm">
     Edit
  </a>

  <!-- Convert to Invoice -->
  <a href="convert_to_invoice.php?id=<?php echo $q['id']; ?>"
     class="btn btn-success btn-sm"
     onclick="return confirm('Convert this quotation to invoice?')">
     Convert
  </a>
</td>
</tr>
<?php } ?>
</tbody>

</table>
</div>
</div>

<!-- MODAL -->
<div class="modal fade" id="quoteModal">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">

      <div class="modal-header">
        <h4 class="modal-title">Quotation Preview</h4>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body" style="height:80vh;">
        <iframe id="quoteFrame" src="" style="width:100%;height:100%;border:none;"></iframe>
      </div>

    </div>
  </div>
</div>

<script>
document.querySelectorAll(".openQuote").forEach(function(btn){
    btn.addEventListener("click", function(){
        var id = this.getAttribute("data-id");
        document.getElementById("quoteFrame").src = "quotation_print.php?id=" + id;
        $("#quoteModal").modal("show");
    });
});
</script>

<?php include_once('layouts/footer.php'); ?>
