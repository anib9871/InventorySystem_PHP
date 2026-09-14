<?php
require_once('includes/load.php');

if(isset($_GET['bill'])) {
    $bill_no = urldecode($db->escape($_GET['bill']));

    // 1. Check if GRN exists
    $grn_items = find_by_sql("SELECT * FROM transaction_master WHERE bill_indent_no = '{$bill_no}'");
    
    if(!$grn_items) {
        $session->msg("d", "GRN record not found.");
        redirect('manage_grn.php');
    }

    // Double Cancel Check
    if($grn_items[0]['status'] == 0) {
        $session->msg("w", "This GRN is already cancelled.");
        redirect('manage_grn.php');
    }

    $db->query("START TRANSACTION");

    try {
        // 2. INVENTORY ROLLBACK (Stock wapas minus karna)
        foreach($grn_items as $item) {
            $qty_to_reverse = (float)$item['quantity'] + (float)$item['free_qty'];
            $prod_id = (int)$item['product_id'];

            if(function_exists('update_product_qty')) {
                update_product_qty(-$qty_to_reverse, $prod_id);
            } else {
                $db->query("UPDATE products SET quantity = quantity - {$qty_to_reverse} WHERE id = '{$prod_id}'");
            }
        }

        // 3. ✨ MAIN FIX: Transaction Master ki Quantity 0 karni hogi ✨
        $db->query("UPDATE transaction_master 
                    SET status = 0, 
                        quantity = 0, 
                        free_qty = 0, 
                        net_price = 0,
                        comments = CONCAT(IFNULL(comments,''), ' (CANCELLED)') 
                    WHERE bill_indent_no = '{$bill_no}'");

        // 4. Clean up related tables (Payments & Ledgers)
        // YAHAN MERA PURANA CODE FAIL HO RAHA THA 'id' KI WAJAH SE, AB YE THEEK HAI!
        $db->query("DELETE FROM shipping WHERE bill_no = '{$bill_no}'");
        $db->query("DELETE FROM supplier_payment WHERE ledger_id IN (SELECT ledger_id FROM supplier_ledger WHERE bill_no = '{$bill_no}')");
        $db->query("DELETE FROM supplier_ledger WHERE bill_no = '{$bill_no}'");

        $db->query("COMMIT");
        $session->msg("s", "GRN '{$bill_no}' Cancel ho gaya aur Stock godown se minus ho chuka hai!");

    } catch (Exception $e) {
        $db->query("ROLLBACK");
        $session->msg("d", "Failed to cancel GRN: " . addslashes($e->getMessage()));
    }
} else {
    $session->msg("d", "No GRN No provided.");
}

redirect('manage_grn.php');
?>