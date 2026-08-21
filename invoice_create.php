<?php
require_once('includes/load.php');
$system = $_GET['system'] ?? 'billing';
//page_require_level(2);
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

$center_id = $_SESSION['center_id'] ?? 0;

if($_SESSION['role_id'] == 3){
    $customers = find_by_sql("
    SELECT * FROM customer_master
    WHERE center_id = '$center_id'
    ");
}else{
    $customers = find_all('customer_master');
}

$products = find_by_sql("
    SELECT 
        p.id,
        p.name,
        p.sale_price,
        p.type,
        g.gst_percent,
        (
            COALESCE(SUM(
                CASE 
                    WHEN t.transaction_type IN (1,4) THEN t.quantity 
                    WHEN t.transaction_type IN (2,3,5,6) THEN -t.quantity 
                    ELSE 0 
                END
            ), 0)
            -
            COALESCE((
                SELECT SUM(d.qty) 
                FROM demo_item_detail d 
                WHERE d.product_id = p.id AND d.status = 1
            ), 0)
        ) AS current_stock
    FROM products p
    LEFT JOIN transaction_master t ON p.id = t.product_id
    LEFT JOIN gst_master g ON p.gst_id = g.id
    WHERE p.is_active = 1
    GROUP BY p.id, p.name, p.sale_price, p.type, g.gst_percent
");
$payment_modes = find_by_sql("SELECT id, mode_name FROM payment_mode_master WHERE is_active = 1");

/* TERMS & CONDITIONS */
$terms_templates = find_all('terms_conditions_master');

$rate_map = [];
$rates = find_by_sql("
    SELECT r.product_id, r.rate, g.gst_percent
    FROM rate_master r
    LEFT JOIN gst_master g ON g.id = r.gst_id
");

foreach($rates as $r){
    $rate_map[$r['product_id']] = $r;
}

/* SAVE INVOICE */
if(isset($_POST['save_invoice'])){

    if($_SESSION['role_id'] == 2){
       $center_id = (int)$_POST['center_id'];
    }else{
       $center_id = (int)$_SESSION['center_id'];
    }
    global $db;

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $db->query("START TRANSACTION");

    $debug = [];
    $debug[] = "========================";
    $debug[] = "INVOICE CREATE DEBUG";
    $debug[] = date("Y-m-d H:i:s");
    $debug[] = "System : ".$system;
    $debug[] = "POST : ".json_encode($_POST);

    try {

        /* ===== GET NEXT INVOICE NUMBER FROM SEQUENCE ===== */
        $fy = find_by_sql("
        SELECT fy_id, fy_name
        FROM financial_year_master
        LIMIT 1
        ");

        $fy_id = $fy[0]['fy_id'];

        $seq = find_by_sql("
        SELECT *
        FROM sequence_master
        WHERE sequence_category='invoice'
        AND fy_id='$fy_id'
        LIMIT 1
        ");

        if($seq){
            $seq = $seq[0];
            $next = $seq['last_no'] + 1;

            $db->query("
            UPDATE sequence_master
            SET last_no = '$next'
            WHERE sequence_category = 'invoice'
            AND fy_id = '$fy_id'
            ");
        }else{
            $next = 1;
            $db->query("
            INSERT INTO sequence_master
            (
                sequence_category,
                fy_id,
                last_no
            )
            VALUES
            (
                'invoice',
                '$fy_id',
                1
            )
            ");
        }

        $fy_name = substr($fy[0]['fy_name'], 2);
        $inv_no = $fy_name . "/" . $next;

        /* CALCULATE TOTAL PAID */
        $total_paid = 0;
        if(isset($_POST['payment_amount']) && is_array($_POST['payment_amount'])){
            foreach($_POST['payment_amount'] as $amt){
                $total_paid += (float)$amt;
            }
        }

        $doc_type = ($total_paid > 0) ? 'TAX_INVOICE' : 'PROFORMA';
        $cust = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;

        if($system != 'inventory'){
            if($cust <= 0){
                $name  = remove_junk($db->escape($_POST['manual_name']));
                $phone = remove_junk($db->escape($_POST['manual_phone']));
                $gst   = remove_junk($db->escape($_POST['manual_gst']));
                $addr  = remove_junk($db->escape($_POST['manual_address']));

                if($name == ""){
                    echo "<script>
                    alert('Please enter customer name');
                    window.location='invoice_create.php';
                    </script>";
                    exit;
                }

                if($phone != ""){
                    $check = find_by_sql("SELECT id FROM customer_master WHERE contact_no='$phone' LIMIT 1");
                    if($check){
                        $cust = $check[0]['id'];
                    }else{
                        $db->query("INSERT INTO customer_master
                        (customer_name, contact_no, address, gst_no, center_id)
                        VALUES
                        ('$name', '$phone', '$addr', '$gst', '$center_id')");
                        $cust = $db->insert_id();
                    }
                }else{
                    $db->query("INSERT INTO customer_master
                    (customer_name, contact_no, address, gst_no, center_id)
                    VALUES
                    ('$name', '', '$addr', '$gst', '$center_id')");
                    $cust = $db->insert_id();
                }
            }
        }else{
            if($cust <= 0){
                echo "<script>
                alert('Please select customer');
                window.location='invoice_create.php';
                </script>";
                exit;
            }
        }

        if(!isset($_SESSION['org_id'])){
            echo "<script>
            alert('Session expired. Please login again');
            window.location='index.php';
            </script>";
            exit;
        }

        $org_id = $_SESSION['org_id'];

        /* STATE CODE CHECK */
        $org_data = find_by_sql("
        SELECT gst_no 
        FROM organization_master 
        WHERE id = '{$org_id}'
        ");
        $cust_data = find_by_sql("SELECT gst_no FROM customer_master WHERE id = $cust");

        $cust_gst = '';
        if(!empty($cust_data)){
            $cust_gst = $cust_data[0]['gst_no'] ?? '';
        }

        $org_gst = '';
        if(!empty($org_data)){
            $org_gst = $org_data[0]['gst_no'] ?? '';
        }

        $org_state_code = substr($org_gst, 0, 2);
        $cust_state_code = substr($cust_gst, 0, 2);

        /* Determine GST Mode */
        $tax_mode = 'CGST_SGST';

        if(isset($_POST['product_id']) && count($_POST['product_id']) > 0){
            foreach($_POST['product_id'] as $pid){
                $product = find_by_id('products', $pid);
                if(in_array($product['type'], [1,2])){
                    if(trim((string)$org_state_code) !== trim((string)$cust_state_code)){
                        $tax_mode = 'IGST';
                    }else{
                        $tax_mode = 'CGST_SGST';
                    }
                    break;
                }
            }
        }

        if (!empty($_POST['invoice_date'])) {
            $qdate = $_POST['invoice_date'];
            $formats = ['d/M/Y', 'd-m-Y', 'Y-m-d'];
            foreach ($formats as $format) {
                $dt = DateTime::createFromFormat($format, $qdate);
                if ($dt instanceof DateTime) {
                    $qdate = $dt->format('Y-m-d');
                    break;
                }
            }
        } else {
            $qdate = date('Y-m-d');
        }

        $gst_type = isset($_POST['gst_type']) ? $_POST['gst_type'] : 'inclusive';

        $subtotal  = 0;
        $net_total = 0;
        $total_gst = 0;

        /* INSERT MASTER */
        $insertMaster = $db->query("
        INSERT INTO invoice
        (
        invoice_no,
        invoice_date,
        customer_id,
        organization_id,
        quotation_id,
        subtotal,
        gst_total,
        net_total,
        paid_amount,
        due_amount,
        payment_status,
        gst_type,
        remarks,
        terms_conditions,
        created_at
        )
        VALUES
        (
        '$inv_no',
        '$qdate',
        '$cust',
        '$org_id',
        NULL,
        0,
        0,
        0,
        0,
        0,
        'Unpaid',
        '$gst_type',
        '$doc_type',
        '".$db->escape($_POST['terms_conditions'])."',
        NOW()
        )
        ");

        if(!$insertMaster){
            echo "<script>alert('Master Insert Error');window.location='invoice_create.php';</script>";
            exit;
        }

        $qid = $db->insert_id();
        $debug[] = "Invoice ID : ".$qid;

        if(!$qid){
            echo "<script>
            alert('Something went wrong while saving invoice');
            window.location='invoice_create.php';
            </script>";
            exit;
        }

        if(!isset($_POST['product_id']) || count($_POST['product_id']) == 0){
            $db->query("DELETE FROM invoice WHERE id = $qid");
            echo "<script>
            alert('Something went wrong while saving invoice');
            window.location='invoice_create.php';
            </script>";
            exit;
        }

        /* INSERT ITEMS */
        $itemInserted = false;

        foreach($_POST['product_id'] as $i => $pid){
            $pid = (int)$pid;

            if($pid <= 0){
                continue;
            }

            $qty  = isset($_POST['qty'][$i]) ? (float)$_POST['qty'][$i] : 0;
            $base = isset($_POST['rate'][$i]) ? (float)$_POST['rate'][$i] : 0;
            $gst = 0;

            if($gst_enabled == "Yes"){
               $gst = isset($_POST['gst'][$i]) ? (float)$_POST['gst'][$i] : 0;
            }
            $disc = isset($_POST['discount'][$i]) ? (float)$_POST['discount'][$i] : 0;

            $product = find_by_id('products',$pid);

            if($product['type'] == 1){
                $stock_row = find_by_sql("
                SELECT 
                (
                    COALESCE(SUM(
                        CASE
                            WHEN transaction_type IN (1,4) THEN quantity
                            WHEN transaction_type IN (2,3,5,6) THEN -quantity
                            ELSE 0
                        END
                    ), 0)
                    -
                    COALESCE((
                        SELECT SUM(qty) 
                        FROM demo_item_detail 
                        WHERE product_id = {$pid} AND status = 1
                    ), 0)
                ) AS stock
                FROM transaction_master
                WHERE product_id = {$pid}
                ");

                $current_stock = (float)($stock_row[0]['stock'] ?? 0);

                if($qty > $current_stock){
                    $db->query("DELETE FROM invoice WHERE id = $qid");
                    echo "<script>
                    alert('Stock not available. Available stock: ".$current_stock."');
                    window.history.back();
                    </script>";
                    exit;
                }
            }

            if($qty <= 0 || $base <= 0){
                continue;
            }

            $itemInserted = true;
            $line_base = $qty * $base;
            $discounted_base = $line_base - $disc;

            if($gst_type == "exclusive"){
                if($tax_mode == 'IGST'){
                    $igst_amount = $discounted_base * $gst / 100;
                    $cgst_amount = 0;
                    $sgst_amount = 0;
                    $gst_amount  = $igst_amount;
                }else{
                    $cgst_amount = ($discounted_base * $gst / 100) / 2;
                    $sgst_amount = ($discounted_base * $gst / 100) / 2;
                    $igst_amount = 0;
                    $gst_amount  = $cgst_amount + $sgst_amount;
                }

                $rate_incl  = $base + ($base * $gst / 100);
                $line_total = $discounted_base + $gst_amount;
            }else{
                $gst_amount = $discounted_base - ($discounted_base / (1 + $gst/100));

                if($tax_mode == 'IGST'){
                    $igst_amount = $gst_amount;
                    $cgst_amount = 0;
                    $sgst_amount = 0;
                }else{
                    $cgst_amount = $gst_amount / 2;
                    $sgst_amount = $gst_amount / 2;
                    $igst_amount = 0;
                }

                $rate_incl  = $base;
                $line_total = $discounted_base;
            }

            $total_gst += $gst_amount;
            $subtotal  += $line_base;
            $net_total += $line_total;

            $insertItem = $db->query("
            INSERT INTO invoice_items
            (invoice_id, product_id, qty, rate_excl_gst,
            discount_amount, gst_percent, rate_incl_gst, 
            cgst_amount, sgst_amount, igst_amount, line_total)
            VALUES
            ($qid, $pid, $qty, $base,
            $disc, $gst, $rate_incl,
            $cgst_amount, $sgst_amount, $igst_amount, $line_total)
            ");

            if(!$insertItem){
                $db->query("DELETE FROM invoice WHERE id = $qid");
                echo "<script>
                alert('Item Insert Error');
                window.location='invoice_create.php';
                </script>";
                exit;
            }

            /* TRANSACTION MASTER ENTRY */
            $trans = $db->query("
            INSERT INTO transaction_master
            (
            product_id,
            supplier_id,
            bill_indent_no,
            entry_date,
            bill_indent_date,
            quantity,
            free_qty,
            unit,
            rate_id,
            gst_id,
            unit_price,
            gst_amount,
            discount_amount,
            net_price,
            mrp,
            misc_amount,
            sale_amount,
            sale_gst,
            sale_net,
            transaction_type,
            status,
            payment_status,
            payment_mode,
            amount_received,
            balance_amount,
            from_dept,
            to_dept,
            comments,
            created_at,
            center_id
            )
            VALUES
            (
            '$pid',
            NULL,
            '$inv_no',
            NOW(),
            NOW(),
            '$qty',
            0,
            'PCS',
            0,
            0,
            '$base',
            '$gst_amount',
            '$disc',
            '$line_total',
            0,
            0,
            '$discounted_base',
            '$gst_amount',
            '$line_total',
            2,
            1,
            0,
            NULL,
            0,
            0,
            'STORE',
            'CUSTOMER',
            'Sale Invoice',
            NOW(),
            '$center_id'
            )
            ");

            /* DEMO DETAILS UPDATE */
            $demo_row = find_by_sql("
                SELECT id, qty FROM demo_item_detail 
                WHERE customer_id = '$cust' AND product_id = '$pid' AND status = 1 
                LIMIT 1
            ");

            if(!empty($demo_row)){
                $demo_id = $demo_row[0]['id'];
                $old_qty = (float)$demo_row[0]['qty'];
                $new_qty = $old_qty - $qty;

                if($new_qty <= 0){
                    $db->query("UPDATE demo_item_detail SET qty = 0, status = 0 WHERE id = '$demo_id'");
                } else {
                    $db->query("UPDATE demo_item_detail SET qty = '$new_qty' WHERE id = '$demo_id'");
                }
            }

            if(!$trans){
                echo "<script>alert('Transaction Error');window.location='invoice_create.php';</script>";
                exit;
            }
        }

        if(!$itemInserted){
            $db->query("DELETE FROM invoice WHERE id = $qid");
            echo "<script>
            alert('Please select at least one valid product');
            window.location='invoice_create.php';
            </script>";
            exit;
        }

        /* UPDATE TOTALS */
        $due_amount = round($net_total - $total_paid, 2);

        if($due_amount <= 0){
            $due_amount = 0;
            $payment_status = "Paid";
        }elseif($total_paid > 0){
            $payment_status = "Partial";
        }else{
            $payment_status = "Unpaid";
        }

        $gst_total = $total_gst;

        $update = $db->query("
        UPDATE invoice SET
        subtotal = '$subtotal',
        gst_total = '$gst_total',
        net_total = '$net_total',
        paid_amount = '$total_paid',
        due_amount = '$due_amount',
        payment_status = '$payment_status'
        WHERE id = '$qid'
        ");

        if(!$update){
            throw new Exception("Invoice Update Failed : ".$db->error);
        }

        if($system == 'billing' && isset($_POST['payment_amount'])){
            foreach($_POST['payment_amount'] as $mode_id => $amt){
                $amt = (float)$amt;
                if($amt > 0){
                    $pm = find_by_id('payment_mode_master', $mode_id);
                    $mode_name = $pm['mode_name'];
                    $utr = $_POST['utr_no'][$mode_id] ?? '';

                    $db->query("
                    INSERT INTO payments
                    (
                    invoice_id,
                    customer_id,
                    payment_mode,
                    amount,
                    reference_no,
                    center_id
                    )
                    VALUES
                    (
                    $qid,
                    $cust,
                    '$mode_name',
                    '$amt',
                    '".$db->escape($utr)."',
                    '$center_id'
                    )
                    ");
                }
            }
        }

        /* LEDGER ENTRY */
        $db->query("
        INSERT INTO ledger_entries (invoice_id, customer_id, account, type, amount)
        VALUES ($qid, $cust, 'CUSTOMER', 'DEBIT', '$net_total')
        ");

        $db->query("
        UPDATE customer_master 
        SET balance = balance + $net_total
        WHERE id = $cust
        ");

        $db->query("
        INSERT INTO ledger_entries (invoice_id, customer_id, account, type, amount)
        VALUES ($qid, $cust, 'SALES', 'CREDIT', '$net_total')
        ");

        if($system == 'billing' && isset($_POST['payment_amount'])){
            foreach($_POST['payment_amount'] as $mode_id => $amt){
                $amt = (float)$amt;
                if($amt > 0){
                    $pm = find_by_id('payment_mode_master', $mode_id);
                    $mode_name = strtoupper($pm['mode_name']);

                    $db->query("
                    INSERT INTO ledger_entries (invoice_id, customer_id, account, type, amount)
                    VALUES ($qid, $cust, '$mode_name', 'DEBIT', '$amt')
                    ");

                    $db->query("
                    INSERT INTO ledger_entries (invoice_id, customer_id, account, type, amount)
                    VALUES ($qid, $cust, 'CUSTOMER', 'CREDIT', '$amt')
                    ");

                    $db->query("
                    UPDATE customer_master 
                    SET balance = balance - $amt
                    WHERE id = $cust
                    ");
                }
            }
        }

        $db->query("COMMIT");

        file_put_contents(
            __DIR__.'/invoice_debug.log',
            implode(PHP_EOL,$debug).PHP_EOL.PHP_EOL,
            FILE_APPEND
        );

        echo "<script>
        window.location='invoice_print.php?id=".$qid."';
        </script>";
        exit;

    } catch(Exception $e){
        $db->query("ROLLBACK");

        $debug[] = "EXCEPTION : ".$e->getMessage();

        file_put_contents(
            __DIR__.'/invoice_debug.log',
            implode(PHP_EOL,$debug).PHP_EOL.PHP_EOL,
            FILE_APPEND
        );

        echo "<script>
        alert(" . json_encode($e->getMessage()) . ");
        window.location='invoice_create.php';
        </script>";

        exit;
    }
}
?>

<?php include_once('layouts/header.php'); ?>

<style>
/* Enterprise POS Terminal Pro */
:root {
  --navy-dark: #0f172a;
  --navy-light: #1e293b;
  --border-slate: #cbd5e1;
  --border-light: #e2e8f0;
  --blue-accent: #2563eb;
  --blue-hover: #1d4ed8;
  --emerald-accent: #059669;
  --emerald-hover: #047857;
  --danger-accent: #dc2626;
}

body {
  background-color: #f1f5f9;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Inter", sans-serif;
  color: #0f172a;
}

.invoice-terminal {
  padding: 8px 14px 14px;
}

.pos-card {
  background: #ffffff;
  border: 1px solid var(--border-slate);
  border-radius: 6px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  margin-bottom: 8px;
}

.pos-title-lbl {
  font-size: 10.5px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  margin-bottom: 2px;
  display: block;
}

/* Controls */
.form-control, .form-control-sm {
  height: 32px !important;
  border-radius: 4px !important;
  border: 1px solid var(--border-slate);
  font-size: 12px;
  font-weight: 600;
  color: #0f172a;
  box-shadow: none !important;
}

.form-control:focus {
  border-color: var(--blue-accent);
}

.input-group-btn .btn {
  height: 32px !important;
  border-radius: 0 4px 4px 0 !important;
  background: var(--blue-accent);
  color: #ffffff;
  border: 1px solid var(--blue-accent);
  padding: 0 10px;
}

.btn-top-trigger {
  height: 32px;
  font-size: 12px;
  font-weight: 700;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  width: 100%;
}

/* Full Width Billing Grid (Perfect Height: 220px) */
.grid-container-full {
  height: 220px !important;
  max-height: 220px !important;
  overflow-y: auto;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

#itemTable {
  width: 100%;
  min-width: 720px;
  margin-bottom: 0;
  border-collapse: collapse;
}

#itemTable thead th {
  background: var(--navy-dark) !important;
  color: #ffffff;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  padding: 8px 8px;
  position: sticky;
  top: 0;
  z-index: 10;
  border: none;
  white-space: nowrap;
}

#itemTable tbody tr td {
  padding: 0 !important;
  vertical-align: middle;
  border-top: 1px solid var(--border-light);
  background: #ffffff;
}

#itemTable tbody tr:hover td {
  background-color: #f8fafc;
}

#itemTable input.grid-cell {
  width: 100%;
  height: 34px !important;
  border: none !important;
  background: transparent !important;
  font-size: 12px;
  font-weight: 600;
  color: #0f172a;
  padding: 2px 8px;
  box-shadow: none !important;
  outline: none !important;
}

#itemTable input.grid-cell:focus {
  background: #eff6ff !important;
  color: var(--blue-hover) !important;
}

#itemTable input.grid-cell[readonly] {
  color: #334155;
  cursor: default;
}

.btn-del-cell {
  width: 22px;
  height: 22px;
  border-radius: 3px;
  background: #fee2e2;
  color: var(--danger-accent);
  border: 1px solid #fca5a5;
  font-size: 13px;
  line-height: 1;
  font-weight: bold;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  padding: 0;
  margin: auto;
}

.btn-del-cell:hover {
  background: var(--danger-accent);
  color: #ffffff;
}

.empty-grid-msg {
  padding: 50px 0 !important;
  text-align: center;
  color: #94a3b8;
  font-size: 12.5px;
  font-weight: 600;
}

/* 50/50 Bottom Summary Layout */
.summary-container {
  padding: 10px 14px;
}

.summary-metric-card {
  background: #f8fafc;
  border: 1px solid var(--border-light);
  border-radius: 5px;
  padding: 6px 14px;
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: space-around;
}

.summary-metric-card .stat-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 3px 0;
  font-size: 12px;
  border-bottom: 1px dashed var(--border-slate);
}

.summary-metric-card .stat-row:last-child {
  border-bottom: none;
}

.stat-row.net-bold strong {
  color: var(--blue-accent);
  font-size: 14.5px;
}

.stat-row.due-bold strong,
.stat-row.due-bold span {
  color: var(--danger-accent);
  font-weight: 700;
}

.stock-tag-box {
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
  border-radius: 4px;
  font-size: 11.5px;
  font-weight: 700;
  color: #065f46;
  white-space: nowrap;
}

.btn-save-master {
  height: 36px;
  background: var(--emerald-accent);
  border: none;
  border-radius: 4px;
  font-size: 13px;
  font-weight: 700;
  color: #ffffff;
  width: 100%;
  transition: 0.15s ease;
}

.btn-save-master:hover {
  background: var(--emerald-hover);
  color: #ffffff;
}

/* Compact Modal Widths */
.modal-dialog-compact {
  max-width: 430px;
  margin: 35px auto;
}

/* Mobile & Tablet Responsiveness */
@media (max-width: 991px) {
  .invoice-terminal {
    padding: 6px;
  }
  .grid-container-full {
    height: auto !important;
    max-height: 220px !important;
  }
  .modal-dialog-compact {
    max-width: 92%;
    margin: 20px auto;
  }
  .summary-metric-card {
    margin-bottom: 10px;
  }
}

/* Custom Scrollbars */
::-webkit-scrollbar {
  width: 5px;
  height: 5px;
}
::-webkit-scrollbar-track {
  background: #f1f5f9;
}
::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 3px;
}
::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
</style>

<div class="invoice-terminal">
<form method="post" onsubmit="return validateCustomer()">

  <!-- TOP HEADER ROW -->
  <div class="pos-card" style="padding: 8px 14px;">
    <div class="row align-items-end">
      
      <div class="col-xs-12 col-sm-6 col-md-2" style="margin-bottom: 4px;">
        <label class="pos-title-lbl">Invoice Date</label>
        <input type="text" name="invoice_date" id="invoice_date" class="form-control" value="<?= date('d/M/Y'); ?>" autocomplete="off">
      </div>

      <div class="col-xs-12 col-sm-6 col-md-3" style="margin-bottom: 4px;">
        <label class="pos-title-lbl">Customer</label>
        <div class="input-group">
          <select name="customer_id" id="customer_select" class="form-control">
            <option value="">Select Customer</option>
            <?php foreach($customers as $c): ?>
              <option value="<?=$c['id'];?>"><?=$c['customer_name'];?></option>
            <?php endforeach; ?>
          </select>
          <span class="input-group-btn">
            <button type="button" class="btn" data-toggle="modal" data-target="#quickAddCustomerModal" title="Add Customer">
              <i class="glyphicon glyphicon-plus" style="font-size: 10px;"></i>
            </button>
          </span>
        </div>
      </div>

      <div class="col-xs-12 col-sm-6 col-md-2" style="margin-bottom: 4px;">
        <label class="pos-title-lbl">GST Mode</label>
        <select name="gst_type" class="form-control">
          <option value="exclusive" selected>Exclusive GST</option>
          <option value="inclusive">Inclusive GST</option>
          <option value="nogst">No GST</option>
        </select>
      </div>

      <div class="col-xs-6 col-sm-3 col-md-2" style="margin-bottom: 4px;">
        <label class="pos-title-lbl" style="visibility:hidden;">Products</label>
        <button type="button" class="btn btn-primary btn-top-trigger" data-toggle="modal" data-target="#productCatalogueModal">
          <i class="glyphicon glyphicon-search" style="font-size: 11px;"></i> Add Products
        </button>
      </div>

      <div class="col-xs-6 col-sm-3 col-md-3" style="margin-bottom: 4px;">
        <label class="pos-title-lbl" style="visibility:hidden;">Payment</label>
        <button type="button" class="btn btn-info btn-top-trigger" data-toggle="modal" data-target="#paymentModal" style="background:#0284c7; border-color:#0284c7; color:#fff;">
          <i class="glyphicon glyphicon-credit-card" style="font-size: 11px;"></i> Payment (<span id="btnPaidDisplay">₹0.00</span>)
        </button>
      </div>

      <?php if($system == 'billing'): ?>
      <div class="col-xs-12 col-sm-6 col-md-2" style="margin-bottom: 4px;">
        <label class="pos-title-lbl">Customer Name</label>
        <input type="text" name="manual_name" class="form-control" placeholder="Name">
      </div>
      <div class="col-xs-12 col-sm-6 col-md-2" style="margin-bottom: 4px;">
        <label class="pos-title-lbl">Contact No</label>
        <input type="text" name="manual_phone" class="form-control" placeholder="Phone">
      </div>
      <?php endif; ?>

      <?php if($_SESSION['role_id'] == 2 && $system != 'inventory'): ?>
      <div class="col-xs-12 col-sm-6 col-md-2" style="margin-bottom: 4px;">
        <label class="pos-title-lbl">Center</label>
        <select name="center_id" class="form-control" required>
          <?php foreach(find_all('master_center') as $c): ?>
            <option value="<?= $c['center_id']; ?>"><?= $c['center_name']; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

    </div>
  </div>

  <!-- FULL-WIDTH BILLING DATA GRID (PERFECT SWEET SPOT: 220px) -->
  <div class="pos-card" style="padding:0; overflow:hidden;">
    <div class="grid-container-full">
      <table class="table" id="itemTable">
        <thead>
          <tr>
            <th style="width: 32%; padding-left: 12px;">Product Description</th>
            <th style="width: 8%;" class="text-center">Qty</th>
            <th style="width: 12%;" class="text-right">Price (₹)</th>
            <?php if($gst_enabled == "Yes"): ?>
            <th style="width: 8%;" class="text-center">GST %</th>
            <th style="width: 10%;" class="text-right">GST (₹)</th>
            <?php endif; ?>
            <th style="width: 8%;" class="text-center">Disc %</th>
            <th style="width: 10%;" class="text-right">Disc (₹)</th>
            <th style="width: 10%;" class="text-right">Line Total (₹)</th>
            <th style="width: 2%; text-align:center;"></th>
          </tr>
        </thead>
        <tbody id="billBody">
          <tr id="emptyRowMsg">
            <td colspan="<?= ($gst_enabled == 'Yes') ? 9 : 7; ?>" class="empty-grid-msg">
              No products added to bill. Click <b>"Add Products"</b> above to insert items.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- EQUAL 50/50 BOTTOM SUMMARY, EXPANDED NOTES & SAVE -->
  <div class="pos-card summary-container">
    <div class="row">
      
      <!-- LEFT 50%: Financial Metrics -->
      <div class="col-xs-12 col-md-6" style="margin-bottom: 6px;">
        <div class="summary-metric-card">
          <div class="stat-row">
            <span>Gross Total</span>
            <strong>₹ <span id="gross">0.00</span></strong>
          </div>
          <div class="stat-row net-bold">
            <span>Net Payable</span>
            <strong>₹ <span id="net">0.00</span></strong>
          </div>
          <div class="stat-row" style="color:var(--emerald-accent);">
            <span>Paid Amount</span>
            <strong>₹ <span id="paid">0.00</span></strong>
          </div>
          <div class="stat-row due-bold">
            <span>Balance Due</span>
            <strong>₹ <span id="balance">0.00</span></strong>
          </div>
          <div class="stat-row">
            <span>Change / Return</span>
            <strong>₹ <span id="returnAmt">0.00</span></strong>
          </div>
        </div>
      </div>

      <!-- RIGHT 50%: Terms, Stock & Save Action Button -->
      <div class="col-xs-12 col-md-6" style="display:flex; flex-direction:column; justify-content:space-between;">
        <div>
          <div class="row" style="margin-bottom: 6px;">
            <div class="col-xs-8">
              <select id="termsTemplate" class="form-control" style="font-size:12px; height:30px !important;">
                <option value="">Select Terms Template</option>
                <?php foreach($terms_templates as $t): ?>
                  <option value="<?= htmlspecialchars($t['template']); ?>"><?= htmlspecialchars($t['template_name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-xs-4">
              <div id="stockInfo" class="stock-tag-box" title="Stock Info">Stock: —</div>
            </div>
          </div>
          
          <textarea name="terms_conditions" id="termsBox" class="form-control" style="height: 75px !important; resize: none; font-size:12px; margin-bottom: 6px;" placeholder="Add invoice terms, bank details or remarks..."></textarea>
        </div>
        
        <button type="submit" name="save_invoice" class="btn btn-save-master">
          💾 Save & Print Invoice
        </button>
      </div>

    </div>
  </div>

  <!-- COMPACT PAYMENT BREAKDOWN MODAL -->
  <div class="modal fade" id="paymentModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-compact" role="document">
      <div class="modal-content" style="border-radius:6px; border:none; box-shadow:0 15px 35px rgba(0,0,0,0.25);">
        <div class="modal-header" style="background:#0284c7; color:#ffffff; border-top-left-radius:6px; border-top-right-radius:6px; padding:8px 14px;">
          <button type="button" class="close" data-dismiss="modal" style="color:#ffffff; opacity:0.9;">&times;</button>
          <h5 class="modal-title" style="font-weight:700; margin:0; font-size:13px;">Payment Modes</h5>
        </div>
        <div class="modal-body" style="padding:10px 14px;">
          <table class="table mb-0" style="margin-bottom:0;">
            <?php foreach($payment_modes as $pm): ?>
            <tr>
              <td width="26" style="vertical-align:middle; padding: 4px 2px;">
                <input type="checkbox" class="payCheck" data-mode="<?=$pm['id'];?>" style="margin:0; cursor:pointer;">
              </td>
              <td style="font-size:11.5px; font-weight:700; vertical-align:middle; padding: 4px 2px;">
                <?= strtoupper($pm['mode_name']); ?>
              </td>
              <td width="150" style="padding: 4px 2px;">
                <input type="number" step="0.01" min="0" name="payment_amount[<?=$pm['id'];?>]" class="form-control payAmt text-right" disabled data-mode="<?=$pm['id'];?>" value="0" style="height: 26px !important; font-size: 11.5px; margin-bottom: 2px;">
                <?php if(strtolower($pm['mode_name']) != 'cash'): ?>
                <input type="text" name="utr_no[<?=$pm['id'];?>]" class="form-control utrField" placeholder="Ref / UTR" disabled data-mode="<?=$pm['id'];?>" style="height: 24px !important; font-size: 11px;">
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </table>
        </div>
        <div class="modal-footer" style="padding:6px 14px; background:#f8fafc; border-bottom-left-radius:6px; border-bottom-right-radius:6px;">
          <button type="button" class="btn btn-primary btn-sm font-weight-bold" data-dismiss="modal" style="font-size: 11.5px; padding: 3px 12px;">Done</button>
        </div>
      </div>
    </div>
  </div>

</form>
</div>

<!-- COMPACT PRODUCT CATALOGUE MODAL -->
<div class="modal fade" id="productCatalogueModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-compact" role="document">
    <div class="modal-content" style="border-radius:6px; border:none; box-shadow:0 15px 35px rgba(0,0,0,0.25);">
      <div class="modal-header" style="background:var(--navy-dark); color:#ffffff; border-top-left-radius:6px; border-top-right-radius:6px; padding:8px 14px;">
        <button type="button" class="close" data-dismiss="modal" style="color:#ffffff; opacity:0.9;">&times;</button>
        <h5 class="modal-title" style="font-weight:700; margin:0; font-size:13px;">Product Catalogue</h5>
      </div>
      <div class="modal-body" style="padding:10px 14px;">
        <input type="text" id="productSearch" class="form-control mb-2" placeholder="Search product..." style="height: 28px !important; font-size: 11.5px;">
        <div style="max-height: 220px; overflow-y: auto; border: 1px solid var(--border-light); border-radius: 4px;">
          <table class="table table-hover mb-0">
            <tbody id="productList"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer" style="padding:6px 14px; background:#f8fafc; border-bottom-left-radius:6px; border-bottom-right-radius:6px;">
        <button type="button" class="btn btn-default btn-sm" data-dismiss="modal" style="font-size: 11.5px; padding: 3px 12px;">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- DEMO POP-UP MODAL -->
<div class="modal fade" id="demoModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-compact" role="document">
    <div class="modal-content" style="border-radius:6px; border:none; box-shadow:0 15px 35px rgba(0,0,0,0.25);">
      <div class="modal-header" style="background:var(--navy-dark); color:#ffffff; border-top-left-radius:6px; border-top-right-radius:6px; padding:8px 14px;">
        <button type="button" class="close" data-dismiss="modal" style="color:#ffffff; opacity:0.9;">&times;</button>
        <h5 class="modal-title" style="font-weight:700; margin:0; font-size:13px;">Active Demo Items</h5>
      </div>
      <div class="modal-body" style="padding:10px 14px;">
        <p class="text-muted" style="font-size:11px; margin-bottom:6px;">Select quantities to import:</p>
        <div class="table-responsive" style="border: 1px solid var(--border-slate); border-radius:4px;">
          <table class="table table-bordered table-striped" style="font-size:11.5px; margin-bottom:0;">
            <thead style="background:var(--navy-light); color:#ffffff;">
              <tr>
                <th width="30" class="text-center"><input type="checkbox" id="checkAllDemo" checked></th>
                <th>Product</th>
                <th width="70" class="text-center">Hold</th>
                <th width="90" class="text-center">Qty</th>
                <th width="70" class="text-right">Price</th>
              </tr>
            </thead>
            <tbody id="demoModalTableBody"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer" style="padding:6px 14px; background:#f8fafc; border-bottom-left-radius:6px; border-bottom-right-radius:6px;">
        <button type="button" class="btn btn-default btn-sm" data-dismiss="modal" style="font-size: 11.5px;">Skip</button>
        <button type="button" class="btn btn-success btn-sm font-weight-bold" id="importDemoBtn" style="font-size: 11.5px;">Import</button>
      </div>
    </div>
  </div>
</div>

<!-- QUICK ADD CUSTOMER MODAL -->
<div class="modal fade" id="quickAddCustomerModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-compact" role="document">
    <div class="modal-content" style="border-radius:6px; border:none; box-shadow:0 15px 35px rgba(0,0,0,0.25);">
      <div class="modal-header" style="background:var(--blue-accent); color:#ffffff; border-top-left-radius:6px; border-top-right-radius:6px; padding:8px 14px;">
        <button type="button" class="close" data-dismiss="modal" style="color:#ffffff; opacity:0.9;">&times;</button>
        <h5 class="modal-title" style="font-weight:700; margin:0; font-size:13px;">Quick Add Customer</h5>
      </div>
      <form id="quickCustomerForm">
        <div class="modal-body" style="padding:10px 14px;">
          <div class="form-group" style="margin-bottom:8px;">
            <label class="pos-title-lbl">Customer Name <span class="text-danger">*</span></label>
            <input type="text" id="quick_cust_name" name="customer_name" class="form-control" style="height:28px !important; font-size:11.5px;" placeholder="Full name" required autocomplete="off">
          </div>
          <div class="form-group" style="margin-bottom:8px;">
            <label class="pos-title-lbl">Contact No</label>
            <input type="text" id="quick_cust_phone" name="contact_no" class="form-control" style="height:28px !important; font-size:11.5px;" placeholder="Mobile" autocomplete="off">
          </div>
          <div class="form-group" style="margin-bottom:8px;">
            <label class="pos-title-lbl">GST No</label>
            <input type="text" id="quick_cust_gst" name="gst_no" class="form-control" style="height:28px !important; font-size:11.5px;" placeholder="GSTIN" maxlength="15" style="text-transform:uppercase;">
          </div>
        </div>
        <div class="modal-footer" style="padding:6px 14px; background:#f8fafc; border-bottom-left-radius:6px; border-bottom-right-radius:6px;">
          <button type="button" class="btn btn-default btn-sm" data-dismiss="modal" style="font-size: 11.5px;">Cancel</button>
          <button type="submit" id="saveQuickCustBtn" class="btn btn-success btn-sm font-weight-bold" style="font-size: 11.5px;">Save & Select</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include_once('layouts/footer.php'); ?>

<script>
const products = <?= json_encode($products); ?>;

/* RENDER PRODUCTS IN MODAL */
function renderProducts(filter=""){
  let list = document.getElementById("productList");
  list.innerHTML = "";

  products
  .filter(p => p.name.toLowerCase().includes(filter.toLowerCase()))
  .forEach(p => {
    let tr = document.createElement("tr");
    tr.style.cursor = "pointer";
    tr.innerHTML = `
      <td style="font-size:11.5px; font-weight:600; padding: 6px 8px;">${p.name}</td>
      <td class="text-right" style="font-size:11.5px; font-weight:700; color:#475569; padding: 6px 8px;">₹${parseFloat(p.sale_price).toFixed(2)}</td>
    `;
    tr.addEventListener("click", () => {
      addProduct(p);
      $('#productCatalogueModal').modal('hide');
    });
    list.appendChild(tr);
  });
}
renderProducts();

document.getElementById("productSearch").addEventListener("input", e => renderProducts(e.target.value));

/* ADD PRODUCT TO BILLING GRID */
function addProduct(p){
  document.getElementById("stockInfo").innerHTML = `Stock: ${Math.floor(Number(p.current_stock || 0))} PCS`;

  let emptyMsg = document.getElementById("emptyRowMsg");
  if(emptyMsg) emptyMsg.remove();

  let rows = document.querySelectorAll("#billBody tr:not(#emptyRowMsg)");
  for(let r of rows){
    let pId = r.querySelector(".productName").dataset.productId;
    if(pId == p.id){
      let qtyInput = r.querySelector(".qty");
      qtyInput.value = parseInt(qtyInput.value || 0) + 1;
      calculate(r);
      return;
    }
  }

  let row = document.createElement("tr");
  row.innerHTML = `
    <td class="productName" data-product-id="${p.id}" style="font-size:12px; font-weight:700; padding-left: 12px !important;">
      ${p.name}
      <input type="hidden" name="product_id[]" value="${p.id}">
    </td>
    <td><input type="number" name="qty[]" class="grid-cell qty text-center" value="1" min="1"></td>
    <td><input type="number" step="0.01" name="rate[]" class="grid-cell base text-right" value="${p.sale_price}"></td>
    <?php if($gst_enabled == "Yes"): ?>
    <td><input type="number" name="gst[]" class="grid-cell gst text-center" value="${p.gst_percent}"></td>
    <td><input type="text" class="grid-cell gstAmt text-right" readonly></td>
    <?php endif; ?>
    <td><input type="number" step="0.01" class="grid-cell discPer text-center" value="0"></td>
    <td><input type="number" step="0.01" name="discount[]" class="grid-cell discAmt text-right" value="0"></td>
    <td><input type="text" class="grid-cell totalRow text-right font-weight-bold" style="color:var(--blue-accent);" readonly></td>
    <td class="text-center" style="padding-right: 6px !important;"><button type="button" class="btn-del-cell remove" title="Remove">&times;</button></td>
  `;

  document.getElementById("billBody").appendChild(row);
  calculate(row);
}

/* EVENT DELEGATION */
document.addEventListener("input", function(e){
  if(
    e.target.classList.contains("qty") ||
    e.target.classList.contains("base") ||
    e.target.classList.contains("gst") ||
    e.target.classList.contains("discPer") ||
    e.target.classList.contains("discAmt")
  ){
    calculate(e.target.closest("tr"));
  }
});

document.addEventListener("change", function(e){
  if(e.target.name == "gst_type"){
    document.querySelectorAll("#billBody tr:not(#emptyRowMsg)").forEach(r => calculate(r));
  }
});

/* REMOVE ROW */
document.addEventListener("click", function(e){
  if(e.target.classList.contains("remove")){
    let row = e.target.closest("tr");
    row.remove();
    updateSummary();

    let rows = document.querySelectorAll("#billBody tr:not(#emptyRowMsg)");
    if(rows.length === 0){
      document.getElementById("stockInfo").innerHTML = "Stock: —";
      document.getElementById("billBody").innerHTML = `
        <tr id="emptyRowMsg">
          <td colspan="<?= ($gst_enabled == 'Yes') ? 9 : 7; ?>" class="empty-grid-msg">
            No products added to bill. Click <b>"Add Products"</b> above to insert items.
          </td>
        </tr>`;
    }else{
      let lastRow = rows[rows.length - 1];
      let productId = lastRow.querySelector(".productName").dataset.productId;
      let productObj = products.find(p => p.id == productId);
      if(productObj){
        document.getElementById("stockInfo").innerHTML = `Stock: ${Math.floor(Number(productObj.current_stock || 0))} PCS`;
      }
    }
  }
});

/* CALCULATION ENGINE */
function calculate(r){
  let qty  = parseFloat(r.querySelector(".qty").value) || 0;
  let base = parseFloat(r.querySelector(".base").value) || 0;
  let gst = 0;

  let gstField = r.querySelector(".gst");
  if(gstField){
    gst = parseFloat(gstField.value) || 0;
  }

  let dPer = parseFloat(r.querySelector(".discPer").value) || 0;
  let dAmt = parseFloat(r.querySelector(".discAmt").value) || 0;

  let total = qty * base;

  let active = document.activeElement;
  if(active && active.classList.contains("discPer")){
    dAmt = (total * dPer) / 100;
    r.querySelector(".discAmt").value = dAmt.toFixed(2);
  } else if(active && active.classList.contains("discAmt")){
    dPer = total > 0 ? (dAmt / total) * 100 : 0;
    r.querySelector(".discPer").value = dPer.toFixed(2);
  }

  let afterDisc = total - dAmt;

  let gstType = "exclusive";
  let gstSelect = document.querySelector("select[name='gst_type']");
  if(gstSelect){
    gstType = gstSelect.value;
  }

  let gstAmt = 0;
  let final = 0;

  if(gstType == "nogst"){
    gstAmt = 0;
    final = afterDisc;
  } else if(gstType == "exclusive"){
    gstAmt = (afterDisc * gst) / 100;
    final = afterDisc + gstAmt;
  } else {
    gstAmt = afterDisc - (afterDisc * 100 / (100 + gst));
    final = afterDisc;
  }

  let gstAmtField = r.querySelector(".gstAmt");
  if(gstAmtField){
    gstAmtField.value = gstAmt.toFixed(2);
  }
  r.querySelector(".totalRow").value = final.toFixed(2);

  updateSummary();
}

/* UPDATE TOTALS SUMMARY */
function updateSummary(){
  let gross = 0;
  document.querySelectorAll(".totalRow").forEach(t => {
    gross += parseFloat(t.value) || 0;
  });

  document.getElementById("gross").innerText = gross.toFixed(2);
  document.getElementById("net").innerText   = gross.toFixed(2);

  let paid = 0;
  document.querySelectorAll(".payAmt").forEach(i => {
    paid += parseFloat(i.value) || 0;
  });

  document.getElementById("paid").innerText = paid.toFixed(2);
  document.getElementById("btnPaidDisplay").innerText = `₹${paid.toFixed(2)}`;

  let balance = gross - paid;
  let returnAmt = 0;

  if(paid > gross){
    returnAmt = paid - gross;
    balance = 0;
  }

  document.getElementById("balance").innerText   = balance.toFixed(2);
  document.getElementById("returnAmt").innerText = returnAmt.toFixed(2);
}

/* PAYMENT HANDLERS */
document.querySelectorAll(".payCheck").forEach(chk => {
  chk.addEventListener("change", function(){
    let net = parseFloat(document.getElementById("net").innerText) || 0;
    let input = document.querySelector(`.payAmt[data-mode='${chk.dataset.mode}']`);
    let utr = document.querySelector(`.utrField[data-mode='${chk.dataset.mode}']`);

    if(chk.checked){
      input.disabled = false;
      if(utr) utr.disabled = false;

      let remaining = net;
      document.querySelectorAll(".payAmt").forEach(i => {
        if(i !== input) remaining -= parseFloat(i.value) || 0;
      });

      input.value = remaining > 0 ? remaining.toFixed(2) : 0;
    } else {
      input.value = 0;
      input.disabled = true;
      if(utr){
        utr.disabled = true;
        utr.value = '';
      }
    }
    updateSummary();
  });
});

document.querySelectorAll(".payAmt").forEach(input => {
  input.addEventListener("input", function(){
    if(parseFloat(this.value) < 0) this.value = 0;
    updateSummary();
  });
});

function validateCustomer(){
  let cust  = document.querySelector("select[name='customer_id']").value;
  let nameEl = document.querySelector("input[name='manual_name']");
  let name  = nameEl ? nameEl.value.trim() : "";
  let phoneEl = document.querySelector("input[name='manual_phone']");
  let phone = phoneEl ? phoneEl.value.trim() : "";

  if(cust == "" && name == "" && phone == ""){
    alert("Please select customer OR enter name & contact");
    return false;
  }
  return true;
}

/* TEMPLATE HANDLER */
document.getElementById("termsTemplate")?.addEventListener("change", function(){
  document.getElementById("termsBox").value = this.value;
});

/* FLATPICKR INITIALIZATION */
document.addEventListener("DOMContentLoaded", function () {
  if (typeof flatpickr !== 'undefined') {
    flatpickr("#invoice_date", {
      dateFormat: "d/M/Y",
      allowInput: false,
      disableMobile: true
    });
  }
});

/* DEMO POPUP LOGIC */
let currentDemoData = [];
document.querySelector("select[name='customer_id']")?.addEventListener("change", function () {
  let custId = this.value;
  if (!custId || custId == "0") return;

  fetch(`get_demo_items.php?customer_id=${custId}`)
    .then(res => res.json())
    .then(data => {
      if (data && data.length > 0) {
        currentDemoData = data;
        let tbody = document.getElementById("demoModalTableBody");
        tbody.innerHTML = "";

        data.forEach((item, index) => {
          tbody.innerHTML += `
            <tr>
              <td class="text-center" style="vertical-align:middle;"><input type="checkbox" class="demoItemCheck" value="${index}" checked></td>
              <td style="vertical-align:middle; font-weight:700;">${item.product_name}</td>
              <td class="text-center" style="vertical-align:middle;"><span class="badge" style="background:#475569;">${item.qty} PCS</span></td>
              <td class="text-center" style="vertical-align:middle;">
                <input type="number" class="form-control text-center invoiceDemoQty" data-max="${item.qty}" value="${item.qty}" min="1" max="${item.qty}" style="height:24px !important; font-size:11px; font-weight:bold; color:var(--blue-accent);">
              </td>
              <td class="text-right" style="vertical-align:middle;">₹${item.sale_price}</td>
            </tr>
          `;
        });

        if (typeof $ !== 'undefined' && $.fn.modal) {
          $('#demoModal').modal('show');
        }
      }
    })
    .catch(err => console.error("Error fetching demo items:", err));
});

document.getElementById("checkAllDemo")?.addEventListener("change", function() {
  document.querySelectorAll(".demoItemCheck").forEach(cb => cb.checked = this.checked);
});

document.addEventListener("input", function(e) {
  if(e.target.classList.contains("invoiceDemoQty")){
    let max = parseFloat(e.target.getAttribute("data-max")) || 0;
    let val = parseFloat(e.target.value) || 0;
    if(val > max){
      alert("Invoiced quantity cannot exceed the available demo quantity (" + max + " PCS)!");
      e.target.value = max;
    }
  }
});

document.getElementById("importDemoBtn")?.addEventListener("click", function () {
  let selectedBoxes = document.querySelectorAll(".demoItemCheck:checked");
  if(selectedBoxes.length === 0){
    alert("Please select at least one item to import!");
    return;
  }

  selectedBoxes.forEach(cb => {
    let index = cb.value;
    let item  = currentDemoData[index];
    let row   = cb.closest("tr");
    let customQty = parseFloat(row.querySelector(".invoiceDemoQty").value) || item.qty;

    let pObj = {
      id: item.product_id,
      name: item.product_name,
      sale_price: item.sale_price,
      gst_percent: item.gst_percent || 0,
      current_stock: 999
    };

    addProduct(pObj);

    let rows = document.querySelectorAll("#billBody tr:not(#emptyRowMsg)");
    let lastRow = rows[rows.length - 1];
    if (lastRow) {
      let qtyInput = lastRow.querySelector(".qty");
      if(qtyInput){
        qtyInput.value = customQty;
        calculate(lastRow);
      }
    }
  });

  $('#demoModal').modal('hide');
});

/* QUICK ADD CUSTOMER AJAX */
document.getElementById("quickCustomerForm")?.addEventListener("submit", function (e) {
  e.preventDefault();
  let name = document.getElementById("quick_cust_name").value.trim();
  let contact = document.getElementById("quick_cust_phone").value.trim();
  let gst = document.getElementById("quick_cust_gst").value.trim();

  if (!name) {
    alert("Please enter customer name!");
    return;
  }

  let btn = document.getElementById("saveQuickCustBtn");
  btn.disabled = true;
  btn.innerText = "Saving...";

  let formData = new FormData();
  formData.append("customer_name", name);
  formData.append("contact_no", contact);
  formData.append("gst_no", gst);

  fetch("ajax_add_customer.php", {
    method: "POST",
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    btn.disabled = false;
    btn.innerText = "Save & Select";

    if (data.status === "success") {
      let select = document.getElementById("customer_select");
      let newOption = new Option(data.name, data.id, true, true);
      select.add(newOption);
      select.dispatchEvent(new Event("change"));
      document.getElementById("quickCustomerForm").reset();
      $('#quickAddCustomerModal').modal('hide');
    } else {
      alert(data.message || "Something went wrong!");
    }
  })
  .catch(err => {
    btn.disabled = false;
    btn.innerText = "Save & Select";
    console.error("Error adding customer:", err);
    alert("Server error occurred!");
  });
});
</script>
