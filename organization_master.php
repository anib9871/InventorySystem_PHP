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
  $address  = remove_junk($db->escape($_POST['address']));
  $phone    = remove_junk($db->escape($_POST['phone']));
  $email    = remove_junk($db->escape($_POST['email']));
  $contact  = remove_junk($db->escape($_POST['contact']));
  $gst      = remove_junk($db->escape($_POST['gst']));
  $state_id = (int)$_POST['state_id'];
  $state_code = remove_junk($db->escape($_POST['state_code']));

  // ❌ Backend Validation Alerts
  if(empty($mnemonic)){
      $session->msg("d", "Mnemonic field khali nahi ho sakta!");
      redirect('organization_master.php', false);
  }

  $master_org_id = $_SESSION['org_id'];

  $master_org = find_by_sql("
  SELECT org_id, org_name 
  FROM master_inventory.master_organization 
  WHERE org_id = '{$master_org_id}'
  LIMIT 1
  ");

  if(!$master_org){
      $session->msg("d", "Invalid Master Organization found!");
      redirect('organization_master.php', false);
  }

  $org_id   = $master_org[0]['org_id'];
  $org_name = $master_org[0]['org_name'];

  $sql = "INSERT INTO organization_master
  (id,mnemonic,org_name,address,state_id,state_code,phone,email,contact_person,gst_no)
  VALUES
  ('$org_id','$mnemonic','$org_name','$address','$state_id','$state_code',
   '$phone','$email','$contact','$gst')";

  if($db->query($sql)){
      $session->msg("s","Organization added successfully!");
  } else {
      $session->msg("d","Failed to add organization to database!");
  }
  redirect('organization_master.php',false);
}

/* UPDATE ORGANIZATION */
if(isset($_POST['update_org'])){

  $id = (int)$_POST['id'];

  $mnemonic = strtoupper(substr(remove_junk($db->escape($_POST['mnemonic'])),0,5));
  $master_org_id = $_SESSION['org_id'];

  if(empty($mnemonic)){
      $session->msg("d", "Mnemonic field khali nahi ho sakta!");
      redirect('organization_master.php', false);
  }

  $master_org = find_by_sql("
  SELECT org_name 
  FROM master_inventory.master_organization 
  WHERE org_id = '{$master_org_id}'
  LIMIT 1
  ");

  if(!$master_org){
      $session->msg("d", "Master Organization record missing!");
      redirect('organization_master.php', false);
  }

  $org_name = $master_org[0]['org_name'];
  $address  = remove_junk($db->escape($_POST['address']));
  $phone    = remove_junk($db->escape($_POST['phone']));
  $email    = remove_junk($db->escape($_POST['email']));
  $contact  = remove_junk($db->escape($_POST['contact']));
  $gst      = remove_junk($db->escape($_POST['gst']));
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
        WHERE id='{$_SESSION['org_id']}'";

  if($db->query($sql)){
      $session->msg("s","Organization updated successfully!");
  } else {
      $session->msg("d","Update query failed!");
  }

  redirect('organization_master.php',false);
}

/* EDIT LOAD */
$edit = false;
if(isset($_GET['edit'])){
   $eid = (int)$_GET['edit'];
   $edit = find_by_id("organization_master",$eid);
   if(!$edit){
       $session->msg("d", "Record not found!");
       redirect('organization_master.php', false);
   }
}

/* DELETE */
if(isset($_GET['del'])){
   $id = (int)$_GET['del'];
   if($db->query("DELETE FROM organization_master WHERE id='$id'")){
       $session->msg("s","Organization deleted successfully!");
   } else {
       $session->msg("d","Failed to delete organization!");
   }
   redirect('organization_master.php',false);
}

include_once('layouts/header.php');
?>

<style>
body {
    background-color: #f4f7fb;
}

.panel {
    border: none;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    margin-bottom: 15px;
    background: #ffffff;
}

.panel-heading {
    padding: 10px 15px;
    font-size: 13px;
    font-weight: 700;
    border-bottom: 1px solid #eef2f5 !important;
    background: #ffffff !important;
    color: #1a253f;
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
}

.org-title {
    text-align: center;
    border-bottom: 2px solid #2b8cff !important;
    font-size: 15px;
    font-weight: 700;
    letter-spacing: 0.8px;
    color: #1d3557;
    padding: 10px 0;
}

.org-title i {
    color: #2b8cff;
    margin-right: 6px;
}

.panel-body {
    padding: 15px;
}

label {
    font-size: 11px;
    margin-bottom: 3px;
    font-weight: 600;
    color: #4a5568;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.form-group-compact {
    margin-bottom: 10px;
}

.form-control {
    height: 30px;
    font-size: 12px;
    padding: 3px 8px;
    border-radius: 5px;
    border: 1px solid #cbd5e1;
    box-shadow: none;
    transition: all 0.2s ease-in-out;
}

.form-control:focus {
    border-color: #2b8cff;
    box-shadow: 0 0 0 2px rgba(43, 140, 255, 0.15);
}

.form-control[readonly] {
    background-color: #f1f5f9;
    color: #64748b;
}

textarea.form-control {
    height: 30px;
    resize: none;
    padding-top: 5px;
}

.btn-custom {
    height: 30px;
    font-size: 11px;
    font-weight: 600;
    padding: 0 15px;
    border-radius: 5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: all 0.2s;
}

.btn-success-custom {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #fff;
    border: none;
}

.btn-success-custom:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    color: #fff;
    box-shadow: 0 2px 6px rgba(16, 185, 129, 0.3);
}

