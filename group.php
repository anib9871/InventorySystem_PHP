<?php
$page_title = 'Group Management';
require_once('includes/load.php');

$all_groups = find_all('user_groups');

$edit_group = null;

/* =========================
   DELETE GROUP
========================= */
if(isset($_GET['delete'])){

    $delete_id = delete_by_id('user_groups',(int)$_GET['delete']);

    if($delete_id){
        $session->msg("s","Group deleted successfully.");
    } else {
        $session->msg("d","Failed to delete group.");
    }

    redirect('group.php', false);
}

/* =========================
   EDIT FETCH
========================= */
if(isset($_GET['edit'])){

    $edit_group = find_by_id('user_groups',(int)$_GET['edit']);
}

/* =========================
   ADD / UPDATE GROUP
========================= */
if(isset($_POST['save_group'])){

    $req_fields = array('group-name','group-level');
    validate_fields($req_fields);

    if(empty($errors)){

        $name   = remove_junk($db->escape($_POST['group-name']));
        $level  = remove_junk($db->escape($_POST['group-level']));
        $status = remove_junk($db->escape($_POST['status']));

        /* ======================
           UPDATE
        ====================== */
        if(isset($_POST['group_id']) && !empty($_POST['group_id'])){

            $group_id = (int)$_POST['group_id'];

            $query  = "UPDATE user_groups SET ";
            $query .= "group_name='{$name}', ";
            $query .= "group_level='{$level}', ";
            $query .= "group_status='{$status}' ";
            $query .= "WHERE id='{$group_id}'";

            if($db->query($query)){

                $session->msg('s',"Group updated successfully.");
            } else {

                $session->msg('d',"Failed to update group.");
            }

        } else {

            /* ======================
               INSERT
            ====================== */

            if(find_by_groupName($name)){

                $session->msg('d','Group name already exists.');
                redirect('group.php', false);
            }

            if(find_by_groupLevel($level)){

                $session->msg('d','Group level already exists.');
                redirect('group.php', false);
            }

            $query  = "INSERT INTO user_groups (";
            $query .= "group_name, group_level, group_status";
            $query .= ") VALUES (";
            $query .= "'{$name}','{$level}','{$status}'";
            $query .= ")";

            if($db->query($query)){

                $session->msg('s',"Group added successfully.");
            } else {

                $session->msg('d',"Failed to add group.");
            }
        }

    } else {

        $session->msg("d", $errors);
    }

    redirect('group.php', false);
}
?>

<?php include_once('layouts/header.php'); ?>

<div class="row">

    <!-- LEFT SIDE FORM -->
    <div class="col-md-4">

        <div class="panel panel-default">

            <div class="panel-heading">
                <strong>
                    <span class="glyphicon glyphicon-plus"></span>

                    <?php if($edit_group): ?>
                        Edit Group
                    <?php else: ?>
                        Add Group
                    <?php endif; ?>
                </strong>
            </div>

            <div class="panel-body">

                <?php echo display_msg($msg); ?>

                <form method="post" action="group.php">

                    <input type="hidden" name="group_id"
                    value="<?php echo $edit_group ? (int)$edit_group['id'] : ''; ?>">

                    <div class="form-group">
                        <label>Group Name</label>

                        <input type="text"
                               class="form-control"
                               name="group-name"
                               value="<?php echo $edit_group ? remove_junk($edit_group['group_name']) : ''; ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label>Group Level</label>

                        <input type="number"
                               class="form-control"
                               name="group-level"
                               value="<?php echo $edit_group ? (int)$edit_group['group_level'] : ''; ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label>Status</label>

                        <select class="form-control" name="status">

                            <option value="1"
                            <?php if($edit_group && $edit_group['group_status']=='1') echo 'selected'; ?>>
                                Active
                            </option>

                            <option value="0"
                            <?php if($edit_group && $edit_group['group_status']=='0') echo 'selected'; ?>>
                                Deactive
                            </option>

                        </select>
                    </div>

                    <button type="submit"
                            name="save_group"
                            class="btn btn-info">

                        <?php if($edit_group): ?>
                            Update Group
                        <?php else: ?>
                            Add Group
                        <?php endif; ?>

                    </button>

                    <?php if($edit_group): ?>

                        <a href="group.php" class="btn btn-default">
                            Cancel
                        </a>

                    <?php endif; ?>

                </form>

            </div>
        </div>
    </div>

    <!-- RIGHT SIDE TABLE -->
    <div class="col-md-8">

        <div class="panel panel-default">

            <div class="panel-heading">
                <strong>
                    <span class="glyphicon glyphicon-th"></span>
                    Groups List
                </strong>
            </div>

            <div class="panel-body">

                <table class="table table-bordered table-striped">

                    <thead>
                        <tr>
                            <th width="50">#</th>
                            <th>Group Name</th>
                            <th width="120">Level</th>
                            <th width="120">Status</th>
                            <th width="120">Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach($all_groups as $group): ?>

                        <tr>

                            <td><?php echo count_id(); ?></td>

                            <td>
                                <?php echo remove_junk(ucwords($group['group_name'])); ?>
                            </td>

                            <td>
                                <?php echo (int)$group['group_level']; ?>
                            </td>

                            <td>

                                <?php if($group['group_status']=='1'): ?>

                                    <span class="label label-success">
                                        Active
                                    </span>

                                <?php else: ?>

                                    <span class="label label-danger">
                                        Deactive
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <a href="group.php?edit=<?php echo (int)$group['id']; ?>"
                                   class="btn btn-warning btn-xs">

                                    <i class="glyphicon glyphicon-pencil"></i>
                                </a>

                                <a href="group.php?delete=<?php echo (int)$group['id']; ?>"
                                   class="btn btn-danger btn-xs"
                                   onclick="return confirm('Delete this group?')">

                                    <i class="glyphicon glyphicon-trash"></i>
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
