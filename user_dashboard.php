<?php
$page_title = 'User Dashboard';
require_once('includes/load.php');

$user_center_id = (int)$_SESSION['center_id'];

/* LAST 7 DAYS SALES */
$sales_chart = find_by_sql("
SELECT 
DATE(entry_date) as date,
SUM(sale_net) as total

FROM transaction_master

WHERE transaction_type = 2
AND center_id = '{$user_center_id}'

GROUP BY DATE(entry_date)

ORDER BY DATE(entry_date) ASC

LIMIT 7
");

$dates = [];
$totals = [];

foreach($sales_chart as $s){
   $dates[]  = $s['date'];
   $totals[] = $s['total'];
}

/* TOP 5 SALES CONTRIBUTORS */
$top_products = find_by_sql("
SELECT 
p.name,
SUM(t.sale_net) as total_sale

FROM transaction_master t

LEFT JOIN products p
ON p.id = t.product_id

WHERE t.transaction_type = 2
AND t.center_id = '{$user_center_id}'

AND DATE(t.entry_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)

GROUP BY t.product_id

ORDER BY total_sale DESC

LIMIT 5
");

$product_names = [];
$product_sales = [];

foreach($top_products as $tp){

   $product_names[] = $tp['name'];
   $product_sales[] = $tp['total_sale'];

}

/* TODAY REVENUE */

$today_revenue = find_by_sql("
SELECT COALESCE(SUM(sale_net),0) as total

FROM transaction_master

WHERE transaction_type = 2
AND center_id = '{$user_center_id}'
AND DATE(entry_date)=CURDATE()
");

$today_revenue = $today_revenue[0]['total'];


/* MONTH REVENUE */

$month_revenue = find_by_sql("
SELECT COALESCE(SUM(sale_net),0) as total

FROM transaction_master

WHERE transaction_type = 2
AND center_id = '{$user_center_id}'
AND MONTH(entry_date)=MONTH(CURDATE())
AND YEAR(entry_date)=YEAR(CURDATE())
");

$month_revenue = $month_revenue[0]['total'];

?>

<?php include_once('layouts/header.php'); ?>

<style>

#dash-wrap{
    padding:20px;
}

.top-cards{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14px;
    margin-bottom:20px;
}

.rev-card{
    background:linear-gradient(135deg,#a10805,#7f1d1d);
    border:none;
    border-radius:14px;
    padding:20px;
    box-shadow:0 10px 30px rgba(0,0,0,0.12);
    transition:0.3s ease;
    overflow:hidden;
    position:relative;
}

.rev-card:hover{
    transform:translateY(-4px);
}

.rev-card:before{
    content:'';
    position:absolute;
    top:0;
    left:0;
    width:5px;
    height:100%;
    background:#ffb703;
}

.rev-label{
    font-size:11px;
    letter-spacing:2px;
    color:#fff;
    margin-bottom:15px;
    font-weight:600;
}

.rev-value{
    font-size:42px;
    font-weight:800;
    color:#fff;
    line-height:1;
}

.rev-growth{
    margin-top:15px;
    color:#fff;
    font-size:14px;
    font-weight:600;
}

.dh-body{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14px;
}

.dh-panel{
    background:#fff;
    border:none;
    border-radius:14px;
    padding:14px;
    box-shadow:0 10px 25px rgba(0,0,0,0.08);
}

.dh-sec{
    font-size:12px;
    font-weight:600;
    letter-spacing:1px;
    text-transform:uppercase;
    margin-bottom:15px;
    color:#555;
}

.dh-chart-wrap{
    position:relative;
    width:100%;
    height:180px;
}



@media(max-width:991px){

.dh-body{
    grid-template-columns:1fr;
}

}

</style>

<div id="main-content">

<div id="dash-wrap">

<div class="top-cards">

    <div class="rev-card">

        <div class="rev-label">
            TODAY'S REVENUE
        </div>

        <div class="rev-value">
            ₹<?= number_format($today_revenue); ?>
        </div>

        <div class="rev-growth">
            ↑ Live Collection
        </div>

    </div>

    <div class="rev-card">

        <div class="rev-label">
            THIS MONTH
        </div>

        <div class="rev-value">
            ₹<?= number_format($month_revenue); ?>
        </div>

        <div class="rev-growth">
            ↑ Monthly Revenue
        </div>

    </div>

</div>

<div class="dh-body">

    <!-- SALES OVERVIEW -->

    <div class="dh-panel">

        <div class="dh-sec">
            Sales Overview — Last 7 Days
        </div>

        <div class="dh-chart-wrap">
            <canvas id="salesChart"></canvas>
        </div>

    </div>

    <!-- TOP 5 SALES -->

    <div class="dh-panel">

        <div class="dh-sec">
            Top 5 Sales Contributors — Last 7 Days
        </div>

        <div class="dh-chart-wrap">
            <canvas id="topProductsChart"></canvas>
        </div>

    </div>

</div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

/* SALES OVERVIEW */

var ctx1 = document.getElementById('salesChart').getContext('2d');

new Chart(ctx1, {

    type:'line',

    data:{
        labels: <?= json_encode($dates); ?>,

        datasets:[{

            label:'Sales ₹',

            data: <?= json_encode($totals); ?>,

            borderWidth:3,

            fill:true,

            tension:0.4,

            backgroundColor:'rgba(37,99,235,0.12)',

            borderColor:'#2563eb',

            pointBackgroundColor:'#fff',

            pointBorderColor:'#2563eb',

            pointRadius:5

        }]
    },

    options:{
        responsive:true,
        maintainAspectRatio:false,

        scales:{
            y:{
                beginAtZero:true
            }
        }
    }

});

/* TOP PRODUCTS */

var ctx2 = document.getElementById('topProductsChart').getContext('2d');

new Chart(ctx2, {

    type:'bar',

    data:{

        labels: <?= json_encode($product_names); ?>,

        datasets:[{

            label:'Sales ₹',

            data: <?= json_encode($product_sales); ?>,

            borderWidth:1,

            backgroundColor:'#22c55e',

            borderColor:'#16a34a'

        }]
    },

    options:{
        responsive:true,
        maintainAspectRatio:false,

        scales:{
            y:{
                beginAtZero:true
            }
        }
    }

});

</script>

<?php include_once('layouts/footer.php'); ?>