.btn-clear {
    background: #f1f5f9;
    color: #64748b;
    border: 1px solid #cbd5e1;
}

.btn-clear:hover {
    background: #e2e8f0;
    color: #334155;
}

/* Table Scroll Properties */
.table-scrollable {
    max-height: 300px;
    overflow-y: auto;
    overflow-x: auto;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
}

.table {
    margin-bottom: 0;
    font-size: 12px;
    width: 100%;
}

.table thead {
    position: sticky;
    top: 0;
    background: #18233b;
    color: #ffffff;
    z-index: 10;
}

.table thead th {
    border: none !important;
    padding: 8px 10px;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: #18233b;
}

.table tbody td {
    padding: 6px 10px;
    vertical-align: middle !important;
    border-color: #f1f5f9;
}

.table tbody tr:hover {
    background: #f8fafc;
}

.badge-state {
    background: #e0f2fe;
    color: #0369a1;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
}

.address-cell {
    min-width: 200px;
    max-width: 240px;
    white-space: normal;
    word-break: break-word;
}

.search-box {
    position: relative;
    width: 280px;
    margin-bottom: 10px;
}

.search-box i {
    position: absolute;
    left: 10px;
    top: 8px;
    color: #94a3b8;
    font-size: 12px;
}

.search-box input {
    padding-left: 30px;
    height: 30px !important;
}

.action-td {
    min-width: 80px;
    white-space: nowrap !important;
}

.action-cell {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 5px !important;
}

.equal-btn {
    width: 28px !important;
    height: 26px !important;
    padding: 0 !important;
    line-height: 26px !important;
    font-size: 11px !important;
    border-radius: 4px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    margin: 0 !important;
}
</style>

