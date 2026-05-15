<?php
require_once('includes/load.php');

$customers = find_by_sql("

SELECT 
cm.*,
gsm.state_name,
mc.center_name

FROM customer_master cm

LEFT JOIN gst_state_master gsm
ON gsm.id = cm.state_id

LEFT JOIN master_center mc
ON mc.center_id = cm.center_id

ORDER BY cm.id DESC

");
?>

<html>
<head>

<title>Customer Report</title>

<link rel="stylesheet"
href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css">

<style>

body{
padding:20px;
font-size:12px;
}

table{
font-size:12px;
}

</style>

</head>

<body onload="window.print()">

<h3>Customer Report</h3>

<table class="table table-bordered">

<tr>

<th>#</th>
<th>Name</th>
<th>Contact</th>
<th>Center</th>
<th>Created Date</th>

</tr>

<?php foreach($customers as $i=>$c): ?>

<tr>

<td><?php echo $i+1; ?></td>

<td><?php echo $c['customer_name']; ?></td>

<td><?php echo $c['contact_no']; ?></td>

<td><?php echo $c['center_name']; ?></td>

<td>
<?php echo date('d-m-Y', strtotime($c['created_date'])); ?>
</td>

</tr>

<?php endforeach; ?>

</table>

</body>
</html>