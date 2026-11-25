<?php
$page_title = 'Purchase Order (PO)';
require_once('includes/load.php');

$products = find_by_sql("SELECT * FROM products WHERE is_active = 1 ORDER BY name");

$suppliers = find_by_sql("
    SELECT sm.*, gsm.state_name 
    FROM supplier_master sm
    LEFT JOIN gst_state_master gsm ON gsm.id = sm.state_id
    ORDER BY sm.supplier_name ASC
");

$gst_list = find_by_sql("
    SELECT id, gst_name, gst_percent
    FROM gst_master
    WHERE status = 1
    ORDER BY gst_percent
");

/* ===== FETCH ORGANIZATIONS & PARSE JSON ADDRESSES ===== */
$all_orgs = find_by_sql("SELECT * FROM organization_master ORDER BY org_name ASC");
$org_address_list = [];

foreach ($all_orgs as $o) {
    $addr_raw  = $o['address'] ?? '';
    $decoded   = json_decode($addr_raw, true);
    $addresses = is_array($decoded) ? $decoded : [$addr_raw];

    foreach ($addresses as $idx => $addr) {
        $addr_text  = is_array($addr) ? ($addr['text'] ?? '') : (string)$addr;
        $addr_state = is_array($addr) ? ($addr['state'] ?? ($o['state'] ?? '')) : ($o['state'] ?? '');
        $addr_gst   = is_array($addr) ? ($addr['gst'] ?? ($o['gst_no'] ?? $o['gstin'] ?? '')) : ($o['gst_no'] ?? $o['gstin'] ?? '');

        if (trim($addr_text) !== '') {
            $org_address_list[] = [
                'key'      => $o['id'] . '|' . $idx,
                'org_name' => $o['org_name'],
                'address'  => trim($addr_text),
                'state'    => trim($addr_state),
                'gstin'    => trim($addr_gst)
            ];
        }
    }
}

/* ===== FETCH NEXT PO NUMBER FROM SEQUENCE MASTER ===== */
function get_next_po_number($db) {
    $fy = find_by_sql("SELECT fy_id, fy_name FROM financial_year_master WHERE is_active = 1 LIMIT 1");
    if (!$fy) return ['po_no' => 'PO/' . date('Y') . '/1', 'seq_id' => 0, 'next_no' => 1];

    $fy_id   = $fy[0]['fy_id'];
    $fy_name = $fy[0]['fy_name'];

    $seq = find_by_sql("SELECT * FROM sequence_master WHERE sequence_category = 'po' AND fy_id = '{$fy_id}' LIMIT 1");
    if ($seq) {
        $next_no = (int)$seq[0]['last_no'] + 1;
        return [
            'po_no'   => $fy_name . '/PO/' . $next_no,
            'seq_id'  => $seq[0]['sequence_id'],
            'next_no' => $next_no
        ];
    }
    return ['po_no' => $fy_name . '/PO/1', 'seq_id' => 0, 'next_no' => 1];
}

$next_po_info = get_next_po_number($db);
$edit_mode    = false;
$edit_po      = '';

if (isset($_GET['edit'])) {
    $edit_mode = true;
    $edit_po   = urldecode($db->escape($_GET['edit']));
}

/* ================= SAVE / UPDATE PO (transaction_type = 9) ================= */
if (isset($_POST['save_po']) || isset($_POST['update_po'])) {

    if (empty($_POST['items_json'])) {
        $session->msg("d", "Add at least one item");
        redirect('purchase_order.php');
    }

    $items = json_decode($_POST['items_json'], true);
    if (!is_array($items) || count($items) == 0) {
        $session->msg("d", "Invalid items");
        redirect('purchase_order.php');
    }

    $supplier_id = (int)$_POST['supplier_id'];
    $po_date     = $_POST['po_date'];

    foreach (['d/M/Y', 'd-m-Y', 'Y-m-d'] as $format) {
        $dt = DateTime::createFromFormat($format, $po_date);
        if ($dt instanceof DateTime) {
            $po_date = $dt->format('Y-m-d');
            break;
        }
    }

    $billing_name    = $db->escape($_POST['billing_name'] ?? '');
    $billing_address = $db->escape($_POST['billing_address'] ?? '');
    $billing_state   = $db->escape($_POST['billing_state'] ?? '');
    $billing_gstin   = $db->escape($_POST['billing_gstin'] ?? '');

    $same_as_billing = isset($_POST['same_as_billing']) ? 1 : 0;
    if ($same_as_billing == 1) {
        $shipping_name    = $billing_name;
        $shipping_address = $billing_address;
        $shipping_state   = $billing_state;
        $shipping_gstin   = $billing_gstin;
    } else {
        $shipping_name    = $db->escape($_POST['shipping_name'] ?? $billing_name);
        $shipping_address = $db->escape($_POST['shipping_address'] ?? '');
        $shipping_state   = $db->escape($_POST['shipping_state'] ?? '');
        $shipping_gstin   = $db->escape($_POST['shipping_gstin'] ?? '');
    }

    $comments   = $db->escape($_POST['comments'] ?? '');
    $entry_date = date('Y-m-d H:i:s');
    $org_id     = isset($_SESSION['org_id']) ? (int)$_SESSION['org_id'] : 0;
    $user_id    = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

    $db->query("START TRANSACTION");

    try {
        if (isset($_POST['update_po'])) {
            $po_no = $db->escape($_POST['old_po_no']);
            $db->query("DELETE FROM transaction_master WHERE bill_indent_no = '$po_no' AND transaction_type = 9");
            $db->query("DELETE FROM shipping WHERE bill_no = '$po_no'");
        } else {
            $seq_data = get_next_po_number($db);
            $po_no    = $db->escape($seq_data['po_no']);
            if ($seq_data['seq_id'] > 0) {
                $db->query("UPDATE sequence_master SET last_no = '{$seq_data['next_no']}' WHERE sequence_id = '{$seq_data['seq_id']}'");
            }
        }

        foreach ($items as $it) {
            $qty      = (float)$it['qty'];
            $free     = (float)$it['free_qty'];
            $rate     = (float)$it['rate'];
            $gstp     = (float)$it['gst_percent'];
            $disc     = (float)$it['discount'];
            $misc     = (float)$it['misc'];
            $mrp      = (float)$it['mrp'];
            $buy_type = $it['buy_type'];

            if ($buy_type == 'inclusive') {
                $total_amount = ($qty * $rate) - $disc + $misc;
                $base_amount  = $total_amount / (1 + ($gstp / 100));
                $gst_amount   = $total_amount - $base_amount;
                $net_amount   = $total_amount;
            } else {
                $base_amount = ($qty * $rate) - $disc + $misc;
                $gst_amount  = ($base_amount * $gstp) / 100;
                $net_amount  = $base_amount + $gst_amount;
            }

            $existing_rate = find_by_sql("
                SELECT id FROM rate_master
                WHERE product_id = '{$it['product_id']}'
                AND rate = '$rate' AND gst_id = '{$it['gst_id']}' AND mrp = '$mrp'
                LIMIT 1
            ");

            if ($existing_rate) {
                $rate_id = $existing_rate[0]['id'];
            } else {
                $db->query("
                    INSERT INTO rate_master (product_id, rate, mrp, gst_id, price_date, is_active, created_at)
                    VALUES ('{$it['product_id']}', '$rate', '$mrp', '{$it['gst_id']}', CURDATE(), 1, NOW())
                ");
                $rate_id = $db->insert_id();
            }

            $db->query("
                INSERT INTO transaction_master (
                    product_id, supplier_id, bill_indent_no, po_id, entry_date, bill_indent_date,
                    quantity, free_qty, unit, rate_id, gst_id, unit_price, gst_amount,
                    discount_amount, misc_amount, net_price, mrp, transaction_type, status,
                    payment_status, amount_received, balance_amount,
                    from_dept, to_dept,
                    billing_name, billing_address, billing_state, billing_gstin,
                    shipping_name, shipping_address, shipping_state, shipping_gstin, same_as_billing,
                    comments, created_at, updated_by, organization_id
                ) VALUES (
                    '{$it['product_id']}', '$supplier_id', '$po_no', '$po_no', '$entry_date', '$po_date',
                    '$qty', '$free', 'PCS', '$rate_id', '{$it['gst_id']}', '$rate', '$gst_amount',
                    '$disc', '$misc', '$net_amount', '$mrp', 9, 1,
                    0, 0, '$net_amount',
                    'STORE', 'SUPPLIER',
                    '$billing_name', '$billing_address', '$billing_state', '$billing_gstin',
                    '$shipping_name', '$shipping_address', '$shipping_state', '$shipping_gstin', '$same_as_billing',
                    '$comments', NOW(), '$user_id', '$org_id'
                )
            ");
        }

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
                    VALUES ('$supplier_id', '$po_no', '$shipping_type_id', '$gst_id', '$amount', '$gst_percent', '$gst_type', '$gst_amount', '$total', NOW())
                ");
            }
        }

        $db->query("COMMIT");
        $redirect_url = isset($_POST['update_po'])
            ? 'purchase_order.php?updated=1&po=' . urlencode($po_no)
            : 'purchase_order.php?created=1&po=' . urlencode($po_no);
        redirect($redirect_url);

    } catch (Exception $e) {
        $db->query("ROLLBACK");
        die($e->getMessage());
    }
}

$edit_items   = [];
$edit_charges = [];
$po_info      = [];

if ($edit_mode) {
    $edit_items = find_by_sql("
        SELECT tm.*, p.name, p.hsn_code, COALESCE(p.buy_type,'exclusive') as buy_type, COALESCE(g.gst_percent,0) as gst_percent
        FROM transaction_master tm
        LEFT JOIN products p ON p.id = tm.product_id
        LEFT JOIN gst_master g ON g.id = tm.gst_id
        WHERE tm.bill_indent_no = '{$edit_po}' AND tm.transaction_type = 9
    ");

    $edit_charges = find_by_sql("
        SELECT s.shipping_type_id, s.gst_id, s.amount, s.gst_percent, s.gst_type, s.gst_amount, s.total_amount as total, stm.type_name
        FROM shipping s
        LEFT JOIN shipping_type_master stm ON stm.id = s.shipping_type_id
        WHERE s.bill_no = '{$edit_po}'
    ");

    $po_data = find_by_sql("SELECT * FROM transaction_master WHERE bill_indent_no = '{$edit_po}' AND transaction_type = 9 LIMIT 1");
    $po_info = $po_data ? $po_data[0] : [];
}

$shipping_types = find_by_sql("SELECT * FROM shipping_type_master WHERE is_active = 1");

include_once('layouts/header.php');
?>
<style id="purchase-order-ui-overrides">
/* Purchase Order - organised GRN-style layout */
.grn-card{
    background:#fff;border:1px solid #dbe3ef;border-radius:9px;
    box-shadow:0 5px 18px rgba(15,23,42,.07);overflow:hidden;margin:4px 0 18px;
}
.grn-card-header{
    min-height:38px;padding:9px 18px!important;background:#0b1736!important;
    color:#fff!important;font-size:13px;font-weight:700;letter-spacing:.2px;
}
.grn-card-header .btn{border-radius:4px!important;padding:4px 10px!important}
.grn-card-body{padding:14px!important;background:#fff}
.section-block{
    border:1px solid #dce5f0!important;background:#f8fafc!important;
    border-radius:7px!important;padding:12px!important;margin:0 0 13px!important;
}
.po-section-title{
    display:flex;align-items:center;gap:7px;color:#0b1736;
    font-size:11px;font-weight:800;text-transform:uppercase;
    letter-spacing:.45px;margin:0 0 10px;
}
.po-section-title:before{
    content:"";display:inline-block;width:3px;height:14px;
    background:#2563eb;border-radius:3px;
}
.flex-row-balanced{
    display:flex!important;align-items:flex-end!important;gap:10px!important;
    flex-wrap:nowrap;
}
.flex-row-balanced>div{min-width:0}
.form-label-custom{
    display:block!important;font-size:10px!important;font-weight:700!important;
    color:#26364d!important;text-transform:uppercase;letter-spacing:.25px;
    margin:0 0 5px!important;
}
.grn-input-custom{
    height:32px!important;min-height:32px!important;padding:5px 9px!important;
    border:1px solid #cbd5e1!important;border-radius:4px!important;
    box-shadow:none!important;font-size:11px!important;color:#243247!important;
    background:#fff;
}
.grn-input-custom:focus{
    border-color:#6095e8!important;box-shadow:0 0 0 2px rgba(37,99,235,.09)!important;
}
.btn-custom,.grn-btn-custom{height:32px!important;min-height:32px!important;
    padding:0 11px!important;font-size:11px!important;font-weight:700!important;
    border-radius:4px!important;white-space:nowrap}
.grn-btn-group{display:flex;align-items:flex-end;gap:5px;flex-wrap:wrap}
#grnItemsTable{
    width:100%;margin:0 0 13px!important;border:1px solid #cbd5e1;
    border-collapse:separate;border-spacing:0;font-size:11px;
}
#grnItemsTable thead th{
    background:#0b1736!important;color:#fff!important;border-color:#263858!important;
    padding:9px 7px!important;font-size:10px!important;font-weight:700!important;
    white-space:nowrap;text-align:center;vertical-align:middle;
}
#grnItemsTable tbody td{
    padding:7px!important;vertical-align:middle!important;
    border-color:#e2e8f0!important;color:#334155;
}
#grnItemsTable tbody tr:nth-child(even){background:#f8fafc}
.table-responsive-balanced{width:100%;overflow-x:auto;border-radius:5px}
.bottom-layout-balanced{
    display:grid!important;grid-template-columns:minmax(0,1.35fr) minmax(310px,.85fr);
    gap:14px!important;align-items:start!important;
}
.bottom-left-balanced,.bottom-right-balanced{
    min-width:0;background:#f8fafc;border:1px solid #dce5f0;
    border-radius:7px;padding:11px!important;
}
.bottom-right-balanced{background:#fff}
.bottom-left-balanced textarea{width:100%;min-height:76px}
.payment-box-balanced{
    background:#f8fafc!important;border:1px solid #e2e8f0!important;
    border-radius:5px!important;color:#475569;
}
#poForm button[type=submit]{height:34px!important;min-width:120px;border-radius:4px!important;
    font-size:12px!important;font-weight:700!important}
@media(max-width:1100px){
    .flex-row-balanced{flex-wrap:wrap!important}
    .flex-row-balanced>div{flex:1 1 180px!important;width:auto!important}
    .grn-btn-group{flex:1 1 100%!important}
}
@media(max-width:767px){
    .grn-card-body{padding:9px!important}
    .section-block{padding:9px!important}
    .flex-row-balanced{gap:8px!important}
    .flex-row-balanced>div{flex:1 1 calc(50% - 8px)!important}
    .bottom-layout-balanced{grid-template-columns:1fr!important}
    .bottom-right-balanced{width:100%}
    .grn-card-header{align-items:flex-start!important;gap:8px;flex-wrap:wrap}
    .grn-btn-group{width:100%}
    .grn-btn-group button{flex:1}
}
</style>

<?php if (isset($_GET['created'])) { ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'PO Created Successfully!',
    text: 'PO No: <?= htmlspecialchars($_GET['po'] ?? ''); ?>',
    showCancelButton: true,
    confirmButtonColor: '#0B1736',
    confirmButtonText: 'Print PO',
    cancelButtonText: 'Close'
}).then((res) => {
    if (res.isConfirmed) window.open('print_po.php?po=<?= urlencode($_GET['po'] ?? ''); ?>', '_blank');
});
</script>
<?php } ?>

<?php if (isset($_GET['updated'])) { ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'PO Updated Successfully!',
    text: 'PO No: <?= htmlspecialchars($_GET['po'] ?? ''); ?>',
    showCancelButton: true,
    confirmButtonColor: '#0B1736',
    confirmButtonText: 'Print PO',
    cancelButtonText: 'Close'
}).then((res) => {
    if (res.isConfirmed) window.open('print_po.php?po=<?= urlencode($_GET['po'] ?? ''); ?>', '_blank');
});
</script>
<?php } ?>

