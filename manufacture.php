<?php
require_once('includes/load.php');
//page_require_level(2);
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

$products = find_by_sql("SELECT id, name FROM products WHERE is_bom = 1");

/* ===============================
   SAVE MANUFACTURE
================================ */
if(isset($_POST['save_manufacture'])){

    global $db;

    $product_id = (int)$_POST['product_id'];
    $qty        = (float)$_POST['qty'];

    if($product_id <= 0 || $qty <= 0){

    $_SESSION['mfg_error'] =
    "Please select device and enter valid quantity";

    redirect('manufacture.php', false);
    exit;
}
    $ref_no = "MFG".time();

    $db->query("START TRANSACTION");

    /* ===== Fetch BOM ===== */
    $bom_items = find_by_sql("
        SELECT raw_product_id, quantity
        FROM bom
        WHERE product_id = {$product_id}
    ");

    if(!$bom_items){

    $_SESSION['mfg_error'] =
    "No BOM found for this product";

    $db->query("ROLLBACK");

    redirect('manufacture.php', false);

    exit;
}

    foreach($bom_items as $b){

        $raw_id  = (int)$b['raw_product_id'];
        $per_unit = (float)$b['quantity'];

        $total_required = $per_unit * $qty;

        $product_data = find_by_id('products', $raw_id);

        $gst_id = (int)$product_data['gst_id'];

        /* ===== Ledger Based Stock Check ===== */
/* ===== Transaction Based Stock Check ===== */

$stock_data = find_by_sql("

SELECT

IFNULL(SUM(
    CASE
    WHEN transaction_type = (1,4)
    THEN quantity
    ELSE 0
    END
),0)

-

IFNULL(SUM(
    CASE
    transaction_type IN (2, 3, 5, 6)
    THEN quantity
    ELSE 0
    END
),0)

AS current_stock

FROM transaction_master

WHERE product_id = {$raw_id}

");

$current_stock = (float)$stock_data[0]['current_stock'];

        if($current_stock < $total_required){
            $db->query("ROLLBACK");
            $raw_product = find_by_id('products', $raw_id);

            $_SESSION['mfg_error'] =
            "Insufficient stock for ".$raw_product['name'];


            redirect('manufacture.php', false);
            exit;
        }

/* ===== Deduct Raw Material ===== */

if(!$db->query("

INSERT INTO transaction_master
(
    product_id,
    bill_indent_no,
    entry_date,
    quantity,
    gst_id,
    transaction_type,
    comments,
    created_at
)

VALUES
(
    {$raw_id},
    '{$ref_no}',
    NOW(),
    {$total_required},
    {$gst_id},
    6,
    'Manufacture Raw Material',
    NOW()
)

")){

    $db->query("ROLLBACK");
    $_SESSION['mfg_error'] =
    "Raw material deduction failed";

    redirect('manufacture.php', false);

    exit;
}
    }

/* ===== Add Finished Goods ===== */

$fg_data = find_by_id('products', $product_id);

$fg_gst_id = (int)$fg_data['gst_id'];

if(!$db->query("

INSERT INTO transaction_master
(
    product_id,
    bill_indent_no,
    entry_date,
    quantity,
    gst_id,
    transaction_type,
    comments,
    created_at
)

VALUES
(
    {$product_id},
    '{$ref_no}',
    NOW(),
    {$qty},
    {$fg_gst_id},
    1,
    'Manufactured Finished Goods',
    NOW()
)

")){

    $db->query("ROLLBACK");
    $_SESSION['mfg_error'] =
    "Finished goods insert failed";


    redirect('manufacture.php', false);

    exit;
}

    $db->query("COMMIT");

    $session->msg("s", "Manufacturing Successful");

redirect('manufacture.php', false);

exit;
}
?>

<?php include_once('layouts/header.php'); ?>

<style>
#bom_container table thead{
    background:#1f2d3d;
}

#bom_container table thead th{
    color:#fff !important;
    font-weight:600;
    border-color:#31445c;
}

#bom_container table tbody td{
    vertical-align:middle;
    font-size:13px;
}

#bom_container .table{
    margin-bottom:15px;
}

#bom_container .alert-info{
    margin-bottom:0;
}


.btn-round{
    border-radius:30px;
    padding:8px 24px;
    font-weight:600;
}

</style>

<div class="row">

    <!-- LEFT SIDE -->
    <div class="col-md-4">
<div class="panel panel-default">
<div class="panel-heading">
<strong>Manufacture Device</strong>
</div>

<div class="panel-body">
<form method="post">

<div class="form-group">
<label>Select Device</label>
<select name="product_id" id="product_id" class="form-control" required>
<option value="">Select</option>
<?php foreach($products as $p){ ?>
<option value="<?= $p['id']; ?>">
<?= $p['name']; ?>
</option>
<?php } ?>
</select>
</div>


<div class="form-group">
<label>Quantity</label>
<input type="number"
       name="qty"
       class="form-control"
       min="1"
       value="1"
       step="1"
       required>
</div>

<br>

<button type="submit"
        name="save_manufacture"
        class="btn btn-success btn-round">
    Manufacture
</button>

</form>

</div>
</div>
</div>


<!-- RIGHT SIDE -->
<div class="col-md-8">

<div class="panel panel-default" id="bom_panel" style="display:none;">

<div class="panel-heading">
<strong>BOM Details</strong>
</div>

<div class="panel-body">

<div id="bom_container" style="display:none;">

<table class="table table-bordered table-hover">

<thead style="background:#1f2d3d; color:#fff;">

<tr>

<th style="color:#fff;">Raw Material</th>

<th style="color:#fff;">Qty / Unit</th>

<th style="color:#fff;">Current Stock</th>

</tr>

</thead>

<tbody id="bom_body"></tbody>

</table>

<div class="alert alert-info">

Maximum Manufacturable :

<strong id="max_qty">0</strong>

</div>

</div>

</div>

</div>

</div>

</div>
<?php include_once('layouts/footer.php'); ?>

<script>
$(document).ready(function () {

    $('#product_id').on('change', function () {

        var product_id = $(this).val();

        if(product_id==''){
            $('#bom_container').hide();
            $('#bom_panel').hide();
            $('#bom_body').html('');
            $('#max_qty').text('0');
            return;
        }

        $.ajax({
            url:'get_bom.php',
            type:'GET',
            data:{product_id:product_id},
            dataType:'json',

            success:function(response){

                var html='';

                $.each(response.items,function(i,item){

                    html += '<tr>';
                    html += '<td>'+item.name+'</td>';
                    html += '<td>'+item.quantity+'</td>';
                    html += '<td>'+Number(item.stock).toFixed(2)+'</td>';
                    html += '</tr>';

                });

                $('#bom_body').html(html);
                $('#max_qty').text(response.max);
                $('#bom_container').show();
                $('#bom_panel').show();

            },

            error:function(xhr){

                console.log(xhr.responseText);

            }

        });

    });

});
</script>

<?php if(isset($_SESSION['mfg_error'])){ ?>

<script>
Swal.fire({
    icon: 'error',
    title: 'Manufacture Failed',
    text: '<?= addslashes($_SESSION['mfg_error']); ?>',
    confirmButtonColor: '#d33'
});
</script>

<?php unset($_SESSION['mfg_error']); } ?>

<?php
$msg = $session->msg();
if($msg){
?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Manufacturing Successful',
    text: 'Finished goods have been created successfully.',
    timer: 1800,
    showConfirmButton: false
});
</script>
<?php } ?>
