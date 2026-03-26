<?php
require_once('includes/load.php');
//page_require_level(2);

$orgs = find_all('organization_master');
$banks = find_all('bank_master');

/* DELETE */
if(isset($_GET['delete'])){
    $id = (int)$_GET['delete'];
    $db->query("DELETE FROM bank_master WHERE id = $id");
    header("Location: bank_master.php");
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
    global $db;

    $org_id = (int)$_POST['organization_id'];
    $bank   = $_POST['bank_name'];
    $acc    = $_POST['account_name'];
    $acc_no = $_POST['account_number'];
    $ifsc   = $_POST['ifsc_code'];
    $branch = $_POST['branch'];
    $upi    = $_POST['upi_id'];

    if(isset($_POST['bank_id']) && $_POST['bank_id'] != ''){
        // UPDATE
        $id = (int)$_POST['bank_id'];
        $db->query("
        UPDATE bank_master SET
        organization_id='$org_id',
        bank_name='$bank',
        account_name='$acc',
        account_number='$acc_no',
        ifsc_code='$ifsc',
        branch='$branch',
        upi_id='$upi'
        WHERE id=$id
        ");
    } else {
        // INSERT
        $db->query("
        INSERT INTO bank_master
        (organization_id, bank_name, account_name, account_number, ifsc_code, branch, upi_id)
        VALUES
        ('$org_id','$bank','$acc','$acc_no','$ifsc','$branch','$upi')
        ");
    }

    header("Location: bank_master.php");
    exit();
}
?>

<?php include_once('layouts/header.php'); ?>

<div class="row">

<!-- LEFT SIDE FORM -->
<div class="col-md-4">
<div class="card shadow-sm">
<div class="card-header bg-white">
<h5 class="mb-0">
<?= isset($edit_data) ? 'EDIT BANK DETAILS' : 'ADD BANK DETAILS' ?>
</h5>
</div>

<div class="card-body">

<form method="post">

<input type="hidden" name="bank_id"
value="<?= $edit_data['id'] ?? '' ?>">

<div class="mb-3">
<label class="form-label">Organization</label>
<select name="organization_id" class="form-control" required>
<option value="">Select Organization</option>
<?php foreach($orgs as $o){ ?>
<option value="<?= $o['id'] ?>"
<?= (isset($edit_data) && $edit_data['organization_id']==$o['id']) ? 'selected' : '' ?>>
<?= $o['org_name'] ?>
</option>
<?php } ?>
</select>
</div>

<div class="mb-3">
<label class="form-label">Bank Name</label>
<input type="text" name="bank_name"
value="<?= $edit_data['bank_name'] ?? '' ?>"
class="form-control" autofocus required>
</div>

<div class="mb-3">
<label class="form-label">Account Holder Name</label>
<input type="text" name="account_name"
value="<?= $edit_data['account_name'] ?? '' ?>"
class="form-control" required>
</div>

<div class="mb-3">
<label class="form-label">Account Number</label>
<input type="text" name="account_number"
value="<?= $edit_data['account_number'] ?? '' ?>"
class="form-control" required>
</div>

<div class="mb-3">
<label class="form-label">IFSC</label>
<input type="text" name="ifsc_code"
value="<?= $edit_data['ifsc_code'] ?? '' ?>"
class="form-control">
</div>

<div class="mb-3">
<label class="form-label">Branch</label>
<input type="text" name="branch"
value="<?= $edit_data['branch'] ?? '' ?>"
class="form-control">
</div>

<div class="mb-4">
<label class="form-label">UPI ID</label>
<input type="text" name="upi_id"
value="<?= $edit_data['upi_id'] ?? '' ?>"
class="form-control">
</div>
<br>
<button type="submit" name="save_bank"
class="btn btn-success w-100 mt-2">
<?= isset($edit_data) ? 'Update Bank' : 'Add Bank' ?>
</button>

</form>

</div>
</div>
</div>


<!-- RIGHT SIDE LIST -->
<div class="col-md-8">
<div class="card shadow-sm">
<div class="card-header bg-white">
<h5 class="mb-0">BANK LIST</h5>
</div>

<div class="card-body">

<div class="mb-3">
<input type="text" id="bankSearch"
class="form-control"
placeholder="Search bank name, IFSC, account no...">
</div>

<table class="table table-bordered table-striped" id="bankTable">
<thead class="table-dark">
<tr>
<th>#</th>
<th>Organization</th>
<th>Bank</th>
<th>Account No</th>
<th>IFSC</th>
<th>UPI</th>
<th width="120">Action</th>
</tr>
</thead>

<tbody>
<?php 
$i=1;
foreach($banks as $b){
$org = find_by_id('organization_master',$b['organization_id']);
?>
<tr>
<td><?= $i++ ?></td>
<td><?= $org['org_name'] ?? '' ?></td>
<td><?= $b['bank_name'] ?></td>
<td><?= $b['account_number'] ?></td>
<td><?= $b['ifsc_code'] ?></td>
<td><?= $b['upi_id'] ?></td>
<td>
<div class="d-flex justify-content-between">
<a href="bank_master.php?edit=<?= $b['id'] ?>"
class="btn btn-sm btn-primary">Edit</a>

<a href="bank_master.php?delete=<?= $b['id'] ?>"
class="btn btn-sm btn-danger"
onclick="return confirm('Delete this bank?')">
Delete
</a>
</div>
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
document.getElementById("bankSearch").addEventListener("keyup", function() {
  let value = this.value.toLowerCase();
  let rows = document.querySelectorAll("#bankTable tbody tr");

  rows.forEach(function(row) {
    let text = row.innerText.toLowerCase();
    row.style.display = text.includes(value) ? "" : "none";
  });
});
</script>
<?php include_once('layouts/footer.php'); ?>
