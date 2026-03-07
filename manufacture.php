<?php
require_once('includes/load.php');
page_require_level(2);
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

$products = find_by_sql("SELECT id, name FROM products WHERE is_bom = 1");

/* ===============================
   SAVE MANUFACTURE
================================ */
if(isset($_POST['save_manufacture'])){

    global $db;

    $product_id = (int)$_POST['product_id'];
    $qty        = (float)$_POST['qty'];

    if($product_id <= 0 || $qty <= 0){
        die("Invalid Data");
    }

    $ref_no = "MFG".time();

    $db->query("START TRANSACTION");

    /* ===== Fetch BOM ===== */
    $bom_items = find_by_sql("
        SELECT raw_product_id, quantity
        FROM bom
        WHERE product_id = {$product_id}
    ");

    if(!$bom_items){
        $db->query("ROLLBACK");
        die("No BOM found for this product");
    }

    foreach($bom_items as $b){

        $raw_id  = (int)$b['raw_product_id'];
        $per_unit = (float)$b['quantity'];

        $total_required = $per_unit * $qty;

        /* ===== Ledger Based Stock Check ===== */
        $stock_data = find_by_sql("
            SELECT IFNULL(SUM(qty_in),0) - IFNULL(SUM(qty_out),0) as current_stock
            FROM stock_ledger
            WHERE product_id = {$raw_id}
        ");

        $current_stock = (float)$stock_data[0]['current_stock'];

        if($current_stock < $total_required){
            $db->query("ROLLBACK");
            die("Insufficient stock for raw material ID ".$raw_id);
        }

        /* ===== Deduct Raw Material ===== */
        if(!$db->query("
            INSERT INTO stock_ledger
            (product_id, reference_no, reference_type, trans_date, qty_in, qty_out, created_at)
            VALUES
            ({$raw_id}, '{$ref_no}', 'MANUFACTURE-RAW', NOW(), 0, {$total_required}, NOW())
        ")){
            $db->query("ROLLBACK");
            die("Raw material deduction failed");
        }
    }

    /* ===== Add Finished Goods ===== */
    if(!$db->query("
        INSERT INTO stock_ledger
        (product_id, reference_no, reference_type, trans_date, qty_in, qty_out, created_at)
        VALUES
        ({$product_id}, '{$ref_no}', 'MANUFACTURE-FG', NOW(), {$qty}, 0, NOW())
    ")){
        $db->query("ROLLBACK");
        die("Finished goods insert failed");
    }

    $db->query("COMMIT");

    echo "<script>alert('Manufacturing Successful'); window.location='manufacture.php';</script>";
}
?>

<?php include_once('layouts/header.php'); ?>

<div class="row">
<div class="col-md-6">
<div class="panel panel-default">
<div class="panel-heading">
<strong>Manufacture Device</strong>
</div>

<div class="panel-body">

<form method="post">

<div class="form-group">
<label>Select Device</label>
<select name="product_id" class="form-control" required>
<option value="">Select</option>
<?php foreach($products as $p){ ?>
<option value="<?= $p['id']; ?>">
<?= $p['name']; ?>
</option>
<?php } ?>
</select>
</div>

<div class="form-group">
<label>Quantity</label>
<input type="number" name="qty" step="0.01" class="form-control" required>
</div>

<br>

<button type="submit" name="save_manufacture" class="btn btn-success">
Manufacture
</button>

</form>

</div>
</div>
</div>
</div>

<?php include_once('layouts/footer.php'); ?>
