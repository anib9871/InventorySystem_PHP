<?php
$page_title = 'Purchase Order List';
require_once('includes/load.php');

/*
 * PO items are stored as multiple transaction_master rows.
 * Aggregate them before joining shipping to avoid duplicated totals
 * and remain compatible with ONLY_FULL_GROUP_BY.
 */
$pos = find_by_sql("
    SELECT
        po.bill_indent_no,
        po.bill_indent_date,
        po.billing_state,
        po.shipping_state,
        po.items_total,
        s.supplier_name,
        COALESCE(sh.shipping_total, 0) AS shipping_total
    FROM (
        SELECT
            bill_indent_no,
            MAX(transaction_id) AS last_transaction_id,
            MAX(bill_indent_date) AS bill_indent_date,
            MAX(billing_state) AS billing_state,
            MAX(shipping_state) AS shipping_state,
            MAX(supplier_id) AS supplier_id,
            SUM(net_price) AS items_total
        FROM transaction_master
        WHERE transaction_type = 9
        GROUP BY bill_indent_no
    ) AS po
    LEFT JOIN supplier_master AS s
        ON s.id = po.supplier_id
    LEFT JOIN (
        SELECT bill_no, SUM(total_amount) AS shipping_total
        FROM shipping
        GROUP BY bill_no
    ) AS sh
        ON sh.bill_no = po.bill_indent_no
    ORDER BY po.last_transaction_id DESC
");

include_once('layouts/header.php');
?>

<style>
    /* Manage GRN-style PO list */
    .po-manage-wrap {
        background: #fff;
        border: 1px solid #d9dee7;
        border-radius: 3px;
        margin: 18px 15px 20px;
        overflow: hidden;
    }

    .po-manage-head {
        min-height: 60px;
        padding: 13px 12px;
        background: #f5f5f5;
        border-bottom: 1px solid #2583ff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .po-manage-title {
        margin: 0;
        color: #172033;
        font-size: 14px;
        line-height: 20px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .po-create-btn {
        background: #28c76f;
        border: 1px solid #28c76f;
        color: #fff !important;
        border-radius: 22px;
        padding: 8px 18px;
        font-size: 11px;
        line-height: 14px;
        font-weight: 700;
        white-space: nowrap;
        text-decoration: none !important;
    }

    .po-create-btn:hover,
    .po-create-btn:focus {
        background: #20b963;
        border-color: #20b963;
        color: #fff !important;
    }

    .po-manage-body {
        padding: 14px 12px 16px;
    }

    .po-search-wrap {
        width: 240px;
        max-width: 100%;
        display: flex;
        margin-bottom: 14px;
    }

    .po-search-icon {
        width: 36px;
        height: 31px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #34445e;
        background: #fff;
        border: 1px solid #d8d8d8;
        border-right: 0;
        border-radius: 3px 0 0 3px;
        font-size: 12px;
    }

    .po-search-input {
        height: 31px !important;
        border: 1px solid #d8d8d8 !important;
        border-radius: 0 3px 3px 0 !important;
        box-shadow: none !important;
        font-size: 12px !important;
    }

    .po-manage-table {
        width: 100%;
        margin: 0;
        border-collapse: collapse;
        font-size: 12px;
        color: #172033;
    }

    .po-manage-table thead th {
        background: #202f50;
        color: #fff;
        border: 1px solid #202f50 !important;
        padding: 9px 8px !important;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
        vertical-align: middle !important;
    }

    .po-manage-table tbody td {
        padding: 9px 8px !important;
        border: 1px solid #d9d9d9 !important;
        vertical-align: middle !important;
        background: #fff;
    }

    .po-manage-table tbody tr:hover td {
        background: #f5faff;
    }

    .po-no-text {
        color: #17345f;
        font-weight: 500;
        white-space: nowrap;
    }

    .po-action-cell {
        white-space: nowrap;
    }

    .po-action-cell .btn {
        margin-right: 4px;
        min-width: 21px;
        padding: 3px 5px !important;
        border: 0;
        border-radius: 2px;
    }

    .po-action-cell .btn:last-child {
        margin-right: 0;
    }

    .po-empty-row {
        text-align: center;
        padding: 25px 10px !important;
        color: #777;
    }

    @media (max-width: 767px) {
        .po-manage-wrap {
            margin: 10px 5px;
        }

        .po-manage-head {
            min-height: 54px;
            padding: 10px;
        }

        .po-manage-body {
            padding: 10px;
        }

        .po-search-wrap {
            width: 100%;
        }
    }
</style>

<div class="row" style="margin:0;">
    <div class="col-md-12" style="padding:0 15px;">
        <?php echo display_msg($msg); ?>
    </div>
</div>

<div class="po-manage-wrap">
    <div class="po-manage-head">
        <h4 class="po-manage-title">MANAGE PURCHASE ORDERS (PO)</h4>

        <a href="purchase_order.php" class="po-create-btn">
            <i class="fa fa-plus"></i> CREATE PO
        </a>
    </div>

    <div class="po-manage-body">
        <div class="po-search-wrap">
            <span class="po-search-icon"><i class="fa fa-search"></i></span>
            <input
                type="text"
                id="poSearch"
                class="form-control po-search-input"
                placeholder="Search PO..."
                autocomplete="off"
            >
        </div>

        <div class="table-responsive">
            <table class="table table-bordered po-manage-table" id="poTable">
                <thead>
                    <tr>
                        <th width="45">#</th>
                        <th>PO No</th>
                        <th>Supplier</th>
                        <th>PO Date</th>
                        <th>Bill State</th>
                        <th>Ship State</th>
                        <th class="text-center" width="90">Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (!empty($pos)) : ?>
                        <?php foreach ($pos as $i => $row) :
                            $po_no = (string)($row['bill_indent_no'] ?? '');
                            $po_date = !empty($row['bill_indent_date'])
                                ? date('d/M/Y', strtotime($row['bill_indent_date']))
                                : '-';
                        ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td>
                                    <strong class="po-no-text">
                                        <?php echo htmlspecialchars($po_no, ENT_QUOTES, 'UTF-8'); ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($row['supplier_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td><?php echo htmlspecialchars($po_date, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($row['billing_state'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($row['shipping_state'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="text-center po-action-cell">
                                    <a
                                        href="print_po.php?po=<?php echo urlencode($po_no); ?>"
                                        target="_blank"
                                        class="btn btn-warning btn-xs"
                                        title="Print PO"
                                    ><i class="fa fa-print"></i></a>

                                    <a
                                        href="purchase_order.php?edit=<?php echo urlencode($po_no); ?>"
                                        class="btn btn-info btn-xs"
                                        title="Edit PO"
                                    ><i class="fa fa-pencil"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="7" class="po-empty-row">No Purchase Orders found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    var searchInput = document.getElementById('poSearch');
    var table = document.getElementById('poTable');

    if (!searchInput || !table) return;

    searchInput.addEventListener('input', function () {
        var searchText = this.value.toLowerCase().trim();
        var rows = table.querySelectorAll('tbody tr');

        rows.forEach(function (row) {
            var rowText = row.innerText.toLowerCase();
            row.style.display = rowText.indexOf(searchText) !== -1 ? '' : 'none';
        });
    });
})();
</script>

<?php include_once('layouts/footer.php'); ?>
