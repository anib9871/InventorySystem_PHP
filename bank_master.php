<?php
require_once('includes/load.php');
//page_require_level(2);

$page_title = 'Bank Master';

/* FETCH DATA */
$org_id = (int)$_SESSION['org_id'];

$current_org = find_by_sql("
SELECT *
FROM organization_master
WHERE id = '{$org_id}'
LIMIT 1
");

$current_org = $current_org ? $current_org[0] : [];
$banks = find_by_sql("SELECT * FROM bank_master ORDER BY id DESC") ?? [];

/* DELETE */
if(isset($_GET['delete'])){
    $id = (int)$_GET['delete'];
    if($db->query("DELETE FROM bank_master WHERE id = $id")){
        $session->msg("s", "Bank details deleted successfully!");
    } else {
        $session->msg("d", "Failed to delete bank details!");
    }
    redirect("bank_master.php", false);
}

/* EDIT FETCH */
$edit_data = null;
if(isset($_GET['edit'])){
    $id = (int)$_GET['edit'];
    $result = find_by_sql("SELECT * FROM bank_master WHERE id = $id");
    if($result){
        $edit_data = $result[0];
    }
}

/* SAVE / UPDATE */
if(isset($_POST['save_bank'])){

   $org_id = (int)$_SESSION['org_id'];
    $bank   = remove_junk($db->escape($_POST['bank_name']));
    $acc    = remove_junk($db->escape($_POST['account_name']));
    $acc_no = remove_junk($db->escape($_POST['account_number']));
    $ifsc   = remove_junk($db->escape($_POST['ifsc_code']));
    $branch = remove_junk($db->escape($_POST['branch']));
    $upi    = remove_junk($db->escape($_POST['upi_id']));

    if(empty($bank) || empty($acc_no)){
        $session->msg("d", "Bank Name and Account Number are required!");
        redirect("bank_master.php", false);
    }

    if(isset($_POST['bank_id']) && $_POST['bank_id'] != ''){

        $id = (int)$_POST['bank_id'];

        $sql = "UPDATE bank_master SET
                organization_id='$org_id',
                bank_name='$bank',
                account_name='$acc',
                account_number='$acc_no',
                ifsc_code='$ifsc',
                branch='$branch',
                upi_id='$upi'
                WHERE id=$id";

        if($db->query($sql)){
            $session->msg("s", "Bank details updated successfully!");
        } else {
            $session->msg("d", "Update failed!");
        }

    } else {

        $sql = "INSERT INTO bank_master
                (organization_id, bank_name, account_name, account_number, ifsc_code, branch, upi_id)
                VALUES
                ('$org_id','$bank','$acc','$acc_no','$ifsc','$branch','$upi')";

        if($db->query($sql)){
            $session->msg("s", "Bank details saved successfully!");
        } else {
            $session->msg("d", "Failed to save bank details!");
        }

    }

    redirect("bank_master.php", false);
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

.badge-ifsc {
    background: #e0f2fe;
    color: #0369a1;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
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

<!-- TOP FORM (FULL WIDTH) -->
<div class="col-md-12">
    <div class="panel panel-default">
        <div class="panel-heading title-header">
            <i class="glyphicon glyphicon-credit-card"></i>
            <?= isset($edit_data) ? 'EDIT BANK DETAILS' : 'BANK DETAILS MASTER' ?>
        </div>

        <div class="panel-body">
            <form method="post" id="bankForm">

                <input type="hidden" name="bank_id" value="<?= $edit_data['id'] ?? '' ?>">

                <!-- Row 1: Organization, Bank Name, Account Holder -->
                <div class="row">
                    <div class="col-md-4 form-group-compact">
                        <label>Organization</label>
                        <input type="hidden" name="organization_id" value="<?= $current_org['id'] ?? '' ?>">
                        <input type="text" class="form-control" value="<?= $current_org['org_name'] ?? '' ?>" readonly>
                    </div>

                    <div class="col-md-4 form-group-compact">
                        <label>Bank Name *</label>
                        <input type="text" id="bank_name" name="bank_name" value="<?= $edit_data['bank_name'] ?? '' ?>" class="form-control" placeholder="e.g. HDFC Bank" required autofocus>
                    </div>

                    <div class="col-md-4 form-group-compact">
                        <label>Account Holder Name</label>
                        <input type="text" name="account_name" value="<?= $edit_data['account_name'] ?? '' ?>" class="form-control" placeholder="Account holder name...">
                    </div>
                </div>

                <!-- Row 2: Account Number, IFSC, Branch, UPI -->
                <div class="row">
                    <div class="col-md-3 form-group-compact">
                        <label>Account Number *</label>
                        <input type="text" name="account_number" value="<?= $edit_data['account_number'] ?? '' ?>" class="form-control" placeholder="Enter account number..." required>
                    </div>

                    <div class="col-md-3 form-group-compact">
                        <label>IFSC Code</label>
                        <input type="text" id="ifsc_code" name="ifsc_code" value="<?= $edit_data['ifsc_code'] ?? '' ?>" class="form-control" placeholder="e.g. HDFC0001234">
                    </div>

                    <div class="col-md-3 form-group-compact">
                        <label>Branch Name</label>
                        <input type="text" name="branch" value="<?= $edit_data['branch'] ?? '' ?>" class="form-control" placeholder="Branch location...">
                    </div>

                    <div class="col-md-3 form-group-compact">
                        <label>UPI ID</label>
                        <input type="text" name="upi_id" value="<?= $edit_data['upi_id'] ?? '' ?>" class="form-control" placeholder="e.g. username@upi">
                    </div>
                </div>

                <!-- Row 3: Action Buttons -->
                <div class="row">
                    <div class="col-md-12 text-right" style="padding-top: 5px;">
                        <?php if(!isset($edit_data)){ ?>
                            <button type="button" class="btn btn-clear btn-custom" onclick="clearForm()">
                                Clear
                            </button>
                        <?php } ?>

                        <?php if(isset($edit_data)){ ?>
                            <a href="bank_master.php" class="btn btn-clear btn-custom">
                                Cancel
                            </a>
                            <button type="submit" name="save_bank" class="btn btn-primary-custom btn-custom">
                                Update Bank
                            </button>
                        <?php } else { ?>
                            <button type="submit" name="save_bank" class="btn btn-success-custom btn-custom">
                                Add Bank
                            </button>
                        <?php } ?>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>

<!-- BOTTOM LIST (FULL WIDTH) -->
<div class="col-md-12">
    <div class="panel panel-default">
        <div class="panel-heading">BANK LIST</div>

        <div class="panel-body">
            <div class="search-box">
                <i class="glyphicon glyphicon-search"></i>
                <input type="text" id="bankSearch" class="form-control" placeholder="Search bank...">
            </div>

            <div class="table-scrollable">
                <table class="table table-bordered table-striped" id="bankTable">
                    <thead>
                        <tr>
                            <th width="40" class="text-center">#</th>
                            <th>Organization</th>
                            <th>Bank Name</th>
                            <th>Account Holder</th>
                            <th>Account Number</th>
                            <th class="text-center">IFSC</th>
                            <th>Branch</th>
                            <th>UPI ID</th>
                            <th width="80" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $i=1;
                        if(!empty($banks)){
                            foreach($banks as $b){
                                $org = find_by_id('organization_master',$b['organization_id']) ?? [];
                        ?>
                        <tr>
                            <td class="text-center"><?= $i++ ?></td>
                            <td><strong><?= $org['org_name'] ?? '' ?></strong></td>
                            <td><?= $b['bank_name'] ?></td>
                            <td><?= $b['account_name'] ?></td>
                            <td><?= $b['account_number'] ?></td>
                            <td class="text-center"><span class="badge-ifsc"><?= $b['ifsc_code'] ?: 'N/A' ?></span></td>
                            <td><?= $b['branch'] ?: 'N/A' ?></td>
                            <td><?= $b['upi_id'] ?: 'N/A' ?></td>
                            <td class="action-td">
                                <div class="action-cell">
                                    <a href="bank_master.php?edit=<?= $b['id'] ?>" class="btn btn-primary btn-xs equal-btn" title="Edit">
                                        <i class="glyphicon glyphicon-pencil"></i>
                                    </a>
                                    <button type="button" onclick="confirmDelete(<?= $b['id'] ?>)" class="btn btn-danger btn-xs equal-btn" title="Delete">
                                        <i class="glyphicon glyphicon-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php 
                            }
                        } else {
                        ?>
                        <tr>
                            <td colspan="9" class="text-center" style="color: #94a3b8; padding: 15px;">No bank details found.</td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

</div>

<script>
window.onload = function () {
    let input = document.getElementById("bank_name");
    if(input) input.focus();
};

function clearForm(){
    document.getElementById("bankForm").reset();
    let input = document.getElementById("bank_name");
    if(input) input.focus();
}

/* Search Filter */
document.getElementById("bankSearch").addEventListener("keyup", function(){
    let value = this.value.toLowerCase();
    document.querySelectorAll("#bankTable tbody tr").forEach(function(row){
        row.style.display = row.innerText.toLowerCase().includes(value) ? "" : "none";
    });
});

/* IFSC Auto Uppercase */
let ifscInput = document.getElementById("ifsc_code");
if(ifscInput){
    ifscInput.addEventListener("input", function () {
        this.value = this.value.toUpperCase();
    });
}

/* Delete confirmation modal */
function confirmDelete(id) {
    Swal.fire({
        title: 'Kya aap sure hain?',
        text: "Is bank detail ko delete kar diya jayega!",
        icon: 'warning',
        showCancelButton: true,
        confirmColor: '#ef4444',
        cancelColor: '#6b7280',
        confirmButtonText: 'Haan, Delete Karo!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "bank_master.php?delete=" + id;
        }
    });
}
</script>

<?php include_once('layouts/footer.php'); ?>
