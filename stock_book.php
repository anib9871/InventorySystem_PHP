<?php
require_once('includes/load.php');
page_require_level(2);

$products = find_all('products');

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$from_date  = $_GET['from_date'] ?? '';
$to_date    = $_GET['to_date'] ?? '';
?>

<?php include_once('layouts/header.php'); ?>

<div class="panel panel-default">
<div class="panel-heading">
<strong>Stock Book Report</strong>
</div>

<div class="panel-body">

<form method="GET">
<div class="row">

<div class="col-md-3">
<select name="product_id" class="form-control" required>
<option value="">Select Product</option>
<?php foreach($products as $p){ ?>
<option value="<?= $p['id']; ?>"
<?= ($product_id == $p['id']) ? 'selected' : ''; ?>>
<?= $p['name']; ?>
</option>
<?php } ?>
</select>
</div>

<div class="col-md-2">
<input type="date" name="from_date" class="form-control"
value="<?= $from_date ?>">
</div>

<div class="col-md-2">
<input type="date" name="to_date" class="form-control"
value="<?= $to_date ?>">
</div>

<div class="col-md-2">
<button type="submit" class="btn btn-primary">View</button>
</div>

</div>
</form>

<?php
if($product_id > 0){

    /* ===== DATE CONDITION ===== */
    $date_condition = "";

    if(!empty($from_date) && !empty($to_date)){
        $date_condition = "AND DATE(trans_date) BETWEEN '$from_date' AND '$to_date'";
    }

    /* ===== FETCH LEDGER ===== */
    $movements = find_by_sql("
    SELECT 
        trans_date,
        reference_no,
        reference_type,
        qty_in,
        qty_out
    FROM stock_ledger
    WHERE product_id = $product_id
    $date_condition
    ORDER BY trans_date ASC
    ");

    /* ===== OPENING LOGIC ===== */
    $opening_qty = 0;

    if(empty($from_date)){
        // if no date filter, start from 0 (ledger based)
        $opening_qty = 0;
    } else {
        // calculate opening before from_date
        $opening_data = find_by_sql("
            SELECT 
            SUM(qty_in) as total_in,
            SUM(qty_out) as total_out
            FROM stock_ledger
            WHERE product_id = $product_id
            AND DATE(trans_date) < '$from_date'
        ");

        if($opening_data){
            $opening_qty =
                (float)$opening_data[0]['total_in'] -
                (float)$opening_data[0]['total_out'];
        }
    }

    echo "<br><table class='table table-bordered table-sm'>";
    echo "<tr style='background:#f2f2f2;'>
    <th>Date</th>
    <th>Reference</th>
    <th>Type</th>
    <th>In</th>
    <th>Out</th>
    <th>Balance</th>
    </tr>";

    echo "<tr style='background:#f9f9f9;font-weight:bold;'>
    <td>-</td>
    <td>Opening Balance</td>
    <td>OPEN</td>
    <td>{$opening_qty}</td>
    <td>0</td>
    <td>{$opening_qty}</td>
    </tr>";

    $balance = $opening_qty;

    foreach($movements as $m){

        $balance += (float)$m['qty_in'];
        $balance -= (float)$m['qty_out'];

        $row_style = "";
        if($balance < 0){
            $row_style = "style='background:#ffe5e5; color:red; font-weight:bold;'";
        }

        echo "<tr $row_style>
        <td>".date("d-m-Y", strtotime($m['trans_date']))."</td>
        <td>{$m['reference_no']}</td>
        <td>{$m['reference_type']}</td>
        <td>{$m['qty_in']}</td>
        <td>{$m['qty_out']}</td>
        <td>{$balance}</td>
        </tr>";
    }

    echo "</table>";
}
?>

</div>
</div>

<?php include_once('layouts/footer.php'); ?>