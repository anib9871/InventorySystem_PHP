<?php
  $page_title = 'All User';
  require_once('includes/load.php');
?>
<?php
// Checkin What level user has permission to view this page
 page_require_level(1);
//pull out all user form database
 $all_users = find_all_user();
 $edit_user = null;

if(isset($_GET['edit'])){
  $edit_user = find_by_id('users',(int)$_GET['edit']);
}
?>
<?php include_once('layouts/header.php'); ?>

<div class="row">
   <div class="col-md-12">
     <?php echo display_msg($msg); ?>
   </div>
</div>

<div class="row">

<!-- LEFT SIDE : CREATE USER -->
<div class="col-md-4">
<?php include_once('add_user.php'); ?>
</div>

<!-- RIGHT SIDE : USERS LIST -->
<div class="col-md-8">

<div class="panel panel-default">

<div class="panel-heading">
<strong>
<span class="glyphicon glyphicon-th"></span>
Users List
</strong>
</div>

</div>


<div class="panel-body">

<!-- SEARCH BAR -->
<div class="row" style="margin-bottom:10px;">
  <div class="col-md-4">
    <input type="text" id="userSearch" class="form-control" placeholder="Search user...">
  </div>
</div>

<div class="table-responsive">
<table class="table table-bordered table-striped" id="usersTable">
        <thead>
          <tr>
            <th class="text-center" style="width: 50px;">#</th>
            <th>Name </th>
            <th>Username</th>
            <th class="text-center" style="width: 15%;">User Role</th>
            <th class="text-center" style="width: 10%;">Status</th>
            <th style="width: 20%;">Last Login</th>
            <th class="text-center" style="width: 100px;">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($all_users as $a_user): ?>
          <tr>
           <td class="text-center"><?php echo count_id();?></td>
           <td><?php echo remove_junk(ucwords($a_user['name']))?></td>
           <td><?php echo remove_junk(ucwords($a_user['username']))?></td>
           <td class="text-center"><?php echo remove_junk(ucwords($a_user['group_name']))?></td>
           <td class="text-center">
           <?php if($a_user['status'] === '1'): ?>
            <span class="label label-success"><?php echo "Active"; ?></span>
          <?php else: ?>
            <span class="label label-danger"><?php echo "Deactive"; ?></span>
          <?php endif;?>
           </td>
           <td><?php echo read_date($a_user['last_login'])?></td>
           <td class="text-center">
             <div class="btn-group">
                <a href="users.php?edit=<?php echo (int)$a_user['id'];?>" class="btn btn-xs btn-warning" data-toggle="tooltip" title="Edit">
                  <i class="glyphicon glyphicon-pencil"></i>
               </a>
                <a href="delete_user.php?id=<?php echo (int)$a_user['id'];?>" class="btn btn-xs btn-danger" data-toggle="tooltip" title="Remove">
                  <i class="glyphicon glyphicon-remove"></i>
                </a>
                </div>
           </td>
          </tr>
        <?php endforeach;?>
</tbody>
</table>
</div>
</div>
</div>


<?php include_once('layouts/footer.php'); ?>

<script>

document.getElementById("userSearch").addEventListener("keyup", function() {

let filter = this.value.toLowerCase();
let rows = document.querySelectorAll("#usersTable tbody tr");

rows.forEach(function(row){

let text = row.textContent.toLowerCase();

if(text.indexOf(filter) > -1){
row.style.display = "";
}else{
row.style.display = "none";
}

});

});

</script>
