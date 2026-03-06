<?php
$page_title = 'Sales';
require_once('includes/load.php');
page_require_level(3);

// fetch data
$products = find_all('products');
$sales = find_all_sale();

/* ADD SALE */
if(isset($_POST['add_sale'])){

  $p_id = (int)$_POST['product_id'];
  $qty  = (int)$_POST['qty'];
  $price = (float)$_POST['price'];
  $total = (float)$_POST['total'];
  $date  = make_date();

  if($p_id == 0 || $qty <= 0){
      $session->msg('d','Please select product and quantity');
      redirect('sale.php',false);
  }

  $sql  = "INSERT INTO sales (product_id,qty,price,date)";
  $sql .= " VALUES ('{$p_id}','{$qty}','{$total}','{$date}')";

  if($db->query($sql)){
      update_product_qty($qty,$p_id);
      $session->msg('s',"Sale Added Successfully");
  } else {
      $session->msg('d',"Failed to add sale");
  }

  redirect('sale.php',false);
}

include_once('layouts/header.php'); 
?>

<div class="row">
<div class="col-md-12">
<?php echo display_msg($msg); ?>
</div>
</div>

<div class="row">

<!-- LEFT : ADD SALE -->
<div class="col-md-4">
<div class="panel panel-default">
<div class="panel-heading"><strong>Add Sale</strong></div>
<div class="panel-body">

<form method="post">

<select name="product_id" id="product" class="form-control" required>
<option value="">Select Product</option>
<?php foreach($products as $p): ?>
<option value="<?php echo $p['id']; ?>" data-price="<?php echo $p['sale_price']; ?>">
  <?php echo $p['name']; ?>
</option>
<?php endforeach; ?>
</select>
<br>

<input type="number" id="price" name="price" class="form-control" placeholder="Price" readonly><br>

<input type="number" id="qty" name="qty" class="form-control" placeholder="Quantity" required><br>

<input type="number" id="total" name="total" class="form-control" placeholder="Total" readonly><br>

<button type="submit" name="add_sale" class="btn btn-success btn-block">
Save Sale
</button>

</form>

</div>
</div>
</div>

<!-- RIGHT : LIST SALES -->
<div class="col-md-8">
<div class="panel panel-default">
<div class="panel-heading"><strong>All Sales</strong></div>
<div class="panel-body">

<table class="table table-bordered">
<thead>
<tr>
<th>#</th>
<th>Product</th>
<th>Qty</th>
<th>Total</th>
<th>Date</th>
<th>Action</th>
</tr>
</thead>

<tbody>
<?php foreach($sales as $s): ?>
<tr>
<td><?php echo count_id(); ?></td>
<td><?php echo $s['name']; ?></td>
<td><?php echo $s['qty']; ?></td>
<td><?php echo $s['price']; ?></td>
<td><?php echo $s['date']; ?></td>

<td>
<a href="delete_sale.php?id=<?php echo $s['id']; ?>" class="btn btn-danger btn-xs">Delete</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>

</table>

</div>
</div>
</div>

</div>

<script>
// auto fill price when product selected
document.getElementById("product").addEventListener("change",function(){
   let price = this.selectedOptions[0].getAttribute("data-price") || 0;
   document.getElementById("price").value = price;
   calcTotal();
});

// auto total
document.getElementById("qty").addEventListener("input", calcTotal);

function calcTotal(){
   let p = parseFloat(document.getElementById("price").value) || 0;
   let q = parseFloat(document.getElementById("qty").value) || 0;
   document.getElementById("total").value = (p*q).toFixed(2);
}
</script>

<?php include_once('layouts/footer.php'); ?>
