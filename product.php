<?php
$page_title = 'Products';
require_once('includes/load.php');
//page_require_level(2);



/* ========= AJAX DUPLICATE NAME CHECK ========= */
if(isset($_GET['check_name'])){
  $name = strtolower(trim($_GET['check_name']));
  $result = find_by_sql("SELECT id FROM products WHERE LOWER(TRIM(name))='{$name}' LIMIT 1");
  echo (!empty($result)) ? "exists" : "ok";
  exit;
}


/* ========= LOAD MASTER DATA ========= */
$products   = join_product_table();
$categories = find_all('categories');


/* ========= EDIT MODE LOAD ========= */
$edit = null;
if(isset($_GET['edit'])){
  $edit = find_by_id("products",(int)$_GET['edit']);
}


/* ========= ADD PRODUCT ========= */
if(isset($_POST['add_product'])){

  $name = remove_junk($db->escape($_POST['product-title']));
  $cat  = (int)$_POST['product-categorie'];
  $buy  = (float)$_POST['buying-price'];
  $sell = (float)$_POST['saleing-price'];
  $gst  = (int)$_POST['gst_id'];
  $hsn = remove_junk($db->escape($_POST['hsn_code']));

  $buy_type  = $_POST['buy_type'];
  $sell_type = $_POST['sell_type'];

  $is_bom = isset($_POST['is_bom']) ? 1 : 0;

  $check = find_by_sql("SELECT id FROM products WHERE name='{$name}' LIMIT 1");
  if($check){
    $session->msg("d","Product already exists");
    redirect('product.php',false);
  }

  $db->query("
   INSERT INTO products
(name,buy_price,sale_price,buy_type,sell_type,gst_id,categorie_id,is_bom,hsn_code,date)
    VALUES
    ('{$name}','{$buy}','{$sell}','{$buy_type}','{$sell_type}','{$gst}','{$cat}','{$is_bom}','{$hsn}',NOW())
  ");

  $session->msg("s","Product added successfully");
  redirect('product.php',false);
}


/* ========= UPDATE PRODUCT ========= */
if(isset($_POST['update_product'])){

  $id = (int)$_POST['product_id'];

  $name = remove_junk($db->escape($_POST['product-title']));
  $cat  = (int)$_POST['product-categorie'];
  $buy  = (float)$_POST['buying-price'];
  $sell = (float)$_POST['saleing-price'];
  $gst  = (int)$_POST['gst_id'];
  $hsn = remove_junk($db->escape($_POST['hsn_code']));

  $buy_type  = $_POST['buy_type'];
  $sell_type = $_POST['sell_type'];

  $is_bom = isset($_POST['is_bom']) ? 1 : 0;

  $db->query("
    UPDATE products SET
      name='{$name}',
      buy_price='{$buy}',
      sale_price='{$sell}',
      buy_type='{$buy_type}',
      sell_type='{$sell_type}',
      gst_id='{$gst}',
      categorie_id='{$cat}',
      is_bom='{$is_bom}',
      hsn_code='{$hsn}'
    WHERE id='{$id}'
  ");

  $session->msg("s","Product updated");
  redirect('product.php',false);
}


include_once('layouts/header.php');
?>


<div class="row">
<div class="col-md-12">
<?php echo display_msg($msg); ?>
</div>
</div>


<div class="row">



<!-- ================= LEFT FORM ================= -->
<div class="col-md-4">

<div class="panel panel-default">
<div class="panel-heading">
<strong><?php echo $edit ? "Edit Product" : "Add Product"; ?></strong>
</div>

<div class="panel-body">

<form method="post">

<input type="hidden" name="product_id" value="<?php echo $edit['id'] ?? ''; ?>">

<input id="product_title" name="product-title" class="form-control"
 placeholder="Product Name" value="<?php echo $edit['name'] ?? ''; ?>" required>

<small id="name_status"></small>
<br>


<!-- CATEGORY -->
<select name="product-categorie" class="form-control" required>
<option value="">Select Category</option>
<?php foreach($categories as $c): ?>
<option value="<?php echo $c['id']; ?>" 
<?php if($edit && $edit['categorie_id']==$c['id']) echo "selected"; ?>>
<?php echo $c['name']; ?>
</option>
<?php endforeach; ?>
</select>
<br>


<!-- GST -->
<select name="gst_id" id="gst_id" class="form-control">
<option value="">Select GST</option>
<?php foreach(find_all("gst_master") as $g): ?>
<option value="<?php echo $g['id']; ?>"
<?php if($edit && $edit['gst_id']==$g['id']) echo "selected"; ?>>
<?php echo $g['gst_percent']; ?>%
</option>
<?php endforeach; ?>
</select>
<br>

<!-- HSN CODE -->
<input type="text"
       name="hsn_code"
       class="form-control"
       placeholder="HSN Code"
       value="<?php echo $edit['hsn_code'] ?? ''; ?>">

<br>


<!-- BUYING -->
<label>Buying Price</label>
<div class="input-group">
<input type="number" step="0.01" id="buy_price"
 name="buying-price" class="form-control"
 value="<?php echo $edit['buy_price'] ?? ''; ?>">

<select name="buy_type" id="buy_type" class="form-control">
<option value="exclusive" <?php if($edit && $edit['buy_type']=="exclusive") echo "selected"; ?>>Excluding GST</option>
<option value="inclusive" <?php if($edit && $edit['buy_type']=="inclusive") echo "selected"; ?>>Including GST</option>
</select>
</div>
<br>


<!-- SELLING -->
<label>Selling Price</label>
<div class="input-group">
<input type="number" step="0.01" id="sell_price"
 name="saleing-price" class="form-control"
 value="<?php echo $edit['sale_price'] ?? ''; ?>">

<select name="sell_type" id="sell_type" class="form-control">
<option value="exclusive" <?php if($edit && $edit['sell_type']=="exclusive") echo "selected"; ?>>Excluding GST</option>
<option value="inclusive" <?php if($edit && $edit['sell_type']=="inclusive") echo "selected"; ?>>Including GST</option>
</select>
</div>
<br>


<!-- BOM -->
<label>
<input type="checkbox" name="is_bom"
<?php if($edit && $edit['is_bom']==1) echo "checked"; ?>>
This product is manufactured (BOM)
</label>

<br><br>


<?php if($edit): ?>
<button name="update_product" class="btn btn-danger btn-block">Update</button>
<a href="product.php" class="btn btn-secondary btn-block">Cancel</a>

<?php else: ?>
<button id="save_btn" name="add_product" class="btn btn-danger btn-block">Save</button>
<button type="reset" class="btn btn-secondary btn-block">Clear</button>
<?php endif; ?>

</form>

</div>
</div>
</div>





<!-- ================= RIGHT TABLE ================= -->
<div class="col-md-8">
<div class="panel panel-default">

<div class="panel-heading"><strong>Product List</strong></div>

<input id="search" class="form-control" placeholder="Search..."><br>

<div class="panel-body">

<table class="table table-bordered" id="productTable">

<tr>
<th>#</th>
<th>Name</th>
<th>Category</th>
<th>Selling</th>
<th>HSN</th>
<th>Action</th>
</tr>

<?php foreach($products as $p): ?>
<tr>
<td><?php echo count_id(); ?></td>
<td><?php echo $p['name']; ?></td>
<td><?php echo $p['categorie']; ?></td>
<td>₹ <?php echo number_format($p['sale_price'],2); ?></td>
<td><?php echo $p['hsn_code']; ?></td>

<td>
<a href="product.php?edit=<?php echo $p['id']; ?>" class="btn btn-info btn-xs">Edit</a>
<a href="delete_product.php?id=<?php echo $p['id']; ?>" class="btn btn-danger btn-xs">Delete</a>
</td>
</tr>
<?php endforeach; ?>

</table>

</div>
</div>
</div>
</div>



<script>
/* auto focus */
document.getElementById("product_title").focus();

/* duplicate check */
let title=document.getElementById("product_title");
let saveBtn=document.getElementById("save_btn");

title.addEventListener("keyup",()=>{
 let name=title.value.trim();
 if(name===""){ saveBtn.disabled=false; return; }

 fetch("product.php?check_name="+name)
 .then(r=>r.text())
 .then(d=>{
   if(d.includes("exists")){
     saveBtn.disabled=true;
     document.getElementById("name_status").innerHTML="❌ Already exists";
   } else {
     saveBtn.disabled=false;
     document.getElementById("name_status").innerHTML="✔ Available";
   }
 })
});


/* search filter */
document.getElementById("search").addEventListener("keyup", function() {
 let val = this.value.toLowerCase();
 document.querySelectorAll("#productTable tr").forEach(function(r){
   r.style.display = r.textContent.toLowerCase().includes(val) ? "" : "none";
 });
});
</script>

<?php include_once('layouts/footer.php'); ?>
