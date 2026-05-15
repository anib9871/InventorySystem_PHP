<ul id="sidebarMenu">

<?php 

$combined = (isset($_SESSION['combined_mode']) && $_SESSION['combined_mode'] == 1);

$inventoryOnly = (
isset($_SESSION['inventory_access']) &&
$_SESSION['inventory_access'] == 1 &&
!$combined
);

$billingOnly = (
isset($_SESSION['billing_access']) &&
$_SESSION['billing_access'] == 1 &&
!$combined
);

$showInventory = $combined || $inventoryOnly;
$showBilling   = $combined || $billingOnly;

?>

<!-- ================= INVENTORY USER ================= -->

<?php if($showInventory): ?>

<li class="inventory-header">

  <a href="#" class="submenu-toggle">

    <i class="glyphicon glyphicon-folder-open"></i>

    INVENTORY USER PANEL

    <span class="arrow">
      <i class="glyphicon glyphicon-chevron-right"></i>
    </span>

  </a>

  <ul class="submenu">

    <li>
      <a href="user_dashboard.php">
        <i class="glyphicon glyphicon-home"></i>
        Dashboard
      </a>
    </li>

    <li>

      <a href="#" class="submenu-toggle">

        <i class="glyphicon glyphicon-credit-card"></i>

        Transaction

        <span class="arrow">
          <i class="glyphicon glyphicon-chevron-right"></i>
        </span>

      </a>

      <ul class="submenu">

        <li>
          <a href="grn.php">
            GRN
          </a>
        </li>

        <li>
          <a href="invoice_create.php">
            Invoice
          </a>
        </li>

        <li>
          <a href="invoice_list.php">
            Save Invoice
          </a>
        </li>

        

      </ul>

    </li>

  </ul>

</li>

<?php endif; ?>


<!-- ================= BILLING USER ================= -->

<?php if($showBilling): ?>

<li class="billing-header">

  <a href="#" class="submenu-toggle">

    <i class="glyphicon glyphicon-user"></i>

    BILLING USER PANEL

    <span class="arrow">
      <i class="glyphicon glyphicon-chevron-right"></i>
    </span>

  </a>

  <ul class="submenu">

    <li>
      <a href="user_dashboard.php">

        <i class="glyphicon glyphicon-home"></i>

        Dashboard

      </a>
    </li>

    <li>

      <a href="#" class="submenu-toggle">

        <i class="glyphicon glyphicon-credit-card"></i>

        Transaction

        <span class="arrow">
          <i class="glyphicon glyphicon-chevron-right"></i>
        </span>

      </a>

      <ul class="submenu">

        <li>
          <a href="invoice_create.php">
            Direct Billing
          </a>
        </li>

        <li>
          <a href="invoice_list.php">
            Save Invoice
          </a>
        </li>
        <!-- <li><a href="billing_sales.php">Sales Report</a></li> -->
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
        <!-- <li><a href="billing_sales.php">Report</a></li> -->
  </ul>
</li>
  </ul>

</li>

<?php endif; ?>

</ul>
