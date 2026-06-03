<?php

require_once('includes/load.php');

header('Content-Type: application/json');

$supplier_id = (int)($_GET['supplier_id'] ?? 0);

if($supplier_id <= 0){

echo json_encode([
'advance' => 0
]);

exit;

}

$sql = "

SELECT
ABS(SUM(balance_amount)) as advance

FROM supplier_ledger

WHERE supplier_id = '$supplier_id'

AND balance_amount < 0

";

$data = find_by_sql($sql);

echo json_encode([

'advance' => $data[0]['advance'] ?? 0

]);