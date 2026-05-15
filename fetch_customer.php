<?php
require_once('includes/load.php');

$phone = $_GET['phone'] ?? '';

$data = find_by_sql("SELECT * FROM customer_master WHERE contact_no='$phone' LIMIT 1");

if($data){
 echo json_encode([
   "status"=>"found",
   "name"=>$data[0]['customer_name'],
   "gst"=>$data[0]['gst_no'],
   "address"=>$data[0]['address']
 ]);
}else{
 echo json_encode(["status"=>"not_found"]);
}
?>