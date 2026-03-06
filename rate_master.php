<?php
$page_title = 'Rate Master';
require_once('includes/load.php');
page_require_level(2);

/* PRODUCT LIST */
$products = find_by_sql("SELECT id, name FROM products ORDER BY name");

/* GST LIST */
$gst_list = find_by_sql("SELECT id, gst_name, gst_percent FROM gst_master WHERE status = 1 ORDER BY gst_percent");

/* ================= SAVE ================= */
if(isset($_POST['save'])){

    $product_id = (int)$_POST['product_id'];
    $rate = $_POST['rate'];
    $mrp = $_POST['mrp'];
    $gst_id = (int)$_POST['gst_id'];
    //$gst_slab = $_POST['gst_slab'];
    $rate_type = $_POST['rate_type']; // INCLUSIVE / EXCLUSIVE (Incoming)
    $rate_type_outgoing = $_POST['rate_type_outgoing']; // INCLUSIVE / EXCLUSIVE
    $batch = $_POST['batch'];
    $expiry = !empty($_POST['expiry']) ? $_POST['expiry'] : NULL;
    $price_date = $_POST['price_date'];

    $sql = "INSERT INTO rate_master
    (product_id, rate, mrp, gst_id, rate_type, rate_type_outgoing, batch_no, expiry_date, price_date)
    VALUES
    (
    '$product_id','$rate','$mrp','$gst_id',
     '$rate_type','$rate_type_outgoing','$batch',".($expiry !==null ? "'$expiry'" : "NULL").",'$price_date'
     )";

    if($db->query($sql)){
        $session->msg("s","Rate added successfully");
    } else {
        $session->msg("d","Failed to add rate");
    }
    redirect('rate_master.php',false);
}

/* ================= DELETE ================= */
if(isset($_GET['del'])){
    $id = (int)$_GET['del'];
    $db->query("DELETE FROM rate_master WHERE id='$id'");
    $session->msg("s","Rate deleted");
    redirect('rate_master.php',false);
}

/* ================= EDIT LOAD ================= */
$edit = false;
if(isset($_GET['edit'])){
    $id = (int)$_GET['edit'];
    $edit = find_by_id("rate_master",$id);

      // SAFETY CHECK (IMPORTANT)
    if(!$edit){
        $session->msg("d","Invalid Rate ID");
        redirect('rate_master.php');
    }
}

/* ================= UPDATE ================= */
if(isset($_POST['update'])){

    $id = (int)$_POST['id'];

    $product_id = (int)$_POST['product_id'];
    $rate = $_POST['rate'];
    $mrp = $_POST['mrp'];
    $gst_id = (int)$_POST['gst_id'];
    $rate_type = $_POST['rate_type'];
    $rate_type_outgoing = $_POST['rate_type_outgoing'];
    $batch = $_POST['batch'];
    $expiry = $_POST['expiry'];
    $price_date = $_POST['price_date'];

    $sql = "UPDATE rate_master SET
        product_id='$product_id',
        rate='$rate',
        mrp='$mrp',
        gst_id='$gst_id',
        rate_type='$rate_type',
        rate_type_outgoing='$rate_type_outgoing',
        batch_no='$batch',
        expiry_date=".($expiry ? "'$expiry'" : "NULL").",
        price_date='$price_date'
        WHERE id='$id'";

    if($db->query($sql)){
        $session->msg("s","Rate updated successfully");
    } else {
        $session->msg("d","Update failed");
    }
    redirect('rate_master.php',false);
}

