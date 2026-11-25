<?php
require_once('includes/load.php');

$po_no = isset($_GET['po']) ? urldecode($db->escape($_GET['po'])) : '';
if ($po_no === '') {
    die("Invalid PO Number");
}

$po_items = find_by_sql("
    SELECT
        tm.*,
        p.name AS product_name,
        p.hsn_code,
        s.supplier_name,
        s.address AS supplier_address,
        s.gst_no AS supplier_gst,
        s.phone AS supplier_phone,
        gsm.state_name AS supplier_state
    FROM transaction_master tm
    LEFT JOIN products p ON p.id = tm.product_id
    LEFT JOIN supplier_master s ON s.id = tm.supplier_id
    LEFT JOIN gst_state_master gsm ON gsm.id = s.state_id
    WHERE tm.bill_indent_no = '{$po_no}'
      AND tm.transaction_type = 9
    ORDER BY tm.transaction_id ASC
");

if (!$po_items) {
    die("Purchase Order not found!");
}

$head = $po_items[0];

$charges = find_by_sql("
    SELECT
        s.type_name,
        sh.total_amount
    FROM shipping sh
    LEFT JOIN shipping_type_master s
        ON s.id = sh.shipping_type_id
    WHERE sh.bill_no = '{$po_no}'
");

/* Compare supplier state with Ship-To state for supply type display. */
$is_igst = (
    strtolower(trim($head['supplier_state'] ?? '')) !==
    strtolower(trim($head['shipping_state'] ?? '')
)) &&
    !empty($head['supplier_state']) &&
    !empty($head['shipping_state']);

function po_h($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Purchase Order - <?= po_h($po_no); ?></title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 18px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #111827;
            background: #fff;
        }

        .no-print {
            max-width: 980px;
            margin: 0 auto 12px;
            text-align: right;
        }

        .print-btn {
            padding: 8px 18px;
            border: 0;
            border-radius: 4px;
            background: #0b1736;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .po-sheet {
            width: 100%;
            max-width: 980px;
            margin: 0 auto;
            padding: 15px;
            border: 1px solid #333;
        }

        .po-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
            padding-bottom: 9px;
            margin-bottom: 10px;
            border-bottom: 2px solid #0b1736;
        }

        .po-title {
            margin: 0 0 5px;
            color: #0b1736;
            font-size: 19px;
            font-weight: 800;
            letter-spacing: .3px;
        }

        .po-header-left,
        .po-header-right {
            line-height: 1.55;
        }

        .po-header-right {
            min-width: 220px;
            text-align: right;
        }

        .address-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            margin-bottom: 11px;
        }

        .address-box {
            min-width: 0;
            border: 1px solid #9ca3af;
            border-radius: 3px;
            padding: 7px;
            line-height: 1.45;
            overflow-wrap: anywhere;
        }

        .address-title {
            margin: -7px -7px 6px;
            padding: 5px 7px;
            background: #e8edf4;
            border-bottom: 1px solid #9ca3af;
            color: #172033;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .items-table {
            width: 100%;
            margin: 0;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .items-table th,
        .items-table td {
            padding: 7px 6px;
            border: 1px solid #666;
            font-size: 10px;
            overflow-wrap: anywhere;
        }

        .items-table th {
            background: #0b1736;
            color: #fff;
            font-weight: 700;
            text-align: left;
        }

        .items-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .text-center {
            text-align: center !important;
        }

        .text-right {
            text-align: right !important;
        }

        .signature-row {
            display: flex;
            justify-content: flex-end;
            align-items: flex-end;
            margin-top: 38px;
            min-height: 48px;
        }

        .signature {
            min-width: 180px;
            text-align: center;
            line-height: 1.5;
        }

        .computer-generated-note {
            margin-top: 14px;
            text-align: center;
            color: #555;
            font-size: 9px;
            font-style: italic;
        }

        .computer-generated-note {
            margin-top: 24px;
            padding-top: 8px;
            border-top: 1px solid #d1d5db;
            text-align: center;
            color: #475569;
            font-size: 10px;
            font-style: italic;
        }

        @page {
            size: A4 landscape;
            margin: 9mm;
        }

        @media print {
            body {
                margin: 0;
                background: #fff;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .no-print {
                display: none !important;
            }

            .po-sheet {
                width: 100%;
                max-width: none;
                margin: 0;
                padding: 0;
                border: none;
            }

            .address-box,
            .items-table tr {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }

        @media screen and (max-width: 700px) {
            body {
                margin: 8px;
            }

            .po-sheet {
                padding: 9px;
                overflow-x: auto;
            }

            .address-grid {
                grid-template-columns: 1fr;
            }

            .po-header {
                flex-direction: column;
            }

            .po-header-right {
                text-align: left;
            }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button type="button" class="print-btn" onclick="window.print()">
        <i class="fa fa-print"></i> Print / Save as PDF
    </button>
</div>

<div class="po-sheet">

    <div class="po-header">
        <div class="po-header-left">
            <h1 class="po-title">PURCHASE ORDER</h1>
        </div>

        <div class="po-header-right">
            <div><strong>PO No:</strong> <?= po_h($po_no); ?></div>
            <div>
                <strong>PO Date:</strong>
                <?= !empty($head['bill_indent_date']) ? date('d-M-Y', strtotime($head['bill_indent_date'])) : '-'; ?>
            </div>
        </div>
    </div>

    <div class="address-grid">
        <div class="address-box">
            <div class="address-title">Vendor / Supplier</div>
            <strong><?= po_h($head['supplier_name']); ?></strong><br>
            <?= nl2br(po_h($head['supplier_address'] ?? '')); ?><br>
            <strong>State:</strong> <?= po_h($head['supplier_state'] ?? '-'); ?><br>
            <strong>GSTIN:</strong> <?= po_h($head['supplier_gst'] ?? '-'); ?><br>
            <strong>Phone:</strong> <?= po_h($head['supplier_phone'] ?? '-'); ?>
        </div>

        <div class="address-box">
            <div class="address-title">Bill To (Billing Address)</div>
            <strong><?= po_h($head['billing_name']); ?></strong><br>
            <?= nl2br(po_h($head['billing_address'] ?? '')); ?><br>
            <strong>State:</strong> <?= po_h($head['billing_state'] ?? '-'); ?><br>
            <strong>GSTIN:</strong> <?= po_h($head['billing_gstin'] ?: '-'); ?>
        </div>

        <div class="address-box">
            <div class="address-title">Ship To (Delivery Address)</div>
            <strong><?= po_h($head['shipping_name']); ?></strong><br>
            <?= nl2br(po_h($head['shipping_address'] ?? '')); ?><br>
            <strong>State:</strong> <?= po_h($head['shipping_state'] ?? '-'); ?><br>
            <strong>GSTIN:</strong> <?= po_h($head['shipping_gstin'] ?: '-'); ?>
        </div>
    </div>

    <!-- Simplified item columns: only the fields required on the PO print. -->
    <table class="items-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 6%;">#</th>
                <th style="width: 64%;">Item Description</th>
                <th class="text-center" style="width: 15%;">HSN</th>
                <th class="text-right" style="width: 15%;">Qty</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($po_items as $idx => $it): ?>
                <tr>
                    <td class="text-center"><?= $idx + 1; ?></td>
                    <td><?= po_h($it['product_name']); ?></td>
                    <td class="text-center"><?= po_h($it['hsn_code'] ?: '-'); ?></td>
                    <td class="text-right"><?= number_format((float)$it['quantity'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="signature-row">
        <div class="signature">
            <strong>For <?= po_h($head['billing_name']); ?></strong>
            <br><br>
            Authorized Signatory
        </div>
    </div>

    <div class="computer-generated-note">
        This is a computer-generated document.
    </div>

</div>

</body>
</html>
