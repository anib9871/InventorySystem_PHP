<?php
$page_title = 'Manage GRN';
require_once('includes/load.php');

$grn_list = find_by_sql("

SELECT
    tm.bill_indent_no,
    MAX(tm.bill_indent_date) AS bill_indent_date,
    MAX(sl.bill_amount) AS total,
    MAX(s.supplier_name) AS supplier_name

FROM transaction_master tm

LEFT JOIN supplier_master s
    ON s.id = tm.supplier_id

LEFT JOIN supplier_ledger sl
    ON sl.bill_no = tm.bill_indent_no

WHERE tm.transaction_type = 1
AND tm.from_dept = 'SUPPLIER'

GROUP BY tm.bill_indent_no

ORDER BY MAX(tm.bill_indent_date) DESC

");

include_once('layouts/header.php');
?>

<style>

#grnTable thead th{
    background:#1f2d4d;
    color:#fff;
    font-weight:600;
    border-color:#1f2d4d;
}

#grnTable tbody td{
    vertical-align:middle;
}

#grnTable tbody tr:hover{
    background:#f7fbff;
}

#grnSearch{
    height:34px;
}

.btn-xs{
    margin-right:4px;
}

.panel{
    border-radius:8px;
}

.panel-heading{
    font-size:24px;
    font-weight:600;
}

.btn-round{
    border-radius:50px;
    padding:8px 20px;
    font-weight:600;
}

</style>

<div class="row">

<div class="col-md-12">

<div class="panel panel-default">

<div class="panel-heading clearfix">

    <strong class="pull-left" style="margin-top:6px;">
        Manage GRN
    </strong>

<a href="grn.php"
   class="btn btn-success btn-sm pull-right btn-round">
    <i class="fa fa-plus"></i> Create GRN
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
                   id="grnSearch"
                   class="form-control"
                   placeholder="Search GRN...">
        </div>
    </div>
</div>

<div class="table-responsive">

<table class="table table-bordered table-hover" id="grnTable">

<thead style="background:#1f2d4d;color:#fff;">

<tr>
<th width="50">#</th>
<th>GRN No</th>
<th>Supplier</th>
<th width="140">Date</th>
<th width="140">Total</th>
<th width="120">Action</th>
</tr>

</thead>

<tbody>

<?php if(empty($grn_list)){ ?>

<tr>
<td colspan="6" class="text-center">
No GRN Found
</td>
</tr>

<?php } ?>

<?php $i=1; foreach($grn_list as $g){ ?>

<tr>

<td><?= $i++; ?></td>

<td><?= $g['bill_indent_no']; ?></td>

<td><?= $g['supplier_name']; ?></td>

<td><?= date('d/M/Y', strtotime($g['bill_indent_date'])); ?></td>

<td>₹ <?= number_format($g['total'],2); ?></td>

<td>

<a href="grn.php?edit=<?= urlencode($g['bill_indent_no']); ?>"
   class="btn btn-info btn-xs"
   title="Edit">
    <i class="fa fa-pencil"></i>
</a>

<a href="print_grn.php?bill=<?= urlencode($g['bill_indent_no']); ?>"
   class="btn btn-danger btn-xs"
   target="_blank"
   title="Print">
    <i class="fa fa-print"></i>
</a>

</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>

<script>

$('#grnSearch').on('keyup', function () {

    var value = $(this).val().toLowerCase();

    $('#grnTable tbody tr').filter(function () {

        $(this).toggle(
            $(this).text().toLowerCase().indexOf(value) > -1
        );

    });

});


$(function () {
    $('[title]').tooltip();
});


</script>

<?php include_once('layouts/footer.php'); ?>
