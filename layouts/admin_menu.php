<ul id="sidebarMenu">

<?> 
$combined      = (isset($_SESSION['combined_mode'])    && $_SESSION['combined_mode']    == 1);
$inventoryOnly = (isset($_SESSION['inventory_access']) && $_SESSION['inventory_access'] == 1 && !$combined);
$billingOnly   = (isset($_SESSION['billing_access'])   && $_SESSION['billing_access']   == 1 && !$combined);

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

$raw_perms = isset($_SESSION['sub_permissions']) ? $_SESSION['sub_permissions'] : [];
$sub_perms = array_map('trim', $raw_perms);

// Helper arrays to check if any sub-permission exists for a section
$master_perms = ['org_master', 'centers', 'paymode', 'supplier_master', 'customer_master', 'products', 'bom_master', 'user_role', 'users', 'categorie', 'gst_master', 'gst_state', 'shipping_type', 'config_master', 'financial_year', 'sequence_master', 'bank_master', 'print_type', 'terms_cond', 'expense_master', 'categories'];
$inv_trans_perms = ['manage_grn', 'quotation_list', 'demo_item_list', 'invoice_list', 'manufacture', 'return_master', 'grn', 'quotation', 'invoice', 'demo_item', 'return', 'direct_billing', 'duplicate_print'];
$payment_perms = ['supplier_advance', 'payments', 'add_expense', 'supp_advance', 'pay_pendency', 'expense', 'manage_payments', 'payment_report'];
$report_perms = ['stock_book', 'inventory_report', 'purchase_report', 'ledger_report', 'daily_revenue_report', 'expense_report', 'stock_report', 'sales_report', 'revenue_report', 'business_report'];

$has_master_access = !empty(array_intersect($master_perms, $sub_perms));
$has_inv_trans_access = !empty(array_intersect($inv_trans_perms, $sub_perms));
$has_payment_access = !empty(array_intersect($payment_perms, $sub_perms));
$has_report_access = !empty(array_intersect($report_perms, $sub_perms));
?>

