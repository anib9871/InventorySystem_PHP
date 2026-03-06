<?php
require_once('includes/load.php');
page_require_level(2);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($id <= 0) die("Invalid ID");

/* Fetch invoice */
$qdata = find_by_sql("SELECT * FROM invoice WHERE id = $id");
if(!$qdata) die("invoice not found");
$quote = $qdata[0];

/* Fetch items */
$items = find_by_sql("
SELECT *
FROM invoice_items
WHERE invoice_id = $id
");

$customers = find_all('customer_master');
$products  = join_product_table();

/* ================= UPDATE ================= */
if(isset($_POST['update_invoice'])){

    $cust = (int)$_POST['customer_id'];
    $gst_type = $_POST['gst_type'] ?? 'exclusive';

    $subtotal = 0;
    $net_total = 0;

    $db->query("DELETE FROM invoice_items WHERE invoice_id = $id");

    foreach($_POST['product_id'] as $i => $pid){

        $pid  = (int)$pid;
        $qty  = (float)$_POST['qty'][$i];
        $base = (float)$_POST['rate'][$i];
        $gst  = (float)$_POST['gst'][$i];
        $disc = (float)$_POST['discount'][$i];

        if($pid <= 0 || $qty <= 0) continue;

        $line_base = $qty * $base;
        $discounted_base = $line_base - $disc;

        if($gst_type == "exclusive"){
            $gst_amount = $discounted_base * $gst / 100;
            $rate_incl  = $base + ($base * $gst / 100);
            $line_total = $discounted_base + $gst_amount;
        } else {
            $gst_amount = $discounted_base - ($discounted_base / (1 + $gst/100));
            $rate_incl  = $base;
            $line_total = $discounted_base;
        }

        $subtotal  += $discounted_base;
        $net_total += $line_total;

        $db->query("
        INSERT INTO invoice_items
        (invoice_id, product_id, qty, rate_excl_gst,
         discount_amount, gst_percent, rate_incl_gst, line_total)
        VALUES
        ($id, $pid, $qty, $base,
         $disc, $gst, $rate_incl, $line_total)
        ");
    }

    $gst_total = $net_total - $subtotal;

    $db->query("
    UPDATE invoice SET
    customer_id = '$cust',
    gst_type = '$gst_type',
    subtotal = '$subtotal',
    gst_total = '$gst_total',
    net_total = '$net_total'
    WHERE id = '$id'
    ");

    echo "<script>
    window.location='invoice_list.php?print_id=".$id."';
    </script>";
}
?>

<?php include_once('layouts/header.php'); ?>

<div class="card shadow-sm">
<div class="card-header bg-white">
<h4>Edit invoice</h4>
</div>

<div class="card-body">
<form method="post">

<!-- CUSTOMER -->
<label>Customer</label>
<select name="customer_id" class="form-control mb-3" required>
<?php foreach($customers as $c){ ?>
<option value="<?=$c['id'];?>"
<?php if($c['id']==$quote['customer_id']) echo "selected"; ?>>
<?=$c['customer_name'];?>
</option>
<?php } ?>
</select>

<!-- GST TYPE -->
<div class="mb-3">
<label>
<input type="radio" name="gst_type" value="exclusive"
<?php if($quote['gst_type']=="exclusive") echo "checked"; ?>>
Exclusive
</label>

<label class="ms-3">
<input type="radio" name="gst_type" value="inclusive"
<?php if($quote['gst_type']=="inclusive") echo "checked"; ?>>
Inclusive
</label>
</div>

<table class="table table-bordered">
<thead>
<tr>
<th>Product</th>
<th>Qty</th>
<th>Rate</th>
<th>GST%</th>
<th>Discount</th>
<th>Total</th>
</tr>
</thead>

<tbody>
<?php foreach($items as $it){ ?>
<tr>
<td>
<select name="product_id[]" class="form-control">
<?php foreach($products as $p){ ?>
<option value="<?=$p['id'];?>"
<?php if($p['id']==$it['product_id']) echo "selected"; ?>>
<?=$p['name'];?>
</option>
<?php } ?>
</select>
</td>

<td>
<input type="number" name="qty[]" class="form-control"
value="<?=$it['qty'];?>">
</td>

<td>
<input type="number" name="rate[]" class="form-control"
value="<?=$it['rate_excl_gst'];?>">
</td>

<td>
<input type="number" name="gst[]" class="form-control"
value="<?=$it['gst_percent'];?>">
</td>

<td>
<input type="number" name="discount[]" class="form-control"
value="<?=$it['discount_amount'];?>">
</td>

<td>
₹ <?=number_format($it['line_total'],2);?>
</td>
</tr>
<?php } ?>
</tbody>
</table>

<div class="text-end mt-3">
<button type="submit" name="update_invoice"
class="btn btn-success">
Update invoice
</button>
</div>

</form>
</div>
</div>

<?php include_once('layouts/footer.php'); ?>