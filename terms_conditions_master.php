<?php
$page_title = 'Terms & Conditions Master';
require_once('includes/load.php');

/* =========================
   SAVE / UPDATE
========================= */
if(isset($_POST['save_terms']))
{
    $tc_id         = (int)$_POST['tc_id'];
    $template_name = remove_junk($db->escape($_POST['template_name']));
    $template      = remove_junk($db->escape($_POST['template']));
    $updated_by    = isset($user['name']) ? $user['name'] : 'Admin';

    // INSERT
    if($tc_id == 0)
    {
        $query = "INSERT INTO terms_conditions_master (template_name, template, updated_by)
                  VALUES ('{$template_name}', '{$template}', '{$updated_by}')";

        if($db->query($query))
        {
            $session->msg('s', 'Template Added Successfully!');
            redirect('terms_conditions_master.php',false);
        } else {
            $session->msg('d', 'Failed to Add Template!');
            redirect('terms_conditions_master.php',false);
        }
    }
    // UPDATE
    else
    {
        $query = "UPDATE terms_conditions_master SET
                    template_name = '{$template_name}',
                    template      = '{$template}',
                    updated_by    = '{$updated_by}',
                    updated_at    = NOW()
                  WHERE tc_id = '{$tc_id}'";

        if($db->query($query))
        {
            $session->msg('s', 'Template Updated Successfully!');
            redirect('terms_conditions_master.php',false);
        } else {
            $session->msg('d', 'Failed to Update Template!');
            redirect('terms_conditions_master.php',false);
        }
    }
}

/* =========================
   DELETE
========================= */
if(isset($_GET['delete']))
{
    $id = (int)$_GET['delete'];
    $query = "DELETE FROM terms_conditions_master WHERE tc_id = '{$id}'";

    if($db->query($query))
    {
        $session->msg('s', 'Template Deleted Successfully!');
        redirect('terms_conditions_master.php',false);
    } else {
        $session->msg('d', 'Failed to Delete Template!');
        redirect('terms_conditions_master.php',false);
    }
}

/* =========================
   EDIT FETCH
========================= */
$edit = null;
if(isset($_GET['edit']))
{
    $id = (int)$_GET['edit'];
    $edit_query = find_by_sql("SELECT * FROM terms_conditions_master WHERE tc_id = '{$id}' LIMIT 1");
    if($edit_query){
        $edit = $edit_query[0];
    }
}

/* =========================
   FETCH ALL
========================= */
$templates = find_by_sql("SELECT * FROM terms_conditions_master ORDER BY tc_id DESC");

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
    font-size: 12px;
    padding: 6px 10px;
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

/* Live Preview Styling */
.preview-box {
    min-height: 180px;
    max-height: 220px;
    overflow-y: auto;
    white-space: pre-line;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 12px;
    border-radius: 6px;
    font-size: 12px;
    color: #334155;
    line-height: 1.5;
}

