<?php
$page_title = 'Supplier Master';
require_once('includes/load.php');
//page_require_level(2);

/* FETCH SUPPLIERS LIST */
$suppliers = find_by_sql("SELECT sm.*, gsm.state_name 
                          FROM supplier_master sm
                          LEFT JOIN gst_state_master gsm ON gsm.id = sm.state_id
                          ORDER BY sm.id DESC");

/* FETCH STATE LIST */
$states = find_by_sql("SELECT * FROM gst_state_master ORDER BY state_name ASC");

/* FETCH ALL ORGANIZATIONS FOR PRINT MODAL */
$all_orgs = find_by_sql("SELECT id, org_name, mnemonic FROM organization_master ORDER BY org_name ASC");
/* ---------- ADD SUPPLIER ---------- */
if(isset($_POST['add_supplier'])){

  $name = remove_junk($db->escape($_POST['supplier_name']));
  $phone = remove_junk($db->escape($_POST['phone']));
  $email = remove_junk($db->escape($_POST['email']));
  $contact = remove_junk($db->escape($_POST['contact']));
  $address = remove_junk($db->escape($_POST['address']));
  $state_id = (int)$_POST['state_id'];
  $state_code = remove_junk($db->escape($_POST['state_code']));
  $gst = remove_junk($db->escape($_POST['gst']));

  if($name==''){
     $session->msg("d","Supplier name is required!");
     redirect('supplier_master.php',false);
  }

  $sql = "INSERT INTO supplier_master
          (supplier_name,phone,email,contact_person,address,state_id,state_code,gst_no)
          VALUES ('$name','$phone','$email','$contact','$address','$state_id','$state_code','$gst')";

  if($db->query($sql)){
      $session->msg("s","Supplier added successfully!");
  }else{
      $session->msg("d","Failed to add supplier!");
  }
  redirect('supplier_master.php',false);
}

/* ---------- UPDATE SUPPLIER ---------- */
if(isset($_POST['update_supplier'])){

  $id = (int)$_POST['id'];

  $name = remove_junk($db->escape($_POST['supplier_name']));
  $phone = remove_junk($db->escape($_POST['phone']));
  $email = remove_junk($db->escape($_POST['email']));
  $contact = remove_junk($db->escape($_POST['contact']));
  $address = remove_junk($db->escape($_POST['address']));
  $state_id = (int)$_POST['state_id'];
  $state_code = remove_junk($db->escape($_POST['state_code']));
  $gst = remove_junk($db->escape($_POST['gst']));

  if($name==''){
     $session->msg("d","Supplier name is required!");
     redirect('supplier_master.php',false);
  }

  $sql = "UPDATE supplier_master SET
          supplier_name='$name',
          phone='$phone',
          email='$email',
          contact_person='$contact',
          address='$address',
          state_id='$state_id',
          state_code='$state_code',
          gst_no='$gst'
          WHERE id='$id'";

  if($db->query($sql)){
      $session->msg("s","Supplier updated successfully!");
  } else {
      $session->msg("d","Update failed!");
  }
  redirect('supplier_master.php',false);
}

/* ---------- EDIT LOAD ---------- */
$edit=false;
if(isset($_GET['edit'])){
   $eid=(int)$_GET['edit'];
   $edit=find_by_id("supplier_master",$eid);
}

/* ---------- DELETE ---------- */
if(isset($_GET['del'])){
   $id=(int)$_GET['del'];
   if($db->query("DELETE FROM supplier_master WHERE id='$id'")){
       $session->msg("s","Supplier deleted successfully!");
   } else {
       $session->msg("d","Failed to delete supplier!");
   }
   redirect('supplier_master.php',false);
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

.title-header {
    text-align: center;
    border-bottom: 2px solid #2b8cff !important;
    font-size: 15px;
    font-weight: 700;
    letter-spacing: 0.8px;
    color: #1d3557;
    padding: 10px 0;
}

.title-header i {
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
    color: #fff !important;
    border: none;
}

.btn-success-custom:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    color: #fff !important;
    box-shadow: 0 2px 6px rgba(16, 185, 129, 0.3);
}

.btn-primary-custom {
    background: linear-gradient(135deg, #2b8cff 0%, #1d72db 100%);
    color: #fff !important;
    border: none;
}

.btn-primary-custom:hover {
    background: linear-gradient(135deg, #1d72db 0%, #155bc4 100%);
    color: #fff !important;
}

.btn-clear {
    background: #f1f5f9;
    color: #64748b !important;
    border: 1px solid #cbd5e1;
}

.btn-clear:hover {
    background: #e2e8f0;
    color: #334155 !important;
}

/* Scrollable Table Container */
.table-scrollable {
    max-height: 320px;
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

/* Action Buttons Flexbox Layout */
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
    <!-- ADD/EDIT FORM (TOP FULL-WIDTH) -->
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading title-header">
                <i class="glyphicon glyphicon-user"></i>
                <?php echo $edit ? 'EDIT SUPPLIER MASTER' : 'SUPPLIER MASTER'; ?>
            </div>
            <div class="panel-body">
                <form method="post" id="supplierForm">
                    <?php if($edit){ ?>
                        <input type="hidden" name="id" value="<?php echo $edit['id']; ?>">
                    <?php } ?>

                    <!-- Row 1: Supplier Name, Phone, Email -->
                    <div class="row">
                        <div class="col-md-4 form-group-compact">
                            <label>Supplier Name *</label>
                            <input type="text" id="supplier_name" name="supplier_name" class="form-control"
                                   value="<?php echo $edit ? $edit['supplier_name'] : ''; ?>"
                                   placeholder="Enter supplier name..." required autofocus>
                        </div>

                        <div class="col-md-4 form-group-compact">
                            <label>Phone / Contact No</label>
                            <input type="text" name="phone" class="form-control"
                                   value="<?php echo $edit ? $edit['phone'] : ''; ?>"
                                   placeholder="Phone number...">
                        </div>

                        <div class="col-md-4 form-group-compact">
                            <label>Email Address</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?php echo $edit ? $edit['email'] : ''; ?>"
                                   placeholder="Email address...">
                        </div>
                    </div>

                    <!-- Row 2: Contact Person, State, State Code, GST No -->
                    <div class="row">
                        <div class="col-md-3 form-group-compact">
                            <label>Contact Person</label>
                            <input type="text" name="contact" class="form-control"
                                   value="<?php echo $edit ? $edit['contact_person'] : ''; ?>"
                                   placeholder="Person name...">
                        </div>

                        <div class="col-md-3 form-group-compact">
                            <label>State</label>
                            <select name="state_id" id="state_id" class="form-control" required>
                                <option value="">Select State</option>
                                <?php foreach($states as $s){ ?>
                                    <option value="<?php echo $s['id']; ?>"
                                            data-code="<?php echo $s['state_code']; ?>"
                                            <?php if($edit && $edit['state_id']==$s['id']) echo "selected"; ?>>
                                        <?php echo $s['state_name']; ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="col-md-2 form-group-compact">
                            <label>State Code</label>
                            <input type="text" name="state_code" id="state_code" class="form-control"
                                   value="<?php echo $edit ? $edit['state_code'] : ''; ?>"
                                   placeholder="State Code" readonly>
                        </div>

                        <div class="col-md-4 form-group-compact">
                            <label>GST No</label>
                            <input type="text" name="gst" id="gst" class="form-control"
                                   value="<?php echo $edit ? $edit['gst_no'] : ''; ?>"
                                   placeholder="Enter GST Number (e.g. 09AAAAA0000A1Z5)">
                        </div>
                    </div>

                    <!-- Row 3: Address & Buttons -->
                    <div class="row">
                        <div class="col-md-8 form-group-compact">
                            <label>Address</label>
                            <textarea name="address" rows="1" class="form-control"
                                      placeholder="Complete address..."><?php echo $edit ? $edit['address'] : ''; ?></textarea>
                        </div>

                        <div class="col-md-4 form-group-compact text-right" style="padding-top: 18px;">
                            <?php if(!$edit){ ?>
                                <button type="button" class="btn btn-clear btn-custom" onclick="clearForm()">
                                    Clear
                                </button>
                            <?php } ?>

                            <?php if($edit){ ?>
                                <a href="supplier_master.php" class="btn btn-clear btn-custom">
                                    Cancel
                                </a>
                                <button class="btn btn-primary-custom btn-custom" name="update_supplier">
                                    Update Supplier
                                </button>
                            <?php }else{ ?>
                                <button class="btn btn-success-custom btn-custom" name="add_supplier">
                                    Save Supplier
                                </button>
                            <?php } ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- LIST (BOTTOM FULL-WIDTH) -->
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading">SUPPLIER LIST</div>

            <div class="panel-body">
                <div class="search-box">
                    <i class="glyphicon glyphicon-search"></i>
                    <input type="text" id="search" class="form-control" placeholder="Search supplier...">
                </div>

                <div class="table-scrollable">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th width="40" class="text-center">#</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Contact Person</th>
                                <th>Address</th>
                                <th>State</th>
                                <th class="text-center">GST Code</th>
                                <th>GST No</th>
                                <th width="80" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="supplierTable">
                            <?php if(!empty($suppliers)): ?>
                                <?php foreach($suppliers as $i=>$s): ?>
                                <tr>
                                    <td class="text-center"><?php echo $i+1; ?></td>
                                    <td><strong><?php echo $s['supplier_name']; ?></strong></td>
                                    <td><?php echo $s['phone']; ?></td>
                                    <td><?php echo $s['email']; ?></td>
                                    <td><?php echo $s['contact_person']; ?></td>
                                    <td class="address-cell"><?php echo $s['address']; ?></td>
                                    <td><span class="badge-state"><?php echo $s['state_name']; ?></span></td>
                                    <td class="text-center"><?php echo $s['state_code']; ?></td>
                                    <td><?php echo $s['gst_no']; ?></td>
                                    <td class="action-td">
                                        <div class="action-cell">
                                            <a href="supplier_master.php?edit=<?php echo $s['id']; ?>" class="btn btn-primary btn-xs equal-btn" title="Edit">
                                                <i class="glyphicon glyphicon-pencil"></i>
                                            </a>
                                            <button type="button" onclick="confirmDelete(<?php echo $s['id']; ?>)" class="btn btn-danger btn-xs equal-btn" title="Delete">
                                                <i class="glyphicon glyphicon-trash"></i>
                                            </button>

                                          <button type="button" 
        onclick="openPrintModal(<?php echo $s['id']; ?>, '<?php echo addslashes($s['supplier_name']); ?>')" 
        class="btn btn-info btn-xs equal-btn" 
        title="Print Address Label">
    <i class="glyphicon glyphicon-envelope"></i>
</button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center" style="color: #94a3b8; padding: 15px;">No suppliers found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="printLabelModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-sm" role="document">
    <div class="modal-content">
      <div class="modal-header" style="background: #18233b; color: #fff;">
        <button type="button" class="close" data-dismiss="modal" style="color: #fff;">&times;</button>
        <h4 class="modal-title" style="font-size: 14px; font-weight: 700;">
            <i class="glyphicon glyphicon-print"></i> Print Address Label
        </h4>
      </div>
      <form action="address_label_print.php" method="GET" target="_blank">
          <div class="modal-body">
              <input type="hidden" name="type" value="supplier">
              <input type="hidden" name="supp_id" id="modal_supp_id">
              
              <div class="form-group">
                  <label>Selected Supplier (TO)</label>
                  <input type="text" id="modal_supp_name" class="form-control" readonly style="font-weight: bold;">
              </div>

              <div class="form-group">
                  <label>Select Organization (FROM) *</label>
                  <select name="org_id" class="form-control" required>
                      <option value="">-- Choose Organization --</option>
                      <?php foreach($all_orgs as $o): ?>
                          <option value="<?php echo $o['id']; ?>" <?php if(isset($_SESSION['org_id']) && $_SESSION['org_id'] == $o['id']) echo 'selected'; ?>>
                              <?php echo $o['org_name']; ?> (<?php echo $o['mnemonic']; ?>)
                          </option>
                      <?php endforeach; ?>
                  </select>
              </div>
          </div>
          <div class="modal-footer">
              <button type="button" class="btn btn-default btn-xs" data-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-success btn-xs" style="font-weight: 600;">
                  <i class="glyphicon glyphicon-print"></i> Generate & Print
              </button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
window.onload = function () {
    let input = document.getElementById("supplier_name");
    if(input) input.focus();
};

function clearForm(){
    document.getElementById("supplierForm").reset();
    let input = document.getElementById("supplier_name");
    if(input) input.focus();
}

document.getElementById("state_id").addEventListener("change", function(){
    let code = this.options[this.selectedIndex].getAttribute("data-code") || '';
    document.getElementById("state_code").value = code;
});

/* Search filter */
document.getElementById("search").addEventListener("keyup", function(){
    let value = this.value.toLowerCase();
    document.querySelectorAll("#supplierTable tr").forEach(function(row){
        row.style.display = row.textContent.toLowerCase().includes(value) ? "" : "none";
    });
});

/* GST Auto Uppercase & Format */
document.getElementById("gst").addEventListener("input", function () {
    this.value = this.value
        .toUpperCase()
        .replace(/[^A-Z0-9]/g, '')
        .substring(0, 15);
});

/* Delete confirmation modal */
function confirmDelete(id) {
    Swal.fire({
        title: 'Are You Sure?',
        text: "This Supplier Will Be Deleted!",
        icon: 'warning',
        showCancelButton: true,
        confirmColor: '#ef4444',
        cancelColor: '#6b7280',
        confirmButtonText: 'Yes, Delete It!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "supplier_master.php?del=" + id;
        }
    });
}

function openPrintModal(suppId, suppName) {
    document.getElementById('modal_supp_id').value = suppId;
    document.getElementById('modal_supp_name').value = suppName;
    $('#printLabelModal').modal('show');
}
</script>

<?php include_once('layouts/footer.php'); ?>
