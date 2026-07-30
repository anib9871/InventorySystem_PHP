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
  $type = (int)$_POST['type']; 
  $sell = (float)$_POST['saleing-price'];
  $gst = ($gst_enabled == "Yes")
       ? (int)$_POST['gst_id']
       : 0;
  $hsn = isset($_POST['hsn_code'])
   ? remove_junk($db->escape($_POST['hsn_code']))
   : '';

  $buy_type = ($gst_enabled == "Yes")
      ? $_POST['buy_type']
      : 'exclusive';

  $sell_type = ($gst_enabled == "Yes")
      ? $_POST['sell_type']
      : 'exclusive';

  $is_bom = isset($_POST['is_bom']) ? 1 : 0;
  $is_active = isset($_POST['is_active']) ? 1 : 0;
  $reorder = isset($_POST['reorder_level'])
   ? (int)$_POST['reorder_level']
   : 0;

  $website_link = remove_junk($db->escape($_POST['website_link']));

  $check = find_by_sql("SELECT id FROM products WHERE name='{$name}' LIMIT 1");
  if($check){
    $_SESSION['swal_error'] = "Product already exists!";
    redirect('product.php',false);
  }

  $db->query("
  INSERT INTO products
  (name,buy_price,sale_price,buy_type,sell_type,gst_id,categorie_id,is_bom, is_active, hsn_code,type,reorder_level,website_link,date)
  VALUES
  ('{$name}','{$buy}','{$sell}','{$buy_type}','{$sell_type}','{$gst}','{$cat}','{$is_bom}',
  '{$is_active}','{$hsn}','{$type}','{$reorder}','{$website_link}',NOW())
  ");

  $product_id = $db->insert_id();

  $incoming_type = strtoupper($buy_type);
  $outgoing_type = strtoupper($sell_type);

  $db->query("
  INSERT INTO rate_master
  (
      product_id,
      rate,
      mrp,
      gst_id,
      rate_type,
      rate_type_outgoing,
      price_date
  )
  VALUES
  (
      '{$product_id}',
      '{$buy}',
      '0',
      '{$gst}',
      '{$incoming_type}',
      '{$outgoing_type}',
      CURDATE()
  )
  ");

  $_SESSION['swal_success'] = "Product added successfully";
  redirect('product.php',false);
}


/* ========= UPDATE PRODUCT ========= */
if(isset($_POST['update_product'])){

  $id = (int)$_POST['product_id'];
  $name = remove_junk($db->escape($_POST['product-title']));
  $cat  = (int)$_POST['product-categorie'];
  $buy  = (float)$_POST['buying-price'];
  $type = (int)$_POST['type']; 
  $sell = (float)$_POST['saleing-price'];
  $gst = ($gst_enabled == "Yes")
       ? (int)$_POST['gst_id']
       : 0;
  $hsn = remove_junk($db->escape($_POST['hsn_code']));

  $buy_type = ($gst_enabled == "Yes")
      ? $_POST['buy_type']
      : 'exclusive';

  $sell_type = ($gst_enabled == "Yes")
      ? $_POST['sell_type']
      : 'exclusive';

  $is_bom = isset($_POST['is_bom']) ? 1 : 0;
  $is_active = isset($_POST['is_active']) ? 1 : 0;
  $reorder = (int)$_POST['reorder_level'];
  $website_link = remove_junk($db->escape($_POST['website_link']));

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
      is_active='{$is_active}',
      hsn_code='{$hsn}',
      type='{$type}',
      reorder_level='{$reorder}',
      website_link='{$website_link}'
    WHERE id='{$id}'
  ");

  $incoming_type = strtoupper($buy_type);
  $outgoing_type = strtoupper($sell_type);

  $db->query("
  UPDATE rate_master SET
      rate = '{$buy}',
      gst_id = '{$gst}',
      rate_type = '{$incoming_type}',
      rate_type_outgoing = '{$outgoing_type}'
  WHERE product_id = '{$id}'
  ");

  $_SESSION['swal_success'] = "Product updated successfully";
  redirect('product.php',false);
}


include_once('layouts/header.php');
?>

<style>
/* Base Panel Styling matching Organization Master */
.panel {
    border-radius: 4px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    margin-bottom: 20px;
    background: #fff;
}

.panel-heading {
    background: #fff !important;
    color: #1f2937 !important;
    font-size: 14px;
    font-weight: 700;
    text-transform: uppercase;
    border-bottom: 2px solid #2f80ed !important;
    padding: 12px 20px !important;
    letter-spacing: 0.5px;
}

.panel-heading i {
    color: #2f80ed;
    margin-right: 8px;
    font-size: 16px;
}

.panel-body {
    padding: 20px !important;
}

/* Label & Input Styling */
label {
    font-size: 11px !important;
    font-weight: 700 !important;
    color: #4b5563 !important;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
    display: block;
}

.form-control {
    height: 38px !important;
    font-size: 13px !important;
    border-radius: 4px !important;
    border: 1px solid #d1d5db !important;
    box-shadow: none !important;
    color: #374151 !important;
}

.form-control:focus {
    border-color: #2f80ed !important;
}

.form-group {
    margin-bottom: 15px;
}

/* Action Buttons */
.save-btn {
    background: #00a65a !important;
    border-color: #00a65a !important;
    color: #fff !important;
    border-radius: 4px !important;
    padding: 8px 20px !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    text-transform: uppercase;
    transition: all 0.2s;
}

.save-btn:hover {
    background: #008d4c !important;
    border-color: #008d4c !important;
}

.btn-clear {
    background: #fff !important;
    border: 1px solid #d1d5db !important;
    color: #374151 !important;
    border-radius: 4px !important;
    padding: 8px 20px !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    text-transform: uppercase;
    margin-right: 8px;
}

.btn-clear:hover {
    background: #f9fafb !important;
    border-color: #9ca3af !important;
}

/* Table Design */
#productTable {
    margin-bottom: 0;
}

#productTable thead th {
    background: #1e293b !important;
    color: #ffffff !important;
    border: none !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px 10px !important;
    vertical-align: middle;
}

