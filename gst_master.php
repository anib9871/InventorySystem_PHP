<?php
$page_title = 'GST Master';
require_once('includes/load.php');
//page_require_level(2);

/* ---------- FETCH ALL ---------- */
$all_gst = find_by_sql("SELECT * FROM gst_master ORDER BY gst_percent ASC");

/* ---------- ADD GST ---------- */
if(isset($_POST['add_gst'])){

  $name = remove_junk($db->escape($_POST['gst_name']));
  $percent = (float)$_POST['gst_percent'];

  if($name == '' || $percent == ''){
     $session->msg("d","Fields cannot be empty");
     redirect('gst_master.php',false);
  }

  /* Prevent duplicate */
  $chk = find_by_sql("SELECT id FROM gst_master WHERE gst_name='$name'");
  if(!empty($chk)){
     $session->msg("d","GST already exists");
     redirect('gst_master.php',false);
  }

  $query = "INSERT INTO gst_master(gst_name,gst_percent)
            VALUES('$name','$percent')";

  if($db->query($query)){
    $session->msg("s","GST Added");
  } else {
    $session->msg("d","Failed to add GST");
  }

  redirect('gst_master.php',false);
}

/* ---------- DELETE GST ---------- */
if(isset($_GET['del'])){
   $id = (int)$_GET['del'];
   $db->query("DELETE FROM gst_master WHERE id='{$id}'");
   $session->msg("s","GST Deleted");
   redirect('gst_master.php',false);
}

/* ---------- LOAD FOR EDIT ---------- */
$edit_data = null;
if(isset($_GET['edit'])){
  $id = (int)$_GET['edit'];
  $res = find_by_sql("SELECT * FROM gst_master WHERE id='{$id}' LIMIT 1");
  if($res){
    $edit_data = $res[0];
  }
}

/* ---------- UPDATE GST ---------- */
if(isset($_POST['update_gst'])){

  $id = (int)$_POST['id'];
  $name = remove_junk($db->escape($_POST['gst_name']));
  $percent = (float)$_POST['gst_percent'];

  if($name == '' || $percent == ''){
     $session->msg("d","Fields cannot be empty");
     redirect('gst_master.php',false);
  }

  $query = "UPDATE gst_master 
            SET gst_name='$name', gst_percent='$percent'
            WHERE id='$id'";

  if($db->query($query)){
    $session->msg("s","GST Updated");
  } else {
    $session->msg("d","Update failed");
  }

  redirect('gst_master.php',false);
}

include_once('layouts/header.php');
?>

<div class="row">
<div class="col-md-12"><?php echo display_msg($msg); ?></div>
</div>

<div class="row">

<!-- ADD / EDIT -->
<div class="col-md-4">
<div class="panel panel-default">
<div class="panel-heading">
  <?php echo $edit_data ? 'Edit GST' : 'Add GST'; ?>
</div>

<div class="panel-body">

<form method="post">

<?php if($edit_data): ?>
<input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
<?php endif; ?>

<input type="text" id="gst_name" name="gst_name" class="form-control"
placeholder="GST Name (Example: GST-5, IGST-12)"
value="<?php echo $edit_data['gst_name'] ?? ''; ?>"><br>

<input type="number" step="0.01" name="gst_percent" class="form-control"
placeholder="GST %"
value="<?php echo $edit_data['gst_percent'] ?? ''; ?>"><br>

<?php if($edit_data): ?>
<button name="update_gst" class="btn btn-danger btn-block">Update</button>
<a href="gst_master.php" class="btn btn-secondary btn-block">Cancel</a>

<?php else: ?>
<button name="add_gst" class="btn btn-danger btn-block">Save</button>
<button type="button" onclick="clearForm()" class="btn btn-secondary btn-block">Clear</button>
<?php endif; ?>

</form>

</div>
</div>
</div>

<!-- GST LIST -->
<div class="col-md-8">
<div class="panel panel-default">
<div class="panel-heading">GST List</div>

<div class="panel-body">

<input type="text" id="search" class="form-control" placeholder="Search GST..."><br>

<div class="table-responsive">
<table class="table table-bordered table-striped" id="gstTable">

<thead>
<tr>
<th>#</th>
<th>Name</th>
<th>GST %</th>
<th>Action</th>
</tr>
</thead>

<tbody>
<?php foreach($all_gst as $i=>$g): ?>
<tr>
<td><?php echo $i+1; ?></td>
<td><?php echo $g['gst_name']; ?></td>
<td><?php echo $g['gst_percent']; ?>%</td>
<td>
<a href="gst_master.php?edit=<?php echo $g['id']; ?>" class="btn btn-info btn-xs">Edit</a>
<a onclick="return confirm('Delete GST?')" href="gst_master.php?del=<?php echo $g['id']; ?>" class="btn btn-danger btn-xs">Delete</a>
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
/* Auto-focus */
window.onload = function () {
 document.getElementById("gst_name").focus();
};

/* Clear form */
function clearForm(){
 document.querySelector("form").reset();
 document.getElementById("gst_name").focus();
}

/* Search filter */
document.getElementById("search").addEventListener("keyup", function(){
 let value = this.value.toLowerCase();

 document.querySelectorAll("#gstTable tbody tr").forEach(function(row){
   row.style.display = row.textContent.toLowerCase().includes(value) ? "" : "none";
 });
});
</script>

<?php include_once('layouts/footer.php'); ?>
