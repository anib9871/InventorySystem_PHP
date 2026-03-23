<?php
require_once('includes/load.php');

if(!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1){
header("Location: home.php");
exit();
}

$page_title = 'Super Admin Dashboard';

/* COUNTS */
$org_total = find_by_sql("SELECT COUNT(org_id) as total FROM master_inventory.master_organization")[0]['total'];
$center_total = find_by_sql("SELECT COUNT(center_id) as total FROM master_inventory.master_center")[0]['total'];
$user_total = find_by_sql("SELECT COUNT(id) as total FROM master_inventory.user_credentials")[0]['total'];

/* RECENT DATA */
$recent_orgs = find_by_sql("SELECT * FROM master_inventory.master_organization ORDER BY org_id DESC LIMIT 5");

$recent_centers = find_by_sql("
SELECT c.center_name,o.org_name 
FROM master_inventory.master_center c
LEFT JOIN master_inventory.master_organization o ON o.org_id=c.org_id
ORDER BY c.center_id DESC LIMIT 5
");

$recent_users = find_by_sql("
SELECT u.username,o.org_name,c.center_name
FROM master_inventory.user_credentials u
LEFT JOIN master_inventory.master_organization o ON o.org_id=u.org_id
LEFT JOIN master_inventory.master_center c ON c.center_id=u.center_id
ORDER BY u.id DESC LIMIT 5
");

include_once('layouts/header.php');
?>

<!-- FONT AWESOME -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

<style>
.dashboard-card{
  border-radius:10px;
  color:#fff;
  padding:14px 12px;
  text-align:center;
  transition:0.3s;
  box-shadow:0 3px 10px rgba(0,0,0,0.08);
  min-height:85px;
  margin-bottom:15px;   /* 🔥 GAP BETWEEN CARDS */
}

.dashboard-card:hover{
  transform:translateY(-4px);
  box-shadow:0 8px 20px rgba(0,0,0,0.15);
}

/* COLORS */
.bg-org{ background:linear-gradient(45deg,#007bff,#00c6ff); }
.bg-center{ background:linear-gradient(45deg,#28a745,#6ddf7a); }
.bg-user{ background:linear-gradient(45deg,#f39c12,#f1c40f); }

/* TEXT */
.dashboard-card h3{
  font-size:20px;
  margin:0;
}
.dashboard-card p{
  font-size:12px;
  margin:4px 0 0;
}

.dashboard-card i{
  font-size:16px;
}

/* 🔥 ROW GAP CONTROL */
.row{
  margin-bottom:10px;
}

/* 🔥 BUTTON GAP */
.btn-block{
  margin-bottom:12px;
}

/* 🔥 PANEL GAP */
.panel{
  margin-bottom:20px;
  border-radius:8px;
}

/* ============================= */
/* 📱 MOBILE FIX */
/* ============================= */

@media (max-width: 768px){

  .row{
    margin-left: -6px;
    margin-right: -6px;
    margin-bottom:5px;
  }

  .row > [class*='col-']{
    padding-left: 6px;
    padding-right: 6px;
    margin-bottom:12px;
  }

  .dashboard-card{
    padding:12px;
    min-height:75px;
    margin-bottom:12px;  /* 🔥 MOBILE GAP */
  }

  .dashboard-card h3{
    font-size:18px;
  }

  .dashboard-card p{
    font-size:11px;
  }

  .btn{
    padding:10px;
    font-size:13px;
  }

  .panel{
    margin-bottom:15px;
  }

  .panel-heading{
    padding:10px;
    font-size:13px;
  }

  .panel-body{
    padding:10px;
  }

  .page{
    padding:80px 12px 20px 12px;
  }
}
</style>

<div class="row">
<div class="col-md-12">
<h2>Super Admin Dashboard</h2>
<p style="color:#888;">Manage Organizations, Centers and Users</p>
</div>
</div>

<!-- ================= STATS ================= -->
<div class="row">

<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
<div class="dashboard-card bg-org">
<h3><i class="fa fa-building"></i> <?php echo $org_total; ?></h3>
<p>Total Organizations</p>
</div>
</div>

<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
<div class="dashboard-card bg-center">
<h3><i class="fa fa-map-marker"></i> <?php echo $center_total; ?></h3>
<p>Total Centers</p>
</div>
</div>

<div class="col-lg-4 col-md-4 col-sm-12 col-xs-12">
<div class="dashboard-card bg-user">
<h3><i class="fa fa-users"></i> <?php echo $user_total; ?></h3>
<p>Total Users</p>
</div>
</div>

</div>

<br>

<!-- ================= ACTION BUTTONS ================= -->
<div class="row">

<div class="col-md-4">
<a href="master_organization.php" class="btn btn-primary btn-block">
<i class="fa fa-plus"></i> Create Organization
</a>
</div>

<div class="col-md-4">
<a href="master_center.php" class="btn btn-success btn-block">
<i class="fa fa-plus"></i> Create Center
</a>
</div>

<div class="col-md-4">
<a href="user_credentials.php" class="btn btn-warning btn-block">
<i class="fa fa-plus"></i> Create User
</a>
</div>

</div>

<br>

<!-- ================= RECENT DATA ================= -->
<div class="row">

<!-- ORG -->
<div class="col-md-4">
<div class="panel panel-default">
<div class="panel-heading"><strong>Recent Organizations</strong></div>
<div class="panel-body">
<ul class="list-group">
<?php foreach($recent_orgs as $o): ?>
<li class="list-group-item"><?php echo $o['org_name']; ?></li>
<?php endforeach; ?>
</ul>
</div>
</div>
</div>

<!-- CENTER -->
<div class="col-md-4">
<div class="panel panel-default">
<div class="panel-heading"><strong>Recent Centers</strong></div>
<div class="panel-body">
<ul class="list-group">
<?php foreach($recent_centers as $c): ?>
<li class="list-group-item">
<?php echo $c['center_name']; ?>
<small>(<?php echo $c['org_name']; ?>)</small>
</li>
<?php endforeach; ?>
</ul>
</div>
</div>
</div>

<!-- USER -->
<div class="col-md-4">
<div class="panel panel-default">
<div class="panel-heading"><strong>Recent Users</strong></div>
<div class="panel-body">
<ul class="list-group">
<?php foreach($recent_users as $u): ?>
<li class="list-group-item">
<?php echo $u['username']; ?>
<small>(<?php echo $u['org_name']; ?> - <?php echo $u['center_name']; ?>)</small>
</li>
<?php endforeach; ?>
</ul>
</div>
</div>
</div>

</div>

<?php include_once('layouts/footer.php'); ?>
