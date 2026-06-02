
<?php
$page_title = 'Quotation List';
require_once('includes/load.php');
//page_require_level(2);

/* ================= FETCH QUOTATIONS ================= */

$quotes = find_by_sql("
SELECT
q.id,
q.quotation_no,
q.quotation_date,
c.customer_name,
q.net_total
FROM quotation_master q
LEFT JOIN customer_master c
ON c.id = q.customer_id
ORDER BY q.id DESC
");

include_once('layouts/header.php');
?>

<style>

.panel{
    border:none;
    border-radius:16px;
    overflow:hidden;
    box-shadow:0 4px 18px rgba(15,23,42,.06);
}

.panel-heading{
    background:#fff !important;
    border-bottom:1px solid #eef2f7 !important;
    padding:16px 20px !important;
    font-size:18px;
    font-weight:600;
}

.panel-body{
    background:#fff;
    padding:18px;
}

.quote-scroll{
    max-height:420px;
    overflow-y:auto;
    overflow-x:auto;
}

.quote-scroll::-webkit-scrollbar{
    width:6px;
    height:6px;
}

.quote-scroll::-webkit-scrollbar-thumb{
    background:#cbd5e1;
    border-radius:10px;
}

.table{
    margin-bottom:0;
}

.table thead th{
    background:#0f172a !important;
    color:#fff;
    border:none !important;
    padding:13px 12px !important;
    font-size:13px;
    white-space:nowrap;
    position:sticky;
    top:0;
    z-index:10;
}

.table tbody td{
    padding:13px 12px !important;
    vertical-align:middle !important;
    border-color:#eef2f7 !important;
    font-size:13px;
}

.table-striped tbody tr:nth-of-type(odd){
    background:#fafcff;
}

.table tbody tr:hover{
    background:#eef4ff !important;
    transition:.2s;
}

.btn{
    border-radius:8px !important;
    font-size:12px;
    padding:6px 12px;
    font-weight:600;
}

.btn + .btn{
    margin-left:4px;
}

.modal-content{
    border:none;
    border-radius:18px;
    overflow:hidden;
}

.modal-header{
    background:#fff;
    border-bottom:1px solid #eef2f7;
}

.modal-title{
    font-size:18px;
    font-weight:600;
}

.modal-body{
    background:#f8fafc;
}

</style>

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

<strong>
Quotation List
</strong>

</div>

<div class="panel-body">

<!-- SCROLL WRAPPER -->
<div class="quote-scroll">

<table class="table table-bordered table-striped">

<thead>

<tr>

<th width="60">#</th>
<th>Quotation No</th>
<th>Date</th>
<th>Customer</th>
<th>Net Amount</th>
<th width="280">Action</th>

</tr>

</thead>

<tbody>

<?php
$i = 1;

foreach($quotes as $q):
?>

<tr>

<td>
<?php echo $i++; ?>
</td>

<td>
<?php echo $q['quotation_no']; ?>
</td>

<td>
<?php echo date('d-m-Y', strtotime($q['quotation_date'])); ?>
</td>

<td>
<?php echo $q['customer_name']; ?>
</td>

<td>
₹ <?php echo number_format($q['net_total'],2); ?>
</td>

<td>

<!-- PRINT -->

<button
class="btn btn-primary btn-sm openQuote"
data-id="<?php echo $q['id']; ?>">

Print

</button>

<!-- EDIT -->

<a
href="quotation_edit.php?id=<?php echo $q['id']; ?>"
class="btn btn-warning btn-sm">

Edit

</a>

<!-- CONVERT -->

<a
href="convert_to_invoice.php?id=<?php echo $q['id']; ?>"
class="btn btn-success btn-sm"
title="Convert Quotation To Invoice"
onclick="return convertQuotation(this.href);">

Convert

</a>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>

</div>

<!-- MODAL -->

<div
class="modal fade"
id="quoteModal">

<div class="modal-dialog modal-xl">

<div class="modal-content">

<div class="modal-header">

<h4 class="modal-title">

Quotation Preview

</h4>

<button
type="button"
class="close"
data-dismiss="modal">

&times;

</button>

</div>

<div
class="modal-body"
style="height:85vh;">

<iframe
id="quoteFrame"
src=""
style="width:100%;height:100%;border:none;"></iframe>

</div>

</div>

</div>

</div>

<script>

document.querySelectorAll(".openQuote")

.forEach(function(btn){

btn.addEventListener("click", function(){

var id = this.getAttribute("data-id");

document.getElementById("quoteFrame").src =
"quotation_print.php?id=" + id;

$("#quoteModal").modal("show");

});

});

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

document.querySelectorAll(".openQuote")

.forEach(function(btn){

btn.addEventListener("click", function(){

var id = this.getAttribute("data-id");

document.getElementById("quoteFrame").src =
"quotation_print.php?id=" + id;

$("#quoteModal").modal("show");

});

});


</script>

<?php include_once('layouts/footer.php'); ?>

