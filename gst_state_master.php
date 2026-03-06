<?php
$page_title = 'GST State Code Master';
require_once('includes/load.php');
page_require_level(2);

/* FETCH */
$states = find_by_sql("SELECT * FROM gst_state_master ORDER BY state_name ASC");

/* ADD */
if(isset($_POST['add_state'])){

  $name = remove_junk($db->escape($_POST['state_name']));
  $code = remove_junk($db->escape($_POST['state_code']));

  if($name=='' || $code==''){
      $session->msg("d","Both fields are required");
      redirect('gst_state_master.php',false);
  }

  $chk = find_by_sql("SELECT id FROM gst_state_master WHERE state_code='$code'");
  if(!empty($chk)){
      $session->msg("d","State code already exists");
      redirect('gst_state_master.php',false);
  }

  $sql = "INSERT INTO gst_state_master(state_name,state_code)
          VALUES('$name','$code')";

  if($db->query($sql)){
      $session->msg("s","State Added");
  } else {
      $session->msg("d","Failed");
  }
  redirect('gst_state_master.php',false);
}

/* DELETE */
if(isset($_GET['del'])){
  $id = (int)$_GET['del'];
  $db->query("DELETE FROM gst_state_master WHERE id='$id'");
  $session->msg("s","Deleted");
  redirect('gst_state_master.php',false);
}

/* EDIT FETCH */
$edit=false;
if(isset($_GET['edit'])){
   $eid = (int)$_GET['edit'];
   $edit = find_by_id("gst_state_master",$eid);
}

/* UPDATE */
if(isset($_POST['update_state'])){

  $id = (int)$_POST['id'];
  $name = $_POST['state_name'];
  $code = $_POST['state_code'];

  $sql="UPDATE gst_state_master SET 
         state_name='$name',
         state_code='$code'
         WHERE id='$id'";

  if($db->query($sql)){
     $session->msg("s","Updated Successfully");
  } else {
     $session->msg("d","Update Failed");
  }

  redirect('gst_state_master.php',false);
}

include_once('layouts/header.php');
?>

<div class="row">
<div class="col-md-12"><?php echo display_msg($msg); ?></div>
</div>

<div class="row">

<!-- ADD / EDIT FORM -->
<div class="col-md-4">
<div class="panel panel-default">
<div class="panel-heading">
<?php echo $edit ? 'Edit State Code' : 'Add State Code'; ?>
</div>

<div class="panel-body">
<form method="post">

<?php if($edit){ ?>
<input type="hidden" name="id" value="<?php echo $edit['id']; ?>">
<?php } ?>

<input type="text" name="state_name" class="form-control"
value="<?php echo $edit ? $edit['state_name'] : ''; ?>"
placeholder="State Name" required><br>

<input type="text" maxlength="2" name="state_code" class="form-control"
value="<?php echo $edit ? $edit['state_code'] : ''; ?>"
placeholder="State Code (e.g. 09)" required><br>

<?php if($edit){ ?>
<button name="update_state" class="btn btn-danger btn-block">Update</button>
<a href="gst_state_master.php" class="btn btn-secondary btn-block">Cancel</a>
<?php } else { ?>
<button name="add_state" class="btn btn-danger btn-block">Save</button>
<button type="button" class="btn btn-secondary btn-block" onclick="clearForm()">Clear</button>
<?php } ?>

</form>
</div>
</div>
</div>

<!-- LIST -->
<div class="col-md-8">
<div class="panel panel-default">
<div class="panel-heading">GST State Code List</div>

<input type="text" id="searchBox" class="form-control" placeholder="Search state or code...">
<br>

<div class="panel-body">
<table class="table table-bordered">
<tr>
<th>#</th>
<th>State</th>
<th>Code</th>
<th>Action</th>
</tr>

<?php foreach($states as $i=>$s): ?>
<tr>
<td><?php echo $i+1; ?></td>
<td><?php echo $s['state_name']; ?></td>
<td><?php echo $s['state_code']; ?></td>

<td>
<a href="gst_state_master.php?edit=<?php echo $s['id']; ?>" class="btn btn-info btn-xs">Edit</a>

<a href="gst_state_master.php?del=<?php echo $s['id']; ?>"
class="btn btn-danger btn-xs">Delete</a>
</td>
</tr>
<?php endforeach; ?>

</table>
</div>
</div>
</div>

</div>
<script>
document.getElementById("searchBox").addEventListener("keyup", function() {
    let value = this.value.toLowerCase();
    let rows = document.querySelectorAll("table tbody tr");

    rows.forEach(function(row) {
        let text = row.innerText.toLowerCase();
        row.style.display = text.includes(value) ? "" : "none";
    });
});

  function clearForm(){
    document.querySelector("form").reset();
    document.getElementById("state_name").focus();
}
</script>

<?php include_once('layouts/footer.php'); ?>
