<?php
require_once('includes/load.php');
header('Content-Type: application/json');

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $name    = remove_junk($db->escape($_POST['customer_name'] ?? ''));
    $contact = remove_junk($db->escape($_POST['contact_no'] ?? ''));
    $gst     = remove_junk($db->escape($_POST['gst_no'] ?? ''));
    
    if(empty($name)){
        echo json_encode(['status' => 'error', 'message' => 'Customer name is required!']);
        exit;
    }

    $center_id = isset($_SESSION['center_id']) ? (int)$_SESSION['center_id'] : 1;

    $sql = "INSERT INTO customer_master (customer_name, contact_no, gst_no, center_id) 
            VALUES ('$name', '$contact', '$gst', '$center_id')";

    if($db->query($sql)){
        $new_id = $db->insert_id();
        echo json_encode([
            'status' => 'success',
            'id'     => $new_id,
            'name'   => $name
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save customer!']);
    }
    exit;
}
