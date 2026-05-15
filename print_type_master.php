<?php
$page_title = 'Print Type Master';
require_once('includes/load.php');
//page_require_level(1);

/* FETCH LIST */
$print_types = find_by_sql("
SELECT *
FROM print_type_master
ORDER BY id ASC
");

/* ADD */
if(isset($_POST['add_print'])){

    $print_name = remove_junk($db->escape($_POST['print_name']));
    $paper_width = remove_junk($db->escape($_POST['paper_width']));
    $css_width = remove_junk($db->escape($_POST['css_width']));
    $status = remove_junk($db->escape($_POST['status']));

    $sql = "INSERT INTO print_type_master
    (print_name,paper_width,css_width,status)

    VALUES

    ('{$print_name}',
     '{$paper_width}',
     '{$css_width}',
     '{$status}')";

    if($db->query($sql)){

        $session->msg("s","Print Type Added");

    } else {

        $session->msg("d","Failed to Add");
    }

    redirect('print_type_master.php',false);
}

/* EDIT */
$edit = false;

if(isset($_GET['edit'])){

    $id = (int)$_GET['edit'];

    $edit = find_by_id(
        'print_type_master',
        $id
    );
}

/* UPDATE */
if(isset($_POST['update_print'])){

    $id = (int)$_POST['id'];

    $print_name = remove_junk($db->escape($_POST['print_name']));
    $paper_width = remove_junk($db->escape($_POST['paper_width']));
    $css_width = remove_junk($db->escape($_POST['css_width']));
    $status = remove_junk($db->escape($_POST['status']));

    $sql = "UPDATE print_type_master SET

            print_name='{$print_name}',
            paper_width='{$paper_width}',
            css_width='{$css_width}',
            status='{$status}'

            WHERE id='{$id}'";

    if($db->query($sql)){

        $session->msg("s","Updated Successfully");

    } else {

        $session->msg("d","Update Failed");
    }

    redirect('print_type_master.php',false);
}

/* DELETE */
if(isset($_GET['del'])){

    $id = (int)$_GET['del'];

    $db->query("
    DELETE FROM print_type_master
    WHERE id='{$id}'
    ");

    $session->msg("s","Deleted Successfully");

    redirect('print_type_master.php',false);
}

include_once('layouts/header.php');
?>

<style>

.equal-btn{
    min-width:60px;
    text-align:center;
}

.add-box{
    margin-top:-50px;
}

</style>

<div class="row">

<div class="col-md-12">
<?php echo display_msg($msg); ?>
</div>

</div>

<div class="row">

<!-- FORM -->

<div class="col-md-4 add-box">

<div class="panel panel-default">

<div class="panel-heading">
<strong>

<?php echo $edit ? 'Edit Print Type' : 'Add Print Type'; ?>

</strong>
</div>

<div class="panel-body">

<form method="post">

<?php if($edit): ?>

<input type="hidden"
name="id"
value="<?php echo $edit['id']; ?>">

<?php endif; ?>

<div class="form-group">

<label>Print Name</label>

<input type="text"
name="print_name"
class="form-control"

value="<?php echo $edit ? $edit['print_name'] : ''; ?>"

placeholder="Example: 80MM Thermal"

required>

</div>

<div class="form-group">

<label>Paper Width</label>

<input type="text"
name="paper_width"
class="form-control"

value="<?php echo $edit ? $edit['paper_width'] : ''; ?>"

placeholder="80mm"

required>

</div>

<div class="form-group">

<label>CSS Width</label>

<input type="text"
name="css_width"
class="form-control"

value="<?php echo $edit ? $edit['css_width'] : ''; ?>"

placeholder="78mm"

required>

</div>

<div class="form-group">

<label>Status</label>

<select name="status"
class="form-control">

<option value="Active"
<?php
if($edit && $edit['status']=="Active")
echo "selected";
?>>
Active
</option>

<option value="Inactive"
<?php
if($edit && $edit['status']=="Inactive")
echo "selected";
?>>
Inactive
</option>

</select>

</div>

<?php if($edit): ?>

<button type="submit"
name="update_print"
class="btn btn-danger btn-block">

Update

</button>

<a href="print_type_master.php"
class="btn btn-default btn-block">

Cancel

</a>

<?php else: ?>

<button type="submit"
name="add_print"
class="btn btn-danger btn-block">

Save

</button>

<button type="reset"
class="btn btn-default btn-block">

Clear

</button>

<?php endif; ?>

</form>

</div>
</div>
</div>

<!-- LIST -->

<div class="col-md-8">

<div class="panel panel-default">

<div class="panel-heading">
<strong>Print Type List</strong>
</div>

<div class="panel-body">

<input type="text"
id="search"
class="form-control"

placeholder="Search Print Type...">

<br>

<div class="table-responsive">

<table class="table table-bordered table-striped">

<thead>

<tr>

<th>#</th>
<th>Print Name</th>
<th>Paper Width</th>
<th>CSS Width</th>
<th>Status</th>
<th>Action</th>

</tr>

</thead>

<tbody id="printTable">

<?php foreach($print_types as $i => $p): ?>

<tr>

<td><?php echo $i+1; ?></td>

<td><?php echo $p['print_name']; ?></td>

<td><?php echo $p['paper_width']; ?></td>

<td><?php echo $p['css_width']; ?></td>

<td><?php echo $p['status']; ?></td>

<td>

<a href="print_type_master.php?edit=<?php echo $p['id']; ?>"
class="btn btn-info btn-xs equal-btn">

Edit

</a>

<a href="print_type_master.php?del=<?php echo $p['id']; ?>"
onclick="return confirm('Delete this print type?')"
class="btn btn-danger btn-xs equal-btn">

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

</div>

<script>

document.getElementById("search")
.addEventListener("keyup", function(){

    let value = this.value.toLowerCase();

    document.querySelectorAll("#printTable tr")
    .forEach(function(row){

        row.style.display =
            row.textContent.toLowerCase()
            .includes(value)

            ? ""

            : "none";
    });

});

</script>

<?php include_once('layouts/footer.php'); ?>