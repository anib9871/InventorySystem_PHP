<?php
require_once('includes/load.php');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($id <= 0) die("Invalid Quotation ID");

/* Fetch Quotation */
$q_data = find_by_sql("SELECT * FROM quotation_master WHERE id = $id");
if(!$q_data) die("Quotation not found");
$quotation = $q_data[0];

/* 🔥 ORG SECURITY */
if($quotation['organization_id'] != $_SESSION['org_id']){
    die("Unauthorized Access");
}

/* 🔥 DUPLICATE CHECK */
$check = find_by_sql("
SELECT id FROM invoice 
WHERE source_type='QUOTATION' 
AND source_id='{$id}'
LIMIT 1
");

if($check){
    die("Invoice already created for this quotation");
}

$db->query("START TRANSACTION");
/* Generate Invoice No */
/* ===== GET NEXT INVOICE NUMBER FROM SEQUENCE ===== */

$fy = find_by_sql("
SELECT fy_id, fy_name
FROM financial_year_master
WHERE is_active = 1
LIMIT 1
");

if(empty($fy)){
    throw new Exception("Active Financial Year not found");
}

$fy_id   = $fy[0]['fy_id'];
$fy_name = $fy[0]['fy_name'];

$seq = find_by_sql("
SELECT last_no
FROM sequence_master
WHERE sequence_category='invoice'
AND fy_id='$fy_id'
LIMIT 1
FOR UPDATE
");

if($seq){

    $next = $seq[0]['last_no'] + 1;

    $db->query("
    UPDATE sequence_master
    SET last_no = '$next'
    WHERE sequence_category='invoice'
    AND fy_id = '$fy_id'
    ");

}else{

    $next = 1;

    $db->query("
    INSERT INTO sequence_master
    (
        sequence_category,
        fy_id,
        last_no
    )
    VALUES
    (
        'invoice',
        '$fy_id',
        1
    )
    ");
}

$invoice_no = $fy_name.'/'.$next;

$fy = find_by_sql("
SELECT fy_name
FROM financial_year_master
WHERE is_active = 1
LIMIT 1
");

$fy_name = $fy[0]['fy_name'];

$invoice_no = $fy_name.'/'.$next;
$db->query("
UPDATE sequence_master 
SET last_no = $next
WHERE sequence_category='invoice'
");


try{
/* Insert Into Invoice Table */
$query = "
INSERT INTO invoice (
invoice_no,
customer_id,
organization_id,
invoice_date,
gst_type,
subtotal,
gst_total,
net_total,
terms_conditions,
advance_paid,
source_type,
source_id
) VALUES (
'{$invoice_no}',
'{$quotation['customer_id']}',
'{$quotation['organization_id']}',
'{$quotation['quotation_date']}',
'".(($gst_enabled == "Yes") ? $quotation['gst_type'] : 'exclusive')."',
'{$quotation['subtotal']}',
'{$quotation['gst_total']}',
'{$quotation['net_total']}',
'".$db->escape($quotation['terms_conditions'])."',
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
    rate_incl_gst,
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
    '{$it['rate_incl_gst']}',
    '{$it['cgst_amount']}',
    '{$it['sgst_amount']}',
    '{$it['igst_amount']}',
    '{$it['line_total']}'
)
")){
  throw new Exception("Invoice Item Insert Failed");
}

    /* ===== STOCK DEDUCT LOGIC (CONVERTED FROM QUOTATION) ===== */

$product_id = (int)$it['product_id'];
$qty        = (float)$it['qty'];

if($qty <= 0){
    continue;
}

/* Fetch product */
$product = find_by_id('products', $product_id);

if($product){

$stock_row = find_by_sql("
SELECT 
COALESCE(SUM(
CASE
WHEN transaction_type = 1 THEN quantity
WHEN transaction_type = 2 THEN -quantity
WHEN transaction_type = 3 THEN -quantity
WHEN transaction_type = 4 THEN quantity
END
),0) AS stock
FROM transaction_master
WHERE product_id = {$product_id}
");

$current_stock = $stock_row[0]['stock'] ?? 0;

if($current_stock < $qty){
    throw new Exception("Insufficient stock for ".$product['name']);
}

    /* Step 1: Finished Goods minus */

    
    $db->query("
INSERT INTO transaction_master
(
product_id,
supplier_id,
bill_indent_no,
entry_date,
bill_indent_date,
quantity,
free_qty,
unit,
rate_id,
gst_id,
unit_price,
gst_amount,
discount_amount,
net_price,
mrp,
misc_amount,
sale_amount,
sale_gst,
sale_net,
transaction_type,
status,
payment_status,
payment_mode,
amount_received,
balance_amount,
from_dept,
to_dept,
comments,
created_at
)
VALUES
(
'$product_id',
NULL,
'$invoice_no',
NOW(),
NOW(),
'$qty',
0,
'PCS',
0,
0,
0,
0,
0,
0,
0,
0,
0,
0,
0,
2,
1,
0,
NULL,
0,
0,
'STORE',
'CUSTOMER',
'Quotation Convert Sale',
NOW()
)
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



}
    }
}
}



/* Redirect to Invoice Print */
$db->query("COMMIT");

ob_clean();

header("Location: invoice_print.php?id=".$new_invoice_id);
exit;

}catch(Exception $e){

    $db->query("ROLLBACK");

    $msg = addslashes($e->getMessage());

    echo "
    <script>

    alert('$msg');

    window.location.href='quotation_list.php';

    </script>
    ";

    exit;

}