/* Scrollable Table Container */
.table-scrollable {
    max-height: 280px;
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

    <!-- LEFT SIDE : FORM -->
    <div class="col-md-5">
        <div class="panel panel-default">
            <div class="panel-heading">
                <i class="glyphicon glyphicon-file" style="color: #2b8cff; margin-right: 5px;"></i>
                <strong><?php echo ($edit) ? 'EDIT TEMPLATE' : 'ADD NEW TEMPLATE'; ?></strong>
            </div>

            <div class="panel-body">
                <form method="POST" id="tcForm">

                    <!-- HIDDEN ID -->
                    <input type="hidden" name="tc_id" value="<?php echo ($edit) ? $edit['tc_id'] : 0; ?>">

                    <div class="form-group form-group-compact">
                        <label>Template Name</label>
                        <input type="text"
                               id="template_name"
                               name="template_name"
                               class="form-control"
                               style="height: 32px;"
                               placeholder="e.g. Standard Invoice Terms"
                               required
                               autofocus
                               value="<?= ($edit) ? $edit['template_name'] : ''; ?>">
                    </div>

                    <!-- TEMPLATE TEXT -->
                    <div class="form-group form-group-compact">
                        <label>Terms & Conditions</label>
                        <textarea name="template"
                                  id="template"
                                  rows="9"
                                  class="form-control"
                                  placeholder="Enter detailed terms and conditions here..."
                                  required><?= ($edit) ? $edit['template'] : ''; ?></textarea>
                    </div>

                    <!-- BUTTONS -->
                    <div class="btn-group-flex">
                        <?php if($edit): ?>
                            <a href="terms_conditions_master.php" class="btn btn-clear btn-custom">
                                Cancel
                            </a>
                            <button type="submit" name="save_terms" class="btn btn-primary-custom btn-custom">
                                Update Template
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn btn-clear btn-custom" onclick="clearForm()">
                                Clear
                            </button>
                            <button type="submit" name="save_terms" class="btn btn-success-custom btn-custom">
                                Save Template
                            </button>
                        <?php endif; ?>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <!-- RIGHT SIDE : LIVE PREVIEW & SAVED LIST -->
    <div class="col-md-7">

        <!-- LIVE PREVIEW -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <i class="glyphicon glyphicon-eye-open" style="color: #2b8cff; margin-right: 5px;"></i>
                <strong>LIVE PREVIEW</strong>
            </div>
            <div class="panel-body">
                <div id="previewBox" class="preview-box">
                    <?php echo ($edit) ? nl2br($edit['template']) : "Terms preview will appear here..."; ?>
                </div>
            </div>
        </div>

        <!-- TABLE -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <strong>SAVED TEMPLATES</strong>
            </div>

            <div class="panel-body">

                <div class="search-box">
                    <i class="glyphicon glyphicon-search"></i>
                    <input type="text" id="searchTemplate" class="form-control" placeholder="Search template...">
                </div>

                <div class="table-scrollable">
                    <table class="table table-bordered table-striped" id="templateTable">
                        <thead>
                            <tr>
                                <th width="40" class="text-center">#</th>
                                <th>Template Name</th>
                                <th>Preview</th>
                                <th>Updated By</th>
                                <th>Updated At</th>
                                <th width="80" class="text-center">Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if(!empty($templates)): ?>
                                <?php foreach($templates as $i => $t): ?>
                                    <tr>
                                        <td class="text-center"><?= $i + 1; ?></td>
                                        <td><strong><?= $t['template_name']; ?></strong></td>
                                        <td><span style="color: #64748b;"><?= substr($t['template'],0,45); ?>...</span></td>
                                        <td><small><?= $t['updated_by']; ?></small></td>
                                        <td><small><?= !empty($t['updated_at']) ? date('d/M/Y h:i A', strtotime($t['updated_at'])) : '-'; ?></small></td>
                                        <td class="action-td">
                                            <div class="action-cell">
                                                <a href="terms_conditions_master.php?edit=<?= $t['tc_id']; ?>" class="btn btn-primary btn-xs equal-btn" title="Edit">
                                                    <i class="glyphicon glyphicon-pencil"></i>
                                                </a>
                                                <button type="button" onclick="confirmDelete(<?= $t['tc_id']; ?>)" class="btn btn-danger btn-xs equal-btn" title="Delete">
                                                    <i class="glyphicon glyphicon-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center" style="color: #94a3b8; padding: 15px;">No terms templates found.</td>
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
    let input = document.getElementById("template_name");
    if(input) input.focus();
};

function clearForm(){
    document.getElementById("tcForm").reset();
    document.getElementById("previewBox").innerText = "Terms preview will appear here...";
    let input = document.getElementById("template_name");
    if(input) input.focus();
}

/* LIVE PREVIEW */
document.getElementById("template").addEventListener("keyup", function(){
    let value = this.value.trim();
    document.getElementById("previewBox").innerText = (value == "") ? "Terms preview will appear here..." : value;
});

/* SEARCH FILTER */
document.getElementById("searchTemplate").addEventListener("keyup", function(){
    let value = this.value.toLowerCase();
    document.querySelectorAll("#templateTable tbody tr").forEach(function(row){
        row.style.display = row.innerText.toLowerCase().includes(value) ? "" : "none";
    });
});

/* Delete confirmation modal */
function confirmDelete(id) {
    Swal.fire({
        title: 'Are You Sure?',
        text: "This Terms-Conditions Will Be Deleted!",
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
</script>


<?php include_once('layouts/footer.php'); ?>
