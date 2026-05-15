<?php

require_once('includes/load.php');

header("Content-Type: application/xls");
header("Content-Disposition: attachment; filename=customer_report.xls");

$customers = find_by_sql("

SELECT 
cm.*,
mc.center_name

FROM customer_master cm

LEFT JOIN master_center mc
ON mc.center_id = cm.center_id

ORDER BY cm.id DESC

");

?>

<table border="1">

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