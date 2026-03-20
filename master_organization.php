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

/* ================= ADD ORGANIZATION ================= */

if(isset($_POST['add_org'])){

  $org_name = remove_junk($db->escape($_POST['org_name']));
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

    /* CONNECT MYSQL (RAILWAY SAFE) */
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

    /* CREATE DATABASE */
    mysqli_query($conn,"CREATE DATABASE IF NOT EXISTS `$db_name`");

    /* TEMPLATE DATABASE (CURRENT DB) */
    $template_db = getenv('MYSQLDATABASE');

    /* GET TABLES */
    $result = mysqli_query($conn,"SHOW TABLES FROM `$template_db`");

    if(!$result){
      die("Error fetching tables: " . mysqli_error($conn));
    }

    /* CLONE TABLE STRUCTURE */
    while($row = mysqli_fetch_row($result)){
      $table = $row[0];

      mysqli_query($conn,"
        CREATE TABLE `$db_name`.`$table`
        LIKE `$template_db`.`$table`
      ");
    }

    $session->msg('s',"Organization Added & Database Created Successfully");
    redirect('master_organization.php', false);
    exit();

  }else{

    $session->msg('d',"Failed to add organization");
    redirect('master_organization.php', false);
    exit();

  }
}

/* ================= DELETE ================= */

if(isset($_GET['delete'])){

  $id = (int)$_GET['delete'];

  $db->query("DELETE FROM master_inventory.master_organization WHERE org_id='{$id}'");

  $session->msg("s","Organization Deleted");
  redirect('master_organization.php');
  exit();
}

/* ================= UPDATE ================= */

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
  exit();
}

include_once('layouts/header.php');
?>

<div class="row">

<!-- FORM -->
<div class="col-md-4">
<div class="panel panel-default">
<div class="panel-heading"><strong>ADD ORGANIZATION</strong></div>

<div class="panel-body">
<form method="post">

<input type="text" name="org_name" class="form-control" placeholder="Organization Name" required><br>
<input type="text" name="db_username" class="form-control" value="root"><br>
<input type="text" name="db_password" class="form-control" placeholder="DB Password"><br>
<input type="text" name="db_link" class="form-control" value="127.0.0.1"><br>
<input type="text" name="port" class="form-control" value="3306"><br>

<button type="submit" name="add_org" class="btn btn-danger">Save Organization</button>

</form>
</div>
</div>
</div>

<!-- LIST -->
<div class="col-md-8">
<div class="panel panel-default">
<div class="panel-heading"><strong>ORGANIZATION LIST</strong></div>

<div class="panel-body">

<table class="table table-bordered">
<thead>
<tr>
<th>#</th>
<th>Organization</th>
<th>Database</th>
<th>Action</th>
</tr>
</thead>

<tbody>
<?php foreach($orgs as $org): ?>
<tr>
<td><?php echo $org['org_id']; ?></td>
<td><?php echo $org['org_name']; ?></td>
<td><?php echo $org['db_name']; ?></td>
<td>
<a href="?delete=<?php echo $org['org_id']; ?>" class="btn btn-danger btn-xs">Delete</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>

</table>

</div>
</div>
</div>

</div>

<?php include_once('layouts/footer.php'); ?>
