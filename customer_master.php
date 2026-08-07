<?php
$page_title = 'Customer Master';
require_once('includes/load.php');
//page_require_level(2);

/* FETCH CUSTOMER LIST */
if($_SESSION['role_id'] == 2){
/* ADMIN = ALL CUSTOMERS */
$customers = find_by_sql("
SELECT 
cm.*,
gsm.state_name,
mc.center_name
FROM customer_master cm
LEFT JOIN gst_state_master gsm ON gsm.id = cm.state_id
LEFT JOIN master_center mc ON mc.center_id = cm.center_id
ORDER BY cm.id DESC
");
}else{
/* USER = ONLY OWN CENTER */
$center_id = isset($_SESSION['center_id']) ? (int)$_SESSION['center_id'] : 1;
$customers = find_by_sql("
SELECT 
cm.*,
gsm.state_name,
mc.center_name
FROM customer_master cm
LEFT JOIN gst_state_master gsm ON gsm.id = cm.state_id
LEFT JOIN master_center mc ON mc.center_id = cm.center_id
WHERE cm.center_id = '$center_id'
ORDER BY cm.id DESC
");
}

/* FETCH STATE LIST */
$states = find_by_sql("SELECT * FROM gst_state_master ORDER BY state_name ASC");

/* FETCH ALL ORGANIZATIONS FOR PRINT MODAL */
$all_orgs = find_by_sql("SELECT id, org_name, mnemonic FROM organization_master ORDER BY org_name ASC");

/* ---------- ADD CUSTOMER ---------- */
if(isset($_POST['add_customer'])){

  $name = remove_junk($db->escape($_POST['customer_name']));
  $contact = remove_junk($db->escape($_POST['contact_no']));
  $email = remove_junk($db->escape($_POST['email']));
  $address = remove_junk($db->escape($_POST['address']));
  $state_id = (int)$_POST['state_id'];
  $state_code = remove_junk($db->escape($_POST['state_code']));
  $gst = remove_junk($db->escape($_POST['gst_no']));
  
  if($name==''){
     $session->msg("d","Customer name is required!");
     redirect('customer_master.php',false);
  }

$center_id = isset($_SESSION['center_id']) ? (int)$_SESSION['center_id'] : 1;

$sql = "INSERT INTO customer_master
(customer_name,contact_no,email,address,state_id,state_code,gst_no,center_id)
VALUES('$name','$contact','$email','$address','$state_id','$state_code','$gst','$center_id')";

  if($db->query($sql)){
     $session->msg("s","Customer added successfully!");
  } else {
     $session->msg("d","Failed to add customer!");
  }
  redirect('customer_master.php',false);
}

/* ---------- UPDATE CUSTOMER ---------- */
if(isset($_POST['update_customer'])){

  $id = (int)$_POST['id'];

  $name = remove_junk($db->escape($_POST['customer_name']));
  $contact = remove_junk($db->escape($_POST['contact_no']));
  $email = remove_junk($db->escape($_POST['email']));
  $address = remove_junk($db->escape($_POST['address']));
  $state_id = (int)$_POST['state_id'];
  $state_code = remove_junk($db->escape($_POST['state_code']));
  $gst = remove_junk($db->escape($_POST['gst_no']));
  $center_id = isset($_SESSION['center_id']) ? (int)$_SESSION['center_id'] : 1;

  if($name==''){
     $session->msg("d","Customer name is required!");
     redirect('customer_master.php',false);
  }

  $sql="UPDATE customer_master SET
        customer_name='$name',
        contact_no='$contact',
        email='$email',
        address='$address',
        state_id='$state_id',
        state_code='$state_code',
        gst_no='$gst',
        center_id='$center_id'
        WHERE id='$id'";

  if($db->query($sql)){
     $session->msg("s","Customer updated successfully!");
  } else {
     $session->msg("d","Update failed!");
  }
  redirect('customer_master.php',false);
}

/* ---------- EDIT LOAD ---------- */
$edit=false;
if(isset($_GET['edit'])){
   $eid=(int)$_GET['edit'];
   $edit=find_by_id("customer_master",$eid);
}

/* ---------- DELETE ---------- */
if(isset($_GET['del'])){
   $id=(int)$_GET['del'];
   if($db->query("DELETE FROM customer_master WHERE id='$id'")){
       $session->msg("s","Customer deleted successfully!");
   } else {
       $session->msg("d","Failed to delete customer!");
   }
   redirect('customer_master.php',false);
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
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    text-decoration: none !important;
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

.badge-center {
    background: #e0e7ff;
    color: #3730a3;
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

<!-- FORM (TOP FULL-WIDTH) -->
<div class="col-md-12">
    <div class="panel panel-default">
        <div class="panel-heading title-header">
            <i class="glyphicon glyphicon-user"></i>
            <?php echo $edit ? 'EDIT CUSTOMER MASTER' : 'CUSTOMER MASTER'; ?>
        </div>

        <div class="panel-body">
            <form method="post" id="customerForm">

                <?php if($edit){ ?>
                    <input type="hidden" name="id" value="<?php echo $edit['id']; ?>">
                <?php } ?>

                <!-- Row 1: Name, Contact, Email -->
                <div class="row">
                    <div class="col-md-4 form-group-compact">
                        <label>Customer Name *</label>
                        <input type="text" id="customer_name" name="customer_name" class="form-control"
                               value="<?php echo $edit ? $edit['customer_name'] : ''; ?>"
                               placeholder="Enter customer name..." required autofocus>
                    </div>

                    <div class="col-md-4 form-group-compact">
                        <label>Contact No</label>
                        <input type="text" name="contact_no" class="form-control"
                               value="<?php echo $edit ? $edit['contact_no'] : ''; ?>"
                               placeholder="Contact number...">
                    </div>

                    <div class="col-md-4 form-group-compact">
                        <label>Email Address</label>
                        <input type="email" id="email" name="email" class="form-control"
                               value="<?php echo $edit ? $edit['email'] : ''; ?>"
                               placeholder="Email address...">
                    </div>
                </div>

                <!-- Row 2: Address, State, State Code, GST No -->
                <div class="row">
                    <div class="col-md-5 form-group-compact">
                        <label>Address</label>
                        <textarea name="address" rows="1" class="form-control"
                                  placeholder="Complete address..."><?php echo $edit ? $edit['address'] : ''; ?></textarea>
                    </div>

                    <div class="col-md-3 form-group-compact">
                        <label>State</label>
                        <select name="state_id" id="state_id" class="form-control">
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

                    <div class="col-md-1 form-group-compact">
                        <label>Code</label>
                        <input type="text" name="state_code" id="state_code" class="form-control"
                               value="<?php echo $edit ? $edit['state_code'] : ''; ?>"
                               placeholder="Code" readonly>
                    </div>

                    <div class="col-md-3 form-group-compact">
                        <label>GST No</label>
                        <input type="text" id="gst_no" name="gst_no" class="form-control"
                               value="<?php echo $edit ? $edit['gst_no'] : ''; ?>"
                               placeholder="GST Number...">
                    </div>
                </div>

                <!-- Row 3: Action Buttons -->
                <div class="row">
                    <div class="col-md-12 text-right" style="padding-top: 5px;">
                        <?php if(!$edit){ ?>
                            <button type="button" class="btn btn-clear btn-custom" onclick="clearForm()">
                                Clear
                            </button>
                        <?php } ?>

                        <?php if($edit){ ?>
                            <a href="customer_master.php" class="btn btn-clear btn-custom">
                                Cancel
                            </a>
                            <button class="btn btn-primary-custom btn-custom" name="update_customer">
                                Update Customer
                            </button>
                        <?php }else{ ?>
                            <button class="btn btn-success-custom btn-custom" name="add_customer">
                                Save Customer
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
        <div class="panel-heading" style="display: flex; align-items: center; justify-content: space-between;">
            <span>CUSTOMER LIST</span>
            
            <div>
                <a href="customer_report_print.php" class="btn btn-primary-custom btn-custom" style="height: 26px; padding: 0 10px; font-size: 10px;" target="_blank">
                    <i class="glyphicon glyphicon-print" style="margin-right: 4px;"></i> Print Report
                </a>
                <a href="customer_report_excel.php" class="btn btn-success-custom btn-custom" style="height: 26px; padding: 0 10px; font-size: 10px;">
                    <i class="glyphicon glyphicon-download" style="margin-right: 4px;"></i> Excel
                </a>
            </div>
        </div>

        <div class="panel-body">
            <div class="search-box" style="margin-bottom: 10px;">
                <i class="glyphicon glyphicon-search"></i>
                <input type="text" id="search" class="form-control" placeholder="Search customer...">
            </div>

            <div class="table-scrollable">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th width="40" class="text-center">#</th>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Email</th>
                            <th>Address</th>
                            <th>State</th>
                            <th class="text-center">State Code</th>
                            <th>GST No</th>
                            <th class="text-center">Center</th>
                            <th>Created Date</th>
                            <th width="80" class="text-center">Action</th>
                        </tr>
                    </thead>

                    <tbody id="custTable">
                        <?php if(!empty($customers)): ?>
                            <?php foreach($customers as $i=>$c): ?>
                            <tr>
                                <td class="text-center"><?php echo $i+1; ?></td>
                                <td><strong><?php echo $c['customer_name']; ?></strong></td>
                                <td><?php echo $c['contact_no']; ?></td>
                                <td><?php echo $c['email']; ?></td>
                                <td class="address-cell"><?php echo $c['address']; ?></td>
                                <td><span class="badge-state"><?php echo $c['state_name']; ?></span></td>
                                <td class="text-center"><?php echo $c['state_code']; ?></td>
                                <td><?php echo $c['gst_no']; ?></td>
                                <td class="text-center">
                                    <span class="badge-center"><?php echo $c['center_name']; ?></span>
                                </td>
                                <td><small><?php echo date('d-m-Y h:i A', strtotime($c['created_date'])); ?></small></td>
                                <td class="action-td">
                                <div class="action-cell">
                                    <a href="customer_master.php?edit=<?php echo $c['id']; ?>" class="btn btn-primary btn-xs equal-btn" title="Edit">
                                        <i class="glyphicon glyphicon-pencil"></i>
                                    </a>
                                    <button type="button" onclick="confirmDelete(<?php echo $c['id']; ?>)" class="btn btn-danger btn-xs equal-btn" title="Delete">
                                        <i class="glyphicon glyphicon-trash"></i>
                                    </button>
                                    
                                    <!-- ⬇️ YEH NAYA BUTTON ADD KAREIN ⬇️ -->
                                    <button type="button" 
                                            onclick="openPrintModal(<?php echo $c['id']; ?>, '<?php echo addslashes($c['customer_name']); ?>')" 
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
                                <td colspan="11" class="text-center" style="color: #94a3b8; padding: 15px;">No customers found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</div>

<!-- PRINT ADDRESS LABEL MODAL -->
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
              <input type="hidden" name="cust_id" id="modal_cust_id">
              
              <div class="form-group">
                  <label>Selected Customer (TO)</label>
                  <input type="text" id="modal_cust_name" class="form-control" readonly style="font-weight: bold;">
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
window.onload = function(){
    let input = document.getElementById("customer_name");
    if(input) input.focus();
};

function clearForm(){
    document.getElementById("customerForm").reset();
    let input = document.getElementById("customer_name");
    if(input) input.focus();
}

/* Auto fill state code */
document.getElementById("state_id").addEventListener("change", function(){
    let code = this.options[this.selectedIndex].getAttribute("data-code") || '';
    document.getElementById("state_code").value = code;
});

/* Search filter */
document.getElementById("search").addEventListener("keyup", function(){
    let value = this.value.toLowerCase();
    document.querySelectorAll("#custTable tr").forEach(function(row){
        row.style.display = row.textContent.toLowerCase().includes(value) ? "" : "none";
    });
});

/* GST Auto Uppercase & Formatting */
document.getElementById("gst_no").addEventListener("input", function () {
    this.value = this.value
        .toUpperCase()
        .replace(/[^A-Z0-9]/g, '')
        .substring(0, 15);
});

/* Delete confirmation modal */
function confirmDelete(id) {
    Swal.fire({
        title: 'Are You Sure?',
        text: "This customer will be deleted!",
        icon: 'warning',
        showCancelButton: true,
        confirmColor: '#ef4444',
        cancelColor: '#6b7280',
        confirmButtonText: 'Yes, Delete It!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "customer_master.php?del=" + id;
        }
    });
}

function openPrintModal(custId, custName) {
    document.getElementById('modal_cust_id').value = custId;
    document.getElementById('modal_cust_name').value = custName;
    $('#printLabelModal').modal('show');
}
</script>

<?php include_once('layouts/footer.php'); ?>