/* ================= LIST ================= */
$rates = find_by_sql("
SELECT 
  r.*, 
  p.name AS product_name,
  g.gst_name,
  g.gst_percent
FROM rate_master r
LEFT JOIN products p ON p.id = r.product_id
LEFT JOIN gst_master g ON g.id = r.gst_id
ORDER BY r.id DESC

");

include_once('layouts/header.php');
?>

<div class="row">

<!-- ================= FORM ================= -->
<div class="col-md-4">
<div class="panel panel-default">
<div class="panel-heading">Add / Edit Rate</div>
<div class="panel-body">

<form method="post">

<?php if($edit){ ?>
<input type="hidden" name="id" value="<?php echo $edit['id']; ?>">
<?php } ?>

<label>Product</label>
<select name="product_id" class="form-control" required>
<option value="">Select Product</option>
<?php foreach($products as $p){ ?>
<option value="<?php echo $p['id']; ?>"
<?php if($edit && $edit['product_id']==$p['id']) echo "selected"; ?>>
<?php echo $p['name']; ?>
</option>
<?php } ?>
</select><br>

<label>Rate</label>
<input type="number" step="0.01" name="rate" class="form-control"
value="<?php echo $edit ? $edit['rate'] : ''; ?>" required><br>

<label>MRP</label>
<input type="number" step="0.01" name="mrp" class="form-control"
value="<?php echo $edit ? $edit['mrp'] : ''; ?>" required><br>

<label>GST</label>
<select name="gst_id" class="form-control" required>
<option value="">Select GST</option>
<?php foreach($gst_list as $g){ ?>
<option value="<?php echo $g['id']; ?>"
<?php if($edit && $edit['gst_id']==$g['id']) echo "selected"; ?>>
<?php echo $g['gst_name'].' ('.$g['gst_percent'].'%)'; ?>
</option>

<?php } ?>
</select><br>



<label>Incoming Rate Type</label>
<select name="rate_type" class="form-control" required>
<option value="INCLUSIVE" <?php if($edit && $edit['rate_type']=="INCLUSIVE") echo "selected"; ?>>Inclusive</option>
<option value="EXCLUSIVE" <?php if($edit && $edit['rate_type']=="EXCLUSIVE") echo "selected"; ?>>Exclusive</option>
</select><br>

<label>Outgoing Rate Type</label>
<select name="rate_type_outgoing" class="form-control" required>
<option value="INCLUSIVE" <?php if($edit && $edit['rate_type_outgoing']=="INCLUSIVE") echo "selected"; ?>>Inclusive</option>
<option value="EXCLUSIVE" <?php if($edit && $edit['rate_type_outgoing']=="EXCLUSIVE") echo "selected"; ?>>Exclusive</option>
</select><br>

<label>Batch No</label>
<input type="text" name="batch" class="form-control"
value="<?php echo $edit ? $edit['batch_no'] : ''; ?>"><br>

<label>Expiry Date</label>
<input type="date" name="expiry" class="form-control"
value="<?php echo $edit ? $edit['expiry_date'] : ''; ?>"><br>

<label>Price Date</label>
<input type="date" name="price_date" class="form-control"
value="<?php echo $edit ? $edit['price_date'] : date('Y-m-d'); ?>" required><br>

<?php if($edit){ ?>
<button name="update" class="btn btn-danger">Update</button>
<a href="rate_master.php" class="btn btn-secondary">Cancel</a>
<?php } else { ?>
<button name="save" class="btn btn-danger">Save</button>
<button type="reset" class="btn btn-secondary">Clear</button>
<?php } ?>

</form>

</div>
</div>
</div>

<!-- ================= LIST ================= -->
<div class="col-md-8">
<div class="panel panel-default">
<div class="panel-heading">Rate List</div>

<input type="text" id="rateSearch" class="form-control"
placeholder="Search by Product / GST / Batch / Rate"
style="margin-bottom:10px;">

<div class="panel-body">

<div class="table-responsive">

<table class="table table-bordered table-striped" id="rateTable">

<thead>
<tr>
<th>#</th>
<th>Product</th>
<th>Rate</th>
<th>MRP</th>
<th>GST</th>
<th>In Type</th>
<th>Out Type</th>
<th>Batch</th>
<th>Expiry</th>
<th>Price Date</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php foreach($rates as $i=>$r){ ?>
<tr>
<td><?php echo $i+1; ?></td>
<td><?php echo $r['product_name']; ?></td>
<td><?php echo $r['rate']; ?></td>
<td><?php echo $r['mrp']; ?></td>
<td><?php echo $r['gst_name'].' ('.$r['gst_percent'].'%)'; ?></td>
<td><?php echo $r['rate_type']; ?></td>
<td><?php echo $r['rate_type_outgoing']; ?></td>
<td><?php echo $r['batch_no']; ?></td>
<td><?php echo $r['expiry_date']; ?></td>
<td><?php echo $r['price_date']; ?></td>

<td>
<a href="rate_master.php?edit=<?php echo $r['id']; ?>" 
class="btn btn-xs btn-info">Edit</a>

<a onclick="return confirm('Delete karna sure?')"
href="rate_master.php?del=<?php echo $r['id']; ?>"
class="btn btn-xs btn-danger">Delete</a>
</td>
</tr>
<?php } ?>

</tbody>
</table>

</div> <!-- table-responsive -->

</div> <!-- panel-body -->
</div>
</div>
<script>
document.getElementById("rateSearch").addEventListener("keyup", function() {

  let filter = this.value.toLowerCase();
  let rows = document.querySelectorAll("#rateTable tbody tr");

  rows.forEach(function(row) {
    let text = row.innerText.toLowerCase();
    row.style.display = text.includes(filter) ? "" : "none";
  });

});
</script>
<style>
#rateTable th,
#rateTable td {
    white-space: nowrap;
}
</style>
<?php include_once('layouts/footer.php'); ?>
