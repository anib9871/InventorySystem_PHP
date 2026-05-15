<?php
$page_title = 'Center Master';
require_once('includes/load.php');


/* FETCH CENTERS (DESC) */
$org_id = $_SESSION['org_id'];

$centers = find_by_sql("
SELECT c.center_id,c.center_name
FROM master_center c
WHERE c.org_id='{$org_id}'
ORDER BY c.center_id DESC
");

/* ================= ADD CENTER ================= */
if(isset($_POST['add_center'])){

  $center_name = remove_junk($db->escape($_POST['center_name']));
  $org_id = $_SESSION['org_id'];

  if($center_name == "" || $org_id == 0){
    $session->msg('d',"All fields required");
    redirect('master_center.php', false);
  }

$sql = "INSERT INTO master_center (center_name,org_id)
        VALUES('{$center_name}','{$org_id}')";

  if($db->query($sql)){
    $session->msg('s',"Center Added Successfully");
  } else {
    $session->msg('d',"Failed to add center");
  }

  redirect('master_center.php', false);
}

/* ================= EDIT FETCH ================= */
$edit_center = null;

if(isset($_GET['edit'])){
  $id = (int)$_GET['edit'];

  $org_id = $_SESSION['org_id'];

$res = find_by_sql("
SELECT * FROM master_center 
WHERE center_id='{$id}' AND org_id='{$org_id}' 
LIMIT 1
");

  if($res){
    $edit_center = $res[0];
  }
}

/* ================= UPDATE ================= */
if(isset($_POST['update_center'])){

  $id = (int)$_POST['center_id'];
  $center_name = remove_junk($db->escape($_POST['center_name']));
  $org_id = $_SESSION['org_id'];

$sql = "UPDATE master_center SET
        center_name='{$center_name}',
        org_id='{$org_id}'
        WHERE center_id='{$id}' AND org_id='{$org_id}'";

  if($db->query($sql)){
    $session->msg('s',"Center Updated Successfully");
  } else {
    $session->msg('d',"Update Failed");
  }

  redirect('master_center.php');
}

/* ================= DELETE ================= */
if(isset($_GET['delete'])){
  $id = (int)$_GET['delete'];

 $org_id = $_SESSION['org_id'];

$sql = "DELETE FROM master_center 
        WHERE center_id='{$id}' AND org_id='{$org_id}'";

  $db->query($sql);

  $session->msg("s","Center Deleted");
  redirect('master_center.php');
}

include_once('layouts/header.php');
?>

<div class="row">

<!-- ================= FORM ================= -->
<div class="col-md-4">
<div class="panel panel-default">

<div class="panel-heading">
<strong><?php echo $edit_center ? "EDIT CENTER" : "ADD CENTER"; ?></strong>
</div>

<div class="panel-body">

<form method="post">

<?php if($edit_center): ?>
<input type="hidden" name="center_id" value="<?php echo $edit_center['center_id']; ?>">
<?php endif; ?>



<!-- CENTER NAME -->
<div class="form-group">
<input type="text"
name="center_name"
class="form-control"
placeholder="Center Name"
value="<?php echo $edit_center ? $edit_center['center_name'] : ''; ?>"
required autofocus>
</div>

<!-- BUTTONS -->
<?php if($edit_center): ?>

<button type="submit" name="update_center" class="btn btn-primary">
Update Center
</button>

<a href="master_center.php" class="btn btn-default">
Cancel
</a>

<?php else: ?>

<button type="submit" name="add_center" class="btn btn-danger">
Save Center
</button>

<?php endif; ?>

</form>

</div>
</div>
</div>

<!-- ================= LIST ================= -->
<div class="col-md-8">
<div class="panel panel-default">

<div class="panel-heading">
<strong>CENTER LIST</strong>
</div>

<div class="panel-body">

<input type="text" id="centerSearch" class="form-control" placeholder="Search center..."><br>

<table class="table table-bordered" id="centerTable">

<thead>
<tr>
<th>#</th>
<th>Center Name</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php foreach($centers as $i => $center): ?>

<tr>
<td><?php echo $i+1; ?></td>
<td><?php echo $center['center_name']; ?></td>


<td>
<a href="?edit=<?php echo $center['center_id']; ?>" class="btn btn-info btn-xs">Edit</a>

<a href="?delete=<?php echo $center['center_id']; ?>"
class="btn btn-danger btn-xs"
onclick="return confirm('Delete this center?')">Delete</a>
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
document.getElementById("centerSearch").addEventListener("keyup", function(){
  let filter = this.value.toLowerCase();

  document.querySelectorAll("#centerTable tbody tr").forEach(function(row){
    row.style.display = row.innerText.toLowerCase().includes(filter) ? "" : "none";
  });
});
</script>

<?php include_once('layouts/footer.php'); ?>
