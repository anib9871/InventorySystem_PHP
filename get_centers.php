<?php

require_once('includes/load.php');

$org_id = (int)$_GET['org_id'];

$centers = find_by_sql("SELECT * FROM master_inventory.master_center WHERE org_id='{$org_id}'");

echo '<option value="">Select Center</option>';

foreach($centers as $center){

echo '<option value="'.$center['center_id'].'">'.$center['center_name'].'</option>';

}

?>