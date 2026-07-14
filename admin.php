<?php
$page_title = 'Admin Dashboard';
require_once('includes/load.php');

/* LAST 7 DAYS SALES */

$sales_chart = find_by_sql("
SELECT 
DATE_FORMAT(MIN(t.entry_date),'%b %Y') as month_name,
SUM(t.sale_net) as total,
GROUP_CONCAT(DISTINCT mc.center_name SEPARATOR ', ') as centers

FROM transaction_master t

LEFT JOIN master_center mc
ON mc.center_id = t.center_id

WHERE t.transaction_type = 2

GROUP BY YEAR(t.entry_date), MONTH(t.entry_date)

ORDER BY YEAR(t.entry_date), MONTH(t.entry_date)
");

$centers = [];

foreach($sales_chart as $s){

$centers[] = $s['centers'];

}

$dates = [];
$totals = [];

foreach($sales_chart as $s){

   $dates[]  = $s['month_name'];
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

AND MONTH(t.entry_date)=MONTH(CURDATE())
AND YEAR(t.entry_date)=YEAR(CURDATE())

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




/* MONTH PAYMENT COLLECTION */

$month_revenue = find_by_sql("
SELECT COALESCE(SUM(amount),0) as total

FROM payments

WHERE MONTH(created_at)=MONTH(CURDATE())
AND YEAR(created_at)=YEAR(CURDATE())
");

$month_revenue = $month_revenue[0]['total'];

/* MONTH SALES */

$month_sales = find_by_sql("
SELECT COALESCE(SUM(sale_net),0) as total

FROM transaction_master

WHERE transaction_type = 2
AND MONTH(entry_date)=MONTH(CURDATE())
AND YEAR(entry_date)=YEAR(CURDATE())
");

$month_sales = $month_sales[0]['total'];
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
    grid-template-columns:1fr 1fr 1fr;
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
    flex:1;
    min-width:0;
}

.rev-right{
    min-width:180px;
    text-align:right;
}

.rev-mode{
    font-size:10px;          /* Pehle 12px tha */
    color:#fff;
    margin-bottom:4px;
    line-height:1.3;
    white-space:normal;
    word-break:break-word;
}

.rev-mode span{
    font-size:10px;
    font-weight:600;
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

<?php
$month_mode_text = [];

foreach($month_modes as $mm){
    $month_mode_text[] =
    strtoupper($mm['mode_name']).' : ₹'.number_format($mm['total']);
}
?>

<div class="rev-mode">
<?= implode('<br>', $month_mode_text); ?>
</div>
</div>

</div>

<div class="rev-card">

<div class="rev-left">

    <div class="rev-label">
        MONTH SALES
    </div>

    <div class="rev-value">
        ₹<?= number_format($month_sales); ?>
    </div>

    <div class="rev-growth">
        ↑ Total Invoice Sales
    </div>

</div>

<div class="rev-right">

<div class="rev-mode">
SALES :
<span>₹<?= number_format($month_sales); ?></span>
</div>

</div>

</div>

</div> <!-- top-cards close -->

<div class="dh-body">

    <div class="dh-panel">

        <div class="dh-sec">
            Sales Overview — Monthly Sales
        </div>

        <div class="dh-chart-wrap">
            <canvas id="salesChart"></canvas>
        </div>

    </div>

    <div class="dh-panel">

        <div class="dh-sec">
            Top 5 Sales Contributors — This Month
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

var salesCanvas = document.getElementById('salesChart');

if(salesCanvas){

var ctx1 = salesCanvas.getContext('2d');

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

    scales:{
        x:{
            ticks:{
                autoSkip:true,
                maxTicksLimit:6,
                maxRotation:0,
                minRotation:0
            }
        },
        y:{
            beginAtZero:true
        }
    },

    plugins:{
        tooltip:{
            callbacks:{
                afterLabel:function(context){

                    return 'Centers: ' +
                    context.dataset.centerNames[context.dataIndex];

                }
            }
        }
    }
}

});
}
/* TOP PRODUCTS */

var topCanvas = document.getElementById('topProductsChart');

if(topCanvas){

var ctx2 = topCanvas.getContext('2d');

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
}
</script>

<?php include_once('layouts/footer.php'); ?>
