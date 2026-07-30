<?php
$page_title = 'Sequence Master';
require_once('includes/load.php');
//page_require_level(2);

$sequences = find_by_sql("
SELECT s.*,
       f.fy_name
FROM sequence_master s
LEFT JOIN financial_year_master f
ON f.fy_id = s.fy_id
ORDER BY s.sequence_id DESC
");

/* ADD */
if(isset($_POST['add_sequence'])){

  $category = remove_junk($_POST['sequence_category']);
  $value    = (int)$_POST['last_no'];
  
  $current_fy = find_by_sql("
  SELECT fy_id, fy_name
  FROM financial_year_master
  WHERE is_active = 1
  LIMIT 1
  ");

  if(!$current_fy){
      $session->msg('d',"No Active Financial Year Found!");
      redirect('master_sequence.php',false);
  }

  $fy_id = $current_fy[0]['fy_id'];
  $financial_year = $current_fy[0]['fy_name'];

  $check = find_by_sql("SELECT * FROM sequence_master 
  WHERE sequence_category = '{$category}'
  AND fy_id = '{$fy_id}'");

  if($check){
      $session->msg('d',"Sequence already exists for this category in active financial year!");
      redirect('master_sequence.php',false);
  }

  $sql = "INSERT INTO sequence_master (sequence_category,fy_id,last_no)
          VALUES ('{$category}', '{$fy_id}', '{$value}')";

  if($db->query($sql)){
      $session->msg('s',"Sequence added successfully!");
  }else{
      $session->msg('d',"Failed to add sequence!");
  }
  redirect('master_sequence.php',false);
}

/* DELETE */
if(isset($_GET['delete'])){

    $id = (int)$_GET['delete'];

    $sql = "DELETE FROM sequence_master WHERE sequence_id = '{$id}'";

    if($db->query($sql)){
        $session->msg('s',"Sequence deleted successfully!");
    }else{
        $session->msg('d',"Failed to delete sequence!");
    }

    redirect('master_sequence.php',false);
    exit;
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

.form-control[readonly] {
    background-color: #f1f5f9;
    color: #64748b;
}

/* Form Buttons Container Alignment */
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

.badge-format {
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

<!-- LEFT SIDE FORM -->
<div class="col-md-4">
    <div class="panel panel-default">
        <div class="panel-heading">
            <i class="glyphicon glyphicon-list-alt" style="color: #2b8cff; margin-right: 5px;"></i>
            <strong>ADD SEQUENCE</strong>
        </div>

        <div class="panel-body">
            <form method="post" id="seqForm">

                <div class="form-group form-group-compact">
                    <label>Sequence Category</label>
                    <select name="sequence_category" class="form-control" required autofocus>
                        <option value="">Select Category</option>
                        <option value="invoice">Invoice</option>
                        <option value="quotation">Quotation</option>
                    </select>
                </div>

                <div class="form-group form-group-compact">
                    <label>Last No</label>
                    <input type="number" name="last_no" class="form-control" placeholder="Enter last number..." required>
                </div>

                <div class="form-group form-group-compact">
                    <label>Financial Year</label>
                    <input type="text" class="form-control" value="<?= isset($_SESSION['financial_year']) ? $_SESSION['financial_year'] : ''; ?>" readonly>
                </div>

                <div class="btn-group-flex">
                    <button type="button" class="btn btn-clear btn-custom" onclick="document.getElementById('seqForm').reset();">
                        Clear
                    </button>
                    <button type="submit" name="add_sequence" class="btn btn-success-custom btn-custom">
                        Save Sequence
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<!-- RIGHT SIDE LIST -->
<div class="col-md-8">
    <div class="panel panel-default">
        <div class="panel-heading">
            <strong>SEQUENCE LIST</strong>
        </div>

        <div class="panel-body">

            <div class="search-box">
                <i class="glyphicon glyphicon-search"></i>
                <input type="text" id="seqSearch" class="form-control" placeholder="Search sequence...">
            </div>

            <div class="table-scrollable">
                <table class="table table-bordered table-striped" id="seqTable">
                    <thead>
                        <tr>
                            <th width="40" class="text-center">#</th>
                            <th>Sequence Category</th>
                            <th class="text-center" width="80">Last No</th>
                            <th class="text-center">Financial Year</th>
                            <th class="text-center">Bill Format</th>
                            <th class="text-center" width="80">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if(!empty($sequences)): ?>
                            <?php foreach($sequences as $i => $seq): ?>
                                <tr>
                                    <td class="text-center"><?= $i + 1; ?></td>
                                    <td><strong><?= ucfirst($seq['sequence_category']); ?></strong></td>
                                    <td class="text-center"><?= $seq['last_no']; ?></td>
                                    <td class="text-center"><?= $seq['fy_name']; ?></td>
                                    <td class="text-center">
                                        <span class="badge-format"><?= $seq['fy_name']; ?>/<?= $seq['last_no']; ?></span>
                                    </td>
                                    <td class="action-td">
                                        <div class="action-cell">
                                            <a href="edit_sequence.php?id=<?= $seq['sequence_id']; ?>" class="btn btn-primary btn-xs equal-btn" title="Edit">
                                                <i class="glyphicon glyphicon-pencil"></i>
                                            </a>
                                            <button type="button" onclick="confirmDelete(<?= $seq['sequence_id']; ?>)" class="btn btn-danger btn-xs equal-btn" title="Delete">
                                                <i class="glyphicon glyphicon-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center" style="color: #94a3b8; padding: 15px;">No sequences found.</td>
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
// Filter Search
document.getElementById("seqSearch").addEventListener("keyup", function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll("#seqTable tbody tr");

    rows.forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(filter) ? "" : "none";
    });
});

// Delete Confirmation
function confirmDelete(id) {
    Swal.fire({
        title: 'Kya aap sure hain?',
        text: "Is sequence ko delete kar diya jayega!",
        icon: 'warning',
        showCancelButton: true,
        confirmColor: '#ef4444',
        cancelColor: '#6b7280',
        confirmButtonText: 'Haan, Delete Karo!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "master_sequence.php?delete=" + id;
        }
    });
}
</script>

<?php include_once('layouts/footer.php'); ?>
