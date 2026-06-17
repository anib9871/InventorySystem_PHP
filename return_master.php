<?php
$page_title = 'Return Master';
require_once('includes/load.php');

$products  = find_all('products');
$suppliers = find_all('supplier_master');
$customers = find_all('customer_master');

$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_return = null;

if($edit_id > 0)
{
    $edit_data = find_by_sql("
    SELECT *
    FROM transaction_master
    WHERE transaction_id='{$edit_id}'
    AND transaction_type IN (3,4,5)
    LIMIT 1
    ");

    if(!empty($edit_data))
    {
        $edit_return = $edit_data[0];
    }
}


$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$view_return = null;

if($view_id > 0)
{
    $view_data = find_by_sql("
    SELECT tm.*,
           p.name product_name
    FROM transaction_master tm
    LEFT JOIN products p
    ON p.id = tm.product_id
    WHERE tm.transaction_id='{$view_id}'
    LIMIT 1
    ");

    if(!empty($view_data))
    {
        $view_return = $view_data[0];
    }
}

/* SAVE RETURN */

if(isset($_POST['save_return']))
{
    global $db;

    $return_type = (int)$_POST['return_type'];

    $supplier_id = isset($_POST['supplier_id'])
        ? (int)$_POST['supplier_id']
        : 0;

    $customer_id = isset($_POST['customer_id'])
        ? (int)$_POST['customer_id']
        : 0;

    $product_id = (int)$_POST['product_id'];
    $qty        = (float)$_POST['qty'];
    $remarks    = remove_junk($db->escape($_POST['remarks']));

    $refund_required =
    isset($_POST['refund_required']) ? 1 : 0;

    $credit_note_required =
    isset($_POST['credit_note_required']) ? 1 : 0;

   $discarded = 0;

    if($product_id <= 0 || $qty <= 0)
    {
        $session->msg('d','Invalid Data');
        redirect('return_master.php',false);
    }

    if($return_type <= 0)
    {
        $session->msg('d','Please Select Return Type');
        redirect('return_master.php',false);
    }

    if($return_type == 1 && $supplier_id <= 0)
    {
        $session->msg('d','Please Select Supplier');
        redirect('return_master.php',false);
    }

    if($return_type == 2 && $customer_id <= 0)
    {
        $session->msg('d','Please Select Customer');
        redirect('return_master.php',false);
    }

    $product = find_by_id('products',$product_id);

    $gst_id = (int)$product['gst_id'];

    if($return_type == 1)
    {
        /* Return To Supplier */
        $credit_amount =
        (float)$product['buy_price'] * $qty;
    }
    else
    {
        /* Return From Customer */
        $credit_amount =
        (float)$product['sale_price'] * $qty;
    }

    /* RETURN TO SUPPLIER */

    if($return_type == 1)
    {
        $transaction_type = 3;

        $from_dept = 'STORE';
        $to_dept   = 'SUPPLIER';

        /* CHECK AVAILABLE STOCK */

        $stock = find_by_sql("
        SELECT quantity
        FROM inventory
        WHERE product_id = '{$product_id}'
        ORDER BY inventory_id DESC
        LIMIT 1
        ");

        if(empty($stock))
        {
            $session->msg('d','Stock Record Not Found');
            redirect('return_master.php',false);
        }

        if($stock[0]['quantity'] < $qty)
        {
            $session->msg('d','Insufficient Stock Available');
            redirect('return_master.php',false);
        }

        $db->query("
        INSERT INTO transaction_master
        (
            product_id,
            supplier_id,
            quantity,
            gst_id,
            transaction_type,
            from_dept,
            to_dept,
            comments,
            refund_required,
            credit_note_required,
            discarded,
            entry_date
        )
        VALUES
        (
            '{$product_id}',
            '{$supplier_id}',
            '{$qty}',
            '{$gst_id}',
            '{$transaction_type}',
            '{$from_dept}',
            '{$to_dept}',
            '{$remarks}',
            '{$refund_required}',
            '{$credit_note_required}',
            0,
            NOW()
        )
        ");

        $transaction_id = $db->insert_id();

        /* STOCK REMOVE FROM INVENTORY */

        $db->query("
        UPDATE inventory
        SET quantity = quantity - {$qty}
        WHERE product_id = '{$product_id}'
        ORDER BY inventory_id DESC
        LIMIT 1
        ");
    }

    /* RETURN FROM CUSTOMER */

    elseif($return_type == 2)
    {
        $transaction_type = 4;

        $from_dept = 'CUSTOMER';
        $to_dept   = 'STORE';

        $discarded = 0;
    }
    elseif($return_type == 3)
    {
        $transaction_type = 5;

        $from_dept = 'STORE';
        $to_dept   = 'DISCARD';

        $discarded = 1;

    $db->query("
    INSERT INTO transaction_master
    (
        product_id,
        quantity,
        gst_id,
        transaction_type,
        from_dept,
        to_dept,
        comments,
        refund_required,
        credit_note_required,
        discarded,
        entry_date
    )
    VALUES
    (
        '{$product_id}',
        '{$qty}',
        '{$gst_id}',
        '{$transaction_type}',
        '{$from_dept}',
        '{$to_dept}',
        '{$remarks}',
        0,
        0,
        1,
        NOW()
    )
    ");

    $transaction_id = $db->insert_id();

    $db->query("
    UPDATE inventory
    SET quantity = quantity - {$qty}
    WHERE product_id = '{$product_id}'
    ORDER BY inventory_id DESC
    LIMIT 1
    ");

    $session->msg('s','Discard Saved Successfully');
    redirect('return_master.php',false);

    }

        $db->query("
        INSERT INTO transaction_master
        (
            product_id,
            customer_id,
            quantity,
            gst_id,
            transaction_type,
            from_dept,
            to_dept,
            comments,
            refund_required,
            credit_note_required,
            discarded,
            entry_date
        )
        VALUES
        (
            '{$product_id}',
            '{$customer_id}',
            '{$qty}',
            '{$gst_id}',
            '{$transaction_type}',
            '{$from_dept}',
            '{$to_dept}',
            '{$remarks}',
            '{$refund_required}',
            '{$credit_note_required}',
            '{$discarded}',
            NOW()
        )
        ");

$transaction_id = $db->insert_id();

/* INVENTORY ENTRY ONLY IF NOT DISCARDED */

if($discarded == 0)
{
    $db->query("
    INSERT INTO inventory
    (
        transaction_id,
        product_id,
        quantity,
        origin_dept,
        status,
        updated_at
    )
    VALUES
    (
        '{$transaction_id}',
        '{$product_id}',
        '{$qty}',
        'CUSTOMER',
        1,
        NOW()
    )
    ");
}



    /* CREDIT NOTE */

    if($credit_note_required == 1)
    {
        $party_type =
        ($return_type == 1)
        ? 'SUPPLIER'
        : 'CUSTOMER';

        $party_id =
        ($return_type == 1)
        ? $supplier_id
        : $customer_id;

        $credit_no =
        'CN'.date('YmdHis');

        $db->query("
        INSERT INTO credit_notes
        (
            transaction_id,
            party_type,
            party_id,
            amount,
            credit_note_no,
            remarks,
            created_at
        )
            VALUES
            (
                '{$transaction_id}',
                '{$party_type}',
                '{$party_id}',
                '{$credit_amount}',
                '{$credit_no}',
                '{$remarks}',
                NOW()
            )
        ");
    }

    $session->msg('s','Return Saved Successfully');
    redirect('return_master.php',false);
}

if(isset($_POST['update_return']))
{
    $transaction_id = (int)$_POST['transaction_id'];

    $qty = (float)$_POST['qty'];

    $remarks =
    remove_junk(
    $db->escape($_POST['remarks'])
    );

    $refund_required =
    isset($_POST['refund_required']) ? 1 : 0;

    $credit_note_required =
    isset($_POST['credit_note_required']) ? 1 : 0;

    $discarded = ($edit_return && $edit_return['transaction_type']==5) ? 1 : 0;

    $db->query("
    UPDATE transaction_master
    SET
        quantity='{$qty}',
        comments='{$remarks}',
        refund_required='{$refund_required}',
        credit_note_required='{$credit_note_required}',
        discarded='{$discarded}',
        updated_at=NOW()
    WHERE transaction_id='{$transaction_id}'
    ");

    $session->msg(
    's',
    'Return Updated Successfully'
    );

    redirect(
    'return_master.php',
    false
    );
}

include_once('layouts/header.php');
?>

<div class="row">

<div class="panel panel-default">

<div class="panel-heading">
<strong>Return History</strong>
</div>

<div class="panel-body">

<div class="col-md-6">

<div class="panel panel-default">

<div class="panel-heading">
<strong>Return Entry</strong>
</div>

<div class="panel-body">

<?php echo display_msg($msg); ?>

<form method="post">

<?php if($edit_return): ?>

<input type="hidden"
name="transaction_id"
value="<?php echo $edit_return['transaction_id']; ?>">

<?php endif; ?>

<div class="form-group">
<label>Return Type</label>

<select name="return_type"
id="return_type"
class="form-control"
required>

<option value="">Select</option>

<option value="1"
<?php
if($edit_return &&
$edit_return['transaction_type']==3)
echo 'selected';
?>>
Return To Supplier
</option>

<option value="2"
<?php
if($edit_return &&
$edit_return['transaction_type']==4)
echo 'selected';
?>>
Return From Customer
</option>

<option value="3"
<?php
if($edit_return &&
$edit_return['transaction_type']==5)
echo 'selected';
?>>
Discard
</option>

</select>
</div>


<!-- Supplier -->

<div class="form-group"
id="supplier_div"
style="display:none;">

<label>Supplier</label>

<select name="supplier_id"
class="form-control">

<option value="">Select Supplier</option>

<?php foreach($suppliers as $s): ?>

<option value="<?php echo $s['id']; ?>">
<?php echo $s['supplier_name']; ?>
</option>

<?php endforeach; ?>

</select>

</div>


<!-- Customer -->

<div class="form-group"
id="customer_div"
style="display:none;">

<label>Customer</label>

<select name="customer_id"
class="form-control">

<option value="">Select Customer</option>

<?php foreach($customers as $c): ?>

<option value="<?php echo $c['id']; ?>">
<?php echo $c['customer_name']; ?>
</option>

<?php endforeach; ?>

</select>

</div>


<div class="form-group">

<label>Product</label>

<select name="product_id"
class="form-control"
required>

<option value="">Select Product</option>

<?php foreach($products as $p): ?>

<option value="<?php echo $p['id']; ?>"
<?php
if(
$edit_return &&
$edit_return['product_id']==$p['id']
)
echo 'selected';
?>>

<?php echo $p['name']; ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="form-group">

<label>Quantity</label>

<input type="number"
step="0.01"
name="qty"
class="form-control"
required
value="<?php
echo $edit_return
? $edit_return['quantity']
: '';
?>">

</div>


<div class="checkbox">

<label>
<input type="checkbox"
name="refund_required"
value="1"
<?php
if(
$edit_return &&
$edit_return['refund_required']==1
)
echo 'checked';
?>>

Refund Required
</label>

</div>


<div class="checkbox">

<label>
<input type="checkbox"
name="credit_note_required"
value="1"
<?php
if(
$edit_return &&
$edit_return['credit_note_required']==1
)
echo 'checked';
?>>

Credit Note Required
</label>

</div>



<div class="form-group">

<label>Remarks</label>

<textarea
name="remarks"
class="form-control"
rows="4"><?php
echo $edit_return
? $edit_return['comments']
: '';
?></textarea>

</div>

<?php if($edit_return): ?>

<button type="submit"
name="update_return"
class="btn btn-primary">

Update Return

</button>

<a href="return_master.php"
class="btn btn-default">

Cancel Edit

</a>

<?php else: ?>

<button type="submit"
name="save_return"
class="btn btn-success">

Save Return

</button>

<?php endif; ?>

</form>

</div>
</div>
</div>

<div class="col-md-6">
<script>

document.getElementById('return_type')
.addEventListener('change',function(){

let val = this.value;

document.getElementById('supplier_div')
.style.display = 'none';

document.getElementById('customer_div')
.style.display = 'none';

if(val == '1')
{
    document.getElementById('supplier_div')
    .style.display = 'block';
}

if(val == '2')
{
    document.getElementById('customer_div')
    .style.display = 'block';

   
}

});

window.onload = function(){

let val =
document.getElementById('return_type').value;

if(val == '1')
{
    document.getElementById('supplier_div')
    .style.display = 'block';
}

if(val == '2')
{
    document.getElementById('customer_div')
    .style.display = 'block';
}

};

</script>

<?php

$returns = find_by_sql("
SELECT tm.*,
p.name product_name
FROM transaction_master tm
LEFT JOIN products p
ON p.id = tm.product_id
WHERE tm.transaction_type IN (3,4,5)
ORDER BY tm.transaction_id DESC
");

?>

<table class="table table-bordered">

<thead>

<tr>
<th>#</th>
<th>Product</th>
<th>Qty</th>
<th>Type</th>
<th>Date</th>
<th>Action</th>
</tr>

</thead>

<tbody>

<?php
$i = 1;
foreach($returns as $r):
?>

<tr>

<td><?php echo $i++; ?></td>

<td><?php echo $r['product_name']; ?></td>

<td><?php echo $r['quantity']; ?></td>

<td>

<?php
if($r['transaction_type']==3)
    echo 'Supplier Return';
elseif($r['transaction_type']==4)
    echo 'Customer Return';
else
    echo 'Discard';
?>

</td>

<td><?php echo $r['entry_date']; ?></td>

<td>

<a href="return_master.php?edit=<?php echo $r['transaction_id']; ?>"
class="btn btn-primary btn-xs">

Edit

</a>

<a href="return_master.php?view=<?php echo $r['transaction_id']; ?>"
class="btn btn-info btn-xs">
View
</a>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>
</div>

</div>
</div>

<?php if($view_return): ?>

<div class="row">
<div class="col-md-12">

<div class="panel panel-info">

<div class="panel-heading">
<strong>Return Details</strong>
</div>

<div class="panel-body">

<table class="table table-bordered">

<tr>
<th>Product</th>
<td><?php echo $view_return['product_name']; ?></td>
</tr>

<tr>
<th>Quantity</th>
<td><?php echo $view_return['quantity']; ?></td>
</tr>

<tr>
<th>Type</th>
<td>
<?php
if($view_return['transaction_type']==3)
    echo 'Supplier Return';
elseif($view_return['transaction_type']==4)
    echo 'Customer Return';
else
    echo 'Discard';
?>
</td>
</tr>

<tr>
<th>Date</th>
<td><?php echo $view_return['entry_date']; ?></td>
</tr>

<tr>
<th>Remarks</th>
<td><?php echo $view_return['comments']; ?></td>
</tr>

<tr>
<th>Refund Required</th>
<td><?php echo $view_return['refund_required'] ? 'Yes' : 'No'; ?></td>
</tr>

<tr>
<th>Credit Note Required</th>
<td><?php echo $view_return['credit_note_required'] ? 'Yes' : 'No'; ?></td>
</tr>

<tr>
<th>Discarded</th>
<td><?php echo $view_return['discarded'] ? 'Yes' : 'No'; ?></td>
</tr>

</table>

<a href="return_master.php"
class="btn btn-default">
Close
</a>

</div>
</div>

</div>
</div>

<?php endif; ?>

<?php include_once('layouts/footer.php'); ?>
