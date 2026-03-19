<?php
$page_title = 'Payment Mode Master';
require_once('includes/load.php');
//page_require_level(1);

/* ================= ADD ================= */

if(isset($_POST['add_mode'])){

  $mode_name = remove_junk($db->escape($_POST['mode_name']));
  $created_by = $_SESSION['user_id'];

  if($mode_name != ""){

  $check = find_by_sql("
  SELECT id FROM payment_mode_master
  WHERE mode_name = '$mode_name'
  AND is_active = 1
");

if(count($check) > 0){
  $session->msg("d", "Payment Mode Already Exists");
  redirect('payment_mode_master.php');
}

    $db->query("
      INSERT INTO payment_mode_master
      (mode_name, is_active, created_by, created_at)
      VALUES
      ('$mode_name', 1, '$created_by', NOW())
    ");

    $session->msg("s", "Payment Mode Added Successfully");
  } else {
    $session->msg("d", "Mode Name Required");
  }

  redirect('payment_mode_master.php');
}


/* ================= DELETE ================= */

if(isset($_GET['delete'])){
  $id = (int)$_GET['delete'];

  $db->query("
    UPDATE payment_mode_master
    SET is_active = 0
    WHERE id = '$id'
  ");

  $session->msg("s", "Payment Mode Deleted");
  redirect('payment_mode_master.php');
}

/* ================= EDIT FETCH ================= */

$edit_mode = null;

if(isset($_GET['edit'])){
  $edit_id = (int)$_GET['edit'];

  $edit_data = find_by_sql("
    SELECT * FROM payment_mode_master
    WHERE id = '$edit_id'
    LIMIT 1
  ");

  if(count($edit_data) > 0){
    $edit_mode = $edit_data[0];
  }
}

/* ================= UPDATE ================= */

if(isset($_POST['update_mode'])){

  $id = (int)$_POST['mode_id'];
  $mode_name = remove_junk($db->escape($_POST['mode_name']));
  $updated_by = $_SESSION['user_id'];

  if($mode_name != ""){

    $db->query("
      UPDATE payment_mode_master
      SET mode_name = '$mode_name',
          updated_at = NOW(),
          updated_by = '$updated_by'
      WHERE id = '$id'
    ");

    $session->msg("s", "Payment Mode Updated Successfully");
  } else {
    $session->msg("d", "Mode Name Required");
  }

  redirect('payment_mode_master.php');
}

/* ================= FETCH ================= */

$modes = find_by_sql("
  SELECT * FROM payment_mode_master
  WHERE is_active = 1
  ORDER BY id DESC
");

include_once('layouts/header.php');
?>

<div class="row">

<!-- LEFT : ADD FORM -->
<div class="col-md-4">
<div class="panel panel-default">
<div class="panel-heading"><strong>Add Payment Mode</strong></div>
<div class="panel-body">

<form method="post">
<input type="hidden" name="mode_id" value="<?= $edit_mode['id'] ?? ''; ?>">

<label>Mode Name</label>
<input type="text"
       name="mode_name"
       class="form-control"
       value="<?= $edit_mode['mode_name'] ?? ''; ?>"
       autofocus
       required><br>

<?php if($edit_mode){ ?>
  <button name="update_mode"
          class="btn btn-primary btn-block">
    Update
  </button>
<?php } else { ?>
  <button name="add_mode"
          class="btn btn-success btn-block">
    Add
  </button>
<?php } ?>

</form>

</div>
</div>
</div>


<!-- RIGHT : LIST -->
<div class="col-md-8">
<div class="panel panel-default">
<div class="panel-heading">
<strong>Payment Modes List</strong>
</div>
<div class="panel-body">

<input type="text"
       id="searchMode"
       class="form-control"
       placeholder="Search payment mode..."><br>

<table class="table table-bordered table-striped">
<thead>
<tr>
<th>#</th>
<th>Mode Name</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>

<tbody id="modeTable">

<?php foreach($modes as $i => $mode){ ?>
<tr>
<td><?= $i+1; ?></td>
<td><?= $mode['mode_name']; ?></td>
<td><?= $mode['is_active'] ? 'Active' : 'Inactive'; ?></td>
<td>

<?php if($mode['is_active']){ ?>

<a href="?edit=<?= $mode['id']; ?>"
   class="btn btn-xs btn-primary">
  Edit
</a>

  <a href="?delete=<?= $mode['id']; ?>"
     class="btn btn-xs btn-danger"
     onclick="return confirm('Are you sure?')">
     Delete
  </a>

<?php } ?>

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
document.getElementById("searchMode").addEventListener("keyup", function() {

  let value = this.value.toLowerCase();
  let rows = document.querySelectorAll("#modeTable tr");

  rows.forEach(row => {
    row.style.display =
      row.innerText.toLowerCase().includes(value)
      ? ""
      : "none";
  });

});
</script>
<?php include_once('layouts/footer.php'); ?>
