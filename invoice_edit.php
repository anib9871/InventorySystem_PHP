<?php
require_once('includes/load.php');
//page_require_level(2);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($id <= 0) die("Invalid ID");

/* Fetch invoice */
$qdata = find_by_sql("SELECT * FROM invoice WHERE id = $id");
if(!$qdata) die("invoice not found");
$quote = $qdata[0];

/* Fetch items */
$items = find_by_sql("
SELECT *
FROM invoice_items
WHERE invoice_id = $id
");

$customers = find_all('customer_master');
$products  = join_product_table();

/* ================= UPDATE ================= */
if(isset($_POST['update_invoice'])){

    $cust = (int)$_POST['customer_id'];
    $gst_type = ($gst_enabled == "Yes")
            ? ($_POST['gst_type'] ?? 'exclusive')
            : 'exclusive';

    $subtotal = 0;
    $net_total = 0;

    // 🔥 OLD DATA (reverse ke liye)
$old = find_by_id('invoice', $id);
$old_total = $old['net_total'];
$old_customer = $old['customer_id'];

$old_acc = "CUSTOMER";

// 🔥 ONLY ITEMS DELETE
$db->query("DELETE FROM invoice_items WHERE invoice_id = $id");

    foreach($_POST['product_id'] as $i => $pid){

        $pid  = (int)$pid;
        $qty  = (float)$_POST['qty'][$i];
        $base = (float)$_POST['rate'][$i];
        $gst = 0;

if($gst_enabled == "Yes"){
   $gst = (float)$_POST['gst'][$i];
}
        $disc = (float)$_POST['discount'][$i];

        if($pid <= 0 || $qty <= 0) continue;

        $line_base = $qty * $base;
        $discounted_base = $line_base - $disc;

if($gst_enabled == "No"){

    $gst_amount = 0;

    $cgst_amount = 0;
    $sgst_amount = 0;
    $igst_amount = 0;

    $rate_incl  = $base;

    $line_total = $discounted_base;

}
else{

    if($gst_type == "exclusive"){

        $gst_amount = $discounted_base * $gst / 100;

        $rate_incl  = $base + ($base * $gst / 100);

        $line_total = $discounted_base + $gst_amount;

    }else{

        $gst_amount = $discounted_base
                    - ($discounted_base / (1 + $gst/100));

        $rate_incl  = $base;

        $line_total = $discounted_base;
    }

    /* 🔥 GST SPLIT */

    if($tax_mode == "IGST"){

        $igst_amount = $gst_amount;

        $cgst_amount = 0;
        $sgst_amount = 0;

    }else{

        $cgst_amount = $gst_amount / 2;

        $sgst_amount = $gst_amount / 2;

        $igst_amount = 0;
    }
}

$subtotal  += $discounted_base;

$net_total += $line_total;

$db->query("
INSERT INTO invoice_items
(
invoice_id,
product_id,
qty,
rate_excl_gst,
discount_amount,
gst_percent,
rate_incl_gst,
line_total,
cgst_amount,
sgst_amount,
igst_amount
)
VALUES
(
$id,
$pid,
$qty,
$base,
$disc,
$gst,
$rate_incl,
$line_total,
$cgst_amount,
$sgst_amount,
$igst_amount
)
");
    }

    $gst_total = $net_total - $subtotal;

    // 🔥 OLD ENTRY REVERSE (VERY IMPORTANT)

// CUSTOMER reverse
$db->query("
INSERT INTO ledger_entries (invoice_id, customer_id, account, type, amount)
VALUES ($id, $old_customer, 'CUSTOMER', 'CREDIT', '$old_total')
");

// SALES reverse
$db->query("
INSERT INTO ledger_entries (invoice_id, customer_id, account, type, amount)
VALUES ($id, $old_customer, 'SALES', 'DEBIT', '$old_total')
");

    $db->query("
    UPDATE invoice SET
    customer_id = '$cust',
    gst_type = '$gst_type',
    subtotal = '$subtotal',
    gst_total = '$gst_total',
    net_total = '$net_total'
    WHERE id = '$id'
    ");

    // 🔥 LEDGER ENTRY (REINSERT)

// CUSTOMER DEBIT
$customer_acc = "CUSTOMER";

$db->query("
INSERT INTO ledger_entries (invoice_id, customer_id, account, type, amount)
VALUES ($id, $cust, 'CUSTOMER', 'DEBIT', '$net_total')
");

// SALES CREDIT
$db->query("
INSERT INTO ledger_entries (invoice_id, customer_id, account, type, amount)
VALUES ($id, $cust, 'SALES', 'CREDIT', '$net_total')
");

if(isset($_POST['payment_amount'])){
  foreach($_POST['payment_amount'] as $mode_id => $amt){

    if($amt > 0){

      $pm = find_by_id('payment_mode_master', $mode_id);
      $mode_name = $pm['mode_name'];

      $db->query("
      INSERT INTO payments (invoice_id, payment_mode, amount)
      VALUES ($id, '$mode_name', '$amt')
      ");

      // 🔥 LEDGER PAYMENT
      $db->query("
      INSERT INTO ledger_entries (invoice_id, account, type, amount)
      VALUES ($id, '".strtoupper($mode_name)."', 'DEBIT', '$amt')
      ");



$db->query("
INSERT INTO ledger_entries (invoice_id, customer_id, account, type, amount)
VALUES ($id, $cust, 'CUSTOMER', 'CREDIT', '$amt')
");
    }
  }
}


    echo "<script>
    window.location='invoice_list.php?print_id=".$id."';
    </script>";
}


?>

<?php include_once('layouts/header.php'); ?>

<div class="card shadow-sm">
<div class="card-header bg-white">
<h4>Edit invoice</h4>
</div>

<div class="card-body">
<form method="post">

<!-- CUSTOMER -->
<label>Customer</label>
<select name="customer_id" class="form-control mb-3" required>
<?php foreach($customers as $c){ ?>
<option value="<?=$c['id'];?>"
<?php if($c['id']==$quote['customer_id']) echo "selected"; ?>>
<?=$c['customer_name'];?>
</option>
<?php } ?>
</select>

<?php if($gst_enabled == "Yes"): ?>
<!-- GST TYPE -->
<div class="mb-3">
<label>
<input type="radio" name="gst_type" value="exclusive"
<?php if($quote['gst_type']=="exclusive") echo "checked"; ?>>
Exclusive
</label>

<label class="ms-3">
<input type="radio" name="gst_type" value="inclusive"
<?php if($quote['gst_type']=="inclusive") echo "checked"; ?>>
Inclusive
</label>
</div>
<?php endif; ?>

<table class="table table-bordered">
<thead>
<tr>
<th>Product</th>
<th>Qty</th>
<th>Rate</th>
<?php if($gst_enabled == "Yes"): ?>
<th>GST%</th>
<?php endif; ?>
<th>Discount</th>
<th>Total</th>
<th>Action</th>
</tr>
</thead>

<tbody>
<?php foreach($items as $it){ ?>
<tr>
<td>
<select name="product_id[]" class="form-control">
<?php foreach($products as $p){ ?>
<option value="<?=$p['id'];?>"
<?php if($p['id']==$it['product_id']) echo "selected"; ?>>
<?=$p['name'];?>
</option>
<?php } ?>
</select>
</td>

<td>
<input type="number" name="qty[]" class="form-control"
value="<?=$it['qty'];?>">
</td>

<td>
<input type="number" name="rate[]" class="form-control"
value="<?=$it['rate_excl_gst'];?>">
</td>

<?php if($gst_enabled == "Yes"): ?>
<td>
<input type="number" name="gst[]" class="form-control"
value="<?=$it['gst_percent'];?>">
</td>
<?php endif; ?>

<td>
<input type="number" name="discount[]" class="form-control"
value="<?=$it['discount_amount'];?>">
</td>

<td>
₹ <?=number_format($it['line_total'],2);?>
</td>

<td>
  <button type="button" class="btn btn-danger btn-sm removeRow">
    ✖
  </button>
</td>
</tr>
<?php } ?>
</tbody>
</table>

<div class="text-end mt-3">
<button type="submit" name="update_invoice"
class="btn btn-success">
Update invoice
</button>
</div>

</form>
</div>
</div>

<script>
document.addEventListener("click", function(e){
  if(e.target.classList.contains("removeRow")){
    let row = e.target.closest("tr");
    row.remove();
  }
});
</script>
<?php include_once('layouts/footer.php'); ?>
