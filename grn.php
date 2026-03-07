<?php
$page_title = 'GRN';
require_once('includes/load.php');
page_require_level(2);

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

/* ================= SAVE GRN ================= */

if (isset($_POST['save_grn'])) {

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
  $payment_mode = $_POST['payment_mode'] ?? '';
  $amount_paid  = (float)($_POST['amount_paid'] ?? 0);
  $comments     = $_POST['comments'] ?? '';

  $entry_date  = date('Y-m-d H:i:s');
  $grand_total = 0;

  $db->query("START TRANSACTION");

  try {

    foreach ($items as $it) {

      $qty   = (float)$it['qty'];
      $free  = (float)$it['free_qty'];
      $rate  = (float)$it['rate'];
      $gstp  = (float)$it['gst_percent'];
      $disc  = (float)$it['discount'];
      $misc  = (float)$it['misc'];
      $mrp   = (float)$it['mrp'];

      $base_amount = ($qty * $rate) - $disc + $misc;
      $gst_amount  = ($base_amount * $gstp) / 100;
      $net_amount  = $base_amount + $gst_amount;

      $grand_total += $net_amount;

      /* ===== RATE MASTER ===== */
      $db->query("
        INSERT INTO rate_master
        (product_id, rate, mrp, gst_id, price_date, is_active, created_at)
        VALUES
        ('{$it['product_id']}', '$rate', '$mrp', '{$it['gst_id']}', CURDATE(), 1, NOW())
      ");
      $rate_id = $db->insert_id();

      /* ===== TRANSACTION MASTER ===== */
      $db->query("
        INSERT INTO transaction_master
        (
          product_id, supplier_id, bill_indent_no,
          entry_date, bill_indent_date,
          quantity, free_qty, unit,
          rate_id, gst_id,
          unit_price, gst_amount,
          discount_amount, misc_amount,
          net_price, mrp,
          transaction_type, status,
          payment_status, payment_mode,
          amount_received, balance_amount,
          from_dept, to_dept, comments,
          created_at
        )
        VALUES
        (
          '{$it['product_id']}',
          '$supplier_id',
          '$bill_no',
          '$entry_date',
          '$bill_date',
          '$qty',
          '$free',
          'PCS',
          '$rate_id',
          '{$it['gst_id']}',
          '$rate',
          '$gst_amount',
          '$disc',
          '$misc',
          '$net_amount',
          '$mrp',
          1,
          1,
          0,
          '$payment_mode',
          0,
          '$net_amount',
          'SUPPLIER',
          'STORE',
          '$comments',
          NOW()
        )
      ");

      $transaction_id = $db->insert_id();

      /* ===== INVENTORY ===== */
      $db->query("
        INSERT INTO inventory
        (transaction_id, product_id, quantity, free_qty,
         fin_year, status, origin_dept, updated_at)
        VALUES
        ('$transaction_id', '{$it['product_id']}',
         '$qty', '$free', '2025-26', 1, 'SUPPLIER', NOW())
      ");

      update_product_qty($qty + $free, $it['product_id']);

        /* ===== UPDATE PRODUCT MASTER (LATEST BUY PRICE) ===== */

$db->query("
  UPDATE products
  SET 
    buy_price = '$rate',
    gst_id    = '{$it['gst_id']}',
    buy_type  = 'exclusive'
  WHERE id = '{$it['product_id']}'
");
    }

  /* ================= INSERT SHIPPING HERE ================= */

$charges = json_decode($_POST['charges_json'], true);

if (is_array($charges)) {
  foreach ($charges as $c) {

    $shipping_type_id = (int)$c['shipping_type_id'];
    $gst_id = (int)$c['gst_id'];
    $amount = (float)$c['amount'];
    $gst_percent = (float)$c['gst_percent'];
    $gst_type = $c['gst_type'];
    $gst_amount = (float)$c['gst_amount'];
    $total = (float)$c['total'];

    $db->query("
      INSERT INTO shipping
      (supplier_id, bill_no, shipping_type_id,
      gst_id,
      amount, gst_percent, gst_type,
      gst_amount, total_amount, created_at)
      VALUES
      ('$supplier_id', '$bill_no', '$shipping_type_id',
      '$gst_id',
      '$amount', '$gst_percent', '$gst_type',
      '$gst_amount', '$total', NOW())
    ");

    $grand_total += $total;
  }
}

    /* ===== SUPPLIER LEDGER ===== */
    $db->query("
      INSERT INTO supplier_ledger
      (supplier_id, bill_no, bill_date,
       bill_amount, paid_amount, balance_amount,
       payment_status, created_at)
      VALUES
      (
        '$supplier_id',
        '$bill_no',
        '$bill_date',
        '$grand_total',
        '$amount_paid',
        '".($grand_total - $amount_paid)."',
        '".($amount_paid > 0 ? 1 : 0)."',
        NOW()
      )
    ");
    $ledger_id = $db->insert_id();

    /* ===== SUPPLIER PAYMENT ===== */
    if ($amount_paid > 0) {
      $db->query("
        INSERT INTO supplier_payment
        (ledger_id, supplier_id, payment_date,
         payment_amount, payment_mode, created_at)
        VALUES
        ('$ledger_id', '$supplier_id', CURDATE(),
         '$amount_paid', '$payment_mode', NOW())
      ");
    }

    $db->query("COMMIT");
    $session->msg("s", "GRN Created Successfully");

  } catch (Exception $e) {
    $db->query("ROLLBACK");
    $session->msg("d", "GRN Failed");
  }

  redirect('grn.php');
}

include_once('layouts/header.php');
?>

<!-- ================= UI ================= -->

<div class="row">
  <div class="col-md-12"><?php echo display_msg($msg); ?></div>
</div>

<div class="row">

<!-- LEFT : ITEM ENTRY -->
<div class="col-md-4">
<div class="panel panel-default">
<div class="panel-heading"><strong>Item Entry</strong></div>
<div class="panel-body">

<label>Supplier</label>
<select name="supplier_id" form="grnForm" class="form-control" required>
<option value="">Select Supplier</option>
<?php foreach ($suppliers as $s) { ?>
<option value="<?= $s['id']; ?>"><?= $s['supplier_name']; ?></option>
<?php } ?>
</select><br>

<label>Bill / GRN No</label>
<input type="text" name="bill_no" form="grnForm" class="form-control" required><br>

<label>Bill Date</label>
<input type="date" name="bill_date" form="grnForm" class="form-control" required><br>

<input type="hidden" id="product">
<input type="hidden" id="hsn_code">
<input type="hidden" id="sac_code">

<div class="input-group">
  <input type="text" id="product_name" class="form-control" placeholder="Select Product" readonly>
  <span class="input-group-btn">
    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#productModal">
      Choose
    </button>
  </span>
</div>
<br>

<input id="qty" class="form-control" placeholder="Quantity"><br>
<input id="free_qty" class="form-control" placeholder="Free Qty"><br>
<input id="rate" class="form-control" placeholder="Unit Price"><br>
<input id="discount" class="form-control" placeholder="Discount"><br>
<input id="misc" class="form-control" placeholder="Misc Amount"><br>
<input id="mrp" class="form-control" placeholder="MRP"><br>

<select id="gst" class="form-control">
<option value="">Select GST</option>
<?php foreach ($gst_list as $g) { ?>
<option value="<?= $g['id']; ?>" data-gst="<?= $g['gst_percent']; ?>">
<?= $g['gst_name']; ?> (<?= $g['gst_percent']; ?>%)
</option>
<?php } ?>
</select><br>

<div id="previousRateBox"
     style="display:none; margin-top:8px; padding:8px;
            background:#f5f5f5; border-left:4px solid #2196F3;
            font-size:12px;">
</div>


<br>

<button type="button" onclick="addItem()" class="btn btn-info btn-block">
Add Item
</button>

</div>
</div>
</div>

<!-- RIGHT SIDE -->
<!-- RIGHT : ITEM LIST + PAYMENT -->
<div class="col-md-8">
<form method="post" id="grnForm">

<input type="hidden" name="charges_json" id="charges_json">


<input type="hidden" name="items_json" id="items_json">

<div class="panel panel-default">
<div class="panel-heading">
<strong>GRN ITEMS</strong>
</div>

<div class="panel-body">

<table class="table table-bordered table-striped">
<thead>
<tr>
<th width="5%">#</th>
<th width="10%">Product</th>
<th width="10%">HSN Code</th>
<th width="10%">Qty</th>
<th width="10%">Rate</th>
<th width="12%">Amount</th>
<th width="10%">GST%</th>
<th width="10%">GST Amt</th>
<th width="15%">Total</th>
<th width="5%">X</th>
</tr>
</thead>
<tbody id="itemBody"></tbody>
</table>

<hr>

<h4><strong>Shipping / Additional Charges</strong></h4>

<div class="row">

<div class="col-md-3">
<select id="charge_type" class="form-control">
<option value="">Select Type</option>
<?php
$shipping_types = find_by_sql("SELECT * FROM shipping_type_master WHERE is_active = 1");
foreach($shipping_types as $st){ ?>
  <option value="<?= $st['id']; ?>">
    <?= $st['type_name']; ?>
  </option>
<?php } ?>
</select>
</div>

<div class="col-md-2">
<input type="number" id="charge_amount" class="form-control" placeholder="Amount">
</div>

<div class="col-md-2">
<select id="charge_gst_type" class="form-control">
<option value="EXCLUSIVE">Exclusive</option>
<option value="INCLUSIVE">Inclusive</option>
</select>
</div>

<div class="col-md-2">
<select id="charge_gst_id" class="form-control">
  <option value="">Select GST</option>
  <?php foreach ($gst_list as $g) { ?>
    <option value="<?= $g['id']; ?>" 
            data-gst="<?= $g['gst_percent']; ?>">
      <?= $g['gst_name']; ?> (<?= $g['gst_percent']; ?>%)
    </option>
  <?php } ?>
</select>
</div>

<div class="col-md-2">
<button type="button" onclick="addCharge()" class="btn btn-warning">
Add
</button>
</div>

</div>

<br>

<table class="table table-bordered">
<thead>
<tr>
<th>Type</th>
<th>Amount</th>
<th>GST %</th>
<th>GST Amt</th>
<th>Total</th>
<th>X</th>
</tr>
</thead>
<tbody id="chargeBody"></tbody>
</table>


<h4 class="text-right" style="margin-top:10px;">
Grand Total ₹ <span id="grandTotal">0.00</span>
</h4>

<hr>

<div class="row" style="margin-top:15px;">

<div class="col-md-6">
<label><strong>Payment Mode</strong></label>
<select name="payment_mode" class="form-control">
<option value="">-- None --</option>

<?php foreach($payment_modes as $pm){ ?>
<option value="<?= $pm['mode_name']; ?>">
  <?= $pm['mode_name']; ?>
</option>
<?php } ?>

</select>
</div>

<div class="col-md-6">
<label><strong>Amount Paid</strong></label>
<input type="number" name="amount_paid" class="form-control">
</div>

</div>

<br>

<label><strong>Comments</strong></label>
<textarea name="comments" class="form-control" rows="3"></textarea>

<br>

<button name="save_grn" class="btn btn-success pull-right">
Create GRN
</button>

</div>
</div>

</form>
</div>


<!-- ================= SCRIPT ================= -->

<script>

let items = [];
let sno = 1;
let charges = [];

function addItem() {

  let pid = document.getElementById("product").value;
  let pname = document.getElementById("product_name").value;

  let qty   = parseFloat(document.getElementById("qty").value) || 0;
  let free  = parseFloat(document.getElementById("free_qty").value) || 0;
  let rate  = parseFloat(document.getElementById("rate").value) || 0;
  let disc  = parseFloat(document.getElementById("discount").value) || 0;
  let misc  = parseFloat(document.getElementById("misc").value) || 0;
  let mrp   = parseFloat(document.getElementById("mrp").value) || 0;

  let gstSel = document.getElementById("gst");
  let gst_id = gstSel.value;
  let gstp   = parseFloat(gstSel.options[gstSel.selectedIndex]?.dataset.gst || 0);

  if (!pid || qty <= 0 || rate <= 0 || !gst_id) {
    alert("Please fill all item fields");
    return;
  }

  let base  = (qty * rate) - disc + misc;
  let gst   = (base * gstp) / 100;
  let total = base + gst;

  items.push({
    sno: sno++,
    product_id: pid,
    name: pname,
    hsn_code: document.getElementById("hsn_code").value,
    qty: qty,
    free_qty: free,
    rate: rate,
    gst_id: gst_id,
    gst_percent: gstp,
    gst_amount: gst,
    discount: disc,
    misc: misc,
    mrp: mrp,
    total: total
  });

  renderItems();
}

function renderItems() {

  let tb = document.getElementById("itemBody");
  tb.innerHTML = "";
  let grand = 0;

  items.forEach((it, i) => {

    grand += it.total;
    let amount = it.qty * it.rate;
    let gst_amt = (amount * it.gst_percent) / 100;


    tb.innerHTML += `
      <tr>
        <td>${it.sno}</td>
        <td>${it.name}</td>
        <td>${it.hsn_code || '-'}</td>
        <td>${it.qty} (+${it.free_qty})</td>
        <td>${it.rate}</td>
        <td>${amount.toFixed(2)}</td> 
        <td>${it.gst_percent}%</td>
        <td>${it.gst_amount.toFixed(2)}</td>
        <td>${it.total.toFixed(2)}</td>
        <td>
          <button type="button" onclick="items.splice(${i},1);renderItems()">X</button>
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

  document.getElementById("grandTotal").innerText = finalTotal.toFixed(2);

  document.getElementById("items_json").value = JSON.stringify(items);
  document.getElementById("charges_json").value = JSON.stringify(charges);
}

function addCharge() {

  let select = document.getElementById("charge_type");
  let shipping_type_id = select.value;
  let type_name = select.options[select.selectedIndex].text;
  let amount = parseFloat(document.getElementById("charge_amount").value) || 0;
  let gstSelect = document.getElementById("charge_gst_id");
  let gst_id = gstSelect.value;
  let gst_percent = parseFloat(
    gstSelect.options[gstSelect.selectedIndex]?.dataset.gst || 0
  );
  let gst_type = document.getElementById("charge_gst_type").value;

  if (amount <= 0) {
    alert("Enter valid amount");
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

  charges.push({
    shipping_type_id,
    type_name,
    gst_id,
    amount,
    gst_percent,
    gst_type,
    gst_amount,
    total
  });

  renderCharges();
}

function renderCharges() {

  let body = document.getElementById("chargeBody");
  body.innerHTML = "";

  charges.forEach((c, i) => {
    body.innerHTML += `
      <tr>
        <td>${c.type_name}</td>
        <td>${c.amount}</td>
        <td>${c.gst_percent}%</td>
        <td>${c.gst_amount.toFixed(2)}</td>
        <td>${c.total.toFixed(2)}</td>
        <td>
          <button type="button"
          onclick="charges.splice(${i},1);renderCharges()">X</button>
        </td>
      </tr>
    `;
  });

  updateGrandTotal();
}


function selectProduct(id, name, hsn) {

  document.getElementById("product").value = id;
  document.getElementById("product_name").value = name;
  document.getElementById("hsn_code").value = hsn;
  $('#productModal').modal('hide');

  fetch("get_product_rate.php?product_id=" + id)
    .then(res => res.json())
    .then(data => {

      if (!data || Object.keys(data).length === 0) return;

      if (data.rate !== undefined)
        document.getElementById("rate").value = data.rate;

      if (data.mrp !== undefined)
        document.getElementById("mrp").value = data.mrp;

      if (data.gst_id)
        document.getElementById("gst").value = data.gst_id;

   if (data.last_rate !== undefined) {

  let box = document.getElementById("previousRateBox");
  box.style.display = "block";

box.innerHTML = `
    <strong>Last Purchase Details:</strong><br>
    Rate (Inclusive GST): ₹ ${data.last_rate}<br>
    GST Applied: ${data.gst_percent}%<br>
    MRP: ₹ ${data.mrp}<br>
    Price Date: ${data.price_date}
  `;
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
        row.style.display =
          row.innerText.toLowerCase().includes(value) ? "" : "none";
      });

    });
  }

});


</script>


<!-- PRODUCT MODAL -->
<div class="modal fade" id="productModal">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4>Select Product</h4>
      </div>

      <div class="modal-body">

        <input type="text" id="searchProduct" class="form-control" placeholder="Search product..."><br>

        <div style="max-height:350px; overflow-y:auto;">
          <table class="table table-bordered table-hover">
            <thead>
              <tr>
                <th>#</th>
                <th>Product Name</th>
              </tr>
            </thead>
            <tbody id="productTable">
              <?php $i=1; foreach($products as $p){ ?>
              <tr onclick="selectProduct(
                '<?= $p['id']; ?>',
                '<?= $p['name']; ?>',
                '<?= $p['hsn_code']; ?>'
              )"
              style="cursor:pointer;">

              <td><?= $i++; ?></td>
              <td><?= $p['name']; ?></td>
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


<?php include_once('layouts/footer.php'); ?>
