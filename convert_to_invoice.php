<?php
require_once('includes/load.php');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($id <= 0) die("Invalid Quotation ID");

/* Fetch Quotation */
$q_data = find_by_sql("SELECT * FROM quotation_master WHERE id = $id");
if(!$q_data) die("Quotation not found");
$quotation = $q_data[0];

/* Generate Invoice No */
$invoice_no = "INV".time();
$db->query("START TRANSACTION");

/* Insert Into Invoice Table */
$query = "
INSERT INTO invoice (
invoice_no,
customer_id,
organization_id,
invoice_date,
gst_type,
subtotal,
net_total,
advance_paid,
source_type,
source_id
) VALUES (
'{$invoice_no}',
'{$quotation['customer_id']}',
'{$quotation['organization_id']}',
NOW(),
'{$quotation['gst_type']}',
'{$quotation['subtotal']}',
'{$quotation['net_total']}',
0,
'QUOTATION',
'$id'
)
";

if(!$db->query($query)){
    $db->query("ROLLBACK");
    die("Invoice Insert Failed");
}

$new_invoice_id = $db->insert_id();

/* Fetch Quotation Items */
$items = find_by_sql("SELECT * FROM quotation_items WHERE quotation_id = $id");

/* Insert Items into Invoice Items */
foreach($items as $it){

if(!$db->query("
INSERT INTO invoice_items (
    invoice_id,
    product_id,
    qty,
    rate_excl_gst,
    discount_amount,
    gst_percent,
    cgst_amount,
    sgst_amount,
    igst_amount,
    line_total
) VALUES (
    '{$new_invoice_id}',
    '{$it['product_id']}',
    '{$it['qty']}',
    '{$it['rate_excl_gst']}',
    '{$it['discount_amount']}',
    '{$it['gst_percent']}',
    '{$it['cgst_amount']}',
    '{$it['sgst_amount']}',
    '{$it['igst_amount']}',
    '{$it['line_total']}'
)
")){
    $db->query("ROLLBACK");
    die("Invoice Item Insert Failed");
}

    /* ===== STOCK DEDUCT LOGIC (CONVERTED FROM QUOTATION) ===== */

$product_id = (int)$it['product_id'];
$qty        = (float)$it['qty'];

/* Fetch product */
$product = find_by_id('products', $product_id);

if($product){

$stock_data = find_by_sql("
    SELECT 
    IFNULL(SUM(qty_in),0) - IFNULL(SUM(qty_out),0) as current_stock
    FROM stock_ledger
    WHERE product_id = {$product_id}
");

$current_stock = (float)$stock_data[0]['current_stock'];

if($current_stock < $qty){
    $db->query("ROLLBACK");
    die("Insufficient stock for ".$product['name']);
}

    /* Step 1: Finished Goods minus */
    $db->query("
        UPDATE products
        SET quantity = quantity - {$qty}
        WHERE id = {$product_id}
    ");

    /* Stock Ledger Entry (Finished Product) */
    $db->query("
        INSERT INTO stock_ledger
        (product_id, reference_no, reference_type, trans_date, qty_in, qty_out, created_at)
        VALUES
        ({$product_id}, '{$invoice_no}', 'SALE-CONVERT', NOW(), 0, {$qty}, NOW())
    ");

    /* Step 2: If BOM → Raw Materials minus */
    if($product['is_bom'] == 1){

        $bom_items = find_by_sql("
            SELECT raw_product_id, quantity
            FROM bom
            WHERE product_id = {$product_id}
        ");

    foreach($bom_items as $b){

    $raw_id  = (int)$b['raw_product_id'];
    $bom_qty = (float)$b['quantity'];

    // 🔥 Pehle calculate karo
    $total_raw_deduct = $bom_qty * $qty;

    $raw_product = find_by_id('products', $raw_id);

$raw_stock_data = find_by_sql("
    SELECT 
    IFNULL(SUM(qty_in),0) - IFNULL(SUM(qty_out),0) as current_stock
    FROM stock_ledger
    WHERE product_id = {$raw_id}
");

$current_raw_stock = (float)$raw_stock_data[0]['current_stock'];

if($current_raw_stock < $total_raw_deduct){
    $db->query("ROLLBACK");
    die("Insufficient raw material stock for ".$raw_product['name']);
}

    $db->query("
        UPDATE products
        SET quantity = quantity - {$total_raw_deduct}
        WHERE id = {$raw_id}
    ");

    $db->query("
        INSERT INTO stock_ledger
        (product_id, reference_no, reference_type, trans_date, qty_in, qty_out, created_at)
        VALUES
        ({$raw_id}, '{$invoice_no}', 'SALE-BOM-CONVERT', NOW(), 0, {$total_raw_deduct}, NOW())
    ");
}
    }
}
}



/* Redirect to Invoice Print */
$db->query("COMMIT");
header("Location: invoice_print.php?id=".$new_invoice_id);
exit;