<?php

$page_title = 'Terms & Conditions Master';

require_once('includes/load.php');


/* =========================
   SAVE / UPDATE
========================= */

if(isset($_POST['save_terms']))
{

    $tc_id         = (int)$_POST['tc_id'];
    $template_name = remove_junk(
    $db->escape($_POST['template_name'])
    );

    $template = remove_junk(
    $db->escape($_POST['template'])
    );

    $updated_by = $user['name'];


    // INSERT
    if($tc_id == 0)
    {

        $query = "

INSERT INTO terms_conditions_master
(
template_name,
template,
updated_by
)
        VALUES
(
'{$template_name}',
'{$template}',
'{$updated_by}'
)

        ";

        if($db->query($query))
        {
            $session->msg(
            's',
            'Template Added Successfully'
            );

            redirect('terms_conditions_master.php',false);
        }

    }

    // UPDATE
    else
    {

        $query = "

        UPDATE terms_conditions_master

        SET

        template_name = '{$template_name}',
        template      = '{$template}',
        updated_by    = '{$updated_by}',
        updated_at    = NOW()

        WHERE tc_id = '{$tc_id}'

        ";

        if($db->query($query))
        {
            $session->msg(
            's',
            'Template Updated Successfully'
            );

            redirect('terms_conditions_master.php',false);
        }

    }

}



/* =========================
   DELETE
========================= */

if(isset($_GET['delete']))
{

    $id = (int)$_GET['delete'];

    $query = "
    DELETE FROM terms_conditions_master
    WHERE tc_id = '{$id}'
    ";

    if($db->query($query))
    {
        $session->msg(
        's',
        'Template Deleted Successfully'
        );

        redirect('terms_conditions_master.php',false);
    }

}



/* =========================
   EDIT FETCH
========================= */

$edit = null;

if(isset($_GET['edit']))
{

    $id = (int)$_GET['edit'];

$edit_query = find_by_sql("
SELECT *
FROM terms_conditions_master
WHERE tc_id = '{$id}'
LIMIT 1
");

$edit = $edit_query[0];

}



/* =========================
   FETCH ALL
========================= */

$templates = find_by_sql("

SELECT *
FROM terms_conditions_master
ORDER BY tc_id DESC

");

?>

<?php include_once('layouts/header.php'); ?>


<div class="row">


    <!-- LEFT SIDE -->

    <div class="col-md-5">

        <div class="panel panel-default">

            <div class="panel-heading">

                <strong>

                    Add / Update Template

                </strong>

            </div>

            <div class="panel-body">

                <form method="POST">


                    <!-- HIDDEN ID -->

                    <input
                    type="hidden"
                    name="tc_id"

                    value="<?php
                    echo ($edit)
                    ? $edit['tc_id']
                    : 0;
                    ?>">

        <div class="form-group">

<label>Template Name</label>

<input
type="text"
name="template_name"
class="form-control"
required
value="<?= ($edit) ? $edit['template_name'] : ''; ?>">

</div>

 

                    <!-- TEMPLATE -->

                    <div class="form-group">

                        <label>
                            Terms & Conditions
                        </label>

                        <textarea
                        name="template"
                        id="template"
                        rows="10"
                        class="form-control"
                        required><?php

                        echo ($edit)
                        ? $edit['template']
                        : '';

                        ?></textarea>

                    </div>



                    <!-- BUTTON -->

                    <button
                    type="submit"
                    name="save_terms"
                    class="btn btn-success">

                        <i class="glyphicon glyphicon-floppy-disk"></i>

                        <?php
                        echo ($edit)
                        ? ' Update Template'
                        : ' Save Template';
                        ?>

                    </button>


                    <a
                    href="terms_conditions_master.php"
                    class="btn btn-default">

                        Clear

                    </a>

                </form>

            </div>

        </div>

    </div>





    <!-- RIGHT SIDE -->

    <div class="col-md-7">


        <!-- LIVE PREVIEW -->

        <div class="panel panel-primary">

            <div class="panel-heading">

                <strong>

                    Live Preview

                </strong>

            </div>

            <div class="panel-body">

                <div
                id="previewBox"

                style="
                min-height:220px;
                white-space:pre-line;
                background:#f9f9f9;
                border:1px solid #ddd;
                padding:15px;
                border-radius:5px;
                ">

                <?php

                if($edit)
                {
                    echo nl2br($edit['template']);
                }
                else
                {
                    echo "Terms preview will appear here...";
                }

                ?>

                </div>

            </div>

        </div>




        <!-- TABLE -->

        <div class="panel panel-default">

            <div class="panel-heading">

                <strong>

                    Saved Templates

                </strong>

            </div>

            <div class="panel-body">

                <input
                type="text"
                id="searchTemplate"
                class="form-control"
                placeholder="Search Template">

                <br>


                <div class="table-responsive">

                    <table class="table table-bordered table-striped">

                        <thead>

                            <tr>

                                <th>#</th>

                                <th>Template Name</th>
                                <th>Preview</th>

                                <th>Updated By</th>

                                <th>Updated At</th>

                                <th>Action</th>

                            </tr>

                        </thead>



                        <tbody id="templateTable">

                        <?php foreach($templates as $t): ?>

                            <tr>

                                <td>
                                    <?= $t['tc_id']; ?>
                                </td>

<td>
<?= $t['template_name']; ?>
</td>

<td>
<?= substr($t['template'],0,80); ?>...
</td>


                                <td>
                                    <?= $t['updated_by']; ?>
                                </td>


                                <td>
    <?= !empty($t['updated_at']) ? date('d/M/Y h:i A', strtotime($t['updated_at'])) : '-'; ?>
</td>


                                <td>

                                    <!-- EDIT -->

                                    <a
                                    href="terms_conditions_master.php?edit=<?= $t['tc_id']; ?>"

                                    class="btn btn-xs btn-primary">

                                        <i class="glyphicon glyphicon-edit"></i>

                                    </a>



                                    <!-- DELETE -->

                                    <a

                                    href="terms_conditions_master.php?delete=<?= $t['tc_id']; ?>"

                                    class="btn btn-xs btn-danger"

                                    onclick="
                                    return confirm(
                                    'Delete this template?'
                                    )
                                    ">

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

</div>




<script>


/* =========================
   LIVE PREVIEW
========================= */

document.getElementById("template")

.addEventListener("keyup", function(){

    let value = this.value.trim();

    document.getElementById("previewBox")

    .innerText =

    (value == "")
    ? "Terms preview will appear here..."
    : value;

});



/* =========================
   SEARCH
========================= */

document.getElementById("searchTemplate")

.addEventListener("keyup", function(){

    let value = this.value.toLowerCase();

    document.querySelectorAll(
    "#templateTable tr"
    )

    .forEach(function(row){

        row.style.display =

        row.innerText.toLowerCase()
        .includes(value)

        ? ""

        : "none";

    });

});


</script>


<?php include_once('layouts/footer.php'); ?>
