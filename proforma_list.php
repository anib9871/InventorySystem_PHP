<?php
$page_title = 'Proforma List';
require_once('includes/load.php');
//page_require_level(2);

/* Fetch ONLY Proforma Invoices (Purane Paid bills yahan nahi dikhenge) */
$invoices = find_by_sql("
  SELECT i.id,
         i.invoice_no,
         i.invoice_date,
         c.customer_name,
         i.net_total,
         i.remarks AS doc_type, 
         i.payment_status
  FROM invoice i
  LEFT JOIN customer_master c ON c.id = i.customer_id
  WHERE i.remarks LIKE 'PROFORMA%' 
    AND i.payment_status != 'Paid' 
    AND i.payment_status != 'Partial'
  ORDER BY i.id DESC
");

include_once('layouts/header.php');
?>

<style>
    /* Custom Styling matching the new UI */
    .custom-panel {
        background: #fff;
        border: none;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .custom-panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        border-bottom: 2px solid #3b82f6; 
    }
    .custom-panel-title {
        font-size: 15px;
        font-weight: 700;
        color: #1e293b;
        letter-spacing: 0.5px;
        margin: 0;
    }
    .btn-pill {
        border-radius: 20px;
        font-weight: 600;
        font-size: 12px;
        padding: 6px 16px;
        letter-spacing: 0.3px;
    }
    .btn-tax-invoice {
        background-color: #3b82f6; /* Blue for Tax Invoices */
        color: white;
        border: none;
    }
    .btn-tax-invoice:hover { background-color: #2563eb; color: white; }
    
    .btn-create {
        background-color: #10b981; /* Green for Create */
        color: white;
        border: none;
        margin-left: 8px;
    }
    .btn-create:hover { background-color: #059669; color: white; }

    .search-container {
        width: 300px;
        margin-bottom: 15px;
    }
    .search-input {
        border-radius: 4px;
        border: 1px solid #cbd5e1;
        box-shadow: none;
    }

    /* Table Styling */
    .custom-table {
        border: 1px solid #e2e8f0;
        margin-bottom: 0;
    }
    .custom-table thead th {
        background-color: #1e293b !important; 
        color: #ffffff !important;
        font-weight: 600;
        font-size: 13px;
        border-bottom: none !important;
        padding: 10px 15px;
    }
    .custom-table tbody td {
        padding: 10px 15px;
        vertical-align: middle;
        font-size: 13.5px;
        color: #334155;
        border-top: 1px solid #f1f5f9;
    }
    .action-btn {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        margin: 0 2px;
    }
    
    /* Badges */
    .status-badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11.5px;
        font-weight: 600;
    }
    .badge-pending { background-color: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; }
    .badge-converted { background-color: #d1fae5; color: #059669; border: 1px solid #6ee7b7; }
</style>

<div class="row">
    <div class="col-md-12">
        <div class="panel custom-panel">
            
            <!-- HEADER SECTION -->
            <div class="custom-panel-header">
                <h4 class="custom-panel-title">PROFORMA INVOICE LIST</h4>
                <div>
                    <!-- 🔥 LINK TO TAX INVOICES 🔥 -->
                    <a href="invoice_list.php" class="btn btn-tax-invoice btn-pill">
                        <i class="glyphicon glyphicon-list-alt"></i> TAX INVOICES
                    </a>
                    
                    <!-- CREATE INVOICE BUTTON -->
                    <a href="invoice_create.php" class="btn btn-create btn-pill">
                        + CREATE INVOICE
                    </a>
                </div>
            </div>

            <div class="panel-body">
                <!-- SEARCH BAR -->
                <div class="search-container">
                    <div class="input-group">
                        <span class="input-group-addon" style="background:#fff; border-color:#cbd5e1;"><i class="glyphicon glyphicon-search text-muted"></i></span>
                        <input type="text" id="searchInput" class="form-control search-input" placeholder="Search Proforma...">
                    </div>
                </div>

                <!-- TABLE WRAPPER -->
                <div style="max-height:500px; overflow-y:auto; border-radius: 4px;">
                    <table class="table table-striped table-hover custom-table">
                        <thead>
                            <tr>
                                <th style="width: 5%;">#</th>
                                <th style="width: 15%;">Proforma No</th>
                                <th style="width: 15%;">Date</th>
                                <th style="width: 25%;">Customer</th>
                                <th style="width: 15%;">Net Amount</th>
                                <th style="width: 10%;">Status</th>
                                <th style="width: 15%; text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="proformaTableBody">
                            <?php
                            $i = 1;
                            foreach($invoices as $inv){
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td style="font-weight: 500; color: #f59e0b;"><?php echo $inv['invoice_no']; ?></td>
                                <td><?php echo date('d/M/Y', strtotime($inv['invoice_date'])); ?></td>
                                <td><?php echo htmlspecialchars($inv['customer_name']); ?></td>
                                <td style="font-weight: 600;">₹<?php echo number_format($inv['net_total'], 2); ?></td>
                                
                                <!-- Conversion Status -->
                                <td>
                                    <?php if($inv['payment_status'] == 'Converted'): ?>
                                        <span class="status-badge badge-converted">Converted</span>
                                    <?php else: ?>
                                        <span class="status-badge badge-pending">Pending</span>
                                    <?php endif; ?>
                                </td>

                                <td style="text-align: center;">
                                    <!-- PRINT ICON BUTTON (Red) -->
                                    <a href="invoice_print.php?id=<?php echo $inv['id']; ?>" target="_blank" class="btn btn-danger action-btn" title="Print">
                                        <i class="glyphicon glyphicon-print"></i>
                                    </a>

                                    <!-- EDIT ICON BUTTON (Blue) -->
                                    <a href="invoice_edit.php?id=<?php echo $inv['id']; ?>" class="btn btn-info action-btn" title="Edit">
                                        <i class="glyphicon glyphicon-pencil"></i>
                                    </a>

                                    <!-- 🔥 Cancel Button (Naya Code - English Alert) 🔥 -->
                                    <?php if($inv['payment_status'] !== 'Cancelled' && $inv['payment_status'] !== 'Converted'): ?>
                                        <a href="proforma_cancel.php?id=<?php echo $inv['id']; ?>" 
                                           class="btn action-btn" 
                                           style="background-color: #333; color: white;" 
                                           title="Cancel Proforma"
                                           onclick="return confirm('Are you sure you want to cancel this Proforma Invoice?');">
                                            <i class="glyphicon glyphicon-ban-circle"></i>
                                        </a>
                                    <?php elseif($inv['payment_status'] === 'Cancelled'): ?>
                                        <span class="status-badge" style="background: #e5e7eb; color: #374151; display:inline-block; margin-top:5px; font-size:10px;">Cancelled</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                    
                    <?php if(empty($invoices)): ?>
                        <div style="text-align:center; padding: 30px; color: #94a3b8; font-weight: 500;">
                            No Proforma Invoices Found.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
// Search Functionality Logic for Proforma List
document.getElementById("searchInput").addEventListener("keyup", function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll("#proformaTableBody tr");

    rows.forEach(row => {
        let text = row.textContent.toLowerCase();
        if(text.includes(filter)){
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
});
</script>

<?php include_once('layouts/footer.php'); ?>
