<?php
require_once('includes/load.php');

// Check if ID is passed
if(isset($_GET['id'])) {
    $invoice_id = (int)$_GET['id'];

    // 1. Fetch the invoice to ensure it exists
    $invoice = find_by_sql("SELECT * FROM invoice WHERE id='{$invoice_id}' LIMIT 1");
    
    if(!$invoice) {
        $session->msg("d", "Invoice record not found.");
        redirect('invoice_list.php');
    }

    // 2. Mark Invoice as Cancelled and clear the Due Amount
    $query = "UPDATE invoice 
              SET payment_status = 'Cancelled', 
                  due_amount = 0, 
                  remarks = CONCAT(IFNULL(remarks,''), ' (CANCELLED)') 
              WHERE id = '{$invoice_id}'";
              
    if($db->query($query)){
        
        // 3. Optional: Cancel transaction master entry if it exists
        $db->query("UPDATE transaction_master 
                    SET comments = 'CANCELLED' 
                    WHERE bill_indent_no = '{$invoice[0]['invoice_no']}'");

        $session->msg("s", "Invoice '{$invoice[0]['invoice_no']}' has been cancelled successfully.");
    } else {
        $session->msg("d", "Sorry, failed to cancel the invoice.");
    }
} else {
    $session->msg("d", "No invoice ID provided.");
}

redirect('invoice_list.php');
?>