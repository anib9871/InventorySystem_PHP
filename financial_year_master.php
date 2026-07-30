<?php
$page_title = 'Financial Year Master';
require_once('includes/load.php');

/* FETCH FY */
$years = find_by_sql("
SELECT *
FROM financial_year_master
ORDER BY fy_start_year DESC
");

/* AUTO CREATE CURRENT FY */
$month = date('m');

if($month >= 4){
    $start_year = date('Y');
}else{
    $start_year = date('Y') - 1;
}

$end_year = $start_year + 1;

$fy_name = substr($start_year,2,2) . "-" . substr($end_year,2,2);

/* CHECK EXISTING */
$check_current_fy = find_by_sql("
SELECT *
FROM financial_year_master
WHERE fy_name='{$fy_name}'
");

/* AUTO INSERT */
if(!$check_current_fy){
    $db->query("
    INSERT INTO financial_year_master
    (fy_name, fy_start_year, fy_end_year, is_active)
    VALUES
    ('{$fy_name}', '{$start_year}', '{$end_year}', 1)
    ");
}

/* ================= SET ACTIVE ================= */
if(isset($_GET['active'])){
    $id = (int)$_GET['active'];

    $db->query("
    UPDATE financial_year_master
    SET is_active = 0
    ");

    $db->query("
    UPDATE financial_year_master
    SET is_active = 1
    WHERE fy_id='{$id}'
    ");

    $session->msg('s', 'Financial Year Activated Successfully!');
    redirect('financial_year_master.php');
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

/* Scrollable Table Container */
.table-scrollable {
    max-height: 350px;
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
    width: 280px;
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

.badge-active {
    background: #e0f2fe;
    color: #0369a1;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
}

.badge-inactive {
    background: #f1f5f9;
    color: #64748b;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
}

.btn-custom {
    height: 26px;
    font-size: 10px;
    font-weight: 600;
    padding: 0 12px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: all 0.2s;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    text-decoration: none !important;
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
</style>

<div class="row">
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading">
                <i class="glyphicon glyphicon-calendar" style="color: #2b8cff; margin-right: 5px;"></i>
                <strong>FINANCIAL YEAR LIST</strong>
            </div>

            <div class="panel-body">

                <div class="search-box">
                    <i class="glyphicon glyphicon-search"></i>
                    <input type="text" id="fySearch" class="form-control" placeholder="Search financial year...">
                </div>

                <div class="table-scrollable">
                    <table class="table table-bordered table-striped" id="fyTable">
                        <thead>
                            <tr>
                                <th width="50" class="text-center">#</th>
                                <th>Financial Year</th>
                                <th class="text-center">Start Year</th>
                                <th class="text-center">End Year</th>
                                <th class="text-center" width="100">Status</th>
                                <th class="text-center" width="120">Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if(!empty($years)): ?>
                                <?php foreach($years as $i => $fy): ?>
                                    <tr>
                                        <td class="text-center"><?php echo $i + 1; ?></td>
                                        <td><strong><?php echo $fy['fy_name']; ?></strong></td>
                                        <td class="text-center"><?php echo $fy['fy_start_year']; ?></td>
                                        <td class="text-center"><?php echo $fy['fy_end_year']; ?></td>
                                        <td class="text-center">
                                            <?php if($fy['is_active']==1): ?>
                                                <span class="badge-active">Active</span>
                                            <?php else: ?>
                                                <span class="badge-inactive">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if($fy['is_active'] != 1): ?>
                                                <button type="button" onclick="confirmActivate(<?php echo $fy['fy_id']; ?>)" class="btn btn-primary-custom btn-custom">
                                                    Set Active
                                                </button>
                                            <?php else: ?>
                                                <span style="color: #10b981; font-weight: 600; font-size: 11px;">
                                                    <i class="glyphicon glyphicon-ok"></i> Current
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center" style="color: #94a3b8; padding: 15px;">No Financial Years found.</td>
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
document.getElementById("fySearch").addEventListener("keyup", function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll("#fyTable tbody tr");

    rows.forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(filter) ? "" : "none";
    });
});

// Activate confirmation dialog
function confirmActivate(id) {
    Swal.fire({
        title: 'Activate Financial Year?',
        text: "Is financial year ko system ka active year set kar diya jayega!",
        icon: 'question',
        showCancelButton: true,
        confirmColor: '#2b8cff',
        cancelColor: '#6b7280',
        confirmButtonText: 'Haan, Activate Karo!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "financial_year_master.php?active=" + id;
        }
    });
}
</script>

<?php include_once('layouts/footer.php'); ?>
