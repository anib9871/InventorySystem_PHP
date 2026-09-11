<?php
require_once('includes/load.php');

// Check if ID is passed
if(isset($_GET['id'])) {
    $invoice_id = (int)$_GET['id'];

    // 1. Fetch the invoice to ensure it exists
    $invoice = find_by_sql("SELECT * FROM invoice WHERE id='{$invoice_id}' LIMIT 1");
    
    if(!$invoice) {
        $session->msg("d", "Proforma record not found.");
        redirect('proforma_list.php');
    }

    $inv_data = $invoice[0];

    // Double checks taaki pehle se Cancel ya Convert hua Proforma dubara process na ho
    if($inv_data['payment_status'] === 'Cancelled') {
        $session->msg("w", "Yeh Proforma pehle se hi cancel ho chuka hai.");
        redirect('proforma_list.php');
    }
    
    if($inv_data['payment_status'] === 'Converted') {
        $session->msg("w", "Yeh Proforma pehle hi Tax Invoice ban chuka hai, isko ab cancel nahi kar sakte.");
        redirect('proforma_list.php');
    }

    // 2. Mark Proforma as Cancelled and clear the Due Amount
    // (Notice: Yahan stock inventory rollback NAHI lagaya hai, kyunki ye kaccha bill hai)
    $query = "UPDATE invoice 
              SET payment_status = 'Cancelled', 
                  due_amount = 0, 
                  remarks = CONCAT(IFNULL(remarks,''), ' (CANCELLED)') 
              WHERE id = '{$invoice_id}'";
              
    if($db->query($query)){
        $session->msg("s", "Proforma '{$inv_data['invoice_no']}' Cancel ho gaya hai!");
    } else {
        $session->msg("d", "Sorry, failed to cancel the Proforma.");
    }
} else {
    $session->msg("d", "No Proforma ID provided.");
}

redirect('proforma_list.php');
?>