<div class="row" style="margin-bottom:5px;">
  <div class="col-md-12"><?php echo display_msg($msg); ?></div>
</div>

<div class="grn-card">
  <div class="grn-card-header" style="display:flex; justify-content:space-between; align-items:center;">
    <span><i class="fa fa-file-text-o"></i> PURCHASE ORDER (PO)</span>
    <div style="display:flex; gap:6px;">
      <a href="po_list.php" class="btn btn-xs btn-default" style="font-weight:600;">Manage PO</a>
      <?php if ($edit_mode) { ?>
        <a href="print_po.php?po=<?= urlencode($edit_po); ?>" target="_blank" class="btn btn-xs btn-warning" style="font-weight:600;">Print PO</a>
      <?php } ?>
    </div>
  </div>
  <div class="grn-card-body">

    <!-- SECTION 1: SUPPLIER, PO NO, PO DATE + SINGLE-LINE BILL TO & SHIP TO -->
    <div class="po-section-title"><i class="fa fa-file-text-o"></i> Order &amp; Delivery Details</div>
    <div class="section-block">
      <!-- Row 1: Exact GRN Header Row -->
      <div class="flex-row-balanced">
        <div style="flex: 2.5;">
          <label class="form-label-custom">Supplier <span style="color:red;">*</span></label>
          <select name="supplier_id" id="supplier_id" form="poForm" class="form-control grn-input-custom" required>
            <option value="">Select Supplier</option>
            <?php foreach ($suppliers as $s) { ?>
              <option value="<?= $s['id']; ?>" <?= ($edit_mode && ($po_info['supplier_id'] ?? '') == $s['id']) ? 'selected' : ''; ?>>
                <?= $s['supplier_name']; ?> <?= !empty($s['state_name']) ? '(' . $s['state_name'] . ')' : ''; ?>
              </option>
            <?php } ?>
          </select>
        </div>

        <div style="flex: 1.2;">
          <label class="form-label-custom">PO No <span style="color:red;">*</span></label>
          <input type="text" name="po_no" value="<?= $edit_mode ? ($po_info['bill_indent_no'] ?? '') : $next_po_info['po_no']; ?>" class="form-control grn-input-custom" form="poForm" readonly style="background:#f1f5f9; font-weight:700;">
        </div>

        <div style="flex: 1.2;">
          <label class="form-label-custom">PO Date <span style="color:red;">*</span></label>
          <input type="text" name="po_date" id="po_date" value="<?= $edit_mode ? date('d/M/Y', strtotime($po_info['bill_indent_date'])) : date('d/M/Y'); ?>" class="form-control grn-input-custom" form="poForm" required>
        </div>
      </div>

      <!-- Hidden billing name & gstin synced from preset -->
      <input type="hidden" name="billing_name" id="billing_name" form="poForm" value="<?= $edit_mode ? htmlspecialchars($po_info['billing_name'] ?? '') : ($org_address_list[0]['org_name'] ?? ''); ?>">
      <input type="hidden" name="billing_gstin" id="billing_gstin" form="poForm" value="<?= $edit_mode ? htmlspecialchars($po_info['billing_gstin'] ?? '') : ($org_address_list[0]['gstin'] ?? ''); ?>">
      <input type="hidden" name="shipping_name" id="shipping_name" form="poForm" value="<?= $edit_mode ? htmlspecialchars($po_info['shipping_name'] ?? '') : ($org_address_list[0]['org_name'] ?? ''); ?>">
      <input type="hidden" name="shipping_gstin" id="shipping_gstin" form="poForm" value="<?= $edit_mode ? htmlspecialchars($po_info['shipping_gstin'] ?? '') : ''; ?>">

      <!-- Row 2: Compact Single-Line Bill To & Ship To -->
      <div class="flex-row-balanced" style="margin-top: 8px;">
        <div style="flex: 1.4;">
          <label class="form-label-custom">Bill To Address <span style="color:red;">*</span></label>
          <select id="bill_preset" class="form-control grn-input-custom" onchange="applyPreset('bill', this)">
            <?php foreach ($org_address_list as $oa): ?>
              <option value="<?= $oa['key']; ?>"
                      data-name="<?= htmlspecialchars($oa['org_name']); ?>"
                      data-address="<?= htmlspecialchars($oa['address']); ?>"
                      data-state="<?= htmlspecialchars($oa['state']); ?>"
                      data-gst="<?= htmlspecialchars($oa['gstin']); ?>"
                      <?= ($edit_mode && ($po_info['billing_address'] ?? '') == $oa['address']) ? 'selected' : ''; ?>>
                <?= htmlspecialchars($oa['org_name'] . ' - ' . $oa['address']); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="billing_address" id="billing_address" form="poForm" value="<?= $edit_mode ? htmlspecialchars($po_info['billing_address'] ?? '') : ($org_address_list[0]['address'] ?? ''); ?>">
        </div>

        <div style="width: 130px;">
          <label class="form-label-custom">Bill State <span style="color:red;">*</span></label>
          <input type="text" name="billing_state" id="billing_state" form="poForm" class="form-control grn-input-custom" placeholder="State" value="<?= $edit_mode ? htmlspecialchars($po_info['billing_state'] ?? '') : ($org_address_list[0]['state'] ?? ''); ?>" required>
        </div>

        <div style="flex: 1.8;">
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <label class="form-label-custom">Ship To Address <span style="color:red;">*</span></label>
            <?php $is_same = $edit_mode ? (int)($po_info['same_as_billing'] ?? 0) : 0; ?>
            <label style="margin:0 0 3px 0; font-size:10px; font-weight:600; cursor:pointer; color:#2563eb;">
              <input type="checkbox" name="same_as_billing" id="same_as_billing" form="poForm" value="1" <?= $is_same ? 'checked' : ''; ?> onchange="toggleSameAsBilling()"> Same as Billing
            </label>
          </div>
          <div style="display:flex; gap:4px;">
            <select id="ship_preset" class="form-control grn-input-custom" style="width:130px;" onchange="applyPreset('ship', this)">
              <option value="">Saved Addr...</option>
              <?php foreach ($org_address_list as $oa): ?>
                <option value="<?= $oa['key']; ?>"
                        data-name="<?= htmlspecialchars($oa['org_name']); ?>"
                        data-address="<?= htmlspecialchars($oa['address']); ?>"
                        data-state="<?= htmlspecialchars($oa['state']); ?>"
                        data-gst="<?= htmlspecialchars($oa['gstin']); ?>">
                  <?= htmlspecialchars(substr($oa['address'], 0, 28)); ?>...
                </option>
              <?php endforeach; ?>
            </select>
            <input type="text" name="shipping_address" id="shipping_address" form="poForm" class="form-control grn-input-custom" placeholder="Enter Delivery Address..." value="<?= $edit_mode ? htmlspecialchars($po_info['shipping_address'] ?? '') : ''; ?>" <?= $is_same ? 'readonly' : ''; ?> required>
          </div>
        </div>

        <div style="width: 130px;">
          <label class="form-label-custom">Ship State <span style="color:red;">*</span></label>
          <input type="text" name="shipping_state" id="shipping_state" form="poForm" class="form-control grn-input-custom" placeholder="e.g. Delhi" value="<?= $edit_mode ? htmlspecialchars($po_info['shipping_state'] ?? '') : ''; ?>" <?= $is_same ? 'readonly' : ''; ?> required>
        </div>
      </div>
    </div>

