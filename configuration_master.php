<?php
$page_title = 'Configuration Master';
require_once('includes/load.php');
page_require_level(2);

// FETCH ORGANIZATION LIST
$org_list = find_by_sql("SELECT id, org_name FROM organization_master ORDER BY org_name");

// -------------------- SAVE NEW --------------------
if(isset($_POST['save'])){
    $org_id = (int)$_POST['org_id'];
    $batch_required = $_POST['batch_required'];
    $gst_registered = $_POST['gst_registered'];

    $sql = "INSERT INTO configuration_master (org_id, batch_required, gst_registered)
            VALUES ('{$org_id}','{$batch_required}','{$gst_registered}')";

    if($db->query($sql)){
        $session->msg("s","Configuration saved successfully");
    }else{
        $session->msg("d","Failed to save configuration");
    }

    redirect('configuration_master.php', false);
}

// -------------------- DELETE --------------------
if(isset($_GET['delete_id'])){
    $id = (int)$_GET['delete_id'];
    $db->query("DELETE FROM configuration_master WHERE id = '{$id}'");
    $session->msg("s","Configuration deleted");
    redirect('configuration_master.php', false);
}

// -------------------- FETCH DATA FOR EDIT --------------------
$edit_data = null;
if(isset($_GET['edit_id'])){
    $id = (int)$_GET['edit_id'];
    $res = find_by_sql("SELECT * FROM configuration_master WHERE id='{$id}' LIMIT 1");
    if($res){
        $edit_data = $res[0];
    }
}

// -------------------- UPDATE --------------------
if(isset($_POST['update'])){
    $id = (int)$_POST['config_id'];
    $org_id = (int)$_POST['org_id'];
    $batch_required = $_POST['batch_required'];
    $gst_registered = $_POST['gst_registered'];

    $sql = "UPDATE configuration_master 
            SET org_id='{$org_id}',
                batch_required='{$batch_required}',
                gst_registered='{$gst_registered}'
            WHERE id='{$id}'";

    if($db->query($sql)){
        $session->msg("s","Configuration updated successfully");
    }else{
        $session->msg("d","Update failed");
    }

    redirect('configuration_master.php', false);
}

// -------------------- SEARCH (SERVER SIDE) --------------------
$search = "";
$where = "";

if(isset($_GET['search']) && $_GET['search'] != ""){
    $search = strtolower($db->escape($_GET['search']));

    $where = "WHERE 
        LOWER(o.org_name) LIKE '%{$search}%'
        OR LOWER(c.batch_required) LIKE '%{$search}%'
        OR LOWER(c.gst_registered) LIKE '%{$search}%'";
}

// -------------------- LIST DATA --------------------
$config_list = find_by_sql("
SELECT c.*, o.org_name 
FROM configuration_master c
LEFT JOIN organization_master o ON o.id = c.org_id
{$where}
ORDER BY c.id DESC
");
?>

<?php include_once('layouts/header.php'); ?>

<div class="row">

<!-- ADD / EDIT FORM -->
<div class="col-md-6">
<div class="panel panel-default">
<div class="panel-heading">
<strong><?php echo $edit_data ? "Edit Configuration" : "Add Configuration"; ?></strong>
</div>

<div class="panel-body">

<form method="post" id="configForm">

<?php if($edit_data): ?>
<input type="hidden" name="config_id" value="<?php echo $edit_data['id']; ?>">
<?php endif; ?>

<div class="form-group">
<label>Organization</label>
<select name="org_id" class="form-control" required>
<option value="">-- Select Organization --</option>

<?php foreach($org_list as $org): ?>
<option value="<?php echo $org['id']; ?>"
<?php if($edit_data && $edit_data['org_id']==$org['id']) echo "selected"; ?>>
<?php echo $org['org_name']; ?>
</option>
<?php endforeach; ?>

</select>
</div>

<div class="form-group">
<label>Batch Required</label>
<select name="batch_required" class="form-control" required>
<option value="Yes" <?php if($edit_data && $edit_data['batch_required']=="Yes") echo "selected"; ?>>Yes</option>
<option value="No" <?php if($edit_data && $edit_data['batch_required']=="No") echo "selected"; ?>>No</option>
</select>
</div>

<div class="form-group">
<label>GST Registered</label>
<select name="gst_registered" class="form-control" required>
<option value="Yes" <?php if($edit_data && $edit_data['gst_registered']=="Yes") echo "selected"; ?>>Yes</option>
<option value="No" <?php if($edit_data && $edit_data['gst_registered']=="No") echo "selected"; ?>>No</option>
</select>
</div>

<?php if($edit_data): ?>
<button type="submit" name="update" class="btn btn-danger">Update</button>
<a href="configuration_master.php" class="btn btn-secondary">Cancel</a>
<?php else: ?>
<button type="submit" name="save" class="btn btn-danger">Save</button>
<?php endif; ?>

<button type="button" class="btn btn-secondary" onclick="document.getElementById('configForm').reset();">
Clear
</button>

</form>

</div>
</div>
</div>

<!-- LIST + SEARCH -->
<div class="col-md-6">
<div class="panel panel-default">
<div class="panel-heading">
<strong>Configuration List</strong>
</div>

<div class="panel-body">

<form method="get" class="form-inline" style="margin-bottom:10px;">
<input type="text" name="search" id="liveSearch" class="form-control"
placeholder="Search by Organization / GST / Batch"
value="<?php echo $search; ?>" style="width:70%;">
<button type="submit" class="btn btn-primary">Search</button>
<a href="configuration_master.php" class="btn btn-default">Reset</a>
</form>

<table class="table table-bordered table-striped">
<thead>
<tr>
<th>#</th>
<th>Organization</th>
<th>Batch</th>
<th>GST</th>
<th>Action</th>
</tr>
</thead>

<tbody id="configTable">
<?php foreach($config_list as $conf): ?>
<tr>
<td><?php echo $conf['id']; ?></td>
<td><?php echo $conf['org_name']; ?></td>
<td><?php echo $conf['batch_required']; ?></td>
<td><?php echo $conf['gst_registered']; ?></td>

<td>
<a href="configuration_master.php?edit_id=<?php echo $conf['id']; ?>" class="btn btn-xs btn-info">Edit</a>

<a onclick="return confirm('Delete karna sure?')" 
href="configuration_master.php?delete_id=<?php echo $conf['id']; ?>" 
class="btn btn-xs btn-danger">Delete</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>

</table>

</div>
</div>
</div>

</div>

<!-- LIVE SEARCH JS -->
<script>
document.getElementById("liveSearch").addEventListener("keyup", function(){
    let val = this.value.toLowerCase();

    document.querySelectorAll("#configTable tr").forEach(function(row){
        row.style.display = row.innerText.toLowerCase().includes(val) ? "" : "none";
    });
});
</script>

<?php include_once('layouts/footer.php'); ?>
