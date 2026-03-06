$bom = find_by_id('bom_master',(int)$_GET['id']);
$items = $db->query("SELECT * FROM bom_items WHERE bom_id={$bom['id']}");

if(isset($_POST['update_bom'])){
  $db->query("UPDATE bom_master SET bom_name='$name' WHERE id={$bom['id']}");
  $db->query("DELETE FROM bom_items WHERE bom_id={$bom['id']}");

  foreach($_POST['raw_product_id'] as $i=>$raw){
    $qty=$_POST['qty'][$i];
    $db->query("INSERT INTO bom_items (bom_id, raw_product_id, quantity)
                VALUES ({$bom['id']},$raw,$qty)");
  }
}
