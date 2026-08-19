<?php
$page_title = 'GRN';
require_once('includes/load.php');

$products   = find_all('products');
$suppliers  = find_all('supplier_master');

$gst_list = find_by_sql("
  SELECT id, gst_name, gst_percent
  FROM gst_master
  WHERE status = 1
  ORDER BY gst_percent
");

$payment_modes = find_by_sql("
  SELECT * FROM payment_mode_master
  WHERE is_active = 1
  ORDER BY mode_name
");

$edit_mode = false;
$edit_bill = '';

if(isset($_GET['edit'])){
    $edit_mode = true;
    $edit_bill = urldecode($db->escape($_GET['edit']));
}

/* ================= SAVE / UPDATE GRN ================= */
if (isset($_POST['save_grn']) || isset($_POST['update_grn'])) {

  if (empty($_POST['items_json'])) {
    $session->msg("d", "Add at least one item");
    redirect('grn.php');
  }

  $items = json_decode($_POST['items_json'], true);
  if (!is_array($items) || count($items) == 0) {
    $session->msg("d", "Invalid items");
    redirect('grn.php');
  }

  $supplier_id  = (int)$_POST['supplier_id'];
  $bill_no      = $_POST['bill_no'];
  $bill_date    = $_POST['bill_date'];

  $formats = ['d/M/Y', 'd-m-Y', 'Y-m-d'];
  foreach ($formats as $format) {
      $dt = DateTime::createFromFormat($format, $bill_date);
      if ($dt instanceof DateTime) {
          $bill_date = $dt->format('Y-m-d');
          break;
      }
  }

  $payment_mode = $_POST['payment_mode'] ?? '';
  $payments     = json_decode($_POST['payments_json'], true);
  $total_paid   = 0;
  $used_advance = (float)($_POST['used_advance'] ?? 0);
  $comments     = $_POST['comments'] ?? '';

  $entry_date   = date('Y-m-d H:i:s');
  $grand_total  = 0;

  $db->query("START TRANSACTION");

  try {
    if(isset($_POST['update_grn'])){
      $old_bill_no = $db->escape($_POST['old_bill_no']);
      $old = find_by_sql("SELECT * FROM transaction_master WHERE bill_indent_no = '$old_bill_no'");

      foreach($old as $o){
        update_product_qty(-($o['quantity'] + $o['free_qty']), $o['product_id']);
      }

      $db->query("DELETE FROM inventory WHERE transaction_id IN (SELECT transaction_id FROM transaction_master WHERE bill_indent_no = '$old_bill_no')");
      $db->query("DELETE FROM transaction_master WHERE bill_indent_no = '$old_bill_no'");
      $db->query("DELETE FROM shipping WHERE bill_no = '$old_bill_no'");
      $db->query("DELETE FROM supplier_payment WHERE ledger_id IN (SELECT ledger_id FROM supplier_ledger WHERE bill_no = '$old_bill_no')");
      $db->query("DELETE FROM supplier_ledger WHERE bill_no = '$old_bill_no'");
    }

    foreach ($items as $it) {
      $qty   = (float)$it['qty'];
      $free  = (float)$it['free_qty'];
      $rate  = (float)$it['rate'];
      $gstp  = (float)$it['gst_percent'];
      $disc  = (float)$it['discount'];
      $misc  = (float)$it['misc'];
      $mrp   = (float)$it['mrp'];
      $buy_type = $it['buy_type'];

      if($buy_type == 'inclusive'){
          $total_amount = ($qty * $rate) - $disc + $misc;
          $base_amount  = $total_amount / (1 + ($gstp / 100));
          $gst_amount   = $total_amount - $base_amount;
          $net_amount   = $total_amount;
      } else {
          $base_amount = ($qty * $rate) - $disc + $misc;
          $gst_amount  = ($base_amount * $gstp) / 100;
          $net_amount  = $base_amount + $gst_amount;
      }

      $grand_total += $net_amount;

      /* ===== RATE MASTER ===== */
      $existing_rate = find_by_sql("
        SELECT id FROM rate_master
        WHERE product_id = '{$it['product_id']}'
        AND rate = '$rate' AND gst_id = '{$it['gst_id']}' AND mrp = '$mrp'
        LIMIT 1
      ");

      if($existing_rate){
          $rate_id = $existing_rate[0]['id'];
      } else {
          $db->query("
            INSERT INTO rate_master (product_id, rate, mrp, gst_id, price_date, is_active, created_at)
            VALUES ('{$it['product_id']}', '$rate', '$mrp', '{$it['gst_id']}', CURDATE(), 1, NOW())
          ");
          $rate_id = $db->insert_id();
      }

      /* ===== TRANSACTION MASTER ===== */
      $db->query("
        INSERT INTO transaction_master (
          product_id, supplier_id, bill_indent_no, entry_date, bill_indent_date,
          quantity, free_qty, unit, rate_id, gst_id, unit_price, gst_amount,
          discount_amount, misc_amount, net_price, mrp, transaction_type, status,
          payment_status, payment_mode, amount_received, balance_amount,
          from_dept, to_dept, comments, created_at
        ) VALUES (
          '{$it['product_id']}', '$supplier_id', '$bill_no', '$entry_date', '$bill_date',
          '$qty', '$free', 'PCS', '$rate_id', '{$it['gst_id']}', '$rate', '$gst_amount',
          '$disc', '$misc', '$net_amount', '$mrp', 1, 1, 0, '$payment_mode',
          0, '$net_amount', 'SUPPLIER', 'STORE', '$comments', NOW()
        )
      ");

      $transaction_id = $db->insert_id();

      /* ===== INVENTORY ===== */
      $db->query("
        INSERT INTO inventory (transaction_id, product_id, quantity, free_qty, fin_year, status, origin_dept, updated_at)
        VALUES ('$transaction_id', '{$it['product_id']}', '$qty', '$free', '2025-26', 1, 'SUPPLIER', NOW())
      ");

      update_product_qty($qty + $free, $it['product_id']);

      /* ===== UPDATE PRODUCT MASTER ===== */
      $db->query("
        UPDATE products
        SET buy_price = '$rate', gst_id = '{$it['gst_id']}', buy_type = '{$buy_type}'
        WHERE id = '{$it['product_id']}'
      ");
    }

    /* ===== SHIPPING ===== */
    $charges = json_decode($_POST['charges_json'], true);
    if (is_array($charges)) {
      foreach ($charges as $c) {
        $shipping_type_id = (int)$c['shipping_type_id'];
        $gst_id           = (int)$c['gst_id'];
        $amount           = (float)$c['amount'];
        $gst_percent      = (float)$c['gst_percent'];
        $gst_type         = $c['gst_type'];
        $gst_amount       = (float)$c['gst_amount'];
        $total            = (float)$c['total'];

        $db->query("
          INSERT INTO shipping (supplier_id, bill_no, shipping_type_id, gst_id, amount, gst_percent, gst_type, gst_amount, total_amount, created_at)
          VALUES ('$supplier_id', '$bill_no', '$shipping_type_id', '$gst_id', '$amount', '$gst_percent', '$gst_type', '$gst_amount', '$total', NOW())
        ");
        $grand_total += $total;
      }
    }

    $round_off = isset($_POST['round_off']) ? (int)$_POST['round_off'] : 0;
    $grand_total = ($round_off == 1) ? round($grand_total) : round($grand_total, 2);

    if (is_array($payments)) {
      foreach ($payments as $p){
        if(strtoupper($p['mode']) != 'ADVANCE'){
          $total_paid += (float)$p['amount'];
        }
      }
      $total_paid += $used_advance;
    }

    /* ===== SUPPLIER LEDGER ===== */
    $db->query("
      INSERT INTO supplier_ledger (supplier_id, bill_no, bill_date, bill_amount, paid_amount, balance_amount, payment_status, created_at)
      VALUES (
        '$supplier_id', '$bill_no', '$bill_date', '".round($grand_total,2)."',
        '$total_paid', '".max(0, round($grand_total - $total_paid,2))."',
        '".($total_paid >= $grand_total ? 1 : 0)."', NOW()
      )
    ");
    $ledger_id = $db->insert_id();

    /* ===== SUPPLIER PAYMENT ===== */
    if (is_array($payments)) {
      foreach ($payments as $p){
        if(strtoupper($p['mode']) != 'ADVANCE'){
          $mode   = $p['mode'];
          $amount = $p['amount'];
          $utr    = $p['utr'] ?? '';

          $db->query("
            INSERT INTO supplier_payment (ledger_id, supplier_id, payment_date, payment_amount, payment_mode, reference_no, created_at)
            VALUES ('$ledger_id','$supplier_id',CURDATE(),'$amount','$mode','$utr',NOW())
          ");
        }
      }
    }

    if($used_advance > 0){
      $remaining = $used_advance;
      $advances = find_by_sql("
        SELECT ledger_id, balance_amount FROM supplier_ledger
        WHERE supplier_id = '$supplier_id' AND balance_amount < 0
        ORDER BY ledger_id ASC
      ");

      foreach($advances as $adv){
        if($remaining <= 0) break;
        $currentAdvance = abs($adv['balance_amount']);

        if($remaining >= $currentAdvance){
          $db->query("UPDATE supplier_ledger SET balance_amount = 0 WHERE ledger_id = '{$adv['ledger_id']}'");
          $remaining -= $currentAdvance;
          $db->query("
            INSERT INTO supplier_payment (ledger_id, supplier_id, payment_date, payment_amount, payment_mode, reference_no, created_at)
            VALUES ('$ledger_id', '$supplier_id', CURDATE(), '$currentAdvance', 'ADVANCE', 'Advance Adjusted', NOW())
          ");
        } else {
          $newBalance = -($currentAdvance - $remaining);
          $db->query("UPDATE supplier_ledger SET balance_amount = '$newBalance' WHERE ledger_id = '{$adv['ledger_id']}'");
          $adjustAmount = $remaining;
          $db->query("
            INSERT INTO supplier_payment (ledger_id, supplier_id, payment_date, payment_amount, payment_mode, reference_no, created_at)
            VALUES ('$ledger_id', '$supplier_id', CURDATE(), '$adjustAmount', 'ADVANCE', 'Advance Adjusted', NOW())
          ");
          $remaining = 0;
        }
      }
    }

    $db->query("COMMIT");
    redirect(isset($_POST['update_grn']) ? 'grn.php?updated=1' : 'grn.php?created=1');

  } catch (Exception $e) {
    $db->query("ROLLBACK");
    die($e->getMessage());
  }

  redirect('grn.php');
}

$edit_items = [];
$edit_charges = [];
$edit_payments = [];
$edit_advance = 0;
$edit_ledger = null;

if($edit_mode){
  $edit_items = find_by_sql("
    SELECT tm.*, p.name, p.hsn_code, COALESCE(p.buy_type,'exclusive') as buy_type, COALESCE(g.gst_percent,0) as gst_percent
    FROM transaction_master tm
    LEFT JOIN products p ON p.id = tm.product_id
    LEFT JOIN gst_master g ON g.id = tm.gst_id
    WHERE tm.bill_indent_no = '{$edit_bill}'
  ");

  $edit_charges = find_by_sql("
    SELECT s.shipping_type_id, s.gst_id, s.amount, s.gst_percent, s.gst_type, s.gst_amount, s.total_amount as total, stm.type_name
    FROM shipping s
    LEFT JOIN shipping_type_master stm ON stm.id = s.shipping_type_id
    WHERE s.bill_no = '{$edit_bill}'
  ");

  $edit_payments = find_by_sql("
    SELECT sp.* FROM supplier_payment sp
    LEFT JOIN supplier_ledger sl ON sl.ledger_id = sp.ledger_id
    WHERE sl.bill_no = '{$edit_bill}'
  ");

  $ledger_data = find_by_sql("SELECT * FROM supplier_ledger WHERE bill_no = '{$edit_bill}' LIMIT 1");
  $edit_ledger = $ledger_data ? $ledger_data[0] : null;

  // Extract advance paid if any
  foreach($edit_payments as $p){
    if(strtoupper($p['payment_mode']) == 'ADVANCE'){
      $edit_advance += (float)$p['payment_amount'];
    }
  }

  $grn_info = find_by_sql("SELECT * FROM transaction_master WHERE bill_indent_no = '{$edit_bill}' LIMIT 1");
  $grn_info = $grn_info ? $grn_info[0] : [];
}

$shipping_types = find_by_sql("SELECT * FROM shipping_type_master WHERE is_active = 1");

include_once('layouts/header.php');
?>

<?php if(isset($_GET['created'])){ ?>
<script>
Swal.fire({ icon: 'success', title: 'Success', text: 'GRN Created Successfully', showConfirmButton: false, timer: 1800 });
</script>
<?php } ?>

<?php if(isset($_GET['updated'])){ ?>
<script>
Swal.fire({ icon: 'success', title: 'Success', text: 'GRN Updated Successfully', showConfirmButton: false, timer: 1800 });
</script>
<?php } ?>

<div class="row" style="margin-bottom:5px;">
  <div class="col-md-12"><?php echo display_msg($msg); ?></div>
</div>

<div class="grn-card">
  <div class="grn-card-header">
    <i class="fa fa-file-text-o"></i> GOODS RECEIPT NOTE (GRN)
  </div>
  <div class="grn-card-body">
    
    <!-- HEADER INPUTS (SECTION 1) -->
    <div class="section-block">
      <div class="flex-row-balanced">
        <div style="flex: 2.5;">
          <label class="form-label-custom">Supplier <span style="color:red;">*</span></label>
          <select name="supplier_id" id="supplier_id" form="grnForm" class="form-control grn-input-custom" required>
            <option value="">Select Supplier</option>
            <?php foreach ($suppliers as $s) { ?>
              <option value="<?= $s['id']; ?>" <?= ($edit_mode && $grn_info['supplier_id'] == $s['id']) ? 'selected' : ''; ?>>
                <?= $s['supplier_name']; ?>
              </option>
            <?php } ?>
          </select>
        </div>

        <div style="flex: 1.2;">
          <label class="form-label-custom">Bill / GRN No <span style="color:red;">*</span></label>
          <input type="text" name="bill_no" value="<?= $edit_mode ? $grn_info['bill_indent_no'] : ''; ?>" class="form-control grn-input-custom" form="grnForm" placeholder="Enter Bill No" required>
        </div>

        <div style="flex: 1.2;">
          <label class="form-label-custom">Bill Date <span style="color:red;">*</span></label>
          <input type="text" name="bill_date" id="bill_date" value="<?= $edit_mode ? date('d/M/Y', strtotime($grn_info['bill_indent_date'])) : date('d/M/Y'); ?>" class="form-control grn-input-custom" form="grnForm" required>
        </div>
      </div>
    </div>

    <!-- PREVIOUS RATE BOX -->
    <div id="previousRateBox" style="display:none; padding:6px 12px; background:#eff6ff; border-left:4px solid #2563eb; border-radius:4px; font-size:11px; margin-bottom:12px;"></div>

    <input type="hidden" id="product">
    <input type="hidden" id="hsn_code">
    <input type="hidden" id="sac_code">

<!-- ITEM ENTRY FORM (SECTION 2) -->
<div class="section-block">
  <div class="flex-row-balanced">
    <div style="width: 220px;">
      <label class="form-label-custom">Product <span style="color:red;">*</span></label>
      <div style="display: flex; align-items: center;">
        <input type="text" id="product_name" class="form-control grn-input-custom" placeholder="Click Choose..." readonly style="border-top-right-radius: 0 !important; border-bottom-right-radius: 0 !important; border-right: 0 !important;">
        <button type="button" class="btn btn-primary btn-custom" onclick="openProductModal()" style="border-top-left-radius: 0 !important; border-bottom-left-radius: 0 !important; height: 32px !important; display: inline-flex; align-items: center;">Choose</button>
      </div>
    </div>

        <div style="width: 100px;">
          <label class="form-label-custom">Qty <span style="color:red;">*</span></label>
          <input id="qty" type="number" class="form-control grn-input-custom" oninput="calculateDiscount()" placeholder="0">
        </div>

        <div style="width: 100px;">
          <label class="form-label-custom">Free Qty</label>
          <input id="free_qty" type="number" class="form-control grn-input-custom" placeholder="0">
        </div>

   <div class="grn-btn-group">
  <button type="button" onclick="addItem()" class="btn btn-success grn-btn-custom">
    <i class="fa fa-plus"></i> Add Item
  </button>
  <button type="button" onclick="cancelEditItem()" class="btn btn-danger grn-btn-custom">
    Cancel
  </button>
  <button type="button" class="btn btn-info grn-btn-custom" onclick="$('#shippingModal').modal('show');">
    <i class="fa fa-truck"></i> Shipping
  </button>
</div>
      </div>

      <div class="flex-row-balanced" style="margin-top: 10px;">
        <div style="flex: 1;">
          <label class="form-label-custom">Rate</label>
          <input id="rate" type="number" step="any" min="0" class="form-control grn-input-custom" oninput="calculateDiscount()" placeholder="0.00">
        </div>
        <div style="width: 80px;">
          <label class="form-label-custom">Disc %</label>
          <input id="disc_percent" type="number" step="any" class="form-control grn-input-custom" oninput="calculateDiscount()" placeholder="%">
        </div>
        <div style="flex: 1;">
          <label class="form-label-custom">Disc Amt</label>
          <input id="discount" type="number" step="any" class="form-control grn-input-custom" oninput="calculateDiscountFromAmount()" placeholder="0.00">
        </div>
        <div style="width: 90px;">
          <label class="form-label-custom">Misc</label>
          <input id="misc" type="number" step="any" class="form-control grn-input-custom" placeholder="0">
        </div>
        <div style="flex: 1;">
          <label class="form-label-custom">MRP</label>
          <input id="mrp" type="number" step="any" min="0" class="form-control grn-input-custom" placeholder="MRP">
        </div>
        <div style="flex: 1.3;">
          <label class="form-label-custom">GST Rate</label>
          <select id="gst" class="form-control grn-input-custom">
            <option value="">Select GST</option>
            <?php foreach ($gst_list as $g) { ?>
              <option value="<?= $g['id']; ?>" data-gst="<?= $g['gst_percent']; ?>"><?= $g['gst_name']; ?> (<?= $g['gst_percent']; ?>%)</option>
            <?php } ?>
          </select>
        </div>
        <div style="flex: 1.2;">
          <label class="form-label-custom">GST Type</label>
          <select id="buy_type" class="form-control grn-input-custom">
            <option value="exclusive">Exclusive</option>
            <option value="inclusive">Inclusive</option>
          </select>
        </div>
        <div style="flex: 1.3;">
          <label class="form-label-custom">Net Amt</label>
          <input id="net_amount" type="number" class="form-control grn-input-custom" readonly placeholder="0.00">
        </div>
      </div>
    </div>

    <!-- SHIPPING SUMMARY -->
    <div id="shippingSummary" style="display:none; padding:6px 12px; background:#f8fafc; border:1px solid #cbd5e1; border-radius:4px; margin-bottom:10px; font-size:11px;"></div>

    <!-- MAIN FORM -->
    <form method="post" id="grnForm">
      <input type="hidden" name="round_off" id="round_off" value="0">
      <input type="hidden" name="charges_json" id="charges_json">
      <input type="hidden" name="items_json" id="items_json">
      <input type="hidden" name="payments_json" id="payments_json">
      <input type="hidden" name="used_advance" id="used_advance" value="<?= $edit_mode ? $edit_advance : 0; ?>">
      <?php if($edit_mode){ ?>
        <input type="hidden" name="old_bill_no" value="<?= $edit_bill; ?>">
      <?php } ?>

      <!-- ITEMS TABLE -->
      <div class="table-responsive-balanced">
        <table id="grnItemsTable" class="table table-bordered table-striped">
          <thead>
            <tr>
              <th width="35">#</th>
              <th>Product</th>
              <th width="80">HSN</th>
              <th width="100">Qty</th>
              <th width="80">Rate</th>
              <th width="90">Total Amt</th>
              <th width="65">Disc %</th>
              <th width="80">Disc Amt</th>
              <th width="90">Net Amt</th>
              <th width="60">GST %</th>
              <th width="80">GST Amt</th>
              <th width="95">Net Total</th>
              <th width="65">Action</th>
            </tr>
          </thead>
          <tbody id="itemBody"></tbody>
        </table>
      </div>

      <!-- HIDDEN CHARGES CONTAINER -->
      <div style="display:none;">
        <select id="charge_type"><option value="">Type</option></select>
        <input type="number" id="charge_amount">
        <select id="charge_gst_type"><option value="EXCLUSIVE">Exclusive</option></select>
        <select id="charge_gst_id"><option value="">GST</option></select>
      </div>
      <div id="chargeBody" style="display:none;"></div>

      <!-- BOTTOM SECTION: ADVANCE, COMMENTS & PAYMENTS -->
      <div class="bottom-layout-balanced">
        
        <!-- LEFT: ADVANCE & COMMENTS -->
        <div class="bottom-left-balanced">
          <!-- ADVANCE BOX -->
          <div class="alert alert-info" id="advanceSection" style="display:none; padding:8px 12px; font-size:11px; margin-bottom:10px; border-radius:4px;">
            <strong>Available Advance: ₹ <span id="advanceAmount">0.00</span></strong> | 
            <strong style="color:#0369a1;">Balance Advance: ₹ <span id="balanceAdvance">0.00</span></strong>
            <span id="useAdvanceWrapper" style="margin-left:12px;">
              <label style="margin:0; font-weight:600;"><input type="checkbox" id="useAdvance" <?= ($edit_mode && $edit_advance > 0) ? 'checked' : ''; ?>> Use Advance</label>
            </span>
            <input type="number" id="advanceInput" class="form-control grn-input-custom" style="margin-top:6px; display:<?= ($edit_mode && $edit_advance > 0) ? 'block' : 'none'; ?>; width:160px;" placeholder="Enter Advance" min="0" step="any" value="<?= $edit_mode ? $edit_advance : ''; ?>">
          </div>

          <!-- COMMENTS -->
          <div>
            <label class="form-label-custom">Comments / Remarks</label>
            <textarea name="comments" class="form-control" placeholder="Enter Remarks / Comments..." rows="3" style="font-size:12px; border-radius:4px; resize:none; border:1px solid #cbd5e1; padding:6px 8px;"><?= $edit_mode ? $grn_info['comments'] : ''; ?></textarea>
          </div>
        </div>

        <!-- RIGHT: PAYMENTS & TOTAL -->
        <div class="bottom-right-balanced">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
            <strong style="font-size:12px; color:#1e293b; text-transform:uppercase;">Payment Modes</strong>
            <div style="display:flex; align-items:center; gap:10px;">
              <label style="margin:0; font-size:11px; font-weight:600; cursor:pointer;">
                <input type="checkbox" id="roundOffToggle" onchange="updateGrandTotal()"> Round Off
              </label>
              <div style="font-size:14px; font-weight:800; color:#0f172a;">
                Total: ₹ <span id="grandTotal">0.00</span>
              </div>
            </div>
          </div>

          <!-- PAYMENT CHECKBOXES -->
          <div class="payment-box-balanced">
            <?php foreach($payment_modes as $pm){ ?>
              <div class="pay-row-balanced">
                <div style="display:flex; align-items:center; gap:6px;">
                  <input type="checkbox" class="pay-check" onchange="togglePaymentInput(this)" value="<?= $pm['mode_name']; ?>" id="pay_<?= $pm['id']; ?>">
                  <label for="pay_<?= $pm['id']; ?>" style="margin:0; font-size:11px; font-weight:600; text-transform:uppercase; cursor:pointer;">
                    <?= $pm['mode_name']; ?>
                  </label>
                </div>
                <div style="display:flex; gap:6px;">
                  <input type="number" step="any" min="0" class="form-control grn-input-custom pay-amount" style="display:none; width:85px;" data-mode="<?= $pm['mode_name']; ?>" placeholder="Amount">
                  <input type="text" class="form-control grn-input-custom pay-utr" style="display:none; width:95px;" data-mode="<?= $pm['mode_name']; ?>" placeholder="UTR No">
                </div>
              </div>
            <?php } ?>
          </div>

          <!-- SUBMIT BUTTON -->
          <div style="margin-top:10px; text-align:right;">
            <?php if($edit_mode){ ?>
              <button name="update_grn" class="btn btn-primary btn-custom" style="padding:0 24px !important; font-size:13px !important;">Update GRN</button>
            <?php } else { ?>
              <button name="save_grn" class="btn btn-success btn-custom" style="padding:0 24px !important; font-size:13px !important;">Create GRN</button>
            <?php } ?>
          </div>

        </div>

      </div>

    </form>

  </div>
</div>

<!-- ================= SHIPPING MODAL ================= -->
<div class="modal fade" id="shippingModal" data-backdrop="static" data-keyboard="false" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 450px; margin: 50px auto;">
    <div class="modal-content" style="border-radius: 10px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.2); overflow: hidden;">
      
      <div class="modal-header" style="background: #0B1736; color: #ffffff; padding: 12px 20px; border: none; display: flex; align-items: center; justify-content: space-between;">
        <h4 class="modal-title" style="font-size: 15px; font-weight: 600; margin: 0; color: #fff;">
          <i class="fa fa-truck" style="margin-right: 8px;"></i> Add Shipping Charge
        </h4>
        <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: 0.8; font-size: 20px; outline: none; border: none; background: transparent; cursor: pointer;">&times;</button>
      </div>

      <div class="modal-body" style="padding: 20px; background-color: #fcfcfc;">
        <div class="form-group" style="margin-bottom: 15px;">
          <label style="font-size: 11px; font-weight: 600; color: #444; margin-bottom: 4px; display: block;">
            Shipping Type <span style="color:red;">*</span>
          </label>
          <div style="display: flex; gap: 6px;">
            <select id="modal_charge_type" class="form-control" style="height: 32px; font-size: 12px; border-radius: 4px;">
              <option value="">Select Type</option>
              <?php foreach($shipping_types as $st){ ?>
                <option value="<?= $st['id']; ?>"><?= $st['type_name']; ?></option>
              <?php } ?>
            </select>
            <button type="button" class="btn btn-default" onclick="refreshShippingTypesModal()" title="Refresh Shipping Types" style="height: 32px; padding: 0 10px; border-radius: 4px; border: 1px solid #ccc; background: #fff;">
              <i class="fa fa-refresh" id="refreshIcon"></i>
            </button>
          </div>
        </div>

        <div class="form-group" style="margin-bottom: 15px;">
          <label style="font-size: 11px; font-weight: 600; color: #444; margin-bottom: 4px; display: block;">
            Amount (₹) <span style="color:red;">*</span>
          </label>
          <input type="number" step="any" min="0" id="modal_charge_amount" class="form-control" placeholder="0.00" style="height: 32px; font-size: 12px; border-radius: 4px;">
        </div>

        <div class="row" style="margin-left: -5px; margin-right: -5px;">
          <div class="col-xs-6" style="padding-left: 5px; padding-right: 5px;">
            <div class="form-group" style="margin-bottom: 10px;">
              <label style="font-size: 11px; font-weight: 600; color: #444; margin-bottom: 4px; display: block;">GST Type</label>
              <select id="modal_charge_gst_type" class="form-control" style="height: 32px; font-size: 12px; border-radius: 4px;">
                <option value="EXCLUSIVE">Exclusive</option>
                <option value="INCLUSIVE">Inclusive</option>
              </select>
            </div>
          </div>
          <div class="col-xs-6" style="padding-left: 5px; padding-right: 5px;">
            <div class="form-group" style="margin-bottom: 10px;">
              <label style="font-size: 11px; font-weight: 600; color: #444; margin-bottom: 4px; display: block;">GST Percent</label>
              <select id="modal_charge_gst_id" class="form-control" style="height: 32px; font-size: 12px; border-radius: 4px;">
                <option value="">Select GST</option>
                <?php foreach ($gst_list as $g) { ?>
                  <option value="<?= $g['id']; ?>" data-gst="<?= $g['gst_percent']; ?>">
                    <?= $g['gst_name']; ?> (<?= $g['gst_percent']; ?>%)
                  </option>
                <?php } ?>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer" style="padding: 10px 20px; background: #f1f3f5; border-top: 1px solid #e9ecef; display: flex; justify-content: flex-end; gap: 8px;">
        <button type="button" class="btn btn-default btn-sm" data-dismiss="modal" style="height: 30px; font-size: 12px; border-radius: 4px;">Cancel</button>
        <button type="button" class="btn btn-success btn-sm" onclick="saveShippingModal()" style="height: 30px; font-size: 12px; font-weight: 600; border-radius: 4px; padding: 0 15px;">
          <i class="fa fa-check" style="margin-right: 4px;"></i> Add Charge
        </button>
      </div>

    </div>
  </div>
</div>

<!-- PRODUCT SEARCH MODAL -->
<div class="modal fade" id="productModal">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header" style="background:#0B1736; color:#fff; padding:12px 18px;">
        <button type="button" class="close" data-dismiss="modal" style="color:#fff;">&times;</button>
        <h4 class="modal-title" style="font-size:15px; color:#fff;">Select Product</h4>
      </div>
      <div class="modal-body" style="padding:15px;">
        <input type="text" id="searchProduct" class="form-control grn-input-custom" placeholder="Search product name or HSN..." autofocus style="margin-bottom:12px;">
        <div style="max-height:320px; overflow-y:auto; border:1px solid #cbd5e1; border-radius:4px;">
          <table class="table table-bordered table-hover" style="margin:0; font-size:12px;">
            <thead>
              <tr style="background:#f1f5f9;">
                <th width="10%">#</th>
                <th width="70%">Product Name</th>
                <th width="20%">HSN Code</th>
              </tr>
            </thead>
            <tbody id="productTable">
              <?php $i=1; foreach($products as $p){ ?>
                <tr onclick="selectProduct('<?= $p['id']; ?>', '<?= addslashes($p['name']); ?>', '<?= $p['hsn_code']; ?>')" style="cursor:pointer;">
                  <td><?= $i++; ?></td>
                  <td><b><?= $p['name']; ?></b></td>
                  <td><?= $p['hsn_code']; ?></td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- JAVASCRIPT LOGIC -->
<script>
if(typeof items === 'undefined'){ var items = []; }
if(typeof charges === 'undefined'){ var charges = []; }

let sno = items.length + 1;
let payments = [];
let backupItem = null;

<?php if($edit_mode){ ?>
  items = <?= json_encode($edit_items); ?>;
  items.forEach((it,index)=>{
    it.sno = index + 1;
    it.qty = parseFloat(it.quantity || 0);
    it.free_qty = parseFloat(it.free_qty || 0);
    it.rate = parseFloat(it.unit_price || 0);
    it.discount = parseFloat(it.discount_amount || 0);
    it.misc = parseFloat(it.misc_amount || 0);
    it.mrp = parseFloat(it.mrp || 0);
    it.gst_id = parseInt(it.gst_id || 0);
    it.gst_percent = parseFloat(it.gst_percent || 0);
    it.gst_amount = parseFloat(it.gst_amount || 0);
    it.base_amount = parseFloat(it.net_price || 0) - parseFloat(it.gst_amount || 0);
    it.total = parseFloat(it.net_price || 0);
  });

  charges = <?= json_encode($edit_charges ?? []); ?>;
  charges.forEach(c => {
    c.shipping_type_id = parseInt(c.shipping_type_id || 0);
    c.gst_id = parseInt(c.gst_id || 0);
    c.amount = parseFloat(c.amount || 0);
    c.gst_percent = parseFloat(c.gst_percent || 0);
    c.gst_amount = parseFloat(c.gst_amount || 0);
    c.total = parseFloat(c.total || c.total_amount || 0);
    c.gst_type = c.gst_type || "EXCLUSIVE";
    c.type_name = c.type_name || "Shipping";
  });

  let editPayments = <?= json_encode($edit_payments); ?>;
  let editBillAmount = <?= (float)($edit_ledger['bill_amount'] ?? 0); ?>;

  document.addEventListener("DOMContentLoaded", function(){
    renderItems();
    renderCharges();

    document.getElementById("items_json").value = JSON.stringify(items);
    document.getElementById("charges_json").value = JSON.stringify(charges);

    // AUTO-CHECK ROUND OFF IF SAVED AMOUNT WAS WHOLE NUMBER
    if(editBillAmount > 0 && Number.isInteger(editBillAmount)){
      document.getElementById("roundOffToggle").checked = true;
      updateGrandTotal();
    }

    // AUTO-POPULATE PAYMENT MODES AND UTR NUMBERS
    editPayments.forEach(p => {
      let mode = p.payment_mode;
      if(mode.toUpperCase() !== 'ADVANCE'){
        let checkbox = document.querySelector('.pay-check[value="' + mode + '"]');
        let amountInput = document.querySelector('.pay-amount[data-mode="' + mode + '"]');
        let utrInput = document.querySelector('.pay-utr[data-mode="' + mode + '"]');

        if(checkbox){
          checkbox.checked = true;
          togglePaymentInput(checkbox);
        }
        if(amountInput){
          amountInput.value = parseFloat(p.payment_amount).toFixed(2);
        }
        if(utrInput && p.reference_no && mode.toLowerCase() !== 'cash'){
          utrInput.value = p.reference_no;
        }
      }
    });
  });
<?php } ?>

function collectPayments(){
  payments = [];
  document.querySelectorAll(".pay-check").forEach(check => {
    if(check.checked){
      let mode = check.value;
      let amountInput = document.querySelector('.pay-amount[data-mode="' + mode + '"]');
      let amount = parseFloat(amountInput.value) || 0;
      let utrInput = document.querySelector('.pay-utr[data-mode="' + mode + '"]');
      let utr = utrInput ? utrInput.value : '';

      if(amount > 0){
        payments.push({ mode: mode, amount: amount, utr: utr });
      }
    }
  });
  document.getElementById("payments_json").value = JSON.stringify(payments);
}

function calculateDiscount(){
  let qty = parseFloat(document.getElementById("qty").value) || 0;
  let rate = parseFloat(document.getElementById("rate").value) || 0;
  let discPercent = parseFloat(document.getElementById("disc_percent").value) || 0;
  let totalAmt = qty * rate;
  let discAmt = (totalAmt * discPercent) / 100;
  let netAmt = totalAmt - discAmt;

  document.getElementById("discount").value = Math.round(discAmt);
  document.getElementById("net_amount").value = Math.round(netAmt);
}

function calculateDiscountFromAmount(){
  let qty = parseFloat(document.getElementById("qty").value) || 0;
  let rate = parseFloat(document.getElementById("rate").value) || 0;
  let totalAmt = qty * rate;
  let discAmt = parseFloat(document.getElementById("discount").value) || 0;
  let discPercent = (totalAmt > 0) ? (discAmt * 100) / totalAmt : 0;

  document.getElementById("disc_percent").value = Math.round(discPercent);
  document.getElementById("net_amount").value = Math.round(totalAmt - discAmt);
}

function addItem() {
  let pid = document.getElementById("product").value;
  let pname = document.getElementById("product_name").value;
  let qty   = parseFloat(document.getElementById("qty").value) || 0;
  let free  = parseFloat(document.getElementById("free_qty").value) || 0;
  let rate  = parseFloat(document.getElementById("rate").value) || 0;
  let disc_percent = parseFloat(document.getElementById("disc_percent").value) || 0;
  let disc  = parseFloat(document.getElementById("discount").value) || 0;
  let misc  = parseFloat(document.getElementById("misc").value) || 0;
  let mrp   = parseFloat(document.getElementById("mrp").value) || 0;

  let gstSel = document.getElementById("gst");
  let gst_id = gstSel.value;
  let gstp   = parseFloat(gstSel.options[gstSel.selectedIndex]?.dataset.gst || 0);

  let errors = [];
  if(document.getElementById("supplier_id").value == ""){ errors.push("Supplier"); }
  if(document.querySelector('[name="bill_no"]').value.trim() == ""){ errors.push("Bill / GRN No"); }
  if(!pid){ errors.push("Product"); }
  if(qty <= 0){ errors.push("Quantity"); }
  if(rate < 0){ errors.push("Rate"); }
  if(!gst_id){ errors.push("GST"); }
  if(mrp < 0){ errors.push("MRP"); }

  if(errors.length > 0){
    Swal.fire({ icon: 'warning', title: 'Required Fields', html: errors.join("<br>") });
    return;
  }

  let buyType = document.getElementById("buy_type").value;
  let total_amt = qty * rate;
  if(disc_percent > 0){ disc = Math.round((total_amt * disc_percent) / 100); }

  let base = 0, gst = 0, total = 0;

  if (buyType === "inclusive") {
    total = (qty * rate) - disc + misc;
    base = total / (1 + (gstp / 100));
    gst = total - base;
  } else {
    base = (qty * rate) - disc + misc;
    gst = (base * gstp) / 100;
    total = base + gst;
  }

  backupItem = null;

  items.push({
    sno: sno++, product_id: pid, name: pname,
    hsn_code: document.getElementById("hsn_code").value,
    qty: qty, free_qty: free, rate: rate, gst_id: gst_id, gst_percent: gstp,
    buy_type: buyType, base_amount: base, gst_amount: gst,
    disc_percent: disc_percent, discount: disc, misc: misc, mrp: mrp, total: total
  });

  renderItems();

  document.getElementById("product").value = "";
  document.getElementById("product_name").value = "";
  document.getElementById("hsn_code").value = "";
  document.getElementById("qty").value = "";
  document.getElementById("free_qty").value = "";
  document.getElementById("rate").value = "";
  document.getElementById("discount").value = "";
  document.getElementById("disc_percent").value = "";
  document.getElementById("misc").value = "";
  document.getElementById("mrp").value = "";
  document.getElementById("gst").value = "";
  document.getElementById("net_amount").value = "";
  document.getElementById("previousRateBox").style.display = "none";
  document.getElementById("previousRateBox").innerHTML = "";
}

function renderItems() {
  let tb = document.getElementById("itemBody");
  tb.innerHTML = "";

  items.forEach((it, i) => {
    it.sno = i + 1;
    tb.innerHTML += `
    <tr>
      <td>${it.sno}</td>
      <td><b>${it.name}</b></td>
      <td>${it.hsn_code || '-'}</td>
      <td>${parseFloat(it.qty).toFixed(2)} (+${parseFloat(it.free_qty).toFixed(2)})</td>
      <td>${it.rate}</td>
      <td>${(it.qty * it.rate).toFixed(2)}</td>
      <td>${it.disc_percent || 0}%</td>
      <td>${it.discount.toFixed(2)}</td>
      <td>${it.base_amount.toFixed(2)}</td>
      <td>${it.gst_percent}%</td>
      <td>${it.gst_amount.toFixed(2)}</td>
      <td><b>${it.total.toFixed(2)}</b></td>
      <td>
        <button type="button" onclick="editItem(${i})" class="btn btn-xs btn-info"><i class="fa fa-pencil"></i></button>
        <button type="button" onclick="items.splice(${i},1);renderItems()" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i></button>
      </td>
    </tr>`;
  });

  updateGrandTotal();
}

function updateGrandTotal() {
  let itemTotal = 0;
  let chargeTotal = 0;

  items.forEach(it => itemTotal += it.total);
  charges.forEach(c => chargeTotal += c.total);

  let finalTotal = itemTotal + chargeTotal;
  let roundChecked = document.getElementById("roundOffToggle").checked;

  document.getElementById("round_off").value = roundChecked ? 1 : 0;
  if(roundChecked){ finalTotal = Math.round(finalTotal); }

  document.getElementById("grandTotal").innerText = finalTotal.toFixed(2);
  document.getElementById("items_json").value = JSON.stringify(items);
  document.getElementById("charges_json").value = JSON.stringify(charges);

  let checkedBoxes = document.querySelectorAll(".pay-check:checked");
  if(checkedBoxes.length == 1){
    checkedBoxes.forEach(ch => {
      let mode = ch.value;
      let input = document.querySelector('.pay-amount[data-mode="' + mode + '"]');
      if(input && !input.value){
        let advanceUsed = parseFloat(document.getElementById("used_advance").value) || 0;
        let payable = Math.max(0, finalTotal - advanceUsed);
        input.value = roundChecked ? Math.round(payable) : payable.toFixed(2);
      }
    });
  }
}

function saveShippingModal(){
    let amountVal = document.getElementById("modal_charge_amount").value;
    let typeVal = document.getElementById("modal_charge_type").value;

    if(!typeVal || !amountVal || parseFloat(amountVal) <= 0){
        Swal.fire({ icon: 'warning', title: 'Incomplete Details', text: 'Please select Shipping Type and enter a valid Amount.' });
        return;
    }

    document.getElementById("charge_type").value = typeVal;
    document.getElementById("charge_amount").value = amountVal;
    document.getElementById("charge_gst_type").value = document.getElementById("modal_charge_gst_type").value;
    document.getElementById("charge_gst_id").value = document.getElementById("modal_charge_gst_id").value;

    addCharge();

    document.getElementById("modal_charge_type").value = "";
    document.getElementById("modal_charge_amount").value = "";
    document.getElementById("modal_charge_gst_id").value = "";
    document.getElementById("modal_charge_gst_type").value = "EXCLUSIVE";

    $('#shippingModal').modal('hide');
}

function refreshShippingTypesModal() {
    let icon = $("#refreshIcon");
    icon.addClass("fa-spin");

    $.ajax({
        url: "get_shipping_types.php",
        type: "GET",
        dataType: "json",
        success: function(data) {
            let html = '<option value="">Select Type</option>';
            data.forEach(function(row) {
                html += '<option value="' + row.id + '">' + row.type_name + '</option>';
            });

            $("#modal_charge_type").html(html);
            icon.removeClass("fa-spin");
        },
        error: function() {
            icon.removeClass("fa-spin");
            Swal.fire({ icon: 'error', title: 'Error', text: 'Unable to refresh shipping types.' });
        }
    });
}

function addCharge() {
  let select = document.getElementById("charge_type");
  let shipping_type_id = select.value;
  let type_name = select.options[select.selectedIndex] ? select.options[select.selectedIndex].text : '';
  let amount = parseFloat(document.getElementById("charge_amount").value) || 0;
  let gstSelect = document.getElementById("charge_gst_id");
  let gst_id = gstSelect.value;
  let gst_percent = parseFloat(gstSelect.options[gstSelect.selectedIndex]?.dataset.gst || 0);
  let gst_type = document.getElementById("charge_gst_type").value;

  if (amount <= 0) {
    Swal.fire({ icon: 'warning', title: 'Invalid Amount', text: 'Please enter a valid amount.' });
    return;
  }

  let taxable, gst_amount, total;
  if (gst_type === "EXCLUSIVE") {
    taxable = amount;
    gst_amount = taxable * gst_percent / 100;
    total = taxable + gst_amount;
  } else {
    taxable = amount / (1 + gst_percent / 100);
    gst_amount = amount - taxable;
    total = amount;
  }

  charges.push({ shipping_type_id, type_name, gst_id, amount, gst_percent, gst_type, gst_amount, total });
  renderCharges();

  document.getElementById("charge_type").value = "";
  document.getElementById("charge_amount").value = "";
  document.getElementById("charge_gst_id").value = "";
  document.getElementById("charge_gst_type").value = "EXCLUSIVE";
}

function renderCharges() {
  let summary = document.getElementById("shippingSummary");
  if(charges.length == 0){
    summary.style.display = "none";
    summary.innerHTML = "";
    updateGrandTotal();
    return;
  }

  summary.style.display = "block";
  summary.innerHTML = "<strong>Shipping Charges:</strong> ";

  charges.forEach((c, i) => {
    summary.innerHTML += `
      <span style="display:inline-flex; align-items:center; gap:6px; background:#fff; border:1px solid #cbd5e1; padding:3px 8px; border-radius:4px; margin-right:6px;">
        <b>${c.type_name || 'Shipping'}:</b> ₹${c.total.toFixed(2)} (${c.gst_type})
        <button type="button" onclick="editCharge(${i})" class="btn btn-xs btn-info" style="padding:0 4px;"><i class="fa fa-pencil"></i></button>
        <button type="button" onclick="charges.splice(${i},1);renderCharges()" class="btn btn-xs btn-danger" style="padding:0 4px;"><i class="fa fa-trash"></i></button>
      </span>`;
  });

  updateGrandTotal();
}

function selectProduct(id, name, hsn) {
  document.getElementById("product").value = id;
  document.getElementById("product_name").value = name;
  document.getElementById("hsn_code").value = hsn;

  $('#searchProduct').val('');
  $("#productTable tr").show();
  $('#productModal').modal('hide');

  setTimeout(function () {
    document.getElementById("qty").focus();
    document.getElementById("qty").select();
  }, 150);

  fetch("get_product_rate.php?product_id=" + id)
    .then(res => res.json())
    .then(data => {
      if (!data || Object.keys(data).length === 0) return;
      if (data.rate !== undefined) document.getElementById("rate").value = data.rate;
      if (data.mrp !== undefined) document.getElementById("mrp").value = data.mrp;
      if (data.gst_id) document.getElementById("gst").value = data.gst_id;
      if (data.buy_type) document.getElementById("buy_type").value = data.buy_type;

      if (data.last_rate !== undefined) {
        let box = document.getElementById("previousRateBox");
        box.style.display = "block";
        box.innerHTML = `<strong>Last Purchase:</strong> Rate (Inc GST): <b>₹${data.last_rate}</b> | GST: <b>${data.gst_percent}%</b> | MRP: <b>₹${data.mrp}</b> | Date: <b>${data.price_date}</b>`;
      }
    })
    .catch(err => console.log("JSON Error:", err));
}

document.addEventListener("DOMContentLoaded", function () {
  let searchBox = document.getElementById("searchProduct");
  if (searchBox) {
    searchBox.addEventListener("keyup", function () {
      let value = this.value.toLowerCase();
      let rows = document.querySelectorAll("#productTable tr");
      rows.forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(value) ? "" : "none";
      });
    });
  }
});

document.getElementById("grnForm").addEventListener("submit", function(e){
  updateGrandTotal();
  document.getElementById("round_off").value = document.getElementById("roundOffToggle").checked ? 1 : 0;
  collectPayments();

  let supplier = document.querySelector('[name="supplier_id"]').value;
  let billNo   = document.querySelector('[name="bill_no"]').value.trim();
  let billDate = document.querySelector('[name="bill_date"]').value;
  let errors   = [];

  if(supplier == '') errors.push("Supplier is required.");
  if(billNo == '') errors.push("Bill / GRN No is required.");
  if(billDate == '') errors.push("Bill Date is required.");
  if(items.length == 0) errors.push("Please add at least one item.");

  if(errors.length > 0){
    e.preventDefault();
    Swal.fire({ icon: 'warning', title: 'Validation Error', html: errors.join("<br>") });
    return false;
  }

  let warningMessages = [];
  let enteredPaymentTotal = 0;

  document.querySelectorAll(".pay-check").forEach(check => {
    if(check.checked){
      let mode = check.value;
      let amountInput = document.querySelector('.pay-amount[data-mode="' + mode + '"]');
      let utrInput = document.querySelector('.pay-utr[data-mode="' + mode + '"]');
      let amount = parseFloat(amountInput.value) || 0;
      enteredPaymentTotal += amount;
      let utr = utrInput ? utrInput.value.trim() : '';

      if(amount <= 0) warningMessages.push(mode + " selected but amount not entered.");
      if(mode.toLowerCase() != 'cash' && amount > 0 && utr == '') warningMessages.push(mode + " selected but UTR No not entered.");
    }
  });

  let grandTotal = parseFloat(document.getElementById("grandTotal").innerText.replace(/,/g,'')) || 0;
  if(enteredPaymentTotal > Math.ceil(grandTotal)){
    e.preventDefault();
    Swal.fire({ icon: 'error', title: 'Invalid Payment', text: 'Payment amount cannot be greater than Grand Total.' });
    return false;
  }

  if(warningMessages.length > 0){
    e.preventDefault();
    Swal.fire({
      title: 'Continue?',
      html: warningMessages.join("<br>") + "<br><br>Do you still want to continue?",
      icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes, Continue', cancelButtonText: 'Cancel'
    }).then((result) => {
      if(result.isConfirmed){
        document.getElementById("grnForm").submit();
      }
    });
  }
});

function togglePaymentInput(check){
  let mode = check.value;
  let input = document.querySelector('.pay-amount[data-mode="' + mode + '"]');
  let utrInput = document.querySelector('.pay-utr[data-mode="' + mode + '"]');
  let total = parseFloat(document.getElementById("grandTotal").innerText.replace(/,/g,'')) || 0;
  let checkedBoxes = document.querySelectorAll(".pay-check:checked");

  if(check.checked){
    input.style.display = "block";
    if(mode.toLowerCase() != 'cash'){
      utrInput.disabled = false;
      utrInput.style.display = "block";
      utrInput.required = true;
    } else {
      utrInput.required = false;
      utrInput.disabled = true;
      utrInput.style.display = "none";
      utrInput.value = '';
    }

    if(checkedBoxes.length == 1 && !input.value){
      let advanceUsed = parseFloat(document.getElementById("used_advance").value) || 0;
      let payable = Math.max(0, total - advanceUsed);
      let roundChecked = document.getElementById("roundOffToggle").checked;
      input.value = roundChecked ? Math.round(payable) : payable.toFixed(2);
    }
    input.focus();
  } else {
    input.style.display = "none"; input.value = "";
    utrInput.style.display = "none"; utrInput.value = "";
  }
}

function editItem(index){
  let it = items[index];
  backupItem = {...it};

  document.getElementById("product").value = it.product_id;
  document.getElementById("product_name").value = it.name;
  document.getElementById("hsn_code").value = it.hsn_code;
  document.getElementById("qty").value = it.qty;
  document.getElementById("free_qty").value = it.free_qty;
  document.getElementById("rate").value = it.rate;
  document.getElementById("disc_percent").value = it.disc_percent || 0;
  document.getElementById("discount").value = it.discount;
  document.getElementById("misc").value = it.misc;
  document.getElementById("mrp").value = it.mrp;
  document.getElementById("gst").value = it.gst_id;
  document.getElementById("buy_type").value = it.buy_type;

  items.splice(index,1);
  renderItems();
}

function cancelEditItem(){
  if(backupItem){ items.push(backupItem); backupItem = null; renderItems(); }
  document.getElementById("product").value = "";
  document.getElementById("product_name").value = "";
  document.getElementById("hsn_code").value = "";
  document.getElementById("qty").value = "";
  document.getElementById("free_qty").value = "";
  document.getElementById("rate").value = "";
  document.getElementById("disc_percent").value = "";
  document.getElementById("discount").value = "";
  document.getElementById("net_amount").value = "";
  document.getElementById("misc").value = "";
  document.getElementById("mrp").value = "";
  document.getElementById("gst").value = "";
  document.getElementById("buy_type").value = "exclusive";
  document.getElementById("previousRateBox").style.display = "none";
  document.getElementById("previousRateBox").innerHTML = "";
}

function loadSupplierAdvance(){
  let supplier = document.getElementById("supplier_id");
  if(!supplier) return;
  let supplier_id = supplier.value;

  if(!supplier_id){
    document.getElementById("advanceAmount").innerText = "0.00";
    document.getElementById("advanceSection").style.display = "block";
    document.getElementById("useAdvanceWrapper").style.display = "none";
    return;
  }

  fetch("get_supplier_advance.php?supplier_id=" + supplier_id)
  .then(res => res.json())
  .then(data => {
    let adv = parseFloat(data.advance || 0);
    document.getElementById("advanceAmount").innerText = adv.toFixed(2);
    document.getElementById("advanceSection").style.display = "block";

    if(adv > 0 || <?= $edit_mode ? 'true' : 'false'; ?>){
      document.getElementById("useAdvanceWrapper").style.display = "block";
    } else {
      document.getElementById("useAdvanceWrapper").style.display = "none";
      document.getElementById("useAdvance").checked = false;
      document.getElementById("advanceInput").style.display = "none";
      document.getElementById("advanceInput").value = "";
      document.getElementById("used_advance").value = 0;
    }
  });
}

loadSupplierAdvance();
document.getElementById("supplier_id").addEventListener("change", loadSupplierAdvance);

let useAdvance = document.getElementById("useAdvance");
if(useAdvance){
  document.getElementById("advanceInput").addEventListener("input", function(){
    let available = parseFloat(document.getElementById("advanceAmount").innerText.replace(/,/g,'')) || 0;
    let total = parseFloat(document.getElementById("grandTotal").innerText.replace(/,/g,'')) || 0;
    let entered = parseFloat(this.value) || 0;

    if (entered > (available + <?= $edit_mode ? $edit_advance : 0; ?>)) {
      entered = available + <?= $edit_mode ? $edit_advance : 0; ?>;
    }
    if (entered > total) entered = total;

    this.value = entered.toFixed(2);
    document.getElementById("used_advance").value = entered;
    document.getElementById("balanceAdvance").innerText = (available - entered).toFixed(2);
    updateGrandTotal();
  });

  useAdvance.addEventListener("change", function(){
    let available = parseFloat(document.getElementById("advanceAmount").innerText.replace(/,/g,'')) || 0;
    let total = parseFloat(document.getElementById("grandTotal").innerText.replace(/,/g,'')) || 0;

    if(this.checked){
      let amount = Math.min(available + <?= $edit_mode ? $edit_advance : 0; ?>, total);
      document.getElementById("advanceInput").style.display = "block";
      document.getElementById("advanceInput").value = amount.toFixed(2);
      document.getElementById("used_advance").value = amount;
      document.getElementById("balanceAdvance").innerText = (available - amount).toFixed(2);
    } else {
      document.getElementById("advanceInput").style.display = "none";
      document.getElementById("advanceInput").value = "";
      document.getElementById("used_advance").value = 0;
      document.getElementById("balanceAdvance").innerText = available.toFixed(2);
    }
    updateGrandTotal();
  });
}

// 1. फिलहाल एडिट हो रहे charge के इंडेक्स को ट्रैक करने के लिए वैरिएबल
let editingChargeIndex = null;

// 2. Edit Button Function (अब यह डेटा तुरंत डिलीट नहीं करेगा)
function editCharge(index){
  editingChargeIndex = index; // ट्रैक करें कि कौन सा आइटम एडिट हो रहा है
  let c = charges[index];
  
  // Modal के Inputs में पुराना डेटा भरें
  document.getElementById("modal_charge_type").value = c.shipping_type_id;
  document.getElementById("modal_charge_amount").value = c.amount;
  document.getElementById("modal_charge_gst_id").value = c.gst_id || "";
  document.getElementById("modal_charge_gst_type").value = c.gst_type ? c.gst_type.toUpperCase() : "EXCLUSIVE";

  // Pop-up Modal Open करें
  $('#shippingModal').modal('show');
}

// 3. Modal Save Function (नया ऐड करेगा या पुराने को अपडेट करेगा)
function saveShippingModal(){
  let amountVal = document.getElementById("modal_charge_amount").value;
  let typeVal = document.getElementById("modal_charge_type").value;

  if(!typeVal || !amountVal || parseFloat(amountVal) <= 0){
      Swal.fire({ icon: 'warning', title: 'Incomplete Details', text: 'Please select Shipping Type and enter a valid Amount.' });
      return;
  }

  let select = document.getElementById("modal_charge_type");
  let type_name = select.options[select.selectedIndex] ? select.options[select.selectedIndex].text : 'Shipping';
  
  let gstSelect = document.getElementById("modal_charge_gst_id");
  let gst_id = gstSelect.value;
  let gst_percent = parseFloat(gstSelect.options[gstSelect.selectedIndex]?.dataset.gst || 0);
  let gst_type = document.getElementById("modal_charge_gst_type").value;
  let amount = parseFloat(amountVal) || 0;

  let taxable = 0, gst_amount = 0, total = 0;
  if (gst_type === "EXCLUSIVE") {
    taxable = amount;
    gst_amount = taxable * gst_percent / 100;
    total = taxable + gst_amount;
  } else {
    taxable = amount / (1 + gst_percent / 100);
    gst_amount = amount - taxable;
    total = amount;
  }

  let updatedCharge = { 
    shipping_type_id: parseInt(typeVal), 
    type_name: type_name, 
    gst_id: gst_id ? parseInt(gst_id) : '', 
    amount: amount, 
    gst_percent: gst_percent, 
    gst_type: gst_type, 
    gst_amount: gst_amount, 
    total: total 
  };

  // अगर एडिट हो रहा था तो पुरानी जगह रिप्लेस करें, वरना नया आइटम ऐड करें
  if (editingChargeIndex !== null) {
    charges[editingChargeIndex] = updatedCharge;
    editingChargeIndex = null; // रीसेट करें
  } else {
    charges.push(updatedCharge);
  }

  renderCharges();
  resetShippingModalInputs();
  $('#shippingModal').modal('hide');
}

// 4. Modal Inuts Reset करने का हेल्पर फंक्शन
function resetShippingModalInputs(){
  document.getElementById("modal_charge_type").value = "";
  document.getElementById("modal_charge_amount").value = "";
  document.getElementById("modal_charge_gst_id").value = "";
  document.getElementById("modal_charge_gst_type").value = "EXCLUSIVE";
  editingChargeIndex = null;
}

// 5. जब भी Modal Cancel या Close (X) हो, तो ट्रैकर रीसेट कर दें
$('#shippingModal').on('hidden.bs.modal', function () {
  resetShippingModalInputs();
});

function openProductModal(){
  $('#productModal').modal('show');
  setTimeout(function(){
    $('#searchProduct').val('');
    $("#productTable tr").show();
    document.getElementById("searchProduct").focus();
  },300);
}
</script>

<?php include_once('layouts/footer.php'); ?>
