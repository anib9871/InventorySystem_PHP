<?php
$page_title = 'Rate Master';
require_once('includes/load.php');
//page_require_level(2);

/* PRODUCT LIST */
$products = find_by_sql("SELECT id, name FROM products ORDER BY name");

/* GST LIST */
$gst_list = find_by_sql("SELECT id, gst_name, gst_percent FROM gst_master WHERE status = 1 ORDER BY gst_percent");

/* ================= SAVE ================= */
if(isset($_POST['save'])){

    $product_id = (int)$_POST['product_id'];
    $rate = $_POST['rate'];
    $mrp = $_POST['mrp'];
    $gst_id = ($gst_enabled == "Yes")
          ? (int)$_POST['gst_id']
          : 0;


    //$gst_slab = $_POST['gst_slab'];
    $rate_type = ($gst_enabled == "Yes")
    ? $_POST['rate_type']
    : 'EXCLUSIVE';

$rate_type_outgoing = ($gst_enabled == "Yes")
    ? $_POST['rate_type_outgoing']
    : 'EXCLUSIVE';
    if($rate_type == "NO_GST"){
   $gst_id = 0;
}

    $batch = ($expiry_required == "Yes")
    ? $_POST['batch']
    : '';

$expiry = ($expiry_required == "Yes" && !empty($_POST['expiry']))
    ? $_POST['expiry']
    : NULL;
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

if(isset($_GET['bulk_type'])){

    $type = $_GET['bulk_type'];

    $db->query("UPDATE rate_master 
                SET rate_type = '$type',
                    rate_type_outgoing = '$type'");

    $session->msg("s","All rates updated to $type");
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
    $gst_id = ($gst_enabled == "Yes")
          ? (int)$_POST['gst_id']
          : 0;

$rate_type = ($gst_enabled == "Yes")
    ? $_POST['rate_type']
    : 'EXCLUSIVE';

$rate_type_outgoing = ($gst_enabled == "Yes")
    ? $_POST['rate_type_outgoing']
    : 'EXCLUSIVE';

    if($rate_type == "NO_GST"){
   $gst_id = 0;
}
$batch = ($expiry_required == "Yes")
    ? $_POST['batch']
    : '';

$expiry = ($expiry_required == "Yes")
    ? $_POST['expiry']
    : NULL;
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

<?php if($gst_enabled == "Yes"): ?>

<label>GST</label>
<select name="gst_id" class="form-control">
<option value="">Select GST</option>
<?php foreach($gst_list as $g){ ?>
<option value="<?php echo $g['id']; ?>"
<?php if($edit && $edit['gst_id']==$g['id']) echo "selected"; ?>>
<?php echo $g['gst_name'].' ('.$g['gst_percent'].'%)'; ?>
</option>

<?php } ?>
</select><br>
<?php endif; ?>



<?php if($gst_enabled == "Yes"): ?>

<label>Incoming Rate Type</label>
<select name="rate_type" class="form-control" required>
<option value="INCLUSIVE" <?php if($edit && $edit['rate_type']=="INCLUSIVE") echo "selected"; ?>>Inclusive</option>
<option value="EXCLUSIVE" <?php if($edit && $edit['rate_type']=="EXCLUSIVE") echo "selected"; ?>>Exclusive</option>
<option value="NO_GST"
<?php if($edit && $edit['rate_type']=="NO_GST") echo "selected"; ?>>
No GST
</option>
</select><br>

<label>Outgoing Rate Type</label>
<select name="rate_type_outgoing" class="form-control" required>
<option value="INCLUSIVE" <?php if($edit && $edit['rate_type_outgoing']=="INCLUSIVE") echo "selected"; ?>>Inclusive</option>
<option value="EXCLUSIVE" <?php if($edit && $edit['rate_type_outgoing']=="EXCLUSIVE") echo "selected"; ?>>Exclusive</option>
<option value="NO_GST"
<?php if($edit && $edit['rate_type_outgoing']=="NO_GST") echo "selected"; ?>>
No GST
</option>
</select><br>

<?php endif; ?>

<?php if($expiry_required == "Yes"): ?>

<label>Batch No</label>
<input type="text" name="batch" class="form-control"
value="<?php echo $edit ? $edit['batch_no'] : ''; ?>"><br>

<label>Expiry Date</label>
<input type="date" name="expiry" class="form-control"
value="<?php echo $edit ? $edit['expiry_date'] : ''; ?>"><br>

<?php endif; ?>

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


<?php if($gst_enabled == "Yes"): ?>
<select id="bulkType" class="form-control" style="width:200px;display:inline-block;">
  <option value="">Select Type</option>
  <option value="INCLUSIVE">Inclusive</option>
  <option value="EXCLUSIVE">Exclusive</option>
  <option value="NO_GST">No GST</option>
</select>

<button onclick="bulkUpdate()" class="btn btn-primary">
  Apply to All
</button>

<?php endif; ?>

<div class="panel-body">

<div class="table-responsive">

<table class="table table-bordered table-striped" id="rateTable">

<thead>
<tr>
<th>#</th>
<th>Product</th>
<th>Rate</th>
<th>MRP</th>
<?php if($gst_enabled == "Yes"): ?>
<th>GST</th>
<?php endif; ?>
<?php if($gst_enabled == "Yes"): ?>
<th>In Type</th>
<th>Out Type</th>
<?php endif; ?>
<?php if($expiry_required == "Yes"): ?>
<th>Batch</th>
<th>Expiry</th>
<?php endif; ?>
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
<?php if($gst_enabled == "Yes"): ?>
<td>

<?php
if($r['rate_type'] == "NO_GST"){
    echo "No GST";
} else {
    echo $r['gst_name'].' ('.$r['gst_percent'].'%)';
}
?>

</td>
<?php endif; ?>
<?php if($gst_enabled == "Yes"): ?>
<td><?php echo $r['rate_type']; ?></td>
<td><?php echo $r['rate_type_outgoing']; ?></td>
<?php endif; ?>
<?php if($expiry_required == "Yes"): ?>
<td><?php echo $r['batch_no']; ?></td>
<td><?php echo $r['expiry_date']; ?></td>
<?php endif; ?>
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

// 🔍 SEARCH
document.getElementById("rateSearch").addEventListener("keyup", function() {

  let filter = this.value.toLowerCase();
  let rows = document.querySelectorAll("#rateTable tbody tr");

  rows.forEach(function(row) {
    let text = row.innerText.toLowerCase();
    row.style.display = text.includes(filter) ? "" : "none";
  });

});

// 🔥 BULK UPDATE (ALAG FUNCTION)
function bulkUpdate(){

    let type = document.getElementById("bulkType").value;

    if(type == ""){
        alert("Select type first");
        return;
    }

   if(confirm("Are you sure? All rates will be updated!")){
    window.location = "rate_master.php?bulk_type=" + type;
}
}

</script>
<style>
#rateTable th,
#rateTable td {
    white-space: nowrap;
}
</style>
<?php include_once('layouts/footer.php'); ?>
