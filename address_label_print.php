<?php
require_once('includes/load.php');

$type    = isset($_GET['type']) ? trim($_GET['type']) : 'customer';
$cust_id = isset($_GET['cust_id']) ? (int)$_GET['cust_id'] : 0;
$supp_id = isset($_GET['supp_id']) ? (int)$_GET['supp_id'] : 0;

// Naya Logic: Combined Dropdown se Org ID aur Address Index nikalna
$org_and_address = isset($_GET['org_and_address']) ? $_GET['org_and_address'] : '';
$parts = explode('|', $org_and_address);
$org_id = isset($parts[0]) ? (int)$parts[0] : (isset($_GET['org_id']) ? (int)$_GET['org_id'] : 0);
$addr_index = isset($parts[1]) ? (int)$parts[1] : 0;

if((!$cust_id && !$supp_id) || !$org_id){
    die("Invalid request! Please select both Party and Organization.");
}

/* FETCH TO PARTY DETAILS (CUSTOMER YA SUPPLIER) */
if($type == 'supplier' || $supp_id > 0){
    $target_id = $supp_id ? $supp_id : $cust_id;
    $party_query = find_by_sql("
        SELECT sm.supplier_name AS party_name, sm.contact_person, sm.phone AS contact_no, 
               sm.email, sm.address, sm.state_code, sm.gst_no, gsm.state_name 
        FROM supplier_master sm 
        LEFT JOIN gst_state_master gsm ON gsm.id = sm.state_id 
        WHERE sm.id = '{$target_id}' 
        LIMIT 1
    ");
} else {
    $party_query = find_by_sql("
        SELECT cm.customer_name AS party_name, '' AS contact_person, cm.contact_no, 
               cm.email, cm.address, cm.state_code, cm.gst_no, gsm.state_name 
        FROM customer_master cm 
        LEFT JOIN gst_state_master gsm ON gsm.id = cm.state_id 
        WHERE cm.id = '{$cust_id}' 
        LIMIT 1
    ");
}

/* FETCH ORGANIZATION DETAILS (FROM) */
$organization = find_by_sql("
    SELECT om.*, gsm.state_name 
    FROM organization_master om 
    LEFT JOIN gst_state_master gsm ON gsm.id = om.state_id 
    WHERE om.id = '{$org_id}' 
    LIMIT 1
");

if(!$party_query || !$organization){
    die("Record not found!");
}

$to_party = $party_query[0];
$org      = $organization[0];

// Naya Logic: JSON array se selected address aur uska state/code bahar nikalna
$raw_address = $org['address'];
$decoded_addresses = json_decode($raw_address, true);

$sender_address_text = '';
$sender_state_name = $org['state_name']; // Default/Fallback
$sender_state_code = $org['state_code']; // Default/Fallback

if (is_array($decoded_addresses) && isset($decoded_addresses[$addr_index])) {
    $selected_addr = $decoded_addresses[$addr_index];
    if (is_array($selected_addr)) {
        $sender_address_text = isset($selected_addr['text']) ? $selected_addr['text'] : '';
        // Agar specific address me state/code hai toh wo use karo, nahi toh main organization ka use karo
        $sender_state_name = !empty($selected_addr['state_name']) ? $selected_addr['state_name'] : $org['state_name'];
        $sender_state_code = !empty($selected_addr['state_code']) ? $selected_addr['state_code'] : $org['state_code'];
    } else {
        $sender_address_text = $selected_addr; // Purane text format ke liye
    }
} else {
    $sender_address_text = $raw_address; // Fallback
}
?>
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dispatch Address Label - <?php echo htmlspecialchars($to_party['party_name']); ?></title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        body {
            background-color: #f3f4f6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        /* A4 Page Layout */
        .a4-page {
            width: 210mm;
            height: 297mm;
            background: #ffffff;
            padding: 15mm;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            position: relative;
        }
        
        /* Action Bar (Screen Only) */
        .no-print-bar {
            position: fixed;
            top: 15px;
            right: 20px;
            z-index: 999;
            display: flex;
            gap: 10px;
        }
        .btn-print {
            background: #10b981;
            color: #fff;
            border: none;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(16,185,129,0.3);
        }
        .btn-print:hover { background: #059669; }

        /* Label Box Container */
        .label-card {
            border: 2px dashed #1e293b;
            border-radius: 8px;
            padding: 25px;
            background: #fff;
            width: 100%;
            margin-bottom: 20px;
        }

        .header-title {
            text-align: center;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
            margin-bottom: 20px;
            color: #1e293b;
        }

        /* Two Column Layout: FROM & TO */
        .address-grid {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 20px;
        }

        .address-box {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 15px;
            position: relative;
        }

        .address-box.from-box {
            background-color: #f8fafc;
        }

        .address-box.to-box {
            background-color: #ffffff;
            border: 2px solid #1e293b;
        }

        .box-badge {
            display: inline-block;
            font-size: 12px;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .badge-from {
            background: #e2e8f0;
            color: #334155;
        }

        .badge-to {
            background: #1e293b;
            color: #ffffff;
            font-size: 14px;
        }

        .party-name {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .party-detail {
            font-size: 13px;
            color: #334155;
            line-height: 1.5;
        }

        .party-detail strong {
            color: #0f172a;
        }

        .address-text {
            margin: 8px 0;
            white-space: pre-line;
            word-break: break-word;
        }

        /* PRINT STYLES */
        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .no-print-bar {
                display: none !important;
            }
            .a4-page {
                box-shadow: none;
                padding: 10mm;
                width: 100%;
                height: auto;
            }
            .label-card {
                border: 2px solid #000;
            }
        }
    </style>
</head>
<body>

    <div class="no-print-bar">
        <button class="btn-print" onclick="window.print()">
            🖨️ Print / Save as PDF
        </button>
    </div>

    <div class="a4-page">
        <div class="label-card">
            <div class="header-title">PARCEL / DISPATCH SLIP</div>
            
            <div class="address-grid">
                <div class="address-box from-box">
                    <span class="box-badge badge-from">FROM (SENDER)</span>
                    <div class="party-name"><?php echo htmlspecialchars($org['org_name']); ?></div>
                    <div class="party-detail">
                        <?php if(!empty($org['contact_person'])): ?>
                            <div><strong>Attn:</strong> <?php echo htmlspecialchars($org['contact_person']); ?></div>
                        <?php endif; ?>
                        
                        <div class="address-text"><?php echo htmlspecialchars($sender_address_text); ?></div>
                        
                        <div><strong>State:</strong> <?php echo htmlspecialchars($sender_state_name); ?> (Code: <?php echo htmlspecialchars($sender_state_code); ?>)</div>
                        <?php if(!empty($org['phone'])): ?>
                            <div><strong>Ph:</strong> <?php echo htmlspecialchars($org['phone']); ?></div>
                        <?php endif; ?>
                        
                        <?php if(!empty($org['email'])): ?>
                            <div><strong>Email:</strong> <?php echo htmlspecialchars($org['email']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="address-box to-box">
                    <span class="box-badge badge-to">TO (RECIPIENT)</span>
                    <div class="party-name" style="font-size: 18px; color: #000;">
                        <?php echo htmlspecialchars($to_party['party_name']); ?>
                    </div>
                    <div class="party-detail" style="font-size: 14px;">
                        <?php if(!empty($to_party['contact_person'])): ?>
                            <div><strong>Attn:</strong> <?php echo htmlspecialchars($to_party['contact_person']); ?></div>
                        <?php endif; ?>

                        <div class="address-text" style="font-size: 14px; font-weight: 500;">
                            <?php echo htmlspecialchars($to_party['address']); ?>
                        </div>
                        
                        <div><strong>State:</strong> <?php echo htmlspecialchars($to_party['state_name']); ?> (Code: <?php echo htmlspecialchars($to_party['state_code']); ?>)</div>
                        
                        <?php if(!empty($to_party['contact_no'])): ?>
                            <div style="font-size: 15px; margin-top: 5px;">
                                <strong>Mobile/Ph:</strong> <?php echo htmlspecialchars($to_party['contact_no']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if(!empty($to_party['email'])): ?>
                            <div><strong>Email:</strong> <?php echo htmlspecialchars($to_party['email']); ?></div>
                        <?php endif; ?>
                        
                        <?php if(!empty($to_party['gst_no'])): ?>
                            <div><strong>GSTIN:</strong> <?php echo htmlspecialchars($to_party['gst_no']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
