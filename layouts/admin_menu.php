<ul id="sidebarMenu">

<?php 

$combined     = (isset($_SESSION['combined_mode'])    && $_SESSION['combined_mode']    == 1);
$inventoryOnly= (isset($_SESSION['inventory_access']) && $_SESSION['inventory_access'] == 1 && !$combined);
$billingOnly  = (isset($_SESSION['billing_access'])   && $_SESSION['billing_access']   == 1 && !$combined);

$showInventory = $combined || $inventoryOnly;
$showBilling   = $combined || $billingOnly;

$gst_enabled = "Yes";

$config = find_by_sql("
  SELECT gst_registered
  FROM configuration_master
  LIMIT 1
");

if(!empty($config)){
   $gst_enabled = $config[0]['gst_registered'];
}

?>

<!-- ================= INVENTORY ================= -->
<?php if($showInventory): ?>
<li class="inventory-header">
  <a href="#" class="submenu-toggle" data-menu="inventory_main">
    <i class="glyphicon glyphicon-folder-open"></i>
    INVENTORY SYSTEM
    <span class="arrow"><i class="glyphicon glyphicon-chevron-right"></i></span>
  </a>

  <!-- <ul class="submenu"> -->
  <ul class="submenu" style="display:block !important;">
    

    <!-- DASHBOARD -->
    <li>
      <a href="admin.php?system=inventory">
        <i class="glyphicon glyphicon-home"></i>
        Dashboard
      </a>
    </li>

    <!-- MASTERS -->
    <li>
      <a href="#" class="submenu-toggle" data-menu="inventory_masters">
        <i class="glyphicon glyphicon-briefcase"></i>
        Masters
        <span class="arrow"><i class="glyphicon glyphicon-chevron-right"></i></span>
      </a>
      <ul class="submenu">
        <li><a href="organization_master.php">Organization Master</a></li>
        <li><a href="master_center.php">Centers</a></li>
        <li><a href="payment_mode_master.php">Paymode</a></li>
        <li><a href="supplier_master.php">Supplier Master</a></li>
        <li><a href="customer_master.php">Customer Master</a></li>
        <li><a href="product.php">Products</a></li>
        <li><a href="bom_master.php">BOM Master</a></li>
        <li><a href="group.php">User Role</a></li>
        <li><a href="users.php">Users</a></li>
        <li><a href="categorie.php">Categories</a></li>
        <?php if($gst_enabled == "Yes"): ?>
        <li><a href="gst_master.php">GST Master</a></li>
        <?php endif; ?>
        <?php if($gst_enabled == "Yes"): ?>
        <li><a href="gst_state_master.php">GST State Code Master</a></li>
        <?php endif; ?>
        <li><a href="shipping_type_master.php">Shipping Type Master</a></li>
        <li><a href="configuration_master.php">Configuration Master</a></li>
        <!-- <li><a href="rate_master.php">Rate Master</a></li> -->
        <li><a href="financial_year_master.php">Financial Year Master</a></li>
        <li><a href="master_sequence.php">Sequence Master</a></li>
        <li><a href="bank_master.php">Bank Master</a></li>
        <li><a href="print_type_master.php">Print Type Master</a></li>
        <li><a href="terms_conditions_master.php">Terms Conditions Master</a></li>
        <li><a href="expense_master.php">Expense Master</a></li>
      </ul>
    </li>

    <!-- TRANSACTION -->
    <li>
      <a href="#" class="submenu-toggle" data-menu="inventory_transaction">
        <i class="glyphicon glyphicon-credit-card"></i>
        Transaction
        <span class="arrow"><i class="glyphicon glyphicon-chevron-right"></i></span>
      </a>
      <ul class="submenu">
        <!-- <li><a href="grn.php">GRN</a></li> -->
        <li><a href="manage_grn.php">GRN</a></li>
        <!-- <li><a href="create_quotation.php">Quotation</a></li> -->
        <li><a href="quotation_list.php">Quotation</a></li>
        <!-- <li><a href="invoice_create.php?system=inventory">Invoice</a></li> -->
        <li><a href="demo_item_list.php">Demo Item Detail </a></li>
        <li><a href="invoice_list.php">Invoice</a></li>
        <li><a href="manufacture.php">Manufacture</a></li>
        <li><a href="return_master.php">Return</a></li>

      </ul>
    </li>

       <!-- REPORT -->
    <li>
      <a href="#" class="submenu-toggle" data-menu="inventory_payments">
        <i class="glyphicon glyphicon-duplicate"></i>
        Payments
        <span class="arrow"><i class="glyphicon glyphicon-chevron-right"></i></span>
      </a>
      <ul class="submenu">
        <li><a href="supplier_advance.php">Supplier Advance</a></li>
         <li><a href="payments.php">Payment Pendency Report</a></li>
        <!-- <li><a href="payment_report.php">Payment Reports</a></li> -->
         <li><a href="add_expence.php">Expence</a></li>
        
      </ul>
    </li>

    <!-- REPORT -->
    <li>
      <a href="#" class="submenu-toggle" data-menu="inventory_reports">
        <i class="glyphicon glyphicon-duplicate"></i>
        Reports
        <span class="arrow"><i class="glyphicon glyphicon-chevron-right"></i></span>
      </a>
      <ul class="submenu">
        <li><a href="stock_book.php">Stock Report</a></li>
        <li><a href="inventory_report.php">Sales Report</a></li>
        <li><a href="purchase_report.php">Purchase Report</a></li>
        <li><a href="ledger_report.php">Ledger Report</a></li>
        <li><a href="daily_revenue_report.php">Revenue Report</a></li>
         <li><a href="expense_report.php">Expense Report</a></li>
        
      </ul>
    </li>

  </ul>
</li>
<?php endif; ?>


<!-- ================= BILLING ================= -->
<?php if($showBilling): ?>
<li class="billing-header">
  <a href="#" class="submenu-toggle" data-menu="billing_main">
    <i class="glyphicon glyphicon-credit-card"></i>
    BILLING SYSTEM
    <span class="arrow"><i class="glyphicon glyphicon-chevron-right"></i></span>
  </a>

  <ul class="submenu">

    <!-- DASHBOARD -->
    <li>
      <a href="admin.php?system=billing">
        <i class="glyphicon glyphicon-home"></i>
        Dashboard
      </a>
    </li>

    <!-- MASTERS -->
    <li>
      <a href="#" class="submenu-toggle" data-menu="billing_masters">
        <i class="glyphicon glyphicon-briefcase"></i>
        Masters
        <span class="arrow"><i class="glyphicon glyphicon-chevron-right"></i></span>
      </a>
      <ul class="submenu">
        <li><a href="organization_master.php">Organization Master</a></li>
        <li><a href="master_center.php">Centers</a></li>
        <li><a href="payment_mode_master.php">Payment Mode Master</a></li>
        <li><a href="group.php">User Role</a></li>
        <li><a href="users.php">Users</a></li>
        <li><a href="categorie.php">Categories</a></li>
        <?php if($gst_enabled == "Yes"): ?>
        <li><a href="gst_master.php">GST Master</a></li>
        <?php endif; ?>
        <?php if($gst_enabled == "Yes"): ?>
        <li><a href="gst_state_master.php">GST State Code Master</a></li>
        <?php endif; ?>
        <li><a href="shipping_type_master.php">Shipping Type Master</a></li>
        <li><a href="configuration_master.php">Configuration Master</a></li>
        <li><a href="product.php">Products</a></li>
        <li><a href="rate_master.php">Rate Master</a></li>
        <li><a href="customer_master.php">Customer Master</a></li>
        <li><a href="financial_year_master.php">Financial Year Master</a></li>
        <li><a href="master_sequence.php">Sequence Master</a></li>
        <li><a href="bank_master.php">Bank Master</a></li>
        <li><a href="print_type_master.php">Print Type Master</a></li>
        <li><a href="terms_conditions_master.php">Terms Conditions Master</a></li>
      </ul>
    </li>

    <!-- TRANSACTION -->
    <li>
      <a href="#" class="submenu-toggle" data-menu="billing_transaction">
        <i class="glyphicon glyphicon-credit-card"></i>
        Transaction
        <span class="arrow"><i class="glyphicon glyphicon-chevron-right"></i></span>
      </a>
      <ul class="submenu">
        <li><a href="invoice_create.php">Direct Billing</a></li>
        <li><a href="invoice_list.php">Duplicate Print</a></li>
        <li><a href="payments.php">Manage Payments</a></li>
        <li><a href="payment_report.php">Payments Report</a></li>


      </ul>
    </li>

    <!-- REPORT -->
    <li>
      <a href="#" class="submenu-toggle" data-menu="billing_reports">
        <i class="glyphicon glyphicon-credit-card"></i>
        Report
        <span class="arrow"><i class="glyphicon glyphicon-chevron-right"></i></span>
      </a>
      <ul class="submenu">
        <li><a href="business_report.php">Sales Report</a></li>
      </ul>
    </li>

  </ul>
</li>
<?php endif; ?>

</ul>
