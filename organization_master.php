<?php
$page_title = 'Organization Master';
require_once('includes/load.php');
//page_require_level(2);

/* FETCH ORGANIZATION LIST */
$orgs = find_by_sql("SELECT om.*, gsm.state_name 
                     FROM organization_master om
                     LEFT JOIN gst_state_master gsm ON gsm.id = om.state_id
                     ORDER BY om.id DESC");

/* FETCH STATE LIST */
$states = find_by_sql("SELECT * FROM gst_state_master ORDER BY state_name ASC");

/* ADD ORGANIZATION */
if(isset($_POST['add_org'])){

  $mnemonic = strtoupper(substr(remove_junk($db->escape($_POST['mnemonic'])),0,5));

  $org_name = remove_junk($db->escape($_POST['org_name']));
  $address = remove_junk($db->escape($_POST['address']));
  $phone = remove_junk($db->escape($_POST['phone']));
  $email = remove_junk($db->escape($_POST['email']));
  $contact = remove_junk($db->escape($_POST['contact']));
  $gst = remove_junk($db->escape($_POST['gst']));
  $state_id = (int)$_POST['state_id'];
  $state_code = remove_junk($db->escape($_POST['state_code']));

  if($org_name == ''){
      $session->msg("d","Organization name is required");
      redirect('organization_master.php',false);
  }

  $sql = "INSERT INTO organization_master
          (mnemonic,org_name,address,state_id,state_code,phone,email,contact_person,gst_no)
          VALUES
          ('$mnemonic','$org_name','$address','$state_id','$state_code',
           '$phone','$email','$contact','$gst')";

  if($db->query($sql)){
      $session->msg("s","Organization Added");
  } else {
      $session->msg("d","Failed to add");
  }
  redirect('organization_master.php',false);
}

/* UPDATE ORGANIZATION */
if(isset($_POST['update_org'])){

  $id = (int)$_POST['id'];

  $mnemonic = strtoupper(substr(remove_junk($db->escape($_POST['mnemonic'])),0,5));
  $org_name = remove_junk($db->escape($_POST['org_name']));
  $address = remove_junk($db->escape($_POST['address']));
  $phone = remove_junk($db->escape($_POST['phone']));
  $email = remove_junk($db->escape($_POST['email']));
  $contact = remove_junk($db->escape($_POST['contact']));
  $gst = remove_junk($db->escape($_POST['gst']));
  $state_id = (int)$_POST['state_id'];
  $state_code = remove_junk($db->escape($_POST['state_code']));

  $sql = "UPDATE organization_master SET
            mnemonic='$mnemonic',
            org_name='$org_name',
            address='$address',
            state_id='$state_id',
            state_code='$state_code',
            phone='$phone',
            email='$email',
            contact_person='$contact',
            gst_no='$gst'
          WHERE id='$id'";

  if($db->query($sql)){
      $session->msg("s","Organization Updated");
  } else {
      $session->msg("d","Update Failed");
  }

  redirect('organization_master.php',false);
}

/* EDIT LOAD */
$edit = false;
if(isset($_GET['edit'])){
   $eid = (int)$_GET['edit'];
   $edit = find_by_id("organization_master",$eid);
}

/* DELETE */
if(isset($_GET['del'])){
   $id = (int)$_GET['del'];
   $db->query("DELETE FROM organization_master WHERE id='$id'");
   $session->msg("s","Organization Deleted");
   redirect('organization_master.php',false);
}

include_once('layouts/header.php');
?>

<style>
.add-box{ margin-top:-50px; }
.equal-btn{ min-width:60px; text-align:center; }
</style>

<div class="row">
<div class="col-md-12"><?php echo display_msg($msg); ?></div>
</div>

<div class="row">

<!-- ADD FORM -->
<div class="col-md-3 add-box">
<div class="panel panel-default">
<div class="panel-heading">Add Organization</div>

<div class="panel-body">

<form method="post" id="orgForm">

<?php if($edit){ ?>
<input type="hidden" name="id" value="<?php echo $edit['id']; ?>">
<?php } ?>

<input type="text" maxlength="5" id="mnemonic" name="mnemonic" class="form-control"
value="<?php echo $edit ? $edit['mnemonic'] : ''; ?>" placeholder="Mnemonic *" required><br>

<input type="text" name="org_name" class="form-control"
value="<?php echo $edit ? $edit['org_name'] : ''; ?>" placeholder="Organization Name *" required><br>

<input type="text" name="phone" class="form-control"
value="<?php echo $edit ? $edit['phone'] : ''; ?>" placeholder="Phone No"><br>

<input type="email" name="email" class="form-control"
value="<?php echo $edit ? $edit['email'] : ''; ?>" placeholder="Email ID"><br>

<input type="text" name="contact" class="form-control"
value="<?php echo $edit ? $edit['contact_person'] : ''; ?>" placeholder="Contact Person"><br>

<textarea name="address" class="form-control" placeholder="Address"><?php echo $edit ? $edit['address'] : ''; ?></textarea><br>

<select name="state_id" id="state_id" class="form-control" required>
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
value="<?php echo $edit ? $edit['state_code'] : ''; ?>" placeholder="State Code" readonly><br>

<input type="text" name="gst" id="gst" class="form-control"
value="<?php echo $edit ? $edit['gst_no'] : ''; ?>" placeholder="Enter GST Number"><br>

<?php if($edit){ ?>
<button name="update_org" class="btn btn-danger btn-block">Update</button>
<a href="organization_master.php" class="btn btn-secondary btn-block">Cancel</a>
<?php } else { ?>
<button name="add_org" class="btn btn-danger btn-block">Save Organization</button>
<button type="button" class="btn btn-secondary btn-block" onclick="document.getElementById('orgForm').reset();">Clear</button>
<?php } ?>

</form>

</div>
</div>
</div>

<!-- LIST -->
<div class="col-md-9">
<div class="panel panel-default">
<div class="panel-heading">Organization List</div>

<div class="panel-body">

<input type="text" id="search" class="form-control" placeholder="Search organization..."><br>

<div class="table-responsive">

<table class="table table-bordered table-striped">

<thead>
<tr>
<th>#</th>
<th>Mnemonic</th>
<th>Organization</th>
<th>Phone</th>
<th>Email</th>
<th>Contact</th>
<th>Address</th>
<th>State</th>
<th>State Code</th>
<th>GST No</th>
<th>Action</th>
</tr>
</thead>

<tbody id="organizationTable">

<?php foreach($orgs as $i=>$o): ?>
<tr>
<td><?php echo $i+1; ?></td>
<td><?php echo $o['mnemonic']; ?></td>
<td><?php echo $o['org_name']; ?></td>
<td><?php echo $o['phone']; ?></td>
<td><?php echo $o['email']; ?></td>
<td><?php echo $o['contact_person']; ?></td>
<td><?php echo $o['address']; ?></td>
<td><?php echo $o['state_name']; ?></td>
<td><?php echo $o['state_code']; ?></td>
<td><?php echo $o['gst_no']; ?></td>

<td>
<a href="organization_master.php?edit=<?php echo $o['id']; ?>" class="btn btn-info btn-xs equal-btn">Edit</a>

<a onclick="return confirm('Delete karna sure?')" 
   href="organization_master.php?del=<?php echo $o['id']; ?>" 
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
document.getElementById("state_id").addEventListener("change", function(){
 let code = this.options[this.selectedIndex].getAttribute("data-code");
 document.getElementById("state_code").value = code;
});

document.getElementById("search").addEventListener("keyup", function(){
 let value = this.value.toLowerCase();
 document.querySelectorAll("#organizationTable tr").forEach(function(row){
   row.style.display = row.textContent.toLowerCase().includes(value) ? "" : "none";
 });
});
</script>

<?php include_once('layouts/footer.php'); ?>
