<?php
$page_title = 'Admin Dashboard';
require_once('includes/load.php');

/* LAST 7 DAYS SALES */

$sales_chart = find_by_sql("
SELECT 
DATE(t.entry_date) as date,
SUM(t.sale_net) as total,
GROUP_CONCAT(DISTINCT mc.center_name SEPARATOR ', ') as centers

FROM transaction_master t

LEFT JOIN master_center mc
ON mc.center_id = t.center_id

WHERE t.transaction_type = 2

GROUP BY DATE(t.entry_date)

ORDER BY DATE(t.entry_date) ASC

LIMIT 7
");

$centers = [];

foreach($sales_chart as $s){

$centers[] = $s['centers'];

}

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
AND DATE(entry_date)=CURDATE()
");

$today_revenue = $today_revenue[0]['total'];




/* MONTH REVENUE */

$month_revenue = find_by_sql("
SELECT COALESCE(SUM(sale_net),0) as total

FROM transaction_master

WHERE transaction_type = 2
AND MONTH(entry_date)=MONTH(CURDATE())
AND YEAR(entry_date)=YEAR(CURDATE())
");

$month_revenue = $month_revenue[0]['total'];
/* TODAY PAYMENT MODE */

$today_modes = find_by_sql("
SELECT 
pm.mode_name,
SUM(p.amount) as total

FROM payments p

LEFT JOIN payment_mode_master pm
ON pm.mode_name = p.payment_mode

WHERE DATE(p.created_at)=CURDATE()

GROUP BY p.payment_mode
");

/* MONTH PAYMENT MODE */

$month_modes = find_by_sql("
SELECT 
pm.mode_name,
SUM(p.amount) as total

FROM payments p

LEFT JOIN payment_mode_master pm
ON pm.mode_name = p.payment_mode

WHERE MONTH(p.created_at)=MONTH(CURDATE())
AND YEAR(p.created_at)=YEAR(CURDATE())

GROUP BY p.payment_mode
");

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
    margin-bottom:18px;
}

.rev-card{
    background:linear-gradient(135deg,#a10805,#7f1d1d);
    border:none;
    border-radius:14px;
    padding:18px;
    box-shadow:0 10px 30px rgba(0,0,0,0.12);
    overflow:hidden;
    position:relative;

    display:flex;
    justify-content:space-between;
    align-items:center;

    min-height:145px;
}
.rev-left{
    width:65%;
}

.rev-right{
    width:35%;
    text-align:right;
}

.rev-mode{
    font-size:12px;
    color:#fff;
    margin-bottom:5px;
    white-space:nowrap;
}

.rev-mode span{
    font-weight:700;
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
    margin-bottom:12px;
    font-weight:600;
}

.rev-value{
    font-size:34px;
    font-weight:800;
    color:#fff;
    line-height:1;
}

.rev-growth{
    margin-top:12px;
    color:#fff;
    font-size:13px;
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
    height:220px;
}

@media(max-width:991px){

.dh-body{
    grid-template-columns:1fr;
}

.top-cards{
    grid-template-columns:1fr;
}

}

</style>

<div id="main-content">

<div id="dash-wrap">

<div class="top-cards">

<div class="rev-card">

<div class="rev-left">

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

<div class="rev-right">

<?php foreach($today_modes as $tm): ?>

<div class="rev-mode">
<?= strtoupper($tm['mode_name']); ?> :
<span>₹<?= number_format($tm['total']); ?></span>
</div>

<?php endforeach; ?>

</div>

</div>

   <div class="rev-card">

<div class="rev-left">

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

<div class="rev-right">

<?php foreach($month_modes as $mm): ?>

<div class="rev-mode">
<?= strtoupper($mm['mode_name']); ?> :
<span>₹<?= number_format($mm['total']); ?></span>
</div>

<?php endforeach; ?>

</div>

</div>

</div>

<div class="dh-body">

    <div class="dh-panel">

        <div class="dh-sec">
            Sales Overview — Last 7 Days
        </div>

        <div class="dh-chart-wrap">
            <canvas id="salesChart"></canvas>
        </div>

    </div>

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

    centerNames: <?= json_encode($centers); ?>,

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

    plugins:{
    tooltip:{
        callbacks:{
            afterLabel:function(context){

                return 'Centers: ' + context.dataset.centerNames[context.dataIndex];

            }
        }
    }
},
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
        maintainAspectRatio:false
    }

});

</script>

<?php include_once('layouts/footer.php'); ?>
