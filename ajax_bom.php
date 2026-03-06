<?php
require_once('includes/load.php');

if (!$session->isUserLoggedIn(true)) {
  redirect('index.php', false);
}

if (isset($_POST['product_id'])) {

  $product_id = (int)$_POST['product_id'];

  $result = $db->query("
    SELECT p.name AS raw_material, b.quantity
    FROM bom b
    JOIN products p ON p.id = b.raw_product_id
    WHERE b.product_id = {$product_id}
  ");

  if (!$result || $result->num_rows === 0) {
    echo "<p class='text-muted'>No BOM found for this product.</p>";
    exit;
  }

  echo "<table class='table table-bordered'>";
  echo "<tr><th>Raw Material</th><th>Quantity</th></tr>";

  while ($row = $result->fetch_assoc()) {
    echo "<tr>
            <td>{$row['raw_material']}</td>
            <td>{$row['quantity']}</td>
          </tr>";
  }

  echo "</table>";
}
