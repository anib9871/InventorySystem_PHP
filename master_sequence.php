<?php
require_once('includes/load.php');
//page_require_level(2);

$sequences = find_by_sql("

SELECT s.*,
       f.fy_name

FROM sequence_master s

LEFT JOIN financial_year_master f
ON f.fy_id = s.fy_id

ORDER BY s.sequence_id DESC
");

/* ADD */
if(isset($_POST['add_sequence'])){

  $category = remove_junk($_POST['sequence_category']);
  $value    = (int)$_POST['last_no'];
 $current_fy = find_by_sql("
SELECT fy_id, fy_name
FROM financial_year_master
WHERE is_active = 1
LIMIT 1
");

$fy_id = $current_fy[0]['fy_id'];

$financial_year =
$current_fy[0]['fy_name'];
  $prefix = $db->escape($_POST['prefix']);
  

  $check = find_by_sql("SELECT * FROM sequence_master 
  WHERE sequence_category = '{$category}'
AND fy_id = '{$fy_id}'");

  if($check){
      $session->msg('d',"Sequence already exists");
      redirect('master_sequence.php',false);
  }

$sql = "INSERT INTO sequence_master
(sequence_category,fy_id,last_no,prefix)

VALUES

('{$category}',
 '{$fy_id}',
 '{$value}',
 '{$prefix}')";

  if($db->query($sql)){
      $session->msg('s',"Sequence Added");
      redirect('master_sequence.php',false);
  }else{
      $session->msg('d',"Insert Failed");
      redirect('master_sequence.php',false);
  }
}

/* DELETE */
if(isset($_GET['delete'])){
  $id = (int)$_GET['delete'];
  delete_by_id('sequence_master',$id);
  redirect('master_sequence.php');
}

?>

<?php include_once('layouts/header.php'); ?>

<div class="row">

<!-- LEFT SIDE FORM -->
<div class="col-md-4">

<div class="panel panel-default">
<div class="panel-heading">
<strong>Add Sequence</strong>
</div>

<div class="panel-body">

<form method="post">

<div class="form-group">
<label>Sequence Category</label>
<select name="sequence_category" class="form-control" required>
<option value="">Select</option>
<option value="invoice">Invoice</option>
<option value="quotation">Quotation</option>
</select>
</div>

<div class="form-group">
<label>Last No</label>
<input type="number" name="last_no" class="form-control" required>
</div>


<div class="form-group">

<label>Financial Year</label>

<input type="text"
class="form-control"
value="<?= $_SESSION['financial_year']; ?>"
readonly>

</div>

<div class="form-group">
<label>Prefix</label>
<input type="text" 
name="prefix" 
class="form-control"
placeholder="INV">
</div>



<button class="btn btn-success" name="add_sequence">
Add Sequence
</button>

</form>

</div>
</div>
</div>

<!-- RIGHT SIDE LIST -->

<div class="col-md-8">

<div class="panel panel-default">

<div class="panel-heading">
<strong>Sequence List</strong>
</div>

<div class="panel-body">

<table class="table table-bordered">

<thead>
<tr>
<th>#</th>
<th>Sequence Category</th>
<th>Last no</th>
<th>Financial Year</th>
<th>Prefix</th>

<th>Action</th>
</tr>
</thead>

<tbody>

<?php foreach($sequences as $seq): ?>

<tr>

<td><?= count_id(); ?></td>

<td><?= ucfirst($seq['sequence_category']); ?></td>

<td><?= $seq['last_no']; ?></td>

<td><?= $seq['fy_name']; ?></td>

<td><?= $seq['prefix']; ?></td>




<td>

<a href="edit_sequence.php?id=<?= $seq['sequence_id']; ?>" 
class="btn btn-xs btn-primary">
Edit
</a>

<a href="master_sequence.php?delete=<?= $seq['sequence_id']; ?>" 
class="btn btn-xs btn-danger">
Delete
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

<?php include_once('layouts/footer.php'); ?>