#productTable tbody td {
    font-size: 13px;
    color: #334155;
    vertical-align: middle !important;
    padding: 10px !important;
    border-color: #f1f5f9;
}

#productTable tbody tr:hover {
    background-color: #f8fafc;
}

#productTable .btn-xs {
    width: 28px;
    height: 28px;
    line-height: 28px;
    padding: 0;
    text-align: center;
    border-radius: 4px;
    margin: 0 2px;
}

/* Badges */
.label-success {
    background-color: #22c55e !important;
    font-weight: 600;
    padding: 4px 8px;
    border-radius: 4px;
}

.label-danger {
    background-color: #ef4444 !important;
    font-weight: 600;
    padding: 4px 8px;
    border-radius: 4px;
}

.checkbox-inline, .checkbox label {
    font-size: 12px !important;
    font-weight: 600 !important;
    color: #374151;
    text-transform: none;
}
</style>


<div class="row">
    <div class="col-md-12">
        <?php echo display_msg($msg); ?>
    </div>
</div>


<div class="row">

<!-- ================= TOP FORM ================= -->
<div class="col-md-12">

    <div class="panel panel-default">
        <div class="panel-heading">
            <i class="glyphicon glyphicon-shopping-cart"></i>
            PRODUCT
        </div>

        <div class="panel-body">

        <form method="post">

        <input type="hidden" name="product_id" value="<?php echo $edit['id'] ?? ''; ?>">

        <div class="row">

            <!-- Product Name -->
            <div class="col-md-4">
                <div class="form-group">
                    <label>Product Name</label>
                    <input
                        id="product_title"
                        name="product-title"
                        class="form-control"
                        placeholder="e.g. Refricheck"
                        value="<?php echo $edit['name'] ?? ''; ?>"
                        required>
                    <small id="name_status"></small>
                </div>
            </div>

            <!-- Category -->
            <div class="col-md-2">
                <div class="form-group">
                    <label>Category</label>
                    <select name="product-categorie" class="form-control" required>
                        <option value="">Select</option>
                        <?php foreach($categories as $c): ?>
                        <option value="<?php echo $c['id']; ?>"
                        <?php
                        if($edit){
                            if($edit['categorie_id']==$c['id']) echo "selected";
                        }else{
                            if($c['id']==1) echo "selected";
                        }
                        ?>>
                        <?php echo $c['name']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Type -->
            <div class="col-md-2">
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" class="form-control">
                        <option value="1" <?php if($edit && $edit['type']==1) echo "selected"; ?>>Product</option>
                        <option value="2" <?php if($edit && $edit['type']==2) echo "selected"; ?>>Service</option>
                    </select>
                </div>
            </div>

        <?php if($gst_enabled=="Yes"): ?>

            <!-- GST -->
            <div class="col-md-2">
                <div class="form-group">
                    <label>GST</label>
                    <select name="gst_id" id="gst_id" class="form-control">
                        <option value="">Select</option>
                        <?php foreach(find_all("gst_master") as $g): ?>
                        <option value="<?php echo $g['id']; ?>" <?php if($edit && $edit['gst_id']==$g['id']) echo "selected"; ?>>
                        <?php echo $g['gst_name']; ?> (<?php echo $g['gst_percent']; ?>%)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- HSN -->
            <div class="col-md-2">
                <div class="form-group">
                    <label>HSN Code</label>
                    <input
                        type="text"
                        name="hsn_code"
                        class="form-control"
                        value="<?php echo $edit['hsn_code'] ?? ''; ?>">
                </div>
            </div>

        <?php endif; ?>

        </div>


        <div class="row">

            <!-- Buying Price -->
            <div class="col-md-3">
                <div class="form-group">
                    <label>Buying Price</label>
                    <div class="input-group">
                        <input
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            id="buy_price"
                            name="buying-price"
                            class="form-control"
                            oninput="this.value=this.value.replace(/[^0-9.]/g,'').replace(/(\..*)\./g,'$1');"
                            value="<?php echo $edit['buy_price'] ?? ''; ?>">

                        <?php if($gst_enabled=="Yes"): ?>
                        <span class="input-group-btn">
                            <select name="buy_type" id="buy_type" class="form-control" style="width:110px;">
                                <option value="exclusive" <?php if($edit && $edit['buy_type']=="exclusive") echo "selected"; ?>>Ex GST</option>
                                <option value="inclusive" <?php if($edit && $edit['buy_type']=="inclusive") echo "selected"; ?>>Inc GST</option>
                            </select>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Selling Price -->
            <div class="col-md-3">
                <div class="form-group">
                    <label>Selling Price</label>
                    <div class="input-group">
                        <input
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            id="sell_price"
                            name="saleing-price"
                            class="form-control"
                            oninput="this.value=this.value.replace(/[^0-9.]/g,'').replace(/(\..*)\./g,'$1');"
                            value="<?php echo $edit['sale_price'] ?? ''; ?>">

                        <?php if($gst_enabled=="Yes"): ?>
                        <span class="input-group-btn">
                            <select name="sell_type" id="sell_type" class="form-control" style="width:110px;">
                                <option value="exclusive" <?php if($edit && $edit['sell_type']=="exclusive") echo "selected"; ?>>Ex GST</option>
                                <option value="inclusive" <?php if($edit && $edit['sell_type']=="inclusive") echo "selected"; ?>>Inc GST</option>
                            </select>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <?php
        if(
           (isset($_SESSION['inventory_access']) && $_SESSION['inventory_access']==1)
           ||
           (isset($_SESSION['combined_mode']) && $_SESSION['combined_mode']==1)
        ):
        ?>

            <!-- Website -->
            <div class="col-md-4">
                <div class="form-group">
                    <label>Website Link</label>
                    <input type="url"
                           name="website_link"
                           class="form-control"
                           placeholder="https://example.com"
                           value="<?php echo $edit['website_link'] ?? ''; ?>">
                </div>
            </div>

            <!-- Reorder -->
            <div class="col-md-2">
                <div class="form-group">
                    <label>Re-Order</label>
                    <input type="number"
                           name="reorder_level"
                           class="form-control"
                           min="0"
                           value="<?php echo (int)($edit['reorder_level'] ?? 0); ?>">
                </div>
            </div>

        </div>

        <div class="row" style="margin-top: 10px;">

            <div class="col-md-6">
                <div class="checkbox" style="margin-top:8px;">
                    <label style="margin-right: 15px;">
                        <input type="checkbox"
                               name="is_bom"
                               <?php if($edit && $edit['is_bom']==1) echo "checked"; ?>>
                        This product is manufactured (BOM)
                    </label>

                    <label>
                        <input type="checkbox"
                               name="is_active"
                        <?php
                        if($edit){
                            if($edit['is_active']==1) echo "checked";
                        }else{
                            echo "checked";
                        }
                        ?>>
                        Active Product
                    </label>
                </div>
            </div>

        <?php endif; ?>

            <!-- Buttons -->
            <div class="col-md-6 text-right">
                <?php if($edit): ?>
                    <a href="product.php" class="btn btn-clear">Cancel</a>
                    <button type="submit" name="update_product" class="btn btn-success save-btn">
                        Update Product
                    </button>
                <?php else: ?>
                    <button type="reset" class="btn btn-clear">Clear</button>
                    <button type="submit" id="save_btn" name="add_product" class="btn btn-success save-btn">
                        Save Product
                    </button>
                <?php endif; ?>
            </div>

        </div>

        </form>

        </div>
    </div>
