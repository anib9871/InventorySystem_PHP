<?php
$page_title = 'Supplier Master';
require_once('includes/load.php');
page_require_level(2);

/* FETCH SUPPLIERS LIST */
$suppliers = find_by_sql("SELECT sm.*, gsm.state_name 
                          FROM supplier_master sm
                          LEFT JOIN gst_state_master gsm ON gsm.id = sm.state_id
                          ORDER BY sm.id DESC");

/* FETCH STATE LIST */
$states = find_by_sql("SELECT * FROM gst_state_master ORDER BY state_name ASC");

/* ---------- ADD SUPPLIER ---------- */
if(isset($_POST['add_supplier'])){

  $name = remove_junk($db->escape($_POST['supplier_name']));
  $phone = remove_junk($db->escape($_POST['phone']));
  $email = remove_junk($db->escape($_POST['email']));
  $contact = remove_junk($db->escape($_POST['contact']));
  $address = remove_junk($db->escape($_POST['address']));
  $state_id = (int)$_POST['state_id'];
  $state_code = remove_junk($db->escape($_POST['state_code']));
  $gst = remove_junk($db->escape($_POST['gst']));

  if($name==''){
     $session->msg("d","Supplier name is required");
     redirect('supplier_master.php',false);
  }

  $sql = "INSERT INTO supplier_master
          (supplier_name,phone,email,contact_person,address,state_id,state_code,gst_no)
          VALUES ('$name','$phone','$email','$contact','$address','$state_id','$state_code','$gst')";

  if($db->query($sql)){
      $session->msg("s","Supplier Added");
  }else{
      $session->msg("d","Failed to add supplier");
  }
  redirect('supplier_master.php',false);
}

/* ---------- UPDATE SUPPLIER ---------- */
if(isset($_POST['update_supplier'])){

  $id = (int)$_POST['id'];

  $name = remove_junk($db->escape($_POST['supplier_name']));
  $phone = remove_junk($db->escape($_POST['phone']));
  $email = remove_junk($db->escape($_POST['email']));
  $contact = remove_junk($db->escape($_POST['contact']));
  $address = remove_junk($db->escape($_POST['address']));
  $state_id = (int)$_POST['state_id'];
  $state_code = remove_junk($db->escape($_POST['state_code']));
  $gst = remove_junk($db->escape($_POST['gst']));

  $sql = "UPDATE supplier_master SET
          supplier_name='$name',
          phone='$phone',
          email='$email',
          contact_person='$contact',
          address='$address',
          state_id='$state_id',
          state_code='$state_code',
          gst_no='$gst'
          WHERE id='$id'";

  if($db->query($sql)){
      $session->msg("s","Supplier Updated");
  } else {
      $session->msg("d","Update Failed");
  }
  redirect('supplier_master.php',false);
}

/* ---------- EDIT LOAD ---------- */
$edit=false;
if(isset($_GET['edit'])){
   $eid=(int)$_GET['edit'];
   $edit=find_by_id("supplier_master",$eid);
}

/* ---------- DELETE ---------- */
if(isset($_GET['del'])){
   $id=(int)$_GET['del'];
   $db->query("DELETE FROM supplier_master WHERE id='$id'");
   $session->msg("s","Supplier Deleted");
   redirect('supplier_master.php',false);
}

include_once('layouts/header.php');
?>

<style>
.equal-btn{
  min-width:60px;   /* same width */
  text-align:center;
}
</style>


<div class="row">
<div class="col-md-12"><?php echo display_msg($msg); ?></div>
</div>

<div class="row">

<!-- ADD / EDIT FORM -->
<div class="col-md-3" style="margin-top:-50px;">
<div class="panel panel-default">
<div class="panel-heading"><?php echo $edit ? 'Edit Supplier' : 'Add Supplier'; ?></div>

<div class="panel-body">
<form method="post">

<?php if($edit){ ?>
<input type="hidden" name="id" value="<?php echo $edit['id']; ?>">
<?php } ?>

<input type="text" id="supplier_name" name="supplier_name" class="form-control"
value="<?php echo $edit ? $edit['supplier_name'] : ''; ?>"
placeholder="Supplier Name *" required><br>


<input type="text" name="phone" class="form-control"
value="<?php echo $edit ? $edit['phone'] : ''; ?>"
placeholder="Contact No"><br>

<input type="email" name="email" class="form-control"
value="<?php echo $edit ? $edit['email'] : ''; ?>"
placeholder="Email"><br>

<input type="text" name="contact" class="form-control"
value="<?php echo $edit ? $edit['contact_person'] : ''; ?>"
placeholder="Contact Person"><br>

<textarea name="address" class="form-control"
placeholder="Address"><?php echo $edit ? $edit['address'] : ''; ?></textarea><br>

<!-- STATE DROPDOWN -->
<select name="state_id" id="state_id" class="form-control" required>
<option value="">Select State</option>

<?php foreach($states as $s){ ?>
<option value="<?php echo $s['id']; ?>"
data-code="<?php echo $s['state_code']; ?>"
<?php if($edit && $edit['state_id']==$s['id']) echo "selected"; ?>>
<?php echo $s['state_name']; ?>
</option>
<?php } ?>
</select>
<br>

<!-- STATE CODE AUTO -->
<input type="text" name="state_code" id="state_code" class="form-control"
value="<?php echo $edit ? $edit['state_code'] : ''; ?>"
placeholder="State Code" readonly><br>

<!-- GST AUTO PREFIX -->
<input type="text" name="gst" id="gst" class="form-control"
value="<?php echo $edit ? $edit['gst_no'] : ''; ?>"
placeholder="Enter GST Number">
<br>


<?php if($edit){ ?>
<button name="update_supplier" class="btn btn-danger btn-block">Update</button>
<a href="supplier_master.php" class="btn btn-secondary btn-block">Cancel</a>
<?php } else { ?>
<button name="add_supplier" class="btn btn-danger btn-block">Save Supplier</button>
<button type="button" class="btn btn-secondary btn-block" onclick="clearForm()">Clear</button>

<?php } ?>

</form>
</div>
</div>
</div>

<!-- LIST -->
<div class="col-md-9">
<div class="panel panel-default">
<div class="panel-heading">Supplier List</div>

<div class="panel-body">

<input type="text" id="search" class="form-control" placeholder="Search supplier..."><br>

<div class="table-responsive">
<table class="table table-bordered table-striped">

<tr>
<th>#</th>
<th>Name</th>
<th>Phone</th>
<th>Email</th>
<th>Contact Person</th>
<th>Address</th>
<th>State</th>
<th>GST Code</th>
<th>GST No</th>
<th>Action</th>
</tr>

<tbody id="supplierTable">
<?php foreach($suppliers as $i=>$s): ?>
<tr>
<td><?php echo $i+1; ?></td>
<td><?php echo $s['supplier_name']; ?></td>
<td><?php echo $s['phone']; ?></td>
<td><?php echo $s['email']; ?></td>
<td><?php echo $s['contact_person']; ?></td>
<td><?php echo $s['address']; ?></td>
<td><?php echo $s['state_name']; ?></td>
<td><?php echo $s['state_code']; ?></td>
<td><?php echo $s['gst_no']; ?></td>

<td>
<a href="supplier_master.php?edit=<?php echo $s['id']; ?>" 
   class="btn btn-info btn-xs equal-btn">Edit</a>

<a href="supplier_master.php?del=<?php echo $s['id']; ?>" 
   class="btn btn-danger btn-xs equal-btn"
   onclick="return confirm('Delete supplier?');">Delete</a>
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
document.getElementById("state_id").addEventListener("change", function(){
    let code=this.options[this.selectedIndex].getAttribute("data-code");
    document.getElementById("state_code").value=code;
});

  window.onload = function () {
      document.getElementById("supplier_name").focus();
  };

  function clearForm(){
    document.querySelector("form").reset();
    document.getElementById("supplier_name").focus();
}


/* Search filter */
document.getElementById("search").addEventListener("keyup", function(){
  let value = this.value.toLowerCase();
  document.querySelectorAll("#supplierTable tr").forEach(function(row){
    row.style.display = row.textContent.toLowerCase().includes(value) ? "" : "none";
  });
});


</script>

<?php include_once('layouts/footer.php'); ?>
