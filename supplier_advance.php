<?php
$page_title = 'Supplier Advance';
require_once('includes/load.php');

$suppliers = find_all('supplier_master');

$payment_modes = find_by_sql("
SELECT *
FROM payment_mode_master
WHERE is_active = 1
ORDER BY mode_name ASC
");

/* ================= SAVE ADVANCE ================= */
if(isset($_POST['save_advance'])){

  $supplier_id = (int)$_POST['supplier_id'];
  $amount = (float)$_POST['amount'];
  $mode = $db->escape($_POST['payment_mode']);
  $ref = $db->escape($_POST['reference_no']);
  $payment_date = $_POST['payment_date'];

  $formats = ['d/M/Y','d-m-Y','Y-m-d'];

  foreach($formats as $format){
      $dt = DateTime::createFromFormat($format,$payment_date);
      if($dt){
          $payment_date = $dt->format('Y-m-d');
          break;
      }
  }

  $payment_date = $db->escape($payment_date);

  if($supplier_id <= 0 || $amount <= 0){
    $_SESSION['swal_error'] = 'Please enter valid supplier and amount!';
    redirect('supplier_advance.php', false);
  }

  $db->query("
  INSERT INTO supplier_ledger
  (
  supplier_id,
  bill_no,
  bill_date,
  bill_amount,
  paid_amount,
  balance_amount,
  payment_status,
  entry_type,
  created_at
  )
  VALUES
  (
  '$supplier_id',
  'ADVANCE',
  '$payment_date',
  0,
  '$amount',
  '-$amount',
  1,
  'ADVANCE',
  NOW()
  )
  ");

  $ledger_id = $db->insert_id();

  $db->query("
  INSERT INTO supplier_payment
  (
  ledger_id,
  supplier_id,
  payment_date,
  payment_amount,
  payment_mode,
  reference_no,
  created_at
  )
  VALUES
  (
  '$ledger_id',
  '$supplier_id',
  '$payment_date',
  '$amount',
  '$mode',
  '$ref',
  NOW()
  )
  ");

  $session->msg('s', 'Supplier Advance Added Successfully!');
  redirect('supplier_advance.php', false);
}

/* ================= UPDATE ADVANCE ================= */
if(isset($_POST['update_advance'])){

  $ledger_id = (int)$_POST['ledger_id'];
  $supplier_id = (int)$_POST['supplier_id'];
  $amount = (float)$_POST['amount'];
  $mode = $db->escape($_POST['payment_mode']);
  $ref = $db->escape($_POST['reference_no']);
  $payment_date = $_POST['payment_date'];

  $formats = ['d/M/Y','d-m-Y','Y-m-d'];

  foreach($formats as $format){
      $dt = DateTime::createFromFormat($format,$payment_date);
      if($dt){
          $payment_date = $dt->format('Y-m-d');
          break;
      }
  }

  $payment_date = $db->escape($payment_date);

  $db->query("
  UPDATE supplier_ledger
  SET
  supplier_id='{$supplier_id}',
  bill_date='{$payment_date}',
  paid_amount='{$amount}',
  balance_amount='-{$amount}'
  WHERE ledger_id='{$ledger_id}'
  ");

  $db->query("
  UPDATE supplier_payment
  SET
  supplier_id='{$supplier_id}',
  payment_date='{$payment_date}',
  payment_amount='{$amount}',
  payment_mode='{$mode}',
  reference_no='{$ref}'
  WHERE ledger_id='{$ledger_id}'
  ");

  $session->msg('s', 'Supplier Advance Updated Successfully!');
  redirect('supplier_advance.php', false);
}

/* ================= EDIT FETCH ================= */
$edit_data = null;
if(isset($_GET['edit'])){
  $ledger_id = (int)$_GET['edit'];
  $edit = find_by_sql("
  SELECT
  sl.*,
  sp.payment_mode,
  sp.reference_no
  FROM supplier_ledger sl
  LEFT JOIN supplier_payment sp ON sp.ledger_id = sl.ledger_id
  WHERE sl.ledger_id = '{$ledger_id}'
  LIMIT 1
  ");

  if($edit){
    $edit_data = $edit[0];
  }
}

/* ================= ADVANCE LIST ================= */
$advances = find_by_sql("
SELECT
sl.*,
sm.supplier_name,
sp.payment_mode,
sp.reference_no
FROM supplier_ledger sl
LEFT JOIN supplier_master sm ON sm.id = sl.supplier_id
LEFT JOIN supplier_payment sp ON sp.ledger_id = sl.ledger_id
WHERE
    sl.entry_type='ADVANCE'
    AND sl.balance_amount < 0
ORDER BY sl.ledger_id DESC
");

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
    background-color: #ffffff;
    color: #334155;
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

.badge-amount {
    color: #059669;
    font-weight: 700;
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
    width: 60px;
    text-align: center;
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
                <i class="glyphicon glyphicon-piggy-bank"></i>
                <?= $edit_data ? 'EDIT SUPPLIER ADVANCE' : 'SUPPLIER ADVANCE' ?>
            </div>
            <div class="panel-body">
                <form method="post" id="advanceForm">
                    <input type="hidden" name="ledger_id" value="<?= $edit_data['ledger_id'] ?? ''; ?>">

                    <!-- Row 1: Supplier, Payment Date, Advance Amount -->
                    <div class="row">
                        <div class="col-md-4 form-group-compact">
                            <label>Supplier *</label>
                            <select name="supplier_id" class="form-control" required autofocus>
                                <option value="">Select Supplier</option>
                                <?php foreach($suppliers as $s): ?>
                                    <option value="<?= $s['id']; ?>" <?= ($edit_data && $edit_data['supplier_id']==$s['id']) ? 'selected' : ''; ?>>
                                        <?= $s['supplier_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4 form-group-compact">
                            <label>Payment Date *</label>
                            <input type="text" name="payment_date" id="payment_date" class="form-control"
                                   value="<?= isset($edit_data['bill_date']) ? date('d/M/Y', strtotime($edit_data['bill_date'])) : date('d/M/Y'); ?>"
                                   autocomplete="off" required>
                        </div>

                        <div class="col-md-4 form-group-compact">
                            <label>Advance Amount (₹) *</label>
                            <input type="number" step="0.01" min="0.01" name="amount" class="form-control"
                                   placeholder="0.00" value="<?= $edit_data['paid_amount'] ?? ''; ?>" required>
                        </div>
                    </div>

                    <!-- Row 2: Payment Mode, Reference No, Remarks -->
                    <div class="row">
                        <div class="col-md-4 form-group-compact">
                            <label>Payment Mode *</label>
                            <select name="payment_mode" class="form-control" required>
                                <option value="">Select Mode</option>
                                <?php foreach($payment_modes as $pm): ?>
                                    <option value="<?= $pm['mode_name']; ?>" <?= ($edit_data && $edit_data['payment_mode']==$pm['mode_name']) ? 'selected' : ''; ?>>
                                        <?= strtoupper($pm['mode_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4 form-group-compact">
                            <label>Reference No</label>
                            <input type="text" name="reference_no" class="form-control"
                                   placeholder="Transaction/Cheque/Ref ID..." value="<?= $edit_data['reference_no'] ?? ''; ?>">
                        </div>

                        <div class="col-md-4 form-group-compact">
                            <label>Remarks</label>
                            <textarea name="remarks" class="form-control" rows="1" placeholder="Remarks..."><?= $edit_data['remarks'] ?? ''; ?></textarea>
                        </div>
                    </div>

                    <!-- Row 3: Action Buttons -->
                    <div class="row">
                        <div class="col-md-12 text-right" style="padding-top: 5px;">
                            <?php if(!$edit_data){ ?>
                                <button type="button" class="btn btn-clear btn-custom" onclick="clearForm()">
                                    Clear
                                </button>
                            <?php } ?>

                            <?php if($edit_data){ ?>
                                <a href="supplier_advance.php" class="btn btn-clear btn-custom">
                                    Cancel
                                </a>
                                <button type="submit" name="update_advance" class="btn btn-primary-custom btn-custom">
                                    Update Advance
                                </button>
                            <?php } else { ?>
                                <button type="submit" name="save_advance" class="btn btn-success-custom btn-custom">
                                    Save Advance
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
            <div class="panel-heading">SUPPLIER ADVANCE HISTORY</div>

            <div class="panel-body">
                <div class="search-box">
                    <i class="glyphicon glyphicon-search"></i>
                    <input type="text" id="searchAdvance" class="form-control" placeholder="Search supplier...">
                </div>

                <div class="table-scrollable">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th width="40" class="text-center">#</th>
                                <th>Supplier Name</th>
                                <th class="text-right">Advance Amount</th>
                                <th class="text-center">Payment Mode</th>
                                <th>Reference No</th>
                                <th class="text-center">Date</th>
                                <th width="60" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="advanceTable">
                            <?php if(!empty($advances)): ?>
                                <?php foreach($advances as $i => $a): ?>
                                <tr>
                                    <td class="text-center"><?= $i+1; ?></td>
                                    <td><strong><?= $a['supplier_name']; ?></strong></td>
                                    <td class="text-right">
                                        <span class="badge-amount">₹ <?= number_format(abs($a['balance_amount']),2); ?></span>
                                    </td>
                                    <td class="text-center"><?= strtoupper($a['payment_mode']); ?></td>
                                    <td><?= $a['reference_no'] ?: '<span style="color:#94a3b8;">N/A</span>'; ?></td>
                                    <td class="text-center"><?= date('d/M/Y', strtotime($a['bill_date'])); ?></td>
                                    <td class="action-td">
                                        <div class="action-cell">
                                            <a href="supplier_advance.php?edit=<?= $a['ledger_id']; ?>" class="btn btn-primary btn-xs equal-btn" title="Edit">
                                                <i class="glyphicon glyphicon-pencil"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center" style="color: #94a3b8; padding: 15px;">No advance payment records found.</td>
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
function clearForm(){
    document.getElementById("advanceForm").reset();
}

/* Search filter */
document.getElementById("searchAdvance").addEventListener("keyup", function(){
    let value = this.value.toLowerCase();
    document.querySelectorAll("#advanceTable tbody tr").forEach(function(row){
        row.style.display = row.innerText.toLowerCase().includes(value) ? "" : "none";
    });
});

/* Flatpickr Date Init */
document.addEventListener("DOMContentLoaded", function () {
    flatpickr("#payment_date", {
        dateFormat: "d/M/Y",
        allowInput: false,
        disableMobile: true
    });
});
</script>

<?php include_once('layouts/footer.php'); ?>
