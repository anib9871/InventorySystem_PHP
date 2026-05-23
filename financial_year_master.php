<?php
require_once('includes/load.php');

/* FETCH FY */

$years = find_by_sql("
SELECT *
FROM financial_year_master
ORDER BY fy_start_year DESC
");


/* AUTO CREATE CURRENT FY */

$month = date('m');

if($month >= 4){
    $start_year = date('Y');
}else{
    $start_year = date('Y') - 1;
}

$end_year = $start_year + 1;

$fy_name =
substr($start_year,2,2)
. "-" .
substr($end_year,2,2);

/* CHECK EXISTING */

$check_current_fy = find_by_sql("
SELECT *
FROM financial_year_master
WHERE fy_name='{$fy_name}'
");

/* AUTO INSERT */

if(!$check_current_fy){

$db->query("
INSERT INTO financial_year_master
(fy_name, fy_start_year, fy_end_year, is_active)

VALUES

(
'{$fy_name}',
'{$start_year}',
'{$end_year}',
1
)
");

}



/* ================= SET ACTIVE ================= */

if(isset($_GET['active'])){

    $id = (int)$_GET['active'];

    $db->query("
    UPDATE financial_year_master
    SET is_active = 0
    ");

    $db->query("
    UPDATE financial_year_master
    SET is_active = 1
    WHERE fy_id='{$id}'
    ");

    $session->msg('s',
    'Financial Year Activated');

    redirect('financial_year_master.php');
}
?>

<?php include_once('layouts/header.php'); ?>

<div class="row">



<!-- RIGHT -->

<div class="col-md-12">

<div class="panel panel-default">

<div class="panel-heading">
<strong>Financial Year List</strong>
</div>

<div class="panel-body">

<div class="table-responsive">

<table class="table table-bordered table-striped">

<thead>

<tr>
<th>#</th>
<th>Financial Year</th>
<th>Start Year</th>
<th>End Year</th>
<th>Status</th>
<th>Action</th>
</tr>

</thead>

<tbody>

<?php foreach($years as $fy): ?>

<tr>

<td><?= count_id(); ?></td>

<td><?= $fy['fy_name']; ?></td>

<td><?= $fy['fy_start_year']; ?></td>

<td><?= $fy['fy_end_year']; ?></td>

<td>

<?php if($fy['is_active']==1): ?>

<span class="label label-success">

Active

</span>

<?php else: ?>

<span class="label label-default">

Inactive

</span>

<?php endif; ?>

</td>

<td>

<a href="financial_year_master.php?active=<?= $fy['fy_id']; ?>"
class="btn btn-primary btn-xs">

Set Active

</a>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>
</div>

</div>

</div>

<?php include_once('layouts/footer.php'); ?>
