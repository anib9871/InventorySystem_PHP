<?php
$page_title = 'BOM Master';
require_once('includes/load.php');
//page_require_level(2);

/* FETCH PRODUCTS WITH PRICE + GST + BUY TYPE */
$products = find_by_sql("
SELECT id,name,buy_price,gst_id,buy_type 
FROM products 
ORDER BY name ASC
");

/* ---------- SAVE BOM ---------- */
if(isset($_POST['save_bom'])){

    $product_id = (int)$_POST['product_id'];

    if($product_id == 0){
        $session->msg("d","Please select product");
        redirect('bom_master.php',false);
    }

    /* delete old bom first */
    $db->query("DELETE FROM bom WHERE product_id='{$product_id}'");

    /* insert new rows */
    if(!empty($_POST['raw_product_id'])){
        foreach($_POST['raw_product_id'] as $i=>$raw){

            $raw_id = (int)$raw;
            $qty = (float)$_POST['qty'][$i];

            if($raw_id>0 && $qty>0){
                $db->query("
                    INSERT INTO bom(product_id,raw_product_id,quantity)
                    VALUES('{$product_id}','{$raw_id}','{$qty}')
                ");
            }
        }
    }

    $session->msg("s","BOM saved successfully");
    redirect('bom_master.php',false);
}

/* ---------- DELETE BOM ---------- */
if(isset($_GET['delete'])){
    $pid = (int)$_GET['delete'];
    $db->query("DELETE FROM bom WHERE product_id='{$pid}'");
    $session->msg("s","BOM deleted");
    redirect('bom_master.php',false);
}

/* ---------- LOAD FOR EDIT ---------- */
$edit_pid = 0;
$edit_rows = [];

if(isset($_GET['edit'])){
    $edit_pid = (int)$_GET['edit'];

    $edit_rows = $db->query("
        SELECT raw_product_id,quantity
        FROM bom
        WHERE product_id='{$edit_pid}'
    ");
}

/* ---------- VIEW BOM ---------- */
$view_items = [];
if(isset($_GET['pid'])){
    $pid = (int)$_GET['pid'];

    $view_items = $db->query("
        SELECT 
            p.name AS raw_name,
            p.buy_price,
            p.buy_type,
            g.gst_percent,
            b.quantity
        FROM bom b
        JOIN products p ON p.id=b.raw_product_id
        LEFT JOIN gst_master g ON g.id=p.gst_id
        WHERE b.product_id='{$pid}'
    ");
}

/* ---------- BOM PRODUCT LIST ---------- */
$bom_products = $db->query("
    SELECT DISTINCT b.product_id,p.name 
    FROM bom b
    JOIN products p ON p.id=b.product_id
    ORDER BY p.name ASC
");

include_once('layouts/header.php');
?>

<div class='row'>
<div class='col-md-12'>
<?php echo display_msg($msg); ?>
</div>
</div>

<div class='row'>

<!-- ================= ADD / EDIT FORM ================= -->
<div class="col-md-6">
<div class="panel panel-default">
<div class="panel-heading">
<?php echo $edit_pid ? 'Edit BOM' : 'Create / Update BOM'; ?>
</div>

<div class="panel-body">

<form method="post">

<label>Product</label>
<select name="product_id" class="form-control" required>
<option value="">Select Product</option>

<?php foreach($products as $p): ?>
<option value="<?php echo $p['id']; ?>" <?php if($edit_pid==$p['id']) echo "selected"; ?>>
<?php echo $p['name']; ?>
</option>
<?php endforeach; ?>

</select>

<br>
<h4>Raw Materials</h4>

<div id="bom_rows">

<?php 
/* If editing — load existing rows */
if($edit_pid && $edit_rows->num_rows>0){
while($er=$edit_rows->fetch_assoc()){
?>

<div class="row bom_row" style="margin-bottom:8px">

<div class="col-md-6">
<select name="raw_product_id[]" class="form-control">
<option value="">Select Raw Material</option>
<?php foreach($products as $p): ?>
<option value="<?php echo $p['id']; ?>" <?php if($p['id']==$er['raw_product_id']) echo "selected"; ?>>
<?php echo $p['name']; ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-4">
<input type="number" step="0.01" name="qty[]" value="<?php echo $er['quantity']; ?>" class="form-control">
</div>

<div class="col-md-2">
<button type="button" class="btn btn-success addRow">+</button>
</div>

</div>

<?php } } else { ?>

<!-- Default First Row -->
<div class="row bom_row" style="margin-bottom:8px">

<div class="col-md-6">
<select name="raw_product_id[]" class="form-control">
<option value="">Select Raw Material</option>
<?php foreach($products as $p): ?>
<option value="<?php echo $p['id']; ?>"><?php echo $p['name']; ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-4">
<input type="number" step="0.01" name="qty[]" class="form-control" placeholder="Qty">
</div>

<div class="col-md-2">
<button type="button" class="btn btn-success addRow">+</button>
</div>

</div>

<?php } ?>

</div>

<br>
<button name="save_bom" class="btn btn-danger">Save BOM</button>

</form>

</div>
</div>
</div>


<!-- ================= BOM LIST ================= -->
<div class="col-md-6">
<div class="panel panel-default">
<div class="panel-heading">BOM List</div>

<div class="panel-body">

<table class="table table-bordered">
<tr>
<th>#</th>
<th>Product</th>
<th>View</th>
<th>Edit</th>
<th>Delete</th>
</tr>

<?php $i=1; while($r=$bom_products->fetch_assoc()): ?>
<tr>
<td><?php echo $i++; ?></td>
<td><?php echo $r['name']; ?></td>

<td><a class="btn btn-primary btn-xs" href="bom_master.php?pid=<?php echo $r['product_id']; ?>">View</a></td>

<td><a class="btn btn-info btn-xs" href="bom_master.php?edit=<?php echo $r['product_id']; ?>">Edit</a></td>

<td><a class="btn btn-danger btn-xs" onclick="return confirm('Delete full BOM for this product?');" href="bom_master.php?delete=<?php echo $r['product_id']; ?>">Delete</a></td>
</tr>
<?php endwhile; ?>

</table>

</div>
</div>
</div>

</div>

<!-- ================= VIEW BOM DETAILS ================= -->
<?php if(isset($_GET['pid'])): ?>
<div class="row">
<div class="col-md-10">

<div class="panel panel-info">
<div class="panel-heading">
BOM Details
<a href="bom_master.php" class="btn btn-default btn-xs pull-right">Close</a>
</div>

<div class="panel-body">

<table class="table table-bordered">
<tr>
<th>#</th>
<th>Raw Material</th>
<th>Qty</th>
<th>Unit Price (Incl GST)</th>
<th>GST %</th>
<th>Line Total</th>
</tr>

<?php
$grand_total = 0;
$i=1;

while($x=$view_items->fetch_assoc()):

$qty = (float)$x['quantity'];
$gst = (float)$x['gst_percent'];
$base_price = (float)$x['buy_price'];

/* ---------- IMPORTANT PART ---------- */
/* convert excluding → including */
if($x['buy_type'] == "exclusive"){
    $unit_price_inclusive = $base_price * (1 + ($gst/100));
} else {
    $unit_price_inclusive = $base_price;
}

/* line total */
$line_total = $unit_price_inclusive * $qty;

$grand_total += $line_total;
?>

<tr>
<td><?php echo $i++; ?></td>
<td><?php echo $x['raw_name']; ?></td>
<td><?php echo $qty; ?></td>
<td><?php echo number_format($unit_price_inclusive,2); ?></td>
<td><?php echo $gst; ?>%</td>
<td><?php echo number_format($line_total,2); ?></td>
</tr>

<?php endwhile; ?>

<tr>
<td colspan="5" align="right"><strong>Grand Total (Incl GST)</strong></td>
<td><strong><?php echo number_format($grand_total,2); ?></strong></td>
</tr>

</table>

</div>
</div>

</div>
</div>
<?php endif; ?>

<script>
/* add BOM rows */
document.addEventListener("click",function(e){
 if(e.target.classList.contains("addRow")){
   let r=document.querySelector(".bom_row").cloneNode(true);
   r.querySelectorAll("input,select").forEach(x=>x.value="");
   document.getElementById("bom_rows").appendChild(r);
 }
});
</script>

<?php include_once('layouts/footer.php'); ?>
