<?php
$page_title = 'Subscription Plans';
require_once('includes/load.php');

/* FETCH PLANS */
$plans = find_by_sql("SELECT * FROM master_inventory.subscription_plans ORDER BY plan_id DESC");

/* FETCH EDIT PLAN */
$edit_plan = null;

if(isset($_GET['edit'])){
  $id = (int)$_GET['edit'];

  $result = find_by_sql("SELECT * FROM master_inventory.subscription_plans WHERE plan_id='{$id}' LIMIT 1");

  if($result){
    $edit_plan = $result[0];
  }
}

/* ADD PLAN */
if(isset($_POST['add_plan'])){

  $plan_name = remove_junk($db->escape($_POST['plan_name']));
  $duration = (int)$_POST['duration_days'];
  $price = $_POST['price'];
  $remark = remove_junk($db->escape($_POST['remark']));

  $sql = "INSERT INTO master_inventory.subscription_plans
  (plan_name, duration_days, price, status, remark)
  VALUES
  ('$plan_name','$duration','$price',1,'$remark')";

  if($db->query($sql)){
    $session->msg('s',"Plan Added Successfully");
  } else {
    $session->msg('d',"Failed to add plan");
  }

  redirect('subscription_plans.php', false);
}


/* UPDATE PLAN */
if(isset($_POST['update_plan'])){

  $id = (int)$_POST['plan_id'];

  $plan_name = remove_junk($db->escape($_POST['plan_name']));
  $duration = (int)$_POST['duration_days'];
  $price = $_POST['price'];
  $remark = remove_junk($db->escape($_POST['remark']));

  $sql = "UPDATE master_inventory.subscription_plans SET
  plan_name='$plan_name',
  duration_days='$duration',
  price='$price',
  remark='$remark'
  WHERE plan_id='$id'";

  if($db->query($sql)){
    $session->msg('s',"Plan Updated Successfully");
  } else {
    $session->msg('d',"Failed to update plan");
  }

  redirect('subscription_plans.php', false);
}

/* DELETE PLAN */
if(isset($_GET['delete'])){
  $id = (int)$_GET['delete'];

  $db->query("DELETE FROM master_inventory.subscription_plans WHERE plan_id='$id'");
  $session->msg('s',"Plan Deleted");
  redirect('subscription_plans.php');
}

include_once('layouts/header.php');
?>

<div class="row">

<!-- ADD PLAN FORM -->
<div class="col-md-4">
<div class="panel panel-default">
<div class="panel-heading">
<strong>Add Plan</strong>
</div>

<div class="panel-body">

<form method="post">

<?php if($edit_plan): ?>
<input type="hidden" name="plan_id" value="<?php echo $edit_plan['plan_id']; ?>">
<?php endif; ?>

<div class="form-group">
<input type="text" name="plan_name" class="form-control"
value="<?php echo $edit_plan ? $edit_plan['plan_name'] : ''; ?>"
placeholder="Plan Name" required>
</div>

<div class="form-group">
<input type="number" name="duration_days" class="form-control"
value="<?php echo $edit_plan ? $edit_plan['duration_days'] : ''; ?>"
placeholder="Duration (Days)" required>
</div>

<div class="form-group">
<input type="text" name="price" class="form-control"
value="<?php echo $edit_plan ? $edit_plan['price'] : ''; ?>"
placeholder="Price">
</div>

<div class="form-group">
<input type="text" name="remark" class="form-control"
value="<?php echo $edit_plan ? $edit_plan['remark'] : ''; ?>"
placeholder="Remark">
</div>

<?php if($edit_plan): ?>

<button type="submit" name="update_plan" class="btn btn-primary">
Update Plan
</button>

<a href="subscription_plans.php" class="btn btn-default">Cancel</a>

<?php else: ?>

<button type="submit" name="add_plan" class="btn btn-primary">
Save Plan
</button>

<?php endif; ?>

</form>

</div>
</div>
</div>

<!-- PLAN LIST -->
<div class="col-md-8">
<div class="panel panel-default">

<div class="panel-heading">
<strong>Plan List</strong>
</div>

<div class="panel-body">

<table class="table table-bordered">
<thead>
<tr>
<th>#</th>
<th>Name</th>
<th>Days</th>
<th>Price</th>
<th>Status</th>
<th>Remark</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php foreach($plans as $p): ?>
<tr>
<td><?php echo $p['plan_id']; ?></td>
<td><?php echo $p['plan_name']; ?></td>
<td><?php echo $p['duration_days']; ?></td>
<td><?php echo $p['price']; ?></td>
<td><?php echo $p['status'] == 1 ? 'Active' : 'Inactive'; ?></td>
<td><?php echo $p['remark']; ?></td>

<td>

<a href="?edit=<?php echo $p['plan_id']; ?>" class="btn btn-info btn-xs">
Edit
</a>

<a href="?delete=<?php echo $p['plan_id']; ?>" class="btn btn-danger btn-xs">
Delete
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

<?php include_once('layouts/footer.php'); ?>