</div>


<!-- ================= BOTTOM TABLE ================= -->
<div class="col-md-12">
    <div class="panel panel-default">

        <div class="panel-heading">
            PRODUCT LIST
        </div>

        <div class="panel-body">
            <div style="margin-bottom: 15px;">
                <div class="input-group" style="width:280px;">
                    <span class="input-group-addon" style="background:#fff;"><i class="glyphicon glyphicon-search" style="color:#9ca3af;"></i></span>
                    <input id="search" class="form-control" placeholder="Search product...">
                </div>
            </div>

            <div style="max-height: 500px; overflow-y: auto;">
                <table class="table table-bordered table-hover" id="productTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Type</th>
                            <?php if(isset($_SESSION['inventory_access']) && $_SESSION['inventory_access'] == 1): ?>
                            <th>Reorder</th>
                            <?php endif; ?>
                            <th>Website</th>
                            <th>Buying</th>
                            <th>Selling</th>
                            <?php if($gst_enabled == "Yes"): ?>
                            <th>HSN</th>
                            <?php endif; ?>
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($products as $p): ?>
                        <tr>
                            <td><?php echo count_id(); ?></td>
                            <td><b><?php echo $p['name']; ?></b></td>
                            <td><?php echo $p['categorie']; ?></td>
                            <td><?php echo ($p['type']==1) ? 'Product' : 'Service'; ?></td>

                            <?php if(isset($_SESSION['inventory_access']) && $_SESSION['inventory_access'] == 1): ?>
                            <td><?php echo (int)($p['reorder_level'] ?? 0); ?></td>
                            <?php endif; ?>

                            <td>
                            <?php if(!empty($p['website_link'])){ ?>
                                <a href="<?php echo $p['website_link']; ?>" target="_blank" class="label label-default">
                                    Open
                                </a>
                            <?php } ?>
                            </td>

                            <td>₹ <?php echo number_format($p['buy_price'],2); ?></td>
                            <td>₹ <?php echo number_format($p['sale_price'],2); ?></td>
                            <?php if($gst_enabled == "Yes"): ?>
                            <td><?php echo $p['hsn_code']; ?></td>
                            <?php endif; ?>

                            <td>
                                <?php if($p['is_active']==1){ ?>
                                    <span class="label label-success">Active</span>
                                <?php }else{ ?>
                                    <span class="label label-danger">Inactive</span>
                                <?php } ?>
                            </td>

                            <td class="text-center">
                                <a href="product.php?edit=<?php echo $p['id']; ?>" class="btn btn-info btn-xs" title="Edit">
                                    <i class="glyphicon glyphicon-pencil"></i>
                                </a>
                                <a href="delete_product.php?id=<?php echo $p['id']; ?>" class="btn btn-danger btn-xs" title="Delete" onclick="return confirm('Delete this product?');">
                                    <i class="glyphicon glyphicon-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

