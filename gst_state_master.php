<?php
$page_title = 'GST State Code Master';
require_once('includes/load.php');
//page_require_level(2);

if(isset($gst_enabled) && $gst_enabled == "No"){
   $session->msg("d","GST disabled from configuration");
   redirect('home.php');
}

/* =========================================================
   SMART AUTO-FILL: SIRF MISSING STATES KO ADD KAREGA
========================================================= */
$default_states = [
    '01' => 'Jammu & Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab', 
    '04' => 'Chandigarh', '05' => 'Uttarakhand', '06' => 'Haryana', 
    '07' => 'Delhi', '08' => 'Rajasthan', '09' => 'Uttar Pradesh', 
    '10' => 'Bihar', '11' => 'Sikkim', '12' => 'Arunachal Pradesh', 
    '13' => 'Nagaland', '14' => 'Manipur', '15' => 'Mizoram', 
    '16' => 'Tripura', '17' => 'Meghalaya', '18' => 'Assam', 
    '19' => 'West Bengal', '20' => 'Jharkhand', '21' => 'Odisha', 
    '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh', '24' => 'Gujarat', 
    '26' => 'Dadra & Nagar Haveli & Daman & Diu', '27' => 'Maharashtra', 
    '29' => 'Karnataka', '30' => 'Goa', '31' => 'Lakshadweep', 
    '32' => 'Kerala', '33' => 'Tamil Nadu', '34' => 'Puducherry', 
    '35' => 'Andaman & Nicobar Islands', '36' => 'Telangana', 
    '37' => 'Andhra Pradesh', '38' => 'Ladakh'
];

// 1. Check karo ki database me abhi kaun-kaun se state code hain
$existing_states = find_by_sql("SELECT state_code FROM gst_state_master");
$existing_codes = [];
if ($existing_states) {
    foreach ($existing_states as $es) {
        $existing_codes[] = $es['state_code'];
    }
}

// 2. Jo state code database me NAHI hain, unhe ek list me daalo
$missing_inserts = [];
foreach ($default_states as $code => $name) {
    if (!in_array($code, $existing_codes)) {
        $name_esc = $db->escape($name);
        $missing_inserts[] = "('$name_esc', '$code')";
    }
}

// 3. Agar koi missing hai ya naya database hai, toh unhe insert kar do
if (!empty($missing_inserts)) {
    $insert_query = "INSERT INTO gst_state_master (state_name, state_code) VALUES " . implode(", ", $missing_inserts);
    $db->query($insert_query);
}
/* ========================================================= */

/* FETCH */
$states = find_by_sql("SELECT * FROM gst_state_master ORDER BY state_name ASC");

/* ADD */
if(isset($_POST['add_state'])){

  $name = remove_junk($db->escape($_POST['state_name']));
  $code = remove_junk($db->escape($_POST['state_code']));

  if($name=='' || $code==''){
      $session->msg("d","Both fields are required!");
      redirect('gst_state_master.php',false);
  }

  $chk = find_by_sql("SELECT id FROM gst_state_master WHERE state_code='$code'");
  if(!empty($chk)){
      $session->msg("d","State code already exists!");
      redirect('gst_state_master.php',false);
  }

  $sql = "INSERT INTO gst_state_master(state_name,state_code)
          VALUES('$name','$code')";

  if($db->query($sql)){
      $session->msg("s","State code added successfully!");
  } else {
      $session->msg("d","Failed to add state code!");
  }
  redirect('gst_state_master.php',false);
}

/* DELETE */
if(isset($_GET['del'])){
  $id = (int)$_GET['del'];
  if($db->query("DELETE FROM gst_state_master WHERE id='$id'")){
      $session->msg("s","State code deleted successfully!");
  } else {
      $session->msg("d","Failed to delete state code!");
  }
  redirect('gst_state_master.php',false);
}

/* EDIT FETCH */
$edit=false;
if(isset($_GET['edit'])){
   $eid = (int)$_GET['edit'];
   $edit = find_by_id("gst_state_master",$eid);
}