<!-- SECTION 2: SIMPLE PO ITEM ENTRY -->
    <div class="po-section-title"><i class="fa fa-cubes"></i> Product &amp; Quantity</div>
    <div class="section-block">
      <div class="flex-row-balanced" style="align-items:flex-end;">
        <div style="flex:2; min-width:220px;">
          <label class="form-label-custom">Product <span style="color:red;">*</span></label>
          <div style="display:flex; align-items:center;">
            <input type="text" id="product_name" class="form-control grn-input-custom" placeholder="Click Choose..." readonly style="border-top-right-radius:0!important;border-bottom-right-radius:0!important;border-right:0!important;">
            <button type="button" class="btn btn-primary btn-custom" onclick="openProductModal()" style="border-radius:0!important;display:inline-flex;align-items:center;">Choose</button>
            <a href="product.php?redirect=purchase_order" class="btn btn-success btn-custom" title="Add New Product" style="border-top-left-radius:0!important;border-bottom-left-radius:0!important;display:inline-flex;align-items:center;padding:0 8px;"><i class="fa fa-plus"></i></a>
          </div>
        </div>
        <div style="width:140px;">
          <label class="form-label-custom">Qty <span style="color:red;">*</span></label>
          <input id="qty" type="number" min="0.01" step="any" class="form-control grn-input-custom" placeholder="Enter Qty">
        </div>
        <div class="grn-btn-group">
          <button type="button" onclick="addItem()" class="btn btn-success grn-btn-custom"><i class="fa fa-plus"></i> Add Item</button>
          <button type="button" onclick="cancelEditItem()" class="btn btn-danger grn-btn-custom">Cancel</button>
        </div>
      </div>

      <!-- Hidden fields retained for compatibility with existing PO transaction records. -->
      <input type="hidden" id="product">
      <input type="hidden" id="hsn_code">
      <input type="hidden" id="free_qty" value="0">
      <input type="hidden" id="rate" value="0">
      <input type="hidden" id="disc_percent" value="0">
      <input type="hidden" id="discount" value="0">
      <input type="hidden" id="misc" value="0">
      <input type="hidden" id="mrp" value="0">
      <input type="hidden" id="net_amount" value="0">
      <select id="gst" style="display:none;" aria-hidden="true">
        <option value="">Select GST</option>
        <?php foreach ($gst_list as $g) { ?>
          <option value="<?= $g['id']; ?>" data-gst="<?= $g['gst_percent']; ?>"><?= htmlspecialchars($g['gst_name']); ?> (<?= $g['gst_percent']; ?>%)</option>
        <?php } ?>
      </select>
      <select id="buy_type" style="display:none;" aria-hidden="true">
        <option value="exclusive">Exclusive</option>
        <option value="inclusive">Inclusive</option>
      </select>
    </div>

    <!-- MAIN FORM -->
    <form method="post" id="poForm">
      <input type="hidden" name="round_off" id="round_off" value="0">
      <input type="hidden" name="charges_json" id="charges_json">
      <input type="hidden" name="items_json" id="items_json">
      <?php if ($edit_mode) { ?>
        <input type="hidden" name="old_po_no" value="<?= htmlspecialchars($edit_po); ?>">
      <?php } ?>

      <div class="po-section-title"><i class="fa fa-list-alt"></i> Added PO Items</div>
      <!-- ITEMS TABLE -->
      <div class="table-responsive-balanced">
        <table id="grnItemsTable" class="table table-bordered table-striped">
          <thead>
            <tr>
              <th width="45">#</th>
              <th>Product</th>
              <th width="130">HSN</th>
              <th width="150">Qty</th>
              <th width="90">Action</th>
            </tr>
          </thead>
          <tbody id="itemBody"></tbody>
        </table>
      </div>

      <div class="section-block" style="text-align:right;">
        <?php if ($edit_mode) { ?>
          <button name="update_po" type="submit" class="btn btn-primary btn-custom" style="padding:0 24px !important;font-size:13px!important;">Update PO</button>
        <?php } else { ?>
          <button name="save_po" type="submit" class="btn btn-success btn-custom" style="padding:0 24px !important;font-size:13px!important;">Create PO</button>
        <?php } ?>
      </div>
    </form>

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
        <input type="text" id="searchProduct" class="form-control grn-input-custom" placeholder="Search product name or HSN..." style="margin-bottom:12px;">
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
              <?php $i = 1; foreach ($products as $p) { ?>
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

<script>
var items = <?= $edit_mode ? json_encode($edit_items) : '[]'; ?>;
var charges = <?= $edit_mode ? json_encode($edit_charges) : '[]'; ?>;
let sno = items.length + 1;
let backupItem = null;
const hiddenGstSelect = document.getElementById("gst");
if (hiddenGstSelect && hiddenGstSelect.options.length > 1) hiddenGstSelect.selectedIndex = 1;

function applyPreset(target, selectEl) {
    let opt = selectEl.options[selectEl.selectedIndex];
    if (!selectEl.value) return;

    if (target === 'bill') {
        document.getElementById("billing_name").value    = opt.getAttribute("data-name") || '';
        document.getElementById("billing_address").value = opt.getAttribute("data-address") || '';
        document.getElementById("billing_state").value   = opt.getAttribute("data-state") || '';
        document.getElementById("billing_gstin").value   = opt.getAttribute("data-gst") || '';
        if (document.getElementById("same_as_billing").checked) toggleSameAsBilling();
    } else {
        document.getElementById("same_as_billing").checked = false;
        toggleSameAsBilling();
        document.getElementById("shipping_name").value    = opt.getAttribute("data-name") || '';
        document.getElementById("shipping_address").value = opt.getAttribute("data-address") || '';
        document.getElementById("shipping_state").value   = opt.getAttribute("data-state") || '';
        document.getElementById("shipping_gstin").value   = opt.getAttribute("data-gst") || '';
    }
}

function toggleSameAsBilling() {
    let isSame = document.getElementById("same_as_billing").checked;
    let shipAddr  = document.getElementById("shipping_address");
    let shipState = document.getElementById("shipping_state");

    if (isSame) {
        document.getElementById("shipping_name").value  = document.getElementById("billing_name").value;
        document.getElementById("shipping_gstin").value = document.getElementById("billing_gstin").value;
        shipAddr.value  = document.getElementById("billing_address").value;
        shipState.value = document.getElementById("billing_state").value;
        shipAddr.setAttribute('readonly', true);
        shipState.setAttribute('readonly', true);
    } else {
        shipAddr.removeAttribute('readonly');
        shipState.removeAttribute('readonly');
    }
}

document.getElementById("billing_state").addEventListener("input", function() {
    if (document.getElementById("same_as_billing").checked) toggleSameAsBilling();
});

document.addEventListener("DOMContentLoaded", function() {
    if (typeof flatpickr !== 'undefined') flatpickr("#po_date", { dateFormat: "d/M/Y" });
});

<?php if ($edit_mode) { ?>
items.forEach((it, index) => {
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
charges.forEach(c => {
    c.shipping_type_id = parseInt(c.shipping_type_id || 0);
    c.gst_id = parseInt(c.gst_id || 0);
    c.amount = parseFloat(c.amount || 0);
    c.gst_percent = parseFloat(c.gst_percent || 0);
    c.gst_amount = parseFloat(c.gst_amount || 0);
    c.total = parseFloat(c.total || 0);
});
document.addEventListener("DOMContentLoaded", function() {
    renderItems();
    renderCharges();
});
<?php } ?>

function calculateDiscount() {
    let qty = parseFloat(document.getElementById("qty").value) || 0;
    let rate = parseFloat(document.getElementById("rate").value) || 0;
    let discPercent = parseFloat(document.getElementById("disc_percent").value) || 0;
    let totalAmt = qty * rate;
    let discAmt = (totalAmt * discPercent) / 100;
    document.getElementById("discount").value = discAmt.toFixed(2);
    document.getElementById("net_amount").value = (totalAmt - discAmt).toFixed(2);
}

function calculateDiscountFromAmount() {
    let qty = parseFloat(document.getElementById("qty").value) || 0;
    let rate = parseFloat(document.getElementById("rate").value) || 0;
    let totalAmt = qty * rate;
    let discAmt = parseFloat(document.getElementById("discount").value) || 0;
    let discPercent = (totalAmt > 0) ? (discAmt * 100) / totalAmt : 0;
    document.getElementById("disc_percent").value = discPercent.toFixed(2);
    document.getElementById("net_amount").value = (totalAmt - discAmt).toFixed(2);
}

function addItem() {
    let pid = document.getElementById("product").value;
    let pname = document.getElementById("product_name").value;
    let qty = parseFloat(document.getElementById("qty").value) || 0;
    let free = parseFloat(document.getElementById("free_qty").value) || 0;
    let rate = parseFloat(document.getElementById("rate").value) || 0;
    let disc_percent = parseFloat(document.getElementById("disc_percent").value) || 0;
    let disc = parseFloat(document.getElementById("discount").value) || 0;
    let misc = parseFloat(document.getElementById("misc").value) || 0;
    let mrp = parseFloat(document.getElementById("mrp").value) || 0;

    let gstSel = document.getElementById("gst");
    let gst_id = gstSel.value;
    let gstp = parseFloat(gstSel.options[gstSel.selectedIndex]?.dataset.gst || 0);

    if (!pid || qty <= 0) {
        Swal.fire({ icon: 'warning', title: 'Required Fields', text: 'Please select a Product and enter a valid Quantity.' });
        return;
    }

    // GST remains an internal compatibility value; it is not part of the PO requirement list.
    if (!gst_id && gstSel.options.length > 1) {
        gstSel.selectedIndex = 1;
        gst_id = gstSel.value;
        gstp = parseFloat(gstSel.options[gstSel.selectedIndex]?.dataset.gst || 0);
    }

    let buyType = document.getElementById("buy_type").value;
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

    items.push({
        sno: sno++, product_id: pid, name: pname,
        hsn_code: document.getElementById("hsn_code").value,
        qty: qty, free_qty: free, rate: rate, gst_id: gst_id, gst_percent: gstp,
        buy_type: buyType, base_amount: base, gst_amount: gst,
        disc_percent: disc_percent, discount: disc, misc: misc, mrp: mrp, total: total
    });

    renderItems();
    cancelEditItem();
}

function renderItems() {
    let tb = document.getElementById("itemBody");
    tb.innerHTML = "";
    items.forEach((it, i) => {
        it.sno = i + 1;
        tb.innerHTML += `
        <tr>
          <td class="text-center">${it.sno}</td>
          <td><strong>${it.name || ''}</strong></td>
          <td class="text-center">${it.hsn_code || '-'}</td>
          <td class="text-center"><strong>${parseFloat(it.qty || 0).toLocaleString('en-IN')}</strong></td>
          <td class="text-center">
            <button type="button" onclick="editItem(${i})" class="btn btn-xs btn-info" title="Edit"><i class="fa fa-pencil"></i></button>
            <button type="button" onclick="items.splice(${i},1);renderItems()" class="btn btn-xs btn-danger" title="Remove"><i class="fa fa-trash"></i></button>
          </td>
        </tr>`;
    });
    updateGrandTotal();
}

function editItem(index) {
    let it = items[index];
    backupItem = { ...it };
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
    items.splice(index, 1);
    renderItems();
}

function cancelEditItem() {
    if (backupItem) { items.push(backupItem); backupItem = null; renderItems(); }
    ['product', 'product_name', 'hsn_code', 'qty', 'free_qty', 'rate', 'disc_percent', 'discount', 'net_amount', 'misc', 'mrp', 'gst'].forEach(id => {
        document.getElementById(id).value = "";
    });
    document.getElementById("buy_type").value = "exclusive";
}

function renderCharges() {
    // Shipping charges remain in memory and are submitted to the backend;
    // their summary is intentionally not displayed on the PO form.
    updateGrandTotal();
}

function updateGrandTotal() {
    // Keep totals/charges in the data payload for the existing backend logic.
    // The PO form no longer displays Total, GST summary, or Shipping summary.
    document.getElementById("round_off").value = 0;
    document.getElementById("items_json").value = JSON.stringify(items);
    document.getElementById("charges_json").value = JSON.stringify(charges);
}

function selectProduct(id, name, hsn) {
    document.getElementById("product").value = id;
    document.getElementById("product_name").value = name;
    document.getElementById("hsn_code").value = hsn;
    $('#productModal').modal('hide');
    setTimeout(() => { document.getElementById("qty").focus(); }, 150);

    fetch("get_product_rate.php?product_id=" + id)
        .then(res => res.json())
        .then(data => {
            if (!data || Object.keys(data).length === 0) return;
            if (data.rate !== undefined) document.getElementById("rate").value = data.rate;
            if (data.mrp !== undefined) document.getElementById("mrp").value = data.mrp;
            if (data.gst_id) document.getElementById("gst").value = data.gst_id;
            if (data.buy_type) document.getElementById("buy_type").value = data.buy_type;

        });
}

function openProductModal() {
    $('#productModal').modal('show');
    setTimeout(() => {
        $('#searchProduct').val('');
        $("#productTable tr").show();
        document.getElementById("searchProduct").focus();
    }, 300);
}

document.getElementById("searchProduct")?.addEventListener("keyup", function() {
    let val = this.value.toLowerCase();
    document.querySelectorAll("#productTable tr").forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(val) ? "" : "none";
    });
});

document.getElementById("poForm").addEventListener("submit", function(e) {
    updateGrandTotal();
    if (document.getElementById("supplier_id").value == "" || items.length == 0) {
        e.preventDefault();
        Swal.fire({ icon: 'warning', title: 'Validation Error', text: 'Please select a Supplier and add at least one item.' });
    }
});
</script>

<?php include_once('layouts/footer.php'); ?>