<div class="row">
    <!-- ADD/EDIT FORM -->
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading org-title">
                <i class="glyphicon glyphicon-briefcase"></i> ORGANIZATION MASTER
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

                    <!-- Row 1 -->
                    <div class="row">
                        <div class="col-md-2 form-group-compact">
                            <label>Mnemonic</label>
                            <input type="text" maxlength="5" name="mnemonic" class="form-control" value="<?php echo $edit ? $edit['mnemonic'] : ''; ?>" required placeholder="e.g. FERT">
                        </div>

                        <div class="col-md-3 form-group-compact">
                            <label>Organization</label>
                            <input type="text" class="form-control" value="<?php echo $edit ? $edit['org_name'] : $org_name; ?>" readonly>
                        </div>

                        <div class="col-md-3 form-group-compact">
                            <label>Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo $edit ? $edit['phone'] : ''; ?>" placeholder="Phone Number">
                        </div>

                        <div class="col-md-4 form-group-compact">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" value="<?php echo $edit ? $edit['email'] : ''; ?>" placeholder="Email Address">
                        </div>
                    </div>

                    <!-- Row 2 -->
                    <div class="row">
                        <div class="col-md-3 form-group-compact">
                            <label>Contact Person</label>
                            <input type="text" name="contact" class="form-control" value="<?php echo $edit ? $edit['contact_person'] : ''; ?>" placeholder="Person Name">
                        </div>

                        <div class="col-md-3 form-group-compact">
                            <label>State</label>
                            <select name="state_id" id="state_id" class="form-control">
                                <option value="">Select State</option>
                                <?php foreach($states as $s){ ?>
                                    <option value="<?php echo $s['id'];?>" data-code="<?php echo $s['state_code'];?>" <?php if($edit && $edit['state_id']==$s['id']) echo "selected"; ?>>
                                        <?php echo $s['state_name'];?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="col-md-2 form-group-compact">
                            <label>State Code</label>
                            <input type="text" id="state_code" name="state_code" class="form-control" readonly value="<?php echo $edit ? $edit['state_code'] : ''; ?>">
                        </div>

                        <div class="col-md-4 form-group-compact">
                            <label>GST No</label>
                            <input type="text" name="gst" class="form-control" value="<?php echo $edit ? $edit['gst_no'] : ''; ?>" placeholder="22AAAAA0000A1Z5">
                        </div>
                    </div>

                    <!-- Row 3 -->
                    <div class="row">
                        <div class="col-md-8 form-group-compact">
                            <label>Address</label>
                            <textarea name="address" rows="1" class="form-control" placeholder="Complete address..."><?php echo $edit ? $edit['address'] : ''; ?></textarea>
                        </div>

                        <div class="col-md-4 form-group-compact text-right" style="padding-top: 18px;">
                            <?php if(!$edit){ ?>
                                <button type="button" class="btn btn-clear btn-custom" onclick="document.getElementById('orgForm').reset()">
                                    Clear
                                </button>
                            <?php } ?>

                            <?php if($edit){ ?>
                                <button class="btn btn-success-custom btn-custom" name="update_org">
                                    Update Organization
                                </button>
                            <?php }else{ ?>
                                <button class="btn btn-success-custom btn-custom" name="add_org">
                                    Save Organization
                                </button>
                            <?php } ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- LIST -->
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading">ORGANIZATION LIST</div>

            <div class="panel-body">
                <div class="search-box">
                    <i class="glyphicon glyphicon-search"></i>
                    <input type="text" id="search" class="form-control" placeholder="Search organization...">
                </div>

                <div class="table-scrollable">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th width="40">#</th>
                                <th>Mnemonic</th>
                                <th>Organization</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Contact</th>
                                <th>Address</th>
                                <?php if(isset($gst_enabled) && $gst_enabled == "Yes"): ?>
                                    <th>State</th>
                                    <th>State Code</th>
                                    <th>GST No</th>
                                <?php endif; ?>
                                <th width="80" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="organizationTable">
                            <?php foreach($orgs as $i=>$o): ?>
                            <tr>
                                <td><?php echo $i+1; ?></td>
                                <td><strong><?php echo $o['mnemonic']; ?></strong></td>
                                <td><?php echo $o['org_name']; ?></td>
                                <td><?php echo $o['phone']; ?></td>
                                <td><?php echo $o['email']; ?></td>
                                <td><?php echo $o['contact_person']; ?></td>
                                <td class="address-cell"><?php echo $o['address']; ?></td>
                                <?php if(isset($gst_enabled) && $gst_enabled == "Yes"): ?>
                                    <td><span class="badge-state"><?php echo $o['state_name']; ?></span></td>
                                    <td><?php echo $o['state_code']; ?></td>
                                    <td><?php echo $o['gst_no']; ?></td>
                                <?php endif; ?>
                                <td class="action-td">
                                    <div class="action-cell">
                                        <a href="organization_master.php?edit=<?php echo $o['id']; ?>" class="btn btn-primary btn-xs equal-btn" title="Edit">
                                            <i class="glyphicon glyphicon-pencil"></i>
                                        </a>
                                        <button type="button" onclick="confirmDelete(<?php echo $o['id']; ?>)" class="btn btn-danger btn-xs equal-btn" title="Delete">
                                            <i class="glyphicon glyphicon-trash"></i>
                                        </button>
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
</div>

<script>
document.getElementById("state_id").addEventListener("change", function(){
    let code = this.options[this.selectedIndex].getAttribute("data-code") || '';
    document.getElementById("state_code").value = code;
});

document.getElementById("search").addEventListener("keyup", function(){
    let value = this.value.toLowerCase();
    document.querySelectorAll("#organizationTable tr").forEach(function(row){
        row.style.display = row.textContent.toLowerCase().includes(value) ? "" : "none";
    });
});

function confirmDelete(id) {
    Swal.fire({
        title: 'Are You Sure?',
        text: "This organization will be deleted!",
        icon: 'warning',
        showCancelButton: true,
        confirmColor: '#ef4444',
        cancelColor: '#6b7280',
        confirmButtonText: 'Yes, Delete It!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "organization_master.php?del=" + id;
        }
    });
}
</script>

<?php include_once('layouts/footer.php'); ?>
