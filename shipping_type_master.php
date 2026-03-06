<?php
$page_title = 'Shipping Type Master';
require_once('includes/load.php');
page_require_level(1);

$gst_list = find_all('gst_master');

/* ================= DELETE ================= */
if(isset($_GET['delete'])){
  $id = (int)$_GET['delete'];

  $db->query("DELETE FROM shipping_type_master WHERE id = '$id'");
  $session->msg("s","Shipping Type Deleted");
  redirect('shipping_type_master.php');
}

/* ================= ADD ================= */
if(isset($_POST['add_type'])){

  $type_name = remove_junk($db->escape($_POST['type_name']));
  $default_gst_id = (int)$_POST['default_gst_id'];
  $is_gst_applicable = (int)$_POST['is_gst_applicable'];
  $created_by = $_SESSION['user_id'];
  $sac_code = remove_junk($db->escape($_POST['sac_code'] ?? ''));

  $db->query("INSERT INTO shipping_type_master
(type_name, sac_code, default_gst_id, is_gst_applicable,
 created_by, created_at)
              VALUES
              ('$type_name', '$sac_code', '$default_gst_id',
               '$is_gst_applicable',
               '$created_by', NOW())");

  $session->msg("s","Shipping Type Added Successfully");
  redirect('shipping_type_master.php');
}

/* ================= UPDATE ================= */
if(isset($_POST['update_type'])){

  $id = (int)$_POST['id'];
  $type_name = remove_junk($db->escape($_POST['type_name']));
  $default_gst_id = (int)$_POST['default_gst_id'];
  $is_gst_applicable = (int)$_POST['is_gst_applicable'];
  $sac_code = remove_junk($db->escape($_POST['sac_code'] ?? ''));

  $db->query("UPDATE shipping_type_master SET
type_name='$type_name',
sac_code='$sac_code',
default_gst_id='$default_gst_id',
is_gst_applicable='$is_gst_applicable',
updated_at=NOW()
              WHERE id='$id'");

  $session->msg("s","Shipping Type Updated Successfully");
  redirect('shipping_type_master.php');
}

/* ================= EDIT FETCH ================= */
$edit = null;
if(isset($_GET['edit'])){
  $id = (int)$_GET['edit'];
  $edit = find_by_id('shipping_type_master',$id);
}

/* ================= FETCH LIST ================= */
$types = find_by_sql("
  SELECT stm.*, gm.gst_percent
  FROM shipping_type_master stm
  LEFT JOIN gst_master gm ON gm.id = stm.default_gst_id
  ORDER BY stm.id DESC
");

include_once('layouts/header.php');
?>

<div class="row">

<!-- LEFT FORM -->
<div class="col-md-4">
<div class="panel panel-default">
<div class="panel-heading">
<strong><?= $edit ? 'Edit Shipping Type' : 'Add Shipping Type'; ?></strong>
</div>
<div class="panel-body">

<form method="post">

<?php if($edit){ ?>
<input type="hidden" name="id" value="<?= $edit['id']; ?>">
<?php } ?>

<label>Type Name</label>
<input type="text"
       id="typeName"
       name="type_name"
       value="<?= $edit ? $edit['type_name'] : ''; ?>"
       class="form-control"
       required><br>

<label>SAC Code (Optional)</label>
<input type="text"
       name="sac_code"
       value="<?= $edit ? $edit['sac_code'] : ''; ?>"
       class="form-control"
       placeholder="Enter SAC Code (if applicable)">
<br>

<label>Default GST</label>
<select name="default_gst_id" class="form-control">
<option value="0">-- None --</option>
<?php foreach($gst_list as $gst){ ?>
<option value="<?= $gst['id']; ?>"
<?= ($edit && $edit['default_gst_id']==$gst['id'])?'selected':''; ?>>
<?= $gst['gst_name']; ?> (<?= $gst['gst_percent']; ?>%)
</option>
<?php } ?>
</select><br>

<label>GST Applicable</label>
<select name="is_gst_applicable" class="form-control">
<option value="1"
<?= ($edit && $edit['is_gst_applicable']==1)?'selected':''; ?>>
Yes
</option>
<option value="0"
<?= ($edit && $edit['is_gst_applicable']==0)?'selected':''; ?>>
No
</option>
</select><br>

<?php if($edit){ ?>
<button name="update_type" class="btn btn-primary btn-block">
Update
</button>
<a href="shipping_type_master.php"
   class="btn btn-default btn-block">
Cancel
</a>
<?php } else { ?>
<button name="add_type" class="btn btn-success btn-block">
Add
</button>
<?php } ?>

</form>

</div>
</div>
</div>

<!-- RIGHT LIST -->
<div class="col-md-8">
<div class="panel panel-default">
<div class="panel-heading">
<strong>Shipping Types List</strong>
</div>
<div class="panel-body">

<input type="text"
       id="searchShipping"
       class="form-control"
       placeholder="Search shipping type..."><br>

<table class="table table-bordered table-striped">
<thead>
<tr>
<th>#</th>
<th>Type Name</th>
<th>Default GST</th>
<th>GST Applicable</th>
<th>SAC</th>
<th width="18%">Action</th>
</tr>
</thead>
<tbody id="shippingTable">

<?php foreach($types as $i => $type){ ?>
<tr>
<td><?= $i+1; ?></td>
<td><?= $type['type_name']; ?></td>
<td><?= $type['gst_percent'] ? $type['gst_percent'].'%' : 'N/A'; ?></td>
<td><?= $type['is_gst_applicable'] ? 'Yes' : 'No'; ?></td>

<!-- SAC FIRST -->
<td><?= $type['sac_code'] ?: 'N/A'; ?></td>

<!-- ACTION AFTER SAC -->
<td>
  <a href="shipping_type_master.php?edit=<?= $type['id']; ?>"
     class="btn btn-xs btn-primary">
     Edit
  </a>

  <a href="shipping_type_master.php?delete=<?= $type['id']; ?>"
     class="btn btn-xs btn-danger"
     onclick="return confirm('Are you sure?')">
     Delete
  </a>
</td>

</tr>
<?php } ?>

</tbody>
</table>

</div>
</div>
</div>

</div>

<script>
document.getElementById("searchShipping").addEventListener("keyup", function() {
  let value = this.value.toLowerCase();
  let rows = document.querySelectorAll("#shippingTable tr");

  rows.forEach(row => {
    row.style.display =
      row.innerText.toLowerCase().includes(value) ? "" : "none";
  });
});

document.addEventListener("DOMContentLoaded", function () {
    const input = document.getElementById("typeName");
    if(input){
        input.focus();
        input.select(); // edit mode me pura text select bhi ho jayega
    }
});
</script>

<?php include_once('layouts/footer.php'); ?>