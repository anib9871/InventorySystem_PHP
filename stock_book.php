<?php
require_once('includes/load.php');
// page_require_level(2);

$products = find_all('products');



?>

<?php include_once('layouts/header.php'); ?>

<style>

@media print {

    @page{
    size: A4 portrait;
    margin: 10mm;
}

    /* Sab kuch hide */
    body *{
        visibility:hidden;
    }

    /* Sirf report dikhao */
    .panel,
    .panel *{
        visibility:visible;
    }

.panel{
    position:absolute;
    left:0;
    top:0;
    width:100%;
    box-shadow:none !important;
    border:1px solid #000 !important;
}

   body{
    font-size:12px;
}

    .btn{
        display:none !important;
    }

    table{
        width:100% !important;
        border-collapse:collapse !important;
    }

    th, td{
        border:1px solid #000 !important;
        padding:8px !important;
        text-align:center;
    }

    .panel-heading{
        font-size:22px;
        font-weight:bold;
        text-align:center;
        margin-bottom:15px;
    }
}

#stockTable th{
    cursor:pointer;
    position:relative;
    padding-right:22px;
}

#stockTable th:after{
    font-family:FontAwesome;
    content:"\f0dc";   /* sort */
    position:absolute;
    right:8px;
    color:#c5c5c5;
}

#stockTable th.asc:after{
    content:"\f0de";   /* sort-up */
    color:#007bff;
}

#stockTable th.desc:after{
    content:"\f0dd";   /* sort-down */
    color:#007bff;
}

</style>

<div class="panel panel-default">
<div class="panel-heading">
<strong>Stock Book Report</strong>
</div>

<div class="panel-body">

<div class="row" style="margin-bottom:15px;">
    <div class="col-md-4 pull-right">
        <input
            type="text"
            id="stockSearch"
            class="form-control"
            placeholder="Search Product Name..."
        >
    </div>
</div>

<div style="margin-bottom:15px; text-align:right;">

<button
type="button"
onclick="window.print();"
class="btn btn-danger">

<i class="fa fa-file-pdf-o"></i> Export PDF

</button>

</div>

<?php

$stock_report = find_by_sql("

SELECT 
    p.id,
    p.name,
    p.reorder_level,

    SUM(
        CASE 
        WHEN t.transaction_type = 1 
        THEN t.quantity 
        ELSE 0 
        END
    ) as total_in,

    SUM(
        CASE 
        WHEN t.transaction_type = 2 
        THEN t.quantity 
        ELSE 0 
        END
    ) as total_out

FROM products p

LEFT JOIN transaction_master t
ON p.id = t.product_id

WHERE p.type =1

GROUP BY p.id

ORDER BY p.name ASC

");

?>

<table class="table table-bordered table-striped" id="stockTable">

<thead>
<tr style="background:#f2f2f2;">
    <th style="cursor:pointer;">#</th>
    <th style="cursor:pointer;">Product Name</th>
    <th style="cursor:pointer;">Current Stock</th>
    <th style="cursor:pointer;">Reorder Level</th>
    <th style="cursor:pointer;">Status</th>
</tr>
</thead>

<tbody>

<?php
$i = 1;

foreach($stock_report as $row):

$current_stock =
(float)$row['total_in']
-
(float)$row['total_out'];

$reorder = (float)$row['reorder_level'];

$status = "In Stock";
$color  = "#b6d7a8";

if($current_stock <= $reorder){

    $status = "Low Stock";
    $color  = "#f9cb9c";
}

if($current_stock <= ($reorder / 2)){

    $status = "Critically Low";
    $color  = "#f4cccc";
}
?>

<tr>

<td><?= $i++; ?></td>

<td><?= $row['name']; ?></td>

<td><?= $current_stock; ?></td>

<td><?= $reorder; ?></td>

<td style="
background:<?= $color ?>;
font-weight:bold;
text-align:center;
">

<?= $status ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>



</div>
</div>

<script>
document.getElementById("stockSearch").addEventListener("keyup", function () {

    var value = this.value.toLowerCase();
    var rows = document.querySelectorAll("#stockTable tbody tr");

    rows.forEach(function(row){

        var text = row.innerText.toLowerCase();

        if(text.indexOf(value) > -1){
            row.style.display = "";
        }else{
            row.style.display = "none";
        }

    });

});


const table = document.getElementById("stockTable");
const headers = table.querySelectorAll("th");

headers.forEach((header, index) => {

    let asc = true;

    header.addEventListener("click", function () {

        // Sab headers se arrow class hata do
        headers.forEach(h=>{
            if(h!==header){
                h.classList.remove("asc","desc");
            }
        });

        const tbody = table.querySelector("tbody");
        const rows = Array.from(tbody.querySelectorAll("tr"));

        rows.sort(function(a,b){

            let x = a.cells[index].innerText.trim();
            let y = b.cells[index].innerText.trim();

            if(!isNaN(parseFloat(x)) && !isNaN(parseFloat(y))){
                return asc
                    ? parseFloat(x)-parseFloat(y)
                    : parseFloat(y)-parseFloat(x);
            }

            return asc
                ? x.localeCompare(y)
                : y.localeCompare(x);

        });

        rows.forEach(row=>tbody.appendChild(row));

        if(asc){
            header.classList.remove("desc");
            header.classList.add("asc");
        }else{
            header.classList.remove("asc");
            header.classList.add("desc");
        }

        asc=!asc;

    });

});


</script>

<?php include_once('layouts/footer.php'); ?>