<!-- ================= INVENTORY ================= -->
<?php if($showInventory): ?>
<li class="inventory-header">
  <a href="#" class="submenu-toggle" data-menu="inventory_main">
    <i class="glyphicon glyphicon-folder-open"></i>
    INVENTORY SYSTEM
    <span class="arrow"><i class="glyphicon glyphicon-chevron-right"></i></span>
  </a>

  <ul class="submenu" style="display:block !important;">

    <!-- DASHBOARD -->
    <li>
      <?php if(isset($_SESSION['user_level']) && $_SESSION['user_level'] == 1): ?>
          <a href="admin.php?system=inventory">
      <?php else: ?>
          <a href="user_dashboard.php">
      <?php endif; ?>
        <i class="glyphicon glyphicon-home"></i>
        Dashboard
      </a>
    </li>

    <!-- MASTERS -->
    <?php if((isset($_SESSION['menu_masters']) && $_SESSION['menu_masters'] == 1 && $has_master_access) || (isset($_SESSION['user_level']) && $_SESSION['user_level'] == 1)): ?>
    <li>
      <a href="#" class="submenu-toggle" data-menu="inventory_masters">
        <i class="glyphicon glyphicon-briefcase"></i>
        Masters
        <span class="arrow"><i class="glyphicon glyphicon-chevron-right"></i></span>
      </a>
      <ul class="submenu">
        <?php if(in_array('org_master', $sub_perms)): ?><li><a href="organization_master.php">Organization Master</a></li><?php endif; ?>
        <?php if(in_array('centers', $sub_perms)): ?><li><a href="master_center.php">Centers</a></li><?php endif; ?>
        <?php if(in_array('paymode', $sub_perms)): ?><li><a href="payment_mode_master.php">Paymode</a></li><?php endif; ?>
        <?php if(in_array('supplier_master', $sub_perms)): ?><li><a href="supplier_master.php">Supplier Master</a></li><?php endif; ?>
        <?php if(in_array('customer_master', $sub_perms)): ?><li><a href="customer_master.php">Customer Master</a></li><?php endif; ?>
        <?php if(in_array('products', $sub_perms)): ?><li><a href="product.php">Products</a></li><?php endif; ?>
        <?php if(in_array('bom_master', $sub_perms)): ?><li><a href="bom_master.php">BOM Master</a></li><?php endif; ?>
        <?php if(in_array('user_role', $sub_perms)): ?><li><a href="group.php">User Role</a></li><?php endif; ?>
        <?php if(in_array('users', $sub_perms)): ?><li><a href="users.php">Users</a></li><?php endif; ?>
        <?php if(in_array('categorie', $sub_perms) || in_array('categories', $sub_perms)): ?><li><a href="categorie.php">Categories</a></li><?php endif; ?>
        <?php if($gst_enabled == "Yes" && in_array('gst_master', $sub_perms)): ?>
        <li><a href="gst_master.php">GST Master</a></li>
        <?php endif; ?>
        <?php if($gst_enabled == "Yes" && in_array('gst_state', $sub_perms)): ?>
        <li><a href="gst_state_master.php">GST State Code Master</a></li>
        <?php endif; ?>
        <?php if(in_array('shipping_type', $sub_perms)): ?><li><a href="shipping_type_master.php">Shipping Type Master</a></li><?php endif; ?>
        <?php if(in_array('config_master', $sub_perms)): ?><li><a href="configuration_master.php">Configuration Master</a></li><?php endif; ?>
        <?php if(in_array('fin_year', $sub_perms)): ?><li><a href="financial_year_master.php">Financial Year Master</a></li><?php endif; ?>
        <?php if(in_array('sequence_master', $sub_perms)): ?><li><a href="master_sequence.php">Sequence Master</a></li><?php endif; ?>
        <?php if(in_array('bank_master', $sub_perms)): ?><li><a href="bank_master.php">Bank Master</a></li><?php endif; ?>
        <?php if(in_array('print_type', $sub_perms)): ?><li><a href="print_type_master.php">Print Type Master</a></li><?php endif; ?>
        <?php if(in_array('terms_cond', $sub_perms)): ?><li><a href="terms_conditions_master.php">Terms Conditions Master</a></li><?php endif; ?>
        <?php if(in_array('expense_master', $sub_perms)): ?><li><a href="expense_master.php">Expense Master</a></li><?php endif; ?>
      </ul>
    </li>
    <?php endif; ?>

    <!-- TRANSACTION -->
    <?php if((isset($_SESSION['menu_transaction']) && $_SESSION['menu_transaction'] == 1 && $has_inv_trans_access) || (isset($_SESSION['user_level']) && $_SESSION['user_level'] == 1)): ?>
    <li>
      <a href="#" class="submenu-toggle" data-menu="inventory_transaction">
        <i class="glyphicon glyphicon-credit-card"></i>
        Transaction
        <span class="arrow"><i class="glyphicon glyphicon-chevron-right"></i></span>
      </a>
      <ul class="submenu">
        <?php if(in_array('grn', $sub_perms)): ?><li><a href="manage_grn.php">GRN</a></li><?php endif; ?>
        <?php if(in_array('quotation', $sub_perms)): ?><li><a href="quotation_list.php">Quotation</a></li><?php endif; ?>
        <?php if(in_array('demo_item', $sub_perms)): ?><li><a href="demo_item_list.php">Demo Item Detail </a></li><?php endif; ?>
        <?php if(in_array('invoice', $sub_perms) || in_array('duplicate_print', $sub_perms)): ?><li><a href="invoice_list.php">Invoice</a></li><?php endif; ?>
        <?php if(in_array('manufacture', $sub_perms)): ?><li><a href="manufacture.php">Manufacture</a></li><?php endif; ?>
        <?php if(in_array('return', $sub_perms)): ?><li><a href="return_master.php">Return</a></li><?php endif; ?>
      </ul>
    </li>
    <?php endif; ?>

    <!-- PAYMENTS -->
    <?php if((isset($_SESSION['menu_payments']) && $_SESSION['menu_payments'] == 1 && $has_payment_access) || (isset($_SESSION['user_level']) && $_SESSION['user_level'] == 1)): ?>
    <li>
      <a href="#" class="submenu-toggle" data-menu="inventory_payments">
        <i class="glyphicon glyphicon-duplicate"></i>
        Payments
        <span class="arrow"><i class="glyphicon glyphicon-chevron-right"></i></span>
      </a>
      <ul class="submenu">
        <?php if(in_array('supp_advance', $sub_perms)): ?><li><a href="supplier_advance.php">Supplier Advance</a></li><?php endif; ?>
         <?php if(in_array('pay_pendency', $sub_perms)): ?><li><a href="payments.php">Payment Pendency Report</a></li><?php endif; ?>
         <?php if(in_array('expense', $sub_perms)): ?><li><a href="add_expense.php">Expense</a></li><?php endif; ?>
      </ul>
    </li>
    <?php endif; ?>

    <!-- REPORTS -->
    <?php if((isset($_SESSION['menu_reports']) && $_SESSION['menu_reports'] == 1 && $has_report_access) || (isset($_SESSION['user_level']) && $_SESSION['user_level'] == 1)): ?>
    <li>
      <a href="#" class="submenu-toggle" data-menu="inventory_reports">
        <i class="glyphicon glyphicon-duplicate"></i>
        Reports
        <span class="arrow"><i class="glyphicon glyphicon-chevron-right"></i></span>
      </a>
      <ul class="submenu">
        <?php if(in_array('stock_report', $sub_perms)): ?><li><a href="stock_book.php">Stock Report</a></li><?php endif; ?>
        <?php if(in_array('sales_report', $sub_perms)): ?><li><a href="inventory_report.php">Sales Report</a></li><?php endif; ?>
        <?php if(in_array('purchase_report', $sub_perms)): ?><li><a href="purchase_report.php">Purchase Report</a></li><?php endif; ?>
        <?php if(in_array('ledger_report', $sub_perms)): ?><li><a href="ledger_report.php">Ledger Report</a></li><?php endif; ?>
        <?php if(in_array('revenue_report', $sub_perms)): ?><li><a href="daily_revenue_report.php">Revenue Report</a></li><?php endif; ?>
         <?php if(in_array('expense_report', $sub_perms)): ?><li><a href="expense_report.php">Expense Report</a></li><?php endif; ?>
      </ul>
    </li>
    <?php endif; ?>

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
      <?php if(isset($_SESSION['user_level']) && $_SESSION['user_level'] == 1): ?>
          <a href="admin.php?system=billing">
      <?php else: ?>
          <a href="user_dashboard.php">
      <?php endif; ?>
        <i class="glyphicon glyphicon-home"></i>
        Dashboard
      </a>
    </li>

    <!-- MASTERS -->
    <?php if((isset($_SESSION['menu_masters']) && $_SESSION['menu_masters'] == 1 && $has_master_access) || (isset($_SESSION['user_level']) && $_SESSION['user_level'] == 1)): ?>
    <li>
      <a href="#" class="submenu-toggle" data-menu="billing_masters">
        <i class="glyphicon glyphicon-briefcase"></i>
        Masters
        <span class="arrow"><i class="glyphicon glyphicon-chevron-right"></i></span>
      </a>
      <ul class="submenu">
        <?php if(in_array('org_master', $sub_perms)): ?><li><a href="organization_master.php">Organization Master</a></li><?php endif; ?>
        <?php if(in_array('centers', $sub_perms)): ?><li><a href="master_center.php">Centers</a></li><?php endif; ?>
        <?php if(in_array('paymode', $sub_perms)): ?><li><a href="payment_mode_master.php">Payment Mode Master</a></li><?php endif; ?>
        <?php if(in_array('user_role', $sub_perms)): ?><li><a href="group.php">User Role</a></li><?php endif; ?>
        <?php if(in_array('users', $sub_perms)): ?><li><a href="users.php">Users</a></li><?php endif; ?>
        <?php if(in_array('categorie', $sub_perms) || in_array('categories', $sub_perms)): ?><li><a href="categorie.php">Categories</a></li><?php endif; ?>
        <?php if($gst_enabled == "Yes" && in_array('gst_master', $sub_perms)): ?>
        <li><a href="gst_master.php">GST Master</a></li>
        <?php endif; ?>
        <?php if($gst_enabled == "Yes" && in_array('gst_state', $sub_perms)): ?>
        <li><a href="gst_state_master.php">GST State Code Master</a></li>
        <?php endif; ?>
        <?php if(in_array('shipping_type', $sub_perms)): ?><li><a href="shipping_type_master.php">Shipping Type Master</a></li><?php endif; ?>
        <?php if(in_array('config_master', $sub_perms)): ?><li><a href="configuration_master.php">Configuration Master</a></li><?php endif; ?>
        <?php if(in_array('products', $sub_perms)): ?><li><a href="product.php">Products</a></li><?php endif; ?>
        <?php if(in_array('rate_master', $sub_perms)): ?><li><a href="rate_master.php">Rate Master</a></li><?php endif; ?>
        <?php if(in_array('customer_master', $sub_perms)): ?><li><a href="customer_master.php">Customer Master</a></li><?php endif; ?>
        <?php if(in_array('fin_year', $sub_perms)): ?><li><a href="financial_year_master.php">Financial Year Master</a></li><?php endif; ?>
        <?php if(in_array('sequence_master', $sub_perms)): ?><li><a href="master_sequence.php">Sequence Master</a></li><?php endif; ?>
        <?php if(in_array('bank_master', $sub_perms)): ?><li><a href="bank_master.php">Bank Master</a></li><?php endif; ?>
        <?php if(in_array('print_type', $sub_perms)): ?><li><a href="print_type_master.php">Print Type Master</a></li><?php endif; ?>
        <?php if(in_array('terms_cond', $sub_perms)): ?><li><a href="terms_conditions_master.php">Terms Conditions Master</a></li><?php endif; ?>
      </ul>
    </li>
    <?php endif; ?>

    <!-- TRANSACTION -->
    <?php if((isset($_SESSION['menu_transaction']) && $_SESSION['menu_transaction'] == 1 && $has_inv_trans_access) || (isset($_SESSION['user_level']) && $_SESSION['user_level'] == 1)): ?>
    <li>
      <a href="#" class="submenu-toggle" data-menu="billing_transaction">
        <i class="glyphicon glyphicon-credit-card"></i>
        Transaction
        <span class="arrow"><i class="glyphicon glyphicon-chevron-right"></i></span>
      </a>
      <ul class="submenu">
        <?php if(in_array('direct_billing', $sub_perms)): ?><li><a href="invoice_create.php">Direct Billing</a></li><?php endif; ?>
        <?php if(in_array('duplicate_print', $sub_perms)): ?><li><a href="invoice_list.php">Duplicate Print</a></li><?php endif; ?>
        <?php if(in_array('manage_payments', $sub_perms)): ?><li><a href="payments.php">Manage Payments</a></li><?php endif; ?>
        <?php if(in_array('payment_report', $sub_perms)): ?><li><a href="payment_report.php">Payments Report</a></li><?php endif; ?>
      </ul>
    </li>
    <?php endif; ?>

    <!-- REPORT -->
    <?php if((isset($_SESSION['menu_reports']) && $_SESSION['menu_reports'] == 1 && $has_report_access) || (isset($_SESSION['user_level']) && $_SESSION['user_level'] == 1)): ?>
    <li>
      <a href="#" class="submenu-toggle" data-menu="billing_reports">
        <i class="glyphicon glyphicon-credit-card"></i>
        Report
        <span class="arrow"><i class="glyphicon glyphicon-chevron-right"></i></span>
      </a>
      <ul class="submenu">
        <?php if(in_array('business_report', $sub_perms)): ?><li><a href="business_report.php">Sales Report</a></li><?php endif; ?>
      </ul>
    </li>
    <?php endif; ?>

  </ul>
</li>
<?php endif; ?>

</ul>
