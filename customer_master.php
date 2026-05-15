<?php
$page_title = 'Customer Master';
require_once('includes/load.php');
//page_require_level(2);

/* FETCH CUSTOMER LIST */
if($_SESSION['role_id'] == 2){

/* ADMIN = ALL CUSTOMERS */

$customers = find_by_sql("

SELECT 

cm.*,
gsm.state_name,
mc.center_name

FROM customer_master cm

LEFT JOIN gst_state_master gsm
ON gsm.id = cm.state_id

LEFT JOIN master_center mc
ON mc.center_id = cm.center_id

ORDER BY cm.id DESC

");

}else{

/* USER = ONLY OWN CENTER */

$center_id = $_SESSION['center_id'];

$customers = find_by_sql("

SELECT 

cm.*,
gsm.state_name,
mc.center_name

FROM customer_master cm

LEFT JOIN gst_state_master gsm
ON gsm.id = cm.state_id

LEFT JOIN master_center mc
ON mc.center_id = cm.center_id

WHERE cm.center_id = '$center_id'

ORDER BY cm.id DESC

");

}

/* FETCH STATE LIST */
$states = find_by_sql("SELECT * FROM gst_state_master ORDER BY state_name ASC");

/* ---------- ADD CUSTOMER ---------- */
if(isset($_POST['add_customer'])){

  $name = remove_junk($db->escape($_POST['customer_name']));
  $contact = remove_junk($db->escape($_POST['contact_no']));
  $email = remove_junk($db->escape($_POST['email']));
  $address = remove_junk($db->escape($_POST['address']));
  $state_id = (int)$_POST['state_id'];
  $state_code = remove_junk($db->escape($_POST['state_code']));
  $gst = remove_junk($db->escape($_POST['gst_no']));
  
  if($name==''){
     $session->msg("d","Customer name is required");
     redirect('customer_master.php',false);
  }

$center_id = $_SESSION['center_id'];

$sql = "INSERT INTO customer_master
(customer_name,contact_no,email,address,state_id,state_code,gst_no,center_id)

VALUES(
'$name',
'$contact',
'$email',
'$address',
'$state_id',
'$state_code',
'$gst',
'$center_id'
)";

  if($db->query($sql)){
     $session->msg("s","Customer Added");
  } else {
     $session->msg("d","Failed to add");
  }
  redirect('customer_master.php',false);
}

/* ---------- UPDATE CUSTOMER ---------- */
if(isset($_POST['update_customer'])){

  $id = (int)$_POST['id'];

  $name = remove_junk($db->escape($_POST['customer_name']));
  $contact = remove_junk($db->escape($_POST['contact_no']));
  $email = remove_junk($db->escape($_POST['email']));
  $address = remove_junk($db->escape($_POST['address']));
  $state_id = (int)$_POST['state_id'];
  $state_code = remove_junk($db->escape($_POST['state_code']));
  $gst = remove_junk($db->escape($_POST['gst_no']));
  $center_id = $_SESSION['center_id'];
  

  $sql="UPDATE customer_master SET
        customer_name='$name',
        contact_no='$contact',
        email='$email',
        address='$address',
        state_id='$state_id',
        state_code='$state_code',
        gst_no='$gst',
        center_id='$center_id'
        WHERE id='$id'";


  if($db->query($sql)){
     $session->msg("s","Customer Updated");
  } else {
     $session->msg("d","Update Failed");
  }
  redirect('customer_master.php',false);
}

/* ---------- EDIT LOAD ---------- */
$edit=false;
if(isset($_GET['edit'])){
   $eid=(int)$_GET['edit'];
   $edit=find_by_id("customer_master",$eid);
}

/* ---------- DELETE ---------- */
if(isset($_GET['del'])){
   $id=(int)$_GET['del'];
   $db->query("DELETE FROM customer_master WHERE id='$id'");
   $session->msg("s","Customer Deleted");
   redirect('customer_master.php',false);
}
include_once('layouts/header.php');
?>

<style>

.equal-btn{
  min-width:55px;
  text-align:center;
  padding:3px 6px;
  font-size:11px;
}

.table-responsive table{
  font-size:12px;
}

.table-responsive table th,
.table-responsive table td{
  padding:6px !important;
  vertical-align:middle !important;
}

.table-responsive table th{
  white-space:nowrap;
}

.label{
  font-size:11px;
}

</style>


<div class="row">
<div class="col-md-12"><?php echo display_msg($msg); ?></div>
</div>

<div class="row">

<!-- FORM -->
<div class="col-md-3">
<div class="panel panel-default">
<div class="panel-heading">
<?php echo $edit ? 'Edit Customer' : 'Add Customer'; ?>
</div>

<div class="panel-body">
<form method="post">

<?php if($edit){ ?>
<input type="hidden" name="id" value="<?php echo $edit['id']; ?>">
<?php } ?>

<input type="text" id="customer_name" name="customer_name" class="form-control"
value="<?php echo $edit ? $edit['customer_name'] : ''; ?>"
placeholder="Customer Name *" required><br>

<input type="text" name="contact_no" class="form-control"
value="<?php echo $edit ? $edit['contact_no'] : ''; ?>"
placeholder="Contact No"><br>

<input type="text" id="email" name="email" class="form-control"
value="<?php echo $edit ? $edit['email'] : ''; ?>"
placeholder="Email" required><br>

<textarea name="address" class="form-control"
placeholder="Address"><?php echo $edit ? $edit['address'] : ''; ?></textarea><br>

<select name="state_id" id="state_id" class="form-control">
<option value="">Select State</option>

<?php foreach($states as $s){ ?>
<option value="<?php echo $s['id']; ?>"
data-code="<?php echo $s['state_code']; ?>"
<?php if($edit && $edit['state_id']==$s['id']) echo "selected"; ?>>
<?php echo $s['state_name']; ?>
</option>
<?php } ?>

</select><br>

<input type="text" name="state_code" id="state_code" class="form-control"
value="<?php echo $edit ? $edit['state_code'] : ''; ?>"
placeholder="State Code" readonly><br>

<input type="text" name="gst_no" class="form-control"
value="<?php echo $edit ? $edit['gst_no'] : ''; ?>"
placeholder="GST Number"><br>

<?php if($edit){ ?>
<button name="update_customer" class="btn btn-danger btn-block">Update</button>
<a href="customer_master.php" class="btn btn-secondary btn-block">Cancel</a>
<?php } else { ?>
<button name="add_customer" class="btn btn-danger btn-block">Save Customer</button>
<button type="button" class="btn btn-secondary btn-block" onclick="clearForm()">Clear</button>
<?php } ?>

</form>
</div>
</div>
</div>

<!-- LIST -->
<div class="col-md-9">
<div class="panel panel-default">
<div class="panel-heading">Customer List</div>

<div class="panel-body">

<div class="row" style="margin-bottom:10px;">

<div class="col-md-4">
<input type="text" id="search" class="form-control" placeholder="Search customer...">
</div>

<div class="col-md-8 text-right">

<a href="customer_report_print.php" 
class="btn btn-primary btn-sm" target="_blank">

<i class="glyphicon glyphicon-print"></i>
Print Report

</a>

<a href="customer_report_excel.php" 
class="btn btn-success btn-sm">

<i class="glyphicon glyphicon-download"></i>
Excel

</a>

</div>

</div>

<div class="table-responsive">
<table class="table table-bordered table-striped">

<tr>
<th>#</th>
<th>Name</th>
<th>Contact</th>
<th>Email</th>
<th>Address</th>
<th>State</th>
<th>State Code</th>
<th>GST No</th>
<th>Center</th>
<th>Created Date</th>
<th>Action</th>
</tr>

<tbody id="custTable">
<?php foreach($customers as $i=>$c): ?>
<tr>
<td><?php echo $i+1; ?></td>
<td><?php echo $c['customer_name']; ?></td>
<td><?php echo $c['contact_no']; ?></td>
<td><?php echo $c['email']; ?></td>
<td><?php echo $c['address']; ?></td>
<td><?php echo $c['state_name']; ?></td>
<td><?php echo $c['state_code']; ?></td>
<td><?php echo $c['gst_no']; ?></td>
<td>
<span class="label label-primary">
<?php echo $c['center_name']; ?>
</span>
</td>
<td>
<?php echo date('d-m-Y h:i A', strtotime($c['created_date'])); ?>
</td>
<td>
<a href="customer_master.php?edit=<?php echo $c['id']; ?>" 
   class="btn btn-info btn-xs equal-btn">Edit</a>

<a href="customer_master.php?del=<?php echo $c['id']; ?>" 
   class="btn btn-danger btn-xs equal-btn">Delete</a>
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
/* Auto fill state code */
document.getElementById("state_id").addEventListener("change", function(){
  let code=this.options[this.selectedIndex].getAttribute("data-code");
  document.getElementById("state_code").value=code;
});

/* Auto Focus */
window.onload = function(){
  document.getElementById("customer_name").focus();
};

function clearForm(){
    document.querySelector("form").reset();
    document.getElementById("customer_name").focus();
}

/* Search filter */
document.getElementById("search").addEventListener("keyup", function(){
  let value = this.value.toLowerCase();
  document.querySelectorAll("#custTable tr").forEach(function(row){
    row.style.display = row.textContent.toLowerCase().includes(value) ? "" : "none";
  });
});
</script>

<?php include_once('layouts/footer.php'); ?>
