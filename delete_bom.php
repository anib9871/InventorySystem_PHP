$id = (int)$_GET['id'];
$db->query("DELETE FROM bom_master WHERE id=$id");
redirect('bom_master.php');