/* UPDATE */
if(isset($_POST['update_state'])){

  $id = (int)$_POST['id'];
  $name = remove_junk($db->escape($_POST['state_name']));
  $code = remove_junk($db->escape($_POST['state_code']));

  if($name=='' || $code==''){
      $session->msg("d","Both fields are required!");
      redirect('gst_state_master.php',false);
  }

  $sql="UPDATE gst_state_master SET 
         state_name='$name',
         state_code='$code'
         WHERE id='$id'";

  if($db->query($sql)){
     $session->msg("s","Updated successfully!");
  } else {
     $session->msg("d","Update failed!");
  }

  redirect('gst_state_master.php',false);
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
    border-bottom: 2px solid #2b8cff !important;
    background: #ffffff !important;
    color: #1d3557;
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.panel-body {
    padding: 15px;
}

label {
    font-size: 11px;
    margin-bottom: 4px;
    font-weight: 600;
    color: #4a5568;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.form-group-compact {
    margin-bottom: 10px;
}

.form-control {
    height: 32px;
    font-size: 12px;
    padding: 4px 10px;
    border-radius: 5px;
    border: 1px solid #cbd5e1;
    box-shadow: none;
    transition: all 0.2s ease-in-out;
}

.form-control:focus {
    border-color: #2b8cff;
    box-shadow: 0 0 0 2px rgba(43, 140, 255, 0.15);
}

/* Buttons Flex Alignment */
.btn-group-flex {
    display: flex !important;
    align-items: center !important;
    justify-content: flex-end !important;
    gap: 8px !important;
    margin-top: 15px;
}

.btn-custom {
    height: 32px;
    font-size: 11px;
    font-weight: 600;
    padding: 0 14px;
    border-radius: 5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: all 0.2s;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    text-decoration: none !important;
    white-space: nowrap !important;
    line-height: 1 !important;
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
    padding: 8px 12px;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: #18233b;
}

.table tbody td {
    padding: 8px 12px;
    vertical-align: middle !important;
    border-color: #f1f5f9;
}

.table tbody tr:hover {
    background: #f8fafc;
}

.search-box {
    position: relative;
    width: 100%;
    margin-bottom: 12px;
}

.search-box i {
    position: absolute;
    left: 10px;
    top: 9px;
    color: #94a3b8;
    font-size: 12px;
}

.search-box input {
    padding-left: 30px;
    height: 32px !important;
}

.badge-code {
    background: #e0f2fe;
    color: #0369a1;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

/* Action Cell Styling */
.action-td {
    width: 80px;
    text-align: center;
    white-space: nowrap !important;
}

.action-cell {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px !important;
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

<!-- ADD / EDIT FORM -->
<div class="col-md-4">
    <div class="panel panel-default">
        <div class="panel-heading">
            <i class="glyphicon glyphicon-globe" style="color: #2b8cff; margin-right: 5px;"></i>
            <strong><?php echo $edit ? 'EDIT STATE CODE' : 'ADD STATE CODE'; ?></strong>
        </div>

        <div class="panel-body">
            <form method="post" id="stateForm">

                <?php if($edit){ ?>
                    <input type="hidden" name="id" value="<?php echo $edit['id']; ?>">
                <?php } ?>

                <div class="form-group form-group-compact">
                    <label>State Name</label>
                    <input type="text" id="state_name" name="state_name" class="form-control"
                           value="<?php echo $edit ? $edit['state_name'] : ''; ?>"
                           placeholder="Enter State Name..." required autofocus>
                </div>

                <div class="form-group form-group-compact">
                    <label>State Code</label>
                    <input type="text" maxlength="2" name="state_code" class="form-control"
                           value="<?php echo $edit ? $edit['state_code'] : ''; ?>"
                           placeholder="e.g. 09" required>
                </div>

                <div class="btn-group-flex">
                    <?php if($edit){ ?>
                        <a href="gst_state_master.php" class="btn btn-clear btn-custom">
                            Cancel
                        </a>
                        <button name="update_state" class="btn btn-primary-custom btn-custom">
                            Update State
                        </button>
                    <?php } else { ?>
                        <button type="button" class="btn btn-clear btn-custom" onclick="clearForm()">
                            Clear
                        </button>
                        <button name="add_state" class="btn btn-success-custom btn-custom">
                            Save State
                        </button>
                    <?php } ?>
                </div>

            </form>
        </div>
    </div>
</div>

<!-- LIST -->
<div class="col-md-8">
    <div class="panel panel-default">
        <div class="panel-heading">
            <strong>GST STATE CODE LIST</strong>
        </div>

        <div class="panel-body">

            <div class="search-box">
                <i class="glyphicon glyphicon-search"></i>
                <input type="text" id="searchBox" class="form-control" placeholder="Search state or code...">
            </div>

            <div class="table-scrollable">
                <table class="table table-bordered table-striped" id="stateTable">
                    <thead>
                        <tr>
                            <th width="50" class="text-center">#</th>
                            <th>State</th>
                            <th class="text-center" width="100">Code</th>
                            <th class="text-center" width="80">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($states)): ?>
                            <?php foreach($states as $i=>$s): ?>
                            <tr>
                                <td class="text-center"><?php echo $i+1; ?></td>
                                <td><strong><?php echo $s['state_name']; ?></strong></td>
                                <td class="text-center"><span class="badge-code"><?php echo $s['state_code']; ?></span></td>
                                <td class="action-td">
                                    <div class="action-cell">
                                        <a href="gst_state_master.php?edit=<?php echo $s['id']; ?>" class="btn btn-primary btn-xs equal-btn" title="Edit">
                                            <i class="glyphicon glyphicon-pencil"></i>
                                        </a>
                                        <button type="button" onclick="confirmDelete(<?php echo $s['id']; ?>)" class="btn btn-danger btn-xs equal-btn" title="Delete">
                                            <i class="glyphicon glyphicon-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center" style="color: #94a3b8; padding: 15px;">No state codes found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

</div>

<script>
window.onload = function () {
    let input = document.getElementById("state_name");
    if(input) input.focus();
};

function clearForm(){
    document.getElementById("stateForm").reset();
    document.getElementById("state_name").focus();
}

/* Search filter */
document.getElementById("searchBox").addEventListener("keyup", function() {
    let value = this.value.toLowerCase();
    document.querySelectorAll("#stateTable tbody tr").forEach(function(row) {
        row.style.display = row.innerText.toLowerCase().includes(value) ? "" : "none";
    });
});

/* Delete confirmation */
function confirmDelete(id) {
    Swal.fire({
        title: 'Are You Sure?',
        text: "This state code will be deleted!",
        icon: 'warning',
        showCancelButton: true,
        confirmColor: '#ef4444',
        cancelColor: '#6b7280',
        confirmButtonText: 'Yes, Delete It!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "gst_state_master.php?del=" + id;
        }
    });
}
</script>

<?php include_once('layouts/footer.php'); ?>
