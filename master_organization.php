<?php
$page_title = 'Organization Master';
require_once('includes/load.php');

/* FETCH ORGANIZATIONS */
$orgs = find_by_sql("SELECT * FROM master_inventory.master_organization ORDER BY org_id DESC");

/* FETCH EDIT DATA */
$edit_org = null;

if(isset($_GET['edit'])){
  $id = (int)$_GET['edit'];

  $result = find_by_sql("SELECT * FROM master_inventory.master_organization WHERE org_id='{$id}' LIMIT 1");

  if($result){
    $edit_org = $result[0];
  }
}

/* ADD ORGANIZATION */

if(isset($_POST['add_org'])){

$org_name = remove_junk($db->escape($_POST['org_name']));


/* AUTO GENERATE DATABASE NAME */

$db_name = strtolower(str_replace(" ","_",$org_name))."_inventory";

$db_username = remove_junk($db->escape($_POST['db_username']));
$db_password = remove_junk($db->escape($_POST['db_password']));
$db_link = remove_junk($db->escape($_POST['db_link']));
$port = remove_junk($db->escape($_POST['port']));

$sql = "INSERT INTO master_inventory.master_organization
(org_name,db_name,db_username,db_password,db_link,port)
VALUES(
'{$org_name}',
'{$db_name}',
'{$db_username}',
'{$db_password}',
'{$db_link}',
'{$port}'
)";

if($db->query($sql)){

    $conn = mysqli_connect(
        getenv('MYSQLHOST'),
        getenv('MYSQLUSER'),
        getenv('MYSQLPASSWORD'),
        '',
        getenv('MYSQLPORT')
    );

    if(!$conn){
        die("Connection Failed: " . mysqli_connect_error());
    }

    mysqli_query($conn,"CREATE DATABASE IF NOT EXISTS `$db_name`");

    $template_db = getenv('MYSQLDATABASE');

    $result = mysqli_query($conn,"SHOW TABLES FROM `$template_db`");

    if(!$result){
        die("Error fetching tables: " . mysqli_error($conn));
    }

    while($row = mysqli_fetch_row($result)){
        $table = $row[0];

        mysqli_query($conn,"
        CREATE TABLE `$db_name`.`$table`
        LIKE `$template_db`.`$table`
        ");
    }

    $session->msg('s',"Organization Added & Database Created Automatically");
    redirect('master_organization.php', false);
    exit();

}else{

    $session->msg('d',"Failed to add organization");
    redirect('master_organization.php', false);
    exit();
}
}

/* DELETE ORGANIZATION */

if(isset($_GET['delete'])){

$id = (int)$_GET['delete'];

$sql = "DELETE FROM master_inventory.master_organization
WHERE org_id='{$id}'";

$db->query($sql);

$session->msg("s","Organization Deleted");
redirect('master_organization.php');

}

if(isset($_POST['update_org'])){

$id = (int)$_POST['org_id'];

$org_name = remove_junk($db->escape($_POST['org_name']));
$db_name = remove_junk($db->escape($_POST['db_name']));
$db_username = remove_junk($db->escape($_POST['db_username']));
$db_password = remove_junk($db->escape($_POST['db_password']));
$db_link = remove_junk($db->escape($_POST['db_link']));
$port = remove_junk($db->escape($_POST['port']));

$sql = "UPDATE master_inventory.master_organization SET
org_name='{$org_name}',
db_name='{$db_name}',
db_username='{$db_username}',
db_password='{$db_password}',
db_link='{$db_link}',
port='{$port}'
WHERE org_id='{$id}'";

$db->query($sql);

$session->msg('s',"Organization Updated");
redirect('master_organization.php');

}

include_once('layouts/header.php');
?>

<div class="row">

<!-- ADD ORGANIZATION FORM -->

<div class="col-md-4">

<div class="panel panel-default">

<div class="panel-heading">
<strong>ADD ORGANIZATION</strong>
</div>

<div class="panel-body">

<form method="post">

<?php if($edit_org): ?>
<input type="hidden" name="org_id" value="<?php echo $edit_org['org_id']; ?>">
<?php endif; ?>

<div class="form-group">
<input type="text"
name="org_name"
class="form-control"
value="<?php echo $edit_org ? $edit_org['org_name'] : ''; ?>"
placeholder="Organization Name"
required>
</div>

<div class="form-group">
<input type="text"
name="db_username"
class="form-control"
placeholder="DB Username"
value="root"
required>
</div>

<div class="form-group">
<input type="text"
name="db_password"
class="form-control"
value="<?php echo $edit_org ? $edit_org['db_password'] : ''; ?>"
placeholder="DB Password">
</div>

<div class="form-group">
<input type="text"
name="db_link"
class="form-control"
placeholder="DB Link (127.0.0.1)"
value="inventorysystemphp-production.up.railway.app">
</div>

<div class="form-group">
<input type="text"
name="port"
class="form-control"
placeholder="Port"
value="3306">
</div>

<?php if($edit_org): ?>

<button type="submit" name="update_org" class="btn btn-primary">
Update Organization
</button>

<a href="master_organization.php" class="btn btn-default">Cancel</a>

<?php else: ?>

<button type="submit" name="add_org" class="btn btn-danger">
Save Organization
</button>

<?php endif; ?>

</form>

</div>
</div>

</div>


<!-- ORGANIZATION LIST -->

<div class="col-md-8">

<div class="panel panel-default">

<div class="panel-heading">
<strong>ORGANIZATION LIST</strong>
</div>

<div class="panel-body">

<input type="text"
id="orgSearch"
class="form-control"
placeholder="Search organization...">

<br>

<div class="table-responsive">
<table class="table table-bordered" id="orgTable">

<thead>

<tr>
<th>#</th>
<th>Organization</th>
<th>Database Name</th>
<th>DB Username</th>
<th>DB Link</th>
<th>Port</th>
<th>Action</th>
</tr>

</thead>

<tbody>

<?php foreach($orgs as $org): ?>

<tr>

<td><?php echo $org['org_id']; ?></td>
<td><?php echo $org['org_name']; ?></td>
<td><?php echo $org['db_name']; ?></td>
<td><?php echo $org['db_username']; ?></td>
<td><?php echo $org['db_link']; ?></td>
<td><?php echo $org['port']; ?></td>

<td>

<div style="display:flex; gap:5px;">
  <a href="?edit=<?php echo $org['org_id']; ?>"
  class="btn btn-info btn-xs">Edit</a>

  <a href="?delete=<?php echo $org['org_id']; ?>"
  class="btn btn-danger btn-xs"
  onclick="return confirm('Delete this organization?')">Delete</a>
</div>

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

document.getElementById("orgSearch").addEventListener("keyup", function(){

var filter = this.value.toLowerCase();
var rows = document.querySelectorAll("#orgTable tbody tr");

rows.forEach(function(row){

var text = row.innerText.toLowerCase();
row.style.display = text.indexOf(filter) > -1 ? "" : "none";

});

});

</script>

<?php include_once('layouts/footer.php'); ?>