</div>



<script>
/* auto focus */
document.getElementById("product_title").focus();

/* duplicate check */
let title = document.getElementById("product_title");
let saveBtn = document.getElementById("save_btn");

title.addEventListener("input", function () {
    let name = this.value.trim();

    if(name === ""){
        if(saveBtn) saveBtn.disabled = false;
        document.getElementById("name_status").innerHTML = "";
        return;
    }

    fetch("product.php?check_name=" + encodeURIComponent(name))
    .then(r => r.text())
    .then(d => {
        d = d.trim();
        if(d === "exists"){
            if(saveBtn) saveBtn.disabled = true;
            document.getElementById("name_status").innerHTML = "<span style='color:red;font-size:11px;'>❌ Already exists</span>";
        }else{
            if(saveBtn) saveBtn.disabled = false;
            document.getElementById("name_status").innerHTML = "<span style='color:green;font-size:11px;'>✔ Available</span>";
        }
    });
});


/* search filter */
document.getElementById("search").addEventListener("keyup", function () {
    let val = this.value.toLowerCase();
    document.querySelectorAll("#productTable tbody tr").forEach(function(row){
        row.style.display = row.textContent.toLowerCase().includes(val) ? "" : "none";
    });
});

/* ========= SWEETALERT TOAST & MODAL CONFIG ========= */

<?php if(isset($_SESSION['swal_success'])): ?>
/* SUCCESS ALERT -> TOP RIGHT TOAST */
Swal.fire({
    icon: 'success',
    title: '<?php echo $_SESSION['swal_success']; ?>',
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2500,
    timerProgressBar: true
});
<?php unset($_SESSION['swal_success']); ?>
<?php endif; ?>


<?php if(isset($_SESSION['swal_error'])): ?>
/* ERROR ALERT -> CENTER MODAL */
Swal.fire({
    icon: 'error',
    title: 'Error',
    text: '<?php echo $_SESSION['swal_error']; ?>',
    position: 'center',
    confirmButtonText: 'OK',
    confirmButtonColor: '#d33'
});
<?php unset($_SESSION['swal_error']); ?>
<?php endif; ?>

</script>

<?php include_once('layouts/footer.php'); ?>
