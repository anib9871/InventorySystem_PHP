<?php
$page_title = 'Organization Master';
require_once('includes/load.php');
//page_require_level(2);

/* FETCH ORGANIZATION LIST */
$org_id = $_SESSION['org_id'];

$orgs = find_by_sql("SELECT om.*, gsm.state_name 
FROM organization_master om
LEFT JOIN gst_state_master gsm ON gsm.id = om.state_id
WHERE om.id = '{$org_id}'
ORDER BY om.id DESC");

/* FETCH STATE LIST */
$states = find_by_sql("SELECT * FROM gst_state_master ORDER BY state_name ASC");

/* ADD ORGANIZATION */
if(isset($_POST['add_org'])){

  $mnemonic = strtoupper(substr(remove_junk($db->escape($_POST['mnemonic'])),0,5));


  $address = remove_junk($db->escape($_POST['address']));
  $phone = remove_junk($db->escape($_POST['phone']));
  $email = remove_junk($db->escape($_POST['email']));
  $contact = remove_junk($db->escape($_POST['contact']));
  $gst = remove_junk($db->escape($_POST['gst']));
  $state_id = (int)$_POST['state_id'];
  $state_code = remove_junk($db->escape($_POST['state_code']));

$master_org_id = $_SESSION['org_id'];

$master_org = find_by_sql("
SELECT org_id, org_name 
FROM master_inventory.master_organization 
WHERE org_id = '{$master_org_id}'
LIMIT 1
");

if(!$master_org){
  die("Invalid Master Organization");
}

$org_id   = $master_org[0]['org_id'];
$org_name = $master_org[0]['org_name'];


$sql = "INSERT INTO organization_master
(id,mnemonic,org_name,address,state_id,state_code,phone,email,contact_person,gst_no)
VALUES
('$org_id','$mnemonic','$org_name','$address','$state_id','$state_code',
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
  $master_org_id = $_SESSION['org_id'];

    $master_org = find_by_sql("
    SELECT org_name 
    FROM master_inventory.master_organization 
    WHERE org_id = '{$master_org_id}'
    LIMIT 1
    ");

  $org_name = $master_org[0]['org_name'];
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
        WHERE id='{$_SESSION['org_id']}'  ";

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
.add-box{
    margin-top:0;
}

.panel{
    border-radius:10px;
    margin-bottom:12px;
}

.panel-body{
    padding:12px 15px;
}

.panel-heading{
    padding:10px 15px;
    font-size:14px;
    font-weight:600;
}

label{
    font-size:12px;
    margin-bottom:4px;
    font-weight:600;
}

.form-control{
    height:32px;
    font-size:12px;
    padding:4px 8px;
}

textarea.form-control{
    height:55px;
    resize:none;
    padding-top:6px;
}

.btn{
    height:32px;
    font-size:12px;
    padding:4px 12px;
}

.row{
    margin-bottom:8px;
}

.table{
    margin-bottom:0;
    font-size:12px;
}

.table thead{
    background:#18233b;
    color:#fff;
}

.table thead th{
    border:none !important;
    padding:11px 10px;
    white-space:nowrap;
    vertical-align:middle !important;
}

.table tbody td{
    padding:6px 8px;
    line-height:18px;
    vertical-align:middle !important;
    font-size:12px;
}

.table tbody tr:hover{
    background:#f5f9ff;
}

.panel-heading{
    background:#fff !important;
    border-bottom:2px solid #2b8cff;
    text-transform:uppercase;
    letter-spacing:.5px;
}

.table-responsive{
    border:1px solid #e6e6e6;
    border-radius:8px;
    overflow:hidden;
}

#search{
    width:320px;
    margin-bottom:12px;
}

.equal-btn{
    width:32px;
    height:28px;
    padding:0;
    margin:1px;
    line-height:28px;
}

.badge-state{
    background:#eaf6ea;
    color:#228b22;
    padding:2px 7px;
    border-radius:10px;
    font-size:11px;
    font-weight:600;
}

.address-cell{
    min-width:260px;
    max-width:260px;
    white-space:normal;
    word-break:break-word;
    line-height:18px;
}

.org-table{
    overflow-x:auto;
    overflow-y:hidden;
    white-space:nowrap;
}

.org-table table{
    min-width:1250px;
}

.search-box{
    position:relative;
    width:320px;
    margin-bottom:12px;
}

.search-box i{
    position:absolute;
    left:12px;
    top:9px;
    color:#888;
    z-index:2;
}

.search-box input{
    padding-left:35px;
    height:34px !important;
}

.org-title{
    text-align:center;
    background:#ffffff !important;
    border-bottom:2px solid #2b8cff;
    font-size:18px;
    font-weight:700;
    letter-spacing:1px;
    color:#1d3557;
    padding:12px 0;
}

.org-title i{
    color:#2b8cff;
    margin-right:8px;
    font-size:16px;
}
</style>

<div class="row">
<div class="col-md-12"><?php echo display_msg($msg); ?></div>
</div>

<div class="row">

<!-- ADD FORM -->
<div class="col-md-12">

<div class="panel panel-default">

<div class="panel-heading text-center org-title">
    <i class="glyphicon glyphicon-briefcase"></i>
    ORGANIZATION
</div>

<div class="panel-body">

<form method="post" id="orgForm">

<?php if($edit){ ?>
<input type="hidden" name="id" value="<?php echo $edit['id']; ?>">
<?php } ?>

<?php
$master_org = find_by_sql("
SELECT org_name
FROM master_inventory.master_organization
WHERE org_id = '{$_SESSION['org_id']}'
LIMIT 1
");
$org_name = $master_org ? $master_org[0]['org_name'] : '';
?>

<div class="row">

<div class="col-md-2">
<label>Mnemonic</label>
<input type="text" maxlength="5"
name="mnemonic"
class="form-control"
value="<?php echo $edit ? $edit['mnemonic'] : ''; ?>"
required>
</div>

<div class="col-md-3">
<label>Organization</label>
<input type="text"
class="form-control"
value="<?php echo $edit ? $edit['org_name'] : $org_name; ?>"
readonly>
</div>

<div class="col-md-2">
<label>Phone</label>
<input type="text"
name="phone"
class="form-control"
value="<?php echo $edit ? $edit['phone'] : ''; ?>">
</div>

<div class="col-md-3">
<label>Email</label>
<input type="email"
name="email"
class="form-control"
value="<?php echo $edit ? $edit['email'] : ''; ?>">
</div>

<div class="col-md-2" style="padding-top:21px">

<?php if($edit){ ?>

<button
class="btn btn-success btn-block"
name="update_org">
Update
</button>

<?php }else{ ?>

<button
class="btn btn-success btn-block"
name="add_org">
Save Organization
</button>

<?php } ?>

</div>

</div>

<div class="row" style="margin-top:8px;">

<div class="col-md-3">
<label>Contact Person</label>
<input
type="text"
name="contact"
class="form-control"
value="<?php echo $edit ? $edit['contact_person'] : ''; ?>">
</div>

<div class="col-md-3">
<label>State</label>

<select
name="state_id"
id="state_id"
class="form-control">

<option value="">Select State</option>

<?php foreach($states as $s){ ?>

<option
value="<?php echo $s['id'];?>"
data-code="<?php echo $s['state_code'];?>"
<?php if($edit && $edit['state_id']==$s['id']) echo "selected"; ?>>

<?php echo $s['state_name'];?>

</option>

<?php } ?>

</select>

</div>

<div class="col-md-2">

<label>State Code</label>

<input
type="text"
id="state_code"
name="state_code"
class="form-control"
readonly
value="<?php echo $edit ? $edit['state_code'] : ''; ?>">

</div>

<div class="col-md-4">

<label>GST No</label>

<input
type="text"
name="gst"
class="form-control"
value="<?php echo $edit ? $edit['gst_no'] : ''; ?>">

</div>

</div>

<div class="row" style="margin-top:8px;">

<div class="col-md-12">

<label>Address</label>

<textarea
name="address"
rows="1"
class="form-control"><?php echo $edit ? $edit['address'] : ''; ?></textarea>

</div>

</div>

<?php if(!$edit){ ?>

<div class="text-right" style="margin-top:15px">

<button
type="button"
class="btn btn-default"
onclick="document.getElementById('orgForm').reset()">
Clear
</button>

</div>

<?php } ?>

</form>

</div>
</div>

</div>

<!-- LIST -->
<div class="col-md-12">
<div class="panel panel-default">
<div class="panel-heading">Organization List</div>

<div class="panel-body">

<div class="search-box">
    <i class="glyphicon glyphicon-search"></i>

    <input
        type="text"
        id="search"
        class="form-control"
        placeholder="Search organization...">
</div>

<div class="table-responsive org-table">

<table class="table table-bordered table-striped">

<thead>
<tr>
<th width="45">#</th>
<th>Mnemonic</th>
<th>Organization</th>
<th>Phone</th>
<th>Email</th>
<th>Contact</th>
<th style="min-width:260px;">Address</th>
<?php if($gst_enabled == "Yes"): ?>

<th>State</th>
<th>State Code</th>
<th>GST No</th>

<?php endif; ?>
<th width="90" class="text-center">Action</th>
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
<td class="address-cell">
<?php echo $o['address']; ?>
</td>
<?php if($gst_enabled == "Yes"): ?>

<td>
<span class="badge-state">
<?php echo $o['state_name']; ?>
</span>
</td>

<td><?php echo $o['state_code']; ?></td>

<td><?php echo $o['gst_no']; ?></td>

<?php endif; ?>
<td class="text-center">
<a href="organization_master.php?edit=<?php echo $o['id']; ?>"
class="btn btn-primary btn-xs equal-btn">
<i class="glyphicon glyphicon-pencil"></i>
</a>

<a onclick="return confirm('Delete karna sure?')"
href="organization_master.php?del=<?php echo $o['id']; ?>"
class="btn btn-danger btn-xs equal-btn">
<i class="glyphicon glyphicon-trash"></i>
</a>
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
