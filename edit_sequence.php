<?php
require_once('includes/load.php');
page_require_level(2);

$id = (int)$_GET['id'];

$sequence = find_by_sql("SELECT * FROM sequence_master WHERE sequence_id = $id LIMIT 1");


if(!$sequence){
  $session->msg('d',"Sequence not found");
  redirect('master_sequence.php');
}
$sequence = $sequence[0];
/* UPDATE */
if(isset($_POST['update_sequence'])){

  $category = remove_junk($_POST['sequence_category']);
  $last_no  = (int)$_POST['last_no'];

  $sql = "UPDATE sequence_master SET
          sequence_category = '{$category}',
          last_no = '{$last_no}'
          WHERE sequence_id = '{$id}'";

  if($db->query($sql)){
      $session->msg('s',"Sequence Updated");
      redirect('master_sequence.php',false);
  }else{
      $session->msg('d',"Update Failed");
      redirect('master_sequence.php',false);
  }

}

?>

<?php include_once('layouts/header.php'); ?>

<div class="row">
<div class="col-md-6">

<div class="panel panel-default">

<div class="panel-heading">
<strong>Edit Sequence</strong>
</div>

<div class="panel-body">

<form method="post">

<div class="form-group">
<label>Sequence Category</label>
<select name="sequence_category" class="form-control" required>

<option value="invoice"
<?php if($sequence['sequence_category']=='invoice') echo 'selected'; ?>>
Invoice
</option>

<option value="quotation"
<?php if($sequence['sequence_category']=='quotation') echo 'selected'; ?>>
Quotation
</option>

</select>
</div>

<div class="form-group">
<label>Last No</label>
<input type="number"
name="last_no"
class="form-control"
value="<?= $sequence['last_no']; ?>"
required>
</div>

<button class="btn btn-success" name="update_sequence">
Update
</button>

<a href="master_sequence.php" class="btn btn-default">
Cancel
</a>

</form>

</div>
</div>

</div>
</div>

<?php include_once('layouts/footer.php'); ?>
