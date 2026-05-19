<?php
require_once('includes/load.php');

/* FETCH FY */

$years = find_by_sql("
SELECT *
FROM financial_year_master
ORDER BY fy_start_year DESC
");

/* ================= ADD FY ================= */

if(isset($_POST['add_fy'])){

    $start_year = (int)$_POST['fy_start_year'];

    $end_year = $start_year + 1;

$fy_name =
    substr($start_year,2,2)
    . "-" .
    substr($end_year,2,2);

    /* CHECK */

    $check = find_by_sql("
    SELECT *
    FROM financial_year_master
    WHERE fy_name='{$fy_name}'
    ");

    if($check){

        $session->msg('d',
        'Financial Year Already Exists');

        redirect('financial_year_master.php');
    }

    /* INSERT */

    $sql = "INSERT INTO financial_year_master
    (fy_name, fy_start_year, fy_end_year)

    VALUES

    ('{$fy_name}',
     '{$start_year}',
     '{$end_year}')";

    if($db->query($sql)){

        $session->msg('s',
        'Financial Year Added');

    }else{

        $session->msg('d',
        'Insert Failed');
    }

    redirect('financial_year_master.php');
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

<!-- LEFT -->

<div class="col-md-4">

<div class="panel panel-default">

<div class="panel-heading">
<strong>Add Financial Year</strong>
</div>

<div class="panel-body">

<form method="post">

<div class="form-group">

<label>Start Year</label>

<select name="fy_start_year"
class="form-control"
required>

<option value="">Select</option>

<?php
$current = date('Y');

for($i=$current-2; $i<=$current+10; $i++):
?>

<option value="<?= $i; ?>">

<?= $i; ?>

</option>

<?php endfor; ?>

</select>

</div>

<button type="submit"
name="add_fy"
class="btn btn-success btn-block">

Add Financial Year

</button>

</form>

</div>
</div>
</div>

<!-- RIGHT -->

<div class="col-md-8">